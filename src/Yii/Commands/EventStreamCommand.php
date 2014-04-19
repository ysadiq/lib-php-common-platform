<?php
/**
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright (c) 2012-2013 by DreamFactory Software, Inc. All rights reserved.
 *
 * @copyright     Copyright 2013 DreamFactory Software, Inc. All rights reserved.
 * @link          http://dreamfactory.com DreamFactory Software, Inc.
 */
namespace DreamFactory\Platform\Yii\Commands;

use DreamFactory\Platform\Events\RatchetMessage;
use Ratchet\Server\IoServer;

/**
 * EventStreamCommand
 */
class EventStreamCommand extends CliProcess
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     *
     */
    public function actionStartStream( $id = null )
    {
        $this->logInfo( 'Starting event stream server' );

        $_status = 'reopened';

        //  Get the ID to use, or make a new one...
        $_id = $id;

        if ( empty( $_id ) && null === ( $_id = Pii::request( false )->query->get( 'id' ) ) )
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

        $this->logInfo( 'Event stream "' . $_id . '"started' );
    }

    /**
     * Starts the event stream server
     *
     * @param int $port The port upon which to run the server. Defaults to 8080
     *
     * @throws \RuntimeException
     * @throws \React\Socket\ConnectionException
     * @return bool
     */
    public function actionStartServer( $port = 8080 )
    {
        $_server = IoServer::factory(
            new RatchetMessage(),
            $port
        );

        $_server->run();

        return true;
    }

}