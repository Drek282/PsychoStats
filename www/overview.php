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
 *	Version: $Id: overview.php 541 2008-08-18 11:24:58Z lifo $
 */

define("PSYCHOSTATS_PAGE", true);
$basename = basename(__FILE__, '.php');
include(__DIR__ . "/includes/common.php");
include(PS_ROOTDIR . "/includes/class_Color.php");
$cms->theme->page_title('Stats Overview—PsychoStats');

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
	$cms->full_page_err('overview', array(
		'message_title'	=> $cms->trans("No Stats Found"),
		'message'	=> $cms->trans("You must run stats.pl before you will see any stats."),
		'form_key'		=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
		'cookieconsent'	=> $cookieconsent,
	));
	exit();
}
unset ($results);

// default pie slice colors (see styles.xml in the theme to change)
$pie_slice_colors = array(
	'#0033FF','#00B3FF','#00FFCC','#00FF4D','#CC00FF',
	'#7A95FF','#FFD83D','#B3FF00','#FF0033','#FFCC00'
);


// collect url parameters ...
$validfields = array('ip','ofc');
$cms->theme->assign_request_vars($validfields, true);

// return a list of geocoded IP's using the most active IPs in the database
if (is_numeric($ip) and $ip > 0) {
	$limit = min($ip, 100);
	$fields =
		'p.plrid,p.rank,p.skill,p.activity,pp.name,pp.icon,pp.cc,' .
		'c.kills,c.headshotkills,c.onlinetime,c.killsperdeath kpd';

	// return a list of highest ranked players that have lat,lng values set
	$profiles = $ps->db->fetch_rows(1,
		"SELECT $fields,pp.latitude lat,pp.longitude lng " .
		"FROM $ps->t_plr_profile pp, $ps->t_plr p, $ps->c_plr_data c " . 
		"WHERE p.uniqueid=pp.uniqueid AND p.plrid=c.plrid AND p.allowrank=1 AND " . 
		"pp.latitude IS NOT NULL AND pp.longitude IS NOT NULL AND pp.cc <>'A2' " . 
		"ORDER BY p.rank,p.skill DESC,c.kills DESC LIMIT $limit"
	);
	$profiles = array_reverse($profiles);
	if ($profiles) $limit -= count($profiles);

	// return a list of IP's of the highest ranking players.
	$list = $ps->db->fetch_rows(1,
		"SELECT DISTINCT ip.plrid, INET_NTOA(ipaddr) ipaddr, p.plrid," .
		"p.rank,p.skill,p.activity,pp.name,pp.icon,pp.cc,c.kills,c.headshotkills," .
		"c.onlinetime,c.killsperdeath kpd " .
		"FROM $ps->t_plr_ids_ipaddr ip, $ps->t_plr p, $ps->t_plr_profile pp, $ps->c_plr_data c " . 
		"WHERE ip.plrid=p.plrid AND p.uniqueid=pp.uniqueid AND p.plrid=c.plrid AND p.allowrank=1 AND " . 
		"pp.latitude IS NULL AND pp.longitude IS NULL AND pp.cc <>'A2' AND " . 
		"(ipaddr NOT BETWEEN 167772160 AND 184549375) AND " .		// 10/8
		"(ipaddr NOT BETWEEN 2886729728 AND 2887778303) AND " .		// 172.16/12
		"(ipaddr NOT BETWEEN 3232235520 AND 3232301055) AND " .		// 192.168/16
		"(NOT ipaddr IN (2130706433, 0)) " .				// 127.0.0.1, 0.0.0.0
		"GROUP BY ip.plrid " .
		"ORDER BY p.rank,p.skill DESC,c.kills DESC LIMIT $limit"
	);
	$list = array_reverse($list);
	$iplist = array();
	foreach ($list as $p) {
		$iplist[$p['ipaddr']] = $p;
	}
	$xml = $ps->ip_lookup(array_values_by_key($iplist,'ipaddr'), true);

	// process the returned XML so we can add more attributes to the data (plr data)
	// I assume the returned XML is in a proper <markers></markers> format to keep this XML routine simple.
	// its hard to find a simple 'xml2array' function that doesn't add all sorts of extra nested arrays...
	$xp = xml_parser_create(); $index = null;
	xml_parser_set_option($xp, XML_OPTION_CASE_FOLDING, false);
	xml_parser_set_option($xp, XML_OPTION_SKIP_WHITE, true);
	xml_parse_into_struct($xp,$xml,$vals,$index);
	xml_parser_free($xp);

	$markers = array();
	foreach ($vals as $m) {
		if ($m['tag'] != 'marker') continue;
		$set = $m['attributes'];
		$ip = $set['ip'];
		if ($iplist[$ip]) {
			$set = array_merge($set, $iplist[$ip]);
		}
		unset($set['ip'],$set['ipaddr']);
		$set['onlinetime'] = compacttime($set['onlinetime']);
//		$set['activity_bar'] = pct_bar(array('pct' => $set['activity'], 'width' => 215, 'title' => "Activity: " . $set['activity'] . "%" ));

		if (!empty($set['lat']) and !empty($set['lng'])) {
			$markers[ $ip ] = $set;
		}
	}

	// now, spew out the markers XML
	$xml = "<markers>\n";
	$markers = array_merge($profiles, $markers);
	foreach ($markers as $m) {
		$node = "  <marker ";
		foreach ($m as $key => $val) {
			$node .= "$key=\"" . ps_escape_html($val) . "\" ";
		}
		$node .= "/>\n";
		$xml .= $node;
	}
	$xml .= "</markers>\n";
	header("Content-Type: text/xml");
	print $xml;
	exit;
} elseif ($ofc) {
	switch ($ofc) {
		case 'day': return_ofc_day(); break;
		case 'h24': return_ofc_24(); break;
		case 'cc':  return_ofc_cc(); break;
	}
	exit;
}

