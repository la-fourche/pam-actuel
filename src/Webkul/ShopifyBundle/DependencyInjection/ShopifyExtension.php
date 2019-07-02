<?php

namespace Webkul\ShopifyBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class ShopifyExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('mass_actions.yml');
        $loader->load('jobs.yml');
        $loader->load('job_parameters.yml');
        $loader->load('form_entry.yml');
        $loader->load('steps.yml');
        $loader->load('readers.yml');
        $loader->load('processors.yml');
        $loader->load('writers.yml');
        $loader->load('repositories.yml');
        $loader->load('data_sources.yml');
        $loader->load('controllers.yml');
        $loader->load('event_listener.yml');
        //customizations
        // $loader->load('customization/datamapping.yml');
        /* version wise loading */
        if(class_exists('Akeneo\Platform\CommunityVersion')) {
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        } elseif(class_exists('Pim\Bundle\CatalogBundle\Version')) {
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        }

        $version = $versionClass::VERSION;
       
        $versionDirectoryPrefix = '2.x/';
        if($version > '2.2' && $version < '3.0') {
            $versionDirectoryPrefix = '2.2/';
        } elseif ($version > '3.0'){
            $versionDirectoryPrefix = '3.0/';
        }

        $loader->load($versionDirectoryPrefix . 'jobs.yml');
        $loader->load($versionDirectoryPrefix . 'processors.yml');
        $loader->load($versionDirectoryPrefix . 'servicenames.yml');
    }
}
