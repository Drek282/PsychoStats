<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty award_format modifier plugin
 *
 * Type:     modifier<br>
 * Name:     award_format<br>
 * Purpose:  format award values either via sprintf or using php code
 * @param string
 * @param string
 * @return string
 */
function smarty_modifier_award_format($value, $format) {
/*	# using eval for this is way too insecure. So I'm hard coding specific format values
	if (strpos($format, "code:") === 0) {
		$code = substr($format, 5);
		if (!preg_match('/;$/',$code)) $code .= ';';			// make sure there's a trailing ";"
		$code = sprintf("return $code", $value);
		return eval($code);
	} else {
		return sprintf($format, $value);
	}
*/
	switch ($format) {
		case "commify": 	return commify($value);
		case "compacttime": 	return compacttime($value);
		case "date":		return date("Y-m-d", $value);
		case "datetime":	return date("Y-m-d H:i:s", $value);
	}
	return ($format{0} == '%') ? sprintf($format, $value) : $value;
}

?>
