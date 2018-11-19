An implementation of RedLock algorithm

Based on Based on [Redlock-rb](https://github.com/ronnylt/redlock-php) 

Initialize lock manager:
```php
//Initialize connection
$servers = [
    ['127.0.0.1', 6379, 0.01],
    ['127.0.0.1', 6389, 0.01]
];

$lockManager = new LockManager($servers);
```

Acquire lock:
```php
$lockResult = $lockManager->lock('foo');//Return true when the lock is acquired false otherwise
```

Release lock:
```php
//release locked value
$lockManager->unlock('foo');
```
