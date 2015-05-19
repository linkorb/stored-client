<?php

namespace Stored;

if (!function_exists('curl_file_create')) {
    function curl_file_create($filename, $mimetype = '', $postname = '') {
        return "@$filename;filename="
            . ($postname ?: basename($filename))
            . ($mimetype ? ";type=$mimetype" : '');
    }
}


class Client
{
    protected static $config;
    protected static $uploads = array();

    public static function configure(Array $config)
    {
        self::$config = $config;
    }

    /**
     *  Generate random strings, with time as their prefix.
     *
     *  Store random values is not index-friendly (as it a random hash),
     *  but by prefixing time we add order (so we it can be index friendly)
     *
     *  @return <string>
     */
    protected static function getUniqId()
    {
        $prefix = dechex(time());

        if (is_callable('openssl_random_pseudo_bytes')) {
            return $prefix . bin2hex(openssl_random_pseudo_bytes(8));
        }

        return substr($prefix . substr(strrev(uniqid(true)), 0, 10) . strrev(uniqid(true)), 0, 24);
    }

    /**
     *  Simple signature
     *
     *  Signs the string (usually a JSON object) with a key, so the server can know
     *  that our request is legit.
     *
     *  @return string
     */
    protected static function doSign($str)
    {
        $to_sign = self::$config['secret'] . "\0" . $str;
        $method  = self::getSignMethod();

        return hash($method, $to_sign, true);
    }

    /**
     *  Return the best signing algorithm to calculate the signature hash
     *
     *  @return string
     */
    protected static function getSignMethod()
    {
        if (is_callable('hash_algos') && in_array('sha256', hash_algos())) {
            return 'sha256';
        }
        return 'sha1';
    }

    /**
     *  Make base64 encode URL friendly
     *
     *  @param string $str  String to encode
     *
     *  @return string
     */
    public static function base64_encode($str)
    {
         return strtr(base64_encode($str), '+/=', '-_~');
    }

    /**
     *  Prepare URL to upload file
     *
     *  It returns an URL where the file upload is expected. The URL has
     *  a signature to make sure it's legic, it also has a JSON object
     *  with the settings of what file type and constrains are expected.
     *
     *  The format is quite simple:
     *
     *      base64_encode([1 byte: Signature Length] [N-bytes: Signature] [12 bytes: upload_id] [JSON Object])
     *  
     *
     *  @param array    $config     Array with upload specifications
     *
     *  @return string Upload ID
     */ 
    protected static function prepare_upload(Array $config)
    {
        $internal_id  = self::getUniqId();
        $settings = hex2bin($internal_id) . json_encode($config);
        $signature = self::doSign($settings);
        $upload_id = self::base64_encode(chr(strlen($signature)) . $signature . $settings);
        $config    = self::$config;

        return array(
            'file_id' => $internal_id,
            'url' => "{$config['server']}/{$config['user']}/{$upload_id}"
        );
    }

    protected static function get_url() 
    {
        $https =!empty($_SERVER['HTTPS']) ? "https" : "http";
        return $https . '://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    }

    /**
     *  Generates an URL for client upload
     *
     *  A client upload is when give pass this URL to our 
     *  visitor and they push their files directly to the 
     *  stored server.
     *
     *  @return array($file_id, $url_to_upload)
     */
    public static function client_upload(Array $args = array())
    {
        $args['cb'] = self::get_url();
        return self::prepare_upload($args);
    }
    
    protected static function check_request()
    {
        $data = $_REQUEST['std'];
        ksort($data);
        unset($data['sg']);
        $data = http_build_query($data);
        return hash('sha256', self::$config['secret'] . hash('sha256', $data)) === $_REQUEST['std']['sg'];
    }

    public static function get_upload_details()
    {
        if (!self::Check_request()) {
            throw new \RuntimeException("The request is not legit");
        }
        return $_REQUEST['std'];
    }

    public static function did_upload()
    {
        return !empty($_REQUEST['std']) && is_array($_REQUEST['std']) && self::check_request();
    }

    public static function store_upload($name, Array $args = array())
    {
        if (empty($_FILES) || empty($_FILES[$name])) {
            throw new \RuntimeException("There is no upload named $name");
        }
        return self::store_file($_FILES[$name]['tmp_name'], $args);
    }

    public static function store_file($file, Array $args = array())
    {
        $args['name'] = 'file';
        $args['limit'] = filesize($file)+1;
        $upload = self::prepare_upload($args);
        $url  =  $upload['url'] . '.json';
        $post = array('file' => curl_file_create($file), 'd' => uniqid(true)); 
        $ch   = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST            => 1,
            CURLOPT_POSTFIELDS      => $post,
            CURLOPT_RETURNTRANSFER  => 1
        ));
        $data = @json_decode(curl_exec($ch));
        curl_close($ch);

        return $data;
    }
}

