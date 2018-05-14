<?php
namespace infrajs\template;
use infrajs\load\Load;
use infrajs\event\Event;
use infrajs\config\Config;
use infrajs\sequence\Sequence;
use infrajs\template\Template;
use infrajs\path\Path;
use infrajs\access\Access;
use infrajs\router\Router;
use infrajs\path\URN;
use infrajs\view\view;


//Template::$conf['root']=URN::getAbsRoot();
//Sequence::set(Template::$scope, array('~root'), Template::$conf['root']);


Template::$fs['load'] = function ($src) {
	return Load::loadTEXT($src);
};

$fn2 = function ($name=null) {
	return $conf = Config::pub($name);
};
Sequence::set(Template::$scope, array('infra', 'config'), $fn2);//deprecated
Sequence::set(Template::$scope, array('Config', 'get'), $fn2);

if (Router::$main) Template::$scope['~conf'] = Config::get();//deprecated



$fn3 = function () {
	return View::getPath();
};
Sequence::set(Template::$scope, array('infra', 'view', 'getPath'), $fn3);
Sequence::set(Template::$scope, array('View', 'getPath'), $fn3);

$fn4 = function () {
	return View::getHost();
};
Sequence::set(Template::$scope, array('infra', 'view', 'getHost'), $fn4);
Sequence::set(Template::$scope, array('View', 'getHost'), $fn4);

$fn5 = function ($s) {
	return Sequence::short($s);
};
Sequence::set(Template::$scope, array('infra', 'seq', 'short'), $fn5);
Sequence::set(Template::$scope, array('Sequence', 'short'), $fn5);

$fn6 = function ($s) {
	return Sequence::right($s);
};
Sequence::set(Template::$scope, array('infra', 'seq', 'right'), $fn6);
Sequence::set(Template::$scope, array('Sequence', 'right'), $fn6);

$fn7 = function () {
	return View::getRoot();
};
Sequence::set(Template::$scope, array('infra', 'view', 'getRoot'), $fn7);
Sequence::set(Template::$scope, array('View', 'getRoot'), $fn7);

/*
$fn8 = function ($src) {
	return Load::srcInfo($src);
};
Sequence::set(Template::$scope, array('infra', 'srcinfo'), $fn8);
*/

$host = $_SERVER['HTTP_HOST'];
$p = explode('?', $_SERVER['REQUEST_URI']);
$pathname = $p[0];
Sequence::set(Template::$scope, array('location', 'host'), $host);
Sequence::set(Template::$scope, array('location', 'pathname'), $pathname);




Template::$scope['Path'] = array();
Template::$scope['Path']['encode'] = function ($str) {
	return Path::encode($str);
};

Template::$scope['Access'] = array();
Template::$scope['Access']['adminTime'] = function () {
	return Access::adminTime();
};
Template::$scope['Access']['getDebugTime'] = function () {
	return Access::getDebugTime();
};
