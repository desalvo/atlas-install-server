<?php

  require("guid.php");
  require("db.php");

  function set_rel_data() {
    // Generic info
    if (isset($_POST["cs"]) && $_POST["cs"] != "") $cs = $_POST["cs"];
    if (isset($_POST["rel"]) && $_POST["rel"] != "") $relname = $_POST["rel"];
    if (isset($_POST["status"]) && $_POST["status"] != "") $status = $_POST["status"];
    if (isset($_POST["comments"]) && $_POST["comments"] != "") {
      $comments = addslashes($_POST["comments"]);
    }

    // Process the user
    if (isset($_POST["user"]) && $_POST["user"] != "") {
      $res = db_query("SELECT ref FROM user WHERE name=" . db_quote($_POST["user"],'rw'));
      $row = mysqli_fetch_row($res);
      if (!$row) {
        $res = db_query("INSERT INTO user SET name=" . db_quote($_POST["user"],'rw') . ", dn=" . db_quote(getenv("SSL_CLIENT_S_DN"),'rw') . ", email=" . db_quote($_POST["email"],'rw'));
        $res = db_query("SELECT ref FROM user WHERE name=" . db_quote($_POST["user"],'rw'));
        $row = mysqli_fetch_row($res);
      }
      $userref = $row[0];
      if (!isset($_POST["quiet"])) echo "INSTALL SERVER> User ".$_POST["user"]." has id ".$userref."\n";
    }

    // Process the site
    if (isset($cs)) {
      $res = db_query("SELECT ref FROM site WHERE cs=" . db_quote($cs,'rw'));
      $row = mysqli_fetch_row($res);
      if ($row) {
        $siteref = $row[0];
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> ".$cs." has id ".$siteref."\n";
      } else {
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Unknown resource ".$cs."\n";
      }
    }

    // Process the release
    if (isset($relname) && isset($siteref)) {
      $query = "SELECT ref FROM release_stat WHERE name=" . db_quote($relname,'rw') . " AND sitefk=" . db_int($siteref,1);
      $res = db_query($query);
      $row = mysqli_fetch_row($res);
      if ($row) {
        $relref = $row[0];
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Release ".$relname." has id ".$relref."\n";
      } else {
        if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Unknown release ".$relname." in site\n";
      }
    }

    // Handle the release data
    if (isset($relref)) {
      // Update the release data
      $query = "UPDATE release_stat SET status=" . db_quote($status,'rw');
      if (isset($comments) && $comments != "")
          $query .= ", comments=" . db_quote($_POST["comments"],'rw');
      $query .= " WHERE ref=" . db_int($relref,1);
      $res = db_query($query);
      if (!isset($_POST["quiet"])) echo "INSTALL SERVER> release name ".$relname." (".$relref.") UPDATE successful\n";
    } else {
      $relname=NULL;
      if (!isset($_POST["quiet"]) && !isset($status))  echo "INSTALL SERVER> No job status\n";
      if (!isset($_POST["quiet"])) echo "INSTALL SERVER> Cannot update release data\n";
    }
    return $relname;
  }

  // Main interface
  $relname = set_rel_data();
  if ($relname) {
    if (!isset($_POST["quiet"])) {
      echo "INSTALL SERVER> release ".$relname." updated successfully";
    } else {
      echo $relname;
    }
  } else {
    if (!isset($_POST["quiet"])) echo "INSTALL SERVER> release update failed";
  }
?>