// generate some colors for the activity bar
$colors = '';
if ($ps->conf['theme']['map']['google_key']) {
	$c = new Image_Color();
	$c->setColors('cc0000','00cc00');
	$range = $c->getRange(100, 1);
	foreach ($range as $i => $col) {
		$colors .= sprintf("<span id='color-%s'>%s</span>\n", $i, $col);
	}
	$colors .= "<span id='color-100'>00CC00</span>\n";
}

// assign variables to the theme
$cms->theme->assign(array(
	'maintenance'		=> $maintenance,
	'activity_colors' 	=> $colors,
	'form_key'			=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'cookieconsent'		=> $cookieconsent,
	'title_logo'		=> ps_title_logo(),
	'game_name'			=> ps_game_name(),
));

// display the output
if ($ps->conf['theme']['map']['google_key']) {
	$p = host_secure() ? 'https' : 'http';
	$cms->theme->add_js($p . '://maps.googleapis.com/maps/api/js?key=' . $ps->conf['theme']['map']['google_key'] . '&callback=init_google', 'defer');
	//$cms->theme->add_js('http://maps.google.com/maps?file=api&amp;v=2&amp;key=' . $ps->conf['theme']['map']['google_key']);
	$cms->theme->add_js('js/map.js');
}
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

function return_ofc_cc() {
	global $cms, $ps, $pie_slice_colors;
	$styles =& $cms->theme->styles;
	
	$max = 8;		// 2 less than the real max (10)
	$data = array();
	$labels = array();
	$exclude = array();
	$total = 0;

	$ps->db->query(
		"SELECT pp.cc,cn,COUNT(*) " . 
		"FROM $ps->t_plr_profile pp, $ps->t_geoip_cc cc " . 
		"WHERE pp.cc IS NOT NULL AND cc.cc=pp.cc " . 
		"GROUP BY pp.cc " . 
		"ORDER BY 3 DESC LIMIT $max"
	);
	while (list($cc,$cn,$cctotal) = $ps->db->fetch_row(0)) {
		$total += $cctotal;
		$data[] = $cctotal;
		$labels[] = '(' . strtoupper($cc) . ") $cn";
		$exclude[] = $cc;
	}

	// get 'other' CC's that were not not in the top <$max>
	list($cctotal) = $ps->db->fetch_list(
		"SELECT COUNT(*) " . 
		"FROM $ps->t_plr_profile pp, $ps->t_geoip_cc cc " . 
		"WHERE pp.cc IS NOT NULL AND cc.cc=pp.cc AND NOT pp.cc IN (" . 
			implode(',', array_map("db_escape", $exclude)) . 
		") "
	);
	$data[] = $cctotal;
	$labels[] = $cms->trans("Other");
	$otheridx = count($data)-1;

/**/
	// get a count of players that have no CC defined
	list($cctotal) = $ps->db->fetch_list(
		"SELECT COUNT(*) " . 
		"FROM $ps->t_plr_profile pp " . 
		"WHERE pp.cc IS NULL "
	);
	$data[] = $cctotal;
	$labels[] = $cms->trans("Unknown");
/**/

	$total = array_sum($data);

	// combine any values that are <= 1%
	if ($otheridx) {
		$extra = 0;
		for ($i=0; $i<$otheridx; $i++) {
			if ($data[$i] / $total * 100 <= 1.0) {
				$data[$otheridx] += $data[$i];
				unset($data[$i], $labels[$i]);
			}
		}
	}

	// calculate percentages for each value (the OFC pie function only accepts percentages, not absolute values.....)
	$values = array();
	foreach ($data as $d) {
		$v = sprintf("%0.1f", $d / $total * 100);
		if (fmod($v, 1) == 0) $v = round($v);
		$values[] = $v;
	}

	include_once(PS_ROOTDIR . '/includes/ofc/open-flash-chart.php');
	$g = new graph();
	$g->bg_colour = $styles->val('flash.pie.bgcolor', 'flash.bgcolor');

	//$g->title(
	//	$styles->val('flash.pie.title', $cms->trans('Country Breakdown'), true),
	//	'{' . $styles->val('flash.pie.title.style', 'background: transparent; font-size: 16px', true) . '}'
	//);
	$g->pie(
		75,
		$styles->val('flash.pie.slices.border', '#505050', true),
		'{' . $styles->val('flash.pie.labels.style', 'font-size: 12px; display: none', true) . '}'
	);
	$g->pie_values($values, $labels);
	$g->pie_slice_colours($styles->attr_list('flash.pie.slices.slice', 'color', $pie_slice_colors, true));
	$g->set_tool_tip($styles->val('flash.pie.tooltip', '#x_label#<br>#val#%', true));

	// display the data
	print $g->render();
}

