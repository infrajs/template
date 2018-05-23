<?php
namespace infrajs\template;
use infrajs\sequence\Sequence;
use infrajs\once\Once;
use infrajs\path\Path;
/*
parse
	make
		 prepare(template); Находим все вставки {}
		 analysis(ar); Бежим по всем скобкам и разбираем их что куда и тп
			 parseexp('exp')
				parseCommaVar('asd.as[2]')
					parsevar('asd.as[2]') и повторить потом
		tpls=getTpls(ar) Объект свойства это шаблоны. каждый шаблон это массив элементов в которых описано что с ними делать строка или какая-то подстановка
		res=parseEmptyTpls(tpls);

	text=exec(tpls,data,tplroot,dataroot) парсится - подставляются данные выполняется то что указано в элементах массивов
		execTpl конкретный tpl
			getValue один шаг в шаблоне
				getCommaVar
					getVar
					getPath

 */

/*
  * условия {asdf?:asdf} {asdf&asdf?:asdf} {asdf|asdf?:asdf}
  * {data:asd{asdf}}
  *
 */
/*
 * url нужен чтобы кэширвоать загрузку. текст передаётся если надо [text]
 * data не кэшируется передаётся объектом
 * tplroot строка что будет корневым шаблоном
 * repls дополнительный массив подстановок.. результат работы getTpls
 * dataroot путь в данных от которых начинается корень данных для первого шаблона
 */
/*
 * Функции берутся в следующем порядке сначало от this в данных потом от корня данных потом в спецколлекции потом в глобальной области
 **/

class Template {
	public static $conf=array();
	public static $fs=array();
	public static $md = array();
	public static $moment = false;
	public static $replacement = array();
	public static $replacement_ind = array();
	//Набор функций доступных везде и значений
	public static $scope = array();
	public static function prepare($template)
	{
		$start = false;
		$breaks = 0;
		$res = array();
		$exp = '';
		$str = '';
		
		$strar=str_split($template);
		for ($i = 0, $l = sizeof($strar); $i < $l; ++$i) {
			$sym=$strar[$i];
			
			if (!$start) {
				if ($sym === '{') {
					$start = 1;
				} else {
					$str .= $sym;
				}
			} elseif ($start === 1) {
				if (preg_match("/\s/", $sym)) {
					$start = false;//Игнорируем фигурную скобку если далее пробельный символ
					$str .= '{'.$sym;
				} else {
					$start = true;
				}
			}
			if ($start === true) {
				if ($sym === '{') {
					++$breaks;
				}
				if ($sym === '}') {
					--$breaks;
				}
				if ($breaks === -1) {
					//Текущий символ } выражение закрыто. Есть $str предыдущая строка и $exp строка текущегго выражения
					if ($str != '') {
						$res[] = $str;
					}
					$res[] = array($exp);

					$breaks = 0;
					$str = '';
					$exp = '';
					$start = false;
				} else {
					$exp .= $sym;
				}
			}
		}
		if ($start === 1) {
			$str .= '{';
		}
		if ($str != '') {
			$res[] = $str;
		}
		if ($exp) {
			$res[sizeof($res) - 1] .= '{'.$exp;
		}

		return $res;
	}
	public static function analysis(&$group)
	{
		/*
		 *  as.df(sdf[as.d()])
		 *  as.df   (  sdf[	as.d  ()	]  )
		 *  as.df   (  sdf[  ( as.d  ())   ]  )
		 * 'as.df', [ 'sdf[',['as.d',[]] ,']' ]
		 *
		 * 'as.df',[ 'sdf[as.d',[] ],']'
		 * */


		foreach($group as $i => $exp) {
			if (is_string($exp)) {
				continue;
			} else {
				$exp = $exp[0];
			}

			//asdf.asdf(sadf.asdf)
			//['asdf.asdf',['asdf.asdf']]
			//
			//(asdf&&asdf)|(sadf&&asdf)
			//[['asdf&&asdf'],'|',['asdf&&asdf']]
			//
			// b&a[b].c()
			/*
			array('b&',
				array('type'=>'square',
					'suf','a',
					'val'=>array('b')),
				'.',
				array('type'=>'round',
					'suf','c',
					'val'=>array())
			*/
			//
			if (isset($exp[0]) && $exp[0] == '{' && isset($exp[strlen($exp) - 1]) && $exp[strlen($exp) - 1] == '}') {
				$group[$i] = $exp;
				continue;
			}

			$group[$i] = static::parseexp($exp);

	/*
			 * a[b(c)]()
			 * a[(b(c))]()
			 * a[  (b (c))  ] ()
			 * 'a[', ['(b',['(c)'],')',] ,']',['()']
			 * */
			//print_r($group[$i]);
		}
	}
	public static function parse($template, $data = array(), $tplroot = 'root', $dataroot = '', $tplempty = 'root')
	{
		$tpls = static::make($template, $tplempty);
		$tpls = static::includes($tpls, $data, $dataroot);

		$text = static::exec($tpls, $data, $tplroot, $dataroot);

		return $text;
	}
	
