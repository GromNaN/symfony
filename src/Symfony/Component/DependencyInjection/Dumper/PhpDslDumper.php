<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Yaml\Dumper as YmlDumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

/**
 * PhpDslDumper dumps a service container as a PHP string.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PhpDslDumper extends Dumper
{
    /**
     * Dumps the service container as an YAML string.
     */
    public function dump(array $options = []): string
    {
        $code = <<<'PHP'
        <?php

        namespace Symfony\Component\DependencyInjection\Loader\Configurator;

        return static function (ContainerConfigurator $container) {

        PHP;

        $code .= $this->addParameters();
        $code .= $this->addServices();

        $code .= "\n};";

        return $code;
    }

    private function addService(string $id, Definition $definition): string
    {
        $code = "\n";
        foreach ($definition->getErrors() as $error) {
            $code .= \sprintf("\n        // %s\n", $error);
        }

        $code .= "        ->set(".$this->dumpValue($id);
        if (($class = $definition->getClass()) && $definition->getClass() !== $id) {
            if (str_contains($class, '\\')) {
                if (!str_starts_with($class, '\\')) {
                    $class = '\\'.$class;
                }
                $code .= ', '.$class.'::class';
            } else {
                $code .= ', '.$this->dumpValue($class);
            }
        }
        $code .= ')';

        if ($definition->isPublic()) {
            $code .= "\n            ->public()";
        }

        foreach ($definition->getTags() as $name => $tags) {
            foreach ($tags as $attributes) {
                $code .= "\n            ->tag(".$this->dumpValue($name);
                if ($attributes) {
                    $code .= ', ' . $this->dumpValue($attributes);
                }
                $code .= ')';
            }
        }

        if ($definition->getFile()) {
            $code .= \sprintf("\n            ->file(%s)", $this->dumpValue($this->container->resolveEnvPlaceholders($definition->getFile())));
        }

        if ($definition->isSynthetic()) {
            $code .= "\n            ->synthetic()";
        }

        if ($definition->isDeprecated()) {
            $deprecation = $definition->getDeprecation('%service_id%');
            $code .= \sprintf("\n            ->deprecate(%s, %s, %s)",
                $this->dumpValue($deprecation['package']),
                $this->dumpValue($deprecation['version']),
                $this->dumpValue($deprecation['message']),
            );
        }

        if ($definition->isAutowired()) {
            $code .= "\n            ->autowire()";
        }

        if ($definition->isAutoconfigured()) {
            $code .= "\n            ->autoconfigure()";
        }

        if ($definition->isAbstract()) {
            $code .= "\n            ->abstract()";
        }

        if ($definition->isLazy()) {
            $code .= "\n            ->lazy()";
        }

        if ($definition->getArguments()) {
            $index = 0;
            $list = true;
            $close = '';
            foreach ($definition->getArguments() as $argKey => $argValue) {
                if ($list) {
                    if ($argKey === $index) {
                        if ($index === 0) {
                            $code .= "\n            ->args([";
                            $close = "\n            ])";
                        }
                        $code .= "\n                ".$this->dumpValue($argValue).',';
                        $index++;
                    } else {
                        $list = false;
                        $code .= $close;
                        $close = '';
                    }
                }
                if (!$list) {
                    $code .= "\n            ->arg(".$this->dumpValue($argKey) . ', '.$this->dumpValue($argValue).')';
                }
            }
            $code .= $close;
        }

        foreach ($definition->getProperties() as $propertyName => $propertyValue) {
            $code .= \sprintf("\n            ->property(%s, %s)", $this->dumpValue($propertyName), $this->dumpValue($propertyValue));
        }

        foreach ($definition->getMethodCalls() as $call) {
            $code .= \sprintf("\n            ->call(%s, %s)", $this->dumpValue($call[0]), $this->dumpValue($call[1]));
        }

        if ($definition->isShared()) {
            $code .= "\n            ->share()";
        }

        if (null !== $decoratedService = $definition->getDecoratedService()) {
            if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE === ($decoratedService[3] ?? ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)) {
                unset($decoratedService[3]);
                if (0 === ($decoratedService[2] ?? 0)) {
                    unset($decoratedService[2]);
                    if (null === ($decoratedService[1] ?? null)) {
                        unset($decoratedService[1]);
                    }
                }
            } else {
                $decoratedService[3] = '\\'.ContainerInterface::class.'::'.match ($decoratedService[3]) {
                    ContainerInterface::IGNORE_ON_INVALID_REFERENCE => 'IGNORE_ON_INVALID_REFERENCE',
                    ContainerInterface::NULL_ON_INVALID_REFERENCE => 'NULL_ON_INVALID_REFERENCE',
                    ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE => 'RUNTIME_EXCEPTION_ON_INVALID_REFERENCE',
                };
            }
            for($i=0; $i<3; $i++) {
                if (array_key_exists($i, $decoratedService)) {
                    $decoratedService[$i] = $this->dumpValue($decoratedService[$i]);
                }
            }

            $code .= sprintf("\n            ->decorate(%s)", implode(', ', $decoratedService));
        }

        if ($callable = $definition->getFactory()) {
            if (\is_array($callable) && ['Closure', 'fromCallable'] !== $callable && $definition->getClass() === $callable[0]) {
                $code .= \sprintf("\n            ->constructor(%s)", $this->dumpValue($callable[1]));
            } else {
                $code .= \sprintf("\n            ->factory(%s)", $this->dumpCallable($callable));
            }
        }

        if ($callable = $definition->getConfigurator()) {
            $code .= \sprintf("\n            ->configurator(%s)", $this->dumpCallable($callable));
        }

        return $code;
    }

    private function addServiceAlias(string $alias, Alias $id): string
    {
        $code = "\n        ->alias(".$this->dumpValue($alias).', '.$this->dumpValue((string) $id).')';

        if ($id->isDeprecated()) {
            $deprecation = $id->getDeprecation();
            $code .= "\n            ->deprecate(".$this->dumpValue($deprecation['package']).', '.$this->dumpValue($deprecation['version']).', '.$this->dumpValue($deprecation['message']).')';
        }

        if ($id->isPublic()) {
            $code .= "\n            ->public()";
        }

        return $code;
    }

    private function addServices(): string
    {
        if (!$this->container->getDefinitions()) {
            return '';
        }

        $code = "    \$container->services()";

        foreach ($this->container->getDefinitions() as $id => $definition) {
            $code .= $this->addService($id, $definition);
        }

        $aliases = $this->container->getAliases();
        foreach ($aliases as $alias => $id) {
            $code .= $this->addServiceAlias($alias, $id);
        }

        return $code . ';';
    }

    private function addParameters(): string
    {
        if (!$this->container->getParameterBag()->all()) {
            return '';
        }

        $code = '    $container->parameters()';

        foreach ($this->container->getParameterBag()->all() as $name => $value) {
            $code .= \sprintf("\n        ->set(%s, %s)", $this->dumpValue($name), $this->dumpValue($value));
        }
        $code .= ";\n\n";

        return $code;
    }

    /**
     * Dumps callable to YAML format.
     */
    private function dumpCallable(mixed $callable): mixed
    {
        // @todo

        return $this->dumpValue($callable);
    }

    /**
     * Dumps the value to YAML format.
     *
     * @throws RuntimeException When trying to dump object or resource
     */
    private function dumpValue(mixed $value): string
    {
        if ($value instanceof ServiceClosureArgument) {
            return 'service_closure('.$this->dumpValue($value->getValues()[0]).')';
        }

        if ($value instanceof ArgumentInterface) {
            $tag = $value;

            if ($value instanceof TaggedIteratorArgument || ($value instanceof ServiceLocatorArgument && $tag = $value->getTaggedIteratorArgument())) {
                $content = 'tagged_iterator('.$this->dumpValue($tag->getTag());

                if ($tag->getIndexAttribute()) {
                    $content .= ', indexAttribute: '.$this->dumpValue($tag->getIndexAttribute());
                }
                if (null !== $tag->getDefaultIndexMethod()) {
                    $content .= ', defaultIndexMethod: '.$this->dumpValue($tag->getDefaultIndexMethod());
                }
                if (null !== $tag->getDefaultPriorityMethod()) {
                    $content .= ', defaultPriorityMethod: '.$this->dumpValue($tag->getDefaultPriorityMethod());
                }
                if ($tag->getExclude()) {
                    $content .= ', exclude: '.$this->dumpValue($tag->getExclude());
                }
                if (!$tag->excludeSelf()) {
                    $content .= ', excludeSelf: false';
                }

                return $content.')';
            }
            if ($value instanceof IteratorArgument) {
                return \sprintf('iterator(%s)', $this->dumpValue($value->getValues()));
            }
            if ($value instanceof ServiceLocatorArgument) {
                return \sprintf('service_locator(%s)', $this->dumpValue($value->getValues()));
            }
        }

        if (\is_array($value)) {
            $values = [];
            if (array_is_list($value)) {
                foreach ($value as $v) {
                    $values[] = $this->dumpValue($v);
                }
            } else {
                foreach ($value as $k => $v) {
                    $values[] = $this->dumpValue($k) . ' => ' . $this->dumpValue($v);
                }
            }

            return '[' . implode(', ', $values) . ']';
        } elseif ($value instanceof Reference) {
            return $this->getServiceCall((string) $value, $value);
        } elseif ($value instanceof Parameter) {
            return $this->getParameterCall((string) $value);
        } elseif ($value instanceof Expression) {
            return \sprintf('expr(%s)', $this->dumpValue((string)$value));
        } elseif ($value instanceof Definition) {
            // @todo inline definitions
            return sprintf('inline_service(%s)', $value->getClass() ? $this->dumpValue($value->getClass()) : 'null');
        } elseif ($value instanceof \UnitEnum) {
            return \sprintf('\\%s::%s', $value::class, $value->name);
        } elseif ($value instanceof AbstractArgument) {
            return sprintf('abstract_arg(%s)', $this->dumpValue($value->getText()));
        } elseif (\is_object($value) || \is_resource($value)) {
            throw new RuntimeException(\sprintf('Unable to dump a service container if a parameter is an object or a resource, got "%s".', get_debug_type($value)));
        }

        if (is_string($value)) {
            $value = $this->container->resolveEnvPlaceholders($value);

            if (!preg_match('//u', $value) || preg_match('/[^\x00\x07-\x0d\x1B\x20-\xff]/', $value)) {
                return sprintf('hex2bin(\'%s\')', bin2hex($value));
            }

            if (str_contains($value, '\\') && (class_exists($value) || interface_exists($value) || enum_exists($value))) {
                return sprintf('\\%s::class', ltrim($value, '\\'));
            }

            if (preg_match( '~%env\((.+)\)%~', $value, $matches)) {
                return \sprintf('env(%s)', $this->dumpValue($matches[1]));
            }

            $escaped = addcslashes($value, "\0..\37\\\"");
            if ($escaped === $value) {
                return \sprintf('\'%s\'', addcslashes($value, '\''));
            }

            return sprintf('"%s"', addcslashes($value, "\0..\37\\\$\""));
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (null === $value) {
            return 'null';
        }

        throw new \InvalidArgumentException(sprintf('Unsupported value of type "%s".', gettype($value)));
    }

    private function getServiceCall(string $id, ?Reference $reference = null): string
    {
        $code = 'service('.$this->dumpValue($id).')';

        $code .= match ($reference->getInvalidBehavior()) {
            ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE => '',
            ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE => '', // default behavior
            ContainerInterface::IGNORE_ON_INVALID_REFERENCE => '->ignoreOnInvalid()',
            ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE => '->ignoreOnUninitialized()',
            ContainerInterface::NULL_ON_INVALID_REFERENCE => '->nullOnInvalid()',
        };

        return $code;
    }

    private function getParameterCall(string $id): string
    {
        return 'param('.$this->dumpValue($id).')';
    }
}
