<?php
mb_internal_encoding('UTF-8');
use Monolog\Logger;
use Monolog\Handler;
use Monolog\Formatter;
use Monolog\Processor;

/**
 * class 'Settings'
 */
class Settings extends Singleton {
	public static $log;		//PsrLogLoggerInterface instance
	public static $sphinx=null;
	public static $sql=null;
	public static $url=null; //current url
	public static $msg=array();	//array of application variables
	public static $usr=array();	//current user details
	public static $req=array();	//querystring for unnamed parameters.
	public static $qst=array();	//associational querystring - for named parameters.
	public static $pag=array();	//current page details (set by websites)..
	private static $sqls=array();
	public static $website=null;
	public static $domain=null;	//The domain to be used for emails being sent out.
	private static $sql_types=null;
	private static $log_stack = array();
	
	public static function sql_types() { 
	    if (is_null(static::$sql_types)) {
	        static::$sql_types = array();
	        $constants = get_defined_constants(true);
	        foreach ($constants['mysqli'] as $c => $n) if (preg_match('/^MYSQLI_TYPE_(.*)/', $c, $m)) static::$sql_types[$n] = $m[1];
	    }
	    return static::$sql_types;
	}

/**
 * '__construct'
 */

 	protected function __construct() {
 		if($_SERVER['QUERY_STRING']) {
			parse_str($_SERVER['QUERY_STRING'],self::$qst);
			self::$req=explode ('&',$_SERVER['QUERY_STRING']);
		}
		self::$url=strtok($_SERVER["REQUEST_URI"],'?');
		if ( !empty($_SERVER['PHP_AUTH_USER']) ) {
			self::$usr['RU']= $_SERVER['PHP_AUTH_USER'];
		} elseif ( isset($_SERVER['REDIRECT_REMOTE_USER']) ) {
			self::$usr['RU']= $_SERVER['REDIRECT_REMOTE_USER'];
		} elseif ( isset($_SERVER['REMOTE_USER']) ) {
			self::$usr['RU']= $_SERVER['REMOTE_USER'];
		}  else {
			self::$usr['RU']= 'unknown';
		}
		self::$usr['uid']=0;
		self::$usr['ID']=0;
		
		if(isset($_SERVER["RS_SQLCONFIG_FILE"])) { //the path to the my.cnf for this connection.
			self::$sql = mysqli_init();
			self::$sqls = parse_ini_file($_SERVER["RS_SQLCONFIG_FILE"]);
			mysqli_options(self::$sql,MYSQLI_READ_DEFAULT_FILE,$_SERVER["RS_SQLCONFIG_FILE"]);
			mysqli_real_connect(self::$sql,self::$sqls['host'],self::$sqls['user'],self::$sqls['password'],self::$sqls['database']);
		} else {
			self::$sqls['host']=getenv('RS_SQLHOST');
			self::$sqls['user']=getenv('RS_SQLUSER');
			self::$sqls['password']=getenv('RS_SQLUSERPW');
			self::$sqls['database']=getenv('RS_DATABASE');
			self::$sql = new mysqli(self::$sqls['host'],self::$sqls['user'],self::$sqls['password'],self::$sqls['database']);
		}
		if (self::$sql->connect_error) {
			static::$log->fatal('SQL Connection Error (' . self::$sql->connect_errno . ') ' . self::$sql->connect_error);
			die();
		}
		$srch=getenv('RS_SEARCH_HOST');
		if (!empty($srch)) {
	       self::$sphinx = new mysqli(getenv('RS_SEARCH_HOST'),NULL,NULL,NULL,9306);
		} else {
		   self::$sphinx = null;
		}
		self::$sql->set_charset("utf8");
		self::$website='http';
		if(!empty($_SERVER['HTTPS']) && $_SERVER["HTTPS"]=='on') { self::$website.='s'; }
		$host=$_SERVER["HTTP_HOST"]; //client used this to get here..
		self::$website.='://' . $host;
		if (!empty($_SERVER["HTTP_DOMAIN"])) {	//allow for domain to be passed over.
			self::$domain=$_SERVER["HTTP_DOMAIN"];
		} else {
			$domain_arr = explode('.',$host, 2);	//[xxx][yy.coo.bbb]
			if($domain_arr[0]=='www') { //[WWW.wibble.co.uk]/[WWW.domain]  [wibble-preview.redsnapper.net]
				self::$domain=$domain_arr[1];
			} else {
				self::$domain=$host;
			}
		}
	}
	
/**
 * 'setLogger' Set Logger. Should be done pretty much immediately after construct.
 
CREATE TABLE sio_log (
  id int(11) NOT NULL AUTO_INCREMENT,
  l_channel char(64) NOT NULL DEFAULT '',
  l_level char(8) NOT NULL DEFAULT '',
  l_message text NOT NULL,
  l_date char(32) NOT NULL DEFAULT '',
  ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 
 */
	public static function setLogger($log_i = NULL,$hndl_i = NULL) {
		if (is_null($log_i) && is_null(static::$log) ) {
  			static::$log = new NViewLogger('Log');
		} else {
  			static::$log = $log_i;
		}
		if (is_null($hndl_i)) {
			$log_sql = "insert low_priority into sio_log (l_channel,l_level,l_message,l_date) VALUES (?,?,?,?)";
			$log_stm = static::$sql->prepare($log_sql);
 			static::$log->pushHandler(new SQLHandler($log_stm,"ssss"));
		} else {
 			static::$log->pushHandler($hndl_i);
		}
		static::$log->pushProcessor(new Processor\PsrLogMessageProcessor());
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
				if ($rx = self::$sql->query("select id,username,email from sio_user where active='on' and username='". @self::$usr['RU'] ."'")) {
					while ($row = $rx->fetch_assoc()) {
						self::$usr['ID']=$row['id'];
						self::$usr['username']=$row['username'];
						self::$usr['email']=$row['email'];
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

/**
 * 'rows' number of rows in a result.
 */
	public static function rows($rs) {
		$result = -1;
		if($rs instanceof mysqli_result) {
			$result = $rs->num_rows;
		}
		return $result;
	}
	
/**
 * 'Settings::blank' returns what empty() should return
 */
	public static function blank($value) {
		return empty($value) && !is_numeric(@$value);
	}
	

/**
 * 'esc' mysql escape.
 */
	public static function esc(&$s) {
		if(!is_null(self::$sql)) {
			$s=self::$sql->real_escape_string($s);
		}
		return $s;
	}

	public static function table_exists($table) {
		$retval=false;
		if(!is_null(self::$sql)) {
			if ($rx = self::$sql->query("select count(*) as present from information_schema.columns where table_schema = '".self::$sqls['database']."' and table_name = '".$table."'")) {
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

	public static function field_exists($table,$fieldname) {
		$retval=false;
		if(!is_null(self::$sql)) {
			if ($rx = self::$sql->query("select count(*) as present from information_schema.columns where table_schema = '".self::$sqls['database']."' and table_name = '".$table."' and column_name = '".$fieldname."'")) {
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

	public static function sphinx_esc( $s ) {
		$retval="";
		if (mb_check_encoding($s)) {
			$from = array ( '\\', '(',')','|','-','!','@','~','"','&', '/', '^', '$', '=', "'", "\x00", "\n", "\r", "\x1a" );
			$to   = array ( '\\\\', '\\\(','\\\)','\\\|','\\\-','\\\!','\\\@','\\\~','\\\"', '\\\&', '\\\/', '\\\^', '\\\$', '\\\=', "\\'", "\\x00", "\\n", "\\r", "\\x1a" );
			$retval=str_replace( $from, $to, $s );
		}
		return $retval;
	}

/**
 * 'close'
 */
	public static function close() {
		self::$sql->close();
		if (!empty(self::$sphinx)) {
			self::$sphinx->close();
		}
	}
}
