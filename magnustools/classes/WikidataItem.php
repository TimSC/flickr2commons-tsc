<?PHP

namespace Toolforge ;

class WikidataItem {

	public $q ;
	public $j ;
	public $wikidata_preferred_langs ;
	
	public function __construct ( $q = '' , $wikidata_preferred_langs = ['en','de','nl','fr','es','it','zh'] ) {
		global $wikidata_api_url ;
		$this->wikidata_preferred_langs = $wikidata_preferred_langs ;
		if ( $q != '' ) {
			if ( !preg_match('/^[PLM]\d+$/',$q) ) $q = 'Q' . preg_replace ( '/\D/' , '' , "$q" ) ;
			$this->q = $q ;
			$url = "$wikidata_api_url?action=wbgetentities&ids=$q&format=json" ;
			$j = json_decode ( file_get_contents ( $url ) ) ;
			$this->j = $j->entities->$q ;
		}
	}
	
	public function getQ () {
		return $this->q ;
	}
	
	public function getLabel ( $lang = '' , $strict = false ) {
		if ( !isset ( $this->j->labels ) ) return $this->q ;
		if ( isset ( $this->j->labels->$lang ) ) return $this->j->labels->$lang->value ; // Shortcut
		if ( $strict ) return $this->q ;
		
		$score = 9999 ;
		$best = $this->q ;
		foreach ( $this->j->labels AS $v ) {
			$p = array_search ( $v->language , $this->wikidata_preferred_langs ) ;
			if ( $p === false ) $p = 999 ;
			$p *= 1 ;
			if ( $p >= $score ) continue ;
			$score = $p ;
			$best = $v->value ;
		}
		return $best ;
	}

	public function getAliases ( $lang ) {
		$ret = [] ;
		if ( !isset($this->j->aliases) ) return $ret ;
		if ( !isset($this->j->aliases->$lang) ) return $ret ;
		foreach ( $this->j->aliases->$lang AS $v ) $ret[] = $v->value ;
		return $ret ;
	}
	
	public function getAllAliases () {
		$ret = [] ;
		if ( !isset($this->j->aliases) ) return $ret ;
		foreach ( $this->j->aliases AS $lang => $al ) {
			foreach ( $al AS $v ) $ret[$lang][] = $v->value ;
		}
		return $ret ;
	}
	
	public function getDesc ( $lang = '' , $strict = false ) {
		if ( !isset ( $this->j->descriptions ) ) return '' ;
		if ( isset ( $this->j->descriptions->$lang ) ) return $this->j->descriptions->$lang->value ; // Shortcut
		if ( $strict ) return '' ;
		
		$score = 9999 ;
		$best = '' ;
		foreach ( $this->j->descriptions AS $v ) {
			$p = array_search ( $v->language , $this->wikidata_preferred_langs ) ;
			if ( $p === false ) $p = 999 ;
			if ( $p*1 >= $score*1 ) continue ;
			$score = $p ;
			$best = $v->value ?? 0 ;
		}
		return $best ;
	}
	
	public function getTarget ( $claim ) {
		$nid = 'numeric-id' ;
		if ( !isset($claim->mainsnak) ) return false ;
		if ( !isset($claim->mainsnak->datavalue) ) return false ;
		if ( !isset($claim->mainsnak->datavalue->value) ) return false ;
		if ( !isset($claim->mainsnak->datavalue->value->id) ) return false ;
		return $claim->mainsnak->datavalue->value->id ;
	}
	
	public function hasLabel ( $label ) {
		if ( !isset($this->j) ) return false ;
		if ( !isset($this->j->labels) ) return false ;
		foreach ( $this->j->labels AS $lab ) {
			if ( $lab->value == $label ) return true ;
		}
		return false ;
	}
	
	public function hasLabelInLanguage ( $lang ) {
		if ( !isset($this->j) ) return false ;
		if ( !isset($this->j->labels) ) return false ;
		if ( !isset($this->j->labels->$lang) ) return false ;
		return true ;
	}

	public function hasDescriptionInLanguage ( $lang ) {
		if ( !isset($this->j) ) return false ;
		if ( !isset($this->j->descriptions) ) return false ;
		if ( !isset($this->j->descriptions->$lang) ) return false ;
		return true ;
	}

	public function hasExternalSource ( $claim ) {
		return false ; // DUMMY
	}

	public function sanitizeP ( $p ) {
		return 'P' . preg_replace ( '/\D/' , '' , "$p" ) ;
	}

	public function sanitizeQ ( &$q ) {
		if ( preg_match ( '/^[0-9 ]+$/' , $q ) ) $q = 'Q'.preg_replace('/\D/','',"$q") ;
	}
	
	public function getStrings ( $p ) {
		$ret = [] ;
		if ( !$this->hasClaims($p) ) return $ret ;
		$claims = $this->getClaims($p) ;
		foreach ( $claims AS $c ) {
			if ( !isset($c->mainsnak) ) continue ;
			if ( !isset($c->mainsnak->datavalue) ) continue ;
			if ( !isset($c->mainsnak->datavalue->value) ) continue ;
			if ( !isset($c->mainsnak->datavalue->type) ) continue ;
			if ( $c->mainsnak->datavalue->type != 'string' ) continue ;
			$ret[] = $c->mainsnak->datavalue->value ;
		}
		return $ret ;
	}
	
