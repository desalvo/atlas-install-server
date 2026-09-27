<?php

require('db.php');        // database connect script.
require('config.php');    // Main configuration
require('user_info.php'); // User informations
?>

<HTML>
<HEAD>
<TITLE><?php echo $LJSFi_VO; ?> Installation System - Release Definition</TITLE>
<?php require("../css/page_header.php"); page_header(".."); ?>
<script type="text/javascript">
function openpop(url) {
    newWin = window.open(url,'Details','scrollbars=no,resizable=yes, width=300, height=300,status=no,location=no,toolbar=no');
}
function closeWin() {
    self.close();
}

function selectRow(form,id) {
  var elm = document.getElementById('parsel_'+id);
  elm.checked=true;
  var elm = document.getElementById('tr_'+id);
  elm.className="selection3";
}

var btnWhichButton;

function checkform(form) {
  var retval=true;
  var fields = document.getElementsByName('pardef_field_name[]');
  var fnames = new Array();
  for(i = 0; i < fields.length; i++) {
    if (fnames[fields[i].value] == undefined) {
      fnames[fields[i].value] = 1;
    } else {
      fnames[fields[i].value]++;
    }
    if (fnames[fields[i].value] > 1) {
      alert("Field names must be unique");
      retval = false;
    }
  }
  var selection = document.getElementById('parsel_'+(fields.length-1));
  if (fields[fields.length-1].value == "" && selection.checked) {
    alert("No field name specified");
    retval=false;
  }
  if (btnWhichButton.value == 'Delete') {
    answ = confirm('Do you really want to delete the selected parameters? Only optional parameters will be deleted.');
    if (!answ) {
      retval=false
    } 
  }
  return retval;
}
</script>

</HEAD>
<BODY>
<script type="text/javascript" src="../js/wz_tooltip.js"></script>
  <div id="main">
    <div id="header">
<?php require("../css/main_header.php"); main_header($LJSFi_VO, ".."); ?>
<?php require("../css/menubar.php"); menubar(".."); ?>
<?php include "../css/req_help.html" ?>
    </div>
    <div id="content_header"></div>
    <div id="site_content2">
      <div id="content" align="center">
        <!-- insert the page content here -->
        <h3><?php echo $LJSFi_VO; ?> Installation - Release Parameter Definitions</h3>

