<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

require_once('/data/project/magnustools/public_html/php/ToolforgeCommon.php') ;

class Issue {
	const ZERO_TIME = '0000-00-00 00:00:00' ;
	const FIELDS = ['label','status','date_created','date_last','site','url','description_text_id','tool','priority'] ;

	protected $issue_id ;
	protected $label ;
	protected $status = 'OPEN' ;
	protected $date_created = self::ZERO_TIME ;
	protected $date_last = self::ZERO_TIME ;
	protected $site = 'WIKI' ;
	protected $url = '' ;
	protected $description_text_id = 0 ;
	protected $description = '' ;
	protected $tool = 0 ;
	protected $priority = 'NORMAL' ;

	public function id() { return $this->issue_id * 1 ; }
	public function label() { return $this->label ; }
	public function status() { return $this->status ; }
	public function date_created() { return $this->date_created ; }
	public function date_last() { return $this->date_last ; }
	public function site() { return $this->site ; }
	public function url() { return $this->url ; }
	public function description() { return $this->description ; }
	public function description_text_id() { return $this->description_text_id * 1 ; }
	public function tool() { return $this->tool * 1 ; }
	public function priority() { return $this->priority ; }

	public function set_status ( $new_status ) { $this->status = trim(strtoupper($new_status)) ; }

	public static function new_from_object ( $o ) {
		$ret = new self ;
		$ret->fill_from_object($o) ;
		return $ret ;
	}

	public static function new_from_id ( $issue_id , $buggregator ) {
		$issue_id *= 1 ;
		$sql = "SELECT * FROM `vw_issue` WHERE `id`={$issue_id}" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) return Issue::new_from_object ( $o ) ;
		throw Exception("No issue with ID '{$issue_id}'!") ;
	}

	public function get_or_create_issue_id ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function extract_times () {
		# NEEDS TO RETURN AN ARRAY!
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function construct_url ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	public function determine_tool ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function get_associated_urls ( $buggregator ) {
		# DEFAULT: Scrape description for URLs, change as required!
		if ( preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $this->description, $m) ) {
			return $m[0] ;
		}
		return [] ;
	}

	protected function get_associated_users ( $buggregator ) {
		throw new Exception("{__METHOD__} should never be called directly!") ;
	}

	protected function fill_from_object ( $o ) {
		if ( !isset($o->id) ) throw new Exception("{__METHOD__}: `id` not in given object") ;
		$this->issue_id = $o->id ;
		foreach ( self::FIELDS AS $field ) {
			if ( !isset($o->$field) ) throw new Exception("{__METHOD__}: Field {$field} not in given object") ;
			$this->$field = $o->$field ;
		}
	}

	public function set_times () {
		$times = $this->extract_times() ;
		foreach ( $times as $time ) {
			if ( $this->date_created==self::ZERO_TIME or $this->date_created>$time ) $this->date_created = $time ;
			if ( $this->date_last<$time ) $this->date_last = $time ;
		}
	}

	protected function add_associated_urls ( $buggregator ) {
		$urls = $this->get_associated_urls ( $buggregator ) ;
		foreach ( $urls as $url ) {
			$url = $buggregator->escape ( $url ) ;
			$sql = "INSERT IGNORE INTO `url_issue` (`url`,`issue_id`) VALUES ('{$url}',{$this->issue_id})" ;
			$buggregator->getSQL ( $sql ) ;
		}
	}

	protected function add_associated_users ( $buggregator ) {
		$user_id2role = $this->get_associated_users ( $buggregator ) ;
		foreach ( $user_id2role AS $user_id => $role ) {
			$user_id *= 1 ;
			$role = $buggregator->escape ( $role ) ;
			$sql = "INSERT IGNORE INTO `user_issue` (`user_id`,`issue_id`,`role`) VALUES ({$user_id},{$this->issue_id},'{$role}')" ;
			$buggregator->getSQL ( $sql ) ;
		}
	}

	static public function determine_tool_from_text ( $text , $buggregator ) {
		$ret = 0 ;
		$toolname2id = $buggregator->get_unique_tool_name_ids() ;
		$candidate_tools = [] ;
		foreach ( $toolname2id as $tool_name => $tool_id ) {
			$pattern = "|\b{$tool_name}\b|i" ;
			if ( !preg_match($pattern,$text,$m) ) continue ;
			$candidate_tools[] = $tool_id ;
		}
		$candidate_tools = array_values ( array_unique($candidate_tools) ) ;
		if ( count($candidate_tools) == 1 ) {
			$ret = $candidate_tools[0] * 1 ;
		}
		return $ret ;
	}

	protected function create_as_new_issue ( $buggregator ) {
		$this->set_times() ;
		$this->construct_url ( $buggregator ) ;
		$this->determine_tool ( $buggregator ) ;

		$this->description_text_id = $buggregator->get_or_create_text_id ( $this->description ) ;

		$values = [] ;
		foreach ( self::FIELDS AS $field ) $values[] = $buggregator->escape($this->$field);
		$fields = implode('`,`',self::FIELDS) ;
		$values = implode("','",$values) ;
		$sql = "INSERT IGNORE INTO `issue` (`{$fields}`) VALUES ('{$values}')" ;
		$buggregator->getSQL ( $sql ) ;
		$this->issue_id = $buggregator->last_insert_id() ;
		$this->add_associated_urls ( $buggregator ) ;
		$this->add_associated_users ( $buggregator ) ;
		return $this->issue_id ;
	}

	public function update_in_database ( $buggregator , $fields = self::FIELDS ) {
		if ( $this->issue_id == 0 ) throw new Exception("{__METHOD__}: No issue ID");
		$values = [] ;
		foreach ( $fields AS $field ) $values[] = "`{$field}`='".$buggregator->escape($this->$field)."'";
		$sql = "UPDATE `issue` SET " . implode(',',$values) . " WHERE `id`={$this->issue_id}" ;
		$buggregator->getSQL ( $sql ) ;
	}
}

