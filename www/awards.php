<?php
/**
 *	This file is part of PsychoStats.
 *
 *	Written by Jason Morriss
 *	Copyright 2008 Jason Morriss
 *
 *	PsychoStats is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	PsychoStats is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	Version $Id: awards.php 495 2008-06-18 18:41:37Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
include(PS_ROOTDIR . "/includes/class_calendar.php");
$cms->theme->page_title('Awards—PsychoStats');

// Is PsychoStats in maintenance mode?
$maintenance = $ps->conf['main']['maintenance_mode']['enable'];

// Page cannot be viewed if the site is in maintenance mode.
if ($maintenance and !$cms->user->is_admin()) previouspage('index.php');

// create the form variable
$form = $cms->new_form();

// Get cookie consent status from the cookie if it exists.
$cms->session->options['cookieconsent'] ??= false;
($ps->conf['main']['security']['enable_cookieconsent']) ? $cookieconsent = $cms->session->options['cookieconsent'] : $cookieconsent = 1;
if (isset($cms->input['cookieconsent'])) {
	$cookieconsent = $cms->input['cookieconsent'];

	// Update cookie consent status in the cookie if they are accepted.
	// Delete coolies if they are rejected.
	if ($cookieconsent) {
		$cms->session->opt('cookieconsent', $cms->input['cookieconsent']);
		$cms->session->save_session_options();

		// save a new form key in the users session cookie
		// this will also be put into a 'hidden' field in the form
		if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());
		
	} else {
		$cms->session->delete_cookie();
		$cms->session->delete_cookie('_id');
		$cms->session->delete_cookie('_opts');
		$cms->session->delete_cookie('_login');
	}
	previouspage($php_scnm);
}

// Check to see if there is any data in the database before we continue.
$cmd = "SELECT * FROM $ps->t_plr_data LIMIT 1";

$results = array();
$results = $ps->db->fetch_rows(1, $cmd);

// if $results is empty then we have no data in the database
if (empty($results)) {
	$cms->full_page_err('awards', array(
		'maintenance'	=> $maintenance,
		'message_title'	=> $cms->trans("No Stats Found"),
		'message'		=> $cms->trans("You must run stats.pl before you will see any stats."),
		'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
		'cookieconsent'	=> $cookieconsent,
	));
	exit();
}
unset ($results);

// collect url parameters ...
$validfields = array('v','d','time','p');
$cms->theme->assign_request_vars($validfields, true);

// v = what view to display awards as (day,week,month);
// d = date to view awards for (with the specified view)
// p = limit awards matching this plrid

if (!in_array($v, array('day','week','month'))) {
	if ($ps->conf['main']['awards']['daily']) {
		$v = 'day';
	} elseif ($ps->conf['main']['awards']['weekly']) { 
		$v = 'week';
	} else { 
		$v = 'month';
	}
}

if (!is_numeric($p)) $p = '';
$_p = $ps->db->escape($p, true);

$views = array(
	'day'	=> $cms->trans("Day"),
	'week'	=> $cms->trans("Week"),
	'month'	=> $cms->trans("Month")
);

// get the min/max ranges for each award range (view).
$range = array();
$cmd = "SELECT awardrange,MAX(awarddate),MIN(awarddate) FROM $ps->t_awards ";
if ($p) $cmd .= " WHERE topplrid=$_p ";
$cmd .= "GROUP BY awardrange";
$list = $ps->db->fetch_rows(0, $cmd);
foreach ($list as $a) {
	$range[$a[0]]['max'] = $a[1];
	$range[$a[0]]['min'] = $a[2];
}
unset($list);

// no date or the string is invalid?
if (empty($d) or !preg_match('/^\d\d\d\d-\d\d?-\d\d?$/', $d)) {
	$d = date('Y-m-d');
//	$d = $range[$v]['max'];
}

// determine if this date exists
/*
list($valid_date) = $ps->db->fetch_list(sprintf("SELECT 1 FROM $ps->t_awards WHERE awarddate=%s AND awardrange=%s LIMIT 1",
	$ps->db->escape($d, true),
	$ps->db->escape($v, true)
));
// if the selected date is not in the database then default to the newest date for the current view
if (!$valid_date) {
	$d = $range[$v]['max'];
}
*/

// either the selected date or the next oldest date will be returned
$cmd = "SELECT awarddate FROM $ps->t_awards WHERE awardrange = '$v' AND awarddate <= '$d' "; 
if ($p) $cmd .= "AND topplrid=$_p ";
$cmd .= "ORDER BY awarddate DESC LIMIT 1";
list($d) = !empty($ps->db->fetch_list($cmd)) ? $ps->db->fetch_list($cmd) : null;

// if date is still empty then we have no awards in the database (at least not for the selected view)
if (empty($d)) {
	$cms->full_page_err('awards', array(
		'message_title'	=> $cms->trans("No Awards Found"),
		'message'	=> $cms->trans("There are currently no awards in the database to display."),
		'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
		'cookieconsent'	=> $cookieconsent,
	));
	exit();
}

