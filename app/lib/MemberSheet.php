<?php
/**
 * Fiche personnelle imprimable d'un collaborateur.   [V15-FICHE-PDF]
 *
 * Produit une page HTML autonome — sa mise en forme est écrite à l'intérieur —
 * pensée pour le papier autant que pour l'écran. Le bouton « Imprimer » ouvre
 * la fenêtre d'impression du navigateur, où la destination « Enregistrer au
 * format PDF » donne le PDF sans qu'aucune bibliothèque ne soit installée sur
 * le serveur. C'est volontaire : un hébergement mutualisé ne garantit ni les
 * extensions ni la mémoire qu'exigent les fabricants de PDF, alors que tous
 * les navigateurs savent imprimer en PDF depuis des années.
 *
 * Deux conséquences guident la feuille de style ci-dessous :
 *  - aucun aplat de couleur ne porte d'information, car les navigateurs
 *    n'impriment pas les fonds par défaut ; les séparations sont des filets ;
 *  - une rubrique a le droit de se poursuivre page suivante, mais son titre
 *    est rangé dans un « thead » que le navigateur réimprime en haut de cette
 *    page-là : on ne tombe jamais sur des réponses sans savoir à quoi.
 */
class MemberSheet
{
    /**
     * La page complète, prête à être envoyée au navigateur.
     *
     * @param array  $c        la ligne du collaborateur
     * @param string $lang     langue de la fiche ('fr' ou 'en')
     * @param string $backUrl  adresse du lien « retour » (vide = pas de lien)
     * @param string $backLbl  libellé de ce lien
     */
    public static function page(array $c, string $lang, string $backUrl = '', string $backLbl = ''): string
    {
        $id      = (int)$c['id'];
        $profile = MemberProfile::get($id);
        $data    = $profile['data'];
        $photo   = $profile['photo_image_id'] ? Img::row($profile['photo_image_id']) : null;
        $fields  = Forms::def('form_infos')['fields'] ?? [];

        $tr = fn(string $k): string => I18n::t($k, $lang);
        $titre = $tr('sheet_title') . ' — ' . ($c['name'] ?: $c['email']);

        $h  = "<!DOCTYPE html>\n<html lang=\"" . e($lang) . "\">\n<head>\n";
        $h .= "<meta charset=\"utf-8\">\n";
        $h .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
        $h .= "<meta name=\"robots\" content=\"noindex, nofollow\">\n";
        $h .= '<title>' . e($titre) . "</title>\n";
        $h .= "<style>\n" . self::css() . "</style>\n</head>\n<body>\n";

        // Barre d'outils : à l'écran seulement, jamais sur le papier.
        $h .= "<div class=\"bar\">\n";
        $h .= '  <button type="button" class="print" onclick="window.print()">' . e($tr('sheet_print')) . "</button>\n";
        $h .= '  <p class="tip">' . e($tr('sheet_tip')) . "</p>\n";
        if ($backUrl !== '') {
            $h .= '  <a class="back" href="' . e($backUrl) . '">' . e($backLbl) . "</a>\n";
        }
        $h .= "</div>\n";

        $h .= "<article class=\"sheet\">\n";

        // ---- En-tête -------------------------------------------------------
        $h .= "<header class=\"head\">\n";
        if ($photo) {
            Img::ensure($photo, 'square');
            $h .= '  <img class="portrait" src="' . e(Img::fileUrl($photo, 'square', 'jpg')) . '" alt="">' . "\n";
        }
        $h .= "  <p class=\"marque\">LE VOISIN</p>\n";
        $h .= '  <h1>' . e($tr('sheet_title')) . "</h1>\n";
        $h .= '  <p class="qui">' . e($c['name'] ?: $c['email']) . "</p>\n";
        $h .= "  <table class=\"ident\"><tbody>\n";
        $h .= self::ligne($tr('sheet_account_email'), (string)$c['email']);
        if (trim((string)$c['mobile']) !== '') {
            $h .= self::ligne($tr('sheet_account_mobile'), (string)$c['mobile']);
        }
        $h .= "  </tbody></table>\n";
        $h .= "</header>\n";

        // ---- Bio -----------------------------------------------------------
        if (trim((string)$profile['bio']) !== '') {
            $h .= "<section class=\"bloc\">\n";
            $h .= '  <h2>' . e($tr('sheet_bio')) . "</h2>\n";
            $h .= '  <p class="bio">' . nl2br(e($profile['bio'])) . "</p>\n";
            $h .= "</section>\n";
        }

        // ---- Les rubriques du formulaire, dans leur ordre d'origine ---------
        // Chaque rubrique est fabriquée à part puis n'est ajoutée que si elle
        // a fini par contenir quelque chose : une rubrique dont toutes les
        // lignes sont masquées par une condition ne laisse pas un titre seul.
        $titre_bloc = '';
        $lignes     = '';
        $vider = function () use (&$titre_bloc, &$lignes, &$h) {
            if ($lignes !== '') {
                $h .= "<section class=\"bloc\">\n" . self::table($titre_bloc, $lignes) . "</section>\n";
            }
            $lignes = '';
        };
        foreach ($fields as $fd) {
            if (($fd['type'] ?? '') === 'section') {
                $vider();
                $titre_bloc = e(Forms::label($fd['label'], $lang));
                continue;
            }
            $lignes .= self::champ($fd, $data, $lang, $tr);
        }
        $vider();

        // ---- Date et signature ---------------------------------------------
        // Une fiche personnelle finit souvent dans un dossier papier : autant
        // qu'elle porte l'endroit où la dater et la signer.
        $h .= "<section class=\"bloc signature\">\n";
        $h .= '  <div><span class="lbl">' . e($tr('sheet_place_date')) . "</span><span class=\"trait\"></span></div>\n";
        $h .= '  <div><span class="lbl">' . e($tr('sheet_signature')) . "</span><span class=\"trait\"></span></div>\n";
        $h .= "</section>\n";

        $h .= '<footer class="pied">' . e($tr('sheet_footer')) . ' ' . e(date('d.m.Y')) . "</footer>\n";
        $h .= "</article>\n</body>\n</html>\n";
        return $h;
    }

