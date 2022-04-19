<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableResourceStream;
use Amp\Cancellation;
use Amp\Http\Client\RequestBody;
use Amp\Promise;
use Amp\Success;
use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class AmpBody implements RequestBody, ReadableStream
{
    private ReadableStream|\Closure|string $body;
    private array $info;
    private \Closure $onProgress;
    private ?int $offset = 0;
    private int $length = -1;
    private ?int $uploaded = null;

    /**
     * @param \Closure|resource|string $body
     */
    public function __construct($body, &$info, \Closure $onProgress)
    {
        $this->info = &$info;
        $this->onProgress = $onProgress;

        if (\is_resource($body)) {
            $this->offset = ftell($body);
            $this->length = fstat($body)['size'];
            $this->body = new ReadableResourceStream($body);
        } elseif (\is_string($body)) {
            $this->length = \strlen($body);
            $this->body = $body;
        } else {
            $this->body = $body;
        }
    }

    public function createBodyStream(): ReadableStream
    {
        if (null !== $this->uploaded) {
            $this->uploaded = null;

            if (\is_string($this->body)) {
                $this->offset = 0;
            } elseif ($this->body instanceof ReadableResourceStream) {
                fseek($this->body->getResource(), $this->offset);
            }
        }

        return $this;
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function getBodyLength(): ?int
    {
        return $this->length - $this->offset;
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        $this->info['size_upload'] += $this->uploaded;
        $this->uploaded = 0;
        ($this->onProgress)();

        $chunk = $this->doRead();
        if (null !== $chunk) {
            $this->uploaded = \strlen($chunk);
        } else {
            $this->info['upload_content_length'] = $this->info['size_upload'];
        }

        return $chunk;
    }

    public static function rewind(RequestBody $body): RequestBody
    {
        if (!$body instanceof self) {
            return $body;
        }

        $body->uploaded = null;

        if ($body->body instanceof ReadableResourceStream) {
            fseek($body->body->getResource(), $body->offset);

            return new $body($body->body, $body->info, $body->onProgress);
        }

        if (\is_string($body->body)) {
            $body->offset = 0;
        }

        return $body;
    }

    private function doRead(): ?string
    {
        if ($this->body instanceof ReadableResourceStream) {
            return $this->body->read();
        }

        if (null === $this->offset || !$this->length) {
            return null;
        }

        if (\is_string($this->body)) {
            $this->offset = null;

            return $this->body;
        }

        if ('' === $data = ($this->body)(16372)) {
            $this->offset = null;

            return null;
        }

        if (!\is_string($data)) {
            throw new TransportException(sprintf('Return value of the "body" option callback must be string, "%s" returned.', get_debug_type($data)));
        }

        return $data;
    }

    public function close(): void
    {
        $this->body->close();
    }

    public function isClosed(): bool
    {
        return $this->body->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->onClose($onClose);
    }

    public function isReadable(): bool
    {
        return $this->body->isReadable();
    }
}
