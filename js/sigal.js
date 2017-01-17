function dowloadselected() {
	if (window.selectedPictures && window.selectedPictures.length>0) {
		var data = window.selectedPictures.concat(new Array());
		data['album'] = document.location;
		post_to_url('?dlselected', data, "post");
	}
}

function post_to_url(path, params, method) {
    method = method || "post"; // Set method to post by default if not specified.

    // The rest of this code assumes you are not using a library.
    // It can be made less wordy if you use one.
    var form = document.createElement("form");
    form.setAttribute("method", method);
    form.setAttribute("action", path);

    for(var key in params) {
        if(params.hasOwnProperty(key)) {
            var hiddenField = document.createElement("input");
            hiddenField.setAttribute("type", "hidden");
            hiddenField.setAttribute("name", "img"+key);
            hiddenField.setAttribute("value", params[key]);

            form.appendChild(hiddenField);
         }
    }

		//alert(form.innerHTML);
    document.body.appendChild(form);
    form.submit();
}

function addToDownload(file) {
	if (window.selectedPictures==undefined) {
		window.selectedPictures = new Array();
	} 
	
	var pos = window.selectedPictures.indexOf(file);
	if (pos >= 0) {
		// remove one item on pos
		window.selectedPictures.splice(pos, 1); 
	} else {
		// add new value
		window.selectedPictures.push(file);
	}
	
	// update counter
	var o=document.getElementById('multipledownloadlinkcnt');
	o.innerHTML = window.selectedPictures.length; 
}

function toggleAllCheckboxes() {
	var elems = document.getElementsByTagName('input'), i;
  for (i = 0; i < elems.length; i++) {
  	//alert(elems[i].value + "=" + elems[i].checked);
  	elems[i].checked = !elems[i].checked;
  	addToDownload(elems[i].value);
  }
}

/* preloads the next middle size image */
function preload_next(){
	var current = $("#cee_img")[0];			
	var imgs = $(".fotos a.i");
	var next = -1;
	var nexthref = null;
	imgs.each(function(k,i) {
		var gallery = $(i).data("ceebox").gallery;
		if (gallery.gNum === next) {
			nexthref = i.href;
			return false; // break
		}
		if (i.href === current.src) {
			next = gallery.gNum + 1;
			return true; // continue
		}
	});		
	
	if (nexthref !== null) {
		var imgPreload = new Image();
		imgPreload.src = nexthref;
	}
	
}
