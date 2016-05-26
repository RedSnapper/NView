<?php
mb_internal_encoding('UTF-8');
class Sio {
	const SIG = "sio_";
	private static $v=array();
	private static $cb=array();
	private static $use_un=true;
	public static $utility = NULL;
	public static $presets = array(); //only good for email/username presets.

	public static function run($key=NULL,$debug=false) {
		$v = new NView(@static::$v[static::SIG]);
		$err = $v->messages();
		if (isset($err) && !empty($err)) {
			print $err;
		} else {
        $formlet=null;
			$stt=0;		//stt: 0=other, 1=sign-in, 2=sign-out.
			if (Session::has('username')) { //signed in.
				$stt=2; //default = sign-out.
				if(!empty(Settings::$qst['siof'])) {
					$siof=Settings::$qst['siof'];
					if (SioSetEmail::conforms($siof)) {
						$formlet=SioSetEmail::pushit($siof); //sig.pushit
						$stt=0;
					} else {
						$stt=1;
					}
				}
				if ($stt==2 ) {
					if (SioSetPW::inScope()) {  //doing a set-pw post.
						$stt=0; $Sio=new SioSetPW($key); $formlet=$Sio->form(false);
						if ($Sio->success()) {
							$formlet=$Sio->pushit();
						}
					} elseif (SioSetEmail::inScope()) {  //doing a set-pw post.
						$stt=0; $Sio=new SioSetEmail($key); $formlet=$Sio->form(false);
						if ($Sio->success()) {
							$formlet=SioSetEmail::pushit();
						}
					}
				}
			} else { //not-signed in
				$stt=1; //default = sign-in.
				if(!empty(Settings::$qst['siof'])) {
					$siof=Settings::$qst['siof'];
					if (SioReg::conforms($siof)) {
						$stt=0;
						$formlet=SioReg::pushit($siof);
					} elseif (SioResetPW::conforms($siof)) {
						$stt=0; $Sio=new SioResetPW($siof);
						$formlet=$Sio->form(false);
						if ($Sio->success()) { //else this is a get/failed post.
							$formlet=SioResetPW::pushit();
						}
					} else {
						$stt=1;
					}
				} else {
					if (SioReg::inScope() || isset(Settings::$qst[SioReg::SIG]) ) {
						$stt=0; $Sio=new SioReg($key); $formlet=$Sio->form(false);
						if ($Sio->success()) {
							$formlet=$Sio->pushit();
						}
					} elseif (SioForgot::inScope() || isset(Settings::$qst[SioForgot::SIG]) ) {
						$stt=0; $Sio=new SioForgot($key); $formlet=$Sio->form(false);
						if ($Sio->success()) {
							$formlet=$Sio->commitv();
						}
					}
				}
			}
			switch ($stt) {
				case 0: break;
				case 1: {
					$ssi=new SioSignIn($key); $formlet=$ssi->form(false);
					//need to do 'waiting for validation' line here.
					if ($ssi->success()) {
						$Sio=new SioSignOut($key); $formlet = $Sio->form(false);
					}
				} break;
				case 2: {
					$sso=new SioSignOut($key); $formlet=$sso->form(false);
					if ($sso->success()) {
						$Sio=new SioSignIn($key); $formlet=$Sio->form(false);
					}
				} break;
			}
			$v->set("//*[@data-xp='sio']",$formlet);
			return $v;
		}
	}

//This must be run to overload views and translations.
	public static function initialise($use_un=true,$custom_views=array(),$translations=NULL,$presets=array(),$cb_fns=array()) {
		static::$use_un=$use_un;
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

	public static function userid($identifier=null,$pending=true) {
		$retval = null;
		if($identifier)  {
			Settings::esc($identifier);
			if(static::$use_un) {
				$qry="select id from sio_user where username='$identifier' and active !=''";
			} else {
				if ( (bool) $pending) {
					$qry="select id from sio_user where (email='$identifier' and active='on') or (emailp'=$identifier' and active='xx')";
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

	public static function signout(){
		static::callback(array("user"=>Settings::$usr));
		Settings::$usr['ID']=NULL;
		Settings::usr(false);
		Session::del();
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
