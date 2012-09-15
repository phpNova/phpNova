/*
 * AJAX functions.  For site-specific functions, see main.js.
 */

function getHTTPObject()
{
	if ( typeof XMLHttpRequest != 'undefined' )
	{
		return new XMLHttpRequest();
	}
	else
	{
		try
		{
			return new ActiveXObject( "Msxml2.XMLHTTP" );
		}
		catch ( e )
		{
			try
			{
				return new ActiveXObject( "Microsoft.XMLHTTP" );
			}
			catch( e )
			{
				return false;
			}
		}
	}
}

function updateView( url, method, elementId, postData, parent )
{
	if ( url == "NULL" )
	{
		if ( parent == true )
		{
			window.parent.document.getElementById( elementId ).innerHTML = "";
		}
		else
		{
			document.getElementById( elementId ).innerHTML = "";
		}
		return;
	}
	
	var http = getHTTPObject();
	
	http.open( method, url, true );
	http.setRequestHeader( "User-Agent", "XMLHTTP/1.0" );
	if ( postData != '' )
	{
		http.setRequestHeader( "Content-Type", "application/x-www-form-urlencoded" );
	}
	http.onreadystatechange = function()
	{
		if ( http.readyState == 4 )
		{
			if ( parent == true )
			{
				window.parent.document.getElementById( elementId ).innerHTML = http.responseText;
			}
			else
			{
				document.getElementById( elementId ).innerHTML = http.responseText;
			}
			
			/* Rebuild any script tags so browser will execute them.  --Kris */
			if ( parent == true )
			{
				var scripts = window.parent.document.getElementById( elementId ).getElementsByTagName( "script" );
			}
			else
			{
				var scripts = document.getElementById( elementId ).getElementsByTagName( "script" );
			}
			var len = scripts.length;
			
			for ( var i = 0; i < len; i++ )
			{
				if ( parent == true )
				{
					var newscript = window.parent.document.createElement( "script" );
				}
				else
				{
					var newscript = document.createElement( "script" );
				}
				newscript.type = "text/javascript";
				newscript.text = scripts[i].text;
				
				if ( parent == true )
				{
					window.parent.document.getElementById( elementId ).appendChild( newscript );
				}
				else
				{
					document.getElementById( elementId ).appendChild( newscript );
				}
			}
		}
	}
	http.send( postData );
}

function loadURL( url, params, divid )
{
	document.getElementById( divid ).innerHTML = "<img src=\"view/loading.gif\" />";
	
	var baseurl = "view/view.php?url=";
	
	if ( url == '' )
	{
		try
		{
			url = document.getElementById( "url" ).value;
		}
		catch ( e )
		{
			return false;
		}
	}
	
	/* Note - Use "filters=<PHP func in filters.php>( string args )|<another func()>" etc to apply custom HTML filters.  --Kris */
	if ( params != '' )
	{
		url = encodeURIComponent( url ) + "&" + params;
	}
		
	updateView( baseurl + url, "GET", divid, '' );
	
	return true;
}

function loadTemplate( template, divid, silent, parent )
{
	if ( silent != true )
	{
		if ( parent == true )
		{
			window.parent.document.getElementById( divid ).innerHTML = "<img src=\"view/loading.gif\" />";
		}
		else
		{
			document.getElementById( divid ).innerHTML = "<img src=\"view/loading.gif\" />";
		}
	}
	
	var baseurl = "view/view.php?template=";
	
	if ( template == '' )
	{
		try
		{
			template = document.getElementById( "template" ).value;
		}
		catch ( e )
		{
			return false;
		}
	}
	
	/* Note - Template parsing occurs in templates.class.php; variables set in controller.class.php.  --Kris */
	updateView( baseurl + template, "GET", divid, '', parent );
	
	return true;
}

function loadTemplateSilently( template, divid )
{
	var baseurl = "view/view.php?template=";
	
	if ( template == '' )
	{
		try
		{
			template = document.getElementById( "template" ).value;
		}
		catch ( e )
		{
			return false;
		}
	}
	
	/* Note - Template parsing occurs in templates.class.php; variables set in controller.class.php.  --Kris */
	updateView( baseurl + template, "GET", divid, '' );
	
	return true;
}

function sendForm( form, divid )
{
	var postData = '';
	
	for ( i = 0; i < form.elements.length; i++ )
	{
		if ( postData != '' )
		{
			postData += "&";
		}
		
		postData += form.elements[i].name + "=" + encodeURIComponent( form.elements[i].value );
	}
	
	if ( divid == null )
	{
		divid = "viewerdiv";
	}
	
	if ( postData != '' )
	{
		postData += "&";
	}
	
	postData += "formid=" + encodeURIComponent( form.id );
	
	/* The controller will route the request based on the formid that was sent. --Kris */
	updateView( "view/view.php", "POST", divid, postData );
	
	return true;
}

