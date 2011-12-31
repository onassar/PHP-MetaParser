PHP-MetaParser
===

Inspired by Facebook&#039;s link sharing flow, this abstractly accessed class
**attempts** to parse a document (x/html), and retrieve it&#039;s
meta-information.

As that sounds, it returns the following kind of data (using
[http://www.bbc.com/](http://www.bbc.com/) as an example:

    Array
    (
        [base] => http://www.bbc.com/
        [favicon] => http://www.bbc.co.uk/favicon.ico
        [meta] => Array
            (
                [description] => Breaking news, sport, ...
                [keywords] => Array
                    (
                        [0] => BBC
                        [1] => bbc.co.uk
                        ...
                        [6] => BBCi
                    )
    
            )
    
        [images] => Array
            (
                [0] => http://sa.bbc.co.uk/bbc/bbc/s?name=home.page&amp;geo_edition=us&amp;ml_name=barlesque&amp;app_type=web&amp;language=en-GB&amp;ml_version=0.6.3
                [1] => http://static.bbc.co.uk/frameworks/barlesque/1.21.3/desktop/3/img/blocks/light.png
                [2] => http://static.bbc.co.uk/wwhomepage-3.5/ic/news/432-259/57632000/jpg/_57632639_013603124-1.jpg
                [3] => http://static.bbc.co.uk/wwhomepage-3.5/ic/news/432-259/57626000/jpg/_57626527_57626526.jpg
                ...
                [25] => http://me.effectivemeasure.net/em_image
            )
    
        [openGraph] => Array
            (
                [title] => BBC - Homepage
                [type] => website
                [image] => http://static.bbc.co.uk/wwhomepage-3.5/1.0.29/img/iphone.png
                [url] => http://www.bbc.co.uk/
            )
    
        [title] => BBC - Homepage
        [url] => http://www.bbc.com/
    )


This class, as seen by the example below, works very well when coupled with the
[PHP-Curler](https://github.com/onassar/PHP-Curler) class.

### Parsing Example

    // booting
    require_once APP . '/vendors/PHP-Curler/Curler.class.php';
    require_once APP . '/vendors/PHP-MetaParser/MetaParser.class.php';
    
    // curling
    $curler = (new Curler());
    $url = 'http://www.bbc.com/';
    $body = $curler->get($url);
    $parser = (new MetaParser($body, $url));
    print_r($parser->getDetails());
