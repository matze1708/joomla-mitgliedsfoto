<?php
// Einfacher Test der Joomla-unabhängigen Logik: php tests/run.php
define('_JEXEC', 1);
$h = __DIR__ . '/../com_mitgliedsfoto/admin/src/Helper/';
foreach (['FotoException', 'Token', 'Dateiname', 'Bild', 'Mitglieder'] as $c) { require $h . $c . '.php'; }

use SvAlzey\Component\Mitgliedsfoto\Administrator\Helper\{Token, Dateiname, Bild, FotoException, Mitglieder};

$fehler = 0;
function pruefe(string $name, bool $ok): void { global $fehler; echo ($ok ? "OK    " : "FEHLER") . " $name\n"; $fehler += $ok ? 0 : 1; }
function wirft(callable $f, string $key): bool { try { $f(); } catch (FotoException $e) { return $e->getMessage() === $key; } return false; }

// Token
$t = Token::erzeugen('1234', 'geheim');
pruefe('Token 64 Hex', (bool) preg_match('/^[0-9a-f]{64}$/', $t));
pruefe('Token gültig', Token::pruefen('1234', $t, 'geheim'));
pruefe('Token falsche Nr', !Token::pruefen('1235', $t, 'geheim'));
pruefe('Token falscher Schlüssel', !Token::pruefen('1234', $t, 'anders'));
pruefe('Token leer', !Token::pruefen('1234', '', 'geheim') && !Token::pruefen('', $t, 'geheim'));
pruefe('Token kein MD5', strlen($t) !== 32);
pruefe('Token Großbuchstaben/Müll abgelehnt', !Token::pruefen('1234', strtoupper($t), 'geheim') && !Token::pruefen('1234', $t . "'", 'geheim') && !Token::pruefen('1234', substr($t, 0, 63), 'geheim'));

// Mitgliedsnummer (Eingabe-Whitelist)
pruefe('Nr gültig', Mitglieder::nrGueltig('9001') && Mitglieder::nrGueltig('A-12/3') && Mitglieder::nrGueltig('0042'));
pruefe('Nr SQL/HTML/Pfad abgelehnt', !Mitglieder::nrGueltig("1' OR '1'='1") && !Mitglieder::nrGueltig('<script>') && !Mitglieder::nrGueltig('../1') && !Mitglieder::nrGueltig('') && !Mitglieder::nrGueltig(str_repeat('1', 51)) && !Mitglieder::nrGueltig("1\n2"));
pruefe('UTC-Kalenderwert -> Ortszeit', Mitglieder::utcInOrtszeit('1980-05-16 22:00:00', 'Europe/Berlin') === '1980-05-17' && Mitglieder::utcInOrtszeit('1980-05-17 00:00:00', 'Europe/Berlin') === '1980-05-17 00:00:00');

// Dateiname
pruefe('Umlaute/ß', Dateiname::erzeugen('Müller-Lüdenscheidt', 'Jörg Ärmel', '007') === 'Mueller-Luedenscheidt_Joerg-Aermel_007.jpg');
pruefe('ß', Dateiname::erzeugen('Groß', 'Hans', '12') === 'Gross_Hans_12.jpg');
pruefe('Akzente', Dateiname::erzeugen('Hernández', 'Zoë', '5') === 'Hernandez_Zoe_5.jpg');
pruefe('Path-Traversal', !str_contains(Dateiname::erzeugen('../../etc', '/passwd', '../1'), '/') && !str_contains(Dateiname::erzeugen('../../etc', '/passwd', '../1'), '..'));
pruefe('Leere Namen', Dateiname::erzeugen('', '', '') === 'x_x_x.jpg');

// Geburtsdatum (Aushang-Weg)
$m = (object) ['geburtsdatum' => '1975-03-07 00:00:00'];
pruefe('Datum TT.MM.JJJJ', Mitglieder::geburtsdatumPasst($m, '07.03.1975') && Mitglieder::geburtsdatumPasst($m, '7.3.1975'));
pruefe('Datum ISO', Mitglieder::geburtsdatumPasst($m, '1975-03-07'));
pruefe('Datum falsch/ungültig', !Mitglieder::geburtsdatumPasst($m, '08.03.1975') && !Mitglieder::geburtsdatumPasst($m, '31.02.1975') && !Mitglieder::geburtsdatumPasst($m, ''));
pruefe('Kein Geburtsdatum hinterlegt', !Mitglieder::geburtsdatumPasst((object) ['geburtsdatum' => null], '07.03.1975'));
pruefe('Text-Format dd.mm.yyyy in DB', Mitglieder::geburtsdatumPasst((object) ['geburtsdatum' => '07.03.1975'], '1975-03-07'));

