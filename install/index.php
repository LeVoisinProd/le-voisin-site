<?php
/**
 * Le Voisin — assistant d'installation.
 * À SUPPRIMER (le dossier /install) une fois l'installation terminée.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
$ROOT = dirname(__DIR__);

if (is_file($ROOT . '/config.php')) {
    exit('Le site est déjà installé. Supprimez le dossier /install (et config.php si vous voulez réinstaller).');
}

// ---- Vérification des prérequis ----
$checks = [
    'PHP 8.0 ou plus'            => PHP_VERSION_ID >= 80000,
    'Extension PDO MySQL'        => extension_loaded('pdo_mysql'),
    'Extension GD (images)'      => extension_loaded('gd'),
    'GD : support WebP'          => function_exists('imagewebp'),
    'Extension mbstring'         => extension_loaded('mbstring'),
    'Extension curl (vidéos)'    => extension_loaded('curl'),
    'Dossier racine inscriptible (config.php)' => is_writable($ROOT),
    'Dossier uploads inscriptible' => is_writable($ROOT . '/uploads'),
];
$blocking = !$checks['PHP 8.0 ou plus'] || !$checks['Extension PDO MySQL'] || !$checks['Extension GD (images)']
    || !$checks['Dossier racine inscriptible (config.php)'] || !$checks['Dossier uploads inscriptible'];

// ---- Auto-détection de l'URL ----
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$autoUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocking) {
    $db = [
        'host' => trim($_POST['db_host'] ?? 'localhost'),
        'name' => trim($_POST['db_name'] ?? ''),
        'user' => trim($_POST['db_user'] ?? ''),
        'pass' => (string)($_POST['db_pass'] ?? ''),
        'charset' => 'utf8mb4',
    ];
    $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');
    $adminEmail = mb_strtolower(trim($_POST['admin_email'] ?? ''));
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $withSeed = !empty($_POST['seed']);
    $withDemoImages = !empty($_POST['demo_images']);

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse email administrateur invalide.';
    if (mb_strlen($adminPass) < 10) $errors[] = 'Le mot de passe administrateur doit faire au moins 10 caractères.';
    if (!preg_match('~^https?://~', $baseUrl)) $errors[] = 'L\'URL du site doit commencer par http:// ou https://';

    $pdo = null;
    if (!$errors) {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['name']),
                $db['user'], $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Throwable $ex) {
            $errors[] = 'Connexion à la base impossible : ' . $ex->getMessage();
        }
    }

    if (!$errors && $pdo) {
        try {
            foreach (['schema.sql', $withSeed ? 'seed.sql' : null] as $file) {
                if (!$file) continue;
                $sql = str_replace("\r\n", "\n", (string)file_get_contents(__DIR__ . '/' . $file));
                $sql = preg_replace('~^[ \t]*--[^\n]*~m', '', $sql); // retire les lignes de commentaire (sinon elles « collent » à l'instruction suivante)
                foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
                    if ($stmt !== '') $pdo->exec($stmt);
                }
            }
            // Compte administrateur
            $st = $pdo->prepare('INSERT INTO users (email, name, pass_hash) VALUES (?, ?, ?)
                                 ON DUPLICATE KEY UPDATE pass_hash = VALUES(pass_hash)');
            $st->execute([$adminEmail, 'Administrateur', password_hash($adminPass, PASSWORD_DEFAULT)]);

            // config.php
            $conf = "<?php\nreturn [\n"
                . "    'db' => [\n"
                . "        'host' => " . var_export($db['host'], true) . ",\n"
                . "        'name' => " . var_export($db['name'], true) . ",\n"
                . "        'user' => " . var_export($db['user'], true) . ",\n"
                . "        'pass' => " . var_export($db['pass'], true) . ",\n"
                . "        'charset' => 'utf8mb4',\n"
                . "    ],\n"
                . "    'base_url' => " . var_export($baseUrl, true) . ",\n"
                . "    'languages' => ['en', 'fr'],\n"
                . "    'debug' => false,\n"
                . "    'secret' => " . var_export(bin2hex(random_bytes(24)), true) . ",\n"
                . "];\n";
            file_put_contents($ROOT . '/config.php', $conf);

            // Images de démonstration
            if ($withSeed && $withDemoImages) {
                require $ROOT . '/app/bootstrap.php';
                I18n::init();
                require __DIR__ . '/demo-images.php';
                lv_demo_images();
            }
            $done = true;
        } catch (Throwable $ex) {
            $errors[] = 'Erreur pendant l\'installation : ' . $ex->getMessage();
        }
    }
}

/**
 * Le crochet et la croix des prérequis. [V33-VECTORIEL]
 * Ils étaient écrits avec des caractères que les téléphones vont chercher dans
 * leur police d'émojis en couleur : on les dessine, comme partout ailleurs sur
 * le site. Ici le dessin est écrit sur place plutôt que par la classe Ico :
 * cette page-là s'affiche avant que le site existe, sans ses feuilles de style
 * ni ses classes.
 */
