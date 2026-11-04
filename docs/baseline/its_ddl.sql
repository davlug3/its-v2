/* ===== acls ===== */
CREATE TABLE `acls` (
  `id` int(11) NOT NULL,
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acl` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=26;

/* ===== application_settings ===== */
CREATE TABLE `application_settings` (
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `setting` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=36;

/* ===== attachements ===== */
CREATE TABLE `attachements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name_org` varchar(255) NOT NULL,
  `file_name` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

/* ===== categories ===== */
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=43 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=30;

/* ===== groups ===== */
CREATE TABLE `groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=60 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=46;

/* ===== locations ===== */
CREATE TABLE `locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `address` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `type` int(11) NOT NULL DEFAULT 1 COMMENT '1 = Global 2 = Mileage Only',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=145 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=72;

/* ===== locations2 ===== */
CREATE TABLE `locations2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `address` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `type` int(11) NOT NULL DEFAULT 1 COMMENT '1 = Global 2 = Mileage Only',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=106 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=72;

/* ===== mods ===== */
CREATE TABLE `mods` (
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `parent` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `display` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `path` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `has_icon` int(11) NOT NULL COMMENT '1 = TRUE 2 = FALSE',
  `icon` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `acl` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `enabled` int(11) NOT NULL COMMENT '1 = TRUE 2 = FALSE',
  `weight` int(11) NOT NULL,
  PRIMARY KEY (`name`),
  KEY `name` (`name`),
  KEY `parent` (`parent`),
  FULLTEXT KEY `parent_2` (`parent`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=113;

/* ===== notifications ===== */
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `data` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `dt` int(11) NOT NULL,
  `state` int(11) NOT NULL COMMENT '1 = New 2 = Sent',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=159;

/* ===== states ===== */
CREATE TABLE `states` (
  `id` int(11) NOT NULL,
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=20;

/* ===== tickets ===== */
CREATE TABLE `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` text NOT NULL,
  `issue` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `preview` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `resolution` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_uid` int(11) NOT NULL,
  `created_date` int(11) NOT NULL,
  `created_time` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `place` varchar(1000) DEFAULT NULL,
  `contact` varchar(45) DEFAULT NULL,
  `assigned_gid` int(11) NOT NULL DEFAULT 0,
  `assigned_uid` int(11) NOT NULL DEFAULT 0,
  `assigned_date` int(11) NOT NULL DEFAULT 0,
  `assigned_time` int(11) NOT NULL DEFAULT 0,
  `state_id` int(11) NOT NULL,
  `resolved_uid` int(11) NOT NULL DEFAULT 0,
  `resolved_date` int(11) NOT NULL DEFAULT 0,
  `resolved_time` int(11) NOT NULL DEFAULT 0,
  `eu_uid` int(11) NOT NULL,
  `approved_by_supervisor` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tickets_id` (`id`),
  KEY `idx_tickets_assigned_uid_resolved_uid` (`assigned_uid`,`resolved_uid`),
  KEY `idx_tickets_resolved_date` (`resolved_date`)
) ENGINE=MyISAM AUTO_INCREMENT=107236 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=772;

/* ===== tickets_log ===== */
CREATE TABLE `tickets_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `data` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `date` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`),
  KEY `ticket_logindex` (`ticket_id`,`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=408872 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=114;

/* ===== users ===== */
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fn` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `ln` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `mail` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `password` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `acl_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `fpc` int(11) NOT NULL COMMENT '1 = No 2 = Yes',
  `state` int(11) NOT NULL COMMENT '1 = Enabled 2 = Disabled',
  `login_attempts` int(11) NOT NULL,
  `last_login_ip` int(11) NOT NULL,
  `last_login_dt` int(11) NOT NULL,
  `profile_pic_path` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_users_id` (`id`),
  KEY `idx_users_group_id` (`group_id`),
  KEY `idx_supervisor_id` (`supervisor_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4993 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AVG_ROW_LENGTH=90;
