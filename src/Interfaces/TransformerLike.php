<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Interfaces;

/**
 * Something that acts like a data transformer
 */
interface TransformerLike
{
<<<<<<< HEAD:src/Interfaces/TransformerLike.php
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param mixed $dataToFormat
	 * @param array $options Any formatter-specific options
	 *
	 * @return mixed The formatted data
	 */
	public static function format( $dataToFormat, $options = array() );

	/**
	 * Adds criteria garnered from the query string from DataTables
	 *
	 * @param array|\CDbCriteria $criteria
	 * @param array              $columns
	 *
	 * @return array|\CDbCriteria
	 */
	public static function buildCriteria( $columns, $criteria = null );
=======
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string Called before the resource request is dispatched
     */
    const PRE_PROCESS = '{api_name}.{action}.pre_process';
    /**
     * @var string Called after the resource handler has processed the request
     */
    const POST_PROCESS = '{api_name}.{action}.post_process';
    /**
     * @var string Called after data has been formatted for caller but before send
     */
    const AFTER_DATA_FORMAT = '{api_name}.{action}.after_data_format';
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.:src/Events/Enums/ResourceServiceEvents.php
}
