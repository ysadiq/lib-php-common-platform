<?php
namespace DreamFactory\Tests\Services;

use DreamFactory\Platform\Services\SystemManager;
use Kisma\Core\Utility\Log;

/**
 * SystemManagerTest
 */
class SystemManagerTest extends \PHPUnit_Framework_TestCase
{
	protected function setUp()
	{
		Log::setDefaultLog( __DIR__ . '/../log/error.log' );

		parent::setUp();
	}

	public function testSystemManager()
	{
		$_model = new SystemManager();

		$this->assertNotEmpty( $_model );
	}

	protected function tearDown()
	{
		parent::tearDown();
	}
}
