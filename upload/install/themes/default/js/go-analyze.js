var allow_next;
$(function(){
	$('#btn-recheck').click(do_recheck).click();

});

function do_recheck(e) {
	$('#go-ctrl input:not(:hidden)').attr('disabled','disabled');
	$('#results-pending').slideDown();
	$('#results').slideUp('normal', function(){
		$.post('go.php', { s: step, a: 1, install: $('#go-ctrl input[name=install]').val() }, function(data){
			$('#go-ctrl input:not(:hidden)').attr('disabled','');
			$('#results-pending').slideUp('normal'); //, function(){ $('#results').html(data).slideDown() });
			$('#results').html(data).slideDown();
		});
	});
	e.preventDefault();
	return false;
}
