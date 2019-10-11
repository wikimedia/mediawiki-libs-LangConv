<?php

namespace Wikimedia\LangConv;

use DOMDocument;
use DOMDocumentFragment;
use DOMNode;
use Wikimedia\Assert\Assert;

class ReplacementMachine {

	private $baseLanguage;
	private $codes;
	private $machines = [];

	/**
	 * ReplacementMachine constructor.
	 * @param string $baseLanguage
	 * @param string[] $codes
	 */
	public function __construct( $baseLanguage, $codes ) {
		$this->baseLanguage = $baseLanguage;
		$this->codes = $codes;
		foreach ( $codes as $code ) {
			$bracketMachines = [];
			foreach ( $codes as $code2 ) {
				if ( !$this->validCodePair( $code, $code2 ) ) {
					continue;
				}
				$dstCode = $code === $code2 ? 'noop' : $code2;
				$bracketMachines[$code2] = $this->loadFST( "brack-$code-$dstCode", true );
			}
			$this->machines[$code] = [
				'convert' => $this->loadFST( "trans-$code" ),
				'bracket' => $bracketMachines,
			];
		}
	}

	/** @return string[] */
	public function getCodes() {
		return $this->codes;
	}

	/**
	 * Load a conversion machine from a pFST file with filename $filename from the fst directory.
	 * @param string $filename filename, omitting the .pfst file extension
	 * @param bool $justBrackets whether to return only the bracket locations
	 * @return callable
	 */
	public function loadFST( $filename, $justBrackets = false ) {
		return FST::compile( __dir__ . "/../fst/$filename.pfst", $justBrackets );
	}

	/**
	 * Override this method in subclass if you want to limit the possible code pairs bracketed.
	 * (For example, zh has a large number of variants, but we typically want to use only a limited
	 * number of these as possible invert codes.)
	 * @param string $destCode
	 * @param string $invertCode
	 * @return bool whether this is a valid bracketing pair.
	 */
	public function validCodePair( $destCode, $invertCode ) {
		return true;
	}

	/**
	 * Quantify a guess about the "native" language of string `s`.
	 * We will be converting *to* `destCode`, and our guess is that when we round trip we'll want
	 * to convert back to `invertCode` (so `invertCode` is our guess about the actual language of
	 * `s`).
	 * If we were to make this encoding, the returned value `unsafe` is the number of codepoints
	 * we'd have to specially-escape, `safe` is the number of codepoints we wouldn't have to
	 * escape, and `len` is the total number of codepoints in `s`.  Generally lower values of
	 * `nonsafe` indicate a better guess for `invertCode`.
	 * @param string $s
	 * @param string $destCode
	 * @param string $invertCode
	 * @return array Statistics about the given guess, containing the keys 'safe', 'unsafe', and
	 * 'length' (should be `safe + unsafe`.)
	 */
	public function countBrackets( $s, $destCode, $invertCode ) {
		Assert::precondition( $this->validCodePair( $destCode, $invertCode ),
			"Invalid code pair: $destCode/$invertCode" );
		$m = $this->machines[$destCode]['bracket'][$invertCode];
		// call array_values on the result of unpack() to transform from a 1- to 0-indexed array
		$bytes = array_values( unpack( 'C*', $s ) );
		$brackets = $m( $bytes, 0, count( $bytes ), true );
		$safe = 0;
		$unsafe = 0;
		for ( $i = 1; $i < count( $brackets ); $i++ ) {
			$safe += ( $brackets[$i] - $brackets[$i - 1] );
			if ( ++$i < count( $brackets ) ) {
				$unsafe += ( $brackets[$i] - $brackets[$i - 1] );
			}
		}
		// Note that this is counting codepoints, not UTF-8 code units.
		return [
			'safe' => $safe,
			'unsafe' => $unsafe,
			'length' => $brackets[count( $brackets ) - 1]
		];
	}

