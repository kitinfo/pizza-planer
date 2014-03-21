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

	createPizzaNode:function(nodePizza){
		var wrapper=gui.build("div", "pizza");
		wrapper.setAttribute("data-id", nodePizza.id);
		var wantLink=gui.build("a", "button want-link", "Dabei!");
		wantLink.onclick=pizza.changeParticipation;
		wantLink.href="#";

		wrapper.appendChild(wantLink);
		wrapper.appendChild(gui.build("h3", undefined, nodePizza.name));
		wrapper.appendChild(gui.build("span", "contents", nodePizza.content));
		wrapper.appendChild(gui.build("div", "persons", "Anzahl Personen: "+nodePizza.maxpersons));
		wrapper.appendChild(gui.build("div", "price", "Preis (Gesamt/pro Person): "+nodePizza.price+"/"+(nodePizza.price/nodePizza.maxpersons)));
		wrapper.appendChild(gui.build("div", "people"));

		return wrapper;
	}
};

var pizza={
	userinfo:{"name":"", "id":0},
	interval:undefined,

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
			gui.statusDisplay("No user detected");
			return;
		}
		if(pizza.userinfo.id!=0&&pizza.userinfo.name){
			//TODO check if credentials match
			pizza.updateAll();
			gui.displayInterface("main");
			gui.statusDisplay("User detected");
		}
	},

	changeParticipation:function(event){
		var pizzaId=event.target.parentNode.getAttribute("data-id");
		api.asyncPost("change-pizza", JSON.stringify({"id":pizza.userinfo.id, "to":pizzaId}), function(data){
			if(data.status.db=="ok"){
				gui.statusDisplay("Participation registered");
			}
			else{
				gui.statusDisplay("Failed to participate in pizza :(");
			}
			pizza.updateUsers();
		});
	},

	createPizza:function(){
		var pname=gui.elem("new-name").value;
		var pdesc=gui.elem("new-contents").value;
		var pprice=parseFloat(gui.elem("new-price").value);
		var pnump=parseInt(gui.elem("new-maxpersons").value,10);

		if(pname&&pdesc&&!Number.isNaN(pprice)&&!Number.isNaN(pnump)){
			api.asyncPost("add-pizza", JSON.stringify({"name":pname, "maxpersons":pnump, "price":pprice, "content":pdesc}),function(data){
				//window.alert(JSON.stringify(data));
				if(data.status.db=="ok"){
					//refresh pizzas
					pizza.updateAll();
					//set view
					gui.displayInterface("main");
				}
				else{
					gui.statusDisplay("Failed to add your pizza :(");
				}
			});	
		}
		else{
			gui.statusDisplay("Invalid input.");
		}
	},

	updateAll:function(){
		if(pizza.interval){
			window.clearInterval(pizza.interval);
		}

		//kill current displays
		var pizzas=document.getElementsByClassName("pizza");
		while(pizzas.length>0){
			pizzas[0].parentNode.removeChild(pizzas[0]);
		}

		//get all pizzas
		api.asyncGet("pizzas",function(data){
			if(data.status.db=="ok"&&data.pizzas){
				//window.alert(JSON.stringify(data.pizzas));
				//create elements & display
				data.pizzas.forEach(function(pizza){
					var domNode=gui.createPizzaNode(pizza);
					gui.elem("pizza-main").appendChild(domNode);
				});
				pizza.updateUsers();
				pizza.interval=setInterval(pizza.updateUsers,5000)
			}
			else{
				gui.statusDisplay("Failed to fetch pizza information");
			}
		});	
	},

	updateUsers:function(){
		api.asyncGet("pizza-users", function(data){
			if(data.status.db=="ok"&&data.pizzausers){
				var pizzanodes=document.getElementsByClassName("pizza");
				for(var i=0;i<data.pizzausers.length;i++){
					//find node with this pizza
					for(var c=0;c<pizzanodes.length;c++){
						if(pizzanodes[c].getAttribute("data-id")==data.pizzausers[i].id){
							//add user count to user stats
							var persons=pizzanodes[c].getElementsByClassName("persons")[0];
							var people=pizzanodes[c].getElementsByClassName("people")[0];
							var participate=pizzanodes[c].getElementsByClassName("want-link")[0];

							if(data.pizzausers[i].users.length<data.pizzausers[i].maxpersons){
								participate.style.display="inline-block";
							}
							else{
								participate.style.display="none";
							}

							persons.textContent="Anzahl Personen: "+data.pizzausers[i].users.length+"/"+data.pizzausers[i].maxpersons;
						
							people.innerHTML="";
							if(data.pizzausers[i].users.length>0){
								//add users to people
								people.innerText="Beteiligte:";
								var list=gui.build("ul");
								for(var d=0;d<data.pizzausers[i].users.length;d++){
									list.appendChild(gui.build("li",undefined,data.pizzausers[i].users[d].name));
								}
								people.appendChild(list);
							}
							break;
						}
					}
					if(c==pizzanodes.length){
						//if not found, do pull TODO
						window.alert("Pizza not found locally!");
					}
				}
			}
			else{
				gui.statusDisplay("Failed fetching the user data");
			}
		});
	},

	userCreate:function(){
		//get user name
		var uname=gui.elem("user-input").value;
		//check for empty
		if(!uname||uname.match("/^\s*$/")){
			gui.statusDisplay("Invalid user name");
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
