<?php
namespace DreamFactory\Platform\Events;

use DreamFactory\Platform\Events\Enums\EventSourceHeaders;
use DreamFactory\Yii\Utility\Pii;
use Igorw\EventSource\Stream;
use Kisma\Core\Seed;
use Kisma\Core\Utility\Option;

/**
 * Chunnel
 * An event channel/tunnel for clients
 */
class Chunnel extends Seed
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var Stream[]
     */
    protected static $_streams = array();

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string          $streamId
     * @param string          $eventName
     * @param array           $data
     * @param EventDispatcher $dispatcher
     *
     * @return bool
     */
    public static function send( $streamId, $eventName, array $data = array(), $dispatcher = null )
    {
        if ( !static::isValidStreamId( $streamId ) )
        {
            return false;
        }

        $_data = json_encode(
            array_merge(
                Option::clean( $data ),
                static::_streamStamp( $streamId, false )
            ),
            JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES
        );

        /** @noinspection PhpUndefinedMethodInspection */

        return static::$_streams[$streamId]
            ->event()
            ->setEvent( $eventName )
            ->setData( $_data )
            ->end()
            ->flush();
    }

    /**
     * Create and return a stream
     *
     * @param string $id
     *
     * @throws \InvalidArgumentException
     * @return Stream
     */
    public static function create( $id )
    {
        if ( empty( $id ) )
        {
            throw new \InvalidArgumentException( 'You must give this process an ID. $id cannot be blank.' );
        }

        //  Send the EventSource headers
        $_response = clone ( $_response = Pii::responseObject() );
        $_response->headers->add( EventSourceHeaders::all() );
        $_response->sendHeaders();

        //  Keep PHP happy, never time out
        set_time_limit( 0 );

        //  We all scream NEW STREAM!
        return
            static::isValidStreamId( $id )
                ? static::$_streams[$id]
                : static::$_streams[$id] = new Stream();
    }

    /**
     * @param string $streamId
     *
     * @return bool
     */
    public static function isValidStreamId( $streamId )
    {
        return array_key_exists( $streamId, static::$_streams );
    }

    /**
     * @return bool
     */
    protected static function _startHeartBeat()
    {
        return false;
    }

    /**
     * Handles the output stream to the client
     *
     * @param string $streamData
     *
     * @throws \InvalidArgumentException
     */
    protected static function _streamHandler( $streamData )
    {
        echo $streamData;
        ob_flush();
        flush();
    }

    /**
     * Creates a common stamp for all streamed events
     *
     * @param string $id
     * @param bool   $asJson
     * @param int    $jsonOptions
     *
     * @return array|string
     */
    protected static function _streamStamp( $id, $asJson = true, $jsonOptions = 0 )
    {
        $_stamp = array( 'stream_id' => $id, 'timestamp' => microtime( true ) );

        return $asJson ? json_encode( $_stamp, $jsonOptions | ( JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES ) ) : $_stamp;
    }
}
