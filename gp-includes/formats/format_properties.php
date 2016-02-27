<?php

class GP_Format_Properties extends GP_Format {

	public $name = 'Java Properties File (.properties)';
	public $extension = 'properties';
	public $filename_pattern = '%s_%s';

	public $exported = '';

	public function print_exported_file( $project, $locale, $translation_set, $entries ) {
		$result = '';

		$sorted_entries = $entries;
		usort( $sorted_entries, array( $this, 'sort_entries' ) );

		foreach ( $sorted_entries as $entry ) {
			$entry->context = $this->escape( $entry->context );
			$translation = empty( $entry->translations ) ? $entry->context : $entry->translations[0];

			$original = empty( $entry->context ) ? $entry->singular : $entry->context;
			$original = str_replace( "\n", "\\n", $original );

			$translation = str_replace( "\n", "\\n", $translation );
			$translation = $this->uni_encode( $translation );

			$comment = preg_replace( "/(^\s+)|(\s+$)/us", "", $entry->extracted_comments );

			if ( $comment == "" ) {
				$comment = "No comment provided.";
			}

			$comment_lines = explode( "\n", $comment );

			foreach( $comment_lines as $line ) {
				$result .= "# $line\n";
			}
			
			$result .= $this->escape_key( $original ) . " = $translation\n\n";
		}

		return $result;
	}

	private function uni_encode( $string ) {
		$result = '';
		$offset = 0;
		
		while ( $offset >= 0 ) {
			$val = $this->ordutf8( $string, $offset );

			if( $val > 128 ) {
				$result .= sprintf( '\u%04x', $val );
			} else {
				$result .= chr( $val );
			}
		}
		
		return $result;
	}
	
	private function uni_decode( $string ) {
		return preg_replace_callback( "/\\\\u([a-f0-9]{4})/", array( $this, "uni_decode_callback" ), $string );
	}
	
	private function uni_decode_callback( $matches ) {
		$binary = decbin( hexdec( $matches[1] ) );
		$bin_length = strlen( $binary );
		
		$byte = array();
		
		if( $bin_length > 16 ) {        // > 16 bits, need 4 unicode bytes to encode
			$byte[ 0 ] = chr( bindec( '11110' . sprintf( '%03s', substr( $binary, 0, $bin_length - 18 ) ) ) );
			$byte[ 1 ] = chr( bindec( '10' . sprintf( '%06s', substr( $binary, -( 6 * 3 ), 6 ) ) ) );
			$byte[ 2 ] = chr( bindec( '10' . sprintf( '%06s', substr( $binary, -( 6 * 2 ), 6 ) ) ) );
			$byte[ 3 ] = chr( bindec( '10' . sprintf( '%06s', substr( $binary, -( 6 * 1 ), 6) ) ) );
		} else if( $bin_length > 11 ) {	// > 11 bits, need 3 unicode bytes to encode
			$byte[ 0 ] = chr( bindec( '1110' . sprintf( '%04s', substr( $binary, 0, $bin_length - 12 ) ) ) );
			$byte[ 1 ] = chr( bindec( '10' . sprintf( '%06s', substr( $binary, -( 6 * 2 ), 6 ) ) ) );
			$byte[ 2 ] = chr( bindec( '10' . sprintf( '%06s', substr( $binary, -( 6 * 1 ), 6) ) ) );
		} else if( $bin_length > 7 ) {  // > 7 bites, need 2 unicode bytes to encode
			$byte[ 0 ] = chr( bindec( '110' . sprintf( '%05s', substr( $binary, 0, $bin_length - 6 ) ) ) );
			$byte[ 1 ] = chr( bindec( '10' . sprintf( '%06s', substr( $binary, -( 6 * 1 ), 6 ) ) ) );
		} else {                        // < 8 bites, need 1 unicode bytes to encode
			$byte[ 0 ] = chr( bindec( '0' . sprintf(  '%07s', $binary ) ) );
		}
		
		/* This is an alternate way to encode the character but it needs the iconv functions available:
		 *
		 *		iconv( 'UCS-4LE', 'UTF-8', pack( 'V', hexdec( $matches[ 1 ] ) ) );
		 *
		 */
		
		return implode( $byte );
	}
	
