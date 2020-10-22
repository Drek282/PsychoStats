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
 *	Version: $Id: imgcc.php 367 2008-03-17 17:47:45Z lifo $
 */

/*
	Displays a piechart showing the breakdown of the various countries that players are from
*/
define("PSYCHOSTATS_PAGE", true);
include(__DIR__ . "/includes/imgcommon.php");
include(JPGRAPH_DIR . '/jpgraph_pie.php');


$imgfilename = 'auto';
$data = array();
$labels = array();

$imgconf = array();
$q = array();



if (!isImgCached($imgfilename)) {
	$imgconf = load_img_conf();
	$q =& $imgconf['ccimg'];

	$timeframe = imgdef($q['timeframe'], 0);
	$total = imgdef($q['slices']['total'], 10);
	if (!is_numeric($total) or $total < 1 or $total > 20) $total = 10;

	// total profiles with a non-empty country code
#	list($total) = $ps->db->fetch_row(0, "SELECT COUNT(cc) FROM $ps->t_plr_profile WHERE cc != ''");
#	if (!$total) $total = 1;

	// top 10 countries by count
#	if ($timeframe) {
#		$ps->db->query("SELECT pp.cc,cn,COUNT(pp.cc) FROM $ps->t_plr_profile pp, $ps->t_geoip_cc cc WHERE pp.cc != '' AND cc.cc=pp.cc GROUP BY pp.cc ORDER BY 3 DESC LIMIT $total");
#	} else {
		$ps->db->query("SELECT pp.cc,cn,COUNT(pp.cc) FROM $ps->t_plr_profile pp, $ps->t_geoip_cc cc WHERE pp.cc != '' AND cc.cc=pp.cc GROUP BY pp.cc ORDER BY 3 DESC LIMIT $total");
#	}
#	print $ps->db->lastcmd;
	while (list($cc,$cn,$cctotal) = $ps->db->fetch_row(0)) {
		$data[] = $cctotal;
		$labels[] = '(' . strtoupper($cc) . ") $cn";
	}

	// total of unknown CC's
/**
	$ps->db->query("SELECT COUNT(*) FROM $ps->t_plr_profile pp WHERE cc IN ('','00') LIMIT 1");
	list($cc,$cctotal) = $ps->db->fetch_row(0);
	$data[] = $cctotal;
	$labels[] = '?';
/**/

	// if we have no country data show a 100% unknown slice
	if (!count($data)) {
		$data[] = 1;
		$labels[] = 'unknown';
	}
}

//$graph = new PieGraph(375, 285, $imgfilename, CACHE_TIMEOUT);
$graph = new PieGraph(imgdef($q['width'],600), imgdef($q['height'], 300), $imgfilename, CACHE_TIMEOUT);
if (imgdef($q['antialias'], 0)) $graph->SetAntiAliasing();
$graph->SetColor(imgdef($q['frame']['margin'], '#d7d7d7'));

$graph->title->Set(imgdef($q['frame']['title']['_content'], 'Breakdown of Countries'));
$graph->title->SetFont(constant(imgdef($q['frame']['title']['font'], 'FF_FONT1')),FS_BOLD);
//$graph->subtitle->Set("(Excludes unknown)");
//$graph->subtitle->SetFont(FF_FONT0,FS_NORMAL);

$p1 = new PiePlot($data);
$p1->ExplodeSlice(0);		// make the largest slice explode out from the rest
#$p1->ExplodeAll();
//$p1->SetStartAngle(45); 
if (imgdef($q['slices']['border'], 0) == 0) $p1->ShowBorder(false,false);
$p1->SetGuideLines();
$p1->SetCenter(0.35);
$p1->SetTheme(imgdef($q['slices']['theme'], 'earth'));
$p1->SetLegends($labels);
$p1->SetGuideLinesAdjust(1.1);

$graph->SetMarginColor(imgdef($q['frame']['margin'], '#d7d7d7')); 
$graph->SetFrame(true,imgdef($q['frame']['color'],'gray'),imgdef($q['frame']['width'], 1)); 

$graph->Add($p1);

/*
if (count($data)) {
	$t = new Text("Not enough history\navailable\nto display piechart");
	$t->SetPos(0.5,0.5,'center','center');
	$t->SetFont(FF_FONT2, FS_BOLD);
	$t->ParagraphAlign('centered');
	$t->SetBox('lightyellow','black','gray');
	$t->SetColor('orangered4');
//	$graph->legend->Hide();
	$graph->AddText($t);
}
*/

stdImgFooter($graph);
$graph->Stroke();

?>
