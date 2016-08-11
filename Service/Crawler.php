<?php

namespace Symbio\FulltextSearchBundle\Service;

use Goutte\Client;
use Symbio\FulltextSearchBundle\Entity\Page;
use Symbio\FulltextSearchBundle\Provider\HtmlProvider;
use Symbio\FulltextSearchBundle\Provider\PdfProvider;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use ZendSearch\Lucene\Document;
use ZendSearch\Lucene\Document\Field;

class Crawler {
    const USER_AGENT_PARAM = 'user_agent';
    const MENU_SECTIONS_PARAM = 'menu_sections';
    const BODY_SECTIONS_PARAM = 'body_sections';
    const TITLE_TAGS_PARAM = 'title_tags';
    const TITLE_CLASS_PARAM = 'title_class';
    const DEFAULT_IMAGE_PARAM = 'default_image';
    const DEFAULT_INDEX_PARAM = 'default_index';
    const BOOST_PARAM = 'boost';
    const LINK_SELECTOR_PARAM = 'link_selector';
    const CRAWL_EXTERNAL_LINKS = 'crawl_external_links';
    const EXTERNAL_LINKS_DEPTH = 'external_links_depth';
    const NOINDEX_CLASS_PARAM = 'noindex_class';
    const PAGE_ID_PARAM = 'page_id';
    const ROUTE_NAME_PARAM = 'route_name';

    const ROOT_DIR = 'root_dir';
    const WEB_DIR = 'web_dir';
    const IMAGE_URI = 'image_uri';

    protected $baseUrl;
    protected $protocol;
    protected $host;

    protected $maxDepth;
    protected $indexName;

    protected $container;
    protected $indexManager;

    protected $logger;

    protected $parameters;

    protected $pages;
    protected $pagesStatusStats;

    protected $bodyLinksXPath;

    public function __construct(ContainerInterface $container, IndexManager $indexManager) {
        $this->container = $container;
        $this->indexManager = $indexManager;

        $this->parameters = array(
            self::USER_AGENT_PARAM => $container->getParameter('symbio_fulltext_search.'.self::USER_AGENT_PARAM),
            self::TITLE_CLASS_PARAM => $container->getParameter('symbio_fulltext_search.'.self::TITLE_CLASS_PARAM),
            self::DEFAULT_IMAGE_PARAM => $container->getParameter('symbio_fulltext_search.'.self::DEFAULT_IMAGE_PARAM),
            self::DEFAULT_INDEX_PARAM => $container->getParameter('symbio_fulltext_search.'.self::DEFAULT_INDEX_PARAM),
            self::PAGE_ID_PARAM => $container->getParameter('symbio_fulltext_search.'.self::PAGE_ID_PARAM),
            self::ROUTE_NAME_PARAM => $container->getParameter('symbio_fulltext_search.'.self::ROUTE_NAME_PARAM),
            self::MENU_SECTIONS_PARAM => $container->getParameter('symbio_fulltext_search.'.self::MENU_SECTIONS_PARAM),
            self::BODY_SECTIONS_PARAM => $container->getParameter('symbio_fulltext_search.'.self::BODY_SECTIONS_PARAM),
            self::TITLE_TAGS_PARAM => $container->getParameter('symbio_fulltext_search.'.self::TITLE_TAGS_PARAM),
            self::BOOST_PARAM => $container->getParameter('symbio_fulltext_search.'.self::BOOST_PARAM),
            self::LINK_SELECTOR_PARAM => $container->getParameter('symbio_fulltext_search.'.self::LINK_SELECTOR_PARAM),
            self::CRAWL_EXTERNAL_LINKS => $container->getParameter('symbio_fulltext_search.'.self::CRAWL_EXTERNAL_LINKS),
            self::EXTERNAL_LINKS_DEPTH => $container->getParameter('symbio_fulltext_search.'.self::EXTERNAL_LINKS_DEPTH),
            self::NOINDEX_CLASS_PARAM => $container->getParameter('symbio_fulltext_search.'.self::NOINDEX_CLASS_PARAM),
            self::ROOT_DIR => $container->get('kernel')->getRootDir() . '/../',
            self::WEB_DIR => $container->getParameter('symbio_fulltext_search.'.self::WEB_DIR),
            self::IMAGE_URI => $container->getParameter('symbio_fulltext_search.'.self::IMAGE_URI),
        );

        // assemble body links XPath array
        $linkSelector = $this->parameters[self::LINK_SELECTOR_PARAM];
        $this->bodyLinksXPath = array_map(function($sectionSelector) use ($linkSelector) {
            return $sectionSelector.'//'.$linkSelector;
        }, $this->parameters[self::BODY_SECTIONS_PARAM]);
    }

