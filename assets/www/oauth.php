<?php
        include ("config.php");
        session_start();

        $authCode = $_GET[code];

        //If the authorization code is null,then retrieve the auth code.
        if ($authCode == null || $authCode == "") {

            //Form URL to get the authorization code
            $authorizeUrl = "$FQDN/oauth/authorize";
            $authorizeUrl .= "?scope=".$scope;
            $authorizeUrl .= "&client_id=".$api_key;
            $authorizeUrl .= "&redirect_uri=".$authorize_redirect_uri;

            header("Location: $authorizeUrl");
        } else {
            // **********************************************************************
            // ** code to get access token by passing auth code, client ID and
	    // ** client secret
            // **********************************************************************

            //Form URL to get the access token
            $accessTok_Url = "$FQDN/oauth/access_token";
            $accessTok_Url .= "?client_id=".$api_key;
            $accessTok_Url .= "&client_secret=".$secret_key;
            $accessTok_Url .= "&code=" . $authCode . "&grant_type=authorization_code";
            
	    //http header values
            $accessTok_headers = array(
            'Content-Type: application/x-www-form-urlencoded'
            );

	    //Invoke the URL
            $accessTok = curl_init();
            curl_setopt($accessTok, CURLOPT_URL, $accessTok_Url);
            curl_setopt($accessTok, CURLOPT_HTTPGET, 1);
            curl_setopt($accessTok, CURLOPT_HEADER, 0);
            curl_setopt($accessTok, CURLINFO_HEADER_OUT, 0);
            curl_setopt($accessTok, CURLOPT_HTTPHEADER, $accessTok_headers);
            curl_setopt($accessTok, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($accessTok, CURLOPT_SSL_VERIFYPEER, false);
            $accessTok_response = curl_exec($accessTok);

	    $responseCode=curl_getinfo($accessTok,CURLINFO_HTTP_CODE);
	   /*
	       If URL invocation is successful fetch the access token and store it in session,
	       else display the error.
	   */
      	if($responseCode==200){
	    $jsonObj = json_decode($accessTok_response);
            $accessToken = $jsonObj->{'access_token'};//fetch the access token from the response.
            $_SESSION["sms_access_token"]=$accessToken;//store the access token in to session.
		}
		else{
		echo curl_error($accessTok);
		}
            curl_close ($accessTok);
            header("location:index.php");//redirect to the index page.
            exit;
        }
?>
