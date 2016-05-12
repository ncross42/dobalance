<?php
/* admin/main.php
	register_setting( DOBslug.'_options_upin', 'dob_use_upin'     , 'trim' );
	register_setting( DOBslug.'_options_upin', 'dob_upin_type'    , 'trim' );
	register_setting( DOBslug.'_options_upin', 'dob_upin_cpid'    , 'trim' );
	register_setting( DOBslug.'_options_upin', 'dob_upin_keyfile' , 'trim' );
	register_setting( DOBslug.'_options_upin', 'dob_upin_logpath' , 'trim' );
 */

function upin_error($val) {/*{{{*/
	file_put_contents('/tmp/'.date('His').'.upin_ajax.err', 
		date('### Y-m-d H:i:s ###').PHP_EOL.print_r( $val, true ) 
	);
	exit;
}/*}}}*/

function upin_log($ext,$val) {/*{{{*/
	file_put_contents('/tmp/'.date('His').'.upin_ajax.'.$ext, print_r( $val, true ) );
}/*}}}*/

function dob_upin_get_options() {/*{{{*/
	$cpid = get_option('dob_upin_cpid');	// 회원사코드
	$keyfile = get_option('dob_upin_keyfile');	// '/okname/okname.key';  // 운영 키파일
	$logpath = get_option('dob_upin_logpath');	// '/okname/log';         // 로그경로
	if ( empty($cpid) || empty($keyfile) || empty($logpath) ) {
		exit( "Failed to initialize
			upin_cpid:$cpid, upin_keyfile:$keyfile, upin_logpath:$logpath" 
		);
	}
	return array ( $cpid, $keyfile, $logpath );
}/*}}}*/

add_action( 'wp_ajax_upin_kcb1', 'upin_kcb1' );
add_action( 'wp_ajax_nopriv_upin_kcb1', 'upin_kcb1' );
function upin_kcb1() {	// kcb-ipin cert & login/*{{{*/
	list( $cpCode, $keyfile, $logpath ) = dob_upin_get_options();

	// 암호화키 파일 절대경로 (웹서버 권한필요) - 매월마다 갱신됨.
	$r1 = $r2 = '0';		// reserved1, reserved2
	$servUrl = 'http://www.ok-name.co.kr/KcbWebService/OkNameService'; // 운영 서버
	$options = 'CUL';		// options. 'L'값이 로그생성.
	$cmd = array($keyfile, $cpCode, $r1, $r2, $servUrl, $logpath, $options); // 명령어
	$output = NULL;
	$ret = okname($cmd, $output);		// 실행
	if ($ret) {
		$retcode = sprintf( ($ret<=200?'B':'S').'%03d', $ret );
		exit("<script>alert('$retcode'); self.close(); </script>");
	}

	//성공일 경우 인증값을 output에서 얻음
	list($pubkey, $sig, $timestamp) = explode("\n", $output);
	$returnUrl  = admin_url('admin-ajax.php?action=upin_kcb2'); // 인증을 마치고 돌아올 주소.
	$moduleType = '3';				// 서버에 설치된 okname모듈의 유형 (1:exe, 2:com+, 3:php_ext, 4:jni)

	echo <<<HTML
<html>
<head> <meta http-equiv="Content-Type" content="text/html; charset=utf-8"> </head>
<body>
	<form name="kcbInForm" method="post" target="_self" action="https://ipin.ok-name.co.kr/tis/ti/POTI01A_LoginRP.jsp">
		<input type="hidden" name="CPCODE"       value="$cpCode"/>
		<input type="hidden" name="WEBPUBKEY"    value="$pubkey"/>
		<input type="hidden" name="WEBSIGNATURE" value="$sig"/>
		<input type="hidden" name="CPREQUESTNUM" value="$timestamp"/>
		<input type="hidden" name="IDPCODE"      value="V"/> <!-- 고정값. KCB기관코드 -->
		<input type="hidden" name="IDPURL"       value="https://ipin.ok-name.co.kr/tis/ti/POTI90B_SendCertInfo.jsp"/>
		<input type="hidden" name="RETURNURL"    value="$returnUrl"/>
		<input type="hidden" name="MODULETYPE"   value="$moduleType"/>
	</form>
</body>
<script language="JavaScript">
	document.kcbInForm.submit();
</script>
</html>
HTML;
	wp_die();
}/*}}}*/

