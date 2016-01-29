jQuery(document).ready(function($){
	$(".jlk").click( function(e){
		e.preventDefault();
		var task = $(this).attr("data-task");
		var post_id = $(this).attr("data-post_id");
		var user_id = $(this).attr("data-user_id");
		var nonce = $(this).attr("data-nonce");

		if ( '0'==user_id ) {
			alert('Please Login');
			return;
		}

		$(".status-" + post_id).html("&nbsp;&nbsp;").addClass("loading-img").show();

		$.post(
			dob_vote_js.ajaxurl
			//ajaxurl
			, {action: "bdd", task : task, post_id : post_id, nonce: nonce}
			, function( response ) {
				window.console.log(response);
				$(".lc-" + post_id).html(response.like);
				$(".unlc-" + post_id).html(response.unlike);
				$(".status-" + post_id).removeClass("loading-img").empty().html(response.msg);
			}
		);
	});

	$('#toggle-view li.toggle').click(function (e) {
		var text = $(this).children('div.panel');
		if (text.is(':hidden')) {
			text.slideDown('200');
			$(this).children('span').html('[close]');        
		} else {
			text.slideUp('200');
			$(this).children('span').html('[open]');        
		}
	});
});
