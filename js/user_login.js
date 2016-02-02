// JavaScript Document
var $body = null;
var $head = null;
var $validator = null;
jQuery(document).ready(function($) {
/*	
	$('body')
	  .removeClass('error404')
	  .addClass('single-author');
*/
	 function setBtnCSS(){
		$('#login,#logout').css('margin-top','-.3em');	
		$('#login .ui-button-text,#logout .ui-button-text').css('padding','.1em 1em');	 
	 }
	 function tryLogin() {
		  var closeIt = false;
		  
		  $('input').removeClass( "ui-state-error" );
		  var login_data = {
					action	:	'login',
					nuid	:	$('#nuid').val(),
					pwd		:	$('#pwd').val()
				};
		   // add code for field validation here
		  $.ajax({
				type		:	'POST',
				url			:	'http://'+ window.location.hostname+'/utility/gi-whiteboard/inc/session_mgr.php',
				dataType	:	'json',
				data		: 	login_data,
				success : function(json){
						// things to do if you get a successful return, not successful login
						if (json.login_error && json.login_error !== '') { // failed
							icoAlert = '<span class="ui-icon ui-icon-alert" style="float:left;margin:0 5px 50px 10px;"></span>';
							$('p.validateTips').html(icoAlert+json.login_error);
							$('p.validateTips').addClass("ui-state-error");
						} else {
							var login_cookie = json['login_cookie'];
							var session_cookie = $.cookie('nvsa_session');
							
							if (session_cookie == null && login_cookie !== '') $.cookie('nvsa_session',login_cookie,{expires:0,path:'/'});
							
							$('#LocalCrumbs span.login').html(json.login_html);
							$('#logout').button().on('click',function(evt) {
								evt.preventDefault();
								logout();
							});
							setBtnCSS();
							dlgLogin.dialog( "close" );
							window.location.reload();
						}
					}, // end function
				error: function(xhr, textStatus, errorThrown){
					if (xhr.hasOwnProperty('responseText')){
						alert('request failed\n' + xhr.responseText);
					} else {
						alert('request failed\n' + errorThrown);	
					}
				}
		   }); // end ajax call
	  
		  if ( closeIt == true ) {	 // add code to try logging on
			dlgLogin.dialog( "close" );
		  }
		  
		  return closeIt;
    }
	function logout(){
		// use ajax to logout;
		$.ajax({
			url		:	'http://'+ window.location.hostname +'/utility/gi-whiteboard/inc/session_mgr.php',
			type	:	'POST',
			dataType:	"json",
			data	:	{
				action:'logout'
				},
			success:		function(json){
					if ($.cookie('nvsa_session') !== null) $.cookie('nvsa_session',null,{path:'/'});
					dlgLogout.dialog("open");
					$('#LocalCrumbs span.login').html(json.login_html);
					$( "#login").button().on( "click", function(evt) {
						evt.preventDefault();
						dlgLogin.dialog( "open" );
					});
					setBtnCSS();
				}, // end function
			error: function(xhr, textStatus, errorThrown){
					if (xhr.hasOwnProperty('responseText')){
						alert('request failed\n' + xhr.responseText);
					} else {
						alert('request failed\n' + errorThrown);	
					}
					
				}
			});
	}
 
    dlgLogin = $( "#logging" ).dialog({
      autoOpen: false,
      height: 280,
      width: 380,
      modal: true,
      buttons: {
        "Login": tryLogin,
        Cancel: function() {
          dlgLogin.dialog( "close" );
        }
      },
	  open:  function(evt,ui){
		 $('.ui-state-error').removeClass('ui-state-error');	
		 $('p.validateTips').text('Please enter your NUID and Password just like you do when logging into your computer.');
	  },
      close: function() {
        form[0].reset();
        $('input').removeClass( "ui-state-error" );
      }
    });
	dlgLogout = $('#logging_out').dialog({
      autoOpen: false,
      height: 240,
      width: 350,
      modal: true,
      buttons: {
        Ok: function() {
          dlgLogout.dialog( "close" );
		  window.location.reload();
        }
      },
      close: function() {
        form[0].reset();
        $('input').removeClass( "ui-state-error" );
      }
    });
 
    form = dlgLogin.find( "form#user_login" ).on( "submit", function( event ) {
      event.preventDefault();
      tryLogin();
    });
 
    $( "#login").button().on( "click", function(evt) {
		evt.preventDefault();
		dlgLogin.dialog( "open" );
    });
	$('#logout').button().on('click',function(evt) {
		evt.preventDefault();
		logout();
	});
	setBtnCSS();

		
/*	$validator = new FormValidator(document.forms['casp']);
	$('.date').not('[required]').each(function(){
		$validator.WatchField($(this).attr('name'),'DT','xx/xx/xxxx')
	});
	$('.phone').not('[required]').each(function(){
		$validator.WatchField($(this).attr('name'),'LPH','xxx-xxx-xxxx')
	});
	$('[required]').each(function(){
		if ($(this).has('[placeholder]') ) {
			if ( $(this).hasClass('date') ){
				$validator.WatchField($(this).attr('name'),'DT',$(this).attr('placeholder'));
			}else if ($(this).hasClass('phone')){
				$validator.WatchField($(this).attr('name'),'LPH',$(this).attr('placeholder'));
			} else {
				$validator.WatchField($(this).attr('name'),'T',$(this).attr('placeholder'));
			} // end if has class
		} else {
			$validator.WatchField($(this).attr('name'));
		} // end if has placeholder attribute
	});
*/
});