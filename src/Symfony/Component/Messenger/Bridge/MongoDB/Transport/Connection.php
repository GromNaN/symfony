<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDB\Transport;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Driver\Result as DriverResult;
use MongoDB\Exception\Exception as MongoDBException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Synchronizer\SchemaSynchronizer;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use MongoDB\Client;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Connection implements ResetInterface
{
    protected const TABLE_OPTION_NAME = '_symfony_messenger_collection_name';

    protected const DEFAULT_OPTIONS = [
        'collection_name' => 'messenger_messages',
        'queue_name' => 'default',
        'redeliver_timeout' => 3600,
        'auto_setup' => true,
    ];

    /**
     * Configuration of the connection.
     *
     * Available options:
     *
     * * collection_name: name of the table
     * * connection: name of the Doctrine's entity manager
     * * queue_name: name of the queue
     * * redeliver_timeout: Timeout before redeliver messages still in handling state (i.e: delivered_at is not null and message is still in table). Default: 3600
     * * auto_setup: Whether the table should be created automatically during send / get. Default: true
     */
    protected $configuration = [];
    protected $driverConnection;
    protected $queueEmptiedAt;
    private bool $autoSetup;

    private Client $mongodb;

    public function __construct(array $configuration, Client $mongodb = null)
    {
        $this->configuration = array_replace_recursive(static::DEFAULT_OPTIONS, $configuration);
        $this->mongodb = $mongodb;
        $this->autoSetup = $this->configuration['auto_setup'];
    }

    public function reset()
    {
        $this->queueEmptiedAt = null;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public static function buildConfiguration(#[\SensitiveParameter] string $dsn, array $options = []): array
    {
        if (false === $components = parse_url($dsn)) {
            throw new InvalidArgumentException('The given Doctrine Messenger DSN is invalid.');
        }

        $query = [];
        if (isset($components['query'])) {
            parse_str($components['query'], $query);
        }

        $configuration = ['connection' => $components['host']];
        $configuration += $query + $options + static::DEFAULT_OPTIONS;

        $configuration['auto_setup'] = filter_var($configuration['auto_setup'], \FILTER_VALIDATE_BOOL);

        // check for extra keys in options
        $optionsExtraKeys = array_diff(array_keys($options), array_keys(static::DEFAULT_OPTIONS));
        if (0 < \count($optionsExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found: [%s]. Allowed options are [%s].', implode(', ', $optionsExtraKeys), implode(', ', array_keys(static::DEFAULT_OPTIONS))));
        }

        // check for extra keys in options
        $queryExtraKeys = array_diff(array_keys($query), array_keys(static::DEFAULT_OPTIONS));
        if (0 < \count($queryExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found in DSN: [%s]. Allowed options are [%s].', implode(', ', $queryExtraKeys), implode(', ', array_keys(static::DEFAULT_OPTIONS))));
        }

        return $configuration;
    }

    /**
     * @param int $delay The delay in milliseconds
     *
     * @return string The inserted id
     *
     * @throws MongoDBException
     */
    public function send(string $body, array $headers, int $delay = 0): string
    {
        $now = new \DateTimeImmutable();
        $availableAt = $now->modify(sprintf('+%d seconds', $delay / 1000));

        $insert = $this->mongodb
            ->dbname
            ->selectCollection($this->configuration['collection_name'])
            ->insertOne([
                'body' => $body,
                'headers' => $headers,
                'queue_name' => $this->configuration['queue_name'],
                'created_at' => $now,
                'available_at' => $availableAt,
            ]);

        return (string) $insert->getInsertedId();
    }

    public function get(): ?array
    {
        if ($this->driverConnection->getDatabasePlatform() instanceof MySQLPlatform) {
            try {
                $this->driverConnection->delete($this->configuration['collection_name'], ['delivered_at' => '9999-12-31 23:59:59']);
            } catch (DriverException $e) {
                // Ignore the exception
            }
        }

        get:
        $this->driverConnection->beginTransaction();
        try {
            $query = $this->createAvailableMessagesQuery()
                ->orderBy('available_at', 'ASC')
                ->setMaxResults(1);

            // Append pessimistic write lock to FROM clause if db platform supports it
            $sql = $query->getSQL();
            if (($fromPart = $query->getQueryPart('from')) &&
                ($table = $fromPart[0]['table'] ?? null) &&
                ($alias = $fromPart[0]['alias'] ?? null)
            ) {
                $fromClause = sprintf('%s %s', $table, $alias);
                $sql = str_replace(
                    sprintf('FROM %s WHERE', $fromClause),
                    sprintf('FROM %s WHERE', $this->driverConnection->getDatabasePlatform()->appendLockHint($fromClause, LockMode::PESSIMISTIC_WRITE)),
                    $sql
                );
            }

            // Wrap the rownum query in a sub-query to allow writelocks without ORA-02014 error
            if ($this->driverConnection->getDatabasePlatform() instanceof OraclePlatform) {
                $sql = str_replace('SELECT a.* FROM', 'SELECT a.id FROM', $sql);

                $wrappedQuery = $this->driverConnection->createQueryBuilder()
                    ->select(
                        'w.id AS "id", w.body AS "body", w.headers AS "headers", w.queue_name AS "queue_name", '.
                        'w.created_at AS "created_at", w.available_at AS "available_at", '.
                        'w.delivered_at AS "delivered_at"'
                    )
                    ->from($this->configuration['collection_name'], 'w')
                    ->where('w.id IN('.$sql.')');

                $sql = $wrappedQuery->getSQL();
            }

            // use SELECT ... FOR UPDATE to lock table
            $stmt = $this->executeQuery(
                $sql.' '.$this->driverConnection->getDatabasePlatform()->getWriteLockSQL(),
                $query->getParameters(),
                $query->getParameterTypes()
            );
            $doctrineEnvelope = $stmt instanceof Result || $stmt instanceof DriverResult ? $stmt->fetchAssociative() : $stmt->fetch();

            if (false === $doctrineEnvelope) {
                $this->driverConnection->commit();
                $this->queueEmptiedAt = microtime(true) * 1000;

                return null;
            }
            // Postgres can "group" notifications having the same channel and payload
            // We need to be sure to empty the queue before blocking again
            $this->queueEmptiedAt = null;

            $doctrineEnvelope = $this->decodeEnvelopeHeaders($doctrineEnvelope);

            $queryBuilder = $this->driverConnection->createQueryBuilder()
                ->update($this->configuration['collection_name'])
                ->set('delivered_at', '?')
                ->where('id = ?');
            $now = new \DateTimeImmutable();
            $this->executeStatement($queryBuilder->getSQL(), [
                $now,
                $doctrineEnvelope['id'],
            ], [
                Types::DATETIME_MUTABLE,
            ]);

            $this->driverConnection->commit();

            return $doctrineEnvelope;
        } catch (\Throwable $e) {
            $this->driverConnection->rollBack();

            if ($this->autoSetup && $e instanceof TableNotFoundException) {
                $this->setup();
                goto get;
            }

            throw $e;
        }
    }

    public function ack(string $id): bool
    {
        try {
            return $this
                ->mongodb
                ->dbname
                ->selectCollection($this->configuration['collection_name'])
                ->deleteOne(['id' => $id])
                ->getDeletedCount() > 0;
        } catch (MongoDBException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function reject(string $id): bool
    {
        return $this->ack($id);
    }

    public function setup(): void
    {
        $configuration = $this->driverConnection->getConfiguration();
        $assetFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter(null);
        $this->updateSchema();
        $configuration->setSchemaAssetsFilter($assetFilter);
        $this->autoSetup = false;
    }

    public function getMessageCount(): int
    {
        $this->mongodb
            ->dbname
            ->selectCollection($this->configuration['collection_name'])
            ->countDocuments()
        $queryBuilder = $this->createAvailableMessagesQuery()
            ->select('COUNT(m.id) as message_count')
            ->setMaxResults(1);

        $stmt = $this->executeQuery($queryBuilder->getSQL(), $queryBuilder->getParameters(), $queryBuilder->getParameterTypes());

        return $stmt instanceof Result || $stmt instanceof DriverResult ? $stmt->fetchOne() : $stmt->fetchColumn();
    }

    public function findAll(int $limit = null): array
    {
        $query = $this->createAvailableMessagesQuery();
        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        $data = $this->mongodb
            ->dbname
            ->selectCollection($this->configuration['collection_name'])
            ->find($query, [
                '$order' => ['']
            ]);

        return array_map(fn ($doctrineEnvelope) => $this->decodeEnvelopeHeaders($doctrineEnvelope), $data);
    }

    public function find(mixed $id): ?array
    {
        $data = $this->mongodb
            ->dbname
            ->selectCollection($this->configuration['collection_name'])
            ->findOne([
                'id' => $id,
                'queue_name' => $this->configuration['queue_name']
            ]);

        return null === $data ? null : $this->decodeEnvelopeHeaders($data);
    }

    /**
     * @internal
     */
    public function configureSchema(Schema $schema, DBALConnection $forConnection, \Closure $isSameDatabase): void
    {
        if ($schema->hasTable($this->configuration['collection_name'])) {
            return;
        }

        if ($forConnection !== $this->driverConnection && !$isSameDatabase($this->executeStatement(...))) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    private function createAvailableMessagesQuery(): array
    {
        $now = new \DateTimeImmutable();
        $redeliverLimit = $now->modify(sprintf('-%d seconds', $this->configuration['redeliver_timeout']));

        return [
            '$or' => [
                ['delivered_at' => ['$lt' => $redeliverLimit]],
                ['delivered_at' => null],
            ],
            'available_at' => ['$lte' => $now],
            'queue_name' => $this->configuration['queue_name']
        ];
    }

    private function executeQuery(string $sql, array $parameters = [], array $types = [])
    {
        try {
            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        } catch (TableNotFoundException $e) {
            if ($this->driverConnection->isTransactionActive()) {
                throw $e;
            }

            // create table
            if ($this->autoSetup) {
                $this->setup();
            }
            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        }

        return $stmt;
    }

    protected function executeStatement(string $sql, array $parameters = [], array $types = [])
    {
        try {
            if (method_exists($this->driverConnection, 'executeStatement')) {
                $stmt = $this->driverConnection->executeStatement($sql, $parameters, $types);
            } else {
                $stmt = $this->driverConnection->executeUpdate($sql, $parameters, $types);
            }
        } catch (TableNotFoundException $e) {
            if ($this->driverConnection->isTransactionActive()) {
                throw $e;
            }

            // create table
            if ($this->autoSetup) {
                $this->setup();
            }
            if (method_exists($this->driverConnection, 'executeStatement')) {
                $stmt = $this->driverConnection->executeStatement($sql, $parameters, $types);
            } else {
                $stmt = $this->driverConnection->executeUpdate($sql, $parameters, $types);
            }
        }

        return $stmt;
    }

    private function getSchema(): Schema
    {
        $schema = new Schema([], [], $this->createSchemaManager()->createSchemaConfig());
        $this->addTableToSchema($schema);

        return $schema;
    }

    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->configuration['collection_name']);
        // add an internal option to mark that we created this & the non-namespaced table name
        $table->addOption(self::TABLE_OPTION_NAME, $this->configuration['collection_name']);
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('body', Types::TEXT)
            ->setNotnull(true);
        $table->addColumn('headers', Types::TEXT)
            ->setNotnull(true);
        $table->addColumn('queue_name', Types::STRING)
            ->setLength(190) // MySQL 5.6 only supports 191 characters on an indexed column in utf8mb4 mode
            ->setNotnull(true);
        $table->addColumn('created_at', Types::DATETIME_MUTABLE)
            ->setNotnull(true);
        $table->addColumn('available_at', Types::DATETIME_MUTABLE)
            ->setNotnull(true);
        $table->addColumn('delivered_at', Types::DATETIME_MUTABLE)
            ->setNotnull(false);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['queue_name']);
        $table->addIndex(['available_at']);
        $table->addIndex(['delivered_at']);
    }

    private function decodeEnvelopeHeaders(array $doctrineEnvelope): array
    {
        $doctrineEnvelope['headers'] = json_decode($doctrineEnvelope['headers'], true);

        return $doctrineEnvelope;
    }

    private function updateSchema(): void
    {
        if (null !== $this->schemaSynchronizer) {
            $this->schemaSynchronizer->updateSchema($this->getSchema(), true);

            return;
        }

        $schemaManager = $this->createSchemaManager();
        $comparator = $this->createComparator($schemaManager);
        $schemaDiff = $this->compareSchemas($comparator, $schemaManager->createSchema(), $this->getSchema());

        foreach ($schemaDiff->toSaveSql($this->driverConnection->getDatabasePlatform()) as $sql) {
            if (method_exists($this->driverConnection, 'executeStatement')) {
                $this->driverConnection->executeStatement($sql);
            } else {
                $this->driverConnection->exec($sql);
            }
        }
    }

    private function createSchemaManager(): AbstractSchemaManager
    {
        return method_exists($this->driverConnection, 'createSchemaManager')
            ? $this->driverConnection->createSchemaManager()
            : $this->driverConnection->getSchemaManager();
    }

    private function createComparator(AbstractSchemaManager $schemaManager): Comparator
    {
        return method_exists($schemaManager, 'createComparator')
            ? $schemaManager->createComparator()
            : new Comparator();
    }

    private function compareSchemas(Comparator $comparator, Schema $from, Schema $to): SchemaDiff
    {
        return method_exists($comparator, 'compareSchemas')
            ? $comparator->compareSchemas($from, $to)
            : $comparator->compare($from, $to);
    }
}
