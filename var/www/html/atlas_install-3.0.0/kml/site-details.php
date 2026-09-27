<?php
  function atlas_get_resources($bdii) {
    global $LJSFi_default_infosys,$LJSFi_VO;
    if (!isset($bdii)) { $bdii = $LJSFi_default_infosys; }
    $cs = array();
    $ds=ldap_connect($bdii,2170);
    $r=ldap_bind($ds);
    $filter='(&(objectclass=gluece)(glueceaccesscontrolbaserule=*'.strtolower($LJSFi_VO).'*))';
    $items=array('glueceuniqueid');
    $sr=ldap_search($ds, "o=grid", $filter, $items);
    $info = ldap_get_entries($ds, $sr);
    for ($i=0; $i<$info["count"]; $i++) {
      for ($k=0; $k<count($info[$i]["glueceuniqueid"]); $k++) {
        if (isset($info[$i]["glueceuniqueid"][$k])) $glueceuniqueid = $info[$i]["glueceuniqueid"][$k];
        if ($glueceuniqueid != "") array_push($cs, $glueceuniqueid);
      }
    }
    ldap_close($ds);
    $res = array_unique($cs);
    sort($res);
    return $res;
  }

  function get_site_info($cs, $bdii="exp-bdii.cern.ch") {
    $ds=ldap_connect($bdii,2170);
    $r=ldap_bind($ds);
    // Get the subcluster name
    $filter='(&(objectclass=GlueCluster)(GlueForeignKey=GlueCEUniqueID='.$cs.'))';
    $items=array('GlueClusterUniqueID');
    $sr=ldap_search($ds, "o=grid", $filter, $items);
    $info = ldap_get_entries($ds, $sr);
    $scname = $info[0]["glueclusteruniqueid"][0];
    // Geth the subcluster info
    //$filter='(&(objectclass=GlueSubCluster)(GlueSubClusterUniqueID=' . $scname . '))';
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
    return $res;
  }


  function get_site_details($sitename, $bdii="exp-bdii.cern.ch") {
    $ds=ldap_connect($bdii,2170);
    $r=ldap_bind($ds);
    $filter='(&(objectclass=GlueSite)(GlueSiteUniqueID='.$sitename.'))';
    $items=array('GlueSiteLongitude','GlueSiteLatitude','GlueSiteLocation','GlueSiteOtherInfo','GlueSiteWeb','GlueSiteUserSupportContact');
    $sr=ldap_search($ds, "o=grid", $filter, $items);
    $info = ldap_get_entries($ds, $sr);
    $latitude = 0;
    $longitude = 0;
    $location = "";
    $tiertype = 3;
    $website = "";
    $contacts = "";
    for ($i=0; $i<$info["count"]; $i++) {
      $latitude  = $info[$i]["gluesitelatitude"][0];
      $longitude = $info[$i]["gluesitelongitude"][0];
      $location  = $info[$i]["gluesitelocation"][0];
      $website   = $info[$i]["gluesiteweb"][0];
      $contacts  = $info[$i]["gluesiteusersupportcontact"][0];
      if (isset($info[$i]["gluesiteotherinfo"])) {
        foreach ($info[$i]["gluesiteotherinfo"] as $key => $value) {
          if (substr($value,0,9) == "WLCG_TIER") {
            list($infotype,$tiervalue) = split('=',$value);
            $tiertype = 'Tier-'.$tiervalue;
          }
        }
      }
    }
    ldap_close($ds);
    $res = array($sitename,$latitude,$longitude,$location,$tiertype,$website,$contacts);
    return $res;
  }
?>
