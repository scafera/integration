<?php

declare(strict_types=1);

namespace Scafera\Integration\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;
use Scafera\Kernel\Tool\FileFinder;

final class GatewayNamingValidator implements ValidatorInterface
{
    public function getName(): string
    {
        return 'Integration classes end with Gateway';
    }

    public function validate(string $projectDir): array
    {
        $integrationDir = $projectDir . '/src/Integration';
        if (!is_dir($integrationDir)) {
            return [];
        }

        $violations = [];

        foreach (FileFinder::findPhpFiles($integrationDir) as $file) {
            $basename = pathinfo($file, PATHINFO_FILENAME);
            $relative = 'src/Integration/' . str_replace($integrationDir . '/', '', $file);

            if (!str_ends_with($basename, 'Gateway')) {
                $violations[] = $relative . ': Integration class must end with \'Gateway\' (found \'' . $basename . '\')';
            }
        }

        return $violations;
    }
}
