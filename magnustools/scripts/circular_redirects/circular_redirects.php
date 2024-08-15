#!/usr/bin/php
<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

require_once('/data/project/magnustools/vendor/autoload.php');
require_once('/data/project/magnustools/public_html/php/ToolforgeCommon.php') ;

class CircularRedirects {
	public $tfc ;
	public $wiki_logins ;

	public function __construct () {
		$this->tfc = new ToolforgeCommon ( 'circular_redirects' ) ;

		$ini_array = parse_ini_file('/data/project/magnustools/circularredirectsbot.ini');
		$this->bot_user_name = $ini_array['name'] ;
		$this->bot_user_password = $ini_array['pass'] ;
	}

	protected function get_link_pattern($rtitle) {
		$pattern = preg_quote($rtitle);
		$pattern = preg_replace('/[ _]/','[ _]',$pattern);
		if ( ucfirst($rtitle)!=lcfirst($rtitle) ) {
			$first_letter_up  = ucfirst($rtitle)[0];
			$first_letter_low = lcfirst($rtitle)[0];
			$pattern = preg_replace("/^./","[{$first_letter_up}{$first_letter_low}]",$pattern);
		}
		$pattern = "/\[\[\s*({$pattern})\s*(|\|.*?)\]\]/";
		return $pattern;
	}

	public function update_db($wiki) {
		$dbt = $this->tfc->openDBtool("circular_redirects_p");
		$dbw = $this->tfc->openDBwiki($wiki);
		$total_count = 0 ;
		$offset = 0 ;
		$batch_size = 1000 ;
		$limit = 10000 ;
		$cache = [] ;
		while ( true ) {
			#print "{$offset}\n" ;
			$sql = "SELECT p1.page_title FROM page p1,pagelinks
						WHERE p1.page_id=pl_from AND pl_namespace=0 AND pl_from_namespace=0
						AND EXISTS (SELECT * FROM page p2,redirect WHERE p2.page_namespace=0 
							AND p2.page_title=pl_title AND rd_from=p2.page_id AND rd_namespace=0 AND rd_title=p1.page_title)
						LIMIT {$limit} OFFSET {$offset}";
			$result = $this->tfc->getSQL ( $dbw , $sql ) ;
			$old_total = $total_count ;
			while($o = $result->fetch_object()){
				$total_count++ ;
				$page = $dbt->real_escape_string ( $o->page_title ) ;
				$cache[$page] = "('{$wiki}','{$page}')";
				if ( count($cache)>$batch_size) $this->flushUpdate($dbt,$cache);
				$offset++;
			}
			if ( $old_total+$batch_size > $total_count ) break ;
		}
		$this->flushUpdate($dbt,$cache);
		$ts = $this->tfc->getCurrentTimestamp();
		$ts = substr ( $ts , 0 , 8 ) ; # Day
		$sql = "INSERT IGNORE INTO `stats` (`wiki`,`date`,`total`) VALUES ('{$wiki}','{$ts}',{$total_count})" ;
		$this->tfc->getSQL ( $dbt , $sql ) ;
	}

	protected function flushUpdate(&$dbt,&$cache) {
		if ( count($cache)==0 ) return ;
		$sql = "INSERT IGNORE INTO `pages` (`wiki`,`page`) VALUES ".implode(',',$cache) ;
		$this->tfc->getSQL ( $dbt , $sql ) ;
		$cache = [] ;
	}

	public function simple_fix($wiki,$page,$redirects) {
		if ( count($redirects) == 0 ) return ;
		$page_nice = ucfirst(preg_replace('/_/',' ',$page));
		$wt = $this->tfc->getWikiPageText($wiki,$page);
		if ( preg_match('/\{\{\s*[Bb]ots\s*\}\}/',$wt) ) return false ; # {{bots}}
		$wt_orig = $wt ;
		foreach ( $redirects AS $rtitle ) {
			if ( preg_match("/^[A-Za-z][a-z]+:/",$rtitle) ) continue ; # TODO skipping redirects with namespaces
			$pattern = $this->get_link_pattern($rtitle);
			if ( !preg_match_all($pattern,$wt,$matches,PREG_SET_ORDER|PREG_OFFSET_CAPTURE) ) continue ;
			while (count($matches)>0) {
				$m = array_pop($matches);
				$start = $m[0][1]*1;
				$end = $start + strlen($m[0][0]);
				if ( strlen($m[2][0])>0 ) { # Replace with link text
					$wt = substr($wt,0,$start).$m[2][0].substr($wt,$end);
				} else { # Replace with linked page
					$wt = substr($wt,0,$start).$m[1][0].substr($wt,$end);
				}
			}
		}
		if ( $wt!=$wt_orig ) {
			$ret = true ;
			try {
				$this->setPageText($wiki,$page,$wt,"Replacing circular redirect link (redirect links back to this page) with plain text");
			} catch (Exception $ex) {
				$ret = false ; # Some error
			}
		} else {
			$ret = false ;
		}
		return $ret ;
	}

