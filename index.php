<?php

require '../../kpnvly/wp-load.php';
include_once 'inc/class.sqlidb.php';
require('inc/session_mgr.php');

$mySQLi = new MySQLi_DB($_sqli_conf);

$users = 'wb-users';
$user_meta = 'wb-user-meta';
$group_table = 'wb-groups';
$group_info = array();
$appt_types = 'wb-appointment-types';
$kp_session = (isset($_COOKIE['nvsa_session']) ?  (array) json_decode(base64_decode($_COOKIE['nvsa_session'])) : NULL);

function giw_hdr_title($data){
    // where $data would be string(#) "current title"
    // Example:
    // (you would want to change $post->ID to however you are getting the book order #,
    // but you can see how it works this way with global $post;)
    return 'GI Whiteboard | ';
}
add_filter('wp_title','giw_hdr_title');

function get_groups(){
	global $mySQLi, $group_table;	
	$sql = "SELECT * FROM `$group_table`;";

	$groups = $mySQLi->get_results($sql);

	foreach($groups as $row){
		$out[$row['name']] = $row['level'];	
	}
	return $out;
}
$group_info = get_groups();

function get_providers(){
	global $mySQLi, $user_meta, $users, $group_info;
	
	$sql = "SELECT * FROM `$user_meta` WHERE `meta_key`='groups';";
	$meta = $mySQLi->get_results($sql);
	$out = '';
	foreach($meta as $row){
		$user_groups = unserialize($row['meta_value']);
		if (in_array('Providers',$user_groups)) $uid[] = $row['user_id'];
	} // loop through each user_meta
	
	if (isset($uid)){
		$ids = implode(',',$uid);
		$sql = "SELECT id,CONCAT(`lastname`,', ',`firstname`) name FROM `$users` WHERE id IN ($ids);";
		
		$providers = $mySQLi->get_results($sql);
		if ($providers !== false && $mySQLi->error == ''){
			$out = '';
			foreach($providers as $row){
				$out .= '<option value="'.$row['id'].'">'.$row['name'].'</option>';
			} // loop foreach
			return $out;
		}else{
			return '<option>'.$sql.'</option>';
		} // end if got providers without error
	} else {
		return '<option value="'.($mySQLi->error==''?'':$mySQLi->error_num.":".$mySQLi->error).'" sql="'.$sql.'">[No providers configured]</option>';
	}

} // end function get_providers
function get_appt_types(){
	global $mySQLi,$appt_types;
	
	$sql = "SELECT * FROM `$appt_types` ORDER BY name;";
	$appt = $mySQLi->get_results($sql);
	
	if ($appt !== false && $mySQLi->error == ''){
		$out = '';
		foreach($appt as $row){
			$out .= '<option value="'.$row['id'].'">'.$row['name'].'</option>';	
		} // loop foreach appt
		return $out;
	} // end if appt found without error
	
	return '<option value="'.($mySQLi->error==''?'':$mySQLi->error_num.":".$mySQLi->error).'">(No appt types configured)</option>';
	
}
require_once 'inc/header.php';
$browser_info = $_SERVER['HTTP_USER_AGENT'];
$access = '';

if (stristr($browser_info,'MSIE')!==false){
	echo '<!--// Browser info:  '.$browser_info.' //-->';
}
?>
		<!-- kp_session var dump
        <?php var_dump($kp_session);?>
        -->
