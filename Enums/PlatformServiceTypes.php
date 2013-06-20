<?php
namespace DreamFactory\Platform\Enums;

use Kisma\Core\Enums\SeedEnum;

/**
 * PlatformServiceTypes
 */
class PlatformServiceTypes extends SeedEnum
{
	/**
	 * @var string
	 */
	const LOCAL_SQL_DB_SCHEMA = 'Local SQL DB Schema';
	/**
	 * @var string
	 */
	const LOCAL_SQL_DB = 'Local SQL DB';
	/**
	 * @var string
	 */
	const LOCAL_FILE_STORAGE = 'Local File Storage';
	/**
	 * @var string
	 */
	const LOCAL_EMAIL_SERVICE = 'Local Email Service';
	/**
	 * @var string
	 */
	const REMOTE_AUTH_SERVICE = 'Remote Auth Service';
}