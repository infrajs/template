<?php

use infrajs\template\Template;

require_once(__DIR__.'/../../../../vendor/autoload.php');

$ans=array('title'=>'Простой тест');

$res=Template::parse(array('as{test}df'),array('test'=>1));

if($res=='as1df') $ans['result']=true;

echo json_encode($ans,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);