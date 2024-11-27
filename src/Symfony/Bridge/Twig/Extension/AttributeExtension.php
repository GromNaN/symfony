<?php

namespace Symfony\Bridge\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

final class AttributeExtension extends AbstractExtension
{
    /**
     * @param list<array{type: string, name: string, callable: array, options: array}> $callables
     * @param int $lastModified The last modification time of the configuration
     */
    public function __construct(private array $callables, private int $lastModified = 0)
    {
    }

    public function getFilters(): \Generator
    {
        foreach ($this->callables as $callable) {
            if ($callable['type'] === 'filter') {
                yield new TwigFilter($callable['name'], $callable['callable'], $callable['options']);
            }
        }
    }

    public function getFunctions(): \Generator
    {
        foreach ($this->callables as $callable) {
            if ($callable['type'] === 'function') {
                yield new TwigFunction($callable['name'], $callable['callable'], $callable['options']);
            }
        }
    }

    public function getTests(): \Generator
    {
        foreach ($this->callables as $callable) {
            if ($callable['type'] === 'test') {
                yield new TwigTest($callable['name'], $callable['callable'], $callable['options']);
            }
        }
    }

    public function getLastModified(): int
    {
        return $this->lastModified;
    }
}
