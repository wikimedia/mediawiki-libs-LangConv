<?php

namespace Wikimedia\LangConv;

/**
 * A simple tuple type for the FST backtracking state.
 */
class BacktrackState {
	/**
	 * Edge at which to resume execution.
	 * @var int
	 */
	public $epsEdge;
	/**
	 * Position in the output string.
	 * @var int
	 */
	public $outpos;
	/**
	 * Speculative result string.
	 * @var string
	 */
	public $partialResult = '';
	/**
	 * Speculative bracket list.
	 * @var int[]
	 */
	public $partialBrackets = [];
	/**
	 * Position in the input string.
	 * @var int
	 */
	public $idx;

	/**
	 * Create a new BacktrackState.
	 * @param int $epsEdge
	 * @param int $outpos
	 * @param int $idx
	 */
	public function __construct( int $epsEdge, int $outpos, int $idx ) {
		$this->epsEdge = $epsEdge;
		$this->outpos = $outpos;
		$this->idx = $idx;
	}
}
