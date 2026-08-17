<?php
/**
 * Les cinq onglets de la fiche d'association. [16.08.2026]
 *
 * Infos · LAA|LPP|AMPG · AVS · Impôt Source · Impôt Direct. Repris du
 * dashboard Apps Script, où ils portent la conformité suisse — « la seule
 * capacité qu'aucun des dix-sept logiciels du marché ne couvre », et l'écran
 * qu'Alessandra ouvre tous les mois.
 *
 * LES ONGLETS SONT EN CSS PUR, par des boutons radio cachés. Pas de JavaScript:
 * un formulaire administratif doit se remplir même quand un script casse, et
 * celui-ci porte des numéros AVS et des références fiscales qu'on ne resaisit
 * pas deux fois de bonne humeur.
 *
 * UN SEUL FORMULAIRE POUR LES CINQ ONGLETS, donc un seul « Enregistrer ». Cinq
 * formulaires donneraient cinq enregistrements partiels, et l'on ne saurait
 * plus lequel a été fait. Les grilles à cocher et les comptes par canton sont
 * à part, dans _assoc_grilles.php: ce sont des lignes, pas des champs, et le
 * HTML interdit d'imbriquer un formulaire dans un autre.
 *
 * Attend $v (le lecteur de champ), $err, $id.
 */
declare(strict_types=1);
/** @var callable $v */ /** @var array $err */ /** @var int $id */

?>

<?php /* ═══════════════════════ INFOS ═══════════════════════ */ ?>
<div class="pane pane-infos">
  <div class="grille">
    <div class="titre-bloc">Infos légales</div>
    <?php
    ch('genre', 'Nature', $v('genre') ?: 'artiste', $err, ['type'=>'select','choix'=>$GENRES,
       'aide' => 'Association: une entité juridique de la maison']);
    ch('nom', 'Nom', $v('nom'), $err, ['requis'=>true]);
    ch('nom_legal', 'Nom légal', $v('nom_legal'), $err, ['aide'=>'S\'il diffère du nom d\'usage']);
    ch('forme_juridique', 'Forme juridique', $v('forme_juridique'), $err,
       ['placeholder'=>'Association (CH), SARL, association loi 1901…']);
    ch('date_creation', 'Date de création', $v('date_creation'), $err, ['type'=>'date']);
    ch('statut', 'Statut', $v('statut') ?: 'actif', $err, ['type'=>'select','choix'=>$STATUTS]);
    ch('discipline', 'Discipline', $v('discipline'), $err);
    ch('debut_collab', 'Début de collaboration', $v('debut_collab'), $err, ['type'=>'date']);

    ch('ide', 'N° IDE', $v('ide'), $err, ['placeholder'=>'CHE-123.456.789',
       'aide'=>'Avec ou sans les points, il est remis en forme']);
    ch('registre', 'N° RC (registre)', $v('registre'), $err);
    ch('avs_employeur', 'N° AVS employeur', $v('avs_employeur'), $err);
    ch('ree', 'N° REE', $v('ree'), $err);
    ch('reference_poste', 'Référence La Poste CH', $v('reference_poste'), $err,
       ['placeholder'=>'48.26.xxxxxx.xxxxxxxx']);
    ch('siret', 'SIRET', $v('siret'), $err, ['aide'=>'Les entités françaises']);

    ch('chez', 'Chez (c/o)', $v('chez'), $err,


       ['placeholder'=>'Le nom sur la boîte aux lettres, s\'il n\'est pas celui de l\'association']);

    ch('adresse', 'Adresse', $v('adresse'), $err, ['large'=>true]);
    ch('cp', 'Code postal', $v('cp'), $err, ['placeholder'=>'1205, 75009…']);
    ch('ville', 'Ville', $v('ville'), $err, ['placeholder'=>'Genève, Paris…']);
    /* « Canton / Région » et non « Canton »: le carnet porte maintenant le
       Brésil, le Portugal et la France, où le mot n'existe pas. Un champ dont
       le nom ne s'applique pas à la moitié des fiches reste vide sur cette
       moitié — pas parce qu'on n'a pas l'information, parce qu'on ne sait pas
       qu'elle va là. [17.08.2026] */
    ch('canton', 'Canton / Région', $v('canton'), $err,
       ['aide' => 'GE, VD, BE en Suisse · la région ou le département ailleurs']);
    ch('pays', 'Pays', $v('pays'), $err, ['aide'=>'Décide des obligations sociales et du A1']);

    echo '<div class="titre-bloc">Contact</div>';
    ch('contact_prenom', 'Prénom du contact', $v('contact_prenom'), $err);
    ch('contact_nom', 'Nom du contact', $v('contact_nom'), $err);
    ch('direction', 'Direction artistique', $v('direction'), $err);
    ch('email', 'E-mail', $v('email'), $err, ['type'=>'email']);
    ch('telephone', 'Téléphone', $v('telephone'), $err, ['placeholder'=>'+41 / +33…']);
    ch('site', 'Site web', $v('site'), $err, ['large'=>true]);
    ch('instagram', 'Instagram', $v('instagram'), $err, ['large'=>true]);
    ?>
  </div>

  <?php /* LES DEUX MOTS DE PASSE. Chiffrés en base par Crypto.php, masqués ici,
           et réservés à la direction. Le champ reste vide à l'affichage: on ne
           renvoie pas au navigateur un secret que personne n'a demandé à voir. */ ?>
  <?php if (dash_role() === 'direction'): ?>
    <div class="mdpbloc">
      <div class="titre-bloc">Mots de passe</div>
      <p class="aide-b">Chiffrés en base, comme les IBAN et les AVS des fiches personnelles:
         un dump de la base ne les rend pas. Laissez vide pour ne rien changer.
         <strong>Le meilleur endroit reste un gestionnaire de mots de passe</strong> — c'est
         là que vivent déjà ceux du FTP et du SSH.</p>
      <div class="grille">
        <?php
        ch('email_mdp', 'Mot de passe e-mail', '', $err,
           ['type'=>'password', 'placeholder'=> $v('email_mdp') !== '' ? '•••••••• enregistré' : 'aucun']);
        ch('instagram_mdp', 'Mot de passe Instagram', '', $err,
           ['type'=>'password', 'placeholder'=> $v('instagram_mdp') !== '' ? '•••••••• enregistré' : 'aucun']);
        ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="grille">
    <div class="titre-bloc">Coordonnées bancaires</div>
    <?php
    ch('banque_nom', 'Banque', $v('banque_nom'), $err);
    ch('banque_iban', 'IBAN', $v('banque_iban'), $err, ['large'=>true]);
    ch('banque_bic', 'BIC', $v('banque_bic'), $err);
    ch('devise_defaut', 'Devise par défaut', $v('devise_defaut') ?: 'CHF', $err,
       ['type'=>'select','choix'=>['CHF'=>'CHF','EUR'=>'EUR']]);

    echo '<div class="titre-bloc">Membres du comité et notes</div>';
    ch('comite', 'Membres du comité', $v('comite'), $err, ['type'=>'textarea','large'=>true,'rows'=>4,
       'aide'=>'Un par ligne: prénom, nom, rôle dans le comité']);
    ch('frais_booking', 'Frais de booking', $v('frais_booking'), $err);
    ch('marge_defaut', 'Marge par défaut', $v('marge_defaut'), $err);
    ch('notes', 'Notes', $v('notes'), $err, ['type'=>'textarea','large'=>true,'rows'=>4]);
    ?>
  </div>
