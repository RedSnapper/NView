<?php namespace redsnapper\nview;
mb_internal_encoding('UTF-8');

class SioReg {
	use Form;
	const SIG="Sioreg_";
	public static function sig() { return static::SIG; }
	protected static $munge="sha2(concat(ifnull(username,id),'_',ifnull(password,id),'_',ifnull(email,id),'_',ifnull(ts,id)),256)";
	protected static $v=array();
	protected static $use_un=true;
    protected static $initialised=false;

	function __construct($key=NULL,$receiver_url=NULL) {
		$this->iniForm($key,@static::$v[static::SIG]);
		$this->key=$key;
		$this->receiver_url=$receiver_url; //used in the confirmation email.
		$this->table="sio_user";
		if(static::$use_un) {
			$this->setfld('username',NULL,NULL,@Sio::$presets['username']);
		}
		$this->setfld('emailp',NULL,NULL,@Sio::$presets['email']);
		$this->setfld('passwordn','','!skip');
		$this->setfld('passwordb','','!skip');
 		$this->setfld('commentary','','!skip'); //honeypot.
	}

	public function prefilter() {
		if (isset($this->fields['username'][0])) {
			$this->fields['username'][0]=mb_strtolower($this->fields['username'][0]);
		}
		if (isset($this->fields['emailp'][0])) {
			$this->fields['emailp'][0]=mb_strtolower($this->fields['emailp'][0]);
		}
	}

	public function validate() {
		$retval = true;
		if(static::$use_un) {
			if (isset($this->fields['username'][0])) {
				$un=Settings::esc($this->fields['username'][0]);
				$qry="select count(id) as ok from " . $this->table . " where username='" . $un . "'";
				if ($rx = Settings::$sql->query($qry)) {
					if (strcmp($rx->fetch_row()[0],"0") !== 0) {
						$retval = false;
						$this->seterr("username",Dict::get(static::SIG.'errors_username_exists'));
					}
					$rx->close();
				}
				if (isset($this->fields['passwordn'][0]) && ($this->fields['passwordn'][0] === $this->fields['username'][0])) {
					$this->seterr("passwordn",Dict::get(static::SIG.'errors_new_pw_too_un_simple'));
					$retval = false;
				}
			} else {
				$this->seterr("username",Dict::get(static::SIG.'errors_no_username'));
				$retval = false;
			}
		}

		if (isset($this->fields['emailp'][0])) {
			$em=Settings::esc($this->fields['emailp'][0]);
			$qry="select count(id) as ok from " . $this->table . " where email='".$em."' or emailp='" . $em . "'";
			if ($rx = Settings::$sql->query($qry)) {
				if (strcmp($rx->fetch_row()[0],"0") !== 0) {
					$retval = false;
					$this->seterr("emailp",Dict::get(static::SIG.'errors_email_exists'));
				}
				$rx->close();
			}
			if (isset($this->fields['passwordn'][0]) && ($this->fields['passwordn'][0] === $this->fields['emailp'][0])) {
				$this->seterr("passwordn",Dict::get(static::SIG.'errors_new_pw_too_em_simple'));
				$retval = false;
			}
		} else {
			$this->seterr("emailp",Dict::get(static::SIG.'errors_no_email'));
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
		} else {
			$this->seterr("passwordn",Dict::get(static::SIG.'errors_no_password'));
			$retval = false;
		}
		$this->valSignificant('emailp',Dict::get(static::SIG .'errors_email_badformat'));
		$this->valEmail('emailp',Dict::get(static::SIG .'errors_email_badformat'));
		if ($this->valid) {
			$this->valid = $retval;
		}
	}

	public function populate() {
		$this->vset('emailp');
		$this->vset('commentary','hide'); //it's a honeypot.
	}

	public function commit() {
		$honeypot = @$this->fields['commentary'][0];
		if (empty($honeypot)) { 
			$this->newid = static::createuser(
				@$this->fields['username'][0],
				@$this->fields['emailp'][0],
				@$this->fields['passwordn'][0],
				true,
				$this->receiver_url
			);
		}
		return true;
	}

	public static function conforms($hat=NULL) {
		$retval=false;
		$ha=Settings::esc($hat);
		$query= "select id from sio_user where ".static::$munge."='".$ha."'";
		if ($rs = Settings::$sql->query($query)) {
			if (Settings::rows($rs) == 1) {
				$retval=true;
			}
			$rs->close();
		}
		return $retval;
	}

	public static function pushit($hat=NULL) {
		if(is_null($hat)) {
			$nv=new NView(@static::$v[static::SIG."check_mail"]);
			$nv->set("//*[@data-tr]/@data-tr/preceding-gap()",static::SIG);
		} else {
			$ha=Settings::esc($hat); //Prepare for auto-signin.
			$query= "select emailp,username from sio_user where active='xx' and ".static::$munge."='".$ha."'";
			if ($rs = Settings::$sql->query($query)) {
				$fields=$rs->fetch_row();
				$f_email=$fields[0];
				$f_uname=$fields[1];
				$rs->close();
			}
			//Actual push..			
			$query= "update sio_user set email=emailp,active='on' where active='xx' and ".static::$munge."='".$ha."'";
			Settings::$sql->query($query);
			$nv = new NView(@static::$v[static::SIG."success"]);
			$nv->set("//*[@data-tr]/@data-tr/preceding-gap()",static::SIG);
			Sio::signin($f_uname,$f_email);
		}
		return $nv;
	}
	
