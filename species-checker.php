<?php
/*
Plugin Name: Refleqt Check-Lister
Plugin URI: http://refleqt.co.za/
Description: This plugin provides functionality to parse and estimate SA species names
Version: 0.2 Dec 2016
Author: Refleqt
Author URI: http://refleqt.co.za/
License: GPL2
*/

/* TODO: Dec 2016

 * To improve matches:
 *  (1) keep naked name matches as last resort
 *  (2) is it possible to consider masc/feminine for epithets?
 *  (3) investigate using EoL / GBIF services if can limit to SA species
 * 
 * Functionality:
 *  (2) 'delux' download with other details (common names, etc) and matched name broken into pieces
 */

/* ver 1: against Bolus DB now get 86.4% matching */

require_once("species-core.php");
require_once("template.php");
require_once("config.php");

class Species_Interface {
  const POST_VAR_FORM_SUBMITTED = 'species_form_submitted'; //preliminary or final
  const POST_VAR_FORM_SUBMITTED_FINAL = 'species_final'; //work done, results screen
  const POST_VAR_FORM_DOWNLOAD = 'species_download'; //downloading results
  const POST_VAR_FORM_SPECULATIVE = 'species_speculative'; //user views some speculative matches
  const POST_VAR_FORM_SPECULATIVE_FINAL = 'species_speculative_final'; //submitted speculative matches
  
  const MAX_FILE_SIZE = 800000;
	
  private function check_species_file() {
		$allowed_extensions = array('txt','csv');
		$file = $_FILES['spp_file'];
		if ($file['size'] == 0 || $file['size'] > self::MAX_FILE_SIZE) 
      if ($file['size'] > self::MAX_FILE_SIZE) {
        return 'File was too big (the maximum is ' . round(self::MAX_FILE_SIZE/1024,0) . 'kb)';
      } else {
        return 'No file uploaded';
      }
		if (! preg_match('#\.(.+)$#', $file['name'], $matches))
   		return 'File has no extension';
		if (! in_array($matches[1], $allowed_extensions))
   		return "Extension {$matches[1]} is not allowed (must be .txt or .csv)";
   	if ($file['type'] != "text/plain" && $file['type'] != '') 
   		return "File does not appear to be a text file";
   	
		return "File ok";
	}
  
