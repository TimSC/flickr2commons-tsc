#!/usr/bin/env php
<?php

error_reporting(E_ERROR|E_CORE_ERROR|E_ALL|E_COMPILE_ERROR);
ini_set('display_errors', 'On');

// require_once('/data/project/magnustools/public_html/php/ToolforgeCommon.php') ;
require_once('/data/project/magnustools/scripts/buggregator/Buggregator.php') ;

class ToolMonitor {
	public $tfc ;
	private $alerts = [] ;
	private $testing = false;
	private $last_message_file = '/data/project/magnustools/scripts/monitor/last_message.html';
	private $web_page_file = '/data/project/magnustools/public_html/tool_status.html';

	public function __construct ($testing=false) {
		$this->tfc = new ToolforgeCommon();
		$this->testing = $testing;
	}

	public function run() {
		$this->check_quickstatements();
		$this->check_petscan();
		$this->check_mixnmatch();
		$this->check_reinheitsgebot();
		$this->check_listeriabot();
		$this->check_buggregator();
		$this->wrap_up();
	}

	function check_buggregator() {
		$buggregator = new Buggregator ;
		$subdomains = $buggregator->get_broken_subdomains();
		foreach ($subdomains as $subdomain) {
			if ( in_array($subdomain, ['quickstatements','petscan','mix-n-match','listeriabot']) ) continue;
			if ( isset($this->alerts[$subdomain]) ) continue;
			$this->alerts[$subdomain][] = ["msg"=>"Buggregator reports non-200 status"];
		}
	}

	function url_exists($url) {
	    return curl_init($url) !== false;
	}

	function message2html_mail() {
		$message = "" ;
		foreach ( $this->alerts AS $tool => $tool_alerts ) {
			if ( count($tool_alerts)==0 ) continue;
			$message .= "<h2>{$tool}</h2>\n<ul>\n" ;
			foreach ( $tool_alerts AS $ta ) {
				$message .= "<li>{$ta['msg']}</li>\n" ;
			}
			$message .= "</ul>\n" ;
		}
		return $message;
	}

	function message2html_page() {
		$message = "<html><head><meta http-equiv='refresh' content='1800' ></head><body><h1>Tool status</h1><table>" ;
		foreach ( $this->alerts AS $tool => $tool_alerts ) {
			$message .= "<tr><th>{$tool}</th>" ;

			if ( count($tool_alerts)==0 ) {
				$message .= "<td style='background-color: #b5e7a0;'>Nominal";
			} else {
				$message .= "<td style='color:red'>" ;
				foreach ( $tool_alerts AS $ta ) $message .= "<div>{$ta['msg']}</div>" ;
			}
			$message .= "</td></tr>" ;
		}
		$message .= "</table>";
		$ts = date("Y-m-d H:i:s");
		$message .= "<p>Last update: {$ts}</p>";
		$message .= "<p><i><b>Note</b>:</i> If you can see an error here, Magnus has received an automated email about it, no need to contact him!</p>";
		$message .= "</body></html>";
		return $message;
	}

	function send_message($message) {
		$headers = [] ;
		$headers[] = "From: Tool monitor <toolmonitor@toolforge.org>";

		# To send HTML mail, the Content-type header must be set
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=iso-8859-1';

		if ( $this->testing) print "{$message}\n";
		else {
			$ts = "Updated: ".date("Y-m-d H:i:s");
			$message2 = str_replace("<body>", "<body><p>{$ts}</p>", $message);
			$message2 = str_replace("</body>", "<p>{$ts}</p></body>", $message2);
			if ( mail("magnusmanske@googlemail.com","Tool monitor alert",$message2,implode("\r\n", $headers),"") ) {
				file_put_contents($this->last_message_file, $message);
			}
		}
	}

	function wrap_up() {
		if ( $this->testing ) print_r($this->alerts);
		$message = $this->message2html_page();
		file_put_contents($this->web_page_file, $message);

		$message = $this->message2html_mail();
		if ( $message != '' ) {
			$message = "<html>\n<body>\n{$message}\n</body>\n</html>";

			if ( $this->testing ) $last_message='hni7gsib7BGUCU'; # Unique
			else $last_message = file_get_contents($this->last_message_file);
			if ($message!=$last_message) {
				$this->send_message($message);
			}
		} else {
			if ( !$this->testing ) file_put_contents($this->last_message_file, $message);
		}
	}

