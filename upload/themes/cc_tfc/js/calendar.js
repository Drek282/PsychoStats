$(document).ready(function(){
	cal = $('#calendar1');

	// hover over daily cells
	$('td.calendar-cell', cal).hover(
		function(){ $(this).addClass('calendar-hover') }, 
		function(){ $(this).removeClass('calendar-hover') }
	);

	// hover over week cells
	$('td.calendar-week', cal).hover(
		function(){ $(this).siblings().andSelf().addClass('calendar-hover-week') }, 
		function(){ $(this).siblings().andSelf().removeClass('calendar-hover-week') }
	);

	// highlight an entire row when the week is selected
	// it's too bad we can't do this directly in css
	$('td.calendar-week-selected ~ td', cal).addClass('calendar-week-cell');
});
