<?php

namespace Symbio\FulltextSearchBundle\Provider;

use Symbio\FulltextSearchBundle\Entity\Page;
use Symbio\FulltextSearchBundle\Event\EventsManager;
use Symbio\FulltextSearchBundle\Service\Crawler;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

abstract class Provider
{
    protected $parameters;
    protected $isExternal;

    protected $eventsManager;

    protected $page;

    public function __construct(EventsManager $eventsManager)
    {
        $this->eventsManager = $eventsManager;
    }

    /**
     * extract page info
     * @param DomCrawler $crawler
     * @param string $url
     * @param array $parameters
     * @return array page info
     */
    public function extract(DomCrawler &$crawler, $url, $isExternal, $parameters = array()) {
        $this->parameters = $parameters;
        $this->isExternal = $isExternal;

        if ($this->shouldBeIndexed($crawler, $url)) {
            $this->page = new Page($this->parameters[Crawler::TITLE_TAGS_PARAM]);

            $this->extractTitle($crawler, $url);
            $this->extractHeadlines($crawler, $url);
            $this->extractDescription($crawler, $url);
            $this->extractBody($crawler, $url);
            $this->extractImage($crawler, $url);
            $this->extractPageId($crawler, $url);
            $this->extractRouteName($crawler, $url);

            $this->eventsManager->firePageExtractedEvent($this->getPage());

            return $this->getPageInfo();
        } else {
            return array(
                'dont_index' => true,
            );
        }
    }

    /**
     * get page
     * @return Page
     */
    public function getPage() {
        return $this->page;
    }

    /**
     * get information about page
     * @return array
     */
    public function getPageInfo() {
        return $this->getPage()->toArray();
    }

    /**
     * check no index flag
     * @param DomCrawler $crawler
     * @param string $uri
     * @return boolean
     */
    protected abstract function shouldBeIndexed(DomCrawler &$crawler, $url);

    /**
     * extract document title
     * @param DomCrawler $crawler
     * @param string $uri
     */
    protected abstract function extractTitle(DomCrawler &$crawler, $url);

    /**
     * extract document description
     * @param DomCrawler $crawler
     * @param string $uri
     */
    protected abstract function extractDescription(DomCrawler &$crawler, $url);

    /**
     * extract document headers
     * @param DomCrawler $crawler
     * @param string $uri
     */
    protected abstract function extractHeadlines(DomCrawler &$crawler, $url);

    /**
     * extract document body
     * @param DomCrawler $crawler
     * @param string $uri
     */
    protected abstract function extractBody(DomCrawler &$crawler, $url);

    /**
     * extract document image
     * @param DomCrawler $crawler
     * @param string $uri
     */
    protected abstract function extractImage(DomCrawler &$crawler, $url);

    /**
     * extract page ID
     * @param DomCrawler $crawler
     * @param string $uri
     */
    protected abstract function extractPageId(DomCrawler &$crawler, $url);

    /**
     * extract route name
     * @param DomCrawler $crawler
     * @param string $uri
     */
    protected abstract function extractRouteName(DomCrawler &$crawler, $url);
}
