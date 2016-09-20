<?php

class SioCaptcha {
	use Form {
		commit as trait_commit;
	}
	public static $siteKey = null;
	public static $secret = null;

	public function __toString() {
		return "SioCaptcha";
	}

	const SIG = "siocaptcha_";

	public static function sig() {
		return static::SIG;
	}

	function __construct($debug = false) {
		$this->iniForm(0, @static::$v[static::SIG]);
		Dict::set(array("errors_captcha_missing-input-response" => "You must show us that you are a human."), "en");
		$this->table = "none";
		$this->setfld('captcha', '0');
	}

	public function func() {
		$this->valCaptcha('captcha');
	}

	public function validate() {
		$this->valCaptcha('captcha');
	}

	public function populate() {
		$this->view->set("//*[@data-sitekey]/@data-sitekey", static::$siteKey);
	}

	public function commit() {
		return true; //always do nothing in a commit. we are merely validating.
	}

	public function prefilter() {
		if (isset($_POST['g-recaptcha-response'])) {
			$this->fields['captcha'][] = $_POST['g-recaptcha-response'];
		}
	}

	public static function initialise($custom_views = array()) {
		static::$v = array(
			static::SIG => static::SIG . "v.ixml"
		);
		static::$v = array_replace(static::$v, $custom_views);
	}
	private function valCaptcha($name = 'captcha') {
		if (isset($this->fields[$name][0])) {
			$response = $this->fields[$name][0];
			$recaptcha = new \ReCaptcha\ReCaptcha(static::$secret);
			$resp = $recaptcha->verify($response);
			if (!$resp->isSuccess()) {
				$errors = $resp->getErrorCodes();
				foreach ($errors as $k) {
					$this->seterr($name, Dict::get("errors_captcha_$k"));
					$this->valid = false;
				}
			}
		} else {
			$this->seterr($name, Dict::get('errors_captcha_not_submitted'));
			$this->valid = false;
		}
	}
}