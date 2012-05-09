<?php
header("Cache-Control: no-cache");
header("Content-Type: application/json");
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
$action = $_REQUEST['action'];
$nick = $_REQUEST['nick'];
$to = $_REQUEST['to'];
$from = $_REQUEST['push-accept-source'];
$smsaddress = $_REQUEST['smsaddress'];
if (!isset($smsaddress)) $smsaddress = NULL;
$online = $_REQUEST['online'];

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
	$str = "chat: ";
	foreach($_REQUEST as $key => $value) $str .= ' '.$key."=".preg_replace('/&(?!\w+;)/', '&amp;', $value);
	foreach($headers as $key => $value) $str .= ' '.$key."=".$value;
	logEvent($nick,$str);
	}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
 	$post = json_decode(trim(file_get_contents('php://input')));
	$to = $post->to;
	$msg = $post->msg;
	}

$s = getStatus($nick);
if ($debug>1) logEvent($nick,'chat: '.$action.' nick='.$nick.' status='.$s->status.' inservice='.$config->inservice);

switch ($action) {
	case "open": 
		openChat($nick,$from,$online,$smsaddress);
		break;
	case "close": 
		closeChat($nick); 
		break;
	case "send": 
		chat(FALSE,$nick,$to,$msg); 	
		echo '{"time":"'.time().'","success":true,"status":'.json_encode(getStatus($nick)).'}'; 
		break;
	case "update": 
		putStatus($nick,NULL,'update',NULL,NULL,$online,$smsaddress,NULL);
		echo '{"time":"'.time().'","success":true,"status":'.json_encode(getStatus($nick)).'}';	
		break;
	default:
		echo '{"time":"'.time().'","success":false,"reason":"invalid action"}';
}

function openChat($nick,$from,$online,$smsaddress) {
	global $debug,$config;
	$session = time();
	putStatus($nick,NULL,'open',NULL,NULL,$online,$smsaddress,$session);
	$s = getStatus($nick);
	if ($config->inservice) {
		if ($from == 'all') { $last->all = time(); $last->$nick = time(); }
		else {
			$from .= ','.$nick;
			$f = explode(',',$from);
			for ($i=0;$i<count($f);$i++) {
				if (isset($s->last[$f[$i]])) $last[$f[$i]] = $s->last[$f[$i]]; 
				else $last[$f[$i]] = time();
			}
		}
		putStatus($nick,2,'open',$last,NULL,$online,$smsaddress,$session);
		sleep(1); // wait 1 second so these events will be delivered!
		chat(TRUE,$nick,$nick,'Chat open as ('.$nick.') at '.date(DATE_RFC822).', accepting messages from ('.$from.')');
		chat(FALSE,$nick,'all',$nick.' is now online');	
		echo '{"time":"'.time().'","success":true,"status":'.json_encode(getStatus($nick)).'}';
		}
	else echo '{"time":"'.time().'","success":false,"reason":"service not available","status":'.json_encode(getStatus($nick)).'}';	
}

function chat($system,$nick,$to,$msg) {
	global $debug,$config;
	if ($system!=TRUE) putStatus($nick,2,'send',NULL,$to,NULL,NULL,NULL);
	$ms = json_encode(array("sent"=>time(),"nick"=>$nick,"to"=>$to,"msg"=>$msg));
	file_put_contents("cache/".hash("md5",$nick),$ms.PHP_EOL,FILE_APPEND);
	$ms = json_decode($ms);
	if ($config->sms==TRUE) {
		if ($to=='all') {
			$dh = opendir('status');
			$file = readdir($dh);
			while ($file !== FALSE) {
				if ($file != 'chat.log' && $file != '..' && $file != '.') {
					$s = file_get_contents('status/'.$file);
					$status = json_decode($s);
					logEvent($nick,$status->status.' '.$status->online.' '.$status->smsaddress);
					if ($status->status>0 && $status->online==0 && $status->smsaddress != '') {
						$result = sendSMS($status->smsaddress,date("h:m:s",$ms->sent) . ':' . $ms->nick . ' to ' . $ms->to . '>' . $ms->msg);
						if ($debug>0) logEvent($status->nick,'chat: SMS sent to '.$status->smsaddress.' with result '.$result);
					}
				}
				$file = readdir($dh);
			}	
		}
		else {
			$f = explode(',',$ms->to);
			for ($i=0;$i<count($f);$i++) {
				$status = getStatus($f[$i]);
				if ($debug>0) logEvent($nick,'chat: checking message target '.$f[$i].' status('.$status->status.') online('.$status->online.') smsaddress('.$status->smsaddress.')');
				if (($status->status/1>0) && ($status->online=='false') && ($status->smsaddress != '')) {
					if ($debug>0) logEvent($status->nick,'chat: Sending SMS sent to '.$status->smsaddress);
					$result = sendSMS($status->smsaddress,date("h:m:s",$ms->sent) . ':' . $ms->nick . ' to ' . $ms->to . '>' . $ms->msg);
					if ($debug>0) logEvent($status->nick,'chat: SMS sent to '.$status->smsaddress.' with result '.$result);
				}
			}
		}
	}
}

function closeChat($nick) {
	chat(TRUE,$nick,'all',$nick.' is now offline');
	putStatus($nick,0,'close',NULL,NULL,NULL,'',NULL);
	echo '{"time":"'.time().'","success":true,"status":'.json_encode(getStatus($nick)).'}';
	unlink("cache/".hash("md5",$nick));
}

?>