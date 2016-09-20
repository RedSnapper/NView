<?php
mb_internal_encoding('UTF-8');

/**
 * class 'SioSignOut'
 */
class SioSignOut {
	use Form;
	const SIG = "siosignout_";
	public static function sig() { return static::SIG; }

	protected static $v = array();
	protected static $use_un = true;

/**
 * '__construct'
 */
	function __construct($key=NULL) {
		$this->iniForm($key,@static::$v[static::SIG]);
	  $this->key=$key;
	  $this->table="sio_user";
	}

/**
 * 'populate'
 * fn overloading of trait 'Form'.
 */
	public function populate() {
		if(Settings::$usr['has_password']){
			$this->view->set("//*[@data-xp='btnset']");
		}else{
			$this->view->set("//*[@data-xp='btnreset']");
		}
	}

/**
 * 'func'
 * fn overloading of trait 'Form'.
 */
	public function func() {
		$retval = null;
		switch ($this->fields['_fn'][0]) {
			case 'email': {
				if (static::$useReCaptcha) {
					$SioRe = new SioCaptcha();
					$Sio = new SioSetEmail();
					$Sio::formlets([$SioRe, $Sio], false);
					$retval = $Sio->reveal();
					$cap = $SioRe->reveal();
					$retval->set("//*[@data-xp='siso__captcha']", $cap);
				} else {
					$sf = new SioSetEmail();
					$retval = $sf->form(false);
					$retval->set("//*[@data-xp='siso__captcha']");
				}
			} break;
			case 'reset': {
				$sf=new SioSetPW();
				$retval = $sf->form();
			} break;
		}
		return $retval;
	}

/**
 * 'commit'
 * fn OVERLOADING trait 'Form'.
 */
	public function commit() {
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
			static::SIG .'button_newpassword'=>"Change Password",
			static::SIG .'button_setpassword'=>"Set Password"
		);
		$es = array(
			static::SIG .'button_signout'=>"Cerrar sesión",
			static::SIG .'button_newemailaddr'=>"Cambiar la dirección de correo electrónico registrada",
			static::SIG .'button_newpassword'=>"Cambiar contraseña",
			static::SIG .'button_setpassword'=>"Configurar contraseña"
		);
		$de = array(
			static::SIG .'button_signout'=>"Abmelden",
			static::SIG .'button_newemailaddr'=>"E-Mail-Adresse ändern",
			static::SIG .'button_newpassword'=>"Passwort ändern",
			static::SIG .'button_setpassword'=>"Passwort festlegen"
		);
		
		
		Dict::set($en,'en');
		Dict::set($es,'es');
		Dict::set($de,'de');
	}
}
