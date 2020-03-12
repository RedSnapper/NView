<?php
namespace RS\NView\Sio;

use RS\NView\Dict;

class SioFacebook extends SioService {

	const SERVICE_NAME = "Facebook";
	const VIEW_TPL = "siofacebook.ixml";

	protected function getUserFields(\League\OAuth2\Client\Provider\ResourceOwnerInterface $user) {

		return ["firstname" => $user->getFirstName(), "lastname" => $user->getLastName(), "email" => $user->getEmail()];
	}

	protected function defaultTranslations() {
		$en = array(
			static::SERVICE_NAME . "_login" => "Login with Facebook",
			static::SERVICE_NAME . "_noemail" => "Can not sign in without email permission."
		);
		Dict::set($en, 'en');
	}

	protected function getLongLivedToken(League\OAuth2\Client\Token\AccessToken $token) {
		try {
			return $this->provider->getLongLivedAccessToken($token);
		} catch (Exception $e) {
			$this->logError("Failed to exchange the token: ' . $e->getMessage()");
			return $token;
		}
	}
}
