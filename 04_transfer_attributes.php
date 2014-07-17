<?php

// You can skip the whole PostGIS db bit if you have ogr2ogr spatialite support
$dbname = '';
$user = 'jmk';
$password = '';
$uniqueid = date('Y_m_d_H_i_s') . mt_rand(100, 987654321);

$user_CUSTOM_filepath = $_REQUEST['CUSTOM_uploaded_name'];
$user_CENSUS_filepath = $_REQUEST['CENSUS_uploaded_name'];
$user_group_by = $_REQUEST['group_by'];
$fields = explode(',',$_REQUEST['fields']);
$stats = explode(',',$_REQUEST['stats']);

if ( !preg_match("/[a-zA-Z0-9 \-_\/\.]+/",$user_CUSTOM_filepath) )  {
    echo $user_CUSTOM_filepath;
    return print "File names must have no special characters.";
    // alphanumeric plus dash, underscore, forward slash, period
}
if ( !preg_match("/[a-zA-Z0-9 \-_\/\.]+/",$user_CUSTOM_filepath) )  {
    return print "File names must have no special characters.";
}
if ( !ctype_alnum($user_group_by) ) {
    return print "Field names must be letters and numbers only.";
}
foreach ($fields as $value) {
    if ( !ctype_alnum($value) )  {
        return print "Field names must be letters and numbers only.";
    }
}
foreach ($stats as $value) {
    if ( !ctype_alnum($value) )  {
        return print "Field names must be letters and numbers only.";
    }
}
    
    
downloadHeader($uniqueid, $user_CUSTOM_filepath, $user_CENSUS_filepath, $user_group_by, $fields, $stats);

    
function downloadHeader($uniqueid, $user_CUSTOM_filepath, $user_CENSUS_filepath, $user_group_by, $fields, $stats) {
    $date = date('Y_m_d');
    header("Pragma: public"); // required   
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private",false); // required for certain browsers
    header("Content-Type: application/zip");
    header("Content-Disposition: attachment; filename=output_shp_$uniqueid.zip;" );
    header("Content-Transfer-Encoding: binary");
    downloadShp($uniqueid, $user_CUSTOM_filepath, $user_CENSUS_filepath, $user_group_by, $fields, $stats);
    // TODO: rename functions; split into separate, reusable functions
}


