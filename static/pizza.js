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
	},

	asyncGet:function(call, completionfunc){
		ajax.asyncGet(api.apiPath+"?"+call, function(request){
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
		}, function(e){
			gui.statusDisplay("Error: "+e.message);
		});
	}
};

var gui={
	elem:function(id){
		return document.getElementById(id);
	},

	build:function(tag, cname, text, child){
		var node=document.createElement(tag);
		if(cname){
			node.className=cname;
		}
		if(text){
			node.textContent=text;
		}
		if(child){
			node.appendChild(child);
		}

		return node;
	},

	updateMovingParts:function(){
		gui.elem("main-user").textContent=pizza.userinfo.name;
		gui.elem("new-name").value="";
		gui.elem("new-contents").value="";
		gui.elem("new-price").value="";
		gui.elem("new-maxpersons").value="";
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
		gui.updateMovingParts();
	},

	createPizzaNode:function(pizza){
		var wrapper=gui.build("div", "pizza");
		var wantLink=gui.build("a", "button want-link", "Dabei!");
		wantLink.href="#";

		wrapper.appendChild(wantLink);
		wrapper.appendChild(gui.build("h3", undefined, pizza.name));
		wrapper.appendChild(gui.build("span", "contents", pizza.content));
		wrapper.appendChild(gui.build("div", "persons", "Anzahl Personen: "+pizza.maxperson));
		wrapper.appendChild(gui.build("div", "price", "Preis (Gesamt/pro Person): "+pizza.price+"/"+(pizza.price/pizza.maxperson)));

		return wrapper;
	}
};

var pizza={
	userinfo:{"name":"", "id":0},

	killCookie:function(){
		cookies.setCookie("pizza_user", "");
	},

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
			pizza.updateAll();
			gui.displayInterface("main");
		}
	},

	updateAll:function(){
		//kill current displays
		var pizzas=document.getElementsByClassName("pizza");
		while(pizzas.length>0){
			pizzas[0].parentNode.removeChild(pizzas[0]);
		}

		//get all pizzas
		api.asyncGet("pizzas",function(data){
			if(data.status.db=="ok"&&data.pizzas){
				window.alert(JSON.stringify(data.pizzas));
				//create elements & display
				data.pizzas.forEach(function(pizza){
					var domNode=gui.createPizzaNode(pizza);
					gui.elem("pizza-main").appendChild(domNode);
				});
			}
		});	
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
			//window.alert(JSON.stringify(data));
			if(data.status.db=="ok"&&data.user!=0){
				pizza.userinfo={};
				pizza.userinfo.name=uname;
				pizza.userinfo.id=data.user;
				cookies.setCookie("pizza_user", JSON.stringify(pizza.userinfo));
				gui.displayInterface("main");
			}
			else{
				gui.statusDisplay("Failed to register user");
			}	
		});
	}
};
