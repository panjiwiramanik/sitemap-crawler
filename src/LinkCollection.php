<?php

namespace Ivebe\SitemapCrawler;

use Ivebe\SitemapCrawler\Contracts\ILinkCollection;

class LinkCollection implements ILinkCollection
{
    /**
     * Array of links fetched on all pages
     *
     * @var array
     */
    public $links;
    public $linksAlreadyCrawled;

    /**
     * Add only new links to the collection
     *
     * @param $url
     * @return bool
     */
    public function add($url)
    {
        $this->links[] = $url;

        return true;
    }

    /**
     * Add only new links to the collection
     *
     * @param $url
     * @return bool
     */
    public function addAlready($url)
    {
        $this->linksAlreadyCrawled[] = $url;

        return true;
    }

    /**
     * Check if link is already in the collection
     *
     * @param $url
     * @return bool
     */
    public function exists($url)
    {
        if (array_search($link, $this->links)) {
            return true;
        }
        
        return false;
    }

    /**
     * Do not crawl pages that were already crawled
     *
     * @param $key
     * @return mixed
     * @throws \Exception
     */
    public function isCrawled($link)
    {
        if (array_search($link, $this->linksAlreadyCrawled)) {
            return true;
        }
        
        return false;
    }
}