	public static function includes($tpls, $data, $dataroot)
	{
		$newtpls = array();	
		$find = array();
		foreach ($tpls as $key => $val) {
			$newtpls[$key] = $tpls[$key];
			if (sizeof($val)<1) {
				continue;
			}
			if ($key{mb_strlen($key)-1} == ':') {
				$data = true;
				$src = static::exec($tpls, $data, $key);
				$newtpls[$key] = array(); //Иначе два раза применится
				$text=static::load($src);
				$tpls2 = static::make(array($text));
				$tpls2 = static::includes($tpls2, $data, $dataroot);
				$key=mb_substr($key, 0, -1);
				$key.='.';
				$find[$key]=$tpls2;
			}
		}



		foreach ($find as $name => &$t) {
			foreach ($t as $k => &$subtpl) {
				$k=$name.$k;
				if (isset($tpls[$k])) {
					continue;
				}

				foreach ($subtpl as &$exp) {
					if (!is_string($exp)) {
						
						static::runExpTpl($exp, function (&$exp) use ($name) {
							array_unshift($exp['tpl']['root'], $name);
						});
					}
				}
				$newtpls[$k]=$subtpl;
			}
		}
		return $newtpls;
	}
	/**
	 * Var это {(a[:b](c)?d)?e} - a,b,c,d,e 5 интераций, кроме a[:b]
	 */
	public static function runExpTpl(&$exp, $call)
	{


		if (!empty($exp['term'])) {
			static::runExpTpl($exp['term'], $call);
			static::runExpTpl($exp['yes'], $call);
			static::runExpTpl($exp['no'], $call);
		} else if (!empty($exp['cond'])) {
			static::runExpTpl($exp['a'], $call);
			static::runExpTpl($exp['b'], $call);
		} else {
			if (!empty($exp['fn'])) {
				static::runExpTpl($exp['fn'], $call);
			}
			if (!empty($exp['var'])) {
				foreach ($exp['var'] as &$com) {//comma
					foreach ($com as &$br) {//bracket
						if (isset($br['tpl'])) {
							$call($br);
						}
		                if(is_array($br)){
		                    static::runExpTpl($br, $call);
		                }
					}
				}
			}
		}
	}
	public static function load($url){
		$fn=static::$fs['load'];
		return $fn($url);
	}
	public static function &make($url, $tplempty = 'root')
	{
		$args = array($url, $tplempty);
		
		$tpls = Once::func( function ($url, $tplempty) {

			if (is_array($url)) $template=$url[0];
			else $template=static::load($url);

					
			$ar = static::prepare($template);
			static::analysis($ar);
			$tpls = static::getTpls($ar, $tplempty);
		
			if (!$tpls) {
				$tpls[$tplempty] = array();
			}//Пустой шаблон добавляется когда вообще ничего нет
			//$res=static::parseEmptyTpls($tpls);
			return $tpls;
		}, $args);

		
		return $tpls;
	}
	public static function exec(&$tpls, &$data, $tplroot = 'root', $dataroot = '')
	{
		//Только тут нет conf
		if (is_null($tplroot)) {
			$tplroot = 'root';
		}
		if (is_null($dataroot)) {
			$dataroot = '';
		}
			
		$dataroot = Sequence::right($dataroot);
		$conftpl = array('tpls' => &$tpls,'data' => &$data,'tplroot' => &$tplroot,'dataroot' => $dataroot);
		$r = static::getVar($conftpl, $dataroot);
		$tpldata = $r['value'];
		//if(!$tpldata&&!is_array($tpldata)&&$tpldata!=='0'&&$tpldata!==0)return '';//Когда нет данных

		if (is_null($tpldata) || $tpldata === false || $tpldata === '') {
			return '';
		}//Данные должны быть 0 подходит

	
		$tpl = null;
		static::fora($tpls, function (&$t) use ($tplroot, &$tpl) {
			if (!isset($t[$tplroot])||is_null($t[$tplroot])) return;
			$tpl=$t[$tplroot];
			return false;
		});

		if (is_null($tpl)) return $tplroot; //Когда нет шаблона

		$conftpl['tpl'] = &$tpl;

		

		return static::execTpl($conftpl);
	}
	public static function execTpl($conf)
	{
		$html = '';

		//$dataroot=$conf['dataroot'];
		//dataroot меняется при подключении шаблона и при a().b для b dataroot будет a - так нельзя так как b от корня не может быть взят. с.b должно быть
		//var - asdf[asdf] но получить такую переменную нельзя нужно расчитать этот путь getPath asdf.qwer и где же хранить этот путь
		//lastroot нужен чтобы прощитать с каким dataroot нужно подключить шаблон это всегда путь от корня

		
		
		foreach ($conf['tpl'] as $i => $d) {	

			$var = static::getValue($conf, $conf['tpl'][$i]);//В getValue будет вызываться execTpl но dataroot всегда будет возвращаться в прежнее значение
			if (is_string($var)) {
				$html .= $var;
			} else if (is_float($var)) {
				$html .= $var;
			} else if (is_int($var)) {
				$html .= $var;
			} else {
				$html .= '';
			}
		}
		//$conf['dataroot']=$dataroot;
		return $html;
	}

