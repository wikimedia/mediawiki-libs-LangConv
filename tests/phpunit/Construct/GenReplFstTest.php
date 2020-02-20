<?php

namespace Test\Wikimedia\LangConv\Construct;

use PHPUnit\Framework\TestCase;
use Wikimedia\LangConv\Construct\GenReplFst;

/**
 * @coversDefaultClass \Wikimedia\LangConv\Construct\GenReplFst
 */
class GenReplFstTest extends TestCase {

	/**
	 * @covers ::__construct
	 * @covers ::applyDown
	 * @covers ::applyUp
	 */
	public function testSimple() {
		$g = new GenReplFst( 'test', [
			'ab' => 'aa',
			'abcd' => 'bbbb',
			'cbc' => 'ccc',
			'ba' => 'dd'
		] );

		$this->assertEquals( [ 'aacddd' ], $g->applyDown( 'abcbad' ) );
		$this->assertEquals( [
			'abcbad',
			'aacddd',
			'aacdba',
			'aacbad',
		], $g->applyUp( 'aacddd' ) );
	}
}
