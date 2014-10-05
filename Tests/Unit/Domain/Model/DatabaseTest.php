<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 20.09.14
 * Time: 12:47
 */

namespace Cundd\PersistentObjectStore\Domain\Model;


use Cundd\PersistentObjectStore\AbstractCase;
use Cundd\PersistentObjectStore\Utility\DebugUtility;

class DatabaseTest extends AbstractCase {
	/**
	 * @var \Cundd\PersistentObjectStore\Domain\Model\Database
	 */
	protected $fixture;

	/**
	 * @var \Cundd\PersistentObjectStore\DataAccess\Coordinator
	 */
	protected $coordinator;

	protected function setUp() {
		$this->checkPersonFile();

		$this->setUpXhprof();

		$this->coordinator = $this->getDiContainer()->get('\Cundd\PersistentObjectStore\DataAccess\Coordinator');
		$this->fixture = $this->coordinator->getDataByDatabase('people');
	}

	protected function tearDown() {
//		unset($this->fixture);
//		unset($this->coordinator);
		$this->tearDownXhprof();
	}

	/**
	 * @expectedException \Cundd\PersistentObjectStore\DataAccess\Exception\ReaderException
	 */
	public function invalidDatabaseTest() {
		$this->coordinator = $this->getDiContainer()->get('\Cundd\PersistentObjectStore\DataAccess\Coordinator');
		$this->coordinator->getDataByDatabase('congress_members');
	}

	/**
	 * @test
	 */
	public function findByIdentifierTest() {
		$person = $this->fixture->findByIdentifier('georgettebenjamin@andryx.com');
		$this->assertNotNull($person);

		$this->assertSame(31, $person->valueForKeyPath('age'));
		$this->assertSame('green', $person->valueForKeyPath('eyeColor'));
		$this->assertSame('Georgette Benjamin', $person->valueForKeyPath('name'));
		$this->assertSame('female', $person->valueForKeyPath('gender'));
	}

	/**
	 * A test that should validate the behavior of data object references in a database
	 *
	 * @test
	 */
	public function objectLiveCycleTest() {
		$database2 = $this->coordinator->getDataByDatabase('people');

		/** @var DataInterface $personFromDatabase2 */
		$personFromDatabase2 = $database2->current();

		/** @var DataInterface $personFromFixture */
		$personFromFixture = $this->fixture->current();

		$this->assertEquals($personFromDatabase2, $personFromFixture);

		$movie = 'Star Wars';
		$key = 'favorite_movie';

		$personFromDatabase2->setValueForKey($movie, $key);

		$this->assertEquals($personFromDatabase2, $personFromFixture);
		$this->assertSame($personFromDatabase2, $personFromFixture);
		$this->assertEquals($movie, $personFromFixture->valueForKey($key));
		$this->assertEquals($movie, $personFromDatabase2->valueForKey($key));
	}


}
 