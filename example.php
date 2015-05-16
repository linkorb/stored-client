<?php

require __DIR__ . "/vendor/autoload.php";

use Stored\Client;

Client::configure(array(
    'server' => 'http://localhost:9999',
    'callback' => 'http://node.php/handle',
    'public_key'  => '0xf0dkjasjda',
    'private_key' => '0x0fkkdasdas',
));

$url = Client::image('foobar');
echo `curl -d 'cesar' "{$url[1]}"`;
var_dump($url);

