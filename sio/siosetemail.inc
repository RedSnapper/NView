<?php
mb_internal_encoding('UTF-8');

class SIOSetEmail {
	use Form;
	const SIG = "siosetem_";
	public static function sig() { return static::SIG; }
	private static $munge="sha2(concat(ifnull(username,id),'+',ifnull(password,id),'+',ifnull(email,id),'+',ifnull(ts,id)),256)";
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
		$this->setfld('emailp');
		$this->setfld('emailb','','!skip');
	}

	public function prefilter() {
		if (isset($this->fields['emailp'][0])) {
			$this->fields['emailp'][0]=mb_strtolower($this->fields['emailp'][0]);
		}
		if (isset($this->fields['emailb'][0])) {
			$this->fields['emailb'][0]=mb_strtolower($this->fields['emailb'][0]);
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
		if (isset($this->fields['emailp'][0]) && isset($this->fields['emailb'][0])) {
			$ema=$this->fields['emailp'][0];
			$emb=$this->fields['emailb'][0];
			if ($ema !== $emb ) {
				$this->seterr("emailp",Dict::get(static::SIG .'errors_emails_unmatched'));
				$retval = false;
			}  else {
				$retval = true;
			}
		} else {
			$retval = false;
			$this->seterr("emailp",Dict::get(static::SIG .'errors_emails_empty'));
		}
		$this->valEmail('emailp',Dict::get(static::SIG .'errors_email_badformat'));
		$pwdval = false;
		if (isset($this->fields['password'][0])) {
			if(static::$use_un) {
				$x_unm = Settings::$usr['username'];
				$x_fld="username";
			} else {
				$x_unm = Settings::$usr['email'];
				$x_fld="email";
			}
			$x_pass = $this->fields['password'][0];
			$x_hsh=SIOSetPW::enhash($x_unm,$x_pass);
			Settings::esc($x_unm);
			$qry="select count(id) as ok from " . $this->table . " where active='on' and ".$x_fld."='" .$x_unm. "' and password='" . $x_hsh . "'";
			if ($rx = Settings::$sql->query($qry)) {
				if (strcmp($rx->fetch_row()[0],"1") === 0) {
					$pwdval=true;
				} else {
					$this->seterr("password",Dict::get(static::SIG.'errors_password_bad'));
				}
				$rx->close();
			} else {
				$this->seterr("password","Unexpected Query Error.");
			}
		} else { //set empty error.
			$this->seterr("password",Dict::get(static::SIG.'errors_password_empty'));
		}
		$this->valid = ($this->valid && $retval && $pwdval);
	}

	protected function commit() {
		$em=$this->fields['emailp'][0];
		$emq=Settings::esc($em);
		$cqry="update ".$this->table." set ts=null,emailp='".$emq."' where id='".Settings::$usr['ID']."'";
		Settings::$sql->query($cqry);
		$qry="select username,".static::$munge." as munge from " . $this->table . " where id='" .Settings::$usr['ID']. "' and active='on'";
		if ($rx = Settings::$sql->query($qry)) {
			while ($f = $rx->fetch_assoc()) {
				$mail_v=new NView(@static::$v[static::SIG."email_body"]);
				$mail = new PHPMailer();
				$mail->isSendmail();
				$mail->CharSet='utf-8';
				$mail->Encoding='base64';
				$mail->setFrom('no_reply@redsnapper.net', Dict::get(static::SIG .'note_email_change_title'));
				if (static::$use_un) {
					$mail->addAddress($em,Settings::$usr['username']);
				} else {
					$mail->addAddress($em);
				}
				$mail->Subject = Dict::get(static::SIG .'note_email_change_title');
				$mail->isHTML(true);
				$url=$_SERVER["SCRIPT_URI"];
				if (strpos($url, '?') !== false) {
					$url .= "&siof=" . $f['munge'];
				} else {
					$url .= "?siof=" . $f['munge'];
				}
				if(!static::$use_un) {
					Session::set(static::SIG,SIOSetPW::enhash($this->fields['emailp'][0],$this->fields['password'][0]));
				}
				$mail_v->set("//*[@data-xp='ha']/@href",$url);
				$mail->Body = $mail_v->show(false);
				$mail->AltBody=Dict::get(static::SIG .'note_email_see_html_alt');
				$mail->send();
			}
			$rx->close();
		}
		$this->show = false;
		return true;
	}

	public static function conforms($hat=NULL) {
		$retval=false;
		$ha=Settings::esc($hat);
		$query= "select id from sio_user where id='" .Settings::$usr['ID']. "' and ".static::$munge."='".$ha."'";
		if ($rs = Settings::$sql->query($query)) {
			if (Settings::rows($rs) == 1) {
				$retval=true;
			}
			$rs->close();
		}
		return $retval;
	}

	public static function pushit($ha=NULL) {
		if (is_null($ha)) { //check email
			$nv = new NView(@static::$v[static::SIG."check_mail"]);
		} else {			 //set success..
			Settings::esc($ha);
			if(static::$use_un) {
				$query= "update sio_user set email=emailp where id='" .Settings::$usr['ID']. "' and ".static::$munge."='".$ha."'";
				Settings::$sql->query($query);
				$nv = new NView(@static::$v[static::SIG."success"]);
		   } else {
				if(!static::$use_un) {
					if (Session::has(static::SIG)) {
						$pw_hash=Session::get(static::SIG);
						$query= "update sio_user set email=emailp,password='".$pw_hash."' where id='" .Settings::$usr['ID']. "' and ".static::$munge."='".$ha."'";
						Settings::$sql->query($query);
						Session::del(static::SIG);
						$nv = new NView(@static::$v[static::SIG."success"]);
					} else {
						$nv = new NView(@static::$v[static::SIG."failure"]);
					}
				}
			}
		}
		return $nv;
	}

	public static function initialise($use_un=true,$custom_views=array()) {
		static::$use_un=$use_un;
//view array
		static::$v=array(
			static::SIG=>static::SIG."v.ixml",
			static::SIG."email_body"=>static::SIG."mv.ixml",
			static::SIG."check_mail"=>static::SIG."pv.ixml",
			static::SIG."success"=>static::SIG."sv.ixml",
			static::SIG."failure"=>static::SIG."fv.ixml"
		);
		static::$v = array_replace(static::$v,$custom_views);
//Now do translations.
		$en = array(
			static::SIG ."mesg_success"=>"You have successfully changed your email.",
			static::SIG ."mesg_checkem"=>"To set your email address, confirm the change via the email just sent to you.",
			static::SIG .'prompt_new_email'=>"New Email",
			static::SIG .'prompt_retype_new_email'=>"Retype New Email",
			static::SIG .'prompt_your_password'=>"Password",
			static::SIG .'button_set_email'=>"Set New Email",
			static::SIG .'errors_emails_unmatched'=>" Both emails must be the same.",
			static::SIG .'errors_email_badformat'=>" The email format is not recognised.",
			static::SIG .'errors_emails_empty'=>" The email must have a value.",
			static::SIG .'errors_password_bad'=>" The password is not recognised.",
			static::SIG .'note_email_change_title'=>"Email Change Request",
			static::SIG .'note_email_change_message'=>"It appears that you have chosen to change your e-mail to the one that you received this message on.",
			static::SIG .'note_email_change_action_link'=>"PLEASE CONTINUE HERE",
			static::SIG .'note_email_see_html_alt'=>"Please see the html alternative of this email."
		);
		$de = array(
			static::SIG ."mesg_success"=>"Sie haben Ihre E-Mail-Adresse erfolgreich geändert.",
			static::SIG ."mesg_checkem"=>"Um Ihre E-Mail-Adresse zu bestätigen, lesen Sie bitte die E-Mail, die Ihnen gerade zugeschickt wurde.",
			static::SIG .'prompt_new_email'=>"Neue E-Mail-Adresse",
			static::SIG .'prompt_retype_new_email'=>"Neue E-Mail-Adresse wiederholen.",
			static::SIG .'prompt_your_password'=>"Passwort",
			static::SIG .'button_set_email'=>"Neue E-Mail-Adresse festlegen",
			static::SIG .'errors_emails_unmatched'=>" Beide E-Mail-Adressen müssen übereinstimmen.",
			static::SIG .'errors_email_badformat'=>" Das E-Mail-Format kann nicht erkannt werden.",
			static::SIG .'errors_emails_empty'=>" Sie müssen Ihre E-Mail-Adresse eingeben.",
			static::SIG .'errors_password_bad'=>" Das passwort kann nicht erkannt werden.",
			static::SIG .'note_email_change_title'=>"E-Mail-Adresse ändern",
			static::SIG .'note_email_change_message'=>"Sie möchten die E-Mail-Adresse, an die diese E-Mail geschickt wurde, verwenden.",
			static::SIG .'note_email_change_action_link'=>"Bitte hier fortfahren",
			static::SIG .'note_email_see_html_alt'=>"Sollte die E-Mail nicht korrekt dargestellt werden, wechseln Sie bitte in das HTML-Format."
		);
		Dict::set($en,'en');
		Dict::set($de,'de');
	}

}

