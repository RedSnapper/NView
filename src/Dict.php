<?php
namespace RS\NView;

/**
 * class 'Dict'
 */
class Dict extends Singleton {

	public static $ln=array('en'); //this is an array of fall-back languages...
	private static $lib=array();
	
	public static function library() {
		return '<code>'.print_r(static::$lib,true).'</code>';
	}
	
	public static function setln($ln=array('en')) {
		static::$ln=$ln;
		if (!isset(static::$lib[$ln[0]])) {
			static::$lib[$ln[0]] =  array();
		}	
	}
	
 	public static function ln() {
 		return static::$ln[0];
 	}

	public static function set($kv,$ln) {
		if (!isset(static::$lib[$ln])) {
			static::$lib[$ln] = array();
		}	
		foreach($kv as $k => $v) {
			static::$lib[$ln][$k]=$v;
		}
	}
	
	public static function get($key) {
		$x = 0;
		while (($x < count(static::$ln)) && (!isset(static::$lib[static::$ln[$x]][$key]))) {
		 $x++; 
		}
		if ((!isset(static::$ln[$x])) || (!isset(static::$lib[static::$ln[$x]][$key]))) {
			return '['.$key.']';
		} else {
			return static::$lib[static::$ln[$x]][$key];
		}
	}
	
 	public static function load($file) {
 //load json or xml or whatever....
 	}
}







