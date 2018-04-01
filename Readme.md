[![Build Status](https://travis-ci.org/revenuewire/simple-cache.svg?branch=master)](https://travis-ci.org/revenuewire/simple-cache)
[![Coverage Status](https://coveralls.io/repos/github/revenuewire/simple-cache/badge.svg?branch=master)](https://coveralls.io/github/revenuewire/simple-cache?branch=master)
[![Latest Stable Version](https://poser.pugx.org/revenuewire/simple-cache/v/stable)](https://packagist.org/packages/revenuewire/simple-cache)


# Install
```
composer require revenuewire/simple-cache
```

# About
A PSR-16 "Simple Cache" implementation for adding cache to your applications. 

# Adapters
The following cache adapters are available:
* Array
* APCu
* DyanamoDB
* File

# Samples
```php
<?php
    $cache = new \RW\FileCache("/tmp/unit-test-" . uniqid());
    $k = "hello";
    $v = "world";
    $cache->set($k, $v); //return true
    $cache->get($k); //world
    $cache->has($k); //true
    $cache->delete($k); //true
    $cache->get($k); //null
    $cache->has($k); //false
```