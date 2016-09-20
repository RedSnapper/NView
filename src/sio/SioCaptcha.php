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

	public static function sig() {
		return "botFilter";
	}

	function __construct($debug = false) {
		Dict::set(array("errors_captcha_missing-input-response" => "You must show us that you are a human."), "en");
		$this->table = "none";
		$this->view = new NView("siocaptcha.ixml");
		$this->iniForm('', null, false, 'id', $debug);
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