class WikidataIssue extends Issue {
	protected $tmp_authors = [] ;
	protected $tmp_urls = [] ;

	public static function new_from_topic ( $topic ) {
		$ret = new self ;
		foreach ( $topic AS $k => $v ) {
			$ret->$k = $v ;
		}
		return $ret ;
	}

	protected function extract_times () { return [] ; }
	protected function construct_url ( $buggregator ) {}
	public function determine_tool ( $buggregator ) {}

	protected function get_associated_urls ( $buggregator ) {
		return $this->tmp_urls ;
	}

	protected function get_associated_users ( $buggregator ) {
		$user_id2role = [] ;
		foreach ( $this->tmp_authors AS $username ) {
			$user = new User ( $username , 'WIKI' ) ;
			$user_id = $user->get_or_create_user_id ( $buggregator ) ;
			$user_id2role[$user_id] = 'UNKNOWN' ;
		}
		return $user_id2role ;
	}

	public function get_or_create_issue_id ( $buggregator ) {
		if ( isset($this->issue_id) ) return $this->issue_id ; # Already has an issue ID

		# Paranoia
		if ( !isset($this->url) ) throw new Exception("{__METHOD__}: No url set");

		# Check if exists
		$url = $buggregator->escape ( $this->url ) ;
		$sql = "SELECT * FROM `issue` WHERE `site`='{$this->site}' AND `url`='{$url}'" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) {
			$this->issue_id = $o->id ;
			return $this->issue_id ;
		}

		# Create new issue
		return $this->create_as_new_issue($buggregator) ;
	}
}

class GitIssue extends Issue {
	protected $git_id ;
	protected $git_repo_id ;
	protected $tmp_user_names = [] ;

	protected function extract_times () { return [] ; }
	protected function construct_url ( $buggregator ) {}
	public function determine_tool ( $buggregator ) {}

	protected function get_associated_users ( $buggregator ) {
		$user_id2role = [] ;
		foreach ( $this->tmp_user_names AS $username ) {
			$user = new User ( $username , $this->site ) ;
			$user_id = $user->get_or_create_user_id ( $buggregator ) ;
			if ( count($user_id2role) == 0 ) $user_id2role[$user_id] = 'CREATOR' ;
			else if ( !isset($user_id2role[$user_id]) ) $user_id2role[$user_id] = 'UNKNOWN' ;
		}
		return $user_id2role ;
	}

	public function get_or_create_issue_id ( $buggregator ) {
		if ( isset($this->issue_id) ) return $this->issue_id ; # Already has an issue ID

		# Paranoia
		if ( !isset($this->git_id) ) throw new Exception("{__METHOD__}: No git_id set");
		if ( !isset($this->git_repo_id) ) throw new Exception("{__METHOD__}: No git_repo_id set");

		# Check if exists
		$sql = "SELECT * FROM vw_git_issue WHERE site='{$this->site}' AND git_id={$this->git_id} AND git_repo_id={$this->git_repo_id}" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) {
			$this->issue_id = $o->id ;
			return $this->issue_id ;
		}

		# Create new issue
		$this->create_as_new_issue($buggregator) ;
		
