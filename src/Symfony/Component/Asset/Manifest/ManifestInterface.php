<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Manifest;


use Symfony\Component\Asset\Exception\RuntimeException;

interface ManifestInterface
{
    /**
     * @return string[] Map of virtual file names with actual file names.
     * @throws RuntimeException When the manifest cannot be loaded.
     */
    public function getManifest(): array;
}
