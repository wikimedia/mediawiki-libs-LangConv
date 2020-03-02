<?php
/**
 * Dumps the conversion tables from CrhExceptions.php
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup MaintenanceLanguage
 */

require_once __DIR__ . '/../Maintenance.php';

use Wikimedia\Assert\Assert;

class BadRegexException extends Exception {
}
class BadEscapeException extends BadRegexException {
}

/**
 * Dumps the conversion exceptions table from CrhExceptions.php
 *
 * @ingroup MaintenanceLanguage
 */
class DumpCrh extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Dump crh exceptions' );
	}

	public function getDbType() {
		return Maintenance::DB_NONE;
	}

	function peek( $str ) {
		if ( $str == '' ) { return '';
  }
		return mb_substr( $str, 0, 1 );
	}

	function eat( &$str, $c = null ) {
		Assert::invariant( $c === null || $this->peek( $str ) === $c, "Ate something unexpected." );
		$str = mb_substr( $str, 1 );
		return $this->peek( $str );
	}

	function translateRegexClass( &$str ) {
		$peek = $this->peek( $str );
		$not = false;
		$result = '';
		if ( $peek === '^' ) {
			$not = true;
			$peek = $this->eat( $str, '^' );
		}
		while ( $peek !== ']' ) {
			$first = mb_ord( $this->translateRegexChar( $str, false ) );
			$peek = $this->peek( $str );
			if ( $peek !== '-' ) {
				$last = $first;
			} else {
				$this->eat( $str, '-' );
				$last = mb_ord( $this->translateRegexChar( $str, false ) );
				$peek = $this->peek( $str );
			}
			for ( $i = $first; $i <= $last; $i++ ) {
				if ( $result !== '' ) { $result .= "|";
	   }
				$result .= "{" . mb_chr( $i ) . "}";
			}
		}
		if ( $not ) { return "\\[$result]";
  }
		return $result;
	}

	function translateRegexChar( &$str, $escape = true ) {
		$peek = $this->peek( $str );
		if ( $peek == '\\' ) {
			$c = $this->eat( $str, '\\' );
			$this->eat( $str, $c );
			# XXX check that $c is a reasonable escape sequence, not
			# something special like \w \b etc.
			if ( $c === '.' ) {
				return $escape ? '{.}' : $c;
			} elseif ( $c === 'b' && $escape ) {
				return '[br|.#.]';
			} else {
				throw new BadEscapeException( $c );
			}
		} else {
			$this->eat( $str );
			return $escape ? ( '{' . $peek . '}' ) : $peek;
		}
	}

	function translateRegexBase( &$str ) {
		$peek = $this->peek( $str );
		if ( $peek == '(' ) {
			$this->eat( $str, '(' );
			$r = $this->translateRegex( $str );
			$this->eat( $str, ')' );
			return "[ $r ]";
		} elseif ( $peek == '[' ) {
			$this->eat( $str, '[' );
			$r = $this->translateRegexClass( $str );
			$this->eat( $str, ']' );
			return "[$r]";
		} else {
			# XXX figure out if this needs to be escaped further
			return $this->translateRegexChar( $str, true );
		}
	}

	function translateRegexFactor( &$str ) {
		$base = $this->translateRegexBase( $str );
		$peek = $this->peek( $str );
		while ( $peek == '*' || $peek == '+' || $peek == '?' ) {
			$this->eat( $str, $peek );
			if ( $peek == '?' ) {
				$base = "(" . $base . ")";
			} else {
				$base .= $peek;
			}
			$peek = $this->peek( $str );
		}
		return $base;
	}

	function translateRegexTerm( &$str, $noParens = false ) {
		$factor = '';
		$peek = $this->peek( $str );
		while ( $peek != '' && $peek != ')' && $peek != '|' ) {
			if ( $noParens && $peek === '(' ) { break;
   }
			$nextFactor = $this->translateRegexFactor( $str );
			if ( $factor != '' ) { $factor .= " ";
   }
			$factor .= $nextFactor;
			$peek = $this->peek( $str );
		}
		return $factor;
	}

	function translateRegex( &$str, $noParens = false ) {
		$term = $this->translateRegexTerm( $str, $noParens );
		if ( $this->peek( $str ) == '|' ) {
			$this->eat( $str, '|' );
			$term2 = $this->translateRegex( $str, $noParens );
			return $term . " | " . $term2;
		}
		return $term;
	}

	function translate( $s ) {
		return $this->translateRegex( $s );
	}

	function translateSplit( $str ) {
		$r = [ '' ];
		$c = $this->peek( $str );
		while ( $c !== '' ) {
			if ( $c === '(' ) {
				$r[] = $this->translateRegexBase( $str );
				$r[] = '';
			} else {
				if ( $r[count( $r ) - 1] != '' ) { $r[count( $r ) - 1] .= " ";
	   }
				$r[count( $r ) - 1] .= $this->translateRegex( $str, true );
			}
			$c = $this->peek( $str );
		}
		return $r;
	}

	function dollarSplit( $str ) {
		$i = mb_ord( '1' );
		$r = [ '' ];
		$c = $this->peek( $str );
		while ( $c !== '' ) {
			if ( $c === '$' ) {
				$c = $this->eat( $str, '$' );
				Assert::invariant( $c === mb_chr( $i ), "replacements out of order" );
				$i++;
				$r[] = '_';
				$r[] = '';
			} else {
				$r[count( $r ) - 1] .= $c;
			}
			$c = $this->eat( $str );
		}
		return $r;
	}

	public function emitFomaRepl( $name, $mapArray ) {
		$first = true;
		echo( "define $name [\n" );
		foreach ( $mapArray as $from => $to ) {
			if ( !$first ) { echo( " ,,\n" );
   }
			echo( "  {" . $from . "} @-> {" . $to . "}" );
			$first = false;
		}
		echo( "\n];\n" );
	}

	public function emitFomaRegex( $name, $patArray ) {
		# break up large arrays to avoid segfaults in foma
		if ( count( $patArray ) > 100 ) {
			$r = [];
			foreach ( array_chunk( $patArray, 100, true ) as $chunk ) {
				$n = $name . "'" . ( count( $r ) + 1 );
				$r[] = "$n(br)";
				$this->emitFomaRegex( $n, $chunk );
			}
			echo( "define $name(br) " . implode( ' .o. ', $r ) . ";\n" );
			return;
		}
		$first = true;
		echo( "define $name(br) [\n" );
		foreach ( $patArray as $from => $to ) {
			$from = preg_replace( '/^\/|\/u$/', '', $from, 2, $cnt );
			Assert::invariant( $cnt === 2, "missing regex delimiters: $from $cnt" );
			$from = preg_replace( '/^\\\\b/', '', $from, 1, $startAnchor );
			$from = preg_replace( '/\\\\b$/', '', $from, 1, $endAnchor );
			if ( $first ) {
				$first = false;
			} else {
				echo( " .o.\n" );
			}
			if ( preg_match( '/[$]\d/', $to ) ) {
				try {
					$from = $this->translateSplit( $from );
				} catch ( BadRegexException $ex ) {
					echo( "# SKIPPING $from -> $to\n" );
					$first = true;
					continue;
				}
				$to = $this->dollarSplit( $to );
				# Convert identical parts to context
				for ( $i = 0; $i < count( $from ); $i += 2 ) {
					if ( $from[$i] == ( '{' . $to[$i] . '}' ) ) {
						array_splice( $from, $i + 1, 0, [ '' ] );
						array_splice( $from, $i, 0, [ '' ] );
						array_splice( $to, $i, 1, [ '', '_', '' ] );
					}
				}
				for ( $i = 0; $i < count( $from ); $i += 2 ) {
					if ( $to[$i] !== '' || $from[$i] !== '' ) { break;
		   }
				}
				Assert::invariant(
					$i < count( $from ) && $i < count( $to ),
					"Can't find replace string"
				);
				$f = $from[$i] ?: '[..]';
				$t = $to[$i] ? ( '{' . $to[$i] . '}' ) : '0';
				echo( "  [ $f -> $t ||" );
				if ( $startAnchor ) {
					echo( " [.#.|br]" );
				}
				for ( $j = 0; $j < count( $from ); $j += 2 ) {
					if ( $j === $i ) {
						echo( ' _' );
					} else {
						Assert::invariant( $from[$j] === '', "Bad from part" );
						Assert::invariant( $to[$j] === '', "Bad to part" );
					}
					if ( $j + 1 < count( $from ) ) {
						echo( ' ' );
						echo( $from[$j + 1] );
					}
				}
				if ( $endAnchor ) {
					echo( " [br|.#.]" );
				}
				echo( ' ]' );
				continue;
			}
			if ( preg_match( '/[\\[\\(\\*\\+\\?\\\\]/u', $from ) ) {
				try {
					$r = $this->translate( $from );
				} catch ( BadRegexException $ex ) {
					echo( "# SKIPPING $from -> $to\n" );
					$first = true;
					continue;
				}
				echo( '  [ [' . $r . '] @-> {' . $to . '}' );
			} else {
				echo( '  [ {' . $from . '} @-> {' . $to . '}' );
			}
			if ( $startAnchor || $endAnchor ) {
				echo( ' || ' );
				if ( $startAnchor ) {
					echo( "[.#.|br] " );
				}
				echo( '_' );
				if ( $endAnchor ) {
					echo( " [br|.#.]" );
				}
			}
			echo( ' ]' );
		}
		echo( "\n];\n" );
	}

	public function execute() {
		$crh = Language::factory( 'crh' );
		$converter = $crh->getConverter();
		$converter->loadExceptions();
		$this->emitFomaRepl( "CRH'LATN'EXCEPTIONS", $converter->mCyrl2LatnExceptions );
		$this->emitFomaRepl( "CRH'CYRL'EXCEPTIONS", $converter->mLatn2CyrlExceptions );
		# regular expressions
		$this->emitFomaRegex( "CRH'LATN'PATTERNS", $converter->mCyrl2LatnPatterns );
		$this->emitFomaRegex( "CRH'CYRL'PATTERNS", $converter->mLatn2CyrlPatterns );
	}
}

$maintClass = DumpCrh::class;
require_once RUN_MAINTENANCE_IF_MAIN;