  private function get_post_params_to_session_array( $S_Data ) {
	//returns array of criteria for adding to the session from the form's POST'd parameters		
		
		$submitted = array();
		$submitted['species_process'] = ( isset( $_POST[self::POST_VAR_FORM_SUBMITTED] ) ) ? trim( strip_tags( $_POST[self::POST_VAR_FORM_SUBMITTED] ) ) : '';
    
    switch( $submitted['species_process'] ) {
      
      case '':                                    
        $submitted['species_process_next'] = self::POST_VAR_FORM_SUBMITTED_FINAL; 
        $submitted['species_process_next_label'] = 'Check species';
        break;
      
      case self::POST_VAR_FORM_SUBMITTED_FINAL:        
        $submitted['species_process_next'] = self::POST_VAR_FORM_DOWNLOAD; 
        $submitted['species_process_next_label'] = 'Download your results';
        //get species file and/or copy-pasted list
        $submitted['batch_id'] = 0;
        
        set_time_limit(180); //allow for 3 min of activity
        if( isset( $_POST['spp_textarea'] ) ) {
          $spplist = trim( strip_tags( $_POST['spp_textarea'] ) );
          $submitted['batch_id'] = $S_Data->write_species_txt_to_db( $spplist ); 
        }
        $fstatus = self::check_species_file();
        $submitted['spp_file'] = $fstatus;
        if ($fstatus == "File ok") {
          $submitted['batch_id'] = $S_Data->write_species_file_to_db( $submitted['batch_id'] );
        }
        $S_Data->remove_duplicates( $submitted['batch_id'] );
        break;
      
      case self::POST_VAR_FORM_DOWNLOAD:
        $submitted['batch_id'] = isset( $_POST['spp_batch_id'] )? $_POST['spp_batch_id'] : '' ;
        break;
      
      case self::POST_VAR_FORM_SPECULATIVE:
        $submitted['species_process_next'] = self::POST_VAR_FORM_SPECULATIVE_FINAL;
        $submitted['species_process_next_label'] = 'Submit';
        break;
        
      case self::POST_VAR_FORM_SPECULATIVE_FINAL:        
        foreach( $_POST as $key => $val ) {
          if( substr($key, 0, strlen("spp_match_")) == "spp_match_" ) $submitted[substr($key, strlen("spp_match_"))]['status'] = $val;
          if( substr($key, 0, strlen("comment_spp_match_")) == "comment_spp_match_" ) {
            if( $val != '' ) $submitted[substr($key, strlen("comment_spp_match_"))]['comment'] = $val;
          }
        }
        
      default:
        $submitted['species_process_next'] = 'Error'; 
        $submitted['species_process_next_label'] = 'Error';
        break;
    }
    
		return $submitted;
	}
	
  
	public function species_form_display( $atts ) {
	//returns the HTML for an species form and results.  
		global $DB_SERVER;
		global $DB_DATABASE;
		global $DB_USERNAME;
		global $DB_PASSWORD;
    
    $form_action = get_permalink(); //will always postback to same page
    
    $time_start = microtime(true);
    
    $S_Data = new Species_Data();
		$S_Data->set_db_connection( $DB_SERVER, $DB_DATABASE, $DB_USERNAME , $DB_PASSWORD );
		$err = $S_Data->get_error_state();
		if ( "" != $err ) {
			$markup = $err;
			return $markup;
		}		
    
    $submitted = self::get_post_params_to_session_array( $S_Data ); //get any data submitted by user
        
    date_default_timezone_set('Africa/Johannesburg');
    $date = date('m/d/Y h:i:s a', time());    
    
    
    if( '' == $submitted['species_process'] ) {
      
      $body = & new Template('templates/start_species.tpl.php');
      $body->set('javascript_constants', 'MAX_FILE_SIZE = ' . self::MAX_FILE_SIZE . ';');
      $body->set('form_action', $form_action);
      $body->set('species_process_next', $submitted['species_process_next']);
      $body->set('species_process_next_label', $submitted['species_process_next_label']);
      
    } elseif( $submitted['species_process'] == self::POST_VAR_FORM_SUBMITTED_FINAL ) {
      
      $content = self::process_species( $submitted['batch_id'], $S_Data );
      $stats = $S_Data->get_stats( $submitted['batch_id'] );
      $overall_stats = $S_Data->get_overall_match_stats();      
      $time_finish = microtime(true);   
      
      $body = & new Template( 'templates/finish_species.tpl.php' );  
      $body->set( 'form_content', $content );  
      $body->set( 'file_status', $submitted['spp_file'] );
      $body->set( 'form_action', $form_action );  
      $body->set('species_process_next', $submitted['species_process_next']);
      $body->set('species_process_next_label', $submitted['species_process_next_label']);
      $body->set( 'batch_id', $submitted['batch_id'] );
      $body->set( 'stats', $stats );
      $body->set( 'overall_stats', $overall_stats );
      $body->set( 'time_taken', $time_finish - $time_start );
      $body->set( 'user_contribute', self::POST_VAR_FORM_SPECULATIVE );
         
    } elseif( $submitted['species_process'] == self::POST_VAR_FORM_DOWNLOAD ) {
      
    } elseif( $submitted['species_process'] == self::POST_VAR_FORM_SPECULATIVE ) {
      $to_review = $S_Data->get_some_speculative_entries();
      $body = & new Template( 'templates/start_speculative.tpl.php' );  
      $body->set( 'to_review', $to_review );  
      $body->set( 'form_action', $form_action );
      $body->set( 'species_process_next', $submitted['species_process_next'] );
      $body->set( 'species_process_next_label', $submitted['species_process_next_label'] );
        
    } elseif( $submitted['species_process'] == self::POST_VAR_FORM_SPECULATIVE_FINAL ) {
      $content = self::process_speculative( $submitted, $S_Data );
      $body = & new Template( 'templates/finish_speculative.tpl.php' );        
      $body->set( 'form_action', $form_action );      
      $body->set( 'content', $content );
      $body->set( 'user_contribute', self::POST_VAR_FORM_SPECULATIVE );
    }
    
    $markup = $body->fetch();
    return $markup;
  }
  
// -------------------------------------------------------------------------------------------------
  
  
  private function process_species( $batch_id, $S_Data ) {
    global $SPP_ADMIN;
    $efficiency_test = false;
    set_time_limit(180); //allow for 3 min of activity
    
    //eficiency: don't don't parse perfect matches
    if ($efficiency_test) echo "Start: " . microtime(true) . "<br/>";   
    $S_Data->set_perfect_matches( $batch_id );
    if ($efficiency_test) echo "After perfect: " . microtime(true) . "<br/>";   
    //parse rest
    
    $S_Data->parse_names( $batch_id );
    if ($efficiency_test) echo "After parse: " . microtime(true) . "<br/>";   
    
    //set match on author parts if maybe using only old/new authority
    $S_Data->set_author_part_matches( $batch_id );
    if ($efficiency_test) echo "After set author partwise: " . microtime(true) . "<br/>";   
    
    //set easy matches for full and naked names
    $S_Data->set_species_matches( $batch_id, 'families', false );
    if ($efficiency_test) echo "After set families full: " . microtime(true) . "<br/>";   
    $S_Data->set_species_matches( $batch_id, 'families', true );
    if ($efficiency_test) echo "After set families naked: " . microtime(true) . "<br/>";   
    $S_Data->set_species_matches( $batch_id, 'genera', false );
    if ($efficiency_test) echo "After set genera full: " . microtime(true) . "<br/>";   
    $S_Data->set_species_matches( $batch_id, 'genera', true );
    if ($efficiency_test) echo "After set genera naked: " . microtime(true) . "<br/>";   
    //this is the same as perfect matches, so skip:
    //$S_Data->set_species_matches( $batch_id, 'species', false );
    //issue with below is if the names had very different authors then it is a problem, should not simply match
    $S_Data->set_species_matches( $batch_id, 'species', true );
    if ($efficiency_test) echo "After set species full: " . microtime(true) . "<br/>";   
    
    //now need to set genus matches: perfect and then with levenshtein distance < 2
    $S_Data->set_genus_matches( $batch_id, 'perfect' );
    if ($efficiency_test) echo "After match genera perfect: " . microtime(true) . "<br/>";   
    $S_Data->set_genus_matches( $batch_id, 'levenshtein' );
    if ($efficiency_test) echo "After match genera leven: " . microtime(true) . "<br/>";   
    
    //using genus matches now try for good levenshtein matches on full names, not allowing ssp/var confusion
    $S_Data->set_species_matches_fuzzy( $batch_id, 'full name' );
    if ($efficiency_test) echo "After set spp full fuzzy: " . microtime(true) . "<br/>";   
    $S_Data->set_species_matches_fuzzy( $batch_id, 'naked name' );
    if ($efficiency_test) echo "After set spp naked fuzzy: " . microtime(true) . "<br/>";
    
    //set autonym matches
    $S_Data->set_species_matches_autonym( $batch_id );
    if ($efficiency_test) echo "After set autonym: " . microtime(true) . "<br/>";
    
    //set subsp/var confused matches
    $S_Data->set_species_matches_ssp_var( $batch_id, 'full name' );    
    $S_Data->set_species_matches_ssp_var( $batch_id, 'naked name' );
    if ($efficiency_test) echo "After set ssp/var full+naked: " . microtime(true) . "<br/>";
    
    $S_Data->set_species_matches_fuzzy( $batch_id, 'full name', true);
    $S_Data->set_species_matches_fuzzy( $batch_id, 'naked name', true);
    if ($efficiency_test) echo "After set ssp/var fuzzy: " . microtime(true) . "<br/>";
    
    //finally, set stripped matches (where submitted authors seem too wrong to match otherwise so force naked match)
    $S_Data->set_species_matches_stripped( $batch_id );
    if ($efficiency_test) echo "After set stripped: " . microtime(true) . "<br/>";
    
    //for ambiguous match, try to pick a current name not synonymous one
    $S_Data->rework_ambig_matches( $batch_id );
    if ($efficiency_test) echo "After rework ambigs: " . microtime(true) . "<br/>";
    
    return 'Analysis complete';
  }
  
