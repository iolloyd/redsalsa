<?php
include 'redis.php';
$r = new Redis('localhost', 6379);
//$r->hmset('myhmset', array('foo' =>  'yep', 'bar' => 'pey'));
$r->hmset('myhmset', 'foo', 'yep', 'bar', 'pey');
$r->lpush('mylist', 'foobar');
//print_r($r->hgetall('myhmset'));
