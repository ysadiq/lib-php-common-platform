<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Oasys\Stores\FileSystem;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * Constant
 * A resource that returns system constants
 *
 */
class Constant extends BaseSystemRestResource
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var FileSystem
	 */
	protected static $_cache = null;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Creates a new SystemResource instance
	 */
	public function __construct( $consumer, $resources = array() )
	{
		$_config = array(
			'service_name' => 'system',
			'name'         => 'Constant',
			'api_name'     => 'constant',
			'type'         => 'System',
			'type_id'      => PlatformServiceTypes::SYSTEM_SERVICE,
			'description'  => 'A service that allows you to query the system for the constants that it uses.',
			'is_active'    => true,
		);

		parent::__construct( $consumer, $_config, $resources );
	}

	/**
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @return array
	 */
	protected function _handleResource()
	{
		$_cache = static::_initializeCache();

		if ( empty( $this->_resourceId ) )
		{
			return $_cache;
		}

		$_tag = Inflector::neutralize( $this->_resourceId );

		if ( null !== ( $_response = Option::get( $_cache, $_tag ) ) )
		{
			return array(
				$_tag => $_response
			);
		}

		throw new NotFoundException( 'The constant "' . Inflector::deneutralize( $this->_resourceId ) . '" was not found.', 404 );
	}

	/**
	 * Initializes the cache upon first use for all system enums
	 *
	 * @return array
	 */
	protected static function _initializeCache()
	{
		if ( empty( static::$_cache ) )
		{
			$_path = dirname( dirname( __DIR__ ) ) . '/Enums';

			if ( false !== ( $_files = scandir( $_path ) ) )
			{
				$_tag = Inflector::tag( __CLASS__, true );
				$_store = new FileSystem( $_tag );
				static::$_cache = $_constants = $_store->get( 'constants', array() );

				if ( empty( $_constants ) )
				{
					$_constants = array();

					foreach ( $_files as $_file )
					{
						if ( '.' == $_file || '..' == $_file || is_dir( $_path . '/' . $_file ) || false === strrpos( $_file, '.php', -4 ) )
						{
							continue;
						}

						$_key = Inflector::neutralize( $_file = str_replace( '.php', null, $_file ) );
						$_className = '\\DreamFactory\\Platform\\Enums\\' . $_file;

						try
						{
							$_mirror = new \ReflectionClass( $_className );

							//	Only our Seed Enums can do this
							if ( !$_mirror->isSubclassOf( '\\Kisma\\Core\\Enums\\SeedEnum' ) )
							{
								unset( $_mirror );
								continue;
							}

							$_constants[$_key] = $_mirror->getMethod( 'getDefinedConstants' )->invoke( null, false, $_className );
						}
						catch ( \Exception $_ex )
						{
							//	Who knows...
							continue;
						}
					}

					$_store->set( 'constants', static::$_cache = $_constants );
					$_store->sync();
					unset( $_constants );
				}
			}
		}

		return static::$_cache;
	}
}
