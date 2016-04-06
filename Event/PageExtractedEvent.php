<?php

namespace Symbio\FulltextSearchBundle\Event;

use Symbio\FulltextSearchBundle\Entity\Page;

abstract class PageExtractedEvent
{
    /**
     * Check and modify extracted data
     * @param Page $page
     */
    public abstract function fire(Page $page);
}
