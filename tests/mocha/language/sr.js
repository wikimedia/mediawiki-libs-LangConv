/** Test cases for Serbian language conversion */

'use strict';

/* global describe, it */

require('chai').should();

const domino = require('domino');

describe('LanguageSr tests', function() {

	const { ReplacementMachine } =
		require('../../../lib/ReplacementMachine.js');

	const testCases = [
		{
			title: "A simple conversion of Latin to Cyrillic",
			output: {
				'sr-ec' : 'абвг',
			},
			input: 'abvg'
		},
		{
			title: "Same as above, but assert that -{}-s must be removed and not converted",
			output: {
				// XXX: we don't support embedded -{}- markup in mocha tests;
				//      use parserTests for that
				// 'sr-ec' : 'ljабnjвгdž'
			},
			input: `<span typeof="mw:LanguageVariant" data-mw-variant='{"disabled":{"t":"lj"}}'></span>аб<span typeof="mw:LanguageVariant" data-mw-variant='{"disabled":{"t":"nj"}}'></span>вг<span typeof="mw:LanguageVariant" data-mw-variant='{"disabled":{"t":"dž"}}'></span>`
		},
	];

	const machine = new ReplacementMachine('sr', 'sr-ec', 'sr-el');
	['sr-ec','sr-el'].forEach((variantCode) => {
		const invCode = variantCode === 'sr-ec' ? 'sr-el' : 'sr-ec';
		testCases.forEach((test) => {
			if (variantCode in test.output) {
				it(`${test.title} [${variantCode}]`, function() {
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
