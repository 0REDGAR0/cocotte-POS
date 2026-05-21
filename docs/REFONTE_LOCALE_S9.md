# Refonte locale S9 - etat apres validation XAMPP

## Objectif de la passe

Cette passe vise a fiabiliser le projet sous XAMPP avec SQLite local, sans refonte UI ni changement de payloads JSON.

## Passe ergonomie iPad

Une passe locale de retouches iPad limitees a ete ajoutee sans toucher a SQLite, aux payloads JSON ni aux endpoints PHP existants.

## Mise a jour locale - pause mode sombre et tableaux

Une mise a jour locale supplementaire a ete appliquee pour retirer temporairement le mode sombre visible et stabiliser les tableaux de service.

Principes retenus pour cette passe:

- retrait du bouton de theme sombre sur toutes les pages principales
- desactivation de la logique JS de theme sombre dans `js/server_status.js`
- purge de la preference `cocotte-theme` pour eviter une reactivation locale residuelle
- conservation du bouton refresh iPad dans le header
- ordre du header maintenu en `Internet | Batterie | Heure | Refresh`
- colonnes de tableaux stabilisees avec largeurs coherentes et alignements explicites
- `table-layout: fixed` applique a la table caisse
- grilles harmonisees sur `caisse`, `bar`, `cuisine`, `snack`, `statut` et `archive`
- remplacement des rendus `BAR / CUISINE / SNACK` et `EN_COURS / PRET / TERMINE` en cellules par des badges sobres avec vrai symbole, `title` et `aria-label`
- conservation du chrono `⏱` pour les commandes

Notes de rendu:

- sur ecran large, les badges affichent symbole + libelle
- sur format iPad / compact, le libelle peut etre masque pour garder des colonnes stables, mais le symbole reste accompagne de `title` et `aria-label`
- aucun endpoint PHP, aucune logique metier et aucune structure SQLite n'ont ete modifies

Principes retenus:

- ajout d'un bouton de rechargement force dans le header des pages principales
- ajout d'un cache-buster `?r=timestamp` pour contourner plus facilement le cache Safari/iPad
- ajout temporaire d'un suffixe de dev `?v=dev-20260508-03` sur `style.css` et `server_status.js` pour forcer le chargement des derniers assets pendant les tests iPad
- header serveur conserve dans l'ordre `Internet | Batterie | Heure`
- heure stabilisee visuellement avec largeur fixe et chiffres tabulaires
- caisse rendue legerement plus compacte sans changer son fonctionnement
- panneau "Dernieres commandes" maintenu a droite sur `bar`, `cuisine` et `snack`
- fermeture du panneau historique fiabilisee
- auto-mode admin rendu plus lisible visuellement selon actif / inactif
- bloc header aligne proprement a droite en sequence `Internet | Batterie | Heure | Mode | Refresh`
- ajout d'un mode sombre manuel memorise en `localStorage`
- finition du mode sombre sur tableaux, cartes, historiques, archive, admin et popups

Limites connues de cette version:

- certaines tablettes conservent encore fortement le cache navigateur, d'ou l'interet du bouton refresh
- sur Safari/iPad, un simple reload visuel peut reutiliser des assets caches ; le bouton `↻` reconstruit une URL avec `?r=...` pour forcer la navigation
- les retouches sont volontairesment limitees et ne refondent ni les vues ni la logique metier

### Procedure refresh iPad

- toucher le bouton `↻` dans le header
- le bouton passe brievement en etat de rechargement
- la page est rechargee avec un parametre `?r=timestamp`
- les suffixes `?v=dev-20260508-03` sur `style.css` et `server_status.js` sont temporaires et servent uniquement au developpement / debuggage cache Safari

### Mode sombre manuel

- un bouton `◐` est ajoute dans le meme groupe que le statut serveur du header
- l'ordre cible devient `Internet | Batterie | Heure | Mode | Refresh`
- le theme sombre ajoute une classe `theme-dark` sur `body`
- le choix utilisateur est memorise dans `localStorage`
- le mode sombre continue de fonctionner sans Internet
- les tableaux, panneaux d'historique, cartes commandes et formulaires doivent maintenant suivre le meme theme sombre

### Limites connues

- le suffixe `?v=dev-20260508-03` est provisoire et devra etre retire ou normalise hors phase de dev
- la validation finale du mode sombre et de l'alignement du header reste a confirmer sur iPad reel, surtout avec le cache Safari

## Etat serveur dans le header

Un statut serveur compact est maintenant integre dans le header existant des pages principales.

Ordre d'affichage:

- `Internet`
- `Batterie`
- `HH:MM:SS`

Le rendu est pilote par:

- `php/get_server_status.php`
- `js/server_status.js`

Le header reste unique. L'ancienne barre d'etat separee n'est plus utilisee sur les pages principales.

