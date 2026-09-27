--
-- Database
--

DROP DATABASE IF EXISTS `ljsf_infosys`;
CREATE DATABASE IF NOT EXISTS `ljsf_infosys`;
USE `ljsf_infosys`;

--
-- Table structure for table `cloud`
--

DROP TABLE IF EXISTS `cloud`;
CREATE TABLE `cloud` (
  `ref` int(11) NOT NULL auto_increment,
  `name` varchar(10) NOT NULL default '',
  `description` varchar(255) default NULL,
  PRIMARY KEY  (`ref`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `compute_resource`
--

DROP TABLE IF EXISTS `compute_resource`;
CREATE TABLE `compute_resource` (
  `ref` int(11) NOT NULL auto_increment,
  `ce_endpoint` varchar(128) NOT NULL default '',
  `panda_resourcefk` int(11) NOT NULL default '1',
  PRIMARY KEY  (`ref`,`ce_endpoint`, `panda_resourcefk`),
  KEY `ce_endpoint` (`ce_endpoint`),
  KEY `panda_resourcefk` (`panda_resourcefk`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

--
-- Table structure for table `info`
--

DROP TABLE IF EXISTS `info`;
CREATE TABLE `info` (
  `ref` int(11) NOT NULL auto_increment,
  `last_update` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`ref`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `panda_resource`
--

DROP TABLE IF EXISTS `panda_resource`;
CREATE TABLE `panda_resource` (
  `ref` int(11) NOT NULL auto_increment,
  `atlas_site` varchar(128) NOT NULL default '',
  `site` varchar(128) NOT NULL default '',
  `name` varchar(128) NOT NULL default '',
  `queue` varchar(128) NOT NULL default '',
  `cloudfk` int(11) NOT NULL default '1',
  `tier_level` int(11) default '0',
  `is_analysis` int(11) default '0',
  `is_production` int(11) default '0',
  `is_cvmfs` int(11) default '0',
  `is_default` int(11) default '0',
  `corecount` int(11) default '1',
  `statusfk` int(11) default '1',
  `typefk` int(11) default '1',
  `flavorfk` int(11) default '1',
  `osfk` int(11) default '1',
  `lfcpath` varchar(255) NOT NULL default '',
  `lfcprodpath` varchar(255) NOT NULL default '',
  `sepath` varchar(255) NOT NULL default '',
  `seprodpath` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`ref`,`site`,`name`,`queue`),
  KEY `atlas_site` (`atlas_site`),
  KEY `site` (`site`),
  KEY `name` (`name`),
  KEY `cloudfk` (`cloudfk`),
  KEY `statusfk` (`statusfk`),
  KEY `typefk` (`typefk`),
  KEY `flavorfk` (`flavorfk`),
  KEY `osfk` (`osfk`),
  KEY `is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

--
-- Table structure for table `release_arch`
--

DROP TABLE IF EXISTS `release_cmtconfig`;
CREATE TABLE `release_cmtconfig` (
  `ref` int(11) NOT NULL auto_increment,
  `id` varchar(50) NOT NULL default '',
  `description` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`ref`,`description`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

--
-- Table structure for table `release_data`
--

DROP TABLE IF EXISTS `release_data`;
CREATE TABLE `release_data` (
  `ref` int(11) NOT NULL auto_increment,
  `release_name` varchar(25) default '',
  `major_release` varchar(25) default '',
  `project` varchar(50) default '',
  `cmtconfigfk` int(11) NOT NULL default '0',
  `tag` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`ref`),
  UNIQUE KEY `release_name_project,cmtconfig` (`release_name`,`project`,`cmtconfigfk`),
  KEY `project` (`project`),
  KEY `cmtconfigfk` (`cmtconfigfk`),
  KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

--
-- Table structure for table `release_stat`
--

DROP TABLE IF EXISTS `release_stat`;
CREATE TABLE `release_stat` (
  `ref` int(11) NOT NULL auto_increment,
  `resourcefk` int(11) NOT NULL default '0',
  `releasefk` int(11) NOT NULL default '0',
  PRIMARY KEY  (`ref`),
  UNIQUE KEY `relres` (`resourcefk`, `releasefk`),
  KEY `resourcefk` (`resourcefk`),
  KEY `releasefk` (`releasefk`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=4;

--
-- Table structure for table `resource_flavor`
--

DROP TABLE IF EXISTS `resource_flavor`;
CREATE TABLE `resource_flavor` (
  `ref` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`ref`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `resource_os`
--

DROP TABLE IF EXISTS `resource_os`;
CREATE TABLE `resource_os` (
  `ref` int(11) NOT NULL auto_increment,
  `osname` varchar(128) NOT NULL default '',
  `osversion` varchar(20) NOT NULL default '',
  `osrelease` varchar(20) NOT NULL default '',
  `osclass` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`ref`,`osname`,`osversion`,`osrelease`),
  KEY `name` (`osname`),
  KEY `osversion` (`osversion`),
  KEY `osrelease` (`osrelease`),
  KEY `osclass` (`osclass`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `resource_type`
--

DROP TABLE IF EXISTS `resource_type`;
CREATE TABLE `resource_type` (
  `ref` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`ref`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `schema_version`
--

DROP TABLE IF EXISTS `schema_version`;
CREATE TABLE `schema_version` (
  `ref` int(11) NOT NULL auto_increment,
  `major` int(11) NOT NULL default '0',
  `minor` int(11) NOT NULL default '0',
  `patch` int(11) NOT NULL default '0',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `description` varchar(255) default NULL,
  PRIMARY KEY  (`ref`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `status`
--

DROP TABLE IF EXISTS `status`;
CREATE TABLE `status` (
  `ref` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`ref`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Default values
--
INSERT INTO `schema_version` (major,minor,patch,date,description) VALUES (0,1,0,'2013-03-18',"Initial schema");
INSERT INTO `resource_os` (ref,osname,osversion,osrelease,osclass) VALUES (1,'UNKNOWN','UNKNOWN','0','UNKNOWN');

GRANT USAGE ON *.* TO 'dbreader'@'%' IDENTIFIED BY PASSWORD '0326532b1c3779d9';
GRANT USAGE ON *.* TO 'dbwriter'@'%' IDENTIFIED BY PASSWORD '77e86e9d77f22cdb';
GRANT SELECT ON `ljsf_infosys`.* TO 'dbreader'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER ON `ljsf_infosys`.* TO 'dbwriter'@'%';
FLUSH PRIVILEGES;
