<?php

namespace Goatherd\WpPlugin;

/**
 * Defines a generic request cache for WordPress.
 *
 * Filters:
 * - `gh-cache-url(string)` determine cache url
 * - `gh-cache-ttl(integer)` determine time-to-live
 *
 * Actions:
 * - `gh-cache-prepare` prepare listener/ cache config on `plugins-loaded` hook
 * - `gh-cache-veto`  veto item cachability
 * - `gh-cache-cache` cache item
 * - `gh-cache-purge` remove item from cache
 *
 * It is possible to purge/ cache for multiple URLs but actual execution depends
 * on the actual observer/ handler.
 *
 * Portions derived from
 * copyright (c) Nginx Cache Controller by Ninjax Team (Takayuki Miyauchi; http://ninjax.cc/)
 */
class Cache implements \SplSubject
{
    /**@#+
     * Configuration keys.
     *
     * @var string
     */
    const CONFIG_HANDLERS = 'handle';
    const CONFIG_TIME_TO_LIVE = 'ttl';
    const CONFIG_IS_CACHEABLE = 'cache';
    /**@+-*/

    /**@#+
     * Event context.
     *
     * Names equal WordPress action tags called on event.
     *
     * @var integer
     */
    const EVENT_INIT = 'gh-cache-init'; // wordpress not yet ready
    const EVENT_PREPARE = 'gh-cache-prepare';
    const EVENT_VETO = 'gh-cache-veto';
    const EVENT_CACHE = 'gh-cache-cache';
    const EVENT_PURGE = 'gh-cache-purge';
    /*@#-*/

    /**
     * Plugin options.
     *
     * @var array
     */
    protected $config = array(
        self::CONFIG_HANDLERS => array(
            // default cache handler
            'Goatherd\WpPlugin\Cache\Cache',
            // default purge handler
            'Goatherd\WpPlugin\Cache\Purge',
            // default veto handler
            'Goatherd\WpPlugin\Cache\Veto',
        ),
        self::CONFIG_IS_CACHEABLE => true,
        self::CONFIG_TIME_TO_LIVE => 86400, // one day
    );

    // instance config
    protected $isCacheable;
    protected $timeToLive;
    protected $url;

    /**
     * Event context.
     *
     * @var string
     */
    private $context;

    /**
     * Plugin scope object instance.
     *
     * @var self
     */
    private static $instance;

    /**
     * 
     * @var boolean
     */
    private static $wordpressEnabled = false;

    /**
     *
     * @var array
     */
    private $observers = array();

    /**
     * Singleton init for wordpress.
     *
     * @return self
     */
    public static function initPlugin()
    {
        // run once
        if (!isset(self::$instance)) {
            self::$instance = new static();
            self::$instance->initInstance();
        }

        return self::$instance;
    }

    /**
     * WordPress integration.
     *
     * @return self
     */
    public static function initWordpress()
    {
        $instance = static::initPlugin();

        if (!self::$wordpressEnabled) {
            // mark context switch
            self::$wordpressEnabled = true;
            $instance->initWordpressInstance();
        }

        return $instance;
    }

    /**
     * Enable filters and caching listener.
     *
     * @throws \ErrorException if WordPress is not yet marked available
     */
    public function initWordpressInstance()
    {
        if (!self::$wordpressEnabled) {
            throw new \ErrorException('WordPress not yet marked available.');
        }

        // (1) register cache handlers
        // nonce must last as long as cache expire
        add_filter('nonce-life', array($this, 'filterNonceLife'));

        // proxy header support
        add_filter('pre_comment_user_ip', array($this, 'filterPreCommentUserIp'));
        add_filter('nocache_headers', array($this, 'filterNoCache'));

        // -TODO: not interested in those? wp-cron should be disabled anyway
        #add_action('plugins_loaded', array($this, 'wp_cron_caching'));

        // disable direct current commentor detail output
        add_filter('wp_get_current_commenter', array($this, 'filterwpGetCurrentCommentor'), 9999);
        // enqueue scripts for commentor cookie
        add_action('wp_enqueue_scripts', array($this, 'actionWpEnqueueScripts'));

        // (2) register purge/ flush handlers
        // comments
        add_action('wp_set_comment_status', array($this, 'actionPurgeComment'));
        add_action('comment_post', array($this, 'actionPurgeComment'));

        // posts
        add_action('save_post', array($this, 'actionPurgePost'));
        add_action('publish_future_post', array($this, 'actionPurgePost'));

        // TODO allow some admin control like in
        // add_action('wp_ajax_flushcache', array($this, 'wp_ajax_flushcache'));
        // add_action('wp_ajax_flushthis', array($this, 'wp_ajax_flushthis'));

        // allow handlers to prepare for wordpress
        $this->context = self::EVENT_PREPARE;
        $this->notify();
    }

    /**
     * Purge post by comment id.
     *
     * @param unknown $commentId
     */
    public function actionPurgeComment($commentId)
    {
        $c = get_comment($commentId);
        $url = get_permalink($c->comment_post_ID);
        $this->doPurge($url);
    }

    /**
     * Purge post by id.
     *
     * @param unknown $postId
     */
    public function actionPurgePost($postId)
    {
        $url = get_permalink($postId);
        $this->doPurge($url);
    }

