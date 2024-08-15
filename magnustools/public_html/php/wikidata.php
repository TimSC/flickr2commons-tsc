<?PHP

$wikidata_api_url = 'https://www.wikidata.org/w/api.php' ;

if ( __NAMESPACE__ == 'Toolforge' ) {
} else require_once ( __DIR__ . '/../../classes/WikidataItem.php' ) ;

class WikidataItemList {

	public $testing = false ;
	protected $items = [] ;
	public $testing_output = [] ;
	protected $wikibase_api = '' ;

	public function __construct ( $wikibase_api = '' ) {
		$this->wikibase_api = $wikibase_api ;
	}

	public function sanitizeQ ( &$q ) {
		if ( preg_match ( '/^(?:[PLM]\d+|L\d+-[FS]\d+)$/i' , "$q" ) ) {
			$q = strtoupper ( $q ) ;
		} else {
			$q = 'Q'.preg_replace('/\D/','',"$q") ;
		}
	}

	public function get_wikidata_api_url() {
		if ( $this->wikibase_api != '' ) return $this->wikibase_api ;
		global $wikidata_api_url ;
		if ( !isset($wikidata_api_url) ) return 'https://www.wikidata.org/w/api.php' ;
		return $wikidata_api_url ;
	}
	
	public function updateItems ( $list ) {
		$last_revs = [] ;
		foreach ( $list AS $q ) {
			if ( !$this->hasItem ( $q ) ) continue ;
			$this->sanitizeQ ( $q ) ;
			if ( isset($this->items[$q]->j->lastrevid) ) $last_revs[$q] = $this->items[$q]->j->lastrevid ;
			unset ( $this->items[$q] ) ;
		}
		$this->loadItems ( $list ) ;

		return ;
		// Paranoia
		foreach ( $list AS $q ) {
			if ( !$this->hasItem ( $q ) ) continue ;
			$this->sanitizeQ ( $q ) ;
			if ( $last_revs[$q] == $this->items[$q]->j->lastrevid ) print "<pre>WARNING! Caching issue with $q</pre>" ;
		}
	}

	public function updateItem ( $q ) {
		$this->updateItems ( [$q] ) ;
	}
	
	protected function parseEntities ( $j ) {
		foreach ( $j->entities AS $q => $v ) {
			if ( isset ( $this->items[$q] ) ) continue ; // Paranoia
			if ( __NAMESPACE__ == 'Toolforge' ) {
				$this->items[$q] = new WikidataItem ;
			} else {
				$this->items[$q] = new Toolforge\WikidataItem ;
			}
			$this->items[$q]->q = $q ;
			$this->items[$q]->j = $v ;
		}
	}
		
    function loadItems ( $list ) {
    	$qs = [ [] ] ;
    	foreach ( $list AS $q ) {
    		$this->sanitizeQ($q) ;
    		if ( !preg_match ( '/^[A-Z]\d+/' , $q ) ) continue ; # Paranoia
	    	if ( isset($this->items[$q]) ) continue ;
	    	if ( count($qs[count($qs)-1]) == 50 ) $qs[] = [] ;
    		$qs[count($qs)-1][] = $q ;
    	}
    	
    	if ( count($qs) == 1 and count($qs[0]) == 0 ) return ;
    	
    	$urls = [] ;
    	foreach ( $qs AS $k => $sublist ) {
    		if ( count ( $sublist ) == 0 ) continue ;
			$url = $this->get_wikidata_api_url()."?action=wbgetentities&ids=" . implode('|',$sublist) . "&format=json" ;
			$urls[$k] = $url ;
    	}
#print_r ( $urls ) ;
    	$res = $this->getMultipleURLsInParallel ( $urls ) ;
    	
		foreach ( $res AS $k => $txt ) {
			$j = json_decode ( $txt ) ;
			if ( !isset($j) or !isset($j->entities) ) continue ;
			$this->parseEntities ( $j ) ;
		}
    }
    
    function loadItem ( $q ) {
    	return $this->loadItems ( [ $q ] ) ;
    }
    
