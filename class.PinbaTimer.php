<?
/**
Copyright (C) 2010, Sergey Chernomorets <s.chernomorets@gmail.com>
Released under the terms of the GNU General Public License v3

All running timers will stop after pinba_flush().
For example, code bellow will throw warning like
"pinba_timer_stop(): 57 is not a valid pinba timer resource": 

$r1 = pinba_timer_start(array('tag' => 'value1')); 
$r2 = pinba_timer_start(array('tag' => 'value2')); 
pinba_timer_stop($r2); 
pinba_flush(); 
pinba_timer_stop($r1); 

PinbaTimer solve this problem by wrapping pinba_* functions
into object.

Homepage of pinba: http://pinba.org/wiki/Main_Page

*/

define('PINBA_MAX_START_TIMER', 10000);
define('PINBA_ENABLED', is_callable('pinba_timer_start'));

class PinbaTimer
{
	protected static $counter = 0;
	protected $resource = null;

	function __construct($tags = array())
	{
		if(count($tags))
			$this->StartTimer($tags);
	}

	public function StartTimer($tags)
	{
		if(!PINBA_ENABLED) return;
		if( ++self::$counter > PINBA_MAX_START_TIMER )
			self::flush();

		$this->resource = pinba_timer_start($tags);
		pinba_timer_data_replace($this->resource, array('timer' => $this));
	}

	public function StopTimer($soft = false)
	{
		if(!PINBA_ENABLED) return;

		if($soft && !is_resource($this->resource))
			return;
		pinba_timer_stop($this->resource);
	}

	/**
	 *	Flush timers and restart running
	 */
	public static function flush()
	{
		if(!PINBA_ENABLED) return;
		self::$counter=0;

		$pinba_info = pinba_get_info();
		$active_timers = array();
		
		foreach($pinba_info['timers'] as $timer){
			if($timer['started'] && isset($timer['data']['timer'])
				&& is_a($timer['data']['timer'], 'PinbaTimer'))
			{
				$active_timers[] = array(
					'tags' => $timer['tags'],
					'timer' => $timer['data']['timer'],
				);
			}
		}

		pinba_flush();

		/* Well, all timers were running... so just return without
			restart them (increase PINBA_MAX_START_TIMER!) */
		if(count($active_timers) == count($pinba_info['timers']))
			return;

		foreach($active_timers as $timer){
			$timer['timer']->StartTimer($timer['tags']);
		}
	}
}



/*
// Test:

var_dump(PinbaTimerUnitTest(true));
*/
function PinbaTimerUnitTest($verbose = false)
{
	pinba_flush(); // kill all timers
	PinbaTimer::flush(); // reset counter

	for( $i=0; $i < PINBA_MAX_START_TIMER-1; $i++)
	{
		$p = new PinbaTimer(array('tag' => $i));
		$p->StopTimer();
	}

	$pinba_info = pinba_get_info();
	if(count($pinba_info['timers']) != PINBA_MAX_START_TIMER-1)
	{
		if($verbose) var_dump($pinba_info);
		die("ERROR! unexpected number of timers");
	}

	for( $i=PINBA_MAX_START_TIMER-1 ; $i < PINBA_MAX_START_TIMER+1; $i++)
	{
		$p = new PinbaTimer(array('tag' => $i));
	}

	$pinba_info = pinba_get_info();
	if(    $pinba_info['timers'][0]['started'] !== true
		|| $pinba_info['timers'][0]['tags']['tag'] != PINBA_MAX_START_TIMER-1
		|| $pinba_info['timers'][1]['started'] !== true
		|| $pinba_info['timers'][1]['tags']['tag'] != PINBA_MAX_START_TIMER )
	{
		if($verbose) var_dump($pinba_info);
		die("ERROR! unexpected state of timers!");
	}

	// Test passed
	return 0;
}


