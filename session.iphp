<?php
mb_internal_encoding('UTF-8');

/**
 * class 'Session'
 * This uses static functions, but is constructed at a specific time.
 * requires tables session and sessiondata
 */
class Session extends Singleton {

	private static $tom=null; //timeout minutes
	private static $session=null;
	private static $sqlsess=null;

/*
 * set timeout (in minutes)
*/
	public static function setto($tom_p=1440) {
		self::$tom = Settings::$sql->escape_string($tom_p);
		Settings::$sql->query("delete from sio_session where TIMESTAMPADD(MINUTE," . self::$tom . ",ts) < CURRENT_TIMESTAMP");
	}

/**
 * has (with no name = check for session) - otherwise, check for session variable
 */
	public static function has($nam=NULL) {
		$retval = false;
		if (!empty(self::$session)) {
			if (!empty($nam)) {
				$sqlname = Settings::$sql->escape_string($nam);
				$query= "select count(sid) as found from sio_sessiondata where sid='" . self::$sqlsess . "' and name='" . $sqlname . "'";
				if ($x = Settings::$sql->query($query)) {
					if (strcmp($x->fetch_row()[0],"1") === 0) {
						$retval=true;
					}
					$x->close();
				}
			} else {
				$retval=true;
			}
		}
		return $retval;
	}

	public static function get($nam=NULL) {
		$retval = false;
		if (!empty(self::$session) && !is_null($nam) ) {
			$sqlname = Settings::$sql->escape_string($nam);
			if ($rx = Settings::$sql->query("select value from sio_sessiondata where sid='" . self::$sqlsess . "' and name='" . $sqlname . "'")) {
				$retval = $rx->fetch_row()[0];
				$rx->close();
			}
		}
		return $retval;
	}

	public static function set($nam=NULL,$val=NULL) {
		$retval = false;
		if (!empty(self::$session) && !is_null($nam) ) {
			$sqlnam = Settings::$sql->escape_string($nam);
			if (is_null($nam)) {
				Settings::$sql->query("replace into sio_sessiondata (sid,name,value) values ('" . self::$sqlsess . "','" . $sqlnam . "',null)");
			} else {
				$sqlval = Settings::$sql->escape_string($val);
				Settings::$sql->query("replace into sio_sessiondata (sid,name,value) values ('" . self::$sqlsess . "','" . $sqlnam . "','" . $sqlval . "')");
			}
		}
		return $retval;
	}

	public static function del($nam=NULL) {
		if (!empty(self::$session)) {
			if(!is_null($nam)) {
				$sqlname = Settings::$sql->escape_string($nam);
				$query="delete from sio_sessiondata where sid='" . self::$sqlsess . "' and name='" . $sqlname . "'";
				Settings::$sql->query($query);
			} else {
				Settings::$sql->query("delete from sio_session where id='" . self::$sqlsess . "'");
				Settings::$sql->query("delete from sio_sessiondata where sid='" . self::$sqlsess . "'");
			}
		}
	}

/**
 * '__construct'
 * Set / manage the session cookie and it's correlating data-record.
 */
	protected function __construct() {
		if(!isset($_COOKIE["xsession"])) {
			if(!isset($_COOKIE["session"])) {
					$session_id = getenv("UNIQUE_ID");
					if (! $session_id) {
						$session_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
						  mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
						  mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
						  mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
						);
					}
					self::$session = md5($session_id);
			} else {
				self::$session = $_COOKIE["session"];
			}
			setcookie("xsession",self::$session,time()+8640000,'/'); // 8640000 = 100 days
		} else {
			self::$session = $_COOKIE["xsession"];
		}
		if (!empty(self::$session)) {
			self::$sqlsess = Settings::$sql->escape_string(self::$session);
			Settings::$sql->query("delete sio_sessiondata from sio_sessiondata left join sio_session on sid=id where id is null");
			if ($rx = Settings::$sql->query("select count(id) as scount from sio_session where id='" . self::$sqlsess . "'")) {
				if (strcmp($rx->fetch_row()[0],"0") === 0) {
					Settings::$sql->query("replace into sio_session set id='" . self::$sqlsess . "'");
				}
				$rx->close();
			}
		}
	}

}
