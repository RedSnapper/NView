<?php
mb_internal_encoding('UTF-8');

/**
 * class 'Settings'
 */
class Settings extends Singleton {

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
			die('SQL Connection Error (' . self::$sql->connect_errno . ') ' . self::$sql->connect_error);
		}
		$srch=getenv('RS_SEARCH_HOST');
		if (!empty($srch)) {
	       self::$sphinx = new mysqli(getenv('RS_SEARCH_HOST'),NULL,NULL,NULL,9306);
		} else {
		   self::$sphinx = null;
		}
		self::$sql->set_charset("utf8");
		if (!self::table_exists("sio_session")) {
            self::$sql->query("CREATE TABLE sio_session(id varchar(255) NOT NULL,ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY ts (ts)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        }
        //session field is the normal session cookie, not the xsession value. sid is the xsession key.
		if (!self::table_exists("sio_sessiondata")) {
			self::$sql->query("CREATE TABLE sio_sessiondata(sid varchar(200) NOT NULL,name varchar(128) NOT NULL,value longtext NOT NULL,session varchar(200) DEFAULT NULL,ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(sid,name)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		}
		if (!self::table_exists("sio_user")) {
		    self::$sql->query("CREATE TABLE sio_user (id int(11) NOT NULL AUTO_INCREMENT,username varchar(68) NOT NULL DEFAULT '',password varchar(250) DEFAULT NULL, email text, emailp text,active char(2) DEFAULT 'on',ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,  PRIMARY KEY (id),  UNIQUE KEY username (username),  UNIQUE KEY ausername (username,active)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		}
		if (!self::table_exists("sio_userprofile")) {
		    self::$sql->query("CREATE TABLE sio_userprofile (user int(11) NOT NULL AUTO_INCREMENT,ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (user)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		}
		if (!self::table_exists("sio_activity")) {
		    self::$sql->query("CREATE TABLE sio_activity(id int(11) NOT NULL AUTO_INCREMENT, name varchar(120) NOT NULL DEFAULT 'unknown', active char(2) DEFAULT'on',PRIMARY KEY(id),UNIQUE KEY (name),KEY(active),KEY(name, active)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		}
		if (!self::table_exists("sio_role")) {
		    self::$sql->query("CREATE TABLE sio_role(id int(11) NOT NULL AUTO_INCREMENT,cat int(11) NOT NULL DEFAULT '0',name varchar(68) NOT NULL DEFAULT 'None',comment text,active char(2) DEFAULT 'on',PRIMARY KEY(id),UNIQUE KEY (name),KEY (name,active)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		}
		if (!self::table_exists("sio_rolecat")) {
		    self::$sql->query("CREATE TABLE sio_rolecat(id int(11) NOT NULL AUTO_INCREMENT,name varchar(68) NOT NULL DEFAULT 'None',active char(2) DEFAULT 'on',PRIMARY KEY(id),UNIQUE KEY(name),KEY(name,active)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		}
		if (!self::table_exists("sio_userroles")) {
		    self::$sql->query("CREATE TABLE sio_userroles(user int(11) NOT NULL DEFAULT '0',role int(11) NOT NULL DEFAULT '0',PRIMARY KEY(user,role)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		}
		if (!self::table_exists("sio_roleactivities")) {
		    self::$sql->query("CREATE TABLE sio_roleactivities(role int(11) NOT NULL DEFAULT '0',activity int(11) NOT NULL DEFAULT '0',PRIMARY KEY(role,activity)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		}
		self::$website='http';
		if(!empty($_SERVER['HTTPS']) && $_SERVER["HTTPS"]=='on') { self::$website.='s'; }
		self::$website.='://' . $_SERVER["HTTP_HOST"];
		
		$domain_arr = explode('.',$_SERVER['HTTP_HOST'], 2);
		self::$domain=$domain_arr[1];
		
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
			if ($rx = self::$sql->query("select id,username,email from sio_user where active='on' and username='". self::$usr['RU'] ."'")) {
				while ($row = $rx->fetch_assoc()) {
					self::$usr['ID']=$row['id'];
					self::$usr['username']=$row['username'];
					self::$usr['email']=$row['email'];
				}
				$rx->close();
			}
//Now store user-profile (app specific) data.
            if ($rx = self::$sql->query("select * from sio_userprofile where user='". self::$usr['ID'] ."'")) {
                while ($row = $rx->fetch_assoc()) {
                   foreach($row as $k => $v ) {
 					    self::$usr[$k]=$v;
                   }
                 }
                $rx->close();
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

	public static function sphinx_esc ( $s ) {
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
