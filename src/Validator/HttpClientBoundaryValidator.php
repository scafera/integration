<?php

declare(strict_types=1);

namespace Scafera\Integration\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;
use Scafera\Kernel\Tool\FileFinder;

final class HttpClientBoundaryValidator implements ValidatorInterface
{
    public function getId(): string
    {
        return 'integration.http-client-boundary';
    }

    public function getName(): string
    {
        return 'HttpClient only used in Integration layer';
    }

    public function validate(string $projectDir): array
    {
        $srcDir = $projectDir . '/src';
        if (!is_dir($srcDir)) {
            return [];
        }

        $violations = [];

        foreach (FileFinder::findPhpFiles($srcDir) as $file) {
            $relative = 'src/' . str_replace($srcDir . '/', '', $file);

            if (str_contains($relative, 'src/Integration/')) {
                continue;
            }

            $contents = file_get_contents($file);

            if (preg_match('/^use\s+Scafera\\\\Integration\\\\HttpClient[\s;]/m', $contents)) {
                $violations[] = $relative . ': Scafera\Integration\HttpClient can only be used in Integration/ layer';
            }
        }

        return $violations;
    }
}
