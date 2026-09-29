<?php
function menubar($path=".") {
?>
      <div id="menubar">
        <ul id="menu" class="dropdown dropdown-horizontal">
          <li><a href="#" class="dir">Main</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>" title="Home page">Home page</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/user.php" title="User registration">User registration</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/rai.php" title="Request an installation">Installation request</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/pin.php" title="Pin an installed release">Pin a release</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/subscribe.php" title="Subscribe to email notifications">Mail subscriptions</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/req.php" title="Show the installation requests">Show requests</A></li>
              <li><A HREF="<?php echo $path; ?>/list.php?summary=1" title="Show the installation summary">Show summary</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/tags.php" title="Show the tags matrix">Tags matrix</A><li>
              <li><A HREF="<?php echo $path; ?>/mmap.php" title="Site map">Site&nbsp;map</A><li>
              <li><A HREF="<?php echo $path; ?>/usage_plots.php" title="Usage plots">Usage&nbsp;plots</A><li>
              <li><A HREF="<?php echo $path; ?>/protected/configuration.php" title="Server configuration">Server configuration</A></li>
              <?php if (atlas_has_role(['master'])): ?><li><A HREF="<?php echo $path; ?>/protected/local_users.php" title="Local users">Local users</A></li><?php endif; ?>
              <?php if (atlas_current_identity() && (atlas_current_identity()['source'] ?? '') === 'local'): ?><li><A HREF="<?php echo $path; ?>/auth/change_password.php">Change password</A></li><li><A HREF="<?php echo $path; ?>/auth/logout.php">Logout</A></li><?php else: ?><li><A HREF="<?php echo $path; ?>/auth/login.php">Local login</A></li><?php endif; ?>
            </ul>
          </li>
          <li><a href="#" class="dir">Architectures</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/archdef.php?mode=define" title="Define a new architecture">Definition</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/archdef.php?mode=update" title="Update an architecture definition">Update</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/archdef.php?mode=delete" title="Remove an architecture definition">Removal</A></li>
            </ul>
          </li>
          <li><a href="#" class="dir">InfoSys</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/isdef.php?mode=define" title="Define a new InfoSys">Definition</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/isdef.php?mode=update" title="Update an InfoSys definition">Update</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/isdef.php?mode=delete" title="Remove an InfoSys">Removal</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/ispardef.php" title="InfoSys parameters management">Parameters</A></li>
            </ul>
          </li>
          <li><a href="#" class="dir">Releases</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/reldef.php?mode=define" title="Define a new release">Definition</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/reldef.php?mode=update" title="Update a release definition">Update</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/rel.php" title="Show the release matrix">Matrix</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/critical.php" title="Manage the release criticality">Critical Releases - All</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/critical.php?tier_level=1&show_missing" title="Manage the release criticality [T1]">Critical Releases - T1</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/critical.php?tier_level=2&show_missing" title="Manage the release criticality [T2]">Critical Releases - T2</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/pardef.php" title="Release parameters management">Parameters</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/relsub.php" title="Release subscriptions">Subscriptions</A></li>
            </ul>
          </li>
          <li><a href="#" class="dir">Sites</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/sitedef.php?mode=define" title="Define a new site">Definition</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/sitedef.php?mode=update" title="Update a site definition">Update</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/sitedef.php?mode=delete" title="Remove a site">Removal</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/sitepardef.php" title="Site parameters management">Parameters</A></li>
            </ul>
          </li>
          <li><a href="#" class="dir">Targets</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/tgtdef.php?mode=define" title="Define a new target">Definition</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/tgtdef.php?mode=update" title="Update a target definition">Update</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/tgtdef.php?mode=delete" title="Remove a target definition">Removal</A></li>
            </ul>
          </li>
          <li><a href="#" class="dir">Tasks</a>
            <ul>
              <li><A HREF="<?php echo $path; ?>/protected/taskdef.php?mode=define" title="Define a new task">Definition</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/taskdef.php?mode=update" title="Update a task definition">Update</A></li>
              <li><A HREF="<?php echo $path; ?>/protected/taskdef.php?mode=delete" title="Remove a task definition">Removal</A></li>
            </ul>
          </li>
          <li><a href="#" id="trigger" class="dir">Help</a>
          </li>
        </ul></div>
<?php } ?>
