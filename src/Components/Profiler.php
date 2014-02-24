<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
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
namespace DreamFactory\Platform\Components;

use Kisma\Core\Enums\DateTime;

/**
 * A simple profiling class
 */
class Profiler
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var array The runs I'm tracking
	 */
	protected static $_runs = array();

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param string $id The id of this profile
	 *
	 * @return float
	 */
	public static function start( $id )
	{
		return static::$_runs[$id] = microtime( true );
	}

	/**
	 * Stops the timer. Returns elapsed as ms or pretty string
	 *
	 * @param string $id
	 * @param bool   $prettyPrint If true, elapsed time will be returned in a state suitable for display
	 *
	 * @return float|string The elapsed time in ms
	 */
	public static function stop( $id, $prettyPrint = true )
	{
		$_elapsed = microtime( true ) - ( isset( static::$_runs[$id] ) ? static::$_runs[$id] : 0.0 );

		return $prettyPrint ? static::elapsedAsString( $_elapsed ) : $_elapsed;
	}

	/**
	 * Full cycle profiler using a callable
	 *
	 * @param string   $id        The name of this profile
	 * @param callable $callable  The code to profile
	 * @param array    $arguments The arguments to send to the profile target
	 * @param int      $count     The number of times to run $callable. Defaults to
	 *
	 * @return float
	 */
	public static function profile( $id, $callable, array $arguments = array(), $count = 1 )
	{
		$_runCount = 0;
		$_runs = array();

		while ( $count >= $_runCount-- )
		{
			$_time = microtime( true );
			call_user_func_array( $callable, $arguments );
			$_runs[] = ( microtime( true ) - $_time );
		}

		//	Summarize the runs
		static::$_runs[$id] = static::_summarizeProfiles( $id, $_runs );

		return $_runs['summary']['average'];
	}

	/**
	 * @param string  $id   The id of this timer
	 * @param float[] $runs An array of run times
	 *
	 * @return array
	 */
	protected static function _summarizeProfiles( $id, array $runs = null )
	{
		$_runs['summary'] = array(
			'iterations' => $_count = count( $_runs = $runs ? : static::$_runs ),
			'total'      => $_total = round( array_sum( $_runs ), 4 ),
			'best'       => round( min( $_runs ), 4 ),
			'worst'      => round( max( $_runs ), 4 ),
			'average'    => round( $_total / $_count, 4 ),
		);

		return $_runs;
	}

	/**
	 * @param float      $start
	 * @param float|bool $stop
	 *
	 * @return string
	 */
	public static function elapsedAsString( $start, $stop = false )
	{
		static $_divisors = array(
			'hour'   => DateTime::US_PER_HOUR,
			'minute' => DateTime::US_PER_MINUTE,
			'second' => DateTime::US_PER_SECOND,
		);

		$_ms = round( ( false === $stop ? $start : ( $stop - $start ) ) * 1000 );

		foreach ( $_divisors as $_label => $_divisor )
		{
			if ( $_ms >= $_divisor )
			{
				$_time = floor( $_ms / $_divisor * 100.0 ) / 100.0;

				return $_time . ' ' . ( $_time == 1 ? $_label : $_label . 's' );
			}
		}

		return $_ms . 'ms';

	}

	/**
	 * @param string $id The id of the profile run to retrieve
	 *
	 * @return array
	 */
	public static function getRun( $id )
	{
		return Option::get( static::$_runs, $id );
	}

	/**
	 * @return array
	 */
	public static function getRuns()
	{
		return static::$_runs;
	}
}