    /**
     * Le tableau d'une rubrique. Son titre est une ligne d'en-tête et non un
     * intertitre posé au-dessus : ainsi, quand la rubrique déborde sur la page
     * suivante, le navigateur réimprime ce titre en haut de celle-ci.
     *
     * @param string $titre  déjà échappé (il vient de Forms::label)
     * @param string $lignes les <tr> déjà fabriqués
     */
    private static function table(string $titre, string $lignes): string
    {
        $h = "  <table class=\"champs\">\n";
        if ($titre !== '') {
            $h .= '    <thead><tr><th class="titre" colspan="2">' . $titre . "</th></tr></thead>\n";
        }
        return $h . "    <tbody>\n" . $lignes . "    </tbody>\n  </table>\n";
    }

    /** Une ligne « libellé / valeur ». */
    private static function ligne(string $label, string $valeur, string $classe = ''): string
    {
        $cl = $classe !== '' ? ' class="' . $classe . '"' : '';
        return '    <tr><th>' . e($label) . '</th><td' . $cl . '>' . e($valeur) . "</td></tr>\n";
    }

    /**
     * Une ligne du formulaire. Les champs vides ne sont pas escamotés : une
     * fiche sert aussi à voir d'un coup d'œil ce qui manque encore.
     */
    private static function champ(array $fd, array $data, string $lang, callable $tr): string
    {
        $type = (string)($fd['type'] ?? 'text');
        $key  = (string)($fd['key'] ?? '');

        // Les champs « fichier » ne figurent pas dans les données : ce qui a
        // été déposé vit dans l'espace personnel et dans le CMS, où il se
        // télécharge. Une feuille de papier n'en retiendrait qu'un nom, et
        // un nom de fichier n'est pas la pièce.
        if ($type === 'file') return '';

        // Une question posée sous condition ne s'imprime que si la condition
        // est remplie : demander sa date de mariage à quelqu'un de célibataire
        // ferait passer une ligne sans objet pour une ligne oubliée.
        if (!empty($fd['show_if'])) {
            [$sk, $sv] = $fd['show_if'];
            if (!in_array(trim((string)($data[$sk] ?? '')), (array)$sv, true)) return '';
        }

        $label = Forms::label($fd['label'], $lang);
        $v     = trim((string)($data[$key] ?? ''));
        if ($v === '') {
            return self::ligne($label, empty($fd['required']) ? '—' : $tr('sheet_to_fill'), 'vide');
        }
        $v = self::valeur($fd, $v, $lang, $tr);
        if ($type === 'textarea') {
            return '    <tr><th>' . e($label) . '</th><td>' . nl2br(e($v)) . "</td></tr>\n";
        }
        return self::ligne($label, $v);
    }

    /**
     * Une réponse brute, rendue lisible : « yes » devient Oui, une date
     * s'écrit jour d'abord, un choix retrouve la langue demandée. Publique
     * parce que la fiche imprimée n'est pas seule à l'afficher — le CMS
     * montre les mêmes réponses dans la page du collaborateur, et les deux
     * doivent dire exactement la même chose.   [V16-DATES]
     *
     * @param callable|null $tr traducteur ; à défaut, t() dans la langue $lang
     */
    public static function valeur(array $fd, string $v, string $lang, ?callable $tr = null): string
    {
        if ($v === '') return '';
        $tr = $tr ?: fn(string $k): string => I18n::t($k, $lang);
        $type = (string)($fd['type'] ?? 'text');

        if ($type === 'yesno') {
            return $v === 'yes' ? $tr('form_yes') : ($v === 'no' ? $tr('form_no') : $v);
        }
        if ($type === 'date') {
            return Dates::afficher($v) ?: $v;   // 1990-04-07 → 07.04.1990
        }
        if ($type === 'select') {
            return self::choix($fd, $v, $lang);
        }
        return $v;
    }

