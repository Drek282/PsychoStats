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
 *	Version: $Id: imgconn.php 367 2008-03-17 17:47:45Z lifo $
 */

/*
	Displays a graph that shows the total connections for the last 30 days
*/
define("PSYCHOSTATS_PAGE", true);
include(__DIR__ . "/includes/imgcommon.php");
include(JPGRAPH_DIR . '/jpgraph_line.php');
include(JPGRAPH_DIR . '/jpgraph_regstat.php');

$imgfilename = 'auto';
$data = array();
$datay = array();
$datax = array();
$labels = array();
$sum = 0;
$avg = 0;
$maxconn = 0;

$smooth = 3;	// how many points to smooth the curves
$max = 31;	// more than 30 starts to look ugly

$imgconf = array();
$q = array();

if (!isImgCached($imgfilename)) {
	$imgconf = load_img_conf();
	$q =& $imgconf['connimg'];

//	$ps->db->query("SELECT statdate,SUM(connections),SUM(kills) FROM $ps->t_map_data GROUP BY statdate ORDER BY statdate LIMIT $max");
//	while (list($statdate,$totalforday,$killsforday) = $ps->db->fetch_row(0)) {
	$ps->db->query("SELECT statdate,SUM(connections) FROM $ps->t_map_data GROUP BY statdate ORDER BY statdate DESC LIMIT $max");
	$i = 1;
	while (list($statdate,$totalforday) = $ps->db->fetch_row(0)) {
		$sum += $totalforday;
		array_unshift($datay, $totalforday);
		array_push($datax, $i++);
		array_unshift($labels, $statdate);
//		array_unshift($kills, $killsforday);
		if ($totalforday > $maxconn) $maxconn = $totalforday;
	}

	// DEBUG
/*
	while (count($data) < 30) {
		$totalforday = rand(600,1000);
		$sum += $totalforday;
		$data[] = $totalforday;
		$labels[] = $labels[0];
	}
/**/
}

// Not enough data to produce a proper graph
// jpgraph will crash if we give it an empty array
if (!count($datax)) {
	$sum = 0;
	$datax[] = 0;
	$datay[] = 0;
	$datax[] = 1;
	$datay[] = 1;
	$labels = array("");
}

// calculate the average of our dataset
if (count($datay)) {
	$avg = $sum / count($datay);
}

// Setup the graph.
$graph = new Graph(imgdef($q['width'],600), imgdef($q['height'], 250), $imgfilename, CACHE_TIMEOUT);
$graph->SetScale("linlin");
//$graph->SetY2Scale("lin");
$graph->SetMargin(60,30,20,75);
$graph->title->Set(imgdef($q['frame']['title']['_content'], 'Connections Per Day'));

//$graph->xaxis->SetTickLabels($labels);
$graph->xaxis->HideTicks();
$graph->xaxis->SetLabelAngle(90);
$graph->xaxis->SetLabelFormatCallback('xlabels');
$graph->xaxis->SetLabelAlign('center', 'top');

$graph->yaxis->HideZeroLabel(); 
//$graph->y2axis->HideZeroLabel(); 

$graph->ygrid->SetFill((bool)imgdef($q['frame']['ygrid']['show'], true),
	imgdef($q['frame']['ygrid']['color1'], 'whitesmoke@0.5'),
	imgdef($q['frame']['ygrid']['color2'], 'lightblue@0.5')
);
$graph->ygrid->Show((bool)imgdef($q['frame']['ygrid']['show'], true));

$font1 = constant(imgdef($q['frame']['title']['font'], 'FF_FONT1'));
$legendfont = constant(imgdef($q['legend']['font'], 'FF_FONT1'));
$graph->legend->SetFont($legendfont,FS_NORMAL);
$graph->title->SetFont($font1, FS_BOLD);
$graph->yaxis->title->SetFont($font1,FS_BOLD);
$graph->xaxis->SetFont(FF_FONT0,FS_NORMAL);
//$graph->xaxis->title->SetFont($font1,FS_BOLD);

/*
$graph->SetBackgroundGradient(
	imgdef($q['frame']['color1'], 'gray'),
	imgdef($q['frame']['color2'], 'whitesmoke'),
	constant(imgdef($q['frame']['type'], 'GRAD_LEFT_REFLECTION')),
	constant(imgdef($q['frame']['style'], 'BGRAD_MARGIN'))
); 
/**/
//$graph->SetFrame(false);
$graph->SetMarginColor(imgdef($q['frame']['margin'], '#d7d7d7')); 
$graph->SetFrame(true,imgdef($q['frame']['color'],'gray'),imgdef($q['frame']['width'], 1)); 
//$graph->SetShadow();

//$smooth = max(imgdef($q['smooth'], 10), 10);

$s = new Spline($datax, $datay);
list($x,$y) = $s->Get(count($datay) * $smooth);

// Create the bar pot
$p1 = new LinePlot($y);
$p1->SetLegend(imgdef($q['frame']['plot'][0]['_content'], 'Maximum') . " [$maxconn]");
$p1->SetWeight(imgdef($q['frame']['plot'][0]['weight'], 1));
$p1->SetFillColor(imgdef($q['frame']['plot'][0]['color'], 'blue@0.90'));
$p1->SetBarCenter();

$avg = intval($avg);
if ($avg) {
	$avgdata = array();
	for ($i=0; $i < count($datay) * $smooth; $i++) {
		$avgdata[] = $avg;
	}

	$p2 = new LinePlot($avgdata);
//	$p2->SetStyle('dashed');
	$p2->SetLegend(imgdef($q['frame']['plot'][1]['_content'], 'Average') . " [$avg]");
	$p2->SetWeight(imgdef($q['frame']['plot'][1]['weight'], 2));
	$p2->SetColor(imgdef($q['frame']['plot'][1]['color'], 'khaki4'));
	$p2->SetBarCenter();
	$graph->Add($p2);

	$graph->legend->SetAbsPos(
		imgdef($q['legend']['x'], 20),
		imgdef($q['legend']['y'], 15),
		imgdef($q['legend']['halign'], 'right'),
		imgdef($q['legend']['valign'], 'top')
	);
	$graph->legend->SetFillColor(imgdef($q['legend']['color'], 'lightblue@0.5'));
	$graph->legend->SetShadow(
		imgdef($q['legend']['shadow']['color'], 'gray@0.5'),
		imgdef($q['legend']['shadow']['width'], '2')
	);
}

$graph->Add($p1);

/*
$p3 = new LinePlot($kills);
$p3->SetBarCenter();
$p3->SetLegend("Kills");
$p3->SetFillColor('gray@0.65');

$graph->AddY2($p3);
/**/

if (!$sum) {
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

//if (imgdef($q['antialias'], false)) $graph->img->SetAntiAliasing();

stdImgFooter($graph);
$graph->Stroke();


function xlabels($x) {
	global $labels, $smooth;
	$idx = floor($x / $smooth);
	$idx = $x / $smooth;
//	return $labels[$idx];
	return $idx < count($labels) ? $labels[$idx] : $labels[count($labels)-1];
}


?>
