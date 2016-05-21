
infra.template.scope.infra.config=function(name){
	var conf=infra.config();
	if(!name) return conf;
	return conf[name];
}
infra.template.scope.Config = {};
infra.template.scope.Config.get=function(name){
	var conf=infra.config();
	if(!name) return conf;
	return conf[name];
}