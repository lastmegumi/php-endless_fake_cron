-- phpMyAdmin SQL Dump
-- version 4.7.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 20, 2019 at 06:37 PM
-- Server version: 10.1.23-MariaDB
-- PHP Version: 7.1.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbeusjob`
--

-- --------------------------------------------------------

--
-- Table structure for table `wf_cron`
--

CREATE TABLE `cron` (
  `id` int(4) NOT NULL COMMENT 'i',
  `description` varchar(1024) NOT NULL COMMENT '描述',
  `wf_interval` int(11) NOT NULL COMMENT '间隔',
  `runtimes` int(11) NOT NULL COMMENT '执行次数',
  `status` int(2) NOT NULL COMMENT '状态',
  `start_time` int(12) NOT NULL,
  `last_run` int(12) NOT NULL COMMENT '最后执行',
  `opration` text NOT NULL,
  `arg` varchar(1024) NOT NULL,
  `note` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `wf_cron_status`
--

CREATE TABLE `cron_status` (
  `option_name` varchar(64) NOT NULL,
  `option_value` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `wf_cron_status`
--

INSERT INTO `cron_status` (`option_name`, `option_value`) VALUES
('status', '1'),
('last_run', '1555783573'),
('cron_interval', '3600'),
('start_time', '1553522521'),
('is_run_now', '0'),
('note', '上次执行脚本总共耗时0秒');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `wf_cron`
--
ALTER TABLE `cron`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `wf_cron`
--
ALTER TABLE `cron`
  MODIFY `id` int(4) NOT NULL AUTO_INCREMENT COMMENT 'i', AUTO_INCREMENT=10;COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
