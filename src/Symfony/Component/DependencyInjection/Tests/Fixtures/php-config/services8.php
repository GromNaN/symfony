<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('foo', '%baz%')
        ->set('baz', 'bar')
        ->set('bar', 'foo is %%foo bar')
        ->set('escape', '@escapeme')
        ->set('values', [true, false, null, 0, 1000.3, 'true', 'false', 'null'])
        ->set('utf-8 valid string', "ț᭖\ttest")
        ->set('binary', hex2bin('f0f0f0f0'))
        ->set('binary-control-char', "This is a Bell char \a")
        ->set('console banner', "\033[37;44mHello\033[30;43mWorld\033[0m")
        ->set('null string', 'null')
        ->set('string of digits', '123')
        ->set('string of digits prefixed with minus character', '-123')
        ->set('true string', 'true')
        ->set('false string', 'false')
        ->set('binary number string', '0b0110')
        ->set('numeric string', '-1.2E2')
        ->set('hexadecimal number string', '0xFF')
        ->set('float string', '10100.1')
        ->set('positive float string', '+10100.1')
        ->set('negative float string', '-10100.1');

    $container->services()
        ->set('service_container', \Symfony\Component\DependencyInjection\ContainerInterface::class)
            ->public()
            ->synthetic()
            ->share();
};