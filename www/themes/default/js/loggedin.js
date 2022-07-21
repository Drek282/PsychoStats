$(document).ready(function(){
	width = $(window).width();
	pop = $('#ps-loggedin-popup');
	pop.css({
		top:  parseInt(75) + 'px',
		left: parseInt(width / 2 - pop.width() / 2) + 'px'
	});
//	pop.fadeIn('fast');
	setTimeout(function(){pop.fadeIn('fast');}, 250);
	setTimeout(function(){pop.fadeOut('slow');}, 4000);
});