	public function getFirstString ( $p ) {
		$strings = $this->getStrings ( $p ) ;
		if ( count($strings) == 0 ) return '' ;
		return $strings[0] ;
	}

	public function getClaims ( $p ) {
		$ret = [] ;
		$claims = $this->q[0] == 'M' ? 'statements' : 'claims' ; # Commons MediaInfo fallback
		$p = $this->sanitizeP ( $p ) ;
		if ( !isset($this->j) ) return $ret ;
		if ( !isset($this->j->$claims) ) return $ret ;
		if ( !isset($this->j->$claims->$p) ) return $ret ;
		return $this->j->$claims->$p ;
	}
	
	public function hasTarget ( $p , $q ) {
		$this->sanitizeP ( $p ) ;
		$this->sanitizeQ ( $q ) ;
		$claims = $this->getClaims($p) ;
		foreach ( $claims AS $c ) {
			$target = $this->getTarget($c) ;
			if ( $target == $q ) return true ;
		}
		return false ;
	}
	
	public function hasClaims ( $p ) {
		return count($this->getClaims($p)) > 0 ;
	}
	
	public function getSitelink ( $wiki ) {
		if ( !isset($this->j) ) return ;
		if ( !isset($this->j->sitelinks) ) return ;
		if ( !isset($this->j->sitelinks->$wiki) ) return ;
		return $this->j->sitelinks->$wiki->title ;
	}
	
	public function getSitelinks () {
		$ret = [] ;
		if ( !isset($this->j) ) return $ret ;
		if ( !isset($this->j->sitelinks) ) return $ret ;
		foreach ( $this->j->sitelinks AS $wiki => $x ) $ret[$wiki] = $x->title ;
		return $ret ;
	}
	
	public function getProps () {
		$ret = [] ;
		if ( !isset($this->j) ) return $ret ;
		if ( !isset($this->j->claims) ) return $ret ;
		foreach ( $this->j->claims AS $p => $v ) $ret[] = $p ;
		return $ret ;
	}
	
	public function getClaimByID ( $id ) {
		if ( !isset($this->j) ) return ;
		if ( !isset($this->j->claims) ) return ;
		foreach ( $this->j->claims AS $p => $v ) {
			foreach ( $v AS $dummy => $claim ) {
				if ( $claim->id == $id ) return $claim ;
			}
		}
	}


	public function getSnakValueQS ( $snak ) {
		if ( !isset($snak) or !isset($snak->datavalue) ) {
			// Skip => error message
		} else if ( $snak->datatype == 'string' and $snak->datavalue->type == 'string' ) {
			return '"' . $snak->datavalue->value . '"' ;
		} else if ( $snak->datatype == 'external-id' and $snak->datavalue->type == 'string' ) {
			return '"' . $snak->datavalue->value . '"' ;
		} else if ( $snak->datatype == 'time' and $snak->datavalue->type == 'time' ) {
			return $snak->datavalue->value->time.'/'.$snak->datavalue->value->precision ;
		} else if ( $snak->datatype == 'wikibase-item' and $snak->datavalue->type == 'wikibase-entityid' ) {
			return $snak->datavalue->value->id ;
		}

		if ( 0 ) { // Debug output
			print "Cannot parse snak value:\n" ;
			print_r ( $snak ) ;
			print "\n\n" ;
		}
		return '' ;
	}

	public function statementQualifiersToQS ( $statement ) {
		$ret = [] ;
		$qo = 'qualifiers-order' ;
		if ( !isset($statement->qualifiers) or !isset($statement->$qo) ) return $ret ;
		foreach ( $statement->$qo AS $qual_prop ) {
			if ( !isset($statement->qualifiers->$qual_prop) ) continue ;
			foreach ( $statement->qualifiers->$qual_prop AS $x ) {
				$v = $this->getSnakValueQS ( $x ) ;
				if ( $v == '' ) continue ;
				$ret[] = "$qual_prop\t$v" ;
			}
		}
		return $ret ;
	}

	public function statementReferencesToQS ( $statement ) {
		$ret = [] ;
		if ( !isset($statement->references) ) return $ret ;
	
		$so = 'snaks-order' ;
		foreach ( $statement->references AS $ref ) {
			$ref_ret = [] ;
			foreach ( $ref->$so AS $snak_prop ) {
				if ( !isset($ref->snaks->$snak_prop) ) continue ;
				foreach ( $ref->snaks->$snak_prop AS $ref_snak ) {
					$v = $this->getSnakValueQS ( $ref_snak ) ;
					if ( $v == '' ) continue ;
					$ref_ret[] = "S" . preg_replace ( '/\D/' , '' , $snak_prop ) . "\t$v" ;
				}
			}
			if ( count($ref_ret) > 0 ) $ret[] = $ref_ret ;
		}
	
		return $ret ;
	}

	
}

?>