add_action( 'wp_ajax_upin_kcb2', 'ajax_upin_kcb2' );
add_action( 'wp_ajax_nopriv_upin_kcb2', 'ajax_upin_kcb2' );
function ajax_upin_kcb2() { // kcb-ipin cert post-process /*{{{*/
	list( $cpCode, $keyfile, $logpath ) = dob_upin_get_options();
	
	$req_headers = apache_request_headers();
	//upin_log('header',$req_headers);
	//upin_log('server',$_SERVER);

	//upin_log('post',$_POST);	// tc, encPsnlInfo, IDENTIFYDATA, WEBPUBKEY, WEBSIGNATURE
	@$encPsnlInfo = $_POST['encPsnlInfo']; //아이핀팝업에서 조회한 PERSONALINFO
	@$WEBPUBKEY = trim($_POST['WEBPUBKEY']); //KCB서버 공개키
	@$WEBSIGNATURE = trim($_POST['WEBSIGNATURE']); //KCB서버 서명값

	//파라미터에 대한 유효성여부를 검증한다.
	if(preg_match('~[^0-9a-zA-Z+/=]~', $encPsnlInfo, $match)) upin_error("encPsnlInfo 입력 값 확인이 필요합니다\n$encPsnlInfo");
	if(preg_match('~[^0-9a-zA-Z+/=]~', $WEBPUBKEY, $match)) upin_error("WEBPUBKEY 입력 값 확인이 필요합니다\n$WEBPUBKEY");
	if(preg_match('~[^0-9a-zA-Z+/=]~', $WEBSIGNATURE, $match)) upin_error("WEBSIGNATURE 입력 값 확인이 필요합니다\n$WEBSIGNATURE");
  
	// decrypt
	$servURL = 'http://www.ok-name.co.kr/KcbWebService/OkNameService';// 운영 서버
	$options = 'SUL';
	$cmd = array($keyfile, $cpCode, $servURL, $WEBPUBKEY, $WEBSIGNATURE, $encPsnlInfo, $logpath, $options);
	$output = NULL;
	$ret = okname($cmd, $output);
	//upin_log('output',$output);
	if ($ret) upin_error(sprintf( ($ret<=200?'B':'S').'%03d', $ret ));

	$keys = array ( 
		/*'encPsnlInfo',*/ 'dupinfo', 'coinfo1', 'coinfo2', 'ciupdate', 'virtualno', 'cpcode',
	  'realname', 'cprequestnumber', 'age', 'sex', 'nationalinfo', 'birthdate', 'authinfo'	
	);
	$upin_info = array();
	$result = explode("\n", $output);
	for ( $i=0; $i < count($keys); ++$i ) {
		$upin_info[$keys[$i]] = $result[$i];
	}
	$_SESSION['upin_info'] = $upin_info;
	upin_log('info',$upin_info);

	$form = $_SESSION['upin_form'];
	$html_toggle = '';
	if ( $form == 'registerform' ) {
		//$html_toggle = "opener.document.forms.$form.btn_upin.disabled = true;";
	} elseif ( $form == 'formCart' ) {
		$html_toggle = "opener.document.forms.$form.submit.disabled = false;";
	}

	$label_check_ok = 'IPIN 인증완료';	//__('Successfully Certified', DOBslug);
	echo <<<HTML
<script type="text/javascript">
opener.document.forms.$form.upin_cert.value = '1';
opener.document.forms.$form.btn_upin.value = '$label_check_ok';
opener.document.forms.$form.btn_upin.disabled = true;
$html_toggle
self.close();
</script>
HTML;
	wp_die();
}/*}}}*/

//1. Add a new form element...
add_action( 'register_form', 'upin_register_form' );
function upin_register_form() {
	if ( ! function_exists('okname') ) return;

	$_SESSION['upin_form'] = 'registerform';
	wp_enqueue_script('jquery');
	$label_check = 'IPIN 인증';		//__('Check UPIN', DOBslug);
	$ajax_url = admin_url('admin-ajax.php?action=upin_kcb1');
?>
	<p>
		<label for="upin_cert"><?=$label_check?></label>
		<input type="hidden" name="upin_cert" value="">
		<input type="button" name="btn_upin" id="btn_upin" class="input" value="<?=$label_check?>" onClick="onClick_btn_upin()"/>
	</p>
<script type="text/javascript">
	function onClick_btn_upin() {
		var popupWindow = window.open("<?=$ajax_url?>", "kcbPop", "left=200, top=100, status=0, width=450, height=550");
		popupWindow.focus();
	}
</script>

<?php
}

//2. Add validation. In this case, we make sure upin_cert is required.
add_filter( 'registration_errors', 'upin_registration_errors', 10, 3 );
function upin_registration_errors( $errors, $sanitized_user_login, $user_email ) {
	global $wpdb;

	// check $_POST['upin_cert'] and $_SESSION['upin_info']['coinfo1']
	$upin_ci = empty($_SESSION['upin_info']['coinfo1']) ? '' : $_SESSION['upin_info']['coinfo1'];
	if ( empty($_POST['upin_cert']) || empty($_SESSION['upin_info'])
		|| 88 != strlen($upin_ci)
		|| preg_match('~[^0-9a-zA-Z+/=]~', $upin_ci)
	) {
		$label_error = 'IPIN 인증을 실행해 주세요';	// __( 'You must be certified by UPIN.', DOBslug );
		$errors->add( 'upin_error', '<strong>ERROR</strong>: '.$label_error );
	}

	// check if user is already registered
	$t_upin = $wpdb->prefix.'dob_upin';
	$t_users = $wpdb->prefix.'users';
	$sql = "SELECT user_login, user_email
		FROM $t_upin JOIN $t_users ON user_id=ID
		WHERE ci = '$upin_ci'";
	$row = $wpdb->get_row($sql,ARRAY_A);
	if ( ! empty($row) ) {
		$label_error2 = '이미 등록되어 있습니다';	// __( 'You must be certified by UPIN.', DOBslug );
		$label_error2 .= "\n<br> &gt; login_id : {$row['user_login']} \n<br> &gt; email : {$row['user_email']}";
		$errors->add( 'upin_error2', '<strong>ERROR</strong>: '.$label_error2 );
	}

	return $errors;
}

//3. Finally, save our extra registration user meta.
add_action( 'user_register', 'upin_user_register' );
function upin_user_register( $user_id ) {
	global $wpdb;
	if ( ! empty($_SESSION['upin_info']) ) {
		extract($_SESSION['upin_info']);
		$t_upin = $wpdb->prefix.'dob_upin';
		$sql = <<<SQL
INSERT INTO $t_upin ( user_id, ci, realname, age, sex, nationalinfo, birthdate, authinfo )
VALUES ( '$user_id', '$coinfo1', '$realname', '$age', '$sex', '$nationalinfo', '$birthdate', '$authinfo' )
SQL;
		$success = $wpdb->query( $sql );  // success == 1 (affected_rows)
		if ( empty($success) ) { // failed (duplicated)
			upin_error("upin_user_register\n$sql");
		}
	}
}