function openAJAXWindow( template, newdivid, title, width, height, darkenbg, scaled, titlehidex, x, y, bgcolor, fontcolor, border, 
			titlebgcolor, titlefontcolor, titleborder, parentid )
{
	if ( darkenbg == true )
	{
		darkenpage();
	}
	
	if ( width == null )
	{
		width = 400;
	}
	if ( height == null )
	{
		height = 300;
	}
	
	if ( x == null )
	{
		x = ((screen.width) / 2) - (width / 2);
	}
	if ( y == null )
	{
		y = ((screen.height) / 2) - (height / 2);
	}
	
	if ( x < 0 )
	{
		x = 0;
	}
	if ( y < 0 )
	{
		y = 0;
	}
	
	/* Parent is needed for maximum browser compatibility when "closing" (removing) this "window" (div).  --Kris */
	if ( parentid == null )
	{
		parentid = "ajaxwindowparent";
	}
	
	newdiv = document.createElement( "div" );
	
	newdiv.setAttribute( "id", newdivid );
	
	/* If window can't be force-closed, mark it with the AJAX safe window class so closeAllAJAXWindows() will skip it.  --Kris */
	if ( titlehidex == 1 )
	{
		newdiv.setAttribute( "class", "ajaxwindow ajaxsafewindow" );
	}
	else
	{
		newdiv.setAttribute( "class", "ajaxwindow" );
	}
	
	newdiv.style.height = height + "px";
	newdiv.style.width = width + "px";
	newdiv.style.top = y + "px";
	newdiv.style.left = x + "px";
	
	/* Display a title only if one exists and the div isn't too tiny.  --Kris */
	if ( title != null && height > 50 && width > 50 )
	{
		titlediv = document.createElement( "div" );
		
		titlediv.setAttribute( "id", newdivid + "_title" );
		titlediv.setAttribute( "class", "ajaxwindowtitle" );
		
		if ( titlebgcolor != null )
		{
			titlediv.style.backgroundColor = titlebgcolor;
		}
		if ( titlefontcolor != null )
		{
			titlediv.style.color = titlefontcolor;
		}
		if ( titleborder != null )
		{
			titlediv.style.border = titleborder;
		}
		
		newdiv.appendChild( titlediv );
		
		loadTemplate( "windowtitle&title=" + title + "&windowdivid=" + newdivid + "&hidex=" + titlehidex, newdivid + "_title", true );
	}
	
	bodydiv = document.createElement( "div" );
	
	bodydiv.setAttribute( "id", newdivid + "_body" );
	bodydiv.setAttribute( "class", "ajaxwindowbody" );
	
	/* Apply border and bgcolor to title (does NOT override titleborder/titlebgcolor!) and body instead of entire div.  Useful in creating scaled effect.  --Kris */
	if ( scaled == true )
	{
		if ( bgcolor != null )
		{
			bodydiv.style.backgroundColor = bgcolor;
		}
		else
		{
			bodydiv.style.backgroundColor = "#FFFFFF";
		}
		
		if ( border != null )
		{
			bodydiv.style.border = border;
			
			if ( titleborder == null )
			{
				titlediv.style.border = border;
			}
		}
		else
		{
			bodydiv.style.border = "2px solid black";
			
			if ( titleborder == null )
			{
				titlediv.style.borderTop = "2px solid black";
				titlediv.style.borderLeft = "2px solid black";
				titlediv.style.borderRight = "2px solid black";
			}
		}
	}
	else
	{
		if ( bgcolor != null )
		{
			newdiv.style.backgroundColor = bgcolor;
		}
		else
		{
			newdiv.style.backgroundColor = "#FFFFFF";
		}
		
		if ( border != null )
		{
			newdiv.style.border = border;
		}
		else
		{
			newdiv.style.border = "2px solid black";
		}
	}
	
	if ( fontcolor != null )
	{
		newdiv.style.color = fontcolor;
	}
	
	newdiv.appendChild( bodydiv );
	
	parentdiv = document.getElementById( parentid );
	
	parentdiv.appendChild( newdiv );
	
	loadTemplate( template + "&ajaxwindowdivid=" + newdivid + "_body", newdivid + "_body" );
}

function closeAJAXWindow( divid, leavedark )
{
	if ( leavedark != true )
	{
		lightenpage();
	}
	
	return ( function( x ) { x.parentNode.removeChild( x ); } )( document.getElementById( divid ) );
}

/* Close ALL AJAX windows that are currently open, if any.  --Kris */
function closeAllAJAXWindows( overridesafe )
{
	var trash = getElementsByClassName( "ajaxwindow" );
	
	var allclosed = true;
	for ( var i = 0; i < trash.length; i++ )
	{
		if ( trash[i].className.indexOf( "ajaxsafewindow" ) != -1 
			&& overridesafe != true )
		{
			allclosed = false;
		}
		else
		{
			closeAJAXWindow( trash[i].id, true );
		}
	}
	
	if ( allclosed == true )
	{
		lightenpage();
	}
}

function darkenpage()
{
	document.getElementById( "darkenbackground" ).style.zIndex = 99998;
	document.getElementById( "darkenbackground" ).style.visibility = "visible";
}

function lightenpage()
{
	document.getElementById( "darkenbackground" ).style.zIndex = -9998;
	document.getElementById( "darkenbackground" ).style.visibility = "hidden";
}

/* For older browsers that don't support it.  Emulates HTML 5 parsing behavior.  --Kris */
function getElementsByClassName( classname, node ) 
{
	if ( node == null )
	{
		node = document;
	}
	
	if ( node.getElementsByClassName )
	{
		return node.getElementsByClassName( classname );
	}
	else
	{
		var eles = [];
		
		var working = document.getElementsByTagName( "*" );
		
		var regex = new RegExp( "(^|\s)" + classname + "(\s|$)" );
		for ( var i = 0; i < working.length; i++ )
		{
			if ( regex.test( working[i].className ) )
			{
				eles.push( working[i] );
			}
		}
		
		return eles;
	}
}
