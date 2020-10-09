<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests\Manifest;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Manifest\ManifestInterface;
use Symfony\Component\Asset\Manifest\RemoteJsonManifest;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RemoteJsonManifestTest extends TestCase
{
    public function testGetManifest()
    {
        $manifest = $this->createManifest('https://cdn.example.com/manifest-valid.json');

        $this->assertIsArray($manifest->getManifest());
    }

    public function testMissingManifestFileThrowsException()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('HTTP 404 returned for "https://cdn.example.com/non-existent-file.json"');
        $manifest = $this->createManifest('https://cdn.example.com/non-existent-file.json');
        $manifest->getManifest();
    }

    public function testManifestFileWithBadJSONThrowsException()
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Syntax error');
        $manifest = $this->createManifest('https://cdn.example.com/manifest-invalid.json');
        $manifest->getManifest();
    }

    private function createManifest($manifestUrl): ManifestInterface
    {
        $httpClient = new MockHttpClient(function ($method, $url, $options) {
            $filename = __DIR__.'/../fixtures/'.basename($url);

            if (file_exists($filename)) {
                return new MockResponse(file_get_contents($filename), ['http_headers' => ['content-type' => 'application/json']]);
            }

            return new MockResponse('{}', ['http_code' => 404]);
        });

        return new RemoteJsonManifest($manifestUrl, $httpClient);
    }
}
