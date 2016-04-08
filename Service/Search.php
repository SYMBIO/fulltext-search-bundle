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
    protected $indexManager;

	public function __construct(\AppKernel $kernel, IndexManager $indexManager)
	{
		$this->kernel = $kernel;
        $this->indexManager = $indexManager;
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
			$index = $this->indexManager->getIndex($indexName);

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
				$wordsCombinations = self::getWordsCombinations($expressionWords);
                foreach($wordsCombinations as $wordsCombination) {
                    if (!in_array($wordsCombination, $words)) {
                        $words[] = $wordsCombination;
                    }
                }
			} elseif (!in_array($expression, $words)) {
				$words[] = $expression;
			}
		}

		usort($words, function($a, $b){
			return (count($b) - count($a));
		});

		$scoredWords = array();
        $scoreMax = 0;
		foreach($words as $wordIndex => $word) {
			if (is_array($word)) {
                $words[$wordIndex] = implode(' ', $word);
				if (count($word) > 1) {
                    if (count($word) > $scoreMax) $scoreMax = count($word);
                    $scoredWords[$wordIndex] = '"'.$words[$wordIndex].'"^'.count($word);
				} else {
                    if (1 > $scoreMax) $scoreMax = 1;
                    $scoredWords[$wordIndex] = '"'.$words[$wordIndex].'"^1';
				}
			} else {
                if (1 > $scoreMax) $scoreMax = 1;
                $scoredWords[$wordIndex] = '"'.$word.'"^1';
			}
		}

        foreach($expressions as $expression) {
            if (!in_array($expression, $words)) {
                $scoredWords[] = '"'.$expression.'"^'.($scoreMax + 1);
            }
        }

		$query = '('.implode(' OR ', $scoredWords).')';

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

    protected static function getWordsCombinations($words)
	{
		// primary array with combinations result
        $combinations = array();

        // secondary array to compare if such words combination is not included (prevent wrong words order)
        $combinationsSorted = array();

		foreach ($words as $word) {
			self::addCombination(array($word), $combinations, $combinationsSorted);

			$word2Buffer = array();

			foreach ($words as $word2) {
				if ($word != $word2) {
					self::addCombination(array($word, $word2), $combinations, $combinationsSorted);

					if (count($word2Buffer)) {
						foreach($word2Buffer as $word2BufferIndex => $word2BufferItem) {
							self::addCombination(array($word2BufferItem, $word2), $combinations, $combinationsSorted);
							self::addCombination(array($word, $word2BufferItem, $word2), $combinations, $combinationsSorted);

							if (count($word2Buffer) >= 3) {
								if ($word2BufferIndex == 0) {
									self::addCombination(array_merge(array($word2), array_slice($word2Buffer,1)), $combinations, $combinationsSorted);
									self::addCombination(array_merge(array($word, $word2), array_slice($word2Buffer,1)), $combinations, $combinationsSorted);
								} elseif ($word2BufferIndex == count($word2Buffer)-1) {
									self::addCombination(array_merge(array($word2), array_slice($word2Buffer,0,count($word2Buffer)-1)), $combinations, $combinationsSorted);
									self::addCombination(array_merge(array($word, $word2), array_slice($word2Buffer,0,count($word2Buffer)-1)), $combinations, $combinationsSorted);
								} else {
									self::addCombination(array_merge(array($word2), array_slice($word2Buffer,0,$word2BufferIndex), array_slice($word2Buffer,$word2BufferIndex+1)), $combinations, $combinationsSorted);
									self::addCombination(array_merge(array($word, $word2), array_slice($word2Buffer,0,$word2BufferIndex), array_slice($word2Buffer,$word2BufferIndex+1)), $combinations, $combinationsSorted);
								}
							}
						}
						self::addCombination(array_merge(array($word,$word2),$word2Buffer), $combinations, $combinationsSorted);
						self::addCombination(array_merge(array($word2),$word2Buffer), $combinations, $combinationsSorted);
					}

					$word2Buffer[] = $word2;
				}
			}
		}

		return $combinations;
	}

    protected static function addCombination($combination, &$combinations, &$combinationsSorted)
	{
        $combinationSorted = $combination = array_unique($combination);

        sort($combinationSorted);

		if (!in_array($combinationSorted, $combinationsSorted))	{
			$combinations[] = $combination;
            $combinationsSorted[] = $combinationSorted;
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
            $index = $this->indexManager->getIndex($indexName);
			$results = $index->find('url:"'.$url.'"');
		} catch(\Exception $e) {
			// do nothing
		}

		return isset($results) && $results && count($results) ? $results[0] : false;
	}
}
