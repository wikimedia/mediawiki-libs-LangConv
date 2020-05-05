<?php

namespace Test\Wikimedia\LangConv;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Wikimedia\LangConv\FstReplacementMachine;

/**
 * @coversDefaultClass \Wikimedia\LangConv\FstReplacementMachine
 */
class FstReplacementMachineTest extends TestCase {
	/** @var FstReplacementMachine|null */
	private static $machine;

	public static function setUpBeforeClass(): void {
		self::$machine = new FstReplacementMachine( 'sr', [ 'sr-ec', 'sr-el' ] );
	}

	public static function tearDownAfterClass(): void {
		self::$machine = null;
	}

	/**
	 * @covers ::convert
	 */
	public function testBrackets() {
		$doc = new DOMDocument();
		$result = self::$machine->convert( $doc, "абвг", "sr-el", "sr-ec" );
		$resultHTML = $doc->saveHTML( $result );
		$this->assertEquals( "abvg", $resultHTML );
	}
}
