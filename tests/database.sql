-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jun 16, 2011 at 10:05 AM
-- Server version: 5.5.8
-- PHP Version: 5.3.5

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `sqlparser`
--

-- --------------------------------------------------------

--
-- Table structure for table `articles`
--

CREATE TABLE IF NOT EXISTS `articles` (
  `article_id` int(10) NOT NULL AUTO_INCREMENT,
  `article_format_id` int(10) NOT NULL,
  `article_title` varchar(150) NOT NULL,
  `article_body` text NOT NULL,
  `article_date` date NOT NULL,
  `article_cat_id` int(10) NOT NULL,
  `article_active` tinyint(1) NOT NULL,
  `article_user_id` int(10) NOT NULL,
  PRIMARY KEY (`article_id`),
  KEY `article_format_id` (`article_format_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

--
-- Dumping data for table `articles`
--

INSERT INTO `articles` (`article_id`, `article_format_id`, `article_title`, `article_body`, `article_date`, `article_cat_id`, `article_active`, `article_user_id`) VALUES
(1, 2, 'Tinie Tempah, Arcade Fire score Brits doubles', 'Tinie Tempah and Arcade Fire were the major winners at tonight''s Brit Awards, taking home two trophies apiece.', '2011-02-15', 3, 1, 1),
(2, 1, 'Kelly Osbourne ''defends Leona Lewis''', 'Kelly Osbourne has revealed that she doesn''t understand why Leona Lewis is being judged because of her new style.', '2011-02-16', 2, 1, 0),
(3, 2, 'Adele Storms download chart', 'Adele has stormed the singles chart following her performance at the Brit Awards. The singer, who sang a moving rendition of ''Someone Like You'', saw the track rocket up the download chart immediately after the gig.', '2011-01-16', 2, 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `article_categories`
--

CREATE TABLE IF NOT EXISTS `article_categories` (
  `article_id` int(10) NOT NULL,
  `category_id` int(10) NOT NULL,
  KEY `article_id` (`article_id`,`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `article_categories`
--

INSERT INTO `article_categories` (`article_id`, `category_id`) VALUES
(1, 2),
(2, 1),
(2, 2),
(3, 2);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int(10) NOT NULL AUTO_INCREMENT,
  `category_title` varchar(150) NOT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_title`) VALUES
(1, 'Showbiz'),
(2, 'Music'),
(3, 'Hotels');

-- --------------------------------------------------------

--
-- Table structure for table `formats`
--

CREATE TABLE IF NOT EXISTS `formats` (
  `format_id` int(10) NOT NULL AUTO_INCREMENT,
  `format_title` varchar(150) NOT NULL,
  PRIMARY KEY (`format_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `formats`
--

INSERT INTO `formats` (`format_id`, `format_title`) VALUES
(1, 'Video'),
(2, 'TV Show');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(10) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(50) NOT NULL,
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `user_name`) VALUES
(1, 'Jason'),
(2, 'Paul');
