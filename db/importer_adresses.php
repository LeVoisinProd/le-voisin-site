<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';
/* Les adresses n'existaient QUE dans la base locale: elles venaient des lignes
   `source = 'assoc'` d'une reprise antérieure, que la production n'a jamais
   eues. On ne les invente pas, on les rapatrie — et seulement là où le champ
   est vide, pour ne rien écraser. [16.08.2026] */
$ecrire = in_array('--ecrire', $argv, true);
$j = json_decode((string)file_get_contents($argv[1]), true) ?: [];
$n = 0;
foreach ($j as $a) {
    $o = DB::one("SELECT id, adresse FROM organisation WHERE supprime_le IS NULL
                   AND (source_ref = ? OR nom = ?)", [$a['source_ref'] ?? '', $a['nom']]);
    if (!$o) { printf("  %-22s absente de la base\n", $a['nom']); continue; }
    if (trim((string)$o['adresse']) !== '') { printf("  %-22s a déjà une adresse\n", $a['nom']); continue; }
    printf("  %-22s ← %s%s, %s %s\n", $a['nom'], $a['chez'] ? '℅ ' . $a['chez'] . ' · ' : '',
           $a['adresse'], $a['cp'], $a['ville']);
    $n++;
    if ($ecrire) DB::run("UPDATE organisation SET chez=COALESCE(NULLIF(chez,''),?), adresse=?,
                            cp=COALESCE(NULLIF(cp,''),?), ville=COALESCE(NULLIF(ville,''),?)
                          WHERE id=?", [$a['chez'], $a['adresse'], $a['cp'], $a['ville'], (int)$o['id']]);
}
printf("\n  %d adresse(s) %s\n", $n, $ecrire ? 'reprises' : 'à reprendre');
