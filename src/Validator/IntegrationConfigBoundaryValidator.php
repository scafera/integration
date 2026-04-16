<?php

declare(strict_types=1);

namespace Scafera\Integration\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;
use Scafera\Kernel\Tool\FileFinder;

final class IntegrationConfigBoundaryValidator implements ValidatorInterface
{
    public function getId(): string
    {
        return 'integration.config-boundary';
    }

    public function getName(): string
    {
        return 'Integration config only used in Gateway classes';
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

            if (preg_match('/#\[Integration\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*[\'"][^\'"]+[\'"]\s*\)/', $contents)) {
                $violations[] = $relative . ': #[Integration] config values can only be used in Integration/ layer';
            }
        }

        return $violations;
    }
}
