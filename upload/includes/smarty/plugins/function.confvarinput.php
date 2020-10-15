<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {confvarlayout} function plugin
 *
 * Type:     function<br>
 * Name:     confvarlayout<br>
 * Purpose:  PS3 method to display the statusText layout for the special config variable given
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_confvarinput($args, &$smarty)
{
	global $conf_layout, $form;
	$args += array(
		'var'	=> '',
		'xhtml'	=> 1,
	);

//	$var = $conf_layout[$args['var']];
	$var = $args['var'];
	$name = $var['id'];
	$value = $form->value($name);

	$output = "";
	$options = explode(',', $var['options']);
	$type = strtolower($var['type']);
	switch ($type) {
		case 'none': 
			$output = ps_escape_html($var['value']);
			break;

		case 'checkbox':
			$output .= sprintf("<input name='opts[%s]' value='1' type='checkbox' class='field'%s%s>",
				ps_escape_html($name),
				$value ? ' checked ' : '',
				$args['xhtml'] ? ' /' : ''
			);
			break;

		case 'select':
			$output = sprintf("<select name='opts[%s]' class='field'>", 
				ps_escape_html($name)
			);
			$labels = varinput_build_select($var['options']);
			foreach ($labels as $v => $label) {
				$output .= sprintf("<option value=\"%s\"%s>%s</option>\n",
					ps_escape_html($v),
					$v == $value ? ' selected ' : '',
					ps_escape_html($label)
				);
			}
			$output .= "</select>";
			break;

		case 'boolean':
			$idx = 0;
			$labels = varinput_build_boolean($conf_layout[$var]['options']);
			foreach ($labels as $label => $v) {
				$for = 'for-' . $name . '-' . ++$idx;
				$output .= sprintf("<input id='%s' name='opts[%s]' value=\"%s\" type='radio'%s%s>&nbsp;" .
					"<label class='for' for='$for'>%s</label>\n",
					$for,
					ps_escape_html($name),
					ps_escape_html($v),
					$v == $value ? ' checked ' : '',
					$args['xhtml'] ? ' /' : '',
					ps_escape_html($label)
				);
			}
			break;

		case 'textarea': 
			$attr = varinput_build_attr($var['options']);
			$rows = $attr['rows'] ? $attr['rows'] : 3;
			$cols = $attr['cols'] ? $attr['cols'] : 40;
			$wrap = $attr['wrap'] ? $attr['wrap'] : 'virtual';
			$class = $attr['class'] ? $attr['class'] : 'field';
//			unset($attr['size'], $attr['class']);
			$output = sprintf("<textarea name=\"opts[%s]\" cols=\"%s\" rows=\"%s\" wrap=\"%s\" class=\"%s\">%s</textarea>", 
				ps_escape_html($name), 
				$cols,
				$rows,
				$wrap,
				$class,
				ps_escape_html($value)
			);
			break;

		case 'text':
		default:
			$attr = varinput_build_attr($var['options']);
			$size = $attr['size'] ? $attr['size'] : 40;
			$class = $attr['class'] ? $attr['class'] : 'field';
//			unset($attr['size'], $attr['class']);
			$output = sprintf("<input name=\"opts[%s]\" value=\"%s\" type=\"text\" size=\"%s\" class=\"%s\"%s>", 
				ps_escape_html($name), 
				ps_escape_html($value),
				$size,
				$class,
				$args['xhtml'] ? ' /' : ''
			);
			break;
	};

	return $output;
}

function varinput_build_boolean($opts) {
	global $cms;
	$ary = array();
	if (trim($opts)) {
		$ary = explode(',', $opts);
	} else {
		$ary = array('Yes:1','No:0');
	}
	$l = array();
	foreach ($ary as $item) {
		list($label, $val) = explode(':', $item);
		if (!$val) {
			$x = strtolower($label);
			$val = ($x == 'yes' or $x == 'true' or $x == '1') ? 1 : 0;
		}
		$l[$cms->trans($label)] = $val;		// this has to be manually added to languages/.../global.lng
	}
	return $l;
}

function varinput_build_select($opts) {
	global $cms;
	$ary = array();
	$opts = trim($opts);
	if ($opts) {
		$ary = preg_split('/[,\r?\n]+/', $opts, -1, PREG_SPLIT_NO_EMPTY);
	} else {
		$ary = array();
	}
	$l = array();
	foreach ($ary as $item) {
//		list($label, $val) = explode(':', $item);
		list($val, $label) = explode(':', $item);
		$val = trim($val);
		$label = trim($label);
		if ($label == '') {
			$label = $val;
		}
		$l[$val] = $cms->trans($label);
	}
	return $l;
}

function varinput_build_attr($opts) {
	$ary = array();
	if (trim($opts)) {
		$ary = explode(',', $opts);
	} else {
		$ary = array();
	}
	$l = array();
	foreach ($ary as $item) {
		list($var, $val) = explode('=', $item);
		$var = trim($var);
		$val = trim($val);
		if (!$val) {		// ignore attribs that do not have values
			return;
		}
		$l[$var] = $val;
	}
	return $l;
}

?>