function return_ofc_24() {
	global $cms, $ps;
	$styles =& $cms->theme->styles;

	$hours = array();
	$labels = array();
	$data = array();
	$data_avg = array();
	$conns = array();
	$conns_avg = array();
	$sum = 0;
	$avg = 0;
	$maxlimit = 100;
	$maxlimit2 = 100;
	$minlimit = 0;
	$max = 24;

	list($newest) = $ps->db->fetch_list("SELECT hour FROM $ps->t_map_hourly ORDER BY statdate DESC,hour DESC LIMIT 1");
	if ($newest === null) $newest = date("H");

	// build a list of hours in the proper order
	for ($h=$newest; count($hours)<24; $h--) {
		if ($h < 0) $h = 23;
		$hours[ sprintf('%02d:00', $h) ] = '0';
	}
	$hours = array_reverse($hours);

	// get the last 24 hours of data
	$ps->db->query(
		"SELECT statdate,hour,SUM(kills),SUM(connections) " . 
		"FROM $ps->t_map_hourly " . 
		"GROUP BY statdate,hour " . 
		"ORDER BY statdate DESC,hour DESC LIMIT $max"
	);

	// build our data and labels
	$data = $hours;
	$conns = $hours;
	$maxdata = 0;
	$maxconn = 0;
	while (list($statdate,$hour,$kills,$connections) = $ps->db->fetch_row(0)) {
		$hh = sprintf('%02d:00', $hour);
		$sum += $kills;
		$data[$hh] = $kills;
		$conns[$hh] = $connections;
		$maxdata = max($maxdata, $kills);
		$maxconn = max($maxconn, $connections);
	}
	$labels = array_keys($hours);
#	print_r($hours);
#	print_r($data);
#	print_r($conns);
#	print_r($labels);

	if ($data) {
		$avg = $sum / count($data);
		$data_avg = array_pad(array(), count($data), $avg);
#		$maxlimit  = ceil(ceil($maxdata / 100) * 100);
	}
	if ($conns) {
		$avg = $sum / count($conns);
		$conns_avg = array_pad(array(), count($conns), $avg);
#		$maxlimit2 = ceil(ceil($maxconn / 100) * 100);
	}

	include_once(PS_ROOTDIR . '/includes/ofc/open-flash-chart.php');
	$g = new graph();
	$g->bg_colour = $styles->val('flash.last24.bgcolor', 'flash.bgcolor');

	//$g->title(
	//	$styles->val('flash.last24.title', $cms->trans('Last 24 Hours'), true),
	//	'{' . $styles->val('flash.last24.title.style', 'background: transparent; font-size: 16px', true) . '}'
	//);

	$g->set_data($data_avg);
	$g->set_data($data);
//	$g->set_data($conns_avg);
	$g->set_data($conns);
	$g->attach_to_y_right_axis(3);

	$lines = $styles->attr('flash.last24.lines.line');
	
	$g->line(
		coalesce($lines[0]['width'], 1),
		coalesce($lines[0]['color'], '#9999ee'),
		coalesce($lines[0]['key'], $cms->trans('Average Kills')), 
		coalesce($lines[0]['key_size'], $styles->val('flash.last24.lines.key_size'), 9)
	);
	$g->line_dot(
		coalesce($lines[1]['width'], 2),
		coalesce($lines[1]['dot_size'], 5),
		coalesce($lines[1]['color'], '#5555ff'),
		coalesce($lines[1]['key'], $cms->trans('Kills')), 
		coalesce($lines[1]['key_size'], $styles->val('flash.last24.lines.key_size'), 9)
	);
	$g->line_dot(
		coalesce($lines[2]['width'], 1),
		coalesce($lines[2]['dot_size'], 3),
		coalesce($lines[2]['color'], '#000000'),
		coalesce($lines[2]['key'], $cms->trans('Connections')), 
		coalesce($lines[2]['key_size'], $styles->val('flash.last24.lines.key_size'), 9)
	);

	// label each point with its value
	$g->set_x_labels($labels);
//	$g->set_x_axis_steps(count($labels) / 3 + 1);
//	$g->set_x_tick_size(1);

	$g->set_x_label_style( 10, '#000000', 0, 3, '#cccccc' );
//	$g->set_x_label_style( 10, '0x000000', 0, 2 );

//	$g->set_x_label_style('none');
#	$g->set_x_label_style( 8, '#000000', 2 );
	$g->set_inner_background(
		coalesce($styles->val('flash.last24.bg_inner1', 'flash.bg_inner1'), '#E3F0FD'),
		coalesce($styles->val('flash.last24.bg_inner2', 'flash.bg_inner2'), '#CBD7E6'),
		coalesce($styles->val('flash.last24.bg_inner_angle', 'flash.bg_inner_angle'), 90)
	);
	$g->x_axis_colour( '#eeeeee', '#eeeeee' );
	$g->y_axis_colour( '#5555ff', '#eeeeee' );
	$g->y_right_axis_colour( '#000000', '#eeeeee' );
//	$g->set_x_offset( false );

	// set the Y max
	$g->set_y_max($maxdata);
	$g->set_y_min(0);
	$g->set_y_right_max($maxconn);
	$g->set_y_right_min(0);
/*
	$g->set_y_max($maxlimit);
	$g->set_y_min($minlimit);
	$g->set_y_right_min($minlimit);
	$g->set_y_right_max($maxlimit2);
*/
	$g->set_y_legend(
		coalesce($lines[1]['key'], $cms->trans('Kills')),
		12,
		coalesce($lines[1]['color'], '#5555ff')
	);
	$g->set_y_right_legend(
		coalesce($lines[2]['key'], $cms->trans('Connections')),
		12,
		coalesce($lines[2]['color'], '#000000')
	);
//	$g->y_label_steps();

	$g->set_tool_tip($styles->val('flash.last24.tooltip', '#key#<br>#val# (#x_label#)', true));

	// label every 20 (0,20,40,60)
//	$g->x_label_steps( 2 );

	// display the data
	print $g->render();
}

