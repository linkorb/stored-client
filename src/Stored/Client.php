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
    protected $config;
    protected $rules = array();

    public function __construct(Array $config)
    {
        $args = array('server', 'user', 'secret');
        foreach ($args as $key) {
            if (empty($config[$key])) {
                throw new \RuntimeException("Missing configuration {$key}");
            }
        }
        $this->config = $config;
    }

    // uploadSuccess() {{{
    /**
     *  Did the upload to stored succeed?
     */
    public function uploadSuccess()
    {
        return !empty($_REQUEST['std']) && is_array($_REQUEST['std']) && $this->checkRequestSignature();
    }
    // }}}

    // getCurrentUrl() {{{
    /**
     *  Get current URL
     */
    protected function getCurrentUrl()
    {
        $https =!empty($_SERVER['HTTPS']) ? "https" : "http";
        return $https . '://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    }
    // }}}

    // checkRequestSignature() {{{
    /**
     *  Check if the request is legit or not
     *
     *  @return bool
     */
    protected function checkRequestSignature()
    {
        $data = $_REQUEST['std'];
        ksort($data);
        unset($data['sg']);
        $data = http_build_query($data);
        return hash('sha256', $this->config['secret'] . hash('sha256', $data)) === $_REQUEST['std']['sg'];
    }
    // }}}

    // base64_encode($str) {{{
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
    // }}}

    // doSign($str) {{{
    /**
     *  Simple signature
     *
     *  Signs the string (usually a JSON object) with a key, so the server can know
     *  that our request is legit.
     *
     *  @return string
     */
    protected function doSign($str)
    {
        $to_sign = $this->config['secret'] . "\0" . $str;

        return hash('sha256', $to_sign, true);
    }
    // }}}

    // getUniqId() {{{
    /**
     *  Generate random strings, with time as their prefix.
     *
     *  Store random values is not index-friendly (as it a random hash),
     *  but by prefixing time we add order (so we it can be index friendly)
     *
     *  @return <string>
     */
    protected function getUniqId()
    {
        $prefix = dechex(time());

        if (is_callable('openssl_random_pseudo_bytes')) {
            return $prefix . bin2hex(openssl_random_pseudo_bytes(8));
        }

        return substr($prefix . substr(strrev(uniqid(true)), 0, 10) . strrev(uniqid(true)), 0, 24);
    }
    // }}}

    // prepareUpload(Array $args) {{{
    /**
     *  Prepare upload to stored
     *
     *  @return array('file_id' => $internal_id, 'url' => $url)
     */
    public function prepareUpload(Array $args)
    {
        $internal_id  = $this->getUniqId();
        $settings = hex2bin($internal_id) . json_encode($args);
        $signature = $this->doSign($settings);
        $upload_id = $this->base64_encode(chr(strlen($signature)) . $signature . $settings);
        $config    = $this->config;

        return array(
            'file_id' => $internal_id,
            'url' => "{$config['server']}/{$config['user']}/{$upload_id}"
        );
    }
    // }}}

    // getUploadUrl(Array $args()) {{{
    /**
     *  Generates an URL for client upload
     *
     *  A client upload is when give pass this URL to our 
     *  visitor and they push their files directly to the 
     *  stored server.
     *
     *  @return array($file_id, $url_to_upload)
     */
    public function getUploadUrl(Array $args = array())
    {
        $args['cb'] = $this->getCurrentUrl();
        $upload     =  $this->prepareUpload($args);
        
        return $upload['url'];
    }
    // }}}

    // getUploadDetails() {{{
    /**
     *  Get the upload details, confirmed first if
     *  the data is legit
     *
     *  @return array
     */
    public function getUploadDetails()
    {
        if (!$this->checkRequestSignature()) {
            throw new \RuntimeException("The request is not legit");
        }
        return $_REQUEST['std'];
    }
    // }}}
    
    // storeUpload($name, $args) {{{
    /**
     *  Upload a user uploads to stored
     *
     *  Is not the most optimize thing to-do, as there are many steps:
     *      1. User uplaod to client
     *      2. Client pushes file towards stored
     *
     *  Ideally an user should upload directly to stored
     *
     *  @param string $name     key name in $_FILES
     *  @param array  $args     Upload settings
     */
    public function storeUpload($name, Array $args = array())
    {
        if (empty($_FILES) || empty($_FILES[$name])) {
            throw new \RuntimeException("There is no upload named $name");
        }
        return $this->storeFile($_FILES[$name]['tmp_name'], $args);
    }
    // }}}

    // storeFile($file, Array $args) {{{
    /**
     *  Store file
     *
     *  Store a file from the file-system to stored-server.
     *
     *  @param  string $file    File path
     *  @param  array  $args    Upload settings
     */
    public function storeFile($file, Array $args = array())
    {
        if (!is_readable($file)) {
            throw new \RuntimeException("{$file} does not exists or cannot be read");
        }
        $args['name']  = 'file';
        $args['limit'] = filesize($file)+1;
        $url  =  $this->getUploadUrl($args) . '.json';
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
    // }}}

    public function createRule($name, Array $specs)
    {
        $this->rules[$name] = $specs;

        return $this;
    }

    protected function api($path, $post = "")
    {
        $ch = curl_init($this->config['server'] . $path);
        if ($post) {
            if (is_array($post)) {
                $post = http_build_query($post);
            }
            curl_setopt_array($ch, array(
                CURLOPT_POST            => 1,
                CURLOPT_POSTFIELDS      => $post,
            ));
        }

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => array(
                'X-User: ' . $this->config['user'],
                'X-Auth: ' . bin2hex($this->doSign($path . "\0" . $post)),
            ),
        ));

        $body = @json_decode(curl_exec($ch));
        curl_close($ch);

        return $body;
    }

    public function commit()
    {
        $body = json_encode(array('rules' => $this->rules, 'id' => (int)microtime(true)*1000));
        return $this->api('/api/rules', $body);
    }

}
