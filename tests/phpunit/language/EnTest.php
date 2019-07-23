<?php

namespace Wikimedia\LangConv\Tests\Language;

use PHPUnit\Framework\TestCase;
use Wikimedia\LangConv\ReplacementMachine;

class EnTest extends TestCase {

	const CODES = [ "en", "en-x-piglatin" ];

	/** @var ReplacementMachine */
	private $machine;

	protected function setUp() {
		$this->machine = new ReplacementMachine( "en", self::CODES );
	}

	protected function tearDown() {
		$this->machine = null;
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testConvertEnToPigLatin() {
		$input = "123 Pigpen pig latin of 123 don't stop believing in yourself queen JavaScript " .
			"NASA";
		$output = [
			"en" => "123 Pigpen pig latin of 123 don't stop believing in yourself queen " .
				"JavaScript NASA",
			"en-x-piglatin" => "123 Igpenpay igpay atinlay ofway 123 on'tday opstay elievingbay " .
				"inway ourselfyay eenquay JavaScript NASA",
		];
		$code = "en";

		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->machine->convert( $input, $variantCode, $code );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testConvertFromPigLatin() {
		$input = "123 Igpenpay igpay atinlay ofway 123 on'tday opstay elievingbay inway " .
			"ourselfyay eenquay avaScriptJay ASANAY";
		$output = [
			"en" => "123 Pigpen pig latin of 123 don't tops believing in yourself queen " .
				"avaScriptJay ASANAY",
			"en-x-piglatin" => "123 Igpenpayway igpayway atinlayway ofwayway 123 on'tdayway " .
				"opstayway elievingbayway inwayway ourselfyayway eenquayway avaScriptJay ASANAY",
		];
		// XXX: this is currently treated as just a guess, so it doesn't prevent pig latin from
		// being double-encoded.
		$code = "en-x-piglatin";

		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->machine->convert( $input, $variantCode, $code );
			$this->assertEquals( $expected, $result );
		}
	}

}
