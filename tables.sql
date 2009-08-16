-- User-Library SQL
-- Author: Adam Thody
-- --------------------------------------------------------

CREATE TABLE `ci_sessions` (
  `id` int(8) unsigned NOT NULL auto_increment,
  `session_id` varchar(40) NOT NULL default '0',
  `ip_address` varchar(16) NOT NULL default '0',
  `user_agent` varchar(50) NOT NULL,
  `last_activity` int(10) unsigned NOT NULL default '0',
  `user_data` text NOT NULL,
  PRIMARY KEY  (`session_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE `persistent_sessions` (
  `id` int(8) unsigned NOT NULL auto_increment,
  `identity` varchar(255) NOT NULL,
  `token` varchar(32) NOT NULL,
  `date_created` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`identity`,`token`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `id` int(8) unsigned NOT NULL auto_increment,
  `email` varchar(255) NOT NULL,
  `password` varchar(40) NOT NULL
  PRIMARY KEY  (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;