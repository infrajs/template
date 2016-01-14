
infra.template.scope.infra.config=function(name){
	var conf=infra.config();
	if(!name) return conf;
	return conf[name];
}