<?php
 ?>
<?php require("config.php"); ?>


<div class="sidebarText" align="left">
Actions
</div>
<div class="sidebarLink" align="left">
<a href="javascript:ddtreemenu.flatten('treemenu1', 'expand')"><img src="../img/Plus.gif" border="0" width="10"></a> | <a href="javascript:ddtreemenu.flatten('treemenu1', 'contact')"><img src="../img/Minus.gif" border="0" width="10"></a>
</div>
<ul id="treemenu1" class="treeview">
<li><A HREF=".." title="Main page">Home page</A></li>
<li><A HREF="user.php" title="User registration">User registration</A></li>
<li><A HREF="rai.php" title="Request an installation">Request an installation</A></li>
<li><A HREF="pin.php" title="Pin an installed release">Pin a release</A></li>
<li><A HREF="subscribe.php" title="Subscribe to email notifications">Mail subscriptions</A></li>
<li><A HREF="req.php" title="Show the installation requests">Show requests</A></li>
<li><A HREF="tags.php" title="Show the tags matrix">Tags matrix</A><li>
<li><A HREF="../mmap.php" title="Site map">Site map</A><li>
<li><A HREF="usage_plots.php" title="Usage plots">Usage plots</A><li>
<li><A HREF="../firefox" title="Download the <?php echo $LJSFi_VO; ?> Firefox plugin for software installation">Firefox plugin (experimental)</A></li>
<li>Architectures
	<ul>
	<li><A HREF="archdef.php?mode=define" title="Define a new architecture">Definition</A></li>
	<li><A HREF="archdef.php?mode=update" title="Update an architecture definition">Update</A></li>
	<li><A HREF="archdef.php?mode=delete" title="Remove an architecture definition">Removal</A></li>
	</ul>
</li>
<li>InfoSys
	<ul>
	<li><A HREF="isdef.php?mode=define" title="Define a new InfoSys">Definition</A></li>
	<li><A HREF="isdef.php?mode=update" title="Update an InfoSys definition">Update</A></li>
	<li><A HREF="isdef.php?mode=delete" title="Remove an InfoSys">Removal</A></li>
	<li><A HREF="ispardef.php" title="InfoSys parameters management">Parameters</A></li>
	</ul>
</li>
<li>Releases
	<ul>
	<li><A HREF="reldef.php?mode=define" title="Define a new release">Definition</A></li>
	<li><A HREF="reldef.php?mode=update" title="Update a release definition">Update</A></li>
	<li><A HREF="rel.php" title="Show the release matrix">Matrix</A></li>
	<li><A HREF="critical.php" title="Manage the release criticality">Critical Releases</A></li>
	<li><A HREF="pardef.php" title="Release parameters management">Parameters</A></li>
        <li><A HREF="relsub.php" title="Release subscriptions">Subscriptions</A></li>
	</ul>
</li>
<li>Sites
	<ul>
	<li><A HREF="sitedef.php?mode=define" title="Define a new site">Definition</A></li>
	<li><A HREF="sitedef.php?mode=update" title="Update a site definition">Update</A></li>
	<li><A HREF="sitedef.php?mode=delete" title="Remove a site">Removal</A></li>
	<li><A HREF="sitepardef.php" title="Site parameters management">Parameters</A></li>
	</ul>
</li>
<li>Targets
	<ul>
	<li><A HREF="tgtdef.php?mode=define" title="Define a new target">Definition</A></li>
	<li><A HREF="tgtdef.php?mode=update" title="Update a target definition">Update</A></li>
	<li><A HREF="tgtdef.php?mode=delete" title="Remove a target definition">Removal</A></li>
	</ul>
</li>
<li>Tasks
	<ul>
	<li><A HREF="taskdef.php?mode=define" title="Define a new task">Definition</A></li>
	<li><A HREF="taskdef.php?mode=update" title="Update a task definition">Update</A></li>
	<li><A HREF="taskdef.php?mode=delete" title="Remove a task definition">Removal</A></li>
	</ul>
</li>
</ul>
<script type="text/javascript">
//ddtreemenu.createTree(treeid, enablepersist, opt_persist_in_days (default is 1))
ddtreemenu.createTree("treemenu1", true, 5)
</script>
<HR>
<div class="sidebarLink" align="center">
Powered by<BR>
<img align="absmiddle" src="../img/LJSFi_logo_4.gif" border="0" hspace="8" width="120">
</div>
<?php ?>
