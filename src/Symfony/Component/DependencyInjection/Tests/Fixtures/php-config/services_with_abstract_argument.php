<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('service_container', \Symfony\Component\DependencyInjection\ContainerInterface::class)
            ->public()
            ->synthetic()
            ->share()
        ->set(\Symfony\Component\DependencyInjection\Tests\Fixtures\FooWithAbstractArgument::class)
            ->arg('$baz', abstract_arg('should be defined by Pass'))
            ->arg('$bar', 'test')
            ->share();
};