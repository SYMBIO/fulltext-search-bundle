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
            if (!$this->isExternal) {
                // find title by classname
                if ($this->parameters[Crawler::TITLE_CLASS_PARAM]) {
                    $selector = 'html/body//*/*[contains(@class, "'.$this->parameters[Crawler::TITLE_CLASS_PARAM].'")]';
                    if ($this->crawler->filterXPath($selector)->count()) {
                        $this->crawler->filterXPath($selector)->each(function(DomCrawler $node, $i) {
                            if (!$this->getPage()->hasTitle()) {
                                $this->getPage()->setTitle(trim($this->getNodeText($node)));
                            }
                        });
                    }
                }

                // find first H1
                if (!$this->getPage()->hasTitle()) {
                    foreach ($this->parameters[Crawler::BODY_SECTIONS_PARAM] as $section) {
                        $selector = $section.'//h1';
                        if ($this->crawler->filterXPath($selector)->count()) {
                            $this->crawler->filterXPath($selector)->each(function(DomCrawler $node, $i) {
                                if (!$this->getPage()->hasTitle()) {
                                    $this->getPage()->setTitle(trim($this->getNodeText($node)));
                                }
                            });
                        }
                    }
                }

                // find og:title
                if (!$this->getPage()->hasTitle()) {
                    $selector = 'html/head/meta[@property="og:title"]';
                    if ($this->crawler->filterXPath($selector)->count()) {
                        $title = trim($this->crawler->filterXPath($selector)->attr('content'));
                        if ($title) {
                            $this->getPage()->setTitle($title);
                        }
                    }
                }
            }
            // external link
            else {
                // find in metatags
                foreach(array('html/head/meta[@property="og:title"]','html/head/meta[@name="title"]') as $selector) {
                    if (!$this->getPage()->hasTitle() && $this->crawler->filterXPath($selector)->count()) {
                        $title = trim($this->crawler->filterXPath($selector)->attr('content'));
                        if ($title) {
                            $this->getPage()->setTitle($title);
                        }
                    }
                }

                // find title tag
                if (!$this->getPage()->hasTitle() && $this->crawler->filterXPath('html/head/title')->count()) {
                    $this->crawler->filterXPath('html/head/title')->each(function (DomCrawler $node, $i) {
                        $this->getPage()->setTitle(trim($this->getNodeText($node)));
                    });
                }
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
                foreach(($this->isExternal ? array('h1') : $this->parameters[Crawler::TITLE_TAGS_PARAM]) as $tagIndex => $tag) {
                    if (!$this->isExternal) {
                        switch(strtoupper($tag)) {
                            case 'H1':
                                // vyjmeme vsechny H1 z ARTICLE a zaradime mezi H2 (zpravidla vypisy clanku apod.)
                                if (count($this->parameters[Crawler::TITLE_TAGS_PARAM]) > 1) {
                                    $selector = $section.'//'.$tag.'[ancestor-or-self::article and not(contains(@class, "'.$this->parameters[Crawler::TITLE_CLASS_PARAM].'"))]';
                                    if ($this->crawler->filterXPath($selector)->count()) {
                                        $this->crawler->filterXPath($selector)->each(function (DomCrawler $node, $i) use ($tagIndex) {
                                            $tag = $this->parameters[Crawler::TITLE_TAGS_PARAM][$tagIndex+1];
                                            $this->getPage()->setHeadline($tag, trim(html_entity_decode($this->getNodeText($node))));
                                        });
                                    }
                                }

                                $selector = $section.'//'.$tag.'[not(ancestor-or-self::article) and not(contains(@class, "'.$this->parameters[Crawler::TITLE_CLASS_PARAM].'"))]';
                                break;
                            default:
                                $selector = $section.'//'.$tag;
                        }
                    } else {
                        $selector = $section.'//'.$tag;
                    }

                    if ($this->crawler->filterXPath($selector)->count()) {
                        $this->crawler->filterXPath($selector)->each(function (DomCrawler $node, $i) use ($tag) {
                            $this->getPage()->setHeadline($tag, trim(html_entity_decode($this->getNodeText($node))));
                        });
                    }
                }
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
                $selector = $section . (!$this->isExternal ? '/node()[not(contains(@class, "'.$this->parameters[Crawler::NOINDEX_CLASS_PARAM].'"))]' : '');
                if ($this->crawler->filterXPath($selector)->count()) {
                    $this->crawler->filterXPath($selector)->each(function (DomCrawler $node, $i) use ($selector) {
                        $this->extractBodyNode($node);
                    });
                }
            }
        } catch(\Exception $e) {
            throw new \Exception('Extracting body in HTML provider - '.$e->getMessage());
        }
    }

    /**
     * Extract nodes with respect to excluded elements
     * @param Object $node
     */
    private function extractBodyNode($node) {
        if (!($node instanceof DomCrawler || $node instanceof \DOMElement)) return;

        $isDomCrawler = $node instanceof DomCrawler;

        $content = trim($isDomCrawler ? $this->getNodeText($node) : $node->textContent);

        if ($content) {
            if ($isDomCrawler && $node->getNode(0) instanceof \DOMComment) return;

            $classname = $isDomCrawler ? $node->attr('class') : $node->getAttribute('class');

            if (!$classname || strpos($classname, $this->parameters[Crawler::NOINDEX_CLASS_PARAM]) === false) {
                $html = $isDomCrawler ? $node->html() : $this->getNodeHtml($node);

                if (strpos($html, $this->parameters[Crawler::NOINDEX_CLASS_PARAM])) {
                    $children = $isDomCrawler ? $node->children() : ($node->hasChildNodes() ? $node->childNodes : array());
                    foreach($children as $childNode) {
                        $this->extractBodyNode($childNode);
                    }
                } else {
                    $this->getPage()->setBody($content);
                }
            }
        }
    }

    /**
     * Returns node HTML
     * @param \DOMElement $node
     * @return string The node html
     */
    private function getNodeHtml(\DOMElement $node) {
        $html = '';
        try {
            foreach ($node->childNodes as $child) {
                if (PHP_VERSION_ID >= 50306) {
                    // node parameter was added to the saveHTML() method in PHP 5.3.6
                    // @see http://php.net/manual/en/domdocument.savehtml.php
                    $html .= $child->ownerDocument->saveHTML($child);
                } else {
                    $document = new \DOMDocument('1.0', 'UTF-8');
                    $document->appendChild($document->importNode($child, true));
                    $html .= rtrim($document->saveHTML());
                }
            }
        } catch(\Exception $e) {
            // empty node list - do nothing
        }
        return $html;
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

    /**
     * @param DomCrawler $node
     * @return string
     */
    protected function getNodeText(DomCrawler $node)
    {
        $content = $node->html();

        $content = strtr($content, [
            '<br>' => ' ',
            '<br/>' => ' ',
            '&nbsp;' => ' ',
        ]);

        return strip_tags($content);
    }
}