<?php

namespace Wikimedia\LangConv;

/* Partial port of ReplacementMachine.js for testing. */
class ReplacementMachine {

	private $baseLanguage;
	private $codes;
	private $machines = [];

	/**
	 * ReplacementMachine constructor.
	 * @param string $baseLanguage
	 * @param array $codes
	 */
	public function __construct( $baseLanguage, $codes ) {
		$this->baseLanguage = $baseLanguage;
		$this->codes = $codes;
		foreach ( $codes as $code ) {
			$bracketMachines = [];
			foreach ( $codes as $code2 ) {
				if ( !$this->isValidLanguagePair( $code, $code2 ) ) {
					continue;
				}
				$dstCode = $code === $code2 ? "noop" : $code2;
				$bracketMachines[$code2] = $this->loadFST( "brack-$code-$dstCode", true );
			}
			$this->machines[$code] = [
				"convert" => $this->loadFST( "trans-$code" ),
				"bracket" => $bracketMachines,
			];
		}
	}

	/**
	 * Load a conversion machine from a pFST file with filename $filename from the fst directory.
	 * @param string $filename filename, omitting the .pfst file extension
	 * @param bool $justBrackets whether to return only the bracket locations
	 * @return callable
	 */
	public function loadFST( $filename, $justBrackets = false ) {
		return FST::compile( __dir__ . "/../lib/fst/$filename.pfst", $justBrackets );
	}

	/**
	 * Convert a string of text.
	 * @param string $str text to convert
	 * @param string $destCode destination language code
	 * @param string $invertCode
	 * @return string converted text
	 * @suppress PhanTypeInvalidCallableArraySize
	 */
	public function convert( $str, $destCode, $invertCode ) {
		$machine = $this->machines[$destCode];
		$convertM = $machine["convert"];
		$bracketM = $machine["bracket"][$invertCode];

		// call array_values on the result of unpack() to transform from a 1- to 0-indexed array
		$bytes = array_values( unpack( 'C*', $str ) );

		$brackets = $bracketM( $bytes );
		$result = "";
		for ( $i = 1, $len = count( $brackets ); $i < $len; $i++ ) {
			// A safe string
			$safe = $convertM( $bytes, $brackets[$i - 1], $brackets[$i] );
			if ( strlen( $safe ) > 0 ) {
				$result .= $safe;
			}
			if ( ++$i < count( $brackets ) ) {
				// An unsafe string
				// $orig = array_slice( $bytes, $brackets[$i-1], $brackets[$i] );
				$unsafe = $convertM( $bytes, $brackets[$i - 1], $brackets[$i] );
				// DOM markup stuff omitted
				if ( strlen( $unsafe ) > 0 ) {
					$result .= $unsafe;
				}
			}
		}
		return $result;
	}

	// TODO: Consolidate Language classes into this repo?
	private function isValidLanguagePair( $destCode, $invertCode ) {
		// For now this only seems critical for zh
		if ( substr( $destCode, 0, 2 ) !== "zh" ) {
			return true;
		}
		switch ( $destCode ) {
			case "zh-cn":
				if ( $invertCode === "zh-tw" ) {
					return true;
				}
				// else, fall through
			case "zh-sg":
			case "zh-my":
			case "zh-hans":
				return $invertCode === "zh-hant";
			case "zh-tw":
				if ( $invertCode === "zh-cn" ) {
					return true;
				}
				// else, fall through
			case "zh-hk":
			case "zh-mo":
			case "zh-hant":
				return $invertCode === "zh-hans";
			default:
				return false;
		}
	}

}
