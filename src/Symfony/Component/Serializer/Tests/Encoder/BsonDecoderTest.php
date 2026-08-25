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
use MongoDB\BSON\PackedArray;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\BsonDecoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

#[RequiresPhpExtension('mongodb')]
class BsonDecoderTest extends TestCase
{
    private BsonDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new BsonDecoder();
    }

    public function testSupportsDecoding()
    {
        $this->assertTrue($this->decoder->supportsDecoding('bson'));
        $this->assertFalse($this->decoder->supportsDecoding('json'));
    }

    public function testDecode()
    {
        $bson = (string) Document::fromJSON('{"foo":"bar","nested":{"baz":true},"list":[1,2]}');

        $decoded = $this->decoder->decode($bson, 'bson');

        $expected = new \stdClass();
        $expected->foo = 'bar';
        $expected->nested = new \stdClass();
        $expected->nested->baz = true;
        $expected->list = [1, 2];

        $this->assertEquals($expected, $decoded);
    }

    public function testDecodeList()
    {
        $bson = (string) PackedArray::fromPHP([1, 'two', [3, 4]]);

        $this->assertSame([1, 'two', [3, 4]], $this->decoder->decode($bson, 'bson'));
    }

    public function testDecodeListOfDocuments()
    {
        $bson = (string) PackedArray::fromPHP([['a' => 1], ['a' => 2]]);

        $decoded = $this->decoder->decode($bson, 'bson');

        $this->assertIsList($decoded);
        $this->assertContainsOnlyInstancesOf(\stdClass::class, $decoded);
        $this->assertSame(1, $decoded[0]->a);
        $this->assertSame(2, $decoded[1]->a);
    }

    public function testDecodeEmptyDocumentAsList()
    {
        $this->assertSame([], $this->decoder->decode((string) PackedArray::fromPHP([]), 'bson'));
    }

    public function testDecodeDocumentWithNonSequentialKeys()
    {
        $bson = (string) Document::fromJSON('{"1":"one","0":"zero"}');

        $decoded = $this->decoder->decode($bson, 'bson');

        $this->assertInstanceOf(\stdClass::class, $decoded);
        $this->assertSame('one', $decoded->{1});
    }

    public function testDecodeListWithArrayTypeFromContext()
    {
        $bson = (string) PackedArray::fromPHP([['a' => 1]]);

        $decoded = $this->decoder->decode($bson, 'bson', [
            BsonDecoder::TYPE_MAP => ['array' => 'array', 'document' => 'array'],
        ]);

        $this->assertSame([['a' => 1]], $decoded);
    }

    public function testDecodeWithTypeMapFromContext()
    {
        $bson = (string) Document::fromJSON('{"foo":"bar","nested":{"baz":true}}');

        $decoded = $this->decoder->decode($bson, 'bson', [
            BsonDecoder::TYPE_MAP => ['root' => 'array', 'document' => 'array'],
        ]);

        $this->assertSame(['foo' => 'bar', 'nested' => ['baz' => true]], $decoded);
    }

    public function testDecodeWithDefaultContext()
    {
        $decoder = new BsonDecoder([BsonDecoder::TYPE_MAP => ['root' => 'array', 'document' => 'array']]);

        $this->assertSame(['foo' => 'bar'], $decoder->decode((string) Document::fromJSON('{"foo":"bar"}'), 'bson'));
    }

    public function testDecodeInvalidData()
    {
        $bson = substr((string) Document::fromJSON('{"foo":"bar"}'), 0, 5);

        $this->expectException(NotEncodableValueException::class);
        $this->decoder->decode($bson, 'bson');
    }
}
