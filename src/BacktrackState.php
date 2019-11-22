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
	 * Position in the input string.
	 * @var int
	 */
	public $idx;
	/**
	 * Length of the bracket result array.
	 * @var int
	 */
	public $blen;

	/**
	 * Create a new BacktrackState.
	 * @param int $epsEdge
	 * @param int $outpos
	 * @param int $idx
	 * @param int $blen
	 */
	public function __construct( int $epsEdge, int $outpos, int $idx, int $blen ) {
		$this->epsEdge = $epsEdge;
		$this->outpos = $outpos;
		$this->idx = $idx;
		$this->blen = $blen;
	}
}
