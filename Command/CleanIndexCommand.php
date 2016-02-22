<?php

namespace Symbio\FulltextSearchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Loads initial data
 */
class CleanIndexCommand extends ContainerAwareCommand
{
	/**
	 * @see Command
	 */
	protected function configure()
	{
		$this
			->setDefinition(array())
			->addOption('index', null, InputOption::VALUE_OPTIONAL, 'Index name', null)
			->setName('symbio:fulltext:mr-proper')
			->setDescription('Clean lucene index from non-existing or corrupted pages')
		;
	}

	/**
	 * @see Command
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$indexName = $input->getOption('index');

		$output->writeln(sprintf('Start cleaning at %s', date('d.m.Y H:i:s')));

		$crawler = $this->getContainer()->get('symbio_fulltext_search.crawler');
		$crawler->setIndexName($indexName);
		$crawler->setLogger($output);
		$crawler->cleanIndex($indexName);

		$output->writeln(sprintf('Finished cleaning at %s', date('d.m.Y H:i:s')));
	}
}
