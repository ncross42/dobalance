<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

<?php
$label_tabs_1 = __('Menu').' '.__( 'Settings' );
$label_tabs_2 = __('UPIN').' '.__( 'Settings' );
$label_spi = '개인정보 취급'; //__('Successfully Certified', DOBslug);
?>
	<div id="tabs" class="settings-tab">
		<ul>
			<li><a href="#tabs-1"><?php echo $label_tabs_1; ?></a></li>
			<li><a href="#tabs-2"><?php echo $label_tabs_2; ?></a></li>
			<!--li><a href="#tabs-3"><?php _e( 'Import/Export', DOBslug ); ?></a></li-->
		</ul>

		<div id="tabs-1" class="wrap">
			<div class="postbox">
				<div class="inside">
				<form method="post" action="options.php">
					<?php settings_fields(DOBslug.'_options_menu'); ?>
					<table class="form-table">
						<tr valign="top"><th scope="row">Auto Hierarchy Menu</th>
							<td>
								<label><input type="radio" name="dob_menu_hierarchy" value="0" <?php checked(get_option('dob_menu_hierarchy'), '0'); ?> >disable</label>
								&nbsp; &nbsp;
								<label>
									<input type="radio" name="dob_menu_hierarchy" value="1" <?php checked(get_option('dob_menu_hierarchy'), '1'); ?> >Insert
									<i class="dashicons dashicons-align-left"></i>
								</label>
								&nbsp; &nbsp;
								<label>
									<input type="radio" name="dob_menu_hierarchy" value="2" <?php checked(get_option('dob_menu_hierarchy'), '2'); ?> >Append
									<i class="dashicons dashicons-align-right"></i>
								</label>
								<br><span class="description"><?php echo __( 'Auto generated Hierarchy Menu (disable or select position)', DOBslug ); ?></span>
							</td>
						</tr>
						<tr valign="top"><th scope="row">Auto Topic Menu</th>
							<td>
								<label><input type="radio" name="dob_menu_topic" value="0" <?php checked(get_option('dob_menu_topic'), '0'); ?> >disable</label>
								&nbsp; &nbsp;
								<label>
									<input type="radio" name="dob_menu_topic" value="1" <?php checked(get_option('dob_menu_topic'), '1'); ?> >Insert
									<i class="dashicons dashicons-align-left"></i>
								</label>
								&nbsp; &nbsp;
								<label>
									<input type="radio" name="dob_menu_topic" value="2" <?php checked(get_option('dob_menu_topic'), '2'); ?> >Append
									<i class="dashicons dashicons-align-right"></i>
								</label>
								<br><span class="description"><?php echo __( 'Auto generated Topic Menu (disable or select position)', DOBslug ); ?></span>
							</td>
						</tr>
						<tr valign="top"><th scope="row">Auto Mypage Menu</th>
							<td>
								<label><input type="radio" name="dob_menu_mypage" value="0" <?php checked(get_option('dob_menu_mypage'), '0'); ?> >disable</label>
								&nbsp; &nbsp;
								<label>
									<input type="radio" name="dob_menu_mypage" value="1" <?php checked(get_option('dob_menu_mypage'), '1'); ?> >Insert
									<i class="dashicons dashicons-align-left"></i>
								</label>
								&nbsp; &nbsp;
								<label>
									<input type="radio" name="dob_menu_mypage" value="2" <?php checked(get_option('dob_menu_mypage'), '2'); ?> >Append
									<i class="dashicons dashicons-align-right"></i>
								</label>
								<br><span class="description"><?php echo __( 'Auto generated Mypage Menu (disable or select position)', DOBslug ); ?></span>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
					</p>
				</form>
				</div>
			</div>
			<!-- @TODO: Provide other markup for your options page here. -->
		</div>

		<div id="tabs-2" class="wrap">
			<div class="postbox">
				<div class="inside">
				<form method="post" action="options.php">
					<?php settings_fields(DOBslug.'_options_upin'); ?>
					<table class="form-table">
						<tr valign="top"><th scope="row">Use UPIN</th>
							<td>
								<input type="checkbox" name="dob_use_upin" value="1" <?php checked(get_option('dob_use_upin'), '1'); ?> >
								<span class="description"><?php echo __( 'Unique Personal Identification Number', DOBslug ); ?></span>
							</td>
						</tr>
						<tr valign="top"><th scope="row">UPIN service type</th>
							<td>
								<input type="text" name="dob_upin_type" size=20 value="<?php echo get_option('dob_upin_type'); ?>" >
								<span class="description"><?php echo '서비스 제공자';/*__( 'Service Provider', DOBslug );*/ ?></span>
							</td>
						</tr>
						<tr valign="top"><th scope="row">UPIN cp-id</th>
							<td>
								<input type="text" name="dob_upin_cpid" size=20 value="<?php echo get_option('dob_upin_cpid'); ?>" >
								<span class="description"><?php echo '계약사ID';/*__( 'Contract ID', DOBslug );*/ ?></span>
							</td>
						</tr>
						<tr valign="top"><th scope="row">UPIN key-file</th>
							<td>
								<input type="text" name="dob_upin_keyfile" size=100 value="<?php echo get_option('dob_upin_keyfile'); ?>" />
								<p class="description"><?php echo __( 'FullPath of UPIN key-file.(certification) ', DOBslug ); ?></p>
							</td>
						</tr>
						<tr valign="top"><th scope="row">UPIN log-path</th>
							<td>
								<input type="text" name="dob_upin_logpath" size=100 value="<?php echo get_option('dob_upin_logpath'); ?>" />
								<p class="description"><?php echo __( 'FullPath of UPIN process log', DOBslug ); ?></p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
					</p>
				</form>
				</div>
			</div>
			<!-- @TODO: Provide other markup for your options page here. -->
		</div>

