<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Dumper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\AutowirePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDslDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooClassWithDefaultArrayAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooClassWithDefaultEnumAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooClassWithDefaultObjectAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooClassWithEnumAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooUnitEnum;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooWithAbstractArgument;


class PhpDslDumperTest extends TestCase
{
    protected static string $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
    }

    public function testDump()
    {
        $dumper = new PhpDslDumper(new ContainerBuilder());

        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services1.php', $dumper->dump(), '->dump() dumps an empty container as an empty YAML file');
    }

    public function testAddParameters()
    {
        $container = include self::$fixturesPath.'/containers/container8.php';
        $dumper = new PhpDslDumper($container);
        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services8.php', $dumper->dump(), '->dump() dumps parameters');
    }

    public function testAddService()
    {
        $container = include self::$fixturesPath.'/containers/container9.php';
        $dumper = new PhpDslDumper($container);
        //$this->assertEqualsFile(str_replace('%path%', self::$fixturesPath.\DIRECTORY_SEPARATOR.'includes'.\DIRECTORY_SEPARATOR, file_get_contents(self::$fixturesPath.'/php-config/services9.php'), $dumper->dump(), '->dump() dumps services');
        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services9.php', $dumper->dump(), '->dump() dumps services');

        $dumper = new PhpDslDumper($container = new ContainerBuilder());
        $container->register('foo', 'FooClass')->addArgument(new \stdClass())->setPublic(true);
        try {
            $dumper->dump();
            $this->fail('->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e, '->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
            $this->assertEquals('Unable to dump a service container if a parameter is an object or a resource, got "stdClass".', $e->getMessage(), '->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
        }
    }

    public function testDumpAutowireData()
    {
        $container = include self::$fixturesPath.'/containers/container24.php';
        $dumper = new PhpDslDumper($container);
        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services24.php', $dumper->dump());
    }

    public function testDumpDecoratedServices()
    {
        $container = include self::$fixturesPath.'/containers/container34.php';
        $dumper = new PhpDslDumper($container);
        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services34.php', $dumper->dump());
    }

    public function testDumpLoad()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_dump_load.yml');

        $this->assertEquals([new Reference('bar', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE)], $container->getDefinition('foo')->getArguments());

        $dumper = new PhpDslDumper($container);
        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services_dump_load.php', $dumper->dump());
    }

    public function testInlineServices()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'Class1')
            ->setPublic(true)
            ->addArgument((new Definition('Class2'))
                ->addArgument(new Definition('Class2'))
            )
        ;

        $dumper = new PhpDslDumper($container);
        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services_inline.php', $dumper->dump());
    }

    public function testTaggedArguments()
    {
        $taggedIterator = new TaggedIteratorArgument('foo', 'barfoo', 'foobar', false, 'getPriority');
        $taggedIterator2 = new TaggedIteratorArgument('foo', null, null, false, null, ['baz']);
        $taggedIterator3 = new TaggedIteratorArgument('foo', null, null, false, null, ['baz', 'qux'], false);

        $container = new ContainerBuilder();

        $container->register('foo_service', 'Foo')->addTag('foo');
        $container->register('baz_service', 'Baz')->addTag('foo');
        $container->register('qux_service', 'Qux')->addTag('foo');

        $container->register('foo_service_tagged_iterator', 'Bar')->addArgument($taggedIterator);
        $container->register('foo2_service_tagged_iterator', 'Bar')->addArgument($taggedIterator2);
        $container->register('foo3_service_tagged_iterator', 'Bar')->addArgument($taggedIterator3);

        $container->register('foo_service_tagged_locator', 'Bar')->addArgument(new ServiceLocatorArgument($taggedIterator));
        $container->register('foo2_service_tagged_locator', 'Bar')->addArgument(new ServiceLocatorArgument($taggedIterator2));
        $container->register('foo3_service_tagged_locator', 'Bar')->addArgument(new ServiceLocatorArgument($taggedIterator3));
        $container->register('bar_service_tagged_locator', 'Bar')->addArgument(new ServiceLocatorArgument(new TaggedIteratorArgument('foo')));

        $dumper = new PhpDslDumper($container);
        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services_with_tagged_argument.php', $dumper->dump());
    }

    public function testServiceClosure()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'Foo')
            ->addArgument(new ServiceClosureArgument(new Reference('bar', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)))
        ;

        $dumper = new PhpDslDumper($container);
        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services_with_service_closure.php', $dumper->dump());
    }

    public function testDumpHandlesEnumeration()
    {
        $container = new ContainerBuilder();
        $container
            ->register(FooClassWithEnumAttribute::class, FooClassWithEnumAttribute::class)
            ->setPublic(true)
            ->addArgument(FooUnitEnum::BAR);

        $container->setParameter('unit_enum', FooUnitEnum::BAR);
        $container->setParameter('enum_array', [FooUnitEnum::BAR, FooUnitEnum::FOO]);

        $container->compile();
        $dumper = new PhpDslDumper($container);

        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services_with_enumeration_enum_tag.php', $dumper->dump());
    }

    #[DataProvider('provideDefaultClasses')]
    public function testDumpHandlesDefaultAttribute($class, $expectedFile)
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo', $class)
            ->setPublic(true)
            ->setAutowired(true)
            ->setArguments([2 => true]);

        (new AutowirePass())->process($container);

        $dumper = new PhpDslDumper($container);

        $this->assertEqualsFile(self::$fixturesPath.'/php-config/'.$expectedFile, $dumper->dump());
    }

    public static function provideDefaultClasses()
    {
        yield [FooClassWithDefaultArrayAttribute::class, 'services_with_default_array.php'];
        yield [FooClassWithDefaultObjectAttribute::class, 'services_with_default_object.php'];
        yield [FooClassWithDefaultEnumAttribute::class, 'services_with_default_enumeration.php'];
    }

    public function testDumpServiceWithAbstractArgument()
    {
        $container = new ContainerBuilder();
        $container->register(FooWithAbstractArgument::class, FooWithAbstractArgument::class)
            ->setArgument('$baz', new AbstractArgument('should be defined by Pass'))
            ->setArgument('$bar', 'test');

        $dumper = new PhpDslDumper($container);
        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services_with_abstract_argument.php', $dumper->dump());
    }

    public function testDumpNonScalarTags()
    {
        $container = include self::$fixturesPath.'/containers/container_non_scalar_tags.php';
        $dumper = new PhpDslDumper($container);

        $this->assertEqualsFile(self::$fixturesPath.'/php-config/services_with_array_tags.php', $dumper->dump());
    }

    public function testDumpResolvedEnvPlaceholders()
    {
        $container = new ContainerBuilder();
        $container->setParameter('%env(PARAMETER_NAME)%', '%env(PARAMETER_VALUE)%');
        $container
            ->register('service', '%env(SERVICE_CLASS)%')
            ->setFile('%env(SERVICE_FILE)%')
            ->addArgument('%env(SERVICE_ARGUMENT)%')
            ->setProperty('%env(SERVICE_PROPERTY_NAME)%', '%env(SERVICE_PROPERTY_VALUE)%')
            ->addMethodCall('%env(SERVICE_METHOD_NAME)%', ['%env(SERVICE_METHOD_ARGUMENT)%'])
            ->setFactory('%env(SERVICE_FACTORY)%')
            ->setConfigurator('%env(SERVICE_CONFIGURATOR)%')
            ->setPublic(true)
        ;
        $container->compile();
        $dumper = new PhpDslDumper($container);

        $this->assertEqualsFile(self::$fixturesPath.'/php-config/container_with_env_placeholders.php', $dumper->dump());
    }

    public static function assertEqualsFile(string $expectedFile, string $actualString, string $message = ''): void
    {
        if (!getenv('DUMP_TESTS_GENERATE')) {
            parent::assertFileExists($expectedFile, $message);

            $expectedString = file_get_contents($expectedFile);
            $expectedString = str_replace('%path%', self::$fixturesPath.\DIRECTORY_SEPARATOR.'includes'.\DIRECTORY_SEPARATOR, $expectedString);

            parent::assertSame($expectedString, $actualString, $message);
        }

        if (!file_exists($dir = \dirname($expectedFile))) {
            mkdir($dir, 0777, false);
        }

        $actualString = str_replace(self::$fixturesPath.\DIRECTORY_SEPARATOR.'includes'.\DIRECTORY_SEPARATOR, '%path%', $actualString);

        file_put_contents($expectedFile, $actualString);
    }
}
