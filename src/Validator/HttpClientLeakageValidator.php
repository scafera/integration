<?php

declare(strict_types=1);

namespace Scafera\Integration\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;
use Scafera\Kernel\Tool\FileFinder;

final class HttpClientLeakageValidator implements ValidatorInterface
{
    public function getName(): string
    {
        return 'No direct HTTP client usage in userland';
    }

    public function validate(string $projectDir): array
    {
        $srcDir = $projectDir . '/src';
        if (!is_dir($srcDir)) {
            return [];
        }

        $violations = [];

        foreach (FileFinder::findPhpFiles($srcDir) as $file) {
            $contents = file_get_contents($file);
            $relative = 'src/' . str_replace($srcDir . '/', '', $file);

            if (preg_match('/^use\s+Symfony\\\\Contracts\\\\HttpClient\\\\[{A-Z]/m', $contents)) {
                $violations[] = $relative . ': imports Symfony HttpClient contracts directly — use Scafera\Integration\HttpClient in a Gateway class instead';
            }

            if (preg_match('/^use\s+Symfony\\\\Component\\\\HttpClient\\\\[{A-Z]/m', $contents)) {
                $violations[] = $relative . ': imports Symfony HttpClient component directly — use Scafera\Integration\HttpClient in a Gateway class instead';
            }

            if (preg_match('/\bcurl_\w+\s*\(/', $contents)) {
                $violations[] = $relative . ': uses curl functions directly — use Scafera\Integration\HttpClient in a Gateway class instead';
            }

            if (preg_match('/\bfile_get_contents\s*\(\s*[\'"]https?:\/\//', $contents)
                || preg_match('/\bfile_get_contents\s*\(\s*[\'"]\/\//', $contents)) {
                $violations[] = $relative . ': uses file_get_contents with HTTP URL — use Scafera\Integration\HttpClient in a Gateway class instead';
            }

            if (preg_match('/\bfopen\s*\(\s*[\'"]https?:\/\//', $contents)
                || preg_match('/\bfopen\s*\(\s*[\'"]\/\//', $contents)) {
                $violations[] = $relative . ': uses fopen with HTTP URL — use Scafera\Integration\HttpClient in a Gateway class instead';
            }
        }

        return $violations;
    }
}
