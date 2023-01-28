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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\MongoDB\Transport\Connection;
use Symfony\Component\Messenger\Bridge\MongoDB\Transport\MongoDBTransport;
use Symfony\Component\Messenger\Bridge\MongoDB\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @requires extension redis
 */
class MongoDBTransportFactoryTest extends TestCase
{
    public function testSupportsOnlyRedisTransports()
    {
        $factory = new RedisTransportFactory();

        $this->assertTrue($factory->supports('redis://localhost', []));
        $this->assertTrue($factory->supports('rediss://localhost', []));
        $this->assertFalse($factory->supports('sqs://localhost', []));
        $this->assertFalse($factory->supports('invalid-dsn', []));
    }

    /**
     * @group integration
     */
    public function testCreateTransport()
    {
        $this->skipIfRedisUnavailable();

        $factory = new RedisTransportFactory();
        $serializer = $this->createMock(SerializerInterface::class);
        $expectedTransport = new MongoDBTransport(Connection::fromDsn('redis://'.getenv('REDIS_HOST'), ['stream' => 'bar', 'delete_after_ack' => true]), $serializer);

        $this->assertEquals($expectedTransport, $factory->createTransport('redis://'.getenv('REDIS_HOST'), ['stream' => 'bar', 'delete_after_ack' => true], $serializer));
    }

    private function skipIfRedisUnavailable()
    {
        try {
            (new \Redis())->connect(...explode(':', getenv('REDIS_HOST')));
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }
}
