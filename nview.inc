<?php
mb_internal_encoding('UTF-8');

/**
 * class 'NView'
 * NView class, provides DomNode and easy XPath support
 * (private) members:
 * xp is the xpath manager
 * doc is the internal DOMDocument
 * msgs is the (error) message array.
 */
class NView {

	const GAP_NONE = 1;
	const GAP_FOLLOWING = 2;
	const GAP_PRECEDING = 3;
	const GAP_CHILD = 4;
	const GAP_NATTR = 5;
	const GAP_DATA = 6;

	private $errs = '';
	private $fname = null;
	private $xp = null;
	private $doc = null;
	protected $msgs = array();

/**
 * '__clone'
 */
	public function __clone() {
		$this->initDoc();
		$this->doc = $this->doc->cloneNode(true);
		$this->initXPath();
	}

/**
 * '__construct'
 */
	public function __construct($value='') {
		set_error_handler(array($this, 'doMsg'), E_ALL | E_STRICT);
		try {
			switch(gettype($value)) {
				case 'NULL':
				case 'string': {
					$this->con_string($value);
				} break;
				case 'object': {
					$this->con_object($value);
				} break;
				case 'resource': {
					$contents = '';
					while (!feof($value)) {
					  $contents .= fread($value,1024);
					}
					$this->con_string($contents);
				} break;
				default: {
					$this->doMsg("NView:: __construct does not (yet) support " . gettype($value));
				}
			}
		} catch (Exception $e) {
			$this->doMsg($e->getCode(),"NView: " . $e->getMessage(),$e->getFile(), $e->getLine());
		}
		restore_error_handler();
	}

/**
 * 'doc'
 */
	public function doc() {
		return $this->doc->documentElement;
	}

/**
 * 'show'
 */
	public function show($whole_doc = false) {
		$retval="";
		if (!is_null($this->doc) && !is_null($this->xp)) {
			$this->tidyView();
            ob_start(); 
            $this->doc->save('php://output'); 
            $retval = ob_get_clean();
			if (!$whole_doc) {
				$retval = static::as_fragment($retval);
			}
		}
		return $retval;
	}
	
/**
 * 'as_fragment'
 */
	public static function as_fragment($docstr) {
		$s1='/<\?xml[^?]+\?>/';
		$s2='/<!DOCTYPE \w+>/';
		$s3='/\sxmlns="http:\/\/www.w3.org\/1999\/xhtml"/';
		$ksub = array($s1, $s2, $s3);
		return trim(preg_replace($ksub,'',$docstr));
	}

/**
 * 'tset - translation hooks'
 */
	public function tset($keys= NULL) {
	    if(class_exists("Dict")) {
            if(is_null($keys)) {
                $tr_attrs = $this->get("//*[@data-tr]/@data-tr");
                if ($tr_attrs instanceof DOMNodeList) {
                    foreach($tr_attrs as $n) {
                        $nvnode = $this->strToNode(Dict::get($n->value)); //textnode from the dictionary keyed by the attribute value.
                        $n->parentNode->appendChild($nvnode);			  //now we have added it in.
                        $n->parentNode->removeAttributeNode($n);		  //now remove the translation attribute.
                    }
                } else { //just the one attribute..
                    $this->set("//*[@data-tr='".$tr_attrs."']/child-gap()",Dict::get($tr_attrs));
                    $this->set("//*[@data-tr='".$tr_attrs."']/@data-tr");
                }
            } else {
                foreach($keys as $k) {
                    $this->set("//*[@data-tr='" . $k . "']/child-gap()",Dict::get($k));
                }
            }
        }
	}

/**
 * 'addNamespace'
 */
	public function addNamespace($prefix,$namespace) {
		if (!is_null($this->xp)) {
			$this->xp->registerNamespace($prefix,$namespace);
		}
	}


/**
 * 'strToNode'
 */
	public function strToNode($value=null) {
		$fnode = NULL;
		$fnode = $this->doc->createDocumentFragment();
		$this->errs = '';
		set_error_handler(array($this, 'noMsg'), E_ALL | E_STRICT);
		try {
			// One should always xml-encode ampersands in URLs in XML.
			$fragstr = preg_replace('/&(?![\w#]{1,7};)/i','&amp;',$value);
			$fnode->appendXML($fragstr);
		} catch (Exception $ex) {
			$this->doMsg('Attempted fragment:',htmlspecialchars(print_r($fragstr,true)));
			restore_error_handler();
			throw $ex;
		}
		restore_error_handler();
		if (strpos($this->errs,'parser error') !== false) {
			$fnode = $this->doc->createTextNode($value);
		}
		$node = $this->doc->importNode($fnode, true);
		return $node;

	}

/**
 * 'count'
 */
	public function count($xpath,$ref=null) {
		if (!is_null($this->doc) && !is_null($this->xp)) {
			if (is_null($ref)) {
				$entries = $this->xp->query($xpath);
			} else {
				$entries = $this->xp->query($xpath,$ref);
			}
			if ($entries) {
				return $entries->length;
			} else {
				$this->doMsg('NView: count() ' . $xpath . ' failed.');
				return 0;
			}
		} else {
			$this->doMsg('NView: count() ' . $xpath . ' attempted on a non-document.');
		}
	}

/**
 * 'consume'
 */
	public function consume($xpath, $ref=null) {
		$retval = null;
		$retval = $this->get($xpath, $ref);
		if (!is_null($retval)) {
			$this->set($xpath,null,$ref);
		}
		return $retval;
	}

/**
 * 'get'
 */
	public function get($xpath, $ref=null) {
		$retval = null;
		if (!is_null($this->doc) && !is_null($this->xp)) {
			set_error_handler(array($this,'doMsg'),E_ALL | E_STRICT);
			if (is_null($ref)) {
				$entries = $this->xp->query($xpath);
			} else {
				$entries = $this->xp->query($xpath,$ref);
			}
			if ($entries) {
				switch ($entries->length) {
					case 1: {
						$entry = $entries->item(0);
						if ($entry->nodeType == XML_TEXT_NODE || $entry->nodeType == XML_CDATA_SECTION_NODE) {
							$retval = $entry->nodeValue;
						} elseif ($entry->nodeType == XML_ATTRIBUTE_NODE) {
							$retval = $entry->value;
						} elseif ($entry->nodeType == XML_ELEMENT_NODE) {
							//convert this to a domdocument so that we can maintain the namespace.
							$retval = new DOMDocument("1.0","utf-8");
							$retval->preserveWhiteSpace = true;
							$retval->formatOutput = false;
							$node = $retval->importNode($entry, true);
							$retval->appendChild($node);
							$olde= $this->doc->documentElement;
							if ($olde->hasAttributes()) {
								$myde= $retval->documentElement;
								foreach ($olde->attributes as $attr) {
									if (substr($attr->nodeName,0,6)=="xmlns:") {
										$myde->removeAttribute($attr->nodeName);
										$natr = $retval->importNode($attr,true);
										$myde->setAttributeNode($natr);
									}
								}
							}
							$retval->normalizeDocument();
						} else {
							$retval = $entry;
						}
					} break;
					case 0: break;
					default: {
						$retval=$entries;
					} break;
				}
			} else {
				$this->doMsg('NView::get() ' . $xpath . ' failed.');
			}
			restore_error_handler();
		} else {
			$this->doMsg('NView::get() ' . $xpath . ' attempted on a non-document.');
		}
		return $retval;
	}


/**
 * 'set'
 */
	public function set($xpath,$value=null,$ref=null,$show_err_on_missing=true) {
		//replace node at string xpath with node 'value'.
		set_error_handler(array($this,'doMsg'), E_ALL | E_STRICT);
		if (!is_null($this->doc) && !is_null($this->xp)) {
			$gap = self::GAP_NONE;
			if (substr($xpath,-6)=="-gap()") {
				$xpath = mb_substr($xpath,0,-6); //remove the -gap();
				if (substr($xpath,-6)=="/child") {
					$xpath = mb_substr($xpath,0,-6); //remove the child;
					$gap=self::GAP_CHILD;
				}
				elseif (substr($xpath,-10)=="/preceding") {
					$xpath = mb_substr($xpath,0,-10); //remove the child;
					$gap=self::GAP_PRECEDING;
				}
				elseif (substr($xpath,-10)=="/following") {
					$xpath = mb_substr($xpath,0,-10); //remove the child;
					$gap=self::GAP_FOLLOWING;
				}
			} elseif (substr($xpath,-7)=="/data()") {
				$xpath = mb_substr($xpath,0,-7); //remove the func.;
				$gap=self::GAP_DATA;
			}
			//now act according to value type.
			switch (gettype($value)) {
				case "NULL": {
					if ($gap==self::GAP_NONE) {
						if (is_null($ref)) {
							$entries = $this->xp->query($xpath);
						} else {
							$entries = $this->xp->query($xpath,$ref);
						}
						if ($entries) {
							foreach($entries as $entry) {
								if ($entry instanceof DOMAttr) {
									$entry->parentNode->removeAttributeNode($entry);
								} else {
									$n = $entry->parentNode->removeChild($entry);
									unset($n); //not sure if this is needed..
								}
							}
						} else {
							$this->doMsg('NView::set() ' . $xpath . ' failed.');
						}
					}
				} break;
				case "boolean":
				case "integer":
				case "double":
				case "string":
				case "double":
				case "object" : { //probably a node.
					if (gettype($value) != "object" || is_subclass_of($value,'DOMNode') || $value instanceof DOMNodeList || $value instanceof NView) {
						if (is_null($ref)) {
							$entries = $this->xp->query($xpath);
						} else {
							$entries = $this->xp->query($xpath,$ref);
						}
						if ($entries) {
							if ($entries->length === 0 ) { //maybe this is a new attribute!? [&& $gap == self::GAP_NONE]
								$spoint = mb_strrpos($xpath,'/');
								$apoint = mb_strrpos($xpath,'@');  
								if ($apoint == $spoint+1) {
									$aname= mb_substr($xpath,$apoint+1); //grab the attribute name.
									$xpath= mb_substr($xpath,0,$spoint); //resize the xpath.
									$gap=self::GAP_NATTR;
									if (is_null($ref)) { //re-evaluate the xpath without the attribute component.
										$entries = $this->xp->query($xpath); 
									} else {
										$entries = $this->xp->query($xpath,$ref);
									}
									if (!$entries) {
										$this->doMsg('NView::set() ' . $xpath . ' failed.');
									}
								}
							}
							if ($entries->length !== 0 ) {
								if ($value instanceof NView) {
									if ($gap !== self::GAP_DATA) {
										$value = $value->doc->documentElement;
									} else {
										$value = $value->show(false);
									}
								}
								foreach($entries as $entry) {
									if ($gap == self::GAP_NATTR && $entry->nodeType==XML_ELEMENT_NODE && isset($aname)) {
										$entry->setAttribute($aname,strval($value));
									} else {
										if (($entry->nodeType == XML_ATTRIBUTE_NODE) && (gettype($value) != "object")) {
											switch ($gap) {
												case self::GAP_NONE: {
													$entry->value = strval($value);
												} break;
												case self::GAP_PRECEDING: {
													$entry->value = strval($value) . $entry->value ;
												} break;
												case self::GAP_FOLLOWING:
												case self::GAP_CHILD: {
													$entry->value .= strval($value);
												} break;
											}
										} elseif (($entry->nodeType == XML_CDATA_SECTION_NODE) && (gettype($value) != "object")) {
											switch ($gap) {
												case self::GAP_NONE: {
													$entry->data = strval($value);
												} break;
												case self::GAP_PRECEDING: {
													$entry->insertData(0,strval($value));
												} break;
												case self::GAP_FOLLOWING:
												case self::GAP_CHILD: {
													$entry->appendData(strval($value));
												} break;
											}
										} elseif (($entry->nodeType == XML_COMMENT_NODE) && ($gap == self::GAP_DATA)) {
											if(gettype($value) == "object") {
												$fvalue = "";
												if ($value instanceof DOMNodeList) {
													foreach($value as $nodi) {
														$doc = new DOMDocument("1.0","utf-8");
														$node = $doc->importNode($nodi, true);
														$doc->appendChild($node);
														$txt = $doc->saveXML();
														$fvalue .= static::as_fragment($txt);
													}
												} else {
													if ($value instanceof DOMNode) {
														$doc = new DOMDocument("1.0","utf-8");
														$node=$doc->importNode($value, true);
														$doc->appendChild($node);
														$txt = $doc->saveXML();
														$fvalue = static::as_fragment($txt);
													} else {
														$this->doMsg("NView:  ". gettype($value). " not yet implemented for comment insertion.");
													}											
												}
												$fvalue=str_replace(array("<!--","-->"),"", $value);
												$entry->replaceData(0,$entry->length,$fvalue);
											} else {
												$fvalue=str_replace(array("<!--","-->"),"", $value);
												$entry->replaceData(0,$entry->length,$fvalue);
											}
										} else {
											if ($value instanceof DOMNodeList) {
												foreach($value as $nodi) {
													$nodc = $nodi->cloneNode(true);
													$node = $this->doc->importNode($nodc, true);
													switch ($gap) {
														case self::GAP_NONE: {
															$entry->parentNode->replaceChild($node, $entry);
														} break;
														case self::GAP_PRECEDING: {
															$entry->parentNode->insertBefore($node, $entry);
														} break;
														case self::GAP_FOLLOWING: {
															if (is_null($entry->nextSibling)) {
																$entry->parentNode->appendChild($node);
															} else {
																$entry->parentNode->insertBefore($node,$entry->nextSibling);
															}
														} break;
														case self::GAP_CHILD: {
															$entry->appendChild($node);
														} break;
													}
												}
											} else {
												if (gettype($value) != "object") {
													$node = $this->strToNode(strval($value));
												} else {
													$nodc = $value->cloneNode(true);
													$node = $this->doc->importNode($nodc, true);
												}
												if (!is_null($node->firstChild) || $node->nodeType == XML_TEXT_NODE )
												{
													switch ($gap) {
														case self::GAP_NONE: {
															$entry->parentNode->replaceChild($node, $entry);
														} break;
														case self::GAP_PRECEDING: {
															$entry->parentNode->insertBefore($node, $entry);
														} break;
														case self::GAP_FOLLOWING: {
															if (is_null($entry->nextSibling)) {
																$entry->parentNode->appendChild($node);
															} else {
																$entry->parentNode->insertBefore($node,$entry->nextSibling);
															}
														} break;
														case self::GAP_CHILD: {
															$entry->appendChild($node);
														} break;
													}
												} else {
												/*
												print('<br />$node->firstChild is null with '.$node->nodeType.'<br />');
												This happens when a DOMDocumentFragment is empty.
												*/
												}
											}
										}
									}
								}
								if (gettype($value) != "object" || $value->nodeType == XML_TEXT_NODE && $gap != self::GAP_NONE) {
									$this->doc->normalizeDocument();
								}
							} else {
								if ($show_err_on_missing) {
									$this->doMsg('NView::set() ' . $xpath . ' failed to find the xpath in the document.');
								}
							}
						} else {
							$this->doMsg('NView::set() ' . $xpath . ' failed.');
						}
					} else {
						$this->doMsg("NView: Unknown value type of object ". gettype($value) ." found");
					}
				} break;
				default: { //treat as text.
					$this->doMsg("NView: Unknown value type of object ". gettype($value) ." found");
				}
			}
		} else {
			$this->doMsg('set() ' . $xpath . ' attempted on a non-document.');
		}
		restore_error_handler();
		return $this;
	}

/**
 * 'messages'
 */
	public function messages() {
		$result="";
		if (count($this->msgs) !== 0) {
			$result = "<div class='messages'>";
			foreach($this->msgs as $m) {
				if ($m[3] == 0) {
					$msg = "<p><b>" . $m[0] . "</b></p>";
				} else {
					$msg = "<p><i>" . $m[2] . "</i> Line: <i>" . $m[3] . "</i>; <b>" . $m[1] . "</b></p>";
				}
				$result .= $msg;
			}
			$result .= "<pre>"; // . print_r($this->doc,true);
			if (!empty($this->fname)) {
				 $result .= "Document loaded at :" . $this->fname;
			 }
			ob_start();
			debug_print_backtrace();
			$result .= ob_get_contents();
			ob_end_clean();

			$result .= "</pre></div>";
		}
		return $result;
	}

/**
 * 'initDoc'
 */
	private function initDoc() {
		$this->doc = new DOMDocument("1.0","utf-8");
		$this->doc->preserveWhiteSpace = true;
		$this->doc->formatOutput = false;
	}

/**
 * 'initXPath'
 */
	private function initXPath() {
		$this->xp = new DOMXPath($this->doc);
		$this->xp->registerNamespace("h","http://www.w3.org/1999/xhtml");
	}

/**
 * 'con_class'
 */
	private function con_class($value) {
		if (!is_null($value->doc)) {
			$this->initDoc();
			$this->doc = $value->doc->cloneNode(true);
			$this->initXPath();
		}
	}

/**
 * 'con_node'
 */
	private function con_node($value) {
		if ($value->nodeType == XML_DOCUMENT_NODE) {
			$this->doc = $value->cloneNode(true);
			$this->initXPath();
		} elseif ($value->nodeType == XML_ELEMENT_NODE) {
			$this->initDoc();
			if (empty($value->prefix)) {
				$value->setAttribute("xmlns",$value->namespaceURI);
			} else {
				$value->setAttribute("xmlns:".$value->prefix,$value->namespaceURI);
			}			
			$node = $this->doc->importNode($value, true);
			$this->doc->appendChild($node);
			$olde= $value->ownerDocument->documentElement;
			if ($olde->hasAttributes()) {
				$myde= $this->doc->documentElement;
				foreach ($olde->attributes as $attr) {
					if (substr($attr->nodeName,0,6)=="xmlns:") {
						$myde->removeAttribute($attr->nodeName);
						$natr = $this->doc->importNode($attr,true);
						$myde->setAttributeNode($natr);
					}
				}
			}
			$this->initXPath();
		} else {
			$this->doMsg("NView:: __construct does not (yet) support construction from nodes of type " . $value->nodeType);
		}
	}

/**
 * 'con_file'
 */
	private function con_file($value) {
		if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
			$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,5);
		}
		if (empty($value)) {
			$value = pathinfo($bt[2]['file'], PATHINFO_DIRNAME);
			$value .= '/' . pathinfo($bt[2]['file'], PATHINFO_FILENAME);
		}
		if (strpos($value,'.') === false) {
			if (file_exists($value . '_1.ixml')) {
				$value .= '_1.ixml';
			}
			elseif (file_exists($value . '.xml')) {
				$value .= '.xml';
			}
			elseif (file_exists($value . '_v.ixml')) {
				$value .= '_v.ixml';
			}
		}
		if (!file_exists($value)) {
			$xview = dirname($bt[2]['file']) . '/' . $value;
		} else {
			$xview = $value;
		}
		$this->fname = $xview;
		if (file_exists($xview)) {
			$this->initDoc();
			$data = file_get_contents($xview);
//uncomment these lines if you want newlines stripped from the sourcefile.
//for str_replace, will need to check encoding mb_check_encoding($data)
//			$wss = array("\r\n", "\n", "\r", "\t"); //what we will remove
//			$data = str_replace($wss,"", $data);
			try {
				$this->doc->loadXML($data);
			} catch (Exception $e) {
				$this->doMsg("NView: File '" . $xview . "' was found but didn't parse " . $data);
				$this->doMsg($e->getCode(),$e->getMessage(),$e->getFile(), $e->getLine());
			}
			$this->initXPath();
		} else {
			$this->doMsg("NView: File '" . $xview . "' wasn't found. ");
			ob_start();
			debug_print_backtrace();
			$trace = ob_get_contents();
			ob_end_clean();
			$this->doMsg("<pre>" . $trace . "</pre>");
		}
	}