  private function process_speculative( $submitted, $S_Data ) {
    $speculative = array();
    foreach( $submitted as $key => $val ) {
      if( is_numeric($key) ) $speculative[$key] = $val;
    }
    if( sizeof( $speculative ) ) {
      $S_Data->record_speculative_feedback( $speculative );
      return 'Thank you for your feedback.';
    } else {
      return ''; //no feedback received
    }
  }
  
  // ---------------------------------------------------------------------------------------------------------
  
  public function add_scripts_and_styles( $posts ) {
	//this function adds conditional JS and CSS depending on the shortcode embedded within the page content
   
		if ( empty( $posts ) ) return $posts; 
	
		$shortcode_found = false; // use this flag if styles and scripts need to be enqueued
		foreach( $posts as $post ) {
			if ( stripos( $post->post_content, '[Refleqt_Species_Checker') !== false) {
        $shortcode_found = true;
        break;
      }
    }
   
		if ( $shortcode_found ) {
      
			$my_js_ver  = date("ymd-Gis", filemtime( plugin_dir_path( __FILE__ ) . 'js/species.js' ));
      $my_css_ver = date("ymd-Gis", filemtime( plugin_dir_path( __FILE__ ) . 'css/species.css' ));
      
			//if ( 'foodieness.js' != $search_js ) wp_enqueue_script( 'googlecharts_js', 'https://www.google.com/jsapi' ); //google charts
      
      //if( wp_script_is( 'responsive-child', 'enqueued' ) ) wp_dequeue_style( 'responsive-child' );
      //if( wp_script_is( 'responsive-child', 'registered' ) ) wp_deregister_style( 'responsive-child' );
			//wp_register_style( 'responsive-child', get_stylesheet_uri(), array(), $my_css_ver );      
      wp_enqueue_style( 'species', plugins_url( 'css/species.css' , __FILE__ ), array(), $my_css_ver );
			wp_enqueue_script( 'species.js', plugins_url( 'js/species.js' , __FILE__ ), array(), $my_js_ver );
		}
 
		return $posts;
	}
  
