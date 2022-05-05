<?php

/*
 * This file is part of the Behat MinkExtension.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\MinkExtension\ServiceContainer\Driver;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Christophe Coevoet <stof@notk.org>
 */
class GoutteFactory implements DriverFactory
{
    /**
     * {@inheritdoc}
     */
    public function getDriverName()
    {
        return 'goutte';
    }

    /**
     * {@inheritdoc}
     */
    public function supportsJavascript()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->children()
                ->arrayNode('server_parameters')
                    ->useAttributeAsKey('key')
                    ->prototype('variable')->end()
                ->end()
                ->arrayNode('guzzle_parameters')
                    ->useAttributeAsKey('key')
                    ->prototype('variable')->end()
                    ->info(
                        "For Goutte 1.x, these are the second argument of the Guzzle3 client constructor.\n".
                        'For Goutte 2.x, these are the elements passed in the "defaults" key of the Guzzle4 config.'
                    )
                ->end()
            ->end()
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function buildDriver(array $config)
    {
        if (!class_exists('Behat\Mink\Driver\GoutteDriver')) {
            throw new \RuntimeException(
                'Install MinkGoutteDriver in order to use goutte driver.'
            );
        }

        $clientDefinition = new Definition('Behat\Mink\Driver\Goutte\Client', array(
            $config['server_parameters'],
        ));
        if ($this->isGoutte1()) {
            $client = $this->buildGuzzle3Client($config['guzzle_parameters']);
            $clientDefinition->addMethodCall('setClient', array($client));
        } elseif ($this->isGoutte4()) {
            $client = $this->buildHttpClient($config['server_parameters']);
            $clientDefinition->setArgument('$client', $client);
        } elseif ($this->isGuzzle6()) {
            $client = $this->buildGuzzle6Client($config['guzzle_parameters']);
            $clientDefinition->addMethodCall('setClient', array($client));
        } else {
            $client = $this->buildGuzzle4Client($config['guzzle_parameters']);
            $clientDefinition->addMethodCall('setClient', array($client));
        }

        return new Definition('Behat\Mink\Driver\GoutteDriver', array(
            $clientDefinition,
        ));
    }

    private function buildHttpClient(array $parameters)
    {
        return \Symfony\Component\HttpClient\HttpClient::create($parameters);
    }

    private function buildGuzzle6Client(array $parameters)
    {
        // Force the parameters set by default in Goutte to reproduce its behavior
        $parameters['allow_redirects'] = false;
        $parameters['cookies'] = true;

        return new Definition('GuzzleHttp\Client', array($parameters));
    }

    private function buildGuzzle4Client(array $parameters)
    {
        // Force the parameters set by default in Goutte to reproduce its behavior
        $parameters['allow_redirects'] = false;
        $parameters['cookies'] = true;

        return new Definition('GuzzleHttp\Client', array(array('defaults' => $parameters)));
    }

    private function buildGuzzle3Client(array $parameters)
    {
        // Force the parameters set by default in Goutte to reproduce its behavior
        $parameters['redirect.disable'] = true;

        return new Definition('Guzzle\Http\Client', array(null, $parameters));
    }

    private function isGoutte1()
    {
        if (!method_exists('Goutte\Client', 'setClient')) {
            return false;
        }
        $refl = new \ReflectionParameter(array('Goutte\Client', 'setClient'), 0);

        $type = $refl->getType();
        if ($type instanceof \ReflectionNamedType && 'Guzzle\Http\ClientInterface' === $type->getName()) {
            return true;
        }

        return false;
    }

    private function isGoutte4()
    {
        return class_exists('Goutte\Client')
            && is_a('Goutte\Client', 'Symfony\Component\BrowserKit\HttpBrowser', true);
    }

    private function isGuzzle6()
    {
        return interface_exists('GuzzleHttp\ClientInterface') &&
            version_compare(\GuzzleHttp\ClientInterface::VERSION, '6.0.0', '>=');
    }
}
