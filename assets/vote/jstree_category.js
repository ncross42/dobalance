jQuery(document).ready(function($){

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

	var g_taxonomy = '';

	$('#jstree_category').jstree({
		'core' : {
			'data' : {
				//'url' : ajaxurl + '?action=dob_admin_jstree&operation=get_node',
				'url' : ajaxurl + '?action=dob_admin_jstree&operation=get_node&nonce='+locale_strings.nonce,
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
		'plugins' : ['state','dnd','contextmenu'/*,'wholerow'*/]
	})
	.on('delete_node.jstree', function (e, data) {
		$.get( ajaxurl + '?action=dob_admin_jstree&nonce='+locale_strings.nonce, 
			{ 'operation':'delete_node', 'id':data.node.id, 'taxonomy':g_taxonomy })
		.fail(function () {
			data.instance.refresh();
		});
	})
	.on('create_node.jstree', function (e, data) {
		var ts = new Date().getTime().toString();
		var ts2 = ts.substr( ts.length -5 -3, 5 );
		var time = new Date().toLocaleTimeString();
		var time2 = time.substr( 1 + time.indexOf(' ') ).replace(/:/g,'');
		var new_text = 'name-' + time2 + '//' + 'slug-'+time2;
		data.node.text = new_text;
		$.get( ajaxurl + '?action=dob_admin_jstree&nonce='+locale_strings.nonce, 
			{'operation':'create_node', 'id':data.node.parent, 'taxonomy':g_taxonomy, 'position':data.position, 'text':data.node.text }
		)
		.done(function (d) {
			data.instance.set_id(data.node, d.id);
		})
		.fail(function () {
			data.instance.refresh();
		});
	})
	.on('rename_node.jstree', function (e, data) {
		$.get( ajaxurl + '?action=dob_admin_jstree&nonce='+locale_strings.nonce, 
			{'operation':'rename_node','id' : data.node.id, 'taxonomy':g_taxonomy, 'text' : data.text })
		.fail(function () {
			data.instance.refresh();
		});
	})
	.on('move_node.jstree', function (e, data) {
		$.get( ajaxurl + '?action=dob_admin_jstree&nonce='+locale_strings.nonce, 
			{'operation':'move_node','id' : data.node.id, 'taxonomy':g_taxonomy, 'parent' : data.parent, 'position' : data.position })
		.fail(function () {
			data.instance.refresh();
		});
	})
	.on('copy_node.jstree', function (e, data) {
		$.get( ajaxurl + '?action=dob_admin_jstree&nonce='+locale_strings.nonce, 
			{'operation':'copy_node','id' : data.original.id, 'taxonomy':g_taxonomy, 'parent' : data.parent, 'position' : data.position })
		.always(function () {
			data.instance.refresh();
		});
	})
	.on('changed.jstree', function (e, data) {
		if(data && data.selected && data.selected.length) {
			$.get( ajaxurl + '?action=dob_admin_jstree&nonce='+locale_strings.nonce, 
				{'operation':'get_content','id': data.selected.join(':') } )
			.done(function (d) {
				$('#data .default').text(d.content).show();
			});
		} else {
			$('#data .content').hide();
			$('#data .default').text('Select a file from the tree.').show();
		}
	})
	.on('select_node.jstree', function (e, data) {
		$node = $('#'+data.node.a_attr.id);
		g_taxonomy = $node.attr('taxonomy');
	});

});