<div class="col-md-12 col-lg-2"></div>
<div id="MainContent" role="main" class="col-md-12 col-lg-8">
    <div id="LocalBreadcrumbs">          
        <div id="LocalCrumbs" ><a href="/">NVSA Homepage</a>  »  GI Whiteboard <span class="login" style="float:right"><?php echo get_login_link(); ?></span></div>
	</div>
	<div class="Clear clearfix"></div>
    <article id="post-gi-whiteboard" class="page type-page status-publish hentry bg-layout">
        <header>
        <h1 class="entry-title">GI Whiteboard</h1>
        <div class="Clear"></div>
        </header>
        
        <input type="hidden" name="access_level" id="access_level" value="<?php if (isset($kp_session)) $access = $kp_session['user_access']*1; echo $access;?>" />
        <input type="hidden" name="user_id" id="user_id" value="<?php echo (isset($kp_session) ? $kp_session['ID'] : ''); ?>" />
        <input type="hidden" name="session_id" id="session_id" value="<?php echo (isset($kp_session) && isset($_COOKIE['PHPSESSID']) ? substr($_COOKIE['PHPSESSID'],7) : ''); ?>" />
        <!-- kp_session var dump
        <?php var_dump($kp_session);?>
        -->
         
        <input type="hidden" name="fullname" id="fullname" value="<?php echo (isset($kp_session) ? $kp_session['fullname'] : ''); ?>" />
       <div id="toolbar" class="toolbar" <?php echo (isset($kp_session) ? '' : ' style="display:none" '); ?>>
           <input name="add_item" type="image" id="add_item" style="height:20px;" title="Add entry" value="New entry" src="img/btn-new-document.png" alt="Button: Add entry" />
           <input name="update_item" type="image" id="update_item" style="height:20px;" title="Edit entry" value="Edit entry" src="img/btn-edit-document.png" alt="Button: Edit entry" />
           <input name="book_item" id="book_item" type="image" style="height:20px" title="Mark booked" src="img/btn-mark-booked.png" alt="Button: Mark entry booked" value="Entry booked" />
           <input name="delete_item" type="image" class="admin_only" id="delete_item" title="Delete entry" value="Delete entry" src="img/btn-delete-document.png" alt="Button: Delete entry" width="20" height="20" />
       	  <span class="admin_only" style="float:right"><input type="image" src="img/btn-manage-users.png" width="25" height="25" id="manage_users" name="manage_users" title="Manage Users" /></span>
        </div>
        <div id="MainTable" class="entry-content">
	        <div id="waiting"><h3>Retrieving data...</h3></div>
            
            <script type="text/javascript">
				if (document.documentMode == 7) {
					var browserMsg = '<h1 title="'+<?php echo "'".$browser_info."'";?>+'">Internet explorer must be at least Version 8.0, with &ldquo;Document Mode&rdquo; set to IE8 or higher</h1>';
					browserMsg += '<p style="margin-top:-1.25em;margin-bottom:1.5em;">Press &lt;F12&gt; to open <strong>Developer Tools</strong>. Set both "Browser Mode"(&lt;Alt&gt;+&lt;B&gt;) and "Document Mode"(&lt;Alt&gt;+&lt;M&gt;) to Internet Explorer 8 (or 9, 10, etc)</p>';
					jQuery('#waiting h3').replaceWith(browserMsg);
				}
            </script>
            <div id="table-container">
                <table id="MainDisplay" cellspacing="0" class="display compact nowrap" style="display:none">
                <?php // echo get_table_data(); ?>
                <thead>
                <th width="10%">Date</th>
                <th width="15%">Provider</th><th width="15%">Location</th><th width="10%">AM/PM</th><th width="10%" title="Appointment Type">Appt Type</th><?php if ($access > 0){ ?><th width="10%">Booked?</th><?php };?><th class="all" width="30%">Notes</th><th></th></thead>
                <tfoot>
                <th width="10%">Date</th>
                <th width="15%">Provider</th><th width="15%">Location</th><th width="10%">AM/PM</th><th width="10%" title="Appointment Type">Appt Type</th><?php if ($access > 0){ ?><th width="10%">Booked?</th><?php };?><th class="all" width="30%">Notes</th><th></th></tfoot>
               </table>
           </div>
        </div>
        <div style="padding-bottom:10em;" class="Clear"></div>
    </article>
    <div class="Clear"></div>
</div>
<div id="input_form" title="Create whiteboard entry" style="display:none">
	<form id="wb-add-entry" name="wb-add-entry" method="POST">
    <p class="validateAddTips">*All fields required, except Notes field</p>
	<p><label for="date">Date:</label><input class="datepicker" type="text" name="appt_date" maxlength="10" />&nbsp;&nbsp;
   <label >Preferred time:</label>&nbsp;&nbsp;<label for="am">AM<input type="radio" id="am" name="ampm" value="AM" /></label><label for="pm">PM<input type="radio" value="PM" name="ampm"  /></label></p>
   <p><label for="provider">Provider:</label><select id="provider" name="provider">
   	<option value="" selected="selected">[Select a Provider]</option>
   <?php echo get_providers(); ?>
   </select>&nbsp;&nbsp;
   <label for="location">Location:</label><select id="location" name="location">
   <option value="" selected="selected">[Select a location]</option>
   <option value="Sacramento">Sacramento, Morse</option>
   <option value="Roseville">Roseville, Eureka</option></select></p>
   <p><label for="appt_type">Appointment Type:</label><select id="appt_type" name="appt_type">
   <option value="">[Select one]</option>
   <?php echo get_appt_types(); ?>
   </select></p>
   <label for="notes">Notes (optional):</label><br />
   <div class="Clear" style="margin-bottom:10px;"></div>
   <textarea name="notes" id="notes" cols="65" rows="5"></textarea>
   </form>
</div>
<div id="update_form" title="Update whiteboard entry" style='display:none'>
	<form id="wb-update-entry" name="wb-update-entry" method="POST">
    <p class="validateUpdateTips">*All fields required, except Notes field</p>
	<p><label for="date">Date:</label><input class="datepicker" type="text" name="appt_date" maxlength="10" />&nbsp;&nbsp;
   <label >Preferred time:</label>&nbsp;&nbsp;<label for="am">AM<input type="radio" id="am" name="ampm" value="AM" /></label><label for="pm">PM<input type="radio" value="PM" name="ampm"  /></label></p>
   <p><label for="provider">Provider:</label><select id="provider" name="provider">
   	<option value="" selected="selected">[Select a Provider]</option>
   <?php echo get_providers(); ?>
   </select>&nbsp;&nbsp;
   <label for="location">Location:</label><select id="location" name="location">
   <option value="" selected="selected">[Select a location]</option>
   <option value="Sacramento">Sacramento, Morse</option>
   <option value="Roseville">Roseville, Eureka</option></select></p>
   <p><label for="appt_type">Appointment Type:</label><select id="appt_type" name="appt_type">
   <option value="">[Select one]</option>
   <?php echo get_appt_types(); ?>
   </select></p>
   <label for="notes">Notes (optional):</label><br />
   <div class="Clear" style="margin-bottom:10px;"></div>
   <textarea name="notes" id="notes" cols="65" rows="5"></textarea>
   </form>
</div>
<div id="generic-dialog" title="Basic dialog" style="display:none">
  <p>This is the default dialog which is useful for displaying information.</p>
</div>
<?php include_once "inc/footer.php"; ?>
