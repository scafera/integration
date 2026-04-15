<?php

declare(strict_types=1);

namespace Scafera\Integration\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Scafera\Integration\Validator\IntegrationConfigBoundaryValidator;

class IntegrationConfigBoundaryValidatorTest extends TestCase
{
    private IntegrationConfigBoundaryValidator $validator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->validator = new IntegrationConfigBoundaryValidator();
        $this->tmpDir = sys_get_temp_dir() . '/scafera_integration_config_boundary_test_' . uniqid();
        mkdir($this->tmpDir . '/src/Integration', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testPassesWhenConfigAttributeUsedInGateway(): void
    {
        file_put_contents($this->tmpDir . '/src/Integration/PaymentGateway.php', <<<'PHP'
        <?php
        namespace App\Integration;
        use Scafera\Integration\Attribute\Integration;
        final class PaymentGateway {
            public function __construct(
                #[Integration('stripe')]
                private $http,
                #[Integration('stripe', 'contract_id')]
                private string $contractId,
            ) {}
        }
        PHP);

        $this->assertSame([], $this->validator->validate($this->tmpDir));
    }

    public function testFailsWhenConfigAttributeUsedInService(): void
    {
        mkdir($this->tmpDir . '/src/Service', 0777, true);
        file_put_contents($this->tmpDir . '/src/Service/OrderService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use Scafera\Integration\Attribute\Integration;
        final class OrderService {
            public function __construct(
                #[Integration('stripe', 'contract_id')]
                private string $contractId,
            ) {}
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('OrderService.php', $violations[0]);
        $this->assertStringContainsString('Integration/ layer', $violations[0]);
    }

    public function testFailsWhenConfigAttributeUsedInController(): void
    {
        mkdir($this->tmpDir . '/src/Controller', 0777, true);
        file_put_contents($this->tmpDir . '/src/Controller/OrderController.php', <<<'PHP'
        <?php
        namespace App\Controller;
        use Scafera\Integration\Attribute\Integration;
        final class OrderController {
            public function __construct(
                #[Integration('stripe', 'api_version')]
                private string $apiVersion,
            ) {}
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('OrderController.php', $violations[0]);
    }

    public function testPassesWhenServiceOnlyAttributeUsedOutsideIntegration(): void
    {
        mkdir($this->tmpDir . '/src/Service', 0777, true);
        file_put_contents($this->tmpDir . '/src/Service/CleanService.php', <<<'PHP'
        <?php
        namespace App\Service;
        class CleanService {
            public function __construct(private string $name) {}
        }
        PHP);

        $this->assertSame([], $this->validator->validate($this->tmpDir));
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
        mkdir($this->tmpDir . '/src/Service', 0777, true);
        mkdir($this->tmpDir . '/src/Controller', 0777, true);

        file_put_contents($this->tmpDir . '/src/Service/Bad1.php', <<<'PHP'
        <?php
        use Scafera\Integration\Attribute\Integration;
        class Bad1 {
            public function __construct(
                #[Integration('stripe', 'key')]
                private string $key,
            ) {}
        }
        PHP);

        file_put_contents($this->tmpDir . '/src/Controller/Bad2.php', <<<'PHP'
        <?php
        use Scafera\Integration\Attribute\Integration;
        class Bad2 {
            public function __construct(
                #[Integration('mailgun', 'domain')]
                private string $domain,
            ) {}
        }
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