	protected function loginToWiki ( $api_url , $wiki_user , $wiki_pass ) {
		if ( isset($this->wiki_logins[$api_url]) ) return ;

		$api = new \Mediawiki\Api\MediawikiApi( $api_url );
		$api->login( new \Mediawiki\Api\ApiUser( $wiki_user, $wiki_pass ) );
		$services = new \Mediawiki\Api\MediawikiFactory( $api );

		$this->wiki_logins[$api_url] = (object) [ 'api' => $api , 'services' => $services ] ;
	}

	protected function getApiUrl($wiki) {
		$server = $this->tfc->getWebserverForWiki ( $wiki ) ;
		$api_url = "https://{$server}/w/api.php" ;
		return $api_url;
	}

	protected function getWikiServices($wiki) {
		$api_url = $this->getApiUrl($wiki);
		$this->loginToWiki($api_url,$this->bot_user_name,$this->bot_user_password);
		if ( !isset($this->wiki_logins[$api_url]) ) throw new Exception(__METHOD__.": Not logged in to {$api_url}" ) ;
		$services = $this->wiki_logins[$api_url]->services ;
		return $services;
	}

	protected function setPageText ( $wiki , $page_title , $new_wikitext , $summary = '' ) {
		$services = $this->getWikiServices($wiki);
		$content = new \Mediawiki\DataModel\Content( $new_wikitext );
		$page_title = str_replace(' ','_',$page_title) ;

		$page = $services->newPageGetter()->getFromTitle( $page_title );
		$revisions = (array) $page->getRevisions() ;
		$revision = array_pop ( $revisions ) ;
		$revision = array_pop ( $revision ) ;
		if ( $revision == null ) throw new Exception(__METHOD__.": Not creating new page" ) ;

		$old_wikitext = $revision->getContent()->getData() ;
		if ( trim($old_wikitext) == trim($new_wikitext) ) return ; # No change, no edit

		$ei = new \Mediawiki\DataModel\EditInfo($summary,\Mediawiki\DataModel\EditInfo::MINOR);
		$revision = new \Mediawiki\DataModel\Revision( $content, $page->getPageIdentifier() , null , $ei );
		$services->newRevisionSaver()->save( $revision );
	}

	public function fix_next_page($wiki) {
		$dbt = $this->tfc->openDBtool("circular_redirects_p");
		$sql = "SELECT * FROM `pages` WHERE `wiki`='{$wiki}' AND `simple_case_checked`=0 LIMIT 1" ;
		$result = $this->tfc->getSQL ( $dbt , $sql ) ;
		$ret = false ;
		if($o = $result->fetch_object()){
			$redirects = [] ;
			$page_safe = $dbt->real_escape_string ( $o->page ) ;
			$sql = "SELECT DISTINCT p2.page_title FROM page p1,pagelinks,page p2,redirect
				WHERE p1.page_id=pl_from AND pl_namespace=0 AND pl_from_namespace=0 AND p1.page_title='{$page_safe}'
				AND p2.page_namespace=0 AND p2.page_title=pl_title AND rd_from=p2.page_id AND rd_namespace=0 AND rd_title=p1.page_title" ;
			$dbw = $this->tfc->openDBwiki($wiki);
			$result = $this->tfc->getSQL ( $dbw , $sql ) ;
			while($o2 = $result->fetch_object()) $redirects[] = $o2->page_title;
			$ret = $this->simple_fix($wiki,$o->page,$redirects);
			if ( $ret ) {
				$server = $this->tfc->getWebserverForWiki($wiki);
				print "https://{$server}/wiki/".urlencode($o->page)."\n";
			}
			$sql = "UPDATE `pages` SET `simple_case_checked`=1 WHERE `wiki`='{$wiki}' AND `page`='{$page_safe}'" ;
			$this->tfc->getSQL ( $dbt , $sql ) ;
		} else {
			throw new Exception("No more candidates") ;
		}
		return $ret ;
	}

	public function simple_cases($wiki) {
		while ( true ) {
			try {
				$this->fix_next_page($wiki);
			} catch (Exception $ex) {
				print_r($ex);
				return ;
			}
		}		
	}
}

$cr = new CircularRedirects ;
if ( $argv[1] == 'update' ) $cr->update_db($argv[2]);
else if ( $argv[1] == 'simple' ) $cr->simple_cases($argv[2]);

?>