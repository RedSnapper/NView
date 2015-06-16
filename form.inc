<?php
mb_internal_encoding('UTF-8');

/**
 * trait 'Form'
 */
trait Form {
	public $debug;
	public $show;			//whether or not to show the form.
	private $signature; 	//unique signature for identifying this post.
	private $committed; 	//bool flag for identifying if we committed.
	private $valid;			//bool flag for identifying if we validated.
	private $newid;			//set if there is an insertion involving an auto_increment;
	private $id;			//unique prefix for identifying form fields in the post.
	private $idprefix;		//other bit for this.
	private $idprefixlen;	//length of value.
	private $pksimple;		//bool for pk is array / single value.
	protected $fields;		//array of fields.
	protected $messages;	//array of messages.. not sure this is being used.
	protected $pkey;		//[array/string] name(s) of primary key in sql.
	protected $key;			//[array/string] value(s) of primary key
	protected $table;   	//name of table to be used in sql
	protected $gflds;		//array of sql fields that will be used to get.
	protected $cflds;		//array of sql values that will be used during a commit.
	protected $names;		//array of input names
	protected $view;		//the formlet view (instance of an NView)
	private $subsfield; 	//subscriber field-name  for indicating presence/absence.
	private $subsvalue; 	//subscriber field-value based on sql.
	private $subspostv; 	//subscriber field-value based on post.
	private $subsdshow; 	//subscriber (defaults to true) show empty field if deleted..
	private $subfn; 		//subscriber CNUD function that happened - one of create/null/update/delete
	public $redirect;	    //where to go on insert.
	public $insert_qs; 	    //query string for new records. id placeholder = [[ID]]

/**
 * Wrapper to the $committed variable.
 * Used to identify whether or not the formlet has committed. Will be true only during a post, when the inputs are all valid, and the commit has occured.
 * @return bool
 */
	public function success() {
		return $this->committed;
	}
/**
 * Wrapper to the $committed variable.
 * Used to set the formlet as if it were committed. This is typically used via a func() operator overload.
 * @param bool $state (optional, defaults to true).  
 */
	public function setcommit($state = true) {
		$this->committed = $state;
	}

/**
 * Wrapper to the $newid variable.
 * Provides access to an auto_incremented primary key after a commit.
 * @return integer
 */
	public function newId() {
		return $this->newid;
	}

/**
 * Wrapper to the $valid variable.
 * Indicates if the formlet was found to be valid. 
 * When validation is out of scope, a formlet is always valid.
 * @return bool
 */
	public function valid() {
		return $this->valid;
	}

/**
 * Wrapper to the $id variable.
 * Returns the registered id for this formlet.
 * A formlet's id must be unique for the form within which it is placed.
 * @return integer
 */
	public function getId() {
		return $this->id;
	}

/**
 * Simple delete function which may be over-ridden.
 * This is typically called via a func() 'delete' being implemented.
 */
	public function delete() {
		$q="delete from ".$this->table." where " . $this->pkeysql();
		if($this->debug) {
			print('<br />DEBUG: Delete SQL=' . $q);
		} else {
			Settings::$sql->query($q);
		}
	}

/**
 * This is the validation code block of the formlet.
 * Except for formlets that require no validation, it will be over-ridden.
 * This code block should set the boolean value $this->valid, to indicate validity of the formlet.
 * @return optional bool indicating whether or not the formlet is valid.
 */
	public function validate() {
		return true;
	}

/**
 * This is the populate code block of the formlet.
 * Except for formlets that require no population, it will be over-ridden.
 * This code block is responsible for populating the formlet view with data:
 * - after a successful commit
 * - during a get, where the form is an editing a record.
 */
	public function populate() { //should be overridden.
	}

/**
 * This is the func code block of the formlet.
 * This code block is responsible for handling submits which are special.
 * Sometimes a formlet may have additional functions (such as 'dependencies','delete', etc).
 * Each button may use the name='_fn' and then value which will be used here.
 * The default value for a button (normal submit) is 'save', which func() does NOT support 
 * as the normal formlet processor will handle the 'save' function.
 */
	public function func() {
		switch ($this->fields['_fn'][0]) {
			default: {
				if($this->debug) {
					print_r($this->fields['_fn'][0] . " function not supported");
				}
			} break;
		}
	}

/**
 * The sig() is over-ridden so that each formlet class has it's own sig.
 * The sig() method will be used to identify which formlet class (not instance) is being invoked in a post.
 */
	public static function sig() {
		print("This is an error. sig() needs to be defined by the form class.");
		return "form_";
	}

