CREATE TABLE IF NOT EXISTS `PREFIX_evo_linkmanager_link` (
                                                           `id_link` INT UNSIGNED AUTO_INCREMENT NOT NULL,
                                                           `name` VARCHAR(255) NOT NULL,
  `url` VARCHAR(255) NOT NULL,
  `link_type` VARCHAR(50) NOT NULL DEFAULT 'custom',
  `id_cms` INT UNSIGNED DEFAULT NULL,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_link`),
  KEY `evo_linkmanager_link_position_idx` (`position`),
  KEY `evo_linkmanager_link_active_idx` (`active`)
  ) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_evo_linkmanager_placement` (
                                                                `id_placement` INT UNSIGNED AUTO_INCREMENT NOT NULL,
                                                                `identifier` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_placement`),
  UNIQUE KEY `evo_linkmanager_placement_identifier_unique` (`identifier`)
  ) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_evo_linkmanager_placement_link` (
                                                                     `id_placement` INT UNSIGNED NOT NULL,
                                                                     `id_link` INT UNSIGNED NOT NULL,
                                                                     PRIMARY KEY (`id_placement`, `id_link`),
  CONSTRAINT `evo_linkmanager_placement_link_placement_fk`
  FOREIGN KEY (`id_placement`) REFERENCES `PREFIX_evo_linkmanager_placement` (`id_placement`) ON DELETE CASCADE,
  CONSTRAINT `evo_linkmanager_placement_link_link_fk`
  FOREIGN KEY (`id_link`) REFERENCES `PREFIX_evo_linkmanager_link` (`id_link`) ON DELETE CASCADE
  ) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `PREFIX_evo_linkmanager_link`
(`name`, `url`, `link_type`, `id_cms`, `position`, `active`, `date_add`, `date_upd`)
VALUES
  ('Contact', 'https://support.raviday-barbecue.com/hc/fr/requests/new', 'contact', NULL, 1, 1, NOW(), NOW()),
  ('FAQ', '', 'cms', NULL, 2, 1, NOW(), NOW()),
  ('Découvrir les avis', 'https://example.com/avis', 'custom', NULL, 3, 1, NOW(), NOW());

INSERT INTO `PREFIX_evo_linkmanager_placement`
(`identifier`, `name`, `description`, `active`, `date_add`, `date_upd`)
VALUES
  ('decouvrir_avis', 'Bouton Découvrir les avis', 'Bouton présent dans la section avis du site', 1, NOW(), NOW()),
  ('contact_footer', 'Lien Contact (pied de page)', 'Lien de contact dans le pied de page', 1, NOW(), NOW()),
  ('faq_footer', 'Lien FAQ (pied de page)', 'Lien FAQ dans le pied de page', 1, NOW(), NOW());

-- Découvrir les avis, Contact Footer, FAQ Footer
INSERT INTO `PREFIX_evo_linkmanager_placement_link`
(`id_placement`, `id_link`)
VALUES
  (1, 3),
  (2, 1),
  (3, 2);

CREATE TABLE IF NOT EXISTS `PREFIX_evo_linkmanager_log` (
                                                          `id_log` INT UNSIGNED AUTO_INCREMENT NOT NULL,
                                                          `id_employee` INT UNSIGNED DEFAULT NULL,
                                                          `employee_name` VARCHAR(255) DEFAULT NULL,
  `severity` VARCHAR(20) NOT NULL DEFAULT 'info',
  `resource_type` VARCHAR(50) NOT NULL,
  `resource_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `message` TEXT NOT NULL,
  `details` TEXT DEFAULT NULL,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_log`),
  KEY `evo_linkmanager_log_resource_idx` (`resource_type`, `resource_id`),
  KEY `evo_linkmanager_log_action_idx` (`action`),
  KEY `evo_linkmanager_log_severity_idx` (`severity`),
  KEY `evo_linkmanager_log_date_idx` (`date_add`)
  ) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `PREFIX_evo_linkmanager_log`
(`id_employee`, `employee_name`, `severity`, `resource_type`, `resource_id`, `action`, `message`, `details`, `date_add`)
VALUES
  (NULL, NULL, 'success', 'module', NULL, 'install', 'Module has been installed', NULL, NOW());
