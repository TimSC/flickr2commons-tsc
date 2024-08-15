<?PHP

# Requires PHP7
# Run with ./vendor/bin/phpunit --bootstrap vendor/autoload.php itemdiffTest.php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use PHPUnit\Framework\TestCase;

require_once ( __DIR__ . '/../public_html/php/itemdiff.php' ) ;

/**
 * @covers wikidata
 */
final class wikidataTest extends TestCase {

	public function testCanCreate() :void {
		$i1 = new BlankWikidataItem ;
		foreach ( $i1->object_list AS $k ) {
			$this->assertEquals ( $i1->j->$k , (object) [] ) ;
		}
	}

	public function testCanDiffLabel() :void {
		$i1 = new BlankWikidataItem ;
		$i2 = new BlankWikidataItem ;
		$i1->addLabel ( 'en' , 'test' ) ;
		$diff = $i2->diffToItem ( $i1 ) ;
		$this->assertEquals ( json_encode($diff) , '{"labels":{"en":{"language":"en","value":"test","remove":""}}}' , 'remove label' ) ;

		$i2->addLabel ( 'en' , 'test new' ) ;
		$diff = $i2->diffToItem ( $i1 ) ;
		$this->assertEquals ( json_encode($diff) , '{"labels":{"en":{"language":"en","value":"test new"}}}' , 'change label' ) ;

		$i1->j->labels = [] ;
		$diff = $i2->diffToItem ( $i1 ) ;
		$this->assertEquals ( json_encode($diff) , '{"labels":{"en":{"language":"en","value":"test new"}}}' , 'add label' ) ;
	}

	public function testCanDiffDescription() :void {
		$i1 = new BlankWikidataItem ;
		$i2 = new BlankWikidataItem ;
		$i1->addDescription ( 'en' , 'test' ) ;
		$diff = $i2->diffToItem ( $i1 ) ;
		$this->assertEquals ( json_encode($diff) , '{"descriptions":{"en":{"language":"en","value":"test","remove":""}}}' , 'remove description' ) ;

		$i2->addDescription ( 'en' , 'test new' ) ;
		$diff = $i2->diffToItem ( $i1 ) ;
		$this->assertEquals ( json_encode($diff) , '{"descriptions":{"en":{"language":"en","value":"test new"}}}' , 'change description' ) ;

		$i1->j->descriptions = [] ;
		$diff = $i2->diffToItem ( $i1 ) ;
		$this->assertEquals ( json_encode($diff) , '{"descriptions":{"en":{"language":"en","value":"test new"}}}' , 'add description' ) ;
	}

	public function testCanCreateNewClaims() :void {
		$i1 = new BlankWikidataItem ;

		# Items
		$this->assertEquals ( json_encode($i1->newItem('Q12345')) , '{"value":{"entity-type":"item","numeric-id":"12345","id":"Q12345"},"type":"wikibase-entityid"}' , 'new item claim' ) ;
		$this->assertEquals ( json_encode($i1->newItem('P12345')) , '{"value":{"entity-type":"property","numeric-id":"12345","id":"P12345"},"type":"wikibase-entityid"}' , 'new property claim' ) ;

		# Time
		$j = '{"value":{"time":"+1984-08-09T00:00:00Z","timezone":0,"before":0,"after":0,"precision":11,"calendarmodel":"http:\/\/www.wikidata.org\/entity\/Q1985727"},"type":"time"}' ;
		$this->assertEquals ( json_encode($i1->newTime('1984-08-09')) , $j , 'new time claim 1' ) ;

		$j = '{"value":{"time":"+1984-08-01T00:00:00Z","timezone":0,"before":0,"after":0,"precision":10,"calendarmodel":"http:\/\/www.wikidata.org\/entity\/Q1985727"},"type":"time"}' ;
		$this->assertEquals ( json_encode($i1->newTime('1984-08')) , $j , 'new time claim 2' ) ;

		$j = '{"value":{"time":"+1984-01-01T00:00:00Z","timezone":0,"before":0,"after":0,"precision":9,"calendarmodel":"http:\/\/www.wikidata.org\/entity\/Q1985727"},"type":"time"}' ;
		$this->assertEquals ( json_encode($i1->newTime('1984')) , $j , 'new time claim 3' ) ;

		$j = '{"value":{"time":"+1984-08-09T00:00:00Z","timezone":0,"before":0,"after":0,"precision":11,"calendarmodel":"http:\/\/www.wikidata.org\/entity\/Q1985786"},"type":"time"}' ;
		$this->assertEquals ( json_encode($i1->newTime('1984-08-09',-1,'Q1985786')) , $j , 'new time claim 4' ) ;

		# Coord
		$j = '{"value":{"latitude":0.123,"longitude":-0.456,"altitude":null,"precision":0.0002,"globe":"http:\/\/www.wikidata.org\/entity\/Q2"},"type":"globecoordinate"}' ;
		$this->assertEquals ( json_encode($i1->newCoord(0.123,-0.456)) , $j , 'new coord claim 1' ) ;

		$j = '{"value":{"latitude":0.123,"longitude":-0.456,"altitude":null,"precision":0.0002,"globe":"http:\/\/www.wikidata.org\/entity\/Q111"},"type":"globecoordinate"}' ;
		$this->assertEquals ( json_encode($i1->newCoord(0.123,-0.456,'Q111')) , $j , 'new coord claim 2' ) ;

		# TODO precision, altitude

		# Quantity
		$this->assertEquals ( json_encode($i1->newQuantity(123)) , '{"value":{"amount":"+123","unit":"1"},"type":"quantity"}' , 'new quantity claim 1' ) ;
		$this->assertEquals ( json_encode($i1->newQuantity(123,'Q174789')) , '{"value":{"amount":"+123","unit":"http:\/\/www.wikidata.org\/entity\/Q174789"},"type":"quantity"}' , 'new quantity claim 2' ) ;

		# String
		$this->assertEquals ( json_encode($i1->newString("foobar")) , '{"value":"foobar","type":"string"}' , 'new string claim' ) ;
	}

/*
		$this->assertTrue ( isset($i) ) ;
*/	
}


?>