</div>

<?php /* ══════════════════ LAA · LPP · AMPG ══════════════════ */ ?>
<div class="pane pane-laa">
  <div class="grille">
    <div class="titre-bloc">Assurances</div>
    <?php
    ch('rc_pro', 'RC professionnelle', $v('rc_pro'), $err, ['placeholder'=>'AXA, Zurich, Helvetia…']);
    ch('rc_police', 'N° de police RC', $v('rc_police'), $err);
    ch('laa', 'LAA', $v('laa') ?: 'non', $err, ['type'=>'select',
       'choix'=>['non'=>'Non souscrite','souscrite'=>'Souscrite','en_cours'=>'En cours']]);
    ch('lpp', 'LPP (2e pilier)', $v('lpp') ?: 'non', $err, ['type'=>'select',
       'choix'=>['non'=>'Non','oui'=>'Oui','en_cours'=>'En cours']]);
    ch('ampg', 'AMPG', $v('ampg') ?: 'non', $err, ['type'=>'select',
       'choix'=>['non'=>'Non souscrite','souscrite'=>'Souscrite','en_cours'=>'En cours']]);
    ch('assureur_laa', 'Assureur LAA', $v('assureur_laa'), $err, ['placeholder'=>'Suva, Zurich, AXA…']);
    ch('assureur_lpp', 'Assureur LPP', $v('assureur_lpp'), $err, ['placeholder'=>'Artes e Comoedia, Publica…']);
    ch('trianon', 'N° Trianon (LAA/LPP)', $v('trianon'), $err, ['placeholder'=>'Numéro gestionnaire Trianon']);
    ?>
    <?php /* Une zone de notes ici aussi. [16.08.2026] Anna: « em cada sous
         page deixar um campo para notes ». Une note rangée loin de ce qu'elle
         explique n'est pas relue au moment où elle servirait. */ ?>
    <?php ch('notes_laa', 'Notes sur les assurances', $v('notes_laa'), $err,
           ['type'=>'textarea','large'=>true,'rows'=>3,
            'placeholder'=>'Échéances, courtier, sinistres, ce qui reste à souscrire…']); ?>
  </div>
