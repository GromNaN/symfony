<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

class StreamedJsonResponseTest extends TestCase
{
    public function testResponseSimpleList()
    {
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'articles' => $this->generatorSimple('Article'),
                    'news' => $this->generatorSimple('News'),
                ],
            ],
        );

        $this->assertSame('{"_embedded":{"articles":["Article 1","Article 2","Article 3"],"news":["News 1","News 2","News 3"]}}', $content);
    }

    public function testResponseObjectsList()
    {
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'articles' => $this->generatorArray('Article'),
                ],
            ],
        );

        $this->assertSame('{"_embedded":{"articles":[{"title":"Article 1"},{"title":"Article 2"},{"title":"Article 3"}]}}', $content);
    }

    public function testResponseWithoutGenerator()
    {
        // why it is not the designed usage for a good DX all kind of iterables should be supported
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'articles' => ['Article 1', 'Article 2', 'Article 3'],
                ],
            ],
        );

        $this->assertSame('{"_embedded":{"articles":["Article 1","Article 2","Article 3"]}}', $content);
    }

    public function testResponseWithPlaceholder()
    {
        // the placeholder must not conflict with generator injection
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'articles' => $this->generatorArray('Article'),
                    'placeholder' => '__symfony_json__',
                    'news' => $this->generatorSimple('News'),
                ],
                'placeholder' => '__symfony_json__',
            ],
        );

        $this->assertSame('{"_embedded":{"articles":[{"title":"Article 1"},{"title":"Article 2"},{"title":"Article 3"}],"placeholder":"__symfony_json__","news":["News 1","News 2","News 3"]},"placeholder":"__symfony_json__"}', $content);
    }

    public function testResponseWithMixedKeyType()
    {
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'list' => (function (): \Generator {
                        yield 0 => 'test';
                        yield 'key' => 'value';
                    })(),
                    'map' => (function (): \Generator {
                        yield 'key' => 'value';
                        yield 0 => 'test';
                    })(),
                    'integer' => (function (): \Generator {
                        yield 1 => 'one';
                        yield 3 => 'three';
                    })(),
                ],
            ]
        );

        $this->assertSame('{"_embedded":{"list":["test","value"],"map":{"key":"value","0":"test"},"integer":{"1":"one","3":"three"}}}', $content);
    }

    public function testResponseOtherTraversable()
    {
        $arrayObject = new \ArrayObject(['key' => 'value']);

        $iteratorAggregate = new class implements \IteratorAggregate {
            public function getIterator(): \Traversable {
                return new \ArrayIterator(['Article 1', 'Article 2', 'Article 3']);
            }
        };

        $jsonSerializeAble = new class implements \IteratorAggregate, \JsonSerializable {
            public function getIterator(): \Traversable {
                return new \ArrayIterator(['This should be ignored']);
            }

            public function jsonSerialize(): mixed
            {
                return ['JSON Serialized'];
            }
        };

        // why Generators should be used for performance reasons the object should also work with any Traversable
        // to make things easier for a developer
        $content = $this->createSendResponse(
            [
                'arrayObject' => $arrayObject,
                'iteratorAggregate' => $iteratorAggregate,
                'jsonSerializable' => $jsonSerializeAble,
            ],
        );

        $this->assertSame('{"arrayObject":{"key":"value"},"iteratorAggregate":["Article 1","Article 2","Article 3"],"jsonSerializable":["JSON Serialized"]}', $content);
    }

    public function testResponseStatusCode()
    {
        $response = new StreamedJsonResponse([], 201);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testResponseHeaders()
    {
        $response = new StreamedJsonResponse([], 200, ['X-Test' => 'Test']);

        $this->assertSame('Test', $response->headers->get('X-Test'));
    }

    public function testCustomContentType()
    {
        $response = new StreamedJsonResponse([], 200, ['Content-Type' => 'application/json+stream']);

        $this->assertSame('application/json+stream', $response->headers->get('Content-Type'));
    }

    public function testFlushSize()
    {
        $response = new StreamedJsonResponse([]);
        $response->setFlushSize(50);

        $this->assertSame(50, $response->getFlushSize());
    }

    public function testEncodingOptions()
    {
        $response = new StreamedJsonResponse([
            '_embedded' => [
                'count' => '2', // options are applied to the initial json encode
                'values' => (function (): \Generator {
                    yield "with/unescaped/slash" => 'With/a/slash'; // options are applied to key and values
                    yield '3' => '3'; // numeric check for value, but not for the key
                })(),
            ],
        ]);
        $response->setEncodingOptions(\JSON_UNESCAPED_SLASHES | \JSON_NUMERIC_CHECK);

        $this->assertSame(\JSON_UNESCAPED_SLASHES, $response->getEncodingOptions() & \JSON_UNESCAPED_SLASHES);

        ob_start();
        $response->send();
        $content = ob_get_clean();

        $this->assertSame('{"_embedded":{"count":2,"values":{"with/unescaped/slash":"With/a/slash","3":3}}}', $content);
    }

    /**
     * @param mixed[] $data
     */
    private function createSendResponse(array $data): string
    {
        $response = new StreamedJsonResponse($data);

        ob_start();
        $response->send();

        return ob_get_clean();
    }

    /**
     * @return \Generator<int, string>
     */
    private function generatorSimple(string $test): \Generator
    {
        yield $test.' 1';
        yield $test.' 2';
        yield $test.' 3';
    }

    /**
     * @return \Generator<int, array{title: string}>
     */
    private function generatorArray(string $test): \Generator
    {
        yield ['title' => $test.' 1'];
        yield ['title' => $test.' 2'];
        yield ['title' => $test.' 3'];
    }
}
