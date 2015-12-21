<?php
namespace infrajs\template;
use infrajs\template\Template;
use infrajs\config\Config;

/*
	В infra все пути относительные... относительно корня, несмотря на то где реально находится файл
*/
$isinfra=is_file('vendor/autoload.php');

if(!$isinfra){
	require_once('../../../../vendor/autoload.php');
	$tpl="{:inc.test}{inc::}resources/inc.tpl";
} else {
	$tpl="{:inc.test}{inc::}-template/tests/resources/inc.tpl";
}




$data=array();

$res=Template::parse(array($tpl), $data);
$ans['res']=$res;
if ($res!='Привет!') {
	$ans['result']=false;
	$ans['msg']='Неожиданный резльтат';
	echo json_encode($ans, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	return;
}
$ans['result']=true;
echo json_encode($ans, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
return;
