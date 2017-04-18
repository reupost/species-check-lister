<?php
/* Useful SQL:
UPDATE species s JOIN 
(select fullname_naked, count(*) as ambig from species group by fullname_naked having count(*) > 1) sa
ON s.fullname_naked = sa.fullname_naked
SET s.fullname_naked_is_ambig = true;
 * 
 */


/* this is the core database-level class */
class Species_Data {
  const LEVENSHTEIN_DIST_GENUS = 1; //allow this much variation in genus part of name
  const LEVENSHTEIN_DIST_SPP = 2; //allow this much variation in spp epithets
  const LEVENSHTEIN_PERCENTAGE_SPECULATIVE = 20; //allow this much variation in speculative matches (as % of naked epithet length)
  const LINES_PER_BATCH = 200;
  const MAX_ROWS_TO_FETCH = 5000; //max db rows read into array at once
  const MAX_SPECULATIVE = 5;
  
  var $cur_end_row = -1; //if using getting data in batches
  
	var $db_link = null;
	
	var $db_server = "";
	var $db_name = "";
	var $db_user = "";
	var $db_password = "";
	
	var $error_state = "";
	
	var $table_fields = array(); //e.g. table_fields['myfield'] will contain 2 arrays ['values'] and ['selected']
	//array of [fieldname, array[values], array[selected]]
	//'selected' is used to store user selected value(s) for that field; 'values' include all distinct values
	
	var $table_result_field = array(); //when getting results, this is the field to get data from array of ['field'] and ['summary'] (=COUNT,FIRST,SUM, etc)

	var $results_cube = null; //3d array of results
	
	public function __construct() {
		$this->error_state = "";
		$this->table_fields = array();		
	}
	
	public function __destruct() {
		if ( $this->db_link ) {
			mysql_close($this->db_link);
			$this->db_link = null;
		}
	}
	
	public function set_db_connection( $server, $db, $user, $password ) {
	//requires the database connection information and the table of data to be worked with
	//returns 0 on success, -1 on failure (and sets $error_state to string)
		$this->db_server = $server;
		$this->db_name = $db;
		$this->db_user = $user;
		$this->db_password = $password;
		
		$this->error_state = "";
		$this->table_fields_overall_filter = array();
		$this->table_fields = array();
		
		$this->db_link = @mysql_connect( $this->db_server, $this->db_user, $this->db_password , true ); //do not reuse the existing (Wordpress!) db connection
		if ( ! $this->db_link ) {
			$this->error_state = "DATABASE ERROR: Unable to connect to database server '" . $server . "' (user '" . $user . "' and password '" . $password . "')";
			return -1;
		}
		if ( ! @mysql_select_db( $this->db_name , $this->db_link ) ) {
			$this->error_state = "DATABASE ERROR: Unable to open database '" . $db . "' on server '" . $server . "'";
			return -1;
		}
		mysql_set_charset('utf8',$this->db_link);

		return 0;
	}
	
	public function get_error_state() {
	//returns the current error_state, which is "" if there are no problems
		return $this->error_state;
	}
	
	public function get_table_rowcount() {
	//returns the number of rows in the table, or -1 if error
		$sql = "SELECT count(*) AS rowcount FROM `" . $this->db_table . "`";
		$row = 0;
		$result = 0;
		$result = mysql_query( $sql , $this->db_link );			
		if ( $result ) {
			$row = mysql_fetch_array( $result );
			if ( $row ) {
				return $row['rowcount'];
			}
			mysql_free_result( $result );
		}
		$this->error_state = "DATABASE ERROR: failed to count rows in table";
		return -1;
	}
	
	public function db_get_table_fields() {
	//returns an array of fieldnames and types (which will be empty if there is an error)
		$rec = array();
		$sql = "SHOW COLUMNS FROM `" . $this->db_table . "`";
		$result = mysql_query($sql, $this->db_link );
		if ( $result ) {
			while ( $row = mysql_fetch_assoc( $result ) ) {
				$rec[] = $row;				
			}
			mysql_free_result( $result );
		}
		return $rec;
	}
	
	private function db_field_exists( $fieldname ) {
	//returns -1 if field exists in table, 0 if it doesn't
	//TODO: more efficient array_element_exists php function, right? in_array
		$flds = $this->db_get_table_fields();
		foreach ( $flds as $fld ) {
			if ( $fld['Field'] == $fieldname ) return -1;
		}
		return 0;
	}
  
  private function db_get_field_quotes( $fieldname ) {
	//returns "'" for text-type fields, "" for numbers
		$texttypes = array('binary','blob','char','date','datetime','enum','longblob','longtext','mediumblob','mediumtext','set','text','time','timestamp','tinyblob','tinytext','varbinary','varchar');		
		$fields = $this->db_get_table_fields();
		
		$typeclean = "";
		foreach( $fields as $itm => $attribs ) {
			if ( $attribs['Field'] == $fieldname ) {
				$typeclean = $attribs['Type'];
				break;
			}
		}
		if ( "" != $typeclean ) {		
			if ( false !== strpos( $typeclean,'(' ) ) { //need to remove field size e.g. 'varchar(50)' => 'varchar'
				$typeclean = substr( $typeclean, 0, strpos( $typeclean,'(' ) );
			}
			if ( in_array( $typeclean, $texttypes ) ) return "'"; //is text or date-type field
			return ""; //number-type field
		}
		return -1; //field not found in db table
	}
  
  public function runsql( $sql, $must_affect_rows = true ) {
    mysql_query( $sql, $this->db_link );
    if( $must_affect_rows ) {
      if( mysql_affected_rows( $this->db_link ) <= 0 ) {         
        $error = mysql_error( $this->db_link );
        echo $sql; echo $error; exit;
      }
    }
  }
  
