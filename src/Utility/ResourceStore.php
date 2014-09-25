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
namespace DreamFactory\Platform\Utility;

use Composer\Autoload\ClassLoader;
use DreamFactory\Platform\Enums\ResponseFormats;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Platform\Yii\Models\BasePlatformModel;
use DreamFactory\Platform\Yii\Models\BasePlatformSystemModel;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\CoreSettings;
use Kisma\Core\Interfaces\UtilityLike;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * ResourceStore
 * A base service resource class to handle service resources of various kinds.
 *
 * This object DOES NOT check permissions.
 */
class ResourceStore implements UtilityLike
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var string
     */
    const DEFAULT_MODEL_NAMESPACE = 'DreamFactory\\Platform\\Yii\\Models\\';
    /**
     * @var string
     */
    const DEFAULT_RESOURCE_NAMESPACE = 'DreamFactory\\Platform\\Resources\\System\\';

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var string
     */
    protected static $_resourceName;
    /**
     * @var int
     */
    protected static $_resourceId;
    /**
     * @var array
     */
    protected static $_payload;
    /**
     * @var string Our service name
     */
    protected static $_service = 'system';
    /**
     * @var int The response format if not pass-through
     */
    protected static $_responseFormat;

    //************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param array $settings The settings to reset to
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    public static function reset( $settings = array() )
    {
        static::$_resourceId = Option::get( $settings, 'resource_id' );
        static::$_resourceName = Option::get( $settings, 'resource_name' );
        static::$_service = Option::get( $settings, 'service' );
        static::$_payload = Option::get( $settings, 'payload' );
        static::$_responseFormat = ResponseFormats::RAW;

        if ( empty( static::$_resourceName ) )
        {
            throw new BadRequestException( 'Resource name can not be empty.' );
        }
    }

    /**
     * Individual Methods
     */

    /**
     * @param array $records
     * @param array $extras
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public static function insert( $records, $extras = null )
    {
        return static::bulkInsert( $records, $extras, false );
    }

    /**
     * @param array $record
     * @param array $extras
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public static function insertOne( $record, $extras = null )
    {
        return static::bulkInsert( array($record), $extras, true );
    }

    /**
     * @param array $records
     * @param array $extras
     *
     * @return array
     */
    public static function update( $records, $extras = null )
    {
        return static::bulkUpdate( $records, $extras, false );
    }

    /**
     * @param array $record
     * @param array $extras
     *
     * @return array
     */
    public static function updateOne( $record, $extras = null )
    {
        return static::bulkUpdate( array($record), $extras, true );
    }

    /**
     * @param        $id
     * @param array  $record
     * @param array  $extras
     *
     * @return array
     */
    public static function updateById( $id, $record, $extras = null )
    {
        return static::bulkUpdateById( $id, $record, $extras, true );
    }

    /**
     * @param        $ids
     * @param array  $record
     * @param array  $extras
     *
     * @return array
     */
    public static function updateByIds( $ids, $record, $extras = null )
    {
        return static::bulkUpdateById( $ids, $record, $extras, false );
    }

    /**
     * @param array $records
     * @param array $extras
     *
     * @return array
     */
    public static function delete( $records, $extras = null )
    {
        return static::bulkDelete( $records, $extras, false );
    }

    /**
     * @param mixed $record
     * @param array $extras
     *
     * @return array
     */
    public static function deleteOne( $record, $extras = null )
    {
        return static::bulkDelete( array($record), $extras, true );
    }

    /**
     * @param        $id
     * @param array  $extras
     *
     * @return array
     */
    public static function deleteById( $id, $extras = null )
    {
        return static::bulkDeleteById( $id, $extras, true );
    }

    /**
     * @param        $ids
     * @param array  $extras
     *
     * @return array
     */
    public static function deleteByIds( $ids, $extras = null )
    {
        return static::bulkDeleteById( $ids, $extras, false );
    }

    /**
     * @param int                       $id        Optional ID
     * @param array|\CDbCriteria|string $criteria  An array of criteria, a criteria object, or a comma-delimited list of columns to select
     * @param array                     $params    Bind variable values
     * @param bool                      $singleRow If true, only a single row will be queried
     *
     * @return array
     */
    public static function select( $id = null, $criteria = null, $params = array(), $singleRow = false )
    {
        //	Passed in a comma-delimited string of ids...
        if ( $criteria && is_string( $criteria ) && $criteria !== '*' )
        {
            $criteria = array('select' => $criteria);
        }

        //	Extract proper criteria from third-party library AJAX calls/parameters
        $_fields = Option::get( static::$_payload, 'fields' );
        switch ( static::$_responseFormat )
        {
            case ResponseFormats::DATATABLES:
                $criteria = static::_buildDataTablesCriteria( explode( ',', $_fields ), $criteria );
                break;

            case ResponseFormats::JTABLE:
                $criteria = static::_buildDataTablesCriteria( explode( ',', $_fields ), $criteria );
                break;
        }

        return static::bulkSelectById( null !== $id ? array($id) : null, $criteria, $params, $singleRow );
    }

    /**
     * BULK Methods
     */

    /**
     * @param string $ids
     * @param mixed  $criteria
     * @param array  $params
     * @param bool   $single If true, will return a single array instead of an array of one row
     *
     * @return array|array[]
     */
    public static function bulkSelectById( $ids, $criteria = null, $params = array(), $single = false )
    {
        if ( empty( $ids ) || array(null) == $ids )
        {
            $ids = null;
        }
        else
        {
            $_ids = is_array( $ids ) ? $ids : ( explode( ',', $ids ?: static::$_resourceId ) );
        }

        $_model = static::model();
        $_pk = $_model->tableSchema->primaryKey;

        $_criteria = static::_buildCriteria( $criteria );

        if ( !empty( $_ids ) )
        {
            $_criteria->addInCondition( $_pk, $_ids );
        }

        $_response = array();

        //	Only one row
        if ( false !== $single )
        {
            if ( null !== ( $_model = static::_find( $_criteria, $params ) ) )
            {
                $_response = static::buildResponsePayload( $_model, false );
            }
        }
        //	Multiple rows
        else
        {
            $_models = static::_findAll( $_criteria, $params );

            if ( !empty( $_models ) )
            {
                foreach ( $_models as $_model )
                {
                    $_response[] = static::buildResponsePayload( $_model, false );
                }
            }

            $_response = array('record' => $_response);
        }

        if ( false !== Option::getBool( static::$_payload, 'include_schema', false ) )
        {
            $_response['meta']['schema'] = static::getSchemaForPayload( $_model );
        }

        //	Return a count of rows
        if ( false !== Option::getBool( static::$_payload, 'include_count', false ) )
        {
            $_response['meta']['count'] = static::model()->count( $_criteria, $params );
        }

        return $_response;
    }

    /**
     * @param BasePlatformModel $model
     *
     * @return array
     */
    public static function getSchemaForPayload( $model )
    {
        return SqlDbUtilities::describeTable( null, Pii::db(), $model->tableName(), SystemManager::SYSTEM_TABLE_PREFIX );
    }

    /**
     * @param array $records
     * @param array $extras
     * @param bool  $single_row
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public static function bulkInsert( $records, $extras = null, $single_row = false )
    {
        if ( !empty( $extras ) )
        {
            // override any established request extras
            static::setPayload( $extras );
        }

        static::_validateRecords( $records );

        $_response = array();
        $_transaction = null;
        $_errors = array();

        $_rollback = Option::getBool( static::$_payload, 'rollback' );
        $_continue = Option::getBool( static::$_payload, 'continue' );
        try
        {
            //	Start a transaction
            if ( !$single_row && $_rollback )
            {
                $_transaction = Pii::db()->beginTransaction();
            }

            foreach ( $records as $_key => $_record )
            {
                try
                {
                    $_response[$_key] = static::_insertInternal( $_record );
                }
                catch ( \Exception $_ex )
                {
                    if ( $single_row )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback && $_transaction )
                    {
                        $_transaction->rollBack();
                        throw $_ex;
                    }

                    // track the index of the error and copy error to results
                    $_errors[] = $_key;
                    $_response[$_key] = $_ex->getMessage();
                    if ( !$_continue )
                    {
                        break;
                    }
                }
            }
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( $_ex->getMessage(), $_ex->getCode() );
        }

        if ( !empty( $_errors ) )
        {
            $_msg = array('errors' => $_errors, 'record' => $_response);
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
        }

        //	Commit
        if ( $_transaction )
        {
            try
            {
                $_transaction->commit();
            }
            catch ( \Exception $_ex )
            {
                throw new InternalServerErrorException( $_ex->getMessage(), $_ex->getCode() );
            }
        }

        return $single_row ? current( $_response ) : array('record' => $_response);
    }

    /**
     * @param string $ids
     * @param array  $record
     * @param array  $extras
     * @param bool   $single_row
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public static function bulkUpdateById( $ids, $record, $extras = null, $single_row = false )
    {
        if ( !empty( $extras ) )
        {
            // override any established request extras
            static::setPayload( $extras );
        }

        if ( empty( $record ) )
        {
            throw new BadRequestException( 'There is no record in the request.' );
        }

        $_ids = is_array( $ids ) ? $ids : ( explode( ',', $ids ?: static::$_resourceId ) );

        $_response = array();
        $_transaction = null;
        $_errors = array();

        $_rollback = Option::getBool( static::$_payload, 'rollback' );
        $_continue = Option::getBool( static::$_payload, 'continue' );
        try
        {
            //	Start a transaction
            if ( !$single_row && $_rollback )
            {
                $_transaction = Pii::db()->beginTransaction();
            }

            foreach ( $_ids as $_key => $_id )
            {
                try
                {
                    $_response[$_key] = static::_updateInternal( $_id, $record );
                }
                catch ( \Exception $_ex )
                {
                    if ( $single_row )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback && $_transaction )
                    {
                        $_transaction->rollBack();
                        throw $_ex;
                    }

                    // track the index of the error and copy error to results
                    $_errors[] = $_key;
                    $_response[$_key] = $_ex->getMessage();
                    if ( !$_continue )
                    {
                        break;
                    }
                }
            }
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( $_ex->getMessage(), $_ex->getCode() );
        }

        //	Commit
        if ( $_transaction )
        {
            $_transaction->commit();
        }

        if ( !empty( $_errors ) )
        {
            $_msg = array('errors' => $_errors, 'record' => $_response);
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
        }

        return $single_row ? current( $_response ) : array('record' => $_response);
    }

    /**
     * @param array  $records
     * @param string $extras
     * @param bool   $single_row
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public static function bulkUpdate( $records, $extras = null, $single_row = false )
    {
        if ( !empty( $extras ) )
        {
            // override any established request extras
            static::setPayload( $extras );
        }

        static::_validateRecords( $records );

        $_response = array();
        $_transaction = null;
        $_errors = array();

        $_rollback = Option::getBool( static::$_payload, 'rollback' );
        $_continue = Option::getBool( static::$_payload, 'continue' );
        try
        {
            //	Start a transaction
            if ( !$single_row && $_rollback )
            {
                $_transaction = Pii::db()->beginTransaction();
            }

            $_pk = static::model()->tableSchema->primaryKey;

            foreach ( $records as $_key => $_record )
            {
                try
                {
                    $_response[$_key] = static::_updateInternal( Option::get( $_record, $_pk ), $_record );
                }
                catch ( \Exception $_ex )
                {
                    if ( $single_row )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback && $_transaction )
                    {
                        $_transaction->rollBack();
                        throw $_ex;
                    }

                    // track the index of the error and copy error to results
                    $_errors[] = $_key;
                    $_response[$_key] = $_ex->getMessage();
                    if ( !$_continue )
                    {
                        break;
                    }
                }
            }
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( $_ex->getMessage(), $_ex->getCode() );
        }

        //	Commit
        if ( $_transaction )
        {
            $_transaction->commit();
        }

        if ( !empty( $_errors ) )
        {
            $_msg = array('errors' => $_errors, 'record' => $_response);
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
        }

        return $single_row ? current( $_response ) : array('record' => $_response);
    }

    /**
     * @param string $ids
     * @param string $extras
     * @param bool   $single_row
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public static function bulkDeleteById( $ids, $extras = null, $single_row = false )
    {
        $_ids = is_array( $ids ) ? $ids : ( explode( ',', $ids ?: static::$_resourceId ) );

        return static::bulkDelete( $_ids, $extras, $single_row );
    }

    /**
     * @param array  $records
     * @param string $extras
     * @param bool   $single_row
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public static function bulkDelete( $records, $extras = null, $single_row = false )
    {
        if ( !empty( $extras ) )
        {
            // override any established request extras
            static::setPayload( $extras );
        }

        $_rollback = Option::getBool( static::$_payload, 'rollback' );
        $_continue = Option::getBool( static::$_payload, 'continue' );

        static::_validateRecords( $records );

        $_response = array();
        $_transaction = null;
        $_errors = array();

        try
        {
            //	Start a transaction
            if ( !$single_row && $_rollback )
            {
                $_transaction = Pii::db()->beginTransaction();
            }

            $_pk = static::model()->tableSchema->primaryKey;

            foreach ( $records as $_key => $_record )
            {
                // records could be an array of ids or records containing an id field-value

                if ( is_array( $_record ) )
                {
                    $_id = Option::get( $_record, $_pk );
                }
                else
                {
                    $_id = $_record;
                }
                try
                {
                    $_response[$_key] = static::_deleteInternal( $_id );
                }
                catch ( \Exception $_ex )
                {
                    if ( $single_row )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback && $_transaction )
                    {
                        $_transaction->rollBack();
                        throw $_ex;
                    }

                    // track the index of the error and copy error to results
                    $_errors[] = $_key;
                    $_response[$_key] = $_ex->getMessage();
                    if ( !$_continue )
                    {
                        break;
                    }
                }
            }
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( $_ex->getMessage(), $_ex->getCode() );
        }

        //	Commit
        if ( $_transaction )
        {
            $_transaction->commit();
        }

        if ( !empty( $_errors ) )
        {
            $_msg = array('errors' => $_errors, 'record' => $_response);
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
        }

        return $single_row ? current( $_response ) : array('record' => $_response);
    }

    /**
     * @param BasePlatformSystemModel $resource
     * @param bool                    $refresh
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array
     */
    public static function buildResponsePayload( $resource, $refresh = true )
    {
        if ( empty( $resource ) )
        {
            return array();
        }

        $_fields = Option::get( static::$_payload, 'fields' );
        $_related = Option::get( static::$_payload, 'related' );
        if ( empty( $_fields ) && empty( $_extras ) )
        {
            $_pk = static::model()->tableSchema->primaryKey;

            return array($_pk => $resource->getAttribute( $_pk ));
        }

        //	Refresh requested?
        if ( true === $refresh )
        {
            $resource->refresh();
        }

        $_payload = $resource->getAttributes( $resource->getRetrievableAttributes( $_fields ) );

        if ( !empty( $_related ) )
        {
            $_availableRelations = array_keys( $resource->relations() );

            if ( !empty( $_availableRelations ) )
            {
                $_relatedData = array();

                /**
                 * @var BasePlatformSystemModel[] $_relations
                 */
                foreach ( $_related as $_extra )
                {
                    $_extraName = $_extra['name'];

                    if ( !in_array( $_extraName, $_availableRelations ) )
                    {
                        Log::error( 'Invalid relation "' . $_extraName . '" requested. Available are: ' . implode( ', ', $_availableRelations ) );
                        continue;
                    }

                    $_extraFields = $_extra['fields'];
                    $_relations = $resource->getRelated( $_extraName, true );
                    $_relatedPayload = array();

                    //	Got relations?
                    if ( !empty( $_relations ) )
                    {
                        $_relations = Option::clean( $_relations );
                        $_relative = current( $_relations );

                        if ( !empty( $_relative ) )
                        {
                            $_relatedFields = $_relative->getRetrievableAttributes( $_extraFields );

                            foreach ( $_relations as $_relation )
                            {
                                $_relatedPayload[] = $_relation->getAttributes( $_relatedFields );
                                unset( $_relation );
                            }
                        }

                        unset( $_relatedFields );
                    }

                    $_relatedData[$_extraName] = $_relatedPayload;
                    unset( $_extra, $_relations, $_relative, $_extraFields );
                }

                unset( $_availableRelations );

                if ( !empty( $_relatedData ) )
                {
                    $_payload += $_relatedData;
                }
            }
        }

        return $_payload;
    }

    /**
     * @param string $resourceName
     * @param array  $resources
     *
     * @return BasePlatformRestResource|BasePlatformSystemModel
     */
    public static function resource( $resourceName = null, $resources = array() )
    {
        return static::model( $resourceName, true, $resources );
    }

    /**
     * @param string $resourceName
     * @param bool   $returnResource If true, a RestResource-based class will returned instead of a model
     * @param array  $resources
     *
     * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \Exception
     * @return BasePlatformSystemModel
     */
    public static function model( $resourceName = null, $returnResource = false, $resources = array() )
    {
        /** @var ClassLoader $_loader */
        static $_loader;

        $_returnClass = false;

        if ( !$_loader )
        {
            $_loader = Option::get( $GLOBALS, '_autoloader', \Kisma::get( CoreSettings::AUTO_LOADER ) );
        };

        if ( true === $resourceName && false === $returnResource )
        {
            $resourceName = null;
            $_returnClass = true;
        }

        $_resourceName = $resourceName ?: static::$_resourceName;

        //	Try dynamic system models first
        $_className = \ucwords( $_resourceName );
        $_name = ucfirst( Inflector::deneutralize( $_resourceName ) );

        //	Does the resource have a class?
        if ( false === $returnResource && ( ( class_exists( $_resourceName, false ) || $_loader->loadClass( $_resourceName ) ) ) )
        {
            $_className = $_resourceName;
        }
        //	Does the cleaned name have a class?
        else if ( class_exists( $_name, false ) || $_loader->loadClass( $_name ) )
        {
            $_className = $_name;
        }

        /** @noinspection PhpUndefinedMethodInspection */
        foreach ( false !== $returnResource ? Pii::app()->getResourceNamespaces() : Pii::app()->getModelNamespaces() as $_namespace )
        {
            $_namespace = rtrim( $_namespace, '\\' ) . '\\';

            //	Is it in the namespace?
            if ( class_exists( $_namespace . $_name, false ) || $_loader->loadClass( $_namespace . $_name ) )
            {
                $_className = $_namespace . $_name;
                break;
            }
        }

        //	So, still not found, just let the SPL autoloader have a go and give up.
        if ( empty( $_className ) || ( !empty( $_className ) && !class_exists( $_className, false ) ) )
        {
            throw new \RuntimeException(
                'Invalid ' . ( $returnResource ? 'resource' : 'model' ) . ' type \'' . $_resourceName . '\' requested.'
            );
        }

        //	Return a resource
        if ( false !== $returnResource )
        {
            try
            {
                /** @var BasePlatformRestResource $_resource */
                $_resource = new $_className( Pii::controller(), $resources );
                $_resource->setResponseFormat( static::$_responseFormat );

                return $_resource;
            }
            catch ( ForbiddenException $_ex )
            {
                Log::error( 'Access to resource "' . $_resourceName . '" forbidden: ' . $_ex->getMessage() );
                throw $_ex;
            }
            catch ( \Exception $_ex )
            {
                Log::error( 'Invalid resource class identified: ' . $_className . ' Error: ' . $_ex->getMessage() );
            }
        }

        try
        {
            if ( false !== $_returnClass )
            {
                return new $_className();
            }

            return call_user_func( array($_className, 'model') );
        }
        catch ( ForbiddenException $_ex )
        {
            Log::error( 'Access to model "' . $_resourceName . '" forbidden: ' . $_ex->getMessage() );
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Invalid model class identified: ' . $_className . ' Error: ' . $_ex->getMessage() );
        }

        return null;
    }

    /**
     * Generic permission checker
     *
     * @param string $operation
     * @param string $service
     * @param string $resource
     *
     * @return bool
     */
    public static function checkPermission( $operation, $service = null, $resource = null )
    {
        Session::checkAppPermission();
        Session::checkServicePermission( $operation, $service ?: static::$_service, $resource );

        return true;
    }

    /**
     * Generic permission getter
     *
     * @param string $service
     * @param string $resource
     *
     * @return string
     */
    public static function getPermissions( $service = null, $resource = null )
    {
        return Session::getServicePermissions( $service ?: static::$_service, $resource );
    }

    /**
     * @param array $records
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected static function _validateRecords( &$records )
    {
        if ( empty( $records ) )
        {
            throw new BadRequestException( 'There are no record sets in the request.' );
        }

        if ( !isset( $records[0] ) )
        {
            // conversion from xml can pull single record out of array format
            $records = array($records);
        }
    }

    //*************************************************************************
    //	Internal Provider Specific Methods
    //*************************************************************************

    /**
     * @param int   $id Optional ID
     * @param mixed $criteria
     * @param array $params
     *
     * @throws NotFoundException
     * @return \DreamFactory\Platform\Yii\Models\BasePlatformSystemModel
     */
    protected static function _findByPk( $id = null, $criteria = null, $params = array() )
    {
        if ( null === ( $_resource = static::model()->findByPk( $id ?: static::$_resourceId, $criteria, $params ) ) )
        {
            throw new NotFoundException();
        }

        return $_resource;
    }

    /**
     * @param mixed $criteria
     * @param array $params
     *
     * @throws NotFoundException
     * @internal param int $id Optional ID
     *
     * @return \DreamFactory\Platform\Yii\Models\BasePlatformSystemModel
     */
    protected static function _find( $criteria = null, $params = array() )
    {
        if ( null === ( $_resource = static::model()->find( $criteria, $params ) ) )
        {
            throw new NotFoundException();
        }

        return $_resource;
    }

    /**
     * @param mixed $criteria
     * @param array $params
     *
     * @throws NotFoundException
     * @internal param int $id Optional ID
     *
     * @return \DreamFactory\Platform\Yii\Models\BasePlatformSystemModel
     */
    protected static function _findAll( $criteria = null, $params = array() )
    {
        if ( null === ( $_resources = static::model()->findAll( $criteria, $params ) ) )
        {
            throw new NotFoundException();
        }

        return $_resources;
    }

    /**
     * @param array $record
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException|\Exception
     * @return array
     */
    protected static function _insertInternal( $record )
    {
        if ( empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record to create . ' );
        }

        //	Create record
        $_resource = static::model( true );
        $_resource->setAttributes( $record );

        try
        {
            $_resource->save();
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( 'Failed to create resource "' . static::$_resourceName . '":' . $_ex->getMessage() );
        }

        //	Set related and return
        try
        {
            $_pk = $_resource->tableSchema->primaryKey;

            if ( empty( $_pk ) )
            {
                Log::error( 'Failed to get primary key from created resource "' . static::$_resourceName . '": ' . print_r( $_resource, true ) );

                throw new InternalServerErrorException( 'Failed to get primary key from created user . ' );
            }

            $_resource->setRelated( $record, $_resource->getAttribute( $_pk ) );

            //	Return requested data
            return static::buildResponsePayload( $_resource );
        }
        catch ( BadRequestException $_ex )
        {
            //	Delete the above table entry and clean up
            if ( isset( $_resource ) && !$_resource->getIsNewRecord() )
            {
                $_resource->delete();
            }

            throw $_ex;
        }
    }

    /**
     * @param int   $id
     * @param array $record
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array
     */
    protected static function _updateInternal( $id, $record )
    {
        if ( empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record to create . ' );
        }

        if ( empty( $id ) )
        {
            Log::error( 'Update request with no id supplied: ' . print_r( $record, true ) );
            throw new BadRequestException( 'Identifying field "id" can not be empty for update request . ' );
        }

        $_model = static::_findByPk( $id );

        //	Remove the PK from the record since this is an update
        Option::remove( $record, $_model->tableSchema->primaryKey );

        try
        {
            $_model->setAttributes( $record );
            $_model->save();

            $_model->setRelated( $record, $id );

            return static::buildResponsePayload( $_model );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( 'Failed to update resource: ' . $_ex->getMessage() );
        }
    }

    /**
     * @param int $id
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array
     */
    protected static function _deleteInternal( $id )
    {
        if ( empty( $id ) )
        {
            throw new BadRequestException( "Identifying field 'id' can not be empty for delete request." );
        }

        $_model = static::_findByPk( $id );

        try
        {
            $_model->delete();

            return static::buildResponsePayload( $_model );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( 'Failed to delete "' . static::$_resourceName . '" record:' . $_ex->getMessage() );
        }
    }

    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return \CDbCriteria
     */
    protected static function _buildCriteria( $criteria = null )
    {
        if ( empty( $criteria ) )
        {
            $criteria = array();
        }

        return new \CDbCriteria( $criteria );
    }

    /**
     * Adds criteria garnered from the query string from DataTables
     *
     * @param array|\CDbCriteria $criteria
     *
     * @param array              $columns
     *
     * @return array|\CDbCriteria
     */
    protected static function _buildDataTablesCriteria( $columns, $criteria = null )
    {
        $criteria = static::_buildCriteria( $criteria );
        $_criteria = ( $criteria instanceof \CDbCriteria ? $criteria : new \CDbCriteria( $criteria ) );

        //	Columns
        $_criteria->select = implode( ',', $columns );

        //	Limits
        $_limit = FilterInput::get( INPUT_GET, 'iDisplayLength', -1, FILTER_SANITIZE_NUMBER_INT );
        $_limitStart = FilterInput::get( INPUT_GET, 'iDisplayStart', 0, FILTER_SANITIZE_NUMBER_INT );

        if ( -1 != $_limit )
        {
            $_criteria->limit = $_limit;
            $_criteria->offset = $_limitStart;
        }

        //	Sort
        $_order = array();

        if ( isset( $_GET['iSortCol_0'] ) )
        {
            for ( $_i = 0, $_count = FilterInput::get( INPUT_GET, 'iSortingCols', 0, FILTER_SANITIZE_NUMBER_INT ); $_i < $_count; $_i++ )
            {
                $_column = FilterInput::get( INPUT_GET, 'iSortCol_' . $_i, 0, FILTER_SANITIZE_NUMBER_INT );

                if ( isset( $_GET['bSortable_' . $_column] ) && 'true' == $_GET['bSortable_' . $_column] )
                {
                    $_order[] = $columns[$_column] . ' ' . FilterInput::get( INPUT_GET, 'sSortDir_' . $_i, null, FILTER_SANITIZE_STRING );
                }
            }
        }

        //	Searching...
        $_filter = FilterInput::get( INPUT_GET, 'sSearch', null, FILTER_SANITIZE_STRING );

        if ( !empty( $_filter ) && isset( $_GET['sColumns'] ) )
        {
            $_dtColumns = explode( ',', FilterInput::get( INPUT_GET, 'sColumns', null, FILTER_SANITIZE_STRING ) );

            for ( $_i = 0, $_count = FilterInput::get( INPUT_GET, 'iColumns', 0, FILTER_SANITIZE_NUMBER_INT ); $_i < $_count; $_i++ )
            {
                if ( 'true' == Option::get( $_GET, 'bSearchable_' . $_i ) )
                {
                    $_criteria->addSearchCondition( $_dtColumns[$_i], $_filter, true, 'OR' );
                }
            }
        }

        if ( !empty( $_order ) )
        {
            $_criteria->order = implode( ', ', $_order );
        }

        return $_criteria;
    }

    //*************************************************************************
    //	Properties
    //*************************************************************************

    /**
     * @param int $resourceId
     */
    public static function setResourceId( $resourceId )
    {
        static::$_resourceId = $resourceId;
    }

    /**
     * @return int
     */
    public static function getResourceId()
    {
        return static::$_resourceId;
    }

    /**
     * @param array $payload
     */
    public static function setPayload( $payload )
    {
        static::$_payload = $payload;
    }

    /**
     * @return array
     */
    public static function getPayload()
    {
        return static::$_payload;
    }

    /**
     * @param string $resourceName
     */
    public static function setResourceName( $resourceName )
    {
        static::$_resourceName = $resourceName;
    }

    /**
     * @return string
     */
    public static function getResourceName()
    {
        return static::$_resourceName;
    }

    /**
     * @param string $service
     */
    public static function setService( $service )
    {
        self::$_service = $service;
    }

    /**
     * @return string
     */
    public static function getService()
    {
        return self::$_service;
    }

    /**
     * @param int $responseFormat
     */
    public static function setResponseFormat( $responseFormat )
    {
        self::$_responseFormat = $responseFormat;
    }

    /**
     * @return int
     */
    public static function getResponseFormat()
    {
        return self::$_responseFormat;
    }
}
