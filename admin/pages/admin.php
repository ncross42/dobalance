<?php
require_once('user_hierarchy.php');

global $current_user;
$label_basic = '기본'; // __( 'Basic', DOBslug );
$label_setting = __( 'Settings' );

if ( ! empty($_POST) && isset($_POST['dob_admin'])
	&& wp_verify_nonce( $_POST['dob_admin'], 'dob_admin' ) ) {
	dob_user_hierarchy_update($current_user->ID);
}

?>
<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

			<div class="postbox">
				<form method="post">
				<h3 class="hndle"><span><?php echo $label_basic.' '.$label_setting; ?></span></h3>
				<div class="inside">
				<?php wp_nonce_field( 'dob_admin', 'dob_admin' ); ?>

					<table class="form-table">
<?php echo dob_user_hierarchy_profile($current_user,true); ?>
					</table>
					<p class="submit">
						<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
					</p>
				</div>
				</form>
			</div>

	<div class="right-column-settings-page postbox">
		<h3 class="hndle"><span><?php _e( 'DoBalance.', DOBslug ); ?></span></h3>
		<div class="inside">
		<a href="https://github.com/ncross42/DoBalance"><img src="<?php echo plugins_url('assets/DoBalance_256x256.png', dirname(dirname(__FILE__)) );?>" alt=""></a>
		<a href="https://github.com/ncross42/DoBalance"><img src="<?php echo plugins_url('assets/DoBalance_Korea_379x256.png', dirname(dirname(__FILE__)) );?>" alt=""></a>
		</div>
	</div>

</div>
