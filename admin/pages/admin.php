<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<div id="tabs" class="settings-tab">
		<ul>
				<li><a href="#tabs-1"><?php _e( 'Settings' ); ?></a></li>
				<li><a href="#tabs-2"><?php _e( 'Settings' ); ?> 2</a></li>
				<li><a href="#tabs-3"><?php _e( 'Import/Export', DOBslug ); ?></a></li>
		</ul>

		<div id="tabs-1" class="wrap">
			<div class="postbox">
				<h3 class="hndle"><span><?php _e( 'Category Settings', DOBslug ); ?></span></h3>
				<div class="inside">
				<form method="post" action="options.php">
						<?php settings_fields(DOBslug.'_options'); ?>
						<?php $root_hierarchy = get_option('root_hierarchy'); ?>
						<?php $root_subject = get_option('root_subject'); ?>
						<table class="form-table">
								<!--tr valign="top"><th scope="row">A Checkbox</th>
										<td><input name="asdf" type="checkbox" value="1" <?php checked('1', $root_hierarchy); ?> /></td>
								</tr-->
								<tr valign="top"><th scope="row">Hierarchy ROOT-SLUG</th>
										<td>
											<input type="text" name="root_hierarchy" value="<?php echo $root_hierarchy; ?>" />
											<p class="description"><?php echo __( 'ROOT Hierarchy Category SLUG (requirement)', DOBslug ); ?></p>
										</td>
								</tr>
								<tr valign="top"><th scope="row">Subject ROOT-SLUG</th>
										<td>
											<input type="text" name="root_subject" value="<?php echo $root_subject; ?>" />
											<p class="description"><?php echo __( 'ROOT Subject Category SLUG (requirement)', DOBslug ); ?></p>
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
			<!-- @TODO: Provide other markup for your options page here. -->
			<div class="postbox">
				<h3 class="hndle"><span><?php _e( 'Category Settings', DOBslug ); ?></span></h3>
			</div>
		</div>

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
		</div> <!-- end of tabs-3 -->

	</div> <!-- end of tabs -->

	<div class="right-column-settings-page postbox">
		<h3 class="hndle"><span><?php _e( 'DoBalance.', DOBslug ); ?></span></h3>
		<div class="inside">
		<a href="https://github.com/ncross42/DoBalance"><img src="<?php echo plugins_url('assets/DoBalance_256x256.png', dirname(dirname(__FILE__)) );?>" alt=""></a>
		<a href="https://github.com/ncross42/DoBalance"><img src="<?php echo plugins_url('assets/DoBalance_Korea_379x256.png', dirname(dirname(__FILE__)) );?>" alt=""></a>
		</div>
	</div>

</div>
