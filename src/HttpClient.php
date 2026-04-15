<?php

declare(strict_types=1);

namespace Scafera\Integration;

use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpClient
{
    private readonly HttpClientInterface $client;

    public function __construct(string $baseUrl, ?string $auth = null, ?HttpClientInterface $client = null)
    {
        if ($client !== null) {
            $this->client = $client;
            return;
        }

        $options = ['base_uri' => $baseUrl];

        if ($auth !== null && $auth !== '') {
            $options['headers'] = ['Authorization' => $auth];
        }

        $this->client = SymfonyHttpClient::create($options);
    }

    public function get(string $path, array $options = []): Response
    {
        return new Response($this->client->request('GET', $path, $options));
    }

    public function post(string $path, array $data = [], array $options = []): Response
    {
        if ($data !== []) {
            $options['json'] = $data;
        }

        return new Response($this->client->request('POST', $path, $options));
    }

    public function put(string $path, array $data = [], array $options = []): Response
    {
        if ($data !== []) {
            $options['json'] = $data;
        }

        return new Response($this->client->request('PUT', $path, $options));
    }

    public function patch(string $path, array $data = [], array $options = []): Response
    {
        if ($data !== []) {
            $options['json'] = $data;
        }

        return new Response($this->client->request('PATCH', $path, $options));
    }

    public function delete(string $path, array $options = []): Response
    {
        return new Response($this->client->request('DELETE', $path, $options));
    }
}
