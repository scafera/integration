<?php

declare(strict_types=1);

namespace Scafera\Integration\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Scafera\Integration\Validator\UnusedIntegrationConfigValidator;

class UnusedIntegrationConfigValidatorTest extends TestCase
{
    private UnusedIntegrationConfigValidator $validator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->validator = new UnusedIntegrationConfigValidator();
        $this->tmpDir = sys_get_temp_dir() . '/scafera_integration_unused_config_test_' . uniqid();
        mkdir($this->tmpDir . '/config', 0777, true);
        mkdir($this->tmpDir . '/src/Integration', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testPassesWhenAllConfigValuesUsed(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        integration:
            linkedin:
                base_url: 'https://api.linkedin.com/v2'
                auth: ''
                contract_id: ''
        YAML);

        file_put_contents($this->tmpDir . '/src/Integration/LinkedInGateway.php', <<<'PHP'
        <?php
        namespace App\Integration;
        use Scafera\Integration\Integration;
        final class LinkedInGateway {
            public function __construct(
                #[Integration('linkedin')]
                private $http,
                #[Integration('linkedin', 'contract_id')]
                private string $contractId,
            ) {}
        }
        PHP);

        $this->assertSame([], $this->validator->validate($this->tmpDir));
    }

    public function testFailsWhenConfigValueNotUsed(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        integration:
            linkedin:
                base_url: 'https://api.linkedin.com/v2'
                auth: ''
                contract_id: ''
                seat_limit: 500
        YAML);

        file_put_contents($this->tmpDir . '/src/Integration/LinkedInGateway.php', <<<'PHP'
        <?php
        namespace App\Integration;
        use Scafera\Integration\Integration;
        final class LinkedInGateway {
            public function __construct(
                #[Integration('linkedin')]
                private $http,
                #[Integration('linkedin', 'contract_id')]
                private string $contractId,
            ) {}
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('seat_limit', $violations[0]);
    }

    public function testPassesWhenNoExtraConfigKeys(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        integration:
            stripe:
                base_url: 'https://api.stripe.com/v1'
                auth: ''
        YAML);

        $this->assertSame([], $this->validator->validate($this->tmpDir));
    }

    public function testPassesWhenNoConfigFile(): void
    {
        $emptyDir = sys_get_temp_dir() . '/scafera_no_config_' . uniqid();
        mkdir($emptyDir);

        $this->assertSame([], $this->validator->validate($emptyDir));

        rmdir($emptyDir);
    }

    public function testPassesWhenNoIntegrationSection(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        parameters:
            app.name: 'test'
        YAML);

        $this->assertSame([], $this->validator->validate($this->tmpDir));
    }

    public function testReportsMultipleUnusedKeys(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        integration:
            stripe:
                base_url: 'https://api.stripe.com/v1'
                auth: ''
                webhook_secret: ''
                api_version: '2024-01-01'
        YAML);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(2, $violations);
    }

    public function testReportsUnusedKeysAcrossMultipleIntegrations(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        integration:
            stripe:
                base_url: 'https://api.stripe.com/v1'
                auth: ''
                webhook_secret: ''
            mailgun:
                base_url: 'https://api.mailgun.net/v3'
                auth: ''
                domain: ''
        YAML);

        file_put_contents($this->tmpDir . '/src/Integration/PaymentGateway.php', <<<'PHP'
        <?php
        namespace App\Integration;
        use Scafera\Integration\Integration;
        final class PaymentGateway {
            public function __construct(
                #[Integration('stripe')]
                private $http,
                #[Integration('stripe', 'webhook_secret')]
                private string $secret,
            ) {}
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('mailgun', $violations[0]);
        $this->assertStringContainsString('domain', $violations[0]);
    }

    public function testPassesWhenNoIntegrationDir(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        integration:
            stripe:
                base_url: 'https://api.stripe.com/v1'
                auth: ''
                key: 'val'
        YAML);

        // Remove the Integration dir
        rmdir($this->tmpDir . '/src/Integration');

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('key', $violations[0]);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
