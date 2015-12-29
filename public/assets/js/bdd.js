jQuery(document).ready(function($){
	jQuery(document).on("click", ".jlk", function(e){
		e.preventDefault();
		var task = jQuery(this).attr("data-task");
		var post_id = jQuery(this).attr("data-post_id");
		var nonce = jQuery(this).attr("data-nonce");

		jQuery(".status-" + post_id).html("&nbsp;&nbsp;").addClass("loading-img").show();

		jQuery.post(
			//bddjs.ajax_url
			ajaxurl
			, {action: "bdd", task : task, post_id : post_id, nonce: nonce}
			, function( response ) {
				window.console.log(response);
				jQuery(".lc-" + post_id).html(response.like);
				jQuery(".unlc-" + post_id).html(response.unlike);
				jQuery(".status-" + post_id).removeClass("loading-img").empty().html(response.msg);
			}
		);
	});
});
