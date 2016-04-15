<?php

namespace Symbio\FulltextSearchBundle\DependencyInjection;

use Symbio\FulltextSearchBundle\Service\Crawler;
use Symbio\FulltextSearchBundle\Service\Search;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('symbio_fulltext_search');

        $rootNode
            ->children()
                // crawler user agent info
                ->scalarNode(Crawler::USER_AGENT_PARAM)->defaultValue('Fulltext search crawler by SYMBIO')->end()
                // page title element class
                ->scalarNode(Crawler::TITLE_CLASS_PARAM)->defaultValue('crawler__title')->end()
                // render count of items on the page
                ->scalarNode(Search::ITEMS_ON_PAGE_PARAM)->defaultValue(10)->end()
                // default image URL
                ->scalarNode(Crawler::DEFAULT_IMAGE_PARAM)->defaultValue('')->end()
                // default index name
                ->scalarNode(Crawler::DEFAULT_INDEX_PARAM)->defaultValue('web')->end()
                // XPath to element with route
                ->scalarNode(Crawler::LINK_SELECTOR_PARAM)->defaultValue('a[not(@rel="nofollow")]')->end()
                // XPath to element with page ID
                ->scalarNode(Crawler::PAGE_ID_PARAM)->defaultValue('')->end()
                // XPath to element with route
                ->scalarNode(Crawler::ROUTE_NAME_PARAM)->defaultValue('')->end()
                // crawl external links?
                ->scalarNode(Crawler::CRAWL_EXTERNAL_LINKS)->defaultValue(false)->end()
                // depth of crawling external links
                ->scalarNode(Crawler::EXTERNAL_LINKS_DEPTH)->defaultValue(0)->end()
                // class tells to crawler dont index this page
                ->scalarNode(Crawler::NOINDEX_CLASS_PARAM)->defaultValue('crawler__noindex')->end()
                // name of document root directory
                ->scalarNode(Crawler::WEB_DIR)->defaultValue('web')->end()
                // URI to fulltext image store
                ->scalarNode(Crawler::IMAGE_URI)->defaultValue('/uploads/symbio_fulltext_search')->end()
                // XPath to elements with menu
                ->arrayNode(Crawler::MENU_SECTIONS_PARAM)
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function($v) { return preg_split('/\s*,\s*/', $v); })
                    ->end()
                    ->canBeUnset()
                    ->defaultValue(array('html/body//*[@id="menu"]'))
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
                // array with body elements XPath
                ->arrayNode(Crawler::BODY_SECTIONS_PARAM)
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function($v) { return preg_split('/\s*,\s*/', $v); })
                    ->end()
                    ->canBeUnset()
                    ->defaultValue(array('html/body'))
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
                // headline tags
                ->arrayNode(Crawler::TITLE_TAGS_PARAM)
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function($v) { return preg_split('/\s*,\s*/', $v); })
                    ->end()
                    ->canBeUnset()
                    ->defaultValue(array('h1'))
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
                // content parts boost
                ->arrayNode(Crawler::BOOST_PARAM)
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function($v) { return preg_split('/\s*,\s*/', $v); })
                    ->end()
                    ->canBeUnset()
                    ->defaultValue(array(
                        'title' => 2,
                        'description' => 1.5,
                        'h1' => 2,
                        'body' => 1,
                    ))
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
