<?php
function whmcspushover_getToken()
{
	$app_token = mysql_fetch_array( select_query('tbladdonmodules', 'value', array('module' => 'whmcspushover', 'setting' => 'app_token') ), MYSQL_ASSOC );
	return $app_token['value'];
}

function getAdminUserPermission($permission)
{
	return full_query("SELECT `access_token` FROM `mod_whmcspushover` WHERE `permissions` LIKE '%". $permission ."%'");
}

// Cleans and prepares the message object.
function whmcspushover_prepMessage(&$message)
{
	global $customadminpath, $CONFIG;
	
	$message['token'] = whmcspushover_getToken();
	if (strlen($message['title']) > 250)
		$message['title'] = substr($message['title'], 0, 247)."...";
	
	// Fixes small bug with HTML
	$message['message'] = " ".$message['message'];
	
	if (strlen($message['message']) > 1024)
		$message['message'] = substr($message['message'], 0, 1021)."...";
	
	if ($message["url"] != "")
		$message["url"] = $CONFIG['SystemURL'].'/'.$customadminpath.$message["url"];
	
	$message["html"] = 1;
}

// Pushes a message to a single admin.
function whmcspushover_sendPushSingle($message, $userAccessToken)
{
	whmcspushover_prepMessage($message);
	$message['user'] = $userAccessToken;
	
	$options = array('CURLOPT_SAFE_UPLOAD' => true);
	curlCall("https://api.pushover.net/1/messages.json", $message, $options);
}

// Pushes a message to all the admins that have the specificed notification set.
function whmcspushover_sendPushAll($message, $permission)
{
	whmcspushover_prepMessage($message);
	
	$administrators = getAdminUserPermission($permission);
	while($usr = mysql_fetch_array( $administrators, MYSQL_ASSOC ))
	{
		$message['user'] = $usr['access_token'];
		
		$options = array('CURLOPT_SAFE_UPLOAD' => true);
		curlCall("https://api.pushover.net/1/messages.json", $message, $options);
	}
}

function hook_whmcspushover_ClientAdd($vars) {
	$message = array(
		'priority' => -1,
		'title' => "New WHMCS Client",
		'message' => "A new client has signed up! {$vars['firstname']} {$vars['lastname']} ({$vars['companyname']})",
		'url' => "/clientssummary.php?userid={$vars['userid']}",
		'url_title' => "View Client #{$vars['userid']}",
	);
	
	whmcspushover_sendPushAll($message, 'new_client');
}

function hook_whmcspushover_AfterFraud($vars) {
	$message = array(
		'priority' => ($vars['isfraud'] ? 2 : 0),
		'title' => "Order Fraud Results",
		'message' => "A new order for {$vars['amount']} has been fraud checked. {$vars['clientdetails']['firstname']} {$vars['clientdetails']['lastname']} ({$vars['clientdetails']['companyname']})",
		'url' => "/clientssummary.php?userid={$vars['clientdetails']['userid']}",
		'url_title' => "View Client #{$vars['clientdetails']['userid']}",
	);
	
	whmcspushover_sendPushAll($message, 'fraud_check');
}

function hook_whmcspushover_InvoicePaid($vars) {
	$message = array(
		'priority' => -1,
		'title' => "Invoice #{$vars['invoiceid']} Paid",
		'message' => "",
		'url' => "/invoices.php?action=edit&id={$vars['invoiceid']}",
		'url_title' => "View Invoice #{$vars['invoiceid']}",
	);
	
	whmcspushover_sendPushAll($message, 'new_invoice');
}

function hook_whmcspushover_TicketOpen($vars) {
	$message = array(
		'priority' => (($vars['priority'] == "High") ? 2 : 0),
		'title' => "{$vars['deptname']}: {$vars['priority']} Ticket #{$vars['ticketid']} Opened",
		'message' => "<b>Subject:</b> {$vars['subject']}<br><b>Message:</b> {$vars['message']}",
		'url' => "/supporttickets.php?action=viewticket&id={$vars['ticketid']}",
		'url_title' => "View Ticket #{$vars['ticketid']}",
	);

	whmcspushover_sendPushAll($message, 'new_ticket');
}

function hook_whmcspushover_TicketUserReply($vars) {
	$message = array(
		'priority' => (($vars['priority'] == "High") ? 1 : 0),
		'title' => "{$vars['deptname']}: {$vars['priority']} Ticket #{$vars['ticketid']} Reply",
		'message' => "<b>Subject:</b> {$vars['subject']}<br><b>Message:</b> {$vars['message']}",
		'url' => "/supporttickets.php?action=viewticket&id={$vars['ticketid']}",
		'url_title' => "View Ticket #{$vars['ticketid']}",
	);
	
	whmcspushover_sendPushAll($message, 'new_update');
}

function widget_whmcspushover($vars) {
	
	if(isset($_POST['action']) && $_POST['action'] == 'sendpush')
	{
		$usr = mysql_fetch_array( select_query('tbladmins', 'username', array('id' => $vars['adminid']) ), MYSQL_ASSOC );
		$message = array(
			'priority' => 0,
			'title' => "Message from {$usr['username']}",
			'message' => $_POST['message'],
		);
		whmcspushover_sendPushSingle($message, $_POST['user']);
	}

    $title = "Send a Push Notification";

    $rs = full_query("SELECT `tbladmins`.`username` as `user`, `mod_whmcspushover`.`access_token` as `token`  FROM `mod_whmcspushover`, `tbladmins` WHERE `tbladmins`.`id` = `mod_whmcspushover`.`adminid`");
    $content = '
    <script>
    function widgetsendpush()
    {
		$.post("index.php", { action: "sendpush", user: $("#id_send_push_user").val(), message: $("#id_message_push").val() });
		$("#send_push_confirm").slideDown().delay(2000).slideUp();
		$("#id_message_push").val("");
	}
    </script>
    <div id="send_push_confirm" style="display:none;margin:0 0 5px 0;padding:5px 20px;background-color:#DBF3BA;font-weight:bold;color:#6A942C;">Push Sent Successfully!</div>
    ';
    $options = "User: <select id='id_send_push_user'>";
    while($u = mysql_fetch_array( $rs, MYSQL_ASSOC ))
	{
		$options .= "<option value='". $u['token']. "'>". $u['user']. "</option>";
	}
	$options .= "</select>";
	
	$content .= $options . "<br/><br/><textarea style='width:95%;height:75px;' id='id_message_push'></textarea><br/><br/>";
	$content .= '<input type="button" value="Send" onclick="widgetsendpush()" />';
    return array('title'=>$title,'content'=> $content);
}

add_hook("ClientAdd",10,"hook_whmcspushover_ClientAdd");
add_hook("AfterFraudCheck",10,"hook_whmcspushover_AfterFraud");
add_hook("InvoicePaid",10,"hook_whmcspushover_InvoicePaid");
add_hook("TicketOpen",10,"hook_whmcspushover_TicketOpen");
add_hook("TicketUserReply",10,"hook_whmcspushover_TicketUserReply");
add_hook("AdminHomeWidgets",10,"widget_whmcspushover");

?>