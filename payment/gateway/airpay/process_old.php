<!-- <html>
	<head>
	<meta charset="utf-8">
	<title>Airpay Payment Gateway</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="">
	<meta name="author" content="">
	<link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,400;0,700;1,600&family=Roboto:wght@300;400;700&display=swap"
		rel="stylesheet">
	<link type="text/css" rel="stylesheet" href="assets/style.css">
	</head>
	<style>
table {
  border-collapse: collapse;
  width: 50%;
}

th, td {
  text-align: left;
  padding: 8px;
}
.tdfail{
	color:red;
}
.tdsuccess{
	color:green;
} 
	 </style>
	<body>
    <div class="wrapper">
		<div class="contentbody">
			<div class="lside">

				<div class="lsidewrap">
					<div class="logo"><img src="assets/airpay-text-wh.svg"></div>
					<div class="coverimg"><img src="assets/coverimg.png"></div>
				</div>
			</div>
         <div class="rside">
				<div class="formwrap container-fluid"> -->
<?php
// date_default_timezone_set('Asia/Kolkata');

// header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
// header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
// header( 'Cache-Control: no-store, no-cache, must-revalidate' );
// header( 'Cache-Control: post-check=0, pre-check=0', false );
// header( 'Pragma: no-cache' );

// This is landing page where you will receive response from airpay. 
// The name of the page should be as per you have configured in airpay system
// All columns are mandatory
global $DB;
$TRANSACTIONID = trim($_POST['TRANSACTIONID']);
$APTRANSACTIONID  = trim($_POST['APTRANSACTIONID']);
$AMOUNT  = trim($_POST['AMOUNT']);
$TRANSACTIONSTATUS  = trim($_POST['TRANSACTIONSTATUS']);
$MESSAGE  = trim($_POST['MESSAGE']);
$ap_SecureHash = trim($_POST['ap_SecureHash']);
echo 'TRANSACTIONID-'.$TRANSACTIONID;
echo 'APTRANSACTIONID-'.$APTRANSACTIONID;
echo 'AMOUNT-'.$AMOUNT;
echo 'TRANSACTIONSTATUS-'.$TRANSACTIONSTATUS;
echo 'MESSAGE-'.$MESSAGE;
echo 'ap_SecureHash-'.$ap_SecureHash;


$CHMOD = "";
if (isset($_POST['CHMOD'])){
	$CHMOD = trim($_POST['CHMOD']);
}
if (isset($_POST['CUSTOMVAR'])){
	$CUSTOMVAR  = trim($_POST['CUSTOMVAR']);	
}
else{
	$CUSTOMVAR  = "";
}

$error_msg = '';
if(empty($TRANSACTIONID) || empty($APTRANSACTIONID) || empty($AMOUNT) || empty($TRANSACTIONSTATUS) || empty($ap_SecureHash)){
// Reponse has been compromised. So treat this transaction as failed.
	if(empty($TRANSACTIONID)){
		 $error_msg = 'TRANSACTIONID '; 
	} 
	if(empty($APTRANSACTIONID)){
		 $error_msg .=  ' APTRANSACTIONID'; 
	}
	if(empty($AMOUNT)){
		 $error_msg .=  ' AMOUNT'; 
	}
	if(empty($TRANSACTIONSTATUS)){
		 $error_msg .=  ' TRANSACTIONSTATUS'; 
	}
	if(empty($ap_SecureHash)){
		 $error_msg .=  ' ap_SecureHash'; 
	}
	$error_msg .= '<tr><td>Variable(s) '. $error_msg.' is/are empty.</td></tr>';
}

//THIS IS ADDITIONAL VALIDATION, YOU MAY USE IT.
//$SYSTEM_AMOUNT is amount you will fetch from your database/system against $TRANSACTIONID
//if( $AMOUNT != $SYSTEM_AMOUNT){
// Reponse has been compromised. So treat this transaction as failed.
//$error_msg .= '<tr><td>Amount mismatch in the system.</td></tr>';
//exit();
//}

