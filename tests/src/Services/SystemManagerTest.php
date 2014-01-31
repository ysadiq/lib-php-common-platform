<?php
namespace DreamFactory\Platform\Services;

/**
 * SystemManagerTest
 */
class SystemManagerTest extends \PHPUnit_Framework_TestCase
{
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
