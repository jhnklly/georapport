<!doctype html>
<head>
    <title>georapport (geo-reapportion)</title>
    <style>

    </style>

<head>
<body>
    <div id="ogr_form">
        <div id="id_picking_div"></div>
        <hr />
        <div id="stats_picking_div"></div>
        <input type="submit" id="submit_field_picking_div" value="submit" onclick="submitToOgr('ogr_form');" />
    </div>

    <div id="wait"></div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script>



<?php
/*$BASE_URL = dirname(__FILE__); // /var/www/greeninfo/staff
$BASE_URL = $_SERVER['SERVER_NAME']; // localhost
$BASE_URL = $_SERVER['REQUEST_URI']; // /var/www/greeninfo/staff
*/
$BASE_URL = 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']);

if( empty($_FILES['uploaded_FILES']['name']["'CUSTOM'"]) || ($_FILES['uploaded_FILES']['error']["'CUSTOM'"] !== 0) ) {
    return print "Don't forget to upload a zipfile (with .shp, .dbf, .shx, .prj) of your polygons of interest.";
}    
   
// Check file type and size
$filename = basename($_FILES['uploaded_FILES']['name']["'CUSTOM'"]);
$datetime = date('Y_m_d_H_i_s');
$ext = substr($filename, strrpos($filename, '.') + 1);
if ( !($ext == "zip") || ($_FILES['uploaded_FILES']['size']["'CUSTOM'"] > 99000000) ) { // 99 mb
    return print "Files must be zipped and under 99 MB.";
}
//Determine the path to which we want to save this file
//$newname = dirname(__FILE__).'/upload/'.$filename;
$newname = dirname(__FILE__).'/uploads/'.$datetime.'_CUSTOM.zip';
$unzipped_dirname = dirname(__FILE__).'/uploads/';
//Attempt to move the uploaded file to it's new place
if ( ( move_uploaded_file($_FILES['uploaded_FILES']['tmp_name']["'CUSTOM'"],$newname) ) ) {
   //echo "The file has been saved as: ".$newname;
   $zip = new ZipArchive;
   if ( $zip->open($newname)) {
        $zip->extractTo(dirname(__FILE__).'/uploads/');
        
        for ( $i=0; $i < $zip->numFiles; $i++ ) {
            $entry = $zip->getNameIndex($i); 
            $ext = pathinfo($entry, PATHINFO_EXTENSION);
            if ($ext === 'shp') {
                $CUSTOM_filepath = $unzipped_dirname . $entry;                   
            }
        }
    }
} else {
    echo "Error: A problem occurred during file upload!";
}


if((!empty($_FILES['uploaded_FILES']['name']["'CENSUS'"])) && ($_FILES['uploaded_FILES']['name']["'CENSUS'"]['error'] == 0)) {
  // Check file type and size
  $filename = basename($_FILES['uploaded_FILES']['name']["'CENSUS'"]);
  $datetime = date('Y_m_d_H_i_s');
  $ext = substr($filename, strrpos($filename, '.') + 1);
  if (($ext == "zip") && ($_FILES['uploaded_FILES']['size']["'CENSUS'"] < 99000000)) {
      //Determine the path to which we want to save this file
      //$newname = dirname(__FILE__).'/upload/'.$filename;
      $newname = dirname(__FILE__).'/uploads/'.$datetime.'_CENSUS.zip';
      $unzipped_dirname = dirname(__FILE__).'/uploads/';
      //Check if the file with the same name is already exists on the server
      if (!file_exists($newname)) {
        //Attempt to move the uploaded file to it's new place
        if ((move_uploaded_file($_FILES['uploaded_FILES']['tmp_name']["'CENSUS'"],$newname))) {
           //echo "It's done! The file has been saved as: ".$newname;
           $zip = new ZipArchive;
           if ( $zip->open($newname)) {
                $zip->extractTo(dirname(__FILE__).'/uploads/');
                
                for ( $i=0; $i < $zip->numFiles; $i++ ) {
                    $entry = $zip->getNameIndex($i); 
                    $ext = pathinfo($entry, PATHINFO_EXTENSION);
                    if ($ext === 'shp') {
                        //$info = json_decode(file_get_contents(base_url("ogr_transfer_attributes.py?filepath=$entry")), true);
                        $CENSUS_filepath = $unzipped_dirname . $entry;
                                            
                    }
                }
            
            }
           
        } else {
           echo "Error: A problem occurred during file upload!";
        }
      } else {
         echo "Error: File ".$_FILES['uploaded_FILES']['name']["'CENSUS'"]." already exists";
      }
  } else {
     echo "Gotta be a .zip (with .shp, .dbf, .shx, .prj)";
  }
} else {
 echo "Error: No CENSUS file uploaded";
}

    echo "var BASE_URL ='$BASE_URL';";
    
    $census_json = file_get_contents($BASE_URL . "/03_ogr_get_all_fields_and_types.py?filepath=$CENSUS_filepath");
    $custom_json = file_get_contents($BASE_URL . "/03_ogr_get_all_fields_and_types.py?filepath=$CUSTOM_filepath");
    // TODO: error trap
    echo "var CENSUS_uploaded_name ='$CENSUS_filepath';";
    echo "var CUSTOM_uploaded_name ='$CUSTOM_filepath';";
    echo 'var CENSUS_FIELDS_INFO =' . $census_json . ';';
    echo 'var CUSTOM_FIELDS_INFO =' . $custom_json . ';';
    //return $info;

