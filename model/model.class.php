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
		
		// TODO - Load and instantiate satellite classes.  --Kris
	}
	
	public function __toString()
	{
		return "(PHP Object)";
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
