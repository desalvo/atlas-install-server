<?php
require('db.php');     // database connect script.
require('config.php'); // Main configuration
?>

<html>
<head>
<title><?php echo $LJSFi_VO; ?> Installation System - Map View</title>

<?php require("./css/page_header.php"); page_header("."); ?>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
<script type="text/javascript">
  function initialize() {
    var myLatlng = new google.maps.LatLng(41.9007660, 12.5120858);
    var myOptions = {
      zoom: 15,
      center: myLatlng,
      mapTypeId: google.maps.MapTypeId.HYBRID
    }
    var map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
    var sitesLayer = new google.maps.KmlLayer('http://atlas-install.roma1.infn.it/atlas_install/kml/install.kml', { map: map});
    google.maps.event.addListener(sitesLayer, 'click', function(kmlEvent) {
        var text = kmlEvent.featureData.description;
        showInContentWindow(text);
    });

    function showInContentWindow(text) {
      var sidediv = document.getElementById('content_window');
      sidediv.innerHTML = text;
    }
  }
</script>
</head>
<body onload="initialize()">
  <div id="main">
    <div id="header">
<?php require("./css/main_header.php"); main_header($LJSFi_VO, "."); ?>
<?php require("./css/menubar.php"); menubar("."); ?>
    </div>
    <div id="site_content">
      <div id="content">
        <!-- insert the page content here -->
        <h3><?php echo $LJSFi_VO; ?> software deployment map view</h3>
        <div id="map_canvas" style='height: 400px; width: 800px'></div>
        <div id="content_window" style="width:100px; float:left"></div>
      </div>
    </div>
    <div id="content_footer">
        <A HREF="http://maps.google.com/maps?f=q&source=embed&hl=en&q=https%3A//atlas-install.roma1.infn.it/atlas_install/kml/install.kml">External Google Maps link</A>
    </div>
    <div id="footer">
      <p><a href="mailto:Alessandro.DeSalvo@roma1.infn.it">Contact the installation team</a></p>
    </div>
  </div>
</body>
</html>
