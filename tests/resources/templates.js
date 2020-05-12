import { Template } from '/vendor/infrajs/template/Template.js'

Template.test = function (k) {
	Load.unload('-infra/ext/template.js');
	Load.require('-infra/ext/template.js');
	Template.test = arguments.callee;
	Load.unload('-infra/tests/resources/templates.json');
	var tpls = Load.loadJSON('-infra/tests/resources/templates.json');
	infra.forr(tpls, function (t, key) {
		if (typeof (k) !== 'undefined' && key !== k) return;

		h = key + ' ' + t['tpl'];
		if (typeof (t['data']) == 'undefined') var data = {};
		else var data = t['data'];

		var tp = Template.make([t['tpl']]);
		if (typeof (k) !== 'undefined') console.log(tp);
		var r = Template.exec(tp, data);
		var er = (r !== t['res']);


		if (!er) h += ' "' + r + '"';
		else h += ' "' + r + '" надо "' + t['res'] + '"';

		var com = (t['com'] || '');
		if (com) com = ' > ' + com;
		else com = ' ';
		if (er) console.error(h, com);
		else console.log(h);


		if (typeof (k) !== 'undefined') console.log(data);
	});
}
Template.test.good = true;
