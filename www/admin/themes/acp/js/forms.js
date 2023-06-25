$(document).ready(function(){
	$('#btn-delete').click(confirm_delete);
});

function confirm_delete(e) {
	return window.confirm(delete_message ? delete_message : "Are you sure you want to delete this?");
}
