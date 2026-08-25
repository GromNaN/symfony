<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Encoder;

use MongoDB\BSON\Document;
use MongoDB\Driver\Exception\Exception as DriverException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\RuntimeException;

/**
 * Decodes the raw bytes of a BSON document or array.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class BsonDecoder implements DecoderInterface
{
    public const FORMAT = 'bson';

    /**
     * The type map applied when converting the BSON document to PHP values.
     *
     * @see https://www.php.net/manual/en/mongodb.persistence.deserialization.php
     */
    public const TYPE_MAP = 'bson_type_map';

    private array $defaultContext = [
        self::TYPE_MAP => [
            'root' => 'object',
            'document' => 'object',
            'array' => 'array',
        ],
    ];

    public function __construct(array $defaultContext = [])
    {
        if (!class_exists(Document::class)) {
            throw new RuntimeException('The BsonDecoder class requires the "mongodb" extension. Try running "pie install mongodb/mongodb-extension".');
        }

        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    public function decode(string $data, string $format, array $context = []): mixed
    {
        $context = array_merge($this->defaultContext, $context);
        $typeMap = $context[self::TYPE_MAP];

        try {
            $document = Document::fromBSON($data);

            if (self::isBsonArray($document)) {
                // A BSON array is a document with sequential keys: the "array"
                // type is applied to the root, as for any nested BSON array.
                $typeMap['root'] = $typeMap['array'] ?? 'array';
            }

            return $document->toPHP($typeMap);
        } catch (DriverException $e) {
            throw new NotEncodableValueException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function supportsDecoding(string $format): bool
    {
        return self::FORMAT === $format;
    }

    /**
     * Tells whether the document holds the keys of a BSON array, as produced by
     * BsonEncoder for a PHP list. An empty document is read as an empty list.
     */
    private static function isBsonArray(Document $document): bool
    {
        $index = 0;
        foreach ($document as $key => $value) {
            if ($key !== (string) $index++) {
                return false;
            }
        }

        return true;
    }
}
