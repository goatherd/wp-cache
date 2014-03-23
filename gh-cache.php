<?php
/*
Plugin Name: GH Cache
Author: Maik Penz
Plugin URI: https://github.com/goatherd
Description: Caching layer for wordpress.
Version: 1.0

Defines an abstract caching layer.
Ships a reverse-proxy cache that interacts with nginx fastcgi and proxy caching.

Requires PHP 5.3 or newer.
*/

// favour autoloading
if (!class_exists('Goatherd\WpPlugin\Cache\AbstractObserver', true)) {
    require_once __DIR__ . '/src/Cache/AbstractObserver.php';
}
if (!class_exists('Goatherd\WpPlugin\Cache\Cache', true)) {
    require_once __DIR__ . '/src/Cache/Cache.php';
}
if (!class_exists('Goatherd\WpPlugin\Cache\Purge', true)) {
    require_once __DIR__ . '/src/Cache/Purge.php';
}
if (!class_exists('Goatherd\WpPlugin\Cache\Veto', true)) {
    require_once __DIR__ . '/src/Cache/Veto.php';
}
if (!class_exists('Goatherd\WpPlugin\Cache', true)) {
    require_once __DIR__ . '/src/Cache.php';
}

// let's fully enable the cache
Goatherd\WpPlugin\Cache::initWordpress();