  function download_template( $template )
  //allows download of results OR transparently falls through to normal template
  {
    if( isset( $_POST[self::POST_VAR_FORM_SUBMITTED] ) ) {
      if( $_POST[self::POST_VAR_FORM_SUBMITTED] == self::POST_VAR_FORM_DOWNLOAD ) {
        header("Content-type: application/octet-stream; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"species_results.csv\"");
        header("Pragma: public");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        echo "\xEF\xBB\xBF"; //UTF-8 BOM for Excel
        printf("Submitted name,Matched name,Current accepted name,Redlist status,Source,Ambiguities\n");
        $batch_id = isset( $_POST['spp_batch_id'] )? $_POST['spp_batch_id'] : '' ;
        
        global $DB_SERVER;
        global $DB_DATABASE;
        global $DB_USERNAME;
        global $DB_PASSWORD;
        $S_Data = new Species_Data();
        $S_Data->set_db_connection( $DB_SERVER, $DB_DATABASE, $DB_USERNAME , $DB_PASSWORD );
        $err = $S_Data->get_error_state();
        if ( "" != $err ) {
          printf("%s\n",$err);
        }		
        $file_content = $S_Data->get_results_download( $batch_id );
        printf("%s",$file_content);
        printf("\nReferences:\n");
        $refs = $S_Data->get_results_refs( $batch_id );
        printf("%s",$refs);

        date_default_timezone_set('Africa/Johannesburg');
        printf("\n\nData downloaded from http://refleqt.co.za on: %s\n", date('d/m/Y h:i:s a', time()));
        printf("Thank you!");
        return 0;
      }
    }
    return $template;
  }
}





// -------------------------------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------------

add_filter( 'template_include', array( 'Species_Interface', 'download_template' ) );

/* this allows you to use shortcodes (i.e. '[]' constructions) on your WordPress pages */
add_shortcode( 'Refleqt_Species_Checker', array( 'Species_Interface', 'species_form_display' ) );

/* this inserts [x] function into the page initialisation part of the WordPress workflow */
//add_action( 'init', array( 'Species_Interface', 'species_form_process' ) );

/* this adds the check to see if javascripts or css needs to be loaded when preparing a post to be viewed; i.e. whenever a WordPress Post is loaded 
the content is scanned to check if it contains the [x] shortcode, and appropriate CSS / JS is loaded at that point */
add_filter( 'the_posts', array( 'Species_Interface', 'add_scripts_and_styles' ) ); // the_posts gets triggered before wp_head

?>