// Bild
$tmp = sys_get_temp_dir();
function bild(int $w, int $h, string $typ = 'jpeg'): string {
    $im = imagecreatetruecolor($w, $h); imagefill($im, 0, 0, imagecolorallocate($im, 200, 50, 50));
    $f = tempnam(sys_get_temp_dir(), 'mf'); $typ === 'png' ? imagepng($im, $f) : imagejpeg($im, $f); return $f;
}
$ok = Bild::verarbeiten(bild(912, 1168), 5000000);
$i = getimagesizefromstring($ok);
pruefe('Re-Encode 456x584 JPEG (Fotofeld Ausweis)', $i[0] === 456 && $i[1] === 584 && $i[2] === IMAGETYPE_JPEG);
pruefe('600 dpi im JPEG (JFIF)', substr($ok, 6, 5) === "JFIF\0" && ord($ok[13]) === 1 && unpack('n', substr($ok, 14, 2))[1] === 600 && unpack('n', substr($ok, 16, 2))[1] === 600);
$gd = imagecreatefromstring($ok);
pruefe('RGB (Truecolor, keine Palette/CMYK)', $gd !== false && imageistruecolor($gd));
$i = getimagesizefromstring(Bild::verarbeiten(bild(300, 384), 5000000));
pruefe('Kleineres JPEG -> 456x584', $i[0] === 456 && $i[1] === 584);
pruefe('Altes Format 3:4 (600x800) passt nicht mehr ins Fotofeld', wirft(fn() => Bild::verarbeiten(bild(600, 800), 5000000), 'COM_MITGLIEDSFOTO_ERR_SEITENVERHAELTNIS'));
pruefe('PNG abgelehnt (nur JPEG aus dem Browser)', wirft(fn() => Bild::verarbeiten(bild(456, 584, 'png'), 5000000), 'COM_MITGLIEDSFOTO_ERR_FORMAT'));
pruefe('Riesenbild abgelehnt (Dekompressionsbombe)', wirft(fn() => Bild::verarbeiten(bild(3000, 4000), 50000000), 'COM_MITGLIEDSFOTO_ERR_FORMAT'));
$f = tempnam($tmp, 'mf'); file_put_contents($f, "\xFF\xD8\xFF<?php system('id'); ?>");
pruefe('JPEG-Magic mit PHP-Code abgelehnt', wirft(fn() => Bild::verarbeiten($f, 5000000), 'COM_MITGLIEDSFOTO_ERR_FORMAT'));
$f = bild(456, 584); $j = file_get_contents($f);
file_put_contents($f, substr($j, 0, 2) . "\xFF\xFE\x00\x20<?php system('id'); ?>XXXXXXXXXX" . substr($j, 2)); // Code im JPEG-Kommentar
$out = Bild::verarbeiten($f, 5000000);
pruefe('PHP-Code im JPEG-Kommentar wird entfernt', !str_contains($out, '<?php') && !str_contains($out, 'system('));
$im = imagecreatetruecolor(456, 584); $f = tempnam($tmp, 'mf'); imagejpeg($im, $f);
file_put_contents($f, substr(file_get_contents($f), 0, 2) . "\xFF\xE1\x00\x10Exif\x00\x00GPSDATA!" . substr(file_get_contents($f), 2));
pruefe('EXIF/Metadaten entfernt', !str_contains(Bild::verarbeiten($f, 5000000), 'GPSDATA'));
pruefe('Zu klein', wirft(fn() => Bild::verarbeiten(bild(150, 192), 5000000), 'COM_MITGLIEDSFOTO_ERR_ZU_KLEIN'));
pruefe('Quer abgelehnt', wirft(fn() => Bild::verarbeiten(bild(800, 600), 5000000), 'COM_MITGLIEDSFOTO_ERR_SEITENVERHAELTNIS'));
pruefe('Größenlimit', wirft(fn() => Bild::verarbeiten(bild(912, 1168), 1000), 'COM_MITGLIEDSFOTO_ERR_GROESSE'));
$f = tempnam($tmp, 'mf'); file_put_contents($f, '<?php echo 1; ?>');
pruefe('Kein Bild (PHP-Datei)', wirft(fn() => Bild::verarbeiten($f, 5000000), 'COM_MITGLIEDSFOTO_ERR_FORMAT'));
$f = tempnam($tmp, 'mf'); file_put_contents($f, "GIF89a\x01\x00\x01\x00\x00\x00\x00;");
pruefe('GIF abgelehnt', wirft(fn() => Bild::verarbeiten($f, 5000000), 'COM_MITGLIEDSFOTO_ERR_FORMAT'));
$f = bild(456, 584); file_put_contents($f, "<?php system('id'); ?>", FILE_APPEND);
$out = Bild::verarbeiten($f, 5000000);
pruefe('Angehängter Code wird verworfen', !str_contains($out, '<?php'));

echo $fehler ? "\n$fehler Fehler\n" : "\nAlle Tests bestanden\n";
exit($fehler ? 1 : 0);
