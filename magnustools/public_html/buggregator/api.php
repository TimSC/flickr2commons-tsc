<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

require_once ( '/data/project/magnustools/scripts/buggregator/Buggregator.php' ) ;
require_once ( '/data/project/magnustools/public_html/php/oauth.php' ) ;

function user_can_edit () {
	global $config , $oa , $out ;
	$userinfo = $oa->getConsumerRights()->query->userinfo ;
	if ( !isset($userinfo) ) return false ;
	if ( !isset($userinfo->name) ) return false ;
	if ( !in_array($userinfo->name,$config['write_access']) ) return false ;
	$out['user'] = $userinfo->name ;
	return true ;
}

$oa = new MW_OAuth ( [
	'tool'=>'buggregator',
	'language' => 'www' ,
	'project' => 'mediawiki' ,
	'apiUrl' => 'https://www.mediawiki.org/w/api.php' ,
	'ini_file' => '/data/project/magnustools/buggregator_oauth.ini'
] ) ;

if ( isset($_REQUEST['oauth_verifier']) and isset($_REQUEST['oauth_token']) ) {
	$url = "https://magnustools.toolforge.org/buggregator/" ;
	header( "Location: $url" );
	echo 'Please see <a href="' . htmlspecialchars( $url ) . '">' . htmlspecialchars( $url ) . '</a>';
	exit(0);
}

$buggregator = new Buggregator ;

$action = $buggregator->tfc->getRequest('action','') ;
$out = [ 'status' => 'OK' , 'data' => [] ] ;
$config = [
	'status' => ['OPEN','CLOSED'] ,
	'site' => ['WIKI','WIKIDATA','GITHUB','BITBUCKET'] ,
	'priority' => ['HIGH','NORMAL','LOW'] ,
	'write_access' => ['Magnus Manske'] ,
	'tools' => []
] ;