	// From http://php.net/manual/en/function.ord.php#109812
	private function ordutf8( $string, &$offset ) {
		$code = ord( substr( $string, $offset, 1 ) ); 
		if ( $code >= 128 ) {        //otherwise 0xxxxxxx
			if ( $code < 224 ) {
				$bytesnumber = 2;                //110xxxxx
			} else if ($code < 240) {
				$bytesnumber = 3;        //1110xxxx
			} else if ( $code < 248 ) {
				$bytesnumber = 4;    //11110xxx
			}
			
			$codetemp = $code - 192 - ($bytesnumber > 2 ? 32 : 0) - ($bytesnumber > 3 ? 16 : 0);
			
			for ( $i = 2; $i <= $bytesnumber; $i++ ) {
				$offset ++;
				$code2 = ord( substr( $string, $offset, 1 ) ) - 128;        //10xxxxxx
				$codetemp = ( $codetemp * 64 ) + $code2;
			}
			
			$code = $codetemp;
		}
		
		$offset += 1;
		
		if ( $offset >= strlen( $string ) ) {
			$offset = -1;
		}
		
		return $code;
	}
	
	public function read_translations_from_file( $file_name, $project = null ) {
		if ( is_null( $project ) ) {
			return false;
		}

		$translations = $this->read_originals_from_file( $file_name );

		if ( ! $translations ) {
			return false;
		}

		$originals        = GP::$original->by_project_id( $project->id );
		$new_translations = new Translations;

		foreach( $translations->entries as $key => $entry ) {
			// we have been using read_originals_from_file to parse the file
			// so we need to swap singular and translation
			if ( $entry->context == $entry->singular ) {
				$entry->translations = array();
			} else {
				$entry->translations = array( $entry->singular );
			}

			$entry->singular = null;

			foreach( $originals as $original ) {
				if ( $original->context == $entry->context ) {
					$entry->singular = $original->singular;
					$entry->context = $original->context;
					break;
				}
			}

			if ( ! $entry->singular ) {
				error_log( sprintf( __( 'Missing context %s in project #%d', 'glotpress' ), $entry->context, $project->id ) );
				continue;
			}

			$new_translations->add_entry( $entry );
		}

		return $new_translations;
	}

	public function read_originals_from_file( $file_name ) {
		$entries = new Translations;
		$file = file_get_contents( $file_name );

		if ( false === $file ) {
			return false;
		}

		$entry = $comment = null;
		$inline = false;
		$lines = explode( "\n", $file );

		foreach ( $lines as $line ) {
			if ( preg_match( '/^(#|!)\s*(.*)\s*$/', $line, $matches ) ) {
				// If we have been processing a multi-line entry, save it now.
				if ( true === $inline ) {
					$entries->add_entry( $entry );
					$inline = false;
				}
				
				$matches[1] = trim( $matches[1] );

				if ( $matches[1] !== "No comment provided." ) {
					if ( null !== $comment ) {
						$comment = $comment . "\n" . $matches[2];
					} else {
						$comment = $matches[2];
					}
				} else {
					$comment = null;
				}
			} else if ( false === $inline && preg_match( '/^(.*)(\s?[=|:]\s?)(.*)$/', $line, $matches ) ) {
				// Check to see if this line continues on to the next
				if ( gp_endswith( $line, '\\' ) ) {
					$inline = true;
					$matches[3] = trim( $matches[3], '\\' );
				}
				
				$entry = new Translation_Entry();
				$entry->context = rtrim( $this->unescape( $matches[1] ) );
				$entry->singular = $this->uni_decode( $matches[3] );

				if ( ! is_null( $comment )) {
					$entry->extracted_comments = $comment;
					$comment = null;
				}

				$entry->translations = array();

				// Only save this entry if we're not in a multi line translation.
				if ( false === $inline ) {
					$entries->add_entry( $entry );
				}
			} else {
				// If we're processing a multi-line entry, add the line to the translation.
				if( true === $inline ) {
					// Check to make sure we're not a blank line.
					if( '' != trim( $line ) ) {
						// If there's still more lines to add, trim off the trailing slash.
						if( gp_endswith( $line, '\\' ) ) {
							$line = rtrim( $line, '\\' );
						}

						// Strip off leading spaces.
						$line = ltrim( $line );

						// Decode the translation and add it to the current entry.
						$entry->singular = $entry->singular . $this->uni_decode( $line );
					} else {
						// Any blank line signals end of the entry.
						$entries->add_entry( $entry );
						$inline = false;
					}
				} else {
					// If we hit a blank line and are not processing a multi-line entry, reset the comment.
					$comment = null;
				}
			}
		}

		// Make sure we save the last entry if it is a multi-line entry.
		if ( true === $inline ) {
			$entries->add_entry( $entry );
			$inline = false;
		}

		return $entries;
	}


	private function sort_entries( $a, $b ) {
		if ( $a->context == $b->context ) {
			return 0;
		}

		return ( $a->context > $b->context ) ? +1 : -1;
	}

	private function unescape( $string ) {
		return stripcslashes( $string );
	}

	private function escape( $string ) {
		return addcslashes( $string, '"\\/' );
	}

	private function escape_key( $string ) {
		return addcslashes( $string, '=: ' );
	}
	
}

GP::$formats['properties'] = new GP_Format_Properties;
