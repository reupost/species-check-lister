<b>Help us improve the Check-Lister!</b><br/>
Here are some 'almost' matches to submitted names.  They weren't good enough to include in the results, but help us decide for next time.<br/>
<br/>
Let us know what you think of the matches below.<hr> 
<form name="refleqt_species_form" 
      id="refleqt_species_form" 
      action="<?php echo $form_action ?>" 
      method="post" 
      enctype="multipart/form-data">
<input type="hidden" 
          name="species_form_submitted" 
          id="species_form_submitted" 
          value="<?php echo $species_process_next ?>"
          />

<?php foreach( $to_review as $item ) {
  $item_tag = 'spp_match_' . $item['submitted_id'];
  echo "<div class='species_list_speculative'>Submitted name:</div>" . strip_tags( $item['submitted_name'] ) . "<br/>";
  echo "<div class='species_list_speculative'>Matched name:</div>" . strip_tags( $item['possible_match_name'] ) . "<br/>";
  echo "<b>What do you think of the match?</b><br/>";
  echo 
      "<div class='species_list_speculative'></div><input type='radio' name='" . $item_tag . "' id='" . $item_tag . "' value='ok'> Looks ok<br/>" . 
      "<div class='species_list_speculative'></div><input type='radio' name='" . $item_tag . "' id='" . $item_tag . "' value='bad'> Nope<br/>";
  echo "<div class='species_list_speculative'>Comment:</div>" . "<textarea name='comment_" . $item_tag . "' id='comment_" . $item_tag . " rows='2' cols='40'></textarea>";
  echo "<br/><hr>";
}
?>
   </tbody>
</table>
  <input type="submit"
          value="<?php echo $species_process_next_label ?>"          
          />
</form>