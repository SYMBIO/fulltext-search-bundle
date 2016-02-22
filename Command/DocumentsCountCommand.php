<?php

namespace Symbio\FulltextSearchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Loads initial data
 */
class DocumentsCountCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array())
            ->addOption('index', null, InputOption::VALUE_OPTIONAL, 'Index name', null)
            ->setName('symbio:fulltext:documents-count')
            ->setDescription('Print count of indexed documents')
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $indexName = $input->getOption('index');

        $crawler = $this->getContainer()->get('symbio_fulltext_search.crawler');
        $crawler->setIndexName($indexName);
        $crawler->setLogger($output);
        $output->writeln(sprintf('Indexed documents: %s', $crawler->indexCount()));
    }
}
