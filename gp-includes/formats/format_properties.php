<?php

class GP_Format_Properties extends GP_Format {

	public $name = 'Java Properties File (.properties)';
	public $extension = 'properties';
	public $filename_pattern = '%s_%s';

	public $exported = '';

	/**
	 * Generates a string the contains the $entries to export in the Properties file format.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
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
			$translation = $this->utf8_uni_encode( $translation );

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

	/**
	 * Encodes a PHP string in UTF8 format to a unicode escaped string (multi-byte characters are encoded in the \uXXXX format).
	 *
	 * @since 1.1.0
	 *
	 * @param $string string The string to encode.
	 *
	 * @return string
	 */
	private function utf8_uni_encode( $string ) {
		$result = '';
		$offset = 0;
		
		while ( $offset >= 0 ) {
			$val = $this->ordutf8( $string, $offset );

			if( $val > 127 ) {
				$result .= sprintf( '\u%04x', $val );
			} else {
				$result .= chr( $val );
			}
		}
		
		return $result;
	}
	
	/**
	 * Encodes a PHP string in ascii format to a unicode escaped string (multi-byte characters are encoded in the \uXXXX format).
	 *
	 * @since 1.1.0
	 *
	 * @param $string string The string to encode.
	 *
	 * @return string
	 */
	private function ascii_uni_encode( $string ) {
		$result = '';

		for( $i = 0; $i < strlen( $string ); $i++ ) {
			$val = ord( $string[ $i ] );

			if( $val > 127 ) {
				$result .= sprintf( '\u%04x', $val );
			} else {
				$result .= $string[ $i ] ;
			}
		}
		
		return $result;
	}
	
	/**
	 * Decodes a unicode escaped string to a PHP string.
	 *
	 * @param $string string The string to decode.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function uni_decode( $string ) {
		return preg_replace_callback( "/\\\\u([a-f0-9]{4})/", array( $this, "uni_decode_callback" ), $string );
	}
	
	/**
	 * Part of uni_decode(), this is the call back function that does the heavy lifting of converting a \uXXXX
	 * value to a UTF-8 encoded character sequence.
	 *
	 * @since 1.1.0
	 *
	 * @param $matches array The array of matches from preg_replace_callback().
	 *
	 * @return string
	 */
	private function uni_decode_callback( $matches ) {
		$binary = decbin( hexdec( $matches[1] ) );
		$bin_length = strlen( $binary );
		
		$byte = array();
		
		// UTF-8 encoding is a little complex, see https://en.wikipedia.org/wiki/UTF-8#Description for details of the below values.	
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
	
	/**
	 * Part of utf8_uni_encode(), this returns the character value of a UTF-8 encoded string.
	 *
	 * From http://php.net/manual/en/function.ord.php#109812
	 *
	 * @since 1.1.0
	 *
	 * @param $string string The UTF-8 string to process.
	 * @param $offset int The offset of the string to return the character value of.
	 *
	 * @return int|bool
	 */
	private function ordutf8( $string, &$offset ) {
		$code = ord( substr( $string, $offset, 1 ) ); 
		$bytesnumber = 1;
		
		if ( $code >= 128 ) {             //otherwise 0xxxxxxx
			if ( $code < 224 ) {
				$bytesnumber = 2;        //110xxxxx
			} else if ($code < 240) {
				$bytesnumber = 3;        //1110xxxx
			} else if ( $code < 248 ) {
				$bytesnumber = 4;        //11110xxx
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
	
	/**
	 * Reads a set of translations from a properties file.
	 *
	 * @since 1.1.0
	 *
	 * @param $file_name string The filename of the uploaded properties file.
	 * @param $project GP_Project The project object to read the translations in to.
	 *
	 * @return Translations
	 */
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
			$entry->translations = array( $entry->singular );
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

	/**
	 * Reads a set of original strings from a properties file.
	 *
	 * @since 1.1.0
	 *
	 * @param $file_name string The filename of the uploaded properties file.
	 *
	 * @return Translations
	 */
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
				/* So the following line looks a little weird, why encode just to decode?
				 *
				 * The reason is simple, properties files are in ISO-8859-1 aka Latin-1 format
				 * and can have extended characters above 127 but below 256 represented by a
				 * single byte.  That will break things later as PHP/MySQL will not accept
				 * a mixed encoding string with these high single byte characters in them.
				 *
				 * So let's convert everything to escaped unicode first and then decode 
				 * the whole kit and kaboodle to UTF-8.
				 */
				$entry->singular = $this->uni_decode( $this->ascii_uni_encode( $matches[3] ) );

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


	/**
	 * The callback to sort the entries by, used above in print_exported_file().
	 *
	 * @since 1.1.0
	 *
	 * @param $a Translations The first translation to compare.
	 * @param $b Translations The second translation to compare.
	 *
	 * @return int
	 */
	private function sort_entries( $a, $b ) {
		if ( $a->context == $b->context ) {
			return 0;
		}

		return ( $a->context > $b->context ) ? +1 : -1;
	}

	/**
	 * Unescape a string to be used as a value in the properties file.
	 *
	 * @since 1.1.0
	 *
	 * @param $string string The string to unescape.
	 *
	 * @return string
	 */
	private function unescape( $string ) {
		return stripcslashes( $string );
	}

	/**
	 * Escape a string to be used as a value in the properties file.
	 *
	 * @since 1.1.0
	 *
	 * @param $string string The string to escape.
	 *
	 * @return string
	 */
	private function escape( $string ) {
		return addcslashes( $string, '"\\/' );
	}

	/**
	 * Escape a string to be used as a key name in the properties file.
	 *
	 * @since 1.1.0
	 *
	 * @param $string string The string to escape.
	 *
	 * @return string
	 */
	private function escape_key( $string ) {
		return addcslashes( $string, '=: ' );
	}
	
}

GP::$formats['properties'] = new GP_Format_Properties;
