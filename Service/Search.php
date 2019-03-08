<?php

namespace Symbio\FulltextSearchBundle\Service;

use Symbio\FulltextSearchBundle\Entity\Page;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZendSearch\Lucene\Analysis\Analyzer\Analyzer;
use ZendSearch\Lucene\Analysis\Analyzer\Common\Utf8\CaseInsensitive;
use ZendSearch\Lucene\Lucene;
use ZendSearch\Lucene\Search\Query\Boolean;
use ZendSearch\Lucene\Search\QueryParser;

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

        Lucene::setTermsPerQueryLimit(2048);
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

			Analyzer::setDefault(New CaseInsensitive());
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

		$queryWords = array();

		$query = new Boolean();
		foreach($expressions as $expression) {
            // more words in expression
            if (count($expressionWords = explode(' ', $expression)) > 1) {
                // whole expression
                $query->addSubquery(QueryParser::parse('"'.$expression.'"', 'utf-8'));
                // expression words
                foreach($expressionWords as $expressionWord) {
                    if (
                        !in_array($expressionWord, $queryWords)
                        && $this->isWordValid($expressionWord)
                    ) {
                        $queryWords[] = $expressionWord;
                        $query->addSubquery(QueryParser::parse($expressionWord.'*', 'utf-8'));
                    }
                }
            }
            // one-word expression
            else {
                $query->addSubquery(QueryParser::parse($expression.'*', 'utf-8'));
            }
		}

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

    protected function isWordValid($word)
    {
        if (
            is_numeric($word)
            ||
            mb_strlen($word, 'utf-8') < 3
            ||
            mb_strlen(preg_replace('/[0-9]+/', '', $word)) < 3
        ) {
            return false;
        }

        return true;
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
