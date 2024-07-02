<?php

namespace Symfony\Component\Config;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\NumericNode;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

class JsonSchemaBuilder
{
    public function build(ConfigurationInterface ...$configurations): array
    {
        $properties = [];
        foreach ($configurations as $configuration) {
            $rootNode = $configuration->getConfigTreeBuilder()->buildTree();
            $properties[$rootNode->getName()] = $this->buildNode($rootNode)
                + ['description' => get_class($configuration)];
        }

        $schema = [
            '$schema' => 'http://json-schema.org/draft-07/schema',
            'allOf' => [
                ['$ref' => '#/definitions/Config'],
                ['$ref' => '#/definitions/WhenEnv'],
            ],
            'definitions' => [
                'Config' => [
                    'description' => 'Configuration for the Symfony application',
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => $properties,
                ],
                // We need to merge the schema from all the environments.
                // Because some bundles may be enabled in test, but not in dev.
                'WhenEnv' => [
                    'description' => 'Configuration for a specific environment',
                    'type' => 'object',
                    'additionalProperties' => false,
                    'patternProperties' => [
                        '^when@' => [
                            '$ref' => '#/definitions/Config',
                        ],
                    ],
                ],
            ],
        ];

        return $schema;
    }

    private function buildNode(NodeInterface $node): array
    {
        if ($node instanceof PrototypedArrayNode) {
            $schema = [
                'type' => ['array', 'null'],
                'items' => $this->buildNode($node->getPrototype()),
            ];

            if ($description = $node->getInfo()) {
                $schema['description'] = $description;
            }

            foreach ($node->getChildren() as $childNode) {
                $schema['items']['properties'][$childNode->getName()] = $this->buildNode($childNode);
            }

            return $schema;
        }

        if ($node instanceof ArrayNode) {
            $schema = [
                'type' => 'object',
                'properties' => [],
            ];

            if ($description = $node->getInfo()) {
                $schema['description'] = $description;
            }

            foreach ($node->getChildren() as $childNode) {
                $schema['properties'][$childNode->getName()] = $this->buildNode($childNode);
            }

            return $schema;
        }

        if ($node instanceof NumericNode) {
            // Always accept "string" for parameters and environment variables.
            // @todo add regex check for %env()% of %param%.
            $schema['type'] = ['string', 'number'];
        } elseif ($node instanceof BooleanNode) {
            $schema['type'] = ['string', 'boolean'];
        } elseif ($node instanceof EnumNode) {
            $schema['type'] = 'string';
            $schema['enum'] = $node->getValues();
        } else {
            $schema['type'] = 'string';
        }

        // Allow null values for optional nodes, so that they can be unset.
        if (!$node->isRequired()) {
            $schema['type'] = array_merge((array) $schema['type'], ['null']);
        }


        if ($description = $node->getInfo()) {
            $schema['description'] = $description;
        }
        return $schema;
    }
}
