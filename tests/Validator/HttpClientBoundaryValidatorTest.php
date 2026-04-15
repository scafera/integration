<?php

declare(strict_types=1);

namespace Scafera\Integration\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Scafera\Integration\Validator\HttpClientBoundaryValidator;

class HttpClientBoundaryValidatorTest extends TestCase
{
    private HttpClientBoundaryValidator $validator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->validator = new HttpClientBoundaryValidator();
        $this->tmpDir = sys_get_temp_dir() . '/scafera_integration_boundary_test_' . uniqid();
        mkdir($this->tmpDir . '/src/Integration/Stripe', 0777, true);
        mkdir($this->tmpDir . '/src/Service', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testPassesWhenHttpClientImportedInIntegration(): void
    {
        file_put_contents($this->tmpDir . '/src/Integration/Stripe/PaymentGateway.php', <<<'PHP'
        <?php
        namespace App\Integration\Stripe;
        use Scafera\Integration\HttpClient;
        final class PaymentGateway {
            public function __construct(private HttpClient $http) {}
        }
        PHP);

        $this->assertSame([], $this->validator->validate($this->tmpDir));
    }

    public function testFailsWhenHttpClientImportedInService(): void
    {
        file_put_contents($this->tmpDir . '/src/Service/BadService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use Scafera\Integration\HttpClient;
        final class BadService {
            public function __construct(private HttpClient $http) {}
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('BadService.php', $violations[0]);
        $this->assertStringContainsString('Integration/ layer', $violations[0]);
    }

    public function testFailsWhenHttpClientImportedInController(): void
    {
        mkdir($this->tmpDir . '/src/Controller', 0777, true);
        file_put_contents($this->tmpDir . '/src/Controller/BadController.php', <<<'PHP'
        <?php
        namespace App\Controller;
        use Scafera\Integration\HttpClient;
        final class BadController {}
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('BadController.php', $violations[0]);
    }

    public function testPassesWhenNoSrcDir(): void
    {
        $emptyDir = sys_get_temp_dir() . '/scafera_empty_' . uniqid();
        mkdir($emptyDir);

        $this->assertSame([], $this->validator->validate($emptyDir));

        rmdir($emptyDir);
    }

    public function testReportsMultipleViolations(): void
    {
        mkdir($this->tmpDir . '/src/Controller', 0777, true);

        file_put_contents($this->tmpDir . '/src/Service/Bad1.php', <<<'PHP'
        <?php
        namespace App\Service;
        use Scafera\Integration\HttpClient;
        class Bad1 {}
        PHP);

        file_put_contents($this->tmpDir . '/src/Controller/Bad2.php', <<<'PHP'
        <?php
        namespace App\Controller;
        use Scafera\Integration\HttpClient;
        class Bad2 {}
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(2, $violations);
    }

    public function testPassesWhenImportingGatewayNotHttpClient(): void
    {
        file_put_contents($this->tmpDir . '/src/Service/OrderService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use App\Integration\Stripe\PaymentGateway;
        final class OrderService {
            public function __construct(private PaymentGateway $payment) {}
        }
        PHP);

        $this->assertSame([], $this->validator->validate($this->tmpDir));
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
