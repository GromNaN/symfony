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

use Symfony\Bridge\Twig\Attribute\AsTwigFilter;
use Symfony\Bridge\Twig\Attribute\AsTwigFunction;
use Symfony\Bridge\Twig\Attribute\AsTwigTest;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Symfony\Bridge\Twig\Extension\AttributeExtension;

/**
 * Register an instance of AttributeExtension for each service using the
 * PHP attributes to declare Twig callables.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * @internal
 */
final class AttributeExtensionPass implements CompilerPassInterface
{
    private const TAG = 'twig.attribute_extension';

    public static function autoconfigureFromAttribute(ChildDefinition $definition, AsTwigFilter|AsTwigFunction|AsTwigTest $attribute, \ReflectionMethod $reflector): void
    {
        $parameters = $reflector->getParameters();
        $options = [
            'needs_context' => $attribute->needsContext ?? false,
            'needs_environment' => $attribute->needsEnvironment
                ?? $reflector->getNumberOfRequiredParameters() > 0
                && $parameters[0]->getType() instanceof \ReflectionNamedType
                && Environment::class === $parameters[0]->getType()->getName()
                && !$parameters[0]->isVariadic(),
            'needs_charset' => $attribute->needsCharset ?? false,
            'is_variadic' => $reflector->isVariadic(),
            'deprecation_info' => $attribute->deprecationInfo,
        ];

        if ($attribute instanceof AsTwigFilter) {
            $type = 'filter';
            $options += [
                'is_safe' => $attribute->isSafe,
                'is_safe_callback' => $attribute->isSafeCallback,
                'pre_escape' => $attribute->preEscape,
                'preserves_safety' => $attribute->preservesSafety,
            ];
        } elseif ($attribute instanceof AsTwigFunction) {
            $type = 'function';
            $options += [
                'is_safe' => $attribute->isSafe,
                'is_safe_callback' => $attribute->isSafeCallback,
            ];
        } else {
            $type = 'test';
        }

        $definition->addTag(self::TAG, [
            'type' => $type,
            'name' => $attribute->name,
            'callable' => $reflector->name,
            'options' => $options,
        ]);

        // The service must be tagged as a runtime to call non-static methods
        if (!$reflector->isStatic()) {
            $definition->addTag('twig.runtime');
        }
    }

    public function process(ContainerBuilder $container): void
    {
        $callables = [];
        foreach ($container->findTaggedServiceIds(self::TAG, true) as $id => $tag) {
            $class = $container->getDefinition($id)->getClass();
            foreach($tag as $attributes) {
                $attributes['callable'] = [$class, $attributes['callable']];
                $callables[] = $attributes;
            }
        }

        $container->register('twig.extension.attributes', AttributeExtension::class)
            ->setArgument(0, $callables)
            ->setArgument(1, $container->hasParameter('kernel.container_build_time') ? $container->getParameter('kernel.container_build_time') : time())
            ->addTag('twig.extension');
    }
}