  public function select( $sql, $use_batch = 0 ) {
  //if $cur_end_row is set then limit array size (will be done in chunks), and cur_end_row will be updated 
  // to the start of the next chunk or -1 if all records have been fetched
    $rec = array();		
    
    if( $use_batch ) {
      if ( $this->cur_end_row >= 0 ) {
        $sql .= " LIMIT " . $this->cur_end_row . ", " . self::MAX_ROWS_TO_FETCH;
      }
    }
		$result = mysql_query( $sql, $this->db_link );
		if ( $result ) {
			while( $row = mysql_fetch_assoc( $result ) ) {
				$rec[] = $row;				
			}
			mysql_free_result( $result );
		} else {
      $error = mysql_error( $this->db_link );
      if ( '' != $error ) { echo $error; exit; }
    }
    if( $use_batch ) {
      if ( $this->cur_end_row >= 0 ) {
        $this->cur_end_row += self::MAX_ROWS_TO_FETCH;
        if ( sizeof( $rec ) < self::MAX_ROWS_TO_FETCH ) $this->cur_end_row = -1; //reached end
      }
    }
		return $rec;
  }
  
  private function insert_spp( $batch_id, $spptxt ) {
    $bad = 0;
    $spptxt = trim( $spptxt );
    if( '' == $spptxt ) return $bad;
    
    $sql = "INSERT INTO submitted (batch, submitted_name) VALUES ('" . mysql_real_escape_string( $batch_id ) . "', '" . mysql_real_escape_string( $spptxt ) . "')";
    mysql_query( $sql, $this->db_link );
    $spp_id = mysql_insert_id( $this->db_link );
    if( ! $spp_id ) { // was problem
      $error = mysql_error( $this->db_link );
      $bad = $error;
    }
    return $bad;
  }
  
  private function insert_spp_multi( $batch_id, $spptxtarr ) {
    $bad = 0;    
    
    $sql = "";
    foreach( $spptxtarr as $spp ) {      
      $sql .= ", ('" . mysql_real_escape_string( $batch_id ) . "', '" . mysql_real_escape_string( trim( $spp ) ) . "')";
    }    
    $sql = "INSERT INTO submitted (batch, submitted_name) VALUES " . substr( $sql, 1 );
    mysql_query( $sql, $this->db_link );
    $spp_id = mysql_insert_id( $this->db_link );
    if( ! $spp_id ) { // was problem
      $error = mysql_error( $this->db_link );
      $bad = $error;
    }
    return $bad;
  }
  
  public function write_species_txt_to_db( $txt ) {
    //return batch_id
    $batch_id = md5(uniqid(time()));
    $errors = array();
    
    $textAr = explode( "\n", $txt );    
    $textAr = array_filter( $textAr, 'trim' ); // remove any extra \r characters left behind
    $textAr = array_values( array_filter( $textAr ) ); //remove any blank lines
    //mysql_query("BEGIN");
    $i = 0;
    $spparr = array();
    foreach ($textAr as $line) {
      $spparr[$i] = $line;
      $i++;
      if( self::LINES_PER_BATCH == $i ) {
        $err = self::insert_spp_multi( $batch_id, $spparr );
        if( $err ) $errors[] = $err;
        $spparr = array();
        $i = 0;
      }      
    }
    if( sizeof( $spparr ) ) {
      $err = self::insert_spp_multi( $batch_id, $spparr );
      if( $err ) $errors[] = $err;
    }
    if( sizeof( $errors ) ) {
      //mysql_query("ROLLBACK");
      print_r( $errors ) ;      
      exit;
    } else {
      //mysql_query("COMMIT");
    }
    
    return $batch_id;
  }
  
  public function write_species_file_to_db( $use_batch ) {
    $errors = array();
    
    $batch_id = md5(uniqid(time()));
    if( $use_batch ) $batch_id = $use_batch; //already has a batch associated with submission
    
    $file = $_FILES['spp_file'];
		$arrData = file( $file['tmp_name'] );
		$recs = count( $arrData );		
		$j = 0;
    $spparr = array();    
    //mysql_query("BEGIN");
		for( $i = 0; $i < $recs; $i++ ) {
      $spparr[$j] = $arrData[$i];
      $j++;
      if( self::LINES_PER_BATCH == $j ) {
        $err = self::insert_spp_multi( $batch_id, $spparr );
        if( $err ) $errors[] = $err;
        $spparr = array();
        $j = 0;
      }      
    }
    if( sizeof( $spparr ) ) {
      $err = self::insert_spp_multi( $batch_id, $spparr );
      if( $err ) $errors[] = $err;
    }
    if( sizeof( $errors ) ) {
      //mysql_query("ROLLBACK");
      print_r( $errors ) ;
      exit;
    } else {
      //mysql_query("COMMIT");
    }
    
    return $batch_id;
  }
  
  public function remove_duplicates( $batch_id ) {
    // also removes blanks
    $sql = "
DELETE
FROM submitted USING submitted, submitted e1
WHERE submitted.id > e1.id
    AND submitted.submitted_name = e1.submitted_name
    AND submitted.batch = e1.batch
    AND submitted.batch = '" . $batch_id . "'
    ";
    $this->runsql( $sql, false );
    
    $sql = "
DELETE FROM submitted WHERE trim(submitted_name) = ''
";
    $this->runsql( $sql, false );
  }
  
  private function countDigits( $str )
  {
    return preg_match_all( "/[0-9]/", $str );
  }
  
