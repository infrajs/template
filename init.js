import { Config } from '/vendor/infrajs/config/Config.js'	
import { Template } from '/vendor/infrajs/template/Template.js'
import { Load } from '/vendor/infrajs/load/Load.js'
import { Seq } from '/vendor/infrajs/sequence/Seq.js'
import { Access } from '/vendor/infrajs/access/Access.js'
import { Path } from '/vendor/infrajs/path/Path.js'
import { View } from '/vendor/infrajs/view/View.js'

Template.scope['~data'] = function (src) {
	return Load.loadJSON(src);
}


Template.scope['~conf'] = Config.get();

Template.scope.Config = {};
Template.scope.Config.get=function(name){
	return Config.get(name);
}
Seq.set(Template.scope, ['View', 'getHost'], function () { return View.getHost();} );

Template.scope['Load'] = Load;
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
