<?php

namespace Neoxygen\NeoClient\Tests;

use Neoxygen\NeoClient\Client;
use Neoxygen\NeoClient\ServiceContainer;
use Symfony\Component\Yaml\Yaml;

class NeoClientTestCase extends \PHPUnit_Framework_TestCase
{
    public function getDefaultConfig()
    {
        return __DIR__.'/../../../database_settings.yml';
    }

    public function build()
    {
        $client = new Client();
        $client->loadConfigurationFile($this->getDefaultConfig());
        $client->build();

        return $client;
    }

    public function buildMultiple()
    {
        if (!file_exists(__DIR__.'/../../../database_settings.yml')) {
            return false;
        }

        $config = Yaml::parse($this->getDefaultConfig());
        $connection = array_shift($config['connections']);
        $client = new Client();
        $client->addConnection('dummy', 'http', 'notexistinghost.dev', 7479)
            ->addConnection('default', $connection['scheme'], $connection['host'], $connection['port'], true, 'ikwattro', 'error')
            ->setFallbackConnection('dummy', 'default')
            ->build();

        return $client;
    }
}