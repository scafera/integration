<?php

declare(strict_types=1);

namespace Scafera\Integration\Tests;

use PHPUnit\Framework\TestCase;
use Scafera\Integration\Integration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class IntegrationAttributeTest extends TestCase
{
    public function testExtendsAutowire(): void
    {
        $attr = new Integration('stripe');
        $this->assertInstanceOf(Autowire::class, $attr);
    }

    public function testIsValidPhpAttribute(): void
    {
        $ref = new \ReflectionClass(Integration::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $this->assertCount(1, $attrs);
    }

    public function testTargetsParameterAndProperty(): void
    {
        $ref = new \ReflectionClass(Integration::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $flags = $attrs[0]->newInstance()->flags;
        $this->assertTrue(($flags & \Attribute::TARGET_PARAMETER) !== 0);
        $this->assertTrue(($flags & \Attribute::TARGET_PROPERTY) !== 0);
    }

    public function testResolvesServiceId(): void
    {
        $attr = new Integration('stripe');
        // Autowire stores the service reference internally
        // Verify it was constructed with the correct service ID
        $ref = new \ReflectionProperty(Autowire::class, 'value');
        $value = $ref->getValue($attr);
        $this->assertStringContainsString('scafera.integration.stripe', (string) $value);
    }

    public function testDifferentNamesResolveDifferentServices(): void
    {
        $stripe = new Integration('stripe');
        $mailgun = new Integration('mailgun');

        $ref = new \ReflectionProperty(Autowire::class, 'value');
        $stripeValue = (string) $ref->getValue($stripe);
        $mailgunValue = (string) $ref->getValue($mailgun);

        $this->assertStringContainsString('stripe', $stripeValue);
        $this->assertStringContainsString('mailgun', $mailgunValue);
        $this->assertNotEquals($stripeValue, $mailgunValue);
    }

    public function testResolvesConfigKey(): void
    {
        $attr = new Integration('linkedin', 'contract_id');
        $ref = new \ReflectionProperty(Autowire::class, 'value');
        $value = (string) $ref->getValue($attr);
        $this->assertSame('%scafera.integration.linkedin.contract_id%', $value);
    }

    public function testDifferentKeysResolveDifferentParameters(): void
    {
        $contract = new Integration('linkedin', 'contract_id');
        $region = new Integration('linkedin', 'region');

        $ref = new \ReflectionProperty(Autowire::class, 'value');
        $contractValue = (string) $ref->getValue($contract);
        $regionValue = (string) $ref->getValue($region);

        $this->assertNotEquals($contractValue, $regionValue);
        $this->assertStringContainsString('contract_id', $contractValue);
        $this->assertStringContainsString('region', $regionValue);
    }

    public function testWithoutKeyResolvesService(): void
    {
        $service = new Integration('stripe');
        $config = new Integration('stripe', 'api_version');

        $ref = new \ReflectionProperty(Autowire::class, 'value');
        $serviceValue = (string) $ref->getValue($service);
        $configValue = (string) $ref->getValue($config);

        $this->assertStringContainsString('scafera.integration.stripe', $serviceValue);
        $this->assertStringNotContainsString('%', $serviceValue);
        $this->assertStringContainsString('%', $configValue);
    }
}