// Generating Secure Hash
// $mercid = 	Merchant Id, $username = username
// You will find above two keys on the settings page, which we have defined here in config.php
$username =  '7595201';
$mercid = '21720';
$Hash_data = $TRANSACTIONID.':'.$APTRANSACTIONID.':'.$AMOUNT.':'.$TRANSACTIONSTATUS.':'.$MESSAGE.':'.$mercid.':'.$username;
if($CHMOD == "upi"){
	echo 'upi';
	$Hash_data = $Hash_data.':'.trim($_POST["CUSTOMERVPA"]);
}
$merchant_secure_hash = sprintf("%u", crc32 ($Hash_data));
//comparing Secure Hash with Hash sent by Airpay
if($ap_SecureHash != $merchant_secure_hash){
	echo 'hi';
	// Reponse has been compromised. So treat this transaction as failed.
	$error_msg .= '<tr><td><font color="red">Secure Hash mismatch.</font></td></tr>';
}

if($error_msg){
	echo 'error';
	echo '<table><font color="red"><b>ERROR:</b> '.$error_msg.'</font></table>';
	echo '<table>
			<tr><td><b>Variable Name</b></td><td><b> Value</b></td></tr>
			<tr><td>TRANSACTIONID:</td><td> '.$TRANSACTIONID.'</td></tr>
			<tr><td>APTRANSACTIONID:</td><td> '.$APTRANSACTIONID.'</td></tr>
			<tr><td>AMOUNT:</td><td> '.$AMOUNT.'</td></tr>
			<tr><td>TRANSACTIONSTATUS:</td><td> '.$TRANSACTIONSTATUS.'</td></tr>
			<tr><td>CUSTOMVAR:</td><td> '.$CUSTOMVAR.'</td></tr>
		</table>';

	//exit();
}//if($error_msg)


if($TRANSACTIONSTATUS == 200){
	// Process Successfull transaction
	$order = $DB->get_record('paygw_airpay', ['ap_orderid' => "'.$TRANSACTIONID.'"]);
	print_r($order);exit;
	// Updating order after successfull transaction.
	$order->paymentid = $TRANSACTIONID;
	$order->payment = $AMOUNT;
	$DB->update_record('paygw_airpay', $order);

	//Checking cart history for payment and enrolling user.
	$cart_history_items = $DB->get_records('local_biz_cart_history',['identifier' => $order->itemid, 'userid' => $order->userid]);
	$enrolplugin = enrol_get_plugin('manual');
	$role = $DB->get_record('role', array('shortname' => 'student'), '*', MUST_EXIST);
	
	foreach($cart_history_items as $cartitem){

		// Get enrol instance for course.	
    	$instance = $DB->get_record('enrol', array('roleid'=>$role->id, 'courseid'=>$cartitem->itemid, 'enrol'=> 'manual'));

    	// Enrol user.
    	$enrolplugin->enrol_user($instance, $cartitem->userid, $role->id);

	}

	echo '<table><font color="green"><tr><td class="tdsuccess"><b>SUCCESS TRANSACTION</b></td></tr></font></table>
		<table>
			<tr><td><b>Variable Name</b></td><td><b>Value</b></td></tr>
			<tr><td>TRANSACTIONID:</td><td> '.$TRANSACTIONID.'</td></tr>
			<tr><td>APTRANSACTIONID:</td><td> '.$APTRANSACTIONID.'</td></tr>
			<tr><td>AMOUNT:</td><td> '.$AMOUNT.'</td></tr>
			<tr><td>TRANSACTIONSTATUS:</td><td> '.$TRANSACTIONSTATUS.'</td></tr>
			<tr><td>MESSAGE:</td><td> '.$MESSAGE.'</td></tr>
			<tr><td>CUSTOMVAR:</td><td> '.$CUSTOMVAR.'</td></tr>
		</table>';
}else{
		// Process Failed Transaction
		echo '<table><font color="red"><tr><td class="tdfail"><b>FAILED TRANSACTION</b></td></tr></font></table>
		<table>
			<tr><td><b>Variable Name</b></td><td><b>Value</b></td></tr>
			<tr><td>TRANSACTIONID:</td><td> '.$TRANSACTIONID.'</td></tr>
			<tr><td>APTRANSACTIONID:</td><td> '.$APTRANSACTIONID.'</td></tr>
			<tr><td>AMOUNT:</td><td> '.$AMOUNT.'</td></tr>
			<tr><td>TRANSACTIONSTATUS:</td><td> '.$TRANSACTIONSTATUS.'</td></tr>
			<tr><td>MESSAGE:</td><td> '.$MESSAGE.'</td></tr>
			<tr><td>CUSTOMVAR:</td><td> '.$CUSTOMVAR.'</td></tr>
		</table>';
}


?>
 </div>
         </div>
      </div>
   </div>
</body>
</html>