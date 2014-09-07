<?php

include 'redis.php';

$r = new Rdis('localhost', 6379);

/** @var $r Re*/
$r->hmset('myhmset', 'foo', 'yep', 'bar', 'pey');
$r->lpush('mylist', 'foobar');
print_r($r->hgetall('myhmset'));