<!--
		<div id="tabs-3" class="metabox-holder">
			<div class="postbox">
				<h3 class="hndle"><span><?php _e( 'Export Settings', DOBslug ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Export the plugin settings for this site as a .json file. This allows you to easily import the configuration into another site.', DOBslug ); ?></p>
						<form method="post">
							<p><input type="hidden" name="pn_action" value="export_settings" /></p>
							<p>
									<?php wp_nonce_field( 'pn_export_nonce', 'pn_export_nonce' ); ?>
									<?php submit_button( __( 'Export' ), 'secondary', 'submit', false ); ?>
							</p>
						</form>
				</div>
			</div>
			<div class="postbox">
				<h3 class="hndle"><span><?php _e( 'Import Settings', DOBslug ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Import the plugin settings from a .json file. This file can be obtained by exporting the settings on another site using the form above.', DOBslug ); ?></p>
					<form method="post" enctype="multipart/form-data">
						<p> <input type="file" name="pn_import_file"/> </p>
						<p>
							<input type="hidden" name="pn_action" value="import_settings" />
							<?php wp_nonce_field( 'pn_import_nonce', 'pn_import_nonce' ); ?>
							<?php submit_button( __( 'Import' ), 'secondary', 'submit', false ); ?>
						</p>
					</form>
				</div>
			</div>
		</div>
-->

	</div> <!-- end of tabs -->

	<div class="right-column-settings-page postbox">
		<h3 class="hndle"><span><?php _e( 'DoBalance.', DOBslug ); ?></span></h3>
		<div class="inside">
		<a href="https://github.com/ncross42/DoBalance"><img src="<?php echo plugins_url('assets/DoBalance_256x256.png', dirname(dirname(__FILE__)) );?>" alt=""></a>
		<a href="https://github.com/ncross42/DoBalance"><img src="<?php echo plugins_url('assets/DoBalance_Korea_379x256.png', dirname(dirname(__FILE__)) );?>" alt=""></a>
		</div>
	</div>

</div>
