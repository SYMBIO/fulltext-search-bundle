<?php

namespace Symbio\FulltextSearchBundle\Provider;

use Symbio\FulltextSearchBundle\Entity\Page;
use Symbio\FulltextSearchBundle\Event\EventsManager;
use Symbio\FulltextSearchBundle\Service\Crawler;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

abstract class Provider
{
    const CONFIG_CRAWLER_PARAMETERS_HANDLER = 'crawler_parameters';

    protected $parameters;

    protected $eventsManager;

    protected $page;

    public function __construct(EventsManager $eventsManager)
    {
        $this->eventsManager = $eventsManager;
    }

    /**
     * extract page info
     * @param array $config
     * @return array page info
     */
    public function extract(array $config = array()) {
        if (!isset($config[self::CONFIG_CRAWLER_PARAMETERS_HANDLER]) || !is_array($config[self::CONFIG_CRAWLER_PARAMETERS_HANDLER])) {
            throw new \Exception('Crawler parameters must be injected into provider');
        } else {
            $this->parameters = $config[self::CONFIG_CRAWLER_PARAMETERS_HANDLER];
        }

        $this->configure($config);

        if ($this->shouldBeIndexed()) {
            $this->page = new Page($this->parameters[Crawler::TITLE_TAGS_PARAM]);

            $this->extractTitle();
            $this->extractHeadlines();
            $this->extractDescription();
            $this->extractBody();
            $this->extractImage();
            $this->extractPageId();
            $this->extractRouteName();

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
     * configure provider
     * @param array $config
     */
    protected abstract function configure(array $config);

    /**
     * check no index flag
     * @return boolean
     */
    protected abstract function shouldBeIndexed();

    /**
     * extract document title
     */
    protected abstract function extractTitle();

    /**
     * extract document description
     */
    protected abstract function extractDescription();

    /**
     * extract document headers
     */
    protected abstract function extractHeadlines();

    /**
     * extract document body
     */
    protected abstract function extractBody();

    /**
     * extract document image
     */
    protected abstract function extractImage();

    /**
     * extract page ID
     */
    protected abstract function extractPageId();

    /**
     * extract route name
     */
    protected abstract function extractRouteName();
}
