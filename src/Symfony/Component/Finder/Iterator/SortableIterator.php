<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Iterator;

use Symfony\Component\Finder\SortBy;

/**
 * SortableIterator applies a sort on a given Iterator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @implements \IteratorAggregate<string, \SplFileInfo>
 */
class SortableIterator implements \IteratorAggregate
{
    /** @deprecated use SortBy enum */
    public const SORT_BY_NONE = 0;
    /** @deprecated use SortBy enum */
    public const SORT_BY_NAME = 1;
    /** @deprecated use SortBy enum */
    public const SORT_BY_TYPE = 2;
    /** @deprecated use SortBy enum */
    public const SORT_BY_ACCESSED_TIME = 3;
    /** @deprecated use SortBy enum */
    public const SORT_BY_CHANGED_TIME = 4;
    /** @deprecated use SortBy enum */
    public const SORT_BY_MODIFIED_TIME = 5;
    /** @deprecated use SortBy enum */
    public const SORT_BY_NAME_NATURAL = 6;
    /** @deprecated use SortBy enum */
    public const SORT_BY_NAME_CASE_INSENSITIVE = 7;
    /** @deprecated use SortBy enum */
    public const SORT_BY_NAME_NATURAL_CASE_INSENSITIVE = 8;
    /** @deprecated use SortBy enum */
    public const SORT_BY_EXTENSION = 9;
    /** @deprecated use SortBy enum */
    public const SORT_BY_SIZE = 10;

    /** @var \Traversable<string, \SplFileInfo> */
    private \Traversable $iterator;
    private \Closure|int $sort;

    /**
     * @param \Traversable<string, \SplFileInfo> $iterator
     * @param SortBy|callable                    $sort     The sort type (SortBy case or a PHP callback)
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(\Traversable $iterator, int|SortBy|callable $sort, bool $reverseOrder = false)
    {
        $this->iterator = $iterator;
        if (\is_callable($sort)) {
            $this->sort = $reverseOrder ? static function (\SplFileInfo $a, \SplFileInfo $b) use ($sort) { return -$sort($a, $b); } : $sort(...);

            return;
        }

        if (is_int($sort)) {
            trigger_deprecation('symfony/finder', '6.2', 'Passing an int as $sort argument is deprecated. Use SortBy enum instead.');
        }

        $order = $reverseOrder ? -1 : 1;

        $this->sort = match($sort) {
            self::SORT_BY_NAME,
            SortBy::Name => static function (\SplFileInfo $a, \SplFileInfo $b) use ($order) {
                return $order * strcmp($a->getRealPath() ?: $a->getPathname(), $b->getRealPath() ?: $b->getPathname());
            },

            self::SORT_BY_NAME_NATURAL,
            SortBy::NameNatural => static function (\SplFileInfo $a, \SplFileInfo $b) use ($order) {
                return $order * strnatcmp($a->getRealPath() ?: $a->getPathname(), $b->getRealPath() ?: $b->getPathname());
            },

            self::SORT_BY_NAME_CASE_INSENSITIVE,
            SortBy::NameCaseInsensitive => static function (\SplFileInfo $a, \SplFileInfo $b) use ($order) {
                return $order * strcasecmp($a->getRealPath() ?: $a->getPathname(), $b->getRealPath() ?: $b->getPathname());
            },

            self::SORT_BY_NAME_NATURAL_CASE_INSENSITIVE,
            SortBy::NameNaturalCaseInsensitive => static function (\SplFileInfo $a, \SplFileInfo $b) use ($order) {
                return $order * strnatcasecmp($a->getRealPath() ?: $a->getPathname(), $b->getRealPath() ?: $b->getPathname());
            },

            self::SORT_BY_TYPE,
            SortBy::Type => static function (\SplFileInfo $a, \SplFileInfo $b) use ($order) {
                if ($a->isDir() && $b->isFile()) {
                    return -$order;
                } elseif ($a->isFile() && $b->isDir()) {
                    return $order;
                }

                return $order * strcmp($a->getRealPath() ?: $a->getPathname(), $b->getRealPath() ?: $b->getPathname());
            },

            self::SORT_BY_ACCESSED_TIME,
            SortBy::AccessedTime => static function (\SplFileInfo $a, \SplFileInfo $b) use ($order) {
                return $order * ($a->getATime() - $b->getATime());
            },

            self::SORT_BY_CHANGED_TIME,
            SortBy::ChangedTime => static function (\SplFileInfo $a, \SplFileInfo $b) use ($order) {
                return $order * ($a->getCTime() - $b->getCTime());
            },

            self::SORT_BY_MODIFIED_TIME,
            SortBy::ModifiedTime => static function (\SplFileInfo $a, \SplFileInfo $b) use ($order) {
                return $order * ($a->getMTime() - $b->getMTime());
            },

            self::SORT_BY_EXTENSION,
            SortBy::Extension => static function (\SplFileInfo $a, \SplFileInfo $b) use ($order) {
                return $order * strnatcmp($a->getExtension(), $b->getExtension());
            },

            self::SORT_BY_SIZE,
            SortBy::Size => static function (\SplFileInfo $a, \SplFileInfo $b) use ($order) {
                return $order * ($a->getSize() - $b->getSize());
            },

            default => throw new \InvalidArgumentException('The SortableIterator takes a PHP callable or a valid built-in sort algorithm as an argument.'),
        };
    }

    public function getIterator(): \Traversable
    {
        if (1 === $this->sort) {
            return $this->iterator;
        }

        $array = iterator_to_array($this->iterator, true);

        if (-1 === $this->sort) {
            $array = array_reverse($array);
        } else {
            uasort($array, $this->sort);
        }

        return new \ArrayIterator($array);
    }
}
