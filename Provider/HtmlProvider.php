<?php

namespace Symbio\FulltextSearchBundle\Provider;

use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symbio\FulltextSearchBundle\Service\Crawler;

class HtmlProvider extends Provider
{
    /**
     * {@inheritdoc}
     */
    protected function extractTitle(DomCrawler &$crawler, $url) {
        if ($this->parameters[Crawler::TITLE_CLASS_PARAM]) {
            $selector = 'html/body//*/*[contains(@class, "'.$this->parameters[Crawler::TITLE_CLASS_PARAM].'")]';
            if ($crawler->filterXPath($selector)->count()) {
                $crawler->filterXPath($selector)->each(function (DomCrawler $node, $i) use ($url) {
                    $this->getPage()->setTitle(trim($node->text()));
                });
            }
        }

        if (!$this->getPage()->hasTitle() && $this->getPage()->hasHeadline()) {
            $this->setTitleFromHeadlines();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractDescription(DomCrawler &$crawler, $url) {
        $description = trim($crawler->filterXPath('html/head/meta[@name="description"]')->attr('content'));
        if ($description) {
            $this->getPage()->setDescription($description);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractHeadlines(DomCrawler &$crawler, $url) {
        foreach ($this->parameters[Crawler::BODY_SECTIONS_PARAM] as $section) {
            foreach($this->parameters[Crawler::TITLE_TAGS_PARAM] as $tag) {
                switch(strtoupper($tag)) {
                    // vyjmeme vsechny H1 z ARTICLE a zaradime mezi H2 (zpravidla vypisy clanku apod.)
                    case 'H1':
                        $selector = $section.'//'.$tag.'[ancestor-or-self::article and not(@class="'.$this->parameters[Crawler::TITLE_CLASS_PARAM].'")]';
                        if ($crawler->filterXPath($selector)->count() && count($this->parameters[Crawler::TITLE_TAGS_PARAM]) > 1) {
                            $crawler->filterXPath($selector)->each(function (DomCrawler $node, $i) use ($url,$tag) {
                                $key = $this->parameters[Crawler::TITLE_TAGS_PARAM][1];
                                if (!isset($page[$key]) || !is_array($page[$key])) $page[$key] = array();
                                $this->getPage()->setHeadline($tag, trim($node->text()));
                            });
                        }
                        $selector = $section.'//'.$tag.'[not(ancestor-or-self::article)]';
                        break;
                    default:
                        $selector = $section.'//'.$tag;
                }

                if ($crawler->filterXPath($selector)->count()) {
                    $crawler->filterXPath($selector)->each(function (DomCrawler $node, $i) use ($url,$tag) {
                        $this->getPage()->setHeadline($tag, trim(html_entity_decode($node->text())));
                    });
                }
            }
        }

        if (!$this->getPage()->hasTitle() && $this->getPage()->hasHeadline()) {
            $this->setTitleFromHeadlines();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractBody(DomCrawler &$crawler, $url) {
        foreach ($this->parameters[Crawler::BODY_SECTIONS_PARAM] as $section) {
            $this->getPage()->setBody(trim(html_entity_decode($crawler->filterXPath($section)->text())));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractImage(DomCrawler &$crawler, $url) {
        foreach ($this->parameters[Crawler::BODY_SECTIONS_PARAM] as $section) {
            $imgNodes = $crawler->filterXPath($section.'//img');
            if ($imgNodes->count()) {
                $imgNodes->each(function(DomCrawler $node, $i) use($url) {
                    if (!isset($page['img'])) {
                        $imgSources = $node->extract(array('src'));
                        if ($imgSources && is_array($imgSources) && count($imgSources)) {
                            $this->getPage()->setImage($imgSources[0]);
                        }
                    }
                });
            }
        }

        // if image is not found get the default one
        if (!$this->getPage()->hasImage() && $this->parameters[Crawler::DEFAULT_IMAGE_PARAM]) {
            $this->getPage()->setImage($this->parameters[Crawler::DEFAULT_IMAGE_PARAM]);
        }
    }

    /**
     * @inheritDoc
     */
    protected function extractPageId(DomCrawler &$crawler, $url)
    {
        if (($selector = $this->parameters[Crawler::PAGE_ID_PARAM]) && $crawler->filterXPath($selector)->count()) {
            $this->getPage()->setPageId(trim($crawler->filterXPath($selector)->text()));
        }
    }

    /**
     * @inheritDoc
     */
    protected function extractRouteName(DomCrawler &$crawler, $url)
    {
        if (($selector = $this->parameters[Crawler::ROUTE_NAME_PARAM]) && $crawler->filterXPath($selector)->count()) {
            $this->getPage()->setRouteName(trim($crawler->filterXPath($selector)->text()));
        }
    }

    /**
     * set title from headlines
     */
    private function setTitleFromHeadlines() {
        foreach ($this->parameters[Crawler::TITLE_TAGS_PARAM] as $type) {
            if ($this->getPage()->hasHeadline($type)) {
                $this->getPage()->setTitle($this->getPage()->getRawHeadlinesByType($type)[0]);
                break;
            }
        }
    }
}
