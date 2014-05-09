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
 * Component.php
 * The system component model for the DSP
 *
 * Columns:
 *
 * @property string              $name
 * @property string              $path
 * @property string              $provider
 * @property string              $description
 * @property string              $version
 * @property boolean             $is_active
 *
 */
class Component extends BasePlatformSystemModel
{
    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return static::tableNamePrefix() . 'component';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $_rules = array(
            array( 'name, path, provider, version', 'required' ),
            array( 'name, path', 'unique', 'allowEmpty' => false, 'caseSensitive' => false ),
            array( 'is_active', 'boolean' ),
            array( 'description', 'safe')
        );

        return array_merge( parent::rules(), $_rules );
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
            'path'           => 'Path',
            'provider'       => 'Provider',
            'description'    => 'Description',
            'version'        => 'Version',
            'is_active'      => 'Is Active',
        );

        return parent::attributeLabels( array_merge( $_labels, $additionalLabels ) );
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
                    'path',
                    'provider',
                    'description',
                    'version',
                    'is_active',
                ),
                $columns
            ),
            $hidden
        );
    }
}