		# Create new wiki issue
		$sql = "INSERT IGNORE INTO `git_issue` (`issue_id`,`git_id`,`git_repo_id`) VALUES ({$this->issue_id},{$this->git_id},{$this->git_repo_id})" ;
		$buggregator->getSQL ( $sql ) ;
		return $this->issue_id ;
	}
}

class GithubIssue extends GitIssue {
	public static function new_from_json ( $git_repo , $j ) {
		$ret = new self ;
		$ret->label = $j->title ;
		$ret->url = $j->html_url ;
		$ret->status = strtoupper($j->state) ;
		$ret->date_created = Buggregator::format_time(strtotime($j->created_at)) ;
		$ret->date_last = Buggregator::format_time(strtotime($j->updated_at)) ;
		$ret->description = $j->body ;
		$ret->tool = $git_repo->tool_id ;
		$ret->site = 'GITHUB' ;
		$ret->git_repo_id = $git_repo->id * 1 ;
		$ret->git_id = $j->number * 1 ;
		$ret->tmp_user_names = [ $j->user->login ] ;
		return $ret ;
	}
}

class BitbucketIssue extends GitIssue {
	public static function new_from_json ( $git_repo , $j ) {
		$ret = new self ;
		$ret->label = "{$j->title} [{$j->kind}/{$j->priority}]" ;
		$ret->url = $j->links->html->href ;
		$ret->status = 'OPEN' ; # default
		$ret->date_created = Buggregator::format_time(strtotime($j->created_on)) ;
		$ret->date_last = Buggregator::format_time(strtotime($j->updated_on)) ;
		$ret->description = $j->content->raw ;
		$ret->tool = $git_repo->tool_id ;
		$ret->site = 'BITBUCKET' ;
		$ret->git_repo_id = $git_repo->id * 1 ;
		$ret->git_id = $j->id * 1 ;
		# TODO creating user
		return $ret ;
	}
}

class WikiIssue extends Issue {
	protected $wikitext ;
	protected $wiki_page_id ;
	const DE_MONTHS = [ 'Mär' => 'Mar','Mai' => 'May','Okt' => 'Oct','Dez' => 'Dec'] ;

	public static function new_from_object ( $o ) {
		$ret = new self ;
		$ret->fill_from_object($o) ;
		return $ret ;
	}

	public static function new ( $wiki_page_id , $label , $wikitext ) {
		$ret = new WikiIssue ;
		$ret->label = trim($label) ;
		$ret->wikitext = trim($wikitext) ;
		$ret->wiki_page_id = $wiki_page_id * 1 ;
		return $ret ;
	}

	public function wikitext() { return $this->wikitext ; }
	public function wiki_page_id() { return $this->wiki_page_id * 1 ; }

	public function get_or_create_issue_id ( $buggregator ) {
		if ( isset($this->issue_id) ) return $this->issue_id ; # Already has an issue ID

		# Paranoia
		if ( !isset($this->label) ) throw new Exception("{__METHOD__}: No label set");
		if ( !isset($this->wiki_page_id) ) throw new Exception("{__METHOD__}: No wiki_page_id set");

		# Try page/label
		$safe_label = $buggregator->escape($this->label) ;
		$sql = "SELECT * FROM `issue`,`wiki_issue` WHERE `site`='WIKI' AND `label`='{$safe_label}' AND `issue_id`=`issue`.`id` AND `wiki_page_id`={$this->wiki_page_id}" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) {
			$this->issue_id = $o->id ;
			return $this->issue_id ;
		}

		# Create new issue
		$this->description = $buggregator->tfc->trimWikitextMarkup($this->wikitext);
		$this->create_as_new_issue($buggregator) ;
		
