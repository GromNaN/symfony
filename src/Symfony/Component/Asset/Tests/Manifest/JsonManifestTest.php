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
use Symfony\Component\Asset\Manifest\JsonManifest;
use Symfony\Component\Asset\Manifest\ManifestInterface;

class JsonManifestTest extends TestCase
{
    public function testGetManifest()
    {
        $manifest = $this->createManifest('manifest-valid.json');

        $this->assertIsArray($manifest->getManifest());
    }

    public function testMissingManifestFileThrowsException()
    {
        $this->expectException('RuntimeException');
        $manifest = $this->createManifest('non-existent-file.json');
        $manifest->getManifest();
    }

    public function testManifestFileWithBadJSONThrowsException()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Error parsing JSON');
        $manifest = $this->createManifest('manifest-invalid.json');
        $manifest->getManifest();
    }

    private function createManifest($manifestFilename): ManifestInterface
    {
        return new JsonManifest(__DIR__.'/../fixtures/'.$manifestFilename);
    }
}
