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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Components\EventProxy;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Events\Chunnel;
use DreamFactory\Platform\Events\Enums\StreamEvents;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Log;

/**
 * EventStream
 * System service for event management
 *
 */
class EventStream extends BaseSystemRestResource
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * {@InheritDoc}
     */
    public function __construct( $consumer, $resources = array() )
    {
        if ( HttpMethod::GET != Pii::request( false )->getMethod() )
        {
            throw new ForbiddenException( 'Only GET is allowed.' );
        }

        $_config = array(
            'service_name' => 'system',
            'name'         => 'EventStream',
            'api_name'     => 'event_stream',
            'type'         => 'System',
            'type_id'      => PlatformServiceTypes::SYSTEM_SERVICE,
            'description'  => 'System event stream endpoint',
            'is_active'    => true,
        );

        parent::__construct( $consumer, $_config, $resources );
    }

    /**
     * GET starts the event stream
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \CDbException
     * @throws \InvalidArgumentException
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return bool
     */
    protected function _handleGet()
    {
        $_status = 'reopened';

        if ( null !== ( $_id = Pii::request( false )->query->get( 'id' ) ) )
        {
            if ( !Chunnel::isValidStreamId( $_id ) )
            {
                $_id = null;
            }
        }
        if ( empty( $_id ) )
        {
            $_id = Hasher::hash( microtime( true ), 'sha256' );
            $_status = 'created';
        }

        $_stream = Chunnel::create( $_id );
        $_pid = null;

        if ( function_exists( 'pcntl_fork' ) )
        {
            Log::debug( 'Process control available. Forking stream runner.' );

            switch ( $_pid = pcntl_fork() )
            {
                case -1:
                    Log::error( '  * Forking failed. Running synchronously' );
                    break;

                case 0:
                    Log::debug( '  * Child fork running (#' . getmypid() . ')' );
                    break;

                case 1:
                    Log::info( '  * Forking successful. Running asynchronously.' );

                    return array( 'stream_id' => $_id, 'timestamp' => microtime( true ), 'state' => $_status );
            }

        }

        $_startTime = microtime( true );
        Log::info( 'Event stream "' . $_id . '" ' . $_status . ' at ' . $_startTime );

        //  Notify the client that the stream's about to flow
        Chunnel::send( $_id, StreamEvents::STREAM_STARTED );

        //  Register with the main dispatcher
//        Pii::app()->getDispatcher()->registerStream( $_id, $_stream );

        $_success = true;

//        //  Start up the ping service
//        try
//        {
//            //  Starts a 5 second ping
//            while ( true )
//            {
//                sleep( 5 );
//                Chunnel::send( $_id, StreamEvents::PING );
//            }
//        }
//        catch ( \Exception $_ex )
//        {
//            Log::error( '  * Exception during streaming events: ' . $_ex->getMessage() );
//            $_success = false;
//        }
//
//        try
//        {
//            //  He's dead Jim.
//            Chunnel::send( $_id, StreamEvents::STREAM_STOPPED );
//        }
//        catch ( \Exception $_ex )
//        {
//            //  Meh...
//            $_success = false;
//            Log::error( '  * Failed to send stream stopped event to client' );
//        }
//
//
//        //  Make sure we're off the list
//        Pii::app()->getDispatcher()->unregisterStream( $_id );

        $_endTime = microtime( true );

        return array(
            'success' => $_success,
            'details' => array(
                'stream_id' => $_id,
                'elapsed'   => $_startTime - $_endTime,
                'timestamp' => microtime( true ),
                'state'     => $_status,
            )
        );
    }
}