  public function parse_names( $batch_id, $only_unmatched = false ) {
    $sqlouter = "SELECT * FROM submitted WHERE batch = '" . $batch_id . "'";
    if( $only_unmatched ) $sqlouter .= " AND matched_name IS NULL ORDER BY id";
    
    $arr_ranks = array("subsp.", "subsp", "ssp.", "ssp", "var.", "var", "v.", "v", "forma", "f.", "f");
    $arr_cf = array("cf.", "cf", "aff.", "aff", "?", "ms.", "m.s.", "ms"); //also removing manuscript indicator
    $arr_hybrid = array("x", "×");
    
    $this->cur_end_row = 0; //use batch
    do {
      $recs = $this->select( $sqlouter, 1 ); /* for big batches, split into chunks */
    
      mysql_query("BEGIN");

      foreach ( $recs as $rec ) {
        $name = trim($rec['submitted_name'], "\xC2\xA0\n");      
        //get rid of multiple ?-marks
        while (stripos( $name, '??' ) ) {
          $name = str_replace( '??', '?', $name );
        }
        $name = str_replace( "\t", ' ', $name ); //tab marks      
        $name = str_replace( '?', ' ? ', $name ); //make sure ?-mark not joined to anything
        $name = str_replace( '(', ' (', $name ); //make sure ( not joined to species epithet
        while (stripos( $name, '  ' ) ) { //remove double-spaces
          $name = str_replace( '  ', ' ', $name );
        }

        //weird BOLUS ideas
        $name = str_ireplace( "(cf genus )", "cf_genus", $name );
        $name = str_ireplace( "(cf. genus)", "cf_genus", $name );
        $name = str_ireplace( "(cf genus)", "cf_genus", $name );
        $name = str_ireplace( "(cf.genus)", "cf_genus", $name );

        //sp. novs
        $name = str_ireplace( " (sp. nov.)", " sp_nov", $name );
        $name = str_ireplace( " sp. nov.", " sp_nov", $name );
        $name = str_ireplace( " nov.sp.", " sp_nov", $name );
        $name = str_ireplace( " sp.nov.", " sp_nov", $name );
        $name = str_ireplace( " sp nov", " sp_nov", $name );

        //animal species authors have year
        $numdigits = self::countDigits( $name );
        if( 3 >= $numdigits ) { //not a year, so remove
          $has_digit = self::first_digit_in_string( $name );
          while( $has_digit >= 0 ) {
            $name = self::remove_word_around_char( $name, $has_digit );
            $has_digit = self::first_digit_in_string( $name );
          }
        }

        //TODO: left out genus hybrid

        //now do the processing
        //get rid of any non-breaking space characters:
        $name = preg_replace('~\x{00a0}~siu',' ',$name);
        $arr_name = explode( " ", $name );
        $element = 0;
        $expecting = 'genus';
        $depth = 0;
        $cf = false;
        $hybrid = false;
        $spnov = false;
        $o_gen = '';
        $o_s = array('', '', '');
        $o_r = array('', '', '');
        $o_a = array('', '', '');

        while( $element < count( $arr_name ) ) {
          $name_part = $arr_name[$element];
          if( in_array( strtolower( $name_part ), $arr_cf ) ) {
            $cf = true;
          } elseif( in_array( strtolower( $name_part ), $arr_hybrid ) ) {
            $hybrid = true;
            if( 'auth/rank' == $expecting ) {
              $expecting = 'epithet';
              $depth++;
            }
          } else {
            if( 'genus' == $expecting ) {
              $o_gen = $name_part;
              $expecting = 'epithet';
            } elseif( 'epithet' == $expecting ) {
              if( 'cf_genus' == $name_part ) {
                $cf = true;
              } elseif( 'sp_nov' == $name_part ) {
                $spnov = true;
                $o_s[$depth] = "sp. nov.";
                $expecting = "auth/rank";
              } else {
                $o_s[$depth] = $name_part;
                $expecting = "auth/rank";
              }
            } elseif( 'auth/rank' == $expecting ) {
              if( in_array( strtolower( $name_part ), $arr_ranks ) ) {
                if( in_array( strtolower( $name_part ), array( 'subsp.', 'subsp', 'ssp.', 'ssp' ) ) ) {
                  $o_r[$depth] = 'subsp.';
                  $expecting = "epithet";
                  $depth++;
                } elseif( in_array( strtolower( $name_part ), array( 'var.', 'var', 'v.', 'v' ) ) ) {
                  $o_r[$depth] = 'var.';
                  $expecting = "epithet";
                  $depth++;
                } elseif( in_array( strtolower( $name_part ), array( 'forma', 'f.', 'f' ) ) ) {
                  //need to handle forma indicator as distinct from 'f.' as part of authorities, e.g. "Baker f. ex J.B.Gillett"
                  //there must be a next part, and next part must be all letters and not 'ex' for this to be an actual forma
                  if( $element == count( $arr_name ) - 1 ) {
                    //last part, so not a forma indicator
                    $o_a[$depth] = trim($o_a[$depth] . " " . $name_part);
                  } else {
                    $next = $arr_name[$element+1];
                    if( ctype_alpha($next) && strtolower($next) != 'ex' ) {
                      $o_r[$depth] = 'forma';
                      $expecting = "epithet";
                      $depth++;
                    } else {
                      $o_a[$depth] = trim($o_a[$depth] . " " . $name_part);
                    }
                  }
                } 
              } elseif( 'sp_nov' == $name_part ) {
                $spnov = true;
                $o_s[$depth] = trim($o_a[$depth] . " " . "sp. nov.");
              } else {
                $o_a[$depth] = trim($o_a[$depth] . " " . $name_part);
                //could be more author name bits, so don't change what is expected
              }
            }
          }
          $element++;
        }
        //now write to DB
        $sql = "UPDATE submitted SET " .
          "p_genus = '" . mysql_real_escape_string( $o_gen ) .  "', " .
          "p_sp1 = '" . mysql_real_escape_string( $o_s[0] ) .  "', " .
          "p_auth1 = '" . mysql_real_escape_string( $o_a[0] ) .  "', " .
          "p_rank1 = '" . mysql_real_escape_string( $o_r[0] ) .  "', " .
          "p_sp2 = '" . mysql_real_escape_string( $o_s[1] ) .  "', " .
          "p_auth2 = '" . mysql_real_escape_string( $o_a[1] ) .  "', " .
          "p_rank2 = '" . mysql_real_escape_string( $o_r[1] ) .  "', " .
          "p_sp3 = '" . mysql_real_escape_string( $o_s[2] ) .  "', " .
          "p_auth3 = '" . mysql_real_escape_string( $o_a[2] ) .  "', " .
          "p_cf = " . ($cf? '1' : '0') .  ", " .
          "p_hybrid = " . ($hybrid? '1' : '0') .  ", " .
          "p_fullname = '" . trim( mysql_real_escape_string( $o_gen . 
              ($o_s[0] > ""? " " . $o_s[0] : "") .
              ($o_a[0] > ""? " " . $o_a[0] : "") .
              ($hybrid? " X" : "") .
              ($o_r[0] > ""? " " . $o_r[0] : "") .
              ($o_s[1] > ""? " " . $o_s[1] : "") .
              ($o_a[1] > ""? " " . $o_a[1] : "") .
              ($o_r[1] > ""? " " . $o_r[1] : "") .
              ($o_s[2] > ""? " " . $o_s[2] : "") .
              ($o_a[2] > ""? " " . $o_a[2] : "") ) ) . "', " .
          "p_fullname_naked = '" . trim( mysql_real_escape_string( $o_gen . 
              ($o_s[0] > ""? " " . $o_s[0] : "") .            
              ($hybrid? " X" : "") .
              ($o_r[0] > ""? " " . $o_r[0] : "") .
              ($o_s[1] > ""? " " . $o_s[1] : "") .            
              ($o_r[1] > ""? " " . $o_r[1] : "") .
              ($o_s[2] > ""? " " . $o_s[2] : "") ) ) . "' " .
          "WHERE (id = " . $rec['id'] . ")";
        $this->runsql( $sql );        
      }
      mysql_query("COMMIT");
      
    } while ($this->cur_end_row >= 0);
    
    return 0;
  }
  
