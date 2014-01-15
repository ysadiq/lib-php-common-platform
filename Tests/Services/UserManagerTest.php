<?php
namespace DreamFactory\Tests\Services;

use DreamFactory\Platform\Services\UserManager;
use Kisma\Core\Utility\Log;

/**
 * UserManagerTest
 */
class UserManagerTest extends \PHPUnit_Framework_TestCase
{
	protected function setUp()
	{
		Log::setDefaultLog( __DIR__ . '/../log/error.log' );

		parent::setUp();
	}

	public function testUserManager()
	{
		$_model = new UserManager();

		$this->assertNotEmpty( $_model );
	}

	protected function tearDown()
	{
		parent::tearDown();
	}
}