		# Create new wiki issue
		$wikitext = $buggregator->escape ( $this->wikitext ) ;
		$sql = "INSERT IGNORE INTO `wiki_issue` (`wiki_page_id`,`issue_id`,`wikitext`) VALUES ({$this->wiki_page_id},{$this->issue_id},'{$wikitext}')" ;
		$buggregator->getSQL ( $sql ) ;
		return $this->issue_id ;
	}

	protected function construct_url ( $buggregator ) {
		$this->url = '' ;
		$sql = "SELECT * FROM `wiki_page` WHERE `id`={$this->wiki_page_id}" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()){
			$server = $buggregator->tfc->getWebserverForWiki($o->wiki) ;
			if ( $server == '' ) return ;
			$page = $buggregator->tfc->urlEncode($o->page) ;
			$hash = $buggregator->tfc->urlEncode($this->label) ;
			$this->url = "https://{$server}/wiki/{$page}#{$hash}" ;
		}
	}

	public function determine_tool ( $buggregator ) {
		# Try wiki page hint
		$this->tool = 0 ;
		$sql = "SELECT * FROM `wiki_page` WHERE `id`={$this->wiki_page_id}" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) $this->tool = $o->tool_hint ;

		# Try issue label
		$this->tool = Issue::determine_tool_from_text ( $this->label , $buggregator ) ;
		if ( $this->tool != 0 ) return ;

		# Try issue description
		$this->tool = self::determine_tool_from_text ( $this->wikitext , $buggregator ) ;
	}

	protected function get_associated_urls ( $buggregator ) {
		if ( preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $this->wikitext, $m) ) {
			return $m[0] ;
		}
		return [] ;
	}

	protected function get_associated_users ( $buggregator ) {
		$user_id2role = [] ;
		if ( preg_match_all('#\[\[(User|Benutzer):(.+?)(\||\]\])#',$this->wikitext,$m) ) {
			$role = 'CREATOR' ; # Assuming first user name is the creator
			$usernames = $m[2] ;
			foreach ( $usernames AS $username ) {
				$username = trim ( $username ) ;
				if ( $username == '' ) continue ;
				$user = new User ( $username , 'WIKI' ) ;
				$user_id = $user->get_or_create_user_id ( $buggregator ) ;
				if ( isset($user_id2role[$user_id]) ) continue ;
				$user_id2role[$user_id] = $role ;
				$role = 'UNKNOWN' ;
			}
		}
		return $user_id2role ;
	}

	protected function extract_times () {
		$ret = [] ;
		if ( preg_match_all('|(\d{2}:\d{2}, \d{1,2}\.* \S+ \d{4}) \([A-Z]+\)|',$this->wikitext,$m) ) {
			foreach ( $m[1] AS $time ) {
				foreach ( self::DE_MONTHS AS $from => $to ) $time = str_replace($from,$to,$time) ;
				$time = strtotime($time) ;
				$ret[] = Buggregator::format_time($time) ;
			}
		}
		return $ret ;
	}

	protected function fill_from_object ( $o ) {
		parent::fill_from_object ( $o ) ;
		foreach ( ['wikitext','wiki_page_id'] AS $field ) {
			if ( !isset($o->$field) ) throw new Exception("{__METHOD__}: Field {$field} not in given object") ;
			$this->$field = $o->$field ;
		}
	}

}

class User {
	protected $user_id ;
	protected $username ;
	protected $site ;

	public function __construct ( $username , $site ) {
		$this->site = $site ;
		$this->username = $this->sanitize($username) ;
	}

	protected function sanitize ( $username ) {
		if ( $this->site == 'WIKI' ) {
			$username = str_replace ( '_' , ' ' , $username ) ;
		}
		$username = trim ( $username ) ;
		return $username ;
	}

	public function get_or_create_user_id ( $buggregator ) {
		$username = $buggregator->escape($this->username) ;
		$site = $buggregator->escape($this->site) ;
		$sql = "SELECT * FROM `user` WHERE `name`='{$username}' AND `site`='{$site}'" ;
		$result = $buggregator->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) {
			$this->user_id = $o->id ;
		} else {
			$sql = "INSERT IGNORE INTO `user` (`name`,`site`) VALUES ('{$username}','{$site}')" ;
			$buggregator->getSQL ( $sql ) ;
			$this->user_id = $buggregator->last_insert_id() ;
		}
		return $this->user_id ;
	}
}

class Buggregator {
	public $tfc ;
	protected $tool_db ;
	protected $toolname2id = [] ;

	const IGNORE_TOOL_NAMES = ['data'] ;

	public function __construct () {
		$this->tfc = new ToolforgeCommon ( 'buggregator' ) ;
		$this->tool_db = $this->tfc->openDBtool('buggregator') ;
	}

	static public function format_time ( $time ) {
		return date('Y-m-d H:i:s',$time);
	}

	protected function update_tool_description_from_toolhub($toolhub_object) {
		if ( !isset($toolhub_object->description) ) return ;
		$safe_name = $this->escape(trim($toolhub_object->name)) ;
		if ( $safe_name == '' ) return ;
		$safe_description = $this->escape(trim($toolhub_object->description)) ;
		if ( $safe_description == '' ) return ;
		$sql = "UPDATE `tool` SET `description`='{$safe_description}' WHERE `description`='' AND `toolhub`='{$safe_name}'" ;
		$this->getSQL ( $sql ) ;
	}

