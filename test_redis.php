<?php
include 'redis.php';
$r = new Redis('localhost', 6379);
$r->hmset('myhmset', 'foo', 'yep', 'bar', 'pey');
$r->lpush('mylist', 'foobar');
$r->hgetall('myhmset');
