
Template.scope.infra.config=function(name){
	return Config.get(name);
}
Event.one('Controller.oninit', function () {
	Template.scope['~conf'] = Config.get();
});
Template.scope.Config = {};
Template.scope.Config.get=function(name){
	return Config.get(name);
}
