-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 07, 2026 at 05:35 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cybershield`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CleanupExpiredOTPs` ()   BEGIN
        DELETE FROM otp_codes 
        WHERE (expires_at < NOW() OR is_used = 1) 
        AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
        
        DELETE FROM otp_logs 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
        
        SELECT ROW_COUNT() AS 'rows_cleaned';
    END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `DetectBiasedQuestions` ()   BEGIN
    -- Update bias scores based on answer patterns
    UPDATE question_bank qb
    SET bias_score = (
        SELECT COALESCE(
            (ABS(0.5 - AVG(CASE WHEN aa.is_correct = 1 THEN 1 ELSE 0 END)) * 100),
            0
        )
        FROM answer_analytics aa
        WHERE aa.question_id = qb.id
        AND aa.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    )
    WHERE EXISTS (
        SELECT 1 FROM answer_analytics aa WHERE aa.question_id = qb.id
    );
    
    -- Flag questions with high bias (over 40% deviation from expected)
    UPDATE question_bank
    SET is_active = FALSE
    WHERE bias_score > 40
    AND times_used > 100;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateQuestionStats` ()   BEGIN
    UPDATE question_bank qb
    SET times_used = (
        SELECT COUNT(*) 
        FROM assessment_answers aa 
        WHERE aa.question_id = qb.id
    ),
    correct_rate = (
        SELECT AVG(CASE WHEN is_correct = 1 THEN 100 ELSE 0 END)
        FROM assessment_answers aa
        WHERE aa.question_id = qb.id
    )
    WHERE EXISTS (
        SELECT 1 FROM assessment_answers aa WHERE aa.question_id = qb.id
    );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action_type`, `action_description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'logout', 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:49:42'),
(2, 1, 'logout', 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 04:44:35'),
(3, 1, 'logout', 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 09:41:42'),
(4, 1, 'logout', 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 09:46:51'),
(5, 1, 'logout', 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:18:28'),
(6, 1, 'logout', 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 17:45:33'),
(7, 2, 'assessment', 'Completed assessment — Score: 28% Rank: F', '::1', NULL, '2026-04-04 19:01:46'),
(8, 1, 'logout', 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 19:45:31'),
(9, 2, 'assessment', 'Completed assessment — Score: 24% Rank: F', '::1', NULL, '2026-04-05 03:23:28'),
(10, 1, 'logout', 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 03:34:08'),
(11, 1, 'logout', 'Admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 06:43:41');

-- --------------------------------------------------------

--
-- Table structure for table `answer_analytics`
--

CREATE TABLE `answer_analytics` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `user_answer` varchar(255) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `time_taken_ms` int(11) DEFAULT 0,
  `answer_position` int(11) DEFAULT 0 COMMENT 'Position of answer in options (0-3)',
  `session_id` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `answer_analytics`
--

INSERT INTO `answer_analytics` (`id`, `user_id`, `question_id`, `user_answer`, `is_correct`, `time_taken_ms`, `answer_position`, `session_id`, `created_at`) VALUES
(1, 2, 10, 'Delete account and start over', 0, 0, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(2, 2, 17, 'Decline to use the service', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(3, 2, 69, 'Let them use your phone hotspot', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(4, 2, 58, 'Only if necessary and with strong credentials; avoid if not needed', 1, 55463, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(5, 2, 48, 'Enter your main password', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(6, 2, 3, 'Disable login alerts to reduce notifications', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(7, 2, 97, 'Ask for an email approval', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(8, 2, 63, 'Use your work email to connect', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(9, 2, 65, 'Leave it connected', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(10, 2, 18, 'Use hardware security keys (YubiKey) or biometrics if available', 1, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(11, 2, 37, 'Ignore', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(12, 2, 59, 'Log in with your account', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(13, 2, 39, 'Open your antivirus software directly; do not click the alert', 1, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(14, 2, 52, 'Ask IT for help', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(15, 2, 46, 'Restart the phone', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(16, 2, 61, 'Keep it as backup', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(17, 2, 87, 'Let security know', 0, 0, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(18, 2, 11, 'Print and keep in drawer', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(19, 2, 80, 'Clear autofill data', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(20, 2, 46, 'Check for malware/spyware and remove unknown apps; consider factory reset if needed', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(21, 2, 12, 'Ask a coworker for help', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(22, 2, 103, 'Remove only names; the rest is fine', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(23, 2, 99, 'Take a screenshot', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(24, 2, 88, 'Reply to the voicemail', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(25, 2, 35, 'Enter email to claim', 0, 927, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(26, 2, 48, 'Skip Wi‑Fi setup', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(27, 2, 3, 'Change the password everywhere it was reused and enable MFA', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(28, 2, 12, 'Use the obvious hint', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(29, 2, 75, 'Ignore the prompt', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(30, 2, 46, 'Delete large files', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(31, 2, 54, 'Allow but delete contacts later', 0, 304, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(32, 2, 81, 'Ask IT for a spare', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(33, 2, 96, 'Direct them to IT/onboarding; do not share your credentials', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(34, 2, 18, 'SMS codes', 0, 1529, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(35, 2, 54, 'Never allow', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(36, 2, 44, 'Save work to cloud and log out', 0, 0, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(37, 2, 82, 'Disable to view', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(38, 2, 77, 'Do not provide password; contact ISP using official channels', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(39, 2, 4, 'Write it on a sticky note', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(40, 2, 35, 'Close the popup; never enter info in unexpected popups', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(41, 2, 72, 'Use your own charger/power bank; avoid public USB data ports', 1, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(42, 2, 37, 'Check the tracking number on the official carrier site; avoid clicking the link', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(43, 2, 61, 'Throw it away', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(44, 2, 20, 'Save them in a personal file', 0, 1736, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(45, 2, 64, 'Forward to IT', 0, 1255, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(46, 2, 41, 'Give it to a coworker', 0, 0, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(47, 2, 85, 'Ask for a callback number', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(48, 2, 26, 'Close the app and reopen it manually; never enter credentials in popups', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(49, 2, 105, 'Verify the request through proper channels and minimize data shared', 1, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(50, 2, 55, 'Cover or disconnect the camera; scan for malware', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(51, 2, 55, 'Uninstall webcam software', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(52, 2, 82, 'Disable temporarily', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(53, 2, 89, 'Provide details to enter', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(54, 2, 101, 'Call security', 0, 0, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(55, 2, 74, 'Disconnect from network and scan for malware; investigate the source', 1, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(56, 2, 15, 'Enable and write down the code', 0, 2792, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(57, 2, 60, 'Use a different app', 0, 0, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(58, 2, 89, 'Provide details to enter', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(59, 2, 69, 'Write the password on a sticky note', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(60, 2, 64, 'Ignore the link; update settings directly from your router or IT', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(61, 2, 103, 'Check if sharing is allowed and use an approved secure method (encrypted transfer / access controls)', 1, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(62, 2, 37, 'Reply STOP', 0, 543, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(63, 2, 41, 'Give it to a coworker', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(64, 2, 63, 'Use a fake email', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(65, 2, 70, 'Ignore it', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(66, 2, 66, 'Proceed anyway', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(67, 2, 60, 'Use a different app', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(68, 2, 94, 'Decline; unknown USB drives can contain malware', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(69, 2, 82, 'Disable temporarily', 0, 1, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(70, 2, 27, 'Scan immediately to secure your spot', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(71, 2, 15, 'Only on personal, secured devices; avoid on public/shared devices', 1, 761, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(72, 2, 84, 'Ask for ID and verify with the company; do not let them inside', 1, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(73, 2, 31, 'Share and let friends answer', 0, 703, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(74, 2, 74, 'Ignore it', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(75, 2, 43, 'Install to try the app', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(76, 2, 98, 'Verify with the partner company and follow proper access procedures', 1, 705, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(77, 2, 39, 'Uninstall the antivirus', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(78, 2, 85, 'Install the software they recommend', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(79, 2, 80, 'Do not log in; check the URL carefully and navigate to the official site', 1, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(80, 2, 18, 'SMS codes', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(81, 2, 10, 'Change master password first, then rotate all critical passwords from a clean device', 1, 0, 3, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(82, 2, 93, 'Decline and let them handle it', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(83, 2, 36, 'Enable to proceed', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(84, 2, 89, 'Provide details to enter', 0, 0, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(85, 2, 106, 'Use cross-cut shredding or professional destruction service', 1, 0, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(86, 2, 22, 'Verify on the official carrier site using the tracking number; avoid clicking email links', 1, 0, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(87, 2, 27, 'Scan immediately to secure your spot', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(88, 2, 96, 'Help them reset their password', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(89, 2, 31, 'Use fake information', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(90, 2, 20, 'Transfer knowledge and change all shared passwords; do not keep them', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(91, 2, 107, 'Keep it safe until someone claims it', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(92, 2, 65, 'Investigate and remove the device; change Wi‑Fi password', 1, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(93, 2, 86, 'Ask for references', 0, 0, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(94, 2, 97, 'Reply to confirm', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(95, 2, 33, 'Enter credentials quickly', 0, 0, 1, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(96, 2, 15, 'Never enable', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(97, 2, 92, 'Verify the identity through official channels; be skeptical of urgent requests', 1, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(98, 2, 24, 'Share the message to warn others', 0, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(99, 2, 31, 'Skip the quiz; never share security answers in fun apps', 1, 0, 2, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(100, 2, 7, 'Allow it to enhance security', 0, 0, 0, '9890b924432cc235d9b626152b35e137', '2026-04-04 19:01:46'),
(101, 2, 102, 'Reply to confirm', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(102, 2, 106, 'Keep for future reference', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(103, 2, 103, 'Send it quickly by email attachment', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(104, 2, 42, 'Charge at home only', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(105, 2, 3, 'Use the same password but add a symbol', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(106, 2, 12, 'Create a new account', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(107, 2, 36, 'Contact the colleague via another channel to verify; do not enable content', 1, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(108, 2, 48, 'Use a simpler password', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(109, 2, 26, 'Enter credentials quickly', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(110, 2, 100, 'Hang up', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(111, 2, 15, 'Only on personal, secured devices; avoid on public/shared devices', 1, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(112, 2, 1, 'Ignore it if nothing seems wrong', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(113, 2, 42, 'Borrow a charger', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(114, 2, 99, 'Ignore it', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(115, 2, 41, 'Give it to a coworker', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(116, 2, 102, 'Click to appeal', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(117, 2, 40, 'Click the link to verify', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(118, 2, 16, 'Ignore it', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(119, 2, 52, 'Research the service first', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(120, 2, 86, 'Accept the offer immediately', 0, 1112, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(121, 2, 52, 'Research the service first', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(122, 2, 50, 'Force restart and run antivirus; do not call the number', 1, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(123, 2, 46, 'Delete large files', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(124, 2, 25, 'Do not enable macros; verify the invoice through a known contact or portal', 1, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(125, 2, 9, 'Write it down instead', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(126, 2, 104, 'Minimize the screen', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(127, 2, 20, 'Save them in a personal file', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(128, 2, 43, 'Decline; only install from official app stores', 1, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(129, 2, 25, 'Delete it', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(130, 2, 50, 'Force restart and run antivirus; do not call the number', 1, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(131, 2, 28, 'Check the original sender and verify independently; do not trust forwarded chains', 1, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(132, 2, 98, 'Ask for ID only', 0, 752, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(133, 2, 8, 'Reply with your password', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(134, 2, 41, 'Plug it in to see who it belongs to', 0, 616, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(135, 2, 11, 'Print and keep in drawer', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(136, 2, 94, 'Decline; unknown USB drives can contain malware', 1, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(137, 2, 82, 'Decline or use a trusted site; ad blockers improve security', 1, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(138, 2, 46, 'Ignore it', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(139, 2, 70, 'Update the firmware immediately; set auto-updates if available', 1, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(140, 2, 3, 'Wait to see if your account is affected', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(141, 2, 14, 'Length and uniqueness over complexity; encourage password managers', 1, 165145, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(142, 2, 16, 'Ignore it', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(143, 2, 40, 'Click the link to verify', 0, 0, 3, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(144, 2, 46, 'Ignore it', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(145, 2, 64, 'Click the link to update', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(146, 2, 30, 'Reply to the message', 0, 0, 3, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(147, 2, 71, 'Ignore it', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(148, 2, 84, 'Refuse the delivery', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(149, 2, 86, 'Research the company and verify through official channels; be cautious', 1, 1272, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(150, 2, 8, 'Do not click links; go directly to the official site/app to check your account status', 1, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(151, 2, 71, 'Ignore it', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(152, 2, 34, 'Accept and click to join', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(153, 2, 101, 'Let them in', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(154, 2, 37, 'Ignore', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(155, 2, 104, 'Use a privacy screen and lock the device when stepping away', 1, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(156, 2, 83, 'Ask them for their password to confirm identity', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(157, 2, 67, 'Change to trusted DNS servers (e.g., 8.8.8.8, 1.1.1.1)', 1, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(158, 2, 30, 'Reply to the message', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(159, 2, 59, 'Use your phone instead', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(160, 2, 66, 'Try a different browser', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(161, 2, 1, 'Click the notification link and sign in to check', 0, 0, 3, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(162, 2, 87, 'Lend it to them', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(163, 2, 30, 'Ignore it', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(164, 2, 28, 'Check the original sender and verify independently; do not trust forwarded chains', 1, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(165, 2, 84, 'Take the package at the door', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(166, 2, 90, 'Let them in quickly', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(167, 2, 79, 'Research the QR code first', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(168, 2, 15, 'Enable and write down the code', 0, 1104, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(169, 2, 62, 'Use any free VPN advertised on a pop-up', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(170, 2, 2, 'Use a long passphrase or password manager instead of the suggested pattern', 1, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(171, 2, 42, 'Charge at home only', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(172, 2, 5, 'Report to IT immediately and use a different clean device to change critical passwords', 1, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(173, 2, 81, 'Ask IT for a spare', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(174, 2, 102, 'Reply to confirm', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(175, 2, 69, 'Create a guest network with a separate password', 1, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(176, 2, 74, 'Disconnect from network and scan for malware; investigate the source', 1, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(177, 2, 37, 'Check the tracking number on the official carrier site; avoid clicking the link', 1, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(178, 2, 42, 'Borrow a charger', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(179, 2, 38, 'Upload to proceed quickly', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(180, 2, 28, 'Reply all to confirm', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(181, 2, 100, 'Answer their questions', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(182, 2, 30, 'Reply to the message', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(183, 2, 45, 'Buy a new laptop', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(184, 2, 64, 'Click the link to update', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(185, 2, 77, 'Do not provide password; contact ISP using official channels', 1, 624, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(186, 2, 9, 'Always accept', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(187, 2, 53, 'Upload to public cloud and share link', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(188, 2, 88, 'Reply to the voicemail', 0, 0, 2, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(189, 2, 86, 'Ask for references', 0, 568, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(190, 2, 1, 'Reply to the notification email to ask for details', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(191, 2, 76, 'Send from personal email', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(192, 2, 56, 'Disable remote management and change default passwords', 1, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(193, 2, 79, 'Scan to connect quickly', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(194, 2, 8, 'Reply with your password', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(195, 2, 29, 'Try a different browser', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(196, 2, 107, 'Destroy it', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(197, 2, 95, 'Open the attachment', 0, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(198, 2, 34, 'Decline the invite; verify the sender before accepting any links', 1, 0, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(199, 2, 63, 'Use your work email to connect', 0, 3504, 0, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28'),
(200, 2, 73, 'Accept and email yourself the passwords', 0, 0, 1, '5e9bc9343721938406ab9e90fbf2c952', '2026-04-05 03:23:28');

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `rank` varchar(2) NOT NULL,
  `password_score` int(11) DEFAULT 0,
  `phishing_score` int(11) DEFAULT 0,
  `device_score` int(11) DEFAULT 0,
  `network_score` int(11) DEFAULT 0,
  `social_engineering_score` int(11) DEFAULT 0,
  `data_handling_score` int(11) DEFAULT 0,
  `time_spent` int(11) NOT NULL,
  `questions_answered` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `assessment_date` datetime NOT NULL,
  `assessment_token` varchar(64) DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessments`
--

INSERT INTO `assessments` (`id`, `vendor_id`, `score`, `rank`, `password_score`, `phishing_score`, `device_score`, `network_score`, `social_engineering_score`, `data_handling_score`, `time_spent`, `questions_answered`, `total_questions`, `assessment_date`, `assessment_token`, `session_id`, `created_at`, `updated_at`) VALUES
(1, 1, 95, 'A', 100, 90, 95, 88, 100, 92, 1200, 20, 20, '2024-01-15 10:30:00', 'token_admin_1', 'session_admin_1', '2026-04-02 15:38:59', '2026-04-02 15:38:59'),
(7, 3, 65, 'D', 60, 68, 65, 70, 62, 65, 2000, 20, 20, '2024-01-22 16:45:00', 'token_viewer_1', 'session_viewer_1', '2026-04-02 15:38:59', '2026-04-02 15:38:59'),
(8, 2, 24, 'F', 25, 32, 21, 33, 11, 20, 709, 100, 100, '2026-04-05 05:23:28', 'af0b65d4ad801c8d0328ef8ba2e93956bf9092c2e0793e3eaacceac5caac1f92', NULL, '2026-04-04 19:01:46', '2026-04-05 03:23:28');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_answers`
--

CREATE TABLE `assessment_answers` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `user_answer` text NOT NULL,
  `correct_answer` varchar(255) NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `category` varchar(50) NOT NULL,
  `answer_position` int(11) DEFAULT 0,
  `time_taken_ms` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_answers`
--

