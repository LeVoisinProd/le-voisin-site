<?php
/**
 * Retiré.                                       [DIAG-500] [13.08.2026, soir]
 *
 * Cette page a servi une fois, le soir du 13.08, à chercher d'où venait un 500
 * qui ne se lisait pas dans le code. Le défaut trouvé, elle n'a plus de raison
 * d'être : elle affichait des chemins du serveur et la liste des classes
 * chargées, ce qui n'a rien à faire sur un site en production.
 *
 * ELLE EST VIDÉE PLUTÔT QUE SUPPRIMÉE parce que l'installateur écrit et
 * n'efface pas. Un fichier qu'on croit parti et qui répond encore est pire que
 * pas de fichier du tout : ce squelette-là, lui, ne répond rien.
 *
 * Pour la refaire un jour, elle est dans l'historique — `git show dfa1bd8`.
 * L'idée tient en une phrase : écrire ce qu'on va tenter AVANT de le tenter, et
 * vider le tampon, pour que la dernière ligne affichée nomme l'étape fautive.
 */
http_response_code(404);
exit('Not found');
