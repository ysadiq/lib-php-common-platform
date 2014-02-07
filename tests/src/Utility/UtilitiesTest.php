<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the 'License');
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an 'AS IS' BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Utility;

/**
 * UtilitiesTest
 */
class UtilitiesTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers \DreamFactory\Platform\Utility\Utilities::array_diff_recursive
	 */
	public function test_array_diff_recursive()
	{
		$_original = array(
			'level.1' => array(
				'key.1' => array(
					'subkey.1' => 'subkey.value.1',
				),
				'key.2' => array(
					'subkey.2' => 'subkey.value.2',
				),
				'key.3' => array(
					'subkey.3' => array( 'subkey.value.3.1', 'subkey.value.3.2', 'subkey.value.3.3' ),
				),
			),
			'level.2' => array(
				'key.1' => array(
					'subkey.1' => 'subkey.value.1',
				),
				'key.2' => array(
					'subkey.2' => 'subkey.value.2',
				),
				'key.3' => array(
					'subkey.3' => array( 'subkey.value.3.1', 'subkey.value.3.2', 'subkey.value.3.3' ),
				),
			)
		);

		//	Makin' copies... Should be no difference
		$_copy = array_merge( array(), $_original );
		$_diff = Utilities::array_diff_recursive( $_original, $_copy );
		$this->assertEmpty( $_diff );

		//	Create a mutant copy. Should be different
		$_mutant = array_merge( array(), $_copy );
		$_mutant['level.2'] = null;
		$_diff = Utilities::array_diff_recursive( $_copy, $_mutant );
		$this->assertNotEmpty( $_diff );

		//	A little deeper... should be different too
		$_mutant = array_merge( array(), $_copy );
		$_mutant['level.2']['key.2']['subkey.4'] = 'subkey.4.value';
		$_diff = Utilities::array_diff_recursive( $_copy, $_mutant );
		$this->assertNotEmpty( $_diff );
	}
}
