<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   DoBalance
 * @author    Your Name <email@example.com>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2015 Your Name or Company Name
 */

global $wpdb;

$message = '<b>[FORMAT]</b> category-NAME (native language) <span style="color:red"><b>//</b></span> category-SLUG (<b>only english</b>)';
$nonce_jstree = wp_create_nonce('dobalance_admin_jstree');

$html_favorites = '';

//$hdn_position = array('id'=>0,'text'=>'');
$hdn_favorites = array();
$user_id = (int)get_current_user_id();
if ( isset( $_POST['jstree_user_nonce'] )
	&& wp_verify_nonce( $_POST['jstree_user_nonce'], 'jstree_user.php' ) 
) {
	/*{{{*/ /* hierarchy_position 
	$new_term_taxonomy_id = empty($_POST['hdn_position_id']) ? 0 : (int)$_POST['hdn_position_id'];
	// get old value
	$sql = "SELECT term_taxonomy_id
		FROM {$wpdb->prefix}dob_user_category cate
		WHERE taxonomy='hierarchy' AND user_id=".(int)$user_id;
	$old_id = $wpdb->get_var($sql);
	if ( empty($old_id) ) {
		if ( $new_term_taxonomy_id ) {
			echo $sql = "INSERT INTO {$wpdb->prefix}dob_user_category
				( user_id, taxonomy, term_taxonomy_id )
				VALUES ( $user_id, 'hierarchy', $new_term_taxonomy_id )";
			$wpdb->query($sql);
		}
	} else if ( empty($new_term_taxonomy_id) ) {
		echo $sql = "DELETE FROM {$wpdb->prefix}dob_user_category
			WHERE taxonomy='hierarchy' AND user_id=".(int)$user_id;
		$wpdb->query($sql);
	} else if ( $old_id != $new_term_taxonomy_id ) {
		echo $sql = "UPDATE {$wpdb->prefix}dob_user_category
			SET term_taxonomy_id = $new_term_taxonomy_id
			WHERE taxonomy='hierarchy' AND user_id=".(int)$user_id;
		$wpdb->query($sql);
	}/*}}}*/

	// favorite_category /*{{{*/
	$hdn_favorites = empty($_POST['hdn_favorites']) ? array() : $_POST['hdn_favorites'];
	// get old value
	$sql = "SELECT term_taxonomy_id
		FROM {$wpdb->prefix}dob_user_category
		WHERE taxonomy='favorite' AND user_id=".(int)$user_id;
	$rows = $wpdb->get_results($sql, ARRAY_N);
	$old_ids = array();
	foreach ( $rows as $row ) {
		$old_ids[] = $row[0];
	}
	$del_ids = array_diff($old_ids,$hdn_favorites);
	$new_ids = array_diff($hdn_favorites,$old_ids);

	if ( !empty($del_ids) ) {
		$list_ids = implode(', ',$del_ids);
		echo $sql = "DELETE FROM {$wpdb->prefix}dob_user_category
			WHERE taxonomy='favorite' AND user_id=$user_id
				AND term_taxonomy_id IN ( $list_ids )";
		$wpdb->query($sql);
	}
	if ( !empty($new_ids) ) {
		$sql = "INSERT INTO {$wpdb->prefix}dob_user_category
			( user_id, taxonomy, term_taxonomy_id ) VALUES ";
		$many = array();
		foreach ( $new_ids as $id ) {
			$many[] = "\n\t( $user_id, 'favorite', $id )";
		}
		echo $sql .= implode(',',$many);
		$wpdb->query($sql);
	}
	/*}}}*/
}

/*{{{*/ /* 
$sql = "SELECT term_taxonomy_id, name, slug
	FROM {$wpdb->prefix}dob_user_category cate
		JOIN {$wpdb->prefix}term_taxonomy USING (term_taxonomy_id)
		JOIN {$wpdb->prefix}terms USING (term_id)
	WHERE cate.taxonomy='hierarchy' AND user_id=".(int)$user_id;
$row = $wpdb->get_row($sql, ARRAY_A);
$hdn_position = array ( 
	'id' => $row['term_taxonomy_id'],
	'text' => $row['name'].'//'.$row['slug']
);
$html_position = "<span>{$row['name']}//{$row['slug']}<input type='hidden' name='hdn_position_id' value='{$row['term_taxonomy_id']}'></span>";
/*}}}*/

$sql = "SELECT term_taxonomy_id, name, slug
	FROM {$wpdb->prefix}dob_user_category cate
		JOIN {$wpdb->prefix}term_taxonomy USING (term_taxonomy_id)
		JOIN {$wpdb->prefix}terms USING (term_id)
	WHERE cate.taxonomy='favorite' AND user_id=".(int)$user_id;
