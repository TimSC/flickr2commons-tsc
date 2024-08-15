<?PHP

namespace Toolforge ;

# declare(strict_types=1); # PHP7

/*
error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

ini_set('memory_limit','500M');
set_time_limit ( 60 * 10 ) ; // Seconds
*/


class Widar {
	public $tfc ;
	public $oa ;
	public $json_last_error = JSON_ERROR_NONE ;
	public $result = '' ;
	public $authorization_callback = '' ;
	public $authorize_parameters = '' ; # Optional parameters to 'authorize' call

	public function __construct ( /*string*/ $toolname = '' ) {
		$this->tfc = new Common ( $toolname ) ;
		try {
			$this->oa = new OAuth ( $this->toolname() , 'wikidata' , 'wikidata' ) ;
		} catch ( Exception $e ) { # Error
			// Ignore error
		}
		$this->oa->debugging = true ;
	}

	public function toolname() {
		return $this->tfc->toolname ;
	}

	# Interface using $_REQUEST parameters to call the appropriate method
	# Returns true if an action was performed, and false if not
	public function process_request ( $parameter_name = 'action' ) {
		$action = $this->tfc->getRequest ( $parameter_name , '' ) ;
		switch ( $action ) {
			case 'authorize':
				$this->authorize() ;
				break ;
			case 'remove_claim':
				$this->remove_claim (
					$this->tfc->getRequest('id',''),
					$this->tfc->getRequest('baserev','')
				) ;
				break ;
			case 'set_claims':
				$this->set_claims (
					explode(',',$this->tfc->getRequest('ids','')),
					$this->tfc->getRequest('prop',''),
					$this->tfc->getRequest('target',''),
					$this->tfc->getRequest('claim',''),
					$this->tfc->getRequest('summary','')
				) ;
				break ;
			case 'merge_items':
				$this->merge_items (
					$this->tfc->getRequest('from',''),
					$this->tfc->getRequest('to','')
				) ;
				break ;
			case 'create_redirect':
				$this->create_redirect (
					$this->tfc->getRequest('from',''),
					$this->tfc->getRequest('to','')
				) ;
				break ;
			case 'set_label':
				$this->set_label (
					$this->tfc->getRequest('q',''),
					$this->tfc->getRequest('lang',''),
					$this->tfc->getRequest('label','')
				) ;
				break ;
			case 'set_desc':
				$this->set_description (
					$this->tfc->getRequest('q',''),
					$this->tfc->getRequest('lang',''),
					$this->tfc->getRequest('label','')
				) ;
				break ;
			case 'set_alias':
				$this->set_alias (
					$this->tfc->getRequest('q',''),
					$this->tfc->getRequest('lang',''),
					$this->tfc->getRequest('label',''),
					$this->tfc->getRequest('mode','')
				) ;
				break ;
			case 'set_string':
				$this->set_string (
					$this->tfc->getRequest('id',''),
					$this->tfc->getRequest('prop',''),
					$this->tfc->getRequest('text',''),
					$this->tfc->getRequest('qualifier_claim',''),
					$this->tfc->getRequest('summary','')
				) ;
				break ;
			case 'set_monolang':
				$this->set_monolingual_string (
					$this->tfc->getRequest('id',''),
					$this->tfc->getRequest('prop',''),
					$this->tfc->getRequest('text',''),
					$this->tfc->getRequest('language',''),
					$this->tfc->getRequest('qualifier_claim','')
				) ;
				break ;
			case 'get_rights':
				$this->get_rights() ;
				break ;
			case 'logout':
				$this->logout() ;
				break ;
			case 'set_sitelink':
				$this->set_sitelink (
					$this->tfc->getRequest('q',''),
					$this->tfc->getRequest('site',''),
					$this->tfc->getRequest('title','')
				) ;
				break ;
			case 'set_location':
				$this->set_location (
					$this->tfc->getRequest('id',''),
					$this->tfc->getRequest('prop',''),
					$this->tfc->getRequest('lat',''),
					$this->tfc->getRequest('lon',''),
					$this->tfc->getRequest('qualifier_claim','')
				) ;
				break ;
			case 'set_date':
				$this->set_date ( 
					$this->tfc->getRequest('id','') , 
					$this->tfc->getRequest('prop','') , 
					$this->tfc->getRequest('date','') , 
					$this->tfc->getRequest('prec','') , 
					$this->tfc->getRequest('claim','')
				) ;
				break ;
			case 'set_quantity':
				$this->set_quantity ( 
					$this->tfc->getRequest('id','') , 
					$this->tfc->getRequest('prop','') , 
					$this->tfc->getRequest('amount','') , 
					$this->tfc->getRequest('upper','') , 
					$this->tfc->getRequest('lower','') , 
					$this->tfc->getRequest('unit','1') , 
					$this->tfc->getRequest('claim','')
				) ;
				break ;
			case 'create_blank_item':
				$this->create_blank_item () ;
				break ;
			case 'create_item_from_page':
				$this->create_item_from_page (
					$this->tfc->getRequest('site',''),
					$this->tfc->getRequest('page','')
				) ;
				break ;
			case 'add_source':
				$this->set_source (
					$this->tfc->getRequest('statement',''),
					$this->tfc->getRequest('snaks','')
				) ;
				break ;
			case 'set_text':
				$this->set_text (
					$this->tfc->getRequest('language',''),
					$this->tfc->getRequest('project',''),
					$this->tfc->getRequest('page',''),
					$this->tfc->getRequest('text','')
				) ;
				break ;
			case 'delete':
				$this->delete_page (
					$this->tfc->getRequest('page',''),
					$this->tfc->getRequest('reason','')
				) ;
				break ;
			case 'add_row':
				$this->add_row (
					$this->tfc->getRequest('language',''),
					$this->tfc->getRequest('project',''),
					$this->tfc->getRequest('page',''),
					$this->tfc->getRequest('row','')
				) ;
				break ;
			case 'append':
				$this->append_text (
					$this->tfc->getRequest('language',''),
					$this->tfc->getRequest('project',''),
					$this->tfc->getRequest('page',''),
					$this->tfc->getRequest('text',''),
					$this->tfc->getRequest('header',''),
					$this->tfc->getRequest('section',''),
					$this->tfc->getRequest('summary','')
				) ;
				break ;
			case 'upload_from_url':
				$this->upload_from_url (
					$this->tfc->getRequest('language','commons'),
					$this->tfc->getRequest('project','wikimedia'),
					$this->tfc->getRequest('url',''),
					$this->tfc->getRequest('newfile',''),
					$this->tfc->getRequest('desc',''),
					$this->tfc->getRequest('comment',''),
					isset($_REQUEST['ignorewarnings'])
				) ;
				break ;
			case 'sdc':
				$this->sdc_tag (
					$this->tfc->getRequest('params','')
				) ;
				break ;
			case 'generic':
				$this->generic_action (
					$this->tfc->getRequest('json',''),
					$this->tfc->getRequest('summary','')
				) ;
				break ;
			default:
				return false ;
		}
		return true ;
	}

