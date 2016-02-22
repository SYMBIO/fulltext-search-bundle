<?php

namespace Symbio\FulltextSearchBundle\Service;

use Symbio\FulltextSearchBundle\Entity\Page;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Search
{
	const ITEMS_ON_PAGE_PARAM = 'items_on_page';

	const QUERY_HANDLER = 'orangegate.fulltext_search.query';

	protected $kernel;

	public function __construct(\AppKernel $kernel)
	{
		$this->kernel = $kernel;
	}

	public function search($expression, $page = 1, $conditions = null, $indexName = null)
	{
		if (!$expression) {
			throw new NotFoundHttpException('Empty expression');
		}

		if (!$indexName) {
			$indexName = $this->kernel->getContainer()->getParameter('symbio_fulltext_search.'.Crawler::DEFAULT_INDEX_PARAM);
		}

		if (mb_strlen($expression, 'utf-8') > 2) {
			$index = $this->kernel->getContainer()->get('ivory_lucene_search')->getIndex($indexName);

			$query = $this->prepareQuery($expression, $conditions);
			$results = $index->find($query);

			// strankovani
			$paginator = $this->kernel->getContainer()->get('knp_paginator');
			$pagination = $paginator->paginate(
				$results,
				$page/*page number*/,
				$this->kernel->getContainer()->getParameter('symbio_fulltext_search.items_on_page')/*limit per page*/
			);

			return array(
				'expression' => $expression,
				'pagination' => $pagination,
				'pgdata' => $pagination->getPaginationData(),
			);
		}

		return false;
	}

	public function prepareQuery($expressionOrigin, $conditions)
	{
		setlocale(LC_ALL, "cs_CZ.UTF-8");

		$expressionOrigin = strtr($expressionOrigin, array(','=>'',';'=>'',"'"=>'','"'=>'','-'=>'','_'=>'','/'=>'','\\'=>'','+'=>'','='=>'','?'=>'','.'=>'','!'=>''));
		$expressionTranslit = str_replace("'", '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $expressionOrigin));

		$expressions = array($expressionOrigin);
		if ($expressionOrigin != $expressionTranslit) {
			$expressions[] = $expressionTranslit;
		}

		$words = array();
		foreach($expressions as $expression) {
			if (strpos($expression, ' ') !== false) {
				$expressionWords = explode(' ', $expression);
				foreach($expressionWords as $key => $word) {
					if (!$word || mb_strlen($word, 'utf-8') <= 2) {
						unset($expressionWords[$key]);
					}
				}
				$words = array_merge(self::getWordsCombinations($expressionWords), $words);
			} elseif (!in_array($expression, $words)) {
				$words[] = $expression;
			}
		}

		usort($words, function($a, $b){
			return (count($b) - count($a));
		});

		foreach($words as $wordIndex => $word) {
			if (is_array($word)) {
				if (count($word) > 1) {
					$words[$wordIndex] = '"'.implode(' ', $word).'"^'.count($word);
				} else {
					$words[$wordIndex] = '"'.implode(' ', $word).'"^1';
				}
			} else {
				$words[$wordIndex] = '"'.$word.'"^1';
			}
		}

		$query = '('.implode(' OR ', $words).')';

		// specificke podminky do query
		if (is_array($conditions) && count($conditions)) {
			foreach($conditions as $condition) {
				// TODO - jak v Lucene najit polozky obsahujici url? Wildcard pouzit nejde...
				if (mb_strpos($condition, 'url:', null, 'utf-8') === 0) {
					$uri = trim(substr($condition, 4), '"');
					if (strpos($uri, '://') !== false) {
						$uri = substr($uri,strpos($uri, '://')+3);
						$uri = substr($uri,strpos($uri, '/'));
					}
					$query .= ' AND '.Page::URIS_KEY.':"'.$uri.'"';
				} else {
					$query .= ' AND '.$condition;
				}
			}
		}

		if ($this->kernel->getEnvironment() == 'dev') {
			$session = new Session();
			$session->set(self::QUERY_HANDLER, $query);
		}

		return $query;
	}

	private static function getWordsCombinations($words)
	{
		$combinations = array();

		foreach ($words as $word) {
			self::addCombination(array($word), $combinations);

			$word2Buffer = array();

			foreach ($words as $word2) {
				if ($word != $word2) {
					self::addCombination(array($word, $word2), $combinations);

					if (count($word2Buffer)) {
						foreach($word2Buffer as $word2BufferIndex => $word2BufferItem) {
							self::addCombination(array($word2BufferItem, $word2), $combinations);
							self::addCombination(array($word, $word2BufferItem, $word2), $combinations);

							if (count($word2Buffer) >= 3) {
								if ($word2BufferIndex == 0) {
									self::addCombination(array_merge(array($word2), array_slice($word2Buffer,1)), $combinations);
									self::addCombination(array_merge(array($word, $word2), array_slice($word2Buffer,1)), $combinations);
								} elseif ($word2BufferIndex == count($word2Buffer)-1) {
									self::addCombination(array_merge(array($word2), array_slice($word2Buffer,0,count($word2Buffer)-1)), $combinations);
									self::addCombination(array_merge(array($word, $word2), array_slice($word2Buffer,0,count($word2Buffer)-1)), $combinations);
								} else {
									self::addCombination(array_merge(array($word2), array_slice($word2Buffer,0,$word2BufferIndex), array_slice($word2Buffer,$word2BufferIndex+1)), $combinations);
									self::addCombination(array_merge(array($word, $word2), array_slice($word2Buffer,0,$word2BufferIndex), array_slice($word2Buffer,$word2BufferIndex+1)), $combinations);
								}
							}
						}
						self::addCombination(array_merge(array($word,$word2),$word2Buffer), $combinations);
						self::addCombination(array_merge(array($word2),$word2Buffer), $combinations);
					}

					$word2Buffer[] = $word2;
				}
			}
		}

		return $combinations;
	}

	private static function addCombination($combination, &$combinations)
	{
		sort($combination);
		$combination = array_unique($combination);
		if (!in_array($combination, $combinations))	{
			$combinations[] = $combination;
		}
	}

	public function prepareBreadcrumbsPages($articles)
	{
		$parents = array();

		try {
			$request = $this->kernel->getContainer()->get('request_stack')->getCurrentRequest();
		} catch(\Exception $e) {
			$request = null;
		}

		if (!$request) {
			return $parents;
		}

		$cmsManager = $this->kernel->getContainer()->get('sonata.page.cms_manager_selector')->retrieve();
		$site = $this->kernel->getContainer()->get('sonata.page.site.selector')->retrieve();

		if ($articles) {
			foreach($articles as $article) {
				$field = isset($article->page_id) && $article->page_id ? Page::PAGE_ID_KEY : (isset($article->route_name) && $article->route_name ? Page::ROUTE_NAME_KEY : null);
				if ($field && !isset($parents[$article->{$field}])) {
					$parents[$article->{$field}] = array();
					switch($field) {
						case Page::PAGE_ID_KEY:
							$pageIdentificator = (integer)$article->{$field};
							break;
						case Page::ROUTE_NAME_KEY:
							$pageIdentificator = substr($article->{$field}, 0, -3);
							break;
						default:
							$pageIdentificator = $article->{$field};
					}
					try {
						$articlePage = $cmsManager->getPage($site, $pageIdentificator);
						$parent = $articlePage->getParent();
						if ($parent && $parent->getParent()) {
							do {
								if (strpos($parent->getUrl(), '{') === false) $parents[$article->{$field}][] = $parent;
							} while (($parent = $parent->getParent()) && $parent->getParent());
							krsort($parents[$article->{$field}]);
						}
					} catch(\Exception $e) {
						error_log($e->getMessage());
						// go to next page
					}
				}
			}
		}
		return $parents;
	}

	public function debug($url, $indexName = null)
	{
		if (!$url) {
			throw new NotFoundHttpException();
		}

		if (!$indexName) {
			$indexName = $this->kernel->getContainer()->getParameter('symbio_fulltext_search.'.Crawler::DEFAULT_INDEX_PARAM);
		}

		try {
            $index = $this->kernel->getContainer()->get('ivory_lucene_search')->getIndex($indexName);
			$results = $index->find('url:"'.$url.'"');
		} catch(\Exception $e) {
			// do nothing
		}

		return isset($results) && $results && count($results) ? $results[0] : false;
	}
}
