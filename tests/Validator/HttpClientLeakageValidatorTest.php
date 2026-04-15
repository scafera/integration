<?php

declare(strict_types=1);

namespace Scafera\Integration\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Scafera\Integration\Validator\HttpClientLeakageValidator;

class HttpClientLeakageValidatorTest extends TestCase
{
    private HttpClientLeakageValidator $validator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->validator = new HttpClientLeakageValidator();
        $this->tmpDir = sys_get_temp_dir() . '/scafera_integration_leak_test_' . uniqid();
        mkdir($this->tmpDir . '/src', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testPassesWhenNoHttpImports(): void
    {
        file_put_contents($this->tmpDir . '/src/CleanService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use App\Repository\OrderRepository;
        class CleanService {
            public function __construct(private readonly OrderRepository $orders) {}
        }
        PHP);

        $this->assertSame([], $this->validator->validate($this->tmpDir));
    }

    public function testFailsWhenSymfonyHttpClientContractImported(): void
    {
        file_put_contents($this->tmpDir . '/src/BadService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use Symfony\Contracts\HttpClient\HttpClientInterface;
        class BadService {}
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('BadService.php', $violations[0]);
        $this->assertStringContainsString('Symfony HttpClient contracts', $violations[0]);
    }

    public function testFailsWhenSymfonyHttpClientComponentImported(): void
    {
        file_put_contents($this->tmpDir . '/src/BadService.php', <<<'PHP'
        <?php
        namespace App\Service;
        use Symfony\Component\HttpClient\HttpClient;
        class BadService {}
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('BadService.php', $violations[0]);
        $this->assertStringContainsString('Symfony HttpClient component', $violations[0]);
    }

    public function testFailsWhenCurlUsed(): void
    {
        file_put_contents($this->tmpDir . '/src/CurlService.php', <<<'PHP'
        <?php
        namespace App\Service;
        class CurlService {
            public function fetch(): void {
                $ch = curl_init('https://example.com');
                curl_exec($ch);
            }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('CurlService.php', $violations[0]);
        $this->assertStringContainsString('curl', $violations[0]);
    }

    public function testFailsWhenFileGetContentsWithHttpUrl(): void
    {
        file_put_contents($this->tmpDir . '/src/HttpFetch.php', <<<'PHP'
        <?php
        namespace App\Service;
        class HttpFetch {
            public function fetch(): string {
                return file_get_contents('https://api.example.com/data');
            }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('HttpFetch.php', $violations[0]);
        $this->assertStringContainsString('file_get_contents', $violations[0]);
    }

    public function testFailsWhenFopenWithHttpUrl(): void
    {
        file_put_contents($this->tmpDir . '/src/FopenFetch.php', <<<'PHP'
        <?php
        namespace App\Service;
        class FopenFetch {
            public function fetch(): void {
                $f = fopen('http://example.com/data', 'r');
            }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('FopenFetch.php', $violations[0]);
        $this->assertStringContainsString('fopen', $violations[0]);
    }

    public function testPassesWhenFileGetContentsUsedForLocalFile(): void
    {
        file_put_contents($this->tmpDir . '/src/LocalRead.php', <<<'PHP'
        <?php
        namespace App\Service;
        class LocalRead {
            public function read(): string {
                return file_get_contents('data.json');
            }
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
        file_put_contents($this->tmpDir . '/src/Bad1.php', <<<'PHP'
        <?php
        use Symfony\Contracts\HttpClient\HttpClientInterface;
        class Bad1 {}
        PHP);

        mkdir($this->tmpDir . '/src/Sub', 0777, true);
        file_put_contents($this->tmpDir . '/src/Sub/Bad2.php', <<<'PHP'
        <?php
        class Bad2 {
            public function f(): void { curl_init('x'); }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(2, $violations);
    }

    public function testFailsOnProtocolRelativeUrl(): void
    {
        file_put_contents($this->tmpDir . '/src/ProtocolRelative.php', <<<'PHP'
        <?php
        namespace App\Service;
        class ProtocolRelative {
            public function fetch(): string {
                return file_get_contents('//api.example.com/data');
            }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('file_get_contents', $violations[0]);
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
