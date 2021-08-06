<?php

namespace Wikimedia\LangConv\Test;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Wikimedia\LangConv\NullReplacementMachine;

/**
 * @coversDefaultClass \Wikimedia\LangConv\NullReplacementMachine
 */
class NullReplacementMachineTest extends TestCase {
	/** @var NullReplacementMachine|null */
	private static $machine;

	public static function setUpBeforeClass(): void {
		self::$machine = new NullReplacementMachine( 'sr' );
	}

	public static function tearDownAfterClass(): void {
		self::$machine = null;
	}

	/**
	 * @covers ::convert
	 */
	public function testConvert() {
		$doc = new DOMDocument();
		$result = self::$machine->convert( $doc, "abcd абвг", "sr", "sr" );
		$resultHTML = $doc->saveHTML( $result );
		$this->assertEquals( "abcd &#x430;&#x431;&#x432;&#x433;", $resultHTML );
	}
}
