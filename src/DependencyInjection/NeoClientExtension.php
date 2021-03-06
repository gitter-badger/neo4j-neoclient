<?php

/**
 * This file is part of the "-[:NEOXYGEN]->" NeoClient package
 *
 * (c) Neoxygen.io <http://neoxygen.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Neoxygen\NeoClient\DependencyInjection;

use Doctrine\Instantiator\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\Loader\YamlFileLoader,
    Symfony\Component\DependencyInjection\Extension\ExtensionInterface,
    Symfony\Component\DependencyInjection\Definition,
    Symfony\Component\Config\Definition\Processor,
    Symfony\Component\Config\FileLocator;
use Neoxygen\NeoClient\DependencyInjection\Definition as ConfigDefinition;

class NeoClientExtension implements  ExtensionInterface
{
    protected $container;

    public function load(array $configs, ContainerBuilder $container)
    {
        $this->container = $container;
        $processor = new Processor();
        $configuration = new ConfigDefinition();

        $config = $processor->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yml');

        if ($config['cache']['enabled'] === true) {
            $container->setParameter('neoclient.cache_path', $config['cache']['cache_path']);
        }

        $container->setParameter('neoclient.response_format', $config['response_format']);
        $container->setParameter('neoclient.default_result_data_content', $config['default_result_data_content']);

        $this->addConnectionDefinitions($config, $container);
        $this->addRegisteredExtensionsDefinitions($config, $container);
        $this->addListeners($config);
        $this->registerCustomCommands($config);

        if ($config['response_format'] === 'custom') {
            $class = $config['response_formatter_class'];
            if (!class_exists($class)) {
                throw new \InvalidArgumentException(sprintf('The class %s does not exist', $class));
            }

            $definition = new Definition();
            $definition->setClass($class);
            $container->setDefinition('neoclient.response_formatter', $definition);

            $client = $container->findDefinition('neoclient.http_client');
            $client->addMethodCall(
                'setResponseFormatter',
                array($definition)
            );
        }

    }

    private function addConnectionDefinitions($config, $container)
    {
        foreach ($config['connections'] as $connectionAlias => $settings) {
            if ($container->hasDefinition(sprintf('neoclient.connection.%s', $connectionAlias))) {
                throw new \InvalidArgumentException(sprintf('The connection %s can only be declared once, check your config file', $connectionAlias));
            }

            $definition = new Definition();
            $definition
                ->setClass('Neoxygen\NeoClient\Connection\Connection')
                ->addArgument($connectionAlias)
                ->addArgument($settings['scheme'])
                ->addArgument($settings['host'])
                ->addArgument($settings['port'])
                ->addTag('neoclient.registered_connection')
                ->setLazy(true);
            if (isset($settings['auth']) && true === $settings['auth']) {
                $definition->addArgument(true)
                    ->addArgument($settings['user'])
                    ->addArgument($settings['password']);
            }
            if ($fallbackOf = $this->isFallbackConnection($config, $connectionAlias)) {
                $definition->addTag('neoclient.fallback_connection', array('fallback_of' => $fallbackOf, 'connection_alias' => $connectionAlias));
            }
            $container->setDefinition(sprintf('neoclient.connection.%s', $connectionAlias), $definition);
        }
    }

    private function addRegisteredExtensionsDefinitions($config, $container)
    {
        foreach ($config['extensions'] as $alias => $props) {
            $this->registerCoreExtension($alias, $props);
        }

        // Registering Core Commands
        $this->registerCoreExtension('neoclient_core', array('class' => 'Neoxygen\NeoClient\Extension\NeoClientCoreExtension'));
        $this->registerCoreExtension('neoclient_auth', array('class' => 'Neoxygen\NeoClient\Extension\NeoClientAuthExtension'));
        $this->registerCoreExtension('neoclient_changefeed', array('class' => 'Neoxygen\NeoClient\Extension\NeoClientChangeFeedExtension'));
    }

    private function registerCoreExtension($alias, $props)
    {
        $definition = new Definition();
        $definition->setClass($props['class'])
            ->addTag('neoclient.extension_class');
        $this->container->setDefinition(sprintf('neoclient.extension_%s', $alias), $definition);
    }

    private function registerCustomCommands(array $config)
    {
        foreach ($config['custom_commands'] as $command) {
            $definition = new Definition();
            $definition->setClass($command['class']);
            $definition->addTag('neoclient.custom_command', array($command['alias']));
            $this->container->setDefinition(sprintf('neoclient.custom_command.%s', $command['alias']), $definition);
        }
    }

    private function isFallbackConnection(array $config, $alias)
    {
        if (isset($config['fallback'])) {
            foreach ($config['fallback'] as $con => $fallback) {
                if ($alias === $fallback) {
                    return $con;
                }
            }
        }

        return false;
    }

    private function addListeners(array $config)
    {

    }

    public function getAlias()
    {
        return 'neoclient';
    }

    public function getXsdValidationBasePath()
    {
        return false;
    }

    public function getNamespace()
    {
        return false;
    }
}