  private function first_digit_in_string( $str ) {
    $result = -1;
    $ii = strlen( $str );
    for( $i = 0; $i < $ii; $i++ ) {
      if( is_numeric( substr( $str, $i, 1 ) ) ) {
        $result = $i;
        break;
      }
    }    
    return $result;
  }
  
  private function remove_word_around_char( $str, $charpos ) {
    $result = "";
    $s1 = substr( $str, 0, $charpos );
    $s2 = substr( $str, $charpos + 1 );    
    $s1space = strrpos( $s1, " " );    
    if( $s1space === false ) $s1space = strlen( $s1 );
    $s2space = strpos( $s2, " " );
    if( $s2space === false ) $s2space = strlen( $s2 );
    
    $result = substr( $s1, 0, $s1space );
    $result .= substr( $s2, $s2space );
    return trim( $result );    
  }
  
  public function set_perfect_matches( $batch_id ) {
    //run before going to trouble of parsing names to sort out entirely ok names which can be 'parsed' by adopting parsed
    //values of their matched name
    $sql = "
UPDATE submitted s
JOIN species ss ON s.submitted_name = ss.fullname
SET s.matched_name = ss.fullname, s.matched_id = ss.id, s.match_ambig = ss.fullname_isambig, s.matched_level = 'species', s.matched_confidence = 'full name', s.matched_source = ss.source
WHERE (s.batch = '" . $batch_id . "' AND s.matched_name IS NULL)
    ";
    $this->runsql( $sql, false );     
  }
  
  public function set_author_part_matches( $batch_id ) {
    //match submissions that have the old author 
    //e.g. "Osteospermum clandestinum Less." will be matched to "Osteospermum clandestinum (Less.) Norl."
    $sql = "
UPDATE submitted s
JOIN (SELECT id, source, fullname_naked, fullname, fullname_naked_isambig, spauth_part_new FROM species WHERE spauth_part_new > '') ss 
ON s.p_fullname_naked = ss.fullname_naked AND s.p_auth1 = ss.spauth_part_new
SET s.matched_name = ss.fullname, s.matched_id = ss.id, s.match_ambig = ss.fullname_naked_isambig, s.matched_level = 'species', s.matched_confidence = 'full name [part author]', s.matched_source = ss.source
WHERE (s.batch = '" . $batch_id . "' AND s.matched_name IS NULL)
    ";
    $sql = "
UPDATE submitted s JOIN
(SELECT sub_id, sp_id, fullname, fullname_naked_isambig, source FROM
(SELECT id as sub_id, p_fullname_naked, p_auth1 FROM submitted WHERE matched_name IS NULL AND p_auth1 > '' AND batch = '" . $batch_id . "') su JOIN
(SELECT id as sp_id, source, fullname_naked, fullname, fullname_naked_isambig, spauth_part_new FROM species WHERE spauth_part_new > '') ss 
ON su.p_fullname_naked = ss.fullname_naked AND su.p_auth1 = ss.spauth_part_new) xx
ON s.id = xx.sub_id
SET s.matched_name = xx.fullname, s.matched_id = xx.sp_id, s.match_ambig = xx.fullname_naked_isambig, s.matched_level = 'species', s.matched_confidence = 'full name [part author]', s.matched_source = xx.source
    ";
    $this->runsql( $sql, false );     
    $sql = "
UPDATE submitted s
JOIN (SELECT id, source, fullname_naked, fullname, fullname_naked_isambig, spauth_part_old FROM species WHERE spauth_part_old > '') ss 
ON s.p_fullname_naked = ss.fullname_naked AND s.p_auth1 = ss.spauth_part_old
SET s.matched_name = ss.fullname, s.matched_id = ss.id, s.match_ambig = ss.fullname_naked_isambig, s.matched_level = 'species', s.matched_confidence = 'full name [part author]', s.matched_source = ss.source
WHERE (s.batch = '" . $batch_id . "' AND s.matched_name IS NULL)
    ";
    $sql = "
UPDATE submitted s JOIN
(SELECT sub_id, sp_id, fullname, fullname_naked_isambig, source FROM
(SELECT id as sub_id, p_fullname_naked, p_auth1 FROM submitted WHERE matched_name IS NULL AND p_auth1 > '' AND batch = '" . $batch_id . "') su JOIN
(SELECT id as sp_id, source, fullname_naked, fullname, fullname_naked_isambig, spauth_part_old FROM species WHERE spauth_part_old > '') ss 
ON su.p_fullname_naked = ss.fullname_naked AND su.p_auth1 = ss.spauth_part_old) xx
ON s.id = xx.sub_id
SET s.matched_name = xx.fullname, s.matched_id = xx.sp_id, s.match_ambig = xx.fullname_naked_isambig, s.matched_level = 'species', s.matched_confidence = 'full name [part author]', s.matched_source = xx.source
    ";
    $this->runsql( $sql, false );
  }
  