$andplr = $p ? " AND topplrid=$_p" : "";

// select them separately, since it's possible a date will not exist $nX should be empty in that case.
@list($p1) = $ps->db->fetch_list("SELECT awarddate FROM $ps->t_awards WHERE awardrange = 'day' AND awarddate < '$d' $andplr ORDER BY awarddate DESC LIMIT 1");
@list($p2) = $ps->db->fetch_list("SELECT awarddate FROM $ps->t_awards WHERE awardrange = 'week' AND awarddate < '$d' $andplr ORDER BY awarddate DESC LIMIT 1");
@list($p3) = $ps->db->fetch_list("SELECT awarddate FROM $ps->t_awards WHERE awardrange = 'month' AND awarddate < '$d' $andplr ORDER BY awarddate DESC LIMIT 1");

@list($n1) = $ps->db->fetch_list("SELECT awarddate FROM $ps->t_awards WHERE awardrange = 'day' AND awarddate > '$d' $andplr LIMIT 1");
@list($n2) = $ps->db->fetch_list("SELECT awarddate FROM $ps->t_awards WHERE awardrange = 'week' AND awarddate > '$d' $andplr LIMIT 1");
@list($n3) = $ps->db->fetch_list("SELECT awarddate FROM $ps->t_awards WHERE awardrange = 'month' AND awarddate > '$d' $andplr LIMIT 1");

$prev = array( 'day' => $p1, 'week' => $p2, 'month' => $p3 );
$next = array( 'day' => $n1, 'week' => $n2, 'month' => $n3 );

// create column information based on view
if ($v == 'month') {
} elseif ($v == 'week') {
} else { // day
}

// create a calendar
$cal = new Calendar($d);
$cal->startofweek( $ps->conf['main']['awards']['startofweek'] == 'monday' ? 1 : 0 );	// 0=sun, 1=mon
$cal->set_conf(array(
	'show_timeurl'	=> false,
));

$first = $cal->first_date();
$last = $cal->last_date();

// populate the calendar with days that have awards
$list = $ps->db->fetch_rows(1, "SELECT awarddate,awardrange FROM $ps->t_awards WHERE awardrange <> 'week' AND (awarddate BETWEEN '$first' AND '$last') $andplr GROUP BY awarddate");
foreach ($list as $day) {
	$cal->day($day['awarddate'], array('link' => ps_url_wrapper(array( 'v' => $day['awardrange'], 'd' => $day['awarddate'], 'p' => $p ))));
}

$list = $ps->db->fetch_rows(1, "SELECT awarddate FROM $ps->t_awards WHERE awardrange='week' AND (awarddate BETWEEN '$first' AND '$last') $andplr GROUP BY awarddate");
foreach ($list as $week) {
	$cal->week($week['awarddate'], array('link' => ps_url_wrapper(array( 'v' => 'week', 'd' => $week['awarddate'], 'p' => $p ))));
}
// select the week on the calendar so it highlights the week instead of the 1st day
$cal->selected($v);
$cms->theme->assign('calendar', $cal->draw());

// If bots are to be excluded from ranking
$exclude = (!$ps->conf['main']['ranking']['bots_listed']) ? "AND (p.uniqueid NOT LIKE '%BOT%') " : '';

// load the awards for the date specified (for all players; not just the selected one if $p is selected)
$list = $ps->db->fetch_rows(1, 
	"SELECT a.*,ac.phrase,ac.negative,ac.format,ac.rankedonly,ac.description,p.*,pp.* ". 
	"FROM $ps->t_awards a, $ps->t_config_awards ac, $ps->t_plr p, $ps->t_plr_profile pp " .
	"WHERE ac.id=a.awardid AND p.plrid=a.topplrid AND pp.uniqueid=p.uniqueid AND awardrange='$v' AND awarddate='$d' " .
	$exclude .
	"ORDER BY idx,awardtype,awardname"
);
$awards = array();
foreach ($list as $a) {
	if ($a['interpolate']) {
		$ary = unserialize($a['interpolate']);
		if (is_array($ary)) {
			$a = array_merge($a, $ary);
		}
		unset($ary);
	}

	$awards[ $a['awardtype'] ][] = $a;

//	if ($a['awardtype'] == 'player') {
//		$awards[ $a['awardtype'] ][] = $a;
//	} else {
//		// separate weapon awards from weapon class awards...
////		$key = trim(str_replace('weapon class', '', $a['awardweapon']));
//		$awards[ $a['awardtype'] ][] = $a;
//	}

}
//print_r($awards);

$awards['weaponclass'] ??= null;
$awards['weapon'] ??= null;

