<?php
namespace RS\NView\Sio;

class SioUtility {

/*
	password settings.
*/
    /**
     * @var int. The minimum required length (0 = ignore)
     */
    private $minLength;
    /**
     * @var int. The minimum required score (0 = ignore)
     */
    private $minScore;

    /**
     * @var boolean alpha characters
     */
    private $alpha;
    /**
     * @var boolean numeric characters
     */
    private $numeric;
    /**
     * @var boolean symbols
     */
    private $symbols;
    /**
     * @var boolean Should contain both upper and lowercase
     */
    private $mixedCase;

    /**
     * @var array pw_regex that contains the regular expression tests and the (default) error sigs.
     */
    private $pw_regex = array();

    function __construct()
    {
    	$this->pw_set_minLength();
    	$this->pw_set_minScore();
    	$this->pw_set_needsAlpha();
    	$this->pw_set_needsNumeric();
    	$this->pw_set_needsSymbols();
    	$this->pw_set_needsMixedCase();
    	$this->initialise();
    }

	/*pw settings functions */
    public function pw_set_minLength($minLength=7)
    {
        $this->minLength = (int) $minLength;
    }
    public function pw_set_minScore($minScore=0)
    {
        $this->minScore = (int) $minScore;
    }
    public function pw_set_needsAlpha($alpha=true)
    {
        $this->alpha = (boolean) $alpha;
        if($this->alpha)
        {
        	$this->pw_regex['pw_error_no_alpha']= "/^(?=.+[A-Za-z]).+$/";
        } else {
        	unset($this->pw_regex['pw_error_no_alpha']);
        }
    }
    public function pw_set_needsNumeric($numeric=false)
    {
        $this->numeric = (boolean) $numeric;
        if($this->numeric)
        {
        	$this->pw_regex['pw_error_no_number']= "/\d/";
        } else {
        	unset($this->pw_regex['pw_error_no_number']);
        }
    }
    public function pw_set_needsSymbols($symbols=false)
    {
        $this->symbols = (boolean) $symbols;
        if($this->symbols)
        {
        	$this->pw_regex['pw_error_no_symbols']= "/^(?=.+[^\w\s]).+$/";
        } else {
        	unset($this->pw_regex['pw_error_no_symbols']);
        }
    }
    public function pw_set_needsMixedCase($mixedCase=false)
    {
        $this->mixedCase = (boolean) $mixedCase;
        if($this->mixedCase)
        {
        	$this->pw_regex['pw_error_not_mixed_case']= "/^(?=.*[a-z])(?=.*[A-Z]).+$/";
        } else {
        	unset($this->pw_regex['pw_error_not_mixed_case']);
        }
    }

    /**
     * Score a password for complexity.
     *
     * @return integer between 0 and 100(!)
     */
    public function pw_score($password="")
    {
    	$score = 0;
    	$pw_len=strlen($password);
    	if ($pw_len > 0) {
			$score_len=max($pw_len,6);
			$pa=str_split($password);		//all the characters, in an array.
			$ar = array_unique($pa);		//just the unique characters, sorted.
			$asize=count($ar);				//the size of the alphabet being used.
			$min=0.000000001;

			//one score is set by considering the size of the unique by the size of the password.
			//so, a single repeated character should have a low score, and every letter being different is high.
			//therefore, where N is the number of chars in the password, and U being the number of unique letters,
			//we are looking at a result of (U/N)
			$score_a = $asize / $score_len; //the effect of duplicate characters should not be too high.

			$cc=preg_replace('/(.)./','\1',bin2hex($password)); //This is the character class string.
			$cz=gzdeflate($cc,9,ZLIB_ENCODING_RAW);				//deflate of char. class string.
			$score_b = max($min,min($score_len,strlen($cz)-5) / $score_len);	//5 is the minimum value of gzdeflate length.

			$pz=gzdeflate($password,9,ZLIB_ENCODING_RAW);			//deflate of password
			$score_c = max($min,min($score_len,strlen($pz)-5) / $score_len);	//5 is the minimum value of gzdeflate length.

			$normal = 100 * sqrt($score_a * $score_b * $score_c);
			$score = (int) max(0,min($normal,100));
		}
     	return $score;
   }

    /**
     * Validate the password against all requirements
     *
     * @returns bool
     * @populates the errors array.
     */
    public function pw_validate($password="",&$err_arr)
    {
    	if ($this->minLength > 0) {
				if (mb_strlen($password) < $this->minLength) {
    			$err_arr[]='pw_error_length_too_short';
    		}
    	}
    	if ($this->minScore > 0) {
    		if ($this->pw_score($password) < $this->minScore) {
    			$err_arr[]='pw_error_low_score';
    		}
    	}

 		foreach($this->pw_regex as $sig => $reg) {
 			if (!(boolean) preg_match($reg,$password)) {
    			$err_arr[]=$sig;
 			}
 		}
        return count($err_arr) == 0;
    }

	private function initialise() {
//translations
		$en = array(
			"pw_error_length_too_short"=>" Passwords must be more than six characters.",
			"pw_error_not_mixed_case"=>" Passwords must include both upper and lower case characters.",
			"pw_error_no_number"=>" Passwords must include at least one digit.",
			"pw_error_no_alpha"=>" Passwords must include at least one letter.",
            "pw_error_no_symbols"=>" Password must contain at least one symbol. E.g. $",
            "pw_error_low_score"=> " Password strength is too low"
		);

		$de = array(
			'pw_error_length_too_short'=> "Das Passwort muss aus mehr als sechs Zeichen bestehen.",
            "pw_error_not_mixed_case"=>" Passwörter müssen sowohl Groß- als auch Kleinbuchstaben enthalten.",
            "pw_error_no_number"=>" Passwords must include at least one digit.",
            "pw_error_no_alpha"=>" Passwörter müssen mindestens eine Ziffer enthalten.",
            "pw_error_no_symbols"=>" Das Passwort muss mindestens ein Symbol enthalten.  z.B. $",
            "pw_error_low_score"=> " Passwortstärke  ist zu gering."
		);


        $es = array(
            'pw_error_length_too_short'=> "La contraseña debe tener al menos seis caracteres.",
            "pw_error_not_mixed_case"=>" Las contraseñas deben incluir mayúsculas y minúsculas.",
            "pw_error_no_number"=>" Las contraseñas deben incluir al menos un dígito.",
            "pw_error_no_alpha"=>" Las contraseñas deben incluir al menos una letra.",
            "pw_error_no_symbols"=>" La contraseña debe contener al menos un símbolo. Por ejemplo. $",
            "pw_error_low_score"=> " La seguridad de contraseña es demasiado baja."
        );

		Dict::set($en,'en');
		Dict::set($de,'de');
        Dict::set($es,'es');
	}


}
