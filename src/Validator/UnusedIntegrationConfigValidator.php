<?php

declare(strict_types=1);

namespace Scafera\Integration\Validator;

use Scafera\Kernel\Contract\ValidatorInterface;
use Scafera\Kernel\Tool\FileFinder;
use Symfony\Component\Yaml\Yaml;

final class UnusedIntegrationConfigValidator implements ValidatorInterface
{
    public function getName(): string
    {
        return 'All integration config values are used in Gateway classes';
    }

    public function validate(string $projectDir): array
    {
        $configFile = $projectDir . '/config/config.yaml';
        if (!is_file($configFile)) {
            return [];
        }

        $config = Yaml::parseFile($configFile);
        if (!isset($config['integration']) || !is_array($config['integration'])) {
            return [];
        }

        $definedKeys = [];
        foreach ($config['integration'] as $name => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach ($entry as $key => $value) {
                if ($key === 'base_url' || $key === 'auth') {
                    continue;
                }
                $definedKeys[$name][$key] = true;
            }
        }

        if ($definedKeys === []) {
            return [];
        }

        $integrationDir = $projectDir . '/src/Integration';
        if (is_dir($integrationDir)) {
            foreach (FileFinder::findPhpFiles($integrationDir) as $file) {
                $contents = file_get_contents($file);
                if (preg_match_all('/#\[Integration\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $contents, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        unset($definedKeys[$match[1]][$match[2]]);
                    }
                }
            }
        }

        $violations = [];
        foreach ($definedKeys as $name => $keys) {
            foreach ($keys as $key => $_) {
                $violations[] = 'integration.' . $name . '.' . $key . ': config value is defined but not used in any Gateway class, either use it or remove it';
            }
        }

        return $violations;
    }
}
