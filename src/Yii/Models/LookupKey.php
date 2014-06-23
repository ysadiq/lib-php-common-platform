<?php
/**
 * This file is part of the DreamFactory Users Platform(tm) SDK For PHP
 *
 * DreamFactory Users Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
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
use Kisma\Core\Utility\Option;

/**
 * LookupKey.php
 * The system lookup model for the DSP
 *
 * Columns:
 *
 * @property integer    $id
 * @property integer    $role_id
 * @property integer    $user_id
 * @property string     $name
 * @property string     $value
 * @property boolean    $private
 * @property boolean    $allow_user_update
 *
 * Relations:
 *
 * @property Role       $role
 * @property User       $user
 */
class LookupKey extends BasePlatformSystemModel
{
    //*************************************************************************
    //* Methods
    //*************************************************************************
    /**
     * @var bool
     */
    protected static $_internal = false;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return static::tableNamePrefix() . 'lookup_key';
    }

    /**
     * @return array
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            array(
                //	Secure String
                'base_platform_model.secure_string' => array(
                    'class'            => 'DreamFactory\\Platform\\Yii\\Behaviors\\SecureString',
                    'salt'             => $this->getDb()->password,
                    'secureAttributes' => array(
                        'value',
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
            array('name', 'required'),
            array('role_id, user_id', 'numerical', 'integerOnly' => true),
            array('name', 'length', 'max' => 64),
            array('private, allow_user_update', 'boolean', 'allowEmpty' => false),
            array('value', 'safe'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
            'role' => array(self::BELONGS_TO, __NAMESPACE__ . '\\Role', 'role_id'),
            'user' => array(self::BELONGS_TO, __NAMESPACE__ . '\\User', 'user_id'),
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
                'role_id'           => 'Role',
                'user_id'           => 'User',
                'name'              => 'Name',
                'value'             => 'Value',
                'private'           => 'Private',
                'allow_user_update' => 'Allow User Update',
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
                    'name',
                    'value',
                    'private',
                    'allow_user_update',
                ),
                $columns
            ),
            $hidden
        );
    }

    /**
     * {@InheritDoc}
     */
    public function afterFind()
    {
        parent::afterFind();

        // might be json
        if ( !empty( $this->value ) && ( "0" !== $this->value ) )
        {
            $_temp = json_decode( $this->value, true );
            if ( JSON_ERROR_NONE == json_last_error() )
            {
                $this->value = $_temp;
            }
        }

        if ( $this->private && !static::$_internal )
        {
            $this->value = '********';
        }
    }

    /**
     * @param array          $lookups
     * @param integer | null $role_id
     * @param integer | null $user_id
     *
     * @throws \Exception
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return void
     */
    public static function assignLookupKeys( $lookups, $role_id = null, $user_id = null )
    {
        if ( !empty( $role_id ) && !empty( $user_id ) )
        {
            throw new BadRequestException( 'Role ID and User ID can not be set at the same time.' );
        }

        try
        {
            $lookups = array_values( $lookups ); // reset indices if needed
            $_count = count( $lookups );

            // check for dupes before processing
            for ( $_key1 = 0; $_key1 < $_count; $_key1++ )
            {
                $_lookup = $lookups[$_key1];
                $_name = Option::get( $_lookup, 'name', '' );

                for ( $_key2 = $_key1 + 1; $_key2 < $_count; $_key2++ )
                {
                    $_lookup2 = $lookups[$_key2];
                    $_name2 = Option::get( $_lookup2, 'name', '' );
                    if ( $_name == $_name2 )
                    {
                        throw new BadRequestException( "Duplicated lookup '$_name' in role lookup keys." );
                    }
                }
            }

            $_params = array();
            if ( !empty( $role_id ) )
            {
                $_where = 'role_id = :role_id AND user_id IS NULL';
                $_params[':role_id'] = $role_id;
            }
            elseif ( !empty( $user_id ) )
            {
                $_where = 'role_id IS NULL AND user_id = :user_id';
                $_params[':user_id'] = $user_id;
            }
            else
            {
                $_where = 'role_id IS NULL AND user_id IS NULL';
            }

            static::$_internal = true;
            $_oldLookups = static::model()->findAll( $_where, $_params );
            $_toDelete = array();
            foreach ( $_oldLookups as $_old )
            {
                $_found = false;
                foreach ( $lookups as $_key => $_item )
                {
                    $_assignName = Option::get( $_item, 'name', '' );
                    if ( $_assignName == $_old->name )
                    {
                        // found it, make sure nothing needs to be updated
                        $_assignValue = Option::get( $_item, 'value' );
                        $_assignPrivate = Option::getBool( $_item, 'private' );
                        $_assignAllow = Option::getBool( $_item, 'allow_user_update' );
                        if ( $_old->private && !$_assignPrivate )
                        {
                            throw new BadRequestException( 'Private lookups can not be made not private.' );
                        }

                        $_needUpdate = false;
                        if ( !( $_old->private && ( '********' === $_assignValue ) ) &&
                             ( $_old->value != $_assignValue )
                        )
                        {
                            $_old->value = is_array( $_assignValue ) ? json_encode( $_assignValue ) : $_assignValue;
                            $_needUpdate = true;
                        }
                        if ( $_old->private != $_assignPrivate )
                        {
                            $_old->private = $_assignPrivate;
                            $_needUpdate = true;
                        }
                        if ( $_old->allow_user_update != $_assignAllow )
                        {
                            $_old->allow_user_update = $_assignAllow;
                            $_needUpdate = true;
                        }

                        if ( $_needUpdate )
                        {
                            // simple update request
                            if ( !$_old->save() )
                            {
                                throw new \Exception( "Record update failed." );
                            }
                        }

                        // otherwise throw it out
                        unset( $lookups[$_key] );
                        $_found = true;
                        continue;
                    }
                }
                if ( !$_found )
                {
                    $_toDelete[] = $_old->id;
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
            if ( !empty( $_toUpdate ) )
            {
                /** @var LookupKey $_item */
                foreach ( $_toUpdate as $_item )
                {
                    // simple update request
                    if ( !$_item->save() )
                    {
                        throw new \Exception( "Record update failed." );
                    }
                }
            }
            if ( !empty( $lookups ) )
            {
                foreach ( $lookups as $_item )
                {
                    $_value = Option::get( $_item, 'value' );
                    $_value = is_array( $_value ) ? $_value : json_encode( $_value );
                    // simple insert request
                    $_record = array(
                        'name'    => Option::get( $_item, 'name' ),
                        'value'   => $_value,
                        'private' => Option::get( $_item, 'private', false )
                    );
                    if ( !empty( $role_id ) )
                    {
                        $_record['role_id'] = $role_id;
                    }
                    elseif ( !empty( $user_id ) )
                    {
                        $_record['user_id'] = $user_id;
                        $_record['allow_user_update'] = Option::get( $_item, 'allow_user_update', false );
                    }
                    $_new = new LookupKey;
                    $_new->setAttributes( $_record );
                    if ( !$_new->save() )
                    {
                        throw new \Exception( "Record insert failed." );
                    }
                }
            }

            static::$_internal = false;
        }
        catch ( \Exception $ex )
        {
            static::$_internal = false;
            throw new \Exception( "Error updating lookups assignment.\n{$ex->getMessage()}" );
        }
    }

    /**
     * @param integer | null $role_id
     * @param integer | null $user_id
     * @param bool           $mask_private
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \Exception
     * @return array
     */
    public static function getLookupKeys( $role_id = null, $user_id = null, $mask_private = true )
    {
        if ( !empty( $role_id ) && !empty( $user_id ) )
        {
            throw new BadRequestException( 'Role ID and User ID can not be requested at the same time.' );
        }

        try
        {
            $_criteria = new \CDbCriteria();
            $_params = array();
            if ( !empty( $role_id ) )
            {
                $_criteria->addCondition( 'role_id = :role_id AND user_id IS NULL' );
                $_params[':role_id'] = $role_id;
            }
            elseif ( !empty( $user_id ) )
            {
                $_criteria->addCondition( 'role_id IS NULL AND user_id = :user_id' );
                $_params[':user_id'] = $user_id;
            }
            else
            {
                $_criteria->addCondition( 'role_id IS NULL AND user_id IS NULL' );
            }

            static::$_internal = $mask_private;
            $_lookups = static::model()->findAll( $_criteria, $_params );
            $_out = array();
            /** @var LookupKey $_lookup */
            foreach ( $_lookups as $_lookup )
            {
                $_out[] = $_lookup->getAttributes();
            }
            static::$_internal = false;

            return $_out;
        }
        catch ( \Exception $ex )
        {
            throw new \Exception( "Error retrieving lookups for global.\n{$ex->getMessage()}" );
        }
    }

    /**
     * @param integer | null $role_id
     * @param integer | null $user_id
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \Exception
     * @return array
     */
    public static function getForSession( $role_id = null, $user_id = null )
    {
        try
        {
            $_where = 'role_id IS NULL AND user_id IS NULL';
            $_params = array();
            if ( !empty( $role_id ) )
            {
                $_where .= ' OR role_id = :role_id';
                $_params[':role_id'] = $role_id;
            }
            if ( !empty( $user_id ) )
            {
                $_where .= ' OR user_id = :user_id';
                $_params[':user_id'] = $user_id;
            }

            static::$_internal = true;
            $_lookups = static::model()->findAll( $_where, $_params );
            //  Build semi-flat comparison array
            $_flattened = array();
            /** @var LookupKey $_lookup */
            foreach ( $_lookups as $_lookup )
            {
                $_data = $_lookup->getAttributes();
                if ( !array_key_exists( $_data['name'], $_flattened ) )
                {
                    if ( !isset( $_data['role_id'] ) )
                    {
                        $_data['role_id'] = -1;
                    }

                    if ( !isset( $_data['user_id'] ) )
                    {
                        $_data['user_id'] = -1;
                    }

                    $_flattened[$_data['name']] = $_data;

                    continue;
                }

                $_targetUserId = $_flattened[$_data['name']]['user_id'];
                $_targetRoleId = $_flattened[$_data['name']]['role_id'];

                $_userId = ( isset( $_data['user_id'] ) ? $_data['user_id'] : -1 );
                $_roleId = ( isset( $_data['role_id'] ) ? $_data['role_id'] : -1 );

                if ( -1 != $_userId && $_userId > $_targetUserId )
                {
                    $_flattened[$_data['name']] = $_data;
                    continue;
                }

                if ( -1 == $_targetUserId && -1 != $_roleId && $_roleId > $_targetRoleId )
                {
                    $_flattened[$_data['name']] = $_data;
                    continue;
                }
            }

            $_plain = array();
            $_secret = array();
            foreach ( $_flattened as $_key => $_lookup )
            {
                if ( Option::getBool( $_lookup, 'private' ) )
                {
                    $_secret[$_key] = $_lookup['value'];
                }
                else
                {
                    $_plain[$_key] = $_lookup['value'];
                }
            }

            return array('lookup' => $_plain, 'secret' => $_secret);
        }
        catch ( \Exception $ex )
        {
            throw new \Exception( "Error retrieving lookups for session.\n{$ex->getMessage()}" );
        }
    }
}