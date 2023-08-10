$(document).ready(function(){
	$('.ps-plugin-table a[@id^=uninstall-]').click(confirm_uninst);
	$('#ps-id-pending-plugins a[@id^=install-]').click(confirm_inst);
});

function confirm_uninst(e) {
	var text = $(this).parent().siblings('.item');
	return window.confirm("Are you sure you want to uninstall the plugin (it will not be deleted)?:\n\t" + text.html());
}

function confirm_inst(e) {
	var text = $(this).parent().siblings();
	return window.confirm("Are you sure you want to install the plugin?:\n\t" + text.html());
}
