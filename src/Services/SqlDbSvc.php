<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\SqlDbUtilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;

/**
 * SqlDbSvc.php
 * A service to handle SQL database services accessed through the REST API.
 *
 */
class SqlDbSvc extends BaseDbSvc
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var bool If true, a database cache will be created for remote databases
     */
    const ENABLE_REMOTE_CACHE = true;
    /**
     * @var string The name of the remote cache component
     */
    const REMOTE_CACHE_ID = 'cache.remote';
    /**
     * Resource tag for dealing with stored procedures
     */
    const STORED_PROC_RESOURCE = '_proc';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var \CDbConnection
     */
    protected $_dbConn;
    /**
     * @var boolean
     */
    protected $_isNative = false;
    /**
     * @var array
     */
    protected $_fieldCache;
    /**
     * @var array
     */
    protected $_relatedCache;
    /**
     * @var integer
     */
    protected $_driverType = SqlDbUtilities::DRV_OTHER;
    /**
     * @var null | \CDbTransaction
     */
    protected $_transaction = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new SqlDbSvc
     *
     * @param array $config
     * @param bool  $native
     *
     * @throws \InvalidArgumentException
     */
    public function __construct( $config, $native = false )
    {
        parent::__construct( $config );

        $this->_fieldCache = array();
        $this->_relatedCache = array();

        if ( false !== ( $this->_isNative = $native ) )
        {
            $this->_dbConn = Pii::db();
        }
        else
        {
            $_credentials = Session::replaceLookup( Option::get( $config, 'credentials' ), true );

            if ( null === ( $dsn = Session::replaceLookup( Option::get( $_credentials, 'dsn' ), true ) ) )
            {
                throw new \InvalidArgumentException( 'DB connection string (DSN) can not be empty.' );
            }

            if ( null === ( $user = Session::replaceLookup( Option::get( $_credentials, 'user' ), true ) ) )
            {
                throw new \InvalidArgumentException( 'DB admin name can not be empty.' );
            }

            if ( null === ( $password = Session::replaceLookup( Option::get( $_credentials, 'pwd' ), true ) ) )
            {
                throw new \InvalidArgumentException( 'DB admin password can not be empty.' );
            }

            /** @var \CDbConnection $_db */
            $_db = Pii::createComponent(
                array(
                    'class'                 => 'CDbConnection',
                    'connectionString'      => $dsn,
                    'username'              => $user,
                    'password'              => $password,
                    'charset'               => 'utf8',
                    'enableProfiling'       => defined( YII_DEBUG ),
                    'enableParamLogging'    => defined( YII_DEBUG ),
                    'schemaCachingDuration' => 3600,
                    'schemaCacheID'         => ( !$this->_isNative && static::ENABLE_REMOTE_CACHE )
                            ? static::REMOTE_CACHE_ID : 'cache',
                )
            );

            Pii::app()->setComponent( 'db.' . $this->_apiName, $_db );

            // 	Create pdo connection, activate later
            if ( !$this->_isNative && static::ENABLE_REMOTE_CACHE )
            {
                $_cache = Pii::createComponent(
                    array(
                        'class'                => 'CDbCache',
                        'connectionID'         => 'db' /* . $this->_apiName*/,
                        'cacheTableName'       => 'df_sys_cache_remote',
                        'autoCreateCacheTable' => true,
                        'keyPrefix'            => $this->_apiName,
                    )
                );

                try
                {
                    Pii::app()->setComponent( static::REMOTE_CACHE_ID, $_cache );
                }
                catch ( \CDbException $_ex )
                {
                    Log::error( 'Exception setting cache: ' . $_ex->getMessage() );

                    //	Disable caching...
                    $_db->schemaCachingDuration = 0;
                    $_db->schemaCacheID = null;

                    unset( $_cache );
                }

                //	Save
                $this->_dbConn = $_db;
            }
        }

        switch ( $this->_driverType = SqlDbUtilities::getDbDriverType( $this->_dbConn ) )
        {
            case SqlDbUtilities::DRV_MYSQL:
                $this->_dbConn->setAttribute( \PDO::ATTR_EMULATE_PREPARES, true );
//				$this->_dbConn->setAttribute( 'charset', 'utf8' );
                break;

            case SqlDbUtilities::DRV_SQLSRV:
//				$this->_dbConn->setAttribute( constant( '\\PDO::SQLSRV_ATTR_DIRECT_QUERY' ), true );
                //	These need to be on the dsn
//				$this->_dbConn->setAttribute( 'MultipleActiveResultSets', false );
//				$this->_dbConn->setAttribute( 'ReturnDatesAsStrings', true );
//				$this->_dbConn->setAttribute( 'CharacterSet', 'UTF-8' );
                break;

            case SqlDbUtilities::DRV_DBLIB:
                $this->_dbConn->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
                break;
        }

        $_attributes = Option::clean( Option::get( $config, 'parameters' ) );

        if ( !empty( $_attributes ) )
        {
            $this->_dbConn->setAttributes( $_attributes );
        }
    }

    /**
     * Object destructor
     */
    public function __destruct()
    {
        if ( !$this->_isNative && isset( $this->_dbConn ) )
        {
            try
            {
                $this->_dbConn->active = false;
                $this->_dbConn = null;
            }
            catch ( \PDOException $_ex )
            {
                error_log( "Failed to disconnect from database.\n{$_ex->getMessage()}" );
            }
            catch ( \Exception $_ex )
            {
                error_log( "Failed to disconnect from database.\n{$_ex->getMessage()}" );
            }
        }
    }

    /**
     * @return int
     */
    public function getDriverType()
    {
        return $this->_driverType;
    }

    /**
     * @return \CDbConnection
     */
    public function getDbConn()
    {
        return $this->_dbConn;
    }

    /**
     * @throws \Exception
     */
    protected function checkConnection()
    {
        if ( !isset( $this->_dbConn ) )
        {
            throw new InternalServerErrorException( 'Database connection has not been initialized.' );
        }

        try
        {
            $this->_dbConn->setActive( true );
        }
        catch ( \PDOException $_ex )
        {
            throw new InternalServerErrorException( "Failed to connect to database.\n{$_ex->getMessage()}" );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to connect to database.\n{$_ex->getMessage()}" );
        }
    }

    protected function resourceIsTable( $resource )
    {
        if ( static::STORED_PROC_RESOURCE == $resource )
        {
            return false;
        }

        return parent::resourceIsTable( $resource );
    }

    /**
     * Corrects capitalization, etc. on table names, ensures it is not a system table
     *
     * @param $name
     *
     * @return string
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function correctTableName( $name )
    {
        $this->checkForNativeSystemTable( $name );

        return SqlDbUtilities::correctTableName( $this->_dbConn, $name );
    }

    protected function checkForNativeSystemTable( $name, $for_query = true )
    {
        if ( $this->_isNative )
        {
            // check for system tables and deny
            static $_length;

            if ( !$_length )
            {
                $_length = strlen( SystemManager::SYSTEM_TABLE_PREFIX );
            }

            if ( 0 === substr_compare( $name, SystemManager::SYSTEM_TABLE_PREFIX, 0, $_length ) )
            {
                if ( $for_query )
                {
                    throw new NotFoundException( "Table '$name' not found." );
                }

                throw new BadRequestException( "Use of the prefix '" .
                                               SystemManager::SYSTEM_TABLE_PREFIX .
                                               "' is not allowed." );
            }
        }
    }

    /**
     * @param string $resource
     * @param string $resource_id
     * @param string $action
     *
     * @internal param string $resourceId
     */
    protected function validateResourceAccess( $resource, $resource_id, $action )
    {
        if ( !empty( $resource ) )
        {
            switch ( $resource )
            {
                case static::STORED_PROC_RESOURCE:
                    if ( !empty( $resource_id ) )
                    {
                        $resource = $resource . '/' . $resource_id;
                    }
                    break;
                case static::SCHEMA_RESOURCE:
                    if ( !empty( $resource_id ) )
                    {
                        $resource_id = $this->correctTableName( $resource_id );
                    }
                    break;
                default:
                    $resource = $this->correctTableName( $resource );
                    break;
            }

        }

        parent::validateResourceAccess( $resource, $resource_id, $action );
    }

    /**
     * @param string $procedure
     * @param string $action
     *
     * @throws BadRequestException
     */
    protected function validateStoredProcedureAccess( &$procedure, $action = null )
    {
        // finally check that the current user has privileges to access this table
        $this->validateResourceAccess( static::STORED_PROC_RESOURCE, $procedure, $action );
    }

    /**
     * @return array|bool
     */
    protected function _handleResource()
    {
        switch ( $this->_resource )
        {
            case static::STORED_PROC_RESOURCE:
                return $this->_handleStoredProcedures();
                break;
        }

        return parent::_handleResource();
    }

    /**
     * {@InheritDoc}
     */
    protected function _detectRequestMembers()
    {
        parent::_detectRequestMembers();

        if ( !empty( $this->_resource ) )
        {
            switch ( $this->_resource )
            {
                case static::STORED_PROC_RESOURCE:
                case static::SCHEMA_RESOURCE:
                    break;
                default:
                    // All calls can request related data to be returned
                    $_related = Option::get( $this->_requestPayload, 'related' );
                    if ( !empty( $_related ) && is_string( $_related ) && ( '*' !== $_related ) )
                    {
                        $_relations = array();
                        if ( !is_array( $_related ) )
                        {
                            $_related = array_map( 'trim', explode( ',', $_related ) );
                        }
                        foreach ( $_related as $_relative )
                        {
                            $_extraFields = Option::get( $this->_requestPayload, $_relative . '_fields', '*' );
                            $_extraOrder = Option::get( $this->_requestPayload, $_relative . '_order', '' );
                            $_relations[] =
                                array('name' => $_relative, 'fields' => $_extraFields, 'order' => $_extraOrder);
                        }

                        $this->_requestPayload['related'] = $_relations;
                    }
                    break;
            }
        }

        return $this;
    }

    // REST service implementation

    /**
     * @throws \Exception
     * @return array
     */
    protected function _listTables()
    {
        // check for system tables
        $_exclude = ( $this->_isNative ) ? SystemManager::SYSTEM_TABLE_PREFIX : null;

        $_names = SqlDbUtilities::describeDatabase( $this->_dbConn, null, $_exclude );
        $_extras =
            SqlDbUtilities::getSchemaExtrasForTables( $this->getServiceId(), $_names, false, 'table,label,plural' );

        $_tables = array();
        foreach ( $_names as $name )
        {
            $label = '';
            $plural = '';
            foreach ( $_extras as $each )
            {
                if ( 0 == strcasecmp( $name, Option::get( $each, 'table', '' ) ) )
                {
                    $label = Option::get( $each, 'label' );
                    $plural = Option::get( $each, 'plural' );
                    break;
                }
            }

            if ( empty( $label ) )
            {
                $label = Inflector::camelize( $name, '_', true );
            }

            if ( empty( $plural ) )
            {
                $plural = Inflector::pluralize( $label );
            }

            $_tables[] = array('name' => $name, 'label' => $label, 'plural' => $plural);
        }

        return $_tables;
    }

    /**
     * @param array|null $options
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public function retrieveResources( $options = null )
    {
        $_resources = parent::retrieveResources( $options );

        $_asComponents = Option::getBool( $options, 'as_access_components' );
        if ( Option::getBool( $this->_requestPayload, 'include_procs' ) || $_asComponents )
        {
            $_namesOnly = Option::getBool( $this->_requestPayload, 'names_only' );

            try
            {
                $_result = SqlDbUtilities::listStoredProcedures( $this->_dbConn );
                if ( !empty( $_result ) )
                {
                    $_name = static::STORED_PROC_RESOURCE . '/';
                    $_access = $this->getPermissions( $_name );
                    if ( !empty( $_access ) )
                    {
                        if ( $_namesOnly || $_asComponents )
                        {
                            $_resources[] = $_name;
                            if ( $_asComponents )
                            {
                                $_resources[] = $_name . '*';
                            }
                        }
                        else
                        {
                            $_resources[] = array('name' => $_name, 'access' => $_access);
                        }
                    }

                    foreach ( $_result as $_name )
                    {
                        $_name = static::STORED_PROC_RESOURCE . '/' . $_name;
                        $_access = $this->getPermissions( $_name );
                        if ( !empty( $_access ) )
                        {
                            if ( $_namesOnly || $_asComponents )
                            {
                                $_resources[] = $_name;
                            }
                            else
                            {
                                $_resources[] = array('name' => $_name, 'access' => $_access);
                            }
                        }
                    }
                }
            }
            catch ( RestException $_ex )
            {
//                throw $_ex;
            }
            catch ( \Exception $_ex )
            {
//                throw new InternalServerErrorException( "Failed to list resources for this service.\n{$_ex->getMessage(
//                )}" );
            }
        }

        return $_resources;
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleStoredProcedures()
    {
        switch ( $this->_action )
        {
            /** @noinspection PhpMissingBreakStatementInspection */
            case static::GET:
                if ( empty( $this->_resourceId ) )
                {
                    $_result = $this->listProcedures();

                    return array('resource' => $_result);
                }

            case static::POST:
                $_params = Option::get( $this->_requestPayload, 'params' );
                $_wrapper = Option::get( $this->_requestPayload, 'wrapper' );
                $_schema = Option::get( $this->_requestPayload, 'schema' );

                return SqlDbUtilities::callProcedure(
                    $this->_dbConn,
                    $this->_resourceId,
                    $_params,
                    $_schema,
                    $_wrapper
                );
                break;

