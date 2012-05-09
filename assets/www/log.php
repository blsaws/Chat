<?php
header("Content-Type: text/event-stream");
ini_set('zlib.output_compression', "0");
ini_set('output_buffering', "0");
ini_set('implicit_flush', "1");
ob_implicit_flush(true);
ob_end_flush();

/*
 * Chat Demo
 * index.html: Webapp providing Chat Demo UI, preferences and EventSource connection management 
 * chat.css: stylesheet
 * pushapi.js: Code emulating the EventSource API for SMS based events, or invoking the EventSource API directly when needed
 * EventSource.js: EventSource polyfill by Yaffle: https://github.com/Yaffle/EventSource (adds eventsource support for Android browser/WebView)
 * chat.php: Chat session management, message request collection, and message delivery via SMS
 * eventsource.php: Chat event delivery via EventSource
 * include.php: Common code
 * config.php: service static configuration and SMS API code from AT&T
 * config.json: service dynamic configuration file. Permissions must be world read/write.
 * control.html: Control panel
 * control.php: Control panel server code
 * log.php: Control panel events
 * oauth.php: OAUth code provided by AT&T
 * php5.ini: PHP configuration
 * .htaccess: Apache configuration
 * status/: directory for chatter status and debug log. Permissions must be world read/write/execute.
 * cache/: directory for chatter event log. Permissions must be world read/write/execute.
 */

include ("include.php");
include ("config.php");
 
if ($_REQUEST['key'] == $control_key) {
	$config = json_decode(file_get_contents("config.json"));
	$debug = $config->debug;
	
	if (isset($headers["Last-Event-Id"])) $id = $headers["Last-Event-Id"]+1;
	else $id = 1;
	if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	 	$post = json_decode(trim(file_get_contents('php://input')));
	 	if (isset($_POST["Last-Event-ID"])) $id = $_POST["Last-Event-ID"]+1;
		}
		
	/*
	 * Main Loop
	 */
	sendEvent('Welcome to the chat control panel');
	// Example status file
	// {"nick":"GNote","status":0,"action":"close","updated":1333740788,"last":{"all":1333740712,"GNote":1333740704},
	// "to":"all","online":"true","smsaddress":null,"session":1333740704}
	// Build status array
	$status = array();
	$dh = opendir('status');
	$file = readdir($dh);
	while ($file !== FALSE) {
		if ($file != 'chat.log' && $file != '..' && $file != '.') {
			$s = file_get_contents('status/'.$file);
			$status[] = json_decode($s);
		}
		$file = readdir($dh);
	}	
	// Show who's online
	$str = "Online (of ".count($status).' registered): ';
	for ($i=0; $i<count($status);$i++) if ($status[$i]->status/1>0) $str = $str.$status[$i]->nick.' ';
	sendEvent($str);
	$last = time();
	$logt = time();
	while (TRUE):
		$new = array();
		$dh = opendir('cache');
		$file = readdir($dh);
		while ($file !== FALSE) {
			if ($file != '..' && $file != '.') $new = findNew('cache/'.$file,$last,$new);
			$file = readdir($dh);
		}
		if (count($new)>0) $last = sendNew($new,$last);
		
		$log = file('status/chat.log');
		$logts = $logt;
		for ($i=0;$i<count($log);$i++) {
			$loge = json_decode($log[$i]);
			if ($loge->sent > $logt) {
				if ($i==0) $logts = $loge->sent;
				sendEvent($log[$i]);
			}
			else break;
		}		
		$logt = $logts;
		sleep(3);
	endwhile;
}
else sendEvent('Sorry, wrong key');

function findNew($file,$last,$new) {
	$log = file($file);
	$loge = array_pop($log);
	$ms = json_decode($loge);
	while (count($log)>=0):
		if ($ms->sent > $last) {
			$new[] = $loge;
			$loge = array_pop($log);
			$ms = json_decode($loge);
		}
		else break;
	endwhile;
	return($new);
}

function sendNew($new,$last) {
	arsort($new);
	$sent = 0;
	for (;count($new)>0;$j++) {
		$ms = json_decode(array_pop($new));
		$sent++;
		sendEvent(date("h:m:s",$ms->sent) . ':' . $ms->nick . ' to ' . $ms->to . '>' . $ms->msg);
		if ($ms->sent>$last) $last = $ms->sent;
	}
	return($last);
}

function sendEvent($data) {
	global $id;
	$str = "event: message".PHP_EOL . "id: ".($id++).PHP_EOL . 'data: '.$data;
	echo $str.PHP_EOL.PHP_EOL;	
	flush();
	ob_flush();
}

?>