    /**
     * initiate the crawling mechanism on all links
     * @param string $baseUrl URL to crawl
     * @param integer $maxDepth Maximal depth of crawling
     * @param boolean $force Force to rewrite all documents
     * @param boolean $clean Whether to do index cleaning
     * @param boolean $indexing Whether to do website indexing
     */
    public function createIndex($baseUrl, $maxDepth = false, $force = true, $clean = true, $indexing = true) {
        \error_reporting(E_ALL & ~E_NOTICE);

        // first remove from index all non-existing or exhausted pages
        if ($clean) {
            $this->cleanIndex();
        }

        if ($indexing) {
            $this->log(sprintf('Generate index "%s" ...', $this->indexName));
        }

        // http protocol not included, prepend it to the base url
        if (strpos($baseUrl, 'http') === false) {
            $baseUrl = 'http://' . $baseUrl;
        }

        $this->baseUrl = $baseUrl;
        $this->protocol = substr($baseUrl, 0, strpos($baseUrl, ':'));

        $host = substr($baseUrl, strlen($this->protocol.'://'));
        $this->host = strpos($host, '/') !== false ? substr($host, 0, strpos($host, '/')) : $host;

        if ($this->baseUrl == $this->protocol.'://'.$this->host) {
            $this->baseUrl .= '/';
        }

        $this->pages = array();
        $this->maxDepth = $maxDepth;

        // initialize first element in the pages
        $this->pages[$this->baseUrl] = array(
            'links_text' => array('BASE_URL'),
            'absolute_url' => $this->baseUrl,
            'frequency' => 1,
            'visited' => false,
            'external_link' => false,
            'original_urls' => array($this->baseUrl),
        );

        // crawl website into pages array
        $this->log('Crawling started ...');

        $this->pagesStatusStats = array();
        $this->crawlSinglePage($this->baseUrl, $this->maxDepth);

        $this->log('Crawling finished');

        // create index from pages array
        if ($indexing) {
            $this->indexPages($force);
        }

        // print status codes stats
        $pagesStatusStats = array();
        foreach($this->pagesStatusStats as $statusCode => $statusCount) {
            $pagesStatusStats[] = sprintf('%s: %s', $statusCode, $statusCount);
        }
        $this->log(sprintf('Status codes: '.implode(', ', $pagesStatusStats)));

        if ($indexing) {
            $this->log('Generating finished');
        }
    }

    /**
     * index cleaning - remove non existing or corrupted pages
     */
    public function cleanIndex() {
        \error_reporting(E_ALL & ~E_NOTICE);

        $this->log(sprintf('Clean index "%s" ...', $this->indexName));

        try {
            $index = $this->indexManager->getIndex($this->indexName);
        } catch(\Exception $e) {
            $index = null;
        }

        $counter = 0;

        if (is_object($index) && $index->count()) {
            $this->log('Indexed documents: '.$index->count());

            foreach(range(0,$index->count()-1) as $documentId) {
                if ($index->isDeleted($documentId)) continue;

                $document = $index->getDocument($documentId);
                if (is_object($document) && isset($document->url) && $document->url) {
                    $client = new Client();
                    try {
                        @$crawler = $client->request('GET', $document->url);
                        $status = $client->getResponse()->getStatus();
                    } catch(\Exception $e) {
                        $status = 500;
                    }

                    $this->log($status . ($status >= 400 ? ' REMOVE' : '') . ':' . $documentId . ':' . $document->url);

                    if ($status >= 400) {
                        $index->delete($documentId);
                        $counter++;
                    }
                } else {
                    $this->log('410 REMOVE' . ':' . $documentId . ':empty URL');
                    $index->delete($documentId);
                    $counter++;
                }
            }

            // commit your change
            $index->commit();

            // if you want you can optimize your index
            $index->optimize();
        } else {
            $this->log('Indexed documents: 0');
        }

        $this->log(sprintf('Cleaning finished with %s removed document', $counter));
    }

