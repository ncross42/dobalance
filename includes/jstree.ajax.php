<?php
/**
 * Create ajax callback for this plugin
 */

if ( !class_exists( 'jsTree' ) ) {
	require_once('jstree.class.php');
}

add_action( 'wp_ajax_dob_admin_jstree', 'dob_admin_jstree_ajax' );
if( !function_exists( 'dob_admin_jstree_ajax' ) ) :
	function dob_admin_jstree_ajax() {
		if ( isset($_REQUEST) ) {
			if ( !isset( $_REQUEST['nonce'] ) 
				|| !wp_verify_nonce( $_REQUEST['nonce'], 'dob_admin_jstree_ajax'.DOBver ) 
			) {
				exit('error nonce');
				return;
			}
		}

		$taxonomy = isset($_REQUEST['taxonomy']) ? $_REQUEST['taxonomy'] : '';
		$ondrag = isset($_REQUEST['ondrag']);

		//$terms = $_REQUEST['terms'];
		if(isset($_GET['operation'])) {
			$tree = new jsTree( array('taxonomy'=>$taxonomy) );
			try {
				$rslt = null;
				switch($_GET['operation']) {
				case 'analyze':
					var_dump($tree->analyze(true));
					die();
					break;
				case 'get_node':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;
					$temp = $tree->get_children($node);
					$rslt = array();
					foreach($temp as $v) {
						switch ( $v['taxonomy'] ) {
							case 'hierarchy': $icon = 'dashicons dashicons-networking'; break;
							case 'topic': $icon = 'dashicons dashicons-editor-paste-text'; break;
							case 'group': $icon = 'dashicons dashicons-groups'; break;
							case 'category': default: $icon = 'dashicons dashicons-category';
						}
						$a_attr = array ( 'slug'=>$v['slug'], 'pos'=>$v['pos'], 'taxonomy'=>$v['taxonomy'] );
						if ( $ondrag ) {
							$a_attr['draggable'] = 'true';
							$a_attr['ondragstart'] = 'drag(event)';
						}
						$rslt[] = array(
							'id'					=> $v['term_taxonomy_id'],
							'text'				=> $v['name'].'//'.$v['slug'],
							'children'		=> ($v['rgt'] - $v['lft'] > 1),
							'icon'				=> $icon,
							'li_attr'			=> array (),
							'a_attr'			=> $a_attr,
						);
					}
					break;
				case "get_content":
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? $_GET['id'] : 0;
					$node = explode(':', $node);
					if(count($node) > 1) {
						$rslt = array('content' => 'Multiple selected');
					} else {
						$temp = $tree->get_node((int)$node[0], array('with_path' => true));
						$content = 'Selected: /' . implode('/',array_map(function($v){ return $v['name']; }, $temp['path'])). '/'.$temp['name'];
						$rslt = array(
							'content' => $content,
						);
					}
					break;
				case 'create_node':
					$parent_id = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;
					$position = isset($_GET['position']) ? (int)$_GET['position'] : 0;
					$text = trim($_GET['text']);
					if ( strpos($text, '//') < 1 ) {
						$name = 'name-s-'.time();
						$slug = 'slug-s-'.time();
					} else {
						list( $name, $slug ) = explode('//',$text);
					}
					$temp = $tree->mk($parent_id, $position, array('name'=>$name,'slug'=>$slug) );
					$rslt = array('id' => $temp);
          $tree->log2cache($_GET['operation']." name:$name, slug:$slug");
					break;
				case 'rename_node':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;
					$text = trim($_GET['text']);
					if ( 1 <= strpos($text,'//') ) {
						list($name,$slug) = explode('//',$text);
						$rslt = $tree->rn( $node, array('name'=>$name,'slug'=>$slug) );
					}
					break;
				case 'delete_node':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;
					$rslt = $tree->rm($node);
          $tree->log2cache($_GET['operation']." name:$name, slug:$slug");
					break;
				case 'move_node':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;
					$parn = isset($_GET['parent']) && $_GET['parent'] !== '#' ? (int)$_GET['parent'] : 0;
					$rslt = $tree->mv($node, $parn, isset($_GET['position']) ? (int)$_GET['position'] : 0);
          $tree->log2cache($_GET['operation']." node:$node, parent:$parn");
					break;
				case 'copy_node':
					$node = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;
					$parn = isset($_GET['parent']) && $_GET['parent'] !== '#' ? (int)$_GET['parent'] : 0;
					$rslt = $tree->cp($node, $parn, isset($_GET['position']) ? (int)$_GET['position'] : 0);
          $tree->log2cache($_GET['operation']." node:$node, parent:$parn");
					break;
				default:
					throw new Exception('Unsupported operation: ' . $_GET['operation']);
					break;
				}
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode($rslt,JSON_UNESCAPED_UNICODE);
			}
			catch (Exception $e) {
				header($_SERVER["SERVER_PROTOCOL"] . ' 500 Server Error');
				header('Status:  500 Server Error');
				echo $e->getMessage();
			}
			die();
		}
	}
endif;

?>
