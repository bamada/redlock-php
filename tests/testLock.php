<?php

require_once __DIR__ . '/../src/LockManager.php';

$servers = [
        ['127.0.0.1',1000, 0.2],
        ['127.0.0.1',1001, 0.2]
];

$lockManager = new LockManager($servers);
$valueToLock = 'testLock';
$lockResult  = $lockManager->lock($valueToLock);

if($lockResult){
    print_r('Lock acquired.');
} else{
    print_r('Lock not acquired.');
}