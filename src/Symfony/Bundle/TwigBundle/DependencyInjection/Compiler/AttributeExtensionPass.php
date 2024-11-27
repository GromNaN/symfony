<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;
use Twig\Extension\AttributeExtension;

/**
 * Register an instance of AttributeExtension for each service using the
 * PHP attributes to declare Twig callables.
 * Requires Twig 3.19+
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * @internal
 */
class AttributeExtensionPass implements CompilerPassInterface
{
    private const TAG = 'twig.attribute_extension';

    public static function autoconfigureFromAttribute(ChildDefinition $definition, AsTwigFilter|AsTwigFunction|AsTwigTest $attribute, \ReflectionMethod $reflector): void
    {
        $definition->addTag(self::TAG);

        // The service must be tagged as a runtime to call non-static methods
        if (! $reflector->isStatic()) {
            $definition->addTag('twig.runtime');
        }
    }

    public function process(ContainerBuilder $container): void
    {
        if (! class_exists(AttributeExtension::class)) {
            return;
        }

        $services = $container->findTaggedServiceIds(self::TAG, true);

        if (!$services) {
            return;
        }

        foreach ($services as $id => $tags) {
            $definition = $container->getDefinition($id);

            $attributeExtensionDefinition = new Definition(AttributeExtension::class, [$definition->getClass()]);
            $attributeExtensionDefinition->addTag('twig.extension');
            $container->setDefinition('twig.attribute_extension.'.$id, $attributeExtensionDefinition);
        }
    }
}