	public static function &getPath(&$conf, $var)
	{
		//dataroot это прощитанный путь до переменной в котором нет замен
		/*
		 * Функция прощитывает сложный путь
		 * Путь содержит скобки и содежит запятые
		 * asdf[asdf()]
		 * */
		
		$ar = array();
		//Each::forr($var, function &(&$v) use (&$conf, &$ar) {
		foreach ($var as $i => $v) {
			//'[asdf,asdf,[asdf],asdf]'
			if (is_string($v) || is_int($v)) {
				//name
				$ar[] = $v;
			} elseif (is_array($v) && isset($v[0]) && is_array($v[0]) && isset($v[0]['orig']) && is_string($v[0]['orig'])) {
				//name[name]  [name,[{}],name]

				$ar[] = static::getValue($conf, $var[$i][0]);
			} elseif (is_array($v) && isset($v['orig']) && is_string($v['orig'])) {
				//name.name().name [name,{},name]
				//$t=array_merge($ar,$v);
				if ($ar) {
					//смутнопонимаемая ситуация... asdf().qewr().name после замены получаем zxcv.qewr().name потом tyui.name для того чтобы получить tyui нужно установить dataroot zxcv
					//сделать merge zxcv и qwer нельзя потому что qwer это сложный объект и тп... {orig:'a.b[c]'} а qwer это строка путь до знанчения тогда как zxcv нужно ещё прощитать взяв его от qwer
					//в параметрах может потребоваться настоящий root
					//ghjk настоящий root
					//zxcv новый root чтобы корректно получить функцию qwer
					//ghjk нужный root для получения some
					//asdf().   qwer(some)   .name
					//
					//Нужно свести всё к одному руту
					//Редактировать $v['orig'] нельзя.. как указать root только для функции
					//С другой стороны если редактировать $v сейчас то и в следуюищй раз при парсе будет корректива заменящая на новую.. или возвращать изменения
					//В общем раз новый root нужет только для функции находим и подменяем путь до этой функции в структруе.. и потом возвращаем изменения
					$temp = $v['fn']['var'][0];
					$var[$i]['fn']['var'][0] = array_merge($ar, $temp);
					//Добавить в fn
				}

				$d = static::getValue($conf, $var[$i], true);//{some()} вывод пустой если функции нет, чтобы работало {some()?1?2}. Была ошибка выводилось 1 когда функции небыло, так как в условие попадала строка some
				if ($ar) {
					$v['fn']['var'][0] = $temp;
				}
				if (!isset(static::$scope['zinsert'])) {
					static::$scope['zinsert'] = array();
				}
				$n = sizeof(static::$scope['zinsert']);
				static::$scope['zinsert'][$n] = $d;

				$ar = array();
				$ar[] = 'zinsert';
				$ar[] = (string) $n;
			} else {
				$r = static::getVar($conf, $var[$i]);
				$r = $r['value'];
				$ar[] = $r;
			}
			
		};

		return $ar;
	}
	public static function getVar(&$conf, $var = array())
	{
		//dataroot это прощитанный путь до переменной в котором нет замен
		//$var содержит вставки по типу ['asdf',['asdf','asdf'],'asdf'] то есть это не одномерный массив. asdf[asdf.asdf].asdf
		//var одна переменная
		
		if (is_null($var)) {
			//if($checklastroot)$conf['lastroot']=false;//Афигенная ошибка. получена переменная и далее идём к шаблону переменной для которого нет, узнав об этом lastroot не сбивается и шаблон дальше загружается с переменной в lastroot {$indexOf(:asdf,:s)}{data:descr}{descr:}{}
			$value = '';
			$root = false;
		} else {

			$right = static::getPath($conf, $var);
			
			$p = array_merge($conf['dataroot'], $right);

			$p = Sequence::right($p);

			if (isset($p[sizeof($p) - 1]) && (string) $p[sizeof($p) - 1] === '~key') {
				if (sizeof($conf['dataroot']) < 1) {
					$value = null;
				} else {
					$value = $conf['dataroot'][sizeof($conf['dataroot']) - 1];
				}
				if (empty(static::$scope['kinsert'])) {
					static::$scope['kinsert'] = array();
				}
				$n = sizeof(static::$scope['kinsert']);
				static::$scope['kinsert'][$n] = $value;
				$root = array('kinsert',(string) $n);
				
			} else {
				$value = Sequence::get($conf['data'], $p);//Относительный путь от данных

				if (!is_null($value)) {
					$root = $p;
				}

				if (is_null($value) && sizeof($p)) {
					$value = Sequence::get(static::$scope, $p);//Относительный путь
					if (!is_null($value)) {
						$root = $p;
					}
				}

				if (is_null($value)) {
					$value = Sequence::get($conf['data'], $right);//Абсолютный путь
					if (!is_null($value)) {
						$root = $right;
					}
				}

				if (is_null($value) && sizeof($right)) {
					$value = Sequence::get(static::$scope, $right);//Абсолютный путь
					if (!is_null($value)) {
						$root = $right;
					}
				}
				
				if (is_object($value) && method_exists($value, 'toString')) {
					$value = $value->toString();
				}
				if (is_null($value)) {
					$root = $right;
				}
				//Афигенная ошибка. получена переменная и далее идём к шаблону переменной для которого нет, узнав об этом lastroot не сбивается и шаблон дальше загружается с переменной в lastroot {$indexOf(:asdf,:s)}{data:descr}{descr:}{}
			}
		}

		
		return array(
			'root' => $root,//Путь от корня
			'value' => $value,
			//'right'=>$right//Путь которого достаточно чтобы найти переменную и путь о котором знает пользователь asdf[asdf] = asdf.qwer
		);
	}