<?php
  function tableHeader() {
    echo ('<TH class="selection"><CENTER>Select<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'Use these check boxes to select the parameters\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Name<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'The name of the parameter, must be unique\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Type<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'The parameter type\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Null<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'Allow NULL for this parameter\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Default<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'The default value\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Query<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'Selection query for calculated fields\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Show<BR>conf<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'Check this if you want to use this parameter in the configurations\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Show<BR>def<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'Check this is you want to show this parameters in the release definitions\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Format<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'The parameter format, as in C printf. If omitted, a value of %s is assumed. If the parameter has no value, the format is not considered.\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Regexp<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'A regexp to be applied to the parameter value, in the form /[from]/[to]/.\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Token<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'Token name, i.e. the token that will be changed to the parameter value in the template files\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Description<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'A textual description of this parameter, will be used in release definitions\')" onmouseout="UnTip()"></CENTER></TH>');
    echo ('<TH class="selection"><CENTER>Help<BR><img src="../img/help.gif" border="0" onmouseover="Tip(\'A small help on the usage of this parameter\')" onmouseout="UnTip()"></CENTER></TH>');
  }
  // Check the user's credentials
  $sslusername = getenv("SSL_CLIENT_S_DN_CN");
  $ssluserdetails = getenv("SSL_CLIENT_S_DN");
  $user_info = get_user_info();
  if (count($user_info) == 0) {
    $role="";
  } else {
    $userref = $user_info[0]['ref'];
    $role=$user_info[0]['role'];
    $enabled=$user_info[0]['enabled'];
  }

  if (!isset($userref)) {
    echo ("<CENTER><FONT SIZE=+1 COLOR='red'><B>Unknown user. Please <A HREF='user.php'>register to LJSFi.</A></B></FONT></CENTER>\n");
  } elseif ($enabled == 0) {
    echo ("<CENTER><FONT SIZE=+1 COLOR='red'><B>Your user is disabled. Please contact the LJSFi administrator.</B></FONT></CENTER>\n");
  } elseif ($role != "admin" && $role != "master") {
    echo ("<CENTER><FONT SIZE=+1 COLOR='red'><B>You don't have enough privileges to manage parameters</B></FONT></CENTER>");
  } else {
    // Checkbox map
    $cb_map = array(0 => "", 1 => " checked");
    // NULL map
    $null_map = array ("field_typefk" => "NULL"
                      ,"field_nullfk" => "1"
                      ,"field_default" => "NULL"
                      ,"field_query" => "NULL"
                      ,"show_conf" => "0"
                      ,"show_def" => "0"
                      ,"format" => "NULL"
                      ,"fregexp" => "NULL"
                      ,"token" => "NULL"
                      ,"description" => "NULL"
                      ,"help" => "NULL");
    if (isset($_POST["submit"]) && $_POST["submit"] == "Submit") {
      // Check for needed fields
      if ( !isset($_POST['pardef_field_name'])  || $_POST['pardef_field_name'] == "") {
        echo "<FONT COLOR='RED' SIZE=+1><B>No parameter name or description specified</B></FONT>";
      } else {
        // Save the parameter definition in the Database
        $field_list = array();
        $sel_list = array();
        $ref_list = array();
        if (isset($_POST["parsel"])) $sel_list = $_POST["parsel"];
        if (isset($_POST["parref"])) $ref_list = $_POST["parref"];
        if (isset($_POST["pardef_table_name"])) $field_list["table_name"] = $_POST["pardef_table_name"];
        if (isset($_POST["pardef_field_name"])) $field_list["field_name"] = $_POST["pardef_field_name"];
        if (isset($_POST["pardef_field_typefk"])) $field_list["field_typefk"] = $_POST["pardef_field_typefk"];
        if (isset($_POST["pardef_field_nullfk"])) $field_list["field_nullfk"] = $_POST["pardef_field_nullfk"];
        if (isset($_POST["pardef_field_default"])) $field_list["field_default"] = $_POST["pardef_field_default"];
        if (isset($_POST["pardef_field_query"])) $field_list["field_query"] = $_POST["pardef_field_query"];
        if (isset($_POST["pardef_format"])) $field_list["format"] = $_POST["pardef_format"];
        if (isset($_POST["pardef_fregexp"])) $field_list["fregexp"] = $_POST["pardef_fregexp"];
        if (isset($_POST["pardef_token"])) $field_list["token"] = $_POST["pardef_token"];
        if (isset($_POST["pardef_description"])) $field_list["description"] = $_POST["pardef_description"];
        if (isset($_POST["pardef_help"])) $field_list["help"] = $_POST["pardef_help"];
        $field_list["show_conf"] = array();
        $field_list["show_def"]  = array();
        for ($i=0; $i<count($field_list["table_name"]); $i++) {
          $field_list["show_conf"][$i] = 0;
          $field_list["show_def"][$i]  = 0;
        }
        if (isset($_POST["pardef_show_conf"])) {
          foreach ($_POST["pardef_show_conf"] as $key) $field_list["show_conf"][$key] = 1;
        }
        if (isset($_POST["pardef_show_def"])) {
          foreach ($_POST["pardef_show_def"] as $key) $field_list["show_def"][$key] = 1;
        }
        $query_fields = array();
        foreach ($sel_list as $sel_id) {
          foreach (array_keys($field_list) as $field) {
            if (isset($field_list[$field][$sel_id]) && $field_list[$field][$sel_id] != "") {
              $query_fields[$field] = db_quote($field_list[$field][$sel_id],'rw');
            } else {
              $query_fields[$field] = $null_map[$field];
            }
          }
          if (isset($ref_list[$sel_id]) && $ref_list[$sel_id] != "new") {
            // Update an existing record
            $query = "";
            $query_values = array();
            foreach ($query_fields as $fname => $fval) array_push($query_values, db_identifier($fname)."=".$fval);
            if (count($query_values) > 0) $query = "UPDATE field_descriptions SET ".implode(",",$query_values)." WHERE ref=".db_int($ref_list[$sel_id],1);
          } else {
            // Insert a new record
            $query = "INSERT INTO field_descriptions (".implode(",",array_map('db_identifier',array_keys($query_fields))).") VALUES (".implode(",",$query_fields).")";
          }
          if ($query != "") {
            db_query($query);
          } else {
            echo "Invalid parameters";
          }
        }
      }
    } elseif (isset($_POST["submit"]) && $_POST["submit"] == "Delete") {
      if (isset($_POST["parsel"])) $sel_list = $_POST["parsel"];
      if (isset($_POST["parref"])) $ref_list = $_POST["parref"];
      if (isset($_POST["pardef_table_name"])) $table_list = $_POST["pardef_table_name"];
      $id_list = array();
      foreach ($sel_list as $sel) {
        if (   isset($ref_list[$sel]) && $ref_list[$sel] != "new"
            && isset($table_list[$sel]) && $table_list[$sel] == 'release_data_ext')
          array_push($id_list,db_int($ref_list[$sel],1));
      }
      if (count($id_list) > 0) {
        $query = "DELETE FROM release_data_ext WHERE fieldfk IN (".db_int_list($id_list,1).")";
        db_query($query);
        $query = "DELETE FROM field_descriptions WHERE ref IN (".db_int_list($id_list,1).") AND table_name='release_data_ext'";
        db_query($query);
        echo count($id_list)." record(s) deleted.<BR>";
        echo "All data belonging to the selected record(s) has been purged.<BR>";
      } else {
        echo "No record deleted.<BR>";
      }
    }
?>
<TABLE id='select_tbl' border="1" rules="groups">
<COLGROUP width="20"></COLGROUP>
<COLGROUP width="350"></COLGROUP>
<COLGROUP></COLGROUP>
<COLGROUP></COLGROUP>
<COLGROUP></COLGROUP>
<COLGROUP></COLGROUP>
<COLGROUP></COLGROUP>
<COLGROUP></COLGROUP>
<COLGROUP></COLGROUP>
<COLGROUP></COLGROUP>
<COLGROUP></COLGROUP>
<COLGROUP></COLGROUP>
<?php
    tableHeader();
    // Show the table selection for the parameters
    echo ('<form method="post" name="select" action="pardef.php" onsubmit="return checkform(this);">');
    // Get the field type
    $qry_res = db_query("SELECT * FROM field_types");
    $field_types = array();
    while ($row = mysqli_fetch_assoc($qry_res)) $field_types[$row["ref"]] = $row["name"];
    // Get the status list
    $qry_res = db_query("SELECT * FROM status");
    $status_list = array();
    while ($row = mysqli_fetch_assoc($qry_res)) $status_list[$row["ref"]] = $row["description"];
    // Get the current field descriptions
    $qry_res = db_query("SELECT * FROM field_descriptions WHERE table_name='release_data' OR table_name LIKE 'release_data.%' OR table_name='release_data_ext'");
    $field_desc = array();
    while ($par = mysqli_fetch_assoc($qry_res)) $field_desc[$par['field_name']] = $par;
    // Show the fields in tabular form
    $col_type = 1;
    $indx = 0;
    foreach ($field_desc as $field_name => $field_data) {
      echo ('<tr id="tr_'.$indx.'" class="selection'.$col_type.'">');
      echo ('<td align="center"><input type="checkbox" id="parsel_'.$indx.'"name="parsel[]" value="'.$indx.'">');
      echo ('<input type="hidden" name="parref[]" value="'.$field_data["ref"].'">');
      echo ('<input type="hidden" name="pardef_table_name[]" value="'.$field_data["table_name"].'"></td>');
      echo ('<td>'.$field_name);
      echo ('<input type="hidden" name="pardef_field_name[]" value="'.$field_name.'"></td>');
      echo ('<td><select name="pardef_field_typefk[]" onchange="selectRow(this,\''.$indx.'\');">');
      foreach ($field_types as $key => $value) {
        echo ('<option value="'.$key.'"');
        if ($field_data["field_typefk"] == $key) echo " selected";
        echo ('>'.$value.'</option>');
      }
      echo ('</select></td>');
      echo ('<td><select name="pardef_field_nullfk[]" onchange="selectRow(this,\''.$indx.'\');">');
      foreach ($status_list as $key => $value) {
        echo ('<option value="'.$key.'"');
        if ($field_data["field_nullfk"] == $key) echo " selected";
        echo ('>'.$value.'</option>');
      }
      echo ('</select></td>');
      echo ('<td><input type="text" id="parnew_field_default" name="pardef_field_default[]" value="'.$field_data["field_default"].'" onchange="selectRow(this,\''.$indx.'\');"></td>');
      echo ('<td><textarea cols="40" rows="4" name="pardef_field_query[]" onchange="selectRow(this,\''.$indx.'\');">'.$field_data["field_query"].'</textarea></td>');
      echo ('<td><input type="checkbox" name="pardef_show_conf[]" value="'.$indx.'"'.$cb_map[$field_data["show_conf"]].' onclick="selectRow(this,\''.$indx.'\');"></td>');
      echo ('<td><input type="checkbox" name="pardef_show_def[]" value="'.$indx.'"'.$cb_map[$field_data["show_def"]].' onclick="selectRow(this,\''.$indx.'\');"></td>');
      echo ('<td><input type="text" name="pardef_format[]" size="15" value="'.$field_data["format"].'" onchange="selectRow(this,\''.$indx.'\');"></td>');
      echo ('<td><input type="text" name="pardef_fregexp[]" size="15" value="'.$field_data["fregexp"].'" onchange="selectRow(this,\''.$indx.'\');"></td>');
      echo ('<td><input type="text" name="pardef_token[]" size="15" value="'.$field_data["token"].'" onchange="selectRow(this,\''.$indx.'\');"></td>');
      echo ('<td><textarea cols="30" rows="4" name="pardef_description[]" onchange="selectRow(this,\''.$indx["ref"].'\');">'.$field_data['description'].'</textarea></td>');
      echo ('<td><textarea cols="30" rows="4" name="pardef_help[]" onchange="selectRow(this,\''.$indx["ref"].'\');">'.$field_data['help'].'</textarea></td>');
      echo ("</tr>\n");
      $indx++;
      $col_type++;
      if ($col_type > 2) $col_type = 1;
    }
    // New field
    echo ('<tr><td colspan="12" class="selection4">New field</td></tr>');
    tableHeader();
    echo ('<tr id="tr_'.$indx.'" class="selection'.$col_type.'">');
    echo ('<td align="center"><input type="checkbox" id="parsel_'.$indx.'"name="parsel[]" value="'.$indx.'">');
    echo ('<input type="hidden" name="parref[]" value="new">');
    echo ('<input type="hidden" name="pardef_table_name[]" value="release_data_ext"></td>');
    echo ('<td>');
    echo ('<input type="text" id="parnew_field_name" name="pardef_field_name[]" value="" onchange="selectRow(this,\''.$indx.'\');"></td>');
    echo ('<td><select name="pardef_field_typefk[]" onchange="selectRow(this,\''.$indx.'\');">');
    foreach ($field_types as $key => $value) {
      echo ('<option value="'.$key.'"');
      if ($field_data["field_typefk"] == $key) echo " selected";
      echo ('>'.$value.'</option>');
    }
    echo ('</select></td>');
    echo ('<td><select name="pardef_field_nullfk[]" onchange="selectRow(this,\''.$indx.'\');">');
    foreach ($status_list as $key => $value) {
      echo ('<option value="'.$key.'"');
      if ($field_data["field_nullfk"] == $key) echo " selected";
      echo ('>'.$value.'</option>');
    }
    echo ('</select></td>');
    echo ('<td><input type="text" id="parnew_field_default" name="pardef_field_default[]" value="" onchange="selectRow(this,\''.$indx.'\');"></td>');
    echo ('<td><textarea cols="40" rows="4" name="pardef_field_query[]" onchange="selectRow(this,\''.$indx.'\');"></textarea></td>');
    echo ('<td><input type="checkbox" name="pardef_show_conf[]" value="'.$indx.'" checked onclick="selectRow(this,\''.$indx.'\');"></td>');
    echo ('<td><input type="checkbox" name="pardef_show_def[]" value="'.$indx.'" checked onclick="selectRow(this,\''.$indx.'\');"></td>');
    echo ('<td><input type="text" name="pardef_format[]" size="15" value="" onchange="selectRow(this,\''.$indx.'\');"></td>');
    echo ('<td><input type="text" name="pardef_fregexp[]" size="15" value="" onchange="selectRow(this,\''.$indx.'\');"></td>');
    echo ('<td><input type="text" name="pardef_token[]" size="15" value="" onchange="selectRow(this,\''.$indx.'\');"></td>');
    echo ('<td><textarea cols="30" rows="4" name="pardef_description[]" onchange="selectRow(this,\''.$indx["ref"].'\');"></textarea></td>');
    echo ('<td><textarea cols="30" rows="4" name="pardef_help[]" onchange="selectRow(this,\''.$indx["ref"].'\');"></textarea></td>');
    echo ("</tr>\n");

    // End of the table
?>
</td></tr>
</TABLE>
<P>

<input type="submit" name="submit" value="Submit" onclick="btnWhichButton=this">
<input type="submit" name="submit" value="Delete" onclick="btnWhichButton=this">
<input type="reset" name="reset" value="Reset">
</form>
</TD></TR>
<TR><TD height="30" background="../img/bar2.gif">&nbsp;</TD><TD>&nbsp;</TD></TR>
</TABLE>

<?php
  }
?>
      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
    </div>
  </div>
</BODY>
</HTML>
