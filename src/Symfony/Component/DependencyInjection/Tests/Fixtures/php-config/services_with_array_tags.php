<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('service_container', \Symfony\Component\DependencyInjection\ContainerInterface::class)
            ->public()
            ->synthetic()
            ->share()
        ->set('foo', \Bar\FooClass::class)
            ->tag('foo_tag', ['name' => 'attributeName', 'foo' => 'bar', 'bar' => ['foo' => 'bar', 'bar' => 'foo']])
            ->share();
};