    /**
     * Un menu déroulant enregistre le libellé tel qu'il était affiché, donc
     * dans la langue de la personne au moment où elle a répondu. Une fiche
     * demandée en anglais retrouve ici la version anglaise de sa réponse ;
     * si rien ne correspond, on garde ce qui a été enregistré plutôt que de
     * l'effacer.
     */
    private static function choix(array $fd, string $v, string $lang): string
    {
        foreach (Forms::options($fd) as $opt) {
            if (Forms::correspond($opt, $v)) {
                return Forms::label($opt, $lang) ?: $v;
            }
        }
        return $v;
    }

    /** La mise en forme, écrite dans la page pour qu'elle s'imprime partout. */
    private static function css(): string
    {
        return <<<'CSS'
:root { --encre:#111; --gris:#666; --filet:#d7d7d7; }
* { box-sizing:border-box; }
body { margin:0; padding:0 0 40px; background:#efefef; color:var(--encre);
       font:13px/1.5 -apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; }

/* Barre d'outils — écran seulement */
.bar { display:flex; align-items:center; gap:16px; flex-wrap:wrap;
       max-width:210mm; margin:0 auto; padding:18px 12px; }
.bar .print { border:2px solid var(--encre); background:#fff; color:var(--encre);
       font:600 13px/1 inherit; letter-spacing:.04em; text-transform:uppercase;
       padding:12px 20px; border-radius:2px; cursor:pointer; }
.bar .print:hover { background:var(--encre); color:#fff; }
.bar .tip { margin:0; color:var(--gris); font-size:12px; flex:1 1 240px; }
.bar .back { color:var(--gris); text-decoration:none; font-size:12px; }
.bar .back:hover { color:var(--encre); text-decoration:underline; }

/* La feuille */
.sheet { max-width:210mm; margin:0 auto; padding:18mm 16mm; background:#fff;
         box-shadow:0 2px 14px rgba(0,0,0,.14); }

.head { position:relative; border-bottom:2px solid var(--encre);
        padding-bottom:14px; margin-bottom:22px; min-height:34mm;
        break-inside:avoid; page-break-inside:avoid; }
.head .portrait { float:right; width:30mm; height:30mm; object-fit:cover;
        margin:0 0 10px 14px; border:1px solid var(--filet); }
.head .marque { margin:0; font-size:11px; letter-spacing:.34em; font-weight:700; }
.head h1 { margin:14px 0 2px; font-size:21px; letter-spacing:.02em; }
.head .qui { margin:0 0 12px; font-size:16px; font-weight:600; }
.ident { border-collapse:collapse; }
.ident th, .ident td { text-align:left; font-weight:400; padding:1px 0; font-size:12px; }
.ident th { color:var(--gris); padding-right:12px; white-space:nowrap; }

/* Une rubrique peut se poursuivre sur la page suivante — sans quoi une
   rubrique un peu longue laisse un tiers de page blanc derrière elle. Deux
   précautions rendent la coupure inoffensive : le titre de rubrique est une
   ligne d'en-tête de tableau, que le navigateur réimprime en haut de la page
   suivante, et aucune ligne n'est jamais coupée en deux. */
.bloc { margin:0 0 18px; }
.bloc h2, .champs th.titre {
           margin:0 0 6px; font-size:11px; letter-spacing:.16em; text-transform:uppercase;
           color:var(--gris); border-bottom:1px solid var(--filet); padding-bottom:4px;
           break-after:avoid; page-break-after:avoid; }
.bio { margin:0; }

.champs { width:100%; border-collapse:collapse; }
.champs thead { display:table-header-group; }
.champs tr { break-inside:avoid; page-break-inside:avoid; }
.champs th, .champs td { text-align:left; vertical-align:top; padding:5px 0;
           border-bottom:1px solid var(--filet); }
.champs th { width:44%; font-weight:400; color:var(--gris); padding-right:14px; }
.champs th.titre { width:auto; padding:0 0 4px; }
.champs td { font-weight:600; }
.champs td.vide, p.vide { font-weight:400; font-style:italic; color:#999; }
p.vide { margin:4px 0 0; }

.signature { display:flex; gap:24px; margin-top:26px;
             break-inside:avoid; page-break-inside:avoid; }
.signature > div { flex:1; }
.signature .lbl { display:block; font-size:11px; color:var(--gris); margin-bottom:22px; }
.signature .trait { display:block; border-bottom:1px solid var(--encre); }

.pied { margin-top:20px; padding-top:8px; border-top:1px solid var(--filet);
        font-size:10px; color:var(--gris); }

@page { size:A4; margin:14mm 14mm 16mm; }
@media print {
  body { background:#fff; padding:0; }
  .bar { display:none !important; }
  .sheet { max-width:none; margin:0; padding:0; box-shadow:none; }
  a { color:inherit; text-decoration:none; }
}
CSS;
    }
}
