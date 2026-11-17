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
$ok = Bild::verarbeiten(bild(1200, 1600), 5000000);
$i = getimagesizefromstring($ok);
pruefe('Re-Encode 600x800 JPEG', $i[0] === 600 && $i[1] === 800 && $i[2] === IMAGETYPE_JPEG);
$i = getimagesizefromstring(Bild::verarbeiten(bild(450, 600, 'png'), 5000000));
pruefe('PNG -> JPEG 600x800', $i[0] === 600 && $i[1] === 800 && $i[2] === IMAGETYPE_JPEG);
pruefe('Zu klein', wirft(fn() => Bild::verarbeiten(bild(150, 200), 5000000), 'COM_MITGLIEDSFOTO_ERR_ZU_KLEIN'));
pruefe('Quer abgelehnt', wirft(fn() => Bild::verarbeiten(bild(800, 600), 5000000), 'COM_MITGLIEDSFOTO_ERR_SEITENVERHAELTNIS'));
pruefe('Größenlimit', wirft(fn() => Bild::verarbeiten(bild(1200, 1600), 1000), 'COM_MITGLIEDSFOTO_ERR_GROESSE'));
$f = tempnam($tmp, 'mf'); file_put_contents($f, '<?php echo 1; ?>');
pruefe('Kein Bild (PHP-Datei)', wirft(fn() => Bild::verarbeiten($f, 5000000), 'COM_MITGLIEDSFOTO_ERR_FORMAT'));
$f = tempnam($tmp, 'mf'); file_put_contents($f, "GIF89a\x01\x00\x01\x00\x00\x00\x00;");
pruefe('GIF abgelehnt', wirft(fn() => Bild::verarbeiten($f, 5000000), 'COM_MITGLIEDSFOTO_ERR_FORMAT'));
$f = bild(1200, 1600); file_put_contents($f, "<?php system('id'); ?>", FILE_APPEND);
$out = Bild::verarbeiten($f, 5000000);
pruefe('Angehängter Code wird verworfen', !str_contains($out, '<?php'));

echo $fehler ? "\n$fehler Fehler\n" : "\nAlle Tests bestanden\n";
exit($fehler ? 1 : 0);