	private function ident() { //ident includes userid.
 		$ident = '';
 		if(!empty(Settings::$usr['ID'])) {
			$ident = md5( Settings::$usr['ID'] . '_' . $this->signature );
		} else {
			$ident = md5( $this->signature );
		}
		return $ident;
	}

	private function setId($id) {
		$this->id = $id;
		$this->idprefix = $this->id . ':';
 		$this->signature = md5($this->id);
		$this->idprefixlen = mb_strlen($this->idprefix);
	}

/**
 * 'iniForm'
 * To be used in __construct methods.
 * vprefix = prefix sig to data-tr in form views.
 */
	protected function iniForm($idp='',$vp = NULL,$vprefix=true,$pk='id',$debug=false) {
		$this->debug=$debug;
		$this->pkey = $pk; //pk has array of primary key fields (?with values?)
		$this->pksimple = !is_array($pk);
		$this->setId('_' . static::sig() . $idp);
		if ($vp instanceof NView) {
		    $this->view = $vp;
		} else {
			$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,1);
			if (strpos($vp,'/') === false) {
				$file_ar=pathinfo($bt[0]['file']); //want the file.
				$file=$file_ar['dirname'].'/';
				if(empty($vp)) { //need this b/c form is a trait.
					$file .= $file_ar['filename'];
				} else {
					$file .= $vp;
				}
			} else {
				$file = $vp;
			}
		    $this->view = new NView($file);
		    $err = $this->view->messages(); //currently unused.
		    if (!empty($err)) {
		        print $err;
		    }
		}
		if ($vprefix) {
			$this->view->set("//*[@data-tr]/@data-tr/preceding-gap()",static::sig());
		}
		//initialise other members.
		$this->gflds = array(); //array of sql fields that will be used to get.
		$this->cflds = array(); //array of sql fields that will be used in commit.
		$this->names = array(); //array of input names.
		$this->committed = false;
		$this->valid = true;
		$this->show = true;
		$this->insert_qs=NULL;
		$this->redirect = NULL;
		$this->subsfield =NULL;
		$this->subsvalue =NULL;
		$this->newid=0;
	}

/**
 * 'inScope' used to test if a particular class of form is being posted.
 */
 	public static function inScope() {
		return (isset($_POST[static::sig()]));
	}

/**
 * 'isMe' used to test if a particular form instance is being posted.
 */
 	public function isMe() {
 		return ( isset($_POST[static::sig()]) && in_array($this->ident(), $_POST[static::sig()] ) );
	}

/**
 * 'seterr'
 */
	function seterr($ident=NULL,$value=NULL) {
		$this->view->set("//*[*[@data-msg='". $ident ."']]/@class/child-gap()"," invalid");
		$this->view->set("//*[@data-msg='". $ident ."']/child-gap()",$value);
	}

/**
 * 'setfld'
 */
	public function setfld($name=NULL,$gsql=NULL,$csql=NULL) {
		if (! is_null($name) ) {
				array_push($this->names,$name);
			if (!is_null($gsql)) {
				array_push($this->gflds,$gsql);
			} else {
				array_push($this->gflds,$name);
			}
			$this->cflds[$name] = $csql;
		}
	}

/**
 * 'setsub'
 */
	public function setsub($name=NULL,$dshow=true) {
		if (is_null($this->subsfield) && !is_null($name)) {
			$this->subsfield = $name;
			$this->subsvalue = NULL;
			$this->subsdshow = $dshow; //flag indicating whether to show form on delete.
		}
	}

/**
 * 'pkeysql'
 */
	protected function pkeysql() {
		$condition="";
		if ($this->pksimple) {
			$condition = $this->pkey;
			if(is_null($this->key)) {
				$condition .=" is NULL";
			} else {
				$condition .="='". $this->key ."'";
			}
		} else {
			$condition='';
			foreach ($this->pkey as $k => $v) {
				Settings::esc($k);
				if(is_null($v)) {
					$condition.= $k ." is NULL and ";
				} else {
					Settings::esc($v);
					$condition.= $k ."='". $v ."' and ";
				}
			}
			$condition=substr($condition,0,-5);
		}
		return $condition;
	}

