<?php

namespace Wikimedia\LangConv\Tests\Language;

use PHPUnit\Framework\TestCase;
use Wikimedia\LangConv\ReplacementMachine;

class KuTest extends TestCase {

	const CODES = [ "ku-arab", "ku-latn" ];

	/** @var ReplacementMachine */
	private $machine;

	protected function setUp() {
		$this->machine = new ReplacementMachine( "ku", self::CODES );
	}

	protected function tearDown() {
		$this->machine = null;
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testConvertOneChar() {
		$input = "١";
		$output = [
			"ku"      => "١",
			"ku-arab" => "١",
			"ku-latn" => "1",
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testConvertKuLatn() {
		$input = "Wîkîpediya ensîklopediyeke azad bi rengê wîkî ye.";
		$output = [
			"ku"      => "Wîkîpediya ensîklopediyeke azad bi rengê wîkî ye.",
			// XXX broken!
			// "ku-arab" => "ویکیپەدیائە نسیکلۆپەدیەکەئا زاد ب رەنگێ ویکی یە.",
			"ku-latn" => "Wîkîpediya ensîklopediyeke azad bi rengê wîkî ye.",
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
	public function testConvertKuArab() {
		$input = "ویکیپەدیا ەنسیکلۆپەدیەکەئا زاد ب رەنگێ ویکی یە.";
		$output = [
			"ku"      => "ویکیپەدیا ەنسیکلۆپەدیەکەئا زاد ب رەنگێ ویکی یە.",
			"ku-arab" => "ویکیپەدیا ەنسیکلۆپەدیەکەئا زاد ب رەنگێ ویکی یە.",
			"ku-latn" => "wîkîpedîa ensîklopedîekea zad b rengê wîkî îe.",
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	private function convert( $input, $variantCode ) {
		return $this->machine->convert( $input, $variantCode, $this->getInvertCode( $variantCode ) );
	}

	private function getInvertCode( $variantCode ) {
		return $variantCode === "ku-arab" ? "ku-latn" : "ku-arab";
	}

}