    public function actionWpEnqueueScripts()
    {
        if (is_user_logged_in()) {
            return;
        }

        // does not test actual comment form exists
        if (is_singular() && comments_open()) {
            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'jquery.cookie',
                '//cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.0/jquery.cookie.min.js',
                array('jquery'),
                '1.4.0',
                true
            );

            add_action("wp_print_footer_scripts", array($this, "wp_print_footer_scripts_admin_ajax"));
        }
    }

    // nginx-champuru hack
    public function wp_print_footer_scripts_admin_ajax()
    {
        // TODO use script file
        $js = '
<script type="text/javascript">
(function($){
    $("#author").val($.cookie("comment_author_%1$s"));
    $("#email").val($.cookie("comment_author_email_%1$s"));
    $("#url").val($.cookie("comment_author_url_%1$s"));
})(jQuery);
</script>
';
        $js = sprintf($js, COOKIEHASH);

        echo apply_filters('wp_print_footer_scripts_admin_ajax', $js);
    }

    public function filterwpGetCurrentCommentor($commenter)
    {
        // disable
        return array(
            'comment_author'       => '',
            'comment_author_email' => '',
            'comment_author_url'   => '',
        );
    }

    public function filterNonceLife($life)
    {
        // set to be at least as long as ttl
        $ttl = $this->getTimeToLive(true) + 1;
        return $ttl > $life ? $ttl : $life;
    }

    public function filterPreCommentUserIp()
    {
        // proxy support: override remote-addr with x-forwarded-for header
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $X_FORWARDED_FOR = explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']);
            $REMOTE_ADDR = trim($X_FORWARDED_FOR[0]);
        } else {
            $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
        }

        return $REMOTE_ADDR;
    }

    public function filterNoCache($headers)
    {
        // -TODO allow to use config; this may be early on but a filter may work as well
        $doVeto = true;
        if ($doVeto) {
            $this->veto();
        }

        return $headers;
    }

    /**
     * 
     */
    public function initInstance()
    {
        // register default handlers
        foreach ($this->config[self::CONFIG_HANDLERS] as $class) {
            if (class_exists($class, true)) {
                $this->attach(new $class());
            }
        }

        // force reset
        $this->isCacheable = null;
        $this->url = null;
        $this->timeToLive = null;

        // allow handlers to prepare for wordpress
        $this->context = self::EVENT_INIT;
        $this->notify();
    }

    /**
     * Event context (self::EVENT_*).
     *
     * @return string
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Cacheabilty flag.
     *
     * @return boolean
     */
    public function isCacheable()
    {
        if (!isset($this->isCacheable)) {
            $this->isCacheable = (boolean) $this->config[self::CONFIG_IS_CACHEABLE];
        }

        return $this->isCacheable;
    }

    /**
     * Veto caching.
     *
     */
    public function veto($msg = false)
    {
        $this->isCacheable = false;
    }

    /**
     * Get cache URL.
     *
     * @return string
     */
    public function getUrl($refresh = false)
    {
        if (!isset($this->url) || $refresh) {
            // not yet available...
            $url = 'http';
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) {
                $url .= 's';
            } elseif (isset($_SERVER['SERVER_PORT']) && '443' == $_SERVER['SERVER_PORT']) {
                $url .= 's';
            }
            $url .= '://';
            $url .= $_SERVER['HTTP_HOST'];
            $url .= isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

            if (self::$wordpressEnabled) {
                $url = apply_filters('gh-cache-url', $url);
            }
            $this->url = $url;
        }

        return $this->url;
    }

    /**
     * Override cache URL.
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get time-to-live.
     *
     * @return integer
     */
    public function getTimeToLive($refresh = false)
    {
        if (!isset($this->timeToLive) || $refresh) {
            $ttl = $this->config[self::CONFIG_TIME_TO_LIVE];
            if (self::$wordpressEnabled) {
                $ttl = (integer) apply_filters('gh-cache-ttl', $ttl);
            }
            $this->timeToLive = $ttl;
        }

        return $this->timeToLive;
    }

    /**
     * Issue purge command.
     *
     * @return void
     */
    public function doPurge($url = null)
    {
        // refresh
        if (!isset($url)) {
            $this->getUrl(true);
        } else {
            $this->url = $url;
        }

        $this->context = self::EVENT_PURGE;
        $this->notify();
    }

    /**
     * Issue cache command.
     *
     * @return void
     */
    public function doCache()
    {
        // -TODO handle gateway issues when errors occur within callback
        // -TODO handle issues when this is used within an ob_cache callback
        ob_start();
        // refresh
        $this->getUrl(true);
        $this->getTimeToLive(true);

        // veto
        $this->context = self::EVENT_VETO;
        $this->notify();

        // cache
        if ($this->isCacheable()) {
            $this->context = self::EVENT_CACHE;
            $this->notify();
        }
        ob_get_clean();
    }

    /**
     * (non-PHPdoc)
     * @see SplSubject::attach()
     */
    public function attach(\SplObserver $observer)
    {
        $this->observers[spl_object_hash($observer)] = $observer;
    }

    /**
     * (non-PHPdoc)
     * @see SplSubject::detach()
     */
    public function detach(\SplObserver $observer)
    {
        $hash = spl_object_hash($observer);
        if (isset($this->observers[$hash])) {
            unset($this->observers[$hash]);
        }
    }

    /**
     * (non-PHPdoc)
     * @see SplSubject::notify()
     */
    public function notify()
    {
        // wordpress integration
        if (self::$wordpressEnabled) {
            do_action($this->getContext(), $this);
        }

        // observers
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }
}
