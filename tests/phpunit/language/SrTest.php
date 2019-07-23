<?php

namespace Wikimedia\LangConv\Tests\Language;

use PHPUnit\Framework\TestCase;
use Wikimedia\LangConv\ReplacementMachine;

class SrTest extends TestCase {

	const CODES = [ "sr-ec", "sr-el" ];

	/** @var ReplacementMachine */
	private $machine;

	protected function setUp() {
		$this->machine = new ReplacementMachine( "sr", self::CODES );
	}

	protected function tearDown() {
		$this->machine = null;
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testConvertLatinToCyrillic() {
		$input = "abvg";
		$output = [
			"sr-ec" => "абвг",
		];
		foreach ( self::CODES as $variantCode ) {
			if ( !array_key_exists( $variantCode, $output ) ) {
				continue;
			}
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testBracketsNotConverted() {
		$this->markTestSkipped();
		// $input = "<span typeof=\"mw:LanguageVariant\" data-mw-variant='{\"disabled\":"
		// . "{\"t\":\"lj\"}}'></span>аб<span typeof=\"mw:LanguageVariant\" data-mw-variant="
		// . "'{\"disabled\":{\"t\":\"nj\"}}'></span>вг<span typeof=\"mw:LanguageVariant\" "
		// . "data-mw-variant='{\"disabled\":{\"t\":\"dž\"}}'></span>";
		// $output = [
		//     // XXX: we don't support embedded -{}- markup in mocha tests;
		//     //      use parserTests for that
		//     // 'sr-ec' : 'ljабnjвгdž'
		// ];
		// foreach ( self::CODES as $variantCode ) {
		//     if ( !array_key_exists( $variantCode, $output ) ) {
		//         continue;
		//     }
		//     $expected = $output[$variantCode];
		//     $result = $this->convert( $input, $variantCode );
		//     $this->assertEquals( $expected, $result );
		// }
	}

	private function convert( $input, $variantCode ) {
		return $this->machine->convert( $input, $variantCode, $this->getInvertCode( $variantCode ) );
	}

	private function getInvertCode( $variantCode ) {
		return $variantCode === "sr-ec" ? "sr-el" : "sr-ec";
	}

}
