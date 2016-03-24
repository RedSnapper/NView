<?php namespace redsnapper\nview;
mb_internal_encoding('UTF-8');

/**
 * class 'SioSignOut'
 */
class SioSignOut {
	use Form;
	const SIG = "Siosignout_";
	public static function sig() { return static::SIG; }
	private static $v=array();
	private static $use_un=true;

/**
 * '__construct'
 */
	function __construct($key=NULL) {
		$this->iniForm($key,@static::$v[static::SIG]);
	    $this->key=$key;
	    $this->table="sio_user";
	}

/**
 * 'func'
 * fn overloading of trait 'Form'.
 */
	protected function func() {
		switch ($this->fields['_fn'][0]) {
			case 'email': {
				$sf=new SioSetEmail();
				return $sf->form();
			} break;
			case 'reset': {
				$sf=new SioSetPW();
				return $sf->form();
			} break;
		}
	}

/**
 * 'commit'
 * fn OVERLOADING trait 'Form'.
 */
	protected function commit() {
		Sio::signout();
		return true;
	}

	public static function initialise($use_un=true,$custom_views=array()) {
		static::$use_un=$use_un;
//views array.
		static::$v=array(
			static::SIG=>static::SIG."v.ixml",
		);
		static::$v = array_replace(static::$v,$custom_views);

//now do translations
		$en = array(
			static::SIG .'button_signout'=>"Sign Out",
			static::SIG .'button_newemailaddr'=>"Change Registered Email Address",
			static::SIG .'button_newpassword'=>"Change Password"
		);
		$de = array(
			static::SIG .'button_signout'=>"Abmelden",
			static::SIG .'button_newemailaddr'=>"E-Mail-Adresse ändern",
			static::SIG .'button_newpassword'=>"Passwort ändern"
		);
		Dict::set($en,'en');
		Dict::set($de,'de');
	}
}
