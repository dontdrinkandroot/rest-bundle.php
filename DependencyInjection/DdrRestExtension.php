<?php

namespace Dontdrinkandroot\RestBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class DdrRestExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('ddr.rest.api_path', $config['api_path']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
    }

    /**
     * Allow an extension to prepend the extension configurations.
     *
     * @param ContainerBuilder $container
     */
    public function prepend(ContainerBuilder $container)
    {
//        $configs = $container->getExtensionConfig($this->getAlias());
//        $configuration = $this->getConfiguration($configs, $container);
//        $config = $this->processConfiguration($configuration, $configs);
//
//        $securityConfig = ['firewalls' => ['api' => []]];
//
//        $securityConfig['firewalls']['api']['stateless'] = true;
//        $securityConfig['firewalls']['api']['pattern'] = '^' . $config['api_path'];
//        $securityConfig['firewalls']['api']['simple_preauth'] = ['authenticator' => 'ddr.fetchtool.security.auth_token_authenticator'];
//
//        $container->prependExtensionConfig('security', $securityConfig);
    }
}