?>


$(document).ready(function () {
    setupCustomForm(CUSTOM_FIELDS_INFO);
    setupCensusForm(CENSUS_FIELDS_INFO);
});

// TODO: showWait(), hideWait()

function setupCustomForm(fields_info) {
    //fields_info = {'field_list': ['field1','field2','field3','field4']};

    var fields_arr = fields_info.field_names;
    var fields_type_arr = fields_info.field_types;
    
    //var form_html = '<div id="ogr_form">';
    var form_html = 'Pick one "group by" (dissolve; id) field: <br />';
    for (i=0, l=fields_arr.length; i<l; i++) {
        if ( fields_type_arr[i] == 'Integer' || fields_type_arr[i] == 'String' ) {
            form_html += '<span class="field_row"><label><input type="radio" name="group_by" value="'+ fields_arr[i] +'" /> '+ fields_arr[i] +'</label><br />';
        }
    }
    
    document.getElementById('id_picking_div').innerHTML = form_html;
}

function setupCensusForm(fields_info) {
    //fields_info = {'field_list': ['field1','field2','field3','field4']};

    var fields_arr = fields_info.field_names;
    var fields_type_arr = fields_info.field_types;
    //var form_html = '<div id="ogr_form">';
    var form_html = 'Pick numeric fields to calculate, and choose either "count" or "average":<br/>';
    for (i=0, l=fields_arr.length; i<l; i++) {
        // Tie input checkbox to input radio for each
        if ( fields_type_arr[i] == 'Integer' || fields_type_arr[i] == 'Real' ) {
            form_html += '<span class="field_row"><label><input type="checkbox" name="'+ fields_arr[i] +'" value="'+ fields_arr[i] +'" /> '+ fields_arr[i] +'</label>';
            form_html += ':  count <input type="radio" name="radio_'+ fields_arr[i] +'" value="count" /> or <input type="radio" name="radio_'+ fields_arr[i] +'" value="average" /> average </span><br />';
        }
    }
    //form_html += '<input type="submit" id="submit_field_picking_div" value="submit" onclick="submitToOgr(\'ogr_form\');" />';
    //form_html += '</div>';
      
    document.getElementById('stats_picking_div').innerHTML = form_html;
}

var TEMP;
function submitToOgr(form_id) {
    var params = compileParams(form_id);
    var url    = BASE_URL + '/04_transfer_attributes.php' + '?' + $.param(params);
    //$('#wait').show();
    $.ajax({
        type: "POST",
        url: url,
        //data: postData, 
        success: function(response, status, request) {
            var disp = request.getResponseHeader('Content-Disposition');
            if (disp && disp.search('attachment') != -1) {
                //formSubmit(url, params);
                var form = $('<form method="POST" action="' + url + '">');
                $.each(params, function(k, v) {
                    form.append($('<input type="hidden" name="' + k +
                            '" value="' + v + '">'));
                });
                //$('body').append(form);
                form.submit();
                $('#wait').hide();

            }
        }
    });    
}
/*
function innerFormSubmit(url, params) {
    var form = $('<form method="POST" action="' + url + '">');
    $.each(params, function(k, v) {
        form.append( $('<input type="hidden" name="' + k + '" value="' + v + '">') );
    });
    $.ajax({
        type: "POST",
        url: url,
        success: function(response, status, request) {
            console.log('2nd success');
        }
    });
}*/

function compileParams(form_id) {
    var ogr_form = document.getElementById('ogr_form');
    
    // Construct ajax params from form 
    var submit_params = {};
    submit_params.CUSTOM_uploaded_name = CUSTOM_uploaded_name;
    submit_params.CENSUS_uploaded_name = CENSUS_uploaded_name;
    submit_params.fields = [];
    submit_params.stats = [];
    submit_params.group_by = $('input[name=group_by]:checked').val();
    
    $('#'+form_id + ' input[type="checkbox"]:checked').each(function() {
        field_name = $(this).attr('name');
        TEMP = $(this).parent().parent(); // the <label>!
        stat = $(this).parent().parent().find('input[type="radio"]:checked').val();
        submit_params.fields.push(field_name);
        submit_params.stats.push(stat);
        
    });
    submit_params.fields = submit_params.fields.join(",");
    submit_params.stats = submit_params.stats.join(",");
    //console.log(submit_params);
    return submit_params;
     
}

</script>

</body>
</html>