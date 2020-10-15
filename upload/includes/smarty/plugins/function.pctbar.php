<?php
/**
	Creates a non-image percentage bar with defined width and color. 
	color can scale from two colors depending on the percentage value.
**/
function smarty_function_pctbar($args, &$smarty)
{
	return pct_bar($args);
}

?>
