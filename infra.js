
Template.scope.infra.config=function(name){
	return Config.get(name);
}

Template.scope['~conf'] = Config.get();

Template.scope.Config = {};
Template.scope.Config.get=function(name){
	return Config.get(name);
}

Template.scope['Path'] = {};
Template.scope['Path']['encode'] = function (str) {
	return Path.encode(str);
}