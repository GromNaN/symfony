<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\WebLink\Link;

/**
 * Test case borrowed from https://github.com/php-fig/link/.
 */
class LinkTest extends TestCase
{
    public function testCanSetAndRetrieveValues()
    {
        $link = (new Link())
            ->withHref('http://www.google.com')
            ->withRel('next')
            ->withAttribute('me', 'you')
        ;

        $this->assertSame('http://www.google.com', $link->getHref());
        $this->assertContains('next', $link->getRels());
        $this->assertArrayHasKey('me', $link->getAttributes());
        $this->assertSame('you', $link->getAttributes()['me']);
    }

    public function testCanRemoveValues()
    {
        $link = (new Link())
            ->withHref('http://www.google.com')
            ->withRel('next')
            ->withAttribute('me', 'you')
        ;

        $link = $link->withoutAttribute('me')
            ->withoutRel('next');

        $this->assertSame('http://www.google.com', $link->getHref());
        $this->assertFalse(\in_array('next', $link->getRels(), true));
        $this->assertArrayNotHasKey('me', $link->getAttributes());
    }

    public function testAttributeValues()
    {
        $link = new Link();
        $link = $link
            ->withAttribute('string', 'value')
            ->withAttribute('int', 1)
            ->withAttribute('float', 2.3)
            ->withAttribute('bool', true)
            ->withAttribute('array', ['foo', 'bar'])
            ->withAttribute('stringable', $stringable = $this->stringable('foo'))
        ;

        self::assertSame([
            'string' => 'value',
            'int' => 1,
            'float' => 2.3,
            'bool' => true,
            'array' => ['foo', 'bar'],
            'stringable' => $stringable,
        ], $link->getAttributes());
    }

    public function testMultipleRels()
    {
        $link = (new Link())
            ->withHref('http://www.google.com')
            ->withRel('next')
            ->withRel('reference');

        $this->assertCount(2, $link->getRels());
        $this->assertContains('next', $link->getRels());
        $this->assertContains('reference', $link->getRels());
    }

    public function testConstructor()
    {
        $link = new Link('next', 'http://www.google.com');

        $this->assertSame('http://www.google.com', $link->getHref());
        $this->assertContains('next', $link->getRels());
    }

    /**
     * @dataProvider templatedHrefProvider
     */
    public function testTemplated(string|\Stringable $href)
    {
        $link = (new Link())
            ->withHref($href);

        $this->assertTrue($link->isTemplated());
    }

    /**
     * @dataProvider notTemplatedHrefProvider
     */
    public function testNotTemplated(string|\Stringable $href)
    {
        $link = (new Link())
            ->withHref($href);

        $this->assertFalse($link->isTemplated());
    }

    public static function templatedHrefProvider()
    {
        return [
            ['http://www.google.com/{param}/foo'],
            ['http://www.google.com/foo?q={param}'],
            [self::stringable('http://www.google.com/{param}/foo')],
        ];
    }

    public static function notTemplatedHrefProvider()
    {
        return [
            ['http://www.google.com/foo'],
            ['/foo/bar/baz'],
            [self::stringable('http://www.google.com/foo')],
        ];
    }

    private static function stringable(string $value): \Stringable
    {
        return new class($value) implements \Stringable {
            public function __construct(private string $value)
            {
            }

            public function __toString(): string
            {
                return $this->value;
            }
        };
    }
}