	/**
	 * Replace the given text Node with converted text, protecting any markup which can't be
	 * round-tripped back to `invertCode` with appropriate synthetic language-converter markup.
	 * @param DOMNode $textNode
	 * @param string $destCode
	 * @param string $invertCode
	 * @return DOMNode
	 */
	public function replace( $textNode, $destCode, $invertCode ) {
		$fragment = $this->convert(
			$textNode->ownerDocument,
			$textNode->textContent,
			$destCode,
			$invertCode
		);
		// Was a change made?
		$next = $textNode->nextSibling;
		if (
			// `fragment` has exactly 1 child.
			$fragment->firstChild && !$fragment->firstChild->nextSibling &&
			// `fragment.firstChild` is a DOM text node
			$fragment->firstChild->nodeType === 3 &&
			// `textNode` is a DOM text node
			$textNode->nodeType === 3 &&
			$textNode->textContent === $fragment->textContent
		) {
			return $next; // No change.
		}
		$textNode->parentNode->replaceChild( $fragment, $textNode );
		return $next;
	}

	/**
	 * Convert a string of text.
	 * @param DOMDocument $document
	 * @param string $s text to convert
	 * @param string $destCode destination language code
	 * @param string $invertCode
	 * @return DOMDocumentFragment DocumentFragment containing converted text
	 * @suppress PhanTypeInvalidCallableArraySize
	 */
	public function convert( $document, $s, $destCode, $invertCode ) {
		$machine = $this->machines[$destCode];
		$convertM = $machine['convert'];
		$bracketM = $machine['bracket'][$invertCode];
		$result = $document->createDocumentFragment();

		// call array_values on the result of unpack() to transform from a 1- to 0-indexed array
		$bytes = array_values( unpack( 'C*', $s ) );
		$brackets = $bracketM( $bytes );

		for ( $i = 1, $len = count( $brackets ); $i < $len; $i++ ) {
			// A safe string
			$safe = $convertM( $bytes, $brackets[$i - 1], $brackets[$i] );
			if ( strlen( $safe ) > 0 ) {
				$result->appendChild( $document->createTextNode( $safe ) );
			}
			if ( ++$i < count( $brackets ) ) {
				// An unsafe string
				$orig = call_user_func_array(
					'pack',
					array_merge( [ "C*" ], array_slice( $bytes, $brackets[$i - 1], $brackets[$i] ) )
				);
				$unsafe = $convertM( $bytes, $brackets[$i - 1], $brackets[$i] );
				$span = $document->createElement( 'span' );
				$span->textContent = $unsafe;
				$span->setAttribute( 'typeof', 'mw:LanguageVariant' );
				// If this is an anomalous piece of text in a paragraph otherwise written in
				// destCode, then it's possible invertCode === destCode. In this case try to pick a
				// more appropriate invertCode !== destCode.
				$ic = $invertCode;
				if ( $ic === $destCode ) {
					$cs = array_filter( $this->codes, function ( $code ) use ( $destCode ) {
						return $code !== $destCode;
					} );
					$cs = array_map( function ( $code ) use ( $orig ) {
						return [
							'code' => $code,
							'stats' => $this->countBrackets( $orig, $code, $code ),
						];
					}, $cs );
					uasort( $cs, function ( $a, $b ) {
						return $a['stats']['unsafe'] - $b['stats']['unsafe'];
					} );
					if ( count( $cs ) === 0 ) {
						$ic = '-';
					} else {
						$ic = $cs[0]['code'];
						$span->setAttribute( 'data-mw-variant-lang', $ic );
					}
				}
				$span->setAttribute( 'data-mw-variant', json_encode( [
					'twoway' => [
						[ 'l' => $ic, 't' => $orig ],
						[ 'l' => $destCode, 't' => $unsafe ],
					],
					'rt' => true /* Synthetic markup used for round-tripping */
				] ) );
				if ( strlen( $unsafe ) > 0 ) {
					$result->appendChild( $span );
				}
			}
		}
		return $result;
	}

}