    function getItem ( $q ) {
    	$this->sanitizeQ($q) ;
    	if ( !isset($this->items[$q]) ) return ;
    	return $this->items[$q] ;
    }
    
    function getItemJSON ( $q ) {
    	$this->sanitizeQ($q) ;
    	return $this->items[$q]->j ;
    }
    
    function hasItem ( $q ) {
    	$this->sanitizeQ($q) ;
    	return isset($this->items[$q]) ;
    }

    public function getItemLabels ( $list , $language = 'en' ) {
    	$ret = [] ;
    	$list = array_unique($list);

    	$qs = [ [] ] ;
    	foreach ( $list AS $q ) {
    		$this->sanitizeQ($q) ;
    		if ( !preg_match ( '/^[A-Z]\d+/' , $q ) ) continue ; # Paranoia
/*
	    	if ( isset($this->items[$q]) ) { # Have the entire item already
	    		$ret[$q] = $this->items[$q]->getLabel($language,true) ;
	    		continue ;
	    	}
*/
	    	if ( count($qs[count($qs)-1]) == 50 ) $qs[] = [] ;
    		$qs[count($qs)-1][] = $q ;
    	}
    	if ( count($qs) == 1 and count($qs[0]) == 0 ) return $ret ;

    	$urls = [] ;
    	foreach ( $qs AS $k => $sublist ) {
    		if ( count ( $sublist ) == 0 ) continue ;
			$url = $this->get_wikidata_api_url()."?action=wbformatentities&ids=" . implode('|',$sublist) . "&uselang={$language}&format=json" ;
			$urls[$k] = $url ;
    	}
    	$res = $this->getMultipleURLsInParallel ( $urls ) ;
    	
		foreach ( $res AS $k => $txt ) {
			$j = json_decode ( $txt ) ;
			if ( !isset($j) or !isset($j->wbformatentities) ) continue ;
			foreach ( $j->wbformatentities AS $k => $v ) {
				$ret[$k] = strip_tags($v) ;
			}
		}
		return $ret ;
    }
	
	public function loadItemByPage ( $page , $wiki ) {
		$page = urlencode ( ucfirst ( str_replace ( ' ' , '_' , trim($page) ) ) ) ;
		$url = $this->get_wikidata_api_url() . "?action=wbgetentities&sites=$wiki&titles=$page&format=json" ;
		$j = json_decode ( file_get_contents ( $url ) ) ;
		if ( !isset($j) or !isset($j->entities) ) return false ;
		$this->parseEntities ( $j ) ;
		foreach ( $j->entities AS $q => $dummy ) {
			return $q ;
		}
	}

	protected function getMultipleURLsInParallel ( $urls ) {
		$ret = [] ;
	
		$batch_size = 50 ;
		$batches = [ [] ] ;
		foreach ( $urls AS $k => $v ) {
			if ( count($batches[count($batches)-1]) >= $batch_size ) $batches[] = [] ;
			$batches[count($batches)-1][$k] = $v ;
		}
	
		foreach ( $batches AS $batch_urls ) {
	
			$mh = curl_multi_init();
			curl_multi_setopt  ( $mh , CURLMOPT_PIPELINING , CURLPIPE_MULTIPLEX ) ;
			$ch = [] ;
			foreach ( $batch_urls AS $key => $value ) {
				$ch[$key] = curl_init($value);
		//		curl_setopt($ch[$key], CURLOPT_NOBODY, true);
		//		curl_setopt($ch[$key], CURLOPT_HEADER, true);
				curl_setopt($ch[$key], CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch[$key], CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch[$key], CURLOPT_SSL_VERIFYHOST, false);
				curl_multi_add_handle($mh,$ch[$key]);
			}
	
			do {
				curl_multi_exec($mh, $running);
				curl_multi_select($mh);
			} while ($running > 0);
	
			foreach(array_keys($ch) as $key){
				$ret[$key] = curl_multi_getcontent($ch[$key]) ;
				curl_multi_remove_handle($mh, $ch[$key]);
			}
	
			curl_multi_close($mh);
		}
	
		return $ret ;
	}

}

?>