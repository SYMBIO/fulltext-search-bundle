<?php

namespace Symbio\FulltextSearchBundle\Entity;

class Page {
    const TITLE_KEY = 'title';
    const DESCRIPTION_KEY = 'description';
    const HEADLINES_KEY = 'headlines';
    const BODY_KEY = 'body';
    const IMAGE_KEY = 'image';
    const URIS_KEY = 'uris';

    const PAGE_ID_KEY = 'page_id';
    const ROUTE_NAME_KEY = 'route_name';

    const URL_KEY = 'url';

    protected $title = array();
    protected $description = array();
    protected $headlines = array();
    protected $body = array();
    protected $image;

    protected $pageId = '';
    protected $routeName = '';

    private $titleTypes;

    public function __construct($titleTypes = array()) {
        $this->titleTypes = $titleTypes;
    }

    /**
     * set title part
     * @param string $value
     */
    public function setTitle($value) {
        $this->title[] = $value;
    }
    /**
     * has page any title?
     * @return boolean
     */
    public function hasTitle() {
        return count($this->title) > 0;
    }
    /**
     * get title
     * @return string
     */
    public function getTitle() {
        return implode(' - ', $this->title);
    }

    /**
     * set description
     * @param string $value
     */
    public function setDescription($value) {
        $this->description[] = $value;
    }
    /**
     * has page any description?
     * @return boolean
     */
    public function hasDescription() {
        return count($this->description) > 0;
    }
    /**
     * get description
     * @return string
     */
    public function getDescription() {
        return implode(' ', $this->description);
    }

    /**
     * set headline
     * @param string $type headline type
     * @param string $value
     */
    public function setHeadline($type, $value) {
        if (!isset($this->headlines[$type])) $this->headlines[$type] = array();
        $this->headlines[$type][] = $value;
    }
    /**
     * has page any headline?
     * @param string headline type
     * @return boolean
     */
    public function hasHeadline($type = null) {
        if (!empty($type)) {
            return isset($this->headlines[$type]) && count($this->headlines[$type]) > 0;
        } elseif ($type === null) {
            foreach($this->titleTypes as $type) {
                if (isset($this->headlines[$type]) && count($this->headlines[$type]) > 0) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * get headlines
     * @return array
     */
    public function getHeadlines() {
        $headlines = array();
        foreach($this->titleTypes as $type) {
            $headlines[$type] = $this->hasHeadline($type) ? implode(' ', $this->headlines[$type]) : '';
        }
        return $headlines;
    }
    /**
     * get headlines by type
     * @param string headline type
     * @return string
     */
    public function getHeadlinesByType($type) {
        if ($this->hasHeadline($type)) {
            return implode(' ', $this->headlines[$type]);
        }
        return '';
    }
    /**
     * get headlines by type
     * @param string headline type
     * @return array
     */
    public function getRawHeadlinesByType($type) {
        if ($this->hasHeadline($type)) {
            return $this->headlines[$type];
        }
        return array();
    }

    /**
     * set body
     * @param string $value
     */
    public function setBody($value) {
        $this->body[] = $value;
    }
    /**
     * has page any body?
     * @return boolean
     */
    public function hasBody() {
        return count($this->body) > 0;
    }
    /**
     * get body
     * @return string
     */
    public function getBody() {
        return implode(' ', $this->body);
    }

    /**
     * set image
     * @param string $value
     */
    public function setImage($value) {
        $this->image = $value;
    }
    /**
     * has page any image?
     * @return boolean
     */
    public function hasImage() {
        return !empty($this->image);
    }
    /**
     * get image
     * @return string
     */
    public function getImage() {
        return $this->image;
    }

    /**
     * set page ID
     * @param string $value
     */
    public function setPageId($value) {
        $this->pageId = $value;
    }
    /**
     * get page ID
     * @return string
     */
    public function getPageId() {
        return $this->pageId;
    }

    /**
     * set route name
     * @param string $value
     */
    public function setRouteName($value) {
        $this->routeName = $value;
    }
    /**
     * get route name
     * @return string
     */
    public function getRouteName() {
        return $this->routeName;
    }

    public function toArray() {
        return array(
            self::TITLE_KEY => $this->getTitle(),
            self::DESCRIPTION_KEY => $this->getDescription(),
            self::HEADLINES_KEY => $this->getHeadlines(),
            self::BODY_KEY => $this->getBody(),
            self::IMAGE_KEY => $this->getImage(),
            self::PAGE_ID_KEY => $this->getPageId(),
            self::ROUTE_NAME_KEY => $this->getRouteName(),
        );
    }

    // STATIC

    /**
     * has page any headline by type?
     * @param array page
     * @param string headline type
     * @return boolean
     */
    public static function hasHeadlineType($page, $type) {
        return isset($page[self::HEADLINES_KEY][$type]);
    }
    /**
     * get headline
     * @param array page
     * @param string headline type
     * @return string
     */
    public static function getHeadline($page, $type) {
        if (self::hasHeadlineType($page, $type)) {
            return $page[self::HEADLINES_KEY][$type];
        }
        return '';
    }
}