	# Returns true if output was written, false otherwise
	public function render_reponse ( $botmode = true , $parameter_name = 'action' ) {
		# Bot output
		$out = [ 'error' => 'OK' , 'data' => [] ] ;
		$ret = false ;
		$callback = $this->tfc->getRequest('callback2','') ; # For botmode

		try {
			$ret = $this->process_request ( $parameter_name ) ;
		} catch ( Exception $e ) { # Error
			$error_message = $e->getMessage() ;
			if ( $botmode ) {
				$out['error2'] = $this->oa->error??'' ;
				$out['jle'] = $this->json_last_error??'' ;
				$out['res'] = $this->oa->last_res??'' ;
				$out['result'] = $this->result??'' ; # For get_rights
				$out['error'] = $error_message??'' ;
				$this->output_bot ( $out , $callback ) ;
			} else {
				$this->output_widar_header() ;
				print "<h3>Error</h3><ul>" ;
				print "<li>{$out['error']}</li>" ;
				print "<li>{$out['error2']}</li>" ;
				print "<li>{$out['jle']}</li>" ;
				print "<li>{$out['res']}</li>" ;
				print "</ul>" ;
				print $this->tfc->getCommonFooter() ;
			}
			return true ; # Handled that 
		}

		$out['error2'] = $this->oa->error??'' ;
		$out['jle'] = $this->json_last_error??'' ;
		$out['res'] = $this->oa->last_res??'' ;
		$out['result'] = $this->result??'' ; # For get_rights

		if ( !$ret ) {} # No action found, return false
		else if ( $botmode ) {
			$this->output_bot ( $out , $callback ) ;
		} else {
			$this->output_widar_header() ;
			print "<h3>Successful</h3>" ;
			print_r ( $out ) ;
			print $this->tfc->getCommonFooter() ;
		}
		return $ret ;
	}

