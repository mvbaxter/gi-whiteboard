<?php
get_footer();
?>
<!-- DIVs used for dialog presentations -->
<div id="logging" title="Login" style="display:none">
	<img src="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/utility/gi-whiteboard/img/windows-logo.png';?>" style="height: 30px; float:left;" />
    <p class="validateTips">Please enter your NUID and Password just like you do when logging into your computer.</p>
    <form name="user_login" id="user_login" style="padding:5px 20px;">
    	<fieldset style="border:none !important;">
            <label for="name">NUID: </label><br /><input name="nuid" id="nuid" type="text" /><br /><br />
            <label for="pwd">Password: </label><br /><input name="pwd" id="pwd" type="password" value="" /><br />
       </fieldset>
       <!-- Allow form submission with keyboard without duplicating the dialog button -->
       <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
    </form>
</div>
<div id="logging_out" title="Logged out" style="display:none">
	<p class="ui-state-highlight" style="margin:20px 10px;font-size:1.1em;font-weight:bold; padding:1.1em 1em;"><span class="ui-widget-content ui-icon ui-icon-locked" style="float:left; margin:0 5px 5px 10px;"></span>
    You have been logged out.</p>
</div>