// assign variables to the theme
$cms->theme->assign(array(
	'maintenance'		=> $maintenance,
	'view_str' 			=> $views[$v],
	'view'				=> $v,
	'date'				=> $d,
	'next'				=> $next,
	'next_str'			=> next_str($next),
	'prev'				=> $prev,
	'prev_str'			=> prev_str($prev),
	'awards_for_str'	=> curr_str($d,$v),
	'awards'			=> $awards,
	'plrid'				=> $p,
	'i_bots'			=> $ps->invisible_bots(),
	'form_key'			=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'		=> $cookieconsent,
	'title_logo'		=> ps_title_logo(),
	'game_name'			=> ps_game_name(),
));

// display the output
$cms->theme->add_css('css/2column.css');	// this page has a left column
$cms->theme->add_css('css/calendar.css');
$cms->theme->add_js('js/calendar.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

function curr_str($d, $v) {
	global $cms, $p;
	$str = "";
	if ($v == 'day') {
		$str = $cms->trans("Daily awards for");
		list($y1,$m1,$d1) = explode('-', date("Y-m-d"));
		list($y2,$m2,$d2) = explode('-', $d);
		if ("$y2$m2" == "$y1$m1" and $d2 == $d1) {
			$str .= " " . $cms->trans("Today");
		} else {
			$str .= date(" M j Y", ymd2time($d));
		}
	} elseif ($v == 'week') {
		$first = ymd2time($d);
		$second = ymd2time($d) + 60*60*24*6;
		$str = $cms->trans("Weekly awards for week") . date(" W, M", $first);
		if (date("m",$first) == date("m",$second)) {	// is it the same month?
			$str .= " " . date("j", $first) . "-" . date("j Y", $second);
		} else {
			$str .= " " . date(" d Y", $first) . " - " . date("M d Y", ymd2time($d)+60*60*24*6);
		}
	} else {
		$str = $cms->trans("Monthly awards for") . date(" F Y", ymd2time($d));
	}
	return $str;
}

function next_str($next) {
	global $cms, $v, $p;
	$str = "";

	if ($next['day']) {
		list($y1,$m1,$d1) = explode('-', date("Y-m-d"));
		list($y2,$m2,$d2) = explode('-', $next['day']);
		if ("$y2$m2" == "$y1$m1" and $d2+0 == $d1+1) {
			$str .= sprintf("<a href='%s' class='next'>%s</a>", 
				ps_url_wrapper(array( 'v' => 'day', 'd' => $next['day'], 'p' => $p )),
				$cms->trans("Today")
			);
		} else {
			$str .= sprintf("<a href='%s' class='next'>%s</a>", 
				ps_url_wrapper(array( 'v' => 'day', 'd' => $next['day'], 'p' => $p )),
				date("M d", ymd2time($next['day']))
			);
		}
	}

	if ($next['week']) {
		$str .= sprintf("<a href='%s' class='next'>%s</a>", 
			ps_url_wrapper(array( 'v' => 'week', 'd' => $next['week'], 'p' => $p )),
			$cms->trans("Week") . date(" W; M d", ymd2time($next['week']))
		);
	}

	if ($next['month']) {
		$str .= sprintf("<a href='%s' class='next'><b>%s</b></a>", 
			ps_url_wrapper(array( 'v' => 'month', 'd' => $next['month'], 'p' => $p )), 
			date("M", ymd2time($next['month']))
		);
	}
	return "<div class='next'>$str</div>";
}

function prev_str($prev) {
	global $cms, $v, $p;
	$str = "";

	if ($prev['month']) {
		$str .= sprintf("<a href='%s' class='prev'>%s</a>", 
			ps_url_wrapper(array( 'v' => 'month', 'd' => $prev['month'], 'p' => $p )), 
			date("M", ymd2time($prev['month']))
		);
	}

	if ($prev['week']) {
		$str .= sprintf("<a href='%s' class='prev'>%s</a>", 
			ps_url_wrapper(array( 'v' => 'week', 'd' => $prev['week'], 'p' => $p )),
			$cms->trans("Week") . date(" W; M d", ymd2time($prev['week']))
		);
	}

	if ($prev['day']) {
		list($y1,$m1,$d1) = explode('-', date("Y-m-d"));
		list($y2,$m2,$d2) = explode('-', $prev['day']);
		if ("$y2$m2" == "$y1$m1" and $d2+0 == $d1-1) {
			$str .= sprintf("<a href='%s' class='prev'>%s</a>", 
				ps_url_wrapper(array( 'v' => 'day', 'd' => $prev['day'], 'p' => $p )),
				$cms->trans("Yesterday")
			);
		} else {
			$str .= sprintf("<a href='%s' class='prev'>%s</a>", 
				ps_url_wrapper(array( 'v' => 'day', 'd' => $prev['day'], 'p' => $p )),
				date("M d", ymd2time($prev['day']))
			);
		}
	}

	return "<div class='prev'>$str</div>";
}

?>
