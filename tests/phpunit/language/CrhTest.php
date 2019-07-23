<?php

namespace Wikimedia\LangConv\Tests\Language;

use PHPUnit\Framework\TestCase;
use Wikimedia\LangConv\ReplacementMachine;

class CrhTest extends TestCase {

	const CODES = [ "crh-cyrl", "crh-latn" ];

	/** @var ReplacementMachine */
	private $machine;

	protected function setUp() {
		$this->machine = new ReplacementMachine( "crh", self::CODES );
	}

	protected function tearDown() {
		$this->machine = null;
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testGeneralWords() {
		$input = "рузгярнынъ ruzgârnıñ Париж Parij";
		$output = [
			'crh' => 'рузгярнынъ ruzgârnıñ Париж Parij',
			'crh-cyrl' => 'рузгярнынъ рузгярнынъ Париж Париж',
			'crh-latn' => 'ruzgârnıñ ruzgârnıñ Parij Parij',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testMoreGeneralWords() {
		$input = "чёкюч çöküç элифбени elifbeni полициясы politsiyası";
		$output = [
			'crh' => 'чёкюч çöküç элифбени elifbeni полициясы politsiyası',
			'crh-cyrl' => 'чёкюч чёкюч элифбени элифбени полициясы полициясы',
			'crh-latn' => 'çöküç çöküç elifbeni elifbeni politsiyası politsiyası',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testYetMoreGeneralWords() {
		$input = "хусусында hususında акъшамларны aqşamlarnı опькеленюв öpkelenüv";
		$output = [
			'crh' => 'хусусында hususında акъшамларны aqşamlarnı опькеленюв öpkelenüv',
			'crh-cyrl' => 'хусусында хусусында акъшамларны акъшамларны опькеленюв опькеленюв',
			'crh-latn' => 'hususında hususında aqşamlarnı aqşamlarnı öpkelenüv öpkelenüv',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testMoreGeneralWordsStill() {
		$input = "кулюмсиреди külümsiredi айтмайджагъым aytmaycağım козьяшсыз közyaşsız";
		$output = [
			'crh' => 'кулюмсиреди külümsiredi айтмайджагъым aytmaycağım козьяшсыз közyaşsız',
			'crh-cyrl' => 'кулюмсиреди кулюмсиреди айтмайджагъым айтмайджагъым козьяшсыз козьяшсыз',
			'crh-latn' => 'külümsiredi külümsiredi aytmaycağım aytmaycağım közyaşsız közyaşsız',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testExceptionWords() {
		$input = "инструменталь instrumental гургуль gürgül тюшюнмемек tüşünmemek";
		$output = [
			'crh' => 'инструменталь instrumental гургуль gürgül тюшюнмемек tüşünmemek',
			'crh-cyrl' => 'инструменталь инструменталь гургуль гургуль тюшюнмемек тюшюнмемек',
			'crh-latn' => 'instrumental instrumental gürgül gürgül tüşünmemek tüşünmemek',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testRecentProblemWords1() {
		$input = "künü куню sürgünligi сюргюнлиги özü озю etti этти esas эсас dört дёрт";
		$output = [
			'crh' => 'künü куню sürgünligi сюргюнлиги özü озю etti этти esas эсас dört дёрт',
			'crh-cyrl' => 'куню куню сюргюнлиги сюргюнлиги озю озю этти этти эсас эсас дёрт дёрт',
			'crh-latn' => 'künü künü sürgünligi sürgünligi özü özü etti etti esas esas dört dört',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testRecentProblemWords2() {
		$input = "keldi кельди km² км² yüz юзь AQŞ АКъШ ŞSCBnen ШСДжБнен iyül июль";
		$output = [
			'crh' => 'keldi кельди km² км² yüz юзь AQŞ АКъШ ŞSCBnen ШСДжБнен iyül июль',
			'crh-cyrl' => 'кельди кельди км² км² юзь юзь АКъШ АКъШ ШСДжБнен ШСДжБнен июль июль',
			'crh-latn' => 'keldi keldi km² km² yüz yüz AQŞ AQŞ ŞSCBnen ŞSCBnen iyül iyül',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testRecentProblemWords3() {
		$input = "işğal ишгъаль işğalcilerine ишгъальджилерине rayon район üst усть";
		$output = [
			'crh' => 'işğal ишгъаль işğalcilerine ишгъальджилерине rayon район üst усть',
			'crh-cyrl' => 'ишгъаль ишгъаль ишгъальджилерине ишгъальджилерине район район усть усть',
			'crh-latn' => 'işğal işğal işğalcilerine işğalcilerine rayon rayon üst üst',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testRecentProblemWords4() {
		$input = "rayonınıñ районынынъ Noğay Ногъай Yürtü Юрьтю vatandan ватандан";
		$output = [
			'crh' => 'rayonınıñ районынынъ Noğay Ногъай Yürtü Юрьтю vatandan ватандан',
			'crh-cyrl' => 'районынынъ районынынъ Ногъай Ногъай Юрьтю Юрьтю ватандан ватандан',
			'crh-latn' => 'rayonınıñ rayonınıñ Noğay Noğay Yürtü Yürtü vatandan vatandan',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testRecentProblemWords5() {
		$input = "ком-кок köm-kök rol роль AQQI АКЪКЪЫ DAĞĞA ДАГЪГЪА 13-ünci 13-юнджи";
		$output = [
			'crh' => 'ком-кок köm-kök rol роль AQQI АКЪКЪЫ DAĞĞA ДАГЪГЪА 13-ünci 13-юнджи',
			'crh-cyrl' => 'ком-кок ком-кок роль роль АКЪКЪЫ АКЪКЪЫ ДАГЪГЪА ДАГЪГЪА 13-юнджи 13-юнджи',
			'crh-latn' => 'köm-kök köm-kök rol rol AQQI AQQI DAĞĞA DAĞĞA 13-ünci 13-ünci',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testRecentProblemWords6() {
		$input = "ДЖУРЬМЕК CÜRMEK кетсин ketsin джумлеси cümlesi ильи ilyi Ильи İlyi";
		$output = [
			'crh' => 'ДЖУРЬМЕК CÜRMEK кетсин ketsin джумлеси cümlesi ильи ilyi Ильи İlyi',
			'crh-cyrl' => 'ДЖУРЬМЕК ДЖУРЬМЕК кетсин кетсин джумлеси джумлеси ильи ильи Ильи Ильи',
			'crh-latn' => 'CÜRMEK CÜRMEK ketsin ketsin cümlesi cümlesi ilyi ilyi İlyi İlyi',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testRecentProblemWords7() {
		$input = "бруцел brutsel коцюб kotsüb плацен platsen эпицентр epitsentr";
		$output = [
			'crh' => 'бруцел brutsel коцюб kotsüb плацен platsen эпицентр epitsentr',
			'crh-cyrl' => 'бруцел бруцел коцюб коцюб плацен плацен эпицентр эпицентр',
			'crh-latn' => 'brutsel brutsel kotsüb kotsüb platsen platsen epitsentr epitsentr',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testRegexPatternWords() {
		$input = "köyünden коюнден ange аньге";
		$output = [
			'crh' => 'köyünden коюнден ange аньге',
			'crh-cyrl' => 'коюнден коюнден аньге аньге',
			'crh-latn' => 'köyünden köyünden ange ange',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testMultiPartWords() {
		$input = "эки юз eki yüz";
		$output = [
			'crh' => 'эки юз eki yüz',
			'crh-cyrl' => 'эки юз эки юз',
			'crh-latn' => 'eki yüz eki yüz',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testAffixPatterns() {
		$input = "köyniñ койнинъ Avcıköyde Авджыкойде ekvatorial экваториаль Canköy Джанкой";
		$output = [
			'crh' => 'köyniñ койнинъ Avcıköyde Авджыкойде ekvatorial экваториаль Canköy Джанкой',
			'crh-cyrl' => 'койнинъ койнинъ Авджыкойде Авджыкойде экваториаль экваториаль Джанкой Джанкой',
			'crh-latn' => 'köyniñ köyniñ Avcıköyde Avcıköyde ekvatorial ekvatorial Canköy Canköy',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testRomanNumeralsAndQuotes() {
		$input = 'VI,VII IX “dört” «дёрт» XI XII I V X L C D M';
		$output = [
			'crh' => 'VI,VII IX “dört” «дёрт» XI XII I V X L C D M',
			'crh-cyrl' => 'VI,VII IX «дёрт» «дёрт» XI XII I V X L C D M',
			'crh-latn' => 'VI,VII IX “dört” "dört" XI XII I V X L C D M',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testRomanNumeralsInitialsWithoutSpaces() {
		$input = "A.B.C.D.M. Qadırova XII, А.Б.Дж.Д.М. Къадырова XII";
		$output = [
			'crh' => 'A.B.C.D.M. Qadırova XII, А.Б.Дж.Д.М. Къадырова XII',
			'crh-cyrl' => 'А.Б.Дж.Д.М. Къадырова XII, А.Б.Дж.Д.М. Къадырова XII',
			'crh-latn' => 'A.B.C.D.M. Qadırova XII, A.B.C.D.M. Qadırova XII',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testRomanNumeralsInitialsWithSpaces() {
		$input = "G. H. I. V. X. L. Memetov III, Г. Х. Ы. В. X. Л. Меметов III";
		$output = [
			'crh' => 'G. H. I. V. X. L. Memetov III, Г. Х. Ы. В. X. Л. Меметов III',
			'crh-cyrl' => 'Г. Х. Ы. В. X. Л. Меметов III, Г. Х. Ы. В. X. Л. Меметов III',
			'crh-latn' => 'G. H. I. V. X. L. Memetov III, G. H. I. V. X. L. Memetov III',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testAllCaps() {
		$input = "ÑAB QIC ĞUK COT НЪАБ КЪЫДЖ ГЪУК ДЖОТ CA ДЖА";
		$output = [
			'crh' => 'ÑAB QIC ĞUK COT НЪАБ КЪЫДЖ ГЪУК ДЖОТ CA ДЖА',
			'crh-cyrl' => 'НЪАБ КЪЫДЖ ГЪУК ДЖОТ НЪАБ КЪЫДЖ ГЪУК ДЖОТ ДЖА ДЖА',
			'crh-latn' => 'ÑAB QIC ĞUK COT ÑAB QIC ĞUK COT CA CA',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testManyCyrillicToOneLatin() {
		$input = "шофер шофёр şoför корбекул корьбекул корьбекуль körbekül";
		$output = [
			'crh' => 'шофер шофёр şoför корбекул корьбекул корьбекуль körbekül',
			'crh-cyrl' => 'шофер шофёр шофёр корбекул корьбекул корьбекуль корьбекуль',
			'crh-latn' => 'şoför şoför şoför körbekül körbekül körbekül körbekül',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	/**
	 * @covers \Wikimedia\LangConv\FST
	 */
	public function testManyLatinOneCyrillic() {
		$input = "fevqülade fevqulade февкъульаде beyude beyüde бейуде";
		$output = [
			'crh' => 'fevqülade fevqulade февкъульаде beyude beyüde бейуде',
			'crh-cyrl' => 'февкъульаде февкъульаде февкъульаде бейуде бейуде бейуде',
			'crh-latn' => 'fevqülade fevqulade fevqulade beyude beyüde beyüde',
		];
		foreach ( self::CODES as $variantCode ) {
			$expected = $output[$variantCode];
			$result = $this->convert( $input, $variantCode );
			$this->assertEquals( $expected, $result );
		}
	}

	private function convert( $input, $variantCode ) {
		return $this->machine->convert( $input, $variantCode, $this->getInvertCode( $variantCode ) );
	}

	private function getInvertCode( $variantCode ) {
		return $variantCode === "crh-cyrl" ? "crh-latn" : "crh-cyrl";
	}

}
