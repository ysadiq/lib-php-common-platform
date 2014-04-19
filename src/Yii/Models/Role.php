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

use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Exceptions\StorageException;

/**
 * Role.php
 * The system role model for the DSP
 *
 * Columns:
 *
 * @property string              $name
 * @property string              $description
 * @property boolean             $is_active
 * @property integer             $default_app_id
 *
 * Relations:
 *
 * @property App                 $default_app
 * @property RoleServiceAccess[] $role_service_accesses
 * @property RoleSystemAccess[]  $role_system_accesses
 * @property User[]              $users
 * @property App[]               $apps
 * @property Service[]           $services
 * @property LookupKey[]         $lookup_keys
 */
class Role extends BasePlatformSystemModel
{
    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return static::tableNamePrefix() . 'role';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $_rules = array(
            array( 'name', 'required' ),
            array( 'name', 'unique', 'allowEmpty' => false, 'caseSensitive' => false ),
            array( 'name', 'length', 'max' => 64 ),
            array( 'default_app_id', 'numerical', 'integerOnly' => true ),
            array( 'is_active', 'boolean' ),
            array( 'description', 'safe' ),
        );

        return array_merge( parent::rules(), $_rules );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        $_relations = array(
            'default_app'           => array( self::BELONGS_TO, __NAMESPACE__ . '\\App', 'default_app_id' ),
            'role_service_accesses' => array( self::HAS_MANY, __NAMESPACE__ . '\\RoleServiceAccess', 'role_id' ),
            'role_system_accesses'  => array( self::HAS_MANY, __NAMESPACE__ . '\\RoleSystemAccess', 'role_id' ),
            'lookup_keys'           => array( self::HAS_MANY, __NAMESPACE__ . '\\LookupKey', 'role_id' ),
            'users'                 => array( self::HAS_MANY, __NAMESPACE__ . '\\User', 'role_id' ),
            'apps'                  => array( self::MANY_MANY, __NAMESPACE__ . '\\App', 'df_sys_app_to_role(app_id, role_id)' ),
            'services'              => array( self::MANY_MANY, __NAMESPACE__ . '\\Service', 'df_sys_role_service_access(role_id, service_id)' ),
        );

        return array_merge( parent::relations(), $_relations );
    }

    /**
     * @param array $additionalLabels
     *
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels( $additionalLabels = array() )
    {
        $_labels = array(
            'name'           => 'Name',
            'description'    => 'Description',
            'is_active'      => 'Is Active',
            'default_app_id' => 'Default App',
        );

        return parent::attributeLabels( array_merge( $_labels, $additionalLabels ) );
    }

    /**
     * @param array $values
     * @param int   $id
     */
    public function setRelated( $values, $id )
    {
        if ( isset( $values['role_service_accesses'] ) )
        {
            RoleServiceAccess::assignRoleServiceAccesses( $id, $values['role_service_accesses'] );
        }

        if ( isset( $values['role_system_accesses'] ) )
        {
            RoleSystemAccess::assignRoleSystemAccesses( $id, $values['role_system_accesses'] );
        }

        if ( isset( $values['lookup_keys'] ) )
        {
            LookupKey::assignLookupKeys( $values['lookup_keys'], $id );
        }

        if ( isset( $values['apps'] ) )
        {
            $this->assignManyToOneByMap( $id, 'app', 'app_to_role', 'role_id', 'app_id', $values['apps'] );
        }

        if ( isset( $values['users'] ) )
        {
            $this->assignManyToOne( $id, 'user', 'role_id', $values['users'] );
        }

        if ( isset( $values['services'] ) )
        {
            $this->assignManyToOneByMap( $id, 'service', 'role_service_access', 'role_id', 'service_id', $values['services'] );
        }
    }

    /** {@InheritDoc} */
    protected function beforeValidate()
    {
        if ( empty( $this->default_app_id ) )
        {
            $this->default_app_id = null;
        }

        return parent::beforeValidate();
    }

    /**
     * @param \CModelEvent $event
     *
     * @throws StorageException
     */
    public function onBeforeDelete( $event )
    {
        if ( Pii::getState( 'role_id' ) == $this->id )
        {
            throw new StorageException( 'The current role may not be deleted.' );
        }

        parent::onBeforeDelete( $event );
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
                    'name',
                    'description',
                    'is_active',
                    'default_app_id',
                ),
                $columns
            ),
            $hidden
        );
    }

    /**
     * @param array $columns The columns to return in the permissions array
     *
     * @return array|null
     */
    public function getRoleServicePermissions( $columns = null )
    {
        $_perms = null;

        /**
         * @var RoleServiceAccess[] $_permissions
         * @var Service[]           $_services
         */
        if ( $this->role_service_accesses )
        {
            /** @var Role $_perm */
            foreach ( $this->role_service_accesses as $_perm )
            {
                $_permServiceId = $_perm->service_id;
                $_temp = $_perm->getAttributes( $columns ? : array( 'service_id', 'component', 'access', 'filters', 'filter_op' ) );

                if ( $this->services )
                {
                    foreach ( $this->services as $_service )
                    {
                        if ( $_permServiceId == $_service->id )
                        {
                            $_temp['service'] = $_service->api_name;
                        }
                    }
                }

                $_perms[] = $_temp;
            }
        }

        /**
         * @var RoleSystemAccess[] $_permissions
         */
        if ( $this->role_system_accesses )
        {
            /** @var Role $_perm */
            foreach ( $this->role_system_accesses as $_perm )
            {
                $_temp = $_perm->getAttributes( $columns ? : array( 'component', 'access', 'filters', 'filter_op' ) );
                $_temp['service'] = 'system';
                $_perms[] = $_temp;
            }
        }

        return $_perms;
    }
}
