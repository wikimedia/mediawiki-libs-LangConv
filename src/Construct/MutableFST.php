<?php

namespace Wikimedia\LangConv\Construct;

/**
 * A mutable FST.
 */
class MutableFST {
	/** Special alphabet symbol for `nothing` used in AT&T format FST files. */
	public const EPSILON = '@0@';
	/** Special alphabet symbol for `?` used in AT&T format FST files. */
	public const IDENTITY = '@_IDENTITY_SYMBOL_@';

	private $alphabet = [];
	private $states = [];

	/**
	 * Create a new MutableFST with the given alphabet.
	 * @param string[] $alphabet
	 */
	public function __construct( array $alphabet ) {
		// Create start state
		$this->newState();
		// Set up alphabet
		foreach ( $alphabet as $tok ) {
			$this->alphabet[$tok] = true;
		}
		$this->alphabet[self::EPSILON] = true;
		$this->alphabet[self::IDENTITY] = true;
	}

	/**
	 * Add a new state to this FST.
	 * @return State the new state.
	 */
	public function newState(): State {
		$id = count( $this->states );
		$s = $this->states[] = new State( $id );
		return $s;
	}

	/**
	 * Return the start state of this FST.
	 * @return State the start state.
	 */
	public function getStartState(): State {
		return $this->states[0];
	}

	/**
	 * Apply the given FST to the array of input tokens.  Symbols in
	 * 'upper' are matched and symbols in 'lower' are emitted.
	 * @param string[] $input
	 * @return array<array<string>>
	 */
	public function applyDown( array $input ): array {
		return $this->applyUpDown( $input, true );
	}

	/**
	 * Apply the given FST to the array of input tokens.  Symbols in
	 * 'lower' are matched and symbols in 'upper' are emitted.
	 * @param string[] $input
	 * @return array<array<string>>
	 */
	public function applyUp( array $input ): array {
		return $this->applyUpDown( $input, false );
	}

	/**
	 * Apply the given FST to the array of input tokens.
	 * @param string[] $input array of input tokens
	 * @param bool $isDown Whether to apply 'up' or 'down'
	 * @return array<array<string>>
	 */
	private function applyUpDown( array $input, bool $isDown ): array {
		// This is an extremely simple implementation, made for validating
		// the FST not efficient execution.
		$results = [];
		$stack = [];
		$addTok = function ( $toks, $t ) {
			if ( $t !== self::EPSILON ) {
				$toks[] = $t;
			}
			return $toks;
		};
		$stack[] = [ $this->getStartState(), 0, [] ];
		while ( count( $stack ) > 0 ) {
			[ $state, $pos, $emitted ] = array_pop( $stack );
			$tok = $pos < count( $input ) ? $input[$pos] : null;
			if ( $tok !== null && !isset( $this->alphabet[$tok] ) ) {
				$tok = self::IDENTITY;
			}
			foreach ( $state->edges as $e ) {
				if ( $isDown ) {
					$match = $e->upper;
					$output = $e->lower;
				} else {
					$match = $e->lower;
					$output = $e->upper;
				}
				if ( $output === self::IDENTITY && $tok !== null ) {
					$output = $input[$pos];
				}
				if ( $match === self::EPSILON ) {
					$stack[] = [ $e->to, $pos, $addTok( $emitted, $output ) ];
				} elseif ( $match === $tok && $tok !== null ) {
					$stack[] = [ $e->to, $pos + 1, $addTok( $emitted, $output ) ];
				}
			}
			if ( $tok === null && $state->isFinal ) {
				$results[] = $emitted;
			}
		}
		return $results;
	}

	/**
	 * Perform simple optimization on the FST, trimming unreachable states
	 * and attempting to combine edges containing epsilons.
	 */
	public function optimize(): void {
		$reachable = [];
		$stateWorklist = [];
		$stateWorklist[$this->getStartState()->id] = $this->getStartState();
		while ( count( $stateWorklist ) > 0 ) {
			$state = array_pop( $stateWorklist );
			$reachable[$state->id] = true;
			$edgeWorklist = $state->edges; // worklist
			while ( count( $edgeWorklist ) > 0 ) {
				$edge = array_pop( $edgeWorklist );
				if ( $edge === null ) {
					continue; /* removed edge */
				}

				if ( $edge->to->isFinal ) {
					if ( !isset( $reachable[$edge->to->id] ) ) {
						$stateWorklist[$edge->to->id] = $edge->to;
					}
					continue;
				}
				$nEdge = count( $edge->to->edges );
				if ( $nEdge === 0 ) {
					$state->edges[$edge->id] = null; // remove
					// XXX requeue all states which point to this
					continue;
				} elseif ( $nEdge === 1 ) {
					$nextEdge = $edge->to->edges[0];
					if (
						$edge->lower === self::EPSILON &&
						$nextEdge->upper === self::EPSILON
					) {
						$edge->lower = $nextEdge->lower;
						$edge->to = $nextEdge->to;
						$edgeWorklist[] = $edge;
						continue;
					} elseif (
						$edge->upper === self::EPSILON &&
						$nextEdge->lower == self::EPSILON
					) {
						$edge->upper = $nextEdge->upper;
						$edge->to = $nextEdge->to;
						$edgeWorklist[] = $edge;
						continue;
					} elseif (
						$edge->upper === self::EPSILON &&
						$edge->lower === self::EPSILON
					) {
						$edge->upper = $nextEdge->upper;
						$edge->lower = $nextEdge->lower;
						$edge->to = $nextEdge->to;
						$edgeWorklist[] = $edge;
						continue;
					} elseif (
						$nextEdge->upper === self::EPSILON &&
						$nextEdge->lower === self::EPSILON
					) {
						$edge->to = $nextEdge->to;
						$edgeWorklist[] = $edge;
						continue;
					}
				}
				// fall through
				if ( !isset( $reachable[$edge->to->id] ) ) {
					$stateWorklist[$edge->to->id] = $edge->to;
				}
			}
		}
		// Renumber states and edges
		$j = 0;
		for ( $i = 0; $i < count( $this->states ); $i++ ) {
			$s = $this->states[$i];
			if ( isset( $reachable[$s->id] ) ) {
				$s->id = $j++;
				$this->states[$s->id] = $s;
				// renumber edges
				$k = 0;
				for ( $l = 0; $l < count( $s->edges ); $l++ ) {
					$e = $s->edges[$l];
					if ( $e !== null ) {
						$e->id = $k++;
						$s->edges[$e->id] = $e;
					}
				}
				array_splice( $s->edges, $k );
			}
		}
		array_splice( $this->states, $j );
	}

	/**
	 * Write an AT&T format description of this FST to the given filehandle.
	 * @param resource $handle
	 */
	public function writeATT( $handle ): void {
		// Write an AT&T format FST file
		foreach ( $this->states as $state ) {
			$state->writeATT( $handle );
		}
		// Now write the final states
		foreach ( $this->states as $state ) {
			if ( $state->isFinal ) {
				fwrite( $handle, strval( $state->id ) . "\n" );
			}
		}
	}
}
