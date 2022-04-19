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

use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\Request;
use Amp\Promise;
use Amp\Success;
use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class AmpListener implements EventListener
{
    private array $info;
    private array $pinSha256;
    private \Closure $onProgress;
    private $handle;

    public function __construct(array &$info, array $pinSha256, \Closure $onProgress, &$handle)
    {
        $info += [
            'connect_time' => 0.0,
            'pretransfer_time' => 0.0,
            'starttransfer_time' => 0.0,
            'total_time' => 0.0,
            'namelookup_time' => 0.0,
            'primary_ip' => '',
            'primary_port' => 0,
        ];

        $this->info = &$info;
        $this->pinSha256 = $pinSha256;
        $this->onProgress = $onProgress;
        $this->handle = &$handle;
    }

    public function startRequest(Request $request): void
    {
        $this->info['start_time'] = $this->info['start_time'] ?? microtime(true);
        ($this->onProgress)();
    }

    public function startDnsResolution(Request $request): void
    {
        ($this->onProgress)();
    }

    public function startConnectionCreation(Request $request): void
    {
        ($this->onProgress)();
    }

    public function startTlsNegotiation(Request $request): void
    {
        ($this->onProgress)();
    }

    public function startSendingRequest(Request $request, Stream $stream): void
    {
        $host = (string) $stream->getRemoteAddress();

        if (str_contains($host, ':')) {
            $host = '['.$host.']';
        }

        $this->info['primary_ip'] = $host;
        $this->info['primary_port'] = $stream->getRemoteAddress()->getPort();
        $this->info['pretransfer_time'] = microtime(true) - $this->info['start_time'];
        $this->info['debug'] .= sprintf("* Connected to %s (%s) port %d\n", $request->getUri()->getHost(), $host, $this->info['primary_port']);

        if ((isset($this->info['peer_certificate_chain']) || $this->pinSha256) && null !== $tlsInfo = $stream->getTlsInfo()) {
            foreach ($tlsInfo->getPeerCertificates() as $cert) {
                $this->info['peer_certificate_chain'][] = openssl_x509_read($cert->toPem());
            }

            if ($this->pinSha256) {
                $pin = openssl_pkey_get_public($this->info['peer_certificate_chain'][0]);
                $pin = openssl_pkey_get_details($pin)['key'];
                $pin = \array_slice(explode("\n", $pin), 1, -2);
                $pin = base64_decode(implode('', $pin));
                $pin = base64_encode(hash('sha256', $pin, true));

                if (!\in_array($pin, $this->pinSha256, true)) {
                    throw new TransportException(sprintf('SSL public key does not match pinned public key for "%s".', $this->info['url']));
                }
            }
        }
        ($this->onProgress)();

        $uri = $request->getUri();
        $requestUri = $uri->getPath() ?: '/';

        if ('' !== $query = $uri->getQuery()) {
            $requestUri .= '?'.$query;
        }

        if ('CONNECT' === $method = $request->getMethod()) {
            $requestUri = $uri->getHost().': '.($uri->getPort() ?? ('https' === $uri->getScheme() ? 443 : 80));
        }

        $this->info['debug'] .= sprintf("> %s %s HTTP/%s \r\n", $method, $requestUri, $request->getProtocolVersions()[0]);

        foreach ($request->getRawHeaders() as [$name, $value]) {
            $this->info['debug'] .= $name.': '.$value."\r\n";
        }
        $this->info['debug'] .= "\r\n";
    }

    public function completeSendingRequest(Request $request, Stream $stream): void
    {
        ($this->onProgress)();
    }

    public function startReceivingResponse(Request $request, Stream $stream): void
    {
        $this->info['starttransfer_time'] = microtime(true) - $this->info['start_time'];
        ($this->onProgress)();
    }

    public function completeReceivingResponse(Request $request, Stream $stream): void
    {
        $this->handle = null;
        ($this->onProgress)();
    }

    public function completeDnsResolution(Request $request): void
    {
        $this->info['namelookup_time'] = microtime(true) - $this->info['start_time'];
        ($this->onProgress)();
    }

    public function completeConnectionCreation(Request $request): void
    {
        $this->info['connect_time'] = microtime(true) - $this->info['start_time'];
        ($this->onProgress)();
    }

    public function completeTlsNegotiation(Request $request): void
    {
        ($this->onProgress)();
    }

    public function abort(Request $request, \Throwable $cause): void
    {
    }
}