### Heure serveur

- l'heure affichee provient du serveur via `php/get_server_status.php`
- format retourne: `HH:MM:SS`
- le navigateur ne calcule plus l'heure a partir de son horloge locale comme source de verite
- le JS fait seulement avancer l'affichage entre deux rafraichissements serveur pour garder les secondes fluides

### Internet serveur

- le test Internet est fait cote serveur
- il utilise un test TCP tres court vers des cibles publiques
- timeout court pour ne pas bloquer le POS si Internet est indisponible
- valeurs retournees:
  - `online`
  - `offline`
  - `unknown`

Ce statut reste indicatif. Le POS continue de fonctionner en local meme si Internet est absent.

### Batterie serveur

La batterie affichee concerne l'appareil qui heberge le site, pas l'appareil client qui consulte la page.

Important:

- `navigator.getBattery()` n'est pas utilise
- aucune batterie client n'est lue par le front

Payload retourne:

- `battery_status`: `charging` / `discharging` / `full` / `unknown`
- `battery_level_band`: `full` / `medium` / `low` / `critical` / `unknown`
- `battery_available`: `true` / `false`

La tranche batterie est deduite d'un pourcentage eventuel:

- `>= 80`: `full`
- `>= 40`: `medium`
- `>= 20`: `low`
- `< 20`: `critical`

Le pourcentage exact n'est pas affiche dans l'interface.

### Limites sous XAMPP / Windows

Sous XAMPP sur Windows:

- beaucoup de postes n'exposent aucune batterie au serveur web
- `WMIC` peut etre absent selon la version de Windows
- l'acces WMI / PowerShell peut etre indisponible ou restreint

Dans ces cas:

- `battery_available=false`
- `battery_status=unknown`
- `battery_level_band=unknown`

Le comportement attendu est donc proprement degrade, sans erreur bloquante.

### Preparation future Samsung S9+ / Termux

Le endpoint prevoit une tentative `termux-battery-status` si l'hebergement tourne plus tard sur Android / Termux.

Cela prepare une migration locale sur un Samsung S9+ sans changer le contrat JSON cote front.

## Ajustements ergonomie service

- le header conserve un seul niveau visuel avec:
  - logo a gauche
  - titre centre
  - statut serveur a droite
- le statut serveur privilegie maintenant des icones sobres:
  - reseau colore selon `online` / `offline` / `unknown`
  - batterie coloree selon disponibilite, charge et criticite
  - heure conservee en `HH:MM:SS`
- les labels texte du statut restent presents pour l'accessibilite, mais l'affichage privilegie les icones
- les timers de commande utilisent un symbole de temps plus lisible pendant le service
- les boutons `TERMINE` des postes `bar`, `cuisine` et `snack` sont agrandis pour etre visuellement plus faciles a viser sans changer la logique de clic

### Bouton Terminer pleine hauteur

- sur `bar`, `cuisine` et `snack`, les cartes de commande actives sont maintenant separees en:
  - une zone contenu a gauche
  - une zone action a droite
- le bouton `TERMINE` occupe toute la hauteur de la carte dans sa colonne d'action
- sur petit ecran, la zone action peut repasser sous le contenu avec le meme bouton pleine largeur

Etat cible atteint pour cette passe:

- SQLite local conserve dans `storage/pos.sqlite`
- schema versionne dans `database/schema.sql`
- endpoints PHP existants conserves
- chemins PHP fiabilises avec `__DIR__`
- scripts dev ajoutés pour initialiser, remplir et nettoyer la base
- flux principaux verifies par HTTP et par CLI

## Architecture en place

### Stockage

- base SQLite locale: `storage/pos.sqlite`
- schema: `database/schema.sql`
- mode auto provisoire hors base: `php/auto_mode.json`

### Backend

- point d'entree PDO unique: `php/config.php`
- initialisation: `php/init_sqlite.php`
- seed demo: `php/seed_demo.php`
- reset dev: `php/reset_dev.php`

### Frontend

Pages conservees:

- `index.html`
- `caisse.html`
- `bar.html`
- `cuisine.html`
- `snack.html`
- `statut.html`
- `archive.html`
- `admin.html`

## Fichiers touches dans cette passe

### Crees

- `php/seed_demo.php`
- `php/reset_dev.php`

### Modifies

- `admin.html`
- `php/ajouter_article.php`
- `php/ajouter_commande.php`
- `php/archiver.php`
- `php/gerer_articles.php`
- `php/get_articles.php`
- `php/get_auto_mode.php`
- `php/get_commandes.php`
- `php/get_next_commande_id.php`
- `php/modifier_article.php`
- `php/retablir_commande.php`
- `php/set_auto_mode.php`
- `php/supprimer_article.php`
- `php/test_pgsql.php`
- `php/update_statut.php`
- `php/vider_archive.php`
- `docs/REFONTE_LOCALE_S9.md`

