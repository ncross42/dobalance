<?php
/**
 * Hierarchy Type
 */
//if (!class_exists('Bdd_User_Field_Hierarchy')) 
if (!class_exists('Dob_Field_Hierarchy')) 
{
	class Dob_Field_Hierarchy extends BP_XProfile_Field_Type
	{
		private $root;

		public function __construct() {
			$plugin_options = get_option('dobalance-settings');
			$this->root = $plugin_options['dobalance_root_hierarchy'];

			parent::__construct();

			#$this->name = _x( 'Hierarchy (HTML5 field)', 'xprofile field type', 'bxcft' );
			$this->name = 'Dob_Field_Hierarchy';
			$this->set_format( '/^.+$/', 'replace' );
			do_action( 'bp_xprofile_field_type_hierarchy', $this );
		}

		public function get_categories($bJson = false) {
			global $wpdb;
			$ret = array();

			$sql = "
				SELECT 
					term_taxonomy_id, lvl, slug, name /* CONCAT( REPEAT('\t',lvl), slug ) AS slug_full */
					FROM wp_term_taxonomy JOIN wp_terms USING (term_id) 
					WHERE slug LIKE '{$this->root}%' 
					ORDER BY lft";

			$ret = $wpdb->get_results( $sql/*, ARRAY_A*/ );

			return $bJson ? json_encode($ret,JSON_UNESCAPED_UNICODE) : $ret;
		}

		public function admin_field_html (array $raw_properties = array ())
		{
			$html = $this->get_edit_field_html_elements( $raw_properties );
			/*
			$html = $this->get_edit_field_html_elements( array_merge(
					array( 'type' => 'text' ),
					$raw_properties
			) );
			echo "<input <?php echo $html; ?> readonly>";
			*/
		?>
				<select <?php echo $html; ?> readonly>
						<?php bp_the_profile_field_options(); ?>
				</select>
<?php
		}

		public function edit_field_html (array $raw_properties = array ())
		{
			$user_id = bp_displayed_user_id();

			if ( isset( $raw_properties['user_id'] ) ) {
				$user_id = (int) $raw_properties['user_id'];
				unset( $raw_properties['user_id'] );
			}

			// HTML5 required attribute.
			$required_html = '';
			if ( bp_get_the_profile_field_is_required() ) {
				$raw_properties['required'] = 'required';
				$required_html = __( '(required)', 'buddypress' );
			}
			do_action( bp_get_the_profile_field_errors_action() );

			$input_name = bp_get_the_profile_field_input_name();
			$field_name = bp_get_the_profile_field_name();
			$input_html = $this->get_edit_field_html_elements( $raw_properties );

			$selected  = bp_get_the_profile_field_edit_value();
			$options = '';
			foreach ( $this->get_categories() as $row ) {	// lvl, slug, name 
				$options .= sprintf(
					'<option value="%s" %s>%s</option>',
					$row->term_taxonomy_id,
					($selected==$row->term_taxonomy_id)? 'selected="selected"':'',
					str_repeat('&nbsp;',4*($row->lvl)).$row->name
				);
			}

			echo "
				<label for='$input_name'>".$field_name.$required_html."</label>
				<select $input_html >
					$options
				</select>";
		}

		public function admin_new_field_html( BP_XProfile_Field $current_field, $control_type = '' ) {}

	}
}