	public function attempt_verification_auto_forward ( $url ) {
		if ( $this->tfc->getRequest('oauth_verifier','')  == '' ) return ;
		if ( $this->tfc->getRequest('oauth_token','')  == '' ) return ;
		header( "Location: $url" );
		echo 'Please see <a href="' . htmlspecialchars( $url ) . '">' . htmlspecialchars( $url ) . '</a>';
		exit(0);
	}

	protected function output_bot ( $out , $callback = '' ) {
		if ( $callback != '' ) print "{$callback}(" ;
		else header('Content-Type: application/json');
		print json_encode ( $out ) ;
		if ( $callback != '' ) print ");" ;
	}

	public function output_widar_header () {
		print $this->tfc->getCommonHeader ( 'WiDaR' ) ;
		print "<div style='float:right'><a href='//en.wikipedia.org/wiki/Widar' title='Víðarr, slaying the dragon of missing claims'><img border=0 src='https://upload.wikimedia.org/wikipedia/commons/thumb/9/95/Vidar_by_Collingwood.jpg/150px-Vidar_by_Collingwood.jpg' /></a></div>" ;
		print "<h1><i>Wi</i>ki<i>Da</i>ta <i>R</i>emote editor</h1>" ;
	}

	public function output_widar_main_page () {
		$this->output_widar_header() ;
		?>
		<div style='margin-bottom:20px'>This is a tool that is used by other tools; it does not have an interface of its own. It can perform batch edits on WikiData under your user name using <a target='_blank' href='https://blog.wikimedia.org/2013/11/22/oauth-on-wikimedia-wikis/'>OAuth</a>.</div>
		<div>
		<?PHP
		$res = $this->oa->getConsumerRights() ;
	//	print "!<pre>" ;print_r ( $res ) ;print "</pre>" ;
		if ( isset ( $res->error ) ) {
			print "You have not authorized Widar to perform edits on Wikidata on your behalf. <div><a class='btn btn-primary btn-large' href='".htmlspecialchars( $_SERVER['SCRIPT_NAME'] )."?action=authorize'>Authorize WiDaR now</a></div>" ;
		} else if ( !isset($res) or !isset($res->query) ) {
			print "The Wikidata API did not respond to a call in OAuth::getConsumerRights" ;
		} else {
			print "You have authorized WiDaR to edit as " . $res->query->userinfo->name . ". Congratulations! You can always log out <a href='?action=logout'>here</a>." ;
		}
		?>
		</div>
		<div><h3>Tools using WiDaR</h3>
		<ul>
		<li><a href='https://petscan.wmflabs.org'>PetScan</a></li>
		<li><a href='/wikidata-todo/autolist.html'>AutoList</a></li>
		<li><a href='/reasonator'>Reasonator</a></li>
		<li><a href='/wikidata-todo/creator.html'>Wikidata item creator</a></li>
		<li><a href='/quickstatements'>QuickStatements</a></li>
		<li><a href='/wikidata-todo/duplicity.php'>Duplicity</a></li>
		<li><a href='/wikidata-todo/tabernacle.html'>Tabernacle</a></li>
		<li><a href='/mix-n-match'>Mix'n'match</a></li>
		<li><a href='/wikidata-game/'>The Wikidata Game</a> and <a href='/wikidata-game/distributed'>The Distributed Game</a></li>
		<li><a href='/topicmatcher/'>TopicMatcher</a></li>
		</ul>
		<div style='margin-top:20px;border:1px solid #ddd;padding:5px;'>BREAKING CHANGE: JSONP functionality was deactivated due to security concerns</div>
		</div>
		<?PHP
		print $this->tfc->getCommonFooter() ;
	}

	public function authorize() {
		$this->result = $this->toolname() ;
		$this->oa->doAuthorizationRedirect($this->authorization_callback);
		exit ( 0 ) ;
	}

	public function set_label ( $q , $language , $label ) {
		$this->ensureAuth() ;
		if ( $q == '' or $language == '' ) throw new \Exception ( 'Needs q, lang, label' ) ;
		if ( !$this->oa->setLabel ( $q , $label , $language ) ) throw new \Exception ( 'Problem setting label' ) ;
	}

