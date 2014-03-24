<?php

namespace Goatherd\WpPlugin\Cache;

/**
 * Cache observer.
 *
 */
class Cache extends AbstractObserver
{
    /**
     * (non-PHPdoc)
     * @see \Goatherd\WpPlugin\Cache\AbstractObserver::execute()
     */
    public function handleCache($url, $subject)
    {
        // verify conditions
        if (!$subject->isCacheable()) {
            return ;
        }

        $datetime = gmdate('D, d M Y H:i:s \G\M\T', time());
        header('X-Accel-Expires: ' . $subject->getTimeToLive(), true);
        header('Last-Modified: ' . $datetime, true);
    }

    /**
     * (non-PHPdoc)
     * @see \Goatherd\WpPlugin\Cache\AbstractObserver::prepare()
     */
    public function handleInit($url, $subject)
    {
        // initially reject all pages until cachability is verified
        header('X-Accel-Expires: 0', true);
    }

    /**
     * (non-PHPdoc)
     * @see \Goatherd\WpPlugin\Cache\AbstractObserver::handlePrepare()
     */
    public function handlePrepare($url, $subject)
    {
        // register cache listener
        header_register_callback(array($subject, 'doCache'));
    }
}
