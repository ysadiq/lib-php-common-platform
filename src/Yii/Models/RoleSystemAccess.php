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
namespace DreamFactory\Platform\Yii\Models;

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Option;

/**
 * RoleSystemAccess.php
 * The system access model for the DSP
 *
 * Columns:
 *
 * @property integer    $id
 * @property integer    $role_id
 * @property string     $component
 * @property string     $access
 * @property array      $filters
 * @property string     $filter_op
 *
 * Relations:
 *
 * @property Role       $role
 */
class RoleSystemAccess extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'role_system_access';
	}

	/**
	 * @return array
	 */
	public function behaviors()
	{
		return array_merge(
			parent::behaviors(),
			array(
				 //	Secure JSON
				 'base_platform_model.secure_json' => array(
					 'class'              => 'DreamFactory\\Platform\\Yii\\Behaviors\\SecureJson',
					 'salt'               => $this->getDb()->password,
					 'insecureAttributes' => array(
						 'filters',
					 )
				 ),
			)
		);
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
			array( 'role_id', 'required' ),
			array( 'role_id', 'numerical', 'integerOnly' => true ),
			array( 'access, filter_op', 'length', 'max' => 64 ),
			array( 'component', 'length', 'max' => 128 ),
			array( 'filters', 'safe' ),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'role' => array( self::BELONGS_TO, __NAMESPACE__ . '\\Role', 'role_id' ),
		);
	}

	/**
	 * @param array $additionalLabels
	 *
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels( $additionalLabels = array() )
	{
		$_labels = array_merge(
			array(
				 'role_id'   => 'Role',
				 'component' => 'Component',
				 'access'    => 'Access',
				 'filters'   => 'Filters',
				 'filter_op' => 'Filter Operator',
			),
			$additionalLabels
		);

		return parent::attributeLabels( $_labels );
	}

	/**
	 * @param string $requested
	 * @param array  $columns
	 * @param array  $hidden
	 *
	 * @return array
	 */
	public function getRetrievableAttributes( $requested, $columns = array(), $hidden = array() )
	{
		return parent::getRetrievableAttributes(
			$requested,
			array_merge(
				array(
					 'role_id',
					 'component',
					 'access',
					 'filters',
					 'filter_op',
				),
				$columns
			),
			$hidden
		);
	}
    /**
     * @param       $role_id
     * @param array $accesses
     *
     * @throws \Exception
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return void
     */
    public static function assignRoleSystemAccesses( $role_id, $accesses = array() )
    {
        if ( empty( $role_id ) )
        {
            throw new BadRequestException( 'Role ID can not be empty.' );
        }

        try
        {
            $accesses = array_values( $accesses ); // reset indices if needed
            $count = count( $accesses );

            // check for dupes before processing
            for ( $key1 = 0; $key1 < $count; $key1++ )
            {
                $access = $accesses[$key1];
                $component = Option::get( $access, 'component', '' );

                for ( $key2 = $key1 + 1; $key2 < $count; $key2++ )
                {
                    $access2 = $accesses[$key2];
                    $component2 = Option::get( $access2, 'component', '' );
                    if ( $component == $component2 )
                    {
                        throw new BadRequestException( "Duplicated system component '$component' in role system access." );
                    }
                }
            }

            $map_table = static::tableNamePrefix() . 'role_system_access';
            $pkMapField = 'id';
            // use query builder
            $command = Pii::db()->createCommand();
            $command->select( 'id,component,access,filters,filter_op' );
            $command->from( $map_table );
            $command->where( 'role_id = :id' );
            $maps = $command->queryAll( true, array( ':id' => $role_id ) );
            $toDelete = array();
            $toUpdate = array();
            foreach ( $maps as $map )
            {
                $manyComponent = Option::get( $map, 'component', '' );
                $id = Option::get( $map, $pkMapField, '' );
                $found = false;
                foreach ( $accesses as $key => $item )
                {
                    $assignComponent = Option::get( $item, 'component', '' );
                    if ( $assignComponent == $manyComponent )
                    {
                        $_needUpdate = false;
                        // found it, make sure nothing needs to be updated
                        $oldAccess = Option::get( $map, 'access', '' );
                        $newAccess = Option::get( $item, 'access', '' );
                        if ( ( $oldAccess != $newAccess ) )
                        {
                            $map['access'] = $newAccess;
                            $_needUpdate = true;
                        }
                        $oldFilters = Option::get( $map, 'filters' );
                        $oldFilters = is_array( $oldFilters ) ? $oldFilters : array();
                        $newFilters = Option::get( $item, 'filters' );
                        $newFilters = is_array( $newFilters ) ? $newFilters : array();
                        $_diff = Utilities::array_diff_recursive( $oldFilters, $newFilters, true );
                        if ( !empty( $_diff ) )
                        {
                            $map['filters'] = json_encode( $newFilters );
                            $_needUpdate = true;
                        }
                        $oldOp = Option::get( $map, 'filter_op', '' );
                        $newOp = Option::get( $item, 'filter_op', '' );
                        if ( ( $oldOp != $newOp ) )
                        {
                            $map['filter_op'] = $newOp;
                            $_needUpdate = true;
                        }
                        if ( $_needUpdate )
                        {
                            $toUpdate[] = $map;
                        }

                        // otherwise throw it out
                        unset( $accesses[$key] );
                        $found = true;
                        continue;
                    }
                }
                if ( !$found )
                {
                    $toDelete[] = $id;
                    continue;
                }
            }
            if ( !empty( $toDelete ) )
            {
                // simple delete request
                $command->reset();
                $rows = $command->delete( $map_table, array( 'in', $pkMapField, $toDelete ) );
                if ( 0 >= $rows )
                {
                    throw new \Exception( "Record delete failed." );
                }
            }
            if ( !empty( $toUpdate ) )
            {
                foreach ( $toUpdate as $item )
                {
                    $itemId = Option::get( $item, 'id', '', true );
                    // simple update request
                    $command->reset();
                    $rows = $command->update( $map_table, $item, 'id = :id', array( ':id' => $itemId ) );
                    if ( 0 >= $rows )
                    {
                        throw new \Exception( "Record update failed." );
                    }
                }
            }
            if ( !empty( $accesses ) )
            {
                foreach ( $accesses as $item )
                {
                    $_filters = Option::get( $item, 'filters' );
                    $_filters = empty( $_filters ) ? null : json_encode( $_filters );
                    // simple insert request
                    $record = array(
                        'role_id'   => $role_id,
                        'component' => Option::get( $item, 'component', '' ),
                        'access'    => Option::get( $item, 'access', '' ),
                        'filters'   => $_filters,
                        'filter_op' => Option::get( $item, 'filter_op', 'AND' )
                    );
                    $command->reset();
                    $rows = $command->insert( $map_table, $record );
                    if ( 0 >= $rows )
                    {
                        throw new \Exception( "Record insert failed." );
                    }
                }
            }
        }
        catch ( \Exception $ex )
        {
            throw new \Exception( "Error updating accesses to role assignment.\n{$ex->getMessage()}" );
        }
    }
}