<?php

namespace Symbio\FulltextSearchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symbio\FulltextSearchBundle\Service\Crawler;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class SearchController extends Controller
{
	/**
	 * @Route("/search/create", name="search_index_create")
	 * @Template()
	 */
	public function createIndexAction(Request $request)
	{
		set_time_limit(0);

		$command = 'symbio:fulltext:create-index';
		$url = $request->query->get('url', 'http://'.$_SERVER['HTTP_HOST'].($this->container->getParameter("kernel.environment") == 'dev' ? '/app_dev.php/' : '/'));
		$depth = $request->query->get('depth', -1);
		$index = $request->query->get('index', $this->container->getParameter('symbio_fulltext_search.'.Crawler::DEFAULT_INDEX_PARAM));
		$clean = !$request->query->get('dont-clean', false);

		return $this->processIndexOperation($command, $url, $index, $depth, $clean);
	}

	/**
	 * @Route("/search/refresh", name="search_index_refresh")
	 * @Template()
	 */
	public function refreshIndexAction(Request $request)
	{
		set_time_limit(0);

		$command = 'symbio:fulltext:refresh-index';
		$url = $request->query->get('url', 'http://'.$_SERVER['HTTP_HOST'].($this->container->getParameter("kernel.environment") == 'dev' ? '/app_dev.php/' : '/'));
		$depth = $request->query->get('depth', -1);
		$index = $request->query->get('index', $this->container->getParameter('symbio_fulltext_search.'.Crawler::DEFAULT_INDEX_PARAM));
		$clean = !$request->query->get('dont-clean', false);

		return $this->processIndexOperation($command, $url, $index, $depth, $clean);
	}

	protected function processIndexOperation($command, $url, $index, $depth, $clean)
	{
		$configuration = array(
			'command' => $command,
			'url' => $url,
		);
		if ($depth > 0) {
			$configuration['--depth'] = $depth;
		}
		if ($index) {
			$configuration['--index'] = $index;
		}
		if (!$clean) {
			$configuration['--dont-clean'] = true;
		}

		$input = new ArrayInput($configuration);

		// You can use NullOutput() if you don't need the output
		$output = new BufferedOutput();

		$application = new Application($this->get('kernel'));
		$application->setAutoExit(false);
		$application->run($input, $output);

		// return the output, don't use if you used NullOutput()
		$content = $output->fetch();

		// save output as log file
		$logFile = $this->get('kernel')->getRootDir().'/../app/logs/search_'.date('YmdHis').'.log';
		$fp = fopen($logFile, 'w');
		fwrite($fp, 'Command '.$command.' (HTTP call) at '.date('d.m.Y H:i:s')." with params: url -> $url, depth -> $depth, index -> $index, dont-clean -> ".($clean ? 'false' : 'true'));
		fwrite($fp, $content);
		fclose($fp);

		// return new Response(""), if you used NullOutput()
		return new Response(
			$content,
			Response::HTTP_OK,
			array('content-type' => 'text/plain')
		);
	}

	/**
	 * @Route("/search/clean", name="search_clean")
	 * @Template()
	 */
	public function cleanIndexAction(Request $request)
	{
		set_time_limit(0);

		$command = 'symbio:fulltext:mr-proper';
		$index = $request->query->get('index', $this->container->getParameter('symbio_fulltext_search.'.Crawler::DEFAULT_INDEX_PARAM));

		$configuration = array(
			'command' => $command,
		);
		if ($index) {
			$configuration['--index'] = $index;
		}

		$input = new ArrayInput($configuration);

		// You can use NullOutput() if you don't need the output
		$output = new BufferedOutput();

		$application = new Application($this->get('kernel'));
		$application->setAutoExit(false);
		$application->run($input, $output);

		// return the output, don't use if you used NullOutput()
		$content = $output->fetch();

		// save output as log file
		$logFile = $this->get('kernel')->getRootDir().'/../app/logs/search_clean_'.date('YmdHis').'.log';
		$fp = fopen($logFile, 'w');
		fwrite($fp, 'Command '.$command.' (HTTP call) at '.date('d.m.Y H:i:s')." with params: index -> $index");
		fwrite($fp, $content);
		fclose($fp);

		// return new Response(""), if you used NullOutput()
		return new Response(
			$content,
			Response::HTTP_OK,
			array('content-type' => 'text/plain')
		);
	}
}
