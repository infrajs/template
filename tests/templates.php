<?php
	use infrajs\template\Template;
	
	if (!is_file('vendor/autoload.php')) require_once('../../../../vendor/autoload.php');

	$tpls = json_decode(file_get_contents(__DIR__.'/resources/templates.json'),true);

	
	function getmicrotime()
	{
		list($usec, $sec) = explode(' ', microtime());
		return $usec;
	}

	if (empty($_GET['type'])) {
		echo '<table style="font-size:14px; font-family:monospace;">';
		$time = getmicrotime();
		foreach ($tpls as $key => $t) {
			if (isset($_GET['key']) && $_GET['key'] != $key) continue;

			echo '<tr><td>';
			echo $key;
			echo '</td><td>';
			echo htmlentities($t['tpl']);
			echo '</td><td nowrap="1">';
			if (!isset($t['data']) || is_null($t['data'])) {
				$data = array();
			} else {
				$data = $t['data'];
			}

			//for($i=0,$l=10;$i<$l;$i++){
				$r = Template::parse(array($t['tpl']), $data);
			//}
			echo ceil((getmicrotime() - $time) * 1000);
			echo 'мс';
			echo '</td><td>';

			if ($r === $t['res']) {
				echo '"<b>'.htmlentities($r).'</b>"';
			} else {
				echo '<span style="color:red; font-weight:bold"><b>"'.htmlentities($r).'"</b></span><br>"<b style="color:gray">'.htmlentities($t['res']).'</b>"';
			}
			echo '</td><td>';
			echo json_encode($data,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			echo '</td><td>';
			if (isset($t['com'])) echo $t['com'];
			echo '</td><tr>';
		}
		echo '</table>';
	} else {
		$ans = array();
		$ans['title'] = 'Тест шаблонизатора. Без 3х известных ошибок.';
		$ans['class'] = 'bg-warning';
		$ans['result'] = 0;

		foreach ($tpls as $key => $t) {
			if ($key < 3) {
				continue;
			}

			if (!isset($t['data'])||is_null($t['data'])) {
				$data = array();
			} else {
				$data = $t['data'];
			}

			$r = Template::parse(array($t['tpl']), $data);

			if ($r !== $t['res']) {
				$ans['msg'] = 'Ошибка '.$t['tpl'];;
				echo json_encode($ans, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
				return;
			}
		};
		$ans['msg'] = 'Всё ок';
		$ans['result'] = 1;
		echo json_encode($ans, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		return;
	}
