<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\ParameterBag;

class ParameterBagTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testConstructor()
    {
        $this->testAll();
    }

    public function testAll()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $this->assertEquals(['foo' => 'bar'], $bag->all(), '->all() gets all the input');
    }

    public function testAllWithInputKey()
    {
        $bag = new ParameterBag(['foo' => ['bar', 'baz'], 'null' => null]);

        $this->assertEquals(['bar', 'baz'], $bag->all('foo'), '->all() gets the value of a parameter');
        $this->assertEquals([], $bag->all('unknown'), '->all() returns an empty array if a parameter is not defined');
    }

    public function testAllThrowsForNonArrayValues()
    {
        $this->expectException(BadRequestException::class);
        $bag = new ParameterBag(['foo' => 'bar', 'null' => null]);
        $bag->all('foo');
    }

    public function testKeys()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $this->assertEquals(['foo'], $bag->keys());
    }

    public function testAdd()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $bag->add(['bar' => 'bas']);
        $this->assertEquals(['foo' => 'bar', 'bar' => 'bas'], $bag->all());
    }

    public function testRemove()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $bag->add(['bar' => 'bas']);
        $this->assertEquals(['foo' => 'bar', 'bar' => 'bas'], $bag->all());
        $bag->remove('bar');
        $this->assertEquals(['foo' => 'bar'], $bag->all());
    }

    public function testReplace()
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        $bag->replace(['FOO' => 'BAR']);
        $this->assertEquals(['FOO' => 'BAR'], $bag->all(), '->replace() replaces the input with the argument');
        $this->assertFalse($bag->has('foo'), '->replace() overrides previously set the input');
    }

    public function testGet()
    {
        $bag = new ParameterBag(['foo' => 'bar', 'null' => null]);

        $this->assertEquals('bar', $bag->get('foo'), '->get() gets the value of a parameter');
        $this->assertEquals('default', $bag->get('unknown', 'default'), '->get() returns second argument as default if a parameter is not defined');
        $this->assertNull($bag->get('null', 'default'), '->get() returns null if null is set');
    }

    public function testGetDoesNotUseDeepByDefault()
    {
        $bag = new ParameterBag(['foo' => ['bar' => 'moo']]);

        $this->assertNull($bag->get('foo[bar]'));
    }

    public function testSet()
    {
        $bag = new ParameterBag([]);

        $bag->set('foo', 'bar');
        $this->assertEquals('bar', $bag->get('foo'), '->set() sets the value of parameter');

        $bag->set('foo', 'baz');
        $this->assertEquals('baz', $bag->get('foo'), '->set() overrides previously set parameter');
    }

    public function testHas()
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        $this->assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
        $this->assertFalse($bag->has('unknown'), '->has() return false if a parameter is not defined');
    }

    public function testGetAlpha()
    {
        $bag = new ParameterBag(['word' => 'foo_BAR_012']);

        $this->assertEquals('fooBAR', $bag->getAlpha('word'), '->getAlpha() gets only alphabetic characters');
        $this->assertEquals('', $bag->getAlpha('unknown'), '->getAlpha() returns empty string if a parameter is not defined');
    }

    public function testGetAlnum()
    {
        $bag = new ParameterBag(['word' => 'foo_BAR_012']);

        $this->assertEquals('fooBAR012', $bag->getAlnum('word'), '->getAlnum() gets only alphanumeric characters');
        $this->assertEquals('', $bag->getAlnum('unknown'), '->getAlnum() returns empty string if a parameter is not defined');
    }

    public function testGetDigits()
    {
        $bag = new ParameterBag(['word' => 'foo_BAR_012']);

        $this->assertEquals('012', $bag->getDigits('word'), '->getDigits() gets only digits as string');
        $this->assertEquals('', $bag->getDigits('unknown'), '->getDigits() returns empty string if a parameter is not defined');
    }

    /**
     * @group legacy
     */
    public function testGetInt()
    {
        $this->expectDeprecation('Since symfony/http-foundation 6.2: Values returned by "Symfony\Component\HttpFoundation\ParameterBag::getInt()" are not reliable. Use "getInteger()" instead.');

        $bag = new ParameterBag(['digits' => '0123', 'array' => ['5']]);

        $this->assertEquals(123, $bag->getInt('digits'), '->getInt() gets a value of parameter as integer');
        $this->assertEquals(0, $bag->getInt('unknown'), '->getInt() returns zero if a parameter is not defined');
        $this->assertEquals(1, $bag->getInt('array'), '->getInt() returns 1 if a parameter is a non-empty array');
    }

    public function testGetInteger()
    {
        $bag = new ParameterBag(['int' => '123', 'digits' => '0123', 'array' => ['123'], 'string' => 'abc', 'float' => '1.2']);

        $this->assertSame(123, $bag->getInteger('int', 5), '->getInteger() gets a value of parameter as integer');
        $this->assertSame(5, $bag->getInteger('digits', 5), '->getInteger() returns the default if a parameter is not an integer');
        $this->assertSame(5, $bag->getInteger('unknown', 5), '->getInteger() returns the default if a parameter is not defined');
        $this->assertSame(5, $bag->getInteger('array', 5), '->getInteger() returns the default if a parameter is an array');
        $this->assertSame(5, $bag->getInteger('string', 5), '->getInteger() returns the default if a parameter is not numeric');
        $this->assertSame(5, $bag->getInteger('float', 5), '->getInteger() returns the default if a parameter is a float');
    }

    public function testGetString()
    {
        $bag = new ParameterBag(['int' => 123, 'digits' => '0123', 'array' => ['123'], 'string' => 'abc', 'float' => 1.2]);

        $this->assertSame('abc', $bag->getString('string', 'default'), '->getInteger() returns a value of a parameter as string');
        $this->assertSame('123', $bag->getString('int', 'default'), '->getInteger() returns integer casted to string');
        $this->assertSame('1.2', $bag->getString('float', 'default'), '->getInteger() returns float casted to string');
        $this->assertSame('0123', $bag->getString('digits', 'default'), '->getInteger() returns the default if a parameter is not an integer');
        $this->assertSame('default', $bag->getString('unknown', 'default'), '->getInteger() returns the default if a parameter is not defined');
        $this->assertSame('default', $bag->getString('array', 'default'), '->getInteger() returns the default if a parameter is an array');
    }

    public function testFilter()
    {
        $bag = new ParameterBag([
            'digits' => '0123ab',
            'email' => 'example@example.com',
            'url' => 'http://example.com/foo',
            'dec' => '256',
            'hex' => '0x100',
            'array' => ['bang'],
            ]);

        $this->assertEmpty($bag->filter('nokey'), '->filter() should return empty by default if no key is found');

        $this->assertEquals('0123', $bag->filter('digits', '', \FILTER_SANITIZE_NUMBER_INT), '->filter() gets a value of parameter as integer filtering out invalid characters');

        $this->assertEquals('example@example.com', $bag->filter('email', '', \FILTER_VALIDATE_EMAIL), '->filter() gets a value of parameter as email');

        $this->assertEquals('http://example.com/foo', $bag->filter('url', '', \FILTER_VALIDATE_URL, ['flags' => \FILTER_FLAG_PATH_REQUIRED]), '->filter() gets a value of parameter as URL with a path');

        // This test is repeated for code-coverage
        $this->assertEquals('http://example.com/foo', $bag->filter('url', '', \FILTER_VALIDATE_URL, \FILTER_FLAG_PATH_REQUIRED), '->filter() gets a value of parameter as URL with a path');

        $this->assertFalse($bag->filter('dec', '', \FILTER_VALIDATE_INT, [
            'flags' => \FILTER_FLAG_ALLOW_HEX,
            'options' => ['min_range' => 1, 'max_range' => 0xFF],
        ]), '->filter() gets a value of parameter as integer between boundaries');

        $this->assertFalse($bag->filter('hex', '', \FILTER_VALIDATE_INT, [
            'flags' => \FILTER_FLAG_ALLOW_HEX,
            'options' => ['min_range' => 1, 'max_range' => 0xFF],
        ]), '->filter() gets a value of parameter as integer between boundaries');

        $this->assertEquals(['bang'], $bag->filter('array', ''), '->filter() gets a value of parameter as an array');
    }

    public function testFilterCallback()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A Closure must be passed to "Symfony\Component\HttpFoundation\ParameterBag::filter()" when FILTER_CALLBACK is used, "string" given.');

        $bag = new ParameterBag(['foo' => 'bar']);
        $bag->filter('foo', null, \FILTER_CALLBACK, ['options' => 'strtoupper']);
    }

    public function testFilterClosure()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $result = $bag->filter('foo', null, \FILTER_CALLBACK, ['options' => function ($value) {
            return strtoupper($value);
        }]);

        $this->assertSame('BAR', $result);
    }

    public function testGetIterator()
    {
        $parameters = ['foo' => 'bar', 'hello' => 'world'];
        $bag = new ParameterBag($parameters);

        $i = 0;
        foreach ($bag as $key => $val) {
            ++$i;
            $this->assertEquals($parameters[$key], $val);
        }

        $this->assertEquals(\count($parameters), $i);
    }

    public function testCount()
    {
        $parameters = ['foo' => 'bar', 'hello' => 'world'];
        $bag = new ParameterBag($parameters);

        $this->assertCount(\count($parameters), $bag);
    }

    public function testGetBoolean()
    {
        $parameters = ['string_true' => 'true', 'string_false' => 'false'];
        $bag = new ParameterBag($parameters);

        $this->assertTrue($bag->getBoolean('string_true'), '->getBoolean() gets the string true as boolean true');
        $this->assertFalse($bag->getBoolean('string_false'), '->getBoolean() gets the string false as boolean false');
        $this->assertFalse($bag->getBoolean('unknown'), '->getBoolean() returns false if a parameter is not defined');
        $this->assertTrue($bag->getBoolean('unknown', true), '->getBoolean() returns default if a parameter is not defined');
    }
}