	function load_url($url) {
		$ch = curl_init();
		$cnt = 3;
		while ( $cnt>0 ) {
			try {
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
				curl_setopt($ch, CURLOPT_USERAGENT, 'Magnus Tool Status/1.0');
				$ret = curl_exec($ch);
				if($ret !== FALSE) return $ret;
			} catch (Exception $e) {
				print_r($e);
			}
			$cnt-- ;
			sleep(5);
		}
		curl_close($ch);
	}

	function prep_tool($name) {
		$this->alerts[$name] = [] ;
		return $name;
	}


	function check_quickstatements() {
		$tool = $this->prep_tool("QuickStatements");
		$minutes_ago = 60 ;
		$url = "https://quickstatements.toolforge.org/api.php?action=get_batches_info";
		if ( $this->url_exists($url) ) {
			try {
				$j = json_decode($this->load_url($url));
				$latest_edit = '0' ;
				foreach ( $j->data AS $batch_id => $batch ) {
					$batch = $batch->batch ;
					if ( $batch->ts_created == $batch->ts_last_change ) continue ; # Just created, doesn't count as activity
					if ( $latest_edit*1<$batch->ts_last_change*1 ) $latest_edit = $batch->ts_last_change;
				}
				$latest_edit_ts = strtotime($latest_edit);
				$latest_ts_nice = date("Y-m-d H:i:s",$latest_edit_ts);
				$now = time()-60*$minutes_ago;
				if ( $now*1>$latest_edit_ts*1 ) $this->alerts[$tool][] = ["msg"=>"Last batch edit was more than {$minutes_ago} minutes ago ({$latest_ts_nice})"];
			} catch (Exception $e) {
				$this->alerts[$tool][] = ["msg"=>"API returns non-JSON"];
			}
		} else $this->alerts[$tool][] = ["msg"=>"Website does not answer"];
	}

	function check_petscan() {
		$tool = $this->prep_tool("PetScan");
		if ( !$this->url_exists("https://petscan.wmflabs.org/") ) $this->alerts[$tool][] = ["msg"=>"Website does not answer"];
	}

	function check_mixnmatch() {
		$tool = $this->prep_tool("Mix'n'match");
		$minutes_ago = 60;
		$url = "https://mix-n-match.toolforge.org/api.php?query=get_jobs&max=50";
		try {
			$j = json_decode($this->load_url($url));
			$latest_ts = '00000000000000' ;
			foreach ( $j->data AS $job ) {
				if ( $job->last_ts*1>$latest_ts*1) $latest_ts = $job->last_ts;
			}
			$latest_edit = strtotime($latest_ts);
			$latest_ts_nice = date("Y-m-d H:i:s",$latest_edit);
			$ts = time()-60*$minutes_ago;
			if ( $ts*1>$latest_edit*1 ) $this->alerts[$tool][] = ["msg"=>"Last job change was more than {$minutes_ago} minutes ago ({$latest_ts_nice})"];
		} catch (Exception $e) {
			$this->alerts[$tool][] = ["msg"=>"Can't get job info from API"];
		}
	}

	function check_reinheitsgebot() {
		$tool = $this->prep_tool("Reinheitsgebot");
		$url = "https://www.wikidata.org/w/api.php?action=query&list=users&ususers=Reinheitsgebot&usprop=blockinfo&format=json" ;
		try {
			$j = json_decode($this->load_url($url));
			$user = $j->query->users[0];
			if ( isset($user->blockid) )  $this->alerts[$tool][] = ["msg"=>"Blocked for '{$user->blockreason}' since {$user->blockedtimestamp}"];
		} catch (Exception $e) {
			$this->alerts[$tool][] = ["msg"=>"Can't get user info from API"];
		}
	}

	function check_listeriabot() {
		$tool = $this->prep_tool("ListeriaBot");
		$minutes_ago = 60*8 ; # 8h
		$url = "https://en.wikipedia.org/w/api.php?action=query&list=usercontribs&ucuser=ListeriaBot&uclimit=1&format=json" ;
		try {
			$j = json_decode($this->load_url($url));
			$last_edit = $j->query->usercontribs[0];
			$latest_edit = strtotime($last_edit->timestamp);
			$ts = time()-60*$minutes_ago;
			if ( $ts*1>$latest_edit*1 ) $this->alerts[$tool][] = ["msg"=>"Last enwiki edit was more than {$minutes_ago} minutes ago"];
		} catch (Exception $e) {
			$this->alerts[$tool][] = ["msg"=>"Can't get user contributions from enwiki API"];
		}
	}
}

$testing = ($argv[1]??'')=='test';
$tm = new ToolMonitor($testing) ;
$tm->run();



?>