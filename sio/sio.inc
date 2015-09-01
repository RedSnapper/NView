<?php
mb_internal_encoding('UTF-8');
class SIO {
	const SIG = "sio_";
	private static $v=array();
	private static $use_un=true;
	public static $utility = NULL;
	public static $presets = array(); //only good for email/username presets.

	public static function run($key=NULL) {
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
					if (SIOSetEmail::conforms($siof)) {
						$formlet=SIOSetEmail::pushit($siof); //sig.pushit
						$stt=0;
					} else {
						$stt=1;
					}
				}
				if ($stt==2 ) {
					if (SioSetPW::inScope()) {  //doing a set-pw post.
						$stt=0; $sio=new SioSetPW($key); $formlet=$sio->form(false);
						if ($sio->success()) {
							$formlet=$sio->pushit();
						}
					} elseif (SIOSetEmail::inScope()) {  //doing a set-pw post.
						$stt=0; $sio=new SIOSetEmail($key); $formlet=$sio->form(false);
						if ($sio->success()) {
							$formlet=SIOSetEmail::pushit();
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
						$stt=0; $sio=new SioResetPW($siof);
						$formlet=$sio->form(false);
						if ($sio->success()) { //else this is a get/failed post.
							$formlet=SioResetPW::pushit();
						} 
					} else {
						$stt=1;
					}
				} else {
					if (SioReg::inScope() || isset(Settings::$qst[SioReg::SIG]) ) {
						$stt=0; $sio=new SioReg($key); $formlet=$sio->form(false);
						if ($sio->success()) {
							$formlet=$sio->pushit();
						}
					} elseif (SioForgot::inScope() || isset(Settings::$qst[SioForgot::SIG]) ) {
						$stt=0; $sio=new SioForgot($key); $formlet=$sio->form(false);
						if ($sio->success()) {
							$formlet=$sio->commitv();
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
						$sio=new SioSignOut($key); $formlet = $sio->form(false);
					}
				} break;
				case 2: {
					$sso=new SioSignOut($key); $formlet=$sso->form(false);
					if ($sso->success()) {
						$sio=new SioSignIn($key); $formlet=$sio->form(false);
					}
				} break;
			}
			$v->set("//*[@data-xp='sio']",$formlet);
			return $v;
		}
	}

//This must be run to overload views and translations.
	public static function initialise($use_un=true,$custom_views=array(),$translations=NULL,$presets=array()) {
		static::$use_un=$use_un;
		static::$presets=$presets; //prefill username/email if required
		static::$v=array(
			static::SIG => static::SIG."v.ixml"
		);
		SioSignIn::initialise($use_un,$custom_views);
		SioSignOut::initialise($use_un,$custom_views);
		SioReg::initialise($use_un,$custom_views);
		SioForgot::initialise($use_un,$custom_views);
		SioSetEmail::initialise($use_un,$custom_views);
		SioSetPW::initialise($use_un,$custom_views);
		SioResetPW::initialise($use_un,$custom_views);
		//now override any translations
		static::$v = array_replace(static::$v,$custom_views);
		static::$utility = new SioUtility();
		if(!is_null($translations)) {
			foreach ($translations as $lang => $trans_array) {
				Dict::set($trans_array,$lang);
			}
		}
	}
	
	public static function signin($username = null,$email=null,$override=false) {
		if($override || !Session::has('username')) {
			if ($username && $email) {
				Session::set('username',$username);
				Session::set('email',$email);
				Settings::usr();
				$this->signin_fns();
			}
		}
	}

	public static function signout(){
		Settings::$usr['ID']=NULL;
		Settings::usr(false);
		Session::del();
	}
}
