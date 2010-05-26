
--
-- Create schema notes
--

CREATE DATABASE IF NOT EXISTS notes;
USE notes;

--
-- Definition of table `notes`
--

DROP TABLE IF EXISTS `notes`;
CREATE TABLE `notes` (
  `subject` varchar(200) NOT NULL,
  `message` text,
  `id` int(10) unsigned NOT NULL auto_increment,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=latin1;


