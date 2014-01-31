<?php
namespace DreamFactory\Platform\Utility;

/**
 * ResourceStoreTest
 */
class ResourceStoreTest extends \PHPUnit_Framework_TestCase
{
	protected function setUp()
	{
		parent::setUp();

		ResourceStore::reset( array( 'resource_name' => 'provider' ) );
	}

	public function testGetProvider()
	{
//		$_model = ResourceStore::model( 'provider' );
//		$this->assertNotEmpty( $_model );
	}

	public function testGetUser()
	{
//		$_model = ResourceStore::model( 'user' );
//		$this->assertNotEmpty( $_model );
	}

	protected function tearDown()
	{
		parent::tearDown();
	}
}
