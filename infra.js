
Template.scope.infra.config=function(name){
	return Config.get(name);
}

Template.scope['~conf'] = Config.get();//deprecated

Template.scope.Config = {};
Template.scope.Config.get=function(name){
	return Config.get(name);
}
Sequence.set(Template.scope, ['View', 'getHost'], function () { return View.getHost();} );


/*
Sequence.set(Template.scope, ['Sequence', 'right'], function (s) { return Sequence.right(s); } );
Sequence.set(Template.scope, ['Sequence', 'short'], function (s) { return Sequence.short(s); } );


Sequence.set(Template.scope, ['View', 'getPath'], function () { return View.getPath();} );
Sequence.set(Template.scope, ['View', 'getRoot'], function () { return View.getRoot();} );
*/

//Sequence.set(Template.scope, ['location', 'host'], location.host);
//Sequence.set(Template.scope, ['location', 'pathname'], location.pathname);







Template.scope['Path'] = {};
Template.scope['Path']['encode'] = function (str) {
	return Path.encode(str);
}

Template.scope['Access'] = {};
Template.scope['Access']['adminTime'] = function () {
	return Access.adminTime();
};
Template.scope['Access']['getDebugTime'] = function () {
	return Access.getDebugTime();
};
