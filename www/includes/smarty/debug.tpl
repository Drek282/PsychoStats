{* Smarty *}
{* debug.tpl, last updated version 2.1.0 *}
{assign_debug_info}
{capture assign=debug_output}
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Smarty Debug Console</title>
<style>
body, h1, h2, td, th, p {ldelim}
	font-family: sans-serif;
	font-weight: normal;
	font-size: 0.9em;
	margin: 1px;
	padding: 0;
{rdelim}

h1 {ldelim}
	margin: 0;
	text-align: left;
	padding: 2px;
	background-color: #f0c040;
	color:  black;
	font-weight: bold;
	font-size: 1.2em;
 {rdelim}

h2 {ldelim}
	background-color: #9B410E;
	color: white;
	text-align: left;
	font-weight: bold;
	padding: 2px;
	border-top: 1px solid black;
{rdelim}

body {ldelim}
	background: black; 
{rdelim}

p, table, div {ldelim}
	background: #f0ead8;
{rdelim} 

p {ldelim}
	margin: 0;
	font-style: italic;
	text-align: center;
{rdelim}

table {ldelim}
	width: 100%;
{rdelim}

th, td {ldelim}
	font-family: monospace;
	vertical-align: top;
	text-align: left;
	width: 50%;
{rdelim}

td {ldelim}
	color: green;
{rdelim}

.odd {ldelim}
	background-color: #eeeeee;
{rdelim}

.even {ldelim}
	background-color: #fafafa;
{rdelim}

.exectime {ldelim}
	font-size: 0.8em;
	font-style: italic;
{rdelim}

#table_assigned_vars th {ldelim}
	color: blue;
{rdelim}

#table_config_vars th {ldelim}
	color: maroon;
{rdelim}
</style>
</head>
<body>

<h1>Smarty Debug Console</h1>

<h2>included templates &amp; config files (load time in seconds)</h2>

<div>
{section name=templates loop=$_debug_tpls}
	{section name=indent loop=$_debug_tpls[templates].depth}&nbsp;&nbsp;&nbsp;{/section}
	<font color={if $_debug_tpls[templates].type eq "template"}brown{elseif $_debug_tpls[templates].type eq "insert"}black{else}green{/if}>
		{$_debug_tpls[templates].filename|escape:'html'}</font>
	{if isset($_debug_tpls[templates].exec_time)}
		<span class="exectime">
		({$_debug_tpls[templates].exec_time|string_format:"%.5f"})
		{if %templates.index% eq 0}(total){/if}
		</span>
	{/if}
<br>
{sectionelse}
	<p>no templates included</p>
{/section}
</div>

<h2>assigned template variables</h2>

<table id="table_assigned_vars">
	{section name=vars loop=$_debug_keys}
		<tr class="{cycle values="odd,even"}">
			<th>{ldelim}${$_debug_keys[vars]|escape:'html'}{rdelim}</th>
			<td>{$_debug_vals[vars]|@debug_print_var}</td></tr>
	{sectionelse}
		<tr><td><p>no template variables assigned</p></td></tr>
	{/section}
</table>

<h2>assigned config file variables (outer template scope)</h2>

<table id="table_config_vars">
	{section name=config_vars loop=$_debug_config_keys}
		<tr class="{cycle values="odd,even"}">
			<th>{ldelim}#{$_debug_config_keys[config_vars]|escape:'html'}#{rdelim}</th>
			<td>{$_debug_config_vals[config_vars]|@debug_print_var}</td></tr>
	{sectionelse}
		<tr><td><p>no config vars assigned</p></td></tr>
	{/section}
</table>
</body>
</html>
{/capture}
{if isset($_smarty_debug_output) and $_smarty_debug_output eq "html"}
	{$debug_output}
{else}
<script>
	if ( self.name == '' ) {ldelim}
		var title = 'Console';
	{rdelim}
	else {ldelim}
		var title = 'Console_' + self.name;
	{rdelim}
	_smarty_console = window.open("",title,"top=80,width=880,height=600,resizable,scrollbars=yes");
	_smarty_console.document.write('{$debug_output|escape:'javascript'}');
	_smarty_console.document.close();
</script>
{/if}
