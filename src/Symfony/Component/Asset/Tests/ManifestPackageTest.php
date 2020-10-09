<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Exception\RuntimeException;
use Symfony\Component\Asset\Manifest\ManifestInterface;
use Symfony\Component\Asset\ManifestPackage;

class ManifestPackageTest extends TestCase
{
    public function testGetVersion()
    {
        $package = new ManifestPackage($this->getManifest());
        $this->assertSame('/build/styles/abc.css', $package->getVersion('style.css'));
    }

    public function testGetVersionFallbackIfMissing()
    {
        $package = new ManifestPackage($this->getManifest());
        $this->assertSame('style-not-found.css', $package->getVersion('style-not-found.css'));
    }

    public function testGetVersionThrowExceptionWhenStrict()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Asset "style-not-found.css" not found in manifest.');
        $package = new ManifestPackage($this->getManifest(), null, true);
        $package->getVersion('style-not-found.css');
    }

    private function getManifest()
    {
        $manifest = $this->createMock(ManifestInterface::class);
        $manifest->expects($this->once())
            ->method('getManifest')
            ->willReturn([
                'style.css' => '/build/styles/abc.css',
                'script.js' => '/build/scripts/123.js'
            ]);

        return $manifest;
    }
}
