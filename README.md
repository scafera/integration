# scafera/integration

External system communication for the Scafera framework. Provides an enforceable gateway pattern for calling third-party APIs — all behind Scafera-owned types.

Internally adopts `symfony/http-client`. Userland code never imports Symfony HttpClient types — boundary enforcement blocks it at build time. All alternative HTTP mechanisms (cURL, `file_get_contents` with HTTP URLs) are also blocked.

This is a **capability package**. It adds optional external system integration to a Scafera project. It does not define folder structure or architectural rules — those belong to architecture packages.

## Core Idea

Scafera treats the HTTP client as an implementation detail. Your application code interacts with **gateways** — classes with business-level methods like `createPayment()` or `getRate()` — never making raw HTTP calls. Each gateway wraps one external system, receives a configured `HttpClient` via the `#[Integration]` attribute, and uses relative paths against a pre-configured base URL. A build-time validator enforces these boundaries: Symfony HttpClient, cURL, and HTTP-targeted `file_get_contents`/`fopen` are blocked everywhere in `src/`. The `HttpClient` class itself is only allowed inside the `Integration/` layer.

## What it provides

- `HttpClient` — wraps Symfony HttpClient with 5 methods (get, post, put, patch, delete)
- `Response` — wraps Symfony response with 5 methods (statusCode, json, body, headers, header)
- `#[Integration('name')]` — attribute for wiring gateways to configured HttpClient instances
- `#[Integration('name', 'key')]` — attribute for injecting integration-specific config values into gateways (ADR-065)
- `HttpClientLeakageValidator` — blocks all HTTP escape hatches in `src/`
- `GatewayNamingValidator` — enforces `*Gateway` naming in `Integration/`
- `HttpClientBoundaryValidator` — ensures `HttpClient` is only used in `Integration/` layer
- `IntegrationConfigBoundaryValidator` — ensures `#[Integration('name', 'key')]` config values are only used in `Integration/` layer
- `UnusedIntegrationConfigValidator` — flags config values defined in `integration:` but not referenced in any gateway

## Design decisions

- **Gateway, not HTTP client wrapper** — a wrapped HTTP client would scatter `$http->post()` calls across the codebase. Gateways enforce one class per external system with business-level methods (ADR-064).
- **`#[Integration]` attribute for wiring** — extends Symfony's `#[Autowire]`, same pattern as `#[Config]` in the kernel. Explicit at the injection site, greppable, validatable, refactor-safe (ADR-064). With a second argument, resolves integration-specific config values (ADR-065).
- **Relative paths only** — gateways use endpoint paths (`'/charges'`), not full URLs. Base URL is configured once in `config.yaml`.
- **All HTTP escape hatches blocked** — Symfony HttpClient, cURL, `file_get_contents` with HTTP URLs, `fopen` with HTTP URLs. If you need HTTP, you go through a gateway.

## Installation

```bash
composer require scafera/integration
```

The bundle is auto-discovered via Scafera's `symfony-bundle` type detection. No manual registration needed.

## Requirements

- PHP >= 8.4
- scafera/kernel

## Configuration

```yaml
# config/config.yaml
integration:
    stripe:
        base_url: 'https://api.stripe.com/v1'
        auth: ''
    mailgun:
        base_url: 'https://api.mailgun.net/v3'
        auth: ''
```

```yaml
# config.local.yaml (not committed)
integration:
    stripe:
        auth: 'Bearer sk_live_real_secret'
    mailgun:
        auth: 'Basic key-real_secret'
```

If an integration has different base URLs per environment (e.g., sandbox vs production), override `base_url` in `config.local.yaml` as well.

## Gateway

A gateway is one class per external system with business-level methods:

```php
namespace App\Integration\Stripe;

use Scafera\Integration\HttpClient;
use Scafera\Integration\Attribute\Integration;

final class PaymentGateway
{
    public function __construct(
        #[Integration('stripe')]
        private HttpClient $http,
    ) {}

    public function createPayment(int $amount, string $currency): array
    {
        return $this->http->post('/charges', [
            'amount' => $amount,
            'currency' => $currency,
        ])->json();
    }

    public function refund(string $chargeId): array
    {
        return $this->http->post('/refunds', [
            'charge' => $chargeId,
        ])->json();
    }
}
```

### Gateway rules

- Class name must end with `Gateway` — enforced by validator
- One class per external system
- Business-level methods only — `createPayment()`, not `post()`
- No HTTP types in public method signatures — return arrays or domain objects
- No full URLs — endpoint paths only, base URL from config

