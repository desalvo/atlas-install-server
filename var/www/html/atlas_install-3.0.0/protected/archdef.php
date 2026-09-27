<?php

require('db.php');        // database connect script.
require('config.php');    // Main configuration
require('user_info.php'); // User informations
?>

<HTML>
<HEAD>
<TITLE><?php echo $LJSFi_VO; ?> Installation System - Architecture Definition</TITLE>
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
        <h3><?php echo $LJSFi_VO; ?> Installation Architecture Definition</h3>

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
    echo ("<FONT SIZE=+1 COLOR='red'><B>You don't have enough privileges to manage the architectures</B></FONT>");
  } else {
    if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "define") echo "<h4>New architecture definition</h4>\n";
    if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "update") echo "<h4>Architecture update</h4>\n";
    if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "delete") echo "<h4>Architecture removal</h4>\n";
?>
<TABLE id='select_tbl' border="1" rules="groups">
<COLGROUP></COLGROUP>
<COLGROUP width="400"></COLGROUP>
<COLGROUP></COLGROUP>
<?php
    if (isset($_REQUEST['archsrc']) && $_REQUEST['archsrc'] >= 0) {
      if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "delete" && isset($_POST["submit"]) && $_POST["submit"] == "Delete") {
        // Let's remove the architecture
        $query = "SELECT COUNT(*) FROM release_data WHERE archfk=".db_int($_REQUEST['archsrc'],1)." OR sw_archfk=".db_int($_REQUEST['archsrc'],1);
        $qry_res = db_query($query);
        $row = mysqli_fetch_row($qry_res);
        if ($row[0] > 0) {
          echo ('<FONT COLOR="RED" SIZE=+1><B>');
          echo ('You cannot remove the selected architecture because there are releases ('.$row[0].') associated to it');
          echo ('</B></FONT>');
        } else {
          $delquery = "DELETE FROM release_arch WHERE ref=".db_int($_REQUEST['archsrc'],1);
          db_query($delquery);
          echo ('<FONT COLOR="GREEN" SIZE=+1><B>');
          echo ('Architecture removed successfully');
          echo ('</B></FONT>');
        }
      } elseif (isset($_REQUEST["mode"]) && ($_REQUEST["mode"] == "define" || $_REQUEST["mode"] == "update")
             && isset($_POST["submit"]) && $_POST["submit"] == "Save" ) {
        // Let's define or update a new architecture, check for needed fields
        if (!isset($_POST['archdef_description']) || $_POST['archdef_description'] == "") {
          echo "<FONT COLOR='RED' SIZE=+1><B>No architecture description specified</B></FONT>";
        } else {
          // Save the architecture definition in the Database
          $field_list = array();
          $value_list = array();
          $field_ext_list = array();
          // Get the field descriptions
          $query = "SELECT field_name, ft.name AS field_type, field_nullfk, field_default"
                   ." FROM field_descriptions fd, field_types ft"
                  ." WHERE table_name='release_arch' AND fd.field_typefk=ft.ref";
          $qry_res = db_query($query);
          $field_def = array();
          while ($row = mysqli_fetch_assoc($qry_res)) {
            if (isset($row["field_default"])) {
              $field_def[$row["field_name"]] = db_quote($row[3],'rw');
            } else {
              if ($row["field_nullfk"] == 0) $field_def[$row["field_name"]] = "''"; else $field_def[$row["field_name"]] = "NULL";
            }
          }
          foreach ($_POST as $field => $value) {
            if (substr($field,0,8) == "archdef_") {
              array_push($field_list,db_identifier(substr($field,8)));
              if ($value == "") {
                array_push($value_list,$field_def[substr($field,8)]);
              } else {
                array_push($value_list,db_quote($value,'rw'));
              }
            }
          }
          // Insert the architecture definition
          if ($_REQUEST["mode"] == "define") {
            $query = "INSERT INTO release_arch (".implode(',',$field_list).") VALUES (".implode(',',$value_list).")";
          } else {
            $query_fields = array();
            foreach ($value_list as $key => $value) array_push($query_fields,$field_list[$key]."=".$value."");
            $query = "UPDATE release_arch SET ".implode(',',$query_fields)." WHERE ref=".db_int($_REQUEST["archsrc"],1);
          }
          db_query($query);
          // Read the architecture definition and get the id
          $query = "SELECT ref FROM release_arch WHERE description=".db_quote($_POST["archdef_description"],'ro');
          $qry_res = db_query($query);
          $row = mysqli_fetch_row($qry_res);
          if (!isset($row[0])) {
            // No id found for the architecture, insert failed
            echo "Architecture definition failed";
          }
        }
      } else {
        echo ('<form method="post" name="archdef" action="archdef.php">');
        // Get the field descriptions of the main table
        $qry_res = db_query("SELECT field_name, description, help, show_def FROM field_descriptions WHERE table_name='release_arch'");
        $field_desc = array();
        while ($row = mysqli_fetch_row($qry_res)) {
          if (isset($row[2])) { $field_help = $row[2]; } else { $field_help = NULL; }
          $field_desc[$row[0]] = array("description" => $row[1], "help" => $field_help, "show_def" => $row[3]);
        }
        // Get the field relations
        $qry_res = db_query("SELECT field1,table2,description FROM field_relations WHERE table1='release_arch'");
        $foreign_keys = array();
        while ($row = mysqli_fetch_row($qry_res)) {
          $qry_res1 = db_query("SELECT ref, ".$row[2]." FROM ".$row[1]);
          while ($row1 = mysqli_fetch_row($qry_res1)) {
            if (!isset($foreign_keys[$row[0]])) { $foreign_keys[$row[0]] = array(); }
            array_push($foreign_keys[$row[0]],array($row1[0],$row1[1]));
          }
        }
        // Row index
        $indx = 1;
        // Get the default data for the main table
        $query = "SELECT * FROM release_arch";
        $where = array();
        if ($_REQUEST['archsrc'] != "") array_push($where, "release_arch.ref=".db_int($_REQUEST['archsrc'],1));
        if (count($where) > 0) $query .= " WHERE ".implode(' AND ',$where);
        $query .= " ORDER BY description DESC LIMIT 1";
        $qry_res = db_query($query);
        $row = mysqli_fetch_row($qry_res);
        $columns = mysqli_num_fields($qry_res);
        for ($i=0; $i<$columns; $i++) {
          $field_name = mysqli_fetch_field_direct($qry_res,$i)->name;
          if (isset($field_desc[$field_name]) && $field_desc[$field_name]["show_def"] == 1) {
            echo ('<tr class="selection'.$indx.'"><td>');
            if (isset($field_desc[$field_name]["help"])) {
              echo ('<img src="../img/help.gif" border="0"');
              echo ('onmouseover="Tip(\''.addslashes($field_desc[$field_name]["help"]).'\')" onmouseout="UnTip()"');
              echo ('>');
            }
            echo ('</td><td class="selection'.$indx.'">');
            if (isset($field_desc[$field_name])) { echo ($field_desc[$field_name]["description"]); } else { echo ($field_name); }
            echo ("</td><td>");
            if ($_REQUEST['archsrc'] != "") $defval = addslashes(preg_replace('/"/','',$row[$i])); else $defval = "";
            if ($field_name == "date" && $_REQUEST['mode'] == "define") $defval = date("Y-m-d H:i:s");
            if (isset($foreign_keys[$field_name])) {
              echo ('<select name="archdef_'.$field_name.'">');
              $optcount = 0;
              foreach ($foreign_keys[$field_name] as $fk) {
                echo ('<option value="'.$fk[0].'"');
                if ($fk[0] == $defval || ($defval == "" && $optcount == 0)) { echo " selected "; }
                echo ('>'.$fk[1].'</option>');
                $optcount++;
              }
              echo ('</select>');
            } else {
              echo ('<input type="text" name="archdef_'.$field_name.'" size="50" value="'.$defval.'">');
            }
            echo ("</td></tr>\n");
            $indx++;
            if ($indx > 2) $indx = 1;
          }
        }
        if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "delete") $action = "Delete"; else $action = "Save";
        echo ('<input type="hidden" name="mode" value="'.$_REQUEST["mode"].'">');
        echo ('<input type="hidden" name="archsrc" value="'.db_int($_REQUEST['archsrc'],1).'">');
      }
    } else {
      // Source selection
      echo ('<form method="post" name="srcsel" action="archdef.php">');
      if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "update") {
        echo ('<tr><td class="selection" colspan="3">Please select the architecture to update</td></tr><tr><td colspan="3">');
      } elseif (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "delete") {
        echo ('<tr><td class="selection" colspan="3">Please select the architecture to remove</td></tr><tr><td colspan="3">');
      } else {
        echo ('<tr><td class="selection" colspan="3">Please select the definition source</td></tr><tr><td colspan="3">');
      }
      echo ('<center>');
      echo ('<select name="archsrc">');
      if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] == "define") echo ('<option value="" selected>- new -</option>');
      $qry_res = db_query("SELECT ref,description FROM release_arch ORDER BY description");
      while ($row = mysqli_fetch_row($qry_res)) {
        if (isset($_REQUEST["mode"]) && ($_REQUEST["mode"] == "update" || $_REQUEST["mode"] == "delete")) {
          echo ('<option value="'.$row[0].'">'.$row[1].'</option>');
        } else {
          echo ('<option value="'.$row[0].'">Clone architecture '.$row[1].'</option>');
        }
      }
      echo ('</select>');
      echo ('</center>');
      if (!isset($_REQUEST["mode"])) {
        echo ('<input type="hidden" name="mode" value="define">');
      } else {
        echo ('<input type="hidden" name="mode" value="'.$_REQUEST["mode"].'">');
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
