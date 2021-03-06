var timer = null;
var allEvents=new Array();
var newEvents=new Array();
var oldEvents=new Array();
var api_div_numbers=21;


$(window).load(function() {
	initilise();
	if(timer) {
		clearTimeout(timer);
	}
	timer =setTimeout(main , 100 );
});



function fetchEvents(){
	return oldEvents;
}


function main() {
	var url=localStorage["ip_address"]+'/include/api.php?op=get&op2=events&return_type=csv&apipass='+localStorage["api_pass"]+'&user='+localStorage["user_name"]+'&pass='+localStorage["pass"];
	var feedUrl = url;
	localStorage["data_check"]="true";
	req = new XMLHttpRequest();
	req.onload = handleResponse;
	req.onerror = handleError;
	req.open("GET", feedUrl, true);
	req.send(null);
}

function handleError() {
	localStorage["data_check"]="false";
		if(timer) {
			clearTimeout(timer);
		}
		timer =setTimeout(main , 1000);
}

function handleResponse() {
	var doc = req.responseText;
	if (doc=="auth error") {
		localStorage["data_check"]="false";
		if(timer) {
			clearTimeout(timer);
		}
		timer =setTimeout(main , 1000);
	}
	else{
		var n=doc.search("404 Not Found");
		if(n>0){
			localStorage["data_check"]="false";
			if(timer) {
				clearTimeout(timer);
			}
			timer =setTimeout(main , 1000);
		}
		
		else{
			localStorage["data_check"]="true"
			getEvents(doc);
		}
	}
}

function getEvents(reply){
	if(check()){
		all_event_array=reply.split("\n");
		allEvents=divideArray(all_event_array);
		if(oldEvents.length==0){
			oldEvents=allEvents;
		}
		newEvents=fetchNewEvents(allEvents,oldEvents);
		if(newEvents.length!=0){
			for(var k=0;k<newEvents.length;k++){
				 localStorage["new_events"]++;
				showNotification(k);
			}
		}
		oldEvents=allEvents;
		if(localStorage["new_events"]!=0){
				showBadge(localStorage["new_events"]);
		}
		else{
				hideBadge();    
		}
		
		
		if(timer) {
			clearTimeout(timer);
		}
		timer =setTimeout(main , localStorage["refresh"]*1000 );
	}
}

function showBadge(txt) {
chrome.browserAction.setBadgeBackgroundColor({color:[0,200,0,255]});
chrome.browserAction.setBadgeText({ text: txt });
}
function hideBadge() {
chrome.browserAction.setBadgeText({ text: "" });
}
function divideArray(e_array){
	var Events=new Array();
	for(var i=0;i<e_array.length;i++){
		var event=e_array[i].split(";");
		Events.push(event); 
	}
	return Events;
}

function hideNotification(){

}


function fetchNewEvents(A,B){
	var arrDiff = new Array();
	for(var i = 0; i < A.length; i++) {
		var id = false;
		for(var j = 0; j < B.length; j++) {
			if(A[i][0] == B[j][0]) {
				id = true;
				break;
			}
		}
		if(!id) {
			arrDiff.push(A[i]);
		}
	}
	return arrDiff;
}


function showNotification(eventId){
	var Severity;
	if(localStorage["sound_alert"]=="on"){
		if(newEvents[eventId][19]=="Critical"){
			playSound(localStorage["critical"]);
		}
		if(newEvents[eventId][19]=="Informational"){
			playSound(localStorage["informational"]);
		}
		if(newEvents[eventId][19]=="Maintenance"){
			playSound(localStorage["maintenance"]);
		}
		if(newEvents[eventId][19]=="Normal"){
			playSound(localStorage["normal"]);
		}
		if(newEvents[eventId][19]=="Warning"){
			playSound(localStorage["warning"]);
		}
	}
	var notification = webkitNotifications.createHTMLNotification(
	"notification.html?event="+eventId  
);
	notification.show();
}

function getNotification(eventId){
	var title=newEvents[eventId][6];
	var id;
	if(newEvents[eventId][9]==0){
		id=".";
	}
	else {
		id= " in the module with Id "+ newEvents[eventId][9] + ".";
	}
			   
	var event = newEvents[eventId][14]+" : "+newEvents[eventId][17]+". Event occured at "+ newEvents[eventId][5]+id;
	return '<a>' + title + '</a> <br/> <span style="font-size:80%">' + event + '</span>';
	
}

function check(){
	if (localStorage["data_check"]=="true" && localStorage["ip_address"] != null && localStorage["api_pass"] != null &&localStorage["user_name"]!=null &&localStorage["pass"]!=null && localStorage["ip_address"] != "" && localStorage["api_pass"] != "" &&localStorage["user_name"]!="" &&localStorage["pass"]!=""){
		return true;
	}
	else 
		return false;
}

function initilise(){

		if(localStorage["ip_address"]==undefined){
			localStorage["ip_address"]="http://firefly.artica.es/pandora_demo";
					
		}
		
		if(localStorage["api_pass"]==undefined){
			localStorage["api_pass"]="doreik0";
		}
		
		if(localStorage["user_name"]==undefined){
			localStorage["user_name"]="demo";
		}
		
		if(localStorage["pass"]==undefined){
			localStorage["pass"]="demo";
		}
		if(localStorage["critical"]==null){
			localStorage["critical"]="11";
		}
		if(localStorage["informational"]==null){
			localStorage["informational"]="1";
		}
		if(localStorage["maintenance"]==null){
			localStorage["maintenance"]="10";
		}
		if(localStorage["normal"]==null){
			localStorage["normal"]="6";
		}
		if(localStorage["warning"]==null){
			localStorage["warning"]="2";
		}
		if(localStorage["events"]==null){
			localStorage["events"]=20;
		}
		if(localStorage["refresh"]==null){
			localStorage["refresh"]="10";
		}
		if(localStorage["ip_address"]==null){
			localStorage["ip_address"]="http://firefly.artica.es/pandora_demo";
		}
		
		if(localStorage["api_pass"]==null){
			localStorage["api_pass"]="doreik0";
		}
		
		if(localStorage["user_name"]==null){
			localStorage["user_name"]="demo";
		}
		
		if(localStorage["pass"]==null){
			localStorage["pass"]="demo";
		}
		if(localStorage["sound_alert"]==null){
			localStorage["sound_alert"]="on";
		}
		if(localStorage["changed"]==null){
			localStorage["changed"]="false";
		}
		if(localStorage["new_events"]==null){
			localStorage["new_events"]=parseInt(localStorage["events"]);
		}
}
