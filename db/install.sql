CREATE TABLE IF NOT EXISTS `quickcounter_event` (
  `event_id` varchar(32) NOT NULL,
  `counter_id` varchar(32) NOT NULL,
  `range_id` varchar(32) NOT NULL,
  `counter_name` varchar(255) NOT NULL,
  `user_id` varchar(32) NOT NULL,
  `comment` varchar(255) NOT NULL,
  `mkdate` int(20) DEFAULT NULL,
  PRIMARY KEY (`event_id`),
  KEY `range_id` (`range_id`),
  KEY `range_id_2` (`range_id`,`mkdate`)
) ENGINE=MyISAM
