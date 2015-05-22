<?php

require __DIR__ . "/common.php";

$client->createRule('thumbnail', array(
    array('square', 200),
    array('grey'),
));

$client->createRule('small', array(
    array('resize', 700),
));

$client->createRule('big', array(
    array('resize', 1200),
));

$output = $client->commit();
var_dump($output);
