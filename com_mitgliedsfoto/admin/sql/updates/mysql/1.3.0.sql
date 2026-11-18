-- 1.3.0: Erfassung ohne Joomla-Eintrag (Vorstand bzw. QR-Code), Kennzeichen „unbestätigt“
ALTER TABLE `#__mitgliedsfoto` ADD COLUMN `vorname` VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE `#__mitgliedsfoto` ADD COLUMN `nachname` VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE `#__mitgliedsfoto` ADD COLUMN `geburtsdatum` DATE NULL DEFAULT NULL;
ALTER TABLE `#__mitgliedsfoto` ADD COLUMN `geprueft` TINYINT(1) NOT NULL DEFAULT 1;
