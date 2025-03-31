<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Attribute;

/**
 * Configures a class or a property to map to.
 *
 * @experimental
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
readonly class Map
{
    /**
     * @param string|class-string|null                                                               $source    The property or the class to map from
     * @param string|class-string|null                                                               $target    The property or the class to map to
     * @param string|bool|callable(mixed, object): bool|null                                         $if        Control whether to map to the target, or specify the target using a class name. When a string is used the service name takes precedence over the class name
     * @param (string|callable(mixed, object): mixed)|(string|callable(mixed, object): mixed)[]|null $transform A service id or a callable that transforms the value during mapping
     */
    public function __construct(
        public ?string $target = null,
        public ?string $source = null,
        public mixed $if = null,
        public mixed $transform = null,
    ) {
    }
}
