<?php

namespace Stored;

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
        $to_sign = self::$config['private_key'] . "\0" . $str;
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
     *  Queue an image upload. 
     *
     *  It returns an URL where the image upload is expected. The URL has
     *  a signature to make sure it's legic, it also has a JSON object
     *  with the settings of what file type and constrains are expected.
     *
     *  The format is quite simple:
     *
     *      base64_encode([1 byte: Signature Length] [N-bytes: Signature] [JSON Object])
     *  
     *
     *  @param string   $name   Upload label
     *  @param int      $limit  Size in megabytes
     *
     *  @return string Upload ID
     */ 
    public static function image($name = '', $limit = 1024)
    {
        $internal_id  = self::getUniqId();
        $settings = hex2bin($internal_id) . json_encode(array(
            'type' => 'image',
            'name' => 'name',
            'cb' => self::$config['callback'],
            'method'  => self::getSignMethod(),
            'limit' => $limit,
        ));
        $signature = self::doSign($settings);
        $upload_id = self::base64_encode(chr(strlen($signature)) . $signature . $settings);
        $config    = self::$config;

        return array(
            $internal_id,
            "{$config['server']}/store/{$config['public_key']}/{$upload_id}"
        );
    }
}