    /**
     * index deleting - remove all pages
     */
    public function deleteIndex() {
        \error_reporting(E_ALL & ~E_NOTICE);

        $this->log(sprintf('Delete index "%s" ...', $this->indexName));

        try {
            $index = $this->indexManager->getIndex($this->indexName);
        } catch(\Exception $e) {
            $index = null;
        }

        if (is_object($index)) {
            $this->indexManager->removeIndex($this->indexName, true);
            $this->log('Deleting finished');
        } else {
            $this->log('Nothing to delete, index doesn\'t exists');
        }
    }

    /**
     * returns index documents count
     */
    public function indexCount() {
        try {
            $index = $this->indexManager->getIndex($this->indexName);
        } catch(\Exception $e) {
            $index = null;
        }

        if (is_object($index) && $index->count()) {
            return $index->count();
        } else {
            return 0;
        }
    }

    /**
     * crawl url and return pages
     * @param string $baseUrl URL to crawl
     * @return array
     */
    public function extractPages($baseUrl) {
        \error_reporting(E_ALL & ~E_NOTICE);

        // http protocol not included, prepend it to the base url
        if (strpos($baseUrl, 'http') === false) {
            $baseUrl = 'http://' . $baseUrl;
        }

        $this->baseUrl = $baseUrl;
        $this->protocol = substr($baseUrl, 0, strpos($baseUrl, ':'));

        $host = substr($baseUrl, strlen($this->protocol.'://'));
        $this->host = strpos($host, '/') !== false ? substr($host, 0, strpos($host, '/')) : $host;

        if ($this->baseUrl == $this->protocol.'://'.$this->host) {
            $this->baseUrl .= '/';
        }

        $this->pages = array();

        // initialize first element in the pages
        $this->pages[$this->baseUrl] = array(
            'links_text' => array('BASE_URL'),
            'absolute_url' => $this->baseUrl,
            'frequency' => 1,
            'visited' => false,
            'external_link' => false,
            'original_urls' => array($this->baseUrl),
        );

        // crawl website into pages array
        $this->log('Crawling started ...');

        $this->crawlSinglePage($this->baseUrl, false);

        $this->log('Crawling finished');

        return $this->pages;
    }

    /**
     * crawling single url after checking the depth value
     * @param string $url
     * @param int $depth
     */
    protected function crawlPages($url, $depth) {
        if (!$url || (isset($this->pages[$url]) && isset($this->pages[$url]['visited']) && $this->pages[$url]['visited'])) return;

        $client = new Client();
        $client->setHeader('User-Agent', $this->parameters['user_agent']);

        try {
            $crawler = $client->request('GET', $url);
            $statusCode = $client->getResponse()->getStatus();
            $this->log(sprintf("%s: %s", $statusCode, $url));
        } catch(\Exception $e) {
            $statusCode = 400;
            $this->log(sprintf("%s: %s", $statusCode, $url));
            $this->log(sprintf("Error page retrieving (%s)", $e->getMessage()));
        }

        $this->setPageStatusStats($statusCode);

        if ($statusCode >= 400) {
            return;
        }

        if (!isset($this->pages[$url])) $this->pages[$url] = array();

        $this->pages[$url]['status_code'] = $statusCode;

        $contentType = $client->getResponse()->getHeader('Content-Type');
        if (strpos($contentType, ';') !== false) {
            $contentType = substr($contentType, 0, strpos($contentType, ';'));
        }

        switch($contentType) {
            case 'text/html':
                $provider = $this->container->get('symbio_fulltext_search.provider.html');

                try {
                    $pageInfo = $provider->extract(array(
                        HtmlProvider::CONFIG_CRAWLER_PARAMETERS_HANDLER => $this->parameters,
                        HtmlProvider::CONFIG_CRAWLER_HANDLER => $crawler,
                        HtmlProvider::CONFIG_IS_EXTERNAL_LINK_HANDLER => isset($this->pages[$url]['external_link']) ? $this->pages[$url]['external_link'] : false,
                    ));
                } catch (\Exception $e) {
                    error_log('Error retrieving data from link: '.$url.' ('.$e->getMessage().') ');
                    $this->pages[$url]['dont_index'] = true;
                }

                if ($pageInfo) {
                    $this->pages[$url] = array_merge($this->pages[$url], $pageInfo);
                    $this->pages[$url]['visited'] = true; // mark current url as visited

                    if (!isset($this->pages[$url]['external_link']) || !$this->pages[$url]['external_link']) { // for internal uris, get all links inside
                        $links = $this->extractLinks($crawler, $url);
                        if (count($links)) {
                            $this->crawlChildLinks($links, $depth !== false ? $depth - 1 : false);
                        }
                    } elseif ($this->parameters[self::CRAWL_EXTERNAL_LINKS] && $this->parameters[self::EXTERNAL_LINKS_DEPTH] > 0) {
                        $links = $this->extractLinks($crawler, $url);
                        if (count($links)) {
                            $this->crawlChildLinks($links, $this->parameters[self::EXTERNAL_LINKS_DEPTH]);
                        }
                    }
                }
                break;
        }
    }