function chk(bool $ok): string
{
    $couleur = $ok ? '#1d7a3f' : '#b3311f';
    $trace   = $ok
        ? '<path d="m2.6 7.4 3 3 5.8-6.8"/>'
        : '<path d="m3.4 3.4 7.2 7.2M10.6 3.4 3.4 10.6"/>';
    return '<svg viewBox="0 0 14 14" width="15" height="15" aria-hidden="true" focusable="false"'
         . ' style="vertical-align:-.16em;fill:none;stroke:' . $couleur . ';stroke-width:1.8;'
         . 'stroke-linecap:round;stroke-linejoin:round">' . $trace . '</svg>';
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Installation — Le Voisin CMS</title>
<link rel="stylesheet" href="../assets/css/fonts.css">
<style>
body { font-family: "Space Grotesk", "Helvetica Neue", Helvetica, Arial, sans-serif; background:#000; color:#000; margin:0; padding:40px 20px; }
.box { max-width: 720px; margin: 0 auto; background:#fff; border:3px solid #fff; padding:34px 40px; }
h1 { font-size:22px; letter-spacing:.14em; } h1 span { color:#71716b; font-weight:400; letter-spacing:normal; font-size:15px; }
h2 { font-size:14px; text-transform:uppercase; letter-spacing:.1em; margin-top:30px; }
table { width:100%; border-collapse:collapse; font-size:14px; }
td { padding:6px 4px; border-top:1px solid #eee; }
label { display:block; font-weight:700; font-size:13.5px; margin:16px 0 5px; }
input[type=text],input[type=email],input[type=password]{ width:100%; padding:10px 13px; border:2px solid #000; border-radius:0; box-sizing:border-box; font-size:15px; font-family:inherit; }
.row { display:grid; grid-template-columns:1fr 1fr; gap:0 22px; }
.btn { background:#fff; color:#000; border:3px solid #000; border-radius:99px; padding:12px 34px; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; cursor:pointer; margin-top:26px; }
.btn:hover { background:#000; color:#fff; }
.err { background:#fdeae7; color:#b3311f; border:1px solid #f3c3ba; padding:12px 16px; border-radius:6px; margin:18px 0; font-size:14px; }
.ok { background:#e5f3ea; color:#1d7a3f; border:1px solid #bfe0cb; padding:16px 20px; border-radius:6px; }
.hint { color:#71716b; font-size:12.5px; margin:4px 0 0; }
.check { display:flex; gap:10px; align-items:center; margin:14px 0; font-size:14px; }
.check input { width:18px; height:18px; }
a { color:#e2442f; }
</style>
</head>
<body>
<div class="box">
<h1>LE&nbsp;VOISIN <span>— installation du CMS</span></h1>

<?php if ($done): ?>
  <div class="ok">
    <strong>Installation terminée !</strong><br><br>
    1. <strong>Supprimez le dossier <code>/install</code></strong> du serveur (important).<br>
    2. Connectez-vous à l'administration : <a href="../admin/">ouvrir l'administration</a><br>
    3. Renseignez les destinataires des formulaires dans <em>Réglages</em>.
  </div>
<?php else: ?>

  <h2>Prérequis serveur</h2>
  <table>
    <?php foreach ($checks as $lbl => $ok): ?>
    <tr><td><?= htmlspecialchars($lbl) ?></td><td style="text-align:right"><?= chk($ok) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$checks['GD : support WebP']): ?>
  <p class="hint">Sans WebP, les images seront servies en JPEG (le site fonctionne quand même).</p>
  <?php endif; ?>

  <?php if ($blocking): ?>
  <div class="err">Certains prérequis bloquants ne sont pas remplis — contactez votre hébergeur.</div>
  <?php else: ?>

  <?php foreach ($errors as $er): ?><div class="err"><?= htmlspecialchars($er) ?></div><?php endforeach; ?>

  <form method="post">
    <h2>Base de données MySQL</h2>
    <div class="row">
      <div><label>Serveur</label><input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"></div>
      <div><label>Nom de la base</label><input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required></div>
      <div><label>Utilisateur</label><input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required></div>
      <div><label>Mot de passe</label><input type="password" name="db_pass" value=""></div>
    </div>

    <h2>Site</h2>
    <label>URL du site (sans slash final)</label>
    <input type="text" name="base_url" value="<?= htmlspecialchars($_POST['base_url'] ?? $autoUrl) ?>" required>
    <p class="hint">Exemple : https://www.le-voisin.com</p>

    <h2>Compte administrateur</h2>
    <div class="row">
      <div><label>Email</label><input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required></div>
      <div><label>Mot de passe (10 caractères min.)</label><input type="password" name="admin_pass" required minlength="10"></div>
    </div>

    <h2>Contenu initial</h2>
    <label class="check"><input type="checkbox" name="seed" value="1" checked>
      Installer la structure du site (pages, catégories) et le contenu de démonstration</label>
    <label class="check"><input type="checkbox" name="demo_images" value="1" checked>
      Générer des images de démonstration (remplaçables ensuite dans le CMS)</label>

    <button class="btn" type="submit">Installer</button>
  </form>
  <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
