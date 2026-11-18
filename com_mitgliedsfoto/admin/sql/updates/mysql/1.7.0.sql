-- 1.7.0: Druck-Export (Zeitpunkt der Übergabe an die Druckerei)
ALTER TABLE `#__mitgliedsfoto` ADD COLUMN `exportiert_am` DATETIME NULL DEFAULT NULL;
