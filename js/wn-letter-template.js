(function($){

$(document).ready(function(){

	$(".wnlt-date").datetimepicker({
		timepicker:false,
		format:'d.m.Y',
		scrollInput:false
	});

	$(".wnlt-input").on("change keyup blur",function(){
		var val = $(this).val().trim(),
			tagName = $(this).data("wnlt-tag"),
			$wrapper = $(this).parents(".wnlt-wrapper"),
			$tags = $(".wnlt-tag-"+tagName,$wrapper);
		if ( val != "" ) {
			$tags.removeClass("placeholder").addClass("contents").text(val);
		} else {
			$tags.removeClass("contents").addClass("placeholder").text($tags.data("wnlt-placeholder"));
		}
	});

	$(".wnlt-predefined").on("change keyup blur",function(){
		var val = $(this).is(":checked"),
			tagName = $(this).data("wnlt-tag"),
			predefined = $(this).data("wnlt-predefined"),
			$wrapper = $(this).parents(".wnlt-wrapper"),
			$tags = $(".wnlt-tag-"+tagName,$wrapper);
		if ( val != "" ) {
			$tags.removeClass("placeholder").addClass("contents").text(predefined);
		} else {
			$tags.removeClass("contents").addClass("placeholder").text("");
		}
	});

	$(".wnlt-cpt_business").on("change",function(){
		var id = $(this).val(),
			tagName = $(this).data("wnlt-tag"),
			$wrapper = $(this).parents(".wnlt-wrapper"),
			$tags = $(".wnlt-tag-"+tagName,$wrapper)
		if ( id < 0 ) {
			$tags.removeClass("placeholder").addClass("contents").html("- "+wnlt.str.unknown+" -");
		}
		else
		if ( typeof wnlt.cpt_business.items[id] !== "undefined" ) {
			$tags.removeClass("placeholder").addClass("contents").html(Mark.up(wnlt.cpt_business.template,wnlt.cpt_business.items[id]));
		} else {
			$tags.removeClass("contents").addClass("placeholder").text($tags.data("wnlt-placeholder"));
		}
	});

	$(".wnlt-field").filter(function(i){return $(this).prop("title").length;}).tooltipster({
		contentAsHTML: true,
		icon: "?",
		iconDesktop: true,
		theme: "tooltipster-light",
		maxWidth: 300,
		interactive: true
	});

	$(".wnlt-field").trigger("change");

});

})(jQuery);
