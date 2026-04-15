<?php

declare(strict_types=1);

namespace Scafera\Integration\Tests\Advisor;

use PHPUnit\Framework\TestCase;
use Scafera\Integration\Advisor\LocalConfigAdvisor;

class LocalConfigAdvisorTest extends TestCase
{
    private LocalConfigAdvisor $advisor;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->advisor = new LocalConfigAdvisor();
        $this->tmpDir = sys_get_temp_dir() . '/scafera_integration_local_config_test_' . uniqid();
        mkdir($this->tmpDir . '/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testSkippedWhenNoLocalConfig(): void
    {
        $this->assertNotNull($this->advisor->skipped($this->tmpDir));
    }

    public function testNotSkippedWhenLocalConfigExists(): void
    {
        file_put_contents($this->tmpDir . '/config/config.local.yaml', "integration:\n    stripe:\n        auth: 'secret'\n");

        $this->assertNull($this->advisor->skipped($this->tmpDir));
    }

    public function testNoHintsWhenLocalKeysHavePlaceholders(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        integration:
            linkedin:
                base_url: 'https://api.linkedin.com/v2'
                auth: ''
                contract_id: ''
        YAML);

        file_put_contents($this->tmpDir . '/config/config.local.yaml', <<<'YAML'
        integration:
            linkedin:
                auth: 'Bearer secret'
                contract_id: 'CONTRACT-2026-XYZ'
        YAML);

        $this->assertSame([], $this->advisor->advise($this->tmpDir));
    }

    public function testHintWhenLocalKeyMissingFromConfig(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        integration:
            linkedin:
                base_url: 'https://api.linkedin.com/v2'
                auth: ''
        YAML);

        file_put_contents($this->tmpDir . '/config/config.local.yaml', <<<'YAML'
        integration:
            linkedin:
                contract_id: 'CONTRACT-2026-XYZ'
        YAML);

        $hints = $this->advisor->advise($this->tmpDir);
        $this->assertCount(1, $hints);
        $this->assertStringContainsString('contract_id', $hints[0]);
        $this->assertStringContainsString('config.local.yaml', $hints[0]);
        $this->assertStringContainsString('config.yaml', $hints[0]);
    }

    public function testIgnoresBaseUrlAndAuth(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        integration:
            stripe:
                base_url: 'https://api.stripe.com/v1'
        YAML);

        file_put_contents($this->tmpDir . '/config/config.local.yaml', <<<'YAML'
        integration:
            stripe:
                base_url: 'https://sandbox.stripe.com/v1'
                auth: 'Bearer secret'
        YAML);

        $this->assertSame([], $this->advisor->advise($this->tmpDir));
    }

    public function testMultipleHintsAcrossIntegrations(): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', <<<'YAML'
        integration:
            stripe:
                base_url: 'https://api.stripe.com/v1'
                auth: ''
            mailgun:
                base_url: 'https://api.mailgun.net/v3'
                auth: ''
        YAML);

        file_put_contents($this->tmpDir . '/config/config.local.yaml', <<<'YAML'
        integration:
            stripe:
                webhook_secret: 'whsec_123'
            mailgun:
                domain: 'mg.example.com'
        YAML);

        $hints = $this->advisor->advise($this->tmpDir);
        $this->assertCount(2, $hints);
    }

    public function testNoHintsWhenLocalHasNoIntegrationSection(): void
    {
        file_put_contents($this->tmpDir . '/config/config.local.yaml', <<<'YAML'
        parameters:
            app.name: 'test'
        YAML);

        $this->assertSame([], $this->advisor->advise($this->tmpDir));
    }

    public function testHandlesMissingConfigYaml(): void
    {
        file_put_contents($this->tmpDir . '/config/config.local.yaml', <<<'YAML'
        integration:
            stripe:
                webhook_secret: 'whsec_123'
        YAML);

        $hints = $this->advisor->advise($this->tmpDir);
        $this->assertCount(1, $hints);
        $this->assertStringContainsString('webhook_secret', $hints[0]);
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
