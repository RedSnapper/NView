<?php
mb_internal_encoding('UTF-8');

/**
 * class 'SioSignIn'
 * uses SioForgot.
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
	function __construct($key=NULL,$debug=false) {
		$this->iniForm($key,@static::$v[static::SIG],true,'id',$debug);
		$this->key=$key;
		$this->table="sio_user";
		if(static::$use_un) {
			$this->setfld('username',NULL,NULL,@Sio::$presets['username']);
		} else {
			$this->setfld('email',NULL,NULL,@Sio::$presets['email']);
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
	public function func() {
		$retval = null;
		switch ($this->fields['_fn'][0]) {
			case 'forgot': {
				if (Sio::$useReCaptcha) {
					$SioRe = new SioCaptcha();
					$Sio = new SioForgot();
					$Sio::formlets([$SioRe, $Sio], false);
					$retval = $Sio->reveal();
					$cap = $SioRe->reveal();
					$retval->set("//*[@data-xp='siso__captcha']", $cap);
				} else {
					$sf = new SioForgot();
					$retval = $sf->form(false);
					$retval->set("//*[@data-xp='siso__captcha']");
				}
			} break;
			case 'register': {
				if (Sio::$useReCaptcha) {
					$SioRe = new SioCaptcha();
					$Sio = new SioReg();
					$Sio::formlets([$SioRe, $Sio], false);
					$retval = $Sio->reveal();
					$cap = $SioRe->reveal();
					$retval->set("//*[@data-xp='siso__captcha']", $cap);
				} else {
					$sf = new SioReg();
					$retval = $sf->form(false);
					$retval->set("//*[@data-xp='siso__captcha']");
				}
			} break;
		}
		return $retval;
	}

	public function prefilter() {
		if (isset($this->fields['username'][0])) {
			$this->fields['username'][0]=mb_strtolower($this->fields['username'][0]);
		}
		if (isset($this->fields['email'][0])) {
			$this->fields['email'][0]=mb_strtolower($this->fields['email'][0]);
		}
	}

/**
 * 'validate'
 * fn fulfilling abstract requirement of trait 'Form'.
 * validate all fields in this->fields.
 * errors are placed into the this->view.
 */
	public function validate() {
		$retval = false;
		$unm = NULL;
		if(static::$use_un) {
			if (isset($this->fields['username'][0])) {
				$unm=$this->fields['username'][0];
				$field="username";
			} else {
				$this->seterr("username",Dict::get(static::SIG.'errors_username_empty'));
			}
		} else { //using email
			$field="email";
			if (isset($this->fields['email'][0])) { //not empty
				$this->valEmail('email',Dict::get(static::SIG .'errors_email_badformat'));
				$unm=$this->fields['email'][0];
			} else { //set empty error.
				$this->seterr("email",Dict::get(static::SIG.'errors_email_empty'));
			}
		}
		$pw = @$this->fields['password'][0];
		if (!is_null($unm) && !is_null($pw)) {
			$ph=SioSetPW::enhash($unm,$pw);
			Settings::esc($unm);
			$qry="select count(id) as ok from " . $this->table . " where active='on' and ".$field."='" .$unm. "' and password='" . $ph . "'";
			if ($rx = Settings::$sql->query($qry)) {
				if (strcmp($rx->fetch_row()[0],"1") === 0) {
					$retval=true;
				} else {
					if(static::$use_un) {
						$pfield="username";
					} else {
						$pfield="emailp";
					}
					$qry="select count(id) as ok from " . $this->table . " where active='xx' and ".$field."='" .$unm. "' and password='" . $ph . "'";
					if ($ry = Settings::$sql->query($qry)) {
						if (strcmp($ry->fetch_row()[0],"1") === 0) {
							if(static::$use_un) {
								$this->seterr("username",Dict::get(static::SIG.'errors_user_pending_validation'));
							} else {
								$this->seterr("email",Dict::get(static::SIG.'errors_user_pending_validation'));
							}
							$qry="select id,emailp from sio_user where active='xx' and ".$field."='" .$unm. "' and password='" . $ph . "'";
							if ($rp = Settings::$sql->query($qry)) {
								while ($rf = $rp->fetch_assoc()) {
									$email_qry="select '[MUNGE]',emailp from sio_user where id=" . $rf['id'];
									SioReg::mail_pending($rf['emailp'],$email_qry,Settings::$website . Settings::$url);
								}
								$rp->close();
							}

						} else {
							if(static::$use_un) {
								$this->seterr("username",Dict::get(static::SIG.'errors_username_unmatched'));
							} else {
								$this->seterr("email",Dict::get(static::SIG.'errors_email_unmatched'));
							}
						}
						$ry->close();
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
	public function commit() {
		if(static::$use_un) {
			$f_uname=$this->fields['username'][0];
			$user_sql=$f_uname; Settings::esc($user_sql);
			$query= "select email from sio_user where active='on' and username='$user_sql'";
			if ($rs = Settings::$sql->query($query)) {
				$f_email = $rs->fetch_row()[0];
				$rs->close();
			}
		} else {
			$f_email=$this->fields['email'][0];
			$email_sql=$f_email; Settings::esc($email_sql);
			$query= "select username from sio_user where active='on' and email='$email_sql'";
			if ($rs = Settings::$sql->query($query)) {
				$f_uname = $rs->fetch_row()[0];
				$rs->close();
			}
		}
		Sio::signin($f_uname,$f_email,true); //signin regardless of if you are already signed in.
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
		$es = array(
			static::SIG .'prompt_password'=>"Contraseña",
			static::SIG .'button_signin'=>"Iniciar sesión",
			static::SIG .'button_forgot'=>"He olvidado la contraseña",
			static::SIG .'button_register'=>"Registrarse"
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

			$es[static::SIG .'prompt_username']= "Nombre de usuario";
			$es[static::SIG .'errors_username_unmatched']=" Su nombre de usuario o contraseña no coinciden con nuestros registros.";
			$es[static::SIG .'errors_username_empty']=" Es necesario que introduzca su nombre de usuario.";
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

			$es[static::SIG ."prompt_email"]= "Email";
			$es[static::SIG ."errors_email_badformat"]=" El formato de correo electrónico no se reconoce";
			$es[static::SIG .'errors_email_unmatched']=" El correo electrónico o la contraseña no coinciden con nuestros registros";
			$es[static::SIG .'errors_email_empty']=" Es necesario que introduzca su dirección de correo electrónico";
		}
		$en[static::SIG .'errors_user_pending_validation']=" You are registered, but we are waiting for you to validate your email.<br />A new validation email has just been sent.";
		$en[static::SIG ."errors_password_empty"]= "You need to enter your email address and password!";

		$de[static::SIG .'errors_user_pending_validation']=" Sie sind registriert, aber wir warten auf Sie Ihre E-Mail zu bestätigen.<br />Eine neue E-Mail- Validierung hat gerade gesendet wurde.";
		$de[static::SIG ."errors_password_empty"]= "Sie müssen Ihre E-Mail -Adresse und Ihr Passwort eingeben !";

		$es[static::SIG .'errors_user_pending_validation']=" Usted está registrado, pero estamos esperando a que valide su correo electrónico.<br /> Se le acaba de enviar un nuevo correo electrónico de validación.";
		$es[static::SIG ."errors_password_empty"]= "Es necesario que introduzca su dirección de correo electrónico y su contraseña!";

		//set the views after all the above.
		static::$v = array_replace(static::$v,$custom_views);
		Dict::set($en,'en');
		Dict::set($de,'de');
		Dict::set($es,'es');
	}


}
