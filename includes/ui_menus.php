<?php
/* admin/main.php
	register_setting( DOBslug.'_options_menu', 'dob_menu_hierarchy' , 'trim' );
	register_setting( DOBslug.'_options_menu', 'dob_menu_topic'     , 'trim' );
	register_setting( DOBslug.'_options_menu', 'dob_menu_mypage'    , 'trim' );
 */


add_filter( 'wp_nav_menu_items', 'dob_add_auto_nav_menu', 50, 2 );
function dob_add_auto_nav_menu( $items, $args ){
	global $wpdb;

	$himenu = get_option('dob_menu_hierarchy');
	$tomenu = get_option('dob_menu_topic');
	$mymenu = get_option('dob_menu_mypage');

  $taxonomy = $slug = '';
  if ( $slug = get_query_var('hierarchy') ) $taxonomy = 'hierarchy';
  elseif ( $slug = get_query_var('topic') ) $taxonomy = 'topic';

	// favorites
	$html = dob_make_menu_favorite($taxonomy,$slug);
	#$items = $html."\n".$items; // insert
  $items .= "\n".$html;       // append

	if ( $himenu ) {
		$html = dob_make_menu_taxonomy('hierarchy',($taxonomy=='hierarchy'?$slug:''));
#file_put_contents('/tmp/hi.html',$html);
		if ( $himenu == 'insert' ) {
			$items = $html."\n".$items;
		} else if ( $himenu == 'append' ) {
			$items .= "\n".$html;
		}
	}

	if ( $tomenu ) {
		$html = dob_make_menu_taxonomy('topic',($taxonomy=='topic'?$slug:''));
#file_put_contents('/tmp/to.html',$html);
		if ( $tomenu == 'insert' ) {
			$items = $html."\n".$items;
		} else if ( $tomenu == 'append' ) {
			$items .= "\n".$html;
		}
	}

	if ( $mymenu ) {
    $label_settings = __('Settings');
		$label_login    = __('Log in');
		$label_register = __('Register');
		$html = <<<HTML
<li id="menu-item-settings" class="menu-item menu-item-settings menu-item-has-children dropdown" aria-haspopup="true" >
	<a href="#" data-toggle="dropdown" aria-haspopup="true">$label_settings<span class="caret"></span></a>
  <ul class="dropdown-menu">
		<li class="menu-item"><a href="/wp-login.php">$label_login</a></li>
		<li class="menu-item"><a href="/wp-login.php?action=register">$label_register</a></li>
	</ul>
</li>
HTML;
		if ( is_user_logged_in() ) {
			$label_logout    = __('Log out');
			$label_dobalance = '기본설정';   //__('Basic Settings',DOBslug);
			$label_favorite  = '즐겨찾기';   //__('Favorites',DOBslug);
			$label_cart      = '투표바구니'; //__('my voting cart',DOBslug);
			$label_user      = '유저 계층도';//__('jsTree user hierarchy',DOBslug),
			$html = <<<HTML
<li id="menu-item-settings" class="menu-item menu-item-settings menu-item-has-children dropdown" aria-haspopup="true">
	<a href="/wp-admin/" data-toggle="dropdown" aria-haspopup="true">$label_settings<span class="caret"></span></a>
  <ul class="dropdown-menu">
		<li class="menu-item"><a href="/wp-admin/admin.php?page=dobalance">$label_dobalance</a></li>
		<li class="menu-item"><a href="/wp-admin/admin.php?page=dobalance_jstree_favorite">$label_favorite</a></li>
		<li class="menu-item"><a href="/wp-admin/admin.php?page=dobalance_cart">$label_cart</a></li>
		<li class="menu-item"><a href="/wp-admin/admin.php?page=dobalance_jstree_user">$label_user</a></li>
		<li class="menu-item" style="height:5px; background-color:silver;"><!--span class="icon-bar"></span--></li> <!-- dashicons dashicons-minus -->
		<li class="menu-item"><a href="/wp-login.php?action=logout">$label_logout</a></li>
	</ul>
</li>
HTML;
		}

		if ( $mymenu == 'insert' ) {
			$items = $html."\n".$items;
		} else if ( $mymenu == 'append' ) {
			$items .= "\n".$html;
		}
	}

	return $items;
}

