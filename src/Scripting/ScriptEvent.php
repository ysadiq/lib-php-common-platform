<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Scripting;

use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\PlatformEvent;
use DreamFactory\Platform\Resources\System\Config;
use DreamFactory\Platform\Resources\System\User;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Yii\Models\App;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Acts as a proxy between a DSP PHP $event and a server-side script
 */
class ScriptEvent
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @type string The name of the script event schema file
	 */
	const SCRIPT_EVENT_SCHEMA = 'script_event_schema.json';
	/**
	 * @type string The name of the key within the event structure that contains the payload
	 */
	const DEFAULT_PAYLOAD_KEY = 'record';

	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var string The event schema for scripting events
	 */
	static protected $_eventTemplate = false;
	static protected $_payloadKey = self::DEFAULT_PAYLOAD_KEY;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param string $template The name of the template to use for events. These are JSON files and reside in [library]/config/schema
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return bool|array The schema in array form, FALSE on failure.
	 */
	public static function initialize( $template = self::SCRIPT_EVENT_SCHEMA )
	{
		static::$_payloadKey = Pii::getParam( 'scripting.payload_key', static::DEFAULT_PAYLOAD_KEY );

		if ( false !== ( $_eventTemplate = Platform::storeGet( 'scripting.event_schema', false ) ) )
		{
			return $_eventTemplate;
		}

		//	Not cached, get it...
		$_path = Platform::getLibraryConfigPath( '/schema' ) . '/' . trim( $template, ' /' );

		if ( is_file( $_path ) && is_readable( $_path ) && ( false !== ( $_eventTemplate = file_get_contents( $_path ) ) ) )
		{
			if ( false !== ( $_eventTemplate = json_decode( $_eventTemplate, true ) ) && JSON_ERROR_NONE == json_last_error() )
			{
				Platform::storeSet( 'scripting.event_schema', $_eventTemplate, 86400 );

				return $_eventTemplate;
			}
		}

		Log::notice( 'Scripting unavailable. Unable to load scripting event schema: ' . $_path );

		return false;
	}

	/**
	 * Creates a generic, consistent event for scripting and notifications
	 *
	 * The returned array is as follows:
	 *
	 *  array(
	 *      //  This contains information about the event itself (READ-ONLY)
	 *      array(
	 *          'id'                => 'A unique ID assigned to this event',
	 *          'name'              => 'event.name',
	 *          'trigger'           => '{api_name}/{resource}',
	 *          'stop_propagation'  => [true|false],
	 *          'dispatcher'        => array(
	 *              'id'            => 'A unique ID assigned to the dispatcher of this event',
	 *              'type'          => 'The class name of the dispatcher',
	 *        //  THE MEAT! This contains the ACTUAL data received from the client, or what's being sent back to the client (READ-WRITE).
	 *        '[payload_key]' => array(
	 *          //  See recap above for formats
	 *          //  Information about the triggering request
	 *          'request'           => array(
	 *              'timestamp'     => 'timestamp of the initial request',
	 *              'api_name'      =>'The api_name of the called service',
	 *              'resource'      => 'The name of the resource requested',
	 *              'path'          => '/full/path/that/triggered/event',
	 *          ),
	 *        //  This contains the static configuration of the entire platform (READ-ONLY)
	 *        'platform' => array(
	 *            'api'               => [wormhole to inline-REST API],
	 *            'config'            => [standard DSP configuration update],
	 *        ),
	 *        //  This contains any additional information the event sender wanted to convey (READ-ONLY)
	 *        'details' => array(),
	 *      ),
	 *  );
	 *
	 * Please note that the format of the "record" differs slightly on multi-row result sets. In the v1.0 REST API, if a single row of data
	 * is to be returned from a request, it is merged into the root of the resultant array. If there are multiple rows, they are placed into
	 * n key called 'record'. To make matter worse, if you make a multi-row request via XML, and wrap your input "record" in a
	 * <records><record></record>...</records> type wrapper, the resultant array will be placed a level deeper ($payload['records']['record'] = $results).
	 *
	 * Therefore the data exposed by the event system has been "normalized" to provide a reliable and consistent manner in which to process said data.
	 * There should be no need for wasting time trying to determine if your data is "maybe here, or maybe there, or maybe over there even" when received by
	 * your event handlers. If your payload contains record data, you will always receive it in an array container. Even for single rows.
	 *
	 * IMPORTANT: Don't expect this for ALL results. For non-record-like resultant data and/or result sets (i.e. NoSQL, other stuff), the data
	 * may be placed in the payload verbatim.
	 *
	 * IMPORTANTER: The representation of the data will be placed back into the original location/position in the $record from which it was "normalized".
	 * This means that any client-side handlers will have to deal with the bogus determinations. Just be aware.
	 *
	 * To recap, below is a side-by-side comparison of record data as shown returned to the caller, and sent to an event handler.
	 *
	 *  REST API v1.0                           Event Representation
	 *  -------------                           --------------------
	 *  Single row...                           Add a 'record' key and make it look like a multi-row
	 *
	 *      array(                              array(
	 *          'id' => 1,                          'record' => array(
	 *      )                                           0 => array( 'id' => 1, ),
	 *                                              ),
	 *                                          ),
	 *
	 * Multi-row...                             Stays the same...
	 *
	 *      array(                              array(
	 *          'record' => array(                  'record' =>  array(
	 *              0 => array( 'id' => 1 ),            0 => array( 'id' => 1 ),
	 *              1 => array( 'id' => 2 ),            1 => array( 'id' => 2 ),
	 *              2 => array( 'id' => 3 ),            2 => array( 'id' => 3 ),
	 *          ),                                  ),
	 *      )                                   )
	 *
	 * XML multi-row                            The 'records' key is unwrapped, like regular multi-row
	 *
	 *  array(                                  array(
	 *    'records' => array(                     'record' =>  array(
	 *      'record' => array(                        0 => array( 'id' => 1 ),
	 *        0 => array( 'id' => 1 ),                1 => array( 'id' => 2 ),
	 *        1 => array( 'id' => 2 ),                2 => array( 'id' => 3 ),
	 *        2 => array( 'id' => 3 ),            ),
	 *      ),                                  )
	 *    ),
	 *  )
	 *
	 * @param string          $eventName        The event name
	 * @param PlatformEvent   $event            The event
	 * @param EventDispatcher $dispatcher       The dispatcher of the event
	 * @param array           $extra            Any additional data to put into the event structure
	 * @param bool            $includeDspConfig If true, the current DSP config is added to container
	 * @param bool            $returnJson       If true, the event will be returned as a JSON string, otherwise an array.
	 *
	 * @return array|string
	 */
	public static function normalizeEvent( $eventName, PlatformEvent $event, $dispatcher, $extra = array(), $includeDspConfig = true, $returnJson = false )
	{
		static $_config = null;

		$_config = $includeDspConfig ? ( $_config ? : Config::getCurrentConfig() ) : false;

		$_parser = new SwaggerParser();

		//	Clean up the event extras, remove data portion
		$_eventExtras = array_merge( $event->toArray( array( 'data' ) ),
			array(
				'timestamp' => date( 'c', Option::server( 'REQUEST_TIME_FLOAT', Option::server( 'REQUEST_TIME', microtime( true ) ) ) ),
				'path'      => $_path = $dispatcher->getPathInfo( true )
			) );

		//	Clean up the trigger
		$_trigger
			= false !== strpos( $_path, 'rest', 0 ) || false !== strpos( $_path, '/rest', 0 ) ? str_replace( array( '/rest', 'rest' ), null, $_path ) : $_path;

		//	Build the array
		$_event = array(
			//	Basics
			'id'                 => Option::get( $_eventExtras, 'event_id', null, true ),
			'name'               => $eventName,
			'trigger'            => $_trigger,
			'stop_propagation'   => Option::get( $_eventExtras, 'stop_propagation', false, true ),
			//	Dispatcher information
			'dispatcher'         => array(
				'id'   => spl_object_hash( $dispatcher ),
				'type' => Inflector::neutralize( get_class( $dispatcher ) ),
			),
			//	Normalized payload
			static::$_payloadKey => static::normalizeEventData( $event, false ),
			//	Access to the platform api
			'platform'           => array(
				'api'     => $_parser->buildApi( true ),
				'config'  => $_config,
				'session' => Pii::guest() ? false : static::_getCleanedSession(),
			),
			//	The parsed request information
			'request'            => $_eventExtras,
			//	Extra information passed by caller
			'extra'              => Option::clean( $extra ),
		);

		return $returnJson ? json_encode( $_event, JSON_UNESCAPED_SLASHES ) : $_event;
	}

	/**
	 * Cleans up the session data to send along with an event
	 *
	 * @return array
	 * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
	 * @throws \DreamFactory\Platform\Exceptions\UnauthorizedException
	 */
	protected static function _getCleanedSession()
	{
		$_session = Session::generateSessionDataFromUser( Session::getCurrentUserId() );

		if ( isset( $_session, $_session['allowed_apps'] ) )
		{
			$_apps = array();

			/** @var App $_app */
			foreach ( $_session['allowed_apps'] as $_app )
			{
				$_apps[$_app->api_name] = $_app->getAttributes();
			}

			$_session['allowed_apps'] = $_apps;
		}

		return $_session;
	}

	/**
	 * Sandboxes the event data into a normalized fashion
	 *
	 * @param PlatformEvent $event   The event source
	 * @param bool          $wrapped If true (default), the returned array has a single key of '[payload_key]'
	 *                               which contains the normalized payload. If false, the returned array
	 *                               is not wrapped but just the array of the payload
	 *
	 * @return array
	 */
	public static function normalizeEventData( PlatformEvent $event, $wrapped = true )
	{
		$_data = $event->getData();

		//  XML-wrapped
		if ( false !== ( $_records = Option::getDeep( $_data, 'records', 'record', false ) ) )
		{
			return $wrapped ? array( static::$_payloadKey => $_records ) : $_records;
		}

		//  Multi-row
		if ( false !== ( $_records = Option::get( $_data, 'record', false ) ) )
		{
			return $wrapped ? array( static::$_payloadKey => $_records ) : $_records;
		}

		//  Single row, or so we think...
		if ( is_array( $_data ) && !Pii::isEmpty( $_record = Option::get( $_data, 'record' ) ) && count( $_record ) >= 1 )
		{
			return $wrapped ? array( static::$_payloadKey => $_data ) : $_data;
		}

		//  Something completely different...
		return $_data;
	}

	/**
	 * Determines and returns the data back into the location from whence it came
	 *
	 * @param PlatformEvent $event
	 * @param array         $newData
	 * @param bool          $wrapped If true (default), the returned array has a single key of '[payload_key]'
	 *                               which contains the normalized payload. If false, the returned array
	 *                               is not wrapped but just the array of the payload
	 *
	 * @return array|mixed
	 */
	public static function denormalizeEventData( PlatformEvent $event, array $newData = array(), $wrapped = true )
	{
		$_currentData = $event->getData();
		$_innerData = array(
			'record' => $newData,
		);

		//  XML-wrapped
		if ( false !== ( $_records = Option::getDeep( $_currentData, 'records', 'record', false ) ) )
		{
			//  Re-gift
			return $wrapped ? array( 'records' => $_innerData ) : $_innerData;
		}

		//  Multi-row
		if ( false !== ( $_records = Option::get( $_currentData, 'record', false ) ) )
		{
			return $wrapped ? $_innerData : $newData;
		}

		//  Single row, or so we think...
		if ( is_array( $_currentData ) && !Pii::isEmpty( $_record = Option::get( $_currentData, 'record' ) ) && count( $_record ) >= 1 )
		{
			return $wrapped ? $_innerData : $newData;
		}

		//  A single row or something else...
		return $newData;
	}

	/**
	 * Give a normalized event, put any changed data from the payload back into the event
	 *
	 * @param PlatformEvent $event
	 * @param array         $normalizedEvent
	 *
	 * @return $this
	 */
	public static function updateEventFromHandler( PlatformEvent &$event, array $normalizedEvent = array() )
	{
		//  Did propagation stop?
		if ( Option::get( $normalizedEvent, 'stop_propagation', false ) )
		{
			$event->stopPropagation();
		}

		$_payload = Option::get( $normalizedEvent, static::$_payloadKey, array(), false );

		return $event->setData( static::denormalizeEventData( $event, $_payload ) );
	}

	/**
	 * @return string
	 */
	public static function getEventTemplate()
	{
		if ( empty( static::$_eventTemplate ) )
		{
			static::initialize();
		}

		return static::$_eventTemplate;
	}

	/**
	 * @param string $eventTemplate
	 */
	public static function setEventTemplate( $eventTemplate )
	{
		static::$_eventTemplate = $eventTemplate;
	}

}

//	Initialize the event template
ScriptEvent::initialize();

