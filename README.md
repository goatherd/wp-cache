gh-cache
========

WordPress request cache layer.

By default serves nginx reverse proxy/ fastcgi cache modules but is extendedable to use any request caching backend.

## Features

* generic request cache layer
* cache, cache-veto and purge events
* prepare event on `plugins loaded` hook
* defines time-to-live filter (with implicite veto for non-positive times)
* default event handlers (cache, veto, purge)

### Requirements

* PHP 5.3 or newer
* WordPress 3.8+ (may work with older releases)

## Default configuration features

* enables proxy caching through `X-accel-expires` header
* flushes cache on comment
* flushes cache on post publish/ edit
* flush some related posts by default
* selectivly bypasses cache
* can veto through `gh-cache-veto` filter
* can override time-to-live through `gh-cache-ttl` filter
* can extend purge to cover related URLs

### Requirements

* nginx
* php curl module
* can edit vhost configuration

### Installation

**TODO** explain nginx config (simple)
**TODO** explain nginx config (full)
**TODO** explain nginx config (advanced purge for nginx 1.5.7+ and purge module)

## Incompatibilities

* Set-Cookie headers prevent caching
  If your WordPress installation relies on sessions caching will be rather limited or impossible to archive

* Cache is flushed by URL: one-at-a-time
  If your WordPress installation requires huge networks of related posts to be flushed you should use other methods

* Your WordPress installation is required to be cacheable.
  The most common criteria is to serve static content (and AJAX dynamic parts) for the majority of requests.

* Not intended for user (agent) level caching

## Technical Notes

PSR-4 compliant code approximating [SOLID](http://en.wikipedia.org/wiki/SOLID_%28object-oriented_design%29) priciples.

Currently only nginx proxy and fastcgi caching is supported.

Due to limitations of the nginx cache implementation URLs can only be flushed one-at-a-time.
Wildcard flush or full flush is not directly supported.
