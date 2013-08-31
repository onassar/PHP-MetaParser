<?php

    /**
     * MetaParser
     *
     * Parses content for meta and open graph details. Useful when used with a
     * curling library.
     * 
     * @see     <https://github.com/onassar/PHP-Curler>
     * @author  Oliver Nassar <onassar@gmail.com>
     * @todo    add support for paths leading with '//' (aka. use same scheme)
     * @notes   The following urls provide good examples of the parsing engine:
     *          http://www.bbc.com/
     *          http://www.nytimes.com/
     *          http://techcrunch.com/
     *          http://metallo.scripps.edu/
     *          http://jobs.businessinsider.com/job/3b9d1c8e1e5e31d4e4fefb4a551b8b90/?d=1&source=site_home
     *          http://www.wikipedia.org
     *          http://yahoo.com
     *          http://twitter.com/wikileaks/status/8920530488926208#s
     *          http://veryawesomeworld.com/awesomebook/inside.html
     * @example
     * <code>
     *     // booting
     *     require_once APP . '/vendors/PHP-Curler/Curler.class.php';
     *     require_once APP . '/vendors/PHP-MetaParser/MetaParser.class.php';
     *
     *     // curling
     *     $curler = (new Curler());
     *     $url = 'http://www.bbc.com/';
     *     $body = $curler->get($url);
     *     $parser = (new MetaParser($body, $url));
     *     print_r($parser->getDetails());
     * <code>
     */
    class MetaParser
    {
        /**
         * _parsed
         * 
         * @var    array
         * @access protected
         */
        protected $_parsed;

        /**
         * _body.
         * 
         * @var    string
         * @access protected
         */
        protected $_body;

        /**
         * _url
         * 
         * @var    string
         * @access protected
         */
        protected $_url;

        /**
         * __construct
         * 
         * Requires the content body and url to be provided. The url is useful
         * to generate relative paths for images, etc.
         * 
         * @access public
         * @param  String $body
         * @param  String $url
         * @return void
         */
        public function __construct($body, $url)
        {
            $this->_body = $body;
            $this->_url = $url;
        }

        /**
         * resolveFullPath
         * 
         * @see    http://ca3.php.net/manual/en/function.realpath.php#86384
         * @access protected
         * @param  string $addr
         * @param  string $base
         * @return string
         */
        protected function _resolveFullPath($addr, $base)
        {
            // empty address provided
            if (empty($addr)) {
                return $base;
            }

            // parse address; if scheme found, doesn't need to be resolved
            $parsed = parse_url($addr);
            if(array_key_exists('scheme', $parsed)) {
                return $addr;
            }

            // parse base passed in (will always be a full url)
            $parsed = parse_url($base);

            // protocol specific
            if (mb_substr($addr, 0, 2) === '//') {
                return ($parsed['scheme']) . '://' . mb_substr($addr, 2);
            }
            // otherwise if the address should go to the top of the tree
            elseif ($addr{0} === '/') {
                return ($parsed['scheme']) . '://' . ($parsed['host']) .
                    ($addr);
            }

            // if the address doesn't contain any sub-directory calls
            if (!strstr($addr, '../')) {
                return ($base) . ($addr);
            }

            // set-up sub-directory pieces for traversing/replacing
            $pieces['addr'] = explode('../', $addr);
            $pieces['base'] = explode('/', $parsed['path']);
            array_pop($pieces['base']);
            $count = count($pieces['addr']) - 1;

            // array of respective sub-directory replacements (from base)
            $replacements = array_slice($pieces['base'], 0, 0 - $count);
            $replacements = array_filter($replacements);

            // add last non-empty sub-directory as tail
            $tail = array_pop($pieces['addr']);
            if (!empty($tail)) {
                $replacements[] = $tail;
            }

            // return sub-directory traversed address
            return ($parsed['scheme']) . '://' . ($parsed['host']) .
                '/' . implode('/', $replacements);
        }

        /**
         * _parseBase
         * 
         * @todo   find url's that have various base values, and test them
         * @access private
         * @return string
         */
        private function _parseBase()
        {
            // search for base tag; return empty string if none found
            preg_match_all(
                '/<base.*href=(\'|")(.*)\1.*>/imU',
                $this->_body,
                $bases
            );

            // store url components (will need to be used before value returned)
            $components = parse_url($this->_url);
            $path = ($components['scheme']) . '://' . ($components['host']);
            if (isset($components['path'])) {
                $path .= $components['path'];
            } else {
                $path .= '/';
            }

            // remove any filename that was specified by the path explicitely
            $path = preg_replace('/[^\/]*$/U', '', $path);

            // no base tags found
            if (empty($bases[2])) {
                return $path;
            }

            // set base
            $base = trim(array_pop($bases[2]));

            // set variable to check for target attribute value
            $found = array_pop($bases[0]);

            // if a target attribute found
            if (preg_match('/target=/', $found)) {

                // check if target being specified is the document itself (which
                // is okay)
                $self = preg_match('/target=[\'"]{1}_self[\'"]{1}/', $found);

                // if it's not itself, set the base based on the url being
                // 'grabbed'
                if (!$self) {
                    return $path;
                }
            }

            // resolve path (check for trailing slash; always required)
            $resolved = $this->_resolveFullPath($base, $path);
            if (!preg_match('/\/$/', $resolved)) {
                return ($resolved) . '/';
            }
            return $resolved;
        }

        /**
         * _parseCharset
         * 
         * @access private
         * @return String
         */
        private function _parseCharset()
        {
            // get the page's charset (defined as a meta tag)
            preg_match(
                '#<meta(?!\s*(?:name|value)\s*=)[^>]*?charset\s*=[\s"\']*([^\s"\'/>]*)#i',
                $this->_body,
                $charset
            );
            if (empty($charset)) {
                return false;
            }

            // return charset found
            $charset = array_pop($charset);
            $charset = strtolower($charset);
            $charset = trim($charset);
            if ($charset === 'utf8') {
                return 'utf-8';
            }
            return $charset;
        }

        /**
         * _parseDescription
         * 
         * @notes  not checking the index of the regular expression that
         *         corresponds to the actual keywords in order to ensure that an
         *         actual meta tag for keywords was specified. This way I can
         *         return false if the meta tag isn't there at all
         *         due to a bug, the second regex does *not* support newlines in
         *         the meta tag content attribute values
         * @access private
         * @return string
         */
        private function _parseDescription()
        {
            // grab meta tag; return immediately if false
            $description = $this->_parseMetaTag('description');
            if ($description === false) {
                return false;
            }

            // trim/return
            return trim($description);
        }

        /**
         * _parseFavicon
         * 
         * @access private
         * @return string
         */
        private function _parseFavicon()
        {
            // generate default
            $parsed = parse_url($this->_url);
            $default = ($parsed['scheme']) . '://' . ($parsed['host']) .
                '/favicon.ico';

            // get the page links (icon attribute value leading)
            preg_match_all(
                '/<link.+rel=(\'|").*[^-]\bicon\b.{0,20}href=(\'|")(.+)\2/imU',
                $this->_body,
                $favicons
            );
            if (empty($favicons[3])) {

                // get the page links (icon attribute value trailing)
                preg_match_all(
                    '/<link.+href=(\'|")(.+)\1.{0,20}rel=(\'|").*[^-]\bicon\b/imU',
                    $this->_body,
                    $favicons
                );

                // no favicon found
                if (empty($favicons[2])) {
                    return $default;
                }
                $favicon = array_pop($favicons[2]);
            } else {
                $favicon = array_pop($favicons[3]);
            }

            // resolve full path
            $favicon = trim($favicon);
            $favicon = $this->_resolveFullPath($favicon, $this->getBase());
            $favicon = str_replace(PHP_EOL, '', $favicon);
            return $favicon;
        }

        /**
         * _parseImages
         * 
         * @notes  first expression capture relates to [^*]* rather than .* as
         *         this was excluding new lines
         * @access private
         * @return array
         */
        private function _parseImages()
        {
            // get the page images
            preg_match_all(
                '/<img[^*]*src=(\'|")(.+)\1/imU',
                $this->_body,
                $images
            );
            if (empty($images[2])) {
                return array();
            }

            // base the images
            $images = array_pop($images);
            $base = $this->getBase();
            foreach ($images as &$image) {
                $image = $this->_resolveFullPath($image, $base);
                $image = str_replace(PHP_EOL, '', $image);
            }
            $images = array_unique($images);
            $images = array_values($images);

            // return images found
            return $images;
        }

        /**
         * _parseKeywords
         * 
         * @notes  not checking the index of the regular expression that
         *         corresponds to the actual keywords in order to ensure that an
         *         actual meta tag for keywords was specified. This way I can
         *         return false if the meta tag isn't there at all
         * @access private
         * @return array
         */
        private function _parseKeywords()
        {
            // grab meta tag; return immediately if false
            $keywords = $this->_parseMetaTag('keywords');
            if ($keywords === false) {
                return false;
            }

            // iterate over them, and set as array using comma as delimiter
            $keywords = explode(',', $keywords);
            foreach ($keywords as &$keyword) {
                $keyword = trim($keyword);
            }
            return $keywords;
        }

        /**
         * _parseMetaTag
         * 
         * @access protected
         * @param  string $value
         * @return false|string
         */
        protected function _parseMetaTag($value, $attr = 'name')
        {
            // get the page meta-tag (name attribute leading)
            preg_match_all(
                '/<meta.+' . ($attr) . '=(\'|")(\bdc\.\b)?\b' .
                ($value) . '\b\1.+content=(\'|")(.*)\3/imU',
                $this->_body,
                $tags
            );

            // meta tag not found (not that it's empty, but not-found)
            if (empty($tags[3])) {

                // get the page meta-tag (name attribute trailing)
                preg_match_all(
                    '/<meta.+content=(\'|")(.*)\1.+' . ($attr) .
                    '=(\'|")(\bdc\.\b)?\b' . ($value) . '\b\3.+/imU',
                    $this->_body,
                    $tags
                );

                // no meta-tag found
                if (empty($tags[3])) {
                    return false;
                }

                // return value found
                return array_pop($tags[2]);
            }
            
            // return meta-tag found
            return array_pop($tags[4]);
        }

        /**
         * _parseOpenGraphKeys
         * 
         * @access protected
         * @return array
         */
        protected function _parseOpenGraphKeys()
        {
            preg_match_all('/([\'|"]{1})og:([a-zA-Z0-9\-:_]{1,25})\1/', $this->_body, $keys);
            return array_pop($keys);
        }

        /**
         * _parseTitle
         * 
         * @access private
         * @return string
         */
        private function _parseTitle()
        {
            // get the page's title
            // preg_match('/<title[^>]*>([^<]+)<\/title>/i', $this->_body, $titles);
            preg_match('/<title[^>]*>([^<]+)<\/title>/im', $this->_body, $titles);
            if (empty($titles)) {
                return false;
            }

            // return title found
            return trim(array_pop($titles));
        }

        /**
         * getBase
         * 
         * @notes  do not need to check for false value after _parseFavicon as
         *         default will always be returned (eg. domain.com/favicon.ico)
         * @access public
         * @return false|string
         */
        public function getBase()
        {
            // return base found, if any
            if (isset($this->_parsed['base'])) {
                return $this->_parsed['base'];
            }

            // parse base/return
            $base = $this->_parseBase();
            $this->_parsed['base'] = $base;
            return $base;
        }

        /**
         * getCharset
         * 
         * Returns the charset defined in the document, which may or may not be
         * the charset that is rendered by the browser. This is because
         * charsets passed from a server directive supercede those defined in
         * the document.
         * 
         * @see    <http://stackoverflow.com/questions/3458217/how-to-use-regular-expression-to-match-the-charset-string-in-html>
         * @access public
         * @return false|string
         */
        public function getCharset()
        {
            // return charset found, if any
            if (isset($this->_parsed['charset'])) {
                return $this->_parsed['charset'];
            }

            // parse charset/return
            $charset = $this->_parseCharset();
            if ($charset === false) {
                return false;
            }
            $this->_parsed['charset'] = $charset;
            return $charset;
        }

        /**
         * getDescription
         * 
         * @access public
         * @return false|string
         */
        public function getDescription()
        {
            // return description found, if any
            if (isset($this->_parsed['description'])) {
                return $this->_parsed['description'];
            }

            // parse description/return
            $description = $this->_parseDescription();
            if ($description === false) {
                return false;
            }
            $this->_parsed['description'] = $description;
            return $description;
        }

        /**
         * getDetails
         * 
         * @access public
         * @return array
         */
        public function getDetails()
        {
            // return relevant meta data
            return array(
                'base' => $this->getBase(),
                'charset' => $this->getCharset(),
                'favicon' => $this->getFavicon(),
                'meta' => array(
                    'description' => $this->getDescription(),
                    'keywords' => $this->getKeywords()
                ),
                'images' => $this->getImages(),
                'openGraph' => $this->getOpenGraph(),
                'title' => $this->getTitle(),
                'url' => $this->getUrl()
            );
        }

        /**
         * getFavicon
         * 
         * @notes  do not need to check for false value after _parseFavicon as
         *         default will always be returned (eg. domain.com/favicon.ico)
         * @access public
         * @return false|string
         */
        public function getFavicon()
        {
            // return favicon found, if any
            if (isset($this->_parsed['favicon'])) {
                return $this->_parsed['favicon'];
            }

            // parse favicon/return
            $favicon = $this->_parseFavicon();
            $this->_parsed['favicon'] = $favicon;
            return $favicon;
        }

        /**
         * getImages
         * 
         * @access public
         * @return false|array
         */
        public function getImages()
        {
            // return images found, if any
            if (isset($this->_parsed['images'])) {
                return $this->_parsed['images'];
            }

            // parse images/return
            $images = $this->_parseImages();
            $this->_parsed['images'] = $images;
            return $images;
        }

        /**
         * getKeywords
         * 
         * @access public
         * @return false|array
         */
        public function getKeywords()
        {
            // return keywords found, if any
            if (isset($this->_parsed['keywords'])) {
                return $this->_parsed['keywords'];
            }

            // parse keywords if found; encode; return
            $keywords = $this->_parseKeywords();
            if ($keywords === false) {
                return false;
            }
            $this->_parsed['keywords'] = $keywords;
            return $keywords;
        }

        /**
         * getOpenGraph
         * 
         * @access public
         * @return array
         */
        public function getOpenGraph()
        {
            $graph = array();
            $keys = $this->_parseOpenGraphKeys();
            foreach ($keys as $key) {
                $graph[$key] = $this->_parseMetaTag('og:' . ($key), 'property');
            }

            // resolve path to open graph image, if found
            if (in_array('image', $keys)) {
                $graph['imagePath'] = $this->_resolveFullPath(
                    $graph['image'],
                    $this->getBase()
                );
            }
            return $graph;
        }

        /**
         * getTitle
         * 
         * @access public
         * @return false|string
         */
        public function getTitle()
        {
            // return title found, if any
            if (isset($this->_parsed['title'])) {
                return $this->_parsed['title'];
            }

            // parse title/return
            $title = $this->_parseTitle();
            if ($title === false) {
                return false;
            }
            $this->_parsed['title'] = $title;
            return $title;
        }

        /**
         * getUrl
         * 
         * @access public
         * @return string
         */
        public function getUrl()
        {
            return $this->_url;
        }
    }
