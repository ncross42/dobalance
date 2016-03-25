jQuery(document).ready(function($){
	/*
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
				if ( response.error ) {
					alert('error');
					return;
				}
				var nUp = parseInt($(".lc-" + post_id).text());
				var nDown = parseInt($(".unlc-" + post_id).text());
				if ( response.old == 1 ) {
					$(".lc-" + post_id).html(nUp-1);
					if ( response.task == 'unlike' ) $(".unlc-" + post_id).html(nDown+1);
				} else if ( response.old == -1 ) {
					$(".unlc-" + post_id).html(nDown+1);
					if ( response.task == 'like' ) $(".lc-" + post_id).html(nUp+1);
				} else {
					if ( response.task == 'like' ) $(".lc-" + post_id).html(nUp+1);
					else $(".unlc-" + post_id).html(nDown-1);
				}
				$(".status-" + post_id).removeClass("loading-img").empty().html(response.msg);
				location.reload();
			}
		);
	});
	*/

	$('#toggle-view li.toggle h3').click(function (e) {
		var text = $(this).next('div.panel');
		if (text.is(':hidden')) {
			text.slideDown('200');
			$(this).children('span').html('[close]');        
		} else {
			text.slideUp('200');
			$(this).children('span').html('[open]');        
		}
	});

	var check_dup_vote = function() {
		var form = document.getElementById('formDobVote');
		if ( form.dob_vote_val.value == form.dob_vote_old_val.value ) {
			alert('You can NOT vote with same value');
			return true;
		}
	}

	$('#btn_fast,#btn_cart').click( function (e) {
		this.form.dob_vote_cart.value = (this.id=='btn_cart') ? 1 : 0;
		if ( check_dup_vote() ) window.location = window.location.href;
		else this.form.submit();
	});
});
