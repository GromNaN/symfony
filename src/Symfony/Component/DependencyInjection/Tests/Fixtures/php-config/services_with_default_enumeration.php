<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('service_container', \Symfony\Component\DependencyInjection\ContainerInterface::class)
            ->public()
            ->synthetic()
            ->share()
        ->set('foo', \Symfony\Component\DependencyInjection\Tests\Fixtures\FooClassWithDefaultEnumAttribute::class)
            ->public()
            ->autowire()
            ->arg('secondOptional', true)
            ->share();
};