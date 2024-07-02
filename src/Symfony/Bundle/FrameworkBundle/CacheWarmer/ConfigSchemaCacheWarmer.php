<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\JsonSchemaBuilder;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Generate all config builders.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class ConfigSchemaCacheWarmer implements CacheWarmerInterface
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if (!$buildDir) {
            return [];
        }

        $generator = new JsonSchemaBuilder();

        if ($this->kernel instanceof Kernel) {
            /** @var ContainerBuilder $container */
            $container = \Closure::bind(function (Kernel $kernel) {
                $containerBuilder = $kernel->getContainerBuilder();
                $kernel->prepareContainer($containerBuilder);

                return $containerBuilder;
            }, null, $this->kernel)($this->kernel);

            $extensions = $container->getExtensions();
        } else {
            $extensions = [];
            foreach ($this->kernel->getBundles() as $bundle) {
                $extension = $bundle->getContainerExtension();
                if (null !== $extension) {
                    $extensions[] = $extension;
                }
            }
        }

        $configurations = array_filter(array_map(function (ExtensionInterface $extension) {
            if ($extension instanceof ConfigurationInterface) {
                return $extension;
            } elseif ($extension instanceof ConfigurationExtensionInterface) {
                $container = $this->kernel->getContainer();
                return $extension->getConfiguration([], new ContainerBuilder($container instanceof Container ? new ContainerBag($container) : new ParameterBag()));
            }
            return null;
        }, $extensions));
        $json = $generator->build(...$configurations);

        //file_put_contents($buildDir.'/config-schema.json', json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        file_put_contents($buildDir.'/config-schema.json', json_encode($json, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));

        // No need to preload anything
        return [];
    }

    public function isOptional(): bool
    {
        return false;
    }
}
