<?php
/**
 * Erzeugt die Joomla-Update-Datei (update.xml) für ein Release.
 *
 * Aufruf: php tools/update-xml.php <zip> <download-url> <info-url> > update.xml
 */
if ($argc < 4 || !is_file($argv[1])) {
    fwrite(STDERR, "Aufruf: php tools/update-xml.php <zip> <download-url> <info-url>\n");
    exit(1);
}

$manifest = simplexml_load_file(__DIR__ . '/../com_mitgliedsfoto/mitgliedsfoto.xml');
$version  = trim((string) $manifest->version);
$e        = static fn ($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

echo <<<XML
<?xml version="1.0" encoding="utf-8"?>
<updates>
	<update>
		<name>com_mitgliedsfoto</name>
		<description>Foto-Erfassung für die Mitgliedsausweise des Schützenvereins Volker Alzey 1952 e.V.</description>
		<element>com_mitgliedsfoto</element>
		<type>component</type>
		<client>1</client>
		<version>{$e($version)}</version>
		<infourl title="com_mitgliedsfoto {$e($version)}">{$e($argv[3])}</infourl>
		<downloads>
			<downloadurl type="full" format="zip">{$e($argv[2])}</downloadurl>
		</downloads>
		<sha256>{$e(hash_file('sha256', $argv[1]))}</sha256>
		<tags>
			<tag>stable</tag>
		</tags>
		<maintainer>Schützenverein Volker Alzey 1952 e.V.</maintainer>
		<targetplatform name="joomla" version="5.*"/>
		<php_minimum>8.1</php_minimum>
	</update>
</updates>

XML;
