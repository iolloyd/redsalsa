<?php

include 'redis.php';

$r = new Rdis('localhost', 6379);

$r->hmset('myhmset', 'foo', 'yep', 'bar', 'pey');
$r->lpush('mylist', 'one');
$r->lpush('mylist', 'two');
$r->lpush('mylist', 'three');
print_r($r->hgetall('myhmset'));
print_r($r->lrange('mylist', 0, -1));

