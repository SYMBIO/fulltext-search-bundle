<?php

namespace Symbio\FulltextSearchBundle\Provider;

use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symbio\FulltextSearchBundle\Service\Crawler;

class PdfProvider extends Provider
{
    /**
     * @inheritDoc
     */
    protected function extractTitle(DomCrawler &$crawler, $url)
    {
        // TODO: Implement extractTitle() method.
    }

    /**
     * @inheritDoc
     */
    protected function extractDescription(DomCrawler &$crawler, $url)
    {
        // TODO: Implement extractDescription() method.
    }

    /**
     * @inheritDoc
     */
    protected function extractHeadlines(DomCrawler &$crawler, $url)
    {
        // TODO: Implement extractHeadlines() method.
    }

    /**
     * @inheritDoc
     */
    protected function extractBody(DomCrawler &$crawler, $url)
    {
        // TODO: Implement extractBody() method.
    }

    /**
     * @inheritDoc
     */
    protected function extractImage(DomCrawler &$crawler, $url)
    {
        // TODO: Implement extractImage() method.
    }

    /**
     * @inheritDoc
     */
    protected function extractPageId(DomCrawler &$crawler, $url)
    {
        // TODO: Implement extractPageId() method.
    }

    /**
     * @inheritDoc
     */
    protected function extractRouteName(DomCrawler &$crawler, $url)
    {
        // TODO: Implement extractRouteName() method.
    }
}
