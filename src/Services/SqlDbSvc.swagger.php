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

$_additionalParameters = array(
    array(
        'name'          => 'related',
        'description'   => 'Comma-delimited list of relationship names to retrieve for each record, or \'*\' to retrieve all.',
        'allowMultiple' => true,
        'type'          => 'string',
        'paramType'     => 'query',
        'required'      => false,
    )
);

$_additionalNotes =
    'Use the <b>related</b> parameter to return related records for each resource. ' .
    'By default, no related records are returned.<br/> ';

$_base = require( __DIR__ . '/BaseDbSvc.swagger.php' );

return $_base;
