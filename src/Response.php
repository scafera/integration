<?php

declare(strict_types=1);

namespace Scafera\Integration;

use Symfony\Contracts\HttpClient\ResponseInterface;

final class Response
{
    public function __construct(private readonly ResponseInterface $response)
    {
    }

    public function statusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function json(): array
    {
        return $this->response->toArray(false);
    }

    public function body(): string
    {
        return $this->response->getContent(false);
    }

    public function headers(): array
    {
        return $this->response->getHeaders(false);
    }

    public function header(string $name): ?string
    {
        $headers = $this->response->getHeaders(false);
        $lower = strtolower($name);

        return isset($headers[$lower]) ? $headers[$lower][0] : null;
    }
}
