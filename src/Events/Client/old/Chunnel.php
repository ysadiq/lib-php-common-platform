<?php
namespace DreamFactory\Platform\Events;

use DreamFactory\Platform\Events\Enums\EventSourceHeaders;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Curl;

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
     * @var string
     */
    protected $_callbackUrl;
    /**
     * @var string
     */
    protected $_clientId;
    /**
     * @var string
     */
    protected $_clientSecret;
    /**
     * @var Chunnel[]
     */
    protected static $_chunnels = array();

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Run the process
     */
    public static function run( $id )
    {
        if ( empty( $id ) )
        {
            throw new \InvalidArgumentException( 'You must give this process an ID. $id cannot be blank.' );
        }

        $_response = clone ( $_response = Pii::responseObject() );
        $_response->headers->add( EventSourceHeaders::all() );

        $_stream = new EventStream( new static() );
        $_proxy = $_stream->createProxy();

        while ( true )
        {
            /** @noinspection PhpUndefinedMethodInspection */
            $_proxy->setData( 'Hello World' )->end()->flush();

            sleep( 2 );
        }
    }

    /**
     * @param string $callbackUrl
     * @param string $clientId
     * @param string $clientSecret
     * @param array  $options
     *
     * @throws InvalidArgumentException
     */
    public function __construct( $callbackUrl = null, $clientId = null, $clientSecret = null, $options = array() )
    {
        $this->_callbackUrl = $callbackUrl;
        $this->_clientId = $clientId;
        $this->_clientSecret = $clientSecret;

        parent::__construct( $options );

//        if ( empty( $this->_callbackUrl ) || empty( $this->_clientId ) || empty( $this->_clientSecret ) )
//        {
//            throw new InvalidArgumentException( 'Invalid $callbackUrl, $clientId, and/or $clientSecret.' );
//        }
    }

    /**
     * Open a chunnel
     *
     * @param string $name
     *
     * @throws \RuntimeException
     * @internal param string $chunnelName The name of the chunnel to open
     *
     * @return string A token for opening an EventSource chunnel
     */
    public function open( $name )
    {
        $_response = $this->_doSend( '/socket', array( 'channel' => $name ) );

        if ( $_response )
        {
            $_json = @json_decode( $_response );

            if ( JSON_ERROR_NONE !== $_json )
            {
                throw new \RuntimeException( 'The client did not respond properly. Invalid JSON received.' );
            }

            return $_json->socket;
        }

        return false;
    }

    /**
     * Send a message to a channel
     *
     * @param array $options The sending options
     *
     * @return bool true or false
     */
    public function send( $options )
    {
        $_result = $this->_doSend(
            '/event',
            array(
                'channel' => $options['channel'],
                'data'    => $options['data']
            )
        );

        return $_result ? true : false;
    }

    /**
     * @param string $route
     * @param array  $params
     *
     * @return bool|mixed|\stdClass
     * @throws \RuntimeException
     */
    protected function _doSend( $route, $params )
    {
        $_url = $this->_callbackUrl . '/' . ltrim( $route, '/' );

        $_fields = array_merge( $params, $this->_getSignature() );

        $_options = array(
            CURLOPT_HEADER         => false,
            CURLOPT_FRESH_CONNECT  => true,
            CURLOPT_FORBID_REUSE   => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        );

        if ( false === ( $_result = Curl::post( $_url, $_fields, $_options ) ) )
        {
            throw new \RuntimeException( 'Unable to connect to client.' );
        }

        return $_result;
    }

    /**
     * @return array
     */
    protected function _getSignature()
    {
        return array(
            'timestamp' => $_timestamp = microtime( true ),
            'client_id' => $this->_clientId,
            'signature' => sha1( $this->_clientId . '.' . $this->_clientSecret . '.' . $_timestamp ),
        );
    }
}