    /**
     * extracting all <a> tags in the crawled document,
     * and return an array containing information about links like: uri, absolute_url, frequency in document
     * @param DomCrawler $crawler
     * @param string $url
     * @return array
     */
    protected function extractLinks(DomCrawler &$crawler, $ancestorUrl) {
        $links = array();

        // if homepage then extract links from menu parts
        if ($ancestorUrl == $this->baseUrl) {
            // assemble menu links XPath array
            $linkSelector = $this->parameters[self::LINK_SELECTOR_PARAM];
            $menuLinksXPath = array_map(function($menuSectionSelector) use ($linkSelector) {
                return $menuSectionSelector.'//'.$linkSelector;
            }, $this->parameters[self::MENU_SECTIONS_PARAM]);

            $selectors = array_merge($menuLinksXPath, $this->bodyLinksXPath);
        } else {
            $selectors = $this->bodyLinksXPath;
        }

        foreach($selectors as $selector) {
            $crawler->filterXPath($selector)->each(function(DomCrawler $node, $i) use (&$links) {
                $nodeText = trim($node->text());
                $nodeUrl = trim($node->attr('href'));

                if (strpos($nodeUrl, 'mailto:') !== false || strpos($nodeUrl, 'tel:') !== false || strpos($nodeUrl, 'phone:') !== false) return;

                $url = $this->normalizeLink($nodeUrl);

                if (!isset($this->pages[$url])) {
                    if (!isset($links[$url])) {
                        $links[$url] = array(
                            'original_url' => array(),
                            'links_text' => array(),
                            'frequency' => 0,
                        );
                    }

                    $links[$url]['original_url'][$nodeUrl] = $nodeUrl;
                    $links[$url]['links_text'][$nodeText] = $nodeText;

                    if ($this->checkIfCrawlable($nodeUrl)) {
                        if (!preg_match("@^http(s)?@", $nodeUrl)) { //not absolute link
                            $links[$url]['absolute_url'] = $this->protocol.'://'.$this->host.$nodeUrl;
                        } else {
                            $links[$url]['absolute_url'] = $nodeUrl;
                        }

                        $links[$url]['external_link'] = $this->isPageExternal($links[$url]['absolute_url']);
                    } else {
                        $links[$url]['dont_visit'] = true;
                        $links[$url]['external_link'] = false;
                    }

                    $links[$url]['visited'] = false;
                    $links[$url]['frequency']++;
                }
            });
        }

        if (isset($links[$ancestorUrl])) { // if page is linked to itself, ex. homepage
            $links[$ancestorUrl]['visited'] = true; // avoid cyclic loop
        }

        return $links;
    }

    /**
     * after checking the depth limit of the links array passed
     * check if the link if the link is not visited/traversed yet, in order to traverse
     * @param array $links
     * @param int $depth
     */
    protected function crawlChildLinks($links, $depth) {
        if ($depth !== false && $depth == 0) return;

        foreach ($links as $url => $info) {
            if (($this->isPageExternal($url) && !$this->parameters[self::CRAWL_EXTERNAL_LINKS]) || !$this->checkIfCrawlable($url)) continue;

            if (strpos($url, '://') === false) {
                $url = strtr($url, array('//'=>'/'));
            }

            if (!isset($this->pages[$url])) {
                $this->pages[$url] = $info;
            } else {
                $this->pages[$url]['original_urls'] = isset($this->pages[$url]['original_urls'])?array_merge($this->pages[$url]['original_urls'], $info['original_urls']):$info['original_urls'];
                $this->pages[$url]['links_text'] = isset($this->pages[$url]['links_text'])?array_merge($this->pages[$url]['links_text'], $info['links_text']):$info['links_text'];
                if (@$this->pages[$url]['visited']) { //already visited link)
                    $this->pages[$url]['frequency'] = @$this->pages[$url]['frequency'] + @$info['frequency'];
                }
            }

            if (!$this->pages[$url]['visited'] && (!isset($this->pages[$url]['dont_visit']) || !$this->pages[$url]['dont_visit'])) { //traverse those that not visited yet
                $this->crawlSinglePage($this->normalizeLink($links[$url]['absolute_url']), $depth);
            }
        }
    }

