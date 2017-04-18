function checkFileSize() {
    var input, file;

    // (Can't use `typeof FileReader === "function"` because apparently
    // it comes back as "object" on some browsers. So just see if it's there
    // at all.)
    if (!window.FileReader) {                
        return true; //not supported by browser, so assume ok
    }

    input = document.getElementById('spp_file');
    if (!input) {
       return true; //no file upload element, so drop out
    }
    else if (!input.files) {
        return true; //This browser doesn't seem to support the `files` property of file inputs
    }
    else if (!input.files[0]) {
        return true; //Please select a file 
    }
    else {
       file = input.files[0];
       if (file.size > MAX_FILE_SIZE) return false;
       return true;
    }
}

function validateForm(form) {
   errors = '';
   fileok = checkFileSize();
   if (!fileok) errors += 'File is too big: maximum is ' + MAX_FILE_SIZE + ' bytes';
   
   if( '' != errors ) {
      errors = 'Please correct the following errors:\r\n' + errors;
      window.alert( errors );
      return false;
   }
   form.submit();
   return true;
}

function toggleVisible(item) {
   ctl = document.getElementById(item);
   if( 'hidden' == ctl.style.visibility ) {
      ctl.style.visibility = 'visible';
   } else {
      ctl.style.visibility = 'hidden';
   }
   return;
}
