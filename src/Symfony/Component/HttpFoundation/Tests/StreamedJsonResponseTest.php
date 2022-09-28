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
                    'articles' => '__articles__',
                    'news' => '__news__',
                ],
            ],
            [
                '__articles__' => $this->generatorSimple('Article'),
                '__news__' => $this->generatorSimple('News'),
            ],
        );

        $this->assertSame('{"_embedded":{"articles":["Article 1","Article 2","Article 3"],"news":["News 1","News 2","News 3"]}}', $content);
    }

    public function testResponseObjectsList()
    {
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'articles' => '__articles__',
                ],
            ],
            [
                '__articles__' => $this->generatorArray('Article'),
            ],
        );

        $this->assertSame('{"_embedded":{"articles":[{"title":"Article 1"},{"title":"Article 2"},{"title":"Article 3"}]}}', $content);
    }

    public function testResponseWithArray()
    {
        $content = $this->createSendResponse(
            [
                '_embedded' => [
                    'articles' => '__articles__',
                ],
            ],
            [
                '__articles__' => ['Article 1', 'Article 2', 'Article 3'],
            ],
        );

        $this->assertSame('{"_embedded":{"articles":["Article 1","Article 2","Article 3"]}}', $content);
    }

    public function testResponseStatusCode()
    {
        $response = new StreamedJsonResponse([], [], 201);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testResponseHeaders()
    {
        $response = new StreamedJsonResponse([], [], 200, ['X-Test' => 'Test']);

        $this->assertSame('Test', $response->headers->get('X-Test'));
    }

    public function testCustomContentType()
    {
        $response = new StreamedJsonResponse([], [], 200, ['Content-Type' => 'application/json+stream']);

        $this->assertSame('application/json+stream', $response->headers->get('Content-Type'));
    }

    public function testFlushSize()
    {
        $response = new StreamedJsonResponse([], []);
        $response->setFlushSize(50);

        $this->assertSame(50, $response->getFlushSize());
    }

    public function testEncodingOptions()
    {
        $response = new StreamedJsonResponse([], []);
        $response->setEncodingOptions(\JSON_UNESCAPED_SLASHES);

        $this->assertSame(\JSON_UNESCAPED_SLASHES, $response->getEncodingOptions() & \JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param mixed[] $structure
     * @param array<string, \Generator<int|string, mixed> $generics
     */
    private function createSendResponse(
        array $structure,
        array $generics,
    ): string {
        $response = new StreamedJsonResponse($structure, $generics);

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
