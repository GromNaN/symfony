<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset;

use Symfony\Component\Asset\Context\ContextInterface;
use Symfony\Component\Asset\Context\NullContext;
use Symfony\Component\Asset\Exception\RuntimeException;
use Symfony\Component\Asset\Manifest\ManifestInterface;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * Basic package that adds a version to asset URLs.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ManifestPackage implements PackageInterface
{
    private $manifest;
    private $manifestData;
    private $context;
    private $strict;

    public function __construct(ManifestInterface $manifest, ContextInterface $context = null, bool $strict = false)
    {
        $this->manifest = $manifest;
        $this->context = $context ?: new NullContext();
        $this->strict = $strict;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(string $path)
    {
        if (!isset($this->manifestData)) {
            $this->manifestData = $this->manifest->getManifest();
        }

        if (isset($this->manifestData[$path])) {
            return $this->manifestData[$path];
        }

        if ($this->strict) {
            throw new RuntimeException(sprintf('Asset "%s" not found in manifest.', $path));
        }

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl(string $path)
    {
        if ($this->isAbsoluteUrl($path)) {
            // Should be deprecated ?
            return $path;
        }

        return $this->getVersion($path);
    }

    /**
     * @return bool
     */
    protected function isAbsoluteUrl(string $url)
    {
        return false !== strpos($url, '://') || '//' === substr($url, 0, 2);
    }
}
