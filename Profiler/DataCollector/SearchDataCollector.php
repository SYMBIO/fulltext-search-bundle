<?php

namespace Symbio\FulltextSearchBundle\Profiler\DataCollector;

use Symbio\FulltextSearchBundle\Entity\Page;
use Symbio\FulltextSearchBundle\Service\Crawler;
use Symbio\FulltextSearchBundle\Service\Search;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class SearchDataCollector extends DataCollector
{
	protected $search;
    protected $titleTypes;

	protected $records = array();
	protected $fields = array();

	protected $queryData = array();

	public function __construct(Search $search)
	{
		$this->search = $search;
	}

	public function collect(Request $request, Response $response, \Exception $exception = null)
	{
		$url = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$urlData = $this->search->debug($url);

		if ($urlData) {
			foreach ($urlData->getIndex()->getFieldNames() as $field) {
				if (isset($urlData->{$field}) && $urlData->{$field}) {
					$this->records[$field] = $urlData->{$field};
				}
			}
		}

		$this->queryData = $this->fetchQueryData();
	}

	public function getToolbarFields()
	{
		return array(
			Page::TITLE_KEY => 'Title',
			Page::DESCRIPTION_KEY => 'Description',
			Page::IMAGE_KEY => 'Image',
			Page::PAGE_ID_KEY => 'Page ID',
			Page::ROUTE_NAME_KEY => 'Route name',
		);
	}

	public function getPanelFields()
	{
		$fields = array(
			Page::TITLE_KEY => 'Title',
			Page::TITLE_KEY.'_translit' => 'Title translit',
			Page::DESCRIPTION_KEY => 'Description',
			Page::DESCRIPTION_KEY.'_translit' => 'Description translit',
			Page::BODY_KEY => 'Body',
            Page::BODY_KEY.'_translit' => 'Body translit',
            Page::IMAGE_KEY => 'Image',
        );

        $extraFields = array(
            Page::URL_KEY => 'URL',
            Page::URIS_KEY => 'Ancestor URIs',
            Page::PAGE_ID_KEY => 'Page ID',
            Page::ROUTE_NAME_KEY => 'Route name',
        );

        $additionalFields = array();
        foreach($this->getRecords() as $recordKey => $recordValue) {
            if (!in_array($recordKey, array_keys($fields)) && !in_array($recordKey, array_keys($extraFields))) {
                $additionalFields[$recordKey] = strtr(strtoupper($recordKey), array('_TRANSLIT' => ' translit'));
            }
        }
        if (count($additionalFields)) {
            ksort($additionalFields);
            $fields = array_merge($fields, $additionalFields);
        }

        $fields = array_merge($fields, $extraFields);

        return $fields;
	}

	public function getQueryFields()
	{
		return array(
			Search::QUERY_HANDLER => 'Query',
		);
	}

	public function getRecords()
	{
		return $this->records;
	}

	public function getRecord($name)
	{
        switch($name) {
            case Page::URIS_KEY:
                $uris = explode(' ', $this->__get($name));
                krsort($uris);
                return implode($uris, '<br/>');
                break;
            default:
                return $this->__get($name);
        }
	}

	public function hasToolbarRecords()
	{
		foreach ($this->getToolbarFields() as $field => $title) {
			$record = $this->getRecord($field);
			if ($record) return true;
		}
		return false;
	}

	public function hasPanelRecords()
	{
		foreach ($this->getPanelFields() as $field => $title) {
            $record = $this->getRecord($field);
			if ($record) return true;
		}
		return false;
	}

	public function __get($name)
	{
		return $this->records && isset($this->records[$name]) ? $this->records[$name] : '';
	}

	public function hasQueryData()
	{
		if (!count($this->queryData) && (new Session())->has(Search::QUERY_HANDLER)) {
			$this->fetchQueryData();
		}
		return count($this->queryData) > 0 || (new Session())->has(Search::QUERY_HANDLER);
	}

	public function fetchQueryData()
	{
		$session = new Session();
		foreach (array_keys($this->getQueryFields()) as $queryField) {
			$this->queryData[$queryField] = $session->get($queryField, false);
		}
	}

	public function getQueryFieldData($field)
	{
		(new Session)->remove($field);
		return isset($this->queryData[$field]) ? $this->queryData[$field] : '';
	}

	/**
	 * serialize the data.
	 *
	 * @return string
	 */
	public function serialize()
	{
		return serialize(array(
			'records' => $this->records,
		));
	}

	/**
	 * Unserialize the data.
	 *
	 * @param string $data
	 */
	public function unserialize($data)
	{
		$merged = unserialize($data);
		$this->records = $merged['records'];
	}

	public function getName()
	{
		return 'symbio_fulltext_search';
	}
}