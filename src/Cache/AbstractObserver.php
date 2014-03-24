<?php

namespace Goatherd\WpPlugin\Cache;

use Goatherd\WpPlugin\Cache as Subject;

abstract class AbstractObserver implements \SplObserver
{
    /**
     * (non-PHPdoc)
     * @see SplObserver::update()
     */
    public function update(\SplSubject $subject)
    {
        // ensure subject
        if (!(is_object($subject) || $subject instanceof Subject)) {
            return false;
        }

        // extract event
        $context = $subject->getContext();
        $url = $subject->getUrl();

        // propagate
        switch ($context) {
            case Subject::EVENT_INIT:
                $this->handleInit($url, $subject);
                break;

            case Subject::EVENT_PREPARE:
                $this->handlePrepare($url, $subject);
                break;

            case Subject::EVENT_CACHE:
                $this->handleCache($url, $subject);
                break;

            case Subject::EVENT_VETO:
                $this->handleVeto($url, $subject);
                break;

            case Subject::EVENT_PURGE:
                $this->handlePurge($url, $subject);
        }
    }

    /**@#+
     * Event handlers.
     *
     * @param string  $url
     * @param Subject $subject
     * 
     * @return void
     */
    protected function handleCache($url, $subject) {}
    protected function handleInit($url, $subject) {}
    protected function handlePrepare($url, $subject) {}
    protected function handlePurge($url, $subject) {}
    protected function handleVeto($url, $subject) {}
    /*@#-*/
}
