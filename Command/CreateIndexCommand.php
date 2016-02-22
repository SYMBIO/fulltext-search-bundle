<?php

namespace Symbio\FulltextSearchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Loads initial data
 */
class CreateIndexCommand extends ContainerAwareCommand
{
	protected $force = false;

	/**
	 * @see Command
	 */
	protected function configure()
	{
		$this
			->setDefinition(array())
			->addArgument('url', InputArgument::REQUIRED, 'Site to crawl')
			->addOption('index', null, InputOption::VALUE_OPTIONAL, 'Index name', null)
			->addOption('depth', null, InputOption::VALUE_OPTIONAL, 'The depth to crawl to (default is all)', false)
			->addOption('not-clean', null, InputOption::VALUE_NONE, 'Whether to do not clean index')
			->setName('symbio:fulltext:create-index')
			->setDescription('Creates a lucene index for a website')
		;
        $this->force = true;
	}

	/**
	 * @see Command
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$url = $input->getArgument('url');
		$depth = $input->getOption('depth');
		$indexName = $input->getOption('index');
		$notClean = $input->getOption('not-clean');

		$output->writeln(sprintf('Starting from "%s" at %s', $url, date('d.m.Y H:i:s')));

		$crawler = $this->getContainer()->get('symbio_fulltext_search.crawler');
		$crawler->setIndexName($indexName);
		$crawler->setLogger($output);
		$crawler->createIndex($url, $depth !== false ? : false, $this->force, !$notClean);

		$output->writeln(sprintf('Finished crawling at %s', date('d.m.Y H:i:s')));
	}
}
