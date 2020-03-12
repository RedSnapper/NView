<?php
namespace RS\NView\Sio;

use RS\NView\Dict;

class SioGoogle extends SioService {

	const SERVICE_NAME = "Google";
	const VIEW_TPL = "siogoogle.ixml";

	protected function getUserFields(\League\OAuth2\Client\Provider\ResourceOwnerInterface $user) {
		return ["firstname" => $user->getFirstName(), "lastname" => $user->getLastName(), "email" => $user->getEmail()];
	}

	protected function defaultTranslations() {
		$en = array(
			static::SERVICE_NAME . "_login" => "Login with Google",
			static::SERVICE_NAME . "_noemail" => "Can not sign in without email permission."
		);
		Dict::set($en, 'en');
	}

	protected function getLongLivedToken(League\OAuth2\Client\Token\AccessToken $token) {
		return $token;
	}

}
