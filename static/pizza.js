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
		var wantLink=gui.build("span", "button want-link", "Dabei!");
		//wantLink.onclick=pizza.changeParticipation;
		wantLink.href="#";

		wrapper.appendChild(wantLink);
		wrapper.appendChild(gui.build("h3", "pizza-name "+((nodePizza.bought==1)?"strike":""), nodePizza.name, gui.build("em","lock-display",(nodePizza.lock==1&&nodePizza.bought!=1)?" (Locked)":"")));
		wrapper.appendChild(gui.build("span", "contents", nodePizza.content));
		wrapper.appendChild(gui.build("div", "persons", "Anzahl Personen: "+nodePizza.maxpersons));
		wrapper.appendChild(gui.build("div", "price", "Preis (Gesamt/pro Person): "+nodePizza.price+"/"+(nodePizza.price/nodePizza.maxpersons)));
		wrapper.appendChild(gui.build("div", "people"));

		if(pizza.admin){
			var deleteLink=gui.build("span", "button", "Delete");
			deleteLink.onclick=pizza.deletePizza;
			deleteLink.href="#";
			wrapper.appendChild(deleteLink);
		
			var buyLink=gui.build("span", "button", "Mark bought");
			buyLink.onclick=pizza.buyPizza;
			buyLink.href="#";
			wrapper.appendChild(buyLink);
			
			var unlockLink=gui.build("span", "button", nodePizza.lock==1?"Unlock":"Lock");
			unlockLink.onclick=pizza.togglePizzaLock;
			unlockLink.href="#";
			wrapper.appendChild(unlockLink);
		}
		return wrapper;
	}
};

var pizza={
	userinfo:{"name":"", "id":0},
	admin:"",
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
			//check if credentials match
			api.asyncPost("check-user", JSON.stringify(pizza.userinfo), function(data){
				if(data["check-user"]=="valid"){
					pizza.updateAll();
					gui.displayInterface("main");
					gui.statusDisplay("User detected");
				}
				else{
					pizza.killCookie();
					gui.statusDisplay("Invalid user detected, resetting");
				}
			});
		}
	},

	enableAdmin:function(){
		//ask for admin secret
		pizza.admin=window.prompt("Admin secret?");
		pizza.updateAll();
	},

	deletePizza:function(event){
		var pizzaId=event.target.parentNode.getAttribute("data-id");
		api.asyncPost("delete-pizza", JSON.stringify({"id":pizzaId,"secret":pizza.admin}), function(data){
			if(data.status.db=="ok"&&data.status.access[2]=="granted"){
				gui.statusDisplay("Pizza deleted.");
				pizza.updateAll();
			}
			else if(data.status.access=="denied"){
				gui.statusDisplay("Admin access denied");
			}
			else{
				gui.statusDisplay("Failed to delete: "+data.status["delete-pizza"][2]);
			}
		});
	},

	togglePizzaLock:function(event){
		var pizzaId=event.target.parentNode.getAttribute("data-id");
		api.asyncPost("toggle-lock", JSON.stringify({"id":pizzaId, "secret":pizza.admin}), function(data){
			if(data.status.db=="ok"&&data.status.access[2]=="granted"){
				gui.statusDisplay("Pizza lock status changed");
			}
			else if(data.status.access=="denied"){
				gui.statusDisplay("Admin access denied");
			}
			else{
				gui.statusDisplay("Failed to toggle lock: "+data.status["buy-pizza"][2]);
			}
		});
	},

	buyPizza:function(event){
		var pizzaId=event.target.parentNode.getAttribute("data-id");
		api.asyncPost("buy-pizza", JSON.stringify({"id":pizzaId,"secret":pizza.admin}), function(data){
			if(data.status.db=="ok"&&data.status.access[2]=="granted"){
				gui.statusDisplay("Pizza marked as bought.");
			}
			else if(data.status.access=="denied"){
				gui.statusDisplay("Admin access denied");
			}
			else{
				gui.statusDisplay("Failed to mark pizza as bought: "+data.status["buy-pizza"][2]);
			}
		});
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

	toggleUserReady:function(event){
		api.asyncPost("toggle-ready", JSON.stringify({"id":pizza.userinfo.id}), function(data){
			if(data.status.db=="ok"){
				gui.statusDisplay("User marked as ready");
				pizza.updateUsers();
			}
			else{
				gui.statusDisplay("Failed to set status");
			}
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
							var header=pizzanodes[c].getElementsByClassName("pizza-name")[0];
							var lockDisplay=header.getElementsByClassName("lock-display")[0];
							var persons=pizzanodes[c].getElementsByClassName("persons")[0];
							var people=pizzanodes[c].getElementsByClassName("people")[0];
							var participate=pizzanodes[c].getElementsByClassName("want-link")[0];
							participate.textContent="Dabei!";
							participate.onclick=pizza.changeParticipation;

							if(data.pizzausers[i].users.length<data.pizzausers[i].maxpersons&&data.pizzausers[i].lock!=1){
								participate.style.display="inline-block";
							}
							else{
								participate.style.display="none";
							}
							
							if(data.pizzausers[i].bought==1){
								header.style.className="title strike";
							}
							else{
								header.style.className="title";
							}

							if(data.pizzausers[i].lock==1){
								lockDisplay.textContent=" (Locked)";
							}
							else{
								lockDisplay.textContent="";
							}

							persons.textContent="Anzahl Personen: "+data.pizzausers[i].users.length+"/"+data.pizzausers[i].maxpersons;
						
							people.innerHTML="";
							if(data.pizzausers[i].users.length>0){
								//add users to people
								people.innerText="Beteiligte:";
								var list=gui.build("ul");
								for(var d=0;d<data.pizzausers[i].users.length;d++){
									list.appendChild(gui.build("li",undefined,data.pizzausers[i].users[d].name+(data.pizzausers[i].users[d].ready==1?" (Ready)":"")));
									if(data.pizzausers[i].users[d].name==pizza.userinfo.name){
										if(data.pizzausers[i].lock!=1){
											participate.style.display="inline-block";
											participate.textContent="Toggle Ready";
											participate.onclick=pizza.toggleUserReady;
										}
										else{
											participate.style.display="none";
										}
									}
								}
								people.appendChild(list);
							}
							break;
						}
					}
					if(c==pizzanodes.length){
						//if not found, do pull TODO
						window.alert("Pizza "+data.pizzausers[i].id+" not found locally!");
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
				pizza.updateAll();
			}
			else{
				gui.statusDisplay("Failed to register user");
			}	
		});
	}
};