    /**
     * crawl single page
     * @param string $link
     * @param int $depth
     */
    protected function crawlSinglePage($link, $depth) {
        $this->crawlPages($link, $depth);
    }

    /**
     * increase HTTP status stats
     * @param int HTTP status
     */
    protected function setPageStatusStats($status) {
        if (!is_array($this->pagesStatusStats)) $this->pagesStatusStats = array();
        if (!isset($this->pagesStatusStats[$status])) $this->pagesStatusStats[$status] = 0;
        $this->pagesStatusStats[$status]++;
    }

    /**
     * starts index creating
     * @param boolean $force Force to rewrite all documents
     */
    protected function indexPages($force = true) {
        $index = $this->indexManager->getIndex($this->indexName);

        $this->log(sprintf('%s index "%s" ...', $force ? 'Create' : 'Refresh', $this->indexName));

        $counter = 0;

        if (is_object($index)) {
            foreach ($this->pages as $url => $page) {
                if (!$this->isPageValid($url, $page) || (isset($page['dont_index']) && $page['dont_index'])) {
                    if (!isset($page['dont_index']) || !$page['dont_index'] && (!isset($page[Page::TITLE_KEY]) || !$page[Page::TITLE_KEY])) {
                        $this->log('Error: title didn\'t found on page '.$url);
                    }
                    continue;
                }

                // find URL documents
                $hits = $index->find('url:"'.$url.'"');
                if ($hits && is_array($hits) && count($hits) == 0) {
                    $hits = false;
                }

                // delete all the URL documents if forced fetching
                if ($force && $hits) {
                    // there might be more than one in the index
                    foreach ($hits as $hit) {
                        if ($hit->id) $index->delete($hit->id);
                    }
                }

                $this->log($url);

                // index document if URL document doesnt exists or forced fetching
                if (!$hits || $force) {
                    try {
                        $index->addDocument($this->createDocument($url, $page));
                        $counter++;
                    } catch(\Exception $e) {
                        $this->log(sprintf('Indexing URL %s failed', $url));
                    }
                }
            }

            // commit your change
            $index->commit();

            // if you want you can optimize your index
            $this->log('Optimize index...');
            $index->optimize();
        }

        $this->log(sprintf('%s finished with %s documents', $force ? 'Creating' : 'Refreshing', $counter));
    }

