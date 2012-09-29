<?php

/**
 * The model.  See MAVAX documentation for more info.
 */

class model
{
	public function __construct()
	{
		$args = func_get_args();
		
		foreach ( $args as $argarr )
		{
			foreach ( $argarr as $key => $value )
			{
				$this->$key = $value;
			}
		}
		
		$this->ok = TRUE;
		$this->errors = array();
		
		$this->load_config_main();
		$this->load_config_active_modules();
		
		// TODO - Load and instantiate satellite classes.  --Kris
	}
	
	public function __toString()
	{
		return "(PHP Object)";
	}
	
	/* Load and validate the phpNova configuration file.  --Kris */
	private function load_config_main()
	{
		$ini = parse_ini_file( $this->config->ini_main, TRUE );
		
		if ( $ini === FALSE )
		{
			$this->ok = FALSE;
			$this->errors[] = "Error loading phpNova configuration file!";
			
			return;
		}
		
		/* Required sections and their required entries.  --Kris */
		$req = array();
		
		$req["Main"] = array();
		$req["Main"][] = "Version";
		$req["Main"][] = "Base_Path";
		
		$req["Module_Origins"] = array();
		$req["Module_Origins"][] = "phpNova";
		$req["Module_Origins"][] = "phpTemplates";
		
		$req["Module_Paths"] = array();
		$req["Module_Paths"][] = "phpTemplates";
		
		foreach ( $req as $section => $directives )
		{
			if ( !isset( $ini[$section] ) )
			{
				$this->ok = FALSE;
				$this->errors[] = "Main INI file missing required section : " . $section;
			}
			else
			{
				foreach ( $directives as $setting )
				{
					if ( !isset( $ini[$section][$setting] ) )
					{
						$this->ok = FALSE;
						$this->errors[] = "Main INI (section '$section') missing required setting : " . $setting;
					}
				}
			}
		}
		
		$this->phpnova_ini = $ini;
	}
	