	//mail_qry must include '[MUNGE]' or field as munge, and emailp in it's result.
	// eg SioReg::mail_pending($rf['emailp'],$email_qry,Settings::$url);
	public static function mail_pending($destination,$email_qry,$url=NULL) {
		$url = is_null($url) ? $_SERVER["SCRIPT_URI"] : $url;
		$email_qry = str_replace("'[MUNGE]'",static::$munge." as munge",$email_qry);
		if ($rx = Settings::$sql->query($email_qry)) {
			while ($f = $rx->fetch_assoc()) {
			    $from_address="no_reply@" . Settings::$domain;
				$mail_v=new NView(@static::$v[static::SIG."email_body"]);
				$mail_v->set("//*[@data-tr]/@data-tr/preceding-gap()",static::SIG);
				$mail = new PHPMailer();
				$mail->isSendmail();
				$mail->CharSet='utf-8';
				$mail->Encoding='base64';
				$mail->setFrom($from_address, Dict::get(static::SIG .'mail_from'));
				$mail->addAddress($destination,$f['emailp']);
				$mail->addBCC("auto@redsnapper.net",'Auto');
				$mail->Subject = Dict::get(static::SIG.'note_register_title');
				$mail->isHTML(true);
				if (strpos($url, '?') !== false) {
					$url .= '&amp;siof=' . $f['munge'];
				} else {
					$url .= '?siof=' . $f['munge'];
				}
				$mail_v->set("//*[@data-xp='ha']/@href",$url);
				$mail_v->set("//*[@data-xp='hl']/child-gap()",$url);
				$mail->Body = $mail_v->show(false);
				$mail->AltBody=$mail_v->doc()->textContent;;
				$mail->send();
			}
			$rx->close();
		} else {
			print(Settings::$sql->error);
		}
	}

	public static function remindusers($date="1 year",$url=NULL) {	//date is time before now().
		$qry="select id,emailp from sio_user where active='xx' and ts < date_sub(now(),interval $date)";
		if ($rx = Settings::$sql->query($qry)) {
			while ($f = $rx->fetch_assoc()) {
				$email_qry="select ".static::$munge." as munge,emailp from sio_user where id=" . $f['id'];
				static::mail_pending($f['emailp'],$email_qry,$url);
			}
			$rx->close();
		}
	}
	
	//Receiver url is the url that is posted in the email sent for confirmation.
	public static function createuser($un=null,$em=null,$pw=null,$pending=false,$receiver_url=null) {
		$retval = NULL;
		$pwpresent = !is_null($pw);
		$id_for_hash=$em;	//Unescaped used for munge case when registering via email..
		Settings::esc($em); //Escaped.
		if(static::$use_un) {
			$id_for_hash=$un;	//use username.
			Settings::esc($un);
			$extra="username='{$un}'";
		} else { //using email
			$extra="username='".mb_strtolower(uniqid("u",true))."'";
		}
		$unique=false;
		$qve="select count(id) as ok from sio_user where email='{$em}' or emailp='{$em}'";
		if ($rx = Settings::$sql->query($qve)) {
			if (strcmp($rx->fetch_row()[0],"0") === 0) {
				$unique=true;
			}
			$rx->close();
		}
		if ($unique) {
			if ($pending) {
				$em_field = "emailp";
				$active_val= "xx";
				if (static::$use_un) { //sio_forgot needs username in query when use_un is set.
					$email_qry="select '[MUNGE]',emailp,username from sio_user where username='" .$un. "' and active='xx'";
				} else { //using email
					$email_qry="select '[MUNGE]',emailp from sio_user where emailp='" .$em . "' and active='xx'";
				}
			} else {
				$em_field = "email";
				$active_val= "on";
			}
			$ph=SioSetPW::enhash($id_for_hash,$pw); // $pw is being set to null here
			$ph = is_null($ph) ? "NULL" : "'$ph'";
			$insql="insert into sio_user set ts=null,{$extra},{$em_field}='{$em}',password={$ph},active='{$active_val}'";
			Settings::$sql->query($insql);
			if ($r=Settings::$sql->query("select last_insert_id()")) {
				$retval = intval($r->fetch_row()[0]);
				if ($retval !== 0) {
					Settings::$sql->query("insert into sio_userprofile set user=".$retval);
				}
			}
			if ($pending) { //either of these work for auto-signin.
				if($pwpresent) {
					static::mail_pending($em,$email_qry,$receiver_url);
				} else {
					SioForgot::mail_forgot($em,$email_qry,$receiver_url);
				}
			}
		}
		return $retval;
	}