INSERT INTO `assessment_answers` (`id`, `assessment_id`, `question_id`, `question_text`, `user_answer`, `correct_answer`, `is_correct`, `category`, `answer_position`, `time_taken_ms`, `created_at`) VALUES
(101, 8, 102, 'Scenario: You receive a fake termination notice with a link to \"appeal\". What should you do?', 'Reply to confirm', 'Contact HR directly; do not click links in unexpected employment notices', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(102, 8, 106, 'Scenario: You must dispose of printed client files. What is the correct method?', 'Keep for future reference', 'Use cross-cut shredding or professional destruction service', 0, 'data_handling', 0, 0, '2026-04-05 03:23:28'),
(103, 8, 103, 'Scenario: You need to send a file with customer data to a partner. What is the safest first step?', 'Send it quickly by email attachment', 'Check if sharing is allowed and use an approved secure method (encrypted transfer / access controls)', 0, 'data_handling', 0, 0, '2026-04-05 03:23:28'),
(104, 8, 42, 'Scenario: Your laptop battery is dying and you need to charge in public. What is safest?', 'Charge at home only', 'Use your own charger and power bank; avoid public USB ports', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(105, 8, 3, 'Scenario: Your password was reused on another site that was breached. What should you do?', 'Use the same password but add a symbol', 'Change the password everywhere it was reused and enable MFA', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(106, 8, 12, 'Scenario: You forgot your password and the recovery hint is obvious to others. What should you do?', 'Create a new account', 'Skip the hint and use account recovery; change the hint to something non-obvious', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(107, 8, 36, 'Scenario: A colleague shares a document that asks you to enable content for \"security verification\". What should you do?', 'Contact the colleague via another channel to verify; do not enable content', 'Contact the colleague via another channel to verify; do not enable content', 1, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(108, 8, 48, 'Scenario: Your smart TV asks for your Wi‑Fi password during setup. What should you do?', 'Use a simpler password', 'Create a guest network for IoT devices; avoid sharing main network credentials', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(109, 8, 26, 'Scenario: A banking app popup says your session has expired and to re-enter credentials. What do you do?', 'Enter credentials quickly', 'Close the app and reopen it manually; never enter credentials in popups', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(110, 8, 100, 'Scenario: A researcher calls asking about your company\'s security practices. What should you do?', 'Hang up', 'Refer them to PR/legal; don\'t share internal details', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(111, 8, 15, 'Scenario: Your phone shows a \"trusted device\" prompt after login. Should you enable it?', 'Only on personal, secured devices; avoid on public/shared devices', 'Only on personal, secured devices; avoid on public/shared devices', 1, 'password', 0, 0, '2026-04-05 03:23:28'),
(112, 8, 1, 'Scenario: You receive a notification that your account was accessed from a new device. What do you do first?', 'Ignore it if nothing seems wrong', 'Secure the account by changing the password and enabling MFA, then review recent activity', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(113, 8, 42, 'Scenario: Your laptop battery is dying and you need to charge in public. What is safest?', 'Borrow a charger', 'Use your own charger and power bank; avoid public USB ports', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(114, 8, 99, 'Scenario: You receive a fake security alert asking to enter credentials to \"secure your account\". What should you do?', 'Ignore it', 'Navigate to the official site yourself; do not enter credentials in the alert', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(115, 8, 41, 'Scenario: You find a USB drive in the parking lot. What should you do?', 'Give it to a coworker', 'Turn it in to IT/security; do not plug it into your computer', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(116, 8, 102, 'Scenario: You receive a fake termination notice with a link to \"appeal\". What should you do?', 'Click to appeal', 'Contact HR directly; do not click links in unexpected employment notices', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(117, 8, 40, 'Scenario: A dating app match asks you to verify your identity via their link. What should you do?', 'Click the link to verify', 'Use the app\'s official verification features; avoid external links', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(118, 8, 16, 'Scenario: You receive a password reset you didn\'t request. What do you do?', 'Ignore it', 'Secure your account immediately and check for other unauthorized activity', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(119, 8, 52, 'Scenario: Your tablet asks to back up to a cloud service you don\'t recognize. What should you do?', 'Research the service first', 'Decline and use only trusted backup services you set up', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(120, 8, 86, 'Scenario: A stranger on social media offers you a job after only a few messages. What should you do?', 'Accept the offer immediately', 'Research the company and verify through official channels; be cautious', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(121, 8, 52, 'Scenario: Your tablet asks to back up to a cloud service you don\'t recognize. What should you do?', 'Research the service first', 'Decline and use only trusted backup services you set up', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(122, 8, 50, 'Scenario: Your computer shows a blue screen with a phone number for \"tech support\". What should you do?', 'Force restart and run antivirus; do not call the number', 'Force restart and run antivirus; do not call the number', 1, 'device', 0, 0, '2026-04-05 03:23:28'),
(123, 8, 46, 'Scenario: You notice unusual battery drain and data usage on your phone. What should you do?', 'Delete large files', 'Check for malware/spyware and remove unknown apps; consider factory reset if needed', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(124, 8, 25, 'Scenario: You receive a PDF invoice via email that asks you to enable macros to view. What should you do?', 'Do not enable macros; verify the invoice through a known contact or portal', 'Do not enable macros; verify the invoice through a known contact or portal', 1, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(125, 8, 9, 'Scenario: Your browser offers to save a new password. Should you accept?', 'Write it down instead', 'Only if you trust the device and use a master password/encryption; otherwise use a password manager', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(126, 8, 104, 'Scenario: You\'re working in a coffee shop with sensitive documents open. What should you do?', 'Minimize the screen', 'Use a privacy screen and lock the device when stepping away', 0, 'data_handling', 0, 0, '2026-04-05 03:23:28'),
(127, 8, 20, 'Scenario: You\'re leaving a job. What should you do with your work passwords?', 'Save them in a personal file', 'Transfer knowledge and change all shared passwords; do not keep them', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(128, 8, 43, 'Scenario: Your phone asks to install an app from an unknown source. What should you do?', 'Decline; only install from official app stores', 'Decline; only install from official app stores', 1, 'device', 0, 0, '2026-04-05 03:23:28'),
(129, 8, 25, 'Scenario: You receive a PDF invoice via email that asks you to enable macros to view. What should you do?', 'Delete it', 'Do not enable macros; verify the invoice through a known contact or portal', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(130, 8, 50, 'Scenario: Your computer shows a blue screen with a phone number for \"tech support\". What should you do?', 'Force restart and run antivirus; do not call the number', 'Force restart and run antivirus; do not call the number', 1, 'device', 0, 0, '2026-04-05 03:23:28'),
(131, 8, 28, 'Scenario: A coworker forwards an email chain asking you to click a link to \"verify your email\". What do you do?', 'Check the original sender and verify independently; do not trust forwarded chains', 'Check the original sender and verify independently; do not trust forwarded chains', 1, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(132, 8, 98, 'Scenario: Someone claims to be from partner company and needs access to your server room. What should you do?', 'Ask for ID only', 'Verify with the partner company and follow proper access procedures', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(133, 8, 8, 'Scenario: You receive an email claiming your account will be locked unless you \"verify your password\" now. What do you do?', 'Reply with your password', 'Do not click links; go directly to the official site/app to check your account status', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(134, 8, 41, 'Scenario: You find a USB drive in the parking lot. What should you do?', 'Plug it in to see who it belongs to', 'Turn it in to IT/security; do not plug it into your computer', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(135, 8, 11, 'Scenario: A shared spreadsheet requires login credentials to access. How should you store them?', 'Print and keep in drawer', 'Use a password manager or encrypted vault; never store in the sheet', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(136, 8, 94, 'Scenario: Someone at a conference offers you a free USB drive. What should you do?', 'Decline; unknown USB drives can contain malware', 'Decline; unknown USB drives can contain malware', 1, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(137, 8, 82, 'Scenario: A website asks you to disable your ad blocker to view content. What should you do?', 'Decline or use a trusted site; ad blockers improve security', 'Decline or use a trusted site; ad blockers improve security', 1, 'network', 0, 0, '2026-04-05 03:23:28'),
(138, 8, 46, 'Scenario: You notice unusual battery drain and data usage on your phone. What should you do?', 'Ignore it', 'Check for malware/spyware and remove unknown apps; consider factory reset if needed', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(139, 8, 70, 'Scenario: Your router firmware is outdated. What should you do?', 'Update the firmware immediately; set auto-updates if available', 'Update the firmware immediately; set auto-updates if available', 1, 'network', 0, 0, '2026-04-05 03:23:28'),
(140, 8, 3, 'Scenario: Your password was reused on another site that was breached. What should you do?', 'Wait to see if your account is affected', 'Change the password everywhere it was reused and enable MFA', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(141, 8, 14, 'Scenario: You need to create a password policy for your team. What should you prioritize?', 'Length and uniqueness over complexity; encourage password managers', 'Length and uniqueness over complexity; encourage password managers', 1, 'password', 0, 0, '2026-04-05 03:23:28'),
(142, 8, 16, 'Scenario: You receive a password reset you didn\'t request. What do you do?', 'Ignore it', 'Secure your account immediately and check for other unauthorized activity', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(143, 8, 40, 'Scenario: A dating app match asks you to verify your identity via their link. What should you do?', 'Click the link to verify', 'Use the app\'s official verification features; avoid external links', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(144, 8, 46, 'Scenario: You notice unusual battery drain and data usage on your phone. What should you do?', 'Ignore it', 'Check for malware/spyware and remove unknown apps; consider factory reset if needed', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(145, 8, 64, 'Scenario: You receive an email asking you to update your network settings via a link. What should you do?', 'Click the link to update', 'Ignore the link; update settings directly from your router or IT', 0, 'network', 0, 0, '2026-04-05 03:23:28'),
(146, 8, 30, 'Scenario: You receive a voice message with instructions to call a number about \"suspicious activity\". What should you do?', 'Reply to the message', 'Call the official number from the company\'s website; not the number in the message', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(147, 8, 71, 'Scenario: You receive a text claiming your bank account is frozen with a link to unlock. What should you do?', 'Ignore it', 'Call the bank using the official number; do not click the link', 0, 'network', 0, 0, '2026-04-05 03:23:28'),
(148, 8, 84, 'Scenario: A delivery person at your door asks to come inside to \"verify a package\". What should you do?', 'Refuse the delivery', 'Ask for ID and verify with the company; do not let them inside', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(149, 8, 86, 'Scenario: A stranger on social media offers you a job after only a few messages. What should you do?', 'Research the company and verify through official channels; be cautious', 'Research the company and verify through official channels; be cautious', 1, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(150, 8, 8, 'Scenario: You receive an email claiming your account will be locked unless you \"verify your password\" now. What do you do?', 'Do not click links; go directly to the official site/app to check your account status', 'Do not click links; go directly to the official site/app to check your account status', 1, 'password', 0, 0, '2026-04-05 03:23:28'),
(151, 8, 71, 'Scenario: You receive a text claiming your bank account is frozen with a link to unlock. What should you do?', 'Ignore it', 'Call the bank using the official number; do not click the link', 0, 'network', 0, 0, '2026-04-05 03:23:28'),
(152, 8, 34, 'Scenario: You receive a calendar invite from an unknown sender with a link to \"join\". What should you do?', 'Accept and click to join', 'Decline the invite; verify the sender before accepting any links', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(153, 8, 101, 'Scenario: Someone at the door claims to be from utilities and needs access inside. What should you do?', 'Let them in', 'Ask for ID and verify with the utility company; do not allow entry without verification', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(154, 8, 37, 'Scenario: You get a text with a link saying a package is delayed. What should you do?', 'Ignore', 'Check the tracking number on the official carrier site; avoid clicking the link', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(155, 8, 104, 'Scenario: You\'re working in a coffee shop with sensitive documents open. What should you do?', 'Use a privacy screen and lock the device when stepping away', 'Use a privacy screen and lock the device when stepping away', 1, 'data_handling', 0, 0, '2026-04-05 03:23:28'),
(156, 8, 83, 'Scenario: Someone claiming to be IT asks you for a one-time code to \"fix your account\". What is the best response?', 'Ask them for their password to confirm identity', 'Refuse and verify the request through official IT channels', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(157, 8, 67, 'Scenario: You suspect your DNS is being tampered with. What should you do?', 'Change to trusted DNS servers (e.g., 8.8.8.8, 1.1.1.1)', 'Change to trusted DNS servers (e.g., 8.8.8.8, 1.1.1.1)', 1, 'network', 0, 0, '2026-04-05 03:23:28'),
(158, 8, 30, 'Scenario: You receive a voice message with instructions to call a number about \"suspicious activity\". What should you do?', 'Reply to the message', 'Call the official number from the company\'s website; not the number in the message', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(159, 8, 59, 'Scenario: You need to use a coworker\'s computer temporarily. What should you do?', 'Use your phone instead', 'Use a guest account or incognito mode; don\'t save any credentials', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(160, 8, 66, 'Scenario: Your browser warns a site\'s certificate is invalid. What should you do?', 'Try a different browser', 'Do not proceed; verify the site or use a different trusted site', 0, 'network', 0, 0, '2026-04-05 03:23:28'),
(161, 8, 1, 'Scenario: You receive a notification that your account was accessed from a new device. What do you do first?', 'Click the notification link and sign in to check', 'Secure the account by changing the password and enabling MFA, then review recent activity', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(162, 8, 87, 'Scenario: Someone at your desk asks to \"borrow your badge\" while you step away. What should you do?', 'Lend it to them', 'Never share badges; escort them or use proper visitor procedures', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(163, 8, 30, 'Scenario: You receive a voice message with instructions to call a number about \"suspicious activity\". What should you do?', 'Ignore it', 'Call the official number from the company\'s website; not the number in the message', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(164, 8, 28, 'Scenario: A coworker forwards an email chain asking you to click a link to \"verify your email\". What do you do?', 'Check the original sender and verify independently; do not trust forwarded chains', 'Check the original sender and verify independently; do not trust forwarded chains', 1, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(165, 8, 84, 'Scenario: A delivery person at your door asks to come inside to \"verify a package\". What should you do?', 'Take the package at the door', 'Ask for ID and verify with the company; do not let them inside', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(166, 8, 90, 'Scenario: Someone at the entrance says they forgot their badge and asks you to let them in. What should you do?', 'Let them in quickly', 'Direct them to security or reception; do not tailgate', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(167, 8, 79, 'Scenario: You receive a QR code to join Wi‑Fi instantly. What should you do?', 'Research the QR code first', 'Avoid scanning unknown QR codes; connect manually instead', 0, 'network', 0, 0, '2026-04-05 03:23:28'),
(168, 8, 15, 'Scenario: Your phone shows a \"trusted device\" prompt after login. Should you enable it?', 'Enable and write down the code', 'Only on personal, secured devices; avoid on public/shared devices', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(169, 8, 62, 'Scenario: You must use public Wi‑Fi to access your work account. What should you do?', 'Use any free VPN advertised on a pop-up', 'Use a trusted VPN and avoid sensitive actions if you cannot verify the network', 0, 'network', 0, 0, '2026-04-05 03:23:28'),
(170, 8, 2, 'Scenario: A website tells you your password is weak and suggests a simple pattern. What should you do?', 'Use a long passphrase or password manager instead of the suggested pattern', 'Use a long passphrase or password manager instead of the suggested pattern', 1, 'password', 0, 0, '2026-04-05 03:23:28'),
(171, 8, 42, 'Scenario: Your laptop battery is dying and you need to charge in public. What is safest?', 'Charge at home only', 'Use your own charger and power bank; avoid public USB ports', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(172, 8, 5, 'Scenario: You suspect a keylogger on your work computer. What should you do first?', 'Report to IT immediately and use a different clean device to change critical passwords', 'Report to IT immediately and use a different clean device to change critical passwords', 1, 'password', 0, 0, '2026-04-05 03:23:28'),
(173, 8, 81, 'Scenario: You need to use a coworker\'s network cable temporarily. What should you do?', 'Ask IT for a spare', 'Use your own if possible; avoid sharing network hardware', 0, 'network', 0, 0, '2026-04-05 03:23:28'),
(174, 8, 102, 'Scenario: You receive a fake termination notice with a link to \"appeal\". What should you do?', 'Reply to confirm', 'Contact HR directly; do not click links in unexpected employment notices', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(175, 8, 69, 'Scenario: You need to share your Wi‑Fi with a guest. What is the safest method?', 'Create a guest network with a separate password', 'Create a guest network with a separate password', 1, 'network', 0, 0, '2026-04-05 03:23:28'),
(176, 8, 74, 'Scenario: You notice unusual outbound traffic from your computer. What should you do?', 'Disconnect from network and scan for malware; investigate the source', 'Disconnect from network and scan for malware; investigate the source', 1, 'network', 0, 0, '2026-04-05 03:23:28'),
(177, 8, 37, 'Scenario: You get a text with a link saying a package is delayed. What should you do?', 'Check the tracking number on the official carrier site; avoid clicking the link', 'Check the tracking number on the official carrier site; avoid clicking the link', 1, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(178, 8, 42, 'Scenario: Your laptop battery is dying and you need to charge in public. What is safest?', 'Borrow a charger', 'Use your own charger and power bank; avoid public USB ports', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(179, 8, 38, 'Scenario: A website asks you to upload a photo of your ID for \"verification\". What should you do?', 'Upload to proceed quickly', 'Only upload ID on official, trusted sites; avoid unknown sites', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(180, 8, 28, 'Scenario: A coworker forwards an email chain asking you to click a link to \"verify your email\". What do you do?', 'Reply all to confirm', 'Check the original sender and verify independently; do not trust forwarded chains', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(181, 8, 100, 'Scenario: A researcher calls asking about your company\'s security practices. What should you do?', 'Answer their questions', 'Refer them to PR/legal; don\'t share internal details', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(182, 8, 30, 'Scenario: You receive a voice message with instructions to call a number about \"suspicious activity\". What should you do?', 'Reply to the message', 'Call the official number from the company\'s website; not the number in the message', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(183, 8, 45, 'Scenario: Your work laptop is stolen. What is the first priority?', 'Buy a new laptop', 'Report immediately to IT and enable remote wipe if available', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(184, 8, 64, 'Scenario: You receive an email asking you to update your network settings via a link. What should you do?', 'Click the link to update', 'Ignore the link; update settings directly from your router or IT', 0, 'network', 0, 0, '2026-04-05 03:23:28'),
(185, 8, 77, 'Scenario: Your ISP contacts you claiming your account is compromised and asks for your password. What should you do?', 'Do not provide password; contact ISP using official channels', 'Do not provide password; contact ISP using official channels', 1, 'network', 0, 0, '2026-04-05 03:23:28'),
(186, 8, 9, 'Scenario: Your browser offers to save a new password. Should you accept?', 'Always accept', 'Only if you trust the device and use a master password/encryption; otherwise use a password manager', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(187, 8, 53, 'Scenario: You need to share files with a client securely. What is the best method?', 'Upload to public cloud and share link', 'Use encrypted file transfer or a trusted secure share link; avoid email attachments', 0, 'device', 0, 0, '2026-04-05 03:23:28'),
(188, 8, 88, 'Scenario: You receive a voicemail from your \"bank\" asking to call back about fraud. What should you do?', 'Reply to the voicemail', 'Call the bank using the official number on your card; not the number in the voicemail', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(189, 8, 86, 'Scenario: A stranger on social media offers you a job after only a few messages. What should you do?', 'Ask for references', 'Research the company and verify through official channels; be cautious', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(190, 8, 1, 'Scenario: You receive a notification that your account was accessed from a new device. What do you do first?', 'Reply to the notification email to ask for details', 'Secure the account by changing the password and enabling MFA, then review recent activity', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(191, 8, 76, 'Scenario: You need to send sensitive files over email. What should you do?', 'Send from personal email', 'Encrypt the files or use a secure file transfer service', 0, 'network', 0, 0, '2026-04-05 03:23:28'),
(192, 8, 56, 'Scenario: Your router admin page is accessible from the internet. What should you do?', 'Disable remote management and change default passwords', 'Disable remote management and change default passwords', 1, 'device', 0, 0, '2026-04-05 03:23:28'),
(193, 8, 79, 'Scenario: You receive a QR code to join Wi‑Fi instantly. What should you do?', 'Scan to connect quickly', 'Avoid scanning unknown QR codes; connect manually instead', 0, 'network', 0, 0, '2026-04-05 03:23:28'),
(194, 8, 8, 'Scenario: You receive an email claiming your account will be locked unless you \"verify your password\" now. What do you do?', 'Reply with your password', 'Do not click links; go directly to the official site/app to check your account status', 0, 'password', 0, 0, '2026-04-05 03:23:28'),
(195, 8, 29, 'Scenario: A website displays a security warning saying your connection is not private. What should you do?', 'Try a different browser', 'Do not proceed; check the URL manually or use the official site', 0, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(196, 8, 107, 'Scenario: You find a USB drive with labeled \"client data\" in the parking lot. What should you do?', 'Destroy it', 'Turn it in to security/IT; do not attempt to access it', 0, 'data_handling', 0, 0, '2026-04-05 03:23:28'),
(197, 8, 95, 'Scenario: You receive a court summons via email with an attachment. What should you do?', 'Open the attachment', 'Verify with the court directly; do not open attachments', 0, 'social_engineering', 0, 0, '2026-04-05 03:23:28'),
(198, 8, 34, 'Scenario: You receive a calendar invite from an unknown sender with a link to \"join\". What should you do?', 'Decline the invite; verify the sender before accepting any links', 'Decline the invite; verify the sender before accepting any links', 1, 'phishing', 0, 0, '2026-04-05 03:23:28'),
(199, 8, 63, 'Scenario: A coffee shop Wi‑Fi requires your email to connect. What should you do?', 'Use your work email to connect', 'Use a disposable email or decline; avoid giving real credentials', 0, 'network', 0, 0, '2026-04-05 03:23:28'),
(200, 8, 73, 'Scenario: Your browser offers to save passwords for a site. Should you accept?', 'Accept and email yourself the passwords', 'Only if it\'s your personal device with encryption; otherwise use a password manager', 0, 'network', 0, 0, '2026-04-05 03:23:28');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_sessions`
--

CREATE TABLE `assessment_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `question_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`question_ids`)),
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_sessions`
--

INSERT INTO `assessment_sessions` (`id`, `user_id`, `session_id`, `question_ids`, `started_at`, `completed_at`, `ip_address`) VALUES
(1, 2, '852c7fa9b3b8d4e55b19903f37e47229', '[4,103,87,40,79,90,2,35,15,5,39,62,48,37,26,98,70,80,83,67,12,69,1,99,21,9,91,28,11,82,85,3,30,8,41,96,24,73,44,18,56,98,19,94,38,107,36,64,77,46,52,60,75,51,20,88,95,74,72,22,63,86,65,25,58,104,31,6,47,53,105,76,57,27,16,10,68,43,100,7,106,84,55,33,78,45,54,59,93,23,97,32,42,101,92,89,29,81,102,17]', '2026-04-02 15:40:33', NULL, '::1'),
(2, 2, '44c4f2333a5c00d3f6f6a0b8cba43e8b', '[14,1,15,104,95,77,66,30,78,93,91,31,2,78,27,60,88,89,55,82,57,35,30,50,86,103,61,72,94,100,88,44,2,40,25,75,97,43,65,24,43,93,79,55,15,102,15,40,82,67,79,49,107,40,74,36,82,59,76,55,58,97,16,39,73,69,98,105,26,104,49,84,7,59,15,71,98,12,49,90,62,17,64,39,42,12,34,99,13,101,16,36,70,14,33,30,11,103,7,10]', '2026-04-02 15:41:33', NULL, '::1'),
(3, 2, 'ee6bd2e80849d9a762562bba3a68f6c6', '[24,46,100,15,81,81,32,81,68,66,68,26,25,98,4,61,30,93,19,45,23,90,63,106,12,96,67,46,19,42,26,75,35,95,52,20,44,67,68,31,18,8,77,102,101,83,93,49,94,54,4,65,39,66,49,60,14,88,15,31,52,68,19,106,16,75,17,22,8,79,97,86,105,83,90,19,51,10,34,55,58,22,89,14,11,91,106,9,31,61,46,57,32,2,58,85,78,90,95,3]', '2026-04-02 15:43:18', NULL, '::1'),
(4, 2, '832a51e7a655034dd249efbed3bb321b', '[84,20,85,105,18,84,76,56,97,42,61,30,17,45,88,72,76,4,67,77,35,68,107,50,61,98,16,57,31,81,32,74,52,76,26,3,55,102,77,99,5,18,89,49,32,47,20,12,70,18,99,79,90,85,72,97,3,78,20,25,8,56,107,42,53,100,23,60,44,77,93,99,59,32,102,28,13,19,102,16,71,12,56,51,58,33,4,23,46,104,90,35,31,80,4,98,73,87,77,81]', '2026-04-03 15:26:16', NULL, '::1'),
(5, 2, '217380379f1bb8ce1f749e18aedc6787', '[2,44,67,53,76,76,4,70,50,3,23,87,98,45,71,9,16,9,106,92,63,5,1,20,22,84,102,7,39,55,38,90,63,33,23,69,92,13,88,39,99,21,37,73,66,40,62,75,8,35,16,2,54,46,96,54,41,13,106,61,82,95,25,47,11,24,80,45,13,106,93,65,101,52,51,105,35,4,21,27,94,42,7,61,3,43,16,49,99,62,10,77,52,14,30,68,75,100,28,38]', '2026-04-03 16:05:00', NULL, '::1'),
(6, 2, '4b91b8026e28bfc3d3c5759c06c70c95', '[34,33,18,24,52,22,103,68,72,88,85,52,55,87,103,73,74,31,106,4,96,7,68,75,23,56,41,11,35,69,20,63,59,83,4,55,58,9,15,46,63,37,28,36,96,76,13,16,72,27,11,24,7,17,26,62,68,58,90,96,68,3,26,79,20,43,24,58,91,68,84,15,87,26,38,50,56,72,61,57,47,83,74,103,22,69,91,60,50,63,79,5,49,22,107,84,10,102,40,52]', '2026-04-04 04:01:43', NULL, '::1'),
(7, 2, '4a0c2118a23dfc4f9bec4948754694e3', '[70,36,23,36,12,57,1,48,82,53,48,73,63,83,43,13,35,107,25,18,37,81,62,104,1,14,14,5,65,85,60,39,71,14,52,58,34,47,75,1,89,70,40,18,94,14,97,77,97,94,26,77,97,31,37,58,48,73,89,32,102,20,3,41,74,62,37,100,39,46,64,95,27,69,44,57,86,105,41,44,59,106,27,47,20,26,58,5,78,31,5,17,53,97,25,106,17,71,71,6]', '2026-04-04 04:01:52', NULL, '::1'),
(8, 2, 'e72962175373b53c7adf25939fd8de83', '[44,80,31,63,80,95,74,55,15,83,73,86,63,30,13,106,87,27,85,104,73,42,102,88,63,86,68,95,36,26,106,61,17,17,55,39,98,1,55,46,93,81,73,91,41,33,16,75,62,96,10,58,90,3,49,65,95,6,36,32,68,1,19,101,3,76,84,102,80,42,93,17,20,71,59,25,9,91,32,87,74,76,48,17,7,14,42,82,77,28,50,75,3,41,22,43,105,74,52,55]', '2026-04-04 09:26:31', NULL, '::1'),
(9, 2, '00757d90e2ee7235e959325e37ad8145', '[49,13,27,93,68,34,57,11,16,9,11,80,89,29,11,101,22,73,10,91,49,9,29,29,67,67,98,70,7,58,58,82,49,81,102,52,69,71,17,31,104,91,49,106,43,57,1,10,94,40,70,86,93,88,41,17,101,44,88,18,69,43,58,86,63,62,66,52,24,54,86,29,76,71,105,51,58,73,9,26,28,20,4,27,65,73,40,33,59,30,15,82,42,61,33,16,87,65,93,3]', '2026-04-04 09:44:28', NULL, '::1'),
(12, 2, '9890b924432cc235d9b626152b35e137', '[10,17,69,58,48,3,97,63,65,18,37,59,39,52,46,61,87,11,80,46,12,103,99,88,35,48,3,12,75,46,54,81,96,18,54,44,82,77,4,35,72,37,61,20,64,41,85,26,105,55,55,82,89,101,74,15,60,89,69,64,103,37,41,63,70,66,60,94,82,27,15,84,31,74,43,98,39,85,80,18,10,93,36,89,106,22,27,96,31,20,107,65,86,97,33,15,92,24,31,7]', '2026-04-04 18:57:59', NULL, '::1'),
(13, 2, 'fd4489cd1ed30a0fd87006ad40a46d86', '[51,92,101,55,20,102,51,6,48,73,95,29,65,13,93,14,49,18,29,64,29,7,65,37,83,37,47,8,67,23,80,35,3,26,107,106,59,50,95,47,52,75,46,49,17,37,73,85,74,92,83,103,47,17,53,41,87,85,82,24,85,63,19,2,33,73,76,82,34,10,50,66,73,97,22,52,87,77,7,83,85,15,66,34,35,104,42,89,54,103,96,24,73,77,47,3,12,64,77,26]', '2026-04-04 19:04:48', NULL, '::1'),
(14, 2, '82474a54fe3a9007be2e47b952a5a80f', '[19,104,70,46,54,59,19,99,20,28,9,77,92,40,69,46,89,19,54,86,38,62,64,65,1,60,97,29,7,29,102,38,77,69,35,61,67,38,99,18,59,103,96,14,86,72,41,79,102,87,59,16,75,6,40,91,46,73,52,104,73,34,25,12,96,31,66,94,29,5,76,85,18,59,103,11,57,58,99,20,26,11,74,59,30,54,86,95,80,100,50,42,4,34,66,10,35,22,6,43]', '2026-04-04 19:04:52', NULL, '::1'),
(15, 2, 'e7f8caee9b426ff3a72af258dea399b7', '[75,16,57,80,45,36,21,105,87,55,15,2,61,41,43,98,19,92,5,84,97,26,48,32,41,57,72,97,41,102,92,88,73,31,44,68,73,63,30,82,35,107,91,75,4,99,71,28,10,70,81,16,107,45,96,23,3,34,25,2,49,49,98,26,54,49,11,62,24,1,56,80,40,84,33,76,39,96,25,13,75,96,25,33,10,59,36,84,17,51,75,83,81,45,18,61,53,14,103,75]', '2026-04-04 19:05:44', NULL, '::1'),
(16, 2, '2a11e006c6b049bd42fd323a6d3c564b', '[51,66,105,24,23,26,4,9,73,97,74,43,42,70,106,42,94,13,1,2,52,103,69,55,10,41,16,20,38,67,36,16,46,49,16,66,66,75,61,59,28,89,102,25,9,78,83,97,73,85,18,39,28,50,23,41,84,85,67,43,93,99,26,56,53,22,17,73,42,43,87,37,98,20,69,105,30,76,62,95,90,66,61,60,102,47,65,26,96,107,92,8,7,22,46,21,82,5,33,9]', '2026-04-04 19:05:59', NULL, '::1'),
(17, 2, 'a873ec68ff3bceac69bfdc77fb1bb55d', '[5,85,57,11,46,38,10,15,83,7,72,100,104,37,83,58,8,104,24,35,90,26,75,11,70,57,97,40,83,61,50,8,76,88,59,26,91,27,67,26,32,6,42,55,76,40,58,31,45,78,67,54,99,64,62,102,102,55,52,3,97,15,30,57,9,39,77,83,35,75,6,105,67,8,62,30,7,16,97,14,76,79,47,52,37,20,100,100,49,27,11,4,51,49,103,77,29,72,32,72]', '2026-04-04 19:06:09', NULL, '::1'),
(18, 2, 'cf3a9da549f1481be7bb1c4ff3dd2bb4', '[16,53,24,61,26,83,29,59,32,106,84,78,25,88,37,1,49,59,6,57,24,22,11,35,102,60,60,58,93,94,83,9,69,62,7,58,67,97,43,61,78,80,4,77,78,80,29,70,19,107,1,60,38,92,43,6,15,93,93,65,58,10,89,107,107,73,22,99,83,33,41,55,65,99,31,67,86,14,67,71,14,28,81,107,78,77,16,99,35,27,33,71,19,10,99,63,12,10,28,8]', '2026-04-04 19:06:16', NULL, '::1'),
(19, 2, 'df7c1eb15287038366e2f63e3d355e6c', '[54,66,11,52,102,107,65,16,82,62,4,56,105,88,21,84,10,46,84,106,106,44,13,93,16,102,76,86,77,85,81,67,1,7,77,67,19,37,97,48,41,53,62,4,83,26,76,106,88,27,34,99,82,22,27,1,21,45,69,27,4,69,18,62,83,59,22,86,7,76,78,19,80,98,85,6,38,86,24,11,25,96,13,87,62,44,72,32,43,103,11,45,44,77,9,81,79,27,36,52]', '2026-04-04 19:47:02', NULL, '::1'),
(20, 2, '405c035dd5ba72b77ab06c24e57f3412', '[68,1,57,32,5,67,14,69,49,1,105,5,32,49,107,7,94,88,24,13,71,18,60,13,86,96,81,82,101,78,73,35,38,61,44,52,49,103,4,67,57,50,1,45,50,100,16,52,16,59,28,41,104,103,44,32,31,28,88,12,32,55,60,53,38,102,11,6,6,82,71,1,16,76,100,35,42,67,101,107,67,97,32,10,98,58,102,71,32,99,87,86,37,77,28,5,83,27,66,59]', '2026-04-04 19:47:04', NULL, '::1'),
(21, 2, '9ef028ae59318b535fbc0962643451da', '[59,22,91,97,66,45,76,24,37,75,70,67,27,12,5,101,29,50,60,7,3,14,95,89,79,26,45,11,107,15,43,63,59,4,40,30,26,17,91,69,54,46,14,104,19,76,37,42,68,60,2,68,15,82,76,14,91,40,75,70,31,21,83,32,28,1,48,26,98,6,100,46,94,59,15,33,78,20,24,55,104,25,56,2,61,49,104,94,50,74,72,94,24,13,51,19,98,80,56,38]', '2026-04-04 19:47:30', NULL, '::1'),
(22, 2, 'c0656951a3a9c4b1399720843575ed1b', '[7,91,39,39,45,101,5,8,49,30,24,85,97,49,54,60,99,37,82,89,70,72,63,52,51,107,51,18,12,11,76,56,77,104,22,20,107,103,100,68,99,23,1,79,96,26,39,13,46,95,26,13,36,80,67,53,20,105,41,49,12,37,35,9,46,80,71,24,101,88,39,37,67,76,43,41,46,72,43,83,12,70,98,107,6,17,55,24,11,4,40,53,56,21,30,17,80,57,75,79]', '2026-04-04 19:47:58', NULL, '::1'),
(23, 2, '9cbd16b4a3a6f884e462509b2d701fae', '[42,46,29,24,82,49,106,77,74,39,46,41,47,38,83,101,66,73,8,79,92,100,93,46,55,48,56,103,13,72,11,63,84,64,98,29,107,73,65,29,4,44,45,1,30,27,60,2,52,19,107,28,70,12,4,65,57,84,90,33,95,19,80,21,59,99,11,31,22,88,5,2,4,38,40,16,76,9,67,107,42,32,34,10,76,82,13,37,6,71,95,51,32,86,93,81,58,97,105,88]', '2026-04-04 19:48:49', NULL, '::1'),
(24, 2, '402ed7cde8e271be8c17164bf60f6064', '[81,95,75,89,22,46,3,89,33,47,21,90,88,2,26,38,51,87,77,55,57,20,6,37,16,7,87,42,47,63,79,29,7,93,83,89,13,4,21,104,68,65,10,103,107,36,68,67,67,19,100,25,9,16,30,24,6,47,48,34,9,16,24,99,92,29,37,43,30,68,57,27,60,69,97,105,45,48,3,88,82,91,21,34,105,4,11,22,44,85,20,39,7,27,68,90,65,64,50,64]', '2026-04-04 19:50:46', NULL, '::1'),
(25, 2, 'ad5f58b942541190061d3cbad82c0746', '[8,98,39,94,61,72,66,49,17,71,44,59,80,14,76,83,18,47,10,61,33,8,28,10,68,93,61,88,24,103,34,14,106,104,75,3,59,20,32,79,60,107,34,13,10,2,76,96,19,11,71,42,75,43,98,94,14,34,5,54,36,61,43,67,28,54,17,98,100,3,99,106,39,23,43,3,75,39,69,85,60,7,89,31,42,62,88,52,107,44,100,53,71,32,71,87,23,75,95,19]', '2026-04-04 19:51:12', NULL, '::1'),
(26, 2, 'dbb04364a74c9969cf6570c0e6a32441', '[68,27,85,86,72,98,17,1,50,83,74,102,14,3,66,29,16,70,4,26,20,79,3,44,75,81,102,59,11,68,44,62,10,53,41,53,68,107,21,29,59,40,74,93,34,42,20,13,65,51,104,28,42,44,69,24,68,58,37,4,2,88,24,53,45,104,79,95,9,63,74,56,41,53,21,68,29,103,51,67,96,88,57,58,29,39,62,92,91,40,14,38,94,61,73,18,94,22,72,6]', '2026-04-05 03:11:22', NULL, '::1'),
(27, 2, '5e9bc9343721938406ab9e90fbf2c952', '[102,106,103,42,3,12,36,48,26,100,15,1,42,99,41,102,40,16,52,86,52,50,46,25,9,104,20,43,25,50,28,98,8,41,11,94,82,46,70,3,14,16,40,46,64,30,71,84,86,8,71,34,101,37,104,83,67,30,59,66,1,87,30,28,84,90,79,15,62,2,42,5,81,102,69,74,37,42,38,28,100,30,45,64,77,9,53,88,86,1,76,56,79,8,29,107,95,34,63,73]', '2026-04-05 03:11:39', NULL, '::1'),
(30, 2, '2216e91f082873498cd792833a88e04b', '[34,58,89,78,6,102,86,64,100,103,33,100,57,102,67,29,79,58,105,69,34,10,30,25,39,107,62,20,7,7,45,97,48,8,13,39,68,71,61,32,81,3,2,15,39,82,96,81,64,23,52,80,57,51,49,19,70,100,47,24,39,43,77,75,49,50,57,22,19,29,56,32,20,19,79,23,3,94,12,84,53,44,90,29,103,3,78,80,47,1,86,83,63,46,34,53,96,102,9,83]', '2026-04-06 00:54:06', NULL, '::1'),
(31, 2, 'ba17ce2e30bf20128da5d7377ed8c14a', '[29,86,83,50,85,94,66,81,83,81,73,107,24,61,49,45,83,33,14,40,104,78,19,11,75,93,23,8,21,100,18,43,23,41,102,100,35,6,44,20,46,87,90,53,32,4,32,77,76,54,18,20,3,23,64,29,101,87,68,40,72,43,6,22,60,57,90,73,29,88,10,33,36,97,77,62,3,43,17,27,103,100,97,58,6,30,17,52,63,51,64,62,2,66,17,77,19,4,14,105]', '2026-04-06 03:10:50', NULL, '::1'),
(32, 2, 'c8fe5cba5f18b824b54bd0e9c06b0788', '[43,84,3,44,47,77,31,88,103,52,43,39,90,64,51,107,95,98,66,15,100,28,81,58,91,50,26,35,3,6,10,106,13,50,61,99,50,62,35,55,81,44,20,93,21,75,77,27,10,45,13,48,4,23,37,60,60,81,36,23,19,17,76,91,90,86,98,77,63,82,90,82,53,94,68,28,7,50,86,36,103,96,12,54,3,34,79,26,85,72,96,58,1,28,71,97,89,5,77,58]', '2026-04-06 03:12:03', NULL, '::1'),
(33, 8, 'ef697d8b3597af469b2b094c8cea190f', '[77,62,26,39,51,103,38,57,31,24,9,70,13,98,106,76,36,17,37,7,91,95,49,92,80,85,34,29,82,10,60,50,87,74,45,52,28,71,61,48,90,64,8,73,96,79,32,104,12,97,58,23,16,93,27,18,11,14,2,63,68,41,33,100,42,25,69,55,5,54,101,67,47,94,20,89,102,40,6,86,84,88,19,56,66,75,15,53,59,78,3,1,65,81,43,105,21,4,72,99]', '2026-04-06 03:14:47', NULL, '::1'),
(34, 2, '914a63d697c6e0f46be8df59c3dfe193', '[96,25,18,88,36,87,59,96,88,68,21,53,79,67,77,58,93,32,27,87,80,32,62,79,8,75,50,3,83,22,80,1,99,8,33,64,96,95,75,6,77,19,81,69,65,44,86,101,8,36,107,59,98,84,48,44,44,72,72,42,1,80,28,5,39,58,15,9,39,99,47,10,53,8,67,69,56,48,28,24,12,35,50,74,60,94,31,64,49,47,43,65,6,85,61,103,50,42,105,15]', '2026-04-06 06:44:22', NULL, '::1'),
(35, 2, '9b0e3254c39ed5367c609fa0cc95d673', '[91,28,53,32,86,79,86,35,39,15,13,89,56,24,15,44,74,4,70,82,54,60,14,40,89,73,70,48,76,22,68,46,9,105,3,40,14,71,57,107,7,96,41,88,101,59,7,72,91,66,33,12,90,15,50,44,91,6,63,3,56,82,69,86,42,15,84,65,20,86,106,48,103,23,24,9,81,92,51,83,68,101,23,74,21,41,40,8,78,42,43,81,32,56,71,35,73,95,12,103]', '2026-04-06 06:44:27', NULL, '::1'),
(36, 2, '6329c802db6ab100dbffee1623df39e4', '[52,68,69,55,107,79,107,14,79,85,80,91,106,35,78,62,25,82,93,99,40,106,17,94,93,26,37,41,50,33,83,81,43,19,48,94,46,15,101,31,67,40,70,105,33,12,90,60,23,102,36,79,57,84,29,85,92,79,14,13,67,3,58,27,6,67,61,44,57,21,6,39,34,21,69,10,24,41,94,15,39,20,69,96,58,47,1,90,51,46,16,26,65,24,58,80,3,76,78,101]', '2026-04-06 06:45:59', NULL, '::1'),
(37, 2, 'a9ebfbf6aaac81498b2d1af94fd7cb4b', '[63,43,4,72,65,64,38,105,12,22,66,70,73,78,101,15,39,67,89,105,3,62,28,67,62,32,38,2,43,16,50,47,71,57,18,63,40,33,37,76,52,55,56,45,13,106,35,55,50,27,104,92,106,2,59,98,59,80,2,92,98,98,8,101,17,68,94,40,4,53,17,89,102,91,99,62,8,12,27,5,81,98,48,41,22,94,89,95,66,42,46,5,83,52,51,107,97,24,40,51]', '2026-04-06 06:46:19', NULL, '::1'),
(38, 2, '6ca4ec0dac1e781175d3e0cbd7e34e7a', '[76,93,38,21,99,8,27,16,76,77,2,57,18,13,12,78,49,50,23,55,8,57,39,101,86,97,77,57,25,89,83,13,59,50,107,55,69,39,6,105,29,34,100,46,67,23,103,92,70,77,106,11,55,74,34,14,43,100,20,77,65,7,97,81,13,31,70,54,69,55,88,39,90,87,7,65,3,71,28,105,18,89,51,27,25,13,79,51,28,85,81,20,70,75,92,4,50,33,96,81]', '2026-04-06 06:46:47', NULL, '::1'),
(39, 2, '8ee7c46b4c2bd90643cc0c692677dc07', '[72,107,28,34,73,90,91,44,87,57,80,51,80,104,57,62,33,1,59,97,88,18,74,88,18,6,72,97,37,52,87,6,104,45,67,22,5,48,81,30,24,25,9,94,100,107,39,63,6,22,57,57,45,88,85,27,12,28,44,92,13,107,22,90,100,70,84,80,36,2,38,32,16,8,8,74,11,77,54,34,12,23,86,4,65,94,8,35,55,80,52,64,21,18,51,72,68,22,95,59]', '2026-04-06 06:46:57', NULL, '::1'),
(40, 2, '75dc6879bc5a2450823cdb8739b1928b', '[82,7,8,46,88,61,56,2,101,68,63,104,52,36,46,67,7,100,39,57,37,24,104,66,79,37,96,32,61,8,23,66,76,41,52,16,105,51,35,94,94,38,85,87,102,13,12,69,55,56,49,51,99,91,43,50,85,62,89,42,27,24,19,26,72,38,8,88,64,107,80,81,67,105,17,50,77,16,83,84,34,106,5,93,25,56,37,9,34,93,45,72,70,51,78,11,67,5,29,76]', '2026-04-07 13:34:02', NULL, '::1'),
(41, 2, 'f013730206373755d08c34afcdf84939', '[48,17,16,36,31,101,19,76,16,56,10,39,51,12,23,10,107,93,73,94,100,106,63,7,80,24,35,103,80,62,88,64,100,98,37,86,88,36,86,104,15,91,94,4,63,72,13,41,30,43,22,71,70,18,55,61,48,28,83,34,30,61,104,91,97,28,11,78,6,9,68,66,6,53,45,36,44,72,77,56,54,95,69,86,54,86,69,89,33,11,74,100,62,58,45,63,39,102,47,58]', '2026-04-07 13:57:17', NULL, '::1'),
(42, 2, '44a58bd04e1b8c6fa22d1bf758f1d81f', '[104,41,16,71,63,79,5,31,83,33,36,105,101,48,15,53,16,52,51,31,80,33,66,6,106,40,51,2,18,42,2,54,30,73,77,52,70,90,56,30,30,68,38,16,60,66,52,104,99,72,42,53,10,96,102,79,98,73,105,23,4,54,15,97,6,102,67,29,25,25,12,51,44,65,60,80,20,45,98,67,84,16,2,6,9,99,50,27,74,94,103,89,33,8,83,89,17,82,45,40]', '2026-04-07 13:59:27', NULL, '::1'),
(43, 2, 'ec16c3b5dd3578462e79eb1bb0ad1a01', '[9,61,61,25,83,21,2,17,84,18,1,93,39,102,12,5,3,102,48,45,26,86,27,106,27,40,58,78,4,28,70,46,58,68,91,83,82,32,62,69,104,35,8,41,26,10,84,11,3,10,55,21,14,95,102,52,68,21,91,61,74,38,28,46,28,101,83,30,81,79,104,55,64,8,65,43,64,70,75,76,25,93,46,5,66,105,41,25,99,76,89,105,19,70,3,60,39,20,29,11]', '2026-04-07 13:59:39', NULL, '::1');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` enum('login','profile_update','password_change','assessment_complete','data_clear','other') NOT NULL,
  `action_description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action_type`, `action_description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login', 'User logged in successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2026-04-02 09:45:18'),
(2, 1, 'profile_update', 'Updated profile: name to \'System Administrator Updated\', email to \'admin.updated@cybershield.ph\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2026-04-02 07:45:18'),
(3, 1, 'assessment_complete', 'Completed security assessment with score 95%', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2026-04-02 05:45:18'),
(4, 1, 'password_change', 'Password changed successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2026-04-02 03:45:18'),
(5, 2, 'login', 'User logged in successfully', '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', '2026-04-02 01:45:18'),
(6, 2, 'profile_update', 'Updated profile: store name to \'Demo Store Updated\'', '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', '2026-04-01 23:45:18'),
(7, 2, 'assessment_complete', 'Completed security assessment with score 82%', '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', '2026-04-01 21:45:18'),
(8, 2, 'login', 'User logged in successfully', '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', '2026-04-01 19:45:18'),
(9, 3, 'login', 'User logged in successfully', '192.168.1.102', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36', '2026-04-01 17:45:18'),
(10, 3, 'assessment_complete', 'Completed security assessment with score 65%', '192.168.1.102', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36', '2026-04-01 15:45:18'),
(11, 2, 'profile_update', 'Updated profile: name to \'tangina\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 15:48:21'),
(12, 2, 'profile_update', 'Updated profile: name to \'Jean Marc Aguilar\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 15:48:40'),
(13, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:03:58'),
(14, 2, 'profile_update', 'Updated profile: name to \'tangina\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:44:42'),
(15, 2, 'profile_update', 'Updated profile: name to \'Jean Marc Aguilar\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:44:51'),
(16, 2, 'profile_update', 'Updated profile: name to \'tangina\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:48:52'),
(17, 2, 'profile_update', 'Updated profile: ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:48:52'),
(18, 2, 'profile_update', 'Updated profile: name to \'Jean Marc Aguilar\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:49:09'),
(19, 2, 'password_change', 'Password changed successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:50:35'),
(20, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:51:27'),
(21, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:52:37'),
(22, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:53:09'),
(23, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:53:09'),
(24, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 01:53:10'),
(25, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:11:58'),
(26, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:27:46'),
(27, 1, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 11:57:37'),
(28, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-03 13:34:30'),
(29, 1, '', 'Unknown', 'Unknown', NULL, '2026-04-03 17:20:07'),
(30, 2, '', 'Unknown', 'Unknown', NULL, '2026-04-03 17:20:07'),
(31, 3, '', 'Unknown', 'Unknown', NULL, '2026-04-03 17:20:07'),
(33, 1, '', 'Unknown', 'Unknown', NULL, '2026-04-03 17:20:45'),
(34, 2, '', 'Unknown', 'Unknown', NULL, '2026-04-03 17:20:45'),
(35, 3, '', 'Unknown', 'Unknown', NULL, '2026-04-03 17:20:45'),
(37, 1, '', 'Unknown', 'Unknown', NULL, '2026-04-03 17:21:07'),
(38, 2, '', 'Unknown', 'Unknown', NULL, '2026-04-03 17:21:07'),
(39, 3, '', 'Unknown', 'Unknown', NULL, '2026-04-03 17:21:07'),
(41, 1, 'other', 'Admin viewed activity log', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:29:39'),
(42, 1, 'other', 'Admin viewed activity log', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:31:12'),
(43, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:05'),
(44, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:11'),
(45, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:13'),
(46, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:18'),
(47, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:18'),
(48, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:19'),
(49, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:19'),
(50, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:19'),
(51, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:19'),
(52, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:20'),
(53, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:20'),
(54, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:20'),
(55, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:20'),
(56, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:21'),
(57, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:30'),
(58, 1, 'data_clear', 'Admin cleared activity log (30+ days old)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:30'),
(59, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:31'),
(60, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:31'),
(61, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:32:32'),
(62, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:33:26'),
(63, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:33:37'),
(64, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:33:44'),
(65, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:33:45'),
(66, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:33:45'),
(67, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:33:46'),
(68, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:33:46'),
(69, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:34:28'),
(70, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:34:29'),
(71, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:34:29'),
(72, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:34:29'),
(73, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:34:30'),
(74, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-03 17:35:02'),
(75, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:35:03'),
(76, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:35:55'),
(77, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:36:18'),
(78, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-03 17:36:45'),
(79, 1, 'other', 'Viewed Activity Log page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:36:49'),
(80, 1, 'other', 'Viewed Activity Log page', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:37:34'),
(81, 2, 'login', 'User logged in successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-03 17:39:33'),
(82, 1, 'profile_update', 'Updated profile: name to \'admin\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:35:13'),
(83, 1, 'profile_update', 'Updated profile: name to \'wow\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:35:25'),
(84, 1, 'profile_update', 'Updated profile: email to \'christianbacay042504@gmail.com\', store name to \'wow\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:41:12'),
(85, 1, 'password_change', 'Password changed successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:49:36'),
(86, 1, 'login', 'User logged in successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 19:50:09'),
(87, 1, 'login', 'User logged in successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 04:44:43'),
(88, 1, 'login', 'User logged in successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 04:44:43'),
(89, 1, 'login', 'User logged in successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 04:44:44'),
(90, 1, 'profile_update', 'Updated profile: name to \'tangines\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 04:45:34'),
(91, 1, 'profile_update', 'Updated profile: name to \'tanginamo\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 04:52:33'),
(92, 1, 'profile_update', 'Updated profile: name to \'inangnyan\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 04:56:11'),
(93, 1, 'login', 'User logged in successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 09:09:19'),
(94, 2, 'login', 'User logged in successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 09:26:24'),
(95, 1, 'login', 'User logged in successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 09:41:56'),
(96, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 12:59:55'),
(97, 1, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 13:05:47'),
(98, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:19:15'),
(99, 2, 'profile_update', 'Updated profile: name to \'tangina\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:40:56'),
(100, 1, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:53:42'),
(101, 1, 'profile_update', 'Updated profile: name to \'tangina\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 17:20:46'),
(102, 1, 'profile_update', 'Updated profile: name to \'inamonamanya\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 17:21:18'),
(103, 1, 'password_change', 'Password changed successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 17:40:02'),
(104, 1, 'password_change', 'Password changed successfully', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 17:45:28'),
(105, 1, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 17:45:54'),
(106, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 18:56:51'),
(107, 1, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 18:58:48'),
(108, 1, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 19:46:17'),
(109, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 19:46:58'),
(110, 1, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 02:59:01'),
(111, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 03:11:05'),
(112, 1, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 03:35:57'),
(113, 1, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 04:06:43'),
(114, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 08:48:54'),
(115, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 00:53:58'),
(116, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 03:10:36'),
(117, 2, 'profile_update', 'Updated profile: name to \'Jean Marc Aguilar\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 03:11:25'),
(118, 1, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 06:41:50'),
(119, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 06:44:15'),
(120, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 13:33:35'),
(121, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 13:40:34'),
(122, 1, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 14:13:15'),
(123, 1, 'profile_update', 'Updated profile: name to \'Jean Marc Aguilar\'', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 14:28:36'),
(124, 2, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 15:31:57'),
(125, 1, 'login', 'User logged in successfully with 2FA', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-07 15:32:42');

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `icon` varchar(50) NOT NULL,
  `color` varchar(7) DEFAULT '#3B8BFF',
  `category` enum('assessment','consistency','improvement','milestone','special') NOT NULL,
  `requirement_type` enum('score','count','rank','streak','special') NOT NULL,
  `requirement_value` int(11) NOT NULL,
  `points` int(11) DEFAULT 10,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`id`, `name`, `description`, `icon`, `color`, `category`, `requirement_type`, `requirement_value`, `points`, `is_active`, `created_at`) VALUES
(1, 'Security Elite', 'Achieve a perfect score (100%)', '🏆', '#f5c518', 'assessment', 'score', 100, 50, 1, '2026-04-02 15:36:15'),
(2, 'Security Master', 'Score 90% or higher', '🥇', '#f5c518', 'assessment', 'score', 90, 40, 1, '2026-04-02 15:36:15'),
(3, 'Security Expert', 'Score 80% or higher', '🥈', '#c0c0c0', 'assessment', 'score', 80, 30, 1, '2026-04-02 15:36:15'),
(4, 'Consistent Learner', 'Complete 5 assessments', '📚', '#4090ff', 'consistency', 'count', 5, 20, 1, '2026-04-02 15:36:15'),
(5, 'First Steps', 'Complete your first assessment', '🎯', '#3b8bff', 'milestone', 'count', 1, 10, 1, '2026-04-02 15:36:15'),
(6, 'Quick Learner', 'Improve score by 20%', '📈', '#10d982', 'improvement', 'special', 20, 30, 1, '2026-04-02 15:36:15');

-- --------------------------------------------------------

--
-- Stand-in structure for view `biased_questions_view`
-- (See below for the actual view)
--
CREATE TABLE `biased_questions_view` (
`id` int(11)
,`category` enum('password','phishing','device','network','social_engineering','data_handling')
,`difficulty` enum('easy','medium','hard')
,`question_text` text
,`bias_score` decimal(5,2)
,`times_used` int(11)
,`correct_rate` decimal(5,2)
,`bias_level` varchar(24)
);

-- --------------------------------------------------------

--
-- Table structure for table `otp_codes`
--

CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `purpose` enum('login','signup','password_reset','email_verification') DEFAULT 'login',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL,
  `is_used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_codes`
--

INSERT INTO `otp_codes` (`id`, `user_id`, `otp_code`, `purpose`, `expires_at`, `created_at`, `used_at`, `is_used`) VALUES
(19, 2, '759995', 'login', '2026-04-04 14:50:44', '2026-04-04 06:45:44', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `otp_logs`
--

CREATE TABLE `otp_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `purpose` enum('login','signup','password_reset','email_verification') NOT NULL,
  `email_sent_to` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `category` varchar(100) DEFAULT 'Other',
  `status` enum('active','inactive') DEFAULT 'active',
  `image_url` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `user_id`, `name`, `description`, `price`, `stock`, `category`, `status`, `image_url`, `created_at`, `updated_at`) VALUES
(1, 2, 'Wireless Noise-Cancelling Headphones', 'Premium audio with 30-hour battery life', 3499.00, 25, 'Electronics', 'active', NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(2, 2, 'Mechanical Gaming Keyboard', 'RGB backlit keyboard with Cherry MX switches', 2299.00, 40, 'Electronics', 'active', NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(3, 2, 'Ergonomic Office Chair', 'Lumbar support mesh chair', 8999.00, 8, 'Furniture', 'active', NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15');

-- --------------------------------------------------------

--
-- Table structure for table `question_bank`
--

CREATE TABLE `question_bank` (
  `id` int(11) NOT NULL,
  `category` enum('password','phishing','device','network','social_engineering','data_handling') NOT NULL,
  `difficulty` enum('easy','medium','hard') NOT NULL,
  `question_text` text NOT NULL,
  `correct_answer` varchar(255) NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`options`)),
  `explanation` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `bias_score` decimal(5,2) DEFAULT 0.00 COMMENT 'Score indicating question bias (0=unbiased, 100=highly biased)',
  `times_used` int(11) DEFAULT 0 COMMENT 'Number of times this question has been used',
  `correct_rate` decimal(5,2) DEFAULT NULL COMMENT 'Historical correct rate to detect biased questions',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `question_bank`
--

INSERT INTO `question_bank` (`id`, `category`, `difficulty`, `question_text`, `correct_answer`, `options`, `explanation`, `is_active`, `bias_score`, `times_used`, `correct_rate`, `created_at`, `updated_at`) VALUES
(1, 'password', 'easy', 'Scenario: You receive a notification that your account was accessed from a new device. What do you do first?', 'Secure the account by changing the password and enabling MFA, then review recent activity', '[\"Ignore it if nothing seems wrong\", \"Reply to the notification email to ask for details\", \"Secure the account by changing the password and enabling MFA, then review recent activity\", \"Click the notification link and sign in to check\"]', 'Treat unexpected login alerts as potential compromise: secure the account via trusted navigation (not email links), enable MFA, and review activity.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(2, 'password', 'easy', 'Scenario: A website tells you your password is weak and suggests a simple pattern. What should you do?', 'Use a long passphrase or password manager instead of the suggested pattern', '[\"Accept the suggested pattern\", \"Use a long passphrase or password manager instead of the suggested pattern\", \"Add a number to the end\", \"Reuse a strong password from another site\"]', 'Avoid predictable patterns. Use unique, long passphrases or a password manager to generate/store strong passwords.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(3, 'password', 'medium', 'Scenario: Your password was reused on another site that was breached. What should you do?', 'Change the password everywhere it was reused and enable MFA', '[\"Wait to see if your account is affected\", \"Change the password everywhere it was reused and enable MFA\", \"Use the same password but add a symbol\", \"Disable login alerts to reduce notifications\"]', 'Reused credentials are commonly exploited through credential stuffing. Update unique passwords and enable MFA.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(4, 'password', 'medium', 'Scenario: You need to share a password with a coworker for an emergency task. What is the safest way?', 'Use a secure password sharing tool or encrypted message; change it afterward', '[\"Send it in plain text via chat\", \"Use a secure password sharing tool or encrypted message; change it afterward\", \"Write it on a sticky note\", \"Tell them verbally over the phone\"]', 'Never send passwords in plain text. Use encrypted sharing methods and rotate the password after use.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(5, 'password', 'hard', 'Scenario: You suspect a keylogger on your work computer. What should you do first?', 'Report to IT immediately and use a different clean device to change critical passwords', '[\"Restart the computer\", \"Report to IT immediately and use a different clean device to change critical passwords\", \"Install antivirus yourself\", \"Continue working and monitor accounts\"]', 'Keyloggers can capture credentials. Report immediately and change passwords from a known-clean device.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(6, 'password', 'hard', 'Scenario: You must log in to a public computer. What is the safest approach?', 'Use a password manager on your phone instead of typing; avoid saving any credentials', '[\"Type the password quickly\", \"Use a password manager on your phone instead of typing; avoid saving any credentials\", \"Use incognito mode\", \"Log in and clear history afterward\"]', 'Avoid typing passwords on public devices. Use a password manager or your phone to autofill securely.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(7, 'password', 'medium', 'Scenario: A mobile app asks for your device password to \"enhance security\". What should you do?', 'Deny the request and research the app; legitimate apps rarely need device passwords', '[\"Allow it to enhance security\", \"Deny the request and research the app; legitimate apps rarely need device passwords\", \"Restart the phone\", \"Uninstall immediately\"]', 'Apps asking for device/system passwords are suspicious. Deny and verify legitimacy before proceeding.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(8, 'password', 'easy', 'Scenario: You receive an email claiming your account will be locked unless you \"verify your password\" now. What do you do?', 'Do not click links; go directly to the official site/app to check your account status', '[\"Click the link to verify quickly\", \"Do not click links; go directly to the official site/app to check your account status\", \"Reply with your password\", \"Forward to IT\"]', 'Urgent password verification requests are phishing. Always navigate to the official site yourself.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(9, 'password', 'medium', 'Scenario: Your browser offers to save a new password. Should you accept?', 'Only if you trust the device and use a master password/encryption; otherwise use a password manager', '[\"Always accept\", \"Only if you trust the device and use a master password/encryption; otherwise use a password manager\", \"Never accept\", \"Write it down instead\"]', 'Browser password managers are convenient but should be encrypted with a master password or replaced by a dedicated password manager.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(10, 'password', 'hard', 'Scenario: You discover your password manager was compromised. What is the correct recovery order?', 'Change master password first, then rotate all critical passwords from a clean device', '[\"Rotate passwords first, then change master password\", \"Change master password first, then rotate all critical passwords from a clean device\", \"Delete account and start over\", \"Enable 2FA and keep same master\"]', 'Secure the master password first to prevent further compromise, then rotate individual passwords.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(11, 'password', 'medium', 'Scenario: A shared spreadsheet requires login credentials to access. How should you store them?', 'Use a password manager or encrypted vault; never store in the sheet', '[\"Store in the spreadsheet as hidden cells\", \"Use a password manager or encrypted vault; never store in the sheet\", \"Email credentials to team\", \"Print and keep in drawer\"]', 'Never store credentials in documents. Use encrypted password managers or vaults.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(12, 'password', 'easy', 'Scenario: You forgot your password and the recovery hint is obvious to others. What should you do?', 'Skip the hint and use account recovery; change the hint to something non-obvious', '[\"Use the obvious hint\", \"Skip the hint and use account recovery; change the hint to something non-obvious\", \"Ask a coworker for help\", \"Create a new account\"]', 'Obvious hints weaken security. Use secure recovery methods and update hints to private information.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(13, 'password', 'medium', 'Scenario: A colleague asks for your password to \"help troubleshoot\" your account. What do you do?', 'Refuse and offer to share your screen instead; never share passwords', '[\"Share it to get help faster\", \"Refuse and offer to share your screen instead; never share passwords\", \"Ask for their password in return\", \"Change it temporarily then share\"]', 'Never share passwords. Offer screen sharing or controlled access instead.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(14, 'password', 'hard', 'Scenario: You need to create a password policy for your team. What should you prioritize?', 'Length and uniqueness over complexity; encourage password managers', '[\"Require special characters and numbers\", \"Length and uniqueness over complexity; encourage password managers\", \"Frequent mandatory changes\", \"Disallow password managers\"]', 'Long, unique passwords with managers are more effective than frequent changes or complexity rules.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(15, 'password', 'medium', 'Scenario: Your phone shows a \"trusted device\" prompt after login. Should you enable it?', 'Only on personal, secured devices; avoid on public/shared devices', '[\"Always enable for convenience\", \"Only on personal, secured devices; avoid on public/shared devices\", \"Never enable\", \"Enable and write down the code\"]', 'Trusted device features improve convenience but should only be used on secure, personal devices.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(16, 'password', 'easy', 'Scenario: You receive a password reset you didn\'t request. What do you do?', 'Secure your account immediately and check for other unauthorized activity', '[\"Ignore it\", \"Secure your account immediately and check for other unauthorized activity\", \"Click the link to see who did it\", \"Contact support only\"]', 'Unexpected password resets may indicate attempted takeover. Secure the account and review activity.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(17, 'password', 'hard', 'Scenario: You must use a third-party service that requires your main account password. What is the safest approach?', 'Create a unique, strong password for that service; never reuse your main password', '[\"Use your main password for consistency\", \"Create a unique, strong password for that service; never reuse your main password\", \"Use a variation of your main password\", \"Decline to use the service\"]', 'Never reuse primary passwords. Create unique credentials for each service.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(18, 'password', 'medium', 'Scenario: A website offers passwordless login options. Which is most secure?', 'Use hardware security keys (YubiKey) or biometrics if available', '[\"SMS codes\", \"Email links\", \"Use hardware security keys (YubiKey) or biometrics if available\", \"Push notifications\"]', 'Hardware keys and device-based biometrics are more secure than SMS/email for passwordless auth.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(19, 'password', 'easy', 'Scenario: Your child asks for your Netflix password. What should you do?', 'Create a separate profile or use guest features; don\'t share your main password', '[\"Share your main password\", \"Create a separate profile or use guest features; don\'t share your main password\", \"Tell them to guess\", \"Change it after they use it\"]', 'Use profiles/guest access instead of sharing passwords, especially with family.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(20, 'password', 'hard', 'Scenario: You\'re leaving a job. What should you do with your work passwords?', 'Transfer knowledge and change all shared passwords; do not keep them', '[\"Save them in a personal file\", \"Transfer knowledge and change all shared passwords; do not keep them\", \"Delete them from your memory\", \"Share with your replacement\"]', 'Never retain work passwords after leaving. Ensure proper handoff and change all shared credentials.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(21, 'phishing', 'easy', 'Scenario: A message says your account will be locked in 30 minutes unless you verify now. What should you do?', 'Do not click links; go to the official site/app directly or contact support using trusted info', '[\"Click the link quickly to avoid lockout\", \"Forward the message to coworkers to warn them\", \"Do not click links; go to the official site/app directly or contact support using trusted info\", \"Reply asking the sender to prove it is real\"]', 'Urgency is a common phishing tactic. Verify through official channels you access yourself, not through the message.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(22, 'phishing', 'easy', 'Scenario: You receive an unexpected package delivery email with a tracking link. What should you do?', 'Verify on the official carrier site using the tracking number; avoid clicking email links', '[\"Click the tracking link immediately\", \"Verify on the official carrier site using the tracking number; avoid clicking email links\", \"Reply to confirm delivery\", \"Ignore it\"]', 'Phishing often uses fake delivery notifications. Always verify on the official carrier website.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(23, 'phishing', 'medium', 'Scenario: Your boss texts you asking for gift card purchases urgently. What is the correct action?', 'Confirm via a different channel (call/video) before taking any action', '[\"Buy the cards immediately\", \"Confirm via a different channel (call/video) before taking any action\", \"Ask for an email confirmation\", \"Buy one card as a test\"]', 'Executive impersonation scams rely on urgency. Always verify through a separate, trusted channel.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(24, 'phishing', 'medium', 'Scenario: A social media message says your account has violated terms and to click to appeal. What do you do?', 'Go directly to the platform\'s settings to check for violations; avoid clicking the message link', '[\"Click the appeal link immediately\", \"Go directly to the platform\'s settings to check for violations; avoid clicking the message link\", \"Reply to dispute\", \"Share the message to warn others\"]', 'Account violation alerts are common phishing. Navigate to the platform yourself to verify.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(25, 'phishing', 'hard', 'Scenario: You receive a PDF invoice via email that asks you to enable macros to view. What should you do?', 'Do not enable macros; verify the invoice through a known contact or portal', '[\"Enable macros to view the invoice\", \"Do not enable macros; verify the invoice through a known contact or portal\", \"Forward to IT for review\", \"Delete it\"]', 'Macro-enabled documents are common malware vectors. Never enable macros from unsolicited emails.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(26, 'phishing', 'hard', 'Scenario: A banking app popup says your session has expired and to re-enter credentials. What do you do?', 'Close the app and reopen it manually; never enter credentials in popups', '[\"Enter credentials quickly\", \"Close the app and reopen it manually; never enter credentials in popups\", \"Take a screenshot and report\", \"Call the bank\"]', 'Fake session expiry popups steal credentials. Always restart the app manually.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(27, 'phishing', 'medium', 'Scenario: You get a QR code via email claiming it leads to a company vaccine sign-up. What should you do?', 'Scan only from official sources; ignore unsolicited QR codes in emails', '[\"Scan immediately to secure your spot\", \"Scan only from official sources; ignore unsolicited QR codes in emails\", \"Forward to colleagues\", \"Print and scan later\"]', 'QR codes in emails can redirect to malicious sites. Use only official, trusted sources.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(28, 'phishing', 'easy', 'Scenario: A coworker forwards an email chain asking you to click a link to \"verify your email\". What do you do?', 'Check the original sender and verify independently; do not trust forwarded chains', '[\"Click to verify immediately\", \"Check the original sender and verify independently; do not trust forwarded chains\", \"Ask the coworker if it\'s safe\", \"Reply all to confirm\"]', 'Forwarded chains can hide phishing. Verify the original request independently.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(29, 'phishing', 'medium', 'Scenario: A website displays a security warning saying your connection is not private. What should you do?', 'Do not proceed; check the URL manually or use the official site', '[\"Click continue to the site\", \"Do not proceed; check the URL manually or use the official site\", \"Take a screenshot and report\", \"Try a different browser\"]', 'Security warnings often indicate phishing or malicious sites. Do not proceed.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(30, 'phishing', 'hard', 'Scenario: You receive a voice message with instructions to call a number about \"suspicious activity\". What should you do?', 'Call the official number from the company\'s website; not the number in the message', '[\"Call the number provided immediately\", \"Call the official number from the company\'s website; not the number in the message\", \"Reply to the message\", \"Ignore it\"]', 'Vishing uses fake phone numbers. Always use official contact information.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(31, 'phishing', 'medium', 'Scenario: A social media quiz asks for your mother\'s maiden name to \"reveal your celebrity match\". What do you do?', 'Skip the quiz; never share security answers in fun apps', '[\"Answer to see results\", \"Skip the quiz; never share security answers in fun apps\", \"Use fake information\", \"Share and let friends answer\"]', 'Quizzes often harvest security question answers. Avoid sharing personal data.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(32, 'phishing', 'easy', 'Scenario: You get an email saying you won a prize but must pay shipping. What should you do?', 'Decline; legitimate prizes don\'t require payment', '[\"Pay shipping to claim prize\", \"Decline; legitimate prizes don\'t require payment\", \"Research the company first\", \"Negotiate the shipping fee\"]', 'Prize scams require payment. Real winnings don\'t ask you to pay.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(33, 'phishing', 'hard', 'Scenario: A login page looks slightly different from usual but asks for credentials. What do you do?', 'Check the URL carefully; if unsure, navigate to the site manually instead', '[\"Enter credentials quickly\", \"Check the URL carefully; if unsure, navigate to the site manually instead\", \"Take a screenshot\", \"Ask IT to check\"]', 'Look-alike domains are common. Verify the URL or navigate manually.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(34, 'phishing', 'medium', 'Scenario: You receive a calendar invite from an unknown sender with a link to \"join\". What should you do?', 'Decline the invite; verify the sender before accepting any links', '[\"Accept and click to join\", \"Decline the invite; verify the sender before accepting any links\", \"Forward to IT\", \"Accept but ignore the link\"]', 'Calendar invites can contain phishing links. Verify the sender before accepting.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(35, 'phishing', 'easy', 'Scenario: A popup on a website says you\'ve won and to enter your email to claim. What do you do?', 'Close the popup; never enter info in unexpected popups', '[\"Enter email to claim\", \"Close the popup; never enter info in unexpected popups\", \"Take a screenshot\", \"Minimize and come back later\"]', 'Popups claiming prizes are phishing. Do not enter information.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(36, 'phishing', 'hard', 'Scenario: A colleague shares a document that asks you to enable content for \"security verification\". What should you do?', 'Contact the colleague via another channel to verify; do not enable content', '[\"Enable to proceed\", \"Contact the colleague via another channel to verify; do not enable content\", \"Forward to IT\", \"Delete it\"]', 'Document-based phishing asks you to enable content. Verify before enabling.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(37, 'phishing', 'medium', 'Scenario: You get a text with a link saying a package is delayed. What should you do?', 'Check the tracking number on the official carrier site; avoid clicking the link', '[\"Click the link to reschedule\", \"Check the tracking number on the official carrier site; avoid clicking the link\", \"Reply STOP\", \"Ignore\"]', 'Smishing uses fake delivery links. Verify on the official site.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(38, 'phishing', 'easy', 'Scenario: A website asks you to upload a photo of your ID for \"verification\". What should you do?', 'Only upload ID on official, trusted sites; avoid unknown sites', '[\"Upload to proceed quickly\", \"Only upload ID on official, trusted sites; avoid unknown sites\", \"Use a fake ID\", \"Ask customer support first\"]', 'ID theft scams use fake verification. Only upload on trusted platforms.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(39, 'phishing', 'hard', 'Scenario: You receive a fake security alert that looks like it\'s from your antivirus. What do you do?', 'Open your antivirus software directly; do not click the alert', '[\"Click the alert to scan\", \"Open your antivirus software directly; do not click the alert\", \"Restart the computer\", \"Uninstall the antivirus\"]', 'Fake security alerts install malware. Use your antivirus directly.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(40, 'phishing', 'medium', 'Scenario: A dating app match asks you to verify your identity via their link. What should you do?', 'Use the app\'s official verification features; avoid external links', '[\"Click the link to verify\", \"Use the app\'s official verification features; avoid external links\", \"Video chat instead\", \"Ask for their ID first\"]', 'Identity verification scams use external links. Use in-app verification.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(41, 'device', 'easy', 'Scenario: You find a USB drive in the parking lot. What should you do?', 'Turn it in to IT/security; do not plug it into your computer', '[\"Plug it in to see who it belongs to\", \"Turn it in to IT/security; do not plug it into your computer\", \"Format it before using\", \"Give it to a coworker\"]', 'Unknown USB drives can contain malware. Never plug them in.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(42, 'device', 'easy', 'Scenario: Your laptop battery is dying and you need to charge in public. What is safest?', 'Use your own charger and power bank; avoid public USB ports', '[\"Use any available USB port\", \"Use your own charger and power bank; avoid public USB ports\", \"Charge at home only\", \"Borrow a charger\"]', 'Public USB ports can be compromised. Use your own charger/power bank.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(43, 'device', 'medium', 'Scenario: Your phone asks to install an app from an unknown source. What should you do?', 'Decline; only install from official app stores', '[\"Install to try the app\", \"Decline; only install from official app stores\", \"Research the app first\", \"Ask a friend if it\'s safe\"]', 'Sideloading apps increases risk. Stick to official stores.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(44, 'device', 'medium', 'Scenario: You must use a public computer for sensitive work. What should you do?', 'Use a secure browser session and avoid saving any data; log out fully', '[\"Save work to cloud and log out\", \"Use a secure browser session and avoid saving any data; log out fully\", \"Use incognito and email yourself files\", \"Work quickly and hope for the best\"]', 'Avoid saving data on public computers. Use secure sessions and log out completely.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(45, 'device', 'hard', 'Scenario: Your work laptop is stolen. What is the first priority?', 'Report immediately to IT and enable remote wipe if available', '[\"Buy a new laptop\", \"Report immediately to IT and enable remote wipe if available\", \"Change passwords when convenient\", \"File a police report only\"]', 'Immediate reporting enables remote wipe to protect data. Notify IT right away.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(46, 'device', 'hard', 'Scenario: You notice unusual battery drain and data usage on your phone. What should you do?', 'Check for malware/spyware and remove unknown apps; consider factory reset if needed', '[\"Ignore it\", \"Check for malware/spyware and remove unknown apps; consider factory reset if needed\", \"Restart the phone\", \"Delete large files\"]', 'Unusual drain can indicate malware. Scan devices and remove suspicious apps.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(47, 'device', 'medium', 'Scenario: A software update requires restarting during work hours. What should you do?', 'Schedule the update for downtime; do not delay critical security updates', '[\"Postpone until next month\", \"Schedule the update for downtime; do not delay critical security updates\", \"Restart immediately\", \"Ask IT if you can skip\"]', 'Security updates should be applied promptly. Schedule appropriately but don\'t delay.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(48, 'device', 'easy', 'Scenario: Your smart TV asks for your Wi‑Fi password during setup. What should you do?', 'Create a guest network for IoT devices; avoid sharing main network credentials', '[\"Enter your main password\", \"Create a guest network for IoT devices; avoid sharing main network credentials\", \"Skip Wi‑Fi setup\", \"Use a simpler password\"]', 'IoT devices often have weak security. Use a separate network for them.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(49, 'device', 'medium', 'Scenario: You need to dispose of an old work phone. What is the correct method?', 'Factory reset and return to IT; do not sell or donate without clearing', '[\"Factory reset and sell\", \"Factory reset and return to IT; do not sell or donate without clearing\", \"Remove SIM and keep\", \"Give to a family member\"]', 'Work devices must be wiped and returned. Don\'t dispose without clearance.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(50, 'device', 'hard', 'Scenario: Your computer shows a blue screen with a phone number for \"tech support\". What should you do?', 'Force restart and run antivirus; do not call the number', '[\"Call the number for help\", \"Force restart and run antivirus; do not call the number\", \"Take a photo and report\", \"Unplug and wait\"]', 'Fake BSOD scams trick users into calling scammers. Restart and scan for malware.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(51, 'device', 'medium', 'Scenario: A public Wi‑Fi hotspot requires accepting a certificate. What should you do?', 'Avoid connecting; use VPN or trusted network instead', '[\"Accept to connect\", \"Avoid connecting; use VPN or trusted network instead\", \"Accept but use incognito\", \"Connect and quickly log out\"]', 'Certificate warnings on public Wi‑Fi indicate risk. Avoid or use VPN.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(52, 'device', 'easy', 'Scenario: Your tablet asks to back up to a cloud service you don\'t recognize. What should you do?', 'Decline and use only trusted backup services you set up', '[\"Accept to back up\", \"Decline and use only trusted backup services you set up\", \"Research the service first\", \"Ask IT for help\"]', 'Unknown cloud services may be malicious. Use only trusted backup providers.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(53, 'device', 'medium', 'Scenario: You need to share files with a client securely. What is the best method?', 'Use encrypted file transfer or a trusted secure share link; avoid email attachments', '[\"Email as attachments\", \"Use encrypted file transfer or a trusted secure share link; avoid email attachments\", \"Upload to public cloud and share link\", \"Print and hand deliver\"]', 'Use encrypted transfer tools for sensitive files. Email is not secure.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(54, 'device', 'easy', 'Scenario: Your smartwatch asks to sync contacts with your phone. Should you allow?', 'Only if you trust the device; limit data sharing to necessary items', '[\"Always allow\", \"Only if you trust the device; limit data sharing to necessary items\", \"Never allow\", \"Allow but delete contacts later\"]', 'Wearables can access sensitive data. Only allow on trusted devices.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(55, 'device', 'hard', 'Scenario: You suspect your webcam light is on without use. What should you do?', 'Cover or disconnect the camera; scan for malware', '[\"Ignore it\", \"Cover or disconnect the camera; scan for malware\", \"Restart the computer\", \"Uninstall webcam software\"]', 'Unexpected webcam activity may indicate spyware. Cover the camera and scan.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(56, 'device', 'medium', 'Scenario: Your router admin page is accessible from the internet. What should you do?', 'Disable remote management and change default passwords', '[\"Leave it for convenience\", \"Disable remote management and change default passwords\", \"Set a complex password only\", \"Contact ISP\"]', 'Remote router access increases risk. Disable it and use strong passwords.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(57, 'device', 'easy', 'Scenario: You receive a Bluetooth pairing request you don\'t recognize. What should you do?', 'Decline the request; verify the device before pairing', '[\"Accept to see what it is\", \"Decline the request; verify the device before pairing\", \"Ignore it\", \"Restart Bluetooth\"]', 'Unknown Bluetooth requests can be malicious. Decline and verify.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(58, 'device', 'medium', 'Scenario: Your printer asks to connect to cloud services. Should you enable?', 'Only if necessary and with strong credentials; avoid if not needed', '[\"Enable for convenience\", \"Only if necessary and with strong credentials; avoid if not needed\", \"Always disable cloud features\", \"Ask IT first\"]', 'Printers can be attack vectors. Enable cloud only if required and secure it.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(59, 'device', 'hard', 'Scenario: You need to use a coworker\'s computer temporarily. What should you do?', 'Use a guest account or incognito mode; don\'t save any credentials', '[\"Log in with your account\", \"Use a guest account or incognito mode; don\'t save any credentials\", \"Ask to borrow their password\", \"Use your phone instead\"]', 'Avoid saving credentials on shared devices. Use guest/incognito modes.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(60, 'device', 'medium', 'Scenario: Your fitness app requests location permissions always. What should you do?', 'Allow only while using the app; review privacy policy', '[\"Allow always\", \"Allow only while using the app; review privacy policy\", \"Deny and use app without location\", \"Use a different app\"]', 'Limit location access to when necessary. Avoid always-on permissions.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(61, 'device', 'easy', 'Scenario: You find a SIM card on the ground. What should you do?', 'Turn it in to lost and found; do not insert it', '[\"Insert it to see whose it is\", \"Turn it in to lost and found; do not insert it\", \"Throw it away\", \"Keep it as backup\"]', 'Unknown SIM cards can be used for fraud. Turn them in; don\'t use.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(62, 'network', 'easy', 'Scenario: You must use public Wi‑Fi to access your work account. What should you do?', 'Use a trusted VPN and avoid sensitive actions if you cannot verify the network', '[\"Turn off the firewall to improve speed\", \"Use a trusted VPN and avoid sensitive actions if you cannot verify the network\", \"Use any free VPN advertised on a pop-up\", \"Only use websites that load quickly\"]', 'Public Wi‑Fi can be intercepted. A trusted VPN reduces exposure; avoid sensitive access if you cannot secure the connection.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(63, 'network', 'easy', 'Scenario: A coffee shop Wi‑Fi requires your email to connect. What should you do?', 'Use a disposable email or decline; avoid giving real credentials', '[\"Use your work email to connect\", \"Use a disposable email or decline; avoid giving real credentials\", \"Use a fake email\", \"Connect without email if possible\"]', 'Public Wi‑Fi credential harvesting is common. Use disposable or fake emails.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(64, 'network', 'medium', 'Scenario: You receive an email asking you to update your network settings via a link. What should you do?', 'Ignore the link; update settings directly from your router or IT', '[\"Click the link to update\", \"Ignore the link; update settings directly from your router or IT\", \"Forward to IT\", \"Reply asking for confirmation\"]', 'Network setting updates should be done directly, not via email links.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(65, 'network', 'medium', 'Scenario: Your home network shows an unknown device connected. What should you do?', 'Investigate and remove the device; change Wi‑Fi password', '[\"Ignore it\", \"Investigate and remove the device; change Wi‑Fi password\", \"Leave it connected\", \"Restart the router\"]', 'Unknown devices may indicate unauthorized access. Remove them and change passwords.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(66, 'network', 'hard', 'Scenario: Your browser warns a site\'s certificate is invalid. What should you do?', 'Do not proceed; verify the site or use a different trusted site', '[\"Proceed anyway\", \"Do not proceed; verify the site or use a different trusted site\", \"Take a screenshot and report\", \"Try a different browser\"]', 'Invalid certificates indicate risk. Do not proceed.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(67, 'network', 'hard', 'Scenario: You suspect your DNS is being tampered with. What should you do?', 'Change to trusted DNS servers (e.g., 8.8.8.8, 1.1.1.1)', '[\"Ignore it\", \"Change to trusted DNS servers (e.g., 8.8.8.8, 1.1.1.1)\", \"Restart the router\", \"Contact ISP\"]', 'DNS tampering redirects traffic. Use trusted DNS providers.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(68, 'network', 'medium', 'Scenario: A website asks you to download a \"security plugin\" to view content. What should you do?', 'Decline; use a trusted browser or official plugin source', '[\"Download to view content\", \"Decline; use a trusted browser or official plugin source\", \"Scan the plugin after download\", \"Ask IT first\"]', 'Fake security plugins are malware. Only install from official sources.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(69, 'network', 'easy', 'Scenario: You need to share your Wi‑Fi with a guest. What is the safest method?', 'Create a guest network with a separate password', '[\"Share your main password\", \"Create a guest network with a separate password\", \"Let them use your phone hotspot\", \"Write the password on a sticky note\"]', 'Guest networks isolate visitors from your main network. Use them.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(70, 'network', 'medium', 'Scenario: Your router firmware is outdated. What should you do?', 'Update the firmware immediately; set auto-updates if available', '[\"Ignore it\", \"Update the firmware immediately; set auto-updates if available\", \"Buy a new router\", \"Turn off the router\"]', 'Outdated firmware has vulnerabilities. Update promptly.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(71, 'network', 'hard', 'Scenario: You receive a text claiming your bank account is frozen with a link to unlock. What should you do?', 'Call the bank using the official number; do not click the link', '[\"Click the link to unlock\", \"Call the bank using the official number; do not click the link\", \"Reply to the text\", \"Ignore it\"]', 'Smishing uses fake bank links. Use official contact numbers.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(72, 'network', 'medium', 'Scenario: A public charging port looks suspicious. What should you do?', 'Use your own charger/power bank; avoid public USB data ports', '[\"Use the port to charge quickly\", \"Use your own charger/power bank; avoid public USB data ports\", \"Use the port but turn off phone\", \"Ask others if it\'s safe\"]', 'Public USB ports can be used for data theft/juice jacking. Use your own charger.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(73, 'network', 'easy', 'Scenario: Your browser offers to save passwords for a site. Should you accept?', 'Only if it\'s your personal device with encryption; otherwise use a password manager', '[\"Always accept\", \"Only if it\'s your personal device with encryption; otherwise use a password manager\", \"Never accept\", \"Accept and email yourself the passwords\"]', 'Browser password saving is risky on shared devices. Use encrypted managers.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(74, 'network', 'hard', 'Scenario: You notice unusual outbound traffic from your computer. What should you do?', 'Disconnect from network and scan for malware; investigate the source', '[\"Ignore it\", \"Disconnect from network and scan for malware; investigate the source\", \"Restart the computer\", \"Close browser tabs\"]', 'Unusual outbound traffic can indicate malware. Disconnect and scan.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(75, 'network', 'medium', 'Scenario: A website asks for permission to show notifications. What should you do?', 'Deny unless you trust the site and need notifications', '[\"Always allow\", \"Deny unless you trust the site and need notifications\", \"Allow and block later\", \"Ignore the prompt\"]', 'Notifications can be abused. Allow only from trusted sites.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(76, 'network', 'easy', 'Scenario: You need to send sensitive files over email. What should you do?', 'Encrypt the files or use a secure file transfer service', '[\"Attach and send\", \"Encrypt the files or use a secure file transfer service\", \"Compress the files\", \"Send from personal email\"]', 'Email is not secure. Use encryption or secure transfer.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(77, 'network', 'hard', 'Scenario: Your ISP contacts you claiming your account is compromised and asks for your password. What should you do?', 'Do not provide password; contact ISP using official channels', '[\"Provide the password to fix the issue\", \"Do not provide password; contact ISP using official channels\", \"Change your password\", \"Ask for proof first\"]', 'ISPs don\'t ask for passwords. Use official contact methods.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(78, 'network', 'medium', 'Scenario: A social media site asks to link your contacts. Should you allow?', 'Decline unless necessary; limit third-party access to contacts', '[\"Allow to find friends\", \"Decline unless necessary; limit third-party access to contacts\", \"Allow but delete contacts later\", \"Use a fake account\"]', 'Contact access can be abused. Decline unless you truly need it.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(79, 'network', 'easy', 'Scenario: You receive a QR code to join Wi‑Fi instantly. What should you do?', 'Avoid scanning unknown QR codes; connect manually instead', '[\"Scan to connect quickly\", \"Avoid scanning unknown QR codes; connect manually instead\", \"Research the QR code first\", \"Ask the venue for the password\"]', 'QR codes can hide malicious network settings. Connect manually.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(80, 'network', 'medium', 'Scenario: Your browser autofills a password on a site you don\'t recognize. What should you do?', 'Do not log in; check the URL carefully and navigate to the official site', '[\"Log in to check the site\", \"Do not log in; check the URL carefully and navigate to the official site\", \"Change the password immediately\", \"Clear autofill data\"]', 'Autofill on phishing sites steals credentials. Verify sites before logging in.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(81, 'network', 'hard', 'Scenario: You need to use a coworker\'s network cable temporarily. What should you do?', 'Use your own if possible; avoid sharing network hardware', '[\"Use their cable\", \"Use your own if possible; avoid sharing network hardware\", \"Use Wi‑Fi instead\", \"Ask IT for a spare\"]', 'Shared network hardware can be compromised. Use your own equipment.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(82, 'network', 'medium', 'Scenario: A website asks you to disable your ad blocker to view content. What should you do?', 'Decline or use a trusted site; ad blockers improve security', '[\"Disable to view\", \"Decline or use a trusted site; ad blockers improve security\", \"Disable temporarily\", \"Use a different browser\"]', 'Ad blockers prevent malicious ads. Keep them enabled.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(83, 'social_engineering', 'easy', 'Scenario: Someone claiming to be IT asks you for a one-time code to \"fix your account\". What is the best response?', 'Refuse and verify the request through official IT channels', '[\"Share the code because IT needs it\", \"Refuse and verify the request through official IT channels\", \"Ask them for their password to confirm identity\", \"Send your username so they can look up your account\"]', 'Legitimate support will not ask for your password or one-time codes. Verify identity via known helpdesk contacts.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(84, 'social_engineering', 'easy', 'Scenario: A delivery person at your door asks to come inside to \"verify a package\". What should you do?', 'Ask for ID and verify with the company; do not let them inside', '[\"Let them in to verify\", \"Ask for ID and verify with the company; do not let them inside\", \"Take the package at the door\", \"Refuse the delivery\"]', 'Delivery scams can be pretext for burglary. Verify credentials and don\'t allow entry.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(85, 'social_engineering', 'medium', 'Scenario: You receive a call from \"tech support\" saying your computer has a virus and to install software. What should you do?', 'Hang up and run your own antivirus; never install software from callers', '[\"Install the software they recommend\", \"Hang up and run your own antivirus; never install software from callers\", \"Ask for a callback number\", \"Pay for the service\"]', 'Tech support scams install malware. Hang up and use your own security tools.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(86, 'social_engineering', 'medium', 'Scenario: A stranger on social media offers you a job after only a few messages. What should you do?', 'Research the company and verify through official channels; be cautious', '[\"Accept the offer immediately\", \"Research the company and verify through official channels; be cautious\", \"Ask for references\", \"Share your resume\"]', 'Job offers from strangers can be scams. Verify through official company channels.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(87, 'social_engineering', 'hard', 'Scenario: Someone at your desk asks to \"borrow your badge\" while you step away. What should you do?', 'Never share badges; escort them or use proper visitor procedures', '[\"Lend it to them\", \"Never share badges; escort them or use proper visitor procedures\", \"Ask them to wait\", \"Let security know\"]', 'Badge sharing violates security. Use proper visitor access procedures.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(88, 'social_engineering', 'hard', 'Scenario: You receive a voicemail from your \"bank\" asking to call back about fraud. What should you do?', 'Call the bank using the official number on your card; not the number in the voicemail', '[\"Call the number in the voicemail\", \"Call the bank using the official number on your card; not the number in the voicemail\", \"Reply to the voicemail\", \"Ignore it\"]', 'Vishing uses fake callback numbers. Use official contact information.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(89, 'social_engineering', 'medium', 'Scenario: A survey asks for your work email and department for a \"company prize\". What should you do?', 'Decline; legitimate internal surveys don\'t need sensitive details', '[\"Provide details to enter\", \"Decline; legitimate internal surveys don\'t need sensitive details\", \"Use a personal email\", \"Ask HR if it\'s legit\"]', 'Surveys can harvest corporate data. Decline if they ask for sensitive info.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(90, 'social_engineering', 'easy', 'Scenario: Someone at the entrance says they forgot their badge and asks you to let them in. What should you do?', 'Direct them to security or reception; do not tailgate', '[\"Let them in quickly\", \"Direct them to security or reception; do not tailgate\", \"Ask for their name first\", \"Let them in if they look familiar\"]', 'Tailgating bypasses security. Direct visitors to proper access points.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(91, 'social_engineering', 'medium', 'Scenario: A pop-up claims your computer is locked and to call a number to unlock. What should you do?', 'Force restart and run malware scans; do not call the number', '[\"Call the number to unlock\", \"Force restart and run malware scans; do not call the number\", \"Pay the fee\", \"Take a photo and report\"]', 'Ransomware pop-ups are scams. Restart and scan for malware.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(92, 'social_engineering', 'hard', 'Scenario: You receive a LinkedIn request from a CEO you don\'t know with an urgent message. What should you do?', 'Verify the identity through official channels; be skeptical of urgent requests', '[\"Respond immediately\", \"Verify the identity through official channels; be skeptical of urgent requests\", \"Accept and ignore\", \"Report as fake\"]', 'CEO impersonation scams use urgency. Verify identities through official channels.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(93, 'social_engineering', 'medium', 'Scenario: A coworker asks you to approve an unusual financial request via email. What should you do?', 'Confirm via phone or in person before approving', '[\"Approve to help them\", \"Confirm via phone or in person before approving\", \"Ask for more details via email\", \"Decline and let them handle it\"]', 'Financial requests should be verified through separate channels. Email can be spoofed.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(94, 'social_engineering', 'easy', 'Scenario: Someone at a conference offers you a free USB drive. What should you do?', 'Decline; unknown USB drives can contain malware', '[\"Accept and scan it\", \"Decline; unknown USB drives can contain malware\", \"Take it but don\'t use it\", \"Give it to IT\"]', 'Free USB drives often contain malware. Decline unknown devices.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(95, 'social_engineering', 'hard', 'Scenario: You receive a court summons via email with an attachment. What should you do?', 'Verify with the court directly; do not open attachments', '[\"Open the attachment\", \"Verify with the court directly; do not open attachments\", \"Reply to confirm\", \"Forward to legal\"]', 'Legal documents via email can be phishing. Verify with official sources.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(96, 'social_engineering', 'medium', 'Scenario: A stranger claims to be a new hire and asks for help accessing systems. What should you do?', 'Direct them to IT/onboarding; do not share your credentials', '[\"Log them in with your account\", \"Direct them to IT/onboarding; do not share your credentials\", \"Help them reset their password\", \"Ask your manager\"]', 'New hires should use official onboarding. Don\'t share credentials.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(97, 'social_engineering', 'easy', 'Scenario: You receive a text from your \"manager\" asking to buy gift cards urgently. What should you do?', 'Call your manager using their known number to verify', '[\"Buy the cards immediately\", \"Call your manager using their known number to verify\", \"Reply to confirm\", \"Ask for an email approval\"]', 'Gift card scams impersonate managers. Verify via known contact methods.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(98, 'social_engineering', 'medium', 'Scenario: Someone claims to be from partner company and needs access to your server room. What should you do?', 'Verify with the partner company and follow proper access procedures', '[\"Escort them in\", \"Verify with the partner company and follow proper access procedures\", \"Ask for ID only\", \"Let security handle it\"]', 'Verify third-party access through official channels and follow procedures.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15');
INSERT INTO `question_bank` (`id`, `category`, `difficulty`, `question_text`, `correct_answer`, `options`, `explanation`, `is_active`, `bias_score`, `times_used`, `correct_rate`, `created_at`, `updated_at`) VALUES
(99, 'social_engineering', 'hard', 'Scenario: You receive a fake security alert asking to enter credentials to \"secure your account\". What should you do?', 'Navigate to the official site yourself; do not enter credentials in the alert', '[\"Enter credentials to secure\", \"Navigate to the official site yourself; do not enter credentials in the alert\", \"Take a screenshot\", \"Ignore it\"]', 'Fake security alerts steal credentials. Navigate to sites yourself.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(100, 'social_engineering', 'medium', 'Scenario: A researcher calls asking about your company\'s security practices. What should you do?', 'Refer them to PR/legal; don\'t share internal details', '[\"Answer their questions\", \"Refer them to PR/legal; don\'t share internal details\", \"Ask for their credentials\", \"Hang up\"]', 'Unverified researchers may be social engineers. Direct them to official channels.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(101, 'social_engineering', 'easy', 'Scenario: Someone at the door claims to be from utilities and needs access inside. What should you do?', 'Ask for ID and verify with the utility company; do not allow entry without verification', '[\"Let them in\", \"Ask for ID and verify with the utility company; do not allow entry without verification\", \"Refuse entry\", \"Call security\"]', 'Utility scams gain entry to homes/businesses. Verify credentials.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(102, 'social_engineering', 'hard', 'Scenario: You receive a fake termination notice with a link to \"appeal\". What should you do?', 'Contact HR directly; do not click links in unexpected employment notices', '[\"Click to appeal\", \"Contact HR directly; do not click links in unexpected employment notices\", \"Reply to confirm\", \"Ask coworkers\"]', 'Employment scams use fear. Verify with HR directly.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(103, 'data_handling', 'easy', 'Scenario: You need to send a file with customer data to a partner. What is the safest first step?', 'Check if sharing is allowed and use an approved secure method (encrypted transfer / access controls)', '[\"Send it quickly by email attachment\", \"Upload it to a personal cloud drive for convenience\", \"Check if sharing is allowed and use an approved secure method (encrypted transfer / access controls)\", \"Remove only names; the rest is fine\"]', 'Start with policy and approved tools. Use least-privilege sharing and encryption, and minimize data shared.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(104, 'data_handling', 'easy', 'Scenario: You\'re working in a coffee shop with sensitive documents open. What should you do?', 'Use a privacy screen and lock the device when stepping away', '[\"Work quickly and pack up\", \"Use a privacy screen and lock the device when stepping away\", \"Turn your back to the crowd\", \"Minimize the screen\"]', 'Public workspaces expose data. Use privacy screens and lock devices.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(105, 'data_handling', 'medium', 'Scenario: A colleague asks for a list of all customer emails for a \"marketing campaign\". What should you do?', 'Verify the request through proper channels and minimize data shared', '[\"Send the full list\", \"Verify the request through proper channels and minimize data shared\", \"Ask your manager first\", \"Send only emails from last month\"]', 'Bulk data requests should be verified and minimized. Follow data handling policies.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(106, 'data_handling', 'medium', 'Scenario: You must dispose of printed client files. What is the correct method?', 'Use cross-cut shredding or professional destruction service', '[\"Throw in regular trash\", \"Use cross-cut shredding or professional destruction service\", \"Recycle without shredding\", \"Keep for future reference\"]', 'Sensitive documents must be shredded or professionally destroyed.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(107, 'data_handling', 'hard', 'Scenario: You find a USB drive with labeled \"client data\" in the parking lot. What should you do?', 'Turn it in to security/IT; do not attempt to access it', '[\"Plug it in to identify the owner\", \"Turn it in to security/IT; do not attempt to access it\", \"Keep it safe until someone claims it\", \"Destroy it\"]', 'Unknown media with client data must be handled securely. Turn it in; don\'t access.', 1, 0.00, 0, NULL, '2026-04-02 15:36:15', '2026-04-02 15:36:15');

-- --------------------------------------------------------

--
-- Table structure for table `question_order_analytics`
--

CREATE TABLE `question_order_analytics` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `question_id` int(11) NOT NULL,
  `position_in_assessment` int(11) NOT NULL,
  `page_number` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sent_certificates`
--

CREATE TABLE `sent_certificates` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cert_id` varchar(50) NOT NULL,
  `cert_type` varchar(50) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `rank` char(1) DEFAULT NULL,
  `subject_line` text DEFAULT NULL,
  `personal_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `store_name` varchar(100) NOT NULL,
  `role` enum('Admin','Seller','Viewer') DEFAULT 'Seller',
  `is_active` tinyint(1) DEFAULT 1,
  `last_assessment_score` decimal(5,2) DEFAULT NULL,
  `last_assessment_date` datetime DEFAULT NULL,
  `total_assessments` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `full_name`, `store_name`, `role`, `is_active`, `last_assessment_score`, `last_assessment_date`, `total_assessments`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$/LmyJaO9VGtQEo34vvnkyelT8WjClc0RnNNY.rwVqiWD8YRYSby9m', 'senchi2528@gmail.com', 'Jean Marc Aguilar', 'wow', 'Admin', 1, 92.00, '2024-02-01 09:45:00', 3, '2026-04-02 15:36:15', '2026-04-07 14:28:36'),
(2, 'seller', '$2y$10$bLBo9/p33413YPOAlLweQu3SrupFOwrb5LeiInAEWNG3o5P/Q5gIK', 'jeanmarcaguilar829@gmail.com', 'Jean Marc Aguilar', 'JM STORE', 'Seller', 1, 24.00, '2026-04-05 05:23:28', 4, '2026-04-02 15:36:15', '2026-04-06 03:11:25'),
(3, 'viewer', '$2y$10$VP6fVllWeLjea6OYxMNhMOYgrSmJX04yazqQFX8u0KY4.ZGjvG98a', 'viewer@demo.ph', 'Demo Viewer', 'Demo Company', 'Viewer', 1, 65.00, '2024-01-22 16:45:00', 1, '2026-04-02 15:36:15', '2026-04-02 15:38:59'),
(8, 'aguilarmichaele', '$2y$10$j/JT/tHzKpyEWSjKgQP1COyHOlDCjduQ0WEooos94kEPrdzNR55Pq', 'aguilarmichaele@gmail.com', 'Michaele Aguilar', 'Mike Store', 'Seller', 1, NULL, NULL, 0, '2026-04-06 03:14:40', '2026-04-06 03:14:40');

-- --------------------------------------------------------

--
-- Table structure for table `user_achievements`
--

CREATE TABLE `user_achievements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assessment_id` int(11) DEFAULT NULL,
  `points_earned` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_assessment_summary`
-- (See below for the actual view)
--
CREATE TABLE `user_assessment_summary` (
`user_id` int(11)
,`username` varchar(50)
,`full_name` varchar(100)
,`total_assessments` bigint(21)
,`avg_score` decimal(14,4)
,`best_score` int(11)
,`worst_score` int(11)
,`avg_time_spent` decimal(14,4)
,`last_assessment` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `user_question_history`
--

CREATE TABLE `user_question_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`question_ids`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_question_history`
--

INSERT INTO `user_question_history` (`id`, `user_id`, `question_ids`, `created_at`) VALUES
(1, 2, '[57,37,31,77,32,62,3,4,5,48,51,26,11,42,75,33,25,64,99,2,15,19,46,29,92,44,63,6,107,60,69,12,104,58,82,74,36,27,24,43,84,86,56,30,53,90,95,89,8,88,102,106,67,23,98,96,101,94,45,28,93,68,105,83,91,87,72,22,80,17,55,79,21,76,16,38,97,10,70,78,100,59,47,52,103,41,9,85,73,81,39,54,1,20,7,98,40,18,35,65]', '2026-04-02 15:40:33'),
(2, 2, '[82,28,76,107,63,83,10,23,70,4,16,66,15,6,18,43,99,5,3,54,42,33,12,104,106,19,75,103,56,90,48,41,98,37,80,53,92,27,88,58,29,35,101,67,97,64,68,71,73,45,25,91,65,21,78,96,1,7,95,69,87,62,94,79,102,74,52,17,34,50,47,11,46,105,86,8,84,85,2,9,61,93,31,40,36,24,44,77,60,38,26,13,100,81,106,30,72,59,39,20]', '2026-04-02 15:41:33'),
(3, 2, '[59,13,58,101,35,81,60,99,49,26,65,23,56,69,2,85,84,36,42,22,48,50,94,46,88,54,57,20,32,100,70,14,24,8,73,91,93,83,45,44,98,28,82,52,107,17,27,80,95,1,33,86,87,21,103,4,3,53,95,5,43,62,97,25,18,11,7,77,79,89,72,102,17,10,68,66,47,5,38,6,96,16,104,31,71,92,9,19,51,55,67,74,105,39,90,15,12,29,75,78]', '2026-04-02 15:43:18'),
(4, 2, '[98,106,14,60,34,57,48,24,7,62,92,24,78,83,23,59,93,89,11,96,94,31,81,82,1,38,2,75,76,71,66,25,64,69,18,17,103,28,86,42,3,27,52,36,77,80,55,9,35,88,53,105,65,49,84,85,107,70,41,43,6,15,79,90,99,100,32,12,29,63,95,87,47,50,37,74,46,101,21,45,23,30,8,54,91,104,40,13,26,39,58,19,44,51,102,10,56,97,22,33]', '2026-04-03 01:04:19'),
(5, 2, '[52,17,26,101,50,49,64,46,91,3,85,5,86,73,47,107,44,88,10,14,90,4,13,53,43,87,100,80,89,36,94,56,97,103,71,67,54,40,18,32,70,48,99,78,15,31,29,62,27,101,2,28,72,9,42,22,98,105,55,16,96,23,12,41,30,102,75,20,21,8,83,66,57,84,60,45,74,1,76,104,37,77,7,79,68,51,6,19,61,95,59,92,63,34,82,93,69,81,11,58]', '2026-04-03 15:26:16'),
(6, 2, '[73,86,44,67,61,14,56,31,92,46,5,32,19,7,60,75,17,37,65,10,82,30,11,77,88,36,87,96,51,83,105,97,24,78,66,12,27,47,17,8,49,20,15,3,33,43,10,58,18,107,81,63,71,64,76,53,41,4,93,6,57,101,28,23,84,104,106,99,62,55,29,25,80,94,59,40,13,22,68,48,69,42,35,38,100,21,70,1,74,9,16,95,2,98,89,45,54,34,39,14]', '2026-04-03 16:05:00'),
(7, 2, '[46,106,2,1,39,70,99,9,49,17,85,10,43,55,107,21,75,26,50,95,58,56,77,3,47,52,8,69,12,6,65,41,44,35,67,104,88,38,36,42,74,80,37,82,7,103,68,51,25,79,28,63,16,34,101,13,91,19,29,14,66,48,98,61,105,72,53,76,22,102,64,59,81,62,31,100,27,11,89,71,5,54,78,4,33,84,15,32,30,92,87,40,60,24,57,73,23,93,83,45]', '2026-04-04 04:01:43'),
(8, 2, '[58,99,50,43,34,52,105,53,76,2,10,40,33,73,94,20,6,15,62,37,25,59,78,70,100,9,28,41,38,12,7,27,89,91,29,47,4,45,51,74,60,31,30,71,83,55,21,16,57,42,26,88,63,103,75,36,107,32,81,67,54,95,8,98,14,35,17,65,101,97,39,66,72,82,1,23,104,106,24,18,19,13,22,3,49,11,56,48,5,69,92,68,86,79,64,44,46,61,77,84]', '2026-04-04 04:01:52'),
(9, 2, '[75,7,53,93,84,10,104,8,66,81,52,69,1,25,73,82,50,51,48,19,27,86,99,15,63,35,39,32,68,95,3,44,31,106,14,70,103,37,79,92,45,71,18,42,2,88,78,98,96,94,54,59,105,76,89,60,55,85,80,46,9,67,6,72,100,87,36,102,13,77,4,65,90,43,16,47,78,5,34,62,61,56,67,101,57,17,74,41,12,83,97,64,20,23,87,21,38,91,71,30]', '2026-04-04 09:26:31'),
(10, 2, '[102,39,37,77,35,15,9,104,55,62,75,3,28,74,68,84,80,61,86,2,73,13,41,91,26,34,32,64,58,63,81,40,46,69,20,93,36,52,87,88,49,30,71,99,31,51,89,42,78,65,76,100,11,90,48,106,29,79,27,45,25,92,7,85,18,96,57,14,62,103,44,19,16,94,101,67,53,38,56,10,12,21,72,1,17,4,43,70,33,82,66,47,60,50,54,6,98,5,59,8]', '2026-04-04 09:44:28'),
(12, 2, '[104,40,45,65,49,24,86,4,56,13,82,63,55,25,81,85,59,27,20,96,34,52,54,7,28,89,66,92,18,68,50,107,19,79,102,2,23,91,43,17,60,100,88,44,41,64,42,94,62,61,26,30,8,77,83,33,84,97,6,37,75,3,103,14,1,73,22,10,15,98,101,31,29,46,91,93,90,53,9,11,80,74,16,21,36,95,99,76,48,105,69,67,70,47,71,39,72,35,38,87]', '2026-04-04 13:00:01'),
(13, 2, '[49,79,62,10,41,37,103,82,92,25,73,28,1,22,51,86,44,19,18,6,106,67,77,42,33,61,87,63,14,36,50,85,39,84,81,27,80,46,11,69,64,98,52,34,12,89,23,71,78,70,88,54,72,83,59,58,100,26,35,45,97,4,102,101,38,17,40,99,104,68,48,32,55,3,107,105,43,24,9,8,31,74,90,15,65,30,95,21,76,66,96,91,56,47,57,40,29,20,75,5]', '2026-04-04 15:19:26'),
(14, 2, '[18,51,3,53,96,33,94,8,58,29,100,68,15,90,24,66,26,20,92,101,10,99,62,89,22,16,19,14,107,71,65,88,49,74,61,60,80,77,43,54,102,46,91,84,67,27,5,6,37,9,85,93,31,39,57,97,12,17,95,86,41,32,59,81,38,98,78,87,44,105,23,40,73,50,4,79,76,34,13,36,52,11,25,35,55,22,42,104,103,83,7,30,45,56,21,2,28,47,12,1]', '2026-04-04 15:19:30'),
(17, 2, '[62,103,28,39,19,85,38,96,35,73,83,5,56,86,98,9,58,17,60,13,102,21,29,43,74,78,48,34,50,90,66,92,87,4,88,31,70,46,12,14,94,61,16,20,32,72,7,100,69,76,59,53,79,80,27,36,71,10,93,49,45,2,82,25,51,22,77,64,26,75,30,106,99,11,55,57,63,15,105,67,24,84,52,68,91,44,65,95,101,41,104,3,54,1,47,18,107,97,40,81]', '2026-04-04 18:57:59'),
(18, 2, '[72,41,93,69,89,48,85,101,82,105,20,94,74,44,104,73,58,7,43,67,60,55,66,18,47,68,45,75,53,6,37,46,62,102,83,21,24,56,71,34,9,87,54,42,103,2,64,99,22,16,81,33,95,63,77,92,61,23,27,70,91,12,78,4,30,17,3,88,100,65,80,107,51,10,97,106,32,36,39,8,76,31,96,98,59,77,84,5,52,38,28,1,57,13,35,15,25,49,79,90]', '2026-04-04 19:04:48'),
(19, 2, '[14,42,101,89,28,102,43,56,47,70,48,75,91,5,31,15,74,81,106,41,94,46,26,6,7,29,40,93,51,18,62,30,85,72,84,38,20,22,16,8,27,80,86,52,100,24,104,10,68,60,54,105,53,61,92,13,37,23,63,95,1,78,32,79,66,99,103,82,17,88,3,4,83,55,77,90,25,39,9,21,64,19,11,59,57,12,87,69,97,58,44,67,34,98,76,33,2,65,50,49]', '2026-04-04 19:04:52'),
(20, 2, '[41,6,38,68,76,11,34,101,23,22,42,21,90,53,60,18,4,65,26,46,27,83,20,28,75,43,10,89,97,88,95,13,106,40,79,62,85,86,36,77,32,102,19,52,45,63,100,3,94,53,49,25,70,69,104,64,47,12,78,91,84,29,74,72,8,93,24,9,7,14,15,58,30,56,87,31,107,1,82,61,80,96,55,71,35,48,33,103,39,37,92,54,73,81,57,50,17,51,59,44]', '2026-04-04 19:05:44'),
(21, 2, '[13,12,83,77,63,91,15,95,60,65,50,1,18,19,76,21,70,30,88,47,92,90,4,17,84,75,89,38,71,96,57,11,10,2,103,86,7,31,98,99,87,45,61,52,51,79,36,6,37,48,106,42,35,39,22,3,66,28,29,58,81,80,58,68,82,14,33,27,107,44,53,105,102,93,54,49,43,78,23,59,16,20,69,46,9,62,94,74,56,101,25,104,34,32,85,67,55,73,40,41]', '2026-04-04 19:05:59'),
(22, 2, '[83,28,66,47,82,54,48,77,89,103,101,23,51,50,100,33,70,85,46,1,71,30,74,42,75,10,27,59,2,98,35,5,94,19,95,3,41,29,13,44,91,14,61,106,87,17,18,52,31,34,20,6,76,39,56,55,15,25,79,24,45,72,104,62,96,68,90,80,22,12,7,37,58,11,36,43,26,97,69,9,4,16,78,81,38,40,88,21,105,53,67,63,57,8,32,99,86,49,64,102]', '2026-04-04 19:06:09'),
(23, 2, '[7,1,18,10,20,69,30,50,23,47,67,22,65,72,3,92,9,12,14,39,97,49,29,80,25,95,91,34,84,93,19,76,82,4,5,107,24,52,32,88,103,27,60,8,57,33,73,15,64,105,44,59,66,26,51,104,74,75,101,87,36,79,81,78,42,16,40,37,96,31,13,94,38,77,28,46,85,89,90,53,17,106,2,11,6,62,35,71,68,56,100,58,63,98,70,102,48,86,55,61]', '2026-04-04 19:06:16'),
(24, 2, '[78,14,82,92,86,91,37,64,72,71,104,3,23,73,6,7,97,87,107,93,33,76,46,18,11,60,20,55,45,103,85,105,2,53,90,94,36,102,15,62,5,67,10,27,98,54,41,40,74,70,99,107,24,22,106,79,63,51,77,100,29,16,8,12,68,66,13,1,21,48,57,84,69,35,76,61,39,47,56,95,25,4,43,81,88,80,101,30,26,17,83,49,34,78,89,65,19,69,9,75]', '2026-04-04 19:47:02'),
(25, 2, '[83,55,61,12,106,32,85,10,92,42,52,62,68,54,45,104,33,34,50,57,30,3,86,19,15,31,59,20,13,105,93,65,64,20,25,41,43,11,48,69,22,2,107,107,1,87,16,88,27,51,18,77,29,23,9,26,8,100,17,5,49,53,73,35,72,96,103,7,67,81,71,102,66,63,97,39,90,4,99,75,80,59,84,56,47,98,6,58,60,46,14,101,21,36,95,44,74,38,28,79]', '2026-04-04 19:47:04'),
(26, 2, '[25,84,20,8,105,103,49,17,15,51,91,54,28,27,22,35,29,45,66,32,71,11,52,76,68,42,3,65,33,53,88,38,4,9,92,34,57,21,73,95,26,67,72,19,44,40,36,5,64,37,60,104,50,41,17,58,82,43,13,48,10,31,7,59,16,47,14,94,77,1,89,70,46,87,78,24,97,39,69,56,79,23,40,81,102,74,106,2,96,90,61,75,86,55,30,6,12,93,62,18]', '2026-04-04 19:47:30'),
(27, 2, '[65,12,70,16,105,64,21,33,81,14,50,45,84,91,30,41,44,35,15,13,85,19,88,36,92,1,25,54,22,43,55,26,31,73,2,74,3,49,51,93,66,57,100,77,24,20,9,103,53,28,86,72,17,80,56,48,42,52,76,61,32,34,7,83,40,99,38,5,98,58,79,104,95,75,11,69,89,106,97,78,14,46,8,18,96,107,59,90,10,4,60,102,101,6,94,87,29,5,67,6]', '2026-04-04 19:47:58'),
(28, 2, '[16,2,30,29,98,80,13,37,23,87,101,26,70,38,27,88,34,39,106,48,45,11,56,85,96,40,97,25,62,5,12,22,72,94,104,65,24,74,66,77,62,68,54,32,75,93,51,84,28,79,91,47,58,73,14,59,31,78,82,9,60,46,105,52,10,90,81,36,83,63,8,107,67,57,76,41,42,89,71,95,4,3,100,69,92,49,33,103,44,1,19,64,43,21,35,61,20,17,55,53]', '2026-04-04 19:47:58'),
(29, 2, '[101,26,80,18,53,55,88,44,49,38,61,58,54,66,89,65,15,59,64,42,46,100,1,6,70,10,84,39,45,72,51,105,69,67,57,16,30,28,43,68,11,81,86,45,104,14,56,96,5,50,73,33,91,32,25,98,31,2,27,37,8,77,9,99,74,47,63,103,40,83,36,79,13,24,35,106,76,107,34,82,60,17,12,41,87,4,21,3,107,62,52,48,19,93,78,20,23,29,22,85]', '2026-04-04 19:47:58'),
(30, 2, '[85,86,53,94,72,87,105,92,67,90,43,47,62,18,74,107,77,33,99,70,60,28,20,15,82,56,23,10,42,54,27,2,104,46,71,79,84,25,14,36,8,48,1,49,24,50,59,52,64,78,66,21,55,57,81,97,88,3,107,19,17,89,5,68,80,37,39,16,7,93,91,12,75,38,35,106,31,30,96,95,63,98,69,4,61,13,59,29,58,44,40,103,102,83,9,51,41,101,26,45]', '2026-04-04 19:48:42'),
(31, 2, '[15,49,23,104,107,63,56,82,62,105,29,28,61,72,75,90,94,104,38,58,6,98,74,39,106,26,19,53,24,12,1,4,22,80,64,86,9,57,76,52,11,42,27,5,97,8,50,81,68,66,60,87,47,101,73,25,14,95,89,70,48,67,36,88,40,35,54,51,43,10,91,17,30,92,7,99,84,79,31,100,33,55,45,65,37,44,2,102,21,103,46,93,13,78,20,69,32,18,77,16]', '2026-04-04 19:48:49'),
(32, 2, '[89,76,29,68,18,2,72,54,64,66,21,61,93,5,45,94,95,34,6,40,33,38,52,28,19,36,9,70,67,78,83,60,10,47,12,53,90,104,84,50,4,96,63,56,23,73,105,32,62,74,98,7,51,20,77,1,37,92,14,85,97,103,13,24,79,48,11,107,75,39,87,43,16,99,101,17,80,42,58,65,25,100,15,59,44,41,49,88,86,91,71,3,8,106,81,102,69,22,46,30]', '2026-04-04 19:48:53'),
(33, 2, '[106,57,92,25,1,73,82,55,62,6,47,5,61,13,24,44,32,70,18,53,37,51,77,26,99,75,72,67,107,94,14,97,52,50,96,80,85,29,60,40,27,84,20,15,59,71,90,56,78,22,83,16,8,4,3,42,34,74,2,102,86,38,65,48,58,103,76,11,79,36,10,104,35,21,100,9,17,23,28,98,69,88,68,89,64,46,87,30,101,54,45,105,95,91,43,41,81,63,7,66]', '2026-04-04 19:49:00'),
(34, 2, '[75,79,81,45,35,37,24,33,23,2,76,42,5,73,52,107,88,55,54,91,17,87,85,93,44,77,19,56,16,102,106,6,98,3,27,34,86,103,22,100,83,68,65,90,92,61,71,47,67,49,44,74,1,15,57,51,72,46,97,4,78,50,84,91,18,8,58,36,94,13,20,39,62,64,43,70,9,89,95,38,48,96,53,82,41,101,7,10,69,40,63,99,80,31,104,86,60,66,59,12]', '2026-04-04 19:49:02'),
(35, 2, '[15,44,67,24,14,98,77,3,64,45,49,74,28,85,10,86,60,94,20,31,81,40,66,26,47,43,23,62,79,7,54,58,38,25,27,76,34,71,75,55,16,1,11,22,105,89,52,53,9,78,57,51,72,87,75,104,26,61,93,21,92,48,101,106,12,88,70,73,36,35,84,59,27,56,39,91,69,103,5,19,46,65,82,80,18,37,30,29,63,97,68,50,32,8,50,33,83,4,41,42]', '2026-04-04 19:50:05'),
(36, 2, '[80,101,56,96,106,40,82,75,33,55,78,76,72,92,53,104,49,71,7,79,4,91,88,8,3,68,85,32,90,65,94,59,97,99,51,36,41,107,18,44,61,69,83,37,14,93,2,13,35,98,1,26,100,84,5,11,60,39,34,10,54,103,19,28,62,31,64,74,27,77,86,38,50,25,81,17,20,52,23,105,67,29,48,16,70,66,9,58,102,89,47,42,43,87,73,15,12,63,95,6]', '2026-04-04 19:50:10'),
(37, 2, '[55,97,26,35,64,72,20,70,46,24,107,36,18,59,50,19,29,106,82,3,41,2,99,87,22,93,4,66,85,68,8,83,25,92,84,45,80,21,30,29,27,34,44,49,78,57,17,31,9,103,69,86,81,23,40,77,16,91,56,63,35,65,14,100,67,89,104,98,32,13,101,47,88,74,5,9,1,7,28,11,95,58,90,15,105,48,39,39,10,102,38,73,12,79,54,6,51,37,33,60]', '2026-04-04 19:50:46'),
(38, 2, '[88,3,35,48,25,75,76,10,26,18,9,24,57,80,44,42,100,70,94,50,82,1,32,71,60,46,11,99,58,17,39,28,20,103,22,67,8,49,106,91,33,65,51,84,16,12,40,63,2,56,15,61,52,98,90,20,81,41,64,53,73,7,96,55,23,106,36,97,4,107,38,19,43,6,69,59,104,83,72,89,45,86,79,14,78,54,95,30,87,5,31,13,105,74,47,77,101,4,85,21]', '2026-04-04 19:51:12'),
(39, 2, '[90,25,40,53,13,31,37,17,26,23,103,99,14,101,62,92,4,47,81,68,60,88,81,19,5,74,50,43,83,63,10,35,27,55,87,51,97,29,78,8,9,32,49,15,82,24,69,91,39,58,22,38,16,46,96,102,64,45,85,65,80,12,36,73,3,66,34,76,75,18,70,42,72,7,67,52,44,89,61,59,93,42,6,48,56,57,33,62,71,95,11,107,21,54,77,105,20,106,41,79]', '2026-04-05 03:11:22'),
(40, 2, '[27,8,77,71,99,92,90,37,48,25,76,43,102,54,50,72,23,57,83,6,22,78,73,95,44,56,68,75,70,24,96,31,2,17,5,20,105,81,41,66,51,82,84,60,13,69,15,98,7,88,34,104,18,29,61,58,35,100,86,36,10,94,59,42,21,38,19,11,4,85,3,9,87,40,30,32,46,16,53,33,74,65,62,97,14,45,55,93,79,12,101,107,91,1,103,106,47,28,63,26]', '2026-04-05 03:11:39'),
(43, 2, '[10,55,43,102,7,36,58,88,70,80,20,56,42,23,68,24,3,52,31,47,94,85,106,67,27,69,62,50,72,66,75,14,89,97,73,82,19,21,53,51,40,64,29,39,78,12,18,60,48,79,65,9,74,2,1,98,8,37,26,95,91,34,105,59,99,13,28,45,104,17,61,92,101,93,22,38,57,87,16,15,35,25,49,63,30,11,32,76,107,96,84,5,46,81,4,41,54,44,86,71]', '2026-04-06 00:54:06'),
(44, 2, '[37,8,62,42,13,68,69,79,91,47,4,17,30,33,27,15,5,52,2,97,54,9,32,67,78,44,48,25,65,84,20,43,28,7,20,26,39,71,88,95,51,77,107,41,49,92,16,10,70,106,83,40,64,6,18,80,38,57,11,94,29,99,66,85,21,23,72,22,8,59,75,55,19,14,102,35,73,61,96,87,24,56,93,100,34,104,86,46,101,12,1,82,76,63,31,89,45,3,98,105]', '2026-04-06 03:10:50'),
(45, 2, '[38,70,15,103,56,73,75,48,58,20,6,80,54,96,91,50,33,61,35,57,55,52,85,64,19,99,65,23,40,37,36,90,63,43,46,100,17,72,89,94,10,68,44,84,47,93,2,98,95,88,51,30,8,67,53,49,92,9,5,18,45,104,34,77,1,14,42,79,16,90,97,32,31,29,7,26,25,87,4,24,83,81,106,62,60,59,78,27,107,66,101,82,76,41,102,55,21,86,71,11]', '2026-04-06 03:12:03'),
(46, 8, '[55,80,16,96,49,100,40,14,32,59,2,63,76,65,31,41,37,57,18,93,21,95,10,17,71,82,52,99,26,38,77,53,29,85,89,91,1,36,28,4,45,60,75,79,94,50,98,64,8,88,54,33,105,87,23,48,67,103,86,73,70,61,104,68,20,15,7,13,51,74,43,12,92,81,58,102,24,97,72,84,106,9,66,34,11,3,19,42,5,62,69,56,47,27,25,39,90,101,6,78]', '2026-04-06 03:14:47'),
(47, 2, '[43,46,54,63,95,8,62,78,59,98,58,35,73,47,50,82,65,22,6,11,76,97,96,41,83,85,69,73,29,21,61,94,26,51,76,64,36,84,105,101,102,107,77,16,67,19,5,9,91,49,99,60,37,42,1,70,28,3,34,74,2,18,38,27,56,14,93,104,57,89,87,33,48,25,45,17,66,20,4,12,39,10,44,58,79,53,30,92,71,55,80,68,72,88,52,81,75,57,100,31]', '2026-04-06 06:44:22'),
(48, 2, '[90,56,11,18,96,46,34,43,45,10,47,2,12,26,40,99,76,67,38,84,29,33,49,75,70,85,101,51,97,50,95,64,1,74,60,105,89,24,44,100,86,94,8,27,63,87,19,28,7,16,14,80,30,102,83,73,5,62,20,106,68,72,13,88,66,21,65,52,55,103,53,57,4,42,9,25,91,59,107,32,17,81,48,82,22,66,79,92,78,77,15,54,104,35,39,69,41,61,6,71]', '2026-04-06 06:44:27'),
(49, 2, '[96,85,31,54,20,10,61,56,46,104,15,57,32,89,88,60,23,26,79,6,40,67,77,103,80,94,33,87,102,58,93,71,81,62,35,43,78,84,83,53,30,91,95,12,70,73,3,75,28,29,64,42,65,21,48,101,22,38,107,24,9,100,55,8,34,11,49,14,27,36,63,1,25,4,99,76,82,37,69,47,5,39,68,74,90,66,13,2,86,41,19,59,50,106,52,105,44,97,72,35]', '2026-04-06 06:45:59'),
(50, 2, '[4,73,40,26,74,57,20,29,36,88,94,99,80,51,56,16,37,1,13,3,53,79,22,84,42,63,107,98,38,67,97,10,85,68,71,95,83,60,91,12,5,87,103,47,52,55,19,46,77,104,65,75,59,103,93,32,70,14,62,50,15,25,48,86,89,101,11,82,90,92,64,44,31,33,28,45,6,105,9,61,106,54,24,58,27,81,18,66,39,43,76,35,8,17,41,102,96,72,49,78]', '2026-04-06 06:46:19'),
(51, 2, '[49,102,3,58,6,94,52,64,1,29,70,104,67,13,12,38,86,64,89,23,19,84,79,91,54,37,55,4,74,2,15,63,50,35,72,65,51,76,42,21,75,8,43,16,34,106,68,24,18,33,10,9,7,107,20,60,40,47,81,100,98,90,45,87,25,32,93,85,78,88,36,53,99,71,17,27,14,80,39,82,77,11,73,5,28,66,57,101,41,31,46,26,83,103,105,96,92,62,69,56]', '2026-04-06 06:46:47'),
(52, 2, '[76,37,44,34,75,45,49,17,69,71,22,66,95,74,87,1,97,48,6,98,52,38,85,40,19,79,77,65,64,59,81,15,92,58,39,23,70,82,93,107,53,104,18,102,54,2,89,3,31,88,83,73,21,28,63,67,36,11,60,32,33,25,100,41,9,10,4,29,96,55,26,7,99,16,106,37,12,86,72,43,91,101,62,90,47,30,57,27,105,78,56,103,14,94,13,50,20,24,35,8]', '2026-04-06 06:46:57'),
(53, 2, '[47,34,96,37,48,40,104,19,33,31,1,77,73,80,101,25,75,53,72,36,16,51,76,4,99,38,93,59,56,12,98,55,90,106,30,107,52,6,105,17,85,64,78,50,71,10,97,2,106,70,44,95,81,67,24,74,92,8,63,18,32,94,102,69,9,84,66,68,14,5,103,45,65,27,83,61,3,87,57,43,35,62,42,58,89,54,39,26,91,46,29,20,49,60,79,82,100,21,22,41]', '2026-04-07 13:34:02'),
(54, 2, '[83,82,57,43,56,94,86,30,8,37,17,54,78,60,47,13,73,80,36,16,4,69,42,106,18,33,55,99,81,90,64,66,19,27,70,9,79,26,91,38,68,90,11,49,96,44,50,77,24,105,72,100,45,107,15,22,65,29,25,103,75,48,2,58,84,3,61,32,7,85,35,95,28,71,93,34,14,5,39,63,92,41,98,104,46,89,10,76,102,52,67,1,87,97,74,62,59,101,88,21]', '2026-04-07 13:57:17'),
(55, 2, '[93,103,5,27,58,101,81,49,43,39,45,38,86,48,82,30,57,68,1,40,4,20,34,74,35,52,98,51,6,32,64,94,77,79,85,105,54,50,97,106,69,78,90,76,24,16,56,31,59,107,44,7,17,18,100,3,23,5,2,11,12,92,95,14,60,62,67,73,42,87,96,47,13,71,55,15,61,33,29,37,63,53,75,36,88,21,9,8,41,106,10,66,102,80,104,46,84,22,72,19]', '2026-04-07 13:59:27'),
(56, 2, '[12,50,89,104,31,40,84,18,73,42,3,102,33,34,48,1,91,107,66,57,54,90,14,68,86,15,9,96,93,22,35,105,83,30,77,2,56,67,80,13,11,74,101,49,24,32,47,92,8,25,53,95,63,78,60,19,46,94,55,97,16,39,29,52,23,4,17,76,98,64,65,72,10,36,20,88,106,70,37,43,103,51,71,21,85,69,7,27,6,44,28,75,38,5,79,62,35,26,45,17]', '2026-04-07 13:59:39');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `store_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `flagged` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `name`, `email`, `industry`, `contact_person`, `phone`, `address`, `store_name`, `is_active`, `flagged`, `created_at`, `updated_at`) VALUES
(1, 'TechCorp Solutions', 'security@techcorp.com', 'Technology', 'John Smith', '+63-2-555-0123', NULL, 'TechCorp Store', 1, 0, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(2, 'SecureNet Inc', 'info@securenet.com', 'Network Security', 'Sarah Johnson', '+63-2-555-0124', NULL, 'SecureNet Shop', 1, 0, '2026-04-02 15:36:15', '2026-04-02 15:36:15'),
(3, 'DataSafe Systems', 'contact@datasafe.com', 'Data Management', 'Michael Brown', '+63-2-555-0125', NULL, 'DataSafe Hub', 1, 0, '2026-04-02 15:36:15', '2026-04-02 15:36:15');

-- --------------------------------------------------------

--
-- Structure for view `biased_questions_view`
--
DROP TABLE IF EXISTS `biased_questions_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `biased_questions_view`  AS SELECT `question_bank`.`id` AS `id`, `question_bank`.`category` AS `category`, `question_bank`.`difficulty` AS `difficulty`, `question_bank`.`question_text` AS `question_text`, `question_bank`.`bias_score` AS `bias_score`, `question_bank`.`times_used` AS `times_used`, `question_bank`.`correct_rate` AS `correct_rate`, CASE WHEN `question_bank`.`bias_score` > 40 THEN 'High Bias - Needs Review' WHEN `question_bank`.`bias_score` > 25 THEN 'Moderate Bias - Monitor' WHEN `question_bank`.`bias_score` > 10 THEN 'Low Bias' ELSE 'Unbiased' END AS `bias_level` FROM `question_bank` WHERE `question_bank`.`times_used` > 50 ORDER BY `question_bank`.`bias_score` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `user_assessment_summary`
--
DROP TABLE IF EXISTS `user_assessment_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_assessment_summary`  AS SELECT `u`.`id` AS `user_id`, `u`.`username` AS `username`, `u`.`full_name` AS `full_name`, count(`a`.`id`) AS `total_assessments`, avg(`a`.`score`) AS `avg_score`, max(`a`.`score`) AS `best_score`, min(`a`.`score`) AS `worst_score`, avg(`a`.`time_spent`) AS `avg_time_spent`, max(`a`.`assessment_date`) AS `last_assessment` FROM (`users` `u` left join `assessments` `a` on(`u`.`id` = `a`.`vendor_id`)) GROUP BY `u`.`id`, `u`.`username`, `u`.`full_name` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `answer_analytics`
--
ALTER TABLE `answer_analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question` (`question_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `assessment_token` (`assessment_token`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_score` (`score`),
  ADD KEY `idx_rank` (`rank`),
  ADD KEY `idx_assessment_date` (`assessment_date`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assessment_id` (`assessment_id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_is_correct` (`is_correct`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `assessment_sessions`
--
ALTER TABLE `assessment_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_session` (`user_id`,`session_id`),
  ADD KEY `idx_started_at` (`started_at`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_otp_code` (`otp_code`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_purpose` (`purpose`);

--
-- Indexes for table `otp_logs`
--
ALTER TABLE `otp_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_purpose` (`purpose`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `question_bank`
--
ALTER TABLE `question_bank`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_difficulty` (`difficulty`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `question_order_analytics`
--
ALTER TABLE `question_order_analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question` (`question_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_position` (`position_in_assessment`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sent_certificates`
--
ALTER TABLE `sent_certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_badge` (`user_id`,`badge_id`),
  ADD KEY `badge_id` (`badge_id`),
  ADD KEY `assessment_id` (`assessment_id`);

--
-- Indexes for table `user_question_history`
--
ALTER TABLE `user_question_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_flagged` (`flagged`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `answer_analytics`
--
ALTER TABLE `answer_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT for table `assessment_sessions`
--
ALTER TABLE `assessment_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `otp_codes`
--
ALTER TABLE `otp_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `otp_logs`
--
ALTER TABLE `otp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `question_bank`
--
ALTER TABLE `question_bank`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT for table `question_order_analytics`
--
ALTER TABLE `question_order_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sent_certificates`
--
ALTER TABLE `sent_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_achievements`
--
ALTER TABLE `user_achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_question_history`
--
ALTER TABLE `user_question_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `answer_analytics`
--
ALTER TABLE `answer_analytics`
  ADD CONSTRAINT `answer_analytics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `answer_analytics_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `question_bank` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  ADD CONSTRAINT `assessment_answers_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_sessions`
--
ALTER TABLE `assessment_sessions`
  ADD CONSTRAINT `assessment_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD CONSTRAINT `otp_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_logs`
--
ALTER TABLE `otp_logs`
  ADD CONSTRAINT `otp_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `question_order_analytics`
--
ALTER TABLE `question_order_analytics`
  ADD CONSTRAINT `question_order_analytics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `question_order_analytics_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `question_bank` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sent_certificates`
--
ALTER TABLE `sent_certificates`
  ADD CONSTRAINT `sent_certificates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD CONSTRAINT `user_achievements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_achievements_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_achievements_ibfk_3` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_question_history`
--
ALTER TABLE `user_question_history`
  ADD CONSTRAINT `user_question_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Table structure for table `sent_certificates`
--

CREATE TABLE IF NOT EXISTS `sent_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `cert_id` varchar(50) NOT NULL,
  `cert_type` varchar(50) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `rank` char(1) DEFAULT NULL,
  `subject_line` text DEFAULT NULL,
  `personal_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
