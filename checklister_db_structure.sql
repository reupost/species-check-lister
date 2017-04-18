/*
SQLyog Community Edition- MySQL GUI v7.01 
MySQL - 5.6.16-log : Database - refleqt_spp2
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

CREATE DATABASE /*!32312 IF NOT EXISTS*/`refleqt_spp2` /*!40100 DEFAULT CHARACTER SET utf8 */;

/*Table structure for table `families` */

DROP TABLE IF EXISTS `families`;

CREATE TABLE `families` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `family` varchar(100) NOT NULL,
  `source` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`,`family`),
  KEY `ix_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `genera` */

DROP TABLE IF EXISTS `genera`;

CREATE TABLE `genera` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `genus` varchar(100) NOT NULL,
  `family` varchar(100) DEFAULT NULL,
  `genus_length` int(11) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_genus_length` (`genus_length`),
  KEY `ix_source` (`source`),
  KEY `ix_genus` (`genus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `profane` */

DROP TABLE IF EXISTS `profane`;

CREATE TABLE `profane` (
  `profanity` varchar(50) NOT NULL,
  PRIMARY KEY (`profanity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `source` */

DROP TABLE IF EXISTS `source`;

CREATE TABLE `source` (
  `source` varchar(50) NOT NULL,
  `citation` varchar(250) DEFAULT NULL,
  `hasplants` tinyint(1) unsigned DEFAULT NULL,
  `hasanimals` tinyint(1) unsigned DEFAULT NULL,
  PRIMARY KEY (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `species` */

DROP TABLE IF EXISTS `species`;

CREATE TABLE `species` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(20) DEFAULT NULL,
  `kingdom` varchar(20) DEFAULT NULL,
  `phylum` varchar(30) DEFAULT NULL,
  `class` varchar(30) DEFAULT NULL,
  `order` varchar(30) DEFAULT NULL,
  `family` varchar(100) DEFAULT NULL,
  `genus` varchar(100) DEFAULT NULL,
  `sp` varchar(100) DEFAULT NULL,
  `spauth` varchar(100) DEFAULT NULL,
  `ssp` varchar(100) DEFAULT NULL,
  `sspauth` varchar(100) DEFAULT NULL,
  `var` varchar(100) DEFAULT NULL,
  `varauth` varchar(100) DEFAULT NULL,
  `oth` varchar(100) DEFAULT NULL,
  `othauth` varchar(100) DEFAULT NULL,
  `animal_infraspecies` varchar(100) DEFAULT NULL,
  `animal_author` varchar(100) DEFAULT NULL,
  `spauth_part_new` varchar(100) DEFAULT NULL,
  `spauth_part_old` varchar(100) DEFAULT NULL,
  `fullname` varchar(250) DEFAULT NULL,
  `fullname_naked` varchar(250) DEFAULT NULL,
  `fullname_isambig` tinyint(1) unsigned NOT NULL,
  `fullname_naked_isambig` tinyint(1) unsigned NOT NULL,
  `taxstat` varchar(20) DEFAULT NULL,
  `synonymof` varchar(250) DEFAULT NULL,
  `redlist` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_source` (`source`),
  KEY `ix_fullname` (`fullname`),
  KEY `ix_fullname_naked` (`fullname_naked`),
  KEY `ix_genus` (`genus`),
  KEY `ix_family` (`family`),
  KEY `ix_ssp` (`ssp`),
  KEY `ix_var` (`var`),
  KEY `ix_spauth` (`spauth`),
  KEY `ix_sspauth` (`sspauth`),
  KEY `ix_varauth` (`varauth`),
  KEY `ix_sp` (`sp`),
  KEY `ix_oth` (`oth`),
  KEY `ix_othauth` (`othauth`),
  KEY `ix_spauth_old` (`spauth_part_old`),
  KEY `ix_spauth_new` (`spauth_part_new`)
) ENGINE=InnoDB AUTO_INCREMENT=120905 DEFAULT CHARSET=utf8;

/*Table structure for table `submitted` */

DROP TABLE IF EXISTS `submitted`;

CREATE TABLE `submitted` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `submittedon` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `batch` varchar(50) NOT NULL,
  `submitted_name` varchar(250) DEFAULT NULL,
  `matched_name` varchar(250) DEFAULT NULL COMMENT 'what if ambiguous naked name?',
  `matched_id` int(11) DEFAULT NULL COMMENT 'if SA species or genus',
  `matched_level` varchar(20) DEFAULT NULL COMMENT 'family or genus or species',
  `matched_confidence` varchar(50) DEFAULT NULL,
  `matched_source` varchar(50) DEFAULT NULL COMMENT 'SA list or GBIF',
  `p_genus` varchar(100) DEFAULT NULL,
  `p_sp` varchar(100) DEFAULT NULL,
  `p_spauth` varchar(100) DEFAULT NULL,
  `p_ssp` varchar(100) DEFAULT NULL,
  `p_sspauth` varchar(100) DEFAULT NULL,
  `p_var` varchar(100) DEFAULT NULL,
  `p_varauth` varchar(100) DEFAULT NULL,
  `p_oth` varchar(100) DEFAULT NULL,
  `p_othauth` varchar(100) DEFAULT NULL,
  `p_sp1` varchar(100) DEFAULT NULL,
  `p_auth1` varchar(100) DEFAULT NULL,
  `p_rank1` varchar(10) DEFAULT NULL,
  `p_sp2` varchar(100) DEFAULT NULL,
  `p_auth2` varchar(100) DEFAULT NULL,
  `p_rank2` varchar(10) DEFAULT NULL,
  `p_sp3` varchar(100) DEFAULT NULL,
  `p_auth3` varchar(100) DEFAULT NULL,
  `p_cf` tinyint(1) DEFAULT NULL,
  `p_hybrid` tinyint(1) DEFAULT NULL,
  `p_fullname` varchar(250) DEFAULT NULL,
  `p_fullname_naked` varchar(250) DEFAULT NULL,
  `matched_genus` varchar(100) DEFAULT NULL,
  `matched_dist` int(11) DEFAULT NULL,
  `match_ambig` tinyint(1) DEFAULT NULL,
  `ambig_matches` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_submitted_name` (`submitted_name`),
  KEY `ix_p_fullname` (`p_fullname`),
  KEY `ix_matched_name` (`matched_name`),
  KEY `ix_batch` (`batch`),
  KEY `ix_fullname_naked` (`p_fullname_naked`),
  KEY `ix_genus` (`p_genus`),
  KEY `ix_sp1` (`p_sp1`),
  KEY `ix_sp2` (`p_sp2`),
  KEY `ix_auth1` (`p_auth1`),
  KEY `ix_rank1` (`p_rank1`),
  KEY `ix_auth2` (`p_auth2`),
  KEY `ix_sp3` (`p_sp3`),
  KEY `ix_auth3` (`p_auth3`),
  KEY `ix_rank2` (`p_rank2`)
) ENGINE=InnoDB AUTO_INCREMENT=23088 DEFAULT CHARSET=utf8;

/*Table structure for table `submitted_review` */

DROP TABLE IF EXISTS `submitted_review`;

CREATE TABLE `submitted_review` (
  `submitted_id` int(11) unsigned NOT NULL,
  `submitted_name` varchar(255) DEFAULT NULL,
  `matched_genus` varchar(100) DEFAULT NULL,
  `p_fullname` varchar(250) DEFAULT NULL,
  `p_fullname_naked` varchar(250) DEFAULT NULL,
  `possible_match_id` int(11) unsigned DEFAULT NULL,
  `possible_match_name` varchar(255) DEFAULT NULL,
  `contains_profanity` tinyint(1) unsigned DEFAULT NULL,
  `tried_to_match` tinyint(1) unsigned DEFAULT NULL,
  `matched_dist` int(11) DEFAULT NULL,
  PRIMARY KEY (`submitted_id`),
  KEY `ix_tried_to_match` (`tried_to_match`),
  KEY `ix_contains_profanity` (`contains_profanity`),
  KEY `ix_matched_genus` (`matched_genus`),
  KEY `ix_possible_match_id` (`possible_match_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `submitted_review_feedback` */

DROP TABLE IF EXISTS `submitted_review_feedback`;

CREATE TABLE `submitted_review_feedback` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `submitted_id` int(11) unsigned NOT NULL,
  `judgement` varchar(10) DEFAULT NULL,
  `comment` varchar(100) DEFAULT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_submitted_id` (`submitted_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/* Procedure structure for procedure `sp_fixnulls` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_fixnulls` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_fixnulls`()
BEGIN
	UPDATE species set 
		genus = trim(ifnull(genus,'')),
		sp = trim(ifnull(sp,'')), 
		ssp = trim(ifnull(ssp,'')), 
		spauth = trim(ifnull(spauth,'')), 
		sspauth = trim(ifnull(sspauth,'')), 
		var = trim(ifnull(var,'')), 
		varauth = trim(ifnull(varauth,'')), 
		oth = trim(ifnull(oth,'')), 
		othauth = trim(ifnull(othauth,'')), 
		family = trim(ifnull(family,'')), 
		synonymof = trim(ifnull(synonymof,'')), 
		fullname = trim(ifnull(fullname,'')), 
		fullname_naked = trim(ifnull(fullname_naked,''));
	UPDATE species SET
		animal_author = replace(animal_author, ' ', '') 
	WHERE animal_author LIKE '% %';
	UPDATE species SET
		fullname = replace(fullname, ' ', '') 
	WHERE fullname LIKE '% %';
	UPDATE species SET
		fullname_naked = replace(fullname_naked, ' ', '') 
	WHERE fullname_naked LIKE '% %';
/* remove nbsp from animal authors */
    END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_makehighertables` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_makehighertables` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_makehighertables`()
BEGIN
	truncate families;
	INSERT INTO families (family, source) select distinct family, source FROM species where family > '';
	truncate genera;
	INSERT INTO genera (genus, family, genus_length, source) select distinct genus, family, length(genus), source FROM species where genus > '';
    END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_setambigs` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_setambigs` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_setambigs`()
BEGIN
	update species set fullname_isambig = 0, fullname_naked_isambig = 0;
	update species join (select source, fullname, count(*) as numrecs from species group by source, fullname having count(*) > 1) ambig ON species.fullname = ambig.fullname AND species.source = ambig.source set fullname_isambig = 1;
	update species join (select source, fullname_naked, count(*) as numrecs from species group by source, fullname_naked having count(*) > 1) ambig ON species.fullname_naked = ambig.fullname_naked AND species.source = ambig.source set fullname_naked_isambig = 1;
    END */$$
DELIMITER ;

/* Procedure structure for procedure `sp_setspauthparts` */

/*!50003 DROP PROCEDURE IF EXISTS  `sp_setspauthparts` */;

DELIMITER $$

/*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_setspauthparts`()
BEGIN
	update species set spauth_part_old = null, spauth_part_new = null;
	update species set spauth_part_old = trim(substr(spauth,2,instr(spauth,')')-2)) where spauth like '(%)%';
	update species set spauth_part_old = trim(substr(animal_author,2,instr(animal_author,')')-2)) where animal_author like '(%)%';
	update species set spauth_part_new = trim(substr(spauth,instr(spauth,')')+1)) where spauth like '(%)_%';
	update species set spauth_part_new = trim(substr(animal_author,instr(animal_author,')')+1)) where animal_author like '(%)_%';
	/* some animal authors are badly formatted as e.g. "(safdf af), 1994" */
	update species set spauth_part_new = null where spauth_part_new like ",%";
    END */$$
DELIMITER ;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;