// JavaScript Document
jQuery(document).bind("mobileinit", function () {
    jQuery.mobile.ajaxEnabled = false;
});

jQuery.noConflict();
(function( $ ) {
   $('document').ready(function(){
	   /*******    Declare global variables for this script library      ***********/
	   
	   /****** icons, used for various dialg boxes  **********/
	   var icoAlert = '<span class="ui-icon ui-icon-alert" style="float:left;margin:0 5px 50px 10px;"></span>';
	   var icoSuccess = '<span class="ui-icon ui-icon-info" style="float:left;margin:0 5px 50px 10px;"></span>';
	   var icoLocked = '<span class="ui-icon ui-icon-locked" style="float:left;margin:0 5px 50px 10px;"></span>';
	   var $origMsg = ''; //  used to hold default dialog message from the time that dialog is opened, until it is closed.
	   
   	   var tblData = null;		// object referring to the primary datatable object.
	   var _ajax_url = window.location.protocol + "//" + window.location.hostname + '/utility/gi-whiteboard/inc/ajax_requests.php';	   
	   var _access_level = $('#access_level').val()*1;	// the current browser user's access level (set using PHP)
	   var _user_id = $('#user_id').val();				// the current browser user' id, if logged in (set using PHP)
	   var _fullname = $('#fullname').val();				// blank if the current browser user is not logged in
	   var _session_id = $('#session_id').val();			// blank if the current browser user is not logged in
	   var _logged_in = _user_id == '' || _user_id==null ? false : true;
	   
	   // setup variables used to monitor and lockout
	   // documents that are locked open by dialog activity.
	   var _locked_by = '';				//	temp variable to retrieve and display whoever currently has a record locked
	   var _idle_time = 0;					//	set and reset as part of the record locking/release system
	   var _activity_interval = null;	//	used to track the setInterval object for the record locking/releas system
	   var _dialog_lock_time = 5*60; //	5mins ... How long until a lock warning is displayed?  Every N minutes.
	   var _dialog_clock_timer = 10; //	seconds ... How many seconds will the system count down the warning for lock release?
	   var _dialog_count_down_start = _dialog_lock_time + _dialog_clock_timer+1; // at what time do we shut down the countdown and process automation.	
	   
	   // setup variable indicating whether the user's browser is IE
	   // because we have special functions required to make IE run
	   // the same as other browsers.
	   var ua = window.navigator.userAgent;
	   var _IS_IE = ua.indexOf("MSIE ") > 0 ? true : false;
	   
	   //	setup timer to refresh datatable display with server data.
	   var tableTimer = setInterval(function(){
		   refreshTableData();
	   },5*60*1000); // fire every 5 mins
	   
	   // used to improve the global nav's self-closing ability.
	   // compensating for the larger article size.
	   $('#HideBody').height($(document).height());
	   
	   // setup all datapicker objects to use the datepicker icon and dialog
	   $('.datepicker').datepicker({
		  dateFormat:			'mm/dd/yy',
		  defaultDateType:		+1,
		  showOn: "button",
		  buttonImage: "img/datepicker.jpg",
		  buttonImageOnly: true,
		  buttonText: "Select date"
	   });
	   /************ BEGIN HELPER FUNCTIONS  ************************/
	   //	The following functions will all be called (or not) by
	   //	support functions and/or startup routines at the end of
	   //	this script area.
	   /*************************************************************/
	   
	   /* 	watchFilters()													//
	   //	Sets up the filter fields in the footer to display			//
	   //	and clear placeholder text appropriately for IE.			*/
	   function watchFilters(){
			// pause for 1/4 second (from doc ready), then setup 
		   $('.dataTables_scrollFootInner').find('input[placeholder]').each(function(){
			   $placeholder = $(this).attr('placeholder');
			   $(this)
					.attr('title',$placeholder)
					.val( $placeholder )
					.addClass('placeholder');
		   });
		   $('.dataTables_scrollFootInner').delegate('input[placeholder]','focusin',function(){
				if ($(this).val() == $(this).attr('placeholder')){
					$(this)
						.val('')
						.removeClass('placeholder');
				}; // end if value = placeholder
		   });
		   $('.dataTables_scrollFootInner').delegate('input[placeholder]','focusout',function(){
				if ($(this).val() == ''){
					$(this)
						.addClass('placeholder')
						.val($(this).attr('placeholder'));
				}; // end if value is empty
		   });
		}
		// called on on('click',... process values input via displayed dialog
		function addEntry($objFlds, add_more){
			var $entry_data = getData($objFlds);
			
			$entry_data['access_level'] = _access_level;
			$entry_data['user_id'] = _user_id;
			$entry_data['action'] = 'add_entry';
			
			$.ajax({
				url:		_ajax_url,
				type:		"POST",
				data:		$entry_data,
				dataType:	"json",
				success: function(json){
					var origMsg = $('#wb-add-entry p.validateAddTips').html();
					if (json.success==true){
						var msg = 'Entry added successfully.';
						$('#wb-add-entry p.validateAddTips').html(icoSuccess+msg);						
						$('#wb-add-entry p.validateAddTips').addClass('ui-state-highlight ui-state-info');
						$('#wb-add-entry input[type="text"]','#wb-add-entry select').val('');
						refreshTableData();
						if (add_more==false){
							$add_dialog.dialog("close");
						} else {
							$('select[name="appt_type"] option').first().prop('selected',true);
							$('textarea').val('');
							$('input[name="appt_date"]').val('').focus();
						}
					} else {
						$('#wb-add-entry p.validateAddTips').addClass('ui-state-error');
						$('#wb-add-entry p.validateAddTips').html(icoAlert+json.error);
						$('.ui-state-error:not(p)').first().focus();
					}
					setTimeout(function(){
						$('#wb-add-entry p.validateAddTips').html(origMsg);						
						$('#wb-add-entry p.validateAddTips').removeClass().addClass('validateAddTips');
					},10000)
				},
				error: function(jqXHR,textStatus,exception){
					if (jqXHR.status === 0) {
						$error = 'Not connected. Verify Network.';
					} else if (jqXHR.status == 404) {
						$error = '(404) Requested page not found.';
					} else if (jqXHR.status == 500) {
						$error = '(500) Internal Server Error. Please verify target program.';
					} else if (exception === 'parsererror') {
						$error = 'Requested JSON parse failed.';
					} else if (exception === 'timeout') {
						$error = 'Time out error.';
					} else if (exception === 'abort') {
						$error = 'Ajax request aborted.';
					} else {
						$error = 'Uncaught Error.' + jqXHR.responseText + '('+textStatus+')';
					}
					$('#wb-add-entry p.validateAddTips').html(icoAlert+$error);
					$('#wb-add-entry p.validateAddTips').addClass('ui-state-error');
				}
			});
		}
		// called on on('click',... process values input via displayed dialog
		function updateEntry($objFlds,$post_id,$status){
			var $entry_data = getData($objFlds);
			
			$entry_data['access_level'] = _access_level;
			if ($status && $status!='open') {
				$entry_data['status'] = $status;
				switch ($status){
					case "booked":
						$entry_data['action'] = 'book_entry';
						break;
					case "un-book":
						$entry_data['status'] = 'open';
						$entry_data['action'] = 'update_entry';
						break;
				} // end switch
			} else {
				$entry_data['action'] = 'update_entry';
			}
			$entry_data['user_id'] = _user_id;
			$entry_data['id'] = $post_id;
			
			$.ajax({
				url:		_ajax_url,
				type:		"POST",
				data:		$entry_data,
				dataType:	"json",
				success: function(json){
					var origMsg = $('#wb-update-entry p.validateUpdateTips').html();
					if (json.success==true){
						var msg = 'Entry updated successfully.';
						$('#wb-update-entry p.validateUpdateTips').html(icoSuccess+msg);						
						$('#wb-update-entry p.validateUpdateTips').addClass('ui-state-highlight ui-state-info');
						$('#wb-update-entry input[type="text"]','#wb-update-entry select').val('');
						refreshTableData();
						if ($status && $status=='booked') {
							setTimeout(function(){
								display_unbook($post_id);
							},300)
						}
						$update_dialog.dialog("close");		
					} else {
						$error = json.error + (json['lock_error'] ? json['lock_error'] : '');
						$('#wb-update-entry p.validateUpdateTips').addClass('ui-state-error');
						$('#wb-update-entry p.validateUpdateTips').html(icoAlert+$error);
						$('.ui-state-error:not(p)').first().focus();
						setTimeout(function(){
							$('#wb-update-entry p.validateUpdateTips').html(origMsg);						
							$('#wb-update-entry p.validateUpdateTips').removeClass().addClass('validateUpdateTips');
						},10000)
					}
				},
				error: function(jqXHR,textStatus,exception){
					if (jqXHR.status === 0) {
						$error = 'Not connected. Verify Network.';
					} else if (jqXHR.status == 404) {
						$error = '(404) Requested page not found.';
					} else if (jqXHR.status == 500) {
						$error = '(500) Internal Server Error. Please verify target program.';
					} else if (exception === 'parsererror') {
						$error = 'Requested JSON parse failed.';
					} else if (exception === 'timeout') {
						$error = 'Time out error.';
					} else if (exception === 'abort') {
						$error = 'Ajax request aborted.';
					} else {
						$error = 'Uncaught Error.' + jqXHR.responseText + '('+textStatus+')';
					}
					$('#wb-update-entry p.validateUpdateTips').html(icoAlert+$error);
					$('#wb-update-entry p.validateUpdateTips').addClass('ui-state-error');
				}
			});
		}
		// called on on('click',... delete record indicated by button HREF
		function deleteEntry($delete_dialog,$post_id){
			var $entry_data = new Object();
			
			$entry_data['access_level'] = _access_level;
			$entry_data['action'] = 'delete_entry';
			$entry_data['user_id'] = _user_id;
			$entry_data['id'] = $post_id;
			
			$.ajax({
				url:		_ajax_url,
				type:		"POST",
				data:		$entry_data,
				dataType:	"json",
				success: function(json){
					var origMsg = $('#generic-dialog p').html();
					$('.ui-button:contains(Delete)').hide();
					
					if (json.success==true){
						var msg = 'Entry deleted successfully.';
						
						$('#generic-dialog p').addClass('ui-state-highlight');
						$('#generic-dialog p').html(icoSuccess+msg);

						refreshTableData();
						setTimeout(function(){
							display_undelete($post_id)
						},300)
						$delete_dialog.dialog( "close" );
					} else {
						$('#generic-dialog p').addClass('ui-state-error');
						$('#generic-dialog p').html(icoAlert+json.error);
					}
					setTimeout(function(){
						$('#generic-dialog p').html(origMsg);			
						$('#generic-dialog p').removeClass();
					},10000);
				},
				error: function(jqXHR,textStatus,exception){
					if (jqXHR.status === 0) {
						$error = 'Not connected. Verify Network.';
					} else if (jqXHR.status == 404) {
						$error = '(404) Requested page not found.';
					} else if (jqXHR.status == 500) {
						$error = '(500) Internal Server Error. Please verify target program.';
					} else if (exception === 'parsererror') {
						$error = 'Requested JSON parse failed.';
					} else if (exception === 'timeout') {
						$error = 'Time out error.';
					} else if (exception === 'abort') {
						$error = 'Ajax request aborted.';
					} else {
						$error = 'Uncaught Error.' + jqXHR.responseText + '('+textStatus+')';
					}
					$('#generic-dialog p').html(icoAlert+$error);
					$('#generic-dialog p').addClass('ui-state-error');
				}
			});
		}
		// mark selected entry "booked" from a button on('click',... OR dialog button
		function bookEntry($booked_dialog,$post_id){
			var $entry_data = new Object();
			
			$entry_data['access_level'] = _access_level;
			$entry_data['action'] = 'book_entry';
			$entry_data['user_id'] = _user_id;
			$entry_data['id'] = $post_id;
			
			$.ajax({
				url:		_ajax_url,
				type:		"POST",
				data:		$entry_data,
				dataType:	"json",
				success: function(json){
					var origMsg = $('#generic-dialog p').html();
					$('.ui-button:contains(Delete)').hide();
					
					if (json.success==true){
						var msg = 'Entry deleted successfully.';
						
						$('#generic-dialog p').addClass('ui-state-highlight');
						$('#generic-dialog p').html(icoSuccess+msg);

						refreshTableData();
						setTimeout(function(){
							display_unbook($post_id);
						},300)
						$booked_dialog.dialog( "close" );						
					} else {
						$('#generic-dialog p').addClass('ui-state-error');
						$('#generic-dialog p').html(icoAlert+json.error);
					}
					setTimeout(function(){
						$('#generic-dialog p').html(origMsg);			
						$('#generic-dialog p').removeClass();
					},10000);
				},
				error: function(jqXHR,textStatus,exception){
					if (jqXHR.status === 0) {
						$error = 'Not connected. Verify Network.';
					} else if (jqXHR.status == 404) {
						$error = '(404) Requested page not found.';
					} else if (jqXHR.status == 500) {
						$error = '(500) Internal Server Error. Please verify target program.';
					} else if (exception === 'parsererror') {
						$error = 'Requested JSON parse failed.';
					} else if (exception === 'timeout') {
						$error = 'Time out error.';
					} else if (exception === 'abort') {
						$error = 'Ajax request aborted.';
					} else {
						$error = 'Uncaught Error.' + jqXHR.responseText + '('+textStatus+')';
					}
					$('#generic-dialog p').html(icoAlert+$error);
					$('#generic-dialog p').addClass('ui-state-error');
				}
			});
		}
		// Reset booked or deleted entry back to "open" status
		function restoreEntry($undo_dialog,$post_id){
			var $entry_data = new Object();
			$entry_data['id'] = $post_id;
			$entry_data['user_id'] = _user_id;
			$entry_data['status'] = 'open';
			$entry_data['action'] = 'update_entry';
			hideUndo();
			$.ajax({
				url:		_ajax_url,
				type:		"POST",
				data:		$entry_data,
				dataType:	"json",
				success:	function(json){
					var origMsg = $('#generic-dialog p').html();
					$('.ui-button:contains(Booking)').hide();
					if (json.success == true){
						refreshTableData();
						$undo_dialog.dialog( "close" );
					}else{
						$('#generic-dialog p').addClass('ui-state-error');
						$('#generic-dialog p').html(icoAlert+json.error);
					}
					setTimeout(function(){
						$('#generic-dialog p').html(origMsg);			
						$('#generic-dialog p').removeClass();
					},10000);
				},
				error: function(jqXHR,textStatus,exception){
					if (jqXHR.status === 0) {
						$error = 'Not connected. Verify Network.';
					} else if (jqXHR.status == 404) {
						$error = '(404) Requested page not found.';
					} else if (jqXHR.status == 500) {
						$error = '(500) Internal Server Error. Please verify target program.';
					} else if (exception === 'parsererror') {
						$error = 'Requested JSON parse failed.';
					} else if (exception === 'timeout') {
						$error = 'Time out error.';
					} else if (exception === 'abort') {
						$error = 'Ajax request aborted.';
					} else {
						$error = 'Uncaught Error.' + jqXHR.responseText + '('+textStatus+')';
					}
					$('#generic-dialog p').html(icoAlert+$error);
					$('#generic-dialog p').addClass('ui-state-error');
				}
					
			});
		}
		// different from other methods, this function uses ajax to get current values from
		//	the server, while retrieving a lock (if possible) and determining whether the user
		//	is authorized to edit or not.  If now authorized, sets all fields to read-only
		//	and hides update buttons
		function showUpdateDialog($id){
			$update_data = ({
				action: 		'get_entry',
				user_id:		_user_id,
				session_id:	_session_id,
				access_level:  _access_level,
				id:				$id
			});
			$.ajax({
				url:		_ajax_url,
				type:		"POST",
				data:		$update_data,
				dataType:	"json",
				success: function(json){
					if (json['success']==true){
						// retrieve field values from json and set the appropriate field values
						$update_dialog.post_id = $id;
						$flds = json['fields'];
						
						$('#wb-update-entry').find('input[name="appt_date"]').val($flds['appointment_date']);
						$ampm = $flds['ampm'];
						$('#wb-update-entry').find('input[name="ampm"]').filter(function(){
							return ( ($(this).val()==$ampm) || ($(this).text()==$ampm) )
						}).prop('checked',true);
						$provider = $flds['provider'];
						$('#wb-update-entry').find('select>option').filter(function(){
						  	return ( ($(this).val() == $provider) || ($(this).text() == $provider) )
						}).prop('selected', true);
						$('#wb-update-entry').find('select[name="location"]').val($flds['location']);
						$('#wb-update-entry').find('select[name="appt_type"]').val($flds['appointment_type']);
						$('#wb-update-entry').find('#notes').val($flds['notes']);
						
						// get flags to determine if we can edit and if we got a lock
						can_edit = json['can_edit'];
						author = $flds['author'];
						if (can_edit == true) _locked_by = json['locked_by'];
						$update_dialog.can_edit = can_edit;
						
						// if we can't edit, or we failed to get a lock, disable fields and
						//	set a user message indicating the proper status
						if (can_edit == false || (_locked_by && _locked_by!=_user_id)){
							$update_dialog.read_only = true;
							_locked_time = new Date(json['locked_time']);
							if (can_edit == false){
								$error = 'This document is read-only';
								$('#wb-update-entry p.validateUpdateTips').html(icoSuccess+$error);
								$('#wb-update-entry p.validateUpdateTips').addClass('ui-state-highlight');
							}else{ // add option to steel the lock if the lock is more than 5mins old
								$error = 'This document is locked by: ' + _locked_by;
								$error += ' since '+ get_formatted_date_string(_locked_time);
								$('#wb-update-entry p.validateUpdateTips').html(icoLocked+$error);
								$('#wb-update-entry p.validateUpdateTips').addClass('ui-state-error');
							}
							// hid the datepicker icon, disable fields, and hide the update button(s)
							$('img.ui-datepicker-trigger').hide();
							$('#wb-update-entry input').prop('readonly',true);
							$('#wb-update-entry input[type="radio"]').prop('disabled',true);
							$('#wb-update-entry select').prop('disabled',true);
							$('#wb-update-entry textarea').prop('readonly',true);	
							read_only_buttons = $update_dialog.dialog('option','buttons');
							delete read_only_buttons['Update Entry'];
							delete read_only_buttons['Mark Booked'];
							$update_dialog.dialog('option','buttons',read_only_buttons);
						} else {
							// set a flog for the dialog, so it can access the read_only status
							// for use with timers and auto-close
							$update_dialog.read_only=false;
						}
						// All set!  Open it already!
						$update_dialog.dialog('open');
							
					}else{
						alert(json['error']);	
					}
				},
				error: function(jqXHR,textStatus,exception){
					if (jqXHR.status === 0) {
						$error = 'Not connected. Verify Network.';
					} else if (jqXHR.status == 404) {
						$error = '(404) Requested page not found.';
					} else if (jqXHR.status == 500) {
						$error = '(500) Internal Server Error. Please verify target program.';
					} else if (exception === 'parsererror') {
						$error = 'Requested JSON parse failed.';
					} else if (exception === 'timeout') {
						$error = 'Time out error.';
					} else if (exception === 'abort') {
						$error = 'Ajax request aborted.';
					} else {
						$error = 'Uncaught Error.' + jqXHR.responseText + '('+textStatus+')';
					}
					$('#wb-update-entry p.validateUpdateTips').html(icoAlert+$error);
					$('#wb-update-entry p.validateUpdateTips').addClass('ui-state-error');
				}
			});
		} // end function showUpdateDialog
		// NO DIALOG .. Displays a short-term "oops" drop-down to allow a user to Undelete a document
		//	within 5 seconds of their deletion.
		function display_undelete($post_id){
			var undeleteTimer = null;
			$('#Breadcrumbs').prepend('<div id="undo" class="ui-widget ui-widget-content ui-front ui-corner-br ui-corner-bl ui-state-highlight"><p>You have successfully deleted 1 entry</p><p><a href="?	id='+$post_id+'&action=undelete" id="action_undo"> ( Un-delete? ) </a></p></div>');
			$('#undo').slideDown(400);
			$('a#action_undo').on('click',function(e){
				e.preventDefault();
				e.stopPropagation();
				clearTimeout(undeleteTimer);
				
				var $orig_dialog_txt = $('#generic-dialog p').html();
						
				// set the dialog message, before you define and display the dialog
				var $txt = 'Are you sure you want to undelete this entry?';
				var $msg = '<span class="ui-state-error" style="border: none;">'+icoAlert+'</span>'+$txt;
			
				$('#generic-dialog p').html($msg);
				$generic_dialog.dialog('option','title', 'Confirm undelete...');
				var $buttons =	{
						"Undelete entry":	function(){
							restoreEntry( $(this), $post_id);
						} ,
						Cancel:		function(){
							$(this).dialog( "close" );
						}
					};
				$generic_dialog.dialog('option','buttons',$buttons);
				$generic_dialog.dialog( "open" );	
			});
			undeleteTimer = setTimeout(function(){hideUndo();},7000); // used 7secs to allow a full 5secs active display, after slide-in.
		}
		// NO DIALOG .. Displays a short-term "oops" drop-down to allow a user to Un-book a document
		//	within 5 seconds of their deletion.
		function display_unbook($post_id){
			var unbookTimer = null;
			$('#Breadcrumbs').prepend('<div id="undo" class="ui-widget ui-widget-content ui-front ui-corner-br ui-corner-bl ui-state-highlight"><p>You have successfully booked 1 entry</p><p><a href="?	id='+$post_id+'&action=unbook" id="action_undo"> ( Undo? ) </a></p></div>');
			$('#undo').slideDown(400);
			$('a#action_undo').on('click',function(e){
				e.preventDefault();
				e.stopPropagation();
				clearTimeout(unbookTimer);
				
				var $orig_dialog_txt = $('#generic-dialog p').html();
						
				// set the dialog message, before you define and display the dialog
				var $txt = 'Are you sure you want to undo your Booking action?';
				var $msg = '<span class="ui-state-error" style="border: none;">'+icoAlert+'</span>'+$txt;
			
				$('#generic-dialog p').html($msg);
				$generic_dialog.dialog('option','title', 'Confirm undo...');
				var $buttons =	{
						"Undo Booking":	function(){
							restoreEntry( $(this), $post_id);
						} ,
						Cancel:		function(){
							$(this).dialog( "close" );
						}
					};
				$generic_dialog.dialog('option','buttons',$buttons);
				$generic_dialog.dialog( "open" );	
			});
			unbookTimer = setTimeout(function(){hideUndo();},7000); // used 7secs to allow a full 5secs active display, after slide-in.
		}
		/*	release_lock()										//
	   //	Sets up variables and calls ajax function		//
	   //	to release lock for select documents			*/
	   function release_lock($ru_leaving){
			var $rowData = tblData.row('.selected').data();
			var $post_id = $rowData['id'];
			var release_data = {
				action:			'release_lock',
				user_id:		_user_id,
				id:				$post_id
			};
			$.ajax({
				url:		_ajax_url,
				type:		"POST",
				data:		release_data,
				dataType:	"json",
				async:		($ru_leaving==true?false:true),
				success: function(json){
					var released = json['error'];
					var qry = json['relase_sql'];	
				},
				error: function(jqXHR,textStatus,exception){
					if (jqXHR.status === 0) {
						$error = 'Not connected. Verify Network.';
					} else if (jqXHR.status == 404) {
						$error = '(404) Requested page not found.';
					} else if (jqXHR.status == 500) {
						$error = '(500) Internal Server Error. Please verify target program.';
					} else if (exception === 'parsererror') {
						$error = 'Requested JSON parse failed.';
					} else if (exception === 'timeout') {
						$error = 'Time out error.';
					} else if (exception === 'abort') {
						$error = 'Ajax request aborted.';
					} else {
						$error = 'Uncaught Error.' + jqXHR.responseText + '('+textStatus+')';
					}
					$('#wb-add-entry p.validateUpdateTips').html(icoAlert+$error);
					$('#wb-add-entry p.validateUpdateTips').addClass('ui-state-error');
				}
			});	
		}
		// hides the "Undo" drop-down and removes click-ability
		function hideUndo(){
			$('a#action_undo').off('click');
			$('#undo').slideUp(400).replaceWith('');
		}
		// performs countdown for lock release (called by setInterval object)
		function timerIncrement() {
			_idle_time = _idle_time + 1;
			if (_idle_time > _dialog_lock_time) {
				count = _dialog_count_down_start - _idle_time;
				$('#wb-update-entry p.validateUpdateTips')
						.removeClass()
						.html(icoAlert+'This dialog box will close due to inactivity in <strong>'+count+'</strong> seconds')
						.addClass('validateUpdateTips ui-state-error');
				if (count <= 0)	{
					$update_dialog.dialog( "close" );
					_idle_time = 0;
					return;
				}
			}
		}
	   //*****   Generic helper functions.  Not specific to this application  ***/
	   // if the waiting room has been previously set, hide the select object
		String.prototype.endsWith = function(suffix) {
			return this.indexOf(suffix, this.length - suffix.length) !== -1;
		};
		// checks form fields for appropriate/mandatory content, including "very good, but not perfect" date validation.
		function validateForm(objForm,origMsg){
		  objForm.find('.ui-state-error').removeClass('ui-state-error');
		  objForm.find('p[class^=validate]').html(origMsg);
		  
  		  var date = objForm.find('input[name="appt_date"]');
		  var provider = objForm.find('select[name="provider"]');
  		  var location = objForm.find('select[name="location"]');
  		  var appt_type = objForm.find('select[name="appt_type"]');
		  var am_pm = objForm.find('input:radio[name="ampm"]');
		  var notes = objForm.find('textarea[name="notes"]');
		  
		  var valid = checkLength( date, "Date", 8, 10);
		  valid = valid && checkDate(date, 'Please input or select a valid date');
		  valid = valid && checkRadio( am_pm,objForm.find('input:radio[name="ampm"]:checked'), 'Please select a valid time slot (AM or PM)');
		  valid = valid && checkSelect(provider,'[Select a Provider]','Please select a valid provider');
		  valid = valid && checkSelect(location, '[Select a location]', 'Please select a valid location');
		  valid = valid && checkSelect(appt_type, '[Select one]', 'Please select a valid appointment type');

		  return valid;
			
		}
		function checkSelect( o, defaultValue, err ){
			var objForm = o.closest('form');
			$selection = $(o).find('option:selected').text();
			if ($selection == defaultValue || $selection == ''){
				updateTips(objForm,err);
				o
					.addClass('ui-state-error')
					.focus();
				return false;
			} else {
				return true;
			}
		}
		function checkForNames( o, regexp, err){
			var objForm = o.closest('form');
			$fullname = o.val();
			if ($fullname.indexOf(' ') > 0){
				$names = $fullname.split(' ');
				for(var $n = 0; $n < $names.length; $n++){
					if ( !( regexp.test( $names[$n] ) ) ) {
						updateTips(objForm,err);
						o.addClass('ui-state-error');
						o.focus();
						return false;
					} // end if regex
				} // loop for
				o.addClass('ui-state-error');
				o.focus();
				return true;
			} else {
				updateTips( objForm, err );
				return false;
			} // end if space found
		}
		function checkDate( o, err ){
			var isValid = checkRegexp( o, /^(((((((0?[13578])|(1[02]))[\.\-/]?((0?[1-9])|([12]\d)|(3[01])))|(((0?[469])|(11))[\.\-/]?((0?[1-9])|([12]\d)|(30)))|((0?2)[\.\-/]?((0?[1-9])|(1\d)|(2[0-8]))))[\.\-/]?(((19)|(20))?([\d][\d]))))|((0?2)[\.\-/]?(29)[\.\-/]?(((19)|(20))?(([02468][048])|([13579][26])))))$/i, err);
			if (isValid == true){
				var tmpDate = new Date(o.val());
				// double check by looking for the get_month function
				if (typeof( tmpDate.getMonth ) !== 'undefined'){
					return true;
				} else {
					o.addClass( "ui-state-error" );
					updateTips( objForm, err );
					return false;
				}
			}else{
				return true;
			}
			
		}
		// assumes the radio button has nothing selected by default
		function checkRadio( o, selected, err){
			var objForm = o.closest('form');
			var $val = selected.val();
			if ( $val && $val!=='') {
				return true;
			}else{
				o.parent('label').addClass( "ui-state-error" );
				updateTips( objForm, err );
				return false;
			}
		}
		function checkLength( o, n, min, max ) {
		  var objForm = o.closest('form');
		  if ( o.val().length > max || o.val().length < min ) {
			o.addClass( "ui-state-error" );
			updateTips(objForm, "Length of " + n + " must be between " +
			  min + " and " + max + "." );
			return false;
		  } else {
			return true;
		  }
		}
		function checkRegexp( o, regexp, err ) {
		  objForm = o.closest('form');			
		  if ( !( regexp.test( o.val() ) ) ) {
			o.addClass( "ui-state-error" );
			updateTips( objForm, err );
			return false;
		  } else {
			return true;
		  }
		}
		function updateTips( objForm, err ) {
			objForm.find('p[class^=validate]')
				.html( icoAlert+ ' ' + err )
				.addClass( "ui-state-error" );
		}
		function getData(json){
			$json_out = new Object();
			for (var f = 0 ; f < json.length ; f++){
				$myFlds = json[f];
				if ($json_out.hasOwnProperty($myFlds['name'])){
					$json_out[$myFlds['name']] += ','+ $myFlds['value'];
				} else {
					$json_out[$myFlds['name']] = $myFlds['value'];	
				}
			}
			return $json_out;
		}
		function getURLParameter(url, name) {
			return (RegExp(name + '=' + '(.+?)(&|$)').exec(url)||[,null])[1];
		}
		function setupLoggedInObjects(){
			// Define the dialog used to display empty fields for new user additions
			$add_dialog = $( "#wb-add-entry" ).dialog({
			  autoOpen: false,
			  height: 420,
			  width: 585,
			  modal: true,
			  title:  "New whiteboard item",
			  buttons: {
				"Add Entry": function(){

					if ( validateForm($('#wb-add-entry'),$origMsg)==true ){
						$('#wb-add-entry').find('#notes').focus();
						var $objValues = $('#wb-add-entry').serializeArray(); // store json string
						addEntry($objValues, false);
					} // end if form validated properly
				},
				"Add More": function(){
					if (validateForm($('#wb-add-entry'),$origMsg) == true){
						var $objValues = $('#wb-add-entry').serializeArray(); // store json string
						addEntry($objValues, true);
					} // end if form validated properly
				},
	//			"Create an account": addEntry,
				Cancel: function() {
					$add_dialog.dialog( "close" );
				}
			  },
			  open: function(){
				$origMsg = $('#wb-add-entry').find('p[class^=validate]').html();
				hideUndo();
				$('#wb-add-entry p.validateAddTips').html('*All fields required, except Notes field');			  
				$('#wb-add-entry p.validateAddTips').removeClass().addClass('validateAddTips');
			  },
			  close: function() {
				  if(_IS_IE) watchFilters();
				  $('.ui-state-error').removeClass('ui-state-error');
				  $('.ui-state-hightlight').removeClass('ui-state-highlight');
				  $('#wb-add-entry').find('p[class^=validate]').html($origMsg);
				  $('#wb-add-entry')[0].reset();				
			  }
			});
			
			$('#add_item').on('click',function(evt){
				$add_dialog.dialog("open");
			});
			$('#update_item').on('click',function(evt){
				var $rowData = tblData.row('.selected').data();
				if (!$rowData || $rowData.length==0){
					alert('Please select a record to edit');
				}else{
					var $id = $rowData['id'];
					showUpdateDialog($id);
				}
			});
			
			// Use this dialog For both Delete and Book buttons
			// define the text and buttons separately
			$generic_dialog = $( "#generic-dialog" ).dialog({
				autoOpen:		false,
				height:			250,
				width:			300,
				modal:			true,
				resizable:		false,
				closeOnEscape:	false,
			}); // end dialog
			
			$('#delete_item').on('click',function(evt){
				// backup the current dialog msg
				hideUndo();
				var $orig_dialog_txt = $('#generic-dialog p').html();
				
				// set the dialog message, before you define and display the dialog
				var $txt = 'Are you sure you want to delete the selected Whiteboard Entry?';
				var $msg = '<span class="ui-state-error" style="border: none;">'+icoAlert+'</span>'+$txt;
				
				$('#generic-dialog p').html($msg);
				
				// Get the post_id for use in the deleteEntry function
				var $rowData = tblData.row('.selected').data();
				if (!$rowData || $rowData.length==0){
					alert('Please select a record to delete');
				}else{
					var $post_id = $rowData['id'];
					$generic_dialog.dialog('option','title', 'Confirm deletion...');
					var $buttons =	{
							"Delete":	function(){
								hideUndo();
								deleteEntry( $(this), $post_id);
							} ,
							Cancel:		function(){
								$(this).dialog( "close" );
							}
						};
					$generic_dialog.dialog('option','buttons',$buttons);
					$generic_dialog.dialog( "open" );
					
				} // end if row selected;
			}); // end on click
			$('#book_item').on('click',function(evt){
				hideUndo();
				// backup the current dialog msg
				var $orig_dialog_txt = $('#generic-dialog p').html();
							
				// set the dialog message, before you define and display the dialog
				var $txt = 'Are you sure you want to mark the selected Whiteboard Entry as booked?';
				var $msg = '<span class="ui-state-error" style="border: none;">'+icoAlert+'</span>'+$txt;
				
				$('#generic-dialog p').html($msg);
				
				// Get the post_id for use in the deleteEntry function
				var $rowData = tblData.row('.selected').data();
				if (!$rowData || $rowData.length==0){
					alert('Please select a record to mark booked');
				}else{
					var $post_id = $rowData['id'];
					$generic_dialog.dialog('option','title', 'Confirm booking...');
					var $buttons =	{
							"Mark booked":	function(){
								bookEntry( $(this), $post_id);
							} ,
							Cancel:		function(){
								$(this).dialog( "close" );
							}
						};
					$generic_dialog.dialog('option','buttons',$buttons);
					$generic_dialog.dialog( "open" );
					
				} // end if row selected;
			}); // end on click
			$('#manage_users').on('click',function(evt){
				window.location.href+=(window.location.href.endsWith('/') ? '' : '/')+'users/';
			});
		} // end function setupLoggedInObjects();
		
		function refreshTableData(){
			$.ajax({
				type		:	'POST',
				url			:	'inc/ajax_requests.php',
				dataType	:	'json',
				data: {
					action			:	'get_table_data',
					access_level	:	_access_level,
					user_id			:	_user_id
				},
				success: function(json){
					var selectedID = null;
					
					$rowData = tblData.row('.selected').data();
					if ($rowData && $rowData.hasOwnProperty('id') ) selectedID = $rowData['id'];
					tblData
						.clear()
						.rows.add(json['aaData'])
						.draw();
					if (selectedID && selectedID !== null) {
						var arrIDs = tblData
							.column( 0 )
							.data()
							.toArray();
						for (var idx = 0; idx < arrIDs.length; idx++){
							if (arrIDs[idx] == selectedID){
								$('#MainDisplay tbody tr:eq('+idx+')').addClass('selected');
								break;
							}
						}
					} // end if selectedID
					tblData.columns.adjust().draw();
				},
				error: function(jqXHR,textStatus,exception){
					 if (jqXHR.status === 0) {
						$error = 'Not connected. Verify Network.';
					} else if (jqXHR.status == 404) {
						$error = '(404) Requested page not found.';
					} else if (jqXHR.status == 500) {
						$error = '(500) Internal Server Error. Please verify target program.';
					} else if (exception === 'parsererror') {
						$error = 'Requested JSON parse failed.';
					} else if (exception === 'timeout') {
						$error = 'Time out error.';
					} else if (exception === 'abort') {
						$error = 'Ajax request aborted.';
					} else {
						$error = 'Uncaught Error.' + jqXHR.responseText + '('+textStatus+')';
					}
					alert($error);
				}
			});	
		}
		
		function get_formatted_date_string($dt){
			var dd = $dt.getDate();
    		var mm = $dt.getMonth()+1; //January is 0!
    		var yyyy = $dt.getFullYear();	
			
			var hh = $dt.getHours();
    		var ii = $dt.getMinutes();
			var ss = $dt.getSeconds();
			var ampm = 'am';
			
			if (ss*1 < 10) ss = '0'+ss;
			
			if (hh > 12){
				ampm = 'pm';
				hh = hh-12;
			}
			
			return mm+'/'+dd+'/'+yyyy+' '+hh+":"+ii+':'+ss+' '+ampm;
		}
		
		// helper function to emulate PHP implode function
		function implode(glue, pieces) {
		  //  discuss at: http://phpjs.org/functions/implode/
		  // original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
		  // improved by: Waldo Malqui Silva
		  // improved by: Itsacon (http://www.itsacon.net/)
		  // bugfixed by: Brett Zamir (http://brett-zamir.me)
		  //   example 1: implode(' ', ['Kevin', 'van', 'Zonneveld']);
		  //   returns 1: 'Kevin van Zonneveld'
		  //   example 2: implode(' ', {first:'Kevin', last: 'van Zonneveld'});
		  //   returns 2: 'Kevin van Zonneveld'
		  var i = '',
		  retVal = '',
		  tGlue = '';
			
		  if (arguments.length === 1) {
			pieces = glue;
			glue = '';
		  } // end if args
		  
		  if (typeof pieces === 'object') {
			if (Object.prototype.toString.call(pieces) === '[object Array]') {
			  return pieces.join(glue);
			} // end if obj array
			for (i in pieces) {
			  retVal += tGlue + pieces[i];
			  tGlue = glue;
			} // loop for pieces
			return retVal;
		  } // end if typeof pieces object
		  return pieces;
		} // end function implode

//***************************  BEGIN ON-LOAD ITEMS   ***************************//
//	Items that will auto-load (or try to) every time the page loads/re-loads.   //
//******************************************************************************//
   	   $('.error404').removeClass('error404');
	   // secure buttons and sections only intended for admins
	   if (_access_level < 8) $('.admin_only').hide();
	   
	   $('#waiting h3').text('Retrieving data...');
		// Setup - add a text input to each footer cell		
		
	
		// get entries and setup the dataTable to display them.
		$.ajax({
			type		:	'POST',
			url			:	'inc/ajax_requests.php',
			dataType	:	'json',
			data: {
				action			: 	'get_table_data',
				access_level	:	_access_level,
				user_id			:	_user_id
			},
			success : 
				function(json){
				if (json.success == true){
					json['pageLength'] 	= 50;
					json['paging'] 		= true;
					json['scrollY'] 		= false;
					json['scrollX']			= true;
					json['scroll_collapse'] = true;
					json['autoWidth'] 		= true;												
					json['processing']		= true;
					json['stateSave']		= false;
					json['serverSide']		= false;
					json["search"]			= {	"caseInsensitive": true };						
					json['searching'] 		= true;
					json["deferRender"]		= false;
					json['aaSorting']		= [ [ 1, 'asc'],[3,'asc'] ];
					json['responsive']		= {details:
						{type: 'column', target: 'tr'}
					};
					json["columnDefs"] = [
						{ "className":"never","visible": false,"searchable":false,"targets": 0 },
						{ "className":"all","width": "10%", "type" : "date", "targets": 1 },
						{ "className":"all","width": "20%", "targets": 2 },
						{ "className":"all","width": "15%", "targets": 3 },
						{ "className":"all","width": "5%", "targets": 4 },
						{ "className":"all","width": "10%", "targets": 5 },
						{ "className":"all","width" : "30%","orderable": false,"targets" : 6 }/*,
						{ "width" : "5%", "orderable": false,"target":7 },
						{ "className": "control", "width":"5%","orderable": false,"targets":   -1}
						*/];

						var $text = '<tr>';
						
						for(var col in json.aoColumns){
							
							if (col !='contains')
							$text += '<th>'+json.aoColumns[col]['sTitle']+'</th>';
						}

						$text += '</tr>';
						$('table#MainDisplay thead').html($text);
						$('table#MainDisplay tfoot').html($text);
						
						
						//inputWidth = [ '0px','100px','140px','130px','95px','100px','275px'];
						inputWidth = [ '0px','95%','95%','95%','95%','95%','85%'];

						// Setup - add a text input to each footer cell
						$('#MainDisplay tfoot th').each( function () {
							var title = $('#MainDisplay thead th').eq( $(this).index() ).text();
							if (title !== ''){
								var $width = inputWidth[$(this).index()];
								title = $(this).text();
								$(this).html( '<input type="text" placeholder="Filter '+title+'" style="width:'+$width+' !important;" />' );
							}// end if 
						});
						
						$('table#MainDisplay').show();
						
						tblData = $('table#MainDisplay').DataTable( json );

						// Apply the filter
						tblData.columns().eq( 0 ).each( function ( colIdx ) {
							$( 'input', tblData.column( colIdx ).footer() ).on( 'keyup change', function () {
								tblData
									.column( colIdx )
									.search( this.value )
									.draw();
							} );
						} );
						
						if (is_mobile && is_mobile === true){
							$('#MainDisplay tbody').delegate('tr[role="row"]','click',function(){
								tblData.$('tr.selected').removeClass('selected');
								$(this).addClass('selected');
								var $rowData = tblData.row(this).data();
								var $post_id = $rowData['id'];
								showUpdateDialog($post_id);
							});
						} else {
							$('#MainDisplay tbody').delegate( 'tr','click', function () {
								if ( $(this).hasClass('selected') ) {
									$(this).removeClass('selected');
								} else {
									tblData.$('tr.selected').removeClass('selected');
									$(this).addClass('selected');
								}
							});
							$('#MainDisplay tbody').delegate('tr[role="row"]','dblclick',function(){
								$(this).addClass('selected');
								var $rowData = tblData.row(this).data();
								var $post_id = $rowData['id'];
								showUpdateDialog($post_id);
							});
						} // end if is mobile
						
						$('#MainDisplay').delegate('a.more_note','click',function(e){
							e.preventDefault();
							alert($(this).attr('title'));
						});
						$('.dataTables_scrollFootInner tfoot th:last').append('<span style="float:right"><input type="image" height="20" width="20" src="img/btn-remove-filter.png" name="remove_filter" id="remove_filter" title="Clear filters" /></span>');
						$('#remove_filter').on('click',function(e){
							e.preventDefault();
							e.stopPropagation();
							tblData
								 .search( '' )
								 .columns().search( '' )
								 .draw();
							$('.dataTables_scrollFootInner').find('input[placeholder]').each(function(){
								$(this).val('');	
							});
							if (_IS_IE==true) watchFilters();
							
						});
						$('.dataTables_scrollFootInner tfoot input[placeholder~="Date"]').addClass('filter_date_picker');
						$('.filter_date_picker').datepicker({
						  dateFormat:			'd M yy',
						  defaultDateType:		+1,
					   });
						

						$('#waiting').hide(500);
						$('#table-container').show(500);
							
						$('.dataTables_scrollHeadInner table.dataTable').show();
						
						setTimeout(function(){
							$('div.dataTables_scrollBody #MainDisplay').css('width','98%');
							$('.dataTables_scrollHeadInner,.dataTables_scrollHeadInner table').width('99%');
//							$('.dataTables_scrollHeadInner th[aria-controls]:not(.sort_asc)').first().trigger('click');
							tblData.columns.adjust().draw();
							},250);

					}else{
							
					}
			}, // end success function
			error: function(){
					alert('[An AJAX error has occurred]');
				}
		}); // end initial ajax call
	   
	   var update_buttons = {
			"Update Entry": function(){
				if ($update_dialog.can_edit && $update_dialog.can_edit==true){
					if (validateForm($('#wb-update-entry'),$origMsg) == true){
						var $objValues = $('#wb-update-entry').serializeArray(); // store json string
						updateEntry($objValues,$update_dialog.post_id,'open');
					} // end if form validated properly
				} else {
					$update_dialog.dialog( "close" );	
				}
			},
			"Mark Booked":	function(){
				if ($update_dialog.can_edit && $update_dialog.can_edit==true){
					if (validateForm($('#wb-update-entry'),$origMsg) == true){
						var $objValues = $('#wb-update-entry').serializeArray(); // store json string
						updateEntry($objValues,$update_dialog.post_id,'booked');
					} // end if form validated properly
				} else {
					$update_dialog.dialog( "close" );	
				}
			},
//			"Create an account": addEntry,
			Cancel: function() {
				$update_dialog.dialog( "close" );
			}
		};
		$update_dialog = $( "#wb-update-entry" ).dialog({
		  autoOpen: false,
		  height: 440,
		  width: 585,
		  modal: true,
		  title:  "Update whiteboard item",
		  buttons: update_buttons,
		  open:		function(){
			  hideUndo();
			  if ($update_dialog.read_only == false){
	  			  $origMsg = $('#wb-update-entry').find('p[class^=validate]').html();
				  window.onbeforeunload = function(){
					  release_lock(true);
					  $update_dialog.dialog( 'close' );
					};
				  var $def_tips = $('#wb-update-entry p.validateUpdateTips').html();
				  var $def_class = $('#wb-update-entry p.validateUpdateTips').attr('class');
				   
					// setup and start the dialog close countdown features
					var _time_opened = new Date();
					var _time_diff = 0;
					var _thirty_minutes = 30*60*1000;	// multiplying milliseconds
					_dialog_lock_time = 5*60; // after this interval of time if user is ideal then clock timer will start in seconds // 5*60=5mins
					_dialog_clock_timer = 10; //seconds
					_dialog_count_down_start = _dialog_lock_time+_dialog_clock_timer+1;
					_activity_interval = setInterval(function(){timerIncrement();}, 1000); // every 1 second
					//Zero the idle timer on mouse movement.
					$(document).on('mousemove',function (e) {
						_time_diff = Math.abs(new Date() - _time_opened);
						if (_time_diff < _thirty_minutes){
							_idle_time = 0;
							$('#wb-update-entry p.validateUpdateTips')
								.removeClass()
								.html($def_tips)
								.addClass($def_class);
						} else {
							$(document).off('mousemove');
						} // end if
					});
					$(document).on('keypress',function (e) {
						_time_diff = Math.abs(new Date() - _time_opened);
						if (_time_diff < _thirty_minutes){						
							_idle_time = 0;
							$('#wb-update-entry p.validateUpdateTips')
								.removeClass()
								.html($def_tips)
								.addClass($def_class);
						} else {
							$(document).off('keypress');
						} // end if more than 30mins
					});
			  } // end if read_only == false
		  },
		  close: 	function() {
			  	if (_locked_by == _user_id){ // release lock
					release_lock(false);
				} // end if locked_by == user_id
			  	if (_activity_interval && _activity_interval !== null){
					clearInterval(_activity_interval);
					_activity_interval = null;
				}
				if ($update_dialog.read_only == false) $('#wb-update-entry').find('p[class^=validate]').html($origMsg);
				$(document).off('keypress');
				$(document).off('mousemove');
				window.onbeforeunload = null;
				if(_IS_IE) watchFilters();				
				$('#wb-update-entry p.validateUpdateTips').html('*All fields required, except Notes field');			  
				$('#wb-update-entry p.validateUpdateTips').removeClass().addClass('validateUpdateTips');
				$('.ui-state-error').removeClass('ui-state-error');
				$('.ui-state-hightlight').removeClass('ui-state-highlight');
				$('#wb-update-entry')[0].reset();
				$('img.ui-datepicker-trigger').show();
				$('#wb-update-entry input').prop('readonly',false);
				$('#wb-update-entry input[type="radio"]').prop('disabled',false);				
				$('#wb-update-entry select').prop('disabled',false);
				$('#wb-update-entry textarea').prop('readonly',false);	

				$update_dialog.dialog('option','buttons',update_buttons);

		  } // end close
		});
		
		if (_logged_in==true)	setupLoggedInObjects();
		if(_IS_IE==true) setTimeout(function(){ watchFilters(); },250);
		
  	});
})(jQuery);