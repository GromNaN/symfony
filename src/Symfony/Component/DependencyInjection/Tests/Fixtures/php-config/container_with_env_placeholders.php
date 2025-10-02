<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set(env('PARAMETER_NAME'), env('PARAMETER_VALUE'));

    $container->services()
        ->set('service_container', \Symfony\Component\DependencyInjection\ContainerInterface::class)
            ->public()
            ->synthetic()
            ->share()
        ->set('service', env('SERVICE_CLASS'))
            ->public()
            ->file(env('SERVICE_FILE'))
            ->args([
                env('SERVICE_ARGUMENT'),
            ])
            ->property(env('SERVICE_PROPERTY_NAME'), env('SERVICE_PROPERTY_VALUE'))
            ->call(env('SERVICE_METHOD_NAME'), [env('SERVICE_METHOD_ARGUMENT')])
            ->share()
            ->factory(env('SERVICE_FACTORY'))
            ->configurator(env('SERVICE_CONFIGURATOR'));
};