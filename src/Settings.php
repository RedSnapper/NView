<?php
use Monolog\Logger;
use Monolog\Processor;
use Monolog\Handler;
use Monolog\Formatter;

class Settings extends Config {

	public static $log;        //PsrLogLoggerInterface instance
	public static $sphinx = null;
	public static $sql = null;
	public static $url = null; //current url
	public static $msg = array();    //array of application variables
	public static $usr = array();    //current user details
	public static $req = array();    //querystring for unnamed parameters.
	public static $qst = array();    //associational querystring - for named parameters.
	public static $pag = array();    //current page details (set by websites)..
	private static $sqls = array();
	public static $website = null;
	public static $domain = null;    //The domain to be used for emails being sent out.
	private static $sql_types = null;
	private static $log_stack = array();
	private static $mysqli = null;
	private static $sp = null;

	public function __construct(Services $s) {
		parent::__construct($s);
		$this->legacy();
		$this->user();
		$this->uri();
	}

	public function legacy() {

		$s = $this->s;
		$server = $s->get('EnvServer');

		// This is to allow for backward compatibility to mysql.
		$s->addRule('MySqliConnector', [
			'constructParams' => [parse_ini_file($server->get("RS_SQLCONFIG_FILE"))],
			'shared'=> false
		]);

		$connector = $s->create('MySqliConnector');
		$s->addRule('MySqliConnection', [
			'constructParams' => [$connector->connect()],
			'shared' => true
		]);

		static::$sphinx = null;

		if ($server->has('RS_SEARCH_CONFIG_FILE')) {
			static::$sp = $s->get('SphinxConnection');
			static::$sphinx = static::$sp->getConnection();
		}

		static::$sqls = parse_ini_file($_SERVER["RS_SQLCONFIG_FILE"]);
		static::$log = $s->get('LoggerInterface');
		static::$mysqli = $s->get('MySqliConnection');
		static::$sql = static::$mysqli->getConnection();

	}

	private function user() {
		$s = $this->s;
		$server = $s->get('EnvServer');

		if ($server->sig("PHP_AUTH_USER")) {
			self::$usr['RU']= $server->get('PHP_AUTH_USER');
		} elseif ( $server->has("REDIRECT_REMOTE_USER") ) {
			self::$usr['RU']= $server->get('REDIRECT_REMOTE_USER');
		} elseif ( $server->has("REMOTE_USER") ) {
			self::$usr['RU']= $server->get('REMOTE_USER');
		}  else {
			self::$usr['RU']= 'unknown';
		}
		self::$usr['uid']=0;
		self::$usr['ID']=0;
	}

	private function uri() {
		$s = $this->s;
		$server = $s->get('EnvServer');
		$uri = $s->get('UriInterface');
		
		self::$url=$uri->getPath();
		self::$website=$uri->getSchemeAndHost();
		self::$domain=$server->get("HTTP_DOMAIN",$uri->getDomain());

		if($server->has('QUERY_STRING')) {
			parse_str($server->get('QUERY_STRING'),self::$qst);
			self::$req=explode ('&',$server->get('QUERY_STRING'));
		}

	}

	/**
	 * 'usr'  initialise session, and user id etc.
	 *        This requires a 'user' table.
	 */
	public static function usr($construct = true) {
		if($construct) {
			self::$usr = array();	//need to reset all values.
			if (Session::has('username')) {
				$name=Session::get('username');
				if (empty($name)) {
					Session::del();
					self::$usr['RU']='';
				} else {
					self::$usr['RU']=Session::get('username');
				}
			}
//Now store user data.
			if (self::table_exists("sio_user")) {
				if ($rx = self::$sql->query("select id,username,email,if(password is null,false,true) as has_password from sio_user where active='on' and username='". @self::$usr['RU'] ."'")) {
					while ($row = $rx->fetch_assoc()) {
						self::$usr['ID']=$row['id'];
						self::$usr['username']=$row['username'];
						self::$usr['email']=$row['email'];
						self::$usr['has_password']=$row['has_password'];
					}
					$rx->close();
				}
			}
//Now store user-profile (app specific) data.
			if (self::table_exists("sio_userprofile")) {
				if ($rx = self::$sql->query("select * from sio_userprofile where user='". @self::$usr['ID'] ."'")) {
					while ($row = $rx->fetch_assoc()) {
						foreach($row as $k => $v ) {
							self::$usr[$k]=$v;
						}
					}
					$rx->close();
				}
			}
		} else {
			Session::del('username');
			self::$usr['ID']=NULL;
			self::$usr = array_splice(self::$usr,1);
			array_shift(self::$usr);
		}
	}

