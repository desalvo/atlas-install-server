<?php
require('db.php');     // database connect script.
require('config.php'); // Main configuration
?>

<HTML>
<HEAD>
<TITLE><?php echo $LJSFi_VO; ?> Installation System - Map View</TITLE>

<?php require("./css/page_header.php"); page_header("."); ?>
<script src="http://www.google.com/jsapi?key=ABQIAAAAfNt82Yu7ScaeTPEtdJ1RsBQPs-wDJ6up10g-zoo7i5pCIMnXNRTmxcanpUSGIRXFDJlEVNOS2gAvlQ"></script>
<script>
  google.load("earth", "1");
  var ge = null;

  function init() {
    google.earth.createInstance("map3d", initCallback, failureCallback);
  }

  function initCallback(object) {
    ge = object;
    ge.getWindow().setVisibility(true);
    // add a navigation control
    ge.getNavigationControl().setVisibility(ge.VISIBILITY_AUTO);
    
    // add some layers
    ge.getLayerRoot().enableLayerById(ge.LAYER_BORDERS, true);
    ge.getLayerRoot().enableLayerById(ge.LAYER_ROADS, true);

    // fetch a KML file and show it
    
    function finished(object) {
      if (!object) {
        // wrap alerts in API callbacks and event handlers
        // in a setTimeout to prevent deadlock in some browsers
        setTimeout(function() {
          alert('Bad or null KML.');
        }, 0);
        return;
      }
      ge.getFeatures().appendChild(object);
      var la = ge.createLookAt('');
      la.set(41.9007660, 12.5120858, 25, ge.ALTITUDE_RELATIVE_TO_GROUND, 180, 60, 500);
      //la.set(37.77945823391724, -122.4184144773405, 25, ge.ALTITUDE_RELATIVE_TO_GROUND, 180, 60, 500);
      ge.getView().setAbstractView(la);
    }
    
    // fetch the KML
    var url = 'http://atlas-install.roma1.infn.it/atlas_install/kml/allsites.kml'; 
    //var url = 'http://sketchup.google.com/' + 
    //          '3dwarehouse/download?mid=28b27372e2016cca82bddec656c63017&rtyp=k2';
    google.earth.fetchKml(ge, url, finished);
    
    document.getElementById('installed-plugin-version').innerHTML =
      ge.getPluginVersion().toString();
  }

  function failureCallback(object) {
  }
</script>
</HEAD>
<BODY onload='init()' id='body'>

<P>

<TABLE id='ai_tbl' border="1" cellspacing="0" cellpadding="10" rules="groups" width="100%" summary="<?php echo $LJSFi_VO; ?> Installation Web Pages - Map View">
<COLGROUP width="220"></COLGROUP>
<COLGROUP></COLGROUP>
<TR><TD colspan="2" background="img/bar.gif" height="10" class="captionimg">
<CENTER>
<?php echo $LJSFi_VO; ?> Installation Pages - Map View
</CENTER>
</TD></TR>
<TR><TD height="50" background="img/bar3.gif">&nbsp;</TD><TD>&nbsp;</TD></TR>
<TR><TD background="img/bar3.gif" height="100%" valign="top">
<?php include ("sidebar.php"); ?>
</TD><TD valign="top">
<CENTER>
<IMG SRC="img/sw_inst_icon.png" HEIGHT="150"><P>
<div id='map3d' style='border: 1px solid silver; height: 600px; width: 800px;'></div>
<HR>
<P>
</CENTER>
</TD></TR>
<TR><TD height="30" background="img/bar2.gif">&nbsp;</TD><TD>&nbsp;</TD></TR>
</TABLE>

<P>
<A HREF="mailto:Alessandro.DeSalvo@roma1.infn.it">For comments or informations please send me a mail (Alessandro.DeSalvo@roma1.infn.it)</A>
<BR>
</BODY>
</HTML>
