jQuery(document).ready(function($){

	// Prepare new attributes for the repeating section
	var attrs = ["for", "id", "name"];
	function resetAttributeNames(sections) {
		$(sections).each(function() {
			var section = $(this),
				tags = section.find("input,label,textarea,select"),
				idx = section.index();
			tags.each(function() {
				var $this = $(this);
				$.each(attrs, function(i, attr) {
					var attr_val = $this.attr(attr);
					if (attr_val) {
						$this.attr(attr, attr_val.replace(/\[fields\]\[\d+\]\[/, "[fields]["+idx+"]["))
					}
				});
			});
		});
	}
	
	function resetButtons() {
		if ( $(".wnlt-repeating").length > 1 ) {
			$(".wnlt-remove").show();
		} else {
			$(".wnlt-remove").hide();
		}
	}

	function resetTags() {
		var theFieldNames = $(".wnlt-fields-name"),
			theCode = '';
		theCode += "<code class='tag'>%current_date%</code>\n\n";
		theFieldNames.each(function(){
			if ( $(this).val() !== "" ) {
				theCode += "<code class='tag'>["+string_to_slug($(this).val())+"]</code>\n";
			}
		});
		$("#template-tags").html(theCode);
		// $("#template-tags .tag").drags({
			// callback: function(elm){
				// $(elm).
			// }
		// });
		// dragula([$("#template-tags")[0], $("#wnlt-template")[0]], {
			// copy: true,
			// moves: function (el, source, handle, sibling) {
				// return source !== $("#wnlt-template");
			// },
			// accepts: function (el, target) {
				// return target === $("#wnlt-template");
			// }
		// });
	}
	$("#wrap-fields").on("change",".wnlt-fields-name",function(e){
		resetTags();
	});

	function resetExtra(el) {
		$(".wrap-wnlt-fields-extra > *").hide();
		if ( typeof el !== "undefined" ) {
			var extra = $(el).parents(".wnlt-repeating").find(".wnlt-fields-extra-"+$(el).val());
			if ( extra.length ) {
				extra.show();
			}
			return;
		}
		$(".wnlt-fields-type").each(function(){
			var extra = $(this).parents(".wnlt-repeating").find(".wnlt-fields-extra-"+$(this).val());
			if ( extra.length ) {
				extra.show();
			}
		});
	}
	$("#wrap-fields").on("change",".wnlt-fields-type",function(e){
		resetExtra(this);
	});

	$("#business-tags").on("click",".tag",function(e){
		$("#wnlt-business").insertAtCaret($(this).text());
	});
	$("#template-tags").on("click",".tag",function(e){
		$("#wnlt-template").insertAtCaret($(this).text());
	});

	// Clone the previous section, and remove all of the values
	$(".wnlt-repeat").click(function(e){
		e.preventDefault();
		var lastRepeatingGroup = $(".wnlt-repeating").last(),
			cloned = lastRepeatingGroup.clone(true);
		cloned.insertAfter(lastRepeatingGroup);
		cloned.find("input,select,textarea").val("");
		cloned.find("input:radio").attr("checked", false);
		cloned.find("input,select,textarea").eq(0).focus();
		resetAttributeNames([cloned]);
		resetButtons();
	});

	// Move section up, rearrange IDs
	$(".wnlt-up").click(function(e){
		e.preventDefault();
		var theRepeatingGroup = $(this).parents(".wnlt-repeating"),
			prevRepeatingGroup = theRepeatingGroup.prev();
		if ( prevRepeatingGroup.length ) {
			theRepeatingGroup.insertBefore(prevRepeatingGroup);
			resetAttributeNames([theRepeatingGroup,prevRepeatingGroup]);
			resetTags();
		}
	});

	// Move section down, rearrange IDs
	$(".wnlt-down").click(function(e){
		e.preventDefault();
		var theRepeatingGroup = $(this).parents(".wnlt-repeating"),
			nextRepeatingGroup = theRepeatingGroup.next();
		if ( nextRepeatingGroup.length ) {
			theRepeatingGroup.insertAfter(nextRepeatingGroup);
			resetAttributeNames([theRepeatingGroup,nextRepeatingGroup]);
			resetTags();
		}
	});

	// Remove section, rearrange IDs
	$(".wnlt-remove").click(function(e){
		e.preventDefault();
		var theRepeatingGroup = $(this).parents(".wnlt-repeating"),
			siblingRepeatingGroups = theRepeatingGroup.siblings(".wnlt-repeating");
		theRepeatingGroup.remove();
		resetAttributeNames(siblingRepeatingGroups);
		resetButtons();
		resetTags();
	});

	resetAttributeNames($(".wnlt-repeating"));
	resetButtons();
	resetTags();
	resetExtra();

});

function string_to_slug(str) {
	str = str.replace(/^\s+|\s+$/g, ''); // trim
	str = str.toLowerCase();

	// remove accents, swap ñ for n, etc
	var from = "àáäâèéëêìíïîòóöôùúüûñç·/_,:;";
	var to   = "aaaaeeeeiiiioooouuuunc------";
	for (var i=0, l=from.length ; i<l ; i++) {
		str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
	}

	str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
		.replace(/\s+/g, '-') // collapse whitespace and replace by -
		.replace(/-+/g, '-'); // collapse dashes

	return str;
}

function selectText(container) {
	container = document.getElementById(container) || container;
	if (document.selection) {
		var range = document.body.createTextRange();
		range.moveToElementText(container);
		range.select();
	} else if (window.getSelection) {
		var range = document.createRange();
		range.selectNode(container);
		window.getSelection().addRange(range);
	}
}

(function($) {

    $.fn.drags = function(opt) {

        opt = $.extend({handle:"",cursor:"move",callback:null}, opt);

        if(opt.handle === "") {
            var $el = this;
        } else {
            var $el = this.find(opt.handle);
        }

        return $el.css('cursor', opt.cursor).on("mousedown", function(e) {
            if(opt.handle === "") {
                var $drag = $(this).addClass('draggable');
            } else {
                var $drag = $(this).addClass('active-handle').parent().addClass('draggable');
            }
            var z_idx = $drag.css('z-index'),
                drg_h = $drag.outerHeight(),
                drg_w = $drag.outerWidth(),
                pos_y = $drag.offset().top + drg_h - e.pageY,
                pos_x = $drag.offset().left + drg_w - e.pageX;
            $drag.css('z-index', 1000).parents().on("mousemove", function(e) {
                $('.draggable').offset({
                    top:e.pageY + pos_y - drg_h,
                    left:e.pageX + pos_x - drg_w
                }).on("mouseup", function() {
                    $(this).removeClass('draggable').css('z-index', z_idx);
                });
            });
            e.preventDefault(); // disable selection
        }).on("mouseup", function() {
            if(opt.handle === "") {
                $(this).removeClass('draggable');
            } else {
                $(this).removeClass('active-handle').parent().removeClass('draggable');
            }
			if ( typeof callback == "function" ) {
				callback(this);
			}
        });

    }

	$.fn.extend({
		insertAtCaret: function(myValue) {
			if (document.selection) {
					this.focus();
					sel = document.selection.createRange();
					sel.text = myValue;
					this.focus();
			}
			else if (this.selectionStart || this.selectionStart == '0') {
				var startPos = this.selectionStart;
				var endPos = this.selectionEnd;
				var scrollTop = this.scrollTop;
				this.val( this.val().substring(0, startPos) + myValue + this.val().substring(endPos,this.val().length) );
				this.focus();
				this.selectionStart = startPos + myValue.length;
				this.selectionEnd = startPos + myValue.length;
				this.scrollTop = scrollTop;
			} else {
				this.val( this.val() + myValue );
				this.focus();
			}
		}
	})

})(jQuery);
