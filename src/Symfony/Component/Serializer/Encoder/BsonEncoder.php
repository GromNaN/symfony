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
use MongoDB\BSON\PackedArray;
use MongoDB\Driver\Exception\Exception as DriverException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\RuntimeException;

/**
 * Encodes data as a BSON document.
 *
 * The returned string holds the raw bytes of the document, as stored by MongoDB.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class BsonEncoder implements EncoderInterface
{
    public const FORMAT = 'bson';

    public function __construct()
    {
        if (!class_exists(Document::class)) {
            throw new RuntimeException('The BsonEncoder class requires the "mongodb" extension. Try running "pie install mongodb/mongodb-extension".');
        }
    }

    public function encode(mixed $data, string $format, array $context = []): string
    {
        if (!\is_array($data) && !\is_object($data)) {
            throw new NotEncodableValueException(\sprintf('Only arrays and objects can be encoded to BSON, "%s" given.', get_debug_type($data)));
        }

        try {
            if (\is_array($data) && array_is_list($data)) {
                return (string) PackedArray::fromPHP($data);
            }

            return (string) Document::fromPHP($data);
        } catch (DriverException $e) {
            throw new NotEncodableValueException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function supportsEncoding(string $format): bool
    {
        return self::FORMAT === $format;
    }
}
