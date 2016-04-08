<?php

namespace Symbio\FulltextSearchBundle\Provider;

use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symbio\FulltextSearchBundle\Service\Crawler;

class HtmlProvider extends Provider
{
    const CONFIG_CRAWLER_HANDLER = 'crawler';
    const CONFIG_IS_EXTERNAL_LINK_HANDLER = 'is_external_link';

    protected $crawler;
    protected $isExternal;

    /**
     * {@inheritdoc}
     */
    protected function configure(array $config)
    {
        if (!isset($config[self::CONFIG_CRAWLER_HANDLER]) || !($config[self::CONFIG_CRAWLER_HANDLER] instanceof DomCrawler)) {
            throw new \Exception('DomCrawler must be injected into HTML provider');
        } else {
            $this->crawler = $config[self::CONFIG_CRAWLER_HANDLER];
        }

        $this->isExternal = isset($config[self::CONFIG_IS_EXTERNAL_LINK_HANDLER]) && $config[self::CONFIG_IS_EXTERNAL_LINK_HANDLER];
    }


    /**
     * {@inheritdoc}
     */
    protected function shouldBeIndexed()
    {
        $selector = 'html/body[contains(@class, "'.$this->parameters[Crawler::NOINDEX_CLASS_PARAM].'")]';
        return $this->crawler->filterXPath($selector)->count() == 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function extractTitle() {
        try {
            // internal link
            if (!$this->isExternal && $this->parameters[Crawler::TITLE_CLASS_PARAM]) {
                $selector = 'html/body//*/*[contains(@class, "'.$this->parameters[Crawler::TITLE_CLASS_PARAM].'")]';
                if ($this->crawler->filterXPath($selector)->count()) {
                    $this->crawler->filterXPath($selector)->each(function(DomCrawler $node, $i) {
                        $this->getPage()->setTitle(trim($node->text()));
                    });
                }
            }
            // external link
            elseif ($this->isExternal) {
                foreach(array('html/head/meta[@property="og:title"]','html/head/meta[@name="title"]') as $selector) {
                    if (!$this->getPage()->hasTitle() && $this->crawler->filterXPath($selector)->count()) {
                        $title = trim($this->crawler->filterXPath($selector)->attr('content'));
                        if ($title) {
                            $this->getPage()->setTitle($title);
                        }
                    }
                }

                if (!$this->getPage()->hasTitle() && $this->crawler->filterXPath('html/head/title')->count()) {
                    $this->crawler->filterXPath('html/head/title')->each(function (DomCrawler $node, $i) {
                        $this->getPage()->setTitle(trim($node->text()));
                    });
                }
            }

            // find title in headlines if not exists
            if (!$this->getPage()->hasTitle() && $this->getPage()->hasHeadline()) {
                $this->setTitleFromHeadlines();
            }
        } catch(\Exception $e) {
            throw new \Exception('Extracting title in HTML provider - '.$e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractDescription() {
        try {
            if (trim($this->crawler->filterXPath('html/head/meta[@name="description"]')->count())) {
                $description = trim($this->crawler->filterXPath('html/head/meta[@name="description"]')->attr('content'));
                if ($description) {
                    $this->getPage()->setDescription($description);
                }
            }
        } catch(\Exception $e) {
            throw new \Exception('Extracting description in HTML provider - '.$e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractHeadlines() {
        try {
            foreach (($this->isExternal ? array('html/body') : $this->parameters[Crawler::BODY_SECTIONS_PARAM]) as $section) {
                foreach(($this->isExternal ? array('h1','h2','h3') : $this->parameters[Crawler::TITLE_TAGS_PARAM]) as $tag) {
                    if (!$this->isExternal) {
                        switch(strtoupper($tag)) {
                            // vyjmeme vsechny H1 z ARTICLE a zaradime mezi H2 (zpravidla vypisy clanku apod.)
                            case 'H1':
                                $selector = $section.'//'.$tag.'[ancestor-or-self::article and not(@class="'.$this->parameters[Crawler::TITLE_CLASS_PARAM].'")]';
                                if ($this->crawler->filterXPath($selector)->count() && count($this->parameters[Crawler::TITLE_TAGS_PARAM]) > 1) {
                                    $this->crawler->filterXPath($selector)->each(function (DomCrawler $node, $i) use ($tag) {
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

                    if ($this->crawler->filterXPath($selector)->count()) {
                        $this->crawler->filterXPath($selector)->each(function (DomCrawler $node, $i) use ($tag) {
                            $this->getPage()->setHeadline($tag, trim(html_entity_decode($node->text())));
                        });
                    }
                }
            }

            if (!$this->getPage()->hasTitle() && $this->getPage()->hasHeadline()) {
                $this->setTitleFromHeadlines();
            }
        } catch(\Exception $e) {
            throw new \Exception('Extracting headlines in HTML provider - '.$e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractBody() {
        try {
            foreach (($this->isExternal ? array('html/body') : $this->parameters[Crawler::BODY_SECTIONS_PARAM]) as $section) {
                if ($this->crawler->filterXPath($section)->count()) {
                    $this->getPage()->setBody(trim(html_entity_decode($this->crawler->filterXPath($section)->text())));
                }
            }
        } catch(\Exception $e) {
            throw new \Exception('Extracting body in HTML provider - '.$e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function extractImage()
    {
        try {
            if ($this->isExternal) {
                if ($this->crawler->filterXPath('html/head/meta[@property="og:image"]')->count()) {
                    $imageSrc = trim($this->crawler->filterXPath('html/head/meta[@property="og:image"]')->attr('content'));
                    if ($imageSrc) {
                        $this->getPage()->setImage($imageSrc);
                    }
                }
            }

            if (!$this->getPage()->hasImage()) {
                foreach (($this->isExternal ? array('html/body') : $this->parameters[Crawler::BODY_SECTIONS_PARAM]) as $section) {
                    if ($this->crawler->filterXPath($section . '//img')->count()) {
                        $this->crawler->filterXPath($section . '//img')->each(function (DomCrawler $node, $i) {
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
        } catch(\Exception $e) {
            throw new \Exception('Extracting image in HTML provider - '.$e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    protected function extractPageId()
    {
        try {
            if (!$this->isExternal && ($selector = $this->parameters[Crawler::PAGE_ID_PARAM]) && $this->crawler->filterXPath($selector)->count()) {
                $this->getPage()->setPageId(trim($this->crawler->filterXPath($selector)->text()));
            }
        } catch(\Exception $e) {
            throw new \Exception('Extracting field page_id in HTML provider - '.$e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    protected function extractRouteName()
    {
        try {
            if (!$this->isExternal && ($selector = $this->parameters[Crawler::ROUTE_NAME_PARAM]) && $this->crawler->filterXPath($selector)->count()) {
                $this->getPage()->setRouteName(trim($this->crawler->filterXPath($selector)->text()));
            }
        } catch(\Exception $e) {
            throw new \Exception('Extracting field route_name in HTML provider - '.$e->getMessage());
        }
    }

    /**
     * set title from headlines
     */
    protected function setTitleFromHeadlines() {
        foreach (($this->isExternal ? array('h1','h2','h3') : $this->parameters[Crawler::TITLE_TAGS_PARAM]) as $type) {
            if ($this->getPage()->hasHeadline($type)) {
                $this->getPage()->setTitle($this->getPage()->getRawHeadlinesByType($type)[0]);
                break;
            }
        }
    }
}
