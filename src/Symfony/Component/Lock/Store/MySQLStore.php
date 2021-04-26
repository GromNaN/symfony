<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Lock\BlockingSharedLockStoreInterface;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * MySQLStore is a PersistingStoreInterface implementation using
 * MySQL user-level lock functions.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class MySQLStore implements BlockingSharedLockStoreInterface, BlockingStoreInterface
{
    private $conn;
    private $dsn;
    private $username = '';
    private $password = '';
    private $connectionOptions = [];
    private static $storeRegistry = [];

    /**
     * You can either pass an existing database connection as PDO instance or
     * a Doctrine DBAL Connection or a DSN string that will be used to
     * lazy-connect to the database when the lock is actually used.
     *
     * List of available options:
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: []]
     *
     * @param \PDO|Connection|string $connOrDsn A \PDO or Connection instance or DSN string or null
     * @param array                  $options   An associative array of options
     *
     * @throws InvalidArgumentException When first argument is not PDO nor Connection nor string
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When namespace contains invalid characters
     */
    public function __construct($connOrDsn, array $options = [])
    {
        if ($connOrDsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $connOrDsn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)).', __METHOD__));
            }

            $this->conn = $connOrDsn;
            $this->checkDriver();
        } elseif ($connOrDsn instanceof Connection) {
            $this->conn = $connOrDsn;
            $this->checkDriver();
        } elseif (\is_string($connOrDsn)) {
            $this->dsn = $connOrDsn;
        } else {
            throw new InvalidArgumentException(sprintf('"%s" requires PDO or Doctrine\DBAL\Connection instance or DSN string as first argument, "%s" given.', __CLASS__, get_debug_type($connOrDsn)));
        }

        $this->username = $options['db_username'] ?? $this->username;
        $this->password = $options['db_password'] ?? $this->password;
        $this->connectionOptions = $options['db_connection_options'] ?? $this->connectionOptions;
    }

    public function save(Key $key)
    {
        // prevent concurrency within the same connection
        $this->getInternalStore()->save($key);

        $sql = 'SELECT IF(IS_USED_LOCK(:key) = CONNECTION_ID(), -1, GET_LOCK(:key, 0))';
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->bindValue(':key', $this->getHashedKey($key));
        $stmt->setFetchMode(\PDO::FETCH_COLUMN, 0);
        $result = $stmt->execute();

        // Check if lock is acquired
        if ('1' === $stmt->fetchColumn()) {
            $key->markUnserializable();

            return;
        }

        throw new LockConflictedException();
    }

    public function saveRead(Key $key)
    {
        // prevent concurrency within the same connection
        $this->getInternalStore()->saveRead($key);

        $sql = 'SELECT GET_LOCK(:key, 0)';
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':key', $this->getHashedKey($key));
        $result = $stmt->execute();

        // Check if lock is acquired
        if (true === (\is_object($result) ? $result->fetchOne() : $stmt->fetchColumn())) {
            $key->markUnserializable();
            // release lock in case of demotion
            $this->unlock($key);

            return;
        }

        throw new LockConflictedException();
    }

    public function putOffExpiration(Key $key, float $ttl)
    {
        // postgresql locks forever.
        // check if lock still exists
        if (!$this->exists($key)) {
            throw new LockConflictedException();
        }
    }

    public function delete(Key $key)
    {
        // Prevent deleting locks own by an other key in the same connection
        if (!$this->exists($key)) {
            return;
        }

        $this->unlock($key);

        // Prevent deleting Readlocks own by current key AND an other key in the same connection
        $store = $this->getInternalStore();
        try {
            // If lock acquired = there is no other ReadLock
            $store->save($key);
        } catch (LockConflictedException $e) {
            // an other key exists in this ReadLock
        }

        $store->delete($key);
    }

    public function exists(Key $key)
    {
        $sql = "SELECT IF(IS_USED_LOCK(:key) = CONNECTION_ID(), 1, 0)";
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':key', $this->getHashedKey($key));
        $result = $stmt->execute();

        if ((\is_object($result) ? $result->fetchOne() : $stmt->fetchColumn()) > 0) {
            // connection is locked, check for lock in internal store
            return $this->getInternalStore()->exists($key);
        }

        return false;
    }

    public function waitAndSave(Key $key)
    {
        // prevent concurrency within the same connection
        // Internal store does not allow blocking mode, because there is no way to acquire one in a single process
        $this->getInternalStore()->save($key);

        $sql = 'SELECT pg_advisory_lock(:key)';
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':key', $this->getHashedKey($key));
        $stmt->execute();
    }

    public function waitAndSaveRead(Key $key)
    {
        // prevent concurrency within the same connection
        // Internal store does not allow blocking mode, because there is no way to acquire one in a single process
        $this->getInternalStore()->saveRead($key);

        $sql = 'SELECT pg_advisory_lock_shared(:key)';
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':key', $this->getHashedKey($key));
        $stmt->execute();

        // release lock in case of demotion
        $this->unlock($key);
    }

    /**
     * Hash the key to guarantee it contains between 1 and 64 characters
     */
    private function getHashedKey(Key $key): string
    {
        return hash('sha256', (string) $key);
    }

    private function unlock(Key $key): void
    {
        while (true) {
            $sql = "DO RELEASE_LOCK(:key)"; // @todo checks if lock is owned by the current lock ?
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindValue(':key', $this->getHashedKey($key));
            $result = $stmt->execute();

            if (0 === (\is_object($result) ? $result : $stmt)->rowCount()) {
                break;
            }
        }
    }

    /**
     * @return \PDO|Connection
     */
    private function getConnection(): object
    {
        if (null === $this->conn) {
            if (strpos($this->dsn, '://')) {
                if (!class_exists(DriverManager::class)) {
                    throw new InvalidArgumentException(sprintf('Failed to parse the DSN "%s". Try running "composer require doctrine/dbal".', $this->dsn));
                }
                $this->conn = DriverManager::getConnection(['url' => $this->dsn]);
            } else {
                $this->conn = new \PDO($this->dsn, $this->username, $this->password, $this->connectionOptions);
                $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            }

            $this->checkDriver();
        }

        return $this->conn;
    }

    private function checkDriver(): void
    {
        if ($this->conn instanceof \PDO) {
            if ('mysql' !== $driver = $this->conn->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
                throw new InvalidArgumentException(sprintf('The adapter "%s" does not support the "%s" driver.', __CLASS__, $driver));
            }
        } else {
            $driver = $this->conn->getDriver();

            switch (true) {
                case $driver instanceof \Doctrine\DBAL\Driver\Mysqli\Driver:
                case $driver instanceof \Doctrine\DBAL\Driver\PDOMySql\Driver:
                case $driver instanceof \Doctrine\DBAL\Driver\PDO\MySQL\Driver:
                    break;
                default:
                    throw new InvalidArgumentException(sprintf('The adapter "%s" does not support the "%s" driver.', __CLASS__, \get_class($driver)));
            }
        }
    }

    private function getInternalStore(): PersistingStoreInterface
    {
        $namespace = spl_object_hash($this->getConnection());

        return self::$storeRegistry[$namespace] ?? self::$storeRegistry[$namespace] = new InMemoryStore();
    }
}
