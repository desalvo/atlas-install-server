<?php
require('db.php');     // database connect script.
require('config.php'); // Main configuration
?>
<HTML>
<HEAD>
<TITLE><?php echo $LJSFi_VO; ?> Installation System - Firefox plugin</TITLE>

<link rel="shortcut icon" href="../img/favicon.ico">
<link rel="stylesheet" type="text/css" href="../css/ljsf.css"/>
<script type="text/javascript" src="../js/simpletreemenu.js"></script>
<link rel="stylesheet" type="text/css" href="../css/simpletree.css" />

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
<TABLE id='ai_tbl' border="1" cellspacing="0" cellpadding="10" rules="groups" width="100%" summary="Atlas Installation Web Pages">
<COLGROUP width="220"></COLGROUP>
<COLGROUP></COLGROUP>
<TR><TD colspan="2" background="../img/bar.gif" height="10" class="captionimg">
<CENTER>
<?php echo $LJSFi_VO; ?> Installation Pages
</CENTER>
</TD></TR>
<TR><TD height="50" background="../img/bar3.gif">&nbsp;</TD><TD>&nbsp;</TD></TR>
<TR><TD background="../img/bar3.gif" height="100%" valign="top">
<?php include ("sidebar.php"); ?>
</TD><TD valign="top">
<CENTER>
<img src="../img/sw_inst_icon.png">
<P>
<div class="parTitle">
1. The ATLAS software installation Firefox plugin v1.0
</div><P>
<div class="parText">
<A HREF="atlassw-1.0.xpi">ATLASsw</A>
is a Firefox plugin which enables your web browser to start the installations of the ATLAS
software in your local disk directly from the ATLAS LCG Installation System.
</div>
<P>
<div class="parTitle">
2. How it works
</div><P>
<div class="parText">
Once installed, the plugin registers into Firefox a new protocol handler, atlass://. The plugin
is then able to understand and process atlassw:// instructions to install a release in your
local system. The URIs of the available releases can be selected from the
<A HREF="../protected/rel.php">release matrix</A> web page, by clicking on the appropriate
release number. You'll need to have your personal certificate loaded in the browser to access
the release matrix.<BR>
The heart of the ATLASsw plugin is the <A HREF="sw-mgr">sw-mgr</A> script, which is the same agent used to
deploy the ATLAS software in the Grid sites.<BR>
Using the ATLASsw plugin you'll have all the needed ATLAS releases in your local machine
in a few clicks.
</div>
<P>
<div class="parTitle">
3. Installation
</div><P>
<div class="parText">
Simply click the <A HREF="atlassw-1.0.xpi">installation</A>
link and follow the instruction on the screen to install the plugin. You will need at least Firefox 2 to run this plugin.
</div>
<P>
<div class="parTitle">
4. Configuration
</div><P>
<div class="parText">
Before using the ATLASsw plugin you have to configure it. To access the setup, you have to choose
the item "Add-ons" of the "Tools" menu of your browser. A window like this will be shown:
<P>
<CENTER><img src="../img/addons.gif"></CENTER>
<BR>
Clicking on the "Preferences" button of the ATLAS software installer will bring you to the
setup page of the plugin.
<P>
<CENTER><img src="../img/swinstpref.gif"></CENTER>
<BR>
By default the software will be installed in your local disk in /atlas, using pacman 3.25,
caching the installation files in /atlas/snapshots to speed up the re-installations.<BR>
By default the agent will install, test and send the test results to the
<A HREF="https://kv.roma1.infn.it/KV">GKV portal</A>.<BR>
In case of problems with your OS not being correctly recognized by pacman, an option
is provided to instruct pacman to consider your system as a well-known platform.
Setting the "Pretend platform" option to something else than "auto" will force
pacman to use that value instead than discover the platform which is running on.<BR>
Currently the minimum disk size to install and the benchmark options are not used.
</div>
<P>
<div class="parTitle">
5. Contacts
</div><P>
<div class="parText">
Please send an email to <A HREF="mailto:Alessandro.DeSalvo@roma1.infn.it">Alessandro De Salvo</A>
for comments or requests.
</div>

</td></tr> 
</TABLE>
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