	public static function initialise($use_un=true,$custom_views=array()) {
        if (! static::$initialised) {
        	static::$initialised = true;
			static::$use_un=$use_un;
//views array
			static::$v=array(
				static::SIG."email_body"=>static::SIG."mb.ixml",
				static::SIG."success"=>static::SIG."sv.ixml",
				static::SIG."check_mail"=>static::SIG."pv.ixml"
			);

//translations
			$en = array(
				static::SIG ."prompt_email"=>"Email",
				static::SIG ."prompt_password"=>"Password",
				static::SIG ."prompt_retype_password"=>"Retype Password",
				static::SIG ."button_register"=>"Register",
				static::SIG ."button_signin"=>"Sign In",
				static::SIG ."mail_from"=>"Registration Verification Service",
				static::SIG ."note_register_title"=>"Registration Verification Request For: " . $_SERVER['HTTP_HOST'],
				static::SIG ."note_register_message"=>" Someone with this email address has registered for " . $_SERVER['HTTP_HOST'] .". If this was not you you may safely ignore this, otherwise ",
				static::SIG ."note_register_action_link"=>"please click here to confirm your registration.",
				static::SIG ."errors_email_badformat"=>" The email format is not recognised.",
				static::SIG ."errors_email_exists"=>" A user with this email already exists on the system. Please use another email.",
				static::SIG ."errors_no_email"=>" You must enter an email address.",
				static::SIG ."errors_no_password"=>" You must enter a password.",
				static::SIG ."errors_new_pw_different"=>" Both passwords must be the same.",
				static::SIG ."mesg_register_check_email"=>"Thank you for registering.  A confirmation email has been sent to your email address for verification.",
				static::SIG ."mesg_see_html_alt"=>"Please see the html alternative of this email.",
				static::SIG ."mesg_success"=>"You have successfully validated your registration."
			);

			$de = array(
				static::SIG .'prompt_email'=> 'E-Mail-Adresse',
				static::SIG .'prompt_password'=> 'Passwort',
				static::SIG .'prompt_retype_password'=> 'Passwort wiederholen',
				static::SIG .'button_register'=> 'Registrieren',
				static::SIG ."button_signin"=>"Anmelden",
				static::SIG ."mail_from"=>"Anmeldung Verifikationsanfrag",
				static::SIG .'note_register_title'=> 'Registrierung: ' . $_SERVER['HTTP_HOST'],
				static::SIG .'note_register_message'=> 'Sie möchten sich auf unserer Website registrieren.',
				static::SIG .'note_register_action_link'=> 'Bitte bestätigen Sie Ihre Registrierung.',
				static::SIG .'errors_email_badformat'=> 'Das E-Mail-Format kann nicht erkannt werden.',
				static::SIG ."errors_email_exists"=>" Diese  E-Mail-Adresse wird bereits verwendet. Bitte wählen Sie eine andere E-Mail-Adresse.",
				static::SIG .'errors_no_password'=> "Bitte geben Sie Ihr Passwort ein.",
				static::SIG .'errors_new_pw_different'=> "Die beiden Passwörter müssen übereinstimmen.",
				static::SIG .'mesg_register_check_email'=> "Um Ihre Registrierung zu bestätigen, lesen Sie bitte die E-Mail, die Ihnen gerade zugeschickt wurde.",
				static::SIG .'mesg_see_html_alt'=> "Sollte die E-Mail nicht korrekt dargestellt werden, wechseln Sie bitte in das HTML-Format.",
				static::SIG .'mesg_success'=> "Sie wurden erfolgreich registriert."
			);

			if(static::$use_un) {
				static::$v[static::SIG]=static::SIG."uv.ixml";
				$en[static::SIG ."prompt_username"]="Username";
				$de[static::SIG ."prompt_username"]="Benutzername";

				$en[static::SIG ."errors_username_exists"]="This username already exists on the system. Please choose another.";
				$de[static::SIG ."errors_username_exists"]="Dieser Benutzername wird bereits verwendet. Bitte wählen Sie einen anderen Benutzernamen.";

				$en[static::SIG ."errors_no_username"]="You need to enter a username.";
				$de[static::SIG ."errors_no_username"]="Bitte geben Sie Ihren Benutzernamen ein.";

				$en[static::SIG ."errors_new_pw_too_un_simple"]=" Passwords must be different from your username!";
				$de[static::SIG ."errors_new_pw_too_un_simple"]=" Passwort und Benutzername dürfen nicht übereinstimmen.";
			} else {
				static::$v[static::SIG]=static::SIG."mv.ixml";
				$en[static::SIG ."errors_new_pw_too_em_simple"]=" Passwords must be different from your email!";
				$de[static::SIG ."errors_new_pw_too_em_simple"]=" Passwort und E-Mail-Adresse dürfen nicht übereinstimmen.";
			}
			//set these after any contextual changes;
			static::$v = array_replace(static::$v,$custom_views);
			Dict::set($en,'en');
			Dict::set($de,'de');
        }
	
	}

}
