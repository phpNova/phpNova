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
			|| !is_dir( $this->phpnova_ini["Main"]["Base_Path"] ) )
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
				else if ( strpos( $module, ' ' ) !== FALSE )
				{
					$this->ok = FALSE;
					$this->errors[] = "Invalid module name '$module' : Modules may not contain spaces!";
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
				$this->modules[$module] = new stdClass();  // PHP's version of an empty generic object.  --Kris
				$this->modules[$module]->ok = TRUE;
				
				if ( $enabled == 1 )
				{
					$dir = $this->phpnova_ini["Main"]["Base_Path"] . $this->phpnova_ini["Module_Paths"][$module];
					
					/* The hooks.ini file will tell us how/where to include this module.  --Kris */
					if ( file_exists( $dir . "/hooks.ini" ) 
						&& is_readable( $dir . "/hooks.ini" ) 
						&& is_file( $dir . "/hooks.ini" ) )
					{
						$hooks = parse_ini_file( $dir . "/hooks.ini" );
						
						if ( $hooks === FALSE )
						{
							$this->errors[] = "Parsing failed for '$dir/hooks.ini'!  Module skipped.";
							
							continue;
						}
						
						/* First pass, just handle the includes.  This allows non-sequential ordering of directives.  --Kris */
						foreach ( $hooks as $cmd => $value )
						{
							switch( trim( strtolower( $cmd ) ) )
							{
								default:
									if ( strcasecmp( substr( trim( $cmd ), 0, 11 ), "class_args_" ) )
									{
										$this->errors[] = "Unrecognized directive '$cmd' in '$dir/hooks.ini'.  Line skipped.";
									}
									
									break;
								case "class_require":
								case "class_include":
									break;
								case "require":
									try
									{
										require( ( strcmp( substr( $value, 0, 1 ), '/' ) ? $dir . '/' : NULL ) . $value );
									}
									catch ( Exception $e )
									{
										$this->errors[] = "Failed to require '$value' : " . $e->getMessage();
										$this->modules[$module]->ok = FALSE;
									}
									
									break;
								case "require_once":
									try
									{
										require_once( ( strcmp( substr( $value, 0, 1 ), '/' ) ? $dir . '/' : NULL ) . $value );
									}
									catch ( Exception $e )
									{
										$this->errors[] = "Failed to require '$value' : " . $e->getMessage();
										$this->modules[$module]->ok = FALSE;
									}
									
									break;
								case "include":
									if ( include( ( strcmp( substr( $value, 0, 1 ), '/' ) ? $dir . '/' : NULL ) . $value ) === FALSE )
									{
										$this->errors[] = "Failed to include '$value'!";
									}
									
									break;
								case "include_once":
									if ( include_once( ( strcmp( substr( $value, 0, 1 ), '/' ) ? $dir . '/' : NULL ) . $value ) === FALSE )
									{
										$this->errors[] = "Failed to include '$value'!";
									}
									
									break;
							}
							
							if ( $this->modules[$module]->ok == FALSE )
							{
								$this->errors[] = "Fatal error caught at '$cmd = \"$value\"'!  Module '$module' skipped.";
								
								break;
							}
						}
						
						if ( $this->modules[$module]->ok == FALSE )
						{
							if ( isset( $this->modules[$module] ) )
							{
								unset( $this->modules[$module] );
							}
							
							break;
						}
						
						/* Second pass, handle class instantiations.  --Kris */
						foreach ( $hooks as $cmd => $value )
						{
							switch( trim( strtolower( $cmd ) ) )
							{
								default:
									// If invalid, error already generated on first pass.  --Kris
									break;
								case "require":
								case "require_once":
								case "include":
								case "include_once":
									break;
								case "class_require":
								case "class_include":
									$args = array();
									$val_sane = str_replace( ' ', '', $value );
									if ( array_key_exists( "class_args_" . $val_sane, $hooks )
									{
										$args = $this->build_instance_args( $hooks["class_args_" . $val_sane] );
										
										if ( $args === FALSE )
										{
											$this->errors[] = "Args construction failed for $value!";
											
											// If it's a require, fail.  --Kris
											if ( stripos( $cmd, "require" ) !== FALSE )
											{
												$this->modules[$module]->ok = FALSE;
												$this->errors[] = "Failed class_require of : $value";
												
												break;
											}
											
											$args = array();
										}
									}
									
									/* Instantiate the class.  Pass constructor args if applicable.  --Kris */
									$cl = new ReflectionClass( $val_sane );
									$this->modules[$module]->$val_sane = $cl->newInstanceArgs( $args );
									
									break;
							}
						}
						
						if ( $this->modules[$module]->ok == FALSE )
						{
							if ( isset( $this->modules[$module] ) )
							{
								unset( $this->modules[$module] );
							}
							
							break;
						}
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
							$this->modules[$module]->$module = new $module();
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
	
	/* Parse instance args string from hooks.ini.  See hooks.sample.ini for syntax.  --Kris */
	public build_instance_args( $str )
	{
		return $this->cast_instance_args( str_replace( '\,', ',', preg_split( '/(?<!\\\\),[ ]*/', $str, NULL, PREG_SPLIT_NO_EMPTY ) ) );
	}
	
	/* Assign typecasts for argument variables.  Use the 'var' type to skip this step for that value entirely.  --Kris */
	public cast_instance_args( &$args )
	{
		foreach ( $args as &$arg )
		{
			$pair = explode( ' ', $arg );
			
			/* Bulky, but I don't want to be playing around with dynamic typing.  This way we can control what we support and how.  --Kris */
			switch ( trim( strtolower( $pair[0] ) )
			{
				default:
					$this->errors[] = "Unrecognized type '" . $pair[0] . "'!";
					break;
				case "var":
				case "mixed":
					break;
				case "int":
				case "integer":
					try
					{
						$pair[1] = (int) $pair[1];
					}
					catch
					{
						$this->errors[] = "Unable to cast '" . $pair[1] . "' as " . $pair[0];
					}
					
					break;
				case "float":
				case "double":
				case "real":
					try
					{
						$pair[1] = (float) $pair[1];
					}
					catch
					{
						$this->errors[] = "Unable to cast '" . $pair[1] . "' as " . $pair[0];
					}
					
					break;
				case "string":
					try
					{
						$pair[1] = (string) $pair[1];
					}
					catch
					{
						$this->errors[] = "Unable to cast '" . $pair[1] . "' as " . $pair[0];
					}
					
					break;
				case "bool":
				case "boolean":
					try
					{
						$pair[1] = (bool) $pair[1];
					}
					catch
					{
						$this->errors[] = "Unable to cast '" . $pair[1] . "' as " . $pair[0];
					}
					
					break;
				case "binary":
					try
					{
						$pair[1] = (binary) $pair[1];
					}
					catch
					{
						$this->errors[] = "Unable to cast '" . $pair[1] . "' as " . $pair[0];
					}
					
					break;
				case "array":
					try
					{
						$pair[1] = (array) $pair[1];
					}
					catch
					{
						$this->errors[] = "Unable to cast '" . $pair[1] . "' as " . $pair[0];
					}
					
					break;
				case "object":
					try
					{
						$pair[1] = (object) $pair[1];
					}
					catch
					{
						$this->errors[] = "Unable to cast '" . $pair[1] . "' as " . $pair[0];
					}
					
					break;
				case "unset":
					try
					{
						$pair[1] = (unset) $pair[1];
					}
					catch
					{
						$this->errors[] = "Unable to cast '" . $pair[1] . "' as " . $pair[0];
					}
					
					break;
			}
			
			$arg = $pair[1];
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
