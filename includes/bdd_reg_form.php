<?php
/*
    Name: BuddyPress Xprofile Custom Fields Type
    URI: http://donmik.com/en/buddypress-xprofile-custom-fields-type/
    Description: BuddyPress installation required!! This plugin add custom field types to BuddyPress Xprofile extension. Field types are: Birthdate, Email, Url, Datepicker, ...
    Version: 0.0.1
    Author: donmik
    Author URI: http://donmik.com
*/
if (!class_exists('Bdd_Plugin'))
{
    class Bdd_Plugin
    {
        private $version;
        private $user_id = null;

        public function __construct ()
        {
            $this->version = "0.0.1";

            /** Main hooks **/
            add_action( 'plugins_loaded', array($this, 'bdd_update') );

            /** Admin hooks **/
            add_action( 'admin_init', array($this, 'admin_init') );
            add_action( 'admin_notices', array($this, 'admin_notices') );

            /** Buddypress hook **/
            add_action( 'bp_init', array($this, 'init') );
            #add_action( 'xprofile_data_before_save', array($this, 'bdd_xprofile_data_before_save') );
            #add_action( 'xprofile_data_after_delete', array($this, 'bdd_xprofile_data_after_delete') );

            /** Filters **/
            add_filter( 'bp_xprofile_get_field_types', array($this, 'bdd_get_field_types'), 10, 1 );
            add_filter( 'xprofile_get_field_data', array($this, 'bdd_get_field_data'), 10, 2 );
            add_filter( 'bp_get_the_profile_field_value', array($this, 'bdd_get_field_value'), 10, 3 );
            /** BP Profile Search Filters **/
            add_filter ('bps_field_validation_type', array($this, 'bdd_map'), 10, 2);
            add_filter ('bps_field_html_type', array($this, 'bdd_map'), 10, 2);
            add_filter ('bps_field_criteria_type', array($this, 'bdd_map'), 10, 2);
            add_filter ('bps_field_query_type', array($this, 'bdd_map'), 10, 2);
        }

        public function init()
        {
            /** Includes **/
            require_once( 'Bdd_Field_Type_Address.php' );
            //require_once( 'Bdd_Field_Type_District_Korea.php' );

						if ( bp_is_user_profile_edit() || bp_is_register_page()
							|| ( isset($_GET['page']) && $_GET['page'] == 'bp-profile-edit' )
					 	) {
                #wp_enqueue_script('bdd-modernizr', plugin_dir_url(__FILE__) . 'js/modernizr.js', array(), '2.6.2', false);
                #wp_enqueue_script('bdd-jscolor', plugin_dir_url(__FILE__) . 'js/jscolor/jscolor.js', array(), '1.4.1', true);
								wp_enqueue_script( 'postcode', 'http://dmaps.daum.net/map_js_init/postcode.v2.js', array(), null, true );
                #wp_enqueue_script('district_json', plugin_dir_url(__FILE__) . 'includes/district.json', array('jquery'), null, true);
                #wp_enqueue_script('set_district', plugin_dir_url(__FILE__) . 'includes/set_district.js', array('district_json'), null, true);
            }
        }

        public function admin_init()
        {
            if (is_admin() && get_option('bdd_activated') == 1) {
                // Check if BuddyPress 2.0 is installed.
                $version_bp = 0;
                if (function_exists('is_plugin_active') && is_plugin_active('buddypress/bp-loader.php')) {
                    // BuddyPress loaded.
                    $data = get_file_data(WP_PLUGIN_DIR . '/buddypress/bp-loader.php', array('Version'));
                    if (isset($data) && count($data) > 0 && $data[0] != '') {
                        $version_bp = (float)$data[0];
                    }
                }
                if ($version_bp < 2) {
                    $notices = get_option('bdd_notices');
                    $notices[] = __('BuddyPress Xprofile Custom Fields Type plugin needs <b>BuddyPress 2.0</b>, please install or upgrade BuddyPress.', 'bdd');
                    update_option('bdd_notices', $notices);
                    delete_option('bdd_activated');
                }

                // Enqueue javascript.
                //wp_enqueue_script('bdd-js', plugin_dir_url(__FILE__) . 'js/admin.js', array(), $this->version, true);
            }
        }

        public function admin_notices()
        {
            $notices = get_option('bdd_notices');
            if ($notices) {
                foreach ($notices as $notice)
                {
                    echo "<div class='error'><p>$notice</p></div>";
                }
                delete_option('bdd_notices');
            }
        }

        public function bdd_get_field_types($fields)
        {
            $new_fields = array(
                'Address'           => 'Bdd_Field_Type_Address',
                //'District_Korea'    => 'Bdd_Field_Type_District_Korea',
                //'decimal_number'  => 'Bdd_Field_Type_DecimalNumber',
            );
            $fields = array_merge($fields, $new_fields);

            return $fields;
        }

        public function bdd_get_field_data($value, $field_id)
        {
            $field = new BP_XProfile_Field($field_id);
            $value_to_return = strip_tags($value);
            if ($value_to_return !== '') {
                // Color.
                if ($field->type == 'color') {
                    if (strpos($value_to_return, '#') === false) {
                        $value_to_return = '#'.$value_to_return;
                    }
                } else {
                    // Not stripping tags.
                    $value_to_return = $value;
                }
            }

            return apply_filters('bdd_show_field_value', $value_to_return, $field->type, $field_id, $value);
        }

        public function bdd_get_field_value($value='', $type='', $id='')
        {
            $value_to_return = strip_tags($value);
            if ($value_to_return !== '') {
                // Color.
                if ($type == 'color') {
                    if (strpos($value_to_return, '#') === false) {
                        $value_to_return = '#'.$value_to_return;
                    }
                } else {
                    // Not stripping tags.
                    $value_to_return = $value;
                }
            }

            return apply_filters('bdd_show_field_value', $value_to_return, $type, $id, $value);
        }

/*
        function bdd_xprofile_data_before_save($data)
        {
            global $bp;
            $field_id = $data->field_id;
            $field = new BP_XProfile_Field($field_id);

            if ($field->type == 'image' || $field->type == 'file' && isset($_FILES['field_'.$field_id]))
            {
                $uploads = wp_upload_dir();
                $filesize = round($_FILES['field_'.$field_id]['size'] / (1024 * 1024), 2);

                $data->value = (isset($value))?$value:'';
            }
        }

        public function bdd_xprofile_data_after_delete($data)
        {
            $field_id = $data->field_id;
            //$field = new BP_XProfile_Field($field_id);
        }
*/

        public function bdd_map($field_type, $field)
        {
            switch($field_type) {
                case 'color':
                case 'Address':
                case 'District_Korea':
                    $field_type = 'textbox';
                    break;
                    
                case 'decimal_number':
                    $field_type = 'number';
                    break;
            }

            return $field_type;
        }

        public function bdd_update()
        {
					/*
            $locale = apply_filters( 'bdd_load_load_textdomain_get_locale', get_locale() );
            if ( !empty( $locale ) ) {
                $mofile_default = sprintf( '%slang/%s.mo', plugin_dir_path(__FILE__), $locale );
                $mofile = apply_filters( 'bdd_load_textdomain_mofile', $mofile_default );

                if ( file_exists( $mofile ) ) {
                    load_textdomain( "bdd", $mofile );
                }
            }
					 */
            if (!get_option('bdd_activated')) {
                add_option('bdd_activated', 1);
            }
            if (!get_option('bdd_notices')) {
                add_option('bdd_notices');
            }
        }

        public static function activate()
        {
            add_option('bdd_activated', 1);
            add_option('bdd_notices', array());
        }

        public static function deactivate()
        {
            delete_option('bdd_activated');
            delete_option('bdd_notices');
        }
    }
}

if (class_exists('Bdd_Plugin')) {
    register_activation_hook(__FILE__, array('Bdd_Plugin', 'activate'));
    register_deactivation_hook(__FILE__, array('Bdd_Plugin', 'deactivate'));
    $bdd_plugin = new Bdd_Plugin();
}
