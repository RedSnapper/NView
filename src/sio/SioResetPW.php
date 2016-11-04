<?php
mb_internal_encoding('UTF-8');

//This is the 'receiving end' of the paired sioforgot_ class
class SioResetPW {
	use Form;
	const SIG = "sioresetpw_";
	public static function sig() { return static::SIG; }
	//The following munge must be the same as the one in sioforgot_
	private static $munge="sha2(concat(ifnull(username,id),'-',ifnull(password,id),'-',ifnull(email,id),'-',ifnull(ts,id)),256)";
	private static $v=array();
	private static $use_un=true;

	function __construct($key=NULL) {
		$this->iniForm($key,@static::$v[static::SIG]);
		$this->key=$key; //This will be the siof value passed by url.
		$this->table="sio_user";
		$this->setfld('password');
		$this->setfld('passwordb','','!skip');
	}

	public static function setView(string $key, NView $view) {
		static::$v[$key] = $view;
	}

/**
 * 'validate'
 * fn fulfilling abstract requirement of trait 'Form'.
 * validate all fields in this->fields.
 * errors are placed into the this->view.
 */
	public function validate() {
		$retval = $this->valid;
		$the_username="[[broken username]]";
		$the_email="[[broken email]]";
		$ha = Settings::esc($this->key); //this comes via the url...
		$query = "select username,if(active='xx',emailp,email) as email from " . $this->table . " where " . self::$munge . "='$ha'";
		if ($rs = Settings::$sql->query($query)) {
			while ($f = $rs->fetch_assoc()) {
				$the_username=$f['username'];
				$the_email=$f['email'];
			}
			$rs->close();
		} else {
			$retval = false;
		}
		if (isset($this->fields['password'][0]) && isset($this->fields['passwordb'][0])) {
			$pwa=$this->fields['password'][0];
			$pwb=$this->fields['passwordb'][0];
			if ($pwa !== $pwb ) {
				$this->seterr("password",Dict::get(static::SIG .'errors_password_different'));
				$retval = false;
			}
			$err_ar = array();
			$pw_retval = Sio::$utility->pw_validate($pwa,$err_ar);
			foreach($err_ar as $err) {
				$this->seterr("password",Dict::get($err));
			}
			$retval= $retval && $pw_retval;
			if ($pwa === $the_username) {
				$this->seterr("password",Dict::get(static::SIG .'errors_new_pw_too_un_simple'));
				$retval = false;
			}
			if ($pwa === $the_email) {
				$this->seterr("password",Dict::get(static::SIG .'errors_new_pw_too_em_simple'));
				$retval = false;
			}
		} else {
			$retval = false;
			$this->seterr("password",Dict::get(static::SIG .'errors_password_empty'));
		}
		$this->valid = $retval;
	}

	public function commit() {
		$ha=Settings::esc($this->key); //this comes via the url...
		$query= "select username,if(active='xx',emailp,email) as email,active from ".$this->table." where ".self::$munge."='".$ha."'";
		if ($rs = Settings::$sql->query($query)) {
			while ($f = $rs->fetch_assoc()) {
				$f_uname=$f['username'];
				$f_email=$f['email'];
				if(static::$use_un) {
					$unm = $f_uname;
				} else { //using email
					$unm = $f_email;
				}
				$ph=SioSetPW::enhash($unm,$this->fields['password'][0]);
				$cond=" and ".self::$munge."='".$ha."'";
				if ($f['active']=='on') {
					$qry="update " . $this->table . " set password='".$ph."',ts=current_timestamp where active='on' ".$cond ;
				} else {
					//password reset has also confirmed the email..
					$qry="update " . $this->table . " set password='".$ph."',active='on',email=emailp,ts=current_timestamp where active='xx' ".$cond ;
				}
				Settings::$sql->query($qry);
			}
			$rs->close();
			Sio::signin($f_uname,$f_email);
		}
		$this->show = false;
		return true;
	}

	public static function pushit() {
		$nv = new NView(@static::$v[static::SIG."success"]);
		$nv->set("//*[@data-tr]/@data-tr/preceding-gap()",static::SIG);
		return $nv;
	}

	public static function conforms($hat=NULL,$return_id=false) {
		$retval= NULL;
		if (!is_null($hat)) {
			$ha=Settings::esc($hat);
			$query = "select id from sio_user where " . self::$munge . "='$ha'";
			if ($rs = Settings::$sql->query($query)) {
				$retval= (int) $rs->fetch_row()[0];
				$rs->close();
				//we cannot change the db here (to ensure that the hash only works once),
				//because we will use the ts to match the ha.
				$retval = $retval > 0 ? $retval : null;
			}
		}
		$retval = $return_id ? $retval : !is_null($retval) ;
		return $retval;
	}

	public static function initialise($use_un=true,$custom_views=array()) {
		static::$use_un=$use_un;
//views arrays
		static::$v=array(
			static::SIG=>static::SIG."v.ixml",
			static::SIG."success"=>static::SIG."sv.ixml"
		);
		static::$v = array_replace(static::$v,$custom_views);
//translations
		$en = array(
			static::SIG ."mesg_success"=>"You have successfully changed your password.",
			static::SIG .'prompt_password' => "Password",
			static::SIG .'prompt_retype_password' => "Retype Password",
			static::SIG .'button_new_password'=>"Set New Password",
			static::SIG .'errors_password_different'=>" Both passwords must be the same.",
			static::SIG .'errors_password_empty'=>" Enter a password.",
			static::SIG ."errors_new_pw_too_un_simple"=>" Passwords must be different from your username!",
			static::SIG .'errors_new_pw_too_em_simple'=>"  Passwords must be different from your email!"
		);
		$es = array(
			static::SIG ."mesg_success"=>"Ha cambiado correctamente la contraseña.",
			static::SIG .'prompt_password' => "Contraseña",
			static::SIG .'prompt_retype_password' => "Vuelva a escribir la contraseña",
			static::SIG .'button_new_password'=>"Crear una nueva contraseña",
			static::SIG .'errors_password_different'=>" Ambas contraseñas deben ser iguales.",
			static::SIG .'errors_password_empty'=>" Escriba su contraseña.",
			static::SIG ."errors_new_pw_too_un_simple"=>" La contraseña debe ser diferente de su nombre de usuario!",
			static::SIG .'errors_new_pw_too_em_simple'=>"  La contraseña debe ser diferente de su correo electrónico!"
		);
		$de = array(
			static::SIG ."mesg_success"=>"Sie haben Ihre passwort erfolgreich geändert.",
			static::SIG .'prompt_password' => "Passwort",
			static::SIG .'prompt_retype_password' => "Passwort wiederholen",
			static::SIG .'button_new_password'=>"Neues Passwort festlegen",
			static::SIG .'errors_password_different'=>" Beide Passwörter müssen übereinstimmen.",
			static::SIG .'errors_password_empty'=>" Bitte geben Sie Ihr Passwort ein.",
			static::SIG .'errors_new_pw_too_un_simple'=>" Passwort und Benutzername dürfen nicht übereinstimmen.",
			static::SIG .'errors_new_pw_too_em_simple'=>" Passwort und E-Mail dürfen nicht übereinstimmen."
		);
		Dict::set($en,'en');
		Dict::set($es,'es');
		Dict::set($de,'de');
	}


}

