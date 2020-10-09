<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Manifest;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RemoteJsonManifest implements ManifestInterface
{
    private $manifestUrl;
    private $httpClient;

    public function __construct(string $manifestUrl, HttpClientInterface $httpClient)
    {
        $this->manifestUrl = $manifestUrl;
        $this->httpClient = $httpClient;
    }

    public function getManifest(): array
    {
        return $this->httpClient->request('GET', $this->manifestUrl, [
            'headers' => ['accept' => 'application/json'],
        ])->toArray();
    }
}
