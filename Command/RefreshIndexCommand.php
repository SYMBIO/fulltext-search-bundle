<?php

namespace Symbio\FulltextSearchBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Loads initial data
 */
class RefreshIndexCommand extends CreateIndexCommand
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
			->addOption('depth', null, InputOption::VALUE_OPTIONAL, 'The depth to crawl to (default is all)', false)
			->addOption('not-clean', null, InputOption::VALUE_NONE, 'Whether to do not clean index')
			->setName('symbio:fulltext:refresh-index')
			->setDescription('Refresh a lucene index for a website')
		;
		$this->defaultParameters['force'] = false;
	}
}
