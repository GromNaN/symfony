<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Transport;

use MongoDB\BSON\Document;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Exception\Exception as MongoDriverException;
use MongoDB\Driver\Session;
use MongoDB\Driver\WriteConcern;
use MongoDB\Model\BSONDocument;
use MongoDB\Operation\FindOneAndUpdate;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;

/**
 * @internal
 *
 * @author Alessandro Lai <alessandro.lai85@gmail.com>
 */
class Connection
{
    /**
     * Content type of a JSON body, as set by the Messenger serializer. Such a
     * body is stored as a native BSON sub-document.
     */
    public const CONTENT_TYPE_JSON = 'application/json';

    private const DEFAULT_OPTIONS = [
        'database' => null,
        'collection_name' => 'messenger_messages',
        'queue_name' => 'default',
        'redeliver_timeout' => 3600,
    ];

    private string $uniqueId;

    public function __construct(
        private readonly Collection $collection,
        private readonly string $queueName = 'default',
        private readonly int $redeliverTimeout = 3600,
        private readonly ?ClockInterface $clock = null,
    ) {
        $this->uniqueId = uniqid('consumer_', true);
    }

    public static function fromDsn(#[\SensitiveParameter] string $dsn, array $options = [], ?Client $client = null, ?ClockInterface $clock = null): self
    {
        [$configuration, $uri] = self::buildConfiguration($dsn, $options);

        $client ??= new Client($uri);
        $collection = $client->getCollection($configuration['database'], $configuration['collection_name']);

        return new self($collection, $configuration['queue_name'], $configuration['redeliver_timeout'], $clock);
    }

    /**
     * Extracts the transport configuration from the DSN and the options, and
     * returns it along with the DSN stripped from the transport-specific
     * query parameters, ready to be passed to the MongoDB client.
     *
     * The database is read from the DSN path, the other settings from the
     * DSN query string or from the options, the latter taking precedence.
     * Query parameters that are not transport settings are kept and passed
     * to the MongoDB driver.
     *
     * @return array{0: array{database: string, collection_name: string, queue_name: string, redeliver_timeout: int}, 1: string}
     */
    public static function buildConfiguration(#[\SensitiveParameter] string $dsn, array $options = []): array
    {
        if (!str_starts_with($dsn, 'mongodb://') && !str_starts_with($dsn, 'mongodb+srv://')) {
            throw new InvalidArgumentException('The given MongoDB Messenger DSN is invalid. Expecting "mongodb://" or "mongodb+srv://".');
        }

        if (false === $components = parse_url($dsn)) {
            throw new InvalidArgumentException('The given MongoDB Messenger DSN is invalid.');
        }

        $query = [];
        if (isset($components['query'])) {
            parse_str($components['query'], $query);
        }

        if ($invalidOptions = array_diff(array_keys($options), array_keys(self::DEFAULT_OPTIONS))) {
            throw new InvalidArgumentException(\sprintf('Unknown option found: [%s]. Allowed options are [%s].', implode(', ', $invalidOptions), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        $configuration = $options + array_intersect_key($query, self::DEFAULT_OPTIONS) + self::DEFAULT_OPTIONS;
        $configuration['database'] ??= ltrim($components['path'] ?? '', '/') ?: null;

        if (null === $configuration['database']) {
            throw new InvalidArgumentException('The MongoDB Messenger transport requires a "database", provide it in the DSN path or as an option.');
        }

        if (!is_numeric($configuration['redeliver_timeout'])) {
            throw new InvalidArgumentException(\sprintf('The "redeliver_timeout" option must be an integer, "%s" given.', get_debug_type($configuration['redeliver_timeout'])));
        }
        $configuration['redeliver_timeout'] = (int) $configuration['redeliver_timeout'];

        foreach (array_keys(self::DEFAULT_OPTIONS) as $option) {
            $dsn = self::removeUriOption($dsn, $option);
        }

        return [$configuration, $dsn];
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * @throws TransportException
     */
    public function get(): ?BSONDocument
    {
        $options = $this->getWriteOptions();
        $options['returnDocument'] = FindOneAndUpdate::RETURN_DOCUMENT_AFTER;
        $options['sort'] = [
            'availableAt' => 1,
        ];
        $options = $this->setTypeMapOption($options);

        $updateStatement = [
            '$set' => [
                'deliveredTo' => $this->uniqueId,
                'deliveredAt' => new UTCDateTime($this->now()),
            ],
        ];

        try {
            $updatedDocument = $this->collection->findOneAndUpdate($this->createAvailableMessagesQuery(), $updateStatement, $options);
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        if (!$updatedDocument instanceof BSONDocument) {
            return null;
        }

        if ($updatedDocument['deliveredTo'] !== $this->uniqueId) {
            // concurrency issue - some other consumer got to this message while we were updating it
            return null;
        }

        return $updatedDocument;
    }

    /**
     * @param array<string, string> $headers
     * @param int                   $delay   The delay in milliseconds
     *
     * @return ObjectId The inserted id
     *
     * @throws TransportException
     */
    public function send(string $body, array $headers = [], int $delay = 0, ?Session $session = null): ObjectId
    {
        $now = $this->now();
        $availableAt = $now->modify(\sprintf('+%d milliseconds', $delay));

        $document = new BSONDocument();

        $document['body'] = self::parseJsonBody($body, $headers) ?? $body;
        $document['headers'] = new BSONDocument($headers);
        $document['queueName'] = $this->queueName;
        $document['createdAt'] = new UTCDateTime($now);
        $document['availableAt'] = new UTCDateTime($availableAt);

        try {
            $insertResult = $this->collection->insertOne($document, $this->getWriteOptions($session));
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $insertResult->getInsertedId();
    }

    /**
     * @param string $id The ID of the message to ack; the corresponding document will be removed from the collection
     *
     * @return bool Returns true if the document has been deleted
     *
     * @throws TransportException
     */
    public function ack(string $id): bool
    {
        try {
            $deleteResult = $this->collection->deleteOne(['_id' => new ObjectId($id)], $this->getWriteOptions());
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $deleteResult->getDeletedCount() > 0;
    }

    /**
     * @param string $id The ID of the message to reject; the corresponding document will be removed from the collection
     *
     * @return bool Returns true if the document has been deleted
     *
     * @throws TransportException
     */
    public function reject(string $id): bool
    {
        try {
            $deleteResult = $this->collection->deleteOne(['_id' => new ObjectId($id)], $this->getWriteOptions());
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $deleteResult->getDeletedCount() > 0;
    }

    /**
     * @throws TransportException
     */
    public function getMessageCount(): int
    {
        try {
            return $this->collection->countDocuments($this->createAvailableMessagesQuery());
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @throws TransportException
     */
    public function find(string $id): ?BSONDocument
    {
        try {
            $document = $this->collection->findOne(['_id' => new ObjectId($id)], $this->setTypeMapOption());
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $document instanceof BSONDocument ? $document : null;
    }

    /**
     * @return iterable<BSONDocument>
     *
     * @throws TransportException
     */
    public function findAll(?int $limit = null): iterable
    {
        $options = [];
        if (null !== $limit) {
            $options['limit'] = $limit;
        }

        try {
            return $this->collection->find($this->createAvailableMessagesQuery(), $this->setTypeMapOption($options));
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function deleteAll(): void
    {
        try {
            $this->collection->deleteMany(['queueName' => $this->queueName]);
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Creates a compound index including the queueName, availableAt and
     * deliveredAt fields, to speed up the polling query.
     */
    public function setup(): void
    {
        try {
            $this->collection->createIndex([
                'availableAt' => 1,
                'queueName' => 1,
                'deliveredAt' => 1,
            ]);
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function createAvailableMessagesQuery(): array
    {
        $now = $this->now();
        $redeliverLimit = $now->modify(\sprintf('-%d seconds', $this->redeliverTimeout));

        return [
            '$or' => [
                ['deliveredAt' => null],
                ['deliveredAt' => [
                    '$lt' => new UTCDateTime($redeliverLimit),
                ]],
            ],
            'availableAt' => ['$lte' => new UTCDateTime($now)],
            'queueName' => $this->queueName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getWriteOptions(?Session $session = null): array
    {
        if (null === $session) {
            return ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY)];
        }

        if ($session->isInTransaction()) {
            return ['session' => $session];
        }

        return ['session' => $session, 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY)];
    }

    /**
     * A JSON body is stored as a native BSON sub-document, so the message is
     * queryable from the database instead of being an opaque string. The
     * "Content-Type" header, stored along with the message, tells the receiver
     * how to read it back.
     *
     * Returns null when the body is not a JSON object, in which case it is
     * stored as a string.
     *
     * @param array<string, string> $headers
     */
    private static function parseJsonBody(string $body, array $headers): ?Document
    {
        if (self::CONTENT_TYPE_JSON !== ($headers['Content-Type'] ?? null) || !str_starts_with(ltrim($body), '{')) {
            return null;
        }

        try {
            return Document::fromJSON($body);
        } catch (MongoDriverException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $readOptions
     *
     * @return array<string, mixed>
     */
    private function setTypeMapOption(array $readOptions = []): array
    {
        $readOptions['typeMap'] = [
            'root' => BSONDocument::class,
            // A body stored as a sub-document is read back as raw BSON, so the
            // receiver can turn it into the JSON string the serializer expects.
            'fieldPaths' => ['body' => 'bson'],
        ];

        return $readOptions;
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock?->now() ?? new \DateTimeImmutable();
    }

    private static function removeUriOption(string $uri, string $option): string
    {
        if (preg_match('/^(.*[?&])'.$option.'=[^&#]*&?(([^#]*).*)$/', $uri, $matches)) {
            $prefix = $matches[1];
            if ('' === $matches[3]) {
                $prefix = substr($prefix, 0, -1);
            }
            $uri = $prefix.$matches[2];
        }

        return $uri;
    }
}
