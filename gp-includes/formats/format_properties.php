<?php

class GP_Format_Properties extends GP_Format {

	public $name = 'Java Properties File (.properties)';
	public $extension = 'properties';
	public $filename_pattern = '%s_%s';

	public $exported = '';

	public function print_exported_file( $project, $locale, $translation_set, $entries ) {
		$result = '';

		$sorted_entries = $entries;
		usort( $sorted_entries, array( 'GP_Format_Properties', 'sort_entries' ) );

		foreach ( $sorted_entries as $entry ) {
			$entry->context = $this->escape( $entry->context );
			$translation = empty( $entry->translations ) ? $entry->context : $entry->translations[0];

			$original = empty( $entry->context ) ? $entry->singular : $entry->context;
			$original = str_replace( "\n", "\\n", $original );
			$translation = str_replace( "\n", "\\n", $translation );
			$translation = substr( json_encode( $translation ), 1, -1 );
			$translation = str_replace( '\"', '"', $translation );
			$translation = str_replace( '\\\\', '\\', $translation );
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
			} else if ( false === $inline && preg_match( '/^(.*)(=|:)(.*)$/U', $line, $matches ) ) {
				// Check to see if this line continues on to the next
				if( gp_endswith( $line, '\\' ) ) {
					$inline = true;
					$matches[3] = trim( $matches[3], '\\' );
				}
				
				$entry = new Translation_Entry();
				$entry->context = rtrim( $this->unescape( $matches[1] ) );
				$entry->singular = json_decode( '"' . str_replace( '"', '\"', $matches[3] ) . '"' );

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
						$entry->singular = $entry->singular . json_decode( '"' . str_replace( '"', '\"', $line ) . '"' );
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
