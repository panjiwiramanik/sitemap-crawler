<?php

namespace Ivebe\SitemapCrawler;

use Ivebe\SitemapCrawler\Contracts\ICrawler;

class Crawler implements ICrawler
{
    /**
     * Configuration array
     *
     * @var array
     */
    private $config;

    /**
     * @var array
     */
    private $links = [];


    /**
     * Crawler constructor.
     *
     * @param array $config
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        if(!isset($config['treat_trailing_slash_as_duplicate']) ||
           !isset($config['force_trailing_slash']) ||
           !isset($config['depth']) ||
           !isset($config['page_limit'])
        )
            throw new \Exception('Invalid config file. Please double check it.');

        $this->config = $config;
    }

    /**
     * Returns depth to which to follow links from base link.
     *
     * @return integer
     */
    public function getDepth()
    {
        return $this->config['depth'];
    }

    public function getPageLimit()
    {
        return $this->config['page_limit'];
    }

    /**
     * Fetch page over curl
     *
     * @param $url
     * @return mixed
     */
    private function fetch($url)
    {
        try {
            $options = [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:15.0) Gecko/20100101 Firefox/15.0.1',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_AUTOREFERER    => true,
                CURLOPT_CONNECTTIMEOUT => 60,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_MAXREDIRS      => 10
            ];

            $ch = curl_init( $url );
            curl_setopt_array( $ch, $options );
            $content = curl_exec( $ch );
            curl_close( $ch );

            return $content;
        } catch(\Exception $e) {
            return '';
        }
    }


    /**
     * Parse links from HTML code
     *
     * @param $url string link to parse for other links
     */
    private function parse($url, $urlParseSource = null)
    {
        try {
            $dom = new \DOMDocument();
            @$dom->loadHTML($this->fetch($url));

            $xPath = new \DOMXPath($dom);
            $elements = $xPath->query("//a/@href");


            foreach ($elements as $e) {
                if ($urlParseSource) {
                    if ($urlParseSource == parse_url($e->nodeValue, PHP_URL_HOST)) {
                        $this->links[] = $e->nodeValue;
                    }
                } else {
                    $this->links[] = $e->nodeValue;
                }
            }
        } catch(\Exception $e) {
        }
    }

    /**
     * Filter duplicate links
     *
     * @param $baseURL root url from which other urls were collected
     */
    private function filter($baseURL)
    {
        try {
            //trim whitespaces
            $urls = array_map('trim', $this->links);

            // if($this->config['treat_trailing_slash_as_duplicate'])
            // {
            //     $force_trailing_slash = $this->config['force_trailing_slash'];
            //     $urls = array_map(function ($el) use ($force_trailing_slash){

            //         //removing then adding, in case it was already there, we wont duplicate it
            //         $r = rtrim($el, '/');

            //         if($force_trailing_slash)
            //             $r .= '/';

            //         return $r;
            //     }, $urls);
            // }

            //first remove all starting with # hash
            $urls = array_filter($urls, function($el) {
                return strlen($el) > 0 && $el[0] != '#';
            });

            $myHost   = parse_url($baseURL, PHP_URL_HOST);
            $myScheme = parse_url($baseURL, PHP_URL_SCHEME);

            $return = [];

            foreach($urls as $k => $el) {

                //full link, no need to add anything. Just check if link is from the same domain
                if(substr($el, 0, 4) == 'http') {

                    if($myHost == parse_url($el, PHP_URL_HOST))
                        $return[] = $el;

                    continue;
                }

                // force current selected scheme in the sitemap file
                if(substr($el, 0, 2) == '//') {

                    $return[] = $myScheme . ':' . $el;
                    continue;
                }

                //absolute path links
                if($el[0] == '/') {

                    $return[] = $myScheme . '://' . $myHost . $el;
                    continue;
                }


                $return[] = $myScheme . '://' . $myHost . '/' . $el;
            }

            $this->links = $return;
        } catch (\Exception $e) {
        }
    }

    /**
     * Crawl given url and return links fetched.
     *
     * @param $url
     * @return array
     */
    public function process($url, $urlParseSource = null)
    {
        $this->links = [];

        $this->parse($url, $urlParseSource);
        // $this->filter($url);

        return $this->links;
    }
}
