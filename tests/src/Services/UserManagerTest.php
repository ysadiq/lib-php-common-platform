<?php
namespace DreamFactory\Tests\Services;

use DreamFactory\Platform\Services\UserManager;

/**
 * UserManagerTest
 */
class UserManagerTest extends \PHPUnit_Framework_TestCase
{
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
