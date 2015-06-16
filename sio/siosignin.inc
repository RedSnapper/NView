<?php
mb_internal_encoding('UTF-8');

/**
 * class 'SioSignIn'
 */
class SioSignIn {
	use Form;
	const SIG = "siosignin_";
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
		if(static::$use_un) {
			$this->setfld('username');
		} else {
			$this->setfld('email');
		}
		$this->setfld('password');
	}

/**
 * 'ident'
 *  This overrides the standard ident - we don't want to check usr value for signin.
 */
	private function ident() {
		return md5($this->signature);
	}


/*
 * populate, by common demand...
*/
	public function populate() { 
		if(static::$use_un) {
			$this->vset('username');
		} else { //using email
			$this->vset('email');
		}
	}

/**
 * 'func'
 * fn fulfilling form trait func, which deals with different button presses.
 * These are set by values in the buttons on the view.
 * fn 'save' - the default action - is handled higher up.
 */
	protected function func() {
		switch ($this->fields['_fn'][0]) {
			case 'forgot': {
				$sf=new SIOForgot();
				return $sf->form();
			} break;
			case 'register': {
				$sf=new SIOReg();
				return $sf->form();
			} break;
		}
	}
/**
 * 'validate'
 * fn fulfilling abstract requirement of trait 'Form'.
 * validate all fields in this->fields.
 * errors are placed into the this->view.
 */
	protected function validate() {
		$retval = false;
		$unm = NULL;
		if(static::$use_un) {
			if (isset($this->fields['username'][0])) {
                $this->fields['username'][0]=mb_strtolower($this->fields['username'][0]);
				$unm=$this->fields['username'][0];
				$field="username";
			} else {
				$this->seterr("username",Dict::get(static::SIG.'errors_username_empty'));
			}
		} else { //using email
			if (isset($this->fields['email'][0])) { //not empty
				if (!filter_var($this->fields['email'][0], FILTER_VALIDATE_EMAIL)) { //incorrect format
					$this->seterr("email",Dict::get(static::SIG.'errors_email_badformat'));
				} else { //good format.
					$unm=$this->fields['email'][0];
					$field="email";
				}
			} else { //set empty error.
				$this->seterr("email",Dict::get(static::SIG.'errors_email_empty'));
			}
		}
		if (!is_null($unm) && isset($this->fields['password'][0])) {
			$ph=SIOSetPW::enhash($unm,$this->fields['password'][0]);
			Settings::esc($unm);
			$qry="select count(id) as ok from " . $this->table . " where active='on' and ".$field."='" .$unm. "' and password='" . $ph . "'";
			if ($rx = Settings::$sql->query($qry)) {
				if (strcmp($rx->fetch_row()[0],"1") === 0) {
					$retval=true;
				} else {
					if(static::$use_un) {
						$this->seterr("username",Dict::get(static::SIG.'errors_username_unmatched'));
					} else {
						$this->seterr("email",Dict::get(static::SIG.'errors_email_unmatched'));
					}
				}
				$rx->close();
			}
		} else { //set empty error.
			$this->seterr("password",Dict::get(static::SIG.'errors_password_empty'));
		}
		$this->valid = $retval;
	}

/**
 * 'commit'
 * fn OVERLOADING trait 'Form'.
 */
	protected function commit() {
		if(static::$use_un) {
			$username=$this->fields['username'][0];
			Settings::esc($username);
			Session::set('username',$username);
			$query= "select email from ".$this->table." where username='".$username."'";
			if ($rs = Settings::$sql->query($query)) {
				Session::set('email',$rs->fetch_row()[0]);
				$rs->close();
			}
		} else {
			$email=$this->fields['email'][0];
			Settings::esc($email);
			Session::set('email',$email);
			$query= "select username from ".$this->table." where email='".$email."'";
			if ($rs = Settings::$sql->query($query)) {
				Session::set('username',$rs->fetch_row()[0]);
				$rs->close();
			}
		}
		Settings::usr();
		return true;
	}

	public static function initialise($use_un=true,$custom_views=array()) {
		static::$use_un=$use_un;
//translations
		$en = array(
			static::SIG .'prompt_password'=>"Password",
			static::SIG .'button_signin'=>"Sign In",
			static::SIG .'button_forgot'=>"Forgot Password",
			static::SIG .'button_register'=>"Register"
		);
		$de = array(
			static::SIG .'prompt_password'=>"Passwort",
			static::SIG .'button_signin'=>"Anmelden",
			static::SIG .'button_forgot'=>"Passwort vergessen",
			static::SIG .'button_register'=>"Registrieren"
		);

//set the views array.
		if(static::$use_un) {
			static::$v[static::SIG]=static::SIG."uv.ixml";
			$en[static::SIG .'prompt_username']= "Username";
			$en[static::SIG .'errors_username_unmatched']=" Either the username or password don't match our records.";
			$en[static::SIG .'errors_username_empty']=" You need to enter your username.";

			$de[static::SIG .'prompt_username']= "Benutzername";
			$de[static::SIG .'errors_username_unmatched']=" Benutzername und/oder Passwort nicht korrekt.";
			$de[static::SIG .'errors_username_empty']=" Bitte geben Sie Ihren Benutzernamen an.";

		} else {
			static::$v[static::SIG]=static::SIG."mv.ixml";
			$en[static::SIG ."prompt_email"]= "Email";
			$en[static::SIG ."errors_email_badformat"]=" The email format is not recognised";
			$en[static::SIG .'errors_email_unmatched']=" Either the email or password don't match our records";
			$en[static::SIG .'errors_email_empty']=" You need to enter your email address";

			$de[static::SIG ."prompt_email"]= "E-Mail";
			$de[static::SIG ."errors_email_badformat"]=" Das E-Mail-Format kann nicht erkannt werden.";
			$de[static::SIG .'errors_email_unmatched']=" E-Mail-Adresse und/oder Passwort nicht korrekt.";
			$de[static::SIG .'errors_email_empty']=" Bitte geben Sie Ihr Passwort ein.";

		}
		$en[static::SIG ."errors_password_empty"]= "You need to enter your email address and password!";

		//set the views after all the above.
		static::$v = array_replace(static::$v,$custom_views);
		Dict::set($en,'en');
		Dict::set($de,'de');
	}


}