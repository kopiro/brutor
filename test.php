<?php

include 'Brutor.php';

$b = new Brutor([
    'times_per_ip' => 3,
    'times' => 'forever',
    'sleep_per_ip' => 10,
    'sleep' => 120,
    'random_ua' => true,
    'curl_request'  => "'http://www.google.com' -X POST -H 'Origin: http://www.google.com'",
    'curl_continue' => function() { return true; },
    'curl_continue_per_ip' => function($resp) { return false; }
]);

$b->start();