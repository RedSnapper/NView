<?php
mb_internal_encoding('UTF-8');

/**
 * class 'Session'
 * This uses static functions, but is constructed at a specific time.
 * requires tables session and sessiondata
 * cf. http://php.net/manual/en/class.sessionhandlerinterface.php
 * We are currently ignoring the following php.ini session.* configuration settings.
 * cf. http://php.net/manual/en/session.configuration.php
 */
class Session extends Singleton implements \SessionHandlerInterface {

private static $tom=null; 				//timeout minutes
private static $session=null;
private static $sqlsess=null;
private static $apache_cookie=null;		//This is the session cookie or '[new session]'- used for session-only variables.

/*
-SessionHandlerInterface::open — Initialize session
-SessionHandlerInterface::close — Close the session
-SessionHandlerInterface::destroy — Destroy a session
-SessionHandlerInterface::gc — Cleanup old sessions
-SessionHandlerInterface::read — Read session data
-SessionHandlerInterface::write — Write session data
*/

/*
 * SessionHandlerInterface::open — Initialize session
 * We currently do not use the save_path or the name here.
 * As this is not a static, we have already done the initialise in the constructor.
 */
public function open($save_path,$name) {
	return true;
}

/*
 * SessionHandlerInterface::close — Close the session
 * returns bool. We do nothing for this.
 */
public function close() {
	return true;
}

/*
 * SessionHandlerInterface::close — Close the session
 * returns bool.
 */
public function destroy($session_id) {
	$sess = static::$sqlsess;
	Settings::$sql->query("delete from sio_session where id='$sess'");
	Settings::$sql->query("delete from sio_sessiondata where sid='$sess'");
	return true;
}

/*
 * SessionHandlerInterface::gc — Garbage collection (remove stale sessions)
 * returns bool. $maxlifetime is measured in seconds.
 */
public function gc( $maxlifetime ) {
	Settings::$sql->query("delete from sio_session where TIMESTAMPADD(SECOND," . $maxlifetime . ",ts) < CURRENT_TIMESTAMP");
	return true;
}

/*
 * SessionHandlerInterface::write —
 * write session data called by session_write_close() so if you are using _SESSION use it, but before closing mysql.
 * returns string (serialised assoc. array). we ignore session_id.
 */
public function read( $session_id ) {
	$retval=NULL;
	$sess = static::$sqlsess;
	$qry =  "select value from sio_sessiondata where sid='$sess' and name='__SESSION'";
	if ($rx = Settings::$sql->query($qry)) {
		$retval = $rx->fetch_row()[0];
		$rx->close();
	}
	return true;
}

/*
 * SessionHandlerInterface::write —
 * write session data called by session_write_close() so if you are using _SESSION use it, but before closing mysql.
 * returns bool. we ignore session_id. the data is stored only on change.
 */
public function write( $session_id , $data ) {
	Settings::esc($data);
	Settings::$sql->query("replace into sio_sessiondata (sid,name,value) values ('" . static::$sqlsess . "','__SESSION',{$data})");
	return true;
}

/*
 * set timeout (in minutes)
 * this is traditionally set using a php.ini value session.gc_lifetime
 */
	public static function setto($tom_p=1440) {
		static::$tom = Settings::$sql->escape_string($tom_p);
		Settings::$sql->query("delete from sio_session where TIMESTAMPADD(MINUTE," . static::$tom . ",ts) < CURRENT_TIMESTAMP");
	}

/**
 * has (with no name = check for session) - otherwise, check for session variable
 */
	public static function has($nam=NULL) {
		$retval = false;
		if (!empty(static::$session)) {
			if (!empty($nam)) {
				$sqlname = Settings::$sql->escape_string($nam);
				$query= "select count(sid) as found from sio_sessiondata where sid='" . static::$sqlsess . "' and name='" . $sqlname . "' and (session is NULL or session='".static::$apache_cookie."' or session='_NEW')";
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
		if (!empty(static::$session)) {
			if(!is_null($nam)) {
				$sqlname = Settings::$sql->escape_string($nam);
				if ($rx = Settings::$sql->query("select value from sio_sessiondata where sid='" . static::$sqlsess . "' and name='" . $sqlname . "' and (session is NULL or session='".static::$apache_cookie."' or session='_NEW')")) {
					$retval = $rx->fetch_row()[0];
					$rx->close();
				}
			} else {
				$retval = static::$session;
			}
		}
		return $retval;
	}

	public static function set($nam=NULL,$val=NULL,$session_only=false) {
		$retval = false;
		if (!empty(static::$session) && !is_null($nam) ) {
			$sqlnam = Settings::$sql->escape_string($nam);
			$sonly = $session_only ? "'".static::$apache_cookie."'" : "NULL";
			$value = is_null($val) ? "NULL" : "'".Settings::$sql->escape_string($val)."'";
			Settings::$sql->query("replace into sio_sessiondata (sid,name,value,session) values ('" . static::$sqlsess . "','{$sqlnam}',{$value},{$sonly})");
		}
		return $retval;
	}

	public static function del($nam=NULL) {
		if (!empty(static::$session)) {
			if(!is_null($nam)) {
				$sqlname = Settings::$sql->escape_string($nam);
				$query="delete from sio_sessiondata where sid='" . static::$sqlsess . "' and name='" . $sqlname . "'";
				Settings::$sql->query($query);
			} else {
				Settings::$sql->query("delete from sio_session where id='" . static::$sqlsess . "'");
				Settings::$sql->query("delete from sio_sessiondata where sid='" . static::$sqlsess . "'");
			}
		}
	}

/**
 * 'fresh'
 * Returns a boolean. Tests to see if the current session is a new session, or a recovered session..
 */
	public static function fresh() {
		$retval = false;
		if( (!isset($_COOKIE["session"])) || (static::$session === $_COOKIE["session"])) {
			$retval = true;
		}
		return $retval;
	}

/**
 * '__construct'
 * Set / manage the session cookie and it's correlating data-record.
 */
	protected function __construct() {
		$this->start(false);
	}

	public static function start($override = true){
		if(isset($_COOKIE["session"])) {
			static::$apache_cookie = Settings::$sql->escape_string($_COOKIE["session"]);
		} else {
			static::$apache_cookie = "_NEW";
		}
		if(!isset($_COOKIE["xsession"]) || $override) {
			if(!isset($_COOKIE["session"]) || $override) {
				$session_id = getenv("UNIQUE_ID");
				if (! $session_id) {
					$session_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
						mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
						mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
						mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
					);
				}
				static::$session = md5($session_id);
			} else {
				static::$session = $_COOKIE["session"];
			}
			if (empty($_SERVER['HTTPS'])) {
				setcookie("xsession",static::$session,time()+8640000,'/'); // 8640000 = 100 days
			} else {
				//possibly don't need this.
				setcookie("xsession",static::$session,time()+8640000,'/','',true); // 8640000 = 100 days
			}
		} else {
			static::$session = $_COOKIE["xsession"];
		}
		if (!empty(static::$session)) {
			static::$sqlsess = Settings::$sql->escape_string(static::$session);
			Settings::$sql->query("delete sio_sessiondata from sio_sessiondata left join sio_session on sid=id where id is null");
			Settings::$sql->query("replace into sio_session set id='" . static::$sqlsess . "'");
		}
		static::tidy_session();
	}

	/**
	 * Returns the time to live in seconds for the current session
	 * @param int $seconds
	 * @return int
	 */
	public static function ttl(int $seconds=144000):int{

		$session = @$_COOKIE["xsession"];
		$ttl = 0;

		if(!is_null($session)) {
			$statement = Settings::$sql->prepare("select TIMESTAMPDIFF(SECOND,NOW(),(ts + INTERVAL ? SECOND)) from sio_session where id=?");
			$statement->bind_param("is",$seconds,$session);
			$statement->execute();
			$statement->bind_result($ttl);
			$statement->fetch();
		}

		return $ttl;
	}

	/**
	 * 'set cookie as found.'
 	*/
	private static function tidy_session() {
		if((static::$apache_cookie === "_NEW") && isset($_COOKIE["session"])) {
			static::$apache_cookie = Settings::$sql->escape_string($_COOKIE["session"]);
			Settings::$sql->query("update sio_sessiondata set session='".static::$apache_cookie."' where sid='".static::$sqlsess."' and session='_NEW'");
		}
	}





}
