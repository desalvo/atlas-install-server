<?php

require('db.php');        // database connect script.
require('config.php');    // Main configuration
require('user_info.php'); // User informations
require ("$LJSFi_PATH/access_log.php");
?>

<HTML>
<HEAD>
<TITLE><?php echo $LJSFi_VO; ?> Installation System - Release Definition</TITLE>
<?php require("../css/page_header.php"); page_header(".."); ?>
<script type="text/javascript">
function openpop(url) {
    newWin = window.open(url,'Details','scrollbars=no,resizable=yes, width=300,
height=300,status=no,location=no,toolbar=no');
}
function closeWin() {
    self.close();
}
</script>
<script type="text/javascript" src="../js/simpletreemenu.js"></script>
<link rel="stylesheet" type="text/css" href="../css/simpletree.css" />
<link rel="stylesheet" href="../css/jquery-ui.css" />
<script src="../js/jquery-ui.js"></script>

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
    <div id="site_content">
      <div id="content" align="center">
        <!-- insert the page content here -->
        <h3><?php echo $LJSFi_VO; ?> Installation Release Definition</h3>

<?php
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
    echo ("<FONT SIZE=+1 COLOR='red'><B>Unknown user. Please <A HREF='user.php'>register to LJSFi.</A></B></FONT>\n");
  } elseif ($enabled == 0) {
    echo ("<FONT SIZE=+1 COLOR='red'><B>Your user is disabled. Please contact the LJSFi administrator.</B></FONT>\n");
  } elseif ($role != "admin" && $role != "master") {
    echo ("<FONT SIZE=+1 COLOR='red'><B>You don't have enough privileges to manage releases</B></FONT>");
  } else {
    if (isset($_POST["mode"]) && $_POST["mode"] == "define") echo "<h4>New release definition</h4>\n";
    if (isset($_POST["mode"]) && $_POST["mode"] == "update") echo "<h4>Release data update</h4>\n";
?>
<TABLE id='select_tbl' border="1" rules="groups">
<COLGROUP></COLGROUP>
<COLGROUP width="350"></COLGROUP>
<COLGROUP></COLGROUP>
<?php
    if (isset($_POST['relsrc'])) {
      if (   isset($_POST["mode"]) && ($_POST["mode"] == "define" || $_POST["mode"] == "update")
          && isset($_POST["submit"]) && $_POST["submit"] == "Save" ) {
        // Check for needed fields
        if (!isset($_POST['reldef_name']) || $_POST['reldef_name'] == "") {
          echo "<FONT COLOR='RED' SIZE=+1><B>No release name specified</B></FONT>";
        } else {
          // Save the release definition in the Database
          $field_list = array();
          $value_list = array();
          $field_ext_list = array();
          foreach ($_POST as $field => $value) {
            // Process the main table
            if (substr($field,0,7) == "reldef_") {
              array_push($field_list,db_identifier(substr($field,7)));
              if ($value == "") {
                array_push($value_list,'NULL');
              } else {
                array_push($value_list,db_quote($value,'rw'));
              }
            }
            // Process the extension table
            if (substr($field,0,7) == "relext_" && $value != "") $field_ext_list[db_int(substr($field,7),1)] = $value;
          }
          // Main table
          if ($_POST["mode"] == "define") {
            $query = "INSERT INTO release_data (".implode(',',$field_list).") VALUES (".implode(',',$value_list).")";
          } else {
            $query_fields = array();
            foreach ($value_list as $key => $value) array_push($query_fields,$field_list[$key]."=".$value."");
            $query = "UPDATE release_data SET ".implode(',',$query_fields)." WHERE name=".db_quote($_POST["reldef_name"],'rw');
          }
          db_query($query);
          // Read the release definition and get the id
          $query = "SELECT ref FROM release_data WHERE name=".db_quote($_POST["reldef_name"],'ro');
          $qry_res = db_query($query);
          $row = mysqli_fetch_row($qry_res);
          if (!isset($row[0])) {
            // No id found for the release, insert failed
            echo "Release definition failed";
          } else {
            // Extension table
            foreach ($field_ext_list as $key => $value) {
              $extquery = "SELECT ref FROM release_data_ext WHERE relfk=".$row[0]." AND fieldfk=".$key;
              $qry_res = db_query($extquery);
              $extrow = mysqli_fetch_row($qry_res);
              if (isset($extrow[0])) {
                $extquery = "UPDATE release_data_ext SET value=".db_quote($value,'rw')." WHERE ref=".db_int($extrow[0],1);
              } else {
                $extquery = 'INSERT INTO release_data_ext (relfk, fieldfk, value) VALUES ('.db_int($row[0],1).','.db_int($key,1).','.db_quote($value,'rw').')';
              }
              db_query($extquery);
            }
            // Insert done
            if ($_POST["mode"] == "define") {
              echo "Release definition completed successfully";
            } else {
              echo "Release ".$_POST["reldef_name"]." updated successfully";
            }
          }
        }
      } else {
        echo ('<form method="post" name="reldef" action="reldef.php">');
        // Get the field descriptions of the main table
        $qry_res = db_query("SELECT field_name, description, help, show_def, field_default FROM field_descriptions WHERE table_name='release_data'");
        $field_desc = array();
        while ($row = mysqli_fetch_row($qry_res)) {
          if (isset($row[2])) { $field_help = $row[2]; } else { $field_help = NULL; }
          $field_desc[$row[0]] = array("description" => $row[1], "help" => $field_help, "show_def" => $row[3], "default" => $row[4]);
        }
        // Get the field relations
        $qry_res = db_query("SELECT field1,table2,description FROM field_relations WHERE table1='release_data'");
        $foreign_keys = array();
        while ($row = mysqli_fetch_row($qry_res)) {
          $qry_res1 = db_query("SELECT ref, ".$row[2]." FROM ".$row[1]);
          while ($row1 = mysqli_fetch_row($qry_res1)) {
            if (!isset($foreign_keys[$row[0]])) { $foreign_keys[$row[0]] = array(); }
            array_push($foreign_keys[$row[0]],array($row1[0],$row1[1]));
          }
        }
        // Get the default data for the main table
        $query = "SELECT * FROM release_data";
        $where = array();
        if ($_POST['relsrc'] != "") array_push($where, "release_data.name=".db_quote($_POST['relsrc'],'ro'));
        if (count($where) > 0) $query .= " WHERE ".implode(' AND ',$where);
        $query .= " ORDER BY date DESC LIMIT 1";
        $qry_res = db_query($query);
        $row = mysqli_fetch_row($qry_res);
        $columns = mysqli_num_fields($qry_res);
        for ($i=0; $i<$columns; $i++) {
          $field_name = mysqli_fetch_field_direct($qry_res,$i)->name;
          if (isset($field_desc[$field_name]) && $field_desc[$field_name]["show_def"] == 1) {
            echo ('<tr><td class="selection">');
            if (isset($field_desc[$field_name]["help"])) {
              echo ('<img src="../img/help.gif" border="0"');
              echo ('onmouseover="Tip(\''.addslashes($field_desc[$field_name]["help"]).'\')" onmouseout="UnTip()"');
              echo ('>');
            }
            echo ('</td><td class="selection">');
            if (isset($field_desc[$field_name])) { echo ($field_desc[$field_name]["description"]); } else { echo ($field_name); }
            echo ("</td><td>");
            if ($_POST['relsrc'] != "") $defval = $row[$i]; elseif (isset($field_desc[$field_name]["default"])) $defval = $field_desc[$field_name]["default"]; else $defval = "";
            if (substr($field_name,-4) == "date" && ($_POST['mode'] == "define" or !isset($row[$i]))) $defval = date("Y-m-d H:i:s");
            if (isset($foreign_keys[$field_name])) {
              echo ('<select name="reldef_'.$field_name.'">');
              $optcount = 0;
              foreach ($foreign_keys[$field_name] as $fk) {
                echo ('<option value="'.$fk[0].'"');
                if ($fk[0] == $defval || ($defval == "" && $optcount == 0)) { echo " selected "; }
                echo ('>'.$fk[1].'</option>');
                $optcount++;
              }
              echo ('</select>');
            } else {
              if (substr($field_name,-6) == "userfk") {
                echo ('<input type="hidden" name="reldef_'.$field_name.'" value="'.$userref.'">'.$sslusername);
              } else {
                echo ('<input type="text" name="reldef_'.$field_name.'" size="60" value="'.$defval.'">');
              }
            }
            echo ("</td></tr>\n");
          }
        }
        // Get the default data for the extension table
        $query = "SELECT field_descriptions.ref AS fid, field_name, value, show_def, description, help FROM field_descriptions LEFT OUTER JOIN release_data_ext ON release_data_ext.fieldfk = field_descriptions.ref";
        $where = array("table_name = 'release_data_ext'");
        if ($_POST['relsrc'] != "") $query .= " AND release_data_ext.relfk = (SELECT ref FROM release_data WHERE name =".db_quote($_POST['relsrc'],'ro').")";
        if (count($where) > 0) $query .= " WHERE ".implode(' AND ',$where);
        #$query .= " GROUP BY field_name ORDER BY field_descriptions.field_name DESC";
        $query .= " ORDER BY field_descriptions.field_name DESC";
        $qry_res = db_query($query);
        while ($row = mysqli_fetch_assoc($qry_res)) {
          if ($row['show_def'] == 1) {
            if (isset($row['description']) && $row['description'] != "") $field_name = $row['description']; else $field_name = $row['field_name'];
            echo ('<tr><td class="selection">');
            if (isset($row['help']) && $row['help'] != "") {
              echo ('<img src="../img/help.gif" border="0"');
              echo ('onmouseover="Tip(\''.addslashes($row['help']).'\')" onmouseout="UnTip()">');
            }
            echo ('</td><td class="selection">'.$field_name);
            echo ("</td><td>");
            echo ('<input type="text" name="relext_'.$row['fid'].'" size="60" value="'.$row['value'].'">');
            echo ("</td></tr>\n");
          }
        }
        $action = "Save";
        echo ('<input type="hidden" name="mode" value="'.$_POST["mode"].'">');
        echo ('<input type="hidden" name="relsrc" value="'.$_POST['relsrc'].'">');
      }
    } else {
      // Source selection
      echo ('<form method="post" name="srcsel" action="reldef.php">');
      if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "update") {
        echo ('<tr><td class="selection" colspan="3">Please select the release to update</td></tr><tr><td colspan="3">');
      } else {
        
        echo ('<tr><td class="selection" colspan="3" align="center">Please select the definition source.<BR>Use "new" or leave blank for new definitions<BR>or type a release name to clone</td></tr><tr><td colspan="3">');
      }
?>
<center>
<div class="ui-widget">
<input id="relsrc" name="relsrc" />
<script>
$(function() {
var availableReleases = [
<?php
  if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "define") echo ('"new",');
  $qry_res = db_query("SELECT ref,name FROM release_data ORDER BY date DESC","ro");
  while ($row = mysqli_fetch_row($qry_res)) {
    echo '"'.$row[1].'",';
  }
?>
];
$( "#relsrc" ).autocomplete({
   source: availableReleases
});
});
</script>
</div>
<?php
      #echo ('<select name="relsrc">');
      #$rowsel=0;
      #if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "define") echo ('<option value="">- new -</option>');
      #$qry_res = db_query("SELECT ref,name FROM release_data ORDER BY date DESC");
      #while ($row = mysqli_fetch_row($qry_res)) {
      #  if ($row[0] == $rowsel) {
      #    echo ('<option value="'.$row[1].'" selected>'.$row[1].'</option>');
      #  } else {
      #    if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "update") {
      #      echo ('<option value="'.$row[1].'">'.$row[1].'</option>');
      #    } else {
      #      echo ('<option value="'.$row[1].'">Clone release '.$row[1].'</option>');
      #    }
      #  }
      #}
      #echo ('</select>');
      echo ('</center>');
      if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "update") {
        echo ('<input type="hidden" name="mode" value="update">');
      } else {
        echo ('<input type="hidden" name="mode" value="define">');
      }
      $action = "Select";
    }
  }
?>
</td></tr>
</TABLE>
<P>

<?php if (isset($action)) { ?>
<input type="submit" name="submit" value="<?php echo $action; ?>">
<input type="reset" name="reset" value="Reset">
</form>
<?php } ?>
      </div>
    </div>
    <div id="content_footer"></div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
    </div>
  </div>
</BODY>
</HTML>
