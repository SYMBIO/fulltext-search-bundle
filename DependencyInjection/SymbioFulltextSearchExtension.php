<?php

namespace Symbio\FulltextSearchBundle\DependencyInjection;

use Symbio\FulltextSearchBundle\Service\Crawler;
use Symbio\FulltextSearchBundle\Service\Search;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class SymbioFulltextSearchExtension extends Extension
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
        $loader->load('parameters.yml');

        $container->setParameter('symbio_fulltext_search.'.Crawler::USER_AGENT_PARAM, $config[Crawler::USER_AGENT_PARAM]);
        $container->setParameter('symbio_fulltext_search.'.Crawler::TITLE_CLASS_PARAM, $config[Crawler::TITLE_CLASS_PARAM]);
        $container->setParameter('symbio_fulltext_search.'.Search::ITEMS_ON_PAGE_PARAM, $config[Search::ITEMS_ON_PAGE_PARAM]);
        $container->setParameter('symbio_fulltext_search.'.Crawler::DEFAULT_IMAGE_PARAM, $config[Crawler::DEFAULT_IMAGE_PARAM]);
        $container->setParameter('symbio_fulltext_search.'.Crawler::DEFAULT_INDEX_PARAM, $config[Crawler::DEFAULT_INDEX_PARAM]);
        $container->setParameter('symbio_fulltext_search.'.Crawler::PAGE_ID_PARAM, $config[Crawler::PAGE_ID_PARAM]);
        $container->setParameter('symbio_fulltext_search.'.Crawler::ROUTE_NAME_PARAM, $config[Crawler::ROUTE_NAME_PARAM]);
        $container->setParameter('symbio_fulltext_search.'.Crawler::MENU_SECTIONS_PARAM, $config[Crawler::MENU_SECTIONS_PARAM]);
        $container->setParameter('symbio_fulltext_search.'.Crawler::BODY_SECTIONS_PARAM, $config[Crawler::BODY_SECTIONS_PARAM]);
        $container->setParameter('symbio_fulltext_search.'.Crawler::TITLE_TAGS_PARAM, $config[Crawler::TITLE_TAGS_PARAM]);
        $container->setParameter('symbio_fulltext_search.'.Crawler::BOOST_PARAM, $config[Crawler::BOOST_PARAM]);
        $container->setParameter('symbio_fulltext_search.'.Crawler::LINK_SELECTOR_PARAM, $config[Crawler::LINK_SELECTOR_PARAM]);
    }
}
