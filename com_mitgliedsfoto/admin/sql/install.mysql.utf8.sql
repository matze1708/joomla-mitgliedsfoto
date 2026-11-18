CREATE TABLE IF NOT EXISTS `#__mitgliedsfoto` (
  `mitgliedsnr` VARCHAR(50) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'eingereicht',
  `eingereicht_am` DATETIME NOT NULL,
  `dateiname` VARCHAR(255) NULL DEFAULT NULL,
  `einwilligung_am` DATETIME NOT NULL,
  `erfasst_von` INT UNSIGNED NULL DEFAULT NULL,
  `mail_gesendet_am` DATETIME NULL DEFAULT NULL,
  `geloescht_am` DATETIME NULL DEFAULT NULL,
  `vorname` VARCHAR(100) NULL DEFAULT NULL,
  `nachname` VARCHAR(100) NULL DEFAULT NULL,
  `geburtsdatum` DATE NULL DEFAULT NULL,
  `geprueft` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`mitgliedsnr`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__mitgliedsfoto_versuche` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_hash` CHAR(64) NOT NULL,
  `zeit` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_zeit` (`ip_hash`, `zeit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