	public static function setLogHandler($hndl_i = NULL) {
		if (is_null($hndl_i)) {
			$log_sql = "INSERT LOW_PRIORITY INTO sio_log (l_channel,l_level,l_message,l_date) VALUES (?,?,?,?)";
			$log_stm = static::$sql->prepare($log_sql);
			static::$log->pushHandler(new SQLHandler($log_stm, "ssss"));
		} else {
			static::$log->pushHandler($hndl_i);
		}
		static::$log->pushProcessor(new Processor\PsrLogMessageProcessor());
	}

	//legacy handover...
	public static function setLogger($log_i = NULL, $hndl_i = NULL) {
		if (!is_null($log_i)) {
			static::$log = $log_i;
		}
		static::setLogHandler($hndl_i);
	}

	public static function pushLog($name="Log",$level = Logger::DEBUG, $bubble = false, $formatter = NULL) {
		$logfile = fopen('php://temp', 'rw+');
		$log_handler = new Handler\StreamHandler($logfile,$level,$bubble);
		if (is_null($formatter)) {
			$log_handler->setFormatter(new Formatter\HtmlFormatter());
		} else {
			$log_handler->setFormatter($formatter);
		}
		static::$log_stack[] = $logfile;
		static::$log->pushHandler($log_handler);
		static::$log->pushName($name);
	}

	public static function popLog() {
		if (count(static::$log_stack) > 0)
			$logfile = array_pop(static::$log_stack);
		rewind($logfile);
		$result = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<!DOCTYPE div><div xmlns=\"http://www.w3.org/1999/xhtml\">" . stream_get_contents($logfile) . "</div>";
		$handler = static::$log->popHandler();
		$handler->close();
		static::$log->popName();
		return new NView($result);
	}

	public static function sql_types() {
		if (is_null(static::$sql_types)) {
			static::$sql_types = array();
			$constants = get_defined_constants(true);
			foreach ($constants['mysqli'] as $c => $n) if (preg_match('/^MYSQLI_TYPE_(.*)/', $c, $m)) static::$sql_types[$n] = $m[1];
		}
		return static::$sql_types;
	}

	public static function esc(&$s) {
		return static::$mysqli->esc($s);
	}

	public static function sphinx_esc(&$s) {
		return static::$sp->esc($s);
	}

	public static function close() {
		self::$mysqli->close();
		if (!empty(self::$sphinx)) {
			self::$sp->close();
		}
	}

	/**
	 * 'rows' number of rows in a result.
	 */
	public static function rows($rs) {
		$result = -1;
		if ($rs instanceof mysqli_result) {
			$result = $rs->num_rows;
		}
		return $result;
	}

	public static function field_exists($table, $fieldname) {
		$retval = false;
		if (!is_null(self::$sql)) {
			if ($rx = self::$sql->query("SELECT count(*) AS present FROM information_schema.columns WHERE table_schema = '" . self::$sqls['database'] . "' AND TABLE_NAME = '" . $table . "' AND COLUMN_NAME = '" . $fieldname . "'")) {
				while ($row = $rx->fetch_assoc()) {
					if ($row['present'] == 1) {
						$retval = true;
					}
				}
				$rx->close();
			}
		}
		return $retval;
	}

	public static function table_exists($table) {
		$retval = false;
		if (!is_null(self::$sql)) {
			if ($rx = self::$sql->query("SELECT count(*) AS present FROM information_schema.columns WHERE table_schema = '" . self::$sqls['database'] . "' AND TABLE_NAME = '" . $table . "'")) {
				while ($f = $rx->fetch_assoc()) {
					if ($f['present'] > 0) {
						$retval = true;
					}
				}
				$rx->close();
			}
		}
		return $retval;
	}

	/**
	 * decode a string parameter for url inclusion.
	 */
	public static function udecode($data, $pw = "Never use the default password!") {
		$b64s = str_replace(array('-', '_'), array('+', '/'), $data);
		$m4 = strlen($b64s) % 4;
		if ($m4 > 0) {
			$b64s .= substr('====', $m4);
		}
		return gzinflate(openssl_decrypt($b64s, "aes128", $pw, 0, "Use-owt-but-this"));
	}

	/**
	 * encode a string parameter for url inclusion.
	 */
	public static function uencode($data, $pw = "Never use the default password!") {
		return str_replace(array('+', '/', '='), array('-', '_', ''), openssl_encrypt(gzdeflate($data), "aes128", $pw, 0, "Use-owt-but-this"));
	}

}