<?php
/**
 * Dumps the conversion tables from ZhConversion.php
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

/**
 * Dumps the conversion exceptions table from ZhConversion.php
 *
 * @ingroup MaintenanceLanguage
 */
class DumpZh extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Dump zh exceptions' );
	}

	public function getDbType() {
		return Maintenance::DB_NONE;
	}

	public function emitFomaRepl( $name, $mapArray, $unicodeSplit = false ) {
		$first = true;
		echo( "define $name [\n" );
		foreach ( $mapArray as $from => $to ) {
			if ( !$first ) { echo( " |\n" );
   }
			if ( $unicodeSplit ) {
				echo( "  [ " . $this->utf8split( $from, true ) . " ] : [ " . $this->utf8split( $to, true ) . " ]" );
			} else {
				echo( "  {" . $from . "} : {" . $to . "}" );
			}
			$first = false;
		}
		echo( "\n];\n" );
	}

	public function emitFomaRepl2( $name, $mapArray ) {
		$first = true;
		foreach ( $mapArray as $from => $to ) {
			Assert::invariant( strpos( $from, ' ' ) === false, "Space in from: $from" );
			Assert::invariant( strpos( $to, ' ' ) === false, "Space in to: $to" );
			echo( "$from\n" );
			echo( "$to\n" );
		}
	}

	function utf8split( $str, $quote = false ) {
		$a = str_split( $str, 1 );
		$r = [];
		for ( $i = 0; $i < count( $a ); $i++ ) {
			$c = sprintf( "%2X", ord( $a[$i] ) );
			if ( $quote ) { $c = '"' . $c . '"';
   }
			$r[] = $c;
		}
		return implode( ' ', $r );
	}

	public function emitFomaRepl3( $name, $mapArray ) {
		$first = true;
		foreach ( $mapArray as $from => $to ) {
			Assert::invariant( strpos( $from, ' ' ) === false, "Space in from: $from" );
			Assert::invariant( strpos( $to, ' ' ) === false, "Space in to: $to" );
			$from = $this->utf8split( $from );
			$to = $this->utf8split( $to );
			echo( "$from\n" );
			echo( "$to\n" );
		}
	}

	public function execute() {
		$zh = Language::factory( 'zh' );
		$converter = $zh->getConverter();
		# autoConvert will trigger the tables to be loaded
		$converter->autoConvertToAllVariants( "xyz" );
		# XXX WE ALSO LOAD ADDITIONAL CONVERSIONS FROM WIKI PAGES!
		foreach ( $converter->mTables as $var => $table ) {
			if ( !preg_match( '/^zh/', $var ) ) { continue;
   }
			if ( count( $table->getArray() ) === 0 ) { continue;
   }
			$name = "TABLE'" . preg_replace( '/-/', "'", strtoupper( $var ) );
			#if ($var !== 'zh-cn') continue;
			$this->emitFomaRepl( $name, $table->getArray(), true );
		}
	}
}

$maintClass = DumpZh::class;
require_once RUN_MAINTENANCE_IF_MAIN;
