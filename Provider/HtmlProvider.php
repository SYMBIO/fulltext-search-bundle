<?php

namespace Symbio\FulltextSearchBundle\Provider;

use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symbio\FulltextSearchBundle\Service\Crawler;

class HtmlProvider extends Provider
{
    /**
     * {@inheritdoc}
     */
    protected function shouldBeIndexed(DomCrawler &$crawler, $url)
    {
        $selector = 'html/body[contains(@class, "'.$this->parameters[Crawler::NOINDEX_CLASS_PARAM].'")]';
        return $crawler->filterXPath($selector)->count() == 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function extractTitle(DomCrawler &$crawler, $url) {
        // internal link
        if (!$this->isExternal && $this->parameters[Crawler::TITLE_CLASS_PARAM]) {
            $selector = 'html/body//*/*[contains(@class, "'.$this->parameters[Crawler::TITLE_CLASS_PARAM].'")]';
            if ($crawler->filterXPath($selector)->count()) {
                $crawler->filterXPath($selector)->each(function(DomCrawler $node, $i) {
                    $this->getPage()->setTitle(trim($node->text()));
                });
            }
        }
        // external link
        elseif ($this->isExternal) {
            foreach(array('html/head/meta[@property="og:title"]','html/head/meta[@name="title"]') as $selector) {
                if (!$this->getPage()->hasTitle() && $crawler->filterXPath($selector)->count()) {
                    $title = trim($crawler->filterXPath($selector)->attr('content'));
                    if ($title) {
                        $this->getPage()->setTitle($title);
                    }
                }
            }

            if (!$this->getPage()->hasTitle() && $crawler->filterXPath('html/head/title')->count()) {
                $crawler->filterXPath('html/head/title')->each(function (DomCrawler $node, $i) {
                    $this->getPage()->setTitle(trim($node->text()));
                });
            }
        }

        // find title in headlines if not exists
        if (!$this->getPage()->hasTitle() && $this->getPage()->hasHeadline()) {
            $this->setTitleFromHeadlines();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractDescription(DomCrawler &$crawler, $url) {
        if (trim($crawler->filterXPath('html/head/meta[@name="description"]')->count())) {
            $description = trim($crawler->filterXPath('html/head/meta[@name="description"]')->attr('content'));
            if ($description) {
                $this->getPage()->setDescription($description);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractHeadlines(DomCrawler &$crawler, $url) {
        foreach (($this->isExternal ? array('html/body') : $this->parameters[Crawler::BODY_SECTIONS_PARAM]) as $section) {
            foreach(($this->isExternal ? array('h1','h2','h3') : $this->parameters[Crawler::TITLE_TAGS_PARAM]) as $tag) {
                if (!$this->isExternal) {
                    switch(strtoupper($tag)) {
                        // vyjmeme vsechny H1 z ARTICLE a zaradime mezi H2 (zpravidla vypisy clanku apod.)
                        case 'H1':
                            $selector = $section.'//'.$tag.'[ancestor-or-self::article and not(@class="'.$this->parameters[Crawler::TITLE_CLASS_PARAM].'")]';
                            if ($crawler->filterXPath($selector)->count() && count($this->parameters[Crawler::TITLE_TAGS_PARAM]) > 1) {
                                $crawler->filterXPath($selector)->each(function (DomCrawler $node, $i) use ($tag) {
                                    $this->getPage()->setHeadline($tag, trim(html_entity_decode($node->text())));
                                });
                            }
                            $selector = $section.'//'.$tag.'[not(ancestor-or-self::article)]';
                            break;
                        default:
                            $selector = $section.'//'.$tag;
                    }
                } else {
                    $selector = $section.'//'.$tag;
                }

                if ($crawler->filterXPath($selector)->count()) {
                    $crawler->filterXPath($selector)->each(function (DomCrawler $node, $i) use ($tag) {
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
        foreach (($this->isExternal ? array('html/body') : $this->parameters[Crawler::BODY_SECTIONS_PARAM]) as $section) {
            if ($crawler->filterXPath($section)->count()) {
                $this->getPage()->setBody(trim(html_entity_decode($crawler->filterXPath($section)->text())));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractImage(DomCrawler &$crawler, $url) {
        if ($this->isExternal) {
            if ($crawler->filterXPath('html/head/meta[@property="og:image"]')->count()) {
                $imageSrc = trim($crawler->filterXPath('html/head/meta[@property="og:image"]')->attr('content'));
                if ($imageSrc) {
                    $this->getPage()->setImage($imageSrc);
                }
            }
        }

        if (!$this->getPage()->hasImage()) {
            foreach (($this->isExternal ? array('html/body') : $this->parameters[Crawler::BODY_SECTIONS_PARAM]) as $section) {
                if ($crawler->filterXPath($section.'//img')->count()) {
                    $crawler->filterXPath($section.'//img')->each(function(DomCrawler $node, $i) {
                        if (!$this->getPage()->hasImage()) {
                            $imgSources = $node->extract(array('src'));
                            if ($imgSources && is_array($imgSources) && count($imgSources)) {
                                $this->getPage()->setImage($imgSources[0]);
                            }
                        }
                    });
                }
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
        if (!$this->isExternal && ($selector = $this->parameters[Crawler::PAGE_ID_PARAM]) && $crawler->filterXPath($selector)->count()) {
            $this->getPage()->setPageId(trim($crawler->filterXPath($selector)->text()));
        }
    }

    /**
     * @inheritDoc
     */
    protected function extractRouteName(DomCrawler &$crawler, $url)
    {
        if (!$this->isExternal && ($selector = $this->parameters[Crawler::ROUTE_NAME_PARAM]) && $crawler->filterXPath($selector)->count()) {
            $this->getPage()->setRouteName(trim($crawler->filterXPath($selector)->text()));
        }
    }

    /**
     * set title from headlines
     */
    private function setTitleFromHeadlines() {
        foreach (($this->isExternal ? array('h1','h2','h3') : $this->parameters[Crawler::TITLE_TAGS_PARAM]) as $type) {
            if ($this->getPage()->hasHeadline($type)) {
                $this->getPage()->setTitle($this->getPage()->getRawHeadlinesByType($type)[0]);
                break;
            }
        }
    }
}
