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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\WebLink\HttpHeaderParser;

class HttpHeaderParserTest extends TestCase
{
    public function testParse()
    {
        $parser = new HttpHeaderParser();

        $header = [
            '</1>; rel="prerender",</2>; rel="dns-prefetch"; pr="0.7",</3>; rel="preload"; as="script"',
            '</4>; rel="preload"; as="image"; nopush,</5>; rel="alternate next"; hreflang="fr"; hreflang="de"; title="Hello"'
        ];
        $provider = $parser->parse($header);
        $links = $provider->getLinks();

        self::assertCount(5, $links);

        self::assertSame(['prerender'], $links[0]->getRels());
        self::assertSame('/1', $links[0]->getHref());
        self::assertSame(null, $links[0]->getAttribute('rel'));

        self::assertSame(['dns-prefetch'], $links[1]->getRels());
        self::assertSame('/2', $links[1]->getHref());
        self::assertSame('0.7', $links[1]->getAttribute('pr'));

        self::assertSame(['preload'], $links[2]->getRels());
        self::assertSame('/3', $links[2]->getHref());
        self::assertSame('script', $links[2]->getAttribute('as'));
        self::assertSame(null, $links[2]->getAttribute('nopush'));

        self::assertSame(['preload'], $links[3]->getRels());
        self::assertSame('/4', $links[3]->getHref());
        self::assertSame('image', $links[3]->getAttribute('as'));
        self::assertSame(true, $links[3]->getAttribute('nopush'));

        self::assertSame(['alternate', 'next'], $links[4]->getRels());
        self::assertSame('/5', $links[4]->getHref());
        self::assertSame(['fr', 'de'], $links[4]->getAttribute('hreflang'));
        self::assertSame('Hello', $links[4]->getAttribute('title'));
    }

    public function testParseEmpty()
    {
        $parser = new HttpHeaderParser();
        $provider = $parser->parse('');
        self::assertCount(0, $provider->getLinks());
    }

    /** @dataProvider provideHeaderParsingCases */
    #[DataProvider('provideHeaderParsingCases')]
    public function testParseVariousAttributes(string $header, array $expectedAttributes)
    {
        $parser = new HttpHeaderParser();
        $links = $parser->parse($header)->getLinks();

        self::assertCount(1, $links);
        self::assertSame(['alternate'], $links[0]->getRels());
        self::assertSame('/foo', $links[0]->getHref());
        self::assertSame($expectedAttributes, $links[0]->getAttributes());
    }

    public static function provideHeaderParsingCases()
    {
        yield 'double_quotes_in_attribute_value' => [
            '</foo>; rel="alternate"; title="\"escape me\" \"already escaped\" \"\"\""',
            ['title' => '"escape me" "already escaped" """'],
        ];

        yield 'unquoted_attribute_value' => [
            '</foo>; rel=alternate; type=text/html',
            ['type' => 'text/html'],
        ];

        yield 'attribute_with_punctuation' => [
            '</foo>; rel="alternate"; title=">; hello, world; test:case"',
            ['title' => '>; hello, world; test:case'],
        ];
    }
}
