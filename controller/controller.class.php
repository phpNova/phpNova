<?php

/**
 * The controller.  See MAVAX documentation for more info.
 */

class controller
{
	public function __construct()
	{
		require_once( "../includes.php" );
		require_once( "../config.class.php" );
		
		$args = func_get_args();
		
		if ( empty( $args ) || !isset( $args[0] ) || ( !isset( $args[0]["url"] ) && !isset( $args[0]["template"] ) && !isset( $args[0]["formid"] ) ) )
		{
			$this->template = "400";  //Bad request.  --Kris
			$this->status = 400;
		}
		else
		{
			foreach ( $args as $argarr )
			{
				foreach ( $argarr as $key => $value )
				{
					$this->$key = $value;
				}
			}
			
			$this->status = 200;
			
			if ( isset( $_GET["template"] ) && trim( $_GET["template"] ) != NULL && !isset( $this->template ) )
			{
				$template = new Templates();
				if ( $template->exists( $_GET["template"] ) )
				{
					$this->template = $_GET["template"];
				}
				else
				{
					$this->template = "404";  //File not found.  --Kris
					$this->status = 404;
				}
			}
		}
		
		$this->config = new Config();
		
		$this->model = new model( array( "config" => $this->config ) );
	}
	
	public function __toString()
	{
		return "(PHP Object)";
	}
	