  public function set_species_matches( $batch_id, $type_of_match, $naked = false ) {
    
    if( 'families' == $type_of_match ) {
      $sql = "
UPDATE submitted s
JOIN families f ON s.p_fullname" . ($naked? "_naked" : "") . " = f.family
SET s.matched_name = f.family, s.matched_level = 'family', s.matched_confidence = '" . ($naked? 'naked name' : 'full name') . "', s.matched_source = f.source
WHERE (s.batch = '" . $batch_id . "' AND s.matched_name IS NULL);
        ";
      /* TODO: set ambig for families / genera matches */
      
    } elseif( 'genera' == $type_of_match ) { //if same genus name in different families its ok since not actually assigning a genno at this point
      $sql = "
UPDATE submitted s
JOIN genera g ON s.p_fullname" . ($naked? "_naked" : "") . " = g.genus
SET s.matched_name = g.genus, s.matched_level = 'genus', s.matched_confidence = '" . ($naked? 'naked name' : 'full name') . "', s.matched_source = g.source
WHERE (s.batch = '" . $batch_id . "' AND s.matched_name IS NULL);
        ";
      
    } elseif( 'species' == $type_of_match ) {     
      $sql = "
UPDATE submitted s
JOIN species ss ON s.p_fullname" . ($naked? "_naked" : "") . " = ss.fullname" . ($naked? "_naked" : "") . "
SET s.matched_name = ss.fullname, s.matched_level = 'species', s.matched_id = ss.id, s.match_ambig = ss.fullname" . ($naked? "_naked" : "") . "_isambig, s.matched_confidence = '" . ($naked? 'naked name' : 'full name') . "', s.matched_source = ss.source
WHERE (s.batch = '" . $batch_id . "' AND s.matched_name IS NULL" . ($naked? " AND s.p_fullname = s.p_fullname_naked" : "") . ")
        ";
    }    
    $this->runsql( $sql, false ); 
  }
  
  //'last resort' match on naked names where submitted names do have authors by they are not otherwise matchable
  public function set_species_matches_stripped( $batch_id ) {    
    $sql = "
UPDATE submitted s
JOIN species ss ON s.p_fullname_naked = ss.fullname_naked
SET s.matched_name = ss.fullname, s.matched_level = 'species', s.matched_id = ss.id, s.match_ambig = ss.fullname_naked_isambig, s.matched_confidence = 'stripped name', s.matched_source = ss.source
WHERE (s.batch = '" . $batch_id . "' AND s.matched_name IS NULL)
        ";   
    $this->runsql( $sql, false ); 
  }
  
  public function set_genus_matches( $batch_id, $type_of_match ) {
    if( 'perfect' == $type_of_match ) {      
      $sql = "
UPDATE submitted s
JOIN genera g ON s.p_genus = g.genus
SET s.matched_genus = g.genus
WHERE (s.batch = '" . $batch_id . "' AND s.matched_name IS NULL AND s.matched_genus IS NULL);
        ";
      $this->runsql( $sql, false ); 
      
    } elseif( 'levenshtein' == $type_of_match ) {      
      $sql = "
SELECT id, p_genus, char_length(p_genus) AS genus_length 
FROM submitted s 
WHERE (s.batch = '" . $batch_id . "' AND s.matched_name IS NULL AND s.matched_genus IS NULL AND s.p_genus > '')
      ORDER BY char_length(p_genus), p_genus ASC
      ";
      $recs = $this->select( $sql );      
      //since we are only interested in genera which have a levenshtein distance < 2, we can use string length to 
      //avoid having to compare against all genera
      //UPDATE genera SET genus_length = char_length(genus);
      $cur_length = 0;
      $cur_genus = ""; //TODO: this could be more efficient
      mysql_query("BEGIN");
      foreach( $recs as $rec ) {
        if( $rec['p_genus'] != $cur_genus ) {
          $cur_genus = $rec['p_genus'];
          if( $rec['genus_length'] != $cur_length ) {
            $cur_length = $rec['genus_length'];
            $sql_genera = '
SELECT genus FROM genera g 
WHERE (g.genus_length > ' . ($cur_length - 1 - self::LEVENSHTEIN_DIST_GENUS) . ' AND g.genus_length < ' . ($cur_length + 1 + self::LEVENSHTEIN_DIST_GENUS) . ');
            ';
            $genera = $this->select( $sql_genera );          
          }
          $best_match = array();
          $dist = self::min_levenshtein( $rec['p_genus'], $genera, $best_match, 'genus' );
        }
        if( $dist <= self::LEVENSHTEIN_DIST_GENUS ) {
          //write best_match to s.matched_genus
          $sql = "
UPDATE submitted 
SET matched_genus = '" . mysql_real_escape_string( $best_match['genus'] ) . "' 
WHERE id = " . $rec['id'];
          $this->runsql( $sql ); 
        }
      }
      mysql_query("COMMIT");
    }    
  }
  
