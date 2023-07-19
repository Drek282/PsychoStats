<?php
/*
		Test script for the PsychoQuery PHP class.
		This will show you how to use the object to make your own queries.

*/

die("TO USE THIS SCRIPT DELETE LINE #" . __LINE__ . " FROM THIS FILE\nThis script is only usable from a prompt (not the web)\n");
define('PS_ROOTDIR', './');
include('includes/class_PQ.php');

$opts = array(
	'querytype'	=> 'halflife',			// use 'oldhalflife' if it doesn't work for your server
);

if ($GLOBALS['argv'][1]) $opts['ip'] = $GLOBALS['argv'][1];	// get ip from command line if something is specified
if ($GLOBALS['argv'][2]) $opts['querytype'] = $GLOBALS['argv'][2];

// create the new object ..
$pq = PQ::create($opts);
$pq->DEBUG = 1;					// disable this if you do not want to see the debug output

/**/
print "server info:\n";
//$data = $pq->query(array('info'));
#$data = $pq->query(array('info','players','rules'));
$data = $pq->query('rules');
//$data = $pq->query('info');
//$data = $pq->query_info();
//$data = $pq->query_players();
print_r($data);
/**/

/**
$time = $pq->pingserver();
if ($time !== FALSE) {
  print "server responded in " . (int)$time . " ms\n";
} else {
  print "server did not respond.";
}
/**/

/**
$cmd = 'status';
print "rcon '$cmd' from server ...\n";
$output = $pq->rcon($cmd, "password");
print $output;
/**/

/**
// This filter is HL2 specific and will not be valid for other games
$filter = array(
	'type'		=> '',		// 
	'secure'	=> '',		// 1=server is running secure
	'gamedir'	=> 'ns',		// ie: cstrike, dod, tfc
	'map'		=> '',		//
	'linux'		=> '',		// 1=server is running linux
	'empty'		=> '',		// 1=server is not empty
	'proxy'		=> '',		// 1=hltv proxy
	'region'	=> PQ_HL2_REGION_USEAST,
);
print "querying master server for ip list ...\n";
$iplist = $pq->query_master('steam1.steampowered.com:27010', $filter, 'master_callback');
print count($iplist) . " IP's returned.\n";
exit();
/**/

// a callback function can be passed to the query_master function, which is called for each ip (or set of ip's)
function master_callback($ip) {
	if (is_array($ip)) {
		foreach ($ip as $singleip) {
			print "$singleip\n";
		}
	} else {
		print "$ip\n";
	}
}

?>
