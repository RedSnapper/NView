<?php
namespace RS\NView\Sio;

use \League\OAuth2\Client\Provider\ResourceOwnerInterface as Owner;

abstract class SioService {

	protected $provider;
	protected $successURL;
	protected $authScope;
	protected $v;
	protected $token;
	protected $debug = false;
	private $userCreateClosure;
	const SERVICE_NAME = null;

	function __construct(League\OAuth2\Client\Provider\AbstractProvider $provider, array $authScope = array(), NView $v = NULL) {
		$this->provider = $provider;
		$this->authScope = $authScope;
		$this->v = $v ? $v : $this->getNView();
		$this->defaultTranslations();
		$this->successURL = $_SERVER["SCRIPT_URI"];
	}

	abstract protected function getUserFields(Owner $owner);

	abstract protected function defaultTranslations();

	abstract protected function getLongLivedToken(League\OAuth2\Client\Token\AccessToken $token);

	protected function getNView() {
		return new NView(static::VIEW_TPL);
	}

	/**
	 * @param Closure setUserCreateClosure
	 */
	public function setUserCreateClosure(Closure $callback) {
		$this->userCreateClosure = $callback;
	}

	public function setDebug(bool $bool) {
		$this->debug = $bool;
	}

	public function getSignInView() {
		$authUrl = $this->provider->getAuthorizationUrl($this->authScope);
		$session = [
			'state' => $this->provider->getState(),
			'url' => urlencode($this->successURL),
			'service' => static::SERVICE_NAME
		];
		Session::set(static::SERVICE_NAME . "_oauth2state", serialize($session));
		$this->v->set("//*[@data-xp='url']/@href", $authUrl);
		return $this->v;
	}

	public function setSuccessURL($url) {
		$this->successURL = $url;
	}

	public function handleCallback() {
		if (isset(Settings::$qst['code'])) {
			$this->authenticate();
		}
	}

	public function getToken() {
		if (!$this->token) {
			$token = SioOAUTH::getToken(static::SERVICE_NAME, @Settings::$usr['ID']);
			$this->token = $token ? new League\OAuth2\Client\Token\AccessToken((array)json_decode($token)) : false;
		}

		return $this->token;
	}

	public function setTranslations(array $translations) {
		if (!is_null($translations)) {
			foreach ($translations as $lang => $trans_array) {
				Dict::set($trans_array, $lang);
			}
		}
	}

	public function disconnectAccount() {
		SioOAUTH::delete(static::SERVICE_NAME, Settings::$usr['ID']);
	}

	protected function redirect() {
		if ($this->successURL) {
			header("Location: $this->successURL");
		}
	}

	protected function getSignInToken() {

		$state = @Session::get(static::SERVICE_NAME . "_oauth2state");
		$oauthstate = null;
		$service = null;
		if ($state) {
			$state = unserialize($state);
			$oauthstate = $state['state'];
			$service = $state['service'];
			$this->successURL = urldecode($state['url']);
		}

		if (isset(Settings::$qst['code']) && $service == static::SERVICE_NAME && $oauthstate == Settings::$qst['state']) {
			try {
				$token = $this->provider->getAccessToken('authorization_code', [
					'code' => Settings::$qst['code']
				]);
				return $token;
			} catch (\Exception $e) {
				$this->logError($e->getMessage());
			}
		}
		return false;
	}

	protected function authenticate() {
		$this->token = $this->getSignInToken();
		if ($this->token) {
			$this->token = $this->getLongLivedToken($this->token);
			try {
				$owner = $this->provider->getResourceOwner($this->token);
				$this->signIn($owner);
			} catch (\Exception $e) {
				$this->logError($e->getMessage());
			}
		}
	}

	protected function signIn(Owner $owner) {

		$userID = @Settings::$usr['ID'];

		if(is_null($userID)){
			$userID = $this->getExistingUser($owner);
		}

		if (is_null($userID)) {
			$userID = $this->createUser($owner);
		}

		if ($userID) {
			SioOAUTH::add(static::SERVICE_NAME, $owner->getId(), $userID, $this->token, $this->getUserFields($owner));
			$user = Sio::getUserByID($userID);
			if ($user['active'] != 'on') {
				Sio::activateUserById($userID);
			}
			Sio::signinById($userID);
			$this->redirect();
		}
	}

	protected function createUser(Owner $owner) {
		$email = $owner->getEmail();
		if ($email) {
			$userID = SioReg::createuser(null, $email);
			if(!is_null($userID) && !is_null($this->userCreateClosure)){
				$closure = $this->userCreateClosure->bindTo($this,$this);
				$closure($userID,$this->getUserFields($owner));
			}
			return $userID;
		} else {
			$this->seterr("errors", Dict::get(static::SERVICE_NAME . '_noemail'));
		}
		return null;
	}

	protected function getExistingUser(Owner $owner) {
		$userID = SioOAUTH::get(static::SERVICE_NAME, $owner->getId());
		return $userID ? $userID : Sio::userid($owner->getEmail());
	}

	protected function seterr($ident = NULL, $value = NULL) {
		$this->v->set("//*[*[@data-msg='" . $ident . "']]/@class/child-gap()", " invalid");
		$this->v->set("//*[@data-msg='" . $ident . "']/child-gap()", "<span>$value</span>");
	}

	private function log($level, $message) {
		if (!is_null(Settings::$log)) {
			Settings::$log->pushName(static::SERVICE_NAME);
			Settings::$log->addRecord($level, $message);
			Settings::$log->popName();
		}
	}

	protected function logError($message) {
		$this->log(NViewLogger::ERROR, $message);
	}

}
