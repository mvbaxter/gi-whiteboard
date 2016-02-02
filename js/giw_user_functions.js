// JavaScript Document
	
jQuery.noConflict();
(function( $ ) {
   $('document').ready(function(){
	   var emailRegex = /^[_a-zA-Z0-9-]+(\.[\'_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.(([0-9]{1,3})|([a-zA-Z]{2,3})|(info|name))$/i;
	   var $add_dialog = null;
	   var $edit_dialog = null;
	   var $tblUsers = null;
	   
	   $('.datepicker').datepicker({
		  dateFormat:			'mm/dd/yy',
		  defaultDateType:		+1,
		  buttonImage:			'img/datepicker.jpg'
	   });
   	   $('.error404').removeClass('error404');
	   
	   /****************************************/
	   /****************************************/
	   // get entries and setup the dataTable to display them.
	   $ajax_user_data = {
			user_access	:	$('#access_level').val(),
			user_id		:	$('#user_id').val(),
			action		:	'get_table_data'
		};
		$.ajax({
			type		:	'POST',
			url			:	window.location.protocol + "//" + window.location.hostname + '/utility/gi-whiteboard/inc/ajax_user_requests.php',
			dataType	:	'json',
			data		:	$ajax_user_data,
			success : 
				function(json){
					if (json.table_data['success'] == true){
						json['aoColumns'] = json.table_data['aoColumns'];
						json['aaData'] = json.table_data['aaData'];
						json['pageLength'] 	= 50;
						json['paging'] 			= true;
						json['scrollY'] 		= "350px";
						json['scrollX']			= false;
						json['autoWidth'] 		= true;												
						json['processing'] 		= true;
						json['stateSave']		= false;
						json['serverSide']		= false;
						json["search"]			= {	"caseInsensitive": true };						
						json['searching'] 		= true;
						json["deferRender"]		= false;
						json['aaSorting']		= [ [ 2, 'asc'] ];
						json['responsive']		= {details:
							{type: 'column',target: 'tr'}
						};
						json["columnDefs"] = [
							// ID
							{ "className" : "never","searchable":false, "visible": false, "type" : "num", "targets": 0 },
							// NUID
							{ "className" : "all" ,"width": "15%", "targets": 1 },
							// Username
							{ "className" : "all" ,"width": "50%", "targets": 2 },
							// Date of last activity
							{ "className" : "never" ,"visible": false, "orderData" : [ 3, 4 ], "targets": 3 },
							// Last Activity
							{ "className" : "all" ,"width": "30%", "orderData" : [ 3, 4 ], "orderSequence" : ["desc","asc"], "targets": 4 }/*,
							
							{ "width" : "5%", "orderable": false,"target":5 },
							
							{ "className" : "control", "width" : "5%", "orderable" : false, "targets" : -1}
							*/];

						var $text = '<tr>';
						
						for(var col in json.aoColumns){
							if (col !='contains')
							$text += '<th>'+json.aoColumns[col]['sTitle']+'</th>';
						}

						$text += '</tr>';
						$('table#MainDisplay thead').html($text);
						$('table#MainDisplay tfoot').html($text);
						
						
						inputWidth = [ '0px','100px','300px'];
						inputWidth = [ '0px','95%','95%'];

						// Setup - add a text input to each footer cell
						$('#MainDisplay tfoot th').each( function () {
							var title = $('#MainDisplay thead th').eq( $(this).index() ).text();
							if (title !== ''){
								var $width = inputWidth[$(this).index()];
								title = $(this).text();
								$(this).html( '<input type="text" placeholder="Filter '+title+'" style="width:'+$width+' !important;" />' );
							}
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
							var $id = $rowData['id'];
							if ($('#user_id').val() == '') {
								alert('You must be logged in to edit users');
							}else if($('#access_level').val()*1 < 9){
								alert('You must be an Administrator to edit users');
							}else{
								edit_user($id);
							}
						});
						$('#MainDisplay tbody').delegate('tr[role="row"]','taphold',function(){
							$(this).addClass('selected');
							var $rowData = tblData.row(this).data();
							var $id = $rowData['id'];
							if ($('#user_id').val() == '') {
								alert('You must be logged in to edit users');
							}else if($('#access_level').val()*1 < 9){
								alert('You must be an Administrator to edit users');
							}else{
								edit_user($id);
							}
						});

						$('.dataTables_scrollFootInner tfoot th:last').append('<span style="float:right"><input type="image" height="20" width="20" src="../img/btn-remove-filter.png" name="remove_filter" id="remove_filter" title="Clear filters" /></span>');
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
	   /****************************************/
	   
	   
	   // Define the dialog used to display empty fields for new user additions
	   $add_dialog = $( "#wb-add-entry" ).dialog({
		  autoOpen: false,
		  height: 410,
		  width: 600,
		  modal: true,
		  buttons: {
			"Add User": function(){
				if (validateForm($('#wb-add-entry')) == true){
					var $objValues = $('#wb-add-entry').serializeArray(); // store json string
					
					addUser($objValues, false);
					refreshUserTable();
				} // end if form validated properly
			},
			"Add More": function(){
				if (validateForm($('#wb-add-entry')) == true){
					var $objValues = $('#wb-add-entry').serializeArray(); // store json string
					
					addUser($objValues, true);
					refreshUserTable();
				} // end if form validated properly
			},
//			"Create an account": addUser,
			Cancel: function() {
			  $add_dialog.dialog( "close" );
			}
		  },
		  open: function(){
			$('#validateAddTips').removeClass();
			$('#validateAddTips').html('* Required fields');
			$nuid = $('#nuid').val();
			display_user_groups($nuid,'add_user');
			$('#wb-add-entry input[name="groups-help"]').on('click',function(e){ display_groups_help(e);});
		  },
		  close: function() {
			$('#wb-add-entry')[0].reset();
			$('.ui-state-error').removeClass();
			$('.ui-state-hightlight').removeClass();			
		  }
		});
		
		// Define the dialog used for editing existing users.
		// varies from add_dialog because it populates fields as it opens
		// also calls "updateUser()" function on button click
		$edit_dialog = $( "#wb-edit-entry" ).dialog({
		  autoOpen: false,
		  height: 410,
		  width: 600,
		  modal: true,
		  buttons: {
			"Update User": function(){
				myForm = $('#wb-edit-entry');
				myForm.find('.ui-state-error').removeClass('ui-state-error');
				myTips = myForm.find('#validateEditTips');
				myTips.html('* Required fields').removeClass('ui-state-error');
				if (validateForm(myForm) == true){
					
					var $objValues = myForm.serializeArray(); // store json string
					
					$objValues.push(  {name:'id',value:$edit_dialog.user_id} );
					$objValues.push( {name:'user_id',value:$('#user_id').val()} );
					
					updateUser($objValues);
					$edit_dialog.dialog("close");		
					refreshUserTable();
				} // end if form validated properly
			},
//			"Create an account": addUser,
			Cancel: function() {
			  $edit_dialog.dialog( "close" );
			}
		  },
		  open: function(){
			$('#validateEditTips').removeClass();
			$('#validateEditTips').html('* Required fields');
			$nuid = $('#wb-edit-entry').find('input[name="nuid"]').val();
			display_user_groups($nuid,'edit_user');
			$('#wb-edit-entry input[name="groups-help"]').on('click',function(e){ display_groups_help(e);});			
		  },
		  close: function() {
			$('#wb-edit-entry')[0].reset();
			$('.ui-state-error').removeClass();
			$('.ui-state-hightlight').removeClass();		
		  }
		});
		// display the user group checkboxes in dialogs
		function display_user_groups($nuid, $action){
			$data =  {
				nuid	:	$nuid,
				user_id	:	$('#user_id').val(),
				action	:	'get_groups'
			};
			$.ajax({
				url:		window.location.protocol + "//" + window.location.hostname + '/utility/gi-whiteboard/inc/ajax_user_requests.php',
				type:		"POST",
				data:		$data,
				dataType:	"json",
				success: function(json){
					$container = $action == 'add_user' ? '#wb-add-entry' : '#wb-edit-entry';
					$($container+' #groups').html(json.groups);
					if ($action=='edit_user' && $edit_dialog.groups && $edit_dialog.groups.length > 0){
						$groups = $edit_dialog.groups;
						for (var $g = 0 ; $g < $groups.length; $g++){
							$gp = $groups[$g];
							$($container+' input[value="'+$gp+'"]').prop('checked', 'checked');
						} // loop for
					}
				},
				error: function(){
					$('#wb-add-entry #groups').text('[An AJAX error has occurred]');
				}
			});	
		}
		function watchEditButtons(){
			$('#edit_user').on('click',function(evt){
				var $rowData = tblData.row('.selected').data();
				if (!$rowData || $rowData.length==0){
					alert('Please select a user to edit');
				}else{
					var $id = $rowData['id'];
					edit_user($id);
				}
			});
			$('#delete_user').on('click',function(evt){
				var $rowData = tblData.row('.selected').data();
				if (!$rowData || $rowData.length==0){
					alert('Please select a user to delete');
				}else{
					var $id = $rowData['id'];
					var $name = $rowData['username'];
					delete_user($id,$name);
				}
			});
			
			/* // Originally watched action buttons for each table row.
			// add click functions to icon buttons in the 'Actions' column
			$('#MainDisplay td.actions a').on('click',function(evt){
				evt.preventDefault();
				$url = $(this).attr('href');
				$action = getURLParameter($url, 'action');
				$id = getURLParameter($url, 'id');
				if ($action=='edit'){
					edit_user($id);				
				} else {
					delete_user($id);
				}
			});
			*/
	   }
	   // make sure to call it the first time, or it won't work
	   watchEditButtons();

		// use this to setup an "Add User" button
		$('#add_user').on('click',function(evt){
			$add_dialog.dialog( "option", "title" , 'Add user');
			$add_dialog.dialog("open");
		});
	   
	   // if the waiting room has been previously set, hide the select object
		String.prototype.endsWith = function(suffix) {
			return this.indexOf(suffix, this.length - suffix.length) !== -1;
		};
		function edit_user($id){
			var icoAlert = '<span class="ui-icon ui-icon-alert" style="float:left;margin:0 5px 50px 10px;"></span>';
			$edit_user_data = {
				action	:	'get_user_data',
				id		:	$id,
				user_id	:	$('#user_id').val()
			}
			$.ajax({
				url:		window.location.protocol + "//" + window.location.hostname + '/utility/gi-whiteboard/inc/ajax_user_requests.php',
				type:		"POST",
				data:		$edit_user_data,
				dataType:	"json",
				success: function(json){
					if (json.success==true){
						$edit_dialog.dialog('option','title','Edit user ('+json.name+')');
						$('#wb-edit-entry #nuid').val(json.nuid);
						$('#wb-edit-entry #firstname').val(json.firstname);
						$('#wb-edit-entry #lastname').val(json.lastname);						
						$('#wb-edit-entry #email').val(json.email);
						$edit_dialog.groups = json.groups;
						$edit_dialog.dialog('open');
						$edit_dialog.user_id = $id;														

					} else {
						$error = 'User not found. Please seek assistance';
						$('#validateEditTips').html(icoAlert+$error);
						$('#validateEditTips').addClass('ui-state-error');
					}
				},
				error: function(){
					$error = '[An AJAX error has occurred]';
					$('#validateEditTips').html(icoAlert+$error);
					$('#validateEditTips').addClass('ui-state-error');
				}
			});
			
		}
		function addUser($objFlds, add_more){
			var $user_data = getData($objFlds);
			var icoAlert = '<span class="ui-icon ui-icon-alert" style="float:left;margin:0 5px 50px 10px;"></span>';
			
			$user_data['action'] = 'add_user';
			
			$.ajax({
				url:		window.location.protocol + "//" + window.location.hostname + '/utility/gi-whiteboard/inc/ajax_user_requests.php',
				type:		"POST",
				data:		$user_data,
				dataType:	"json",
				success: function(json){
					var origMsg = $('#validateAddTips').html();
					if (json.success==true){
						$('#wb-add-entry input[type="text"]').val('');
						$('#wb-add-entry #nuid').focus();
						var icoSuccess = '<span class="ui-icon ui-icon-info" style="float:left;margin:0 5px 5px 10px;"></span>';
						var msg = 'User '+($user_data['firstname']+' '+$user_data['last_name']).trim()+' added successfully.';
						$('#validateAddTips').html(icoSuccess+msg);						
						$('#validateAddTips').addClass('ui-state-highlight ui-state-info');
						refreshUserTable();
						if (add_more==false) $add_dialog.dialog("close");		
					} else {
						$('#validateAddTips').addClass('ui-state-error');
						$('#validateAddTips').html(icoAlert+json.error);
						if (json.error.indexOf('NUID') > 0){ 
							$('#wb-add-entry input[name="nuid"]')
								.addClass('ui-state-error')
								.focus();
						}
					}
					setTimeout(function(){
						$('#validateAddTips').html(origMsg);						
						$('#validateAddTips').removeClass();
					},3000)
				},
				error: function(){
					$error = '[An AJAX error has occurred]';
					$('#validateAddTips').html(icoAlert+$error);
					$('#validateAddTips').addClass('ui-state-error');
				}
			});
		}
		function delete_user($id,$username){
			var icoAlert = '<span class="ui-state-error" style="border:none"><span class="ui-icon ui-icon-alert" style="float:left;margin:0 5px 50px 10px;"></span></span>';
//			$username = $('#MainDisplay tr[user_id="'+$id+'"] td.username').text();
			$msg = 'Are you sure you want to delete<br /><strong>'+$username+'</strong>?';
			$('#dlgMsg').html(icoAlert+$msg);
			$del_dialog = $( "#dialog_confirm" ).dialog({
			  resizable: false,
			  height:190,
			  modal: true,
			  title:	"Delete user?",
			  buttons: {
				"Delete User": function() {
				  $del_data = {
						action	:	'delete_user',
						user_id	:	$('#user_id').val(),		
						id		:	$id  
				  }
				  $.ajax({
						url:		window.location.protocol + "//" + window.location.hostname + '/utility/gi-whiteboard/inc/ajax_user_requests.php',
						type:		"POST",
						data:		$del_data,
						dataType:	"json",
						success: function(json){
							if (json.success==true){
								refreshUserTable();
								$del_dialog.dialog('close');
							} else {
								$error = "Error:"+json.error;
								$('#$dlgMsg').html(icoAlert+$error);
								$('#dlgMsg').addClass('ui-state-error');
							}
						},
						error: function(){
							$error = '[An AJAX error has occurred]';
							$('#dlgMsg').html(icoAlert+$error);
							$('#dlgMsg').addClass('ui-state-error');
						}
					});
					
				},
				Cancel: function() {
				  $( this ).dialog( "close" );
				}
			  },
				close: function(){
					$('#dlgMsg').removeClass();	
				}
			});
		};
		function updateUser($objFlds){
			var $user_data = getData($objFlds);
			var icoAlert = '<span class="ui-icon ui-icon-alert" style="float:left;margin:0 5px 50px 10px;"></span>';
			
			$user_data['action'] = 'update_user';
			$.ajax({
				url:		window.location.protocol + "//" + window.location.hostname + '/utility/gi-whiteboard/inc/ajax_user_requests.php',
				type:		"POST",
				data:		$user_data,
				dataType:	"json",
				success: function(json){
					if (json.success==true){
						var icoSuccess = '<span class="ui-icon ui-icon-info" style="float:left;margin:0 5px 5px 10px;"></span>';
						var msg = 'User '+($user_data['fistname']+' '+$user_data['lastname']).trim()+' updated successfully.';
						$('#validateEditTips').html(icoSuccess+msg);
						$('#validateEditTips').addClass('ui-state-highlight ui-state-info');
						refreshUserTable();	
					} else {
						$('#validateEditTips').html(icoAlert+json.error);
						$('#validateEditTips').addClass('ui-state-error');
					}
				},
				error: function(){
					$error = '[An AJAX error has occurred]';
					$('#validateEditTips').html(icoAlert+$error);
					$('#validateEditTips').addClass('ui-state-error');
				}
			});
		}
		function refreshUserTable(){
			$ajax_data = {
				user_access	:	$('#access_level').val(),
				action		:	'get_table_data',
				user_id		:	$('#user_id').val()
				};
			$.ajax({
				url:		window.location.protocol + "//" + window.location.hostname + '/utility/gi-whiteboard/inc/ajax_user_requests.php',
				type:		"POST",
				data:		$ajax_data,
				dataType:	"json",
				success: function(json){
					/* Original method, used before we used dataTable format
					if (json.table_data.success==true){
						$('#MainDisplay tbody').replaceWith(json.table_data.tbody);
						watchEditButtons();
					}
					*/
					if (json.table_data.success==true){
						json.aaData = json.table_data['aaData'];
						json.aoColumns = json.table_data['aoColumns'];
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
					}
				},
				error: function(){
					$error = '[An AJAX error has occurred]';
					$('#validateEditTips').html(icoAlert+$error);
					$('#validateEditTips').addClass('ui-state-error');
				}
			});
		}
		function display_groups_help(evt){
			evt.preventDefault();
			evt.stopPropagation();
			$default_title = $('#dialog_confirm').attr('title');
			$default_msg = $('#dialog_confirm p#dlgMsg').html();
			
			$msg = '<ul style="list-style:none outside none;"><h3>Group definitions</h3>';
			$msg += '<li style="margin-bottom:15px"><strong>Users = </strong>&nbsp;Can create new entries.  Can Edit their own entries, but not those created by others.</li>';
			$msg += '<li style="margin-bottom:15px"><strong>Editors = </strong>&nbsp;Can create new entries.  Can Edit ANY entries, including those created by others, but will not see the "Delete" button and cannot delete documents.</li>';
			$msg += '<li style="margin-bottom:15px"><strong>Administrators = </strong>&nbsp;Have complete access to all functions, and the ability to Create/Edit/Delete users and user access.</li>';
			$msg += '<li><strong>Providers = </strong>&nbsp;No impact on access or functions. Only used to populate the "Providers" drop-down list when creating/editing new entries</li>';
			$msg += '</ul>';
			
			$('#dialog_confirm p#dlgMsg').html($msg);
			
			$help_dialog = $('#dialog_confirm').dialog({
				  autoOpen: true,
				  height: 350,
				  width: 550,
				  modal: true,
				  title:	'About Groups...',
				  buttons: {
					Cancel: function() {
					  $help_dialog.dialog( "close" );
					}
				  },
				  close: function() {
					$('#dialog_confirm').attr('title',$default_title);
					$('#dialog_confirm p#dlgMsg').html($default_msg);
				  }
				});
		}
		function validateForm(objForm){
		  objForm.find('.ui-state-error').removeClass('ui-state-error');
		  objForm.find('.dialog_message').html('');
		  
  		  var nuid = objForm.find('input[name="nuid"]');
		  var firstname = objForm.find('input[name="firstname"]');
		  var lastname = objForm.find('input[name="lastname"]');		  
  		  var email = objForm.find('input[name="email"]');
  		  var groups = objForm.find('input[name="groups"]');
		  
		  var valid = checkLength( nuid, "NUID", 6, 8);
 		  valid = valid && checkLength( firstname, "First_Name", 2, 50);
		  valid = valid && checkLength( lastname, "Last_Name", 2, 100);		  
		  valid = valid && checkLength( email, "eMail", 10, 100);
	 
		  valid = valid && checkRegexp( nuid, /^[a-z]([0-9]){5,7}$/i, "NUID must begin with a single letter and end with 5-7 digits (e.g. x12345)." );
  		  valid = valid && checkRegexp( firstname, /^[a-zA-Z]+((\s|\-)[a-zA-Z]+)?$/i, "First name may consist of letters A-Z, hyphens and/or spaces, but not numbers, commas or foreign characters." );
		  valid = valid && checkRegexp( lastname, /^[a-zA-Z]+(([\'\,\.\-][a-zA-Z])?[a-zA-Z]*)*$/i, "Last name may consist of letters A-Z, apostrophes, and/or hyphens, no spaces or numbers." );		  
/***  Original formulae ... originally intended for Username field, not individual fields		  
		  valid = valid && checkRegexp( firstname, /^[^\x00-\x1f\x21-\x26\x28-\x2d\x2f-\x40\x5b-\x60\x7b-\xff]+$/i, "First name may consist of a-z, periods, and/or spaces and must begin with a letter." );
		  valid = valid && checkRegexp( lastname, /^[^\x00-\x1f\x21-\x26\x28-\x2d\x2f-\x40\x5b-\x60\x7b-\xff]+$/i, "Last name may consist of a-z, periods, and/or spaces and must begin with a letter." );		  
***/		  
//		  valid = valid && checkForNames(name, /^[^\x00-\x1f\x21-\x26\x28-\x2d\x2f-\x40\x5b-\x60\x7b-\xff]+$/i, "Please enter valid first and last names." );
		  valid = valid && checkRegexp( email, emailRegex, "Please enter a valid email address (e.g. somebody.u.know@kp.org)" );
		  
		  return valid;
			
		}
		function checkForNames( o, regexp, err){
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
			var icoAlert = '<span class="ui-icon ui-icon-alert" style="float:left;margin:0 5px 50px 10px;"></span>';
			objForm.find('p[id^=validate]')
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
		
	   var _access_level = $('#access_level').val()*1;
	   var _user_id = $('#user_id').val();
	   if (_access_level < 7){
		   var alert_msg = "This area is for authorized Administrators only."
		   if (_user_id=='') alert_msg += "\n\nPlease login if you are an Administrator.";
		   alert_msg += "\n\nYou will be redirected to the user area.";
		   alert(alert_msg);
		   window.location.href = '../';
	   }
  	});
})(jQuery);
