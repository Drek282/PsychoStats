<?php
function smarty_modifier_abbrnum($string, $tail=2) {
	if (intval($string) < 1000) {
		return $string;
	} else {
		return abbrnum($string, $tail, array('', 'K', 'M', 'B'), 1000);
	}
}

?>