    /**
     * create document from configured fields within extracted data
     * @param string $url
     * @param array $page
     * @return Document
     */
    protected function createDocument($url, $page) {
        $document = new Document();

        if (!isset($page['status_code'])) {
            $page['status_code'] = 000;//tmp
        }

        setlocale(LC_ALL, "cs_CZ.UTF-8");

        $document->addField(Field::keyword('url', $url));

        // ancestor URLs to search by URL
        $urlParts = parse_url($url);
        if (isset($urlParts['path']) && $urlParts['path'] && strlen($urlParts['path']) > 1) {
            $uri = $urlParts['path'];
            $uris = array($uri);
            do {
                $uri = substr($uri, 0, strrpos($uri, '/'));
                $uris[] = $uri;
            } while(strrpos($uri, '/') > 1);
            $document->addField(Field::text(Page::URIS_KEY, implode(' ', $uris)));
        }

        foreach(array(Page::TITLE_KEY,Page::DESCRIPTION_KEY,Page::BODY_KEY,Page::IMAGE_KEY) as $fieldName) {
            $fieldValue = isset($page[$fieldName]) ? $page[$fieldName] : '';
            switch($fieldName) {
                case Page::TITLE_KEY:
                case Page::DESCRIPTION_KEY:
                case Page::BODY_KEY:
                    $field = Field::text($fieldName, $fieldValue);
                    // translit
                    $fieldTranslit = Field::text($fieldName.'_translit', str_replace("'", '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $fieldValue)));
                    $fieldTranslit->boost = isset($this->parameters[self::BOOST_PARAM][$fieldName]) ? $this->parameters[self::BOOST_PARAM][$fieldName] : 1.25;
                    $document->addField($fieldTranslit);
                    break;
                case Page::IMAGE_KEY:
                    $field = Field::unIndexed($fieldName, $fieldValue);
                    break;
                default:
                    $translitValue = str_replace("'", '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $fieldValue));
                    $field = Field::text($fieldName, $fieldValue.($translitValue != $fieldValue ? ' '.$translitValue : ''));
            }
            $field->boost = isset($this->parameters[self::BOOST_PARAM][$fieldName]) ? $this->parameters[self::BOOST_PARAM][$fieldName] : 1.25;
            $document->addField($field);
        }

        // title tags as configured i.e. h1, h2, ...
        foreach($this->parameters[self::TITLE_TAGS_PARAM] as $fieldName) {
            $fieldValue = Page::hasHeadlineType($page, $fieldName) ? Page::getHeadline($page, $fieldName) : '';

            $field = Field::text($fieldName, $fieldValue);
            $field->boost = isset($this->parameters[self::BOOST_PARAM][$fieldName]) ? $this->parameters[self::BOOST_PARAM][$fieldName] : 1;
            $document->addField($field);

            $fieldTranslit = Field::text($fieldName.'_translit', str_replace("'", '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $fieldValue)));
            $fieldTranslit->boost = isset($this->parameters[self::BOOST_PARAM][$fieldName]) ? $this->parameters[self::BOOST_PARAM][$fieldName] : 1.25;
            $document->addField($fieldTranslit);
        }

        // page ID if selector defined
        if ($this->parameters[self::PAGE_ID_PARAM]) {
            $fieldValue = isset($page[Page::PAGE_ID_KEY]) ? $page[Page::PAGE_ID_KEY] : '';
            $field = Field::unIndexed(Page::PAGE_ID_KEY, $fieldValue);
            $document->addField($field);
        }

        // route name if selector defined
        if ($this->parameters[self::ROUTE_NAME_PARAM]) {
            $fieldValue = isset($page[Page::ROUTE_NAME_KEY]) ? $page[Page::ROUTE_NAME_KEY] : '';
            $field = Field::unIndexed(Page::ROUTE_NAME_KEY, $fieldValue);
            $document->addField($field);
        }

        return $document;
    }

    /**
     * checks the uri if can be crawled or not
     * in order to prevent links like "javascript:void(0)" or "#something" from being crawled again
     * @param string $uri
     * @return boolean
     */
    protected function checkIfCrawlable($uri) {
        if (empty($uri)) return false;

        $stop_links = array(//returned deadlinks
            '@^javascript\:void\(0\)$@',
            '@^#.*@',
        );

        foreach ($stop_links as $ptrn) {
            if (preg_match($ptrn, $uri)) {
                return false;
            }
        }

        return true;
    }

    /**
     * normalize link before visiting it
     * currently just remove url hash from the string
     * @param string $uri
     * @return string
     */
    protected function normalizeLink($url) {
        $url = preg_replace('@#.*$@', '', $url);

        // relative link
        if (!preg_match("@^http(s)?@", $url)) {
            $url = $this->protocol . '://' . $this->host . $url;
        }

        return $url;
    }

    /**
     * check if the link leads to external site or not
     * @param string $url
     * @return boolean
     */
    protected function isPageExternal($url) {
        return preg_match("@^http(s)?@", $url) && strpos($url, $this->protocol.'://'.$this->host) !== 0;
    }

    /**
     * check if the page has basic properties
     * @param string $url
     * @param array $page
     * @return boolean
     */
    protected function isPageValid($url, $page) {
        return $url && isset($page[Page::TITLE_KEY]) && $page[Page::TITLE_KEY];
    }

    /**
     * set index name
     * @param string $indexName
     */
    public function setIndexName($indexName) {
        $this->indexName = $indexName ? $indexName : $this->parameters[self::DEFAULT_INDEX_PARAM];
    }

    /**
     * set logger
     * @param OutputInterface $logger
     */
    public function setLogger(OutputInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * log message
     * @param string $message
     * @param boolean $newLine Print new line at the message end
     */
    protected function log($message, $newLine = true) {
        if ($this->logger) {
            switch(get_class($this->logger)) {
                case 'Symfony\Component\Console\Output\BufferedOutput':
                case 'Symfony\Component\Console\Output\ConsoleOutput':
                    $this->logger->{$newLine?'writeln':'write'}($message);
                    break;
            }
        }
    }
}