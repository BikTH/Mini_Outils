-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 11 déc. 2025 à 18:46
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `qcm_app`
--

-- --------------------------------------------------------

--
-- Structure de la table `attempts`
--

CREATE TABLE `attempts` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `user_identifier` varchar(255) DEFAULT NULL,
  `date_start` datetime NOT NULL,
  `date_end` datetime NOT NULL,
  `score_auto` float NOT NULL,
  `total_points` float NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `attempt_answers`
--

CREATE TABLE `attempt_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_ids` text DEFAULT NULL,
  `is_full_correct` tinyint(1) DEFAULT NULL,
  `partial_score` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `nb_questions` int(11) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `options`
--

CREATE TABLE `options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `label` char(1) NOT NULL,
  `texte` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `type` enum('qcm_simple','qcm_multiple','ouverte') NOT NULL,
  `enonce` text NOT NULL,
  `explication` text DEFAULT NULL,
  `est_notee` tinyint(1) NOT NULL DEFAULT 1,
  `actif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `attempts`
--
ALTER TABLE `attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exam_id` (`exam_id`),
  ADD KEY `idx_user_identifier` (`user_identifier`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Index pour la table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempt_id` (`attempt_id`),
  ADD KEY `idx_question_id` (`question_id`);

--
-- Index pour la table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `options`
--
ALTER TABLE `options`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `attempts`
--
ALTER TABLE `attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `options`
--
ALTER TABLE `options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  ADD CONSTRAINT `attempt_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