  public function set_species_matches_fuzzy( $batch_id, $type_of_match, $disregard_sspvar_confusion = false) {
    $sql = "
SELECT id, matched_genus, p_fullname, p_fullname_naked 
FROM submitted s 
WHERE (s.batch = '" . $batch_id . "' AND s.matched_genus IS NOT NULL AND s.matched_name IS NULL) 
ORDER BY s.matched_genus ASC;
      ";
    $recs = $this->select( $sql );
    $cur_gen = "";
    mysql_query("BEGIN");
    foreach( $recs as $rec ) {
      if( $rec['matched_genus'] != $cur_gen ) {
        $cur_gen = $rec['matched_genus'];
        $sql_spp = "
SELECT id, fullname, fullname_naked, fullname_isambig, fullname_naked_isambig, source 
FROM species
WHERE genus = '" . mysql_real_escape_string( $cur_gen ) . "'
          ";
        $species = $this->select( $sql_spp );
      }
      $best_match = array();        
      if( 'full name' == $type_of_match ) {
        $dist = self::min_levenshtein( $rec['p_fullname'], $species, $best_match, 'fullname', $disregard_sspvar_confusion );
      } else {
        $dist = self::min_levenshtein( $rec['p_fullname_naked'], $species, $best_match, 'fullname_naked', $disregard_sspvar_confusion );
      }
      if( $dist <= self::LEVENSHTEIN_DIST_SPP ) {
        $sql = "
UPDATE submitted SET ";
        //if( 'full name' != $type_of_match ) {
        //  $sql .= mysql_real_escape_string( $best_match['fullname_naked'] );
        //} else {
        //  $sql .= mysql_real_escape_string( $best_match['fullname'] );
        //}
        $sql .= "matched_name = '" . mysql_real_escape_string( $best_match['fullname'] ) . "', ";
        $sql .= "matched_level = 'species', ";
        $sql .= "matched_id = " . $best_match['id'] . ", ";
        $sql .= "matched_dist = " . $dist . ", ";
        $sql .= "matched_confidence = '" . $type_of_match . ($disregard_sspvar_confusion? ' [subsp/var confusion]' : '') . "', ";
        $sql .= "match_ambig = " . ('full name' == $type_of_match? $best_match['fullname_isambig'] : $best_match['fullname_naked_isambig']) . ", ";        
        $sql .= "matched_source = '" . mysql_real_escape_string( $best_match['source'] ) . "' ";
        $sql .= "WHERE id = " . $rec['id'];
        
        $this->runsql( $sql ); 
      }
    }
    mysql_query("COMMIT");
  }
  
  public function set_species_matches_autonym( $batch_id ) {
    //match X a to X a a 
    $sql = "
UPDATE submitted s 
JOIN species ss ON s.p_genus = ss.genus AND s.p_sp1 = ss.sp
SET s.matched_name = ss.fullname, s.matched_id = ss.id, s.matched_level = 'species', s.matched_confidence = 'autonym', s.match_ambig = ss.fullname_naked_isambig, s.matched_source = ss.source
WHERE (s.batch = '" . $batch_id . "' AND s.p_sp2 = '' AND s.p_rank1 = '' AND (ss.ssp = ss.sp OR ss.var = ss.sp) AND s.matched_name IS NULL);
      ";
    $this->runsql( $sql, false ); 
    return 0;
  }
  
  public function set_species_matches_ssp_var( $batch_id, $type_of_match ){
    //first var->ssp
    $sql = "
UPDATE submitted s 
JOIN species ss ON s.p_genus = ss.genus AND s.p_sp1 = ss.sp AND s.p_sp2 = ss.ssp ";
    if ( 'full name' == $type_of_match ) {
      $sql .= "AND s.p_auth1 = ss.spauth AND s.p_auth2 = ss.sspauth ";
    }
    $sql .= "
SET s.matched_name = ss.fullname, s.matched_id = ss.id, s.matched_level = 'species', s.matched_confidence = '" . $type_of_match . " [subsp/var confusion]', s.match_ambig = ss.fullname" . ( 'full name' == $type_of_match? '' : '_naked' ) . "_isambig, s.matched_source = ss.source
WHERE (s.batch = '" . $batch_id . "' AND s.matched_name IS NULL AND 
  s.p_rank1 = 'var.' AND s.p_rank2 = '' AND s.p_sp3 = '' AND 
  ss.ssp > '' AND ss.var = '' AND ss.oth = '');
      ";    
    $this->runsql( $sql, false ); 
    
    //then ssp->var
    $sql = "
UPDATE submitted s 
JOIN species ss ON s.p_genus = ss.genus AND s.p_sp1 = ss.sp AND s.p_sp2 = ss.var ";
    if ( 'full name' == $type_of_match ) {
      $sql .= "AND s.p_auth1 = ss.spauth AND s.p_auth2 = ss.varauth ";
    }
    $sql .= "
SET s.matched_name = ss.fullname, s.matched_id = ss.id, s.matched_level = 'species', s.matched_confidence = '" . $type_of_match . " [subsp/var confusion]', s.match_ambig = ss.fullname" . ( 'full name' == $type_of_match? '' : '_naked' ) . "_isambig, s.matched_source = ss.source
WHERE (s.batch = '" . $batch_id . "' AND s.matched_name IS NULL AND 
  s.p_rank1 = 'subsp.' AND s.p_rank2 = '' AND s.p_sp3 = '' AND 
  ss.var > '' AND ss.ssp = '' AND ss.oth = '');
      ";
    $this->runsql( $sql, false ); 
    
    //animals do not need to be done because they don't have an infraspecific rank, oddly enough
    
    return 0;
  }
  
