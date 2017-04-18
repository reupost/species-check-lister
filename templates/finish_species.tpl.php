<b>Thank you</b>
<br/>
<br/>
<?php echo $form_content ?><br/>
<?php echo $file_status ?><br/>
<b><?php echo $stats[2] ?></b> names checked<br/>
<b><?php echo $stats[0] . " (" . round(($stats[0]*100)/($stats[2]? $stats[2] : 1)) . "%)" ?></b> names were matched<br/>
<b><?php echo $stats[1] . " (" . round(($stats[1]*100)/($stats[2]? $stats[2] : 1)) . "%)" ?></b> names were not matched<br/>
<br/>
<?php echo (isset($time_taken)? sprintf("%01.2f", $time_taken) . " seconds <br/>" : "") ?>
<form name="refleqt_species_form" 
      id="refleqt_species_form" 
      onsubmit="return validateForm(this)" 
      action="<?php echo $form_action ?>" 
      method="post" 
      enctype="multipart/form-data">
<input type="hidden" 
          name="spp_batch_id" 
          id="spp_batch_id" 
          value="<?php echo $batch_id ?>"
          />
<input type="hidden" 
          name="species_form_submitted" 
          id="species_form_submitted" 
          value="<?php echo $species_process_next ?>"
          />
  <input type="button"
          value="<?php echo $species_process_next_label ?>"
          onclick="javascript:validateForm(this.form)"
          />
</form>
<form action="<?php echo $form_action ?>" method="post" enctype="multipart/form-data">
<input type="hidden" 
          name="species_form_submitted" 
          id="species_form_submitted" 
          value=""
          />  
   <input type='submit' id='speculative_button_link2' value='Return'> to the submission form.
</form>
<form action="<?php echo $form_action ?>" method="post" enctype="multipart/form-data">
   <input type="hidden" 
          name="species_form_submitted" 
          id="species_form_submitted" 
          value="<?php echo $user_contribute ?>"
          />
<b>You can help us improve the Check-Lister!</b>&nbsp;
   <input type='submit' id='speculative_button_link' value='Review'> a few names (from all submissions) that could not be matched.<br/>
</form>
<hr/>
<b>Overall statistics:</b><br/>
<table class='species_stats_table'>
   <thead>
      <tr>
         <td>Match type</td>         
         <td colspan='2'>Records matched</td>
      </tr>
   </thead>
   <tbody>      
<?php 
  $total = 0;
  foreach ($overall_stats as $stat) {
    $total += $stat['numrecs'];    
  }  
  foreach ($overall_stats as $stat) {    
    echo "<tr>";
    if( $stat['match_type'] != 'not matched' ) {
      $match_type_shortcode = str_replace(" ","_",$stat['match_type']);                  
      echo "<td>" . do_shortcode("[infopopup tag=" . $match_type_shortcode . "]") . "</td>";      
    } else {
      echo "<td><i>" . ucfirst($stat['match_type']) . "</i></td>";            
    }
    echo "<td>" . $stat['numrecs'] . "</td>";
    echo "<td>" . round(($stat['numrecs']*100)/$total) . "%" . "</td>";
    echo "</tr>";
  }
?>
   </tbody>
</table>