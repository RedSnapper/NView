<?php
mb_internal_encoding('UTF-8');
class Sio {
	const SIG = "sio_";
	private static $v=array();
	private static $cb=array();
	private static $use_un=true;
	public static $useReCaptcha = false;
	public static $utility = null;
	public static $presets = array(); //only good for email/username presets.

	public static function run($key = null, $debug = false) {
		$v = new NView(@static::$v[static::SIG]);
		$formlet = null;
		$stt = 0;    //stt: 0=other, 1=sign-in, 2=sign-out.
		if (Session::has('username')) { //signed in.
			$stt = 2; //default = sign-out.
			if (!empty(Settings::$qst['siof'])) {
				$siof = Settings::$qst['siof'];
				if (SioSetEmail::conforms($siof)) {
					$formlet = SioSetEmail::pushit($siof); //sig.pushit
					$stt = 0;
				} else {
					$stt = 1;
				}
			}
			if ($stt == 2) {
				if (SioSetPW::inScope()) {  //doing a set-pw post.
					$stt = 0;
					$Sio = new SioSetPW($key);
					$formlet = $Sio->form(false);
					if ($Sio->success()) {
						$formlet = $Sio->pushit();
					}
				} elseif (SioSetEmail::inScope()) {  //doing a set-pw post.
					$stt = 0;
					//if (static::$useReCaptcha) {
					//	$SioRe = new SioCaptcha();
					//	$Sio = new SioSetEmail($key);
					//	$Sio::formlets([$SioRe, $Sio], false);
					//	if ($Sio->success() && $SioRe->success()) {
					//		$formlet = SioSetEmail::pushit();
					//	} else {
					//		$formlet = $Sio->reveal();
					//		$cap = $SioRe->reveal();
					//		$formlet->set("//*[@data-xp='siso__captcha']", $cap);
					//	}
					//} else {
						$Sio = new SioSetEmail($key);
						$formlet = $Sio->form(false);
						if ($Sio->success()) {
							$formlet = SioSetEmail::pushit();
						}
					//	$formlet->set("//*[@data-xp='siso__captcha']");
					//}
				}
			}
		} else { //not-signed in
			$stt = 1; //default = sign-in.
			if (!empty(Settings::$qst['siof'])) {
				$siof = Settings::$qst['siof'];
				if (SioReg::conforms($siof)) {
					$stt = 0;
					$formlet = SioReg::pushit($siof);
				} elseif (SioResetPW::conforms($siof)) {
					$stt = 0;
					$Sio = new SioResetPW($siof);
					$formlet = $Sio->form(false);
					if ($Sio->success()) { //else this is a get/failed post.
						$formlet = SioResetPW::pushit();
					}
				} else {
					$stt = 1;
				}
			} else {
				if (SioReg::inScope() || isset(Settings::$qst[SioReg::SIG])) {
					$stt = 0;
					if (static::$useReCaptcha) {
						$SioRe = new SioCaptcha();
						$Sio = new SioReg($key);
						$Sio::formlets([$SioRe, $Sio], false);
						if ($Sio->success() && $SioRe->success()) {
							$formlet = $Sio->pushit();
						} else {
							$formlet = $Sio->reveal();
							$cap = $SioRe->reveal();
							$formlet->set("//*[@data-xp='siso__captcha']", $cap);
						}
					} else {
						$Sio = new SioReg($key);
						$formlet = $Sio->form(false);
						if ($Sio->success()) {
							$formlet = $Sio->pushit();
						}
						$formlet->set("//*[@data-xp='siso__captcha']");
					}
				} elseif (SioForgot::inScope() || isset(Settings::$qst[SioForgot::SIG])) {
					$stt = 0;
					if (static::$useReCaptcha) {
						$SioRe = new SioForgot();
						$Sio = new SioReg($key);
						$Sio::formlets([$SioRe, $Sio], false);
						if ($Sio->success() && $SioRe->success()) {
							$formlet = $Sio->pushit();
						} else {
							$formlet = $Sio->reveal();
							$cap = $SioRe->reveal();
							$formlet->set("//*[@data-xp='siso__captcha']", $cap);
						}
					} else {
						$Sio = new SioForgot($key);
						$formlet = $Sio->form(false);
						if ($Sio->success()) {
							$formlet = $Sio->commitv();
						}
						$formlet->set("//*[@data-xp='siso__captcha']");
					}
				}
			}
		}
		switch ($stt) {
			case 0:
			break;
			case 1: {
				$ssi = new SioSignIn($key);
				$formlet = $ssi->form(false);
				//need to do 'waiting for validation' line here.
				if ($ssi->success()) {
					$Sio = new SioSignOut($key);
					$formlet = $Sio->form(false);
				}
			}
			break;
			case 2: {
				$sso = new SioSignOut($key);
				$formlet = $sso->form(false);
				if ($sso->success()) {
					$Sio = new SioSignIn($key);
					$formlet = $Sio->form(false);
				}
			}
			break;
		}
		$v->set("//*[@data-xp='sio']", $formlet);
		return $v;
	}

//This must be run to overload views and translations.
	public static function initialise($use_un=true,$custom_views=array(),$translations=NULL,$presets=array(),$cb_fns=array()) {
		static::$use_un=$use_un;
		static::$useReCaptcha = false;
		static::$presets=$presets; //prefill username/email if required
		static::$v=array(
			static::SIG => static::SIG."v.ixml"
		);
		static::$v 	= array_replace(static::$v,$custom_views);
		static::$cb	= array_replace(static::$cb,$cb_fns);
		SioSignIn::initialise($use_un,static::$v);
		SioSignOut::initialise($use_un,static::$v);
		SioReg::initialise($use_un,static::$v);
		SioForgot::initialise($use_un,static::$v);
		SioSetEmail::initialise($use_un,static::$v);
		SioSetPW::initialise($use_un,static::$v);
		SioResetPW::initialise($use_un,static::$v);
		static::$utility	= new SioUtility();
		//now override any translations
		if(!is_null($translations)) {
			foreach ($translations as $lang => $trans_array) {
				Dict::set($trans_array,$lang);
			}
		}
	}