	protected function update_tool_keywords_from_toolhub($toolhub_object) {
		if ( !isset($toolhub_object->keywords) ) return ;
		$safe_name = $this->escape(trim($toolhub_object->name)) ;
		if ( $safe_name == '' ) return ;
		foreach ( $toolhub_object->keywords AS $keyword ) {
			$keyword_safe = $this->escape(trim($keyword)) ;
			$sql = "INSERT IGNORE INTO `keywords` (tool_id,keyword) SELECT id,'{$keyword_safe}' FROM tool WHERE `toolhub`='{$safe_name}'" ;
			$this->getSQL ( $sql ) ;
		}
	}

	public function toolhub_update() {
		$sql = "SELECT DISTINCT `toolhub` FROM `tool` WHERE `toolhub`!=''" ;
		$known_toolhub = [] ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()) $known_toolhub[$o->toolhub] = $o->toolhub ;
		$url = 'https://toolhub.wikimedia.org/api/search/tools/?format=json&ordering=-score&page=1&page_size=1000&q=%22Magnus+Manske%22' ;
		$j = json_decode ( file_get_contents($url) ) ;
		foreach ( $j->results AS $r ) {
			if ( isset($known_toolhub[$r->name]) ) { # We have that
				$this->update_tool_description_from_toolhub($r) ;
				$this->update_tool_keywords_from_toolhub($r) ;
				continue ;
			}

			$candidates = [] ;

			$safe_url = $this->escape(str_replace('http:','https:',$r->url)) ;
			$sql = "SELECT * FROM `tool` WHERE `url`='{$safe_url}' AND `toolhub`=''" ;
			while($o = $result->fetch_object()) $candidates[$o->id] = $o ;

			$safe_name = $this->escape($r->name) ;
			$safe_titles = [] ;
			$safe_titles[] = $this->escape($r->title) ;
			$safe_titles[] = $this->escape(str_replace(' ','_',$r->title)) ;
			$safe_titles[] = $this->escape(str_replace(' ','-',$r->title)) ;
			$safe_titles[] = $this->escape(str_replace(' ','',$r->title)) ;
			$safe_titles = "'".implode("','",$safe_titles)."'" ;
			#print "{$safe_name}: {$safe_titles}\n";
			$sql = "SELECT * FROM `tool` WHERE `name` IN ({$safe_titles}) AND `toolhub`=''" ;
			$result = $this->getSQL ( $sql ) ;
			while($o = $result->fetch_object()) $candidates[$o->id] = $o ;
			$candidates = array_values($candidates) ;
			#if ( $safe_name == 'mm_autodesc' ) print_r($candidates);
			if ( count($candidates) == 0 ) {
				if ( $safe_name!='mm_item_names' ) print "Not found: {$r->title} / {$r->name} : {$r->url}\n" ;
			} else if ( count($candidates) == 1 ) {
				$o = $candidates[0] ;
				$sql = "UPDATE `tool` SET `toolhub`='{$safe_name}' WHERE `id`={$o->id}" ;
				$this->getSQL ( $sql ) ;
				$this->update_tool_description_from_toolhub($r) ;
				$this->update_tool_keywords_from_toolhub($r) ;
			} else {
				print "More than one found: {$r->title} / {$r->name} : {$r->url}\n" ;
			}
		}
	}

	protected function update_from_wikipages() {
		$sql = "SELECT * FROM `wiki_page`" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()){
			$wikitext = $this->tfc->getWikiPageText($o->wiki,$o->page) ;
			$rows = explode ( "\n" , $wikitext ) ;
			$label = '' ;
			$wikitext = '' ;
			foreach ( $rows AS $row ) {
				if ( preg_match('|^\s*={2,}\s*(.+?)\s*={2,}\s*$|',$row,$m) ) {
					if ( $label != '' ) {
						$issue = WikiIssue::new($o->id,$label,$wikitext) ;
						$issue->get_or_create_issue_id($this);
					}
					$label = $m[1] ;
					$wikitext = '' ;
					continue ;
				}
				$wikitext .= "{$row}\n" ;
			}
			if ( $label != '' ) {
				$issue = WikiIssue::new($o->id,$label,$wikitext) ;
				$issue->get_or_create_issue_id($this);
			}
		}
	}

	public function get_unique_tool_name_ids() {
		if ( count($this->toolname2id) == 0 ) {
			$bad_names = self::IGNORE_TOOL_NAMES ;
			$sql = "SELECT group_concat(`id`)  AS `id`,lower(regexp_replace(`name`,'_',' ')) AS `name` from `tool` group by lower(regexp_replace(`name`,'_',' ')) having count(*)=1" ;
			$this->toolname2id = [] ;
			$result = $this->getSQL ( $sql ) ;
			while($o = $result->fetch_object()) {
				if ( isset($this->toolname2id[$o->name]) ) $bad_names[] = $o->name ; # Name collision
				$this->toolname2id[$o->name] = $o->id ;
			}
			foreach ( $bad_names AS $bad_name ) unset($this->toolname2id[$bad_name]) ;
		}
		return $this->toolname2id ;
	}

	protected function update_from_github () {
		$sql = "SELECT * FROM `git_repo` WHERE `repo_type`='GITHUB'" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()){
			$opts = [ "http" => ["method" => "GET","header" => "Accept: application/vnd.github.v3+json\r\n" ]];
			$context = stream_context_create($opts);
			$json = file_get_contents($o->api_issues_url, false, $context);
			$json = json_decode ( $json ) ;
			foreach ( $json AS $git_issue ) {
				$issue = GithubIssue::new_from_json ( $o , $git_issue ) ;
				$issue->get_or_create_issue_id ( $this ) ;
			}
		}
	}
	
	protected function update_from_bitbucket () {
		$sql = "SELECT * FROM `git_repo` WHERE `repo_type`='BITBUCKET'" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()){
			$url = "{$o->api_issues_url}/?pagelen=100" ;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$json = curl_exec($ch);
			$json = json_decode ( $json ) ;
			if ( !isset($json->values) ) continue ;
			foreach ( $json->values AS $git_issue ) {
				$issue = BitbucketIssue::new_from_json ( $o , $git_issue ) ;
				$issue->get_or_create_issue_id ( $this ) ;
			}
		}
	}

	protected function update_from_wikidata() {
		$vtl = 'view-topiclist' ;
		$top = 'topic-of-post' ;
		$limit = 100 ;
		$url = "https://www.wikidata.org/w/api.php?action=flow&submodule=view-topiclist&page=User%20Talk:Magnus%20Manske&vtllimit={$limit}&format=json" ;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$json = curl_exec($ch);
		$json = json_decode ( $json ) ;
		if ( !isset($json) or !isset($json->flow) ) throw new Exception("{__METHOD__} failed to get {$url}") ;
		$json = $json->flow->$vtl->result->topiclist ;
		$topics = [] ;
		foreach ( $json->revisions AS $rev ) {
			$topic_id = $rev->workflowId ;
			$time = self::format_time(strtotime($rev->timestamp)) ;
			$description = '' ;
			$urls = [] ;
			if ( $rev->content->format == 'fixed-html' ) {
				$html = html_entity_decode($rev->content->content);
				$description = trim(html_entity_decode(strip_tags($rev->content->content))) ;
				if ( preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $html, $m) ) {
					foreach ( $m[0] AS $url ) {
						$url = preg_replace ( '|".*$|' , '' , $url ) ;
						$urls[] = $url ;
					}
				}
			}
			if ( !isset($topics[$topic_id]) ) {
				$topics[$topic_id] = [
					'label' => $rev->properties->$top->plaintext ,
					'date_created' => $time ,
					'date_last' => $time ,
					'site' => 'WIKIDATA' ,
					'url' => "https://www.wikidata.org/wiki/Topic:{$topic_id}" ,
					'status' => ($rev->isLocked?'CLOSED':'OPEN') ,
					'description' => $description ,
					'tmp_authors' => [ $rev->author->name ] ,
					'tmp_urls' => $urls
				] ;
			} else {
				if ( $time > $topics[$topic_id]['date_last'] ) $topics[$topic_id]['date_last'] = $time ;
				$topics[$topic_id]['tmp_authors'][] = $rev->author->name ;
				$topics[$topic_id]['description'] .= "\n\n{$description}" ;
				$topics[$topic_id]['tmp_urls'] = array_merge($topics[$topic_id]['tmp_urls'],$urls) ;
			}
		}
		# Cleanup
		foreach ( $topics AS $topic_id => $topic ) {
			$topics[$topic_id]['tmp_authors'] = array_unique ( $topic['tmp_authors'] ) ;
			$topics[$topic_id]['tmp_urls'] = array_unique ( $topic['tmp_urls'] ) ;
			$topics[$topic_id]['description'] = trim($topic['description']) ;
		}
		foreach ( $topics AS $topic ) {
			$issue = WikidataIssue::new_from_topic($topic) ;
			$issue->get_or_create_issue_id($this);
		}
	}
	
	public function update () {
		try {
			$this->update_from_wikipages() ;
		} catch (Exception $e) {
			print $e->getMessage() . "\n" ;
		}
		try {
			$this->update_from_github() ;
		} catch (Exception $e) {
			print $e->getMessage() . "\n" ;
		}
		try {
			$this->update_from_bitbucket() ;
		} catch (Exception $e) {
			print $e->getMessage() . "\n" ;
		}
		try {
			$this->update_from_wikidata() ;
		} catch (Exception $e) {
			print $e->getMessage() . "\n" ;
		}
		$this->check_wikidata_for_tool_items();
		$this->maintenance() ;
	}

	public function check_wikidata_for_tool_items() {
		$existing_items = [] ;
		$sql = "SELECT * FROM `tool_kv` WHERE `key`='item'" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()) $existing_items[$o->value] = $o->tool_id ;

		$sparql = "SELECT ?q { ?q wdt:P31 wd:Q20726407 ; wdt:P178 wd:Q13520818 }" ;
		$items = $this->tfc->getSPARQLitems($sparql,"q");
		foreach ( $items AS $q ) {
			if ( isset($existing_items[$q]) ) continue ;
			print "No key/value pair for tool https://www.wikidata.org/wiki/{$q}\n" ;
		}
	}


	protected function maintenance_wiki_dates () {
		$sql = "SELECT * FROM `vw_wiki_issue` WHERE `date_created`='".Issue::ZERO_TIME."' AND `status`='OPEN'" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()){
			$issue = WikiIssue::new_from_object ( $o ) ;
			if ( $issue->date_created() != WikiIssue::ZERO_TIME ) continue ; # Paranoia
			$issue->set_times() ;
			if ( $issue->date_created() == WikiIssue::ZERO_TIME ) continue ; # None found
			$issue->update_in_database ( $this , ['date_created','date_last'] ) ;
		}
	}

	protected function maintenance_wiki_tool_guess () {
		$sql = "SELECT * FROM `vw_wiki_issue` WHERE `tool`=0 AND `status`='OPEN'" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()) {
			$issue = WikiIssue::new_from_object ( $o ) ;
			if ( $issue->tool() != 0 ) continue ; # Paranoia
			$issue->determine_tool ( $this ) ;
			if ( $issue->tool() == 0 ) continue ; # No avail
			$issue->update_in_database ( $this , ['tool'] ) ;
		}

		$sql = "SELECT `id`,`tool_hint` FROM `vw_wiki_issue` WHERE `tool`=0 AND `tool_hint`!=0" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()) {
			$sql = "UPDATE `issue` SET `tool`={$o->tool_hint} WHERE `tool`=0 AND `id`={$o->id}" ;
			$this->getSQL ( $sql ) ;
		}
	}

	protected function maintenance_not_wiki_tool_guess () {
		# WIKI gets special treatment, see above
		$sql = "SELECT `id`,`label`,`description` FROM `vw_issue` WHERE `tool`=0 AND `status`='OPEN' AND `site`!='WIKI'" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()) {
			$tool_id = Issue::determine_tool_from_text ( $o->label , $this ) ;
			if ( $tool_id == 0 ) $tool_id = Issue::determine_tool_from_text ( $o->description , $this ) ;
			if ( $tool_id == 0 ) continue ; # No avail
			$this->setIssueValue ( $o->id , 'tool' , $tool_id ) ;
		}
	}

	protected function setIssueValue ( $issue_id , $field , $value ) {
		if ( !in_array($field, Issue::FIELDS) ) throw new Exception("{__METHOD__}: '{$field}'' is not a valid field in Issue") ;
		$issue_id *= 1 ;
		$value = $this->escape ( $value ) ;
		$sql = "UPDATE `issue` SET `{$field}`='{$value}' WHERE `id`={$issue_id}" ;
		$this->getSQL ( $sql ) ;
	}

	protected function maintenance_wiki_close_old_replied () {
		# Get all open issues where I wrote something...
		$time = strtotime("-1 month");
		$cutoff_time = self::format_time($time);
		$sql = "SELECT `vw_wiki_issue`.* FROM `vw_wiki_issue`,`user_issue`" ;
		$sql .= " WHERE `date_last`!='".Issue::ZERO_TIME."' AND `date_last`<'{$cutoff_time}' AND `status`='OPEN'" ;
		$sql .= " AND `user_issue`.`issue_id`=`vw_wiki_issue`.`id` AND `user_issue`.`user_id`=14" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()) {
			$issue = WikiIssue::new_from_object ( $o ) ;
			if ( $issue->status() != 'OPEN' ) continue ; # Paranoia
			$rows = explode ( "\n" , trim($issue->wikitext()) ) ;
			$last_row = array_pop($rows);
			# Make sure I wrote on the last row
			if ( !preg_match('|\bMagnus[ _]Manske\b|',$last_row) ) continue ;
			# Old issue, I wrote the last line => closed
			$issue->set_status ( 'CLOSED' ) ;
			$issue->update_in_database ( $this , ['status'] ) ;
		}
	}

	/*
	protected function maintenance_description_text_id() {
		$sql = "SELECT * FROM `issue` WHERE `description_text_id`=0" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()) {
			$text_id = $this->get_or_create_text_id ( $o->description ) ;
			$sql = "UPDATE `issue` SET `description_text_id`={$text_id} WHERE `id`={$o->id}" ;
			$this->getSQL ( $sql ) ;
		}
	}
	*/

	public function maintenance () {
		#$this->maintenance_description_text_id() ;
		$this->maintenance_wiki_dates() ;
		$this->maintenance_wiki_tool_guess() ;
		$this->maintenance_not_wiki_tool_guess() ;
		$this->maintenance_wiki_close_old_replied() ;
	}

	public function getSQL ( $sql ) {
		return $this->tfc->getSQL ( $this->tool_db , $sql ) ;
	}

	public function escape ( $s ) {
		return $this->tool_db->real_escape_string ( $s ) ;
	}

	public function last_insert_id () {
		return $this->tool_db->insert_id ;
	}

	protected function get_user_id ( $username ) {
		$username = $this->escape ( $username ) ;
		$sql = "SELECT `id` FROM `user` WHERE `name`='{$username}' AND `site`='WIKI'" ;
		$result = $this->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) return $o->id ;
		throw new Exception("{__METHOD__}: '{$username}' is not known") ;
	}

	public function log ( $issue_id , $event , $text , $username ) {
		$issue_id *= 1 ;
		$event = $this->escape ( $event ) ;
		$text_id = $this->get_or_create_text_id ( $text ) ;
		$user_id = (int) $this->get_user_id($username) ;
		$sql = "INSERT INTO `log` (`issue_id`,`event`,`text_id`,`user_id`) VALUES ({$issue_id},'{$event}',{$text_id},{$user_id})" ;
		$this->getSQL ( $sql ) ;
		$this->touch_issue ( $issue_id ) ;
	}

	public function touch_issue ( $issue_id ) {
		$issue_id *= 1 ;
		$sql = "UPDATE `issue` SET `date_last`=CURRENT_TIMESTAMP WHERE `id`={$issue_id}" ;
		$this->getSQL ( $sql ) ;
	}

	public function get_or_create_text_id ( $text ) {
		$text = $this->escape ( $text ) ;
		$sql = "SELECT `id` FROM `text` WHERE `text`='{$text}'" ;
		$result = $this->getSQL ( $sql ) ;
		if($o = $result->fetch_object()) return $o->id ;
		$sql = "INSERT INTO `text` (`text`) VALUES ('{$text}')" ;
		$this->getSQL ( $sql ) ;
		return $this->last_insert_id() ;
	}

	public function update_http_status() {
		// Reset status to unknown
		$sql = "UPDATE `tool` SET `http_status`=null WHERE `subdomain`=''" ;
		$this->getSQL ( $sql ) ;

		// Get current http status
		$sql = "SELECT DISTINCT `subdomain` FROM `tool` WHERE `subdomain`!=''" ;
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()) {
			$url = "https://{$o->subdomain}.toolforge.org" ;
			$output = trim(shell_exec("curl -sI \"{$url}\" | grep HTTP | head -1"));
			if ( preg_match('|^HTTP/2\s*(\d+)$|',$output,$m) ) {
				$status = $m[1]*1;
				$sql = "UPDATE `tool` SET `http_status`={$status} WHERE `subdomain`='{$o->subdomain}'" ;
			} else {
				// print "Could not run curl for {$url}\n";
				$sql = "UPDATE `tool` SET `http_status`=null WHERE `subdomain`='{$o->subdomain}'" ;
			}
			$this->getSQL ( $sql );
		}
	}

	public function get_broken_subdomains() {
		$ret = [];
		$sql = "SELECT DISTINCT `subdomain` FROM `tool` WHERE `has_webservice`=1 AND (`http_status`!=200 OR `http_status` IS NULL)";
		$result = $this->getSQL ( $sql ) ;
		while($o = $result->fetch_object()) $ret[] = $o->subdomain;
		return $ret;
	}

}

?>