## Using a gateway in a service

Services inject gateways via constructor — same as any Scafera dependency:

```php
namespace App\Service\Order;

use App\Integration\Stripe\PaymentGateway;

final class PlaceOrder
{
    public function __construct(
        private PaymentGateway $payment,
    ) {}

    public function handle(int $amount): array
    {
        return $this->payment->createPayment($amount, 'usd');
    }
}
```

## Integration-specific configuration

Integrations can carry arbitrary config values beyond `base_url` and `auth`. These are registered as container parameters and injected via the same `#[Integration]` attribute with a second argument:

```yaml
# config/config.yaml
integration:
    linkedin:
        base_url: 'https://api.linkedin.com/v2'
        auth: ''
        contract_id: ''
        seat_limit: 500
```

```yaml
# config.local.yaml (not committed)
integration:
    linkedin:
        auth: 'Bearer token_secret'
        contract_id: 'CONTRACT-2026-XYZ'
```

The gateway receives config values alongside the `HttpClient`:

```php
namespace App\Integration\LinkedIn;

use Scafera\Integration\HttpClient;
use Scafera\Integration\Attribute\Integration;

final class PremiumGateway
{
    public function __construct(
        #[Integration('linkedin')]
        private HttpClient $http,
        #[Integration('linkedin', 'contract_id')]
        private string $contractId,
        #[Integration('linkedin', 'seat_limit')]
        private int $seatLimit,
    ) {}

    public function inviteSeat(string $userId): array
    {
        return $this->http->post('/premium/invites', [
            'contract_id' => $this->contractId,
            'user_id' => $userId,
        ])->json();
    }
}
```

`#[Integration('name')]` without a second argument resolves to the `HttpClient` service. `#[Integration('name', 'key')]` resolves to the config value. The `HttpClient` itself has no awareness of these values — they are resolved by the container before the gateway is constructed.

## HttpClient API

```php
$this->http->get(string $path, array $options = []): Response;
$this->http->post(string $path, array $data = [], array $options = []): Response;
$this->http->put(string $path, array $data = [], array $options = []): Response;
$this->http->patch(string $path, array $data = [], array $options = []): Response;
$this->http->delete(string $path, array $options = []): Response;
```

The `$data` array is sent as JSON body. For other content types (form-encoded, multipart), use the `$options` array directly (e.g., `$this->http->post('/upload', [], ['body' => $formData])`).

## Response API

```php
$response->statusCode(): int;
$response->json(): array;
$response->body(): string;
$response->headers(): array;
$response->header(string $name): ?string;
```

HTTP errors do not throw automatically — error handling is the gateway's responsibility.

## Testing

### Testing services that use gateways

Mock the gateway, not the HTTP client:

```php
final class PlaceOrderTest extends WebTestCase
{
    public function testPlacesOrder(): void
    {
        // Mock PaymentGateway — stable, behavior-focused
        // No HTTP client mocking, no request/response faking
    }
}
```

### Testing gateways themselves

The `HttpClient` constructor accepts an optional `HttpClientInterface` for testing — the bundle never passes it (engine stays hidden in production):

```php
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$mock = new MockHttpClient([new MockResponse('{"id": 1}')]);
$http = new HttpClient('https://api.example.com', null, $mock);

$gateway = new PaymentGateway($http);
$result = $gateway->createPayment(1000, 'usd');
```

## Boundary enforcement

| Blocked | Use instead |
|---------|-------------|
| `Symfony\Contracts\HttpClient\*` | `Scafera\Integration\HttpClient` in a Gateway class |
| `Symfony\Component\HttpClient\*` | `Scafera\Integration\HttpClient` in a Gateway class |
| `curl_*` functions | `Scafera\Integration\HttpClient` in a Gateway class |
| `file_get_contents` with HTTP URLs | `Scafera\Integration\HttpClient` in a Gateway class |
| `fopen` with HTTP URLs | `Scafera\Integration\HttpClient` in a Gateway class |
| `Scafera\Integration\HttpClient` outside `Integration/` | Inject the gateway, not the HTTP client |
| `#[Integration('name', 'key')]` outside `Integration/` | Integration config values belong in gateways only |
| Unused config keys under `integration:` | Remove unused keys or reference them in a gateway |

Enforced via validators (`scafera validate`). The `HttpClient` and integration config values are only allowed inside the `Integration/` layer — services and controllers inject gateways, not HTTP clients or integration config.

## License

MIT
