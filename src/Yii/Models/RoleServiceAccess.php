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
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\Utilities;
use Kisma\Core\Utility\Option;

/**
 * RoleServiceAccess.php
 * The system access model for the DSP
 *
 * Columns:
 *
 * @property integer    $id
 * @property integer    $role_id
 * @property integer    $service_id
 * @property string     $component
 * @property string     $access Deprecated, replaced by verbs
 * @property array      $verbs
 * @property array      $filters
 * @property string     $filter_op
 *
 * Relations:
 *
 * @property Role       $role
 * @property Service    $service
 */
class RoleServiceAccess extends BasePlatformSystemModel
{
    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return static::tableNamePrefix() . 'role_service_access';
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
            array('role_id', 'required'),
            array('role_id, service_id', 'numerical', 'integerOnly' => true),
            array('verbs, filter_op', 'length', 'max' => 64),
            array('component', 'length', 'max' => 128),
            array('filters', 'safe'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
            'role'    => array(self::BELONGS_TO, __NAMESPACE__ . '\\Role', 'role_id'),
            'service' => array(self::BELONGS_TO, __NAMESPACE__ . '\\Service', 'service_id'),
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
                'role_id'    => 'Role',
                'service_id' => 'Service',
                'component'  => 'Component',
                'verbs'      => 'Verbs',
                'filters'    => 'Filters',
                'filter_op'  => 'Filter Operator',
            ),
            $additionalLabels
        );

        return parent::attributeLabels( $_labels );
    }

    /** {@InheritDoc} */
    protected function beforeValidate()
    {
        if ( !empty( $this->verbs ) )
        {
            if ( is_array( $this->verbs ) )
            {
                $this->verbs = implode( ',', $this->verbs );
            }
        }
        else
        {
            $this->verbs = '';
        }

        return parent::beforeValidate();
    }

    /**
     * {@InheritDoc}
     */
    public function afterFind()
    {
        if ( is_string( $this->verbs ) )
        {
            if ( !empty( $this->verbs ) )
            {
                $this->verbs = explode( ',', $this->verbs );
            }
            else
            {
                $this->verbs = array();
            }
        }
        if ( empty( $this->verbs ) )
        {
            $this->verbs = Session::convertAccessToVerbs( $this->access );
        }

        parent::afterFind();
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
                    'service_id',
                    'component',
                    'verbs',
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
    public static function assignRoleServiceAccesses( $role_id, $accesses = array() )
    {
        if ( empty( $role_id ) )
        {
            throw new BadRequestException( 'Role ID can not be empty.' );
        }

        try
        {
            $accesses = array_values( $accesses ); // reset indices if needed
            $_count = count( $accesses );

            // check for dupes before processing
            for ( $_key1 = 0; $_key1 < $_count; $_key1++ )
            {
                $_access = $accesses[$_key1];
                $_serviceId = Option::get( $_access, 'service_id' );
                if ( empty( $_serviceId ) )
                {
                    $_serviceId = null;
                    $accesses[$_key1]['service_id'] = null;
                }
                $_component = Option::get( $_access, 'component', '' );
                $_verbs = Option::clean( Option::get( $_access, 'verbs' ) );

                for ( $_key2 = $_key1 + 1; $_key2 < $_count; $_key2++ )
                {
                    $_access2 = $accesses[$_key2];
                    $_serviceId2 = Option::get( $_access2, 'service_id' );
                    if ( empty( $_serviceId2 ) )
                    {
                        $_serviceId2 = null;
                        $accesses[$_key2]['service_id'] = null;
                    }
                    $_component2 = Option::get( $_access2, 'component', '' );
                    $_verbs2 = Option::clean( Option::get( $_access2, 'verbs' ) );
                    if ( ( $_serviceId == $_serviceId2 ) && ( $_component == $_component2 ) )
                    {
                        // any of the verbs match?
                        $_temp = implode( ',', array_intersect( $_verbs, $_verbs2 ) );
                        if ( !empty( $_temp ) )
                        {
                            throw new BadRequestException( "Duplicated service, component, and verb combination '$_serviceId $_component $_temp' in role service access." );
                        }
                    }
                }
            }

            $_oldMaps = static::model()->findAll( 'role_id = :id', array(':id' => $role_id) );
            $_toDelete = array();
            foreach ( $_oldMaps as $_map )
            {
                $_found = false;
                foreach ( $accesses as $_key => $_item )
                {
                    $_newId = Option::get( $_item, 'service_id' );
                    $_newComponent = Option::get( $_item, 'component', '' );
                    $_newVerbs = Option::clean( Option::get( $_item, 'verbs' ) );
                    $_oldVerbs = $_map->verbs;
                    $_verbTest = array_merge(array_diff( $_oldVerbs, $_newVerbs ), array_diff( $_newVerbs, $_oldVerbs ));
                    if ( ( $_newId == $_map->service_id ) &&
                         ( $_newComponent == $_map->component ) &&
                         empty( $_verbTest )
                    )
                    {
                        $_needUpdate = false;
                        $_newFilters = Option::get( $_item, 'filters' );
                        $_newFilters = is_array( $_newFilters ) ? $_newFilters : array();
                        $_diff = Utilities::array_diff_recursive( $_map->filters, $_newFilters, true );
                        if ( !empty( $_diff ) )
                        {
                            $_map->filters = $_newFilters;
                            $_needUpdate = true;
                        }
                        $_newOp = Option::get( $_item, 'filter_op', '' );
                        if ( ( $_map->filter_op != $_newOp ) )
                        {
                            $_map->filter_op = $_newOp;
                            $_needUpdate = true;
                        }
                        if ( $_needUpdate )
                        {
                            // simple update request
                            if ( !$_map->save() )
                            {
                                throw new \Exception( "Record update failed." );
                            }
                        }

                        // otherwise throw it out
                        unset( $accesses[$_key] );
                        $_found = true;
                        continue;
                    }
                }
                if ( !$_found )
                {
                    $_toDelete[] = $_map->id;
                    continue;
                }
            }
            if ( !empty( $_toDelete ) )
            {
                // simple delete request
                $_criteria = new \CDbCriteria();
                $_criteria->addInCondition( 'id', $_toDelete );
                static::model()->deleteAll( $_criteria );
            }
            if ( !empty( $accesses ) )
            {
                foreach ( $accesses as $_record )
                {
                    // simple insert request
                    $_record['role_id'] = (int)$role_id;
                    $_new = new RoleServiceAccess();
                    $_new->setAttributes( $_record );
                    if ( !$_new->save() )
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