  public function rework_ambig_matches( $batch_id ) {
    //for ambiguous matches, select current sp by preference and add notes of all other matches to ambig_matches field
    
    //TODO: handle ambiguous genus/family matches
    
    //first do replacements
    $sql = "
SELECT s.id, s.matched_id, s.matched_level, s.matched_confidence, ss.synonymof, ss.taxstat 
FROM
submitted s 
JOIN species ss ON s.matched_id = ss.id
WHERE 
s.match_ambig = 1 AND s.batch = '" . $batch_id . "' AND s.matched_level = 'species'
    ";
    $recs = $this->select( $sql );
    foreach( $recs as $rec ) {
      if( ! $this->is_current_name( $rec['synonymof'], $rec['taxstat'] ) ) {
        $match_field = 'fullname';
        if( substr($rec['matched_confidence'],0,5) == 'naked' || substr($rec['matched_confidence'],0,8) == 'stripped' ) $match_field .= "_naked";
        $sql_others = "
SELECT s.id, s.fullname, s.fullname_naked, s.taxstat, s.family, s.source, s.synonymof
FROM
species s JOIN 
(SELECT " . $match_field . " FROM species WHERE id=" . $rec['matched_id']. ") sa 
  ON s." . $match_field . " = sa." . $match_field . "
ORDER BY synonymof, taxstat; 
        ";
        $recs_others = $this->select( $sql_others );
        foreach( $recs_others as $rec_o ) {              
          if( $this->is_current_name( $rec_o['synonymof'], $rec_o['taxstat'] ) ) {            
            //found better (i.e. current) species
            $sql = "
UPDATE submitted 
SET matched_name = '" . mysql_real_escape_string($rec_o['fullname']) . "', 
  matched_id = " . $rec_o['id'] . ", 
  matched_source ='" . $rec_o['source'] . "' 
WHERE id = " . $rec['id'] . "
            ";
            $this->runsql( $sql );         
            break;
          }
        }
      }
    }
    $recs = array();
    $recs_others = array();
    
    //now write ambig matches into notes
    $sql = "
SELECT s.id, s.matched_id, s.matched_level, s.matched_confidence, ss.synonymof, ss.taxstat 
FROM
submitted s 
JOIN species ss ON s.matched_id = ss.id
WHERE 
s.match_ambig = 1 AND s.batch = '" . $batch_id . "' 
    ";
    $recs = $this->select( $sql );
    foreach( $recs as $rec ) {
      $sql_others = "
SELECT s.id, s.fullname, s.fullname_naked, s.taxstat, s.family, s.source, s.synonymof
FROM
species s JOIN 
(SELECT " . $match_field . " FROM species WHERE id=" . $rec['matched_id']. ") sa 
  ON s." . $match_field . " = sa." . $match_field . "
ORDER BY synonymof, taxstat; 
      ";
      $notes = "";
      $recs_others = $this->select( $sql_others );
      foreach( $recs_others as $rec_o ) {
        if( $rec_o['id'] != $rec['matched_id'] ) {
          $notes .= ($notes>''? '; ' : '') .
            $rec_o['fullname'] . 
            ($rec_o['family']>''? ' [' . $rec_o['family'] . ']' : '') .
            ($rec_o['synonymof']>''? ' = ' . $rec_o['synonymof'] : '');
        }
      }
      $sql = "
UPDATE submitted
SET ambig_matches = '" . mysql_real_escape_string($notes) . "'
WHERE id = " . $rec['id'] . "
      ";
      $this->runsql( $sql ); 
    }
  }
  
  //because the data is a little messy
  private function is_current_name( $synonymof, $taxstat ) {
    if( '' == $synonymof &&
      ($taxstat === NULL || $taxstat = '' || $taxstat == 'Accepted' || $taxstat == 'Assumed accepted')
    ) {
      return true;
    } else {
      return false;
    }
  }
  
  private function min_levenshtein( $to_match, $from_set, &$best_match, $from_field = '', $disregard_sspvar_confusion = false ) {
    //from_field is used if its an array of arrays instead of a simple array
    $best_lev = 9999;
    
    if( true == $disregard_sspvar_confusion ) { 
      $to_match = str_replace(' subsp. ', '_', $to_match );
      $to_match = str_replace(' var. ', '_', $to_match );
    }
    
    foreach( $from_set as $compare ) {
      if( '' != $from_field ) {
        $comparetweaked = $compare[$from_field];
      } else {
        $comparetweaked = $compare;
      }
      if( true == $disregard_sspvar_confusion ) {
        $comparetweaked = str_replace(' subsp. ', '_', $comparetweaked );
        $comparetweaked = str_replace(' var. ', '_', $comparetweaked );
      }

      $lev = levenshtein( $to_match, $comparetweaked );
      //if ( true == $disregard_sspvar_confusion ) {
      //  echo $to_match . ' == ' . $comparetweaked . ' : ' . $lev . '<br/>';
      //}
      if( $lev < $best_lev ) {
        $best_lev = $lev;        
        $best_match = $compare;
      }
    }
    return $best_lev;
  }
  
  public function get_stats( $batch_id ) {
    $stats = array(0, 0, 0); //matched, unmatched, total
    $sql = "
SELECT
batch, isnull(matched_name) as unmatched, count(*) as num_recs 
FROM submitted 
WHERE batch = '" . $batch_id . "' GROUP BY batch, isnull(matched_name);
      ";
    $data = $this->select( $sql );
    foreach( $data as $rec ) {
      if( $rec['unmatched'] ) {
        $stats[1] = $rec['num_recs']; //unmatched
      } else {
        $stats[0] = $rec['num_recs']; //matched
      }
    }
    $stats[2] = $stats[0] + $stats[1];
   
    return $stats;
  }
  
