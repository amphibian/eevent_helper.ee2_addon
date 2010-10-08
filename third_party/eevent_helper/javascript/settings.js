$(document).ready(function()
	{
	
	function countPanels()
	{
		if($("form#eevent_helper tbody").size() == 2) {
			$("a#eh_remove_channel").hide();	
		} else {
			$("a#eh_remove_channel").show();
		}					
	}
	
	countPanels();
	
	$("a#eh_add_channel").click(function(){
		var el = $(this).parents("tbody").prev("tbody").clone();
		$("select option:first-child", el).attr("selected", "selected");
		$(this).parents("tbody").prev("tbody").after(el);
		$(el).hide().fadeIn();
		countPanels();
		return false;
	});
	
	$("a#eh_remove_channel").click(function(){
		$(this).parents("tbody").prev("tbody").fadeOut("", function(){
			$(this).remove();
			countPanels();
		});
		return false;
	});
		
	}
);