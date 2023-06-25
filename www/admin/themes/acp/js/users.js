$(document).ready(function(){
	// users.php: toggle all checkboxes on the page
	$('#select-all').click(function(){
		$('input[@name^=sel]').attr('checked', this.checked);
	});

	$('#delete-btn').click(function(){
		if ($('input[@name^=sel]:checked').length == 0) return false;
		return window.confirm(delete_message);
	});

});
