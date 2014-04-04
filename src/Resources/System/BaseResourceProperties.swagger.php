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

/**
 * This file returns an array of common properties that can be merged
 * with a model's unique properties, to complete a full model
 */
return array(
    'created_date'        => array(
        'type'        => 'string',
        'description' => 'The creation date and time of the record',
        'readOnly'    => true,
    ),
    'created_by_id'       => array(
        'type'        => 'integer',
        'format'      => 'int32',
        'description' => 'The ID of the user that created this record',
        'readOnly'    => true,
    ),
    'last_modified_date'  => array(
        'type'        => 'string',
        'description' => 'The date and time of this record\'s last modification',
        'readOnly'    => true,
    ),
    'last_modified_by_id' => array(
        'type'        => 'integer',
        'format'      => 'int32',
        'description' => 'The ID of the user that last modified this record',
        'readOnly'    => true,
    ),
);