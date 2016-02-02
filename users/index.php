<?php
require_once '../../../kpnvly/wp-load.php';
include_once '../inc/class.sqlidb.php';
require '../inc/session_mgr.php';

$mySQLi = new MySQLi_DB($_sqli_conf);
$users_table = 'wb-users';
$kp_session = (isset($_COOKIE['nvsa_session']) ?  (array)json_decode(base64_decode($_COOKIE['nvsa_session'])) : NULL);

function giw_hdr_title($data){
    // where $data would be string(#) "current title"
    // Example:
    // (you would want to change $post->ID to however you are getting the book order #,
    // but you can see how it works this way with global $post;)
    return 'User Management | GI Whiteboard | ';
}
add_filter('wp_title','giw_hdr_title');

function get_groups($nuid){
	return '<tbody><tr><td colspan="4">(No groups found)</td></tr></tbody>';
}

require_once '../inc/header.php';

?>
<div class="col-md-12 col-lg-2"></div>
<div id="MainContent" role="main" class="col-md-12 col-lg-8">
    <div id="LocalBreadcrumbs">
        <div id="LocalCrumbs"><a href="/">NVSA Homepage</a>  »  <a href="/utility/gi-whiteboard/">GI Whiteboard</a>   »  User Management <span class="login" style="float:right"><?php echo get_login_link(); ?></span></div>
	</div>
	<div class="Clear clearfix"></div>
    <article id="post-gi-whiteboard-users" class="page type-page status-publish hentry bg-layout">
        <header>
           <div class="Clear"></div>
        <h1 class="entry-title">User Management</h1>
        </header>
        <div id="toolbar" class="toolbar" style="font-size:.75em;border-bottom:1px silver solid; <?php echo (!isset($kp_session) || ($kp_session['user_access']*1) < 10 ? ' display:none;' : ''); ?>" >
            <input type="hidden" name="access_level" id="access_level" value="<?php if (isset($kp_session)) $access = $kp_session['user_access']*1; echo $access;?>" />
            <input type="hidden" name="user_id" id="user_id" value="<?php echo (isset($kp_session) ? $kp_session['ID'] : ''); ?>" />
        
        <input type="hidden" name="session_id" id="session_id" value="<?php echo (isset($kp_session) && isset($_COOKIE['PHPSESSID']) ? substr($_COOKIE['PHPSESSID'],7) : ''); ?>" />
            <div style="text-align:center;float:left;padding:5px 2px;">
            <input type="image" name="add_user" id="add_user" title="Add user" src="/utility/gi-whiteboard/img/btn-add-user.png" style="padding: 0 8px" />
            </div>
            <div style="text-align:center;float:left;padding:5px 2px;">
            <input type="image" name="edit_user" id="edit_user" title="Edit user" src="/utility/gi-whiteboard/img/btn-edit-user.png" style="padding: 0 8px" />
            </div>
            <div style="text-align:center;float: left;padding:5px 2px;">
            <input type="image" name="delete_user" id="delete_user" title="Delete user" src="/utility/gi-whiteboard/img/btn-delete-user.png" style="padding: 0 8px" />
            </div>
            <div class="Clear"></div>
        </div>
        <div class="Clear" style="padding-bottom:1em;"></div>
        <div id="MainTable" class="entry-content">
            <div id="waiting"><h3>Retrieving data...</h3></div>
	        <div id="table-container" style="width:99%;">
                <table id="MainDisplay" class="display responsive nowrap" style="display:none">
                <!-- Table body is created and inserted via ajax, from giw_user_functions.js -->
                <thead>
                <th width="15%">NUID</th>
                <th width="85%">Name</th>
                </thead>
                <tfoot>
                <th width="15%">NUID</th>
                <th width="85%">Name</th>
                </tfoot>
               </table>
			</div>
        </div>
        <div style="padding-bottom:10em;" class="Clear"></div>
    </article>
    <div class="Clear"></div>
</div>
<div class="input_form" title="Add user" style="display:none">
<?php $nuid = (isset($_POST['nuid']) && $_POST['nuid']!=='') ? $_POST['nuid'] : NULL; ?>
	<form id="wb-add-entry" name="wb-add-entry" method="POST">
    <p id="validateAddTips" class="dialog_message">* Required fields</p>
    <p>
       <label for="nuid">NUID *:</label><input name="nuid" type="text" required="required" id="nuid" placeholder="[NUID]" tabindex="1" size="10" maxlength="12" />&nbsp;&nbsp;</p>
    <p><strong>Name</strong><br />
       <label for="firstname">First *:</label><input name="firstname" type="text" required="required" id="firstname" placeholder="[First Name]" tabindex="2" title="firstname" size="20" maxlength="100" />
       &nbsp;&nbsp;<label for="lastname">Last *:</label>
       <input name="lastname" type="text" required="required" id="lastname" placeholder="[Last Name]" tabindex="2" title="lastname" size="30" maxlength="100" />
 </p>
   <p>
      <label for="email">Contact Email:</label><input name="email" type="text" id="email" placeholder="[Contact email]" tabindex="3" size="60" maxlength="60" /></p>
   <label for="notes">Groups:</label><span style="margin-left:20px;"><input type="image" name="groups-help" src="../img/btn-help-topic.png" height="20" width="20" title="About groups" /></span><br />
   <div class="Clear" style="margin-bottom:10px;"></div>
   <div class="text-list" id="groups">
   	( No groups found )
   </div>
   <div class="Clear" style="margin-bottom:10px;"></div>
   </form>
</div>
<div class="input_form" title="Edit user" style="display:none">
	<form id="wb-edit-entry" name="wb-edit-entry" method="POST">
    <p id="validateEditTips" class="dialog_message">* Required fields</p>
    <p>
       <label for="nuid">NUID*:</label><input name="nuid" type="text" required="required" id="nuid" placeholder="[NUID]" tabindex="1" size="10" maxlength="12" />&nbsp;&nbsp;   </p>
    <p><strong>Name</strong><br />
       <label for="firstname">First *:</label><input name="firstname" type="text" required="required" id="firstname" placeholder="[First Name]" tabindex="2" title="firstname" size="20" maxlength="100" />
       &nbsp;&nbsp;<label for="lastname">Last *:</label>
       <input name="lastname" type="text" required="required" id="lastname" placeholder="[Last Name]" tabindex="2" title="lastname" size="30" maxlength="100" />
    </p>
   <p>
      <label for="email">Contact Email:</label><input name="email" type="text" id="email" placeholder="[Contact email]" tabindex="3" size="60" maxlength="60" /></p>
   <label for="notes">Groups:</label><span style="margin-left:20px;"><input type="image" name="groups-help" src="../img/btn-help-topic.png" height="20" width="20" title="About groups" /></span><br />
   <div class="Clear" style="margin-bottom:10px;"></div>
   <div class="text-list" id="groups">
   	( No groups found )
   </div>
   <div class="Clear" style="margin-bottom:10px;"></div>
   </form>
</div>
<div id="dialog_confirm" class="dialog dialog_message" title="Please confirm" style="display:none">
	<p id="dlgMsg">This is a generic dialog</p>
</div>
<?php include_once '../inc/footer.php' ?>
