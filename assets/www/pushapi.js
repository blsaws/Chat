(function () {

	var global = window;
	
	var PushSource = global.PushSource = function (url) {
		if (options['debug']) logEvent('Pushsource '+url);
		var es = this; 										// return this created object (PushSource) by default
		if (url.indexOf('http://localhost:4035') == 0) { 	// Request for local Push API service
			try {
				this.url = url;
				this.onopen = null;
				this.onmessage = null;
				this.onerror = null;
				this._handler = PushSourceFactory.getNew(url);
				PushSource.registry[this._handler.getIdentifier()] = this;
			}
			catch (eps) {
				alert('Error opening push source: '+eps); 
			}
		}
		else { 												// Request for a network-based Push API service
			try { 
				es = new EventSource(url);
				if (options['debug']) logEvent('Eventsource created');
			}
			catch (e) { }
		}
		return(es);
	};
	
	PushSource.registry = {};
	
	PushSource.triggerEvent = function (evt) {
		PushSource.__open
		PushSource.registry[evt.target]['on' + evt.type].call(global, evt);
	}
	
	PushSource.prototype.close = function () {
		this._handler.close();
		this.readyState = 2;
	}	

})();