var api={
	apiPath:"server/db.php",

	asyncPost:function(call, payload, completionfunc){
		ajax.asyncPost(api.apiPath+"?"+call, payload, function(request){
			if(request.status!=200){
				gui.statusDisplay("Server error "+request.status);
				return;
			}
			
			try{
				var data=JSON.parse(request.responseText);
			}
			catch(e){
				gui.statusDisplay("Error parsing response data");
				return;
			}

			completionfunc(data);
		}, function(err){
			gui.statusDisplay("Error: "+err.message);
		}, "application/json");
	}
};

var gui={
	elem:function(id){
		return document.getElementById(id);
	},

	statusDisplay:function(text){
		gui.elem("status-out").textContent=text;
	},

	displayInterface:function(iface){
		switch(iface){
			case "main":
				gui.elem("user-login").style.display="none";
				gui.elem("pizza-main").style.display="block";
				gui.elem("pizza-new").style.display="none";
				break;
			case "new":
				gui.elem("user-login").style.display="none";
				gui.elem("pizza-main").style.display="none";
				gui.elem("pizza-new").style.display="block";
				break;
			default:
				gui.elem("user-login").style.display="block";
				gui.elem("pizza-main").style.display="none";
				gui.elem("pizza-new").style.display="none";
				break;
		}
	}
};

var pizza={
	userinfo:{"name":"", "id":0},

	init:function(){
		//check if cookie present
		try{
			pizza.userinfo=JSON.parse(cookies.getCookie("pizza_user"));
		}
		catch(e){
			//no cookie
			return;
		}
		if(pizza.userinfo.id!=0&&pizza.userinfo.name){
			//TODO check if credentials match
			gui.displayInterface("main");
		}
	},

	userCreate:function(){
		//get user name
		var uname=gui.elem("user-input").value;
		//check for empty
		if(!uname||uname.match("/^\s*$/")){
			return;
		}
		//try to register
		api.asyncPost("add-user", JSON.stringify({"name":uname}), function(data){
			window.alert(JSON.stringify(data));
			if(data.status.status=="ok"&&data.user!=0){
				pizza.userinfo={};
				pizza.userinfo.name=uname;
				pizza.userinfo.id=data.user;
				cookies.setCookie("pizza_user", JSON.stringify(pizza.userinfo));
				gui.displayInterface("main");
			}	
		});
		//if taken, err out
		//else display main
	}
};