/**
 * 'vsetopts'
 */
	protected function vsetopts($input='_unused',$qvp='select 0 as prompt,0 as value',$v=NULL) {
			if (is_null($v)) {
				$v = new NView('option_v.ixml');
				$err = $v->messages();
			}
			if (isset($err) && !empty($err)) {
				echo $err;
			} else {
				if ($rx = Settings::$sql->query($qvp)) {
					while ($f = $rx->fetch_assoc()) {
						$o = new NView($v);
						$o->set("/h:option/@value",$f['value']);
						$o->set("/h:option/child-gap()",$f['prompt']);
						$this->view->set("//h:select[@name='$input']/child-gap()",$o);
					}
					$rx->close();
				}
				if (isset($this->fields[$input])) {
					foreach ($this->fields[$input] as $val) {
						$this->view->set("//h:select[@name='$input']/h:option[@value='" . $val . "']/@selected","selected");
					}
				}
				$err = $v->messages();
				if (!empty($err)) {
				 	$v->set("//*[@data-xp='debug']",$err);
				}
			}
	}

/**
 * 'vset'
 */
	public function vset($input='_unused',$kind=NULL,$special=NULL) {
		$val=NULL;
		if (is_null($special)) {
			if ($input === $this->subsfield) {
				if (is_null($this->subsvalue) || $this->subsvalue=="false") {
					$val='false';
				} else {
					$val='true';
				}
			} else {
				if (isset($this->fields[$input])) {
					$val=$this->fields[$input][0];
				}
			}
		} else {
			$val=$special;
		}
		if (!is_null($val)) {
			switch($kind) {
				case "cb": {
					if ($val == 'on' || $val == 'true' || $val == 'checked' ) {
						$this->view->set("//*[@name='" . $input . "']/@value",$val);
						$this->view->set("//*[@name='" . $input . "']/@checked",'checked');
					}
				} break;
				case "ta": {
						$this->view->set("//*[@name='" . $input . "']/child-gap()",$val);
				} break;
				case "xp": {
						$this->view->set("//*[@data-xp='" .$input . "']/child-gap()",$val);
				} break;
				case "label": {
						$this->view->set("//*[@data-xp='" .$input . "']//*[@data-xp='label']/text()",$val);
				} break;
				default: {
						$this->view->set("//*[@name='" . $input . "']/@value",$val);
				} break;
			}
		} 
		switch($kind) {
			case "hide": {
					$this->view->set("//*[@data-xp='" .$input . "']/@class/child-gap()"," hide");
			} break;
			case "delete": {
					$this->view->set("//*[@data-xp='" .$input . "']");
			} break;
		}
	}


/**
 * 'formlets'
 * Processes multiple interdependent formlets, preventing invalidity from commits.
 * All formlets must be processed for a commit.
 * Call reveal() for each fm after this.
 * LIMITATIONS: currently this does not support subscribers (which should be orthogonal) or functions.
 */
	public static function formlets($fms=array(),$valfn=NULL,$show=true) {
		$us=true; $debug=false;
		foreach($fms as $fm) {
			if(! $fm->isMe()) { $us=false; }
			if($fm->debug) { $debug = true;
				$fm->forminfo();
			}
		}
		if ( $us ) {
			foreach($fms as $fm) {
				$fm->show=$show;
				$fm->setfrompost();
			}
			$valid = true;
			foreach($fms as $fm) {
				$fm->validate();
				$valid = $valid && $fm->valid();
			}
			if (!is_null($valfn)) {
				$valid = $valid && $valfn();
			}
			if ($valid) {
				foreach($fms as $fm) {
					if (!$fm->success()) {
						$fm->setcommit($fm->commit());
						if ( $fm->show || !($fm->success()) ) { //do not repop if !show and com.
							$fm->setfields(true); //we can repopulate the fields if we want.
						}
					}
				}
			}
		} else {
			foreach($fms as $fm) {
				$fm->setfields(false);
			}
		}
	}

	public function setfrompost() {
		$this->fields = array();
		foreach ($_POST as $k => $v) {
			if (strpos($k,$this->idprefix) === 0) {
				$key = mb_substr($k,$this->idprefixlen);
				if ($key === $this->subsfield) {
					$this->subspostv = $v;
				} else {
					if (isset($this->fields[$key])) {
						$this->fields[$key][]= $v;
					} else {
						$this->fields[$key]= array($v);
					}
				}
			}
		}
		if($this->debug) {
			print("<br />Posted fields<pre>".print_r($this->fields,true)."</pre>");
		}
	}

	public function forminfo() {
		print('<br /><br />id['.$this->id.'] sig['.static::sig().'] signature['.$this->signature.'] ident['.$this->ident().']<br />');
		if(isset($_POST)) {
			print('<code>'. print_r($_POST,true). '</code>');
		}
		if (isset($_POST[static::sig()])) {
			print("This form has been posted ");
			if (in_array($this->ident(),$_POST[static::sig()])) {
				print("and the ident() matches the id, so this is in scope..");
				if($this->isMe()) {
					print(" ... and isMe() agrees!<br />");
				} else {
					print(" ... and isMe() FAILS to agree!!<br />");
				}
			} else {
				print("and the post failed to match because (post)".$_POST[static::sig()]."!=(ident)".$this->ident()."<br />");
			}
		} else {
			print("<p>This form has not been posted.</p>");
		}
	}


