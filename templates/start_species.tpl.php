<script type='text/javascript'>
  <?php echo $javascript_constants ?>
</script>
This tool allows you to verify a list of scientific names.  The focus is on South African species.  For each name submitted, the tool will try to find the full correct scientific name, the currently accepted name (if the name is a synonym), and the current Red List status for that species.<br/>
<br/>
<b>Load your list of scientific names</b><br/>
The list should just contain the names, one per line, and no additional columns of information.<br/>
<form name="refleqt_species_form" 
      id="refleqt_species_form" 
      onsubmit="return validateForm(this)" 
      action="<?php echo $form_action ?>" 
      method="post" 
      enctype="multipart/form-data">
   
  Select file to upload (TXT or CSV only):<br/>
  <input type="file" name="spp_file" id="spp_file" accept=".txt,.csv"><?php echo do_shortcode("[infopopup tag=file_help]") ?><br/>
  <br/>
  Or copy and paste your list of names below:<br/>
  <textarea id="spp_textarea" name="spp_textarea" cols="40" rows="15">      
  </textarea><br/>
  <br/>
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