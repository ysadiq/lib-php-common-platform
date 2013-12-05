<?php
namespace DreamFactory\Tests\Utility;

use DreamFactory\Platform\Utility\ResourceStore;
use Kisma\Core\Utility\Log;

/**
 * ResourceStoreTest
 */
class ResourceStoreTest extends \PHPUnit_Framework_TestCase
{
	protected function setUp()
	{
		Log::setDefaultLog( __DIR__ . '/../log/error.log' );

		ResourceStore::reset( array('resource_name' => 'provider') );

		parent::setUp();
	}

	public function testGetProvider()
	{
		$_model = ResourceStore::model( 'provider' );

		$this->assertNotEmpty( $_model );
	}

	public function testGetUser()
	{
		$_model = ResourceStore::model( 'user' );

		$this->assertNotEmpty( $_model );
	}

	protected function tearDown()
	{
		parent::tearDown();
	}
}
