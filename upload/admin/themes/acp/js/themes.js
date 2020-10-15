$(document).ready(function(){
	$('.ps-theme-table a[@id^=uninstall-]').click(confirm_uninst);
});

function confirm_uninst(e) {
	var text = $(this).parent().siblings('.item');
	return window.confirm("Are you sure you want to uninstall the theme (it will not be deleted)?:\n\t" + text.html());
}

