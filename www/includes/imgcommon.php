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
 *	Version: $Id: imgcommon.php 541 2008-08-18 11:24:58Z lifo $
 */

/*
	Common IMAGE routines. This file generally only contains simple setup 
	and configs for all images created within the context of PsychoStats.
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));

if (defined("PSFILE_IMGCOMMON_PHP")) return 1;
define("PSFILE_IMGCOMMON_PHP", 1);

require_once(__DIR__ . "/common.php");

// JPGRAPH config

// If true images are cached 
define("USE_CACHE", $ps->conf['theme']['images']['cache_enabled'] ? true : false);
define("READ_CACHE", true);

// # of minutes before a cached image is recreated
// 0=never timeout! This means images will only be created once! 
define("CACHE_TIMEOUT", $ps->conf['theme']['images']['cache_timeout']);	

// Path to store cached images. If right blank system defaults will be used
if (!empty($ps->conf['theme']['images']['cache_dir'])) {
	define("CACHE_DIR", catfile($ps->conf['theme']['images']['cache_dir']) . DIRECTORY_SEPARATOR);
} else {
	define("CACHE_DIR", catfile(sys_get_temp_dir(), "ps_img_cache") . DIRECTORY_SEPARATOR);
}

// Path to the TTF fonts. If left blank system defaults will be used
/*
if (!empty($ps->conf['theme']['images']['ttf_dir'])) {
	define("TTF_DIR", $ps->conf['theme']['images']['ttf_dir']);
}
#define("TTF_DIR", "/usr/share/fonts/truetype/msttcorefonts/");
*/

define("INSTALL_PHP_ERR_HANDLER", true);
define("CACHE_FILE_GROUP", "");

//define("BRAND_TIMING", true);	// if true all images will have a timing value on the left footer

// We must load the proper JPGRAPH version depending on our version of PHP
define("JPGRAPH_DIR", __DIR__ . '/jpg5');

define("CATCH_PHPERRMSG", false);

// all JPG constants MUST be defined BEFORE the jpgraph core routines are included
include(JPGRAPH_DIR . '/jpgraph.php');

// remove all pending output buffers
while (@ob_end_clean());

function isImgCached($file) {
	if (!USE_CACHE) return false;
	if ($file == 'auto') {
		$file = GenImgName();		// imported from jpgraph.php
	}

	$filename = catfile(CACHE_DIR, $file);
	if (file_exists($filename)) {
		if (CACHE_TIMEOUT == 0) return true;
		$diff = time() - filemtime($filename);
		return ($diff < CACHE_TIMEOUT * 60);
	} 
	return false;
}

function stdImgFooter(&$graph,$left=true,$right=true) {
	global $ps, $cms, $imgconf;
	$styles =& $cms->theme->styles;
	$i =& $imgconf;
	if ($left) {
		$graph->footer->left->Set(sprintf($styles->val('image.common.footer.left', 'PsychoStats v%s', true), $ps->version(true)));
		$graph->footer->left->SetColor($styles->val('image.common.footer.color', 'black@0.5', true));
		$graph->footer->left->SetFont(constant($styles->val('image.common.footer.font','FF_FONT0', true)),FS_NORMAL);
	}

	if ($right) {
		$graph->footer->right->Set(date($styles->val('image.common.footer.right', 'Y-m-d @ H:i:s', true)));
		$graph->footer->right->SetColor($styles->val('image.common.footer.color', 'black@0.5', true));
		$graph->footer->right->SetFont(constant($styles->val('image.common.footer.font','FF_FONT0', true)),FS_NORMAL);
	}
}

?>
