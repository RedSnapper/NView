<?php
namespace RS\NView;

/**
 * Pattern 'Singleton'
 * Taken from
 * http://www.phptherightway.com/pages/Design-Patterns.html
 */
abstract class Singleton {
    public static function getInstance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new static();
        }
        return $instance;
    }
    protected function __construct() {}
    private function __clone() {}
    private function __wakeup() {}
}
