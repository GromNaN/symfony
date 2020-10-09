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

class JsonManifest implements ManifestInterface
{
    private $manifestPath;

    public function __construct(string $manifestPath)
    {
        $this->manifestPath = $manifestPath;
    }

    public function getManifest(): array
    {
        if (!is_file($this->manifestPath)) {
            throw new RuntimeException(sprintf('Asset manifest file "%s" does not exist.', $this->manifestPath));
        }

        try {
            $data = json_decode(file_get_contents($this->manifestPath), true, 2, \JSON_BIGINT_AS_STRING | (\PHP_VERSION_ID >= 70300 ? \JSON_THROW_ON_ERROR : 0));
        } catch (\JsonException $e) {
            throw new RuntimeException(sprintf('Error parsing JSON from asset manifest file "%s": ', $this->manifestPath).$e->getMessage(), $e->getCode(), $e);
        }

        if (0 < json_last_error()) {
            throw new RuntimeException(sprintf('Error parsing JSON from asset manifest file "%s": ', $this->manifestPath).json_last_error_msg());
        }

        return $data;
    }
}
