<?PHP

require_once ( '/data/project/magnustools/public_html/php/ToolforgeCommon.php' ) ;

class Logger {
	public $tfc ;
	public $db ;
	private $database_name = 'buggregator' ;
	private $tools_table_name = 'logging_tools' ;
	private $logs_table_name = 'logging_uses' ;

	public function __construct () {
		$this->tfc = new ToolforgeCommon ( 'logger' ) ;
		$this->db = $this->tfc->openDBtool ( $this->database_name ) ;
	}

	public function getToolByNameAndMethod ( $toolname , $method , $sanitize = true ) {
		if ( $sanitize ) $this->sanitizeToolAndMethodName ( $toolname , $method ) ;
		$sql = "SELECT id FROM `{$this->tools_table_name}` WHERE `name`='" . $this->db->real_escape_string($toolname) . "' AND `method`='" . $this->db->real_escape_string($method) . "'" ;
		$result = $this->tfc->getSQL ( $this->db , $sql ) ;
		while($o = $result->fetch_object()) return $o->id ;
	}

	protected function createNewToolAndMethod ( $toolname , $method ) {
		$this->sanitizeToolAndMethodName ( $toolname , $method ) ;
		$date = $this->today() ;
		$sql = "INSERT IGNORE INTO `{$this->tools_table_name}` (`name`,`method`,`start_log`) VALUES ('" . $this->db->real_escape_string($toolname) . "','" . $this->db->real_escape_string($method) . "',{$date})" ;
		$this->tfc->getSQL ( $this->db , $sql ) ;
	}

	public function getOrCreateToolByNameAndMethod ( $toolname , $method ) {
		$this->sanitizeToolAndMethodName ( $toolname , $method ) ;
		$tool_id = $this->getToolByNameAndMethod ( $toolname , $method ) ;
		if ( isset($tool_id) ) return $tool_id ;
		$this->createNewToolAndMethod ( $toolname , $method ) ;
		$tool_id = $this->getToolByNameAndMethod ( $toolname , $method ) ;
		if ( !isset($tool_id) ) $this->micDrop ( "Could not create tool '{$toolname}' method '{$method}'" ) ;
		return $tool_id ;
	}

	public function deleteToolById ( $tool_id ) {
		$sql = "DELETE FROM `{$this->logs_table_name}` WHERE `tool_id`={$tool_id}" ;
		$this->tfc->getSQL ( $this->db , $sql ) ;
		$sql = "DELETE FROM `{$this->tools_table_name}` WHERE `id`={$tool_id}" ;
		$this->tfc->getSQL ( $this->db , $sql ) ;
	}

	public function addToLog ( $tool_id , $date = 0 , $increase = 1 ) {
		# Create tool/date entry if necessary, increase usage count
		if ( $date == 0 ) $date = $this->today() ;
		$sql = "INSERT INTO `{$this->logs_table_name}` (`tool_id`,`date`,`used`) VALUES ({$tool_id},{$date},{$increase}) ON DUPLICATE KEY UPDATE `used`=`used`+{$increase}" ;
		$this->tfc->getSQL ( $this->db , $sql ) ;
	}

	public function getAllUses ( $tool_id ) {
		$sql = "SELECT sum(`used`) AS `uses` FROM `{$this->logs_table_name}` WHERE `tool_id`={$tool_id}" ;
		$result = $this->tfc->getSQL ( $this->db , $sql ) ;
		while($o = $result->fetch_object()) return $o->uses ;
	}

	public function micDrop ( $error = '' ) {
		$out = (object) ["status"=>"OK"] ;
		$callback = $this->tfc->getRequest ( 'callback' , '' ) ;
		if ( $error != '' ) {
			$out->status = 'ERROR' ;
			$out->error = trim($error) ;
		}
		if ( $callback != '' ) print $callback.'(' ;
		print json_encode ( $out ) ."\n" ;
		if ( $callback != '' ) print ')' ;
		$this->tfc->flush();
		ob_end_flush() ;
		exit(0) ;
	}

	public function sanitizeToolAndMethodName ( &$toolname , &$method ) {
		$method = preg_replace ( '|\s*[\(\)\"\'\=\;\,].*$|' , '' , $method ) ;
		if ( $toolname == 'mix-n-match' ) {
			$method = preg_replace ( '/\s+(and|union)\b.*/i' , '' , $method ) ;
			$method = preg_replace ( '|\..*|i' , '' , $method ) ;
		}
		if ( $toolname == 'icommons' ) {
			if ( preg_match('|^category\b|',$method) ) $method = 'category' ;
		}
		$toolname = trim ( strtolower ( $toolname ) ) ;
		$method = trim ( strtolower ( $method ) ) ;
		if ( $toolname == '' ) $logger->micDrop ( "No tool name supplied" ) ;
	}

	public function mergeMethods ( $toolname , $method_from , $method_to ) {
		$tool_id_from = $this->getToolByNameAndMethod ( $toolname , $method_from , false ) ;
		if ( !isset($tool_id_from) ) return $this->micDrop ( "No tool {$toolname}:{$method_from}" ) ;
		$tool_id_to = $this->getOrCreateToolByNameAndMethod ( $toolname , $method_to ) ;
		$sql = "SELECT * FROM `{$this->logs_table_name}` WHERE `tool_id`={$tool_id_from}" ;
		$result = $this->tfc->getSQL ( $this->db , $sql ) ;
		#print "{$toolname}: {$method_from}={$tool_id_from} => {$method_to}={$tool_id_to}\n" ;
		# Merge and delete logs
		while($o = $result->fetch_object()) {
			$this->addToLog ( $tool_id_to , $o->date , $o->used ) ;
			$sql = "DELETE FROM `{$this->logs_table_name}` WHERE `id`={$o->id}" ;
			$this->tfc->getSQL ( $this->db , $sql ) ;
		}
		# Delete tool entry
		$sql = "DELETE FROM `{$this->tools_table_name}` WHERE `id`={$tool_id_from}" ;
		$this->tfc->getSQL ( $this->db , $sql ) ;
	}

	protected function today() {
		$date = date ( 'Ymd' ) * 1 ;
		return $date ;
	}

}

?>