<?php
/**
 * Le catalogue des treize skills. [16.08.2026]
 *
 *   php db/seed_skills.php
 *
 * Repris des SKILL.md du dépôt de travail, pas résumé de mémoire. Ce que
 * chacune LIT et ÉCRIT vient de la mesure du 16.08.2026 sur leurs fichiers:
 * c'est l'information qui manque le plus quand une skill rend un mauvais
 * résultat, parce que la cause est presque toujours un fichier de contexte
 * faux et non la skill elle-même.
 *
 * IDEMPOTENT par `nom`.
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

const SKILLS = [
 ['devis','metier','Devis de cession',
  'Calcule un devis de cession à partir du nombre de représentations demandé: jours de travail, cachets, logistique et marge, puis la grille de 1 à 6 représentations et la vue de négociation.',
  '_contexto/artistes.md · baremes.md · tarifs_personnes.md · modele_donnees_documents.md · arborescence.md',
  'devis/ · le Drive de l’association'],

 ['difusao','metier','Stratégie de diffusion',
  'Écrit la stratégie de diffusion d’une pièce: positionnement, cibles à trois niveaux, calendrier ajusté au cycle des saisons, objectifs chiffrés depuis la grille tarifaire. Deux versions, une pour l’artiste et une pour le dossier.',
  'dashboard lv-tour, lv-contacts · _contexto/comparaveis.md',
  'dados/difusao/'],

 ['prospetar','metier','Prospection mensuelle',
  'Trouve salles, festivals et résidences en partant de là où les artistes comparables ont joué. Lit les agendas publics, croise avec ce qui a déjà été contacté, et rend une liste avec l’argument à utiliser à chaque porte.',
  '_contexto/comparaveis.md · dashboard lv-tour, lv-contacts · agendas publics',
  'dados/prospecao/ · tarefas.md'],

 ['vigiar','metier','Veille des appels',
  'Surveille les profils Instagram des salles et festivals, et les plateformes d’appels à projets. Ne rend que ce qui ouvre une porte pour une pièce du roster, avec le délai réel de dépôt.',
  '_contexto/vigia_fontes.md · artistes.md · Instagram',
  'dados/vigia/ · tarefas.md'],

 ['organizar','metier','Répartition des tâches',
  'Transforme un vidage de tout ce qui est en tête en liste priorisée, répartie entre Anna, Mirta et Alessandra selon la fenêtre de disponibilité de chacune.',
  '_contexto/empresa.md, preferencias.md, agora.md · dashboard lv-tasks',
  'tarefas.md'],

 ['fechar','metier','Clôture du jour',
  'Mesure ce qui a vraiment changé, biffe dans tarefas.md ce qui a une preuve, écrit le journal du jour et monte la liste du lendemain. Ne biffe jamais de mémoire: si l’item est du serveur, il mesure le serveur.',
  'git · le serveur · dashboard lv-tasks-unifiees',
  '_tarefas/diario/ · tarefas.md'],

 ['ditar','metier','Dictée',
  'Capture le micro avec détection de langue (PT, EN, FR, ES, IT), transcrit, et propose l’intégration aux modèles de facture, de devis et de projet.',
  'micro · Whisper',
  'transcriptions · Google Sheets'],

 ['iniciar','systeme','Ouvrir la session',
  'Lit le contexte du métier au début d’une session et aide à démarrer.',
  '_contexto/*.md · tarefas.md', '—'],

 ['atualizar','systeme','Mettre le contexte à jour',
  'Balaie l’état du projet et met à jour les fichiers de contexte devenus faux, en comparant ce qui existe avec ce qui est documenté.',
  'les dossiers, les skills, git', '_contexto/*.md · AGENTS.md'],

 ['syncar','systeme','Sauvegarder sur GitHub',
  'Commit et push du dépôt de travail. Attention: il fait `git add -A`, donc il emporte le travail non terminé d’une autre session ouverte au même endroit.',
  'git', 'GitHub'],

 ['mapear','systeme','Cartographier les processus',
  'Interroge sur les processus répétitifs et crée les dossiers et skills du quotidien. À lancer après /setup.',
  '_contexto/empresa.md, preferencias.md', '.claude/skills/'],

 ['novo-projeto','systeme','Nouveau projet',
  'Crée un dossier de projet avec son AGENTS.md, et le référence dans le principal.',
  '_contexto/empresa.md', 'un dossier de projet'],

 ['setup','systeme','Configuration initiale',
  'Configure le Claude Code OS pour le métier: AGENTS.md, mémoire, structure de dossiers, liste de MCP.',
  'les réponses de l’entretien', 'AGENTS.md · _contexto/ · la passerelle Codex'],
];

$n = 0;
foreach (SKILLS as $i => [$nom, $fam, $titre, $res, $lit, $ecrit]) {
    $v = ['nom'=>$nom, 'famille'=>$fam, 'titre'=>$titre, 'resume'=>$res,
          'lit'=>$lit, 'ecrit'=>$ecrit, 'ordre'=>($i + 1) * 10];
    $ex = DB::one('SELECT id FROM skill WHERE nom = ?', [$nom]);
    if ($ex) {
        DB::update('skill', $v, 'id = ?', [(int)$ex['id']]);
    } else {
        DB::insert('skill', $v); $n++;
    }
}
printf("%d skills ajoutées, %d au catalogue\n", $n, (int)DB::val('SELECT COUNT(*) FROM skill'));
echo "Elles ne se lancent pas depuis ici: elles tournent dans le Claude Code, sur le Mac.\n";
