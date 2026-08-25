<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Encoder;

use MongoDB\BSON\Document;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\PackedArray;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\BsonDecoder;
use Symfony\Component\Serializer\Encoder\BsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[RequiresPhpExtension('mongodb')]
class BsonEncoderTest extends TestCase
{
    private BsonEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new BsonEncoder();
    }

    public function testSupportsEncoding()
    {
        $this->assertTrue($this->encoder->supportsEncoding('bson'));
        $this->assertFalse($this->encoder->supportsEncoding('json'));
    }

    public function testEncode()
    {
        $data = [
            'foo' => 'bar',
            'nested' => ['baz' => true, 'qux' => null],
            'list' => [1, 2, 3],
        ];

        $expected = Document::fromJSON('{"foo":"bar","nested":{"baz":true,"qux":null},"list":[1,2,3]}');

        $this->assertSame((string) $expected, $this->encoder->encode($data, 'bson'));
    }

    public function testEncodeList()
    {
        $data = [1, 'two', ['three' => 3], [4, 5]];

        $this->assertSame((string) PackedArray::fromPHP($data), $this->encoder->encode($data, 'bson'));
    }

    public function testEncodeEmptyArray()
    {
        $this->assertSame((string) PackedArray::fromPHP([]), $this->encoder->encode([], 'bson'));
    }

    public function testEncodeObject()
    {
        $data = new \stdClass();
        $data->foo = 'bar';

        $this->assertSame((string) Document::fromJSON('{"foo":"bar"}'), $this->encoder->encode($data, 'bson'));
    }

    public function testEncodeBsonType()
    {
        $id = new ObjectId('5a2493c33c95a1281836eb6a');

        $encoded = $this->encoder->encode(['_id' => $id], 'bson');

        $this->assertSame((string) Document::fromPHP(['_id' => $id]), $encoded);
    }

    public static function provideUnsupportedRootValues(): iterable
    {
        yield 'string' => ['foo'];
        yield 'int' => [1];
        yield 'null' => [null];
        yield 'bool' => [true];
    }

    #[DataProvider('provideUnsupportedRootValues')]
    public function testEncodeUnsupportedRootValue(mixed $data)
    {
        $this->expectException(NotEncodableValueException::class);
        $this->encoder->encode($data, 'bson');
    }

    public function testRoundTripWithSerializer()
    {
        $serializer = new Serializer([new ObjectNormalizer()], [new BsonEncoder(), new BsonDecoder()]);

        $bson = $serializer->serialize(new BsonDummy('bar', 42), 'bson');

        $this->assertEquals(new BsonDummy('bar', 42), $serializer->deserialize($bson, BsonDummy::class, 'bson'));
    }

    public function testRoundTripOfACollectionWithSerializer()
    {
        $serializer = new Serializer([new ArrayDenormalizer(), new ObjectNormalizer()], [new BsonEncoder(), new BsonDecoder()]);
        $messages = [new BsonDummy('a', 1), new BsonDummy('b', 2)];

        $bson = $serializer->serialize($messages, 'bson');

        $this->assertEquals($messages, $serializer->deserialize($bson, BsonDummy::class.'[]', 'bson'));
    }
}

class BsonDummy
{
    public function __construct(
        public string $foo = '',
        public int $bar = 0,
    ) {
    }
}
