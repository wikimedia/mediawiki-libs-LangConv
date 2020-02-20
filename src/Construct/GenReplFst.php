<?php

namespace Wikimedia\LangConv\Construct;

/**
 * GENerate a REPLacement string FST.
 *
 * Create an FST from a replacement string array (aka, as would be provided
 * to `str_tr` or `ReplacementArray` in mediawiki core).
 */
class GenReplFst {
	// This can be anything, as long as it is longer than 1 character
	// (so it doesn't conflict w/ any of the single-character keys)
	private const END_OF_STRING = '*END*';
	// Again, this can be anything as long as it is > 1 character long
	private const EMIT = '*EMIT*';
	// correlation of tree nodes to state machine states
	private const STATE = '*STATE*';

	/** @var array<int|string,string|array> */
	private $prefixTree = [];
	/** @var MutableFST */
	private $fst;
	/** @var State */
	private $anythingState;
	/** @var array */
	private $alphabet;
	/** @var array */
	private $workQueue;

	/**
	 * Add each letter in the given word to our alphabet.
	 * @param array &$alphabet
	 * @param string $word
	 */
	private static function addAlphabet( array &$alphabet, string $word ): void {
		for ( $i = 0; $i < strlen( $word ); $i++ ) {
			$alphabet[ord( $word[$i] )] = true;
		}
	}

	/**
	 * Add the given string (the substring of $from starting from $index) to
	 * the prefix tree $tree, along with the conversion output $to.
	 * @param array<int|string,string|array> &$tree
	 * @param string $from
	 * @param int $index
	 * @param string $to
	 */
	private static function addEntry( array &$tree, string $from, int $index, string $to ): void {
		$c = ord( $from[$index] );
		if ( !isset( $tree[$c] ) ) {
			$tree[$c] = [];
		}
		$nextIndex = $index + 1;
		if ( $nextIndex < strlen( $from ) ) {
			self::addEntry( $tree[$c], $from, $nextIndex, $to );
		} else {
			$tree[$c][self::END_OF_STRING] = $to;
		}
	}

	/**
	 * Add edges from state $from corresponding to the prefix tree $tree,
	 * given the $lastMatch and the characters seen since then, $seen.
	 * @param State $from
	 * @param array &$tree
	 * @param ?string $lastMatch (null if we haven't seen a match)
	 * @param string $seen characters seen since last match
	 */
	private function buildState( State $from, array &$tree, ?string $lastMatch, string $seen ): void {
		$tree[self::STATE] = $from;

		if ( isset( $tree[self::END_OF_STRING] ) ) {
			$lastMatch = $tree[self::END_OF_STRING];
			$seen = '';
		}
		foreach ( $this->alphabet as $c ) {
			$nextSeen = $seen . chr( $c );
			if ( isset( $tree[$c] ) ) {
				$n = $this->fst->newState();
				$from->addEdge( self::byteToHex( $c ), MutableFST::EPSILON, $n );
				$this->buildState( $n, $tree[$c], $lastMatch, $nextSeen );
			} elseif ( $lastMatch !== null ) {
				$n = $this->emit( $from, self::byteToHex( $c ), $lastMatch );
				// simulate the match of $seen from the start state, add
				// appropriate emit edges, then add an epsilon transition
				// to the new state.
				$this->queueEdge( $n, $nextSeen, false );
			} else {
				$n = $this->emit( $from, self::byteToHex( $c ), $nextSeen[0] );
				$this->queueEdge( $n, substr( $nextSeen, 1 ), false );
			}
		}
		// "anything else"
		$n = $this->emit( $from, MutableFST::EPSILON, $lastMatch ?? '' );
		$this->queueEdge( $n, $seen, true/*identity at the end*/ );
	}

