<?php

declare(strict_types=1);

namespace Scafera\Integration;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ScaferaIntegrationBundle extends AbstractBundle
{
    protected string $extensionAlias = 'integration';

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        foreach ($config as $name => $entry) {
            $container->services()
                ->set('scafera.integration.' . $name, HttpClient::class)
                    ->args([$entry['base_url'], $entry['auth'] ?? null]);

            foreach ($entry as $key => $value) {
                if ($key === 'base_url' || $key === 'auth') {
                    continue;
                }
                $builder->setParameter('scafera.integration.' . $name . '.' . $key, $value);
            }
        }

        $container->services()
            ->set(Validator\HttpClientLeakageValidator::class)
                ->tag('scafera.validator')
            ->set(Validator\GatewayNamingValidator::class)
                ->tag('scafera.validator')
            ->set(Validator\HttpClientBoundaryValidator::class)
                ->tag('scafera.validator')
            ->set(Validator\IntegrationConfigBoundaryValidator::class)
                ->tag('scafera.validator')
            ->set(Validator\UnusedIntegrationConfigValidator::class)
                ->tag('scafera.validator')
            ->set(Advisor\LocalConfigAdvisor::class)
                ->tag('scafera.advisor');
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->ignoreExtraKeys(false)
                ->children()
                    ->scalarNode('base_url')->isRequired()->end()
                    ->scalarNode('auth')->defaultNull()->end()
                ->end()
            ->end();
    }
}