	/* Load the modules list configuration file and install as needed.  --Kris */
	private function load_config_active_modules()
	{
		/* Don't attempt to load the active modules if our configuration is bad.  --Kris */
		if ( !isset( $this->phpnova_ini ) || $this->ok == FALSE )
		{
			return;
		}
		
		$ini = parse_ini_file( $this->config->ini_modules );
		
		if ( $ini === FALSE )
		{
			$this->ok = FALSE;
			$this->errors[] = "Error loading modules list configuration file!";
			
			return;
		}
		
		/* Verify that the local paths exist and are accessible.  --Kris */
		// Note - Writability will only be evaluated on an as-needed basis.
		if ( !file_exists( $this->phpnova_ini["Main"]["Base_Path"] ) 
			|| !is_readable( $this->phpnova_ini["Main"]["Base_Path"] ) 
			|| !is_dir( $this->phpnova_ini["Main"]["Base_Path"] )
		{
			$this->ok = FALSE;
			$this->errors[] = "Main::Base_Path does not exist or is not readable!";
		}
		else
		{
			foreach ( $ini as $module => $enabled )
			{
				if ( $enabled != 1 )
				{
					continue;
				}
				else if ( !isset( $this->phpnova_ini["Module_Paths"][$module] ) )
				{
					$this->ok = FALSE;
					$this->errors[] = "Unrecognized module : " . $module;
				}
				else
				{
					$dir = $this->phpnova_ini["Main"]["Base_Path"] . $this->phpnova_ini["Module_Paths"][$module];
					
					if ( !file_exists( $dir ) 
						|| !is_readable( $dir ) 
						|| !is_dir( $dir ) )
					{
						$this->ok = FALSE;
						$this->errors[] = "Unable to locate module : " . $module;
					}
				}
			}
		}
		
		/* If everything's good, load the modules.  --Kris */
		// Note - Any non-fatal errors from here will populate this->errors but will NOT set this->ok to FALSE.  --Kris
		if ( $this->ok == TRUE )
		{
			$this->modules = array();
			
			foreach ( $ini as $module => $enabled )
			{
				if ( $enabled == 1 )
				{
					$dir = $this->phpnova_ini["Main"]["Base_Path"] . $this->phpnova_ini["Module_Paths"][$module];
					
					/* The hooks.ini file will tell us how/where to include this module.  --Kris */
					if ( file_exists( $dir . "/hooks.ini" ) 
						&& is_readable( $dir . "/hooks.ini" ) 
						&& is_file( $dir . "/hooks.ini" ) )
					{
						
					}
					/* If no hooks.ini present, assume (lowercase: template_name).class.php with (template_name) as class name.  --Kris */
					else
					{
						try
						{
							require( $dir . '/' . strtolower( $module ) . ".class.php" );
						}
						catch
						{
							$this->errors[] = "No hooks.ini found and unable to guess include for module : " . $module;
							
							continue;
						}
						
						try
						{
							$this->modules[$module] = new $module;
						}
						catch
						{
							$this->errors[] = "No hooks.ini found and unable to instantiate class for module : " . $module;
							
							continue;
						}
					}
				}
			}
		}
	}
	
	/* Funnel to Abstraction::timespan().  --Kris */
	public function timespan( $seconds, $include_zeroes = FALSE )
	{
		require_once( "phpAbstractions/abstraction.class.php" );
		
		return Abstraction::timespan( $seconds );
	}
	
	/* Convert the given array columns (presumed to be timestamps!) to the given date/time format.  --Kris */
	public function format_date_keys( &$arrobjs, $format = "Y-m-d h:i:s A (T)", $exclude_zero = FALSE, $fix_null = TRUE )
	{
		foreach ( $arrobjs as &$arrobj )
		{
			if ( trim( $arrobj ) == NULL )
			{
				if ( $fix_null == TRUE )
				{
					$arrobj = 0;
				}
				else
				{
					continue;
				}
			}
			else if ( !is_numeric( $arrobj ) )
			{
				$arrobj = $this->config->languages->t( "err_invalid_timestamp" ) . " : $arrobj";
				
				continue;
			}
			
			if ( $arrobj > 0 || $exclude_zero == FALSE )
			{
				$arrobj = date( $format, $arrobj );
			}
		}
		
		return TRUE;
	}
	
	/* Convert the given array columns (presumed to be seconds!) to the appropriate timespan format.  --Kris */
	public function format_timespan_keys( &$arrobjs, $include_zeroes = FALSE )
	{
		foreach ( $arrobjs as &$arrobj )
		{
			if ( trim( $arrobj ) == NULL )
			{
				$arrobj = 0;
			}
			else if ( !is_numeric( $arrobj ) )
			{
				$arrobj = $this->config->languages->t( "err_invalid_timespan" ) . " : $arrobj";
				
				continue;
			}
			
			$arrobj = $this->timespan( $arrobj, $include_zeroes );
		}
		
		return TRUE;
	}
	
	/* Convert the given array columns (presumed to be bytes!) to the given metric unit.  --Kris */
	public function format_bytes_keys( &$arrobjs, $format = "auto", $round = 3, $add_labels = TRUE )
	{
		/* Ordered list of recognized formats.  Corresponds to $bytes * (1024 ^ $key).  --Kris */
		$units = array();
		$units[] = "B";
		$units[] = "KB";
		$units[] = "MB";
		$units[] = "GB";
		$units[] = "TB";
		$units[] = "PB";
		$units[] = "EB";
		$units[] = "ZB";
		$units[] = "YB";
		
		/* Flip the array so we can reference the exponents by format label.  --Kris */
		$units_flipped = $units;
		
		$units = array_flip( $units );
		
		foreach ( $arrobjs as &$arrobj )
		{
			/* If "auto", will use the highest recognized format that returns >= 1.  --Kris */
			if ( strcasecmp( $format, "auto" ) == 0 )
			{
				$useexp = NULL;
				foreach ( $units as $unit => $exp )
				{
					if ( $arrobj / pow( 1024, $exp ) < 1 )
					{
						$useexp = ( $exp > 0 ? ($exp - 1) : 0 );
						break;
					}
					else
					{
						$useexp = $exp;  // So if it's the largest recognized unit, it'll go with that.  --Kris
					}
				}
				
				$use = $units_flipped[$useexp];
			}
			else
			{
				$use = $format;
			}
			
			$arrobj = $arrobj / pow( 1024, $units[$use] );
			
			if ( $round !== FALSE )
			{
				$arrobj = round( $arrobj, $round );
			}
			
			if ( $add_labels == TRUE )
			{
				$arrobj = $arrobj . ' ' . $use;
			}
		}
		
		return TRUE;
	}
	
	/* Since PHP seems to be struggling with this, here's a little help from me.  --Kris */
	public function json_decode_recursive( $data = array() )
	{
		$out = array();
		foreach ( $data as $key => $value )
		{
			if ( is_array( $value ) )
			{
				$out[$key] = $this->json_decode_recursive( $value );
			}
			/* PHP is supposed to recursively handle this but it's not for some reason.  Probably a bug.  This way we'll be covered either way.  --Kris */
			else if ( is_array( json_decode( $value, TRUE ) ) )
			{
				$out[$key] = $this->json_decode_recursive( json_decode( $value, TRUE ) );
			}
			else
			{
				$out[$key] = $value;
			}
		}
		
		return $out;
	}
	
	/* Applies user-specified filters to the retrieved HTML source.  See filters.php for more information.  --Kris */
	public function apply_filters( $data, $filters )
	{
		require_once( "filters.php" );
		
		foreach ( $filters as $filter )
		{
			preg_match( '/[^\(]+/', $filter, $func_name_raw );
			preg_match( '/(?<=\()[^\)]*(?=\))/', $filter, $args_raw );
			
			$func_name = $func_name_raw[0];
			$args = $args_raw[0];
			
			$func_args_raw = explode( ",", $args );
			
			$func_args = array();
			$func_args[] = $data;
			foreach ( $func_args_raw as $arg )
			{
				$func_args[] = trim( $arg );
			}
			
			$data = call_user_func_array( array( 'filters', $func_name ), $func_args );
		}
		
		return $data;
	}
}
