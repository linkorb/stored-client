<?php

require __DIR__ . "/../vendor/autoload.php";

use Stored\Client;

Client::configure(array(
    'server' => 'http://localhost:9999',
    'server' => 'http://188.166.70.124',
    'callback' => 'http://node.php/handle',
    'user' => 2,
    'secret' => 'f438d6947aadeed8bf37b434d2204a2b35d42178ef1d4a53aa21827fcaf3aa42',
));
