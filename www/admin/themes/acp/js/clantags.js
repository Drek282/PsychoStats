$(document).ready(function(){
	delete_message = "Are you sure you want to delete this clantag?";
	$('#type1,#type2').change(change_type);
	change_type();
});

function change_type(e) {
	var div = $('#pos');
	var t = $('#type1')[0];
	if (t.checked) {	// plain
		div.show();
	} else {		// regex
		div.hide();
	}
}