function return_ofc_day() {
	global $cms, $ps;
	$styles =& $cms->theme->styles;

	$days = array();
	$labels = array();
	$data = array();
	$data_avg = array();
	$sum = 0;
	$avg = 0;
	$max = 31;
	$maxlimit = 100;

	// get the last 31 days of data
	$list = $ps->db->fetch_rows(1,
		"SELECT statdate,SUM(connections) connections " . 
		"FROM $ps->t_map_data " . 
		"GROUP BY statdate " . 
		"ORDER BY statdate DESC LIMIT $max"
	);

	$now = $list ? ymd2time($list[0]['statdate']) : time();
	while (count($days) < $max) {
		$days[date('Y-m-d',$now)] = 'null';
		$labels[] = date('M jS',$now);
		$now -= 60*60*24;
	}
        $days = array_reverse($days);
        $labels = array_reverse($labels);

	// build our data and labels
	$data = $days;
	$maxdata = 0;
	foreach ($list as $d) {
		if (!array_key_exists($d['statdate'], $days)) continue;
		$sum += $d['connections'];
		$data[ $d['statdate'] ] = $d['connections'];
		$maxdata = max($maxdata, $d['connections']);
	}

	if ($data) {
		$avg = $sum / count($data);
		$data_avg[] = $avg;
		$data_avg = array_pad($data_avg, count($data), 'null');
		$data_avg[] = $avg;
#		$data_avg = array_pad(array(), count($data), $avg);
		$maxlimit  = ceil(ceil($maxdata / 100) * 100);
	}

	include_once(PS_ROOTDIR . '/includes/ofc/open-flash-chart.php');
	$g = new graph();
	$g->bg_colour = $styles->val('flash.conn.bgcolor', 'flash.bgcolor');

	//$g->title(
	//	$styles->val('flash.conn.title', $cms->trans('Daily Connections'), true),
	//	'{' . $styles->val('flash.conn.title.style', 'background: transparent; font-size: 16px', true) . '}'
	//);

#	$g->set_data($data_avg);
#	$g->line(1, '#9999ee', 'Average Connections', 9);

#	$g->set_data($data);
##	$g->line_hollow(1, 3, '#5555ff', 'Connections', 9);
#	$g->bar(75, '#5555ff', 'Connections', 9);

	$avg_line = new line($styles->val('flash.conn.line.width'), $styles->val('flash.conn.line.color'));
	$avg_line->key(
		$styles->val('flast.conn.line.key', $cms->trans('Average Connections'), true),
		$styles->val('flash.conn.line.size', 9, true)
	);
	$avg_line->data = $data_avg;

	$conn_bar = new bar_3d(
		$styles->val('flash.conn.bar3d.opacity', 75, true),
		$styles->val('flash.conn.bar3d.color1', '#5555ff', true),
		$styles->val('flash.conn.bar3d.color2', '#3333DD', true)
	);
	$conn_bar->key(
		$styles->val('flash.conn.bar3d.key', $cms->trans('Connections'), true),
		$styles->val('flash.conn.bar3d.size', 9, true)
	);
	$conn_bar->data = $data;
/*
	$keys = array_keys($data);
	for ($i=0; $i<count($data); $i++) {
		$conn_bar->add_data_tip($data[$keys[$i]], 
			$cms->trans("Connections: %d", $data[$keys[$i]]) . "<br>" . 
			$cms->trans("Average: %d", $data_avg[0])
		);
	}
/**/

	$g->set_tool_tip(sprintf($styles->val('flash.conn.tooltip', '#x_label#<br>#key#: #val# (' . $cms->trans('Avg') . ': %d)', true), round($data_avg[0])));

	$g->data_sets[] = $avg_line;
	$g->data_sets[] = $conn_bar;

	$g->set_x_axis_3d(6);

	// label each point with its value
	$g->set_x_labels($labels);
//	$g->set_x_axis_steps(count($labels) / 3 + 1);
//	$g->set_x_tick_size(1);

	$g->set_x_label_style( 10, '#000000', 0, 3, '#cccccc' );

//	$g->set_x_label_style('none');
#	$g->set_x_label_style( 8, '#000000', 2 );
	$g->set_inner_background(
		coalesce($styles->val('flash.conn.bg_inner1', 'flash.bg_inner1'), '#E3F0FD'),
		coalesce($styles->val('flash.conn.bg_inner2', 'flash.bg_inner2'), '#CBD7E6'),
		coalesce($styles->val('flash.conn.bg_inner_angle', 'flash.bg_inner_angle'), 90)
	);
	$g->x_axis_colour('#909090', '#ADB5C7');
//	$g->x_axis_colour('#eeeeee', '#eeeeee');
	$g->y_axis_colour('#5555ff', '#eeeeee');
//	$g->set_x_offset( false );

	// set the Y max
	$g->set_y_min(0);
	$g->set_y_max($maxlimit);

	$g->set_y_legend(
		$styles->val('flash.conn.bar3d.key', $cms->trans('Connections')),
		12,
		$styles->val('flash.conn.bar3d.color1', '#5555ff', true)
	);

	print $g->render();
}

?>
