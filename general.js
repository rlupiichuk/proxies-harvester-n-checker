	var req;
	var link;

	function loadXMLDoc(url) {
		if (window.XMLHttpRequest) {
			req = new XMLHttpRequest();
			req.onreadystatechange = processReqChange;
			req.open("GET", url, true);
			req.send(null);
		} else if (window.ActiveXObject) {
			req = new ActiveXObject("Microsoft.XMLHTTP");
			if (req) {
				req.onreadystatechange = processReqChange;
				req.open("GET", url, true);
				req.send();
			}
		}
	}

	function processReqChange() {
		link.innerHTML = stat(req.readyState);

		ab = window.setTimeout("req.abort();", 10000);

		if (req.readyState == 4)
			clearTimeout(ab);
	}

	function stat(n)
	{
		switch (n) {
			case 0:
				return "not init";
				break;

			case 1:
				return "working...";
				break;

			case 2:
				return "finished";
				break;

			case 3:
				return "processing...";
				break;

			case 4:
				reload();
				link.onclick = function() {};
				return "reloading...";
				break;

			default:
				return "error";
		}
	}

	function reload() {
		document.location.reload();
	}

	function justLoad(url, caller, start_text) {
		link = caller;
		caller.innerHTML = 'working...';
		loadXMLDoc(url);
		caller.innerHTML = start_text;
	}