$rows = $wpdb->get_results($sql, ARRAY_A);
$favorites = array();
foreach ( $rows as $row ) {
	$html = '<input type="button" value="DEL" onClick="del_category(this)">'
		."<span>{$row['name']}//{$row['slug']}<input type='hidden' name='hdn_favorites[]' value='{$row['term_taxonomy_id']}'></span>";
	$favorites[] = $html;
}
$html_favorites = implode('<br>',$favorites);
//exit($html_favorites);

?>

<style type="text/css">
#floater { width: 200px; position:fixed; top:20%; right:20px; float: right; z-index:1; opacity: 0.8;}
#floater h3, h4 { margin:1px; padding:1px; }
#floater input[type="button"] { padding:0 1px 0 1px; font-size: 14px; }
#floater input[type="text"] { width: 150px; }
#div_favorite { width:200px;height:240px;padding:2px;border:1px solid #aaa;overflow:scroll; }
@media screen and (min-width: 600px) {
	#floater { width: 300px; position:fixed; top:30%; right:20px; float: right; z-index:1; opacity: 0.9;}
	#floater h3, h4 { margin:5px; }
	#floater input[type="text"] { width: 220px; }
	#div_favorite { width:280px;height:240px;padding:2px;border:1px solid #aaa;overflow:scroll; }
}
@media screen and (min-width: 1000px) {
	#floater { width: 300px; position:fixed; top:30%; left:650px; float: right; z-index:1; opacity: 1.0;}
}
/*
	#floater { width: 300px; position:fixed; top:30%; left:30%; float: right; z-index:1; opacity: 1.0;}
	#floater h3, h4 { margin:5px; }
	#floater input[type="text"] { width: 220px; }
	#div_position { width:230px;height:30px;padding:2px;border:1px solid #aaa; }
	#div_favorite { width:280px;height:150px;padding:2px;border:1px solid #aaa;overflow:scroll; }
*/
</style>

<script>
function allowDrop(ev) {
	ev.preventDefault();
}
function drag(ev) {
	ev.dataTransfer.setData("id", ev.target.id);
}
function del_category(el) {
	var el_parent = el.parentNode;
	var el_br = el.previousSibling;
	var el_span = el.nextSibling;
	if ( ! el_br ) {
		el_br = el_span.nextSibling;
	}
	if ( el_br ) el_parent.removeChild(el_br);
	el_parent.removeChild(el_span);
	el_parent.removeChild(el);
}
function drop(ev) {
	ev.preventDefault();
	var $p = ev.target.tagName=='DIV' ? jQuery(ev.target) : jQuery(ev.target.parentNode);
	var id_src = ev.dataTransfer.getData("id");
	var id = id_src.replace('_anchor','');
	if ( $p.find('input[value="'+id+'"]').length ) {
		alert('Check Duplication');
		return;
	}
	if ( $p.find('span').length == 9 ) {
		alert('Check Maximum count (9)');
		return;
	}
	var data_src = document.getElementById(id_src);
	var text = data_src.textContent;
	var name = 'hdn_favorites[]';
	if ( $p.attr('id') == 'div_position' ) {
		name = 'hdn_position_id';
		$p.html('');
	} else {	// div_favorite
		if ( $p.text().length ) {
			$p.append(jQuery('<br>'));
		}
		var new_node = jQuery('<input type="button" value="DEL" onClick="del_category(this)">');
		$p.append(new_node);
	}
	var new_node = jQuery('<span>'+text+'<input type="hidden" name="'+name+'" value="'+id.replace('_anchor','')+'" ></span>');
	$p.append(new_node);
}
</script>

<div id="floater">
	<form method="post">
	<div class="postbox">
		<h3>Favorite Categories</h3>
		<h4>(Drag & Drop, max-count:9 )</h4>
		<!--div>
			<h4>Hierarchy Position (only one)</h4>
			<div id="div_position" ondrop="drop(event)" ondragover="allowDrop(event)">
				<?php /*echo $html_position; */ ?>
			</div>
		</div-->
		<div>
			<div id="div_favorite" ondrop="drop(event)" ondragover="allowDrop(event)"><?php echo $html_favorites; ?></div>
		</div>
	</div>
	<?php wp_nonce_field( 'jstree_user.php', 'jstree_user_nonce' ); ?>
	<div style="text-align:right;"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></div>
	</form>
</div>

<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
  <div id="container" role="main">


		<div class="postbox">
			<h3><span><?php _e( 'jsTree Category', DOBslug ); ?></span></h3>
			<div class="inside">
				<span style="background-color:#F2DEDE"><?php echo $message; ?></span>
				<div id="jstree_category" data-nonce="<?php echo $nonce_jstree; ?>" >
					<ul>
						<li>Root node 1</li>
						<li>Root node 2</li>
					</ul>
				</div>
			</div>
		</div>

	</div>
</div>