/**
 * 'form'
 */
	public function form($show=true) {
		if($this->debug) {
			$this->forminfo();
		}
		if($this->isMe()) {
			$this->show=$show;
			$this->setfrompost();
			if (!empty($this->fields['_fn'][0]) && strcmp($this->fields['_fn'][0],"save") !== 0 ) {
				if($this->debug) {
					print("<p>Non-save function:".$this->fields['_fn'][0]."</p>");
				}
				$this->setfields(false); //populate the fields from sql
				$fn = $this->func();
				if (!is_null($fn)) {
					return $fn;
				}
			} else {
				if (isset($this->subsfield)) {
					if($this->debug) {
						print("<p>This has a subscriber:".$this->subsfield."</p>");
					}
					$this->subscriber();
				}
				if (!$this->committed) {
					if($this->debug) {
						print("<p>fn Save, about to validate</p>");
					}
					$this->validate();
					if($this->debug) {
						print("<p>Validate returned".$this->valid."</p>");
					}
					if (!$this->valid) {
						Settings::$msg[static::sig()."-status"]="invalid";
					}
					if ($this->valid && ! $this->committed) {
                        if($this->debug) {
                            print("<p>About to commit</p>");
                        }
						$this->committed = $this->commit();
						if ( $this->show || !($this->committed) ) { //do not repop if !show and com.
							$this->setfields(true); //we can repopulate the fields if we want.
							if ($this->committed) {
								Settings::$msg[static::sig()."-status"]="committed";
							}
						}
					} else {
                        if($this->debug) {
                            print("<p>Will not commit</p>");
                        }
					}
				}
			}
		} else {
			$this->setfields(false); //we can populate the fields if we want.
		}
		return $this->reveal();
	}

	public function reveal() {
		if ( $this->show || !($this->committed) ) { //don't repop if !show and com.
			$this->populate();
			$this->view->set("//*[@data-msg][not(node())]");
			$this->view->set("//*[@data-xp='_ident']/@name",static::sig()."[]"); //we have to force this to be an array.
			$this->view->set("//*[@data-xp='_ident']/@value",$this->ident());
			$this->view->set("//*[@name][@name!='" . static::sig() . "[]']/@name/preceding-gap()",$this->idprefix);
			return $this->view;
		} else {
			return null;
		}
	}
/**
 * 'subscriber'
 * This is called only during a post. so we need to find out the current state of the sql.
 */
	protected function subscriber($name=NULL,$orig=NULL) {
		$this->setsubsvalue();
		if ( empty($this->subsvalue) || $this->subsvalue=="false") {
			if ( empty($this->subspostv) || $this->subspostv=="false") { //wasn't set, won't be set - ignore.
				$this->subfn='null';
				$this->committed = true; //was empty, is empty no need to validate or commit.
			} else { //was empty - now inserted.
				$this->subfn='create';
				$this->subsvalue="true";
				$this->committed = false; //need to commit update.
			}
		} else { //subsvalue is true.
			if ( empty($this->subspostv) || $this->subspostv=="false") { //was set, has been unset - a delete.
				$this->subsvalue="false";
				$this->subfn='delete';
				$this->delete();
				foreach ($this->names as $n) {
					$this->fields[$n]=array(NULL);
				}
				$this->committed = true;
				$this->show=$this->subsdshow;
			} else {
				$this->subfn='update';
				$this->subsvalue="true";
				$this->committed = false; //need to commit update.
			}
		}
	}

	private function setsubsvalue() {
		if ($this->pksimple) {
			$field = $this->pkey;
		} else {
			$field = array_keys($this->pkey)[0];
		}
		$query="select " . $field . " from " . $this->table . " where " . $this->pkeysql();
		if($this->debug) {
			print("<p>setsubsvalue() sql:".$query."</p>");
		}
		if ($rx = Settings::$sql->query($query)) {
			if ($rx->num_rows > 0) {
				$this->subsvalue="true";
			} else {
				$this->subsvalue="false";
			}
			$rx->close();
		}
	}


