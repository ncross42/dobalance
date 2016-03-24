jQuery(document).ready(function($){

	$('#jstree_user').jstree({
		'core' : {
			'data' : {
				'url' : ajaxurl + '?action=dob_admin_page_user&nonce='+locale_strings.nonce,
				'data' : function (node) {
					var nid = ('#'==node.id) ? 0 : node.id;
					return { 'pid' : nid };
				}
			},
			'check_callback' : true,
			'themes' : {
				'dots' : false,
				'responsive' : false
			}
		},
		'hotkeys' : false,
		'force_text' : true,
		'plugins' : ['state']
	})
	.bind("loaded.jstree", function (event, data) {	// 'ready.jstree'
		// you get two params - event & data - check the core docs for a detailed description
		$(this).jstree("open_all");
	})
	/*.keydown(function( event ) {
		if ( event.which == 113 ) {
			alert('all');
			event.preventDefault();
		}
	})
	.on('keydown.jstree', 'keyup.jstree', '.jstree-anchor', function (e) {
		// e.which 
		if ( e.which == 113 ) {
			alert('asdf');
			e.preventDefault();
		}
	})*/
	;

});
