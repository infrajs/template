<?php
namespace infrajs\template;
use infrajs\load\Load;
use infrajs\infra\Config;
use infrajs\sequence\Sequence;
use infrajs\path\Path;
use infrajs\view\view;

Template::$fs['load'] = function ($src) {
	return Load::loadTEXT($src);
};
$fn = function ($path) {
	return Path::theme($path);
};
Sequence::set(Template::$scope, array('infra', 'theme'), $fn);


$fn = function ($name=null) {
	return $conf = Config::pub($name);
};
Sequence::set(Template::$scope, array('infra', 'config'), $fn);

$fn = function () {
	return View::getPath();
};
Sequence::set(Template::$scope, array('infra', 'view', 'getPath'), $fn);

$fn = function () {
	return View::getHost();
};
Sequence::set(Template::$scope, array('infra', 'view', 'getHost'), $fn);

$fn = function ($s) {
	return Sequence::short($s);
};
Sequence::set(Template::$scope, array('infra', 'seq', 'short'), $fn);

$fn = function ($s) {
	return Sequence::right($s);
};
Sequence::set(Template::$scope, array('infra', 'seq', 'right'), $fn);

$fn = function () {
	return View::getRoot();
};
Sequence::set(Template::$scope, array('infra', 'view', 'getRoot'), $fn);
$fn = function ($src) {
	return Load::srcInfo($src);
};

Sequence::set(Template::$scope, array('infra', 'srcinfo'), $fn);

$host = $_SERVER['HTTP_HOST'];
$p = explode('?', $_SERVER['REQUEST_URI']);
$pathname = $p[0];
Sequence::set(Template::$scope, array('location', 'host'), $host);
Sequence::set(Template::$scope, array('location', 'pathname'), $pathname);