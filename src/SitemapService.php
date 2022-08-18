<?php

namespace Ivebe\SitemapCrawler;

use Ivebe\SitemapCrawler\Contracts\ICrawler;
use Ivebe\SitemapCrawler\Contracts\ILinkCollection;

class SitemapService
{
    /**
     * @var ICrawler
     */
    private $crawler;

    /**
     * @var ILinkCollection
     */
    private $collection;

    /**
     * @var integer depth to which we will crawl
     */
    private $depth;

    public function __construct(ICrawler $crawler, ILinkCollection $collection)
    {
        $this->crawler    = $crawler;
        $this->collection = $collection;

        $this->depth = $this->crawler->getDepth();
    }

    /**
     * Add all links to collection, if they are not added already
     *
     * @param $links
     */
    private function bulkAdd($links, $urlCrawlSource = null)
    {
        $myHost   = parse_url($urlCrawlSource, PHP_URL_HOST);
        $myScheme = parse_url($urlCrawlSource, PHP_URL_SCHEME);

        foreach ($links as $l => $link) {
            $urlCrawlTarget = parse_url($link, PHP_URL_HOST);
            if ($myHost == $urlCrawlTarget) {
                if(str_contains($link, 'mailto:')) { 
                    continue;
                }

                if(substr($link, 0, 4) == 'http') {
                    if($myHost == parse_url($link, PHP_URL_HOST))
                        $this->collection->add($link);
                    
                    continue;
                }

                if(substr($link, 0, 2) == '//') {
                    $this->collection->add($return[] = $myScheme . ':' . $link);
                    continue;
                }

                //absolute path links
                if($link[0] == '/') {
                    $this->collection->add($return[] = $myScheme . '://' . $myHost . $link);
                    continue;
                }

                $this->collection->add($myScheme . '://' . $myHost . '/' . $link);
            }
        }

        $this->collection->links = array_map('trim', $this->collection->links);

        $this->collection->links = array_filter($this->collection->links, function($el) {
            return strlen($el) > 0 && $el[0] != '#';
        });

        uasort($this->collection->links, 'sortByLength');
        $this->collection->links = array_values(array_unique($this->collection->links));
    }

    private function sortByLength($a, $b) {
        return strlen($a) - strlen($b);
    }

    /**
     * Main entry point, from which we will fetch all links
     * in the provided depth
     *
     * @param $url
     * @return array links
     */
    public function crawl($url)
    {
        $urlCrawlSource = parse_url($url, PHP_URL_HOST);
        $links = $this->crawler->process($url);

        $this->collection->add($url);
        $this->collection->addAlready($url);
        
        $this->bulkAdd($links, $url);

        $depth = $this->depth;
        
        if ($this->collection && $this->collection->links) {
            if (count($this->collection->links) >= $this->crawler->getPageLimit()) {
                return array_slice($this->collection->links, 0, $this->crawler->getPageLimit());
            }

            if($depth > 0)
            {
                while($depth > 0)
                {
                    foreach($this->collection->links as $link){
                        $urlCrawlTarget = parse_url($link, PHP_URL_HOST);
                        if ($urlCrawlTarget === $urlCrawlSource && !$this->collection->isCrawled($link)) {    
                            $links = $this->crawler->process($link, $urlCrawlSource);

                            $this->bulkAdd($links, $url);
                            $this->collection->addAlready($link);

                            if ($this->collection && $this->collection->links && count($this->collection->links) >= $this->crawler->getPageLimit()) {
                                return array_slice($this->collection->links, 0, $this->crawler->getPageLimit());
                            }
                        }
                    }

                    $depth--;
                }
            }

            return array_slice($this->collection->links, 0, $this->crawler->getPageLimit());
        } else {
            return [];
        }
    }

    /**
     * Exporting sitemap.xml as file to download, or save on server.
     *
     * @param $changefreq string that will go to <changefreq> in the xml
     * @param bool $saveToFile
     */
    public function export($changefreq, $saveToFile = false)
    {
        $output  = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
        $output .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $output .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 ';
        $output .= 'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';

        $output .= PHP_EOL;

        foreach($this->collection->links as $link){
            $output .= '<url>' . PHP_EOL;
            $output .= '<loc>' . $link['url'] . '</loc>' . PHP_EOL;
            $output .= '<changefreq>' . $changefreq . '</changefreq>' . PHP_EOL;
            $output .= '</url>' . PHP_EOL;
        }

        $output .= '</urlset>';

        if(!$saveToFile)
        {

            header('Content-type: text/xml');
            header('Content-Disposition: attachment; filename="sitemap.xml"');

            echo $output;
            return;
        }

        file_put_contents($saveToFile, $output);
    }
}
