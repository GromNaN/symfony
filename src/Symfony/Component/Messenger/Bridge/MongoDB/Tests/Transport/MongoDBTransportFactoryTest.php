<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDB\Tests\Transport;

use MongoDB\Driver\Manager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\MongoDB\Transport\Connection;
use Symfony\Component\Messenger\Bridge\MongoDB\Transport\MongoDBTransport;
use Symfony\Component\Messenger\Bridge\MongoDB\Transport\MongoDBTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @requires extension redis
 */
class MongoDBTransportFactoryTest extends TestCase
{
    public function testSupportsOnlyMongoDBTransports()
    {
        $factory = new MongoDBTransportFactory();

        $this->assertTrue($factory->supports('mongodb://localhost', []));
        $this->assertTrue($factory->supports('mongodb://mongodb0.example.com:27017/db0', []));
        $this->assertFalse($factory->supports('redis://localhost', []));
        $this->assertFalse($factory->supports('invalid-dsn', []));
    }

    /**
     * @group integration
     */
    public function testCreateTransport()
    {
        $this->skipIfRedisUnavailable();

        $factory = new MongoDBTransportFactory();
        $serializer = $this->createMock(SerializerInterface::class);
        $expectedTransport = new MongoDBTransport(Connection::fromDsn(getenv('MONGODB_URI'), ['collection' => 'bar']), $serializer);

        $this->assertEquals($expectedTransport, $factory->createTransport(getenv('MONGODB_URI'), ['collection' => 'bar'], $serializer));
    }

    private function skipIfRedisUnavailable()
    {
        try {
            (new Manager(getenv('MONGODB_URI')))->startSession();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }
}
