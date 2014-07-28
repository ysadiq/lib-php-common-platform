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
    /**
     * @var array
     */
    protected $_payload;

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

        $this->_payload = RestData::getPostedData( true, true );
        $this->_tables = Option::get( $this->_payload, 'table' );

        //	Create fields in existing table
        if ( !empty( $this->_tableName ) )
        {
            $this->_fields = Option::get( $this->_payload, 'field' );

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
                return array( 'resource' => $this->describeDatabase() );
            }

            return array( 'table' => $this->describeTables( $this->_tables ) );
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
                return $this->updateTable( $this->_payload );
            }

            return array( 'table' => $this->updateTables( $this->_tables ) );
        }

        if ( empty( $this->_fields ) )
        {
            return $this->createField( $this->_tableName, $this->_payload );
        }

        return array( 'field' => $this->updateFields( $this->_tableName, $this->_fields ) );
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
                return $this->updateTable( $this->_payload, true, true );
            }

            return array( 'table' => $this->updateTables( $this->_tables, true, true ) );
        }

        if ( empty( $this->_fieldName ) )
        {
            if ( empty( $this->_fields ) )
            {
                return $this->updateField( $this->_tableName, null, $this->_payload, true );
            }

            return array( 'field' => $this->updateFields( $this->_tableName, $this->_fields, true, true ) );
        }

        //	Create new field in existing table
        if ( empty( $this->_payload ) )
        {
            throw new BadRequestException( 'No data in schema update request.' );
        }

        return $this->updateField( $this->_tableName, $this->_fieldName, $this->_payload, true );
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
                return $this->updateTable( $this->_payload, true );
            }

            return array( 'table' => $this->updateTables( $this->_tables, true ) );
        }

        if ( empty( $this->_fieldName ) )
        {
            if ( empty( $this->_fields ) )
            {
                return $this->updateField( $this->_tableName, null, $this->_payload );
            }

            return array( 'field' => $this->updateFields( $this->_tableName, $this->_fields, true ) );
        }

        //	Create new field in existing table
        if ( empty( $this->_payload ) )
        {
            throw new BadRequestException( 'No data in schema create request.' );
        }

        return $this->updateField( $this->_tableName, $this->_fieldName, $this->_payload );
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

            return array( 'table' => $this->_tableName );
        }

        $this->deleteField( $this->_tableName, $this->_fieldName );

        return array( 'field' => $this->_fieldName );
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function describeDatabase()
    {
        // 	Exclude system tables
        $_exclude = $this->_isNative ? SystemManager::SYSTEM_TABLE_PREFIX : null;

        try
        {
            $_result = SqlDbUtilities::describeDatabase( $this->_dbConn, null, $_exclude );

            $_resources = array();
            foreach ( $_result as $_table )
            {
                if ( null != $_name = Option::get( $_table, 'name' ) )
                {
                    $_access = $this->getPermissions( $_name );
                    if ( !empty( $_access ) )
                    {
                        $_table['access'] = $_access;
                        $_resources[] = $_table;
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
            throw new InternalServerErrorException(
                "Error describing database tables.\n" . $ex->getMessage(), $ex->getCode()
            );
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

        foreach ( $_tables as $_table )
        {
            if ( $this->_isNative )
            {
                if ( 0 === substr_compare( $_table, $_sysPrefix, 0, $_length ) )
                {
                    throw new NotFoundException( "Table '$_table' not found." );
                }
            }
        }

        try
        {
            $_result = SqlDbUtilities::describeTables( $this->_dbConn, $_tables );
            $_resources = array();
            foreach ( $_result as $_table )
            {
                if ( null != $_name = Option::get( $_table, 'name' ) )
                {
                    $_access = $this->getPermissions( $_name );
                    if ( !empty( $_access ) )
                    {
                        $_table['access'] = $_access;
                        $_resources[] = $_table;
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
            throw new InternalServerErrorException(
                "Error describing database tables '$table_list'.\n" . $ex->getMessage(), $ex->getCode()
            );
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
            $_result = SqlDbUtilities::describeTable( $this->_dbConn, $table );
            $_result['access'] = $this->getPermissions( $table );

            return $_result;
        }
        catch ( RestException $ex )
        {
            throw $ex;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException(
                "Error describing database table '$table'.\n" . $ex->getMessage(), $ex->getCode()
            );
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
            return SqlDbUtilities::describeField( $this->_dbConn, $table, $field );
        }
        catch ( RestException $ex )
        {
            throw $ex;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException(
                "Error describing database table '$table' field '$field'.\n" . $ex->getMessage(), $ex->getCode()
            );
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
                    throw new BadRequestException(
                        "Tables can not use the prefix '$_sysPrefix'. '$_name' can not be created."
                    );
                }
            }
        }

        return SqlDbUtilities::updateTables( $this->_dbConn, $tables, $allow_merge, $allow_delete );
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
            $names = SqlDbUtilities::updateFields( $this->_dbConn, $table, $fields, $allow_merge, $allow_delete );

            return SqlDbUtilities::describeFields( $this->_dbConn, $table, $names );
        }
        catch ( RestException $ex )
        {
            throw $ex;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException(
                "Error creating database fields for table '$table'.\n" . $ex->getMessage(), $ex->getCode()
            );
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
    }

    /**
     * @param string $fieldName
     *
     * @return SchemaSvc
     */
    public function setFieldName( $fieldName )
    {
        $this->_fieldName = $fieldName;

        return $this;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->_fieldName;
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

    /**
     * @param array $payload
     *
     * @return SchemaSvc
     */
    public function setPayload( $payload )
    {
        $this->_payload = $payload;

        return $this;
    }

    /**
     * @return array
     */
    public function getPayload()
    {
        return $this->_payload;
    }

    /**
     * @param \CDbConnection $db_conn
     *
     * @return SchemaSvc
     */
    public function setDbConn( $db_conn )
    {
        $this->_dbConn = $db_conn;

        return $this;
    }

    /**
     * @return \CDbConnection
     */
    public function getDbConn()
    {
        return $this->_dbConn;
    }

    /**
     * @param string $tableName
     *
     * @return SchemaSvc
     */
    public function setTableName( $tableName )
    {
        $this->_tableName = $tableName;

        return $this;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->_tableName;
    }

    /**
     * @param mixed $tables
     *
     * @return SchemaSvc
     */
    public function setTables( $tables )
    {
        $this->_tables = $tables;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTables()
    {
        return $this->_tables;
    }

    /**
     * @param array $fields
     *
     * @return SchemaSvc
     */
    public function setFields( $fields )
    {
        $this->_fields = $fields;

        return $this;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->_fields;
    }
}
