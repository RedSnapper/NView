<?php
mb_internal_encoding('UTF-8');

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
    public function __wakeup() {}
}
