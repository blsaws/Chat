package net.bkaj.chat;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;

 public class PushReceiver extends BroadcastReceiver {
	 @Override 
	 public void onReceive(Context context, Intent intent) { 
		 Intent i = new Intent();
		 i.setAction(intent.getAction());
		 i.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
		 i.setClassName( "net.bkaj.chat", "net.bkaj.chat.ChatActivity" );
		 i.putExtras(intent.getExtras());
		 context.startActivity(i); 
	 }
}