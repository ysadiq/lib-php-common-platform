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
namespace DreamFactory\Platform\Components;

use Kisma\Core\Enums\DateTime;
use Kisma\Core\Utility\Log;

/**
 * Profiler includes
 */
require_once 'xhprof_lib/utils/xhprof_lib.php';
require_once 'xhprof_lib/utils/xhprof_runs.php';

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
		static::$_runs[$id] = array( 'start' => microtime( true ), 'xhprof' => false );

		if ( function_exists( 'xhprof_enable' ) )
		{
			/** @noinspection PhpUndefinedConstantInspection */
			xhprof_enable( XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY );
		}

		return static::$_runs[$id];
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
		static::$_runs[$id]['stop'] = microtime( true );

		if ( !isset( static::$_runs[$id]['start'] ) )
		{
			static::$_runs[$id]['start'] = static::$_runs[$id]['stop'];
		}

		static::$_runs[$id]['elapsed'] = ( static::$_runs[$id]['stop'] - static::$_runs[$id]['start'] );

		if ( static::$_runs[$id]['xhprof'] )
		{
			/** @noinspection PhpUndefinedFunctionInspection */
			/** @noinspection PhpUndefinedMethodInspection */
			static::$_runs[$id]['xhprof'] = array(
				'data'     => $_data = xhprof_disable(),
				'run_name' => $_runName = $id . microtime( true ),
				'runs'     => $_runs = XHProfRuns_Default(),
				'run_id'   => $_runId = $_runs->save_run( $_data, $_runName ),
				'url'      => '/xhprof/index.php?run=' . $_runId . '&source=' . $_runName,
			);

			Log::debug( '~!~ profiler link: ' . static::$_runs[$id]['xhprof']['url'] );
		}

		return $prettyPrint ? static::elapsedAsString( static::$_runs[$id]['elapsed'] ) : static::$_runs[$id]['elapsed'];
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
			$_run = array(
				'start'   => $_time = microtime( true ),
				'end'     => 0,
				'elapsed' => 0,
				'xhprof'  => null,
			);

			if ( function_exists( 'xhprof_enable' ) )
			{
				xhprof_enable();
			}

			call_user_func_array( $callable, $arguments );

			if ( function_exists( 'xhprof_disable' ) )
			{
				$_run['xhprof'] = xhprof_disable();
			}

			$_run['elapsed'] = ( $_run['end'] = microtime( true ) ) - $_run['start'];

			$_runs[] = $_run;

			unset( $_run );
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
			'h' => DateTime::US_PER_HOUR,
			'm' => DateTime::US_PER_MINUTE,
			's' => DateTime::US_PER_SECOND,
		);

		$_ms = round( ( false === $stop ? $start : ( $stop - $start ) ) * 1000 );

		foreach ( $_divisors as $_label => $_divisor )
		{
			if ( $_ms >= $_divisor )
			{
				$_time = floor( $_ms / $_divisor * 100.0 ) / 100.0;

				return $_time . $_label;
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
