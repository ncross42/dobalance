jQuery(document).ready(function($){

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
