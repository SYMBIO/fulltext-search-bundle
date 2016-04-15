<?php

namespace Symbio\FulltextSearchBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Loads initial data
 */
class IndexSinglePageCommand extends CreateIndexCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array())
            ->addArgument('url', InputArgument::REQUIRED, 'Site to crawl')
            ->addOption('index', null, InputOption::VALUE_OPTIONAL, 'Index name', null)
            ->setName('symbio:fulltext:index-single-page')
            ->setDescription('Add single page to lucene index')
        ;
        $this->defaultParameters['depth'] = 0;
        $this->defaultParameters['force'] = true;
        $this->defaultParameters['dont-clean'] = true;
    }
}
