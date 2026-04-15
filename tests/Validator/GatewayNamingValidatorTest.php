<?php

declare(strict_types=1);

namespace Scafera\Integration\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Scafera\Integration\Validator\GatewayNamingValidator;

class GatewayNamingValidatorTest extends TestCase
{
    private GatewayNamingValidator $validator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->validator = new GatewayNamingValidator();
        $this->tmpDir = sys_get_temp_dir() . '/scafera_integration_naming_test_' . uniqid();
        mkdir($this->tmpDir . '/src/Integration/Stripe', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testPassesWhenClassEndsWithGateway(): void
    {
        file_put_contents($this->tmpDir . '/src/Integration/Stripe/PaymentGateway.php', <<<'PHP'
        <?php
        namespace App\Integration\Stripe;
        final class PaymentGateway {}
        PHP);

        $this->assertSame([], $this->validator->validate($this->tmpDir));
    }

    public function testFailsWhenClassDoesNotEndWithGateway(): void
    {
        file_put_contents($this->tmpDir . '/src/Integration/Stripe/PaymentClient.php', <<<'PHP'
        <?php
        namespace App\Integration\Stripe;
        final class PaymentClient {}
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('PaymentClient', $violations[0]);
        $this->assertStringContainsString("must end with 'Gateway'", $violations[0]);
    }

    public function testPassesWhenNoIntegrationDir(): void
    {
        $emptyDir = sys_get_temp_dir() . '/scafera_empty_' . uniqid();
        mkdir($emptyDir . '/src', 0777, true);

        $this->assertSame([], $this->validator->validate($emptyDir));

        $this->removeDir($emptyDir);
    }

    public function testChecksNestedSubdirectories(): void
    {
        mkdir($this->tmpDir . '/src/Integration/Mailgun', 0777, true);

        file_put_contents($this->tmpDir . '/src/Integration/Stripe/PaymentGateway.php', <<<'PHP'
        <?php
        final class PaymentGateway {}
        PHP);

        file_put_contents($this->tmpDir . '/src/Integration/Mailgun/Mailer.php', <<<'PHP'
        <?php
        final class Mailer {}
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('Mailer', $violations[0]);
    }

    public function testReportsMultipleViolations(): void
    {
        file_put_contents($this->tmpDir . '/src/Integration/Stripe/PaymentClient.php', <<<'PHP'
        <?php
        final class PaymentClient {}
        PHP);

        file_put_contents($this->tmpDir . '/src/Integration/Stripe/ApiWrapper.php', <<<'PHP'
        <?php
        final class ApiWrapper {}
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(2, $violations);
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
