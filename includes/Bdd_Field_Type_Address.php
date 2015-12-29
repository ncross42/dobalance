<?php
/**
 * Address Type
 */
//if (!class_exists('Bdd_User_Field_Address')) 
if (!class_exists('Bdd_Field_Type_Address')) 
{
    class Bdd_Field_Type_Address extends BP_XProfile_Field_Type
    {
        public function __construct() {
            parent::__construct();

            #$this->name = _x( 'Address (HTML5 field)', 'xprofile field type', 'bxcft' );
            $this->name = 'Address (korea)';

            $this->set_format( '/^.+$/', 'replace' );
            do_action( 'bp_xprofile_field_type_address', $this );
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
                    'id'  => $input_name,
                    'type'  => 'text',
                    'value' => bp_get_the_profile_field_edit_value(),
                ),
                $raw_properties
            ) );

        ?>
            <label for="<?php echo $input_name; ?>"><?php echo bp_the_profile_field_name() . ( bp_get_the_profile_field_is_required() ? esc_html_e( '(required)', 'buddypress' ) : ''); ?></label>
            <?php do_action( bp_get_the_profile_field_errors_action() ); ?>
            <input <?php echo $html; ?> readonly>
						<input type="button" id="billing_postcode_search" value="우편번호 찾기" class="btn" onclick="openDaumPostcode();" style="height: 40px;">

    <div id="layerAddress" style="display:none;border:5px solid;position:fixed;width:500px;height:500px;left:50%;margin-left:-250px;top:50%;margin-top:-250px;overflow:hidden">
        <img src="http://i1.daumcdn.net/localimg/localimages/07/postcode/320/close.png" id="btnCloseLayer" style="cursor:pointer;position:absolute;right:-3px;top:-3px" onclick="closeDaumPostcode()">
    </div>

    <script>
        // 우편번호 찾기 화면을 넣을 element
        var element = document.getElementById('layerAddress');

        function closeDaumPostcode() {
            // iframe을 넣은 element를 안보이게 한다.
            element.style.display = 'none';
        }

        function openDaumPostcode() {
            new daum.Postcode({
                oncomplete: function(data) {
                    // 팝업에서 검색결과 항목을 클릭했을때 실행할 코드를 작성하는 부분. 우편번호와 주소 정보를 해당 필드에 넣고, 커서를 상세주소 필드로 이동한다.
                    document.getElementById("<?php echo $input_name; ?>").value = data.address;
                    //document.getElementById("zip1").value = data.postcode1;
                    //document.getElementById("zip2").value = data.postcode2;
                    //document.getElementById("addr1").value = data.address;
                    //document.getElementById("addr2").focus();
                // iframe을 넣은 element를 안보이게 한다.
                    element.style.display = 'none';
                },
                width : '100%',
                height : '100%'
            }).embed(element);

            // iframe을 넣은 element를 보이게 한다.
            element.style.display = 'block';
        }
    </script>

       <?php
        }
        
        public function admin_new_field_html( BP_XProfile_Field $current_field, $control_type = '' ) {}

    }
}
