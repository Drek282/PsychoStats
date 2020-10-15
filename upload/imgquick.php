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
 *	Version: $Id: imgquick.php 367 2008-03-17 17:47:45Z lifo $
 */

/*
	Displays a graph that shows the skill history of the player ID given
*/
define("PSYCHOSTATS_PAGE", true);
include(__DIR__ . "/includes/imgcommon.php");
include(JPGRAPH_DIR . '/jpgraph_line.php');
include(JPGRAPH_DIR . '/jpgraph_regstat.php');

$plrid = is_numeric($_GET['id']) ? $_GET['id'] : 0;
$var = in_array(strtolower($_GET['v']), array('skill','kills','onlinetime')) ? strtolower($_GET['v']) : 'skill';
//if ($var == 'skill') $var = 'dayskill';
$_GET = array( 'id' => $plrid, 'v' => $var );

//list($base,$ext) = explode('.', GenImgName());
//$imgfilename = $base . "_" . $plrid . '.' . $ext;
$imgfilename = 'auto';
$datay = array();
$datax = array();
$labels = array();
$sum = 0;
$avg = 0;
$interval = 0;
$minlimit = 0;
$maxlimit = 0;

$smooth = 3;
$max = 30;	// more than 30 starts to look ugly

$imgconf = array();
$q = array();

if (!isImgCached($imgfilename)) {
	$imgconf = load_img_conf();
	$q =& $imgconf['quickimg'];

	$field = $var == 'skill' ? 'dayskill' : $var;
	$ps->db->query("SELECT statdate,$field FROM $ps->t_plr_data WHERE plrid='" . $ps->db->escape($plrid) . "' ORDER BY statdate LIMIT $max");
	$i = 1;
	while (list($statdate,$skill) = $ps->db->fetch_row(0)) {
		$skill = round($skill);
		$sum += $skill;
		$datay[] = $skill;
		$datax[] = $i++;
		$labels[] = $statdate;
	}

	// DEBUG
/*
	while (count($data) < 30) {
		$totalforday = rand(5000,10000);
		$sum += $totalforday;
		$data[] = $totalforday;
		$labels[] = $labels[0];
	}
/**/
}

// Not enough data to produce a proper graph
// jpgraph will crash if we give it an empty array
if (!count($datay)) {
	$sum = 0;
	$datay[] = 0;
} elseif (count($datay) == 1) {
	$datay[1] = $datay[0];
	$datax[1] = $datax[0] + 1;
}

// calculate the average of our dataset
if (count($datay)) {
	$avg = $sum / count($datay);

	if ($var == 'skill') {
		$interval = imgdef($q['interval'], 3000);
	}

	if ($interval) {
		$minlimit = floor(min($datay) / $interval) * $interval;
		$maxlimit = ceil(max($datay) / $interval) * $interval;
	}
}

// Setup the graph.
$graph = new Graph(imgdef($q['width'], 287), imgdef($q['height'], 180) , $imgfilename, CACHE_TIMEOUT);
if ($interval) {
	$graph->SetScale("textlin", $minlimit, $maxlimit);
} else {
	$graph->SetScale("textlin");
}
$graph->SetMargin(45,10,10,20);
$graph->title->Set(imgdef($q['frame']['title']['_content'], 'Quick History'));

//$graph->yaxis->HideZeroLabel(); 
if ($var != 'onlinetime') {
	$graph->yaxis->SetLabelFormat('%d'); 
} else {
	$graph->yaxis->SetLabelFormatCallback('conv_onlinetime');
}

if (count($datay)<2 or !imgdef($q['frame']['xgrid']['show'], true)) {
	$graph->xaxis->Hide();
}
$graph->xaxis->HideLabels();
$graph->xaxis->HideTicks();

$graph->ygrid->SetFill((bool)imgdef($q['frame']['ygrid']['show'], true),
	imgdef($q['frame']['ygrid']['color1'], 'whitesmoke'),
	imgdef($q['frame']['ygrid']['color2'], 'azure2')
);
$graph->ygrid->Show((bool)imgdef($q['frame']['ygrid']['show'], true));

$font1 = constant(imgdef($q['frame']['title']['font'], 'FF_FONT1'));
$legendfont = constant(imgdef($q['legend']['font'], 'FF_FONT0'));
$graph->title->SetFont($font1, FS_BOLD);
$graph->yaxis->title->SetFont($font1,FS_BOLD);
$graph->legend->SetFont($legendfont,FS_NORMAL);
#$graph->xaxis->title->SetFont($font1,FS_BOLD);
#$graph->xaxis->SetFont(FF_FONT0,FS_NORMAL);

$graph->SetMarginColor(imgdef($q['frame']['margin'], '#d7d7d7')); 
$graph->SetFrame(true,imgdef($q['frame']['color'], 'gray'), imgdef($q['frame']['width'], 1)); 
//if (imgdef($q['antialias'], false)) $graph->img->SetAntiAliasing();

$s = new Spline($datax, $datay);
list($x,$y) = $s->Get(count($datay) * $smooth);

$p1 = new LinePlot($y);
$p1->SetLegend(ucfirst($var));
$p1->SetWeight(imgdef($q['frame']['plot'][0]['weight'], 1));
$p1->SetFillColor(imgdef($q['frame']['plot'][0]['color'], 'blue@0.90'));

$avg = intval($avg);
if ($avg) {
	for ($i=0; $i < count($datay) * $smooth; $i++) {
		$avgdata[] = $avg;
	}

	$p2 = new LinePlot($avgdata);
//	$p2->SetStyle('dashed');
	$p2->SetLegend(imgdef($q['frame']['plot'][1]['title'], 'Average'));
	$p2->SetWeight(imgdef($q['frame']['plot'][1]['weight'], 2));
	$p2->SetColor(imgdef($q['frame']['plot'][1]['color'], 'khaki4'));
//	$p2->SetBarCenter();
	$graph->Add($p2);
}

$graph->legend->SetAbsPos(
	imgdef($q['legend']['x'], 5),
	imgdef($q['legend']['y'], 5),
	imgdef($q['legend']['halign'], 'right'),
	imgdef($q['legend']['valign'], 'top')
);
$graph->legend->SetFillColor(imgdef($q['legend']['color'], 'lightblue@0.5'));
$graph->legend->SetShadow(
	imgdef($q['legend']['shadow']['color'], 'gray@0.5'),
	imgdef($q['legend']['shadow']['width'], 2)
);

$graph->Add($p1);

if (count($datay) < 2) {
	$t = new Text("Not enough history\navailable\nto chart graph");
	$t->SetPos(0.5,0.5,'center','center');
	$t->SetFont(FF_FONT2, FS_BOLD);
	$t->ParagraphAlign('centered');
	$t->SetBox('lightyellow','black','gray');
	$t->SetColor('orangered4');
	$graph->yaxis->HideLabels();
	$graph->xaxis->HideLabels();
	$graph->legend->Hide();
	$graph->AddText($t);
}

stdImgFooter($graph);
$graph->Stroke();

function conv_onlinetime($time) {
	return compacttime($time,'hh:mm');
}

?>
