<?php
header("Cache-Control: no-cache");
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
 
$action = $_REQUEST['action'];
$nick = $_REQUEST['nick'];
$to = $_REQUEST['to'];
$from = $_REQUEST['push-accept-source'];

$headers = array();
$hstr = "";
foreach($_SERVER as $key => $value) {
	if($key == 'REMOTE_ADDR') {
		$headers['Source-IP'] = $value;
	}
	if(substr($key, 0, 5) == 'HTTP_') {
		$headers[str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))))] = $value;
	}
}

$config = json_decode(file_get_contents("config.json"));
$debug = $config->debug;
if ($debug>1) {
	$str = "eventsource: ";
	foreach($_REQUEST as $key => $value) $str .= ' '.$key."=".preg_replace('/&(?!\w+;)/', '&amp;', $value);
	foreach($headers as $key => $value) $str .= ' '.$key."=".$value;
	logEvent($nick,$str);
	}

if (isset($headers["Last-Event-Id"])) $id = $headers["Last-Event-Id"]+1;
else $id = 1;
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
 	$post = json_decode(trim(file_get_contents('php://input')));
 	if (isset($_POST["Last-Event-ID"])) $id = $_POST["Last-Event-ID"]+1;
	$to = $post->to;
	$msg = $post->msg;
	}
	

/*
 * Main Loop
 */
	$s = getStatus($nick);
	$last = $s->last;
	$session = $s->session;
	$config = json_decode(file_get_contents("config.json"));
	if ($debug>0) logEvent($nick,'eventsource: inservice='.$config->inservice.', status='.$s->status);
	while ($config->inservice && $s->status>0):
		if ($debug>2) logEvent($nick,'eventsource: *** loop *** status='.$s->status); 
		$new = array();
		// TODO: provide from list management in another thread, read from list from status file
		if ($from == 'all') {
			$dh = opendir('cache');
			$file = readdir($dh);
			while ($file !== FALSE) {
				if ($file != '..' && $file != '.') $new = findNew($nick,'cache/'.$file,$from,$last,$new);
				$file = readdir($dh);
			}
		}
		else {
			$src = explode(',',$from);
			for ($i=0;$i<count($src);$i++) {
				$file = 'cache/'.hash("md5",$src[$i]);
				$new = findNew($nick,$file,$from,$last,$new);
			}
		}
		if (count($new)>0) {
			if ($debug>1) logEvent($nick,'eventsource: *** ready to send *** ('.$s->session.'<='.$session.')'); 
			$last = sendNew($nick,$new,$from,$last);
		}
		sleep(3);
		$s = getStatus($nick);
		if ($debug>2) logEvent($nick,'eventsource: *** end loop *** status='.$s->status); 
		$config = json_decode(file_get_contents("config.json"));
	endwhile;
	if ($s->status == 0) logEvent($nick,'eventsource: session closed');

function findNew($nick,$file,$from,$last,$new) {
	global $debug;
	if (file_exists($file)) {
		$log = file($file);
		$loge = array_pop($log);
		$ms = json_decode($loge);
		$fnick = $ms->nick;
		if ($fnick==$nick) $own = 1;
		else $own = 0;
		if (strstr($from,$fnick)) $t = $last[$fnick];
		else $t = $last->all;
 		if ($debug>2) logEvent($nick,'eventsource: Checking '. $fnick . ' log for new events since '. $t);
		$stop = 100;
		while (count($log)>=0 && --$stop>0):
			if ($ms->sent/1 > $t/1) {
				if ($ms->to == 'all') $all = 1; else $all = 0;
				if (stripos($ms->to,$nick)!==false) $tome = 1; else $tome = 0;
				if ($debug>2) logEvent($nick,'eventsource: ('.$own.'+'.$all.'+'.$tome.'='.($own+$all+$tome).') sent '.($ms->sent-$t).' ms ago: ' . trim($loge));
				if (($own+$all+$tome)>0) $new[] = $loge;
				$loge = array_pop($log);
				$ms = json_decode($loge);
			}
			else break;
		endwhile;
	}
	return($new);
}

function sendNew($nick,$new,$from,$last) {
	global $debug;
	if ($debug>1)	logEvent($nick,'eventsource: sendNew '.count($new).' items'); 
	arsort($new);
	$sent = 0;
	for (;count($new)>0;$j++) {
		$ms = json_decode(array_pop($new));
		$sent++;
		sendEvent($nick,date("h:m:s",$ms->sent) . ':' . $ms->nick . ' to ' . $ms->to . '>' . $ms->msg);
		$fnick = $ms->nick;
		if (stripos($from,$fnick)!==FALSE) $last->$fnick = $ms->sent;
		else if ($ms->sent>$last->all) $last->all = $ms->sent;
	}
	if ($sent>0) putStatus($nick,NULL,'sendNew',$last,NULL,NULL,NULL,NULL);
	return($last);
}

function sendEvent($nick,$data) {
	global $id, $debug;
	if ($debug>1)	logEvent($nick,'eventsource: sendEvent to '.$nick.': '.$data);
	$str = "event: message".PHP_EOL . "id: ".($id++).PHP_EOL . 'data: '.$data;
	echo $str.PHP_EOL.PHP_EOL;	
	flush();
	ob_flush();
}


?>