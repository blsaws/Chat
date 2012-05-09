<?php
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

include ("config.php");

if ($_REQUEST['key'] == $control_key) {
	switch ($_REQUEST['action']) {
		case 'config':
			$config = json_decode(file_get_contents("config.json"));
			// config.json format: { "inservice":true, "sms":false, "debug":2 }
			if ($_REQUEST['inservice']=='true') $config->inservice = TRUE;
			else  $config->inservice = FALSE;
			if ($_REQUEST['sms']=='true') $config->sms = TRUE;
			else  $config->sms = FALSE;
			$config->debug = $_REQUEST['debug'];
			file_put_contents("config.json", json_encode($config));
			echo 'Config updated: '.json_encode($config);			
			break;

		case 'clearlog':
			file_put_contents('status/chat.log', '');
			echo 'Log cleared';
			break;

		case 'clearstatus':
			$dh = opendir('status');
			$file = readdir($dh);
			while ($file !== FALSE) {
				if ($file != 'chat.log' && $file != '..' && $file != '.') unlink('status/'.$file);
				$file = readdir($dh);
			}
			/* Fall through to status check as confirmation */
				
		case 'status':
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
			echo json_encode($status).PHP_EOL.file_get_contents("config.json");
			break;
				
		default:
			
			break;
	}
}
else echo 'Sorry, wrong key';
exit();	

?>