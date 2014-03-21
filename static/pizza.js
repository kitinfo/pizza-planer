var gui={
	elem:function(id){
		return document.getElementById(id);
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
}

var pizza={
	apiPath:"server/db.php",
	user:{},

	init:function(){
		//check if cookie present
		try{
			pizza.user=JSON.parse(cookies.getCookie("pizza_user"));
		}
		catch(e){
			//no cookie
			return;
		}
		if(pizza.user.id&&pizza.user.name){
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
		//if taken, err out
		//else display main
	}
}
