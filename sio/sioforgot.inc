<?php
mb_internal_encoding('UTF-8');

//This is the 'sending end' of the paired sioresetpw_ class
class SioForgot {
	use Form;
//the following munge must be the same as the one found in sioresetpw_
	private static $munge="sha2(concat(ifnull(username,id),'-',ifnull(password,id),'-',ifnull(email,id),'-',ifnull(ts,id)),256)";
	const SIG = "sioforgot_";
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
		$this->setfld('username');
		$this->setfld('email');
	}

/**
 * 'commit-view'
 */
	public function commitv() {
		$cv=new NView(@static::$v[static::SIG."success"]);
		return $cv;
	}

/**
 * 'validate'
 * fn fulfilling abstract requirement of trait 'Form'.
 * validate all fields in this->fields.
 * errors are placed into the this->view.
 */
	protected function validate() {
		$this->valEmail('email',Dict::get(static::SIG .'errors_bad_email'));
	}

/**
 * 'commit'
 * fn OVERLOADING trait 'Form'.
 */
	protected function commit() {
		$this->show=false;
		$em=$this->fields['email'][0];
		$qry="select username,".self::$munge." as munge from " . $this->table . " where active='on' and email='" . $em . "'";
		if ($rx = Settings::$sql->query($qry)) {
			while ($f = $rx->fetch_assoc()) {
				$mail_v=new NView(@static::$v[static::SIG."mailbody"]);
				$mail = new PHPMailer();
				$mail->isSendmail();
				$mail->CharSet='utf-8';
				$mail->Encoding='base64';
				$mail->setFrom('auto@redsnapper.net', Dict::get(static::SIG .'mail_subject') );
				$mail->addBCC('auto@redsnapper.net', 'Auto'); //bcc
				$mail->Subject = Dict::get(static::SIG .'mail_subject');
				$mail->isHTML(true);
				$url=$_SERVER["SCRIPT_URI"];
				if (strpos($url, '?') !== false) {
					$url .= '&amp;siof=' . $f['munge'];
				} else {
					$url .= '?siof=' . $f['munge'];
				}
				if (static::$use_un) {
					$mail->addAddress($em,$f['username']);
					$mail_v->set("//*[@data-xp='un']/child-gap()",$f['username']);
				} else {
					$mail->addAddress($em,$em);
					$mail_v->set("//*[@data-xp='un']");
				}
				$mail_v->set("//*[@data-xp='ha']/@href",$url);
				$mail->Body = $mail_v->show(false);
				$mail->AltBody= Dict::get(static::SIG .'mail_see_alt');
				$mail->send();
			}
			$rx->close();
		}
		return true;
	}

	public static function initialise($use_un=true,$custom_views=array()) {
		static::$use_un=$use_un;
//views array
		static::$v=array(
			static::SIG=>static::SIG."v.ixml",
			static::SIG."mailbody"=>static::SIG."mv.ixml",
			static::SIG."success"=>static::SIG."sv.ixml"
		);
		static::$v = array_replace(static::$v,$custom_views);

//translations
		$en = array(
			static::SIG .'prompt_emailaddress' => "Email",
			static::SIG .'button_resetpassword'=>"Reset Password",
			static::SIG .'errors_bad_email'=>"The email address appears to be wrong",
			static::SIG .'commit_msg'=>"You should shortly receive an email with instructions for resetting your password.",
			static::SIG .'mail_head'=>"Password Reset Request",
			static::SIG .'mail_body'=>"It appears that you have posted a password reset request.",
			static::SIG .'mail_userhint'=>"Your username is:",
			static::SIG .'mail_url_text'=>"PLEASE CONTINUE HERE",
			static::SIG .'mail_see_alt'=>"Please see the html alternative of this email",
			static::SIG .'mail_subject'=>'Password Reset'
		);
		$de = array(
			static::SIG .'prompt_emailaddress'=> "E-Mail-Adresse",
			static::SIG .'button_resetpassword'=> "Passwort zurücksetzen",
			static::SIG .'errors_bad_email'=> "Diese E-Mail-Adresse scheint falsch zu sein.",
			static::SIG .'commit_msg'=> "Sie erhalten in Kürze eine E-Mail mit der Anleitung, wie Sie Ihr Passwort zurücksetzen können.",
			static::SIG .'mail_head'=> "Zurücksetzen des Passwortes",
			static::SIG .'mail_body'=> "Sie möchten Ihr Passwort zurücksetzen.",
			static::SIG .'mail_userhint'=> "Ihr Benutzername ist:",
			static::SIG .'mail_url_text'=> "Bitte hier fortfahren",
			static::SIG .'mail_see_alt'=> "Sollte die E-Mail nicht korrekt dargestellt werden, wechseln Sie bitte in das HTML-Format.",
			static::SIG .'mail_subject'=> "Passwort zurücksetzen"
		);
		Dict::set($en,'en');
		Dict::set($de,'de');
	}

}