/**
 * 'setfields'
 */
	public function setfields($inpost=false) {
		if (!$inpost) {
			$getf = array_combine($this->gflds,$this->names);
			$flds = "";
			foreach ($getf as $i => $k) {
				if (strcmp($i, $k) === 0) {
					$flds .= $i . ',';
				} else {
					$flds .= $i . ' as ' . $k . ',';
				}
			}
			$flds = rtrim($flds,",");
			$condition=$this->pkeysql();
			if(empty($flds)) {
				$flds = "true";
			}
			$query="select " . $flds . " from " . $this->table . " where " . $condition;
			if($this->debug) {
				print('<br />DEBUG: Set fields SQL=' . $query);
			}
			if ($rx = Settings::$sql->query($query)) {
				if (!is_null($this->subsfield)) { //need this for gets.
					if ($rx->num_rows > 0) {
						$this->subsvalue="true";
					} else {
						$this->subsvalue="false";
					}
				}
				while ($f = $rx->fetch_assoc()) {
					foreach ($this->names as $n) {
						$this->fields[$n]=array($f[$n]);
					}
				}
				$rx->close();
			}
		}
	}

/**
 * 'commit'
 */
	public function commit() {
		if($this->debug) {
			print("<br />DEBUG:Processing commit.");
		}
		$retval = false;
		$klist=""; $ulist = ""; $flist=""; $vlist = "";

		//This sets the primary key values.
		if ($this->pksimple) {
			$vlist = is_null($this->key)? "NULL," : "'". $this->key . "',";
			$klist = $this->pkey.",";
		} else {
			foreach ($this->pkey as $k => $v) {
				Settings::esc($k);
				$klist.=$k.",";
				if (is_null($v)) {
					$vlist.="NULL,";
				} else {
					Settings::esc($v);
					$vlist.="'".$v . "',";
				}
			}
		}
		if($this->debug) {
			print("<br />Names<pre>".print_r($this->names,true)."</pre>");
			print("<br />cflds<pre>".print_r($this->cflds,true)."</pre>");
			print("<br />fields<pre>".print_r($this->fields,true)."</pre>");
		}
		foreach ($this->names as $n) {
			if (is_null($this->cflds[$n]) || ($this->cflds[$n] != '!skip')) {
				if (is_null($this->cflds[$n])) {
					if(array_key_exists($n,$this->fields)) {
						$vv=$this->fields[$n][0];
						Settings::esc($vv);
						$val = "'".$vv."'";
					} else {
						$val="''";
					}
				} else {
					$val = $this->cflds[$n];
				}
				$flist .= $n . ",";
				$ulist .= $n . "=values(" . $n . "),";
				$vlist .= $val . ",";
			}
		}
		$flist = rtrim($flist,",");
		$ulist = rtrim($ulist,",");
		$vlist = rtrim($vlist,",");
		$ignore = "";
		$dupekey = "";
		if (empty($flist)) {
			$ignore = " ignore ";
			$klist = rtrim($klist,","); //we need to get rid of this one also..
		}
		if (!empty($ulist)) {
			$dupekey = " on duplicate key update ";
		}
		$query="insert " .$ignore. "into "  . $this->table . "(" . $klist . $flist . ") values(" . $vlist . ")".$dupekey.$ulist;
		if($this->debug) {
			print('<br />DEBUG: Commit SQL=' . $query);
		}
		if (Settings::$sql->query($query)) {
			if ($r=Settings::$sql->query("select last_insert_id()")) {
                $this->newid = intval($r->fetch_row()[0]);
                if (($this->newid !== 0) && ($this->show || !is_null($this->redirect))) {
                    $this->show = false;
                    $this->redirection($this->redirect,$this->insert_qs,$this->newid);
                }
                $r->close();
			}
			$retval = true;
		} else {
			$retval = false;
		}
		return $retval;
	}

	public function redirection($redirect=NULL,$insert_qs=NULL,$newid=0) {
        if ($newid != 0) {
            if (!is_null($redirect)) {
                $new_url = $redirect;
            } else {
                $new_url = Settings::$url;
            }
            if (mb_strpos($new_url,"?") === false) {
              $new_url .= "?";
            }
            if (is_null($insert_qs)) {
                $new_url .= "[[ID]]";
            } else {
                $new_url .= $insert_qs;
            }
            $new_url = str_replace("[[ID]]",$newid,$new_url);
            if($this->debug) {
                print('<br />DEBUG: Redirect set to =' . $new_url);
            } else {
                header("Location: " . $new_url );
            }
	    }
	}

