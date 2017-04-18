<?php echo $content; ?>

<form action="<?php echo $form_action ?>" method="post" enctype="multipart/form-data">
   <input type="hidden" 
          name="species_form_submitted" 
          id="species_form_submitted" 
          value="<?php echo $user_contribute ?>"
          />
   <input type='submit' id='speculative_button_link' value='Review'> a few more names?
</form>
OR</br>
<form action="<?php echo $form_action ?>" method="post" enctype="multipart/form-data">
<input type="hidden" 
          name="species_form_submitted" 
          id="species_form_submitted" 
          value=""
          />  
   <input type='submit' id='speculative_button_link2' value='Return'> to the submission form.
</form>