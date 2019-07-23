<?php

namespace Wikimedia\LangConv;

use Error;

/**
 * Load and execute a finite-state transducer (FST) based converter or
 * bracketing machine from a compact JSON description.
 */
class FST {
	const MAGIC_BYTES = 8; // 8 byte header w/ magic bytes

	// These pseudo-characters appear in the "output" side of the FST only.
	const BYTE_IDENTITY = 0xFF;
	const BYTE_RBRACKET = 0xFE;
	const BYTE_LBRACKET = 0xFD;
	const BYTE_FAIL     = 0xFC;
	// These pseudo-characters appear in the "input" side of the FST.
	const BYTE_EOF      = 0xF8; // The highest possible input char
	const BYTE_EPSILON  = 0x00; // Always appears first in sorted order

	/**
	 * Load an FST description and return a function which runs the machine.
	 * @param string|array $pfst The FST description  either as a filename (to be loaded synchronously)
	 *  or a loaded byte array.
	 * @param bool $justBrackets The machine will return an array of bracket locations,
	 *  instead of the converted text.
	 * @return callable
	 */
	public static function compile( $pfst, $justBrackets = false ) {
		if ( is_string( $pfst ) ) {
			$file = file_get_contents( $pfst );
			// call array_values on the result of unpack() to transform from a 1- to 0-indexed array
			$pfst = array_values( unpack( 'C*', $file ) );
		}
		if (
			count( $pfst ) < ( self::MAGIC_BYTES + 2/* states, min*/ ) ||
				call_user_func_array(
					'pack',
					array_merge( [ "C*" ], array_slice( $pfst, 0, 8 ) )
				) !== "pFST\0WM\0"
		) {
			throw new Error( "Invalid pFST file." );
		}
		if ( $justBrackets === "split" ) {
			// Debugging helper: instead of an array of positions, split the
			// input at the bracket locations and return an array of strings.
			$bfunc = self::compile( $pfst, true );
			return function ( $input, $start = null, $end = null ) use ( $bfunc ) {
				$end = $end ?? count( $input );
				$r = $bfunc( $input, $start, $end );
				$r[] = $end;
				$i = 0;
				return array_map( function ( $j ) use ( $input, &$i ) {
					$b = substr( $input, $i, $j );
					$i = $j;
					return $b;
				}, $r );
			};
		}
		return function ( $input, $start = null, $end = null, $unicode = false )
			use ( $pfst, $justBrackets ) {
			$start = $start ?? 0;
			$end = $end ?? count( $input );
			$countCodePoints = $justBrackets && $unicode;
			$initialState = self::MAGIC_BYTES + 2; /* eof state */
			$state = $initialState;
			$idx = $start;
			$outpos = 0;
			$brackets = [ 0 ];
			$stack = [];
			$result = [];

			// Read zig-zag encoded variable length integers
			// (See [[:en:Variable-length_quantity#Zigzag_encoding]])
			$readUnsignedV = function () use ( $pfst, &$state ) {
				$b = $pfst[$state++];
				$val = $b & 127;
				while ( $b & 128 ) {
					$val += 1;
					$b = $pfst[$state++];
					$val = ( $val << 7 ) + ( $b & 127 );
				}
				return $val;
			};

			$readSignedV = function () use ( $readUnsignedV ) {
				$v = $readUnsignedV();
				if ( $v & 1 ) { // sign bit is in LSB
					return -( $v >> 1 ) - 1;
				} else {
					return $v >> 1;
				}
			};

			// Add a character to the output.
			$emit = $justBrackets ? function ( $code ) use ( $countCodePoints, &$brackets,
				&$outpos, &$result ) {
				if ( $code === self::BYTE_LBRACKET || $code === self::BYTE_RBRACKET ) {
					$brackets[] = $outpos;
				} elseif ( $countCodePoints && $code >= 0x80 && $code < 0xC0 ) {
					/* Ignore UTF-8 continuation characters */
				} else {
					$outpos++;
				}
			} : function ( $code ) use ( &$result, &$outpos ) {
				$result[$outpos++] = $code;
			};

			// Save the current machine state before taking a non-deterministic edge;
			// if the machine fails, restart at the given `state`
			$save = function ( $epsEdge ) use ( &$idx, &$outpos, &$stack, &$brackets ) {
				$stack[] = [
						"epsEdge" => $epsEdge,
						"outpos" => $outpos,
						"idx" => $idx,
						"blen" => count( $brackets )
				];
			};

			$reset = function () use ( $pfst, &$state, &$idx, &$outpos, &$stack, &$brackets,
				$readSignedV, $emit ) {
				$s = array_pop( $stack );
				$outpos = $s["outpos"];
				$idx = $s["idx"];
				$brackets = array_slice( $brackets, 0, $s["blen"] );
				// Get outByte from this edge, then jump to next state
				$state = $s["epsEdge"] + 1; /* skip over inByte */
				$edgeOut = $pfst[$state++];
				if ( $edgeOut !== self::BYTE_EPSILON ) {
					$emit( $edgeOut );
				}
				$edgeDest = $state;
				$edgeDest += $readSignedV();
				$state = $edgeDest;
			};

			// This runs the machine until we reach the EOF state
			while ( $state >= $initialState ) {
				$edgeWidth = $readUnsignedV();
				$nEdges = $readUnsignedV();
				if ( $nEdges === 0 ) {
					$reset();
					continue;
				}
				// Read first edge to see if there are any epsilon edges
				$edge0 = $state;
				while ( $pfst[$edge0] === self::BYTE_EPSILON ) {
					// If this is an epsilon edge, then save a backtrack state
					$save( $edge0 );
					$edge0 += $edgeWidth;
					$nEdges--;
					if ( $nEdges === 0 ) {
						$reset();
						continue 2;
					}
				}
				// Binary search for an edge matching c
				$c = $idx < $end ? $input[$idx++] : /* pseudo-character: */ self::BYTE_EOF;
				$minIndex = 0;
				$maxIndex = $nEdges;
				while ( $minIndex !== $maxIndex ) {
					$currentIndex = ( $minIndex + $maxIndex ) >> 1;
					$targetEdge = $edge0 + ( $edgeWidth * $currentIndex );
					$inByte = $pfst[$targetEdge];
					if ( $inByte <= $c ) {
						$minIndex = $currentIndex + 1;
					} else {
						$maxIndex = $currentIndex;
					}
				}
				// (minIndex-1).inByte <= c, and maxIndex.inByte > c
				$targetEdge = $edge0 + ( $edgeWidth * ( $minIndex - 1 ) );
				$outByte = $minIndex > 0 ? $pfst[$targetEdge + 1] : self::BYTE_FAIL;
				if ( $outByte === self::BYTE_FAIL ) {
					$reset();
					continue;
				}
				if ( $outByte !== self::BYTE_EPSILON ) {
					if ( $outByte === self::BYTE_IDENTITY ) {
						$outByte = $c; // Copy input byte to output
					}
					$emit( $outByte );
				}
				$state = $targetEdge + 2; // skip over inByte/outByte
				$state = $readSignedV() + ( $targetEdge + 2 );
			}

			// Ok, process the final state and return something.
			if ( $justBrackets ) {
				$brackets[] = $outpos;
				return $brackets;
			}
			return call_user_func_array(
				'pack',
				array_merge( [ "C*" ], array_slice( $result, 0, $outpos ) )
			);
		};
	}

}
