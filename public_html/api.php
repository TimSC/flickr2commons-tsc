<?php

require_once ( 'php/common.php' ) ;

$action = get_request ( 'action' , '' ) ;

$out = ['status'=>'OK'] ;

if ( $action == 'check_flickr_files_in_commons' ) {

	$data = json_decode ( get_request ( 'data' , '{}' ) ) ;
	$db = openDB ( 'commons' , 'wikimedia' ) ;
	$out['data']['files'] = [] ;

	$urls = [] ;
	foreach ( $data->files AS $nsid => $file_ids ) {
		$user_names = [$nsid] ;
		if ( isset($data->owners->$nsid) and $data->owners->$nsid != $nsid ) $user_names[] = $data->owners->$nsid ;
		foreach ( $file_ids AS $file_id ) {
			foreach ( ['http','https'] AS $protocol ) {
				foreach ( ['','www.'] AS $p1 ) {
					foreach ( $user_names AS $user ) {
						foreach ( ['photo','photos'] AS $p2 ) {
							foreach ( ['','/'] AS $p3 ) {
								$urls[] = $db->real_escape_string ( "$protocol://com.flickr.$p1/$p2/$user/$file_id$p3" ) ;
							}
						}
					}
				}
			}
		}
	}

	$sql = "SELECT DISTINCT page_title,el_to FROM page,externallinks WHERE page_id=el_from AND page_namespace=6 AND el_index IN ('" . implode("','",$urls) . "')" ;
	$result = getSQL ( $db , $sql ) ;
	while($o = $result->fetch_object()) {
		if ( !preg_match ( '/\/(\d+)\/{0,1}$/' , $o->el_to , $m ) ) continue ; // Huh?
		$out['data']['files'][$m[1]] = $o->page_title ;
	}
	$out['sql'][] = $sql ;

} else if ( $action == 'check_existing_commons_filenames' ) {

	$filenames = json_decode ( get_request ( 'filenames' , '[]' ) ) ; // No "File:" prefix!
	$out['data']['files'] = [] ;
	$db = openDB ( 'commons' , 'wikimedia' ) ;
	$to_check = [] ;
	foreach ( $filenames AS $fn ) {
		$fn = str_replace ( ' ' , '_' , ucfirst ( trim ( $fn ) ) ) ;
		$to_check[] = $db->real_escape_string ( $fn ) ;
		$out['data']['files'][$fn] = 0 ;
	}
	$sql = "SELECT DISTINCT page_title FROM page WHERE page_namespace=6 AND page_title IN ('" . implode("','",$to_check) . "')" ;
	$out['sql'] = $sql ;
	$result = getSQL ( $db , $sql ) ;
	while($o = $result->fetch_object()) {
		$out['data']['files'][$o->page_title] = 1 ;
	}

}

header('Content-type: application/json; charset=UTF-8');
print json_encode ( $out ) ;
myflush();
ob_end_flush() ;


?>