if ( $action == 'authorize' ) {
	$oa->doAuthorizationRedirect('https://magnustools.toolforge.org/buggregator/api.php');
	exit(0);
#} else if ( $action == 'is_logged_in' ) {
#	$out['data']['is_logged_in'] = $oa->isAuthOK() ;
#	$out['data']['message'] = $oa->error ?? '' ;
#	$out['data']['userinfo'] = $oa->userinfo ?? ( (object) [] ) ;
} else if ( $action == 'get_rights' ) {
	$out['result'] = $oa->getConsumerRights() ;
} else if ( $action == 'get_issues' ) {
	$limit = (int) $buggregator->tfc->getRequest('limit',25) ;
	$offset = (int) $buggregator->tfc->getRequest('offset',0) ;
	$sort_by = $buggregator->escape($buggregator->tfc->getRequest('sort_by','acsending')) ;
	$sort_order = $buggregator->escape($buggregator->tfc->getRequest('sort_order','acsending')) ;
	$tool = $buggregator->escape($buggregator->tfc->getRequest('tool','')) ;
	$status = explode(',',$buggregator->escape($buggregator->tfc->getRequest('status',''))) ;
	$site = explode(',',$buggregator->escape($buggregator->tfc->getRequest('site',''))) ;
	$priority = explode(',',$buggregator->escape($buggregator->tfc->getRequest('priority',''))) ;

	$sql_core = [] ;
	# TODO:
	# search
	if ( $tool != '' ) $sql_core[] = "`tool`=" . ($tool*1) ;
	if ( $status!=[''] ) $sql_core[] = "`status` IN ('".implode("','",$status)."')" ;
	if ( $site!=[''] ) $sql_core[] = "`site` IN ('".implode("','",$site)."')" ;
	if ( $priority!=[''] ) $sql_core[] = "`priority` IN ('".implode("','",$priority)."')" ;

	if ( count($sql_core) == 0 ) $sql_core = '' ;
	else $sql_core = ' WHERE (' . implode ( ') AND (' , $sql_core ) . ')' ;

	$sql = "SELECT * FROM `vw_issue` {$sql_core}" ;
	if ( $sort_by != '' ) {
		$sql .= " ORDER BY `{$sort_by}`" ;
		if ( $sort_order == 'descending' ) $sql .= " DESC" ;
	}
	$sql .= " LIMIT {$limit} OFFSET {$offset}" ;
	$out['sql'] = $sql ;
	$out['data']['results'] = [] ;
	$result = $buggregator->getSQL ( $sql ) ;
	while($o = $result->fetch_object()) $out['data']['results'][] = $o ;
	
	$sql = "SELECT count(*) AS `cnt` FROM `vw_issue` {$sql_core}" ;
	$result = $buggregator->getSQL ( $sql ) ;
	if($o = $result->fetch_object()) $out['data']['stats']['this_query'] = $o->cnt ;

	$sql = "SELECT count(*) AS `cnt` FROM `vw_issue` WHERE `status`='OPEN'" ;
	$result = $buggregator->getSQL ( $sql ) ;
	if($o = $result->fetch_object()) $out['data']['stats']['total_open'] = $o->cnt ;

} else if ( $action == 'get_config' ) {
	$out['data'] = $config ;
	$sql = "SELECT * FROM `tool` ORDER BY `name`,`subdomain`" ;
	$result = $buggregator->getSQL ( $sql ) ;
	while($o = $result->fetch_object()) {
		$nice_name = "{$o->subdomain}/{$o->name}" ;
		if ( strtolower($o->subdomain) == strtolower($o->name) ) $nice_name = $o->name ;
		if ( $o->subdomain == '' ) $nice_name = $o->name ;
		$o->nice_name = $nice_name ;
		$out['data']['tools']["{$o->id}"] = $o ;
	}

} else if ( $action == 'set_issue_status' ) {

	$issue_id = (int) $buggregator->tfc->getRequest('issue_id',0) ;
	$new_status = $buggregator->tfc->getRequest('new_status','') ;
	if ( !user_can_edit() ) {
		$out['status'] = "User not logged in, or not in whitelist" ;
	} else if ( $issue_id == 0 ) {
		$out['status'] = "No issue ID set" ;
	} else if ( !in_array ( $new_status , $config['status']) ) {
		$out['status'] = "'{$new_status}' is not a valid issue status" ;
	} else {
		$buggregator->log ( $issue_id , 'STATUS' , 'CLOSED' , $out['user'] ) ;
		$sql = "UPDATE `issue` SET `status`='" . $buggregator->escape($new_status) . "' WHERE `id`={$issue_id}" ;
		$buggregator->getSQL($sql);
	}

} else if ( $action == 'set_issue_tool' ) {

	$issue_id = (int) $buggregator->tfc->getRequest('issue_id',0) ;
	$new_tool_id = (int) $buggregator->tfc->getRequest('new_tool_id',0) ;
	if ( !user_can_edit() ) {
		$out['status'] = "User not logged in, or not in whitelist" ;
	} else if ( $issue_id == 0 ) {
		$out['status'] = "No issue ID set" ;
	} else {
		$buggregator->log ( $issue_id , 'TOOL_ID' , $new_tool_id , $out['user'] ) ;
		$sql = "UPDATE `issue` SET `tool`={$new_tool_id} WHERE `id`={$issue_id}" ;
		$buggregator->getSQL($sql);
	}

} else if ( $action == 'get_tools' ) {

	$out['data'] = [] ;

	$kv = [] ;
	$sql = "SELECT * FROM tool_kv" ;
	$result = $buggregator->getSQL ( $sql ) ;
	while($o = $result->fetch_object()) {
		if ( !isset($kv[$o->tool_id]) ) $kv[$o->tool_id] = [] ;
		$kv[$o->tool_id][$o->key] = $o->value ;
	}

	$sql = "SELECT * FROM `vw_tools_tickets`" ;
	$result = $buggregator->getSQL ( $sql ) ;
	while($o = $result->fetch_object()) {
		if ( isset($kv[$o->id]) ) $o->key_values = $kv[$o->id] ;
		$out['data'][] = $o ;
	}

} else {
	$o->status = "Unknown action '{$action}'" ;
}

header('Content-Type: application/json');
echo json_encode($out) ;

?>