<?php
namespace DreamFactory\Platform\Events;

/**
 * Chunnel
 * An event channel/tunnel for clients
 */
class Chunnel extends Seed
{
    protected $_callbackUrl;
    protected $_clientId;
    protected $_clientSecret;

    /**
     * @param string $callbackUrl
     * @param string $clientId
     * @param string $clientSecret
     * @param array  $options
     *
     * @throws InvalidArgumentException
     */
    public function __construct( $callbackUrl, $clientId, $clientSecret, $options = array() )
    {
        $this->_callbackUrl = $callbackUrl;
        $this->_clientId = $clientId;
        $this->_clientSecret = $clientSecret;

        parent::__construct( $options );

        if ( empty( $this->_callbackUrl ) || empty( $this->_clientId ) || empty( $this->_clientSecret ) )
        {
            throw new InvalidArgumentException( 'Invalid $callbackUrl, $clientId, and/or $clientSecret.' );
        }
    }

    /**
     * Open a chunnel
     *
     * @param string $chunnelName The name of the chunnel to open
     * @return string A token for opening an EventSource chunnel
     */
    public function open( $chunnelName )
    {
        $response = $this->post( "/socket", array( "channel" => $options['channel'] ) );

        if ( $response )
        {
            $json = json_decode( $response );

            return $json->socket;
        }
        else
        {
            return false;
        }
    }

    /**
     * Send a message to a channel
     *
     * @param array $options
     *  array("channel" => "your-channel", "data" => "data-to-send")
     *
     * @return
     *  true or false
     */
    public function send( $options )
    {
        $response = $this->post(
            "/event",
            array(
                "channel" => $options['channel'],
                "data"    => $options['data']
            )
        );

        return $response ? true : false;
    }

    private function post( $path, $params )
    {
        $url = $this->_callbackUrl . $path;
        $fields = array_merge( $params, $this->credentials() );

        $defaults = array(
            CURLOPT_POST           => 1,
            CURLOPT_HEADER         => 0,
            CURLOPT_URL            => $url,
            CURLOPT_FRESH_CONNECT  => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE   => 1,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => http_build_query( $fields ),
            CURLOPT_SSL_VERIFYPEER => 0
        );

        $ch = curl_init();
        curl_setopt_array( $ch, $defaults );
        if ( !$result = curl_exec( $ch ) )
        {
            throw new Exception( "Request to ESHQ failed" );
        }
        curl_close( $ch );

        return $result;
    }

    private function credentials()
    {
        $time = time();

        return array(
            'timestamp' => $time,
            'token'     => $this->token( $time ),
            'key'       => $this->_clientId
        );
    }

    private function token( $time )
    {
        return sha1( $this->_clientId . ":" . $this->_clientSecret . ":" . $time );
    }
}
