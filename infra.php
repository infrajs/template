<?php
namespace infrajs\template;
use infrajs\infra\Load;

Template::$fs['load'] = function ($src) {
	return Load::loadTEXT($src);
};
