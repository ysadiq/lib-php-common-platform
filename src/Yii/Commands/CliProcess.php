<?php
namespace DreamFactory\Platform\Yii\Commands;

use DreamFactory\Platform\Exceptions\EmptyQueueException;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Interfaces\ConsumerLike;
use Kisma\Core\Utility\DateTime;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;

/**
 * CliProcess
 * A convenient base class for CLI processes (i.e. background tasks, stand-alone scripts, etc.)
 *
 * This is a base class for command-line-interface classes in Yii-based applications
 *
 * @property-read string  $thisHost
 * @property-read integer $thisPid
 * @property-read string  $lockFile
 * @property string       $lockTag
 * @property string       $lockFileTemplate
 * @property string       $lockFilePath
 * @property array        $waitProcesses
 * @property boolean      $singleFile
 */
abstract class CliProcess extends \CConsoleCommand implements ConsumerLike
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @const string
     */
    const DEFAULT_LOCK_FILE_PATH = '/var/lock/dreamfactory';
    /**
     * @const string
     */
    const DEFAULT_LOCK_FILE_TEMPLATE = 'dreamfactory.%%appName%%.%%serviceName%%.%%host%%.lock';
    /**
     * @const int
     */
    const DEFAULT_LOCK_AGE = 60;

    //********************************************************************************
    //* Members
    //********************************************************************************

    /**
     * @var int
     */
    protected $_thisPid;
    /**
     * @var string $thisHost The host name of the current machine
     */
    protected $_thisHost;
    /**
     * @var string
     */
    protected $_lockFile;
    /**
     * @var string
     */
    protected $_lockFilePath = self::DEFAULT_LOCK_FILE_PATH;
    /**
     * @var string
     */
    protected $_lockFileTemplate = self::DEFAULT_LOCK_FILE_TEMPLATE;
    /**
     * @var string
     */
    protected $_lockTag;
    /**
     * @var array A list of process tags to respect in terms of running. For instance if you
     *  don't want your once-a-minute process to run while your hourly is running,
     *  this is how to easily do it.
     */
    protected $_waitProcesses = array();
    /**
     * @var boolean
     */
    protected $_singleFile = true;
    /**
     * @var array
     */
    protected $_options = array();
    /**
     * @var bool If true, more verbose logging is enabled
     */
    protected $_verbose = false;
    /**
     * @var bool If true, dumps profile results to the log
     */
    protected $_showProfileResults = false;

    //********************************************************************************
    //* Methods
    //********************************************************************************

    /**
     * @param string                 $commandName
     * @param \CConsoleCommandRunner $commandRunner
     * @param array                  $options
     */
    public function __construct( $commandName, $commandRunner, $options = array() )
    {
        parent::__construct( $commandName, $commandRunner );

        //	Some quick system info
        $this->_options = $options;
        $this->_thisPid = getmypid();
        $this->_thisHost = php_uname( 'n' );
        $this->_lockTag = Inflector::tag( $commandName, true );
    }

    /**
     * Clean up
     */
    public function __destruct()
    {
        try
        {
            @unlink( $this->_lockFile );
        }
        catch ( \Exception $_ex )
        {
            //	Ignore.
        }
    }

    /**
     * {@InheritDoc}
     */
    public function run( $args )
    {
        try
        {
            return parent::run( $args );
        }
        catch ( EmptyQueueException $_ex )
        {
            if ( $this->_verbose )
            {
                Log::info( 'The cupboard is bare', array( 'args' => $args ) );
            }

            $this->handleSuccess();
        }
        catch ( \Exception $_ex )
        {
            $this->handleError( $_ex );
        }
    }

    /**
     * Updates the status email and exits
     *
     * @param mixed $response The results of the process
     *
     * @return int
     */
    public function handleSuccess( $response = null )
    {
        return 0;
    }

    /**
     * Logs an error message, updates the status email and exits
     *
     * @param string     $message   The error message
     * @param \Exception $exception The exception that occurred if any
     *
     * @return int
     */
    public function handleError( $message, \Exception $exception = null )
    {
        $this->logError( $message );

        if ( null !== $exception )
        {
            $this->logDebug( $exception->getTraceAsString() );
        }

        return 1;
    }

    /**
     * @param bool $noLock
     *
     * @return bool
     */
    protected function _createLockFile( $noLock = false )
    {
        //	Make a lock file
        try
        {
            if ( null === $this->_lockTag )
            {
                $this->_lockTag = get_called_class();
            }

            //	Create a lock file name
            $this->_lockFile = $this->_createLockFileName();

            if ( $noLock )
            {
                //	Just return here, no lock file...
                return true;
            }

            if ( !empty( $this->_lockFile ) )
            {
                $_lockFile = $this->_lockFilePath . '/' . $this->_lockFile;

                //	check our lock file...
                if ( false !== ( $_age = $this->_shouldWait( $this->_lockTag ) ) )
                {
                    $this->logWarning( 'Existing process lock: ' . $this->_lockTag );

                    if ( true !== $_age )
                    {
                        $this->handleError( 'Log over age threshold of ' . static::DEFAULT_LOCK_AGE . ' minutes. Please check!', null, true );
                    }

                    $this->handleError( $this->_lockTag . ' :: Cannot start new run while while process is still running/locked.', null, true );

                    return false;
                }

                if ( false === @file_put_contents( $_lockFile, getmypid() ) )
                {
                    @unlink( $_lockFile );
                    $this->handleError( 'Error creating lock file. Cannot continue because of file system error. Please fix.', null, true );

                    return false;
                }
            }

            return true;
        }
        catch ( \Exception $_ex )
        {
            $this->handleError( 'Exception while determining lock status: ', $_ex );

            return false;
        }
    }

    /**
     * @return bool
     */
    protected function _destroyLockFile()
    {
        $_lockFile = $this->_lockFilePath . '/' . $this->_lockFile;

        return file_exists( $_lockFile ) && @unlink( $_lockFile );
    }

    /**
     * Creates a standardized lock-file name
     *
     * @param string $host
     * @param string $appName
     * @param string $serviceName
     * @param string $lockFileTemplate
     *
     * @return string
     */
    protected function _createLockFileName( $host = null, $appName = null, $serviceName = null, $lockFileTemplate = null )
    {
        //	Create a lock file name
        return trim(
            str_ireplace(
                array(
                    '%%host%%',
                    '%%appName%%',
                    '%%serviceName%%',
                ),
                array(
                    $host ? : $this->_thisHost,
                    $appName ? : str_replace( ' ', '-', strtolower( Pii::appName() ) ),
                    $serviceName ? : str_replace( ' ', '-', strtolower( $this->getName() ) ),
                ),
                $lockFileTemplate ? : $this->_lockFileTemplate
            )
        );
    }

    /**
     * Returns number of seconds old the lock is if it exists or false if no lock file.
     *
     * @param string $lockFileName
     * @param int    $lockAge
     *
     * @return bool|int
     */
    protected function _lockExists( $lockFileName = null, $lockAge = null )
    {
        if ( null === $lockFileName )
        {
            $lockFileName = $this->_lockFile;
        }

        $lockFileName = $this->_lockFilePath . '/' . $lockFileName;

        //	check our lock file...
        if ( @file_exists( $lockFileName ) )
        {
            if ( null === $lockAge )
            {
                if ( $this->_verbose )
                {
                    $this->logDebug( 'Lock-file exists: ' . $lockFileName );
                }

                //	Lock file exists!
                return true;
            }

            //	If time is greater than age asked, let someone know...
            $_age = time() - filemtime( $lockFileName );

            if ( $this->_verbose )
            {
                $this->logDebug( 'Lock-file exists and is ' . number_format( $_age / 60, 2 ) . ' minute(s) old: ' . $lockFileName );
            }

            return ( $_age <= $lockAge );
        }

        if ( $this->_verbose )
        {
            $this->logDebug( 'Lock-file DOES NOT exist: ' . $lockFileName );
        }

        return false;
    }

    /**
     * Determine if a process should be blocked by another process
     *
     * @param string $lockTag
     * @param int    $lockAge
     *
     * @return boolean
     */
    protected function _shouldWait( $lockTag, $lockAge = self::DEFAULT_LOCK_AGE )
    {
        $_tagFound = false;

        foreach ( $this->_waitProcesses as $_lockTag => $_waitProcess )
        {
            if ( $lockTag == $_lockTag )
            {
                if ( $this->_lockExists( $_waitProcess['lockFileName'], $_waitProcess['lockAge'] ) )
                {
                    //	Lock exists, so yes, please wait...
                    return true;
                }

                if ( !$_tagFound )
                {
                    $_tagFound = true;
                }
            }
        }

        //	No waits requested? Then we need to check our process
        if ( !$_tagFound )
        {
            return $this->_lockExists();
        }

        return false;
    }

    /**
     * @param string      $lockTag
     * @param int         $lockAge
     * @param string|null $lockFileName
     *
     * @return CliProcess $this
     */
    public function addWaitProcess( $lockTag, $lockAge = self::DEFAULT_LOCK_AGE, $lockFileName = null )
    {
        //	Make a lock file name if
        if ( null === $lockFileName )
        {
            $lockFileName = $this->_createLockFileName( null, null, null, $lockTag );
        }

        $this->_waitProcesses[$lockTag] = array(
            'lockAge'      => $lockAge,
            'lockFileName' => $lockFileName,
        );

        return $this;
    }

    /**
     * @param string $text
     * @param bool   $bold
     * @param int    $indent
     */
    public function writeln( $text = null, $bold = false, $indent = 0 )
    {
        $this->write( $text, $bold, true, $indent );
    }

    /**
     * @param bool $newline
     */
    public function hr( $newline = true )
    {
        $this->write( '************************************************************', false, $newline );
    }

    /**
     * @param string $text
     * @param bool   $bold
     * @param bool   $newLine
     * @param int    $indent
     */
    public function write( $text, $bold = false, $newLine = false, $indent = 0 )
    {
        $text = ( is_object( $text ) ? get_class( $text ) : $text );

        if ( false !== $bold )
        {
            $this->bold( $text );
        }

        echo $text . ( $newLine ? PHP_EOL : null );
    }

    /**
     * @param string $text
     *
     * @return string
     */
    public function bold( $text )
    {
        return "\033[1m" . $text . "\033[0m";
    }

    /**
     * @param callable $callback
     * @param float    $start
     * @param float    $end
     *
     * @return void
     */
    protected function _registerProfile( $callback, $start, $end = null )
    {
        if ( null === $end )
        {
            $end = microtime( true );
        }

        $this->_profileResults[get_called_class() . '::' . $callback[1]][date( 'YmdHis' )] = array(
            'start'   => $start,
            'end'     => $end,
            'elapsed' => $end - $start,
        );
    }

    /**
     * Records time a call takes
     *
     * @param callable $callback
     * @param null     $_
     *
     * @return void
     */
    protected function _profile( $callback, &$_ = null )
    {
        $_start = microtime( true );

        if ( is_callable( $callback ) )
        {
            $_result = call_user_func_array( $callback, func_get_args() );
        }

        $this->_registerProfile( $callback, $_start );
    }

    /**
     * Dumps the profile data to the app log
     */
    protected function _showProfileResults()
    {
        if ( false !== $this->_showProfileResults )
        {
            $_class = get_called_class();
            $_total = 0.0;

            $this->logTrace( '>>START Job Profile for "' . $_class . '"' );

            foreach ( $this->_profileResults as $_method => $_executions )
            {
                foreach ( $_executions as $_runTime => $_results )
                {
                    $_total += $_results['elapsed'];
                    $this->logTrace(
                        str_replace(
                            $_class . '::',
                            null,
                            $_method
                        ) . ': ' . DateTime::prettySeconds( $_results['elapsed'] )
                    );
                }
            }

            $this->logTrace( 'Total Job Time: ' . DateTime::prettySeconds( $_total ) );
            $this->logTrace( '<<END Job Profile' );
        }
    }

    /**
     * Marks the begin/end of a process/sub-process
     *
     * @param string              $tag
     * @param string              $subject
     * @param bool                $stop  If true, marks the end of the block
     * @param null                $status
     * @param YII_LOG_INFO|string $level Ignored currently
     *
     * @return bool
     */
    protected function _logBookend( $tag, $subject, $stop = false, $status = null, $level = 'info' )
    {
        $_entry = sprintf( '%s > %s > %s%s', $stop ? '<<END' : '>>START', $subject, $tag, !empty( $status ) ? ( ' > ' . $status ) : '' );

        if ( !method_exists( $this, 'log' . $level ) )
        {
            $level = 'info';
        }

        $this->{'log' . $level}( $_entry );

        return !$stop;
    }

    /**
     * Outputs a DEBUG message to the current logger
     *
     * @param string $message
     */
    public function logDebug( $message )
    {
        Log::debug( $message );
    }

    /**
     * Outputs a TRACE message to the current logger
     *
     * @param string $message
     */
    public function logTrace( $message )
    {
        Log::debug( $message );
    }

    /***
     * Outputs a WARNING message to the current logger
     *
     * @param string $message
     */
    public function logWarning( $message )
    {
        Log::warning( $message );
    }

    /***
     * Outputs a WARNING message to the current logger
     *
     * @param string $message
     */
    public function logNotice( $message )
    {
        Log::log( $message, 'notice' );
    }

    /***
     * Outputs a ERROR message to the current logger
     *
     * @param string $message
     */
    public function logError( $message )
    {
        Log::error( $message );
    }

    /***
     * Outputs a debug message to the current logger
     *
     * @param string $message
     */
    public function logInfo( $message )
    {
        Log::info( $message );
    }

    /**
     * @param string $lockTag
     *
     * @return CliProcess
     */
    public function setLockTag( $lockTag )
    {
        $this->_lockTag = $lockTag;

        return $this;
    }

    /**
     * @return string
     */
    public function getLockTag()
    {
        return $this->_lockTag;
    }

    /**
     * @param string $lockFile
     *
     * @return CliProcess
     */
    protected function _setLockFile( $lockFile = null )
    {
        $this->_lockFile = $lockFile;

        return $this;
    }

    /**
     * @return string
     */
    public function getLockFile()
    {
        return $this->_lockFile;
    }

    /**
     * @return string
     */
    public function getLockFilePath()
    {
        return $this->_lockFilePath;
    }

    /**
     * @param string $lockFilePath
     *
     * @return CliProcess
     */
    public function setLockFilePath( $lockFilePath )
    {
        $this->_lockFilePath = $lockFilePath;

        return $this;
    }

    /**
     * @return string
     */
    public function getLockFileTemplate()
    {
        return $this->_lockFileTemplate;
    }

    /**
     * @param string $lockFileTemplate
     *
     * @return CliProcess
     */
    public function setLockFileTemplate( $lockFileTemplate )
    {
        $this->_lockFileTemplate = $lockFileTemplate;

        return $this;
    }

    /**
     * @param int $thisPid
     *
     * @return CliProcess
     */
    protected function _setThisPid( $thisPid )
    {
        $this->_thisPid = $thisPid;

        return $this;
    }

    /**
     * @return int
     */
    public function getThisPid()
    {
        return $this->_thisPid;
    }

    /**
     * If true, process only allows one instance
     *
     * @param boolean $singleFile
     *
     * @return CliProcess
     */
    public function setSingleFile( $singleFile )
    {
        $this->_singleFile = $singleFile;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getSingleFile()
    {
        return $this->_singleFile;
    }

    /**
     * @param $thisHost
     *
     * @return CliProcess
     */
    public function setThisHost( $thisHost )
    {
        $this->thisHost = $thisHost;

        return $this;
    }

    /**
     * @return string
     */
    public function getThisHost()
    {
        return $this->thisHost;
    }

    /**
     * @param array $waitProcesses
     *
     * @return CliProcess
     */
    public function setWaitProcesses( $waitProcesses = array() )
    {
        $this->_waitProcesses = $waitProcesses;

        return $this;
    }

    /**
     * @param string $lockTag
     *
     * @return $this
     */
    public function getWaitProcess( $lockTag )
    {
        return $this->_waitProcesses[$lockTag];
    }

    /**
     * @return array
     */
    public function getWaitProcesses()
    {
        return $this->_waitProcesses;
    }

    /**
     * @param array $options
     *
     * @return CliProcess
     */
    public function setOptions( $options )
    {
        $this->_options = $options;

        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * @param boolean $showProfileResults
     *
     * @return CliProcess
     */
    public function setShowProfileResults( $showProfileResults )
    {
        $this->_showProfileResults = $showProfileResults;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getShowProfileResults()
    {
        return $this->_showProfileResults;
    }

    /**
     * @param boolean $verbose
     *
     * @return CliProcess
     */
    public function setVerbose( $verbose )
    {
        $this->_verbose = $verbose;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getVerbose()
    {
        return $this->_verbose;
    }
}
