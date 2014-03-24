<?php

namespace Goatherd\WpPlugin\Cache;

/**
 * Cache veto observer.
 *
 */
class Veto extends AbstractObserver
{
    /**
     * Debug log.
     *
     * @var array
     */
    protected $log = array();

    /**
     * Retrieve debug log.
     *
     * Log contains messages why veto was given
     *
     * @return array
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * (non-PHPdoc)
     * @see \Goatherd\WpPlugin\Cache\AbstractObserver::execute()
     */
    public function handleVeto($url, $subject)
    {
        global $wp_query;

        // reset (debug) log
        $this->log = array();

        // has veto?
        if (!$subject->isCacheable()) {
            return ;
        }

        // do not cache if an error occured just now
        // -TODO may be unneeded as fastcgi forwards error status to server
        if (($e = error_get_last()) && isset($e['type']) && (E_ERROR & $e['type'])) {
            $this->log[] = 'a reportable error occured';
        }

        // disallow cache for error pages
        if (http_response_code() !== 200) {
            $this->log[] = 'http response code is not 200';
        }

        // disallow cache for non-get requests
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->log[] = 'http method is not GET';
        }

        // uncached page types
        if (!isset($wp_query) || is_search() || is_404() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required()) {
            $this->log[] = 'wp page type no-cache condition';
        }

        // do not cache non-index entry scripts
        if (basename($_SERVER['SCRIPT_NAME']) != 'index.php') {
            $this->log[] = 'script is not index.php';
        }

        if (defined('DOING_AJAX') || defined('DOING_CRON')) {
            $this->log[] = 'ajax or cron request';
        }

        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            $this->log[] = 'is logged in';
        }

        // avoid if special cookies are set
        if (preg_match('/^(wp-postpass|wordpress_logged_in|comment_author)_/', implode("\n", array_keys($_COOKIE)))) {
            $this->log[] = 'special cookie no-cache';
        }

        // by default do not cache dynamic requests
        if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') {
            $this->log[] = 'non-empty query no-cache';
        }

        // veto if reasoning/ message was logged
        if ($this->log !== array()) {
            $subject->veto();
        }
    }
}