	/* Determine the vars to be set for each template.  --Kris */
	function set_vars( $clear = TRUE )
	{
		require_once( "../includes.php" );
		
		$l = &$this->config->languages;
		$log = &$this->model->log;
		
		if ( !isset( $this->template ) 
			&& !isset( $this->formid ) )
		{
			return FALSE;
		}
		
		$template = new Templates();
		
		/* If we have a formid, it means there's form data to process.  Template need not be specified.  --Kris */
		if ( isset( $this->formid ) )
		{
			$log->hook( "controller::form_submit", __METHOD__, $l->t( "log_submission_for_formid" ) . " : " . $this->formid );
			switch( strtolower( $this->formid ) )
			{
				default:
					$this->route_template = "400";
					$this->errmsg = $l->t( "err_unrecognized_formid" ) . " : " . "'" . $this->formid . "'";
					break;
				case "login":
					if ( !isset( $this->username ) || !isset( $this->password ) )
					{
						$this->route_template = "400";
						$this->errmsg = $l->t( "err_invalid_form_data" ) . " : " . $this->formdid;
						break;
					}
					
					$res = $this->model->user->login( $this->username, $this->password );
					
					if ( $res["Success"] == TRUE )
					{
						$this->route_template = "login_success";
					}
					else
					{
						$this->route_template = "login";
						$this->errmsg = $l->t( "err_login_failed" ) . " : " . $res["Reason"];
					}
					break;
				case "forgot_password":
					if ( !isset( $this->username_email ) )
					{
						$this->route_template = "400";
						$this->errmsg = $l->t( "err_invalid_form_data" ) . " : " . $this->formdid;
						break;
					}
					
					$res = $this->model->user->forgot_password( $this->username_email );
					
					if ( $res["Success"] == TRUE )
					{
						$this->route_template = "login";
						$this->msg_text = $l->t( "email_sent" );
						$this->msg = $this->clone_for( "msg" );
					}
					else
					{
						$this->route_template = "forgot_password";
						$this->errmsg = $l->t( "err_err" ) . " : " . $res["Reason"];
					}
					break;
				case "profile_edit":
					if ( !isset( $this->email ) || !isset( $this->date_format ) || !isset( $this->userid ) )
					{
						$this->route_template = 400;
						$this->errmsg = $l->t( "err_invalid_form_data" ) . " : " . $this->formid;
						break;
					}
					
					if ( trim( $this->date_format ) == NULL )
					{
						$this->errmsg = $l->t( "err_specify_date" );
					}
					else if ( filter_var( $this->email, FILTER_VALIDATE_EMAIL ) === FALSE )
					{
						$this->errmsg = $l->t( "err_specify_email" );
					}
					else
					{
						$users_query = "update users set email = ? where userid = ?";
						$users_params = array( $this->email, $this->userid );
						
						$profiles_query = "update profiles set date_format = ? where userid = ?";
						$profiles_params = array( $this->date_format, $this->userid );
						
						$this->config->sql->query( $users_query, $users_params, SQL_RETURN_AFFECTEDROWS );
						$this->config->sql->query( $profiles_query, $profiles_params, SQL_RETURN_AFFECTEDROWS );
						
						$this->model->user->populate_session();
						
						$this->msg = $l->t( "profile_settings_saved" );
					}
					
					$this->route_template = "profile";
					break;
				case "reset_password":
					if ( !isset( $this->password ) || !isset( $this->password_retype ) )
					{
						$this->route_template = 400;
						$this->errmsg = $l->t( "err_invalid_form_data" ) . " : " . $this->formdid;
						break;
					}
					
					if ( isset( $this->code ) && $this->code != NULL )
					{
						$userdata = $this->model->user->load_data( "phpsessid = ?", array( $this->code ), "username" );
					}
					else
					{
						$userdata = $this->model->user->load_data();
					}
					
					$oldpass = ( isset( $this->old_password ) ? $this->old_password : NULL );
					$code = ( isset( $this->code ) ? $this->code : NULL );
					
					$res = $this->model->user->change_password( $userdata[0]["username"], $this->password, $this->password_retype, $oldpass, $code );
					
					if ( $res["Success"] == TRUE )
					{
						$res = $this->model->user->login( $userdata[0]["username"], $this->password );
						
						if ( !isset( $res["Success"] ) || $res["Success"] !== TRUE )
						{
							$this->route_template = "reset_password_form";
							$this->errmsg = $l->t( "err_err" ) . " : " . $res["Reason"];
						}
						else
						{
							$this->route_template = "text";
							$this->text = $l->t( "password_change_successful" );
							
							$this->text .= "\r\n" . $this->clone_for( "timer", array( "timer" => "index", 
															"target" => "viewerdiv", 
															"wait" => 1000 ) );
						}
					}
					else
					{
						$this->route_template = "reset_password_form";
						$this->errmsg = $l->t( "err_err" ) . " : " . $res["Reason"];
					}
					break;
			}
			
			/* If no template specified, use form route template.  If no form route template specified in above switch, throw error.  --Kris */
			$this->route_template = ( isset( $this->route_template ) ? $this->route_template : "400" );
			$this->template = ( isset( $this->template ) ? $this->template : $this->route_template );
			
			/* Check again to make sure that the template exists.  Return 404 if it doesn't.  --Kris */
			if ( $template->exists( $this->template ) == FALSE )
			{
				$this->template = "404";  //File not found.  --Kris
				$this->status = 404;
			}
		}
		
		if ( $clear == TRUE || !isset( $this->templatevars ) || !is_array( $this->templatevars ) )
		{
			$this->templatevars = array();
		}
		
		$log->hook( "controller::template_dispatch", __METHOD__, $l->t( "log_controller_dispatch" ) . " : " . $this->template, FALSE );
		switch ( $this->template )
		{
			default:
				return FALSE;
				break;
			case "blank":
				return TRUE;
				break;
			case "400":
				$this->tempaltevars["getvars"] = $_GET;
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : $l->t( "err_400" ) );
				break;
			case "403":
				$this->tempaltevars["getvars"] = $_GET;
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : $l->t( "err_403" ) );
				break;
			case "404":
				$this->templatevars["templatefile"] = $template->filename( $this->template );
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : $l->t( "err_404" ) );
				break;
			case "forgot_password":
				$this->templatevars["submit"] = ( isset( $this->submit ) ? $this->submit : $l->t( "submit" ) );
				$this->templatevars["ajaxwindowdivid"] = ( isset( $this->ajaxwindowdivid ) ? $this->ajaxwindowdivid : NULL );
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
			case "home":
			case "index":
				$this->templatevars["script"] = NULL;
				
				if ( $this->model->user->auth == TRUE )
				{
					$this->templatevars["authcheck"] = NULL;
					
					if ( isset( $this->closeall ) && $this->closeall == 1 )
					{
						$script = "<script language=\"JavaScript\">\r\n";
						//$script .= "closeAllAJAXWindows( true );\r\n";
						$script .= "window.top.location=\"index.php\"";
						$script .= "</script>\r\n";
						
						$this->templatevars["script"] .= $script;
					}
				}
				else
				{
					/* Auto-popup the login form if not authenticated.  --Kris */
					$params = array();
					$params["poptemplate"] = "login";
					$params["title"] = $l->t( "login" );
					$params["width"] = 600;
					$params["height"] = 400;
					$params["darkenbg"] = "true";
					$params["scaled"] = "true";
					$params["submit"] = $l->t( "login" );
					$params["hidex"] = 1;
					$params["hidecontent"] = 1;
					$params["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
					$this->templatevars["authcheck"] = $this->clone_for( "popup", $params );
				}
				$this->templatevars["username"] = ( isset( $_SESSION["username"] ) ? $this->clone_for( "username_link" ) : "You are not logged-in." );
				$this->templatevars["msg"] = ( isset( $this->msg ) ? $this->msg : NULL );
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
			case "login":
				$this->templatevars["submit"] = ( isset( $this->submit ) ? $this->submit : "Login" );
				$this->templatevars["hidecontent"] = ( isset( $this->hidecontent ) ? $this->hidecontent : 0 );
				$this->templatevars["ajaxwindowdivid"] = ( isset( $this->ajaxwindowdivid ) ? $this->ajaxwindowdivid : NULL );
				$this->templatevars["msg"] = ( isset( $this->msg ) ? $this->msg : NULL );
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
			case "logout":
				if ( $this->model->user->logout() == FALSE )
				{
					$this->templatevars["errmsg"] = $l->t( "err_logging_out_user" );
					$this->templatevars["reload"] = NULL;
				}
				
				$this->templatevars["reload"] = $this->clone_for( "reload" );
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
			case "msg":
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				$this->templatevars["msg_text"] = ( isset( $this->msg_text ) ? $this->msg_text : $this->errmsg );
				break;
			case "popup":
				$this->templatevars["poptemplate"] = $this->poptemplate;
				$this->templatevars["title"] = $this->title;
				$this->templatevars["width"] = $this->width;
				$this->templatevars["height"] = $this->height;
				$this->templatevars["darkenbg"] = $this->darkenbg;
				$this->templatevars["scaled"] = $this->scaled;
				$this->templatevars["hidex"] = ( isset( $this->hidex ) ? $this->hidex : 0 );
				
				/* Cycle through all properties and pass as params for AJAX window template, even if unused.  --Kris */
				$thisobj = get_object_vars( $this );
				
				$params = NULL;
				foreach ( $thisobj as $key => $value )
				{
					if ( strcmp( $key, "template" ) && strcmp( $key, "config" ) )
					{
						$params .= '&' . $key . '=' . $value;
					}
				}
				
				$this->templatevars["params"] = $params;
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
			case "profile":
				if ( !isset( $this->username ) && !isset( $_SESSION["username"] ) )
				{
					$this->template = 400;
					$this->errmsg = $l->t( "err_400" );
					$this->set_vars();
					break;
				}
				
				$this->username = ( isset( $this->username ) ? $this->username : $_SESSION["username"] );
				
				$userdata = $this->model->user->load_data( "username = ?", array( $this->username ) );
				
				if ( $userdata == FALSE || !is_array( $userdata ) || empty( $userdata ) 
					|| !isset( $userdata[0] ) || !is_array( $userdata[0] ) || empty( $userdata[0] ) )
				{
					$this->template = 400;
					$this->errmsg = $l->t( "err_400" );
					$this->set_vars();
					break;
				}
				
				$profiledata = $this->model->user->load_profile( $userdata[0]["userid"] );
				
				if ( $profiledata == FALSE || !is_array( $profiledata ) || empty( $profiledata ) 
					|| !isset( $profiledata[0] ) || !is_array( $profiledata[0] ) || empty( $profiledata[0] ) )
				{
					$this->template = 400;
					$this->errmsg = $l->t( "err_400" );
					$this->set_vars();
					break;
				}
				
				/* Just make all retrieved columns available to the template.  --Kris */
				foreach ( $userdata[0] as $key => $val )
				{
					$this->templatevars[$key] = $val;
				}
				
				foreach ( $profiledata[0] as $key => $val )
				{
					$this->templatevars[$key] = $val;
				}
				
				$this->templatevars["ajaxwindowdivid"] = ( isset( $this->ajaxwindowdivid ) ? $this->ajaxwindowdivid : NULL );
				$this->templatevars["submit"] = ( isset( $this->submit ) ? $this->submit : "Save Changes" );
				$this->templatevars["msg"] = ( isset( $this->msg ) ? $this->msg : NULL );
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
			case "reset_password":
				$params = array();
				$params["poptemplate"] = "reset_password_form";
				$params["params"] = "&code=" . ( isset( $this->code ) ? $this->code : NULL );
				$params["title"] = $l->t( "title_reset_password" );
				$params["width"] = 600;
				$params["height"] = 400;
				$params["darkenbg"] = "true";
				$params["scaled"] = "true";
				$params["submit"] = $l->t( "title_change_password" );
				$params["hidex"] = ( isset( $this->hidex ) ? $this->hidex : 1 );
				$params["hidecontent"] = ( isset( $this->hidecontent ) ? $this->hidecontent : 1 );
				$params["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				$this->templatevars["popup"] = $this->clone_for( "popup", $params );
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
			case "reset_password_form":
				if ( $this->model->user->auth == FALSE && ( !isset( $this->code ) || trim( $this->code ) == NULL 
						|| $this->model->user->phpsessid_match( $this->code ) == FALSE ) )
				{
					$this->template = 403;
					$this->errmsg = $l->t( "err_403" );
					$this->set_vars();
					break;
				}
				
				if ( $this->model->user->auth == TRUE )
				{
					$this->templatevars["old_password_caption"] = $this->clone_for( "old_password_caption" );
					$this->templatevars["old_password_field"] = $this->clone_for( "old_password_field" );
				}
				else
				{
					$this->templatevars["old_password_caption"] = NULL;
					$this->templatevars["old_password_field"] = NULL;
				}
				$this->templatevars["ajaxwindowdivid"] = ( isset( $this->ajaxwindowdivid ) ? $this->ajaxwindowdivid : NULL );
				$this->templatevars["code"] = $this->code;
				$this->templatevars["submit"] = ( isset( $this->submit ) ? $this->submit : $l->t( "submit" ) );
				$this->templatevars["msg"] = ( isset( $this->msg ) ? $this->msg : NULL );
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
			case "text":
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				$this->templatevars["text"] = ( isset( $this->text ) ? $this->text : $this->errmsg );
				break;
			case "timer":
				if ( $this->authcheck() == FALSE )
				{
					break;
				}
				
				if ( !isset( $this->timer ) )
				{
					$this->template = 400;
					$this->errmsg = $l->t( "err_no_timer_specified" );
					$this->set_vars();
					break;
				}
				
				if ( !isset( $this->timer_attempt ) )
				{
					$this->timer_attempt = 1;
				}
				else
				{
					$this->timer_attempt++;
				}
				
				switch( trim( strtolower( $this->timer ) ) )
				{
					default:
						$this->template = 400;
						$this->errmsg = $l->t( "err_undefined_timer_specified" ) . " : '" . $this->timer . "'!";
						$this->set_vars();
						break;
					case "index":
						if ( !isset( $this->target ) )
						{
							$this->target = "viewerdiv";
						}
						
						if ( !isset( $this->wait ) )
						{
							$this->wait = 0;
						}
						
						$this->templatevars["refresh"] = "index&closeall=1";
						$this->templatevars["target"] = $this->target;
						$this->templatevars["wait"] = $this->wait;
						break;
				}
				
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
			case "username_link":
				if ( ( !isset( $this->username ) && !isset( $_SESSION["username"] ) )
					|| ( !isset( $this->userid ) && !isset( $_SESSION["userid"] ) ) )
				{
					$this->template = 400;
					$this->errmsg = $l->t( "err_400" );
					$this->set_vars();
					break;
				}
				
				$this->templatevars["userid"] = ( isset( $this->userid ) ? $this->userid : $_SESSION["userid"] );
				$this->templatevars["username"] = ( isset( $this->username ) ? $this->username : $_SESSION["username"] );
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
			case "window_embedded":
				$this->templatevars["title"] = $this->title;
				$this->templatevars["templateem"] = $this->templateem;
				$this->templatevars["params"] = ( isset( $this->params ) ? $this->params : NULL );
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
			case "windowtitle":
				/* If title is NULL, the title bar will not be displayed.  --Kris */
				if ( !isset( $this->title ) )
				{
					$this->title = NULL;
				}
				
				if ( !isset( $this->windowdivid ) )
				{
					$this->windowdivid = NULL;
				}
				
				$this->templatevars["title"] = $this->title;
				$this->templatevars["windowdivid"] = $this->windowdivid;
				$this->templatevars["hidex"] = ( isset( $this->hidex ) ? $this->hidex : 0 );
				$this->templatevars["errmsg"] = ( isset( $this->errmsg ) ? $this->errmsg : NULL );
				break;
		}
		
		return TRUE;
	}
	
	function authcheck()
	{
		if ( $this->model->user->auth() == FALSE )
		{
			$this->template = "window_embedded";
			$this->title = $this->config->languages->t( "login" );
			$this->templateem = "login";
			$this->params = NULL;
			$this->set_vars();
			
			return FALSE;
		}
		else
		{
			return TRUE;
		}
	}
	
	function send_view( $returndata = FALSE, $clearvars = FALSE )
	{
		require( "includes.php" );
		
		if ( isset( $this->template ) )
		{
			$this->model->log->hook( "controller::send_view", __METHOD__, $this->config->languages->t( "log_requesting_template" ) . " : " . $this->template, FALSE );
		}
		
		$return = FALSE;
		if ( $this->status == 200 )
		{
			/*$session = new session();
			foreach ( get_object_vars( $this ) as $key => $value )
			{
				$session->set( $key, $value );
			}*/
			
			/* Display either the template or URL specified.  --Kris */
			if ( isset( $this->url ) )
			{
				if ( isset( $this->filters ) && $this->filters != NULL )
				{
					print $this->model->parse_url( $this->url, explode( "|", $this->filters ) );
				}
				else
				{
					print $this->model->parse_url( $this->url );
				}
			}
			else if ( isset( $this->template ) || isset( $this->formid ) )
			{
				$template = new Templates();
				$this->set_vars( $clearvars );
				if ( isset( $this->templatevars ) && is_array( $this->templatevars ) )
				{
					foreach ( $this->templatevars as $var => $val )
					{
						$template->set( $var, $val );
					}
				}
				
				$return = $template->display( $this->template, $returndata );
				$template->clear();
			}
		}
		else if ( isset( $this->template ) )
		{
			$template = new Templates();
			$this->set_vars( $clearvars );
			if ( isset( $this->templatevars ) && is_array( $this->templatevars ) )
			{
				foreach ( $this->templatevars as $var => $val )
				{
					$template->set( $var, $val );
				}
			}
			
			$return = $template->display( $this->template, $returndata );
			$template->clear();
		}
		
		$this->model->log->hook( "controller::send_view", __METHOD__, $this->config->languages->t( "log_template_sent" ) . " : " . $this->template . "; " . $this->config->languages->t( "log_reason" ) . " = " . ( $return == TRUE ? "TRUE" : "FALSE" ) . "\r\n<br />Templatevars = " . var_export( $this->templatevars, TRUE ), FALSE );
		return $return;
	}
	
	/* Copy the the object instance (array properties snapshot; not a pointer!) for new embedded template and return view output as string.  --Kris */
	protected function clone_for( $template, $params = array(), $clearvars = FALSE, $returnfull = FALSE )
	{
		$this->model->log->hook( "controller::clone_for", __METHOD__, $this->config->languages->t( "log_clone_requested" ) . " : " . $template, FALSE );
		
		$thisobj = get_object_vars( $this );
		$thisobj["template"] = $template;
		
		/* To prevent infinite recursion issues.  --Kris */
		unset( $thisobj["formid"] );
		
		$controller = new Controller( $thisobj );
		
		foreach ( $params as $key => $value )
		{
			$controller->$key = $value;
		}
		
		$res = $controller->send_view( TRUE, $clearvars );
		
		return ( $returnfull == TRUE ? $res : $res["Reason"] );
	}
}
