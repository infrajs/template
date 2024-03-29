import { Config } from '/vendor/infrajs/config/Config.js'
import { Seq } from '/vendor/infrajs/sequence/Seq.js'
import { View } from '/vendor/infrajs/view/View.js'
import { Path } from '/vendor/infrajs/path/Path.js'
import { Load } from '/vendor/infrajs/load/Load.js'
import { Each } from '/vendor/infrajs/each/Each.js'
import { phpdate } from '/vendor/infrajs/phpdate/phpdate.js'
import { Access } from '/vendor/infrajs/access/Access.js'

/*
parse
	make
		 prepare(template); Находим все вставки {}
		 analysis(ar); Бежим по всем скобкам и разбираем их что куда и тп
			 parseexp('exp')
				parseStaple
				parseCommaVar
					parsevar
		tpls=getTpls(ar) Объект свойства это шаблоны. каждый шаблон это массив элементов в которых описано что с ними делать строка или какая-то подстановка
		res=parseEmptyTpls(tpls);

	text=exec(tpls,data,tplroot,dataroot) парсится - подставляются данные выполняется то что указано в элементах массивов
		execTpl
			getValue 				Полностью обрабатывает d
				getCommaVar			Без условий только var tpl fn
					getOnlyVar		Только var tpl
					getVar(conf,d[var])	Только var
					getPath(conf,d[var])	[asdf,[asdf()]] превращает в [['asdf','some']]
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

let Template = {
	store: function (name) {
		if (!this.store.data) this.store.data = { cache: {} };
		if (!name) return this.store.data;
		if (!this.store.data[name]) this.store.data[name] = {};
		return this.store.data[name];
	},
	prepare: function (template) {
		var start = false;
		var breaks = 0;
		var res = [];
		var exp = '';
		var str = '';
		for (var i = 0, l = template.length; i < l; i++) {
			var sym = template.charAt(i);
			if (!start) {
				if (sym === '{') start = 1;
				else str += sym;
			} else if (start === 1) {
				if (/\s/.test(sym)) {
					start = false; //Игнорируем фигурную скобку если далее пробельный символ
					str += '{' + sym;
				} else {
					start = true;
				}
			}
			if (start === true) {
				if (sym === '{') breaks++;
				if (sym === '}') breaks--;
				if (breaks === -1) {
					//Текущий символ } выражение закрыто. Есть $str предыдущая строка и $exp строка текущегго выражения
					if (str) res.push(str);
					res.push([exp]);

					breaks = 0;
					str = '';
					exp = '';
					start = false;
				} else {
					exp += sym;
				}
			}

		}
		if (start === 1) str += '{';
		if (str) res.push(str);
		if (exp) res[res.length - 1] += '{' + exp;
		return res;
	},
	analysis: function (group) {
		/*
		 *  as.df(sdf[as.d()])
		 *  as.df   (  sdf[	as.d  ()	]  )
		 *  as.df   (  sdf[  ( as.d  ())   ]  )
		 * 'as.df', [ 'sdf[',['as.d',[]] ,']' ]
		 *
		 * 'as.df',[ 'sdf[as.d',[] ],']'
		 * */
		infra.forr(group, function (exp, i) {
			if (typeof (exp) == 'string') return;
			else exp = exp[0];


			if (exp.charAt(0) == '{' && exp.charAt(exp.length - 1) == '}') {
				group[i] = exp;
				return;
			}
			group[i] = Template.parseexp(exp);
			/*
			 * a[b(c)]()
			 * a[(b(c))]()
			 * a[  (b (c))  ] ()
			 * 'a[', ['(b',['(c)'],')',] ,']',['()']
			 * */
			//print_r($group[$i]);

		});
	},
	parse: function (url, data, tplroot, dataroot, tplempty) {
		const res = this.make(url, tplempty)
		const tpls = this.includes(res, data, dataroot)
		const text = this.exec(tpls, data, tplroot, dataroot, res['tcounter']);
		return text;
	},
	clone: function (obj) {
		if (obj === null || typeof (obj) != 'object') {
			return obj;
		}
		if (obj.constructor === Array) {
			var temp = [];
			for (var i = 0, l = obj.length; i < l; i++) {
				temp[i] = this.clone(obj[i]);
			}
		} else {
			var temp = {};
			for (var key in obj) {
				temp[key] = this.clone(obj[key]);
			}
		}
		return temp;
	},
	includes: function (res, data, dataroot) {
		const tpls = res['tpls']
		const newtpls = { };
		const find = { };

		for (var key in tpls) {
			newtpls[key] = tpls[key];
			var val = tpls[key];
			if (val.length < 1) continue;

			if (key.charAt(key.length - 1) == ':') {
				var src = Template.exec(tpls, data, key, dataroot, res['tcounter']);
				newtpls[key] = [];
				if (!src) continue;
				//var src=val[0];
				//src=src.replace(/<\/?[^>]+>/gi, '');
				var res2 = this.make(src,'root',);
				const tpls2 = this.includes(res2, data, dataroot);
				
				if (key.length > 1) key = key.slice(0, -1) + '.';
				else key = '';

				find[key] = tpls2;
			}
		}
		
		for (var name in find) {
			var t = find[name];
			for (var k in t) {
				var subtpl = t[k];
				k = name + k;
				if (tpls[k]) {
					continue;
				}
				subtpl = this.clone(subtpl);
				for (var kk in subtpl) {
					var exp = subtpl[kk];
					if (typeof (exp) != 'string') {
						this.runExpTpl(exp, function (exp) {
							exp['tpl']['root'].unshift(name);
						});
					}

				}
				newtpls[k] = subtpl;
			}
		}
		return newtpls;
	},
	/**
	 * Var это {(a[:b](c)?d)?e} - a,b,c,d,e 5 интераций, кроме a[:b]
	 */
	runExpTpl: function (exp, call) {
		if (exp['term']) {
			this.runExpTpl(exp['term'], call);
			this.runExpTpl(exp['yes'], call);
			this.runExpTpl(exp['no'], call);
		} else if (exp['cond']) {
			this.runExpTpl(exp['a'], call);
			this.runExpTpl(exp['b'], call);
		} else {
			if (exp['fn']) {
				this.runExpTpl(exp['fn'], call);
			}
			if (exp['var']) {
				for (var c in exp['var']) { //comma
					var com = exp['var'][c];
					for (var b in com) { //bracket
						var br = com[b];
						if (br['tpl']) {
							call(br);
						}
						if (typeof (br) == 'object') {
							this.runExpTpl(br, call);
						}
					}
				}
			}
		}
	},
	tcounter: 0,
	scounter: 0,
	make: function (url, tplempty = 'root') { //tplempty - имя для подшаблона который будет пустым в документе начнётся без имени
		var stor = this.store();
		//url строка и массив возвращают одну строку и кэш у обоих вариантов будет одинаковый
		if (stor.cache.hasOwnProperty(url.toString())) return stor.cache[url];
		if (typeof (url) == 'string') var template = Load.loadTEXT(url);
		else if (url) var template = url[0];

		var ar = this.prepare(template);
		this.analysis(ar); //[{},'asdfa',{},'asdfa']

		var tpls = this.getTpls(ar, tplempty); //{root:[{},'asdf',{}],'some':['asdf',{}]}
		var some = false;
		for (some in tpls) break;
		if (!some) tpls[tplempty] = []; //Пустой шаблон добавляется когда вообще ничего нет
		//var res=this.parseEmptyTpls(tpls);//[{root:[]}, [{some:[]}], [{asdf:[]}]]
		Template.tcounter++
		const res = { tcounter: Template.tcounter, tpls:tpls }
		for (const s in tpls) {
			Template.scounter++
		}
		stor.cache[url.toString()] = res
		return res;
	},
	//pcounter: 0,
	exec: function (tpls, data, tplroot = 'root', dataroot = '', tcounter = 0) { //Только тут нет conf
		//Template.scope['~pid'] = 'p' + (++Template.pcounter)
		Template.scope['~tid'] = 't' + tcounter + 't'
		const sid = Template.scope['~sid']
		Template.scope['~sid'] = 't' + tcounter + 't' + Path.encode(tplroot) + 's' //1 11 = 11 1
		dataroot = Seq.right(dataroot);
		var conftpl = { 
			'tcounter': tcounter,
			'tpls': tpls, 
			'data': data, 
			'tplroot': tplroot, 
			'dataroot': dataroot 
		};
		var r = Template.getVar(conftpl, dataroot);
		var tpldata = r['value'];
		if (typeof (tpldata) == 'undefined' || tpldata === null || tpldata === false || tpldata === '') {
			Template.scope['~sid'] = sid
			return ''; //Когда нет данных
		}

		var tpl = Each.exec(tpls, function (t) {
			return t[tplroot];
		});
		if (!tpl) {
			Template.scope['~sid'] = sid
			return tplroot; //Когда нет шаблона
		}

		conftpl['tpl'] = tpl;

		//
		//
		////parse depricated
		/*var tplsearch=tplroot+'$onparse';
		var search=infra.fora(tpls,function(t){
			var search=t[tplsearch];
			if(search){
				//delete t[tplcss]; Нельзя удалять так как добавляется в див при замене html в этом диве удалится и css инструкция
				return search;
			}
		});
		if(search){
			var conf={'tpls':tpls,'tpl':search,'data':data,'tplroot':tplsearch,'dataroot':dataroot};
			search=this.execTpl(conf);
			if(search){
				try{
					var fn=eval('(function (data){'+search+'})');
					var r=Seq.get(data,dataroot);
					fn.apply(r,[data]);//this это относительные данные, data в функции это корневые данные
				}catch(e){
					console.log('onparse: '+e);
				}
			}
		}*/
		//


		const html = this.execTpl(conftpl);
		Template.scope['~sid'] = sid
		return html;
	},
	execTpl: function (conf) {
		
		var html = '';

		infra.forr(conf['tpl'], function (d) {
			var v = Template.getValue(conf, d);
			if (typeof (v) === 'string') html += v;
			if (typeof (v) === 'number') html += v;
			if (v && typeof (v) === 'object' && v.toString() !== {}.toString() && !d['term']) html += v;
			else html += '';
		});
		return html;
	},
	getPath: function (conf, v) { //dataroot это прощитанный путь до переменной в котором нет замен
		/*
		 * Функция прощитывает сложный путь
		 * Путь содержит скобки и содежит запятые
		 * asdf[asdf()]
		 * */
		var ar = [];
		infra.forr(v, function (v) { //'[asdf,asdf,[asdf],asdf]'
			if (typeof (v) === 'string' || typeof (v) === 'number') { //name
				ar.push(v);
			} else if (v && v.constructor === Array && v[0] && typeof (v[0]['orig']) !== 'undefined') { //name[name().name]
				ar.push(Template.getValue(conf, v[0]));
			} else if (v && typeof (v) == 'object' && typeof (v['orig']) !== 'undefined') { //name.name().name



				if (ar.length) {
					var temp = v['fn']['var'][0];
					v['fn']['var'][0] = ar.concat(temp);
					//Добавить в fn
				}
				var d = Template.getValue(conf, v, true);
				if (ar.length) {
					v['fn']['var'][0] = temp;
				}
				var scope = Template.scope;
				if (!scope['zinsert']) scope['zinsert'] = [];
				var n = scope['zinsert'].length;
				scope['zinsert'][n] = d;

				ar = [];
				ar.push('zinsert');
				ar.push('' + n);
			} else { //name[name.name]
				var r = Template.getVar(conf, v);
				ar.push(r['value']);
			}
		});
		return ar;
	},
	getVar: function (conf, v) {
		//v содержит вставки по типу ['asdf',['asdf','asdf'],'asdf'] то есть это не одномерный массив. asdf[asdf.asdf].asdf
		var root, value;
		if (v == undefined) {
			//if(checklastroot)conf['lastroot']=false;//Афигенная ошибка. получена переменная и далее идём к шаблону переменной для которого нет, узнав об этом lastroot не сбивается и шаблон дальше загружается с переменной в lastroot {$indexOf(:asdf,:s)}{data:descr}{descr:}{}
			root = false;
			value = '';
			return '';
		} else {
			var right = this.getPath(conf, v); //Относительный путь

			var p = Seq.right(conf['dataroot'].concat(right));

			var scope = Template.scope;
			if (p[p.length - 1] == '~key') {
				if (conf['dataroot'].length < 1) {
					value = null;
				} else {
					value = conf['dataroot'][conf['dataroot'].length - 1];
				}


				if (!scope['kinsert']) scope['kinsert'] = [];
				var n = scope['kinsert'].length;
				scope['kinsert'][n] = value;
				root = ['kinsert', '' + n];
			} else {
				var value = Seq.getr(conf['data'], p); //Относительный путь, от данных
				if (typeof (value) !== 'undefined') root = p;

				//Что брать {:t}   от data или scope относительный или прямой путь

				if (typeof (value) == 'undefined' && p.length) { //Относительный путь, от scope
					value = Seq.getr(scope, p);
					if (typeof (value) !== 'undefined') root = p;
				}

				if (typeof (value) == 'undefined') { //Абслютный путь, от данных
					value = Seq.getr(conf['data'], right);
					if (typeof (value) !== 'undefined') root = right;
				}

				if (typeof (value) == 'undefined' && right.length) { //Абсолютный путь, от scope
					value = Seq.getr(scope, right);
					if (typeof (value) !== 'undefined') root = right;
				}
				if (typeof (value) == 'undefined') root = right;
			}
		}
		return { value: value, root: root };
	},
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
	getCommaVar: function (conf, d, term) {
		//Приходит var начиная от запятых в d [[data],[layer,tpl]] (data,layer.tpl)
		if (d['fn']) {
			var func = this.getValue(conf, d['fn']); //как у функции сохранить this
			if (typeof (func) == 'function') {

				var param = [];
				for (var i = 0, l = d['var'].length; i < l; i++) { //Количество переменных
					if (!d['var'].hasOwnProperty(i)) continue; //когда такое

					if (d['var'][i] && d['var'][i]['orig']) {
						var v = this.getValue(conf, d['var'][i], term);
						param.push(v);
					} else if (d['var']) {
						var v = this.getOnlyVar(conf, d, term, i); //Внутри функции требуется если возможно и просто строка имени переменной
						param.push(v);
					}
				} //$param[]=&$conf;
				Template.moment = conf;
				return func.apply(this, param);
			} else {
				return null;
				//if(term)return null;
				//else return d['orig'];
			}
		} else {
			var v = this.getOnlyVar(conf, d, term);
			return v;
		}
	},
	foru: function (obj, callback) { //Бежим без разницы объекту или массиву
		if (obj && typeof (obj) == 'object' && obj.constructor === Array) {
			return infra.forr(obj, callback); //Массив
		} else {
			for (var i in obj) {
				var r = callback(obj[i], i, obj);
				if (!infra.isNull(r)) return r;
			}
		}
	},
	getOnlyVar: function (conf, d, term, i) {
		
		if (!i) i = 0;
		if (typeof (d['tpl']) == 'object') { //{asdf():tpl}
			var ts = [d['tpl'], conf['tpls']];
			var tpl = this.exec(ts, conf['data'], 'root', conf['dataroot'], conf['tcounter']);

			var r = this.getVar(conf, d['var'][i]);
			var v = r['value'];
			var lastroot = r['root'] || conf['dataroot'];
			var h = '';
			if (!d['multi']) {
				var droot = lastroot.concat();
				h = this.exec(conf['tpls'], conf['data'], tpl, droot, conf['tcounter']);
			} else {
				this.foru(v, function (v, k) {
					var droot = lastroot.concat([k]);
					h += Template.exec(conf['tpls'], conf['data'], tpl, droot, conf['tcounter']);
				});
			}
			v = h;
		} else {
			var r = this.getVar(conf, d['var'][i]);
			var v = r['value'];
			if (!term && typeof (v) === 'undefined') {
				v = '';
			}
		}

		return v;
	},
	test: function (...args) {
		Load.unload('-infra/tests/resources/templates.js');
		Load.require('-infra/tests/resources/templates.js');
		if (Template.test.good) {
			Template.test.apply(this, args);
		} else {
			console.log('Ошибка, загрузки тестов');
		}
	},
	getValue: function (conf, d, term) { //Передаётся элемент подшаблона
		if (typeof (d) == 'string') return d;
		if (d['cond'] && typeof (d['term']) == 'undefined') {
			var a = this.getValue(conf, d['a']);
			var b = this.getValue(conf, d['b']);
			if (d['cond'] == '=') {
				if (typeof (a) == 'boolean' || typeof (b) == 'boolean') {
					return (!a == !b);
				} else {
					return (a == b);
				}
			} else if (d['cond'] == '!') {
				if (typeof (a) == 'boolean' || typeof (b) == 'boolean') { //Из-за разного поведения в php и в javascript
					return (!a != !b);
				} else {
					return (a != b);
				}
			} else if (d['cond'] == '>') {
				return (a > b);
			} else if (d['cond'] == '<') {
				return (a < b);
			} else {
				return false;
			}
		} else if (typeof (d['var']) !== 'undefined') {
			var v = this.getCommaVar(conf, d, term);
			return v;
		} else if (d['term']) {
			var v = this.getValue(conf, d['term'], true);
			if (typeof (v) == 'undefined' || v === null || v === false || v === '' || v === 0) {
				return this.getValue(conf, d['no'], term);
			} else {
				return this.getValue(conf, d['yes'], term);
			}
		}
	},
	getTpls: function (ar, subtpl) { //subtpl - первый подшаблон с которого начинается если конкретно имя не указано
		if (!subtpl) subtpl = 'root';
		var res = {};
		for (var i = 0, l = ar.length; i < l; i++) {
			if (!ar.hasOwnProperty(i)) continue;
			if (typeof (ar[i]) == 'object' && ar[i]['template']) {
				subtpl = ar[i]['template'];
				res[subtpl] = []; //Для пустых шаблонов, чтобы появился массив, кроме root по умолчанию
				continue;
			};
			if (!res[subtpl]) res[subtpl] = [];
			res[subtpl].push(ar[i]);
		}
		infra.foro(res, function (val, subtpl) { //Удаляем переход на новую строчку в конце подшаблона
			var t = res[subtpl].length - 1;
			var str = res[subtpl][t];
			if (typeof (str) != 'string') return;
			res[subtpl][t] = str.replace(/[\r\n]+\s*$/g, '');
			//res[subtpl][t]=str.replace(/\s+$/g,'');
		});
		return res;
	},
	replacement: [],
	replacement_ind: [],
	parseStaple: function (exp) {
		//С К О Б К И
		//Небыло проверок на функции
		//Если проверка была в выражении передаваемом в функции, то тоже могут быть скобки
		var fn = '';
		var fnexp = '';
		var start = 0;
		var newexp = '';
		var specchars = ['?', '|', '&', '[', ']', '{', '}', '=', '!', '>', '<', ':', ',']; //&
		for (var i = 0, l = exp.length; i < l; i++) {
			/*
			 * Механизм замен из asdf.asdf(asdf,asdf) получем временную замену xinsert0 и так каждые скобки после обработки в выражении уже нет скобок а замены расчитываются когда до них доходит дело
			 * любые скобки считаются фукнцией функция без имени просто возвращает результат
			 */
			var ch = exp.charAt(i);
			if (ch === ')' && start) {
				start--;
				if (!start) {

					var k = fn + '(' + fnexp + ')';
					var insnum = this.replacement_ind[k];
					if (typeof (insnum) == 'undefined') {
						insnum = this.replacement.length;
						this.replacement_ind[k] = insnum;
					}

					newexp += '.xinsert' + insnum;
					this.replacement[insnum] = fn;

					//explode(',',$fnexp);//Нельзя там могут быть скобки
					var r = this.parseexp(fnexp, true, fn);
					this.replacement[insnum] = r;
					//Получается переменная значение которой формула а именно функция
					//и мы вставляем сюда сразу да без запоминаний
					fn = '';
					fnexp = '';
					continue;
				}
			}
			if (start) {
				fnexp += ch; //Определение функции fn(fnexp
			} else {
				if (infra.forr(specchars, function (c) { if (c == ch) return true })) {
					newexp += fn + ch;
					fn = '';
				} else {
					if (ch !== '(') fn += ch; //Определение функции fn(
				}
			}

			if (ch === '(') {
				start++;
			}
			//else if(!start)newexp+=ch;

		}
		if (newexp) exp = newexp;
		if (newexp && fn) exp += fn;
		return exp;
	},
	parseexp: function (exp, term, fnnow) { // Приоритет () , ? | & = ! : [] .
		/*
		 * Принимает строку варажения, возвращает сложную форму с orig обязательно
		 */
		var res = {};
		res['orig'] = exp;
		if (fnnow) res['orig'] = fnnow + '(' + res['orig'] + ')';
		else fnnow = '';


		if (fnnow) {
			res['fn'] = this.parseBracket(fnnow); //в имени функции могут содержаться замены xinsert asdf[xinsert1].asdf. Запятые в имени не обрабатываются. Массив как с запятыми но нужен только нулевой элемент, запятых не может быть/ Они уже отсеяны

		}





		exp = this.parseStaple(exp);


		//Сюда проходит выражение exp без скобок, с заменами их на псевдо переменные
		var l = exp.length;
		if (l > 1 && exp[l - 1] === ':' && exp.indexOf(',') === -1) { //Определение подшаблона
			res['template'] = exp.slice(0, -1); //удалили последний символ
			return res;
		}

		var cond = exp.split(',');

		if (cond.length > 1) { //Найдена запятая {some,:print}
			res['var'] = [];
			infra.forr(cond, function (c) {
				res['var'].push(this.parseexp(c, true));
			}.bind(this));
			return res;
		}

		var cond = exp.split('?');
		if (cond.length > 1) { //Найден вопрос и вопрос до двоеточия {some?data:print} {data:val?int}  {data:val?int}
			var cond0 = cond.shift();
			var cond1 = cond.shift();
			var cond2 = cond.join('?');
			res['cond'] = true;
			res['term'] = this.parseexp(cond0, true);
			if (cond2) {
				res['yes'] = this.parseexp(cond1);
				res['no'] = this.parseexp(cond2);
			} else {
				res['yes'] = this.parseexp(cond1);
				res['no'] = this.parseexp('~false');
			}
			return res;
		}

		cond = exp.split('&'); //a&b
		if (cond.length > 1) {
			var cond0 = cond.shift();
			var cond1 = cond.join('|');
			res['cond'] = true;
			res['term'] = this.parseexp(cond0, true);
			res['yes'] = this.parseexp(cond1);
			res['no'] = this.parseexp('~false');
			return res;
		}

		cond = exp.split('|'); //a|b
		if (cond.length > 1) {
			var cond0 = cond.shift();
			var cond1 = cond.join('|');
			res['cond'] = true;
			res['term'] = this.parseexp(cond0, true);
			res['yes'] = this.parseexp(cond0);
			res['no'] = this.parseexp(cond1);
			return res;
		}

		var symbols = ['!', '=', '>', '<'];
		var min = false;
		var sym = false;
		for (var i = 0, l = symbols.length; i < l; i++) {
			if (!symbols.hasOwnProperty(i)) continue;
			var s = symbols[i];
			var ind = exp.indexOf(s);
			if (ind === -1) continue;
			if (min === false || ind < min) {
				min = ind;
				sym = s;
			}
		}
		if (sym) {
			cond = exp.split(sym, 3);
			var cond0 = cond.shift();
			var cond1 = cond.join(sym);
			res['cond'] = sym;
			res['a'] = this.parseexp(cond0); //a&b|c   (1&0)|1=true  1&(0|1)=true  a&b|c
			res['b'] = this.parseexp(cond1);
			return res;
		}

		this.parseBracket(exp, res);

		return res;
	},
	parseBracket: function (exp, res) {

		if (typeof (res) == 'undefined') {
			var res = {};
			res['orig'] = exp;
		}

		res['var'] = this.parseCommaVar(exp);

		return res;
	},
	parseCommaVar: function (v) { //Ищим запятые
		//в выражении var круглых скобок нет они заменены на xinsert (fn())
		//Возвращается массив, элементы либо ещё один главный объект либо массив переменной
		//
		//asdf.asdf,xinsert1,asdf[asdf.asdf][xinsert2]
		//[ ['asdf','asdf'],{'orig':'fn()'}, ['asdf',['asdf','asdf'], {'orig':'fn()'} ] ]
		//
		//Если массив значит скобки, если объект значит сложное выражение в котором могут быть запятые
		//Первый массив - запятые
		//Второй массив - переменная
		//Далее это попадает в Template_getVar

		if (v == '') v = [];
		else v = v.split(','); //Запятые могут быть только на первом уровне, все вложенные запятые заменены на xinsert
		var res = [];
		infra.fora(v, function (v) { //запятые
			var r = Template.parsevar(v);
			res.push(r);
		});
		this.checkInsert(res);
		return res;
	},
	checkInsert: function (rr) {
		infra.fora(rr, function (vv, i, group) { //точки, скобки
			if (typeof (vv) == 'string') {
				var m = vv.match(/^xinsert(\d+)$/);
				if (m) {
					group[i] = Template.replacement[m[1]];
				}
			} else if (vv && vv['orig']) {
				Template.checkInsert(vv['var']);
			}
		});
	},
	parsevar: function (v) { //Ищим скобки as.df[asdf[y.t]][qwer][ert]   asdf[asdf][asdf]
		if (v == '') return undefined;
		//Замен xinsert уже нет
		//asdf.asdf[asdf] На выходе ['asdf','asdf',['asdf']]
		var res = [];

		var start = false;
		var str = '';
		var name = '';
		var open = 0; //Количество вложенных открытий
		for (var i = 0, l = v.length; i < l; i++) {
			var sym = v.charAt(i);
			//var sym=v[i];
			if (start && sym === ']') {
				if (!open) {
					res.push([this.parseexp(name, true)]);
					start = false;
					str = '';
					name = '';
					continue;
				} else {
					open--;
				}
			} else if (!start) { //:[] ищем двоеточее вне скобок
				if (sym == ':') {
					var tpl = v.substr(i + 1);
					var r = {};
					r['orig'] = v;
					r['multi'] = (tpl.charAt(0) === ':');
					if (str) res = res.concat(Seq.right(str));

					r['var'] = [res]; //В переменных к шаблону запятые не обрабатываются. res это массив с одним элементом в котором уже элементов много
					if (r['multi']) tpl = tpl.substr(1);
					r['tpl'] = this.make([tpl])['tpls'];
					if (!r['tpl']['root']) r['tpl']['root'] = [''];
					return [r];
				}

			}

			if (start) name += sym;
			if (sym === '[') {
				if (start) {
					open++;
				} else {
					res = res.concat(Seq.right(str));
					start = true;
				}
			}
			if (!start) str += sym;
		}
		res.push(str);
		var r = [];
		for (var i in res) {
			if (!res.hasOwnProperty(i)) continue;
			var v = res[i];
			if (typeof (v) == 'string') {
				var t = Seq.right(v);
				//a.b[b.c][c]
				//[a,b,[b,c],[c]]
				//b,[b,c]
				//b,[b,c]
				for (var e in t) {
					if (!t.hasOwnProperty(e)) continue;
					r.push(t[e]);
				}
			} else {
				r.push(v);
			}
		}
		return r;
	},
	scope: { //Набор функций доступных везде ну и значений разных $ - стандартная функция шаблонизатора, которых нет в глобальной области, остальные расширения совпадающие с глобальной областью javascript и в его синтаксисе
		'~sid': 't0t0s',
		'~typeof': function (v) {
			return typeof (v);
		},
		'~true': true,
		'~false': false,
		'~json': function (val) {
			return JSON.stringify(val);
		},
		'~years': function (start) {
			let y = new Date().getFullYear();
			if (y == start) return y;
			return start + '&ndash;' + y;
		},
		'~date': function (format, time) {
			if (!time) return '';
			if (time === true) time = new Date();
			return phpdate(format, time);
		},
		'~obj': function (...args) { //создаём объект {$obj(name1,val1,name2,val2)}
			var obj = {};
			for (var i = 0, l = args.length; i < l; i = i + 2) {
				obj[args[i]] = args[i + 1];
			}
			return obj;
		},
		'~encode': function (str) {
			if (!str) return str;
			return encodeURIComponent(str);
		},
		'~decode': function (str) {
			if (!str) return str;
			return decodeURIComponent(str);
		},
		'~length': function (obj) {
			if (!obj) return 0;
			if (obj.constructor === Array) return obj.length;
			if (obj && typeof (obj) == 'object') {
				var c = 0;
				for (var i in obj) {
					if (!obj.hasOwnProperty(i)) continue;
					c++;
				}
				return c;
			}
			if (obj.length != undefined) return obj.length;
			return 0;
		},
		'~inArray': function (val, arr) {
			if (!arr) return false;
			if (arr.constructor === Array) {
				return !!infra.forr(arr, function (v) {
					if (v == val) return true;
				});
			}
			if (typeof (arr) == 'object') {
				return !!infra.foro(arr, function (v) {
					if (v == val) return true;
				});
			}
		},
		'~_regexps': {},
		'~match': function (exp, val) {
			var obj = Template.scope['~_regexps'];
			if (!obj[exp]) obj[exp] = new RegExp(exp);
			return String(val).match(obj[exp]);
		},
		'~test': function (exp, val) {
			var obj = Template.scope['~_regexps'];
			if (!obj[exp]) obj[exp] = new RegExp(exp);
			return obj[exp].test(String(val));
		},
		'~lower': function (str) {
			if (!str) return '';
			return str.toLowerCase();
		},
		'~upper': function (str) {
			if (!str) return '';
			return str.toUpperCase();
		},
		'~print': function (data) {
			var tpl = "{root:}<pre>{~typeof(.)=:object?:echo?:str}</pre>{echo:}{::row}{row:}{~key}: {~typeof(.)=:object?:obj?:str}{obj:}<div style='margin-left:50px'>{:echo}</div>{str:}{~typeof(.)=:boolean?:bool?.}<br>{bool:}{.?:true?:false}";
			var res = Template.parse([tpl], data);
			return res;
		},
		'~indexOf': function (str, v) {
			str = str.toLowerCase();
			v = v.toLowerCase();
			return str.indexOf(v);
		},
		'~dataroot': function () {
			//return Template.moment.dataroot;
			return Seq.short(Template.moment.dataroot);
		},
		'~parse': function (str) {
			var conf = Template.moment;
			if (!str) return '';
			if (typeof (str) == 'object' && !str[0]) return '';
			var res = Template.parse(str, conf.data, 'root', conf['dataroot'], 'root'); //(url,data,tplroot,dataroot,tplempty){
			return res;
		},
		'~islocal': function (str) {
			return /\.ru\.org$/.test(location.host)
		},
		'~tel': function (phone) {
			if (!phone) return '';
			return phone.replace(/[^\d\+]/g,'')
		},
		'~words': function (count, one, two, five) {
			if (!count) count = 0;
			if (count > 20) {
				var str = count.toString();
				count = str[str.length - 1];
				let count2 = str[str.length - 2];
				if (count2 == 1) return five; //xxx10-xxx19 (иначе 111-114 некорректно)
			}
			if (count == 1) {
				return one;
			} else if (count > 1 && count < 5) {
				return two;
			} else {
				return five;
			}
		},
		'~before': function (num) {
			if (!num) return false;
			var conf = Template.moment;
			var dataroot = conf['dataroot'].concat();
			var key = dataroot.pop();

			var obj = Seq.getr(conf['data'], dataroot);
			if (!obj) return true;

			var n = 0;
			if (obj.constructor === Array) {
				for (var k = 0, l = obj.length; k < l; k++) {
					if (n == num) return false;
					n++;
					if (k == key) return true;
				}
			} else {
				for (var k in obj) {
					if (n == num) return false;
					n++;
					if (k == key) return true;
				}
			}
			return true;
		},
		'~cut': function (len, str) {
			if (!str || str.length < len) return str;
			else return str.substr(0, len) + '...';
		},
		'~after': function (num) {
			if (!num) return true;
			var conf = Template.moment;
			var dataroot = conf['dataroot'].concat();
			var key = dataroot.pop();

			var obj = Seq.getr(conf['data'], dataroot);
			if (!obj) return false;

			var n = 0;
			if (obj.constructor === Array) {
				for (var k = 0, l = obj.length; k < l; k++) {
					if (n == num) return true;
					n++;
					if (k == key) return false;
				}
			} else {
				for (var k in obj) {
					if (n == num) return true;
					n++;
					if (k == key) return false;
				}
			}
			return false;
		},
		'~leftOver': function (first, second) { //Кратное
			first = Number(first);
			second = Number(second);
			return first % second;
		},
		'~sum': function (...args) {
			let n = 0;
			for (let i = 0, l = args.length; i < l; i++) n += Number(args[i]);
			return n;
		},
		// '~sid': function (...args) {
		// 	const conf = Template.moment;
		// 	return 's' + conf['tcounter'] + conf['tplroot'];
		// },
		// '~tid': function (...args) {
		// 	const conf = Template.moment;
		// 	return 't' + conf['tcounter'];
		// },
		'~array': function (...args) {
			var ar = [];
			for (let i = 0, l = args.length; i < l; i++) ar.push(args[i]);
			return ar;
		},
		'~multi': function (...args) {
			let n = 1;
			for (let i = 0, l = args.length; i < l; i++) n *= Number(args[i]);
			n = Math.round(n * 1000) / 1000;
			return n;
		},
		'~even': function () {
			var conf = Template.moment;
			var dataroot = conf['dataroot'].concat();
			var key = dataroot.pop();
			var obj = Seq.getr(conf['data'], dataroot);

			var even = 1;
			Template.foru(obj, function (v, k) {
				if (k == key) return false;
				even = even * -1;
			});
			return (even == 1);
		},
		'~odd': function () {
			return !Template.scope['~even']();
		},
		'~path': function (src) {
			//Передаётся либо относительный путь от корня
			//либо абсолютный путь
			var obj = Template.scope['~_regexps'];
			var exp = '^https{0,1}:\/\/';
			if (!obj[exp]) obj[exp] = new RegExp(exp);
			if (String(src).match(obj[exp])) return src;
			var exp = '^\/';
			if (!obj[exp]) obj[exp] = new RegExp(exp);
			if (String(src).match(obj[exp])) return src;
			return '/' + src;
		},
		'~root': function () {
			var conf = Template.moment;
			return conf['data'];
		},
		'~last': function () {
			var conf = Template.moment;
			var dataroot = conf['dataroot'].concat();
			var key = dataroot.pop();
			var obj = Seq.getr(conf['data'], dataroot);

			if (typeof (obj) != 'object') return true;

			if (obj.constructor === Array) {
				var k = obj.length - 1;
			} else {
				for (var k in obj) {
					//Нельзя убирать фигурные скобки, сокращатель скриптов ломается.
				};
			}
			return (k === key);
		},
		'~random': function (...args) {
			return args[Math.floor(Math.random() * args.length)];
		},
		'~first': function () {
			//Возвращает true или false первый или не первый это элемент
			var conf = Template.moment;
			var dataroot = conf['dataroot'].concat();
			var key = dataroot.pop();
			var obj = Seq.getr(conf['data'], dataroot);

			if (typeof (obj) != 'object') return true;

			if (obj.constructor === Array) {
				var k = 0;
			} else {
				for (var k in obj) break;
			}

			return (k == key);
		},
		'~Number': function (key, def) {
			var n = Number(key);
			if (!n && n != 0) n = def;
			return n;
		},
		'~split': function (name, str, sn, sv) {
			str = String(str)
			let ar = str.split(',');
			for (let i = 0, l = ar.length; i < l; i++) {
				let val = ar[i].trim();
				ar[i] = [];
				ar[i][name] = val;
				ar[i][sn] = sv;
			}
			return ar;
		},
		'~round': (float, num = 0) => {
			if (num) return Math.round(float * num * 10) / (num * 10);
			return Math.round(float);
		},
		'~costround': cost => {
			if (!cost && cost != 0) cost = '';
			cost = String(cost);
			const ar = cost.split(/[,\.]/)
			cost = Number(ar[0]);
			let cop = ''
			if (cost < 100) {
				if (ar.length >= 2) {
					cop = ar[1];
					if (cop.length == 1) {
						cop += '0';
					}
					if (cop.length > 2) {
						cop = cop.substring(0, 3)
						cop = Number(cop)
						cop = Math.round(cop / 10)
					}
					if (cop == '00') cop = '';

				}
			}
			return [cost, cop];
		},
		'~cost': function (number, text, float) {
			const r = Template.scope['~costround'](number)
			let cost = String(r[0])
			const cop = r[1]
			let inp = '&nbsp;'
			if (text) inp = ' ';
			const l = cost.length;
			if (l > 4) { //1000
				if (l > 6) {
					//$last = mb_substr($cost, $l - 3, 3);
					//$before = mb_substr($cost, $l - 6, 3);
					//$start = mb_substr($cost, 0, $l - 6);
					const last = cost.substr(l - 3, 3)
					const before = cost.substr(l - 6, 3);
					const start = cost.substr(0, l - 6)
					cost = start + inp + before + inp + last
				} else {
					const last = cost.substr(l - 3, 3)
					const start = cost.substr(0, l - 3)
					cost = start + inp + last
					//cost = cost.substr(0, l - 3) + inp + cost.substr(l - 3, l);
				}
				
			}

			if (cop) {
				if (text) cost = cost + ',' + cop;
				else cost = cost + '<small>,' + cop + '</small>';
			} else if(float) {
				if (text) cost = cost + ',00';
				else cost = cost + '<small>,00</small>';
			}
			

			return cost;
		},
		"infra": {
			"theme": function (path) {
				return Path.theme(path);
			},
			"seq": {
				"short": Seq.short,
				"right": Seq.right
			},
			'srcinfo': Load.srcinfo,
			'conf': Config.get(),
			'view': {
				getPath: function (...args) {
					return View.getPath.apply(View, args)
				},
				getHost: function (...args) {
					return View.getHost.apply(View, args)
				},
				getRoot: function (...args) {
					return View.getRoot.apply(View, args)
				}
			}
		},
		'location': location
	}
}
window.Template = Template;


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



export { Template }