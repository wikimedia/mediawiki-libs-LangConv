/** Test cases for zh language conversion */

'use strict';

/* global describe, it */

require('chai').should();

const domino = require('domino');

describe('LanguageZh tests', function() {

	const { ZhReplacementMachine } =
		require('../../../lib/ZhReplacementMachine.js');

	const testCases = [
		{
			title: "Plain hant -> hans",
			output: {
				'zh'      : '㑯',
				'zh-hans' : '㑔',
				'zh-hant' : '㑯',
				'zh-cn'   : '㑔',
				'zh-hk'   : '㑯',
				'zh-mo'   : '㑯',
				'zh-my'   : '㑔',
				'zh-sg'   : '㑔',
				'zh-tw'   : '㑯',
			},
			input: '㑯',
		},
		{
			title: "Plain hans -> hant",
			output: {
				'zh'      : '㐷',
				'zh-hans' : '㐷',
				'zh-hant' : '傌',
				'zh-cn'   : '㐷',
				'zh-hk'   : '傌',
				'zh-mo'   : '傌',
				'zh-my'   : '㐷',
				'zh-sg'   : '㐷',
				'zh-tw'   : '傌',
			},
			input: '㐷',
		},
		{
			title: "zh-cn specific",
			output: {
				'zh'      : '仲介',
				'zh-hans' : '仲介',
				'zh-hant' : '仲介',
				'zh-cn'   : '中介',
				'zh-hk'   : '仲介',
				'zh-mo'   : '仲介',
				'zh-my'   : '中介',
				'zh-sg'   : '中介',
				'zh-tw'   : '仲介',
			},
			input: '仲介',
		},
		{
			title: "zh-hk specific",
			output: {
				'zh'      : '中文里',
				'zh-hans' : '中文里',
				'zh-hant' : '中文裡',
				'zh-cn'   : '中文里',
				'zh-hk'   : '中文裏',
				'zh-mo'   : '中文裏',
				'zh-my'   : '中文里',
				'zh-sg'   : '中文里',
				'zh-tw'   : '中文裡',
			},
			input: '中文里',
		},
		{
			title: "zh-tw specific",
			output: {
				'zh'      : '甲肝',
				'zh-hans' : '甲肝',
				'zh-hant' : '甲肝',
				'zh-cn'   : '甲肝',
				'zh-hk'   : '甲肝',
				'zh-mo'   : '甲肝',
				'zh-my'   : '甲肝',
				'zh-sg'   : '甲肝',
				'zh-tw'   : 'A肝',
			},
			input: '甲肝',
		},
		{
			title: "zh-tw overrides zh-hant",
			output: {
				'zh'      : '账',
				'zh-hans' : '账',
				'zh-hant' : '賬',
				'zh-cn'   : '账',
				'zh-hk'   : '賬',
				'zh-mo'   : '賬',
				'zh-my'   : '账',
				'zh-sg'   : '账',
				'zh-tw'   : '帳',
			},
			input: '账',
		},
		{
			title: "zh-hk overrides zh-hant",
			output: {
				'zh'      : '一地里',
				'zh-hans' : '一地里',
				'zh-hant' : '一地裡',
				'zh-cn'   : '一地里',
				'zh-hk'   : '一地裏',
				'zh-mo'   : '一地裏',
				'zh-my'   : '一地里',
				'zh-sg'   : '一地里',
				'zh-tw'   : '一地裡',
			},
			input: '一地里',
		},
	];

	const machine = new ZhReplacementMachine();
	['zh-cn','zh-sg', 'zh-my', 'zh-hans', 'zh-tw', 'zh-hk', 'zh-mo', 'zh-hant'].forEach((variantCode) => {
		const invCode = /^zh-(cn|sg|my|hans)$/.test(variantCode) ? 'zh-hant' : 'zh-hans';
		testCases.forEach((test) => {
			if (variantCode in test.output) {
				it(`${ test.title } [${ variantCode }]`, function() {
					const doc = domino.createDocument();
					const out = machine.convert(
						doc, test.input, variantCode, test.code || invCode
					);
					out.textContent.should.equal(test.output[variantCode]);
				});
			}
		});
	});
});
