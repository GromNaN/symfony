<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('service_container', \Symfony\Component\DependencyInjection\ContainerInterface::class)
            ->public()
            ->synthetic()
            ->share()
        ->set('foo_service', 'Foo')
            ->tag('foo')
            ->share()
        ->set('baz_service', 'Baz')
            ->tag('foo')
            ->share()
        ->set('qux_service', 'Qux')
            ->tag('foo')
            ->share()
        ->set('foo_service_tagged_iterator', 'Bar')
            ->args([
                tagged_iterator('foo', indexAttribute: 'barfoo', defaultIndexMethod: 'foobar', defaultPriorityMethod: 'getPriority'),
            ])
            ->share()
        ->set('foo2_service_tagged_iterator', 'Bar')
            ->args([
                tagged_iterator('foo', exclude: ['baz']),
            ])
            ->share()
        ->set('foo3_service_tagged_iterator', 'Bar')
            ->args([
                tagged_iterator('foo', exclude: ['baz', 'qux'], excludeSelf: false),
            ])
            ->share()
        ->set('foo_service_tagged_locator', 'Bar')
            ->args([
                tagged_iterator('foo', indexAttribute: 'barfoo', defaultIndexMethod: 'foobar', defaultPriorityMethod: 'getPriority'),
            ])
            ->share()
        ->set('foo2_service_tagged_locator', 'Bar')
            ->args([
                tagged_iterator('foo', exclude: ['baz']),
            ])
            ->share()
        ->set('foo3_service_tagged_locator', 'Bar')
            ->args([
                tagged_iterator('foo', exclude: ['baz', 'qux'], excludeSelf: false),
            ])
            ->share()
        ->set('bar_service_tagged_locator', 'Bar')
            ->args([
                tagged_iterator('foo'),
            ])
            ->share();
};