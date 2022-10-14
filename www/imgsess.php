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
 *	Version: $Id: imgsess.php 541 2008-08-18 11:24:58Z lifo $
 */

/*
	Displays a graph that shows the total connections for the last 30 days
*/
define("PSYCHOSTATS_PAGE", true);
include(__DIR__ . "/includes/imgcommon.php");
include(JPGRAPH_DIR . '/jpgraph_gantt.php');

$plrid = is_numeric($_GET['id']) ? $_GET['id'] : 0;
//$noname = isset($_GET['nn']);

$imgfilename = 'auto';
$data = array();
$labels = array();
//$plrname = "Unknown";

$styles =& $cms->theme->styles;
$imgconf = array();
$s = array();

$showempty = false;

// how many days are allowed in the image.
$max = 14;

if (!isImgCached($imgfilename)) {
	$showempty = (bool)$styles->val('image.session.bar.showempty', false, true);

	//if (!$noname) {
	//	$plrname = $ps->db->fetch_item("SELECT name FROM $ps->t_plr p, $ps->t_plr_profile pp WHERE p.uniqueid=pp.uniqueid AND p.plrid='" . $ps->db->escape($plrid) . "'");
	//}
	$ps->db->query("SELECT sessionstart,sessionend FROM $ps->t_plr_sessions WHERE plrid='" . $ps->db->escape($plrid) . "' ORDER BY sessionstart DESC");
	$idx = 1;
	$d = array();
	while (list($start,$end) = $ps->db->fetch_row(0)) {
		if (count($d) >= $max) break;
		$d1 = date("Y-m-d", $start);
		$d2 = date("Y-m-d", $end);

		// fill in the gap from the current and previous dates
		if ($showempty and count($data) and $data[count($data)-1][1] > $d1) {
			$diff = floor(($data[count($data)-1][4] - $start) / (60*60*24));
			$empty = $data[count($data)-1][4];
			for ($i=0; $i < $diff; $i++) {
				if (count($d) >= $max) break;
				$empty = $empty - 60*60*24;
				if (!$d[time2ymd($empty)]) $d[time2ymd($empty)] = $idx++;
				$data[] = array(
					$d[time2ymd($empty)]-1,
					date("Y-m-d", $empty),
					'00:00',
					'00:00',
					$empty
				);
			}
		}

		// need to wrap the session to the next day
		if ($d2 > $d1) {
			if (!$d[$d2]) $d[$d2] = $idx++;
			$data[] = array(
				$d[$d2]-1,
				date("Y-m-d", $end),
				"00:00",
				date("H:i", $end),
				$start
			);
		}

		$d[$d1] ??= null;
		if (!$d[$d1]) $d[$d1] = $idx++;
		$data[] = array(
			$d[$d1]-1,
			date("Y-m-d", $start),
			date("H:i", $start),
			$d2 <= $d1 ? date("H:i", $end) : "23:59",
			$start
		);
	}
}

if (!$data) {
	// fake 1 record so the chart doesn't error
	$data[] = array(
		0,
		date("Y-m-d"),
		'00:00',
		'00:00',
		time()
	);
}

// remove any and all output buffers
$ps->ob_clean();

$graph = new GanttGraph(0,0,$imgfilename, CACHE_TIMEOUT);

$showfooter = (bool)$styles->val('image.session.showfooter', 'image.common.footer.show');

$top 	= intval($styles->val('image.session.margin.top', 	'image.common.margin.top', true));
$right 	= intval($styles->val('image.session.margin.right', 	'image.common.margin.right', true));
$bottom	= intval($styles->val('image.session.margin.bottom', 	'image.common.margin.bottom', true));
$left 	= intval($styles->val('image.session.margin.left', 	'image.common.margin.left', true));

$graph->setMargin($left,$right,$top,($showfooter and $bottom<14) ? 14 : $bottom);
$graph->ShowHeaders(GANTT_HHOUR);

//$graph->SetBackgroundGradient('gray','whitesmoke',GRAD_LEFT_REFLECTION,BGRAD_MARGIN); 
$graph->SetMarginColor($styles->val('image.session.frame.margin', '#C4C4C4', true)); 
$graph->SetFrame(true,
		 $styles->val('image.session.frame.color', 'gray', true),
		 $styles->val('image.session.frame.width', 1, true)
); 

//if (!$noname) $graph->title->Set($plrname);
//$graph->title->SetColor('blue');
//$graph->subtitle->Set(imgdef($s['frame']['title'], 'Player Sessions'));
//$graph->subtitle->SetFont(constant(imgdef($s['@frame']['font'], 'FF_FONT0')));

// must override the weekend settings ...
$graph->scale->UseWeekendBackground(false);
$graph->scale->day->SetWeekendColor($styles->val('image.session.header.bgcolor','lightyellow:1.5',true));
$graph->scale->day->SetFontColor($styles->val('image.session.header.color', 'black', true));
$graph->scale->day->SetSundayFontColor($styles->val('image.session.header.color', 'black', true));

// match the weekend settings ...
$graph->scale->hour->SetFontColor($styles->val('image.session.header.color', 'black', true));
$graph->scale->hour->SetBackgroundColor($styles->val('image.session.header.bgcolor','lightyellow:1.5',true));

$graph->scale->hour->SetFont(constant($styles->val('image.session.font', 'FF_FONT0', true)));
$graph->scale->hour->SetIntervall($styles->val('image.session.interval', 2, true));
$graph->scale->hour->SetStyle(constant($styles->val('image.session.header.hourstyle', 'HOURSTYLE_H24', true)));

/**
$graph->scale->actinfo->SetBackgroundColor('lightyellow:1.5');
$graph->scale->actinfo->SetFont(FF_FONT0);
$graph->scale->actinfo->SetColTitles(array(""));
/**/

$show = (bool)$styles->val('image.session.hgrid.show', true, true);
$graph->hgrid->Show($show);
if ($show) {
	$graph->hgrid->SetRowFillColor(
		$styles->val('image.session.hgrid.color1', 'whitesmoke@0.9', true),
		$styles->val('image.session.hgrid.color2', 'darkblue@0.9', true)
	);
}

for($i=0; $i<count($data); ++$i) {
	$bar = new GanttBar($data[$i][0],$data[$i][1],$data[$i][2],$data[$i][3]);
	$bar->SetPattern(
		constant($styles->val('image.session.bar.pattern', 'BAND_RDIAG', true)),
		$styles->val('image.session.bar.patternfill', 'lightblue', true)
	);
	$bar->SetFillColor($styles->val('image.session.bar.fill', 'BAND_SOLID', true));
	$shadow = $styles->val('image.session.bar.shadow','', true);
	if ($shadow) {
		$bar->SetShadow(true, $shadow);
	}
	$graph->Add($bar);
}
$graph->SetVMarginFactor($styles->val('image.session.bar.vmargin', 0.4, true));

if ($styles->val('image.session.showfooter', 'image.common.footer.show')) {
	stdImgFooter($graph);
}
$graph->Stroke();

?>
