<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('unit_enum', \Symfony\Component\DependencyInjection\Tests\Fixtures\FooUnitEnum::BAR)
        ->set('enum_array', [\Symfony\Component\DependencyInjection\Tests\Fixtures\FooUnitEnum::BAR, \Symfony\Component\DependencyInjection\Tests\Fixtures\FooUnitEnum::FOO]);

    $container->services()
        ->set('service_container', \Symfony\Component\DependencyInjection\ContainerInterface::class)
            ->public()
            ->synthetic()
            ->share()
        ->set(\Symfony\Component\DependencyInjection\Tests\Fixtures\FooClassWithEnumAttribute::class)
            ->public()
            ->args([
                \Symfony\Component\DependencyInjection\Tests\Fixtures\FooUnitEnum::BAR,
            ])
            ->share();
};