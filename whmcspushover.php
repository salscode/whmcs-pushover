<?php
if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

function whmcspushover_config() {
    $configarray = array(
    "name" => "WHMCS Pushover",
    "description" => "Send WHMCS notifications via Pushover",
    "version" => "1.0",
    "author" => "Sal Sodano salscode.com",
    "language" => "english",
    "fields" => array(
        "app_token" => array ("FriendlyName" => "Pushover Application Token", "Type" => "text", "Size" => "50", "Description" => "", "Default" => "")
    ));
    return $configarray;
}


function whmcspushover_activate() {
  $query = "CREATE TABLE IF NOT EXISTS `mod_whmcspushover` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `adminid` int(11) NOT NULL,
    `access_token` varchar(255) NOT NULL,
    `permissions` text NOT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
	$result = mysql_query($query);
}

function whmcspushover_deactivate() {
	$query = "DROP TABLE `mod_whmcspushover`";
	$result = mysql_query($query);
}

function whmcspushover_output($vars) {
	if(isset($_POST) && !empty($_POST['access_token']))
	{
		$user_push = select_query('mod_whmcspushover', '', array('adminid' => $_SESSION['adminid']));
		if(mysql_num_rows($user_push) > 0)
		{
			update_query('mod_whmcspushover',array('permissions' => serialize($_POST['permissions']), 'access_token' => $_POST['access_token']), array('adminid' => $_SESSION['adminid']));
		}
		else
		{
			insert_query("mod_whmcspushover", array("adminid" => $_SESSION['adminid'], "access_token" => $_POST['access_token'], 'permissions' => serialize($_POST['permissions'])) );
		}
	}
	else
	{
		$data = select_query('mod_whmcspushover', '', array('adminid' => $_SESSION['adminid']));
		$data = mysql_fetch_array($data, MYSQL_ASSOC);
		$data['permissions'] = unserialize($data['permissions']);
	}
	
	//===========================
	echo "<p><a href='addonmodules.php?module=whmcspushover&disable=1'>Disable WHMCS Pushover</a></p>";
    
	echo '<form method="POST">
	<table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
		<tr>
			<td class="fieldlabel" width="200px">User key</td>
			<td class="fieldarea"><input type="text" name="access_token" value="'. $data['access_token'] .'" size="60"/></td>
		</tr>
		<tr>
			<td class="fieldlabel" width="200px">Notifications</td>
			<td class="fieldarea">
				<table width="100%">
					<tr>
						<td valign="top">
							<input type="checkbox" name="permissions[new_client]" value="1" id="notifications_new_client" '.($data['permissions']['new_client'] == "1" ? "checked" : "").'> <label for="notifications_new_client">New Clients</label><br>
							<input type="checkbox" name="permissions[new_invoice]" value="1" id="notifications_new_invoice" '.($data['permissions']['new_invoice'] == "1" ? "checked" : "").'> <label for="notifications_new_invoice">Paid Invoices</label><br>
							<input type="checkbox" name="permissions[new_ticket]" value="1" id="notifications_new_ticket" '.($data['permissions']['new_ticket'] == "1" ? "checked" : "").'> <label for="notifications_new_ticket">New Support Ticket</label><br>
							<input type="checkbox" name="permissions[new_update]" value="1" id="notifications_new_update" '.($data['permissions']['new_update'] == "1" ? "checked" : "").'> <label for="notifications_new_update">New Support Ticket Update</label><br>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
  
  <p align="center"><input type="submit" value="Save Changes" class="button"></p></form>
  ';
}

?>