	/*
	{
		orig:'asdf:asd',//Оригинальное выражение в фигурных скобках
		var:{'somevar','asdf',[1]},//путь до данных для этого подключаемого шаблона

		tpl:'root',//Имя шаблона который нужно подключить в этом месте
		multi:true//Нужно ли для каждого элемента этих данных подключать указанный шаблон

		term:{},//Выражение которое нужно посчитать
		yes:{},
		no:{}

		cond:'s',//тип условия в одном символе = !
		a:{},
		b:{}
	}
	 */
	public static function bool($var = false)
	{
		return ($var || $var === '0');
	}
	public static function getCommaVar(&$conf, &$d, $term = false)
	{
			
		//Приходит var начиная от запятых в $d
		if (!empty($d['fn'])) {
			$func = static::getValue($conf, $d['fn']);
			if (is_callable($func)) {
				$param = array();
				for ($i = 0, $l = sizeof($d['var']); $i < $l; ++$i) {
					//Количество переменных
					if (isset($d['var'][$i]['orig']) && static::bool($d['var'][$i]['orig'])) {
						$v = static::getValue($conf, $d['var'][$i], $term);
						$param[] = $v;
					} elseif ($d['var']) {
						$v = static::getOnlyVar($conf, $d, $term, $i);
						$param[] = $v;
					}
				}
				//$param[]=&$conf;
				static::$moment = $conf;
				return call_user_func_array($func, $param);
			} else {
				return;//что возвращается когда нет функции которую нужно вызвать
				/*if($term)return null;
				else return $d['orig'];*/
			}
		} else {
			
			$v = static::getOnlyVar($conf, $d, $term);

			return $v;
		}
	}
	public static function getOnlyVar(&$conf, &$d, $term, $i = 0)
	{
		
		if (isset($d['tpl']) && is_array($d['tpl'])) { //{asdf():tpl}
			$ts = array($d['tpl'], $conf['tpls']);
			
			$tpl = static::exec($ts, $conf['data'], 'root', $conf['dataroot']);

			$r = static::getVar($conf, $d['var'][$i]);
			$v = $r['value'];

			$lastroot = $r['root'] ? $r['root'] : $conf['dataroot'];
			$h = '';
			if (!$d['multi']) {
				$droot = $lastroot;
				$h = static::exec($conf['tpls'], $conf['data'], $tpl, $droot);
			} else {
				if ($v) {
					foreach ($v as $kkk => $vvv) {
						$droot = array_merge($lastroot, array($kkk));
						$h .= static::exec($conf['tpls'], $conf['data'], $tpl, $droot);
					}
				}
				/* infra_foru($v,function(&$v,$k) use(&$d,&$h,&$conf,&$lastroot,&$tpl){
					$droot=array_merge($lastroot,array($k));
					$h.=static::exec($conf['tpls'],$conf['data'],$tpl,$droot);
				});*/
			}
			$v = $h;
		} else {

			if (isset($d['var'][$i])) {
				$r = static::getVar($conf, $d['var'][$i]);
			} else {
				$r = null;
			}

			$v = $r['value'];
			if (!$term && is_null($v)) {
				$v = '';
			}
		}

		return $v;
	}
	public static function getValue(&$conf, &$d, $term = false)
	{
		if (is_string($d)) {
			return $d;
		}

		if (!empty($d['cond']) && !isset($d['term'])) {
			$a = static::getValue($conf, $d['a'], false);
			$b = static::getValue($conf, $d['b'], false);
			if ($d['cond'] == '=') {
				return ($a == $b);
			} elseif ($d['cond'] == '!') {
				return ($a != $b);
			} elseif ($d['cond'] == '>') {
				return ($a > $b);
			} elseif ($d['cond'] == '<') {
				return ($a < $b);
			} else {
				return false;
			}
		} elseif (isset($d['var'])) {
			$v = static::getCommaVar($conf, $d, $term);
			return $v;
		} elseif ($d['term']) {
			$var = static::getValue($conf, $d['term'], true);
			if (is_null($var) || $var === false || $var === '' || $var === 0) {
				//Пустой массив не false
				$r = static::getValue($conf, $d['no'], $term);
			} else {
				$r = static::getValue($conf, $d['yes'], $term);
			}

			return $r;
		}
	}

