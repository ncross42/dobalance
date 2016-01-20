jQuery(document).ready(function($){
	var $sidebar   = $("#floater"), 
			$window    = $(window),
			offset     = $sidebar.offset(),
			topPadding = 15;
	$window.scroll(function() {
		if ($window.scrollTop() > offset.top) {
			$sidebar.stop().animate({
				//marginTop: $window.scrollTop() - offset.top + topPadding
				marginTop: 0
			});
		} else {
			$sidebar.stop().animate({
				marginTop: 0
			});
		}
		//console.log( {marginTop: $window.scrollTop() - offset.top + topPadding, scrollTop:$window.scrollTop(), offset_top:offset.top } );
	});

/*
	alert('start dob_admin_jstree.js');

	$('#jstree_category').jstree({
	});

	$(window).resize(function () {
		var h = Math.max($(window).height() - 0, 420);
		$('#container, #data, #tree, #data .content').height(h).filter('.default').css('lineHeight', h + 'px');
	}).resize();
*/
	var paramDefault = { 
		'action': 'dob_admin_jstree',
		'nonce' : locale_strings.nonce
	};

	$('#jstree_category').jstree({
		'core' : {
			'data' : {
				//'url' : ajaxurl + '?action=dob_admin_jstree&operation=get_node',
				'url' : ajaxurl + '?action=dob_admin_jstree&operation=get_node&ondrag=1&taxonomy[]=topic&taxonomy[]=hierarchy&nonce='+locale_strings.nonce,
				'data' : function (node) {
					return { 'id' : node.id };
				}
			},
			'check_callback' : true,
			'themes' : {
				'responsive' : false
			}
		},
		'force_text' : true,
		'plugins' : ['state'/*,'dnd','contextmenu','wholerow'*/]
	});

});