	public function set_description ( $q , $language , $label ) {
		$this->ensureAuth() ;
		if ( $q == '' or $language == '' ) throw new \Exception ( 'Needs q, lang, label' ) ;
		if ( !$this->oa->setDesc ( $q , $label , $language ) ) throw new \Exception ( 'Problem setting description' ) ;
	}

	public function set_alias ( $q , $language , $label , $mode = 'add' ) {
		$this->ensureAuth() ;
		if ( $q == '' or $language == '' or $label == '' ) throw new \Exception ( 'Needs q, lang, label [, mode=add/set/remove]' ) ;
		if ( !$this->oa->setAlias ( $q , $label , $language , $mode ) ) throw new \Exception ( 'Problem setting alias' ) ;
	}

	# Returns ID of new item
	public function create_blank_item () {
		$this->ensureAuth() ;
		if ( !$this->oa->createItem() ) throw new \Exception ( 'Problem creating blank item' ) ;
		return $q = $this->oa->last_res->entity->id ;
	}

	public function create_item_from_page ( $site , $page ) {
		$this->ensureAuth() ;
		if ( $site == '' or $page == '' ) throw new \Exception ( 'Needs site and page' ) ;
		if ( !$this->oa->createItemFromPage ( $site , $page ) ) throw new \Exception ( 'Problem creating item' ) ;
	}
	public function remove_claim ( $id , $baserev = '' ) {
		$this->ensureAuth() ;
		if ( $id == '' ) throw new \Exception ( 'Needs id' ) ;
		if ( !$this->oa->removeClaim ( $id , $baserev ) ) throw new \Exception ( "Problem removing claim {$id}/{$baserev}" ) ;
	}

	public function create_redirect ( $from = '' , $to = '' ) {
		$this->ensureAuth() ;
		$from = trim ( $from ) ;
		$to = trim ( $to ) ;
		if ( $from == '' or $to == '' ) throw new \Exception ( 'Needs from and to' ) ;
		if ( !$this->oa->createRedirect ( $from , $to ) ) throw new \Exception ( "Problem creating redirect '{$from}'=>'{$to}'" ) ;
	}

	public function merge_items ( $from = '' , $to = '' ) {
		$this->ensureAuth() ;
		$from = trim ( $from ) ;
		$to = trim ( $to ) ;
		if ( $from == '' or $to == '' ) throw new \Exception ( 'Needs from and to' ) ;
		if ( !$this->oa->mergeItems ( $from , $to ) ) throw new \Exception ( "Problem merging item '{$from}' into '{$to}'" ) ;
	}

	public function set_claims ( $ids , $prop , $target , $qualifier_claim , $summary = '' ) {
		$this->ensureAuth() ;
		if ( count($ids) == 0 or $prop == '' or $target == '' ) throw new \Exception ( "set_claim parameters incomplete" ) ;
		foreach ( $ids AS $id ) {
			$id = trim ( $id ) ;
			if ( $id == '' && $qualifier_claim == '' ) continue ;
			$claim = [ "prop" => $prop , "target" => $target , "type" => "item" ] ;
			$this->set_q_or_claim ( $claim , $id , $qualifier_claim ) ;
			if ( !$this->oa->setClaim ( $claim , $summary ) ) throw new \Exception ( "set_claims failed: {$id}/{$prop}/{$target}/{$qualifier_claim}" ) ;
		}
	}

	public function set_string ( $id , $prop , $text , $qualifier_claim = '' , $summary = '' ) {
		$this->ensureAuth() ;
		if ( ( $id == '' and $qualifier_claim == '' ) or $prop == '' or $text == '' ) throw new \Exception ( 'set_string parameters incomplete' ) ;
		$claim = [ "prop" => $prop , "text" => $text , "type" => "string" ] ;
		$this->set_q_or_claim ( $claim , $id , $qualifier_claim ) ;
		if ( !$this->oa->setClaim ( $claim ) ) throw new \Exception ( "set_string failed: {$id}/{$prop}/{$text}/{$qualifier_claim}/{$summary}" ) ;
	}

