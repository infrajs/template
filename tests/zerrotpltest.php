<?php
namespace infrajs\infra;
use infrajs\access\Access;
use infrajs\event\Event;
use infrajs\ans\Ans;
use infrajs\template\Template;
use infrajs\path\Path;
use infrajs\sequence\Sequence;
use infrajs\load\Load;

if (!is_file('vendor/autoload.php')) {
	chdir('../../../../');
	require_once('vendor/autoload.php');
}


	$ans = array(
		'title' => 'Тест 0 элемента в массиве. Известная проблема.',
		'class' => 'bg-warning'
	);

$tpl = '{root:}{0:test}{test:}{title}';
	$data = array(
		array(
			'title' => 'good',
		),
	);
	$html = Template::parse(array($tpl), $data, 'root');
	echo $html;

$ans['class'] = 'bg-warning';
	if ($html != 'good') {
		return Ans::ret($ans, '0 элемент принят за false как будто его нет');
	}

return Ans::ret($ans, 'Теcт пройдены. Получился ожидаемый результат поле распарсивания шаблона.');
