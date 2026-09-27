<?php
    require("db.php");
    require("config.php");
    include ("site-details.php");

    $sites = array();
    $qrysite = "SELECT DISTINCT(site.name) FROM site ORDER BY site.name";
    $result = db_query($qrysite);
    while ($row = mysqli_fetch_row($result)) {
        array_push($sites, $row[0]);
    }
    $fh = fopen($LJSFi_KML_CACHE, 'w') or die("Can't open file $LJSFi_KML_CACHE");
    fwrite($fh, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
    fwrite($fh, "<kml xmlns=\"http://www.opengis.net/kml/2.1\">\n");
    fwrite($fh, "  <Document>\n");
    fwrite($fh, "    <name>$LJSFi_VO sites</name>\n");
    fwrite($fh, "    <description>$LJSFi_VO sites.</description>\n");
    foreach ($sites as $site) {
        $siteinfo = get_site_details($site);
        if (isset($siteinfo) and $siteinfo[0] != "" and $siteinfo[2] != 0 and $siteinfo[1] != 0) {
            fwrite($fh, "    <Placemark>\n");
            fwrite($fh, "      <name><![CDATA[".$site." ]]></name>\n");
            fwrite($fh, "      <description><![CDATA[\n");
            fwrite($fh, "        <a href=\"list.php?sitename=".$site."\">".$site." Tier".$siteinfo[4]."</a>\n");
            fwrite($fh, "        <br />\n");
            fwrite($fh, "        <a href=\"".$siteinfo[5]."\">".$siteinfo[5]."</a>\n");
            if ($siteinfo[6] != "") {
                fwrite($fh, "        <br />\n");
                fwrite($fh, "        <a href=\"".$siteinfo[6]."\">site contacts</a>\n");
            }
            fwrite($fh, "      ]]></description>\n");
            fwrite($fh, "      <Style><IconStyle><Icon><href>https://atlas-install.roma1.infn.it/atlas_install/img/sw_inst_icon.png</href></Icon></IconStyle></Style>\n");
            fwrite($fh, "      <Point>\n");
            fwrite($fh, "        <extrude>1</extrude>\n");
            fwrite($fh, "        <coordinates>".$siteinfo[2].",".$siteinfo[1].",0</coordinates>\n");
            fwrite($fh, "      </Point>\n");
            fwrite($fh, "    </Placemark>\n");
        }
    }
    fwrite($fh, "  </Document>\n");
    fwrite($fh, "</kml>\n");
    fclose($fh);
?>