	/**
	 * Chain states together from $fromState to emit $emitStr.
	 * @param State $fromState
	 * @param string $fromChar
	 * @param string $emitStr
	 * @return State the resulting state (after the string has been emitted)
	 */
	private function emit( State $fromState, string $fromChar, string $emitStr ): State {
		if ( strlen( $emitStr ) === 0 && $fromChar !== MutableFST::EPSILON ) {
			$n = $this->fst->newState();
			$fromState->addEdge( $fromChar, MutableFST::EPSILON, $n );
			return $n;
		}
		for ( $i = 0; $i < strlen( $emitStr ); $i++ ) {
			$c = ord( $emitStr[$i] );
			$n = $this->fst->newState();
			$fromState->addEdge( $fromChar, self::byteToHex( $c ), $n );
			$fromState = $n;
			$fromChar = MutableFST::EPSILON;
		}
		return $fromState;
	}

	/**
	 * In the future, create states leading from $from which correspond to
	 * the "replay" input string $input, ending with a out-of-alphabet
	 * character if $endsWithIdentity is true.
	 * @param State $from
	 * @param string $input
	 * @param bool $endsWithIdentity
	 */
	private function queueEdge( State $from, string $input, bool $endsWithIdentity ): void {
		$this->workQueue[] = [ $from, $input, $endsWithIdentity ];
	}

	/**
	 * "Replay" the input string $input from the state corresponding to
	 * the given prefix tree.  Track $lastMatch and the characters seen
	 * since then $seen, and chain any characters emitted while replaying
	 * this input from state $from.
	 * @param array &$tree
	 * @param State $from
	 * @param string $input
	 * @param ?string $lastMatch
	 * @param string $seen
	 * @param bool $endsWithIdentity
	 */
	private function followSeen(
		array &$tree, State $from, string $input,
		?string $lastMatch, string $seen, bool $endsWithIdentity
	): void {
		/*
		error_log(
			"followSeen tree " . $tree[self::STATE]->id . " from " . $from->id .
			" input $input" . "[" . strlen( $input ) . "] " .
			"lastMatch $lastMatch seen $seen " .
			"endsWithIdentity $endsWithIdentity"
		);
		*/
		if ( isset( $tree[self::END_OF_STRING] ) ) {
			$lastMatch = $tree[self::END_OF_STRING];
			$seen = '';
		}
		if ( strlen( $input ) === 0 ) {
			if ( $endsWithIdentity ) {
				$n = $this->emit( $from, MutableFST::EPSILON, $lastMatch ?? '' );
				if ( strlen( $seen ) === 0 ) {
					$n->addEdge( MutableFST::EPSILON, MutableFST::EPSILON, $this->anythingState );
				} elseif ( $lastMatch !== null ) {
					$this->queueEdge( $n, $seen, true );
				} else {
					$n = $this->emit( $n, MutableFST::EPSILON, $seen[0] );
					$this->queueEdge( $n, substr( $seen, 1 ), true );
				}
			} else {
				$from->addEdge( MutableFST::EPSILON, MutableFST::EPSILON, $tree[self::STATE] );
			}
			return;
		}
		$c = ord( $input[0] );
		if ( isset( $tree[$c] ) ) {
			$this->followSeen(
				$tree[$c], $from, substr( $input, 1 ),
				$lastMatch, $seen . $input[0], $endsWithIdentity
			);
		} elseif ( $lastMatch !== null ) {
			$n = $this->emit( $from, MutableFST::EPSILON, $lastMatch );
			$this->queueEdge( $n, $seen . $input, $endsWithIdentity );
		} else {
			// no matches with this input string.  emit the first character
			// verbatim and try to match again from the top.
			$nextSeen = $seen . $input;
			$n = $this->emit( $from, MutableFST::EPSILON, $nextSeen[0] );
			$this->queueEdge( $n, substr( $nextSeen, 1 ), $endsWithIdentity );
		}
	}