</div>

<?php /* ═══════════════════════ AVS ═══════════════════════ */ ?>
<div class="pane pane-avs">
  <div class="grille">
    <div class="titre-bloc">AVS — inscription</div>
    <?php
    ch('avs_inscription', 'N° inscription AVS', $v('avs_inscription'), $err,
       ['placeholder'=>'756.XXXX.XXXX.XX']);
    ch('caisse_avs', 'Caisse AVS', $v('caisse_avs'), $err, ['placeholder'=>'CCGC, Procap, FER…']);
    ch('convention_coll', 'Convention collective', $v('convention_coll'), $err,
       ['placeholder'=>'CCT danse suisse, CCNT…']);
    ?>
    <?php ch('notes_avs', 'Notes sur l\'AVS', $v('notes_avs'), $err,
           ['type'=>'textarea','large'=>true,'rows'=>3,
            'placeholder'=>'Caisse, numéro d\'affilié, changements, décomptes en cours…']); ?>
  </div>
</div>

<?php /* ═══════════════════ IMPÔT SOURCE ═══════════════════ */ ?>
<div class="pane pane-is">
  <div class="avis-b">L'impôt à la source se déclare dans <strong>chaque canton de
    résidence des employé·e·s</strong>, pas dans celui du siège. Une association qui engage
    quelqu'un à Vaud et quelqu'un à Berne a deux comptes, et les oublier coûte une amende.
    Ajoutez un compte par canton concerné.</div>
</div>

<?php /* ═══════════════════ IMPÔT DIRECT ═══════════════════ */ ?>
<div class="pane pane-idirect">
  <div class="deux-cartes">
    <div class="carte ch">
      <h4>Inscription fiscale suisse</h4>
      <div class="grille">
        <?php
        ch('canton_fiscal', 'Canton fiscal', $v('canton_fiscal'), $err, ['type'=>'select',
           'choix'=>array_merge(['' => '— Choisir —'], array_combine(CANTONS, CANTONS))]);
        ch('contribuable_cant', 'N° contribuable cantonal', $v('contribuable_cant'), $err,
           ['placeholder'=>'N° attribué par le canton']);
        ch('tva_ch', 'Assujetti TVA (CH)', $v('tva_ch') ?: 'non', $err, ['type'=>'select',
           'choix'=>['non'=>'Non / Exonéré','oui'=>'Oui']]);
        ch('tva_ch_num', 'N° TVA', $v('tva_ch_num'), $err, ['placeholder'=>'CHE-xxx.xxx.xxx MWST']);
        ch('notes_fisc_ch', 'Notes fiscales CH', $v('notes_fisc_ch'), $err,
           ['type'=>'textarea','large'=>true,'rows'=>3,'placeholder'=>'Régime fiscal, exonérations…']);
        ?>
      </div>
    </div>
    <div class="carte fr">
      <h4>Inscription fiscale France</h4>
      <div class="grille">
        <?php
        ch('rna', 'N° RNA / association', $v('rna'), $err, ['placeholder'=>'W123456789']);
        ch('urssaf', 'N° URSSAF', $v('urssaf'), $err, ['placeholder'=>'N° établissement URSSAF']);
        ch('audiens', 'N° AUDIENS', $v('audiens'), $err, ['placeholder'=>'N° adhérent Audiens']);
        ch('tva_fr', 'Assujetti TVA (FR)', $v('tva_fr') ?: 'non', $err, ['type'=>'select',
           'choix'=>['non'=>'Non / Exonéré','oui'=>'Oui']]);
        ch('tva_fr_num', 'N° TVA intracommunautaire', $v('tva_fr_num'), $err,
           ['placeholder'=>'FR xx xxx xxx xxx']);
        ch('notes_fisc_fr', 'Notes fiscales FR', $v('notes_fisc_fr'), $err,
           ['type'=>'textarea','large'=>true,'rows'=>3,'placeholder'=>'DGFiP, régime, notes…']);
        ?>
      </div>
    </div>
  </div>

  <h4 class="sect-h">Délais de dépôt — déclaration annuelle</h4>
  <div class="tbl"><table>
    <thead><tr><th>Canton</th><th>Délai ordinaire</th></tr></thead>
    <tbody>
    <?php foreach (DELAIS_CANTON as $c => $d): ?>
      <tr><td><?= e($c) ?></td><td class="sec"><?= e($d) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="aide-b">Dates indicatives — des prolongations sont presque toujours possibles, et
     se demandent auprès de l'administration cantonale. En France, selon le régime,
     généralement en mai.</p>
</div>