	public function set_monolingual_string ( $id , $prop , $text , $language , $qualifier_claim = '' ) {
		$this->ensureAuth() ;
		if ( $id == '' or $prop == '' or $text == '' or $language == '' ) throw new \Exception ( 'set_monolingual_string parameters incomplete' ) ;
		$claim = [ "prop" => $prop , "text" => $text , "language" => $language , "type" => "monolingualtext" ] ;
		$this->set_q_or_claim ( $claim , $id , $qualifier_claim ) ;
		if ( !$this->oa->setClaim ( $claim ) ) throw new \Exception ( "set_monolingual_string failed: {$id}/{$prop}/{$text}/{$language}/{$qualifier_claim}" ) ;
	}

	public function set_sitelink ( $q , $site , $title ) {
		$this->ensureAuth() ;
		if ( $q == '' or $site == '' or $title == '' ) throw new \Exception ( 'Needs q, site, and title' ) ;
		if ( !$this->oa->setSitelink ( $q , $site , $title ) ) throw new \Exception ( 'Problem creating sitelink' ) ;
	}

	public function set_source ( $statement , $snaks ) {
		$this->ensureAuth() ;
		if ( $statement == '' or $snaks == '' ) throw new \Exception ( 'Needs statement and snaks' ) ;
		if ( !$this->oa->setSource ( $statement , $snaks ) ) throw new \Exception ( "Problem setting source {$statement} / {$snaks}" ) ;
	}

	public function set_location ( $id , $prop , $lat , $lon , $qualifier_claim = '' ) {
		$this->ensureAuth() ;
		if ( $id == '' or $prop == '' or $lat == '' or $lon == '' ) throw new \Exception ( 'set_location parameters incomplete' ) ;
		$claim = [ "prop" => $prop , "lat" => $lat , "lon" => $lon , "type" => "location" ] ;
		$this->set_q_or_claim ( $claim , $id , $qualifier_claim ) ;
		if ( !$this->oa->setClaim ( $claim ) ) throw new \Exception ( "set_location failed: {$id}/{$prop}/{$lat}/{$lon}/{$qualifier_claim}" ) ;
	}

	public function generic_action ( $json , $summary = '' ) {
		$this->ensureAuth() ;
		$j = json_decode ( $json ) ;
		$this->json_last_error = json_last_error() ;
		if ( $this->json_last_error != JSON_ERROR_NONE ) throw new \Exception ( 'generic_action JSON parsing error' ) ;
		if ( !$this->oa->genericAction ( $j , $summary ) ) throw new \Exception ( "generic_action failed: {$json}/{$summary}" ) ;
	}

	public function set_quantity ( $id , $prop , $amount , $upper , $lower , $unit = '1' , $qualifier_claim = '' ) {
		$this->ensureAuth() ;
		if ( $id == '' or $prop == '' or $amount == '' ) throw new \Exception ( 'set_quantity parameters incomplete' ) ;
		if ( $upper == '' and $lower == '' ) {
			$upper = $amount ;
			$lower = $amount ;
		}
		$claim = [ "prop" => $prop , "amount" => $amount , "upper" => $upper , "lower" => $lower , "unit" => $unit , "type" => "quantity" ] ;
		$this->set_q_or_claim ( $claim , $id , $qualifier_claim ) ;
		if ( !$this->oa->setClaim ( $claim ) ) throw new \Exception ( "set_quantity failed: {$id}/{$prop}/{$amount}/{$upper}/{$lower}/{$unit}/{$qualifier_claim}" ) ;
	}

	public function set_date ( $id , $prop , $date , $precision , $qualifier_claim = '' ) {
		$this->ensureAuth() ;
		if ( $id == '' or $prop == '' or $date == '' or $precision == '' ) throw new \Exception ( 'set_date parameters incomplete' ) ;
		$claim = [ "prop" => $prop , "date" => $date , "prec" => $precision , "type" => "date" ] ;
		$this->set_q_or_claim ( $claim , $id , $qualifier_claim ) ;
		if ( !$this->oa->setClaim ( $claim ) ) throw new \Exception ( "set_date failed: {$id}/{$prop}/{$amount}/{$upper}/{$lower}/{$unit}/{$qualifier_claim}" ) ;
	}

	public function add_row ( $language , $project , $page , $row ) {
		$server = "{$language}.{$project}.org" ;
		if ( $language != '' and $project != '' ) $this->oa = new OAuth ( $this->toolname() , $language , $project ) ;
		else $server = 'www.wikidata.org' ;
		$this->ensureAuth() ;
		$text = file_get_contents ( "http://{$server}/w/index.php?action=raw&title=".urlencode(trim($page)) ) ;
		$text = trim ( $text ) . "\n" . trim($row) ;
		if ( !$this->oa->setPageText ( $page , $text ) ) throw new \Exception ( "Problem adding row to {$language}.{$project}.org/wiki/{$page}" ) ;
	}

