<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

use Generator;

/**
 * StreamedJsonResponse represents a streamed HTTP response for JSON.
 *
 * A StreamedJsonResponse uses a structure and generics to create an
 * efficient resource saving JSON response.
 *
 * It uses flush after a specified flush size to directly stream the data.
 *
 * @see flush()
 *
 * @author Alexander Schranz <alexander@sulu.io>
 *
 * Example usage:
 *
 * $response = new StreamedJsonResponse(
 *     // json structure with replace identifiers
 *     [
 *         '_embedded' => [
 *             'articles' => '__articles__',
 *         ],
 *     ],
 *     // array of generators with replace identifier used as key
 *     [
 *         '__articles__' => (function (): \Generator { // any method or function returning a Generator
 *              yield ['title' => 'Article 1'];
 *              yield ['title' => 'Article 2'];
 *              yield ['title' => 'Article 3'];
 *         })(),
 *     ]
 * );
 */
class StreamedJsonResponse extends StreamedResponse
{
    public const DEFAULT_ENCODING_OPTIONS = JsonResponse::DEFAULT_ENCODING_OPTIONS;

    protected int $encodingOptions = self::DEFAULT_ENCODING_OPTIONS;

    /**
     * @param mixed[] $structure
     * @param array<string, Generator<int|string, mixed> $generics
     */
    public function __construct(
        private readonly array $structure,
        private readonly array $generics,
        int $status = 200,
        array $headers = [],
        private int $flushSize = 500,
    ) {
        parent::__construct(fn () => $this->stream(), $status, $headers);

        if (!$this->headers->get('Content-Type')) {
            $this->headers->set('Content-Type', 'application/json');
        }
    }

    private function stream(): void
    {
        $keys = array_keys($this->generics);

        $jsonEncodingOptions = \JSON_THROW_ON_ERROR | $this->getEncodingOptions();
        $structureText = json_encode($this->structure, $jsonEncodingOptions);

        foreach ($keys as $key) {
            [$start, $end] = explode('"'.$key.'"', $structureText, 2);

            // send first and between parts of the structure
            echo $start;

            $count = 0;
            echo '[';
            foreach ($this->generics[$key] as $array) {
                if (0 !== $count) {
                    // if not first element of the generic a separator is required between the elements
                    echo ',';
                }

                echo json_encode($array, $jsonEncodingOptions);
                ++$count;

                if (0 === $count % $this->flushSize) {
                    flush();
                }
            }
            echo ']';

            $structureText = $end;
        }

        echo $structureText; // send the after part of the structure as last
    }

    /**
     * Returns options used while encoding data to JSON.
     */
    public function getEncodingOptions(): int
    {
        return $this->encodingOptions;
    }

    /**
     * Sets options used while encoding data to JSON.
     *
     * @return $this
     */
    public function setEncodingOptions(int $encodingOptions): static
    {
        $this->encodingOptions = $encodingOptions;

        return $this;
    }

    /**
     * Sets the flush size.
     */
    public function getFlushSize(): int
    {
        return $this->flushSize;
    }

    /**
     * Returns the flush size.
     *
     * @return $this
     */
    public function setFlushSize(int $flushSize): static
    {
        $this->flushSize = $flushSize;

        return $this;
    }
}
