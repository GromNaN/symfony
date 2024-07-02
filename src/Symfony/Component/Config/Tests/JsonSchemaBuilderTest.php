<?php

namespace Symfony\Component\Config\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\JsonSchemaBuilder;

class JsonSchemaBuilderTest extends TestCase
{
    public function testBuild()
    {
        $configurations = [
            new \Symfony\Bundle\DebugBundle\DependencyInjection\Configuration(),
            new \Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration(true),
            new \Symfony\Bundle\TwigBundle\DependencyInjection\Configuration(),
            new \Doctrine\Bundle\DoctrineBundle\DependencyInjection\Configuration(true),
        ];

        $builder = new JsonSchemaBuilder();
        $schema = $builder->build(...$configurations);

        echo json_encode($schema, JSON_PRETTY_PRINT);
    }
}