/**
 * validation functions
 */
 	public function valMacro($name='',$msg="Invalid Macrotext") {
		$this->valid = true;
		if (isset($this->fields[$name][0])) {
			if (empty($this->fields[$name][0]) || !is_string($this->fields[$name][0])) {
				$this->valid = true;
			} else {
				$adv = mb_strpos($this->fields[$name][0],"⌽") === false ? 0:1;
				$tf=getenv('RS_SCRATCH_DIR') . '/mtxt_'.$this->signature.'_mac';
				$fp = fopen($tf,"wb");
				fwrite($fp,$this->fields[$name][0]);
				fclose($fp);
				$command="/websites/editorial/scripts/macrotxt ";
				$xrs = shell_exec($command . getenv('RS_PATH') . " " . $tf ." ". Settings::$usr['RU']);
				if (strpos($xrs,"error") !== false) {
					$msg = new NView($xrs);
					$this->seterr($name,$msg);
					$this->valid=false;
				}
			}
		}
	}
	public function valEmail($name='',$msg="This must be a valid email address") {
		if (isset($this->fields[$name][0])) {
			if (!filter_var($this->fields[$name][0], FILTER_VALIDATE_EMAIL)) {
				$this->valid = false;
				$this->seterr($name,$msg);
			}
		}
	}
	public function valSignificant($name='',$msg="This must have a value") {
		if (isset($this->fields[$name][0])) {
			if (empty($this->fields[$name][0])) {
				$this->valid = false;
				$this->seterr($name,$msg);
			}
		} else {
			$this->valid = false;
			$this->seterr($name,$msg);
		}
	}
	public function valNumeric($name='',$msg=" This must be a number.") {
		if (isset($this->fields[$name][0])) {
			if (empty($this->fields[$name][0]) || !is_numeric($this->fields[$name][0])) {
				$this->valid = false;
				$this->seterr($name,$msg);
			}
		}
	}
	public function valInt($name='',$msg=" This must be a whole number.") {
		if (isset($this->fields[$name][0])) {
			if (empty($this->fields[$name][0]) || !is_int($this->fields[$name][0])) {
				$this->valid = false;
				$this->seterr($name,$msg);
			}
		}
	}
	public function valNotZero($name='',$msg=" This must be filled correctly.") {
		if (isset($this->fields[$name][0])) {
		    $val=intval($this->fields[$name][0]);
            if ( $val === 0 ) {
                $this->valid = false;
                $this->seterr($name,$msg);
            }
		}
	}
	public function valNoWhitespace($name='',$msg=" This must contain no whitespace.") {
		if (isset($this->fields[$name][0])) {
			if (empty($this->fields[$name][0]) || !is_string($this->fields[$name][0]) || preg_match('/\s/',$this->fields[$name][0])) {
				$this->valid = false;
				$this->seterr($name,$msg);
			}
		}
	}
	public function valString($name='',$msg="This must be a string") {
		if (isset($this->fields[$name][0])) {
			if (empty($this->fields[$name][0]) || !is_string($this->fields[$name][0])) {
				$this->valid = false;
				$this->seterr($name,$msg);
			}
		}
	}

	public function valUnique($name='',$msg="This must be unique") {
		if (isset($this->fields[$name][0])) {
			if (!empty($this->fields[$name][0]) && is_string($this->fields[$name][0])) {
				$value=$this->fields[$name][0];
				Settings::esc($value);
				$sql="select " . $name . " from " . $this->table . " where " . $name . "='" . $value . "' and not(" . $this->pkeysql() . ")";
				if ($rx = Settings::$sql->query($sql)) {
					if ($rx->num_rows > 0) {
						$this->valid = false;
						$this->seterr($name,$msg);
					}
					$rx->close();
				}
			}
		}
	}

}