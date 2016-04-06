<?php

namespace Symbio\FulltextSearchBundle\Event;

use Symbio\FulltextSearchBundle\Entity\Page;

class EventsManager
{
    protected $pageExtractedEvent;

    public function __construct(PageExtractedEvent $pageExtractedEvent = null)
    {
        if ($pageExtractedEvent) $this->pageExtractedEvent = $pageExtractedEvent;
    }

    protected function hasPageExtractedEvent()
    {
        return $this->pageExtractedEvent instanceof PageExtractedEvent;
    }

    public function firePageExtractedEvent(Page $page)
    {
        if ($this->hasPageExtractedEvent()) {
            $this->pageExtractedEvent->fire($page);
        }
    }
}