  public function get_results_download( $batch_id ) {
    $sql = "
SELECT
s.submitted_name, s.matched_name, s.matched_id, s.matched_source, s.match_ambig, s.ambig_matches, 
ss.synonymof, ss.redlist
FROM
submitted s LEFT JOIN species ss ON s.matched_id = ss.id
WHERE
s.batch = '" . $batch_id . "'
ORDER BY s.id ASC
";
    $data = $this->select( $sql );
    // Write to memory (unless buffer exceeds 2mb when it will write to /tmp)
    $fp = fopen('php://temp', 'w+');
    //fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); //utf8 encoding
    foreach( $data as $rec ) {
      if (trim($rec['submitted_name']) != '') {
        $fields = array($rec['submitted_name'], $rec['matched_name'], $rec['synonymof'], $rec['redlist'], $rec['matched_source'], $rec['ambig_matches']);
        fputcsv($fp, $fields);
        //$res .= str_replace(","," ", $rec['submitted_name']) . ",";
        //$res .= str_replace(","," ", $rec['matched_name']) . ",";
        //$res .= str_replace(","," ", $rec['synonymof']) . ",";
        //$res .= str_replace(","," ", $rec['redlist']) . ",";
        //$res .= str_replace(","," ", $rec['matched_source']) . "\n";
      }
    }
    rewind($fp); // Set the pointer back to the start
    $res = stream_get_contents($fp); // Fetch the contents of our CSV
    fclose($fp); // Close our pointer and free up memory and /tmp space 
    return $res;
  }
  public function get_results_refs( $batch_id ) {
    $sql = "
SELECT source.* 
FROM source JOIN 
(SELECT DISTINCT matched_source FROM submitted WHERE batch = '" . $batch_id . "') srcs 
ON source.source = srcs.matched_source 
ORDER BY source ASC;
";
    $data = $this->select( $sql );
    $res = "";
    foreach( $data as $rec ) {
      $res .= '"' . $rec['source'] . ': ' . $rec['citation'] . '"' . "\n";
    }
    $sql = "
SELECT * FROM source WHERE source like '%redlist' ORDER BY source ASC;
";
    $data = $this->select( $sql );
    $res .= "\nRedlist information:\n";
    foreach( $data as $rec ) { // HARDCODED
      if ($rec['source'] == 'IUCNredlist') $res .= '"' . 'For animal species: ';
      if ($rec['source'] == 'SANBIredlist') $res .= '"' . 'For plant species: ';
      $res .= $rec['citation'] . '"' . "\n";
    }
    return $res;
  }
  
  public function get_overall_match_stats() {
    $sql = "
select 
case when matched_confidence is null then 1 else 0 end as ordering, 
case substr(ifnull(matched_confidence,'not matched'),1,4) 
when 'full' then 'full name' 
when 'nake' then 'naked name' 
when 'stri' then 'naked name' 
when 'auto' then 'full name'
when 'not ' then 'not matched' end as match_type, 
count(*) as numrecs 
from 
submitted 
group by 
case when matched_confidence is null then 1 else 0 end, 
case substr(ifnull(matched_confidence,'not matched'),1,4) 
when 'full' then 'full name' 
when 'nake' then 'naked name' 
when 'stri' then 'naked name' 
when 'auto' then 'full name' 
when 'not ' then 'not matched' end
ORDER BY ordering, numrecs DESC, match_type
";
    $data = $this->select( $sql );
    return $data;    
  }
  
  private function update_submitted_review_list() {
    $sql = "
INSERT INTO submitted_review (submitted_id, submitted_name, matched_genus, p_fullname, p_fullname_naked, tried_to_match) 
SELECT s.id, s.submitted_name, s.matched_genus, s.p_fullname, s.p_fullname_naked, 0
FROM submitted s 
LEFT JOIN submitted_review sr ON s.id = sr.submitted_id 
WHERE sr.submitted_id IS NULL AND s.matched_name IS NULL AND s.matched_genus IS NOT NULL";
    $this->runsql( $sql, false ); 
    
    //update profanity tags
    $sql = "
SELECT profanity FROM profane
";
    $data = $this->select( $sql );    
    foreach( $data as $rec ) {
      $sql = "
UPDATE submitted_review SET contains_profanity = 1 
WHERE (submitted_name LIKE '%" . $rec['profanity'] . "%' AND contains_profanity IS NULL)
        ";
    }
    $sql = "
UPDATE submitted_review SET contains_profanity = 0 
WHERE (contains_profanity IS NULL)
";
    $this->runsql( $sql, false ); 
  
    $sql = "
SELECT submitted_id, submitted_name, matched_genus, p_fullname, p_fullname_naked 
FROM submitted_review sr 
WHERE (sr.tried_to_match = 0) 
ORDER BY sr.matched_genus ASC;
      ";
    $recs = $this->select( $sql );
    $cur_gen = "";
    mysql_query("BEGIN");
    foreach( $recs as $rec ) {
      if( $rec['matched_genus'] != $cur_gen ) {
        $cur_gen = $rec['matched_genus'];
        $sql_spp = "
SELECT id, fullname, fullname_naked, fullname_isambig, fullname_naked_isambig, source 
FROM species
WHERE genus = '" . mysql_real_escape_string( $cur_gen ) . "'
          ";
        $species = $this->select( $sql_spp );
      }
      $best_match = array();              
      $dist = self::min_levenshtein( $rec['p_fullname_naked'], $species, $best_match, 'fullname_naked' );
      
      $epithet_length = strlen($rec['p_fullname_naked']) - strlen($rec['matched_genus']);      
      $sql = "
UPDATE submitted_review SET ";
      if( $dist*100/$epithet_length <= self::LEVENSHTEIN_PERCENTAGE_SPECULATIVE ) {                
        $sql .= "possible_match_name = '" . mysql_real_escape_string( $best_match['fullname'] ) . "', ";        
        $sql .= "possible_match_id = " . $best_match['id'] . ", ";
        $sql .= "matched_dist = " . $dist . ", ";          
      }       
      $sql .= "tried_to_match = 1 ";
      $sql .= "WHERE submitted_id = " . $rec['submitted_id'];
      
      $this->runsql( $sql, false ); 
    }
    mysql_query("COMMIT");
  }
  
  public function get_some_speculative_entries() {
    $this->update_submitted_review_list();
    //show [x] at random that don't include profanity
    $sql = "
SELECT * FROM submitted_review 
WHERE possible_match_name IS NOT NULL and contains_profanity = 0
ORDER BY rand()
LIMIT " . self::MAX_SPECULATIVE;
    $data = $this->select( $sql );    
    return $data;
  }
  
  public function record_speculative_feedback( $feedback ) {
    foreach( $feedback as $id => $details ) {
      $sql = "
INSERT INTO submitted_review_feedback (submitted_id, judgement, comment)
VALUES (
" . $id . ", 
'" . (isset($details['status'])? $details['status'] : '') . "',
'" . (isset($details['comment'])? mysql_real_escape_string($details['comment']) : '') . "')";
      $this->runsql( $sql ); 
    }
    return 0;
  }
}
?>