<?php

namespace Symbio\FulltextSearchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Loads initial data
 */
class DeleteIndexCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array())
            ->addOption('index', null, InputOption::VALUE_OPTIONAL, 'Index name', null)
            ->setName('symbio:fulltext:delete-index')
            ->setDescription('Delete all pages in lucene index')
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $indexName = $input->getOption('index');

        $output->writeln(sprintf('Starting at %s', date('d.m.Y H:i:s')));

        $crawler = $this->getContainer()->get('symbio_fulltext_search.crawler');
        $crawler->setIndexName($indexName);
        $crawler->setLogger($output);
        $crawler->deleteIndex();

        $output->writeln(sprintf('Finished deleting at %s', date('d.m.Y H:i:s')));
    }
}
