<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MongoDbSessionHandler;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 *
 * @group time-sensitive
 * @group integration
 *
 * @requires extension mongodb
 */
class MongoDbSessionHandlerTest extends TestCase
{
    public array $options;
    private Manager $manager;
    private MockClock $clock;
    private MongoDbSessionHandler $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new Manager('mongodb://'.getenv('MONGODB_HOST'));

        try {
            $this->manager->executeCommand('sf-test', new Command(['ping' => 1]));
        } catch (ConnectionException $e) {
            $this->markTestSkipped(sprintf('MongoDB Server "%s" not running: %s', getenv('MONGODB_HOST'), $e->getMessage()));
        }

        $this->options = [
            'id_field' => '_id',
            'data_field' => 'data',
            'time_field' => 'time',
            'expiry_field' => 'expires_at',
            'database' => 'sf-test',
            'collection' => 'session-test',
        ];

        $this->storage = new MongoDbSessionHandler($this->manager, $this->options);
    }

    public function testCreateFromClient(): void
    {
        if (!class_exists(Client::class)) {
            $this->markTestSkipped('The "mongodb/mongodb" library is required.');
        }

        $client = new Client('mongodb://'.getenv('MONGODB_HOST'));

        $this->storage = new MongoDbSessionHandler($client, $this->options);
        $this->storage->write('foo', 'bar');

        $this->assertSame(1, $client->selectCollection('sf-test', 'session-test')->countDocuments());
    }

    protected function tearDown(): void
    {
        $this->manager->executeCommand('sf-test', new Command(['drop' => 'session-test']));
    }

    /** @dataProvider provideInvalidOptions */
    public function testConstructorShouldThrowExceptionForMissingOptions(array $options): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MongoDbSessionHandler($this->manager, $options);
    }

    public function provideInvalidOptions()
    {
        yield 'empty' => [[]];
        yield 'collection missing' => [['database' => 'foo']];
        yield 'database missing' => [['collection' => 'foo']];
    }

    public function testOpenMethodAlwaysReturnTrue(): void
    {
        $this->assertTrue($this->storage->open('test', 'test'), 'The "open" method should always return true');
    }

    public function testCloseMethodAlwaysReturnTrue(): void
    {
        $this->assertTrue($this->storage->close(), 'The "close" method should always return true');
    }

    public function testRead(): void
    {
        $this->insertSession('foo', 'bar', 0);
        $this->assertEquals('bar', $this->storage->read('foo'));
    }

    public function testReadNotFound(): void
    {
        $this->insertSession('foo', 'bar', 0);
        $this->assertEquals('', $this->storage->read('foobar'));
    }

    public function testReadExpired(): void
    {
        $this->insertSession('foo', 'bar', -100_000);
        $this->assertEquals('', $this->storage->read('foo'));
    }

    public function testWrite(): void
    {
        $expectedTime = (new \DateTimeImmutable())->getTimestamp();
        $expectedExpiry = $expectedTime + (int) \ini_get('session.gc_maxlifetime');
        $this->assertTrue($this->storage->write('foo', 'bar'));

        $sessions = $this->getSessions();
        $this->assertCount(1, $sessions);
        $this->assertEquals('foo', $sessions[0]->_id);
        $this->assertInstanceOf(Binary::class, $sessions[0]->data);
        $this->assertEquals('bar', $sessions[0]->data->getData());
        $this->assertInstanceOf(UTCDateTime::class, $sessions[0]->time);
        $this->assertGreaterThanOrEqual($expectedTime, round((string) $sessions[0]->time / 1000));
        $this->assertInstanceOf(UTCDateTime::class, $sessions[0]->expires_at);
        $this->assertGreaterThanOrEqual($expectedExpiry, round((string) $sessions[0]->expires_at / 1000));
    }

    public function testReplaceSessionData(): void
    {
        $this->storage->write('foo', 'bar');
        $this->storage->write('baz', 'qux');
        $this->storage->write('foo', 'foobar');

        $sessions = $this->getSessions();
        $this->assertCount(2, $sessions);
        $this->assertEquals('foobar', $sessions[0]->data->getData());
    }

    public function testDestroy(): void
    {
        $this->storage->write('foo', 'bar');
        $this->storage->write('baz', 'qux');

        $this->assertTrue($this->storage->destroy('foo'));

        $sessions = $this->getSessions();
        $this->assertCount(1, $sessions);
        $this->assertEquals('baz', $sessions[0]->_id);
    }

    public function testGc(): void
    {
        $this->insertSession('foo', 'bar', -100_000);
        $this->insertSession('bar', 'bar', -100_000);
        $this->insertSession('qux', 'bar', -300);
        $this->insertSession('baz', 'bar', 0);

        $this->assertSame(2, $this->storage->gc(1));
        $this->assertCount(2, $this->getSessions());
    }

    /**
     * @return list<object{_id:string,data:Binary,time:UTCDateTime,expires_at:UTCDateTime>
     */
    private function getSessions(): array
    {
        return $this->manager->executeQuery('sf-test.session-test', new Query([]))->toArray();
    }

    private function insertSession(string $sessionId, string $data, int $timeDiff): void
    {
        $timestamp = (new \DateTimeImmutable())->getTimestamp() + $timeDiff;

        $write = new BulkWrite();
        $write->insert([
            '_id' => $sessionId,
            'data' => new Binary($data, Binary::TYPE_OLD_BINARY),
            'time' => new UTCDateTime($timestamp * 1000),
            'expires_at' => new UTCDateTime(($timestamp + (int) \ini_get('session.gc_maxlifetime')) * 1000),
        ]);

        $this->manager->executeBulkWrite('sf-test.session-test', $write);
    }
}
