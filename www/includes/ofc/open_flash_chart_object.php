<?php

function open_flash_chart_object_str( $width, $height, $url, $use_swfobject=true, $base='' )
{
    //
    // return the HTML as a string
    //
    return _ofc( $width, $height, $url, $use_swfobject, $base );
}

function open_flash_chart_object( $width, $height, $url, $use_swfobject=true, $base='' )
{
    //
    // stream the HTML into the page
    //
    echo _ofc( $width, $height, $url, $use_swfobject, $base );
}

function _ofc( $width, $height, $url, $use_swfobject, $base )
{
    //
    // I think we may use swfobject for all browsers,
    // not JUST for IE...
    //
    //$ie = strstr(getenv('HTTP_USER_AGENT'), 'MSIE');
    
    //
    // escape the & and stuff:
    //
    $url = urlencode($url);
    
    //
    // output buffer
    //
    $out = array();
    
    //
    // check for http or https:
    //
    if (isset ($_SERVER['HTTPS']))
    {
        if (strtoupper ($_SERVER['HTTPS']) == 'ON')
        {
            $protocol = 'https';
        }
        else
        {
            $protocol = 'http';
        }
    }
    else
    {
        $protocol = 'http';
    }
    
    //
    // if there are more than one charts on the
    // page, give each a different ID
    //
    global $open_flash_chart_seqno;
    $obj_id = 'chart';
    $div_name = 'flashcontent';
    
    //$out[] = '<script src="'. $base .'js/ofc.js"></script>';
    
    if( !isset( $open_flash_chart_seqno ) )
    {
        $open_flash_chart_seqno = 1;
        $out[] = '<script src="'. $base .'js/swfobject.js"></script>';
    }
    else
    {
        $open_flash_chart_seqno++;
        $obj_id .= '_'. $open_flash_chart_seqno;
        $div_name .= '_'. $open_flash_chart_seqno;
    }
    
    $php_scnm = $_SERVER['SCRIPT_NAME'];
    $php_scnm_dir = rtrim(dirname($php_scnm), '/\\');
    
	// Using library for auto-enabling Flash object, disabled-Javascript proof  
    $out[] = '<div id="'. $div_name .'"></div>';
	$out[] = '<script src="' . $php_scnm_dir . '/includes/ruffle/ruffle.js"></script>';
	$out[] = '<script>';
	$out[] = '{literal}';
	$out[] = '  window.RufflePlayer = window.RufflePlayer || {};';
	$out[] = '  window.RufflePlayer.config = {';
	$out[] = '    "publicPath": undefined,';
	$out[] = '    "polyfills": true,';
	$out[] = '    "autoplay": "on",';
	$out[] = '    "unmuteOverlay": "hidden",';
	$out[] = '    "backgroundColor": null,';
	$out[] = '    "letterbox": "fullscreen",';
	$out[] = '    "warnOnUnsupportedContent": true,';
	$out[] = '    "contextMenu": false,';
	$out[] = '    "upgradeToHttps": window.location.protocol === "https:",';
	$out[] = '    "maxExecutionDuration": {"secs": 15, "nanos": 0},';
	$out[] = '    "logLevel": "error",';
	$out[] = '  };';
	$out[] = '{/literal}';
	$out[] = '</script>';
	
    $out[] = '<object type="application/x-shockwave-flash" ';
    $out[] = 'width="' . $width . '" height="' . $height . '">';
    $out[] = '<param name="classid" value="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000">';
    $out[] = '<param name="allowScriptAccess" value="sameDomain">';
    $out[] = '<param name="movie" value="'. $base .'open-flash-chart.swf?width='. $width .'&height='. $height . '&data='. $url .'">';
    $out[] = '<param name="quality" value="high">';
    $out[] = '<param name="bgcolor" value="#FFFFFF">';
    $out[] = '<embed src="'. $base .'open-flash-chart.swf?data=' . $url .'" quality="high" bgcolor="#FFFFFF" width="'. $width .'" height="'. $height .'" allowScriptAccess="sameDomain" ';
    $out[] = 'type="application/x-shockwave-flash" id="'. $obj_id .'"/>';
    $out[] = '</object>';
    
    return implode("\n",$out);
}
?>
