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

	// favorites
	$html = dob_make_menu_favorite();
	#$items = $html."\n".$items; // insert
  $items .= "\n".$html;       // append

	if ( $himenu ) {
		$html = dob_make_menu_taxonomy('hierarchy');
#file_put_contents('/tmp/hi.html',$html);
		if ( $himenu == 'insert' ) {
			$items = $html."\n".$items;
		} else if ( $himenu == 'append' ) {
			$items .= "\n".$html;
		}
	}

	if ( $tomenu ) {
		$html = dob_make_menu_taxonomy('topic');
#file_put_contents('/tmp/to.html',$html);
		if ( $tomenu == 'insert' ) {
			$items = $html."\n".$items;
		} else if ( $tomenu == 'append' ) {
			$items .= "\n".$html;
		}
	}

	if ( $mymenu ) {
		$label_login    = '로그인';    // __('Log In',DOBslug);
		$label_register = '회원가입';  // __('Register',DOBslug);
		$html = <<<HTML
<li id="menu-item-mypage" class="menu-item menu-item-mypage menu-item-has-children" aria-haspopup="true" >
	<a href="/wp-login.php">$label_login</a>
	<ul class="sub-menu">
		<li class="menu-item"><a href="/wp-login.php?action=register">$label_register</a></li>
	</ul>
</li>
HTML;
		if ( is_user_logged_in() ) {
			$label_mypage    = '관리';       //__('Management',DOBslug);
			$label_dobalance = '기본설정';   //__('Basic Setting',DOBslug);
			$label_favorite  = '즐겨찾기';   //__('Favorites',DOBslug);
			$label_cart      = '투표바구니'; //__('my voting cart',DOBslug);
			$label_user      = '유저 계층도';//__('jsTree user hierarchy',DOBslug),
			$label_logout    = __('Logout',DOBslug);
			$html = <<<HTML
<li id="menu-item-mypage" class="menu-item menu-item-mypage menu-item-has-children" aria-haspopup="true">
	<a href="/wp-admin/">$label_mypage</a>
	<ul class="sub-menu">
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

function dob_make_menu_favorite() {
	global $wpdb;
	$label_favorite  = '즐겨찾기';   //__('Favorites',DOBslug);
	$sql = "SELECT term_taxonomy_id, tt.taxonomy, name, slug
		FROM {$wpdb->prefix}dob_user_category cate
			JOIN {$wpdb->prefix}term_taxonomy tt USING (term_taxonomy_id)
			JOIN {$wpdb->prefix}terms USING (term_id)
		WHERE cate.taxonomy='favorite' AND user_id=".(int)get_current_user_id();
	$menus = $wpdb->get_results($sql);
	$html_sub = '';
	foreach ( $menus as $m ) {
		$html_sub .= "
			<li class='menu-item menu-item-type-favorite'><a href='/?{$m->taxonomy}={$m->slug}'>{$m->name}</a></li>";
	}
#file_put_contents('/tmp/fa.html',$sql.PHP_EOL.$html_sub);
	return <<<HTML
<li id="menu-item-favorite" class="menu-item menu-item-type-favorite menu-item-favorite menu-item-has-children" aria-haspopup="true">
	<a href="/wp-admin/admin.php?page=dobalance_jstree_favorite">$label_favorite</a>
	<ul class="sub-menu">
		$html_sub
	</ul>
</li>
HTML;
}

function dob_make_menu_taxonomy($taxonomy) {
	global $wpdb;
	$title = $wpdb->get_row("SELECT name, slug 
		FROM {$wpdb->prefix}term_taxonomy JOIN {$wpdb->prefix}terms USING(term_id)
		WHERE taxonomy='$taxonomy' AND lvl=0 LIMIT 1");
	if ( empty($title) ) return '';
	$title_name = $title->name; $title_slug = $title->slug;
	$sql = "SELECT name, slug
		FROM {$wpdb->prefix}term_taxonomy JOIN {$wpdb->prefix}terms USING(term_id) 
		WHERE taxonomy='$taxonomy' AND lvl=1
		ORDER BY pos";
	$menus = $wpdb->get_results($sql);
	$html_sub = '';
	foreach ( $menus as $m ) {
		$html_sub .= "
			<li class='menu-item menu-item-type-taxonomy'><a href='/?$taxonomy={$m->slug}'>{$m->name}</a></li>";
	}
	$label_all = '전체';	// __('All',DOBslug);
	return <<<HTML
<li id="menu-item-$taxonomy" class="menu-item menu-item-type-taxonomy menu-item-$taxonomy menu-item-has-children" aria-haspopup="true">
	<a href="/?$taxonomy=$title_slug/">$title_name($label_all)</a>
	<ul class="sub-menu">
		$html_sub
	</ul>
</li>
HTML;
}
