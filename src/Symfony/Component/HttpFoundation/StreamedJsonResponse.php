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
 * function loadArticles(): \Generator
 *     // some streamed loading
 *     yield ['title' => 'Article 1'];
 *     yield ['title' => 'Article 2'];
 *     yield ['title' => 'Article 3'];
 * }),
 *
 * $response = new StreamedJsonResponse(
 *     // json structure with generators in which will be streamed
 *     [
 *         '_embedded' => [
 *             'articles' => loadArticles(), // any generator which you want to stream as list of data
 *         ],
 *     ],
 * );
 */
class StreamedJsonResponse extends StreamedResponse
{
    public const DEFAULT_ENCODING_OPTIONS = JsonResponse::DEFAULT_ENCODING_OPTIONS;

    private int $encodingOptions = self::DEFAULT_ENCODING_OPTIONS;

    /**
     * @param mixed[]                        $data      JSON Data containing PHP generators which will be streamed as list of data
     * @param int                            $status    The HTTP status code (200 "OK" by default)
     * @param array<string, string|string[]> $headers   An array of HTTP headers
     * @param int                            $flushSize After every which item of a generator the flush function should be called
     */
    public function __construct(
        private readonly array $data,
        int $status = 200,
        array $headers = [],
        private int $flushSize = 100,
    ) {
        parent::__construct($this->stream(...), $status, $headers);

        if (!$this->headers->get('Content-Type')) {
            $this->headers->set('Content-Type', 'application/json');
        }
    }

    private function stream(): void
    {
        $generators = [];
        $structure = $this->data;

        array_walk_recursive($structure, function (&$item, $key) use (&$generators) {
            // generators should be used but for better DX all kind of Traversable are supported
            if ($item instanceof \Traversable && !$item instanceof \JsonSerializable) {
                // using uniqid to avoid conflict with eventually other data in the structure
                $placeholder = uniqid('__placeholder_', true);
                $generators[$placeholder] = $item;

                $item = $placeholder;
            }
        });

        $jsonEncodingOptions = \JSON_THROW_ON_ERROR | $this->getEncodingOptions();
        $structureText = json_encode($structure, $jsonEncodingOptions);

        foreach (array_keys($generators) as $placeholder) {
            // split structure json by placeholder for stream the before and between part of the generator items
            [$start, $end] = explode('"'.$placeholder.'"', $structureText, 2);

            // send first and between parts of the structure
            echo $start;

            $count = 0;
            $startTag = '[';
            foreach ($generators[$placeholder] as $key => $item) {
                if (0 === $count) {
                    // depending of the first elements key the generator is detected as a list or map
                    // we can not check for a whole list or map because that would hurt the performance
                    // of the streamed response which is the main goal of this response class
                    if ($key !== 0) {
                        $startTag = '{';
                    }

                    echo $startTag;
                } else {
                    // if not first element of the generic a separator is required between the elements
                    echo ',';
                }

                if ($startTag === '{') {
                    echo json_encode($key, $jsonEncodingOptions) . ':';
                }

                echo json_encode($item, $jsonEncodingOptions);
                ++$count;

                if (0 === $count % $this->flushSize) {
                    flush();
                }
            }

            echo ($startTag === '[' ? ']' : '}');

            $structureText = $end;
        }

        echo $structureText; // send the after part of the structure json as last
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
     * Returns the flush size.
     */
    public function getFlushSize(): int
    {
        return $this->flushSize;
    }

    /**
     * Sets the flush size.
     *
     * @return $this
     */
    public function setFlushSize(int $flushSize): static
    {
        $this->flushSize = $flushSize;

        return $this;
    }
}
