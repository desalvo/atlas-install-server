<?php

    $ds=ldap_connect("vm009.gla.scotgrid.ac.uk","2135");
    $r=ldap_bind($ds);
    $sr=ldap_search($ds, "Mds-Vo-name=local,o=Grid","");
    $info = ldap_get_entries($ds, $sr); 
    echo $info;
    print_r($info);
    #for ($i=0; $i<$info["count"]; $i++) {
    #  print_r($info[$i]);
      #for ($k=0; $k<count($info[$i]["nordugrid-cluster-contactstring"]); $k++) {
      #  if (isset($info[$i]["nordugrid-cluster-contactstring"][$k])) echo $info[$i]["nordugrid-cluster-contactstring"][$k]."\n";
      #  if (isset($info[$i]["mds-service-hn"][$k])) $glueceinfohostname = $info[$i]["mds-service-hn"][$k];
      #  if ($glueceinfohostname != "") array_push($ce, $glueceinfohostname);
      #}
    #}
?>