	public static function getTpls(&$ar, $subtpl = 'root')
	{
		//subtpl - первый подшаблон с которого начинается если конкретно имя не указано
		$res = array();
		
		for ($i = 0; $i < sizeof($ar); ++$i) {
			if (is_array($ar[$i]) && isset($ar[$i]['template'])) {
				//Если это шаблон
				$subtpl = $ar[$i]['template'];

				$res[$subtpl] = array();//Для пустых определённый шаблонво, кроме root по умолчанию, для него массив не появится
				continue;
			};
			if (!isset($res[$subtpl])) {
				$res[$subtpl] = array();
			}
			$res[$subtpl][] = $ar[$i];
		}

		global $itn;
		foreach ($res as $subtpl => $v) {
			//Удаляется последний символ в предыдущем подшаблонe
			$t = sizeof($res[$subtpl]) - 1;
			$str = isset($res[$subtpl][$t]) ? $res[$subtpl][$t] : null;
			if (!is_string($str)) continue;
			++$itn;

			$str = $res[$subtpl][$t];

			//$ch = mb_substr($str,mb_strlen($str) - 1,1);
			$res[$subtpl][$t] = preg_replace('/[\r\n]+\s*$/', '', $res[$subtpl][$t]);

		}
		return $res;
	}


	public static function parseStaple($exp)
	{
		//С К О Б К И
		//Небыло проверок на функции
		//Если проверка была в выражении передаваемом в функции тоже могут быть скобки
		$fn = '';
		$fnexp = '';
		$start = 0;
		$newexp = '';
		$specchars = array('?','|','&','[',']','{','}','=','!','>','<',':',',');//&
		
		//Делается замена (str) на xinsert.. список знаков при наличии которых в str отменяет замену и отменяет накопление имени функции перед скобками
		
		$expar=str_split($exp);
		for ($i = 0, $l = sizeof($expar); $i < $l; ++$i) {
			$ch=$expar[$i];
			/*
			 * Механизм замен из asdf.asdf(asdf,asdf) получем временную замену xinsert0 и так каждые скобки после обработки в выражении уже нет скобок а замены расчитываются когда до них доходит дело
			 * любые скобки считаются фукнцией функция без имени просто возвращает результат
			 */
			if ($ch == ')' && $start) {
				--$start;
				if (!$start) {
					$k = $fn.'('.$fnexp.')';
					$insnum = isset(static::$replacement_ind[$k]) ? static::$replacement_ind[$k] : null;
					if (is_null($insnum)) {
						$insnum = sizeof(static::$replacement);
						static::$replacement_ind[$k] = $insnum;
					}
					$newexp .= '.xinsert'.$insnum;
					static::$replacement[$insnum] = $fn;
					$r = static::parseexp($fnexp, true, $fn);
					static::$replacement[$insnum] = $r; //Получается переменная значение которой формула а именно функция //и мы вставляем сюда сразу да без запоминаний
					$fn = '';
					$fnexp = '';
					continue;
				}
			}
			if ($start) {
				$fnexp .= $ch;
			} else {
				if (in_array($ch, $specchars)) {
					$newexp .= $fn.$ch;
					$fn = '';
				} else {
					if ($ch !== '(') {
						$fn .= $ch;
					}
				}
			}
			if ($ch === '(') {
				++$start;
			}
		}
		if (is_string($newexp)) {
			$exp = $newexp;
		}
		if (is_string($newexp) && is_string($fn)) {
			$exp .= $fn;
		}

		return $exp;
	}
	public static function parseexp($exp, $term = false, $fnnow = null)
	{
		// Приоритет () ? | & = ! : [] , .
		/*
		 * Принимает строку варажения, возвращает сложную форму с orig обязательно
		 */
		$res = array();
		$res['orig'] = $exp;
		if ($fnnow) {
			$res['orig'] = $fnnow.'('.$res['orig'].')';
		}

		if ($fnnow) {
			$res['fn'] = static::parseBracket($fnnow);
		}//в имени функции может содержать замены xinsert asdf[xinsert1].asdf. Массив как с запятыми но нужен только нулевой элемент, запятых не может быть/ Они уже отсеяны

		$exp = static::parseStaple($exp);
		
	//Сюда проходит выражение exp без скобок, с заменами их на псевдо переменные
		$l = mb_strlen($exp);
		if ($l > 1 && mb_substr($exp,$l - 1,1) == ':' && mb_strpos($exp, ',') === false) {
			$res['template'] = substr($exp, 0, -1);//удалили последний символ
			return $res;
		}
		$cond = explode(',', $exp);
		if (sizeof($cond) > 1) {
			$res['var'] = array();
			//Each::forr($cond, function &($c) use (&$res) {
			foreach ($cond as $c) {
				$res['var'][] = static::parseexp($c, true);
			};

			return $res;
		}

		$cond = explode('?', $exp, 3);
		if (sizeof($cond) > 1) {
			$res['cond'] = true;
			$res['term'] = static::parseexp($cond[0], true);
			if (sizeof($cond) > 2) {
				$res['yes'] = static::parseexp($cond[1]);
				$res['no'] = static::parseexp($cond[2]);
			} else {
				$res['yes'] = static::parseexp($cond[1]);
				$res['no'] = static::parseexp('$false');
			}

			return $res;
		}

		$cond = explode('&', $exp, 2);//a&b
		if (sizeof($cond) === 2) {
			$res['cond'] = true;
			$res['term'] = static::parseexp($cond[0], true);
			$res['yes'] = static::parseexp($cond[1]);
			$res['no'] = static::parseexp('$false');

			return $res;
		}

		$cond = explode('|', $exp, 2);//a|b
		if (sizeof($cond) === 2) {
			$res['cond'] = true;
			$res['term'] = static::parseexp($cond[0], true);
			$res['yes'] = static::parseexp($cond[0]);
			$res['no'] = static::parseexp($cond[1]);

			return $res;
		}

		$symbols = array('!','=','>','<');
		$min = false;
		$sym = false;
		for ($i = 0, $l = sizeof($symbols); $i < $l; ++$i) {
			$s = $symbols[$i];
			$ind = strpos($exp, $s);
			if ($ind === false) {
				continue;
			}
			if ($min === false || $ind < $min) {
				$min = $ind;
				$sym = $s;
			}
		}

		if ($sym) {
			$cond = explode($sym, $exp, 3);
			$res['cond'] = $sym;
			$res['a'] = static::parseexp($cond[0]);//a&b|c   (1&0)|1=true  1&(0|1)=true  a&b|c
			$res['b'] = static::parseexp($cond[1]);

			return $res;
		}

		static::parseBracket($exp, $res);

		return $res;
	}
	public static function parseBracket($exp, &$res = null)
	{
		if (is_null($res)) {
			$res = array();
			$res['orig'] = $exp;
		}

		$res['var'] = static::parseCommaVar($exp);

		return $res;
	}
	public static function parseCommaVar($var)
	{
		//Разбиваем на запятые
		//в выражении var круглых скобок нет они заменены на xinsert (fn())
		//Возвращается массив, элементы либо ещё один главный объект либо массив переменной
		//
		//asdf.asdf,xinsert1,asdf[asdf.asdf][xinsert2]
		//[ ['asdf','asdf'],{'orig':'fn()'}, ['asdf',['asdf','asdf'], {'orig':'fn()'} ] ]
		//
		//a[c:b].asdf
		//['a',{var:['c'],tpl:'b'},'asdf']
		//
		//Если массив значит скобки, если объект значит сложное выражение в котором могут быть запятые
		//Первый массив - запятые
		//Второй массив - переменная
		//Далее это попадает в static::getVar


		if ($var == '') {
			$ar = array();
		} else {
			$ar = explode(',', $var);
		}//Запятые могут быть только на первом уровне, все вложенные запятые заменены на xinsert
		$res = array();

		static::fora($ar, function ($v) use (&$res, &$var) {
			$r = static::parsevar($v);
			$res[] = $r;
		});
		static::checkInsert($res);

		return $res;
	}
	public static function fora(&$ar, $call, $i = null, &$group = null)
	{
		if (is_null($ar)) return;
		if (!is_array($ar)||(!isset($ar[0])&&$ar)) { //Пробежка по индексному массиву
			return $call($ar, $i, $group);
		}
		foreach ($ar as $i => $v){
			$r=static::fora($ar[$i], $call, $i, $ar);
			if (!is_null($r)) return $r;
		}
	}
	public static function checkInsert(&$r)
	{
		static::fora($r, function (&$vv, $i, &$group) {
			//точки, скобки
			if (is_string($vv)) {
				if (preg_match("/^xinsert(\d+)$/", $vv, $m)) {
					$group[$i] = static::$replacement[$m[1]];
				}
			} elseif ($vv && $vv['orig']) {
				static::checkInsert($vv['var']);
			}
		});
	}
	public static function parsevar($var)
	{
		//Ищим скобки as.df[asdf[y.t]][qwer][ert]   asdf[asdf][asdf]
		if ($var == '') {
			return;
		} //Замен xinsert уже нет //asdf.asdf[asdf] На выходе ['asdf','asdf',['asdf']]
		$res = array();

		$start = false;
		$str = '';
		$name = '';
		$open = 0;//Количество вложенных открытий
		
		$varar=str_split($var);
		for ($i = 0, $l = sizeof($varar); $i < $l; ++$i) {
			$sym=$varar[$i];

			if ($start && $sym === ']') {
				if (!$open) {
					$res[] = array(static::parseexp($name, true));//data.name().. data[name]
					$start = false;
					$str = '';
					$name = '';
					continue;
				} else {
					--$open;
				}
			} elseif (!$start) {
				//:[] ищем двоеточее вне скобок
				if ($sym == ':') {

					$tpl = substr($var, $i + 1);
					//echo $tpl;
					$r = array();
					$r['orig'] = $var;
					$r['multi'] = ($tpl != '' && $tpl[0] === ':');
					if ($str) {
						$res = array_merge($res, Sequence::right($str));
					}
					$r['var'] = array($res);//В переменных к шаблону запятые не обрабатываются. res это массив с одним элементом в котором уже элементов много
					if ($r['multi']) {
						$tpl = substr($tpl, 1);
					}
					
					$r['tpl'] = static::make(array($tpl));
					
					if (!isset($r['tpl']['root'])) {
						$r['tpl']['root'] = array('');
					}

					return array($r);
				}
			}

			if ($start) {
				$name .= $sym;
			}
			if ($sym === '[') {
				if ($start) {
					++$open;
				} else {
					$res = array_merge($res, Sequence::right($str));
					$start = true;
				}
			}
			if (!$start) {
				$str .= $sym;
			}
		}

		$res[] = $str;

		$r = array();
		foreach ($res as $v) {
			if (is_string($v)) {
				$rrr = false;
				if ($rrr) {
					$r[] = $rrr;
				} else {
					$t = Sequence::right($v);

	//a.b[b.c][c]
					//[a,b,[b,c],[c]]
					//b,[b,c]
					//b,[b,c]
					foreach ($t as $e) {
						$r[] = $e;
					}
				}
			} else {
				$r[] = $v;
			}
		}

		return $r;
	}
}

