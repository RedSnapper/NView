<?php
mb_internal_encoding('UTF-8');

class SioSetPW {
	use Form;
	const SIG = "siosetpw_";
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
		$this->setfld('password','','!skip');
		$this->setfld('passwordn','','!skip');
		$this->setfld('passwordb','','!skip');
	}

/**
 * 'validate'
 * fn fulfilling abstract requirement of trait 'Form'.
 * validate all fields in this->fields.
 * errors are placed into the this->view.
 */
	protected function validate() {
		$retval = true;
		if (isset($this->fields['password'][0])) {
			if(static::$use_un) {
				$unm=Settings::$usr['username'];
			} else { //using email
				$unm=Settings::$usr['email'];
			}
			$ph=SioSetPW::enhash($unm,$this->fields['password'][0]);
			$qry="select count(id) as ok from " . $this->table . " where active='on' and id='" .Settings::$usr['ID']. "' and password='" . $ph . "'";
			if ($rx = Settings::$sql->query($qry)) {
				if (strcmp($rx->fetch_row()[0],"1") !== 0) {
					$retval = false;
					$this->seterr("password",Dict::get(static::SIG.'errors_orig_pw_wrong'));
				}
				$rx->close();
			}
		} else {
			$this->seterr("password",Dict::get(static::SIG.'errors_password_empty'));
			$retval = false;
		}
		if (isset($this->fields['passwordn'][0]) && isset($this->fields['passwordb'][0])) {
			$pwa=$this->fields['passwordn'][0];
			$pwb=$this->fields['passwordb'][0];
			if ($pwa !== $pwb ) {
				$this->seterr("passwordn",Dict::get(static::SIG.'errors_new_pw_different'));
				$retval = false;
			}
			$err_ar = array();
			$pw_retval = Sio::$utility->pw_validate($pwa,$err_ar);
			foreach($err_ar as $err) {
				$this->seterr("passwordn",Dict::get($err));
			}
			$retval= $retval && $pw_retval;
			if ($pwa === Settings::$usr['username']) {
				$this->seterr("passwordn",Dict::get(static::SIG.'errors_new_pw_too_un_simple'));
				$retval = false;
			}
			if ($pwa === Settings::$usr['email']) {
				$this->seterr("passwordn",Dict::get(static::SIG .'errors_new_pw_too_em_simple'));
				$retval = false;
			}
		} else {
			$this->seterr("passwordn",Dict::get(static::SIG.'errors_new_pw_empty'));
			$retval = false;
		}
		$this->valid = $retval;
	}

	protected function commit() {
		if(static::$use_un) {
			$unm=Settings::$usr['username'];
		} else { //using email
			$unm=Settings::$usr['email'];
		}
		$ph=SioSetPW::enhash($unm,$this->fields['passwordn'][0]);
		$qry="update " . $this->table . " set password='".$ph."' where active='on' and id='" .Settings::$usr['ID']. "'";
		Settings::$sql->query($qry);
		$this->show = false;
		return true;
	}

	public function pushit() {
		$nv = new NView(@static::$v[static::SIG."success"]);
		$nv->set("//*[@data-tr]/@data-tr/preceding-gap()",static::SIG);
		return $nv;
	}

	public static function enhash($unm=NULL,&$pw=NULL) {
		if (!is_null($unm)) {
			Settings::esc($unm);
			$ph = NULL;
			if(!is_null($pw)){
				$ph=hash('sha256', $unm . hex2bin('5BE0BDA8E0BDBCE0BDBEE0BC8BE0BDA7E0BDB1E0BDB4E0BDBE5D') . hash('sha256',$pw,false), false);
				Settings::esc($ph);
			}
			$pw=NULL;
			return $ph;
		} else {
			return NULL;
		}
	}

	public static function initialise($use_un=true,$custom_views=array()) {
		static::$use_un=$use_un;
//set the static arrays.
		static::$v=array(
			static::SIG=>static::SIG."v.ixml",
			static::SIG."success"=>static::SIG."sv.ixml"
		);
		static::$v = array_replace(static::$v,$custom_views);

//now do translations
		$en = array(
			static::SIG ."mesg_success"=>"You have successfully changed your password.",
			static::SIG ."prompt_original_password"=>"Current Password",
			static::SIG ."prompt_new_password"=>"New Password",
			static::SIG ."prompt_retype_new_password"=>"Retype New Password",
			static::SIG ."button_set_new_password"=>"Set New Password",
			static::SIG ."errors_orig_pw_wrong"=>" The current password doesn't match our records.",
			static::SIG ."errors_password_empty"=>" You must enter your current password.",
			static::SIG ."errors_new_pw_empty"=>" You must enter a new password.",
			static::SIG ."errors_new_pw_different"=>" Both passwords must be the same.",
			static::SIG ."errors_new_pw_too_un_simple"=>" Passwords must be different from your username!",
			static::SIG ."errors_new_pw_too_em_simple"=>" Passwords must be different from your email!"
		);
		$es = array(
			static::SIG ."mesg_success"=>"Ha cambiado correctamente la contraseña.",
			static::SIG ."prompt_original_password"=>"Contraseña actual",
			static::SIG ."prompt_new_password"=>"Nueva contraseña",
			static::SIG ."prompt_retype_new_password"=>"Reescriba la nueva contraseña",
			static::SIG ."button_set_new_password"=>"Crear una contraseña nueva",
			static::SIG ."errors_orig_pw_wrong"=>" La contraseña actual no coincide con nuestros registros.",
			static::SIG ."errors_password_empty"=>" Debe introducir su contraseña actual.",
			static::SIG ."errors_new_pw_empty"=>" Debe introducir una nueva contraseña.",
			static::SIG ."errors_new_pw_different"=>" Ambas contraseñas deben ser iguales.",
			static::SIG ."errors_new_pw_too_un_simple"=>" Las contraseñas deben ser diferentes de su nombre de usuario!",
			static::SIG ."errors_new_pw_too_em_simple"=>" Las contraseñas deben ser diferentes de su correo electrónico!"
		);
		$de = array(
			static::SIG ."mesg_success"=>"Sie haben Ihre passwort erfolgreich geändert.",
			static::SIG ."prompt_original_password"=>"Bisheriges Passwort",
			static::SIG ."prompt_new_password"=>"Neues Passwort",
			static::SIG ."prompt_retype_new_password"=>"Neues Passwort wiederholen",
			static::SIG ."button_set_new_password"=>"Neues Passwort festlegen",
			static::SIG ."errors_orig_pw_wrong"=>" Das bisherige Passwort ist nicht korrekt.",
			static::SIG ."errors_password_empty"=>" Bitte geben Sie Ihr bisheriges Passwort ein.",
			static::SIG ."errors_new_pw_empty"=>" Bitte geben Sie ein neues Passwort ein.",
			static::SIG ."errors_new_pw_different"=>" Beide Passwörter müssen übereinstimmen.",
			static::SIG ."errors_new_pw_too_un_simple"=>" Passwort und Benutzername dürfen nicht übereinstimmen.",
			static::SIG ."errors_new_pw_too_em_simple"=>" Passwort und E-Mail dürfen nicht übereinstimmen."
		);
		Dict::set($en,'en');
		Dict::set($es,'es');
		Dict::set($de,'de');
	}

}

