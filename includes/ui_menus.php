<?php
/* admin/main.php
  register_setting( DOBslug.'_options_menu', 'dob_menu_hierarchy' , 'trim' );
  register_setting( DOBslug.'_options_menu', 'dob_menu_topic'     , 'trim' );
  register_setting( DOBslug.'_options_menu', 'dob_menu_mypage'    , 'trim' );
 */


add_filter( 'wp_nav_menu_items', 'dob_add_auto_nav_menu', 50, 2 );
function dob_add_auto_nav_menu( $items, $args ){
  global $wpdb;

  $style  = get_option('dob_menu_style'); // '' or 'bootstrap'
  $himenu = get_option('dob_menu_hierarchy');
  $tomenu = get_option('dob_menu_topic');
  $mymenu = get_option('dob_menu_mypage');

  $bBS = ($style=='bootstrap');
  $taxonomy = $slug = '';
  if ( $slug = get_query_var('hierarchy') ) $taxonomy = 'hierarchy';
  elseif ( $slug = get_query_var('topic') ) $taxonomy = 'topic';

  // favorites
  $html = dob_make_menu_favorite($bBS,$taxonomy,$slug);
  #$items = $html."\n".$items; // insert
  $items .= "\n".$html;       // append

  if ( $himenu ) {
    $html = dob_make_menu_taxonomy($bBS,'hierarchy',($taxonomy=='hierarchy'?$slug:''));
    #file_put_contents('/tmp/hi.html',$html);
    if ( $himenu == 'insert' ) {
      $items = $html."\n".$items;
    } else if ( $himenu == 'append' ) {
      $items .= "\n".$html;
    }
  }

  if ( $tomenu ) {
    $html = dob_make_menu_taxonomy($bBS,'topic',($taxonomy=='topic'?$slug:''));
    #file_put_contents('/tmp/to.html',$html);
    if ( $tomenu == 'insert' ) {
      $items = $html."\n".$items;
    } else if ( $tomenu == 'append' ) {
      $items .= "\n".$html;
    }
  }

  $dd = $bBS ? 'dropdown' : '';
  $dt = $bBS ? 'data-toggle="dropdown"' : '';
  $span_caret = $bBS ? '<span class="caret"></span>' : '';
  $ul_class = $bBS ? 'dropdown-menu' : 'sub-menu';
  if ( $mymenu ) {
    $label_settings = __('Settings');
    $label_login    = __('Log in');
    $label_register = __('Register');
    $html = <<<HTML
<li id="menu-item-settings" class="menu-item menu-item-settings menu-item-has-children $dd" aria-haspopup="true" >
  <a href="#" $dt aria-haspopup="true">$label_settings$span_caret</a>
  <ul class="$ul_class">
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
<li id="menu-item-settings" class="menu-item menu-item-settings menu-item-has-children $dd" aria-haspopup="true">
  <a href="/wp-admin/" $dt aria-haspopup="true">$label_settings$span_caret</a>
  <ul class="$ul_class">
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

function dob_make_menu_favorite($bBS,$taxonomy,$slug) {
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
    $dd       = $bBS ? 'dropdown' : '';
    $children = 'menu-item-has-children';
    $a_attr   = ($bBS?'data-toggle="dropdown"':'').' aria-haspopup="true"';
    $ul_cls   = $bBS ? 'dropdown-menu' : 'sub-menu';
    $label_favorite .= ($bBS ? '<span class="caret"></span>' : '');
  }
#file_put_contents('/tmp/fa.html',$sql.PHP_EOL.$html_sub);
  return <<<HTML
<li id="menu-item-favorite" class="menu-item menu-item-type-favorite menu-item-favorite $parent $children $dd" aria-haspopup="true">
  <a href="#" $a_attr >$label_favorite</a>
  <ul class="$ul_cls" >
    $html_sub
  </ul>
</li>
HTML;
}

function dob_make_menu_taxonomy($bBS,$taxonomy,$slug) {
  global $wpdb;
  $title = $wpdb->get_row("SELECT name, slug 
    FROM {$wpdb->prefix}term_taxonomy JOIN {$wpdb->prefix}terms USING(term_id)
    WHERE taxonomy='$taxonomy' AND lvl=0 LIMIT 1");
  if ( empty($title) ) return '';
  $title_name = '전체 ';	// __('All',DOBslug);
  $title_name .= $title->name; $title_slug = $title->slug;
  $sql = "SELECT name, slug, chl, term_taxonomy_id
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
      $html_sub_hr = '';
      $html_sub2 = '';
      if ( !empty($m->chl) && $m->slug=='seoulyg' ) {
        $html_sub_hr = '<li class="menu-item" style="height:5px; background-color:silver;"></li>';
        $sql = "SELECT name, slug
          FROM {$wpdb->prefix}term_taxonomy JOIN {$wpdb->prefix}terms USING(term_id) 
          WHERE taxonomy='$taxonomy' AND parent={$m->term_taxonomy_id}
          ORDER BY pos";
          $sub_menus = $wpdb->get_results($sql);
          foreach ( $sub_menus as $sub_m ) {
            $active = '';
            if ( $sub_m->slug == $slug ) {
              $parent = 'current-menu-ancestor current-menu-parent';
              $active = 'active';
            }
            $html_sub2 .= "
              <li class='menu-item menu-item-type-taxonomy $active'><a href='/?$taxonomy={$sub_m->slug}'> &gt; {$sub_m->name}</a></li>";
          }
      }
      $html_sub .= $html_sub_hr."
        <li class='menu-item menu-item-type-taxonomy $active'><a href='/?$taxonomy={$m->slug}'>{$m->name}</a></li>";
      $html_sub .= $html_sub2;
  }
  $dd = $a_attr = $ul_cls = '';
  if ( $html_sub ) {
    $dd       = $bBS ? 'dropdown' : '';
    $children = 'menu-item-has-children';
    $a_attr   = ($bBS?'data-toggle="dropdown"':'').' aria-haspopup="true"';
    $ul_cls   = $bBS ? 'dropdown-menu' : 'sub-menu';
    $title_name .= ($bBS ? '<span class="caret"></span>' : '');
  }
  return <<<HTML
<li id="menu-item-$taxonomy" class="menu-item menu-item-type-taxonomy menu-item-$taxonomy $parent $children $dd" aria-haspopup="true">
  <a href="/?$taxonomy=$title_slug/" $a_attr >$title_name</a>
  <ul class="$ul_cls" >
    $html_sub
  </ul>
</li>
HTML;
}
