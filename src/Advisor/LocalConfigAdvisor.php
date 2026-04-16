<?php

declare(strict_types=1);

namespace Scafera\Integration\Advisor;

use Scafera\Kernel\Contract\AdvisorInterface;
use Symfony\Component\Yaml\Yaml;

final class LocalConfigAdvisor implements AdvisorInterface
{
    public function getId(): string
    {
        return 'integration.local-config';
    }

    public function getName(): string
    {
        return 'Integration local config has matching placeholders';
    }

    public function skipped(string $projectDir): ?string
    {
        if (!is_file($projectDir . '/config/config.local.yaml')) {
            return 'config.local.yaml not found';
        }

        return null;
    }

    public function advise(string $projectDir): array
    {
        $localFile = $projectDir . '/config/config.local.yaml';
        $configFile = $projectDir . '/config/config.yaml';

        $local = Yaml::parseFile($localFile);
        if (!isset($local['integration']) || !is_array($local['integration'])) {
            return [];
        }

        $config = [];
        if (is_file($configFile)) {
            $parsed = Yaml::parseFile($configFile);
            if (isset($parsed['integration']) && is_array($parsed['integration'])) {
                $config = $parsed['integration'];
            }
        }

        $hints = [];

        foreach ($local['integration'] as $name => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach ($entry as $key => $value) {
                if ($key === 'base_url' || $key === 'auth') {
                    continue;
                }
                if (!isset($config[$name][$key])) {
                    $hints[] = 'integration.' . $name . '.' . $key . ' is in config.local.yaml but has no placeholder in config.yaml — consider adding it';
                }
            }
        }

        return $hints;
    }
}