function dob_make_menu_favorite($taxonomy,$slug) {
	global $wpdb;

	$label_favorite  = '즐겨찾기';   //__('Favorites',DOBslug);
	$sql = "SELECT term_taxonomy_id, tt.taxonomy, name, slug
		FROM {$wpdb->prefix}dob_user_category cate
			JOIN {$wpdb->prefix}term_taxonomy tt USING (term_taxonomy_id)
			JOIN {$wpdb->prefix}terms USING (term_id)
		WHERE cate.taxonomy='favorite' AND user_id=".(int)get_current_user_id();
	$menus = $wpdb->get_results($sql);
	$html_sub = $parent = '';
	foreach ( $menus as $m ) {
    $active = '';
    if ( $m->taxonomy == $taxonomy && $m->slug == $slug ) {
      $parent = 'current-menu-ancestor current-menu-parent';
      $active = 'active';
    }
		$html_sub .= "
			<li class='menu-item menu-item-type-favorite $active'><a href='/?{$m->taxonomy}={$m->slug}'>{$m->name}</a></li>";
	}
  $dd = $children = $a_attr = $ul_cls = '';
  if ( $html_sub ) {
    $dd       = 'dropdown';
    $children = 'menu-item-has-children';
    $a_attr   = 'data-toggle="dropdown" aria-haspopup="true"';
    $ul_cls   = 'class="dropdown-menu"';
    $label_favorite .= '<span class="caret"></span>';
  }
#file_put_contents('/tmp/fa.html',$sql.PHP_EOL.$html_sub);
	return <<<HTML
<li id="menu-item-favorite" class="menu-item menu-item-type-favorite menu-item-favorite $parent $children $dd" aria-haspopup="true">
	<a href="#" $a_attr >$label_favorite</a>
	<ul $ul_cls >
		$html_sub
	</ul>
</li>
HTML;
}

function dob_make_menu_taxonomy($taxonomy,$slug) {
	global $wpdb;
	$title = $wpdb->get_row("SELECT name, slug 
		FROM {$wpdb->prefix}term_taxonomy JOIN {$wpdb->prefix}terms USING(term_id)
		WHERE taxonomy='$taxonomy' AND lvl=0 LIMIT 1");
	if ( empty($title) ) return '';
	$title_name = $title->name; $title_slug = $title->slug;
	$title_name .= ' 전체';	// __('All',DOBslug);
	$sql = "SELECT name, slug
		FROM {$wpdb->prefix}term_taxonomy JOIN {$wpdb->prefix}terms USING(term_id) 
		WHERE taxonomy='$taxonomy' AND lvl=1
		ORDER BY pos";
	$menus = $wpdb->get_results($sql);
	$html_sub = $parent = '';
	foreach ( $menus as $m ) {
    $active = '';
    if ( $m->slug == $slug ) {
      $parent = 'current-menu-ancestor current-menu-parent';
      $active = 'active';
    }
		$html_sub .= "
			<li class='menu-item menu-item-type-taxonomy $active'><a href='/?$taxonomy={$m->slug}'>{$m->name}</a></li>";
	}
  $dd = $a_attr = $ul_cls = '';
  if ( $html_sub ) {
    $dd       = 'dropdown';
    $children = 'menu-item-has-children';
    $a_attr   = 'data-toggle="dropdown" aria-haspopup="true"';
    $ul_cls   = 'class="dropdown-menu"';
    $title_name .= '<span class="caret"></span>';
  }
	return <<<HTML
<li id="menu-item-$taxonomy" class="menu-item menu-item-type-taxonomy menu-item-$taxonomy $parent $children $dd" aria-haspopup="true">
	<a href="/?$taxonomy=$title_slug/" $a_attr >$title_name</a>
	<ul $ul_cls >
		$html_sub
	</ul>
</li>
HTML;
}
