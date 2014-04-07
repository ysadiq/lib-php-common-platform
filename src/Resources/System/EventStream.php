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
<<<<<<< HEAD
<<<<<<< HEAD
use DreamFactory\Platform\Exceptions\NotFoundException;
=======
=======
>>>>>>> Eventstream testing
<<<<<<< HEAD
=======
use DreamFactory\Platform\Exceptions\NotFoundException;
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
=======
>>>>>>> Eventstream testing
>>>>>>> Eventstream testing
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
<<<<<<< HEAD
<<<<<<< HEAD
     * GET any messages in the event stream
     * This method does not return to the caller, it is self-killing
     *
<<<<<<< HEAD
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
=======
     * @throws \CException
<<<<<<< HEAD
>>>>>>> Composer update and eventstream junk
=======
=======
     * GET starts the event stream
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
=======
     * @throws \CDbException
>>>>>>> Eventstream testing
<<<<<<< HEAD
>>>>>>> Eventstream testing
=======
=======
     * GET any messages in the event stream
     * This method does not return to the caller, it is self-killing
     *
     * @throws \CException
>>>>>>> Composer update and eventstream junk
>>>>>>> Composer update and eventstream junk
     * @throws \InvalidArgumentException
     * @return bool
     */
    protected function _handleGet()
    {
        $_status = 'reopened';

<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
=======
        //  Get the ID to use, or make a new one...
>>>>>>> Composer update and eventstream junk
=======
=======
>>>>>>> Eventstream testing
=======
>>>>>>> Composer update and eventstream junk
        //  Get the ID to use, or make a new one...
=======
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
        if ( null === ( $_id = Pii::request( false )->query->get( 'id' ) ) )
        {
            $_id = Hasher::hash( microtime( true ), 'sha256' );
            $_status = 'created';
        }
<<<<<<< HEAD
<<<<<<< HEAD
        else
        {
            if ( !Chunnel::isValidStreamId( $_id ) )
            {
                throw new NotFoundException();
            }
        }
=======
>>>>>>> Composer update and eventstream junk
=======
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.

        $_pid = null;
        $_stream = Chunnel::create( $_id );

        //  Notify the client that the stream's about to flow
        $_startTime = microtime( true );

        if ( 'created' == $_status )
        {
            Chunnel::send( $_id, StreamEvents::STREAM_CREATED );
        }

        //  Register with the main dispatcher
        Pii::app()->getDispatcher()->registerStream( $_id, $_stream );

        $_success = true;

        Log::info( 'Event stream "' . $_id . '" ' . $_status . ' at ' . $_startTime );

        try
        {
=======
        else
=======
        if ( null !== ( $_id = Pii::request( false )->query->get( 'id' ) ) )
>>>>>>> Eventstream testing
        {
            if ( !Chunnel::isValidStreamId( $_id ) )
            {
                $_id = null;
            }
        }
        if ( empty( $_id ) )
=======
        //  Get the ID to use, or make a new one...
        if ( null === ( $_id = Pii::request( false )->query->get( 'id' ) ) )
>>>>>>> Composer update and eventstream junk
        {
            $_id = Hasher::hash( microtime( true ), 'sha256' );
            $_status = 'created';
        }

        $_pid = null;
        $_stream = Chunnel::create( $_id );

        //  Notify the client that the stream's about to flow
        $_startTime = microtime( true );

        if ( 'created' == $_status )
        {
            Chunnel::send( $_id, StreamEvents::STREAM_CREATED );
        }

        //  Register with the main dispatcher
        Pii::app()->getDispatcher()->registerStream( $_id, $_stream );

        $_success = true;

        Log::info( 'Event stream "' . $_id . '" ' . $_status . ' at ' . $_startTime );

        try
        {
            while ( true )
            {
                Chunnel::send( $_id, StreamEvents::PING );
                sleep( 5 );
            }
        }
        catch ( Exception $_ex )
        {
            Log::error( 'Exception during event stream loop: ' . $_ex->getMessage() );
        }

<<<<<<< HEAD
        $_startTime = microtime( true );
        Log::info( 'Event stream "' . $_id . '" ' . $_status . ' at ' . $_startTime );

        //  Notify the client that the stream's about to flow
        Chunnel::send( $_id, StreamEvents::STREAM_STARTED );

<<<<<<< HEAD
        try
        {
            //  Starts a 5 second ping
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
            while ( true )
            {
                Chunnel::send( $_id, StreamEvents::PING );
                sleep( 5 );
            }
        }
<<<<<<< HEAD
        catch ( Exception $_ex )
        {
            Log::error( 'Exception during event stream loop: ' . $_ex->getMessage() );
        }

<<<<<<< HEAD
        Log::info( 'Event stream "' . $_id . '" ' . $_status );

        //  Notify the client that the stream's about to flow
        Chunnel::send( $_id, StreamEvents::STREAM_STARTED );

        try
        {
            //  Starts a 5 second ping
            while ( true )
            {
                Chunnel::send( $_id, StreamEvents::PING );
                sleep( 5 );
            }
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Exception during streaming events: ' . $_ex->getMessage() );
        }

        //  He's dead Jim.
        Chunnel::send( $_id, StreamEvents::STREAM_STOPPED );

        return array( 'stream_id' => $_id, 'timestamp' => microtime( true ), 'state' => $_status );
=======
        Chunnel::send( $_id, StreamEvents::STREAM_CLOSING );

        Pii::end();
>>>>>>> Composer update and eventstream junk
    }
<<<<<<< HEAD

=======
=======
        catch ( \Exception $_ex )
        {
            Log::error( 'Exception during streaming events: ' . $_ex->getMessage() );
        }

        //  He's dead Jim.
        Chunnel::send( $_id, StreamEvents::STREAM_STOPPED );

        return array( 'stream_id' => $_id, 'timestamp' => microtime( true ), 'state' => $_status );
    }

>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
=======
        //  Register with the main dispatcher
//        Pii::app()->getDispatcher()->registerStream( $_id, $_stream );

        $_success = true;
=======
        Chunnel::send( $_id, StreamEvents::STREAM_CLOSING );
>>>>>>> Composer update and eventstream junk

        Pii::end();
    }
>>>>>>> Eventstream testing
>>>>>>> Eventstream testing
}
