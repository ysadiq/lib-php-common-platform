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
use Kisma\Core\Utility\Option;

/**
 * ServiceDoc.php
 * The system access model for the DSP
 *
 * Columns:
 *
 * @property integer    $id
 * @property integer    $service_id
 * @property string     $format
 * @property string     $content
 *
 * Relations:
 *
 * @property Service    $service
 */
class ServiceDoc extends BasePlatformSystemModel
{
    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return static::tableNamePrefix() . 'service_doc';
    }

    /**
     * @return array
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            array(
                //	Secure Frozen Object
                'base_platform_model.secure_frozen_object' => array(
                    'class'              => 'DreamFactory\\Platform\\Yii\\Behaviors\\SecureFrozenObject',
                    'salt'               => $this->getDb()->password,
                    'insecureAttributes' => array(
                        'content',
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
            array('service_id', 'numerical', 'integerOnly' => true),
            array('format', 'length', 'max' => 64),
            array('content', 'safe'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
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
                'service_id' => 'Service',
                'format'     => 'Format',
                'content'    => 'Content',
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
                    'service_id',
                    'format',
                    'content',
                ),
                $columns
            ),
            $hidden
        );
    }

    /**
     * @param int   $service_id
     * @param array $docs
     *
     * @throws \Exception
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return void
     */
    public static function assignServiceDocs( $service_id, $docs = array() )
    {
        if ( empty( $service_id ) )
        {
            throw new BadRequestException( 'Service Id can not be empty.' );
        }

        try
        {
            $docs = array_values( $docs ); // reset indices if needed
            $_count = count( $docs );

            // check for dupes before processing
            for ( $_key1 = 0; $_key1 < $_count; $_key1++ )
            {
                $_doc = $docs[$_key1];
                $_format = Option::get( $_doc, 'format', '' );

                for ( $_key2 = $_key1 + 1; $_key2 < $_count; $_key2++ )
                {
                    $_doc2 = $docs[$_key2];
                    $_format2 = Option::get( $_doc2, 'format', '' );
                    if ( $_format == $_format2 )
                    {
                        throw new BadRequestException( "Duplicate service doc defined for format '$_format'." );
                    }
                }
            }

            $_oldDocs = static::model()->findAll( 'service_id = :id', array(':id' => $service_id) );
            $_toDelete = array();
            foreach ( $_oldDocs as $_doc )
            {
                $_found = false;
                foreach ( $docs as $_key => $_item )
                {
                    $_newId = Option::get( $_item, 'service_id' );
                    $_newFormat = Option::get( $_item, 'format', '' );
                    if ( ( $_newId == $_doc->service_id ) && ( $_newFormat == $_doc->format ) )
                    {
                        $_doc->content = Option::get( $_item, 'content' );
                        // simple update request
                        if ( !$_doc->save() )
                        {
                            throw new \Exception( "Record update failed." );
                        }

                        // otherwise throw it out
                        unset( $docs[$_key] );
                        $_found = true;
                        continue;
                    }
                }
                if ( !$_found )
                {
                    $_toDelete[] = $_doc->id;
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
            if ( !empty( $docs ) )
            {
                foreach ( $docs as $_record )
                {
                    // simple insert request
                    $_record['service_id'] = (int)$service_id;
                    $_new = new ServiceDoc();
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