## Procedure XAMPP

### Prerequis

- projet dans `C:\xampp\htdocs\POS`
- Apache demarre dans XAMPP
- extensions SQLite actives:
  - `pdo_sqlite`
  - `sqlite3`

### URLs utiles

- accueil:
  - `http://localhost/POS/`
- init SQLite:
  - `http://localhost/POS/php/init_sqlite.php`
- seed demo:
  - `http://localhost/POS/php/seed_demo.php`
- reset dev:
  - `http://localhost/POS/php/reset_dev.php`
- test connexion SQLite:
  - `http://localhost/POS/php/test_pgsql.php`

### Verification CLI utile

```bash
php -m | findstr /I sqlite
php php/init_sqlite.php
php php/seed_demo.php
```

## Scripts dev

### `php/init_sqlite.php`

Role:

- cree `storage/pos.sqlite` si absent
- applique `database/schema.sql`
- confirme les tables creees

Reexecution:

- sans danger

### `php/seed_demo.php`

Role:

- ajoute un jeu minimal d'articles coherents pour les tests
- met a jour les articles deja existants au lieu de les dupliquer

Jeu demo actuel:

- `Espresso`
- `Jus orange`
- `Burger d'Edgar`
- `Salade du jour`
- `Panini jambon`
- `Glace 2 boules`

Reexecution:

- sans danger
- si les articles existent deja, ils sont mis a jour

### `php/reset_dev.php`

Role:

- vide `commande_articles`
- vide `commandes`
- vide `articles`
- remet `auto_mode.json` a `false` partout

Usage:

- utile avant chaque campagne de tests XAMPP

## Ordre de test conseille sous XAMPP

1. Ouvrir `http://localhost/POS/php/reset_dev.php`
2. Ouvrir `http://localhost/POS/php/init_sqlite.php`
3. Ouvrir `http://localhost/POS/php/seed_demo.php`
4. Verifier `http://localhost/POS/php/test_pgsql.php`
5. Ouvrir `http://localhost/POS/admin.html`
6. Verifier les articles seedes
7. Ouvrir `http://localhost/POS/caisse.html`
8. Creer une commande contenant au moins:
   - un article bar
   - un article cuisine
   - un article snack
9. Ouvrir en parallele:
   - `http://localhost/POS/bar.html`
   - `http://localhost/POS/cuisine.html`
   - `http://localhost/POS/snack.html`
10. Verifier que chaque page role n'affiche que les articles concernes
11. Passer les statuts en `pret`
12. Verifier `http://localhost/POS/statut.html`
13. Archiver la commande
14. Verifier `http://localhost/POS/archive.html`
15. Tester la restauration partielle via les boutons `Recuperer` des pages roles
16. Tester la purge archive depuis `admin.html`

## Flux verifies

### Caisse

- lecture des articles: OK
- creation d'une commande multi-articles: OK

### Bar / Cuisine / Snack

- affichage des commandes concernees: OK via `get_commandes.php`
- passage en `pret`: OK
- remise en `en_cours` via `retablir_commande.php`: OK

### Statut

- suivi des commandes via `get_commandes.php`: OK

### Archive

- commande archivee visible avec statut `terminee`: OK
- purge archive via `vider_archive.php`: OK

Note:

- la restauration n'est pas pilotee depuis `archive.html`
- elle existe deja via `retablir_commande.php` et les historiques des pages roles

### Admin

- ajout article: OK
- modification article: OK
- suppression article: OK
- lecture/ecriture du mode auto: OK

### Auto mode

- activation role bar: OK
- effet constate a la creation de commande:
  - article bar cree directement en `pret`
  - article d'une autre categorie reste en `en_cours`

## Problemes corriges

1. Chemins PHP fragiles

- plusieurs scripts chargeaient `config.php` par chemin relatif simple
- tous les scripts actifs utilisent maintenant `require_once(__DIR__ . "/config.php")`

2. Lecture auto mode encore en chemin local direct

- `php/get_auto_mode.php` utilise maintenant le helper central `getAutoModePath()`

3. Bug admin sur noms d'articles avec apostrophe

- `admin.html` construisait les boutons `Modifier` / `Supprimer` avec du HTML inline fragile
- correction:
  - creation des boutons en DOM
  - binding avec `addEventListener`
- effet:
  - un article comme `Burger d'Edgar` ou `Sirop d'orgeat` ne casse plus l'interface admin

## Verifications effectuees

### Syntaxe PHP

- `php -l` sur tous les fichiers `php/*.php`

### Recherche de dependances PostgreSQL restantes

Recherche negative dans `php/`, `database/`, `js/`, et les pages HTML sur:

