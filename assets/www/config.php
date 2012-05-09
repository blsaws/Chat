<?php
    $api_key = "YOUR API KEY";
    $secret_key = "YOUR SECRET KEY";
    $short_code = "YOUR SHORT CODE";
    $FQDN = "https://api.att.com";
    $authorize_redirect_uri = "http://ddpsdk.net/demos/chat";
    $scope = "SMS";
    $control_key = "YOUR CONTORL KEY";

/*
 * SMS API support code from AT&T http://developer.att.com/developer/
 */

	/* Extract POST parmeters from send SMS form
	   and invoke he URL to send SMS along with access token
	*/
function sendSMS($address,$smsMsg) {
	global $debug;
	global $FQDN,$api_key,$secret_key,$scope,$fullToken,$oauth_file;
	if ($debug=='true') sendDebug('sendSMS: '.$address.','.$smsMsg); 	
	$fullToken["accessToken"]=$accessToken;
	$fullToken["refreshToken"]=$refreshToken;
	$fullToken["refreshTime"]=$refreshTime;
	$fullToken["updateTime"]=$updateTime;
      
	$fullToken=check_token($FQDN,$api_key,$secret_key,$scope,$fullToken,$oauth_file);
	$accessToken=$fullToken["accessToken"];
	if ($debug=='true') sendDebug('sendSMS: access token='.$accessToken); 	

	$address =  str_replace("-","",$address);
	$address =  str_replace("tel:","",$address);
	$address =  str_replace("+1","",$address);
	$address = "tel:" . $address;
	
	// Form the URL to send SMS 
	$sendSMS_RequestBody = '{"Address":"'.$address.'","Message":"'.$smsMsg.'"}';//post data
	$sendSMS_Url = "$FQDN/rest/sms/2/messaging/outbox?access_token=".$accessToken;
	$sendSMS_headers = array('Content-Type: application/json');
	if ($debug=='true') sendDebug('sendSMS: $sendSMS_RequestBody='.$sendSMS_RequestBody); 	

	//Invoke the URL
	$sendSMS = curl_init();
	curl_setopt($sendSMS, CURLOPT_URL, $sendSMS_Url);
	curl_setopt($sendSMS, CURLOPT_POST, 1);
	curl_setopt($sendSMS, CURLOPT_HEADER, 0);
	curl_setopt($sendSMS, CURLINFO_HEADER_OUT, 0);
	curl_setopt($sendSMS, CURLOPT_HTTPHEADER, $sendSMS_headers);
	curl_setopt($sendSMS, CURLOPT_POSTFIELDS, $sendSMS_RequestBody);
	curl_setopt($sendSMS, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($sendSMS, CURLOPT_SSL_VERIFYPEER, false);
	$sendSMS_response = curl_exec($sendSMS);
	
	$responseCode=curl_getinfo($sendSMS,CURLINFO_HTTP_CODE);

	/*
	  If URL invocation is successful print success msg along with sms ID,
	  else print the error msg
	*/
	if($responseCode==200 || $responseCode ==201 || $responseCode==300)
	{
		$jsonObj = json_decode($sendSMS_response);
		$smsID = $jsonObj->{'Id'};//if the SMS send successfully ,then will get a SMS id.
		$_SESSION["sms1_smsID"] = $smsID;
		$result = "Message Id:".$smsID;
	}
	else{
		$e = json_decode($sendSMS_response);
		$result = "Error:".json_encode($e);
	}
	curl_close ($sendSMS);
	return($result);
}

		
/*
 * OAuth support code from AT&T 
 */

function RefreshToken($FQDN,$api_key,$secret_key,$scope,$fullToken){

  $refreshToken=$fullToken["refreshToken"];
  $accessTok_Url = $FQDN."/oauth/token";

  //http header values
  $accessTok_headers = array(
			     'Content-Type: application/x-www-form-urlencoded'
			     );

  //Invoke the URL
  $post_data="client_id=".$api_key."&client_secret=".$secret_key."&refresh_token=".$refreshToken."&grant_type=refresh_token";

  $accessTok = curl_init();
  curl_setopt($accessTok, CURLOPT_URL, $accessTok_Url);
  curl_setopt($accessTok, CURLOPT_HTTPGET, 1);
  curl_setopt($accessTok, CURLOPT_HEADER, 0);
  curl_setopt($accessTok, CURLINFO_HEADER_OUT, 0);
  //curl_setopt($accessTok, CURLOPT_HTTPHEADER, $accessTok_headers);
  curl_setopt($accessTok, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($accessTok, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($accessTok, CURLOPT_POST, 1);
  curl_setopt($accessTok, CURLOPT_POSTFIELDS,$post_data);
  $accessTok_response = curl_exec($accessTok);
  $currentTime=time();

  $responseCode=curl_getinfo($accessTok,CURLINFO_HTTP_CODE);
  if($responseCode==200){
    $jsonObj = json_decode($accessTok_response);
    $accessToken = $jsonObj->{'access_token'};//fetch the access token from the response.
    $refreshToken = $jsonObj->{'refresh_token'};
    $expiresIn = $jsonObj->{'expires_in'};
	      
    $refreshTime=$currentTime+(int)($expiresIn); // Time for token refresh
    $updateTime=$currentTime + ( 24*60*60); // Time to get for a new token update, current time + 24h 
	      
    $fullToken["accessToken"]=$accessToken;
    $fullToken["refreshToken"]=$refreshToken;
    $fullToken["refreshTime"]=$refreshTime;
    $fullToken["updateTime"]=$updateTime;
                        
  }
  else{
    $fullToken["accessToken"]=null;
    $fullToken["errorMessage"]=curl_error($accessTok).$accessTok_response;

			
  }
  curl_close ($accessTok);
  return $fullToken;

}
function GetAccessToken($FQDN,$api_key,$secret_key,$scope){
	global $debug;

  $accessTok_Url = $FQDN."/oauth/token";
	    
  //http header values
  $accessTok_headers = array('Content-Type: application/x-www-form-urlencoded');

  //Invoke the URL
  $post_data = "client_id=".$api_key."&client_secret=".$secret_key."&scope=".$scope."&grant_type=client_credentials";
	if ($debug=='true') sendDebug('GetAccessToken: $post_data='.$post_data);
	
  $accessTok = curl_init();
  curl_setopt($accessTok, CURLOPT_URL, $accessTok_Url);
  curl_setopt($accessTok, CURLOPT_HTTPGET, 1);
  curl_setopt($accessTok, CURLOPT_HEADER, 0);
  curl_setopt($accessTok, CURLINFO_HEADER_OUT, 0);
  //  curl_setopt($accessTok, CURLOPT_HTTPHEADER, $accessTok_headers);
  curl_setopt($accessTok, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($accessTok, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($accessTok, CURLOPT_POST, 1);
  curl_setopt($accessTok, CURLOPT_POSTFIELDS,$post_data);
  $accessTok_response = curl_exec($accessTok);
  
  $responseCode=curl_getinfo($accessTok,CURLINFO_HTTP_CODE);
  $currentTime=time();
  /*
   If URL invocation is successful fetch the access token and store it in session,
   else display the error.
  */
  if($responseCode==200)
    {
      $jsonObj = json_decode($accessTok_response);
      $accessToken = $jsonObj->{'access_token'};//fetch the access token from the response.
      $refreshToken = $jsonObj->{'refresh_token'};
      $expiresIn = $jsonObj->{'expires_in'};

      $refreshTime=$currentTime+(int)($expiresIn); // Time for token refresh
      $updateTime=$currentTime + ( 24*60*60); // Time to get a new token update, current time + 24h

      $fullToken["accessToken"]=$accessToken;
      $fullToken["refreshToken"]=$refreshToken;
      $fullToken["refreshTime"]=$refreshTime;
      $fullToken["updateTime"]=$updateTime;
      
    }else{
 
    $fullToken["accessToken"]=null;
    $fullToken["errorMessage"]=curl_error($accessTok).$accessTok_response;

  }
  curl_close ($accessTok);
  return $fullToken;
}
function SaveToken( $fullToken,$oauth_file ){

  $accessToken=$fullToken["accessToken"];
  $refreshToken=$fullToken["refreshToken"];
  $refreshTime=$fullToken["refreshTime"];
  $updateTime=$fullToken["updateTime"];
      

  $tokenfile = $oauth_file;
  $fh = fopen($tokenfile, 'w');
  $tokenfile="<?php \$accessToken=\"".$accessToken."\"; \$refreshToken=\"".$refreshToken."\"; \$refreshTime=".$refreshTime."; \$updateTime=".$updateTime."; ?>";
  fwrite($fh,$tokenfile);
  fclose($fh);
}

function check_token( $FQDN,$api_key,$secret_key,$scope, $fullToken,$oauth_file) {
	global $debug;

  $currentTime=time();
	if ($debug=='true') sendDebug('check_token: $fullToken["updateTime"]='.$fullToken["updateTime"]); 	

  if ( ($fullToken["updateTime"] == null) || ($fullToken["updateTime"] <= $currentTime)){
    $fullToken=GetAccessToken($FQDN,$api_key,$secret_key,$scope);
    if(  $fullToken["accessToken"] == null ){
			if ($debug=='true') sendDebug('check_token: GetAccessToken='.$fullToken["errorMessage"]); 	
    }else{
			if ($debug=='true') sendDebug('check_token: GetAccessToken='.$fullToken["accessToken"]); 	
      SaveToken( $fullToken,$oauth_file );
    }
  }
  elseif ($fullToken["refreshTime"]<= $currentTime){
    $fullToken=RefreshToken($FQDN,$api_key,$secret_key,$scope, $fullToken);
    if(  $fullToken["accessToken"] == null ){
      //      echo $fullToken["errorMessage"];
    }else{
      //      echo $fullToken["accessToken"];
      SaveToken( $fullToken,$oauth_file );
    }
		if ($debug=='true') sendDebug('check_token: RefreshToken='.$accessToken); 	
  }
  
  return $fullToken;
}
?>
