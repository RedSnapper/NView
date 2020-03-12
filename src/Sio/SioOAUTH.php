<?php
namespace RS\NView\Sio;

use RS\NView\Settings;

class SioOAUTH {

	public static function add($service, $serviceId, $userId, League\OAuth2\Client\Token\AccessToken  $token, $fields = array()) {

		$bindings = array();

		$columns = array("service", "id", "user", "token");
		$fieldParams = array_keys($fields);
		$binds = "ssis";
		$binds .= str_repeat("s", count($fieldParams));
		$bindings[] = $binds;
		$bindings[] = $service;
		$bindings[] = $serviceId;
		$bindings[] = $userId;
		$bindings[] = json_encode($token);
		foreach (array_values($fields) as $field) {
			$bindings[] = $field;
		}

		$params = array_merge($columns, $fieldParams);
		$count = count($params);
		$columns = implode(",", $params);
		$values = implode(",", array_fill(1, $count, "?"));

		$query = "replace into sio_oauth ($columns) values ($values)";

		$stmt = Settings::$sql->prepare($query);
		$length = count($bindings);
		if ($length > 0) {
			$a_params = array();
			for ($i = 0; $i < $length; $i++) {
				$a_params[] = &$bindings[$i];
			}
			// Need to pass as paramters to bind_param and needs to be by reference
			call_user_func_array(array($stmt, 'bind_param'), $a_params);
		}

		$stmt->execute();
		$stmt->close();
	}

	public static function get($service, $serviceId) {

		$sql = "SELECT user FROM sio_oauth WHERE service = ? AND id = ? ";

		$stmt = Settings::$sql->prepare($sql);
		$stmt->bind_param('ss', $service, $serviceId);

		$stmt->execute();
		$stmt->bind_result($userId);
		while ($stmt->fetch()) {
			return $userId;
		}
		$stmt->close();
		return false;

	}

	public static function getToken($service, $user) {

		$sql = "SELECT token FROM sio_oauth WHERE service = ? AND user = ? ";
		$stmt = Settings::$sql->prepare($sql);
		$stmt->bind_param('si', $service, $user);
		$stmt->execute();
		$stmt->bind_result($token);
		while ($stmt->fetch()) {
			return $token;
		}
		$stmt->close();
		return false;

	}

	public static function delete($service=null, $user=null) {
		if($service && $user){
			$sql = "delete FROM sio_oauth WHERE service = ? AND user = ? ";
			$stmt = Settings::$sql->prepare($sql);
			$stmt->bind_param('si', $service, $user);
			$stmt->execute();
			$stmt->close();
		}

	}

}
