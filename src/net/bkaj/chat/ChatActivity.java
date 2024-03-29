package net.bkaj.chat;	

import java.net.URISyntaxException;

import net.bkaj.chat.R;

import android.app.Activity;
import android.content.Intent;
import android.os.Bundle;
import android.telephony.SmsMessage;
import android.webkit.WebChromeClient;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

public class ChatActivity extends Activity {

    public static WebView webview;
    public static GapPushSource pushSource;
    public static boolean debug = false;
    
    public class PushSourceFactory {
		private WebView mView;
		public PushSourceFactory(WebView webview) {
			mView = webview;
		}
		public PushSource getNew(String url) throws URISyntaxException {
			pushSource = new GapPushSource(mView, url);
		    return pushSource;
		}
	} 
	
    /** Called when the activity is first created. */
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.main);
        
        webview = new WebView(this);
        setContentView(webview);
        webview.getSettings().setJavaScriptEnabled(true);
        webview.getSettings().setDomStorageEnabled(true);

        final Activity activity = this;
        webview.setWebChromeClient(new WebChromeClient() {
          public void onProgressChanged(WebView view, int progress) {
            activity.setProgress(progress * 1000);
          }
        });
        webview.setWebViewClient(new WebViewClient() {
          public void onReceivedError(WebView view, int errorCode, String description, String failingUrl) {
            Toast.makeText(activity, "Oh no! " + description, Toast.LENGTH_SHORT).show();
          }
        });
       
       PushSourceFactory pushSourceFactory = new PushSourceFactory(webview);
       webview.addJavascriptInterface(pushSourceFactory, "PushSourceFactory");
       webview.loadUrl("http://ddpsdk.net/demos/chat");
    }
    
    @Override
    public void onNewIntent(Intent intent) {
    	if (pushSource.mReadyState == PushSource.CONNECTING) {
        	pushSource.mReadyState = PushSource.OPEN;
    		pushSource.onopen();
			if (debug) Toast.makeText(getBaseContext(), "PushSource open", Toast.LENGTH_LONG).show();
    	}
    	if (pushSource.mReadyState == PushSource.OPEN) {
        	Bundle bundle = intent.getExtras();
        	String str = intent.getAction()+":";
        	String data = "";
    		 if (bundle != null) { 
    			if (intent.getAction().equals("android.provider.Telephony.WAP_PUSH_RECEIVED")) {
    			   String mimeType = intent.getType();
    			   str = "mimeType:"+mimeType;
    			   String headers = intent.getStringExtra("header");
    			   data = intent.getStringExtra("data");
    			   str += "/n" + data;
    			   String contentTypeParameters = intent.getStringExtra("contentTypeParameters");
    			   // TODO: Filter based upon
    					// pushSource.acceptSource
    					// pushSource.acceptContentType
    					// pushSource.acceptApplicationId
    			   pushSource.onmessage(headers+"\n"+str); 
    			   Toast.makeText(getBaseContext(), str, Toast.LENGTH_LONG).show();
    			}
    			else {
    				int i = 0;
    				SmsMessage[ ] msgs = null; 
    				Object[ ] pdus = (Object[ ]) bundle.get("pdus"); 
    				msgs = new SmsMessage[pdus.length];
    				String source;
    				for (i=0; i<msgs.length; i++) { 
    					msgs[i] = SmsMessage.createFromPdu((byte[ ])pdus[i]);
    					source = msgs[i].getOriginatingAddress();
    					str += "Message from sms:" + source; 
    					str += " :";
    					data = msgs[i].getMessageBody().toString();
    					str += data;
    					// TODO: Need to deliver to all PushSource objects created (there could be more than one)
    					if (pushSource.acceptSource == null || pushSource.acceptSource.indexOf(source) > -1) {
    						pushSource.onmessage(data);
    					}
    					else str += " (not delivered: push-accept-source = " + pushSource.acceptSource + ")";
    				}
    				if (debug) Toast.makeText(getBaseContext(), str, Toast.LENGTH_LONG).show();
    			}
    		}
    	}
    }
}