<?php
namespace infrajs\template;
use infrajs\load\Load;

Template::$fs['load'] = function ($src) {
	return Load::loadTEXT($src);
};
