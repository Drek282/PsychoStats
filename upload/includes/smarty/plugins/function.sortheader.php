<?php
/* 
	Smarty function for PsychoStats v3.1. 
	Returns a proper <th>...</th> header ...

*/
function smarty_function_sortheader($args, &$smarty) {
	global $cms;
	static $baseurl = "";
	static $prefix = "";
	static $sort = "";
	static $sortvar = "sort";
	static $order = "";
	static $ordervar = "order";
	static $anchor = "";
	static $active_class = "active";
	static $th_class = "";
	static $th_attrib = "";
	$orig = $args;
	$args += array(
		'reset'		=> 0,		// if true the static vars are reset and an empty string is returned
		'baseurl' 	=> $baseurl,
		'anchor'	=> $anchor,
		'sort'		=> $sort,
		'sortvar'	=> $sortvar,
		'prefix'	=> $prefix,
		'order'		=> $order,
		'ordervar'	=> $ordervar,
		'th_class'	=> $th_class,
		'th_attrib'	=> $th_attrib,
		'label'		=> '',
		'title'		=> null,
		'active'	=> false,
	);

	if ($args['reset']) {
		if (!empty($args['sortvar'])) $sortvar = $args['sortvar'];
		if (!empty($args['ordervar'])) $ordervar = $args['ordervar'];
		$sort = $args['sort'];
		$order = $args['order'];
		$prefix = $args['prefix'];
		$anchor = $args['anchor'];
		$baseurl = $args['baseurl'];
		return '';
	}

	$altorder = ($args['order'] == 'desc') ? 'asc' : 'desc';
	$neworder = $args['sort'] == $sort ? $altorder : $args['order'];
	$label = $args['label'];
	$image = "";

	// mark this header as active if specified or if the order has changed
	if (($args['active'] or $neworder == $altorder) and $orig['sort']) {
		$args['th_class'] = trim($args['th_class'] . " " . $active_class);
//		$image = sprintf(" <img src='%s' />", catfile($cms->theme->url(), sprintf("img/sort_arrow_%s.gif", $args['order'])));
	}

	$url = array();
	$url['_base'] = $args['baseurl'] ? $args['baseurl'] : null;
	$url['_anchor'] = $args['anchor'];
	if ($order and $neworder) $url[ $args['prefix'] . $args['ordervar'] ] = $neworder;
	if ($sort and $args['sort']) $url[ $args['prefix'] . $args['sortvar'] ] = $args['sort'];

//	if ($url['sort'] or $url['order']) {
		return sprintf("<th%s%s><p><a href='%s'><span class='%s'>%s%s</span></a></p></th>", 
			$args['th_class'] ? " class='" . $args['th_class'] . "'" : "",
			$args['th_attrib'] ? " " . $args['th_attrib'] : "",
			ps_url_wrapper($url),
			$args['order'], $label, $image
		);
/*
	} else {
		return sprintf("<th%s%s><p><span class='%s'>%s%s</span></p></th>", 
			$args['th_class'] ? " class='" . $args['th_class'] . "'" : "",
			$args['th_attrib'] ? " " . $args['th_attrib'] : "",
			$args['order'], $label, $image
		);
	}
*/
}

?>
