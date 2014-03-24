<?php

namespace Goatherd\WpPlugin\Cache;

/**
 * Purge observer.
 *
 */
class Purge extends AbstractObserver
{
    const PURGE_USER_AGENT = 'gh-cache (purge)';

    /**
     * (non-PHPdoc)
     * @see \Goatherd\WpPlugin\Cache\AbstractObserver::handlePurge()
     */
    public function handlePurge($url, $subject)
    {
        // TODO this is a rather slow, raw method to force cache renewal for single URLs
        // Problems so far:
        // * nginx uses md5-hashes so no wildcard-purge
        // * nginx does only support purge operations since 1.5.7 or when compiled with special modules
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => static::PURGE_USER_AGENT,
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_VERBOSE => true,
            CURLOPT_HTTPHEADER => array(
                'X-cache-purge: ' . rawurlencode($url),
            )
        );

        // TODO determine success; remove debug output (may allow log)
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        curl_exec($ch);
        curl_close($ch);
    }
}