	public function delete_page ( $page , $reason ) {
		$this->ensureAuth() ;
		if ( !$this->oa->deletePage ( $page , $reason ) ) throw new \Exception ( "Problem deleting page '{$page}'" ) ;
	}

	public function set_text ( $language , $project , $page , $text ) {
		$this->ensureAuth() ;
		$server = "{$language}.{$project}.org" ;
		if ( $language != '' and $project != '' ) $this->oa = new OAuth ( $this->toolname() , $language , $project ) ;
		else $server = 'www.wikidata.org' ;
		if ( !$this->oa->setPageText ( $page , $text ) )  throw new \Exception ( "Problem setting text of {$language}.{$project}.org/wiki/{$page}" ) ;
	}

	public function append_text ( $language , $project , $page , $text , $header = '' , $section = '' , $summary = '' ) {
		$server = "{$language}.{$project}.org" ;
		if ( $language != '' and $project != '' ) $this->oa = new OAuth ( $this->toolname() , $language , $project ) ;
		else $server = 'www.wikidata.org' ;
		$this->ensureAuth() ;
		if ( !$this->oa->addPageText ( $page , $text , $header , $summary , $section ) ) throw new \Exception ( "Problem appending text to {$language}.{$project}.org/wiki/{$page}" ) ;
	}

	public function upload_from_url ( $language , $project , $url , $new_file_name , $description = '' , $comment = '' , $ignore_warnings = false ) {
		$server = "{$language}.{$project}.org" ;
		if ( $language != '' and $project != '' ) $this->oa = new OAuth ( $this->toolname() , $language , $project ) ;
		else $server = 'commons.wikimedia.org' ;
		$this->ensureAuth() ;
		if ( $url == '' ) throw new \Exception ( "No URL given" ) ;
		if ( !$this->oa->doUploadFromURL ( $url , $new_file_name , $description , $comment , $ignore_warnings ) ) throw new \Exception ( $oa->error ) ;
	}

	public function sdc_tag ( $json , $summary = '' ) {
		$this->oa = new OAuth ( $this->toolname() , 'commons' , 'wikimedia' ) ;
		$this->ensureAuth() ;
		$j = json_decode ( $json ) ;
		$this->json_last_error = json_last_error() ;
		if ( $this->json_last_error != JSON_ERROR_NONE ) throw new \Exception ( 'sdc_tag JSON parsing error' ) ;
		if ( !$this->oa->genericAction ( $j , $summary ) ) throw new \Exception ( "sdc_tag failed: {$json}/{$summary}" ) ;
	}

	public function get_rights () {
		//$this->ensureAuth() ;
		$this->result = $this->oa->getConsumerRights() ;
	}

	public function logout () {
		$this->ensureAuth() ;
		$this->oa->logout() ;
	}

	protected function set_q_or_claim ( &$claim , $id , $qualifier_claim ) {
		if ( $qualifier_claim == '' ) $claim['q'] = $id ;
		else $claim['claim'] = $qualifier_claim ;
	}

	protected function ensureAuth () {
		$ch = null;
		$res = $this->oa->doApiQuery( ['format'=>'json','action'=>'query','meta'=>'userinfo'], $ch ); # fetch the username
		if ( isset( $res->error->code ) && $res->error->code === 'mwoauth-invalid-authorization' ) {
			$url = "{$_SERVER['SCRIPT_NAME']}?action=authorize" ;
			if ( $this->authorize_parameters != '' ) $url .= "&{$this->authorize_parameters} " ;
			throw new \Exception ( 'You haven\'t authorized this application yet! Go <a target="_blank" href="' . htmlspecialchars( $url ) . '">here</a> to do that, then reload this page.' ) ;
		}
		if ( !isset( $res->query->userinfo ) ) throw new \Exception ( 'Bad API response[1]: <pre>' . htmlspecialchars( var_export( $res, 1 ) ) . '</pre>' ) ;
		if ( isset( $res->query->userinfo->anon ) ) throw new \Exception ( 'Not logged in. (How did that happen?)' ) ;
	}
}

?>