Template::$scope = array(
	'~typeof' => function ($v = null) {
		if (is_null($v)) return 'null';
		if (is_bool($v)) return 'boolean';
		if (is_string($v)) return 'string';
		if (is_integer($v)) return 'number';
		if (is_array($v)) return 'object';
		if (is_callable($v)) return 'function';
	},
	'~true' => true,
	'~false' => false,
	'~years' => function ($start) {
		$y = date('Y');
		if ($y == $start) {
			return $y;
		}

		return $start.'&ndash;'.$y;
	},
	'~date' => function ($format, $time = null) {
		//if(is_null($time))$time=time(); Нельзя выводить текущую дату когда передан null так по ошибке будет не то выводится когда даты просто нет.
		if ($time === true) {
			$time = time();
		}
		if ($time == '') {
			return '';
		}
		$st = (string) $time;
		if (mb_strlen($st) == 6) {
			$y = mb_substr($st, 0, 2);
			$m = mb_substr($st, 2, 2);
			$d = mb_substr($st, 4, 2);
			$time = mktime(12, 12, 12, $m, $d, $y);
		}
		if (mb_strlen($st) == 8) {
			$y = mb_substr($st, 0, 4);
			$m = mb_substr($st, 4, 2);
			$d = mb_substr($st, 6, 2);
			$time = mktime(12, 12, 12, $m, $d, $y);
		}
		$r = date($format, $time);
		if (strpos($format, 'F') != -1) {
			$trans = array(
				'January' => 'января',
				'February' => 'февраля',
				'March' => 'марта',
				'April' => 'апреля',
				'May' => 'мая',
				'June' => 'июня',
				'July' => 'июля',
				'August' => 'августа',
				'September' => 'сентября',
				'October' => 'октября',
				'November' => 'ноября',
				'December' => 'декабря',
			);
			$r = strtr($r, $trans);
		}

		return $r;

	},
	'~obj' => function () {
		$args = func_get_args();
		$obj = array();
		for ($i = 0, $l = sizeof($args); $i < $l; $i = $i + 2) {
			if ($l == $i + 1) {
				break;
			}
			$obj[$args[$i]] = $args[$i + 1];
		}

		return $obj;
	},
	
	'~encode' => function($str){
		if (!is_string($str)) return $str;
		if (!$str) return $str;
		return urlencode($str);
	},
	'~decode' => function ($str) {
		if (!is_string($str)) return $str;
		if (!$str) return $str;
		return urldecode($str);
	},
	'~length' => function ($obj = null) {
		if (!$obj) {
			return 0;
		}
		if (is_array($obj)) {
			return sizeof($obj);
		} if (is_string($obj)) {
			return mb_strlen($obj);
		}

		return 0;
	},
	'~inArray' => function ($val, $arr) {
		if (!$arr) {
			return false;
		}
		if (is_array($arr)) {
			return in_array($val, $arr);
		}
	},
	'~match' => function ($exp, $val) {
		preg_match('/'.$exp.'/', $val, $match);

		return $match;
	},
	'~test' => function ($exp, $val) {
		$r = preg_match('/'.$exp.'/', $val);

		return !!$r;
	},
	'~lower' => function ($str) {
		return mb_strtolower($str);
	},
	'~upper' => function ($str) {
		return mb_strtoupper($str);
	},
	'~print' => function ($data) {
		$tpl = "{root:}<pre>{~typeof(.)=:object?:echo?:str}</pre>  {echo:}{::row}{row:}{~key}: {~typeof(.)=:object?:obj?:str}{obj:}<div style='margin-left:50px'>{:echo}</div>{str:}{.}<br>";
		$res = Template::parse([$tpl], $data);
		return $res;
	},
	'~parse' => function ($str = '') {
		$conf = Template::$moment;
		if (!$str) {
			return '';
		}
		$res = Template::parse($str, $conf['data'], 'root', $conf['dataroot'], 'root');//(url,data,tplroot,dataroot,tplempty){
		return $res;
	},
	'~indexOf' => function ($str, $v = null) {
		//Начиная с нуля
		if (is_null($v)) {
			return -1;
		}
		$r = mb_stripos($str, $v);
		if ($r === false) {
			$r = -1;
		}

		return $r;
	},
	'~last' => function () {
		$conf = Template::$moment;
		$dataroot = $conf['dataroot'];

		$key = array_pop($dataroot);
		$obj = &Sequence::get($conf['data'], $dataroot);
		if (!$obj) {
			return true;
		}
		foreach ($obj as $k => $v) {
		}
		$r = ($k == $key);

		return $r;
	},
	'~words' => function ($count, $one = '', $two = null, $five = null) {
		if (is_null($two)) {
			$two = $one;
		}
		if (is_null($five)) {
			$five = $two;
		}
		if (!$count) {
			$count = 0;
		}
		if ($count > 20) {
			$str = (string) $count;
			$count = mb_substr($str, mb_strlen($str) - 1, 1);
			$count2 = mb_substr($str, mb_strlen($str) - 2, 1);
			if ($count2 == 1) {
				return $five;
			}//xxx10-xxx19 (иначе 111-114 некорректно)
		}
		if ($count == 1) {
			return $one;
		} elseif ($count > 1 && $count < 5) {
			return $two;
		} else {
			return $five;
		}
	},
	'~even' => function () {
		$conf = Template::$moment;
		$dataroot = $conf['dataroot'];
		$key = array_pop($dataroot);
		$obj = &Sequence::get($conf['data'], $dataroot);
		$even = 1;
		foreach ($obj as $k => $v) {
			if ($key == $k) {
				break;
			}
			$even = $even * -1;
		}

		return ($even == 1);
	},
	'~array' => function () {
		$args = func_get_args();
		$ar = array();
		for ($i = 0, $l = sizeof($args); $i < $l; ++$i) {
			$ar[] = $args[$i];
		}

		return $ar;
	},
	'~multi' => function () {
		$args = func_get_args();
		$n = 1;
		for ($i = 0, $l = sizeof($args); $i < $l; ++$i) {
			$n *= $args[$i];
		}

		return $n;
	},
	'~leftOver' => function ($first, $second) {
		//Кратное
		$first = (int) $first;
		$second = (int) $second;

		return $first % $second;
	},
	'~sum' => function () {
		$args = func_get_args();
		$n = 0;
		for ($i = 0, $l = sizeof($args); $i < $l; ++$i) {
			$n += $args[$i];
		}

		return $n;
	},
	'~odd' => function () {
		$r = Template::$scope['~even'];
		return !$r();
	},
	'~path' => function ($src) {
		//Передаётся либо относительный путь от корня
		//либо абсолютный путь

		//if (preg_match("/^[\-!~]/", $src)) return '/'.$src;
		if (preg_match("/^https{0,1}:\/\//", $src)) return $src;
		if (preg_match("/^\//", $src)) return $src;
		return '/'.$src;
	},
	'~random' => function () {
		$args = func_get_args();
		shuffle($args);
		return $args[0];
	},
	'~first' => function () {
		//Возвращает true или false первый или не первый это элемент
		$conf = Template::$moment;
		$dataroot = $conf['dataroot'];
		$key = array_pop($dataroot);
		$obj = &Sequence::get($conf['data'], $dataroot);

		foreach ($obj as $k => $v) {
			break;
		}

		return ($k == $key);
	},
	'~Number' => function ($key, $def = 0) {
		//Делает из переменной цифру, если это не цифра то будет def
		$n = (int) $key;
		if (!$n && $n != 0) {
			$n = $def;
		}

		return $n;
	},
	'~cost' => function ($cost, $text = false) {

		$cost = (string) $cost;
		$ar = explode('.', $cost);
		if (sizeof($ar) == 1) {
			$ar = explode(',', $cost);
		}

		$cop = '';
		if (sizeof($ar) >= 2) {
			$cost = $ar[0];
			$cop = $ar[1];
			if (mb_strlen($cop) == 1) {
				$cop .= '0';
			}
			if (mb_strlen($cop) > 2) {
				$cop = mb_substr($cop, 0, 3);
				$cop = round($cop / 10);
			}
			if ($cop == '00') {
				$cop = '';
			}
		}

		if ($text) {
			$inp = ' ';
		} else {
			$inp = '&nbsp;';
		}

		if (mb_strlen($cost) > 4) {
			//1000
			$l = mb_strlen($cost);
			$cost = mb_substr($cost, 0, $l - 3).$inp.mb_substr($cost, $l - 3, $l);
		}

		if ($cop) {
			if ($text) {
				$cost = $cost.','.$cop;
			} else {
				$cost = $cost.'<small>,'.$cop.'</small>';
			}
		}

		return $cost;
	}
);


Template::$fs = array (
	"load" => function($src){
		return file_get_contents($src);
	}
);