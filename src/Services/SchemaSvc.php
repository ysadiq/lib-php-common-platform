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
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Interfaces\ServiceOnlyResourceLike;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\SqlDbUtilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * SchemaSvc
 * A service to handle SQL database schema-related services accessed through the REST API.
 *
 */
class SchemaSvc extends BasePlatformRestService implements ServiceOnlyResourceLike
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string
     */
    protected $_tableName;
    /**
     * @var string
     */
    protected $_fieldName;
    /**
     * @var \CDbConnection
     */
    protected $_dbConn;
    /**
     * @var bool
     */
    protected $_isNative = false;
    /**
     * @var array
     */
    protected $_tables;
    /**
     * @var array
     */
    protected $_fields;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new SchemaSvc
     *
     * @param array $config
     * @param bool  $native
     *
     * @throws \InvalidArgumentException
     */
    public function __construct( $config, $native = false )
    {
        parent::__construct( $config );

        if ( empty( $this->_verbAliases ) )
        {
            $this->_verbAliases = array(
                static::MERGE => static::PATCH,
            );
        }

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

            // 	Create pdo connection, activate later
            $this->_dbConn = new \CDbConnection( $dsn, $user, $password );
        }

        switch ( $this->_driverType = SqlDbUtilities::getDbDriverType( $this->_dbConn ) )
        {
            case SqlDbUtilities::DRV_MYSQL:
                $this->_dbConn->setAttribute( \PDO::ATTR_EMULATE_PREPARES, true );
//				$this->_sqlConn->setAttribute( 'charset', 'utf8' );
                break;

            case SqlDbUtilities::DRV_SQLSRV:
//				$this->_sqlConn->setAttribute( \PDO::SQLSRV_ATTR_DIRECT_QUERY, true );
//				$this->_sqlConn->setAttribute( 'MultipleActiveResultSets', false );
//				$this->_sqlConn->setAttribute( 'ReturnDatesAsStrings', true );
                $this->_dbConn->setAttribute( 'CharacterSet', 'UTF-8' );
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
        if ( !$this->_isNative )
        {
            unset( $this->_dbConn );
        }
    }

    protected function _detectRequestMembers()
    {
        $_posted = Option::clean( RestData::getPostedData( true, true ) );
        $this->_requestPayload = array_merge( $_REQUEST, $_posted );

        return $this;
    }

    /**
     * {@InheritDoc}
     */
    protected function _detectResourceMembers( $resourcePath = null )
    {
        parent::_detectResourceMembers( $resourcePath );

        $this->_tableName = Option::get( $this->_resourceArray, 0 );
        $this->_fieldName = Option::get( $this->_resourceArray, 1 );

        return $this;
    }

    /**
     * Get a mano in there...
     */
    protected function _preProcess()
    {
        parent::_preProcess();

        $this->_tables = Option::get( $this->_requestPayload, 'table' );

        //	Create fields in existing table
        if ( !empty( $this->_tableName ) )
        {
            $this->_fields = Option::get( $this->_requestPayload, 'field' );

            $this->checkPermission( $this->_action, $this->_tableName );
        }
        else
        {
            $this->checkPermission( $this->_action );
        }
    }

    /**
     * After a successful schema call, refresh the database cache
     *
     * @return bool|void
     */
    protected function _handleResource()
    {
        if ( false !== ( $_result = parent::_handleResource() ) )
        {
            //	Clear the DB cache if enabled...

            /** @var \CDbCache $_cache */
            if ( null !== ( $_cache = Pii::component( 'cache' ) ) )
            {
                $_cache->flush();
            }

        }

        $this->_triggerActionEvent( $_result );

        return $_result;
    }

    /**
     * @return array|bool
     */
    protected function _handleGet()
    {
        if ( empty( $this->_tableName ) )
        {
            if ( empty( $this->_tables ) )
            {
                $_namesOnly = Option::getBool( $this->_requestPayload, 'as_access_components' );
                $_result = $this->describeDatabase( $_namesOnly );
                if ( $_namesOnly )
                {
                    $_result = array_merge( array('', '*'), $_result );
                }

                return array('resource' => $_result);
            }

            return array('table' => $this->describeTables( $this->_tables ));
        }

        if ( empty( $this->_fieldName ) )
        {
            return $this->describeTable( $this->_tableName );
        }

        return $this->describeField( $this->_tableName, $this->_fieldName );
    }

    /**
     * @return bool
     */
    protected function _handlePost()
    {
        if ( empty( $this->_tableName ) )
        {
            if ( empty( $this->_tables ) )
            {
                return $this->updateTable( $this->_requestPayload );
            }

            return array('table' => $this->updateTables( $this->_tables ));
        }

        if ( empty( $this->_fields ) )
        {
            return $this->createField( $this->_tableName, $this->_requestPayload );
        }

        return array('field' => $this->updateFields( $this->_tableName, $this->_fields ));
    }

    /**
     * @return bool
     */
    protected function _handlePut()
    {
        if ( empty( $this->_tableName ) )
        {
            if ( empty( $this->_tables ) )
            {
                return $this->updateTable( $this->_requestPayload, true, true );
            }

            return array('table' => $this->updateTables( $this->_tables, true, true ));
        }

        if ( empty( $this->_fieldName ) )
        {
            if ( empty( $this->_fields ) )
            {
                return $this->updateField( $this->_tableName, null, $this->_requestPayload, true );
            }

            return array('field' => $this->updateFields( $this->_tableName, $this->_fields, true, true ));
        }

        //	Create new field in existing table
        if ( empty( $this->_requestPayload ) )
        {
            throw new BadRequestException( 'No data in schema update request.' );
        }

        return $this->updateField( $this->_tableName, $this->_fieldName, $this->_requestPayload, true );
    }

    /**
     * @return bool
     */
    protected function _handlePatch()
    {
        if ( empty( $this->_tableName ) )
        {
            if ( empty( $this->_tables ) )
            {
                return $this->updateTable( $this->_requestPayload, true );
            }

            return array('table' => $this->updateTables( $this->_tables, true ));
        }

        if ( empty( $this->_fieldName ) )
        {
            if ( empty( $this->_fields ) )
            {
                return $this->updateField( $this->_tableName, null, $this->_requestPayload );
            }

            return array('field' => $this->updateFields( $this->_tableName, $this->_fields, true ));
        }

        //	Create new field in existing table
        if ( empty( $this->_requestPayload ) )
        {
            throw new BadRequestException( 'No data in schema create request.' );
        }

        return $this->updateField( $this->_tableName, $this->_fieldName, $this->_requestPayload );
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleDelete()
    {
        if ( empty( $this->_fieldName ) )
        {
            $this->deleteTable( $this->_tableName );

            return array('table' => $this->_tableName);
        }

        $this->deleteField( $this->_tableName, $this->_fieldName );

        return array('field' => $this->_fieldName);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function describeDatabase( $names_only = false )
    {
        // 	Exclude system tables
        $_exclude = $this->_isNative ? SystemManager::SYSTEM_TABLE_PREFIX : null;

        try
        {
            $_names = SqlDbUtilities::describeDatabase( $this->_dbConn, null, $_exclude );
            $_extras =
                SqlDbUtilities::getSchemaExtrasForTables( $this->getServiceId(), $_names, false, 'table,label,plural' );

            $_tables = array();
            foreach ( $_names as $_name )
            {
                $_access = $this->getPermissions( $_name );
                if ( !empty( $_access ) )
                {
                    if ( $names_only )
                    {
                        $_tables[] = $_name;
                    }
                    else
                    {
                        $label = '';
                        $plural = '';
                        foreach ( $_extras as $_each )
                        {
                            if ( 0 == strcasecmp( $_name, Option::get( $_each, 'table', '' ) ) )
                            {
                                $label = Option::get( $_each, 'label' );
                                $plural = Option::get( $_each, 'plural' );
                                break;
                            }
                        }

                        if ( empty( $label ) )
                        {
                            $label = Inflector::camelize( $_name, '_', true );
                        }

                        if ( empty( $plural ) )
                        {
                            $plural = Inflector::pluralize( $label );
                        }

                        $_tables[] =
                            array('name' => $_name, 'label' => $label, 'plural' => $plural, 'access' => $_access);
                    }
                }
            }

            return $_tables;
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
     * @param array $table_list
     *
     * @return array|string
     * @throws \Exception
     */
    public function describeTables( $table_list )
    {
        $_tables = SqlDbUtilities::validateAsArray( $table_list, ',', true );

        //	Check for system tables and deny
        $_sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
        $_length = strlen( $_sysPrefix );

        try
        {
            $_resources = array();
            foreach ( $_tables as $_table )
            {
                if ( null != $_name = Option::get( $_table, 'name', $_table, false, true ) )
                {
                    if ( $this->_isNative )
                    {
                        if ( 0 === substr_compare( $_name, $_sysPrefix, 0, $_length ) )
                        {
                            throw new NotFoundException( "Table '$_name' not found." );
                        }
                    }

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
            throw new InternalServerErrorException( "Error describing database tables '$table_list'.\n" .
                                                    $ex->getMessage(), $ex->getCode() );
        }
    }

    /**
     * @param $table
     *
     * @return array
     * @throws \Exception
     */
    public function describeTable( $table )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        if ( $this->_isNative )
        {
            // check for system tables and deny
            if ( 0 === substr_compare(
                    $table,
                    SystemManager::SYSTEM_TABLE_PREFIX,
                    0,
                    strlen( SystemManager::SYSTEM_TABLE_PREFIX )
                )
            )
            {
                throw new NotFoundException( "Table '$table' not found." );
            }
        }

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
     * @param $table
     * @param $field
     *
     * @return array
     * @throws \Exception
     */
    public function describeField( $table, $field )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        if ( $this->_isNative )
        {
            // check for system tables and deny
            if ( 0 === substr_compare(
                    $table,
                    SystemManager::SYSTEM_TABLE_PREFIX,
                    0,
                    strlen( SystemManager::SYSTEM_TABLE_PREFIX )
                )
            )
            {
                throw new NotFoundException( "Table '$table' not found." );
            }
        }

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
     * @param array $tables
     * @param bool  $allow_merge
     * @param bool  $allow_delete
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array
     */
    public function updateTables( $tables, $allow_merge = false, $allow_delete = false )
    {
        $tables = SqlDbUtilities::validateAsArray( $tables, null, true, 'There are no table sets in the request.' );

        $_sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
        $_length = strlen( SystemManager::SYSTEM_TABLE_PREFIX );

        if ( $this->_isNative )
        {
            // check for system tables and deny
            foreach ( $tables as $_table )
            {
                if ( null === ( $_name = Option::get( $_table, 'name' ) ) )
                {
                    throw new BadRequestException( "Table schema received does not have a valid name." );
                }

                if ( 0 === substr_compare( $_name, SystemManager::SYSTEM_TABLE_PREFIX, 0, $_length ) )
                {
                    throw new BadRequestException( "Tables can not use the prefix '$_sysPrefix'. '$_name' can not be created." );
                }
            }
        }

        $_result = SqlDbUtilities::updateTables( $this->_dbConn, $tables, $allow_merge, $allow_delete );
        $_labels = Option::get( $_result, 'labels', true );

        if ( !empty( $_labels ) )
        {
            SqlDbUtilities::setSchemaExtras( $this->getServiceId(), $_labels );
        }

        return $_result;
    }

    /**
     * @param      $table
     * @param bool $allow_merge
     * @param bool $allow_delete
     *
     * @return array
     */
    public function updateTable( $table, $allow_merge = false, $allow_delete = false )
    {
        $_tables = SqlDbUtilities::validateAsArray( $table, null, true, 'Bad data format in request.' );

        $_result = $this->updateTables( $_tables, $allow_merge, $allow_delete );

        return Option::get( $_result, 0, array() );
    }

    /**
     * @param      $table
     * @param      $fields
     * @param bool $allow_merge
     * @param bool $allow_delete
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public function updateFields( $table, $fields, $allow_merge = false, $allow_delete = false )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        if ( $this->_isNative )
        {
            // check for system tables and deny
            $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
            if ( 0 === substr_compare( $table, $sysPrefix, 0, strlen( $sysPrefix ) ) )
            {
                throw new NotFoundException( "Table '$table' not found." );
            }
        }

        try
        {
            $_result = SqlDbUtilities::updateFields( $this->_dbConn, $table, $fields, $allow_merge, $allow_delete );
            $_labels = Option::get( $_result, 'labels', array(), true );

            if ( !empty( $_labels ) )
            {
                SqlDbUtilities::setSchemaExtras( $this->getServiceId(), $_labels );
            }

            $_names = Option::get( $_result, 'names' );
            $_extras = SqlDbUtilities::getSchemaExtrasForFields( $this->getServiceId(), $table, $_names );

            return SqlDbUtilities::describeTableFields( $this->_dbConn, $table, $_names, $_extras );
        }
        catch ( RestException $ex )
        {
            throw $ex;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error creating database fields for table '$table'.\n" .
                                                    $ex->getMessage(), $ex->getCode() );
        }
    }

    /**
     * @param string $table
     * @param array  $data
     *
     * @throws \Exception
     * @return array
     */
    public function createField( $table, $data )
    {
        $_fields = SqlDbUtilities::validateAsArray( $data, null, true, 'Bad data format in request.' );

        $result = $this->updateFields( $table, $_fields );

        return Option::get( $result, 0, array() );
    }

    /**
     * @param string $table
     * @param string $field
     * @param array  $_data
     * @param bool   $allow_delete
     *
     * @return array
     */
    public function updateField( $table, $field, $_data, $allow_delete = false )
    {
        if ( !empty( $field ) )
        {
            $_data['name'] = $field;
        }
        $result = $this->updateFields( $table, $_data, true, $allow_delete );

        return Option::get( $result, 0, array() );
    }

    /**
     * @param $table
     *
     * @throws \Exception
     */
    public function deleteTable( $table )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }
        if ( $this->_isNative )
        {
            // check for system tables and deny
            $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
            if ( 0 === substr_compare( $table, $sysPrefix, 0, strlen( $sysPrefix ) ) )
            {
                throw new NotFoundException( "Table '$table' not found." );
            }
        }

        SqlDbUtilities::dropTable( $this->_dbConn, $table );

        SqlDbUtilities::removeSchemaExtrasForTables( $this->getServiceId(), $table );
    }

    /**
     * @param $table
     * @param $field
     *
     * @throws \Exception
     */
    public function deleteField( $table, $field )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        if ( $this->_isNative )
        {
            // check for system tables and deny
            $sysPrefix = SystemManager::SYSTEM_TABLE_PREFIX;
            if ( 0 === substr_compare( $table, $sysPrefix, 0, strlen( $sysPrefix ) ) )
            {
                throw new NotFoundException( "Table '$table' not found." );
            }
        }

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
