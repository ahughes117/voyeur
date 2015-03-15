<?php

//uncomment following lines for debugging
//error_reporting(E_ALL | E_STRICT);
//ini_set("display_errors", "1");

require_once (__DIR__ . '/url_utils.php');
require_once (__DIR__ . '/../data/memcached.php');

/**
 * The Link Class, along with the parsing functionality
 */
class Preview {

    public $url;
    public $title;
    public $description;
    public $image;
    public $content;

    /**
     * Creates a preview for a given url
     * 
     * @param type $url
     * @return boolean
     */
    public static function create_url_preview($url) {
        global $memcached;

        try {
            //checking the url
            $url = Preview::polish_url($url);

            //first hitting the cache
            $cache_preview = $memcached->get_cached("url_" . $url);

            if (!$cache_preview) {
                //then fetching the main elements of the preview object
                $preview = Preview::fetch_content($url);

                //parsing the last needed elements
                $preview = Preview::parse_content($preview);

                //returning the preview object
                unset($preview->content);

                //finally setting the preview in cache (making it expire after 15 mins)
                $memcached->set_cached_exp("url_" . $url, serialize($preview), 900);
            } else {
                $preview = unserialize($cache_preview);
            }

            return $preview;
        } catch (Exception $x) {
            return false;
        }
    }

    /**
     * Polishes a url's format
     * 
     * @param type $url
     * @return boolean|string
     */
    private static function polish_url($url) {
        try {
            //first trimming and sanitising the url
            $url = trim(strip_tags($url));

            //then checking if the string includes the protocol
            if (strpos($url, 'http') !== 0) {
                $url = 'http://' . $url;
            }

            //returning the final url
            $url = UrlUtils::get_final_url($url);

            //finally returning the url
            return $url;
        } catch (Exception $x) {
            return false;
        }
    }

    /**
     * Fetches the content and the tags of the page
     * 
     * @param type $url
     * @return \Preview|boolean
     * @throws Exception
     */
    private static function fetch_content($url) {
        //creating a preview object
        $preview = new Preview();
        $preview->url = $url;

        try {
            //opening the location and fetching the contents
            $options = array('http' => array('user_agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:36.0) Gecko/20100101 Firefox/36.0'));
            $context = stream_context_create($options);
            $content = file_get_contents($url, false, $context);

            //checking if we got a proper response
            if (!$content || strlen($content) <= 0)
                throw new Exception();

            //setting the content property
            $preview->content = $content;

            //now fetching the tags
            $tags = get_meta_tags($url);

            //checking if we got a proper response
            if (!$tags)
                throw new Exception();

            //setting the description property
            $preview->description = $tags['description'];
            return $preview;
        } catch (Exception $x) {
            return false;
        }
    }

    /**
     * Parses the title and image elements of the content
     * 
     * @param type $preview
     * @return boolean
     */
    private static function parse_content($preview) {
        try {
            //the regex for the title
            $title_regex = "/<title>(.+)<\/title>/i";
            preg_match_all($title_regex, $preview->content, $title, PREG_PATTERN_ORDER);

            //setting the title on the preview object
            $titles = $title[1];
            $preview->title = $titles[0];

            //first attempting to fetch the meta image
            $dom = new DomDocument;
            $dom->loadHTML($preview->content);
            foreach ($dom->getElementsByTagName('meta') as $tag) {
                if ($tag->getAttribute('property') === 'og:image') {
                    $img_meta = $tag->getAttribute('content');
                }
            }

            //if there is no luck, processing all the other images
            if ($img_meta == null || strcmp($img_meta, "") == 0) {

                //the regex for the images
                $image_regex = '/<img[^>]*' . 'src=[\"|\'](.*)[\"|\']/Ui';
                preg_match_all($image_regex, $preview->content, $img, PREG_PATTERN_ORDER);

                $images = $img[1];

                //after we fetched the images, now we need to pick one of them, the biggest one for now
                $size = 0;
                $index = 0;
                for ($i = 0; $i <= sizeof($images); $i++) {
                    if ($images[$i]) {
                        if (strpos($images[$i], 'http') !== 0) {
                            if (strpos($images[$i], '/') !== 0) {
                                $images[$i] = '/' . $images[$i];
                            }
                            $images[$i] = $preview->url . $images[$i];
                        }

                        if (getimagesize($images[$i])) {
                            list($width, $height, $type, $attr) = getimagesize($images[$i]);
                            if ($width * $height > $size) {
                                $index = $i;
                                $size = $width * $height;
                            }
                        }
                    }
                }
                $preview->image = $images[$index];
            } else {
                $preview->image = $img_meta;
            }

            return $preview;
        } catch (Exception $x) {
            return false;
        }
    }

}
