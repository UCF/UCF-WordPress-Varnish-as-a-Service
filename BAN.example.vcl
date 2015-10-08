/* BAN request from allowed hosts */
if (req.request == "BAN") {
	if(!client.ip ~ admin) {
		error 405 "Not allowed.";
	} else {
		if(!req.http.x-ban-host || req.http.x-ban-host == "") {
			ban("obj.http.x-url ~ " + req.http.x-ban-url);
    		error 200 "Banned2";
		} else {
			ban("obj.http.x-host == " + req.http.x-ban-host + " && obj.http.x-url ~ " + req.http.x-ban-url);
			error 200 "Banned3";
		}
	}
}