	/**
	 * Private helper function: convert a numeric byte to the string
	 * token we use in the FST.
	 * @param int $byte
	 * @return string Token
	 */
	private static function byteToHex( int $byte ): string {
		$s = strtoupper( dechex( $byte ) );
		while ( strlen( $s ) < 2 ) {
			$s = "0$s";
		}
		return $s;
	}

	/**
	 * Private helper function: convert a UTF-8 string byte-by-byte into
	 * an array of tokens.
	 * @param string $s
	 * @return string[]
	 */
	private static function stringToTokens( string $s ): array {
		$toks = [];
		for ( $i = 0; $i < strlen( $s ); $i++ ) {
			$toks[] = self::byteToHex( ord( $s[$i] ) );
		}
		$toks[] = "@EOF@";
		return $toks;
	}

	/**
	 * Private helper function: convert an array of tokens into
	 * a UTF-8 string.
	 * @param string[] $toks
	 * @return string
	 */
	private static function tokensToString( array $toks ): string {
		$s = '';
		foreach ( $toks as $token ) {
			if ( $token === '@EOF@' ) {
				continue; // ignore, should be the last token
			} elseif ( strlen( $token ) === 2 ) {
				$s .= chr( hexdec( $token ) );
			} else {
				// shouldn't happen, but handy for debugging if it does
				$s .= $token;
			}
		}
		return $s;
	}

	/**
	 * For testing: apply the resulting FST to the given input string.
	 * @param string $input
	 * @return string[] The possible outputs.
	 */
	public function applyDown( string $input ): array {
		// convert input to byte tokens
		$result = $this->fst->applyDown( self::stringToTokens( $input ) );
		return array_map( function ( $toks ) {
			return self::tokensToString( $toks );
		}, $result );
	}

	/**
	 * For testing: run the resulting FST "in reverse" against the given
	 * input string.
	 * @param string $input
	 * @return string[] The possible outputs.
	 */
	public function applyUp( string $input ): array {
		// convert input to byte tokens
		$result = $this->fst->applyUp( self::stringToTokens( $input ) );
		return array_map( function ( $toks ) {
			return self::tokensToString( $toks );
		}, $result );
	}

	/**
	 * Convert the given $replacementTable (strtr-style) to an FST.
	 * @param string $name
	 * @param array<string,string> $replacementTable
	 */
	public function __construct( string $name, array $replacementTable ) {
		$alphabet = [];
		foreach ( $replacementTable as $from => $to ) {
			self::addAlphabet( $alphabet, $from );
			self::addAlphabet( $alphabet, $to );
			self::addEntry( $this->prefixTree, $from, 0, $to );
		}
		$this->alphabet = array_keys( $alphabet );
		sort( $this->alphabet, SORT_NUMERIC );
		// ok, now we're ready to emit the FST
		$this->fst = new MutableFST( array_map( function ( $n ) {
			return self::byteToHex( $n );
		}, $this->alphabet ) );
		$this->fst->getStartState()->isFinal = true;
		$this->anythingState = $this->fst->newState();
		$this->anythingState->addEdge(
			MutableFST::IDENTITY, MutableFST::IDENTITY,
			$this->fst->getStartState()
		);
		// Create states corresponding to prefix tree nodes
		$this->buildState(
			$this->fst->getStartState(), $this->prefixTree, null, ''
		);
		// Now handle the fixups ("replay" states)
		while ( count( $this->workQueue ) > 0 ) {
			[ $state, $input, $endsWithIdentity ] = array_pop( $this->workQueue );
			/*
			error_log( "Starting over with $input " .
					   "(ends w/ ID: $endsWithIdentity) link from " .
					   $state->id );
			*/
			$this->followSeen( $this->prefixTree, $state, $input, null, '', $endsWithIdentity );
		}
		// ok, done!
		$this->fst->optimize();
	}

	/**
	 * Write the FST to the given file handle in AT&T format.
	 * @param resource $handle
	 */
	public function writeATT( $handle ): void {
		$this->fst->writeATT( $handle );
	}

}
