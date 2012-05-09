<?php
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

function logEvent($nick,$data) {
	global $config;
	if ($config->debug) {
		$log = file_get_contents("status/chat.log");
		$data = str_replace('"',"'",$data);
		$log = '{"sent":'.time().',"event":"('.$nick.') '.$data.'"}'.PHP_EOL.$log;
		file_put_contents("status/chat.log",$log);
	}
}

function putStatus($nick,$status,$action,$last,$to,$online,$smsaddress,$session) {
	global $debug;
	$f = "status/".hash("md5",$nick);
	if (file_exists($f)) $s = file_get_contents($f);
	else $s = '{"nick":"'.$nick.'","status":"0","action":"none","updated":"'.time().'","last":[{"all":"'.time().'"}],"to":"all","online":"true","smsaddress":NULL,"session":"0"}';
	$so = json_decode($s);
	$so->nick = $nick;
	$so->action = $action;
	$so->updated = time();
	// != NULL does not work!
	if (!is_null($status)) $so->status = $status;
	if (!is_null($last)) $so->last = $last;
	if (!is_null($to)) $so->to = $to;
	if (!is_null($online)) $so->online = $online;
	if (!is_null($smsaddress)) $so->smsaddress = $smsaddress;
	if (!is_null($session)) $so->session = $session;
	if (file_put_contents($f, json_encode($so)) == FALSE) logEvent($nick,'putStatus failed for: '.$nick.','.$status.','.$action.','.$last.','.$to.','.$online.','.$smsaddress.','.$session);
	else 	if ($debug>1)	logEvent($nick,'putStatus: '.$nick.','.$status.','.$action.','.$last.','.$to.','.$online.','.$smsaddress.','.$session);
}

function getStatus($nick) {
	global $debug;
	$f = "status/".hash("md5",$nick);
	if (!file_exists($f)) putStatus($nick,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
	$s = file_get_contents($f);
	if ($debug>2)	logEvent($nick,'getStatus: '.$s); 
	return (json_decode($s));
}

// error handler function
function exception_handler($exception) {
	file_put_contents("cache/error.log","Uncaught exception: " . $exception->getMessage() . PHP_EOL , FILE_APPEND);
}
set_exception_handler('exception_handler');

function myErrorHandler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    $str = "";

    switch ($errno) {
			case E_USER_ERROR:
					$str = "ERROR [" . $errno . "] " . $errstr . " on line " . $errline;
					file_put_contents("status/error.log",$str . PHP_EOL , FILE_APPEND);
					exit(1);
					break;
			case E_USER_WARNING:
					$str = "WARNING [" . $errno . "] " . $errstr;
					break;	
			case E_USER_NOTICE:
					$str = "NOTICE [" . $errno . "] " . $errstr;
					break;
			default:
					$str = "Unknown [" . $errno . "] " . $errstr;
					break;
    }

    /* Don't execute PHP internal error handler */
    return true;
}
// set to the user defined error handler
$old_error_handler = set_error_handler("myErrorHandler"); 

?>