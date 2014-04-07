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
     * GET any messages in the event stream
     * This method does not return to the caller, it is self-killing
     *
     * @throws \CException
     * @throws \InvalidArgumentException
     * @return bool
     */
    protected function _handleGet()
    {
        $_status = 'reopened';

        //  Get the ID to use, or make a new one...
        if ( null === ( $_id = Pii::request( false )->query->get( 'id' ) ) )
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

        Chunnel::send( $_id, StreamEvents::STREAM_CLOSING );

        Pii::end();
    }
}
