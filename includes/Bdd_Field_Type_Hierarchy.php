<?php
/**
 * Hierarchy Type
 */
//if (!class_exists('Bdd_User_Field_Hierarchy')) 
if (!class_exists('Bdd_Field_Type_Hierarchy')) 
{
    class Bdd_Field_Type_Hierarchy extends BP_XProfile_Field_Type
    {
        public function __construct() {
            parent::__construct();

            #$this->name = _x( 'Hierarchy (HTML5 field)', 'xprofile field type', 'bxcft' );
            $this->name = 'Hierarchy';

            $this->set_format( '/^.+$/', 'replace' );
            do_action( 'bp_xprofile_field_type_hierarchy', $this );
        }

        public function admin_field_html (array $raw_properties = array ())
        {
            $html = $this->get_edit_field_html_elements( array_merge(
                array( 'type' => 'text' ),
                $raw_properties
            ) );
        ?>
            <input <?php echo $html; ?> readonly>
        <?php
        }

        public function edit_field_html (array $raw_properties = array ())
        {
            if ( isset( $raw_properties['user_id'] ) ) {
                unset( $raw_properties['user_id'] );
            }
            
            // HTML5 required attribute.
            if ( bp_get_the_profile_field_is_required() ) {
                $raw_properties['required'] = 'required';
            }
						$input_name = bp_get_the_profile_field_input_name();

            $html = $this->get_edit_field_html_elements( array_merge(
                array(
                    'type'  => 'text',
                    'value' => bp_get_the_profile_field_edit_value(),
                ),
                $raw_properties
            ) );

        ?>
            <label for="<?php echo $input_name; ?>"><?php echo bp_the_profile_field_name() . ( bp_get_the_profile_field_is_required() ? esc_html_e( '(required)', 'buddypress' ) : ''); ?></label>
            <?php do_action( bp_get_the_profile_field_errors_action() ); ?>
            <input id="textHierarchy" <?php echo $html; ?> readonly>
						<input type="button" id="openHierarchy" value="지역(선거구) 선택" class="btn" style="height: 40px;">

<div id="layerHierarchy">
	<div class="selectHierarchy popup-list valign-outer" style="display:block">
		<div class="valign-middle contents">
			<div class="center-block">
				<ul class="nav nav-tabs">
					<li class="tab1"><a href="#">선거구 선택</a></li>
				</ul>
				<div id="divHierarchy" >
				</div>
				<span id="submitHierarchy" class="btn btn-default">SUBMIT</span>
				<span id="closeHierarchy" class="btn btn-default">CLOSE</span>
			</div>
		</div>
	</div>
</div>

       <?php
        }
        
        public function admin_new_field_html( BP_XProfile_Field $current_field, $control_type = '' ) {}

    }
}