function downloadShp($uniqueid, $user_CUSTOM_filepath, $user_CENSUS_filepath, $user_group_by, $fields, $stats) {
    // The unzipped shp:
    // $infilepath = '/var/www/greeninfo/staff/jk/transfer_attributes/dev/ca3310.shp';
    global $dbname, $user, $password;
    
    $storage_directory = "/var/www/images.tmp";
    
    $dirpath = "$storage_directory/$uniqueid";
    mkdir($dirpath) or die('Error - mkdir failed');
    chmod($dirpath, 0777);
    
    //set up files
    $date = date('Y_m_d');
    $zipfilename = "$dirpath/output_shp_$uniqueid.zip";
    $filename="$dirpath/output_shp_$uniqueid.shp";
    $filename2="$dirpath/output_shp_$uniqueid.shx";
    $filename3="$dirpath/output_shp_$uniqueid.dbf";
    $filename4="$dirpath/output_shp_$uniqueid.prj";
    $csv_file="$dirpath/output_shp_$uniqueid.csv";
    

    if(file_exists("$filename")) {
        unlink("$filename");
        unlink("$filename2");
        unlink("$filename3");
    }
    
    // http://epsg.io/2163 (US National Atlas Equal Area [50 states])
    // http://epsg.io/6350 (conus)
    // http://epsg.io/5070 (conus + AK, HI, Canada)
    //ogrinfo -dialect sqlite -sql "SELECT c.field as cfield, n.field as nfield FROM 'tcc.csv'.tcc as c LEFT JOIN tnc as n ON c.field = n.field" tnc.csv    
    
    // No good: no pops, no join, takes long time: ogr2ogr census_joined.shp -dialect sqlite -sql "SELECT n.* FROM 'all_140_in_06_P1.csv'.all_140_in_06_P1 as c LEFT JOIN tl_2012_06_tract as n ON c.GEOID = n.GEOID" tl_2012_06_tract.shp
    
    $dbconn = pg_connect("dbname=$dbname user=$user password=$password");
    $result = pg_query($dbconn, "DROP TABLE IF EXISTS custom_polygons" );
    $result = pg_query($dbconn, "DROP TABLE IF EXISTS census" );
    $result = pg_query($dbconn, "DROP TABLE IF EXISTS temp_intersect" );
    $result = pg_query($dbconn, "DROP TABLE IF EXISTS final_out" );
    //$result = pg_query($dbconn, "create table buff_9k as SELECT name, ST_Buffer(wkb_geometry,9000) as geometry FROM custom_polygons" );
        
    // Load the custom polygons:
    $ogr_shp2pg = "/usr/local/gdal-1.10.0/bin/ogr2ogr -f Postgresql PG:'host=localhost dbname=$dbname user=$user password=$password' $user_CUSTOM_filepath -nln custom_polygons -nlt MULTIPOLYGON -overwrite -t_srs epsg:2163 -lco PRECISION=NO";
    `$ogr_shp2pg`;
    
    // Add IDs?
    
    // Load the census polygons:
    $ogr_shp2pg = "/usr/local/gdal-1.10.0/bin/ogr2ogr -f Postgresql PG:'host=localhost dbname=$dbname user=$user password=$password' $user_CENSUS_filepath -nln census -nlt MULTIPOLYGON -overwrite -t_srs epsg:2163 -lco PRECISION=NO";
    `$ogr_shp2pg`;
    
    // TODO: erase e.g., water? No, DON'TDO--this is hard for postgis; make user do it prior
    
    /* 
    TODO: filter out census tracts with zero pop (or null field of interest?)
    BUT: perhaps this happens automatically? zero pop effectively erases that part of custom polygon?
     */
    

    $count_fields = array();
    $ave_fields = array();
    
    $length = count($fields);
    for ($i = 0; $i < $length; $i++) {
        if ( $stats[$i] === 'count') {
            // push
            $count_fields[] = $fields[$i];
        } else {
            $ave_fields[] = $fields[$i];
        }
    }
    
    $sql_middle = implode(",",$fields);

    $sql_middle = pg_escape_string($sql_middle);
    $user_group_by = pg_escape_string($user_group_by);
    
    $escaped_query = "CREATE TABLE temp_intersect AS (SELECT ST_Area(custom_polygons.wkb_geometry) AS buff_m2, ST_Area(census.wkb_geometry) AS census_m2, custom_polygons.$user_group_by as $user_group_by, $sql_middle, ST_Intersection(census.wkb_geometry,custom_polygons.wkb_geometry) AS GEOMETRY, CAST (ST_Area(ST_Intersection(census.wkb_geometry,custom_polygons.wkb_geometry)) AS numeric) AS split_m2 FROM  custom_polygons, census WHERE  ST_Intersects(census.wkb_geometry,custom_polygons.wkb_geometry) ) ";
    
    $result = pg_query($dbconn, $escaped_query );
    
    //echo "125 $user_group_by, $sql_middle";
    //$result = pg_query_params($dbconn, 'create table foo as select $1, $2 from custom_polygons', array($user_group_by, $sql_middle) );
    
    /* 
    Fails bc inserts quotes on field names?
    $result = pg_query_params($dbconn, 'CREATE TABLE temp_intersect AS (SELECT ST_Area(custom_polygons.wkb_geometry) AS buff_m2, ST_Area(census.wkb_geometry) AS census_m2, custom_polygons.$1 as $1, ST_Intersection(census.wkb_geometry,custom_polygons.wkb_geometry) AS GEOMETRY, CAST (ST_Area(ST_Intersection(census.wkb_geometry,custom_polygons.wkb_geometry)) AS numeric) AS split_m2 FROM  custom_polygons, census WHERE  ST_Intersects(census.wkb_geometry,custom_polygons.wkb_geometry) ) ', array($user_group_by) );
     */
    
    //pg_query_params($dbconn, "insert into custom_polygons (name, ruleid, acres) values ($1, $2)", array($user_group_by, $sql_middle) );
    //pg_query_params($dbconn, "insert into custom_polygons (name) \$1", array('foo') );
    
    //$result = pg_query($dbconn, "census.$count_fields[0], census.$ave_fields[0], " );
    //$result = pg_query($dbconn, $sql_full );
    $result = pg_query($dbconn, "update temp_intersect set geometry=st_makevalid(geometry) where not st_isvalid(geometry)" );
    $result = pg_query($dbconn, "update temp_intersect set geometry=st_buffer(geometry,0.0001)" );
    //$result = pg_query($dbconn, "update temp_intersect set geometry=st_buffer(geometry,-0.0001)" );
    
     
    
    //$result = pg_query($dbconn, "CREATE TABLE final_out AS (SELECT $user_group_by, ST_Union(geometry) AS geometry, ST_Area(ST_Union(geometry)) as st_area, sum($count_fields[0] * split_m2 / census_m2) as sum_b0,  sum( ($ave_fields[0] * split_m2)/2033845.4 ) as ave_b1  FROM  temp_intersect  GROUP BY $user_group_by )" );
    
    // First get the sum of all splinter areas--we'll need it later:
    $rows = pg_fetch_row( pg_query($dbconn, "SELECT sum(split_m2) FROM temp_intersect" ) );
    $total_area = $rows[0];
    
    $sql_start = "CREATE TABLE final_out AS (SELECT $user_group_by, ST_Union(geometry) AS geometry, ST_Area(ST_Union(geometry)) as st_area, ";
    
    $sql_middle = "";
    //$length = count($fields);
    for ($i = 0; $i < $length; $i++) {
        if ( $stats[$i] === 'count') {
            // simple sum:
            $sql_middle .= " sum($fields[$i] * split_m2 / census_m2) as s$fields[$i], ";
        } else {
            // averages: don't divide each fragment
            $sql_middle .= " sum( ($fields[$i] * split_m2)/$total_area ) as a$fields[$i], ";
        }
    }
    // Remove the last space and comma:
    $sql_middle = substr($sql_middle, 0, -2);

    $sql_end = "   FROM  temp_intersect  GROUP BY $user_group_by ) ";
    
    $sql_full = $sql_start . $sql_middle . $sql_end;
    
    // Found non-noded intersection...
    //echo $sql_full;
    // CREATE TABLE final_out AS (SELECT TYPE, ST_Union(geometry) AS geometry, ST_Area(ST_Union(geometry)) as st_area,  sum( (ALAND10 * split_m2)/3006562831.7650419580158953 ) as ave_0,  sum( (AWATER10 * split_m2)/3006562831.7650419580158953 ) as ave_1,  sum(S001 * split_m2 / census_m2) as sum_2   FROM  temp_intersect  GROUP BY TYPE ) 
    // select count(*) FROM temp_intersect where st_isvalid(geometry) is not null;
    // update temp_intersect set geometry = st_makevalid(geometry) where st_isvalid(geometry) is not null;
    // update temp_intersect set geometry = st_buffer(geometry,0) ;
    // select distinct(geometryType(geometry)) from temp_intersect;
    
    //$result = pg_query($dbconn, "census.$count_fields[0], census.$ave_fields[0], " );
    $result = pg_query($dbconn, $sql_full );
    
    $ogr_pg2shp = "/usr/local/gdal-1.10.0/bin/ogr2ogr $filename PG:'host=localhost dbname=$dbname user=$user password=$password' final_out -overwrite -skipfailures -a_srs epsg:2163"; // also consider -a_srs, -s_srs, -t_srs
    
    
    `$ogr_pg2shp`;
    
    $ogr_dbf2csv = "/usr/local/gdal-1.10.0/bin/ogr2ogr -f csv $csv_file $filename";
    
    // `$ogr_dbf2csv`;

    /*echo '<pre>';
    echo file_get_contents($csv_file);
    echo '</pre>';*/

    //make the zip file
    $zip = new ZipArchive();
    if ($zip->open($zipfilename, ZIPARCHIVE::CREATE)!==TRUE) {
        exit("cannot open <$zipfilename>\n");
    }

    $zip->addFile($filename,"output_shp_$uniqueid.shp");
    $zip->addFile($filename2,"output_shp_$uniqueid.shx");
    $zip->addFile($filename3,"output_shp_$uniqueid.dbf");
    $zip->addFile($filename4,"output_shp_$uniqueid.prj");

    $zip->close();
    readfile($zipfilename);
}

?>

