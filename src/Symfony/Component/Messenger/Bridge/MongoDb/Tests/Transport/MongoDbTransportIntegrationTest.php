<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Tests\Transport;

use MongoDB\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\MongoDb\Stamp\MongoDbSessionStamp;
use Symfony\Component\Messenger\Bridge\MongoDb\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\Connection;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\MongoDbTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

#[RequiresPhpExtension('mongodb')]
#[Group('integration')]
class MongoDbTransportIntegrationTest extends TestCase
{
    private const DATABASE = 'messenger_tests';

    private Client $client;
    private Connection $connection;
    private MongoDbTransport $transport;
    private bool $isReplicaSet = false;

    /** Cached across tests: the readiness probe and the replica set detection run once per process. */
    private static ?bool $serverReachable = null;
    private static bool $replicaSet = false;

    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            $this->markTestSkipped('The "mongodb/mongodb" package is required.');
        }

        $clientClass = new \ReflectionClass(Client::class);
        if ($clientClass->isAbstract()) {
            self::fail(\sprintf('MongoDB\Client is shadowed by the test stub "%s".', $clientClass->getFileName()));
        }

        $this->client = new Client(getenv('MONGODB_URI') ?: 'mongodb://localhost:27017', ['serverSelectionTimeoutMS' => 3000]);

        if (null === self::$serverReachable) {
            // the replica set service can take a few seconds to become ready in
            // CI: retry the ping, then remember the outcome for the whole class
            $probeClient = new Client(getenv('MONGODB_URI') ?: 'mongodb://localhost:27017', ['serverSelectionTimeoutMS' => 1000]);
            $lastThrowable = null;

            for ($attempt = 0; $attempt < 15; ++$attempt) {
                try {
                    $probeClient->getDatabase(self::DATABASE)->command(['ping' => 1]);
                    self::$serverReachable = true;
                    $lastThrowable = null;
                    break;
                } catch (\Throwable $throwable) {
                    $lastThrowable = $throwable;
                    sleep(1);
                }
            }

            if (null !== $lastThrowable) {
                self::$serverReachable = false;
            } else {
                $hello = $this->client->getDatabase('admin')->command(['hello' => 1])->toArray()[0];
                self::$replicaSet = isset($hello['setName']) || isset($hello->setName);
            }
        }

        $this->isReplicaSet = self::$replicaSet;

        if (!self::$serverReachable) {
            $this->markTestSkipped('MongoDB server not found.');
        }

        $hello = $this->client->getDatabase('admin')->command(['hello' => 1])->toArray()[0];
        $this->isReplicaSet = isset($hello['setName']) || isset($hello->setName);

        // the existing tests assert polling semantics: disable the change stream
        // to keep them fast and deterministic (the stream path is covered by
        // dedicated tests using explicitly stream-enabled connections)
        $this->connection = Connection::fromDsn('mongodb://localhost/'.self::DATABASE, ['wait_time' => 0], $this->client);
        $this->connection->deleteAll();
        $this->transport = new MongoDbTransport($this->connection, new PhpSerializer());
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->deleteAll();
        }
    }

    public function testSendGetAckRoundtrip()
    {
        $sentEnvelope = $this->transport->send(new Envelope(new DummyMessage('Hi')));
        $this->assertNotNull($sentEnvelope->last(TransportMessageIdStamp::class));
        $this->assertSame(1, $this->transport->getMessageCount());

        $envelopes = $this->transport->get();
        $this->assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        $this->assertInstanceOf(DummyMessage::class, $message);
        $this->assertSame('Hi', $message->getMessage());

        // the message is locked for other consumers while it is handled
        $this->assertSame([], $this->transport->get());

        $this->transport->ack($envelopes[0]);
        $this->assertSame(0, $this->transport->getMessageCount());
    }

    public function testReject()
    {
        $this->transport->send(new Envelope(new DummyMessage('Hi')));

        $envelopes = $this->transport->get();
        $this->assertCount(1, $envelopes);

        $this->transport->reject($envelopes[0]);
        $this->assertSame(0, $this->transport->getMessageCount());
    }

    public function testSendWithDelay()
    {
        $this->transport->send(new Envelope(new DummyMessage('Later'), [new DelayStamp(60000)]));

        $this->assertSame(0, $this->transport->getMessageCount());
        $this->assertSame([], $this->transport->get());
    }

    public function testMessageIsRedeliveredAfterTheRedeliverTimeout()
    {
        $this->transport->send(new Envelope(new DummyMessage('Hi')));

        $this->assertCount(1, $this->transport->get());
        $this->assertSame([], $this->transport->get());

        $impatientConnection = Connection::fromDsn('mongodb://localhost/'.self::DATABASE, ['redeliver_timeout' => 0], $this->client);
        $impatientTransport = new MongoDbTransport($impatientConnection, new PhpSerializer());

        usleep(2000);
        $this->assertCount(1, $impatientTransport->get());
    }

    public function testAllAndFind()
    {
        $this->transport->send(new Envelope(new DummyMessage('First')));
        $sentEnvelope = $this->transport->send(new Envelope(new DummyMessage('Second')));

        $envelopes = iterator_to_array($this->transport->all());
        $this->assertCount(2, $envelopes);
        $this->assertSame(['First', 'Second'], array_map(static fn (Envelope $envelope) => $envelope->getMessage()->getMessage(), $envelopes));

        $this->assertCount(1, iterator_to_array($this->transport->all(1)));

        $foundEnvelope = $this->transport->find($sentEnvelope->last(TransportMessageIdStamp::class)->getId());
        $this->assertNotNull($foundEnvelope);
        $this->assertSame('Second', $foundEnvelope->getMessage()->getMessage());
    }

    public function testSendWithSession()
    {
        $envelope = new Envelope(new DummyMessage('Hi'), [new MongoDbSessionStamp($this->client->startSession())]);

        $this->transport->send($envelope);

        $this->assertSame(1, $this->transport->getMessageCount());
    }

    public function testSetupCreatesTheIndex()
    {
        $this->transport->setup();

        $indexKeys = [];
        foreach ($this->client->getCollection(self::DATABASE, 'messenger_messages')->listIndexes() as $index) {
            $indexKeys[] = $index->getKey();
        }

        $this->assertContainsEquals(['availableAt' => 1, 'queueName' => 1, 'deliveredAt' => 1], $indexKeys);
    }

    public function testChangeStreamWakesUpAnOpenStream()
    {
        if (!$this->isReplicaSet) {
            $this->markTestSkipped('Change streams require a replica set.');
        }

        if (!\function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is required to run the wake-up test.');
        }

        // 15 s leaves headroom for the producer subprocess to boot and connect
        $streamConnection = Connection::fromDsn('mongodb://localhost/'.self::DATABASE, ['wait_time' => 15], $this->client);

        // a subprocess inserts the message about 400 ms after this test starts,
        // while the get() call below is already blocked on the change stream
        $code = \sprintf(
            'require %s; usleep(400000); $client = new MongoDB\Client(%s, ["serverSelectionTimeoutMS" => 5000]); $connection = Symfony\Component\Messenger\Bridge\MongoDb\Transport\Connection::fromDsn("mongodb://localhost/%s", [], $client); $connection->send("streamed");',
            var_export(\dirname(__DIR__, 8).'/vendor/autoload.php', true),
            var_export(getenv('MONGODB_URI') ?: 'mongodb://localhost:27017', true),
            self::DATABASE
        );

        $producer = proc_open([\PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        $start = microtime(true);
        $document = $streamConnection->get();
        $elapsed = microtime(true) - $start;

        if (\is_resource($producer)) {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($producer);
        }

        $this->assertNotNull($document);
        $this->assertSame('streamed', $document['body']);
        // the message was inserted after the get() call started: a poll query
        // could never return it, only the change stream wait waking up could
        $this->assertGreaterThanOrEqual(0.15, $elapsed);
    }
}