//            case static::PUT:
//            case static::PATCH:
//            case static::MERGE:
//            case static::DELETE:
            default:
                throw new BadRequestException( 'Verb not currently supported on stored procedures.' );
                break;
        }
    }

    /**
     * @throws \Exception
     * @return array
     */
    public function listProcedures()
    {
        $_exclude = '';
        if ( $this->_isNative )
        {
            // check for system tables
            $_exclude = SystemManager::SYSTEM_TABLE_PREFIX;
        }

        $_namesOnly = Option::getBool( $this->_requestPayload, 'names_only' );
        $_resources = array();

        try
        {
            $_result = SqlDbUtilities::listStoredProcedures( $this->_dbConn, '', $_exclude );
            foreach ( $_result as $_name )
            {
                $_access = $this->getPermissions( static::STORED_PROC_RESOURCE . '/' . $_name );
                if ( !empty( $_access ) )
                {
                    if ( $_namesOnly )
                    {
                        $_resources[] = $_name;
                    }
                    else
                    {
                        $_resources[] = array('name' => $_name, 'access' => $_access);
                    }
                }
            }

            return $_resources;
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to list resources for this service.\n{$_ex->getMessage(
            )}" );
        }
    }

    /**
     * @param string $name
     * @param array  $params
     * @param array  $schema
     * @param string $wrapper
     *
     * @throws \Exception
     * @return array
     */
    public function callProcedure( $name, $params = null, $schema = null, $wrapper = null )
    {
        return SqlDbUtilities::callProcedure( $this->_dbConn, $name, $params, $schema, $wrapper );
    }

    //-------- Table Records Operations ---------------------
    // records is an array of field arrays

    /**
     * {@inheritdoc}
     */
    public function updateRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = SqlDbUtilities::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $table = $this->correctTableName( $table );

        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_fields = Option::get( $extras, 'fields' );
        $_related = Option::get( $extras, 'related' );
        $_allowRelatedDelete = Option::getBool( $extras, 'allow_related_delete', false );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        try
        {
            $_fieldsInfo = $this->getFieldsInfo( $table );
            $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
            $_relatedInfo = $this->describeTableRelated( $table );
            $_fields = ( empty( $_fields ) ) ? $_idFields : $_fields;
            $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );
            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

            $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );

            // build filter string if necessary, add server-side filters if necessary
            $_criteria = $this->_convertFilterToNative( $filter, $params, $_ssFilters );
            $_where = Option::get( $_criteria, 'where' );
            $_params = Option::get( $_criteria, 'params', array() );

            if ( !empty( $_parsed ) )
            {
                /** @var \CDbCommand $_command */
                $_command = $this->_dbConn->createCommand();
                $_command->update( $table, $_parsed, $_where, $_params );
            }

            $_results = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );

            if ( !empty( $_relatedInfo ) )
            {
                // update related info
                foreach ( $_results as $_row )
                {
                    $_id = static::checkForIds( $_row, $_idsInfo, $extras );
                    $this->updateRelations( $table, $record, $_id, $_relatedInfo, $_allowRelatedDelete );
                }
                // get latest with related changes if requested
                if ( !empty( $_related ) )
                {
                    $_results = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
                }
            }

            return $_results;
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to update records in '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function patchRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        // currently the same as update here
        return $this->updateRecordsByFilter( $table, $record, $filter, $params, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function truncateTable( $table, $extras = array() )
    {
        // truncate the table, return success
        $table = $this->correctTableName( $table );
        try
        {
            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();

            // build filter string if necessary, add server-side filters if necessary
            $_ssFilters = Option::get( $extras, 'ss_filters' );
            $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
            if ( !empty( $_serverFilter ) )
            {
                $_command->delete( $table, $_serverFilter['filter'], $_serverFilter['params'] );
            }
            else
            {
                $_command->truncateTable( $table );
            }

            return array('success' => true);
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to delete records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecordsByFilter( $table, $filter, $params = array(), $extras = array() )
    {
        if ( empty( $filter ) )
        {
            throw new BadRequestException( "Filter for delete request can not be empty." );
        }

        $table = $this->correctTableName( $table );

        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        try
        {
            $_fieldsInfo = $this->getFieldsInfo( $table );
            /*$_idsInfo = */
            $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
            $_fields = ( empty( $_fields ) ) ? $_idFields : $_fields;
            $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );
            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

            // build filter string if necessary, add server-side filters if necessary
            $_criteria = $this->_convertFilterToNative( $filter, $params, $_ssFilters );
            $_where = Option::get( $_criteria, 'where' );
            $_params = Option::get( $_criteria, 'params', array() );

            $_results = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );

            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();
            $_command->delete( $table, $_where, $_params );

            return $_results;
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to delete records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() )
    {
        $table = $this->correctTableName( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        try
        {
            $_fieldsInfo = $this->getFieldsInfo( $table );
            $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );
            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

            // build filter string if necessary, add server-side filters if necessary
            $_criteria = $this->_convertFilterToNative( $filter, $params, $_ssFilters );
            $_where = Option::get( $_criteria, 'where' );
            $_params = Option::get( $_criteria, 'params', array() );

            return $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to retrieve records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    // Helper methods

    protected function _recordQuery( $table, $select, $where, $bind_values, $bind_columns, $extras )
    {
        $_order = Option::get( $extras, 'order' );
        $_limit = intval( Option::get( $extras, 'limit', 0 ) );
        $_offset = intval( Option::get( $extras, 'offset', 0 ) );
        $_maxAllowed = static::getMaxRecordsReturnedLimit();
        $_needLimit = false;

        // use query builder
        /** @var \CDbCommand $_command */
        $_command = $this->_dbConn->createCommand();
        $_command->select( $select );

        $_from = $table;
        $_command->from( $_from );

        if ( !empty( $where ) )
        {
            $_command->where( $where );
        }
        if ( !empty( $bind_values ) )
        {
            $_command->bindValues( $bind_values );
        }

        if ( !empty( $_order ) )
        {
            $_command->order( $_order );
        }
        if ( $_offset > 0 )
        {
            $_command->offset( $_offset );
        }
        if ( ( $_limit < 1 ) || ( $_limit > $_maxAllowed ) )
        {
            // impose a limit to protect server
            $_limit = $_maxAllowed;
            $_needLimit = true;
        }
        $_command->limit( $_limit );

        $this->checkConnection();
        $_reader = $_command->query();
        $_data = array();
        $_dummy = array();
        foreach ( $bind_columns as $_binding )
        {
            $_name = Option::get( $_binding, 'name' );
            $_type = Option::get( $_binding, 'pdo_type' );
            $_reader->bindColumn( $_name, $_dummy[$_name], $_type );
        }
        $_reader->setFetchMode( \PDO::FETCH_BOUND );
        $_row = 0;
        while ( false !== $_reader->read() )
        {
            $_temp = array();
            foreach ( $bind_columns as $_binding )
            {
                $_name = Option::get( $_binding, 'name' );
                $_type = Option::get( $_binding, 'php_type' );
                $_value = Option::get( $_dummy, $_name );
                if ( 'float' == $_type )
                {
                    $_value = floatval( $_value );
                }
                $_temp[$_name] = $_value;
            }

            $_data[$_row++] = $_temp;
        }

        $_meta = array();
        $_includeCount = Option::getBool( $extras, 'include_count', false );
        // count total records
        if ( $_includeCount || $_needLimit )
        {
            $_command->reset();
            $_command->select( '(COUNT(*)) as ' . $this->_dbConn->quoteColumnName( 'count' ) );
            $_command->from( $_from );
            if ( !empty( $where ) )
            {
                $_command->where( $where );
            }
            if ( !empty( $bind_values ) )
            {
                $_command->bindValues( $bind_values );
            }

            $_count = intval( $_command->queryScalar() );

            if ( $_includeCount || $_count > $_maxAllowed )
            {
                $_meta['count'] = $_count;
            }
            if ( ( $_count - $_offset ) > $_limit )
            {
                $_meta['next'] = $_offset + $_limit + 1;
            }
        }

        if ( Option::getBool( $extras, 'include_schema', false ) )
        {
            $_meta['schema'] = $this->describeTable( $table );
        }

        $_related = Option::get( $extras, 'related' );
        if ( !empty( $_related ) )
        {
            $_relations = $this->describeTableRelated( $table );
            foreach ( $_data as $_key => $_temp )
            {
                $_data[$_key] = $this->retrieveRelatedRecords( $_temp, $_relations, $_related );
            }
        }

        if ( !empty( $_meta ) )
        {
            $_data['meta'] = $_meta;
        }

        return $_data;
    }

    /**
     * @param $name
     *
     * @return array
     * @throws \Exception
     */
    protected function getFieldsInfo( $name )
    {
        if ( isset( $this->_fieldCache[$name] ) )
        {
            return $this->_fieldCache[$name];
        }

        $_extras = SqlDbUtilities::getSchemaExtrasForTables( 0, $name );
        $_fields = SqlDbUtilities::describeTableFields( $this->_dbConn, $name, null, $_extras );
        $this->_fieldCache[$name] = $_fields;

        return $_fields;
    }

    /**
     * @param $name
     *
     * @return array
     * @throws \Exception
     */
    protected function describeTableRelated( $name )
    {
        if ( isset( $this->_relatedCache[$name] ) )
        {
            return $this->_relatedCache[$name];
        }

        $relations = SqlDbUtilities::describeTableRelated( $this->_dbConn, $name );
        $relatives = array();
        foreach ( $relations as $relation )
        {
            $how = Option::get( $relation, 'name', '' );
            $relatives[$how] = $relation;
        }
        $this->_relatedCache[$name] = $relatives;

        return $relatives;
    }

    /**
     * Take in a ANSI SQL filter string (WHERE clause)
     * or our generic NoSQL filter array or partial record
     * and parse it to the service's native filter criteria.
     * The filter string can have substitution parameters such as
     * ':name', in which case an associative array is expected,
     * for value substitution.
     *
     * @param string | array $filter     SQL WHERE clause filter string
     * @param array          $params     Array of substitution values
     * @param array          $ss_filters Server-side filters to apply
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return mixed
     */
    protected function _convertFilterToNative( $filter, $params = array(), $ss_filters = array() )
    {
        // interpret any parameter values as lookups
        $params = static::interpretRecordValues( $params );

        if ( !is_array( $filter ) )
        {
            // todo parse client filter?
            Session::replaceLookupsInStrings( $filter );
            $_filterString = $filter;
            $_serverFilter = $this->buildQueryStringFromData( $ss_filters, true );
            if ( !empty( $_serverFilter ) )
            {
                if ( empty( $filter ) )
                {
                    $_filterString = $_serverFilter['filter'];
                }
                else
                {
                    $_filterString = '(' . $_filterString . ') AND (' . $_serverFilter['filter'] . ')';
                }
                $params = array_merge( $params, $_serverFilter['params'] );
            }

            return array('where' => $_filterString, 'params' => $params);
        }
        else
        {
            // todo parse client filter?
            $_filterArray = $filter;
            $_serverFilter = $this->buildQueryStringFromData( $ss_filters, true );
            if ( !empty( $_serverFilter ) )
            {
                if ( empty( $filter ) )
                {
                    $_filterArray = $_serverFilter['filter'];
                }
                else
                {
                    $_filterArray = array('AND', $_filterArray, $_serverFilter['filter']);
                }
                $params = array_merge( $params, $_serverFilter['params'] );
            }

            return array('where' => $_filterArray, 'params' => $params);
        }
    }

    /**
     * @param array $record
     * @param array $fields_info
     * @param array $filter_info
     * @param bool  $for_update
     * @param array $old_record
     *
     * @return array
     * @throws \Exception
     */
    protected function parseRecord( $record, $fields_info, $filter_info = null, $for_update = false, $old_record = null )
    {
        $record = $this->interpretRecordValues( $record );

        $_parsed = array();
//        $record = DataFormat::arrayKeyLower( $record );
        $_keys = array_keys( $record );
        $_values = array_values( $record );
        foreach ( $fields_info as $_fieldInfo )
        {
//            $name = strtolower( Option::get( $field_info, 'name', '' ) );
            $_name = Option::get( $_fieldInfo, 'name', '' );
            $_type = Option::get( $_fieldInfo, 'type' );
            $_dbType = Option::get( $_fieldInfo, 'db_type' );

            // add or override for specific fields
            switch ( $_type )
            {
                case 'timestamp_on_create':
                    if ( !$for_update )
                    {
                        switch ( $this->_driverType )
                        {
                            case SqlDbUtilities::DRV_DBLIB:
                            case SqlDbUtilities::DRV_SQLSRV:
                                $_parsed[$_name] = new \CDbExpression( '(SYSDATETIMEOFFSET())' );
                                break;
                            default:
                                $_parsed[$_name] = new \CDbExpression( '(NOW())' );
                                break;
                        }
                    }
                    break;
                case 'timestamp_on_update':
                    switch ( $this->_driverType )
                    {
                        case SqlDbUtilities::DRV_DBLIB:
                        case SqlDbUtilities::DRV_SQLSRV:
                            $_parsed[$_name] = new \CDbExpression( '(SYSDATETIMEOFFSET())' );
                            break;
                        default:
                            $_parsed[$_name] = new \CDbExpression( '(NOW())' );
                            break;
                    }
                    break;
                case 'user_id_on_create':
                    if ( !$for_update )
                    {
                        $userId = Session::getCurrentUserId();
                        if ( isset( $userId ) )
                        {
                            $_parsed[$_name] = $userId;
                        }
                    }
                    break;
                case 'user_id_on_update':
                    $userId = Session::getCurrentUserId();
                    if ( isset( $userId ) )
                    {
                        $_parsed[$_name] = $userId;
                    }
                    break;
                default:
                    $_pos = array_search( $_name, $_keys );
                    if ( false !== $_pos )
                    {
                        $_fieldVal = Option::get( $_values, $_pos );
                        // due to conversion from XML to array, null or empty xml elements have the array value of an empty array
                        if ( is_array( $_fieldVal ) && empty( $_fieldVal ) )
                        {
                            $_fieldVal = null;
                        }

                        // overwrite some undercover fields
                        if ( Option::getBool( $_fieldInfo, 'auto_increment', false ) )
                        {
                            unset( $_keys[$_pos] );
                            unset( $_values[$_pos] );
                            continue; // should I error this?
                        }
                        if ( is_null( $_fieldVal ) && !Option::getBool( $_fieldInfo, 'allow_null' ) )
                        {
                            throw new BadRequestException( "Field '$_name' can not be NULL." );
                        }

                        /** validations **/

                        $_validations = Option::get( $_fieldInfo, 'validation' );
                        if ( !is_array( $_validations ) )
                        {
                            // backwards compatible with old strings
                            $_validations = array_map( 'trim', explode( ',', $_validations ) );
                            $_validations = array_flip( $_validations );
                        }

                        if ( !static::validateFieldValue(
                            $_name,
                            $_fieldVal,
                            $_validations,
                            $for_update,
                            $_fieldInfo
                        )
                        )
                        {
                            unset( $_keys[$_pos] );
                            unset( $_values[$_pos] );
                            continue;
                        }

                        if ( !is_null( $_fieldVal ) )
                        {
                            switch ( $this->_driverType )
                            {
                                case SqlDbUtilities::DRV_DBLIB:
                                case SqlDbUtilities::DRV_SQLSRV:
                                    switch ( $_dbType )
                                    {
                                        case 'bit':
                                            $_fieldVal = ( Scalar::boolval( $_fieldVal ) ? 1 : 0 );
                                            break;
                                    }
                                    break;
                                case SqlDbUtilities::DRV_MYSQL:
                                    switch ( $_dbType )
                                    {
                                        case 'tinyint(1)':
                                            $_fieldVal = ( Scalar::boolval( $_fieldVal ) ? 1 : 0 );
                                            break;
                                    }
                                    break;
                            }
                            switch ( SqlDbUtilities::determinePhpConversionType( $_type ) )
                            {
                                case 'int':
                                    if ( !is_int( $_fieldVal ) )
                                    {
                                        if ( ( '' === $_fieldVal ) && Option::getBool( $_fieldInfo, 'allow_null' ) )
                                        {
                                            $_fieldVal = null;
                                        }
                                        elseif ( !( ctype_digit( $_fieldVal ) ) )
                                        {
                                            throw new BadRequestException( "Field '$_name' must be a valid integer." );
                                        }
                                        else
                                        {
                                            $_fieldVal = intval( $_fieldVal );
                                        }
                                    }
                                    break;
                                default:
                            }
                        }
                        $_parsed[$_name] = $_fieldVal;
                        unset( $_keys[$_pos] );
                        unset( $_values[$_pos] );
                    }
                    else
                    {
                        // if field is required, kick back error
                        if ( Option::getBool( $_fieldInfo, 'required' ) && !$for_update )
                        {
                            throw new BadRequestException( "Required field '$_name' can not be NULL." );
                        }
                        break;
                    }
                    break;
            }
        }

        if ( !empty( $filter_info ) )
        {
            $this->validateRecord( $record, $filter_info, $for_update, $old_record );
        }

        return $_parsed;
    }

    /**
     * @param string $table
     * @param array  $record Record containing relationships by name if any
     * @param array  $id     Array of id field and value, only one supported currently
     * @param array  $avail_relations
     * @param bool   $allow_delete
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return void
     */
    protected function updateRelations( $table, $record, $id, $avail_relations, $allow_delete = false )
    {
        // update currently only supports one id field
        if ( is_array( $id ) )
        {
            reset( $id );
            $id = @current( $id );
        }

        $keys = array_keys( $record );
        $values = array_values( $record );
        foreach ( $avail_relations as $relationInfo )
        {
            $name = Option::get( $relationInfo, 'name' );
            $pos = array_search( $name, $keys );
            if ( false !== $pos )
            {
                $relations = Option::get( $values, $pos );
                $relationType = Option::get( $relationInfo, 'type' );
                switch ( $relationType )
                {
                    case 'belongs_to':
                        /*
                    "name": "role_by_role_id",
                    "type": "belongs_to",
                    "ref_table": "role",
                    "ref_field": "id",
                    "field": "role_id"
                    */
                        // todo handle this?
                        break;
                    case 'has_many':
                        /*
                    "name": "users_by_last_modified_by_id",
                    "type": "has_many",
                    "ref_table": "user",
                    "ref_field": "last_modified_by_id",
                    "field": "id"
                    */
                        $relatedTable = Option::get( $relationInfo, 'ref_table' );
                        $relatedField = Option::get( $relationInfo, 'ref_field' );
                        $this->assignManyToOne(
                            $table,
                            $id,
                            $relatedTable,
                            $relatedField,
                            $relations,
                            $allow_delete
                        );
                        break;
                    case 'many_many':
                        /*
                    "name": "roles_by_user",
                    "type": "many_many",
                    "ref_table": "role",
                    "ref_field": "id",
                    "join": "user(default_app_id,role_id)"
                    */
                        $relatedTable = Option::get( $relationInfo, 'ref_table' );
                        $join = Option::get( $relationInfo, 'join', '' );
                        $joinTable = substr( $join, 0, strpos( $join, '(' ) );
                        $other = explode( ',', substr( $join, strpos( $join, '(' ) + 1, -1 ) );
                        $joinLeftField = trim( Option::get( $other, 0, '' ) );
                        $joinRightField = trim( Option::get( $other, 1, '' ) );
                        $this->assignManyToOneByMap(
                            $table,
                            $id,
                            $relatedTable,
                            $joinTable,
                            $joinLeftField,
                            $joinRightField,
                            $relations
                        );
                        break;
                    default:
                        throw new InternalServerErrorException( 'Invalid relationship type detected.' );
                        break;
                }
                unset( $keys[$pos] );
                unset( $values[$pos] );
            }
        }
    }

    /**
     * @param array $record
     *
     * @return string
     */
    protected function parseRecordForSqlInsert( $record )
    {
        $values = '';
        foreach ( $record as $value )
        {
            $fieldVal = ( is_null( $value ) ) ? "NULL" : $this->_dbConn->quoteValue( $value );
            $values .= ( !empty( $values ) ) ? ',' : '';
            $values .= $fieldVal;
        }

        return $values;
    }

    /**
     * @param array $record
     *
     * @return string
     */
    protected function parseRecordForSqlUpdate( $record )
    {
        $out = '';
        foreach ( $record as $key => $value )
        {
            $fieldVal = ( is_null( $value ) ) ? "NULL" : $this->_dbConn->quoteValue( $value );
            $out .= ( !empty( $out ) ) ? ',' : '';
            $out .= "$key = $fieldVal";
        }

        return $out;
    }

    /**
     * @param        $fields
     * @param        $avail_fields
     * @param bool   $as_quoted_string
     * @param string $prefix
     * @param string $fields_as
     *
     * @return string
     */
    protected function parseFieldsForSqlSelect( $fields, $avail_fields, $as_quoted_string = false, $prefix = '', $fields_as = '' )
    {
        if ( empty( $fields ) || ( '*' === $fields ) )
        {
            $fields = SqlDbUtilities::listAllFieldsFromDescribe( $avail_fields );
        }

        $field_arr = ( !is_array( $fields ) ) ? array_map( 'trim', explode( ',', $fields ) ) : $fields;
        $as_arr = ( !is_array( $fields_as ) ) ? array_map( 'trim', explode( ',', $fields_as ) ) : $fields_as;
        if ( !$as_quoted_string )
        {
            // yii will not quote anything if any of the fields are expressions
        }
        $outArray = array();
        $bindArray = array();
        for ( $i = 0, $size = sizeof( $field_arr ); $i < $size; $i++ )
        {
            $field = $field_arr[$i];
            $as = ( isset( $as_arr[$i] ) ? $as_arr[$i] : '' );
            $context = ( empty( $prefix ) ? $field : $prefix . '.' . $field );
            $out_as = ( empty( $as ) ? $field : $as );
            if ( $as_quoted_string )
            {
                $context = $this->_dbConn->quoteColumnName( $context );
                $out_as = $this->_dbConn->quoteColumnName( $out_as );
            }
            // find the type
            $field_info = SqlDbUtilities::getFieldFromDescribe( $field, $avail_fields );
            $dbType = Option::get( $field_info, 'db_type', '' );
            $type = Option::get( $field_info, 'type', '' );

            $bindArray[] = array(
                'name'     => $field,
                'pdo_type' => SqlDbUtilities::determinePdoBindingType( $type, $dbType ),
                'php_type' => SqlDbUtilities::determinePhpConversionType( $type ),
            );

            // todo fix special cases - maybe after retrieve
            switch ( $dbType )
            {
                case 'datetime':
                case 'datetimeoffset':
                    switch ( $this->_driverType )
                    {
                        case SqlDbUtilities::DRV_DBLIB:
                        case SqlDbUtilities::DRV_SQLSRV:
                            if ( !$as_quoted_string )
                            {
                                $context = $this->_dbConn->quoteColumnName( $context );
                                $out_as = $this->_dbConn->quoteColumnName( $out_as );
                            }
                            $out = "(CONVERT(nvarchar(30), $context, 127)) AS $out_as";
                            break;
                        default:
                            $out = $context;
                            break;
                    }
                    break;
                case 'geometry':
                case 'geography':
                case 'hierarchyid':
                    switch ( $this->_driverType )
                    {
                        case SqlDbUtilities::DRV_DBLIB:
                        case SqlDbUtilities::DRV_SQLSRV:
                            if ( !$as_quoted_string )
                            {
                                $context = $this->_dbConn->quoteColumnName( $context );
                                $out_as = $this->_dbConn->quoteColumnName( $out_as );
                            }
                            $out = "($context.ToString()) AS $out_as";
                            break;
                        default:
                            $out = $context;
                            break;
                    }
                    break;
                default :
                    $out = $context;
                    if ( !empty( $as ) )
                    {
                        $out .= ' AS ' . $out_as;
                    }
                    break;
            }

            $outArray[] = $out;
        }

        return array('fields' => $outArray, 'bindings' => $bindArray);
    }

    /**
     * @param        $fields
     * @param        $avail_fields
     * @param string $prefix
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return string
     */
    public function parseOutFields( $fields, $avail_fields, $prefix = 'INSERTED' )
    {
        if ( empty( $fields ) )
        {
            return '';
        }

        $out_str = '';
        $field_arr = array_map( 'trim', explode( ',', $fields ) );
        foreach ( $field_arr as $field )
        {
            // find the type
            if ( false === SqlDbUtilities::findFieldFromDescribe( $field, $avail_fields ) )
            {
                throw new BadRequestException( "Invalid field '$field' selected for output." );
            }
            if ( !empty( $out_str ) )
            {
                $out_str .= ', ';
            }
            $out_str .= $prefix . '.' . $this->_dbConn->quoteColumnName( $field );
        }

        return $out_str;
    }

    // generic assignments

    /**
     * @param $relations
     * @param $data
     * @param $requests
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array
     */
    protected function retrieveRelatedRecords( $data, $relations, $requests )
    {
        if ( empty( $relations ) || empty( $requests ) )
        {
            return $data;
        }

        $_relatedData = array();
        $_relatedExtras = array('limit' => static::MAX_RECORDS_RETURNED, 'fields' => '*');
        if ( '*' == $requests )
        {
            foreach ( $relations as $_name => $_relation )
            {
                if ( empty( $_relation ) )
                {
                    throw new BadRequestException( "Empty relationship '$_name' found." );
                }
                $_relatedData[$_name] = $this->retrieveRelationRecords( $data, $_relation, $_relatedExtras );
            }
        }
        else
        {
            foreach ( $requests as $_request )
            {
                $_name = Option::get( $_request, 'name' );
                $_relation = Option::get( $relations, $_name );
                if ( empty( $_relation ) )
                {
                    throw new BadRequestException( "Invalid relationship '$_name' requested." );
                }

                $_relatedExtras['fields'] = Option::get( $_request, 'fields' );
                $_relatedData[$_name] = $this->retrieveRelationRecords( $data, $_relation, $_relatedExtras );
            }
        }

        return array_merge( $data, $_relatedData );
    }

    protected function retrieveRelationRecords( $data, $relation, $extras )
    {
        if ( empty( $relation ) )
        {
            return null;
        }

        $relationType = Option::get( $relation, 'type' );
        $relatedTable = Option::get( $relation, 'ref_table' );
        $relatedField = Option::get( $relation, 'ref_field' );
        $field = Option::get( $relation, 'field' );
        $fieldVal = Option::get( $data, $field );

        // do we have permission to do so?
        $this->validateTableAccess( $relatedTable, static::GET );

        switch ( $relationType )
        {
            case 'belongs_to':
                $_fields = Option::get( $extras, 'fields' );
                $_ssFilters = Option::get( $extras, 'ss_filters' );
                $_fieldsInfo = $this->getFieldsInfo( $relatedTable );
                $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
                $_bindings = Option::get( $_result, 'bindings' );
                $_fields = Option::get( $_result, 'fields' );
                $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                // build filter string if necessary, add server-side filters if necessary
                $_criteria = $this->_convertFilterToNative( "$relatedField = '$fieldVal'", array(), $_ssFilters );
                $_where = Option::get( $_criteria, 'where' );
                $_params = Option::get( $_criteria, 'params', array() );

                $relatedRecords =
                    $this->_recordQuery( $relatedTable, $_fields, $_where, $_params, $_bindings, $extras );
                if ( !empty( $relatedRecords ) )
                {
                    return Option::get( $relatedRecords, 0 );
                }
                break;
            case 'has_many':
                $_fields = Option::get( $extras, 'fields' );
                $_ssFilters = Option::get( $extras, 'ss_filters' );
                $_fieldsInfo = $this->getFieldsInfo( $relatedTable );
                $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
                $_bindings = Option::get( $_result, 'bindings' );
                $_fields = Option::get( $_result, 'fields' );
                $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                // build filter string if necessary, add server-side filters if necessary
                $_criteria = $this->_convertFilterToNative( "$relatedField = '$fieldVal'", array(), $_ssFilters );
                $_where = Option::get( $_criteria, 'where' );
                $_params = Option::get( $_criteria, 'params', array() );

                return $this->_recordQuery( $relatedTable, $_fields, $_where, $_params, $_bindings, $extras );
                break;
            case 'many_many':
                $join = Option::get( $relation, 'join', '' );
                $joinTable = substr( $join, 0, strpos( $join, '(' ) );
                $other = explode( ',', substr( $join, strpos( $join, '(' ) + 1, -1 ) );
                $joinLeftField = trim( Option::get( $other, 0 ) );
                $joinRightField = trim( Option::get( $other, 1 ) );
                if ( !empty( $joinLeftField ) && !empty( $joinRightField ) )
                {
                    $_fieldsInfo = $this->getFieldsInfo( $joinTable );
                    $_result = $this->parseFieldsForSqlSelect( $joinRightField, $_fieldsInfo );
                    $_bindings = Option::get( $_result, 'bindings' );
                    $_fields = Option::get( $_result, 'fields' );
                    $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                    // build filter string if necessary, add server-side filters if necessary
                    $_criteria = $this->_convertFilterToNative( "$joinLeftField = '$fieldVal'" );
                    $_where = Option::get( $_criteria, 'where' );
                    $_params = Option::get( $_criteria, 'params', array() );

                    $joinData = $this->_recordQuery( $joinTable, $_fields, $_where, $_params, $_bindings, $extras );
                    if ( !empty( $joinData ) )
                    {
                        $relatedIds = array();
                        foreach ( $joinData as $record )
                        {
                            $relatedIds[] = Option::get( $record, $joinRightField );
                        }
                        if ( !empty( $relatedIds ) )
                        {
                            $_fields = Option::get( $extras, 'fields' );
                            $_fieldsInfo = $this->getFieldsInfo( $relatedTable );
                            $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
                            $_bindings = Option::get( $_result, 'bindings' );
                            $_fields = Option::get( $_result, 'fields' );
                            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                            $_where = array('in', $relatedField, $relatedIds);

                            return $this->_recordQuery(
                                $relatedTable,
                                $_fields,
                                $_where,
                                $_params,
                                $_bindings,
                                $extras
                            );
                        }
                    }
                }
                break;
            default:
                throw new InternalServerErrorException( 'Invalid relationship type detected.' );
                break;
        }

        return null;
    }

    /**
     * @param string $one_table
     * @param string $one_id
     * @param string $many_table
     * @param string $many_field
     * @param array  $many_records
     * @param bool   $allow_delete
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return void
     */
    protected function assignManyToOne( $one_table, $one_id, $many_table, $many_field, $many_records = array(), $allow_delete = false )
    {
        if ( empty( $one_id ) )
        {
            throw new BadRequestException( "The $one_table id can not be empty." );
        }

        try
        {
            $_manyFields = $this->getFieldsInfo( $many_table );
            $_pkField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $_manyFields );
            $_fieldInfo = SqlDbUtilities::getFieldFromDescribe( $many_field, $_manyFields );
            $_deleteRelated = ( !Option::getBool( $_fieldInfo, 'allow_null' ) && $allow_delete );
            $_relateMany = array();
            $_disownMany = array();
            $_createMany = array();
            $_updateMany = array();
            $_deleteMany = array();

            foreach ( $many_records as $_item )
            {
                $_id = Option::get( $_item, $_pkField );
                if ( empty( $_id ) )
                {
                    // create new child record
                    $_item[$many_field] = $one_id; // assign relationship
                    $_createMany[] = $_item;
                }
                else
                {
                    if ( array_key_exists( $many_field, $_item ) )
                    {
                        if ( null == Option::get( $_item, $many_field, null, true ) )
                        {
                            // disown this child or delete them
                            if ( $_deleteRelated )
                            {
                                $_deleteMany[] = $_id;
                            }
                            elseif ( count( $_item ) > 1 )
                            {
                                $_item[$many_field] = null; // assign relationship
                                $_updateMany[] = $_item;
                            }
                            else
                            {
                                $_disownMany[] = $_id;
                            }

                            continue;
                        }
                    }

                    // update this child
                    if ( count( $_item ) > 1 )
                    {
                        $_item[$many_field] = $one_id; // assign relationship
                        $_updateMany[] = $_item;
                    }
                    else
                    {
                        $_relateMany[] = $_id;
                    }
                }
            }

            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();

            if ( !empty( $_createMany ) )
            {
                // create new children
                // do we have permission to do so?
                $this->validateTableAccess( $many_table, static::POST );
                $_ssFilters = Session::getServiceFilters( static::POST, $this->_apiName, $many_table );
                foreach ( $_createMany as $_record )
                {
                    $_parsed = $this->parseRecord( $_record, $_manyFields, $_ssFilters );
                    if ( empty( $_parsed ) )
                    {
                        throw new BadRequestException( 'No valid fields were found in record.' );
                    }

                    $_rows = $_command->insert( $many_table, $_parsed );
                    if ( 0 >= $_rows )
                    {
                        throw new InternalServerErrorException( "Creating related $many_table records failed." );
                    }
                }
            }

            if ( !empty( $_deleteMany ) )
            {
                // destroy linked children that can't stand alone - sounds sinister
                // do we have permission to do so?
                $this->validateTableAccess( $many_table, static::DELETE );
                $_ssFilters = Session::getServiceFilters( static::DELETE, $this->_apiName, $many_table );
                $_where = array();
                $_params = array();
                $_where[] = array('in', $_pkField, $_deleteMany);
                $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
                if ( !empty( $_serverFilter ) )
                {
                    $_where[] = $_serverFilter['filter'];
                    $_params = array_merge( $_params, $_serverFilter['params'] );
                }

                if ( count( $_where ) > 1 )
                {
                    array_unshift( $_where, 'AND' );
                }
                else
                {
                    $_where = $_where[0];
                }

                $_command->delete( $many_table, $_where, $_params );
//                if ( 0 >= $_rows )
//                {
//                    throw new NotFoundException( "Deleting related $many_table records failed." );
//                }
            }

            if ( !empty( $_updateMany ) || !empty( $_relateMany ) || !empty( $_disownMany ) )
            {
                // do we have permission to do so?
                $this->validateTableAccess( $many_table, static::PUT );
                $_ssFilters = Session::getServiceFilters( static::PUT, $this->_apiName, $many_table );

                if ( !empty( $_updateMany ) )
                {
                    // update existing and adopt new children
                    $_where = array();
                    $_params = array();
                    $_where[] = "$_pkField = :f_$_pkField";

                    $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
                    if ( !empty( $_serverFilter ) )
                    {
                        $_where[] = $_serverFilter['filter'];
                        $_params = array_merge( $_params, $_serverFilter['params'] );
                    }

                    if ( count( $_where ) > 1 )
                    {
                        array_unshift( $_where, 'AND' );
                    }
                    else
                    {
                        $_where = $_where[0];
                    }

                    foreach ( $_updateMany as $_record )
                    {
                        $_params[":f_$_pkField"] = Option::get( $_record, $_pkField );
                        $_parsed = $this->parseRecord( $_record, $_manyFields, $_ssFilters, true );
                        if ( empty( $_parsed ) )
                        {
                            throw new BadRequestException( 'No valid fields were found in record.' );
                        }

                        $_command->update( $many_table, $_parsed, $_where, $_params );
//                        if ( 0 >= $_rows )
//                        {
//                            throw new InternalServerErrorException( "Updating related $many_table records failed." );
//                        }
                    }
                }

                if ( !empty( $_relateMany ) )
                {
                    // adopt/relate/link unlinked children
                    $_where = array();
                    $_params = array();
                    $_where[] = array('in', $_pkField, $_relateMany);
                    $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
                    if ( !empty( $_serverFilter ) )
                    {
                        $_where[] = $_serverFilter['filter'];
                        $_params = array_merge( $_params, $_serverFilter['params'] );
                    }

                    if ( count( $_where ) > 1 )
                    {
                        array_unshift( $_where, 'AND' );
                    }
                    else
                    {
                        $_where = $_where[0];
                    }

                    $_updates = array($many_field => $one_id);
                    $_parsed = $this->parseRecord( $_updates, $_manyFields, $_ssFilters, true );
                    if ( !empty( $_parsed ) )
                    {
                        $_command->update( $many_table, $_parsed, $_where, $_params );
//                        if ( 0 >= $_rows )
//                        {
//                            throw new InternalServerErrorException( "Updating related $many_table records failed." );
//                        }
                    }
                }

                if ( !empty( $_disownMany ) )
                {
                    // disown/un-relate/unlink linked children
                    $_where = array();
                    $_params = array();
                    $_where[] = array('in', $_pkField, $_disownMany);
                    $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
                    if ( !empty( $_serverFilter ) )
                    {
                        $_where[] = $_serverFilter['filter'];
                        $_params = array_merge( $_params, $_serverFilter['params'] );
                    }

                    if ( count( $_where ) > 1 )
                    {
                        array_unshift( $_where, 'AND' );
                    }
                    else
                    {
                        $_where = $_where[0];
                    }

                    $_updates = array($many_field => null);
                    $_parsed = $this->parseRecord( $_updates, $_manyFields, $_ssFilters, true );
                    if ( !empty( $_parsed ) )
                    {
                        $_command->update( $many_table, $_parsed, $_where, $_params );
//                        if ( 0 >= $_rows )
//                        {
//                            throw new NotFoundException( 'No records were found using the given identifiers.' );
//                        }
                    }
                }
            }
        }
        catch ( \Exception $_ex )
        {
            throw new BadRequestException( "Failed to update many to one assignment.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * @param string $one_table
     * @param mixed  $one_id
     * @param string $many_table
     * @param string $map_table
     * @param string $one_field
     * @param string $many_field
     * @param array  $many_records
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return void
     */
    protected function assignManyToOneByMap( $one_table, $one_id, $many_table, $map_table, $one_field, $many_field, $many_records = array() )
    {
        if ( empty( $one_id ) )
        {
            throw new BadRequestException( "The $one_table id can not be empty." );
        }

        try
        {
            $oneFields = $this->getFieldsInfo( $one_table );
            $pkOneField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $oneFields );
            $manyFields = $this->getFieldsInfo( $many_table );
            $pkManyField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $manyFields );
            $mapFields = $this->getFieldsInfo( $map_table );
//			$pkMapField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $mapFields );

            $_result = $this->parseFieldsForSqlSelect( $many_field, $mapFields );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );
            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;
            $_params[":f_$one_field"] = $one_id;
            $maps =
                $this->_recordQuery( $map_table, $_fields, "$one_field = :f_$one_field", $_params, $_bindings, null );
            unset( $maps['meta'] );

            $createMap = array(); // map records to create
            $deleteMap = array(); // ids of 'many' records to delete from maps
            $createMany = array();
            $updateMany = array();
            foreach ( $many_records as $item )
            {
                $id = Option::get( $item, $pkManyField );
                if ( empty( $id ) )
                {
                    // create new many record, relationship created later
                    $createMany[] = $item;
                }
                else
                {
                    // pk fields exists, must be dealing with existing 'many' record
                    $oneLookup = "$one_table.$pkOneField";
                    if ( array_key_exists( $oneLookup, $item ) )
                    {
                        if ( null == Option::get( $item, $oneLookup, null, true ) )
                        {
                            // delete this relationship
                            $deleteMap[] = $id;
                            continue;
                        }
                    }

                    // update the 'many' record if more than the above fields
                    if ( count( $item ) > 1 )
                    {
                        $updateMany[] = $item;
                    }

                    // if relationship doesn't exist, create it
                    foreach ( $maps as $map )
                    {
                        if ( Option::get( $map, $many_field ) == $id )
                        {
                            continue 2; // got what we need from this one
                        }
                    }

                    $createMap[] = array($many_field => $id, $one_field => $one_id);
                }
            }

            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();

            if ( !empty( $createMany ) )
            {
                // do we have permission to do so?
                $this->validateTableAccess( $many_table, static::POST );
                $_ssManyFilters = Session::getServiceFilters( static::POST, $this->_apiName, $many_table );
                // create new many records
                foreach ( $createMany as $_record )
                {
                    $_parsed = $this->parseRecord( $_record, $manyFields, $_ssManyFilters );
                    if ( empty( $_parsed ) )
                    {
                        throw new BadRequestException( 'No valid fields were found in record.' );
                    }

                    $_rows = $_command->insert( $many_table, $_parsed );
                    if ( 0 >= $_rows )
                    {
                        throw new InternalServerErrorException( "Creating related $many_table records failed." );
                    }

                    $_manyId = (int)$this->_dbConn->lastInsertID;
                    if ( !empty( $_manyId ) )
                    {
                        $createMap[] = array($many_field => $_manyId, $one_field => $one_id);
                    }

                }
            }

            if ( !empty( $updateMany ) )
            {
                // update existing many records
                // do we have permission to do so?
                $this->validateTableAccess( $many_table, static::PUT );
                $_ssManyFilters = Session::getServiceFilters( static::PUT, $this->_apiName, $many_table );

                $_where = array();
                $_params = array();
                $_where[] = "$pkManyField = :f_$pkManyField";

                $_serverFilter = $this->buildQueryStringFromData( $_ssManyFilters, true );
                if ( !empty( $_serverFilter ) )
                {
                    $_where[] = $_serverFilter['filter'];
                    $_params = array_merge( $_params, $_serverFilter['params'] );
                }

                if ( count( $_where ) > 1 )
                {
                    array_unshift( $_where, 'AND' );
                }
                else
                {
                    $_where = $_where[0];
                }

                foreach ( $updateMany as $_record )
                {
                    $_params[":f_$pkManyField"] = Option::get( $_record, $pkManyField );
                    $_parsed = $this->parseRecord( $_record, $manyFields, $_ssManyFilters, true );
                    if ( empty( $_parsed ) )
                    {
                        throw new BadRequestException( 'No valid fields were found in record.' );
                    }

                    $_command->update( $many_table, $_parsed, $_where, $_params );
//                        if ( 0 >= $_rows )
//                        {
//                            throw new InternalServerErrorException( "Updating related $many_table records failed." );
//                        }
                }
            }

            if ( !empty( $createMap ) )
            {
                // do we have permission to do so?
                $this->validateTableAccess( $map_table, static::POST );
                $_ssMapFilters = Session::getServiceFilters( static::POST, $this->_apiName, $map_table );
                foreach ( $createMap as $_record )
                {
                    $_parsed = $this->parseRecord( $_record, $mapFields, $_ssMapFilters );
                    if ( empty( $_parsed ) )
                    {
                        throw new BadRequestException( "No valid fields were found in related $map_table record." );
                    }

                    $_rows = $_command->insert( $map_table, $_parsed );
                    if ( 0 >= $_rows )
                    {
                        throw new InternalServerErrorException( "Creating related $map_table records failed." );
                    }
                }
            }

            if ( !empty( $deleteMap ) )
            {
                // do we have permission to do so?
                $this->validateTableAccess( $map_table, static::DELETE );
                $_ssMapFilters = Session::getServiceFilters( static::DELETE, $this->_apiName, $map_table );
                $_where = array();
                $_params = array();
                $_where[] = "$one_field = '$one_id'";
                $_where[] = array('in', $many_field, $deleteMap);
                $_serverFilter = $this->buildQueryStringFromData( $_ssMapFilters, true );
                if ( !empty( $_serverFilter ) )
                {
                    $_where[] = $_serverFilter['filter'];
                    $_params = array_merge( $_params, $_serverFilter['params'] );
                }

                if ( count( $_where ) > 1 )
                {
                    array_unshift( $_where, 'AND' );
                }
                else
                {
                    $_where = $_where[0];
                }

                $_command->delete( $map_table, $_where, $_params );
//                if ( 0 >= $_rows )
//                {
//                    throw new NotFoundException( "Deleting related $map_table records failed." );
//                }
            }
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to update many to one map assignment.\n{$_ex->getMessage(
            )}" );
        }
    }

    protected function buildQueryStringFromData( $filter_info, $use_params = true )
    {
        $_filters = Option::get( $filter_info, 'filters' );
        if ( empty( $_filters ) )
        {
            return null;
        }

        $_sql = '';
        $_params = array();
        $_combiner = Option::get( $filter_info, 'filter_op', 'and' );
        foreach ( $_filters as $_key => $_filter )
        {
            if ( !empty( $_sql ) )
            {
                $_sql .= " $_combiner ";
            }

            $_name = Option::get( $_filter, 'name' );
            $_op = Option::get( $_filter, 'operator' );
            if ( empty( $_name ) || empty( $_op ) )
            {
                // log and bail
                throw new InternalServerErrorException( 'Invalid server-side filter configuration detected.' );
            }

            $_value = Option::get( $_filter, 'value' );
            $_value = static::interpretFilterValue( $_value );
            if ( $use_params )
            {
                $_paramName = ':ssf_' . $_name . '_' . $_key;
                $_params[$_paramName] = $_value;
                $_value = $_paramName;
            }
            else
            {
                if ( is_bool( $_value ) )
                {
                    $_value = $_value ? 'true' : 'false';
                }

                $_value = ( is_null( $_value ) ) ? 'NULL' : $this->_dbConn->quoteValue( $_value );
            }

            $_sql .= "$_name $_op $_value";
        }

        return array('filter' => $_sql, 'params' => $_params);
    }

    /**
     * Handle raw SQL Azure requests
     */
    protected function batchSqlQuery( $query, $bindings = array() )
    {
        if ( empty( $query ) )
        {
            throw new BadRequestException( '[NOQUERY]: No query string present in request.' );
        }
        $this->checkConnection();
        try
        {
            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand( $query );
            $reader = $command->query();
            $dummy = array();
            foreach ( $bindings as $binding )
            {
                $_name = Option::get( $binding, 'name' );
                $_type = Option::get( $binding, 'pdo_type' );
                $reader->bindColumn( $_name, $dummy[$_name], $_type );
            }

            $data = array();
            $rowData = array();
            while ( $row = $reader->read() )
            {
                $rowData[] = $row;
            }
            if ( 1 == count( $rowData ) )
            {
                $rowData = $rowData[0];
            }
            $data[] = $rowData;

            // Move to the next result and get results
            while ( $reader->nextResult() )
            {
                $rowData = array();
                while ( $row = $reader->read() )
                {
                    $rowData[] = $row;
                }
                if ( 1 == count( $rowData ) )
                {
                    $rowData = $rowData[0];
                }
                $data[] = $rowData;
            }

            return $data;
        }
        catch ( \Exception $_ex )
        {
            error_log( 'batchquery: ' . $_ex->getMessage() . PHP_EOL . $query );

            throw $_ex;
        }
    }

    /**
     * Handle SQL Db requests with output as array
     */
    public function singleSqlQuery( $query, $params = null )
    {
        if ( empty( $query ) )
        {
            throw new BadRequestException( '[NOQUERY]: No query string present in request.' );
        }
        $this->checkConnection();
        try
        {
            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand( $query );
            if ( isset( $params ) && !empty( $params ) )
            {
                $data = $command->queryAll( true, $params );
            }
            else
            {
                $data = $command->queryAll();
            }

            return $data;
        }
        catch ( \Exception $_ex )
        {
            error_log( 'singlequery: ' . $_ex->getMessage() . PHP_EOL . $query . PHP_EOL . print_r( $params, true ) );

            throw $_ex;
        }
    }

    /**
     * Handle SQL Db requests with output as array
     */
    public function singleSqlExecute( $query, $params = null )
    {
        if ( empty( $query ) )
        {
            throw new BadRequestException( '[NOQUERY]: No query string present in request.' );
        }
        $this->checkConnection();
        try
        {
            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand( $query );
            if ( isset( $params ) && !empty( $params ) )
            {
                $data = $command->execute( $params );
            }
            else
            {
                $data = $command->execute();
            }

            return $data;
        }
        catch ( \Exception $_ex )
        {
            error_log( 'singleexecute: ' . $_ex->getMessage() . PHP_EOL . $query . PHP_EOL . print_r( $params, true ) );

            throw $_ex;
        }
    }

    protected function getIdsInfo( $table, $fields_info = null, &$requested_fields = null, $requested_types = null )
    {
        $_idsInfo = array();
        if ( empty( $requested_fields ) )
        {
            $requested_fields = array();
            $_idsInfo = SqlDbUtilities::getPrimaryKeys( $fields_info );
            foreach ( $_idsInfo as $_info )
            {
                $requested_fields[] = Option::get( $_info, 'name' );
            }
        }
        else
        {
            if ( false !== $requested_fields = SqlDbUtilities::validateAsArray( $requested_fields, ',' ) )
            {
                foreach ( $requested_fields as $_field )
                {
                    $_idsInfo[] = SqlDbUtilities::getFieldFromDescribe( $_field, $fields_info );
                }
            }
        }

        return $_idsInfo;
    }

    /**
     * {@inheritdoc}
     */
    protected function initTransaction( $handle = null )
    {
        $this->_transaction = null;

        return parent::initTransaction( $handle );
    }

    /**
     * {@inheritdoc}
     */
    protected function addToTransaction( $record = null, $id = null, $extras = null, $rollback = false, $continue = false, $single = false )
    {
        if ( $rollback )
        {
            // sql transaction really only for rollback scenario, not batching
            if ( !isset( $this->_transaction ) )
            {
                $this->_transaction = $this->_dbConn->beginTransaction();
            }
        }

        $_fields = Option::get( $extras, 'fields' );
        $_fieldsInfo = Option::get( $extras, 'fields_info' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_updates = Option::get( $extras, 'updates' );
        $_idsInfo = Option::get( $extras, 'ids_info' );
        $_idFields = Option::get( $extras, 'id_fields' );
        $_needToIterate = ( $single || !$continue || ( 1 < count( $_idsInfo ) ) );

        $_related = Option::get( $extras, 'related' );
        $_requireMore = Option::getBool( $extras, 'require_more' ) || !empty( $_related );
        $_allowRelatedDelete = Option::getBool( $extras, 'allow_related_delete', false );
        $_relatedInfo = $this->describeTableRelated( $this->_transactionTable );

        $_where = array();
        $_params = array();
        if ( is_array( $id ) )
        {
            foreach ( $_idFields as $_name )
            {
                $_where[] = "$_name = :f_$_name";
                $_params[":f_$_name"] = Option::get( $id, $_name );
            }
        }
        else
        {
            $_name = Option::get( $_idFields, 0 );
            $_where[] = "$_name = :f_$_name";
            $_params[":f_$_name"] = $id;
        }

        $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
        if ( !empty( $_serverFilter ) )
        {
            $_where[] = $_serverFilter['filter'];
            $_params = array_merge( $_params, $_serverFilter['params'] );
        }

        if ( count( $_where ) > 1 )
        {
            array_unshift( $_where, 'AND' );
        }
        else
        {
            $_where = $_where[0];
        }

        /** @var \CDbCommand $_command */
        $_command = $this->_dbConn->createCommand();

        $_out = array();
        switch ( $this->getAction() )
        {
            case static::POST:
                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                $_rows = $_command->insert( $this->_transactionTable, $_parsed );
                if ( 0 >= $_rows )
                {
                    throw new InternalServerErrorException( "Record insert failed." );
                }

                if ( empty( $id ) )
                {
                    $id = array();
                    foreach ( $_idsInfo as $_info )
                    {
                        $_idName = Option::get( $_info, 'name' );
                        if ( Option::getBool( $_info, 'auto_increment' ) )
                        {
                            $_schema = $this->_dbConn->schema->getTable( $this->_transactionTable );
                            $_sequenceName = $_schema->sequenceName;
                            $id[$_idName] = (int)$this->_dbConn->getLastInsertID( $_sequenceName );
                        }
                        else
                        {
                            // must have been passed in with request
                            $id[$_idName] = Option::get( $_parsed, $_idName );
                        }
                    }
                }
                if ( !empty( $_relatedInfo ) )
                {
                    $this->updateRelations(
                        $this->_transactionTable,
                        $record,
                        $id,
                        $_relatedInfo,
                        $_allowRelatedDelete
                    );
                }

                $_idName = ( isset( $_idsInfo, $_idsInfo[0], $_idsInfo[0]['name'] ) ) ? $_idsInfo[0]['name'] : null;
                $_out = ( is_array( $id ) ) ? $id : array($_idName => $id);

                // add via record, so batch processing can retrieve extras
                if ( $_requireMore )
                {
                    parent::addToTransaction( $id );
                }
                break;

            case static::PUT:
            case static::MERGE:
            case static::PATCH:
                if ( !empty( $_updates ) )
                {
                    $record = $_updates;
                }

                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );

                // only update by ids can use batching, too complicated with ssFilters and related update
//                if ( !$_needToIterate && !empty( $_updates ) )
//                {
//                    return parent::addToTransaction( null, $id );
//                }

                if ( !empty( $_parsed ) )
                {
                    $_rows = $_command->update( $this->_transactionTable, $_parsed, $_where, $_params );
                    if ( 0 >= $_rows )
                    {
                        // could have just not updated anything, or could be bad id
                        $_fields = ( empty( $_fields ) ) ? $_idFields : $_fields;
                        $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
                        $_bindings = Option::get( $_result, 'bindings' );
                        $_fields = Option::get( $_result, 'fields' );
                        $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                        $_result = $this->_recordQuery(
                            $this->_transactionTable,
                            $_fields,
                            $_where,
                            $_params,
                            $_bindings,
                            $extras
                        );
                        if ( empty( $_result ) )
                        {
                            throw new NotFoundException( "Record with identifier '" .
                                                         print_r( $id, true ) .
                                                         "' not found." );
                        }
                    }
                }

                if ( !empty( $_relatedInfo ) )
                {
                    $this->updateRelations(
                        $this->_transactionTable,
                        $record,
                        $id,
                        $_relatedInfo,
                        $_allowRelatedDelete
                    );
                }

                $_idName = ( isset( $_idsInfo, $_idsInfo[0], $_idsInfo[0]['name'] ) ) ? $_idsInfo[0]['name'] : null;
                $_out = ( is_array( $id ) ) ? $id : array($_idName => $id);

                // add via record, so batch processing can retrieve extras
                if ( $_requireMore )
                {
                    parent::addToTransaction( $id );
                }
                break;

            case static::DELETE:
                if ( !$_needToIterate )
                {
                    return parent::addToTransaction( null, $id );
                }

                // add via record, so batch processing can retrieve extras
                if ( $_requireMore )
                {
                    $_fields = ( empty( $_fields ) ) ? $_idFields : $_fields;
                    $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
                    $_bindings = Option::get( $_result, 'bindings' );
                    $_fields = Option::get( $_result, 'fields' );
                    $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                    $_result = $this->_recordQuery(
                        $this->_transactionTable,
                        $_fields,
                        $_where,
                        $_params,
                        $_bindings,
                        $extras
                    );
                    if ( empty( $_result ) )
                    {
                        throw new NotFoundException( "Record with identifier '" .
                                                     print_r( $id, true ) .
                                                     "' not found." );
                    }

                    $_out = $_result[0];
                }

                $_rows = $_command->delete( $this->_transactionTable, $_where, $_params );
                if ( 0 >= $_rows )
                {
                    if ( empty( $_out ) )
                    {
                        // could have just not updated anything, or could be bad id
                        $_fields = ( empty( $_fields ) ) ? $_idFields : $_fields;
                        $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
                        $_bindings = Option::get( $_result, 'bindings' );
                        $_fields = Option::get( $_result, 'fields' );
                        $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                        $_result = $this->_recordQuery(
                            $this->_transactionTable,
                            $_fields,
                            $_where,
                            $_params,
                            $_bindings,
                            $extras
                        );
                        if ( empty( $_result ) )
                        {
                            throw new NotFoundException( "Record with identifier '" .
                                                         print_r( $id, true ) .
                                                         "' not found." );
                        }
                    }
                }

                if ( empty( $_out ) )
                {
                    $_idName = ( isset( $_idsInfo, $_idsInfo[0], $_idsInfo[0]['name'] ) ) ? $_idsInfo[0]['name'] : null;
                    $_out = ( is_array( $id ) ) ? $id : array($_idName => $id);
                }
                break;

            case static::GET:
                if ( !$_needToIterate )
                {
                    return parent::addToTransaction( null, $id );
                }

                $_fields = ( empty( $_fields ) ) ? $_idFields : $_fields;
                $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
                $_bindings = Option::get( $_result, 'bindings' );
                $_fields = Option::get( $_result, 'fields' );
                $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                $_result =
                    $this->_recordQuery( $this->_transactionTable, $_fields, $_where, $_params, $_bindings, $extras );
                if ( empty( $_result ) )
                {
                    throw new NotFoundException( "Record with identifier '" . print_r( $id, true ) . "' not found." );
                }

                $_out = $_result[0];
                break;
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    protected function commitTransaction( $extras = null )
    {
        if ( empty( $this->_batchRecords ) && empty( $this->_batchIds ) )
        {
            if ( isset( $this->_transaction ) )
            {
                $this->_transaction->commit();
            }

            return null;
        }

        $_updates = Option::get( $extras, 'updates' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldsInfo = Option::get( $extras, 'fields_info' );
        $_idsInfo = Option::get( $extras, 'ids_info' );
        $_idFields = Option::get( $extras, 'id_fields' );
        $_related = Option::get( $extras, 'related' );
        $_requireMore = Option::getBool( $extras, 'require_more' ) || !empty( $_related );
        $_allowRelatedDelete = Option::getBool( $extras, 'allow_related_delete', false );
        $_relatedInfo = $this->describeTableRelated( $this->_transactionTable );

        $_where = array();
        $_params = array();

        $_idName = ( isset( $_idsInfo, $_idsInfo[0], $_idsInfo[0]['name'] ) ) ? $_idsInfo[0]['name'] : null;
        if ( empty( $_idName ) )
        {
            throw new BadRequestException( 'No valid identifier found for this table.' );
        }

        if ( !empty( $this->_batchRecords ) )
        {
            if ( is_array( $this->_batchRecords[0] ) )
            {
                $_temp = array();
                foreach ( $this->_batchRecords as $_record )
                {
                    $_temp[] = Option::get( $_record, $_idName );
                }

                $_where[] = array('in', $_idName, $_temp);
            }
            else
            {
                $_where[] = array('in', $_idName, $this->_batchRecords);
            }
        }
        else
        {
            $_where[] = array('in', $_idName, $this->_batchIds);
        }

        $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
        if ( !empty( $_serverFilter ) )
        {
            $_where[] = $_serverFilter['filter'];
            $_params = $_serverFilter['params'];
        }

        if ( count( $_where ) > 1 )
        {
            array_unshift( $_where, 'AND' );
        }
        else
        {
            $_where = $_where[0];
        }

        $_out = array();
        $_action = $this->getAction();
        if ( !empty( $this->_batchRecords ) )
        {
            if ( 1 == count( $_idsInfo ) )
            {
                // records are used to retrieve extras
                // ids array are now more like records
                $_fields = ( empty( $_fields ) ) ? $_idFields : $_fields;
                $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
                $_bindings = Option::get( $_result, 'bindings' );
                $_fields = Option::get( $_result, 'fields' );
                $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                $_result =
                    $this->_recordQuery( $this->_transactionTable, $_fields, $_where, $_params, $_bindings, $extras );
                if ( empty( $_result ) )
                {
                    throw new NotFoundException( 'No records were found using the given identifiers.' );
                }

                $_out = $_result;
            }
            else
            {
                $_out = $this->retrieveRecords( $this->_transactionTable, $this->_batchRecords, $extras );
            }

            $this->_batchRecords = array();
        }
        elseif ( !empty( $this->_batchIds ) )
        {
            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();

            switch ( $_action )
            {
                case static::PUT:
                case static::MERGE:
                case static::PATCH:
                    if ( !empty( $_updates ) )
                    {
                        $_parsed = $this->parseRecord( $_updates, $_fieldsInfo, $_ssFilters, true );
                        if ( !empty( $_parsed ) )
                        {
                            $_rows = $_command->update( $this->_transactionTable, $_parsed, $_where, $_params );
                            if ( 0 >= $_rows )
                            {
                                throw new NotFoundException( 'No records were found using the given identifiers.' );
                            }

                            if ( count( $this->_batchIds ) !== $_rows )
                            {
                                throw new BadRequestException( 'Batch Error: Not all requested records could be updated.' );
                            }
                        }

                        foreach ( $this->_batchIds as $_id )
                        {
                            if ( !empty( $_relatedInfo ) )
                            {
                                $this->updateRelations(
                                    $this->_transactionTable,
                                    $_updates,
                                    $_id,
                                    $_relatedInfo,
                                    $_allowRelatedDelete
                                );
                            }
                        }

                        if ( $_requireMore )
                        {
                            $_fields = ( empty( $_fields ) ) ? $_idFields : $_fields;
                            $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
                            $_bindings = Option::get( $_result, 'bindings' );
                            $_fields = Option::get( $_result, 'fields' );
                            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                            $_result = $this->_recordQuery(
                                $this->_transactionTable,
                                $_fields,
                                $_where,
                                $_params,
                                $_bindings,
                                $extras
                            );
                            if ( empty( $_result ) )
                            {
                                throw new NotFoundException( 'No records were found using the given identifiers.' );
                            }

                            $_out = $_result;
                        }
                    }
                    break;

                case static::DELETE:
                    if ( $_requireMore )
                    {
                        $_fields = ( empty( $_fields ) ) ? $_idFields : $_fields;
                        $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
                        $_bindings = Option::get( $_result, 'bindings' );
                        $_fields = Option::get( $_result, 'fields' );
                        $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                        $_result = $this->_recordQuery(
                            $this->_transactionTable,
                            $_fields,
                            $_where,
                            $_params,
                            $_bindings,
                            $extras
                        );
                        if ( count( $this->_batchIds ) !== count( $_result ) )
                        {
                            $_errors = array();
                            foreach ( $this->_batchIds as $_index => $_id )
                            {
                                $_found = false;
                                if ( empty( $_result ) )
                                {
                                    foreach ( $_result as $_record )
                                    {
                                        if ( $_id == Option::get( $_record, $_idName ) )
                                        {
                                            $_out[$_index] = $_record;
                                            $_found = true;
                                            continue;
                                        }
                                    }
                                }
                                if ( !$_found )
                                {
                                    $_errors[] = $_index;
                                    $_out[$_index] =
                                        "Record with identifier '" . print_r( $_id, true ) . "' not found.";
                                }
                            }
                        }
                        else
                        {
                            $_out = $_result;
                        }
                    }

                    $_rows = $_command->delete( $this->_transactionTable, $_where, $_params );
                    if ( count( $this->_batchIds ) !== $_rows )
                    {
                        throw new BadRequestException( 'Batch Error: Not all requested records were deleted.' );
                    }
                    break;

                case static::GET:
                    $_fields = ( empty( $_fields ) ) ? $_idFields : $_fields;
                    $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldsInfo );
                    $_bindings = Option::get( $_result, 'bindings' );
                    $_fields = Option::get( $_result, 'fields' );
                    $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

                    $_result = $this->_recordQuery(
                        $this->_transactionTable,
                        $_fields,
                        $_where,
                        $_params,
                        $_bindings,
                        $extras
                    );
                    if ( empty( $_result ) )
                    {
                        throw new NotFoundException( 'No records were found using the given identifiers.' );
                    }

                    if ( count( $this->_batchIds ) !== count( $_result ) )
                    {
                        $_errors = array();
                        foreach ( $this->_batchIds as $_index => $_id )
                        {
                            $_found = false;
                            foreach ( $_result as $_record )
                            {
                                if ( $_id == Option::get( $_record, $_idName ) )
                                {
                                    $_out[$_index] = $_record;
                                    $_found = true;
                                    continue;
                                }
                            }
                            if ( !$_found )
                            {
                                $_errors[] = $_index;
                                $_out[$_index] = "Record with identifier '" . print_r( $_id, true ) . "' not found.";
                            }
                        }

                        if ( !empty( $_errors ) )
                        {
                            $_context = array('error' => $_errors, 'record' => $_out);
                            throw new NotFoundException( 'Batch Error: Not all records could be retrieved.', null, null, $_context );
                        }
                    }

                    $_out = $_result;
                    break;

                default:
                    break;
            }

            if ( empty( $_out ) )
            {
                $_out = array();
                foreach ( $this->_batchIds as $_id )
                {
                    $_out[] = array($_idName => $_id);
                }
            }

            $this->_batchIds = array();
        }

        if ( isset( $this->_transaction ) )
        {
            $this->_transaction->commit();
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    protected function rollbackTransaction()
    {
        if ( isset( $this->_transaction ) )
        {
            $this->_transaction->rollback();
        }

        return true;
    }

    // Handle schema options, table add, delete, etc

    /**
     * {@inheritdoc}
     */
    public function describeTables( $tables )
    {
        $_tables = SqlDbUtilities::validateAsArray( $tables, ',', true );

        try
        {
            $_resources = array();
            foreach ( $_tables as $_table )
            {
                if ( null != $_name = Option::get( $_table, 'name', $_table, false, true ) )
                {
                    $this->checkForNativeSystemTable( $_name );

                    $_access = $this->getPermissions( $_name );
                    if ( !empty( $_access ) )
                    {
                        $_extras = SqlDbUtilities::getSchemaExtrasForTables( $this->getServiceId(), $_name );
                        $_result = SqlDbUtilities::describeTable( $this->_dbConn, $_name, null, $_extras );
                        $_result['access'] = $_access;
                        $_resources[] = $_result;
                    }
                }
            }

            return $_resources;
        }
        catch ( RestException $ex )
        {
            throw $ex;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error describing database tables.\n" .
                                                    $ex->getMessage(), $ex->getCode() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function describeTable( $table )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        $this->checkForNativeSystemTable( $table );

        try
        {
            $_extras = SqlDbUtilities::getSchemaExtrasForTables( $this->getServiceId(), $table );
            $_result = SqlDbUtilities::describeTable( $this->_dbConn, $table, null, $_extras );
            $_result['access'] = $this->getPermissions( $table );

            return $_result;
        }
        catch ( RestException $ex )
        {
            throw $ex;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error describing database table '$table'.\n" .
                                                    $ex->getMessage(), $ex->getCode() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function describeField( $table, $field )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        $this->checkForNativeSystemTable( $table );

        try
        {
            $_extras = SqlDbUtilities::getSchemaExtrasForFields( 0, $table, $field );
            $_result = SqlDbUtilities::describeTableFields( $this->_dbConn, $table, $field, $_extras );

            return Option::get( $_result, 0 );
        }
        catch ( RestException $ex )
        {
            throw $ex;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error describing database table '$table' field '$field'.\n" .
                                                    $ex->getMessage(), $ex->getCode() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTables( $tables, $check_exist = false )
    {
        $tables = SqlDbUtilities::validateAsArray( $tables, null, true, 'There are no table sets in the request.' );

        // check for system tables and deny
        foreach ( $tables as $_table )
        {
            if ( null === ( $_name = Option::get( $_table, 'name' ) ) )
            {
                throw new BadRequestException( "Table schema received does not have a valid name." );
            }

            $this->checkForNativeSystemTable( $_name, false );
        }

        $_result = SqlDbUtilities::updateTables( $this->_dbConn, $tables );
        $_labels = Option::get( $_result, 'labels', null, true );

        if ( !empty( $_labels ) )
        {
            SqlDbUtilities::setSchemaExtras( $this->getServiceId(), $_labels );
        }

        return $_result;
    }

    /**
     * {@inheritdoc}
     */
    public function createTable( $table, $properties = array(), $check_exist = false )
    {
        $this->checkForNativeSystemTable( $table, false );

        $properties = Option::clean( $properties );
        $properties['name'] = $table;

        $_tables = SqlDbUtilities::validateAsArray( $properties, null, true, 'Bad data format in request.' );
        $_result = SqlDbUtilities::updateTables( $this->_dbConn, $_tables );
        $_labels = Option::get( $_result, 'labels', null, true );

        if ( !empty( $_labels ) )
        {
            SqlDbUtilities::setSchemaExtras( $this->getServiceId(), $_labels );
        }

        return Option::get( $_result, 0, array() );
    }

    /**
     * {@inheritdoc}
     */
    public function createField( $table, $field, $properties = array(), $check_exist = false )
    {
        $this->checkForNativeSystemTable( $table, false );

        $properties = Option::clean( $properties );
        $properties['name'] = $field;

        $_fields = SqlDbUtilities::validateAsArray( $properties, null, true, 'Bad data format in request.' );

        $_result = SqlDbUtilities::updateFields( $this->_dbConn, $table, $_fields );
        $_labels = Option::get( $_result, 'labels', null, true );

        if ( !empty( $_labels ) )
        {
            SqlDbUtilities::setSchemaExtras( $this->getServiceId(), $_labels );
        }

        return $this->describeField( $table, $field );
    }

    /**
     * {@inheritdoc}
     */
    public function updateTables( $tables, $allow_delete_fields = false )
    {
        $tables = SqlDbUtilities::validateAsArray( $tables, null, true, 'There are no table sets in the request.' );

        foreach ( $tables as $_table )
        {
            if ( null === ( $_name = Option::get( $_table, 'name' ) ) )
            {
                throw new BadRequestException( "Table schema received does not have a valid name." );
            }

            $this->checkForNativeSystemTable( $_name, false );
        }

        $_out = SqlDbUtilities::updateTables( $this->_dbConn, $tables, true, $allow_delete_fields );
        $_labels = Option::get( $_out, 'labels', null, true );

        if ( !empty( $_labels ) )
        {
            SqlDbUtilities::setSchemaExtras( $this->getServiceId(), $_labels );
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    public function updateTable( $table, $properties, $allow_delete_fields = false )
    {
        $this->checkForNativeSystemTable( $table, false );

        $properties = Option::clean( $properties );
        $properties['name'] = $table;

        $_tables = SqlDbUtilities::validateAsArray( $properties, null, true, 'Bad data format in request.' );

        $_result = SqlDbUtilities::updateTables( $this->_dbConn, $_tables, true, $allow_delete_fields );
        $_labels = Option::get( $_result, 'labels', null, true );

        if ( !empty( $_labels ) )
        {
            SqlDbUtilities::setSchemaExtras( $this->getServiceId(), $_labels );
        }

        return Option::get( $_result, 0, array() );
    }

    /**
     * {@inheritdoc}
     */
    public function updateField( $table, $field, $properties = array(), $allow_delete_parts = false )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        $this->checkForNativeSystemTable( $table );

        $properties = Option::clean( $properties );
        $properties['name'] = $field;

        $_fields = SqlDbUtilities::validateAsArray( $properties, null, true, 'Bad data format in request.' );

        $_result = SqlDbUtilities::updateFields( $this->_dbConn, $table, $_fields, true );
        $_labels = Option::get( $_result, 'labels', null, true );

        if ( !empty( $_labels ) )
        {
            SqlDbUtilities::setSchemaExtras( $this->getServiceId(), $_labels );
        }

        return $this->describeField( $table, $field );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTable( $table, $check_empty = false )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        $this->checkForNativeSystemTable( $table );

        SqlDbUtilities::dropTable( $this->_dbConn, $table );

        SqlDbUtilities::removeSchemaExtrasForTables( $this->getServiceId(), $table );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteField( $table, $field )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        $this->checkForNativeSystemTable( $table );

        SqlDbUtilities::dropField( $this->_dbConn, $table, $field );

        SqlDbUtilities::removeSchemaExtrasForFields( $this->getServiceId(), $table, $field );
    }

    /**
     * @param boolean $isNative
     *
     * @return SchemaSvc
     */
    public function setIsNative( $isNative )
    {
        $this->_isNative = $isNative;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getIsNative()
    {
        return $this->_isNative;
    }
}