/**
 * 'con_string'
 */
	private function con_string($value) {
		// If there is no value, look for a file adjacent to this.
		if (empty($value)) {
			$this->con_file($value); //handle implicit in file..
		} elseif (strpos($value,'<') === false) {
			$this->con_file($value);
		} else {
			// Treat value as xml to be parsed.
			if (mb_check_encoding($value)) {
				$wss   = array("\r\n", "\n", "\r", "\t"); //
				$value = str_replace($wss,"", $value); //str_replace should be mb safe.
				$this->initDoc();
				$this->doc->loadXML($value);
				$this->initXPath();
			} else {
				$this->doc=NULL;
			}
		}
	}

/**
 * 'con_object'
 */
	private function con_object($value) {
		if ($value instanceof NView)
		{
			$this->con_class($value);
		}
		elseif (is_subclass_of($value,'DOMNode'))
		{
			$this->con_node($value);
		}
		else {
			$this->doMsg("NView: object constructor only uses instances of NView or subclasses of DOMNode.");
		}
	}

/**
 * 'tidyView'
 */
	private function tidyView() {
		//attempt any remaining translations and remove final data-tr attributes.
		$this->tset();
		$this->doc->normalizeDocument();
		$xq = "//*[not(node())][not(contains('[area|base|br|col|hr|img|input|link|meta|param|command|keygen|source]',local-name()))]";
		$entries = $this->xp->query($xq);
		if ($entries) {
			foreach($entries as $entry) {
				$entry->appendChild($this->doc->createTextNode(''));
			}
		}
	}

/**
 * 'doMsg'
 * parser message handler..
 */
	function doMsg($errno, $errstr='', $errfile='', $errline=0) {
		$this->msgs[] = array($errno, $errstr, $errfile, $errline); //this adds to the array
	}

	function noMsg($errno, $errstr='', $errfile='', $errline=0) {
		$this->errs .= $errstr; //error was made.
	}

}
