<?php

  function get_resource_info($resname, $infosys="broker") {
    $res = db_query("SELECT pr.site, ros.osname, ros.osrelease, ros.osversion, rt.name, rf.name FROM panda_resource pr JOIN resource_os ros ON ros.ref=pr.osfk JOIN resource_flavor rf ON rf.ref=pr.flavorfk JOIN resource_type rt ON rt.ref=pr.typefk WHERE pr.name='".db_escape($resname,$infosys)."'",$infosys);
    $row = mysqli_fetch_array($res);
    if ($row) {
      return array($row[0],$row[1],$row[2],$row[3],$row[4],$row[5]);
    } else {
      return NULL;
    }
  }

  function get_cluster_info($cs, $bdii="exp-bdii.cern.ch") {
    $ds=ldap_connect($bdii,2170);
    $r=ldap_bind($ds);
    // Get the subcluster name
    $filter='(&(objectclass=GlueCluster)(GlueForeignKey=GlueCEUniqueID='.$cs.'))';
    $items=array('GlueClusterUniqueID');
    $sr=ldap_search($ds, "o=grid", $filter, $items);
    $info = ldap_get_entries($ds, $sr);
    if (isset($info[0]["glueclusteruniqueid"][0])) {
      $scname = $info[0]["glueclusteruniqueid"][0];
      // Get the subcluster info
      $filter='(&(objectclass=GlueSubCluster)(GlueChunkKey=GlueClusterUniqueID=' . $scname . '))';
      $items=array('GlueHostOperatingSystemName','GlueHostOperatingSystemRelease','GlueHostOperatingSystemVersion','GlueHostApplicationSoftwareRunTimeEnvironment');
      $sr=ldap_search($ds, "o=grid", $filter, $items);
      $info = ldap_get_entries($ds, $sr);
      for ($i=0; $i<$info["count"]; $i++) {
        $osname = $info[$i]["gluehostoperatingsystemname"][0];
        $osrel  = $info[$i]["gluehostoperatingsystemrelease"][0];
        $osver  = $info[$i]["gluehostoperatingsystemversion"][0];
        $gridname = "EGEE";
        foreach ($info[$i]["gluehostapplicationsoftwareruntimeenvironment"] as $mwname) {
          if (preg_match('/^GLITE/',$mwname)) {
            $gridname = "EGEE";
          } elseif (preg_match('/^OSG/',$mwname)) {
            $gridname = "OSG";
          }
        }
        list($scuid, $cuid, $mdsvoname, $other) = split(",", $info[$i]["dn"], 4);
        list($key, $sitename) = split("=", $mdsvoname);
      }
      ldap_close($ds);
      $res = array($sitename,$osname,$osrel,$osver,$gridname);
    } else {
      $res = NULL;
    }
    return $res;
  }
?>