- `pgsql`
- `postgres`
- `192.168.1.32`
- `RETURNING`
- `::int`
- `NOW(`
- `ALTER SEQUENCE`

### Smoke tests HTTP

Valides via serveur PHP local sur:

- chargement de toutes les pages HTML principales
- `reset_dev.php`
- `init_sqlite.php`
- `seed_demo.php` reexecute deux fois
- `get_articles.php`
- `set_auto_mode.php`
- `get_auto_mode.php`
- `ajouter_article.php`
- `modifier_article.php`
- `supprimer_article.php`
- `ajouter_commande.php`
- `get_commandes.php`
- `update_statut.php`
- `retablir_commande.php`
- `archiver.php`
- `vider_archive.php`
- `get_next_commande_id.php`

Resultats observes:

- toutes les pages HTML principales repondent en `HTTP/1.1 200 OK`
- `seed_demo.php` est bien reexecutable:
  - premier passage: insertion
  - second passage: mise a jour sans duplication
- creation commande multi-categories: OK
- date retournee au frontend: format `YYYY-MM-DDTHH:MM:SS`
- archivage: OK
- purge archive: OK
- `get_next_commande_id.php` renvoie `1` quand la base ne contient plus de commande

## Limites connues

1. `auto_mode` reste hors base

- stockage provisoire dans `php/auto_mode.json`

2. `get_next_commande_id.php` n'est pas transactionnel

- il repose encore sur `MAX(id) + 1`

3. `archive.html` n'embarque pas elle-meme de bouton de restauration

- la restauration existe via les vues roles
- ce comportement est documente, pas refondu dans cette passe

4. `php/test_pgsql.php` est un nom legacy

- il teste maintenant SQLite
- il n'est pas renomme pour eviter une rupture inutile

5. le polling toutes les 3 secondes est conserve

- pas bloqueur sous XAMPP
- a mesurer plus tard avant la phase S9+

## Prochaine passe recommandee

1. lancer une validation manuelle complete sous Apache XAMPP reel dans le navigateur
2. decider si la restauration doit aussi exister explicitement dans `archive.html`
3. fiabiliser le numero de commande si plusieurs postes doivent saisir en parallele
4. envisager le passage de `auto_mode` dans SQLite
5. seulement ensuite, preparer la phase batterie / internet / Galaxy S9+

## Refonte graphique POS

Une refonte graphique reversible du POS a ete appliquee principalement dans `css/style.css`.

Principes retenus:

- fond clair chaud et lumineux
- surfaces type cartes arrondies
- boutons plus larges et plus lisibles
- contrastes renforces pour le service
- palette differenciee par page:
  - caisse
  - bar
  - cuisine
  - snack
  - statut
  - archive / admin

Impact technique:

- aucune modification PHP
- aucun changement de schema SQLite
- aucun changement de payload JSON
- conservation des IDs / classes utilises par le JavaScript
- ajout de classes de page sur les balises `body` pour piloter le theme de facon facilement reversible

## Maintenance CSS

Nettoyage progressif effectue:

- une partie des `style=""` historiques a ete deplacee vers `css/style.css`
- remplacement par des classes explicites pour:
  - logos cliquables
  - espaceur de header
  - etat masque initial
  - panneau historique des vues roles
  - cartes d'historique
  - titres/timers de commandes
  - bloc roles admin

Choix de prudence:

- les gros blocs `<style>` embarques dans chaque page HTML sont conserves pour l'instant
- ils restent plus risqués a extraire tant que la refonte n'est pas validee visuellement en detail
- un style inline JS a ete retire de `archive.html`, mais les injections HTML/JS historiques doivent continuer a etre nettoyees par petites passes

## Barre d'etat locale

Une barre d'etat commune a ete ajoutee sur les pages principales via `js/system_status.js`.

Informations affichees:

- heure locale au format `HH:MM`
- etat Internet indicatif base sur `navigator.onLine`
- etat d'alimentation du navigateur courant:
  - `Secteur`
  - `Batterie`
  - `Batterie inconnue`
- niveau batterie sous forme de jauge approximative:
  - plein
  - moyen
  - faible
  - critique

Points importants:

- le POS local continue de fonctionner meme si l'etat affiche `Hors ligne`
- `Hors ligne` signifie seulement que le navigateur ne voit pas d'acces Internet
- cela ne veut pas dire que le serveur POS local est indisponible sur le reseau local
- l'etat batterie affiche est celui de l'appareil qui ouvre la page
- ce n'est pas forcement la batterie du telephone serveur cible

Limites navigateur:

- `navigator.getBattery()` n'est pas disponible partout
- si l'API n'existe pas, la barre reste fonctionnelle et affiche `Batterie inconnue`
- dans une phase ulterieure, si necessaire, un endpoint local specifique pourra exposer l'etat batterie du telephone serveur