	public static function setRecaptcha(string $siteKey, string $secret) {
		SioCaptcha::$siteKey = $siteKey;
		SioCaptcha::$secret = $secret;
		self::$useReCaptcha = true;
	}

	public static function userid($identifier=null,$pending=true) {
		$retval = null;
		if($identifier)  {
			Settings::esc($identifier);
			if(static::$use_un) {
				$qry="select id from sio_user where username='$identifier' and active !=''";
			} else {
				if ( (bool) $pending) {
					$qry="select id from sio_user where (email='$identifier' and active='on') or (emailp='$identifier' and active='xx')";
				} else {
					$qry="select id from sio_user where email='$identifier' and active !=''";
				}
			}
			if ($rx = Settings::$sql->query($qry)) {
				while ($row = $rx->fetch_row()) {
					$retval = $row[0];
				}
			}
		}
		return $retval;
	}

	public static function getUserByID($id){
		$retval = false;
		if($id){
			Settings::esc($id);
			$qry="select email,active,username from sio_user where id=$id";
			if ($rs = Settings::$sql->query($qry)) {
				while ($rf = $rs->fetch_assoc()) {
					$retval=$rf;
				}
				$rs->close();
			}
		}
		return $retval;
	}

	public static function signin($username = null,$email=null,$override=false) {
		if($override || !Session::has('username')) {
			if ($username && $email) {
				Session::set('username',$username);
				Session::set('email',$email);
				Settings::usr();
				static::callback(array("user"=>Settings::$usr));
			}
		}
	}

	public static function signinById($id,$override=false) {
		 Settings::esc($id);
		$query= "select email,username from sio_user where active='on' and id= $id";
		if ($rs = Settings::$sql->query($query)) {
			while ($rf = $rs->fetch_assoc()) {
				$email = $rf['email'];
				$username = $rf['username'];
				self::signin($username,$email,$override);
			}
			$rs->close();
		}
	}
	public static function activateUserById($id) {
		if($id){
			$query = "update sio_user set active='on' where id=$id";
			Settings::$sql->query($query);
		}
	}

	public static function signout(){
		static::callback(array("user"=>Settings::$usr));
		Settings::$usr['ID']=NULL;
		Settings::usr(false);
		Session::del();
		Session::start(); // Start a new session
	}

	public static function callback($args = array()) {
		$caller = debug_backtrace()[1];
		$cb_sig = "{$caller['class']}::{$caller['function']}";  //eg 'SIO::signout'
		$cb = @static::$cb[$cb_sig];
		$args = array_replace($caller['args'],$args);
		if (!is_null($cb)) {
			Settings::$log->pushName("Sio");
			$cb($args);
			Settings::$log->popName();
		}
	}
}
