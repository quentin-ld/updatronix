# Système de mise à jour de WordPress — Référence développeur

[English](en.md) · [Index de la documentation](../README.md)

| Champ | Valeur |
|---|---|
| WordPress inspecté | 7.1.4 |
| Dernière vérification | 2026-11-07 |
| Statut | vérifié |
| Public | Développeurs d’extensions et de thèmes WordPress, rédacteurs techniques. |
| Périmètre | Système de mise à jour du cœur de WordPress — découverte, application, mécanismes de sécurité, configuration et crochets pour développeurs. |
| Vérifié contre | Cœur WordPress 7.1.4, PHP 8.4.27 (base du projet : PHP 8.1 et suivantes), d’après le cœur installé sous `/var/www/html`. |

**Les sept parties**

| # | Partie | Contenu |
|---|---|---|
| 1 | Découverte : comment WordPress détecte les mises à jour disponibles | Transients de mise à jour, vérifications planifiées et déclenchées depuis l’administration, points de terminaison de l’API, sources `Update URI`, sommes de contrôle, extensions obligatoires et drop-ins. |
| 2 | Application : comment WordPress installe les mises à jour | `WP_Upgrader` et ses sous-classes, étape de mise à niveau de la base de données, habillages, dépendances des extensions, retour en arrière, verrous, mode maintenance, signatures de paquets et nettoyage du dossier de préparation. |
| 3 | Les mises à jour automatiques en arrière-plan | `WP_Automatic_Updater`, conditions de désactivation globale, éligibilité par élément, politique de branche du cœur, adhésions et enchaînement des traductions. |
| 4 | Notifications et retour d’information | Notifications et compteurs de l’administration, tests de Santé du site, e-mails d’arrière-plan, charge utile d’`automatic_updates_complete` et mode de récupération. |
| 5 | Points d’entrée et configuration | Écran Mises à jour de WordPress, AJAX, WP-CLI, XML-RPC, REST, méthodes du système de fichiers, constantes de `wp-config.php`, multisite, proxys et rétrogradations. |
| 6 | Référence des crochets et des filtres | Crochets de découverte, de téléchargement, d’installation, de mise à jour automatique, de politique, de notification, de cache et de système de fichiers, avec paramètres et lignes source. |
| 7 | Référence des fichiers et des classes | Les fichiers du cœur concernés et le rôle de chacun, plus les classes clés. |

**Par où commencer**

| Ce que vous voulez faire | La partie à lire |
|---|---|
| Comprendre pourquoi aucune mise à jour n’est proposée | [Partie 1](#partie-1--découverte--comment-wordpress-détecte-les-mises-à-jour-disponibles), §1.2 et §1.6 |
| Intervenir pendant le téléchargement ou l’installation | [Partie 6](#partie-6--référence-des-crochets-et-des-filtres), §6.3 et §6.4 |
| Couper les mises à jour automatiques d’une extension ou d’un thème | [Partie 3](#partie-3--les-mises-à-jour-automatiques-en-arrière-plan), §3.3 et §3.8 |
| Envoyer les résultats de mise à jour ailleurs | [Partie 4](#partie-4--notifications-et-retour-dinformation), §4.7 |
| Retrouver la signature d’un crochet | [Partie 6](#partie-6--référence-des-crochets-et-des-filtres) |
| Savoir quel fichier du cœur fait quoi | [Partie 7](#partie-7--référence-des-fichiers-et-des-classes) |
| Diagnostiquer une mise à niveau en échec | [Partie 2](#partie-2--application--comment-wordpress-installe-les-mises-à-jour), puis §4.8 pour les erreurs fatales |

---

## Partie 1 — Découverte : comment WordPress détecte les mises à jour disponibles

### 1.1 Le modèle de découverte

La découverte est la phase où WordPress apprend quelles mises à jour existent. Rien ne s’installe tant que WordPress n’a pas écrit les offres dans les transients de site et que l’interface d’administration ou le programme de mise à jour automatique ne les a pas lues.

#### Fonctionnement

WordPress sépare la découverte de l’application et du retour d’information. Les fonctions `wp_version_check()`, `wp_update_plugins()` et `wp_update_themes()` vérifient les mises à jour ; elles n’installent rien. Leurs résultats vivent dans les transients de site. L’application repose sur la pile des moteurs de mise à niveau (voir §2.1). Le retour d’information passe par des notifications, des pastilles, des habillages ou des e-mails selon le point d’entrée (voir §4.1–§4.2 et la partie 5).

#### Déroulement

1. Un client HTTP contacte WordPress.org ou un autre hôte pour des métadonnées tierces.
2. WordPress décode la réponse en objets d’offre.
3. Le cœur fusionne les offres dans les charges utiles des transients.
4. Les écrans d’administration et le cron lisent les transients pour décider quoi afficher ou tenter ensuite.
5. Si la découverte échoue, les transients restent périmés ou vides et le site n’affiche aucune mise à jour jusqu’à la réussite d’une vérification ultérieure.

#### Référence

| Concept | Rôle |
|---|---|
| Fonctions de découverte | Remplissent les transients de site `update_core`, `update_plugins` et `update_themes` |
| Application | Sous-classes de `WP_Upgrader` ; la découverte seule ne les invoque pas |
| Retour d’information | Les transients alimentent l’interface d’administration ; l’e-mail d’arrière-plan n’est envoyé qu’après les mises à jour automatiques (voir §4.1) |

#### Ressources pour les développeurs

- [API des extensions : crochets, actions et filtres](https://developer.wordpress.org/plugins/) — comment les crochets s’attachent au comportement du cœur.

---

### 1.2 Les transients de site : la couche de données des mises à jour

Les transients de site contiennent les derniers résultats de découverte. Quand vous modifiez le comportement des mises à jour, vous manipulez généralement ces structures ou les filtres qui les enveloppent.

#### Fonctionnement

Le cœur met en cache les résultats de découverte dans des transients de site, partagés à l’échelle du réseau sur multisite. `wp_version_check()` définit le transient `update_core`. Ce transient contient le tableau des offres dans `updates`, plus `last_checked`, `version_checked` et des charges utiles de traduction facultatives. Les tables de sommes de contrôle n’y sont pas stockées ; le cœur les récupère à la demande par `get_core_checksums()` (voir §1.9).

`wp_update_plugins()` définit le transient `update_plugins` avec les versions de `response`, `no_update`, `checked` et les listes de traductions. `wp_update_themes()` définit le transient `update_themes` selon le même schéma pour les thèmes.

Des filtres dynamiques tels que `pre_set_site_transient_{$transient}` s’exécutent avant l’enregistrement. Ce filtre est le point d’intégration principal pour ajuster ou vider des structures avant l’exécution d’une mise à jour.

#### Déroulement

1. Une fonction de vérification s’exécute et construit ou fusionne une charge utile.
2. Des filtres peuvent modifier la charge utile avant son enregistrement.
3. WordPress enregistre la charge utile comme transient de site.
4. Les lecteurs — l’écran Mises à jour de WordPress, `wp_get_update_data()` et `WP_Automatic_Updater` — consomment le transient.
5. Les transients périmés persistent jusqu’à la prochaine vérification réussie ou jusqu’à un rafraîchissement forcé (voir §1.6).

#### Référence

| Transient | Défini par | Contenu typique |
|---|---|---|
| `update_core` | `wp_version_check()` | `updates` (offres), `last_checked`, `version_checked`, traductions |
| `update_plugins` | `wp_update_plugins()` | `response`, `no_update`, `checked`, traductions |
| `update_themes` | `wp_update_themes()` | Même schéma pour les thèmes |

#### Ressources pour les développeurs

- [Référence de `wp_update_plugins()`](https://developer.wordpress.org/reference/functions/wp_update_plugins/) — vérification des mises à jour d’extensions et stockage dans le transient.

---

### 1.3 Options de politique de branche du cœur (`auto_update_core_*`)

Des options de site contrôlent quelles branches du cœur WordPress peuvent recevoir des mises à jour automatiques lorsque la constante `WP_AUTO_UPDATE_CORE` n’est pas définie dans `wp-config.php`. Ces options fonctionnent avec les filtres et avec le filtre `auto_update_core` propre à chaque offre (voir §3.7).

#### Fonctionnement

Quand `WP_AUTO_UPDATE_CORE` n’est pas définie, le cœur lit les options de site dans la logique de politique de branche de `Core_Upgrader::should_update_to_version()`. `auto_update_core_dev` et `auto_update_core_minor` valent `enabled` par défaut ; `auto_update_core_major` vaut `unset` par défaut (`wp-admin/includes/class-core-upgrader.php:288-290`). Les valeurs sont `enabled`, `disabled` ou non définies. Ces options vivent dans la table des options (options de site sur multisite), elles ne sont pas stockées dans les transients de mise à jour. Pour la priorité avec `WP_AUTO_UPDATE_CORE` et les filtres, voir §5.10 et §3.7.

#### Déroulement

1. Le cœur évalue la branche de la version en cours : développement, mineure ou majeure.
2. Le cœur lit l’option `auto_update_core_*` correspondante, sauf si la constante remplace la politique.
3. Les filtres de branche peuvent changer le résultat booléen.
4. Le résultat alimente `should_update()` avec l’offre et les autres garde-fous.

#### Référence

| Nom | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `auto_update_core_dev` | option de site | `enabled` | Versions de développement ou de type nightly sur la branche en cours |
| `auto_update_core_minor` | option de site | `enabled` | Versions mineures de la même branche x.y |
| `auto_update_core_major` | option de site | `unset` | Versions majeures ; l’interface de WordPress 5.6 et suivantes mappe les choix ici |

#### Ressources pour les développeurs

- [`WP_AUTO_UPDATE_CORE` dans `wp-config.php`](https://developer.wordpress.org/apis/wp-config-php/) — constante qui remplace les options de branche lorsqu’elle est définie.

---

### 1.4 Les indicateurs d’offre `autoupdate` et `disable_autoupdate`

Les réponses de l’API attachent des indicateurs à chaque offre. Ces indicateurs déterminent si un élément est candidat aux mises à jour sans intervention avant l’exécution des filtres par élément.

#### Fonctionnement

Quand WordPress.org répond à une demande de vérification de mise à jour d’une extension ou d’un thème, chaque entrée de la table de réponse est un objet d’offre. Les extensions et les thèmes qui portent un en-tête `Update URI` peuvent recevoir des offres par `update_plugins_{$hostname}` ou `update_themes_{$hostname}` à la place ; ces objets peuvent aussi contenir `autoupdate` et `disable_autoupdate`.

Un booléen `autoupdate` signale que la version est éligible à une mise à jour automatique sans intervention du point de vue de l’annuaire ou du fournisseur tiers. Dans `WP_Automatic_Updater::should_update()`, pour les extensions et les thèmes, le cœur fixe d’abord la décision en attente à partir de `autoupdate`, puis fusionne les adhésions par élément des options de site (`auto_update_plugins`, `auto_update_themes`) quand les mises à jour automatiques sont activées pour le type. Si `autoupdate` est vide et que l’administrateur du site n’a pas adhéré pour l’élément, la décision en attente reste fausse.

La propriété `disable_autoupdate` force la décision en attente à faux avant l’exécution de `apply_filters( "auto_update_{$type}", … )`, mais le filtre peut encore renvoyer vrai pour réactiver la mise à jour.

Ces indicateurs ne sont pas des réglages de site durables. Ils arrivent à chaque réponse HTTP ou depuis une fonction de rappel de filtre. Le transient stocke ce que la dernière vérification a renvoyé. Rafraîchissez les offres avec `wp_update_plugins()` ou `wp_update_themes()` avant de vous appuyer sur des objets d’offre pour une décision de politique.

#### Déroulement

1. La découverte renvoie des offres, chacune portant éventuellement les indicateurs `autoupdate` et `disable_autoupdate`.
2. `should_update()` combine les indicateurs avec les tableaux d’adhésion et les filtres.
3. Un résultat faux écarte l’installation automatique de cet élément, sauf si un filtre renverse la décision.

#### Référence

| Indicateur | Signification |
|---|---|
| `autoupdate` | L’annuaire, le fournisseur tiers de `Update URI` ou l’auteur signale l’éligibilité à une mise à jour automatique |
| `disable_autoupdate` | Met l’élément en veto avant l’exécution d’`auto_update_{$type}` ; les filtres peuvent passer outre |

#### Ressources pour les développeurs

- [Référence de `WP_Automatic_Updater::should_update()`](https://developer.wordpress.org/reference/classes/wp_automatic_updater/should_update/) — pile d’éligibilité aux mises à jour automatiques.

---

### 1.5 Les vérifications planifiées (WP-Cron)

WordPress planifie des vérifications récurrentes pour que la découverte s’exécute sans utilisateur connecté. Vous devez comprendre le comportement du cron lorsque `DISABLE_WP_CRON` ou des planificateurs externes remplacent le mécanisme par défaut.

#### Fonctionnement

`wp_schedule_update_checks()` s’exécute sur `init` et planifie trois événements twicedaily : `wp_version_check` appelle `wp_version_check()`, `wp_update_plugins` appelle `wp_update_plugins()` et `wp_update_themes` appelle `wp_update_themes()`.

Quand `DISABLE_WP_CRON` vaut vrai, WordPress ne lance pas le moteur de cron lors des chargements de page normaux. Les crochets planifiés, dont `wp_version_check`, `wp_update_plugins`, `wp_update_themes` et, par enchaînement, `wp_maybe_auto_update`, ne s’exécutent que si quelque chose invoque `wp-cron.php` (cron système, supervision ou WP-CLI). Les vérifications déclenchées depuis l’administration décrites au §1.6 s’exécutent toujours sur ces requêtes, car elles ne reposent pas sur le cron.

#### Déroulement

1. Le crochet `init` planifie les événements s’ils ne le sont pas déjà.
2. À chaque exécution du cron, les événements arrivés à échéance se déclenchent.
3. Chaque gestionnaire appelle la fonction de mise à jour correspondante.
4. La limitation interne de ces fonctions peut éviter la requête HTTP si une vérification récente est encore valide, sauf si l’appelant force un rafraîchissement (§1.6).
5. En cas d’échec, les transients restent inchangés jusqu’à la prochaine exécution réussie.

#### Référence

| Crochet | Type | Paramètres | Remarques |
|---|---|---|---|
| `wp_version_check` | événement cron | — | Appelle `wp_version_check()` |
| `wp_update_plugins` | événement cron | — | Appelle `wp_update_plugins()` |
| `wp_update_themes` | événement cron | — | Appelle `wp_update_themes()` |

#### Ressources pour les développeurs

- [Désactiver WP-Cron dans `wp-config.php`](https://developer.wordpress.org/apis/wp-config-php/#disable-wordpress-cron) — comportement quand le cron est désactivé.
- [Commande CLI `wp cron event run`](https://developer.wordpress.org/cli/commands/cron/event/run/) — exécution manuelle des événements arrivés à échéance.

---

### 1.6 Vérifications déclenchées depuis l’administration et contournement de la limitation

Le chargement de certains écrans d’administration force des vérifications plus récentes, avec des délais plus courts que les longs délais par défaut. Si vous ajoutez un contrôle « vérifier les mises à jour », reproduisez ces schémas.

#### Fonctionnement

Des crochets sur des écrans d’administration tels que `load-plugins.php`, `load-update.php` et `load-update-core.php` déclenchent des vérifications d’extensions ou de thèmes avec des délais propres au contexte. L’écran Mises à jour de WordPress utilise un délai d’environ une minute, les tableaux de liste environ une heure, le délai par défaut de l’administration environ 12 heures et les vérifications par cron environ deux heures (`wp-includes/update.php:382-394`).

`_maybe_update_plugins()` et `_maybe_update_themes()` sur `admin_init` appliquent un délai de 12 heures quand `last_checked` est récent. `_maybe_update_core()` utilise la même fenêtre de 12 heures uniquement lorsque `version_checked` du transient correspond encore à la version de WordPress en cours. Si les versions diffèrent, une vérification s’exécute immédiatement.

`wp_version_check()` accepte un second paramètre `$force_check`. Quand il vaut vrai, la fonction ignore le délai appliqué au transient `update_core` stocké et envoie une nouvelle requête HTTP. `wp_update_plugins()` et `wp_update_themes()` acceptent `$extra_stats` ; quand ce tableau n’est pas vide, le retour anticipé pour « vérifié récemment et rien n’a changé » est ignoré, ce qui équivaut à une vérification forcée. Un premier argument non vide passé à `wp_version_check()` peut aussi définir `$force_check` en interne, selon `wp-includes/update.php`.

> **Note :** Ne forcez pas les vérifications à chaque requête cron ou d’arrière-plan. Réservez les vérifications forcées aux actions déclenchées par l’utilisateur ou aux tâches peu fréquentes, pour éviter les requêtes excessives vers WordPress.org.

#### Déroulement

1. Un écran d’administration se charge et ses crochets de chargement s’exécutent.
2. Les fonctions de mise à jour s’exécutent avec des délais courts ou avec `$force_check` et des statistiques supplémentaires non vides.
3. La requête HTTP renvoie de nouvelles offres.
4. WordPress rafraîchit les transients.
5. L’interface reflète les nouveaux compteurs de mise à jour.
6. Si l’utilisateur n’a pas les capacités suffisantes, WordPress affiche un message limité.

#### Référence

| Fonction | Fichier | Retourne | Remarques |
|---|---|---|---|
| `wp_version_check()` | `wp-includes/update.php` | void | Passez `$force_check` à vrai pour ignorer le délai du transient pour le cœur |
| `wp_update_plugins()` | `wp-includes/update.php` | void | Un `$extra_stats` non vide contourne le chemin « vérifié récemment » |
| `wp_update_themes()` | `wp-includes/update.php` | void | Un `$extra_stats` non vide contourne le chemin « vérifié récemment » |
| `wp_clean_update_cache()` | `wp-includes/update.php` | void | Vide les transients ; associez-la aux vérifications quand vous avez besoin d’une nouvelle requête HTTP |

#### Ressources pour les développeurs

- [Référence de `wp_version_check()`](https://developer.wordpress.org/reference/functions/wp_version_check/) — API de vérification de version du cœur.
- [Référence de `wp_clean_update_cache()`](https://developer.wordpress.org/reference/functions/wp_clean_update_cache/) — vide les transients de mise à jour.

---

### 1.7 Les points de terminaison de l’API

La découverte dépend de points de terminaison HTTP qui renvoient des offres JSON. La disponibilité de SSL influe sur le transport et sur les avertissements.

#### Fonctionnement

Le cœur envoie des requêtes POST aux points de terminaison de version et de vérification de mise à jour de WordPress.org. Les URL du code source utilisent `http://` ; quand `wp_http_supports( array( 'ssl' ) )` renvoie vrai, WordPress fait passer le schéma en HTTPS.

La vérification de version du cœur envoie des paramètres de chaîne de requête et un corps POST qui contient les traductions encodées. Les vérifications d’extensions et de thèmes envoient des corps JSON listant les composants et les locales installés. Un point de terminaison distinct sert uniquement à la vérification d’intégrité par sommes de contrôle, pas aux offres de mise à jour (voir §1.9). Quand SSL n’est pas disponible, le cœur peut retomber sur HTTP avec des avertissements visibles par l’utilisateur dans certains chemins d’échec.

#### Déroulement

1. Une fonction de mise à jour construit le corps de la requête.
2. WordPress envoie une requête POST au point de terminaison.
3. WordPress décode la réponse.
4. En cas d’erreur, les transients peuvent rester périmés ou WordPress déclenche un comportement de repli.
5. En cas de succès, WordPress met à jour les charges utiles des transients.

#### Référence

| Chemin d’URL sur `api.wordpress.org` | Méthode | Rôle |
|---|---|---|
| `/core/version-check/1.7/` | POST | Offres du cœur ; arguments de requête plus corps (traductions, par exemple) |
| `/plugins/update-check/1.1/` | POST | Offres par extension |
| `/themes/update-check/1.1/` | POST | Offres par thème |
| `/core/checksums/1.0/` | GET | Table chemin-vers-empreinte pour une version et une locale (§1.9) |

#### Ressources pour les développeurs

- [Présentation de l’API HTTP](https://developer.wordpress.org/apis/http-api/) — requêtes sortantes de WordPress.

---

### 1.8 Les sources de mise à jour tierces (`Update URI`)

Les extensions et les thèmes peuvent déclarer un en-tête `Update URI` pour que les métadonnées arrivent d’un autre hôte que WordPress.org. Cela étend la découverte sans remplacer le modèle des transients du cœur.

#### Fonctionnement

Pour les extensions et les thèmes qui déclarent `Update URI`, le cœur applique les filtres dynamiques `update_plugins_{$hostname}` et `update_themes_{$hostname}`. Ce mécanisme permet aux annuaires commerciaux ou personnalisés de fournir des métadonnées de mise à jour. Le cœur n’appelle le filtre que si WordPress.org n’a pas déjà renvoyé d’offre pour l’élément, et il exige au moins une clé `version` dans le tableau renvoyé (`wp-includes/update.php:509-565`). Les préférences de l’interface de mise à jour automatique par entité sont stockées dans des options de site et décrites aux §5.4 et §3.8.

#### Déroulement

1. La découverte s’exécute.
2. Le cœur déduit le nom d’hôte de l’en-tête `Update URI`.
3. Le filtre correspondant peut injecter ou modifier des offres.
4. WordPress fusionne les résultats dans les transients de site standard.
5. La logique du programme de mise à jour automatique décrite dans la partie 3 utilise ces offres comme les autres.

#### Référence

| Crochet | Type | Paramètres | Remarques |
|---|---|---|---|
| `update_plugins_{$hostname}` | filtre | `false\|array $update, array $plugin_data, string $plugin_file, string[] $locales` | Offres d’extensions tierces |
| `update_themes_{$hostname}` | filtre | `false\|array $update, array $theme_data, string $theme_stylesheet, string[] $locales` | Offres de thèmes tierces |

#### Ressources pour les développeurs

- [Manuel des thèmes : en-tête `Update URI`](https://developer.wordpress.org/themes/core-concepts/main-stylesheet/#update-uri) — objet de l’en-tête pour les thèmes ; les extensions suivent le même schéma dans le cœur.

---

### 1.9 Vérification de l’intégrité du cœur (sommes de contrôle)

La vérification par sommes de contrôle compare les fichiers installés aux empreintes attendues pour une version donnée de WordPress. Elle sert au diagnostic et aux outils en ligne de commande. Elle ne filtre pas le programme de mise à jour automatique avant l’exécution des mises à jour automatiques. La vérification des signatures de paquets sur les fichiers ZIP téléchargés avant l’installation est un mécanisme distinct (§2.12).

#### Fonctionnement

L’API de sommes de contrôle de WordPress.org renvoie un JSON dont l’entrée `checksums` associe des chemins de fichiers relatifs aux empreintes attendues pour une version du cœur et une locale. Le cœur récupère cette table par `get_core_checksums()` dans `wp-admin/includes/update.php`.

`Core_Upgrader::check_files()`, dans `wp-admin/includes/class-core-upgrader.php`, charge les sommes de contrôle et compare la sortie de `md5_file()` à chaque empreinte attendue, en ignorant les chemins sous `wp-content/`. Santé du site et les chemins de mise à niveau peuvent utiliser la même API. Le transient de découverte `update_core` n’intègre pas la table des sommes de contrôle. Le cœur la récupère séparément quand il en a besoin.

La commande WP-CLI `wp core verify-checksums` effectue un contrôle d’intégrité similaire en ligne de commande. La vérification est un audit d’intégrité. Elle ne remplace pas la vérification des signatures Ed25519 des paquets ZIP téléchargés (§2.12) et ne sert pas de garde-fou universel avant l’exécution de `WP_Automatic_Updater`.

#### Déroulement

1. Un outil ou une fonction demande les sommes de contrôle d’une version et d’une locale précises.
2. Le cœur compare les fichiers installés à la table des empreintes.
3. Une différence signale des fichiers du cœur modifiés ou manquants.
4. Le résultat informe les administrateurs, mais ne pilote pas à lui seul la chaîne du programme de mise à jour automatique.

#### Référence

| Fonction | Fichier | Retourne | Remarques |
|---|---|---|---|
| `get_core_checksums()` | `wp-admin/includes/update.php` | `array<string, string>` ou `false` | Récupère la table chemin-vers-empreinte ; renvoie `false` en cas d’échec |
| `Core_Upgrader::check_files()` | `wp-admin/includes/class-core-upgrader.php` | `bool` | Compare les fichiers d’`ABSPATH` à la table |

#### Ressources pour les développeurs

- [Référence de `get_core_checksums()`](https://developer.wordpress.org/reference/functions/get_core_checksums/) — récupération de la table des sommes de contrôle.
- [Référence de `Core_Upgrader::check_files()`](https://developer.wordpress.org/reference/classes/core_upgrader/check_files/) — comparaison des fichiers du cœur.
- [Commande CLI `wp core verify-checksums`](https://developer.wordpress.org/cli/commands/core/verify-checksums/) — contrôle d’intégrité en ligne de commande.

---

### 1.10 Les exclusions : extensions obligatoires et drop-ins

Les extensions obligatoires et les drop-ins vivent en dehors des chaînes habituelles de découverte des extensions et des thèmes. Ils n’apparaissent jamais dans les charges utiles de vérification de mise à jour de WordPress.org et ne sont pas mis à niveau par l’interface de mise à jour standard ni par les chemins du programme de mise à jour automatique destinés aux extensions et thèmes ordinaires.

#### Fonctionnement

Les extensions obligatoires se chargent depuis `wp-content/mu-plugins/` (et les fichiers PHP imbriqués là où c’est pris en charge). Le cœur ne les énumère pas comme les extensions ordinaires de `wp-content/plugins/`, si bien que `wp_update_plugins()` ne récupère pas d’offres pour elles auprès de WordPress.org.

Les drop-ins (par exemple `advanced-cache.php`, `db.php` et `object-cache.php`) sont des fichiers isolés ou de petits ensembles de fichiers placés directement sous `wp-content/` et recensés par `get_dropins()`. Ils ne sont pas conditionnés comme des extensions de répertoire ; le cœur n’a pas de flux intégré « mettre à jour ce drop-in depuis l’annuaire ».

Remplacer des extensions obligatoires ou des drop-ins exige un déploiement manuel de fichiers, une chaîne de déploiement personnalisée ou des outils extérieurs aux mécanismes de mise à jour natifs.

#### Déroulement

1. La découverte s’exécute pour les extensions et les thèmes standard.
2. Les extensions obligatoires et les drop-ins sont exclus du modèle d’offre.
3. Un administrateur ou un outil d’automatisation copie les nouveaux fichiers dans `mu-plugins/` ou `wp-content/` (souvent par contrôle de version ou CI/CD).
4. Le cœur n’enregistre pas de métadonnées de version dans les transients de mise à jour pour ces composants.
5. Les extensions obligatoires continuent de se charger tôt au démarrage ; les drop-ins prennent effet lorsqu’ils sont présents et valides.

#### Référence

| Élément | Découverte | Application |
|---|---|---|
| Extensions obligatoires | Non listées dans le flux standard de l’API de mise à jour des extensions | Déploiement manuel ou externe |
| Drop-ins | Non listés comme paquets installables | Déploiement manuel ou externe |

#### Ressources pour les développeurs

- [Extensions obligatoires](https://developer.wordpress.org/plugins/must-use-plugins/) — règles de chargement et contraintes.
- [Référence de `get_dropins()`](https://developer.wordpress.org/reference/functions/get_dropins/) — noms de fichiers des drop-ins et détection.

---

## Partie 2 — Application : comment WordPress installe les mises à jour

### 2.1 Le modèle d’application

L’application est la phase où WordPress télécharge les paquets, les décompresse, remplace les fichiers et termine le travail de suivi. Quand vous diagnostiquez des mises à jour en échec, vous avez besoin de ce modèle, distinct de la découverte et de la phase de mise à niveau de la base de données.

#### Fonctionnement

`WP_Upgrader` coordonne le téléchargement, l’accès au système de fichiers, les emplacements de décompression sous `wp-content/upgrade/`, `install_package()` et l’action `upgrader_process_complete`. Les interfaces interactives utilisent des habillages. Les mises à jour en arrière-plan utilisent `Automatic_Upgrader_Skin` et `WP_Automatic_Updater`. Les opérations groupées suivent la progression des habillages par `update_count` et `update_current`. Remplacer des fichiers n’est pas la même chose que migrer la base de données ; voir §2.4. La vérification des signatures de paquets et le comportement du dossier de préparation sont traités aux §2.12 et §2.13.

#### Déroulement

1. Un point d’entrée construit un moteur de mise à niveau et un habillage.
2. Les flux interactifs peuvent demander des identifiants de système de fichiers.
3. Le paquet est téléchargé, sauf si un filtre court-circuite la requête.
4. Les fichiers sont mis en place.
5. Les crochets se déclenchent et le mode maintenance peut basculer.
6. Les caches des extensions ou des thèmes sont vidés après le succès (§2.11).
7. Le remplacement des fichiers du cœur peut encore exiger une exécution distincte de `wp_upgrade()` pour le schéma (§2.4).

#### Référence

| Couche | Responsabilité |
|---|---|
| `WP_Upgrader` | Orchestration : téléchargement, décompression, installation, crochets |
| Sous-classes spécialisées | Moteurs de mise à niveau du cœur, des extensions, des thèmes et des paquets de langue |
| Habillages | Canal de sortie : HTML, JSON ou messages mis en mémoire tampon |

#### Ressources pour les développeurs

- [Référence de la classe `WP_Upgrader`](https://developer.wordpress.org/reference/classes/wp_upgrader/) — classe de base de tous les moteurs de mise à niveau.

---

### 2.2 `WP_Upgrader` : la classe de base

`WP_Upgrader` fournit l’implémentation commune du téléchargement, du travail sur le système de fichiers, de la décompression et de l’installation des paquets. La plupart des crochets de personnalisation s’attachent ici ou dans ses sous-classes.

#### Fonctionnement

La classe, dans `wp-admin/includes/class-wp-upgrader.php`, coordonne le téléchargement des paquets vers un fichier temporaire par `download_url()`, le travail avec `WP_Filesystem`, la décompression des fichiers ZIP dans `wp-content/upgrade/` par `unpack_package()`, l’appel à `install_package()` et le déclenchement de l’action `upgrader_process_complete`. `run()` applique `upgrader_package_options` avant le travail sur le système de fichiers et `upgrader_install_package_result` après `install_package()` (`wp-admin/includes/class-wp-upgrader.php:849`, `:948`). Les opérations groupées incrémentent des compteurs pour les habillages de progression.

#### Déroulement

1. L’appelant construit un moteur de mise à niveau et un habillage.
2. `download_package()` peut déclencher le filtre `upgrader_pre_download`.
3. `run()` applique `upgrader_package_options`.
4. `install_package()` exécute dans l’ordre les filtres de pré-installation, de sélection de la source, de vidage de la destination et de post-installation.
5. `upgrader_install_package_result` filtre le résultat.
6. À la fin, `upgrader_process_complete` se déclenche.
7. Les erreurs renvoient des objets `WP_Error` à l’habillage ou à l’appelant.

#### Référence

| Classe | Fichier | Hérite de | Rôle |
|---|---|---|---|
| `WP_Upgrader` | `class-wp-upgrader.php` | — | Orchestration de base, verrous et assistants de maintenance |

#### Ressources pour les développeurs

- [Référence de `WP_Upgrader::install_package()`](https://developer.wordpress.org/reference/classes/wp_upgrader/install_package/) — crochets de la chaîne d’installation.

---

### 2.3 Les moteurs de mise à niveau spécialisés

Chaque grand type de paquet a une classe de moteur de mise à niveau adaptée à son type. Quand vous étendez les flux de mise à jour, vous héritez généralement de ces classes ou vous les appelez.

#### Fonctionnement

`Core_Upgrader` remplace les fichiers du cœur WordPress et prend en charge les versions partielles et les options liées au retour en arrière dans sa méthode `upgrade()`. `Plugin_Upgrader` gère les chemins d’extension unique, `bulk_upgrade()` et les installations par ZIP ou URL. `Theme_Upgrader` gère les mises à jour et installations de thèmes. `Language_Pack_Upgrader` installe les mises à jour de traduction collectées depuis les transients.

Le remplacement des fichiers du cœur par `Core_Upgrader` reste distinct de la routine de mise à niveau de la base de données décrite au §2.4.

#### Déroulement

1. Le point d’entrée choisit la classe appropriée.
2. Le moteur de mise à niveau reçoit les métadonnées du paquet depuis les transients ou des arguments directs.
3. Les opérations de fichiers s’exécutent sous mode maintenance et verrous selon le cas.
4. `Language_Pack_Upgrader::async_upgrade()` s’attache à la priorité 20 sur `upgrader_process_complete`, si bien que les traductions s’installent après une mise à niveau du cœur, d’une extension ou d’un thème dans la même requête.
5. `WP_Automatic_Updater::run()` peut retirer puis réattacher ces écouteurs pour maîtriser l’ordre pendant les lots automatiques.

#### Référence

| Classe | Fichier | Hérite de | Rôle |
|---|---|---|---|
| `Core_Upgrader` | `class-core-upgrader.php` | `WP_Upgrader` | Remplacement des fichiers du cœur |
| `Plugin_Upgrader` | `class-plugin-upgrader.php` | `WP_Upgrader` | Mises à jour et installations d’extensions |
| `Theme_Upgrader` | `class-theme-upgrader.php` | `WP_Upgrader` | Mises à jour et installations de thèmes |
| `Language_Pack_Upgrader` | `class-language-pack-upgrader.php` | `WP_Upgrader` | Paquets de traduction |

#### Ressources pour les développeurs

- [Référence de la classe `Core_Upgrader`](https://developer.wordpress.org/reference/classes/core_upgrader/) — mises à jour du cœur.

---

### 2.4 La phase de mise à niveau de la base de données (`wp_upgrade()`)

Remplacer les fichiers du cœur et migrer la base de données sont deux étapes distinctes. Beaucoup de problèmes viennent de leur confusion.

#### Fonctionnement

Après que `Core_Upgrader` a remplacé les fichiers, la phase de mise à niveau de la base de données s’exécute séparément par `wp_upgrade()` dans `wp-admin/includes/upgrade.php`, en dehors de `WP_Upgrader::install_package()`. Cette routine compare l’option `db_version` stockée à la variable globale `$wp_db_version`. Si les valeurs diffèrent, la routine exécute `pre_schema_upgrade()`, `make_db_current_silent()` et `upgrade_all()`. Sur multisite, `upgrade_network()` s’exécute sur le site principal quand cela s’applique, et le cœur écrit `db_version` et `db_last_updated` dans les métadonnées de site (`wp-admin/includes/upgrade.php:684-687`). Après avoir vidé les caches, WordPress déclenche `do_action( 'wp_upgrade', $wp_db_version, $wp_current_db_version )`, où le premier argument est la nouvelle version de la base de données et le second l’ancienne. Les changements de tables et de schéma reposent généralement sur `dbDelta()` pour un SQL idempotent.

> **Note :** Les fichiers peuvent correspondre à une nouvelle version alors que `wp_upgrade()` ne s’est jamais terminé ou a échoué en cours d’exécution. La commande WP-CLI `wp core update-db` force la phase de base de données indépendamment. Après une mise à jour des fichiers du cœur, vérifiez que `db_version` (et les métadonnées de site multisite le cas échéant) correspond aux attentes avant de considérer la mise à niveau comme terminée.

#### Déroulement

1. `Core_Upgrader` installe la nouvelle arborescence de fichiers du cœur.
2. Un chargement de page d’administration ou une exécution CLI invoque `wp_upgrade()` quand les versions diffèrent.
3. `wp_upgrade()` exécute les mises à niveau silencieuses et les mises à niveau de réseau selon les besoins.
4. Les caches sont vidés et l’action `wp_upgrade` se déclenche.
5. Si l’étape 2 est ignorée, le site exécute du PHP nouveau avec un ancien schéma.

#### Référence

| Fonction | Fichier | Retourne | Remarques |
|---|---|---|---|
| `wp_upgrade()` | `wp-admin/includes/upgrade.php` | void | Migration du schéma après le remplacement des fichiers du cœur |
| `dbDelta()` | `wp-admin/includes/upgrade.php` | array | Applique les différences SQL de façon idempotente |

#### Ressources pour les développeurs

- [Référence de `wp_upgrade()`](https://developer.wordpress.org/reference/functions/wp_upgrade/) — routine de mise à niveau de la base de données.
- [Référence de l’action `wp_upgrade`](https://developer.wordpress.org/reference/hooks/wp_upgrade/) — se déclenche après une mise à niveau de la base de données.
- [Commande CLI `wp core update-db`](https://developer.wordpress.org/cli/commands/core/update-db/) — mise à niveau de la base de données en ligne de commande.

---

### 2.5 Les habillages de mise à niveau : la couche de sortie

Les habillages adaptent la sortie du moteur de mise à niveau à une page HTML complète, à du JSON AJAX ou à une exécution silencieuse en arrière-plan. Ils forment le canal entre le moteur de mise à niveau et l’environnement d’exécution.

#### Fonctionnement

Les habillages héritent de `WP_Upgrader_Skin` et implémentent le contrat entre une instance de moteur de mise à niveau et son environnement. Les habillages n’étendent pas `WP_Upgrader` lui-même. L’administration interactive utilise des habillages qui affichent la progression, les erreurs et les formulaires d’identifiants FTP. Les habillages AJAX collectent les messages et les erreurs pour le JSON. Les habillages de mise à jour en arrière-plan suppriment la sortie visible et mettent en mémoire tampon les demandes d’identifiants ; leurs messages alimentent les journaux et le corps des e-mails.

Le moteur de mise à niveau appelle `feedback()`, `error()`, `header()`, `footer()` et `request_filesystem_credentials()`. Les chaînes viennent souvent de `$upgrader->strings`, définies par les méthodes `upgrade_strings()` des sous-classes. `Automatic_Upgrader_Skin` met en mémoire tampon la sortie des identifiants et accumule les messages pour les notifications. `WP_Ajax_Upgrader_Skin` l’étend pour collecter les erreurs des réponses asynchrones.

#### Déroulement

1. Le moteur de mise à niveau définit un habillage.
2. Chaque étape appelle les méthodes de l’habillage pour signaler la progression.
3. Les flux interactifs impriment ou renvoient des données structurées.
4. Les flux d’arrière-plan mettent les messages en mémoire tampon.
5. En cas d’échec, l’habillage ou la couche AJAX fait remonter les erreurs. Les exécutions en arrière-plan n’envoient un e-mail que lorsque le programme de mise à jour automatique traite le lot (partie 4).

#### Référence

| Classe | Fichier | Hérite de | Rôle |
|---|---|---|---|
| `WP_Upgrader_Skin` | `class-wp-upgrader-skin.php` | — | Base : identifiants et retours par défaut |
| `Bulk_Upgrader_Skin` | `class-bulk-upgrader-skin.php` | `WP_Upgrader_Skin` | Progression de plusieurs éléments |
| `Plugin_Upgrader_Skin` | `class-plugin-upgrader-skin.php` | `WP_Upgrader_Skin` | Écran d’une extension |
| `Theme_Upgrader_Skin` | `class-theme-upgrader-skin.php` | `WP_Upgrader_Skin` | Écran d’un thème |
| `Bulk_Plugin_Upgrader_Skin` | `class-bulk-plugin-upgrader-skin.php` | `Bulk_Upgrader_Skin` | Interface de mise à jour groupée d’extensions |
| `Bulk_Theme_Upgrader_Skin` | `class-bulk-theme-upgrader-skin.php` | `Bulk_Upgrader_Skin` | Interface de mise à jour groupée de thèmes |
| `Language_Pack_Upgrader_Skin` | `class-language-pack-upgrader-skin.php` | `WP_Upgrader_Skin` | Écran de mise à jour des traductions |
| `Plugin_Installer_Skin` | `class-plugin-installer-skin.php` | `WP_Upgrader_Skin` | Écran d’installation et d’écrasement d’extension |
| `Theme_Installer_Skin` | `class-theme-installer-skin.php` | `WP_Upgrader_Skin` | Écran d’installation et d’écrasement de thème |
| `Automatic_Upgrader_Skin` | `class-automatic-upgrader-skin.php` | `WP_Upgrader_Skin` | Pas de HTML ; messages pour l’e-mail ou les journaux |
| `WP_Ajax_Upgrader_Skin` | `class-wp-ajax-upgrader-skin.php` | `Automatic_Upgrader_Skin` | Erreurs et messages AJAX |

#### Ressources pour les développeurs

- [Référence de la classe `WP_Upgrader_Skin`](https://developer.wordpress.org/reference/classes/wp_upgrader_skin/) — classe de base des habillages.

---

### 2.6 `WP_Plugin_Dependencies` : résolution des dépendances (WordPress 6.5 et suivantes)

Les dépendances d’extensions déclarées dans les en-têtes sont résolues par `WP_Plugin_Dependencies`. L’ordre des mises à jour groupées du cœur ne trie pas topologiquement par dépendance ; cette API aide votre code personnalisé à ordonner correctement le travail.

#### Fonctionnement

L’en-tête `Requires Plugins` liste des slugs d’extensions WordPress.org séparés par des virgules. `WP_Plugin_Dependencies`, dans `wp-includes/class-wp-plugin-dependencies.php`, lit les en-têtes, récupère les métadonnées des dépendances et peut détecter les graphes circulaires. L’interface d’installation désactive le bouton Installer maintenant tant que les prérequis ne sont pas satisfaits. L’application par WP-CLI est la plus stricte à l’activation ; plusieurs commandes peuvent être nécessaires.

Les mises à jour groupées du cœur ne réordonnent pas selon le graphe de dépendances. Des assistants publics tels que `get_dependency_names()` et `get_dependency_filepath()` vous aident à déduire l’ordre dépendance avant dépendant, même si certains éléments internes sont protégés.

#### Déroulement

1. Le cœur charge les métadonnées de dépendance depuis les en-têtes des extensions.
2. Les dépendances non satisfaites bloquent les actions d’installation dans l’interface.
3. Les mises à jour s’exécutent dans l’ordre de l’appelant, sauf si du code externe les réordonne à partir des données de dépendance.
4. L’activation peut encore échouer si les contraintes ne sont pas satisfaites.

#### Référence

| Classe | Fichier | Hérite de | Rôle |
|---|---|---|---|
| `WP_Plugin_Dependencies` | `class-wp-plugin-dependencies.php` | — | Graphe et métadonnées des extensions requises |

#### Ressources pour les développeurs

- [Référence de la classe `WP_Plugin_Dependencies`](https://developer.wordpress.org/reference/classes/wp_plugin_dependencies/) — référence de la classe.
- [Annonce de fusion des dépendances d’extensions](https://make.wordpress.org/core/2024/02/15/merge-announcement-plugin-dependencies/) — contexte de la fonctionnalité.

---

### 2.7 Le système de retour en arrière (WordPress 6.2 et suivantes)

La prise en charge du retour en arrière a évolué au fil des versions : déplacements de répertoires plus sûrs, retour en arrière manuel des extensions et des thèmes avec sauvegardes temporaires, puis retour en arrière automatique des extensions après une erreur fatale détectée par boucle locale.

#### Fonctionnement

WordPress 6.2 a introduit `move_dir()` pour centraliser des déplacements de type atomique entre les couches de `WP_Filesystem`.

WordPress 6.3 a ajouté le retour en arrière manuel des extensions et des thèmes. Avant le remplacement, les arborescences de répertoires précédentes sont déplacées vers `wp-content/upgrade-temp-backup/plugins/{slug}` ou `.../themes/{slug}`. Sur une `WP_Error` renvoyée par `install_package()` ou sur des échecs associés, la sauvegarde temporaire restaure l’état à l’arrêt (`wp-admin/includes/class-wp-upgrader.php:952-968`). La réactivation est une étape distincte du point d’entrée (`wp-admin/update.php:86`), pas une partie du chemin de retour en arrière. Santé du site a gagné des tests sur l’écriture dans le dossier de sauvegarde et sur l’espace disque.

WordPress 6.6 a ajouté le retour en arrière automatique pour les extensions actives. Après une mise à jour, le cœur peut envoyer une requête de boucle locale à l’URL d’accueil pour détecter les erreurs fatales PHP et appeler `restore_temp_backup()`. Les extensions inactives échappent à la détection des erreurs fatales. Un e-mail peut suivre un retour en arrière.

Les sauvegardes temporaires ne sont pas un produit de retour en arrière général destiné aux utilisateurs. En cas de succès ou après une restauration, WordPress vide leur contenu. `WP_Upgrader::init()` planifie chaque semaine `wp_delete_temp_updater_backups` ; la suppression peut être différée quand des verrous ou des requêtes AJAX sont actifs.

#### Déroulement

1. Une mise à jour démarre et peut créer une sauvegarde temporaire.
2. WordPress remplace les fichiers.
3. En cas d’échec ou de détection d’erreur fatale, la restauration s’exécute depuis la sauvegarde temporaire.
4. Le cron finit par supprimer les anciennes sauvegardes temporaires.
5. Voir §2.10 pour les détails de la boucle locale.

#### Référence

| Jalon | Introduit dans | Comportement |
|---|---|---|
| Déplacements de répertoires plus sûrs | 6.2 | `move_dir()` pour des déplacements de type atomique |
| Retour en arrière manuel | 6.3 | Retour en arrière des extensions et des thèmes depuis une sauvegarde temporaire |
| Retour en arrière automatique des extensions actives | 6.6 | Retour en arrière après une erreur fatale détectée par boucle locale |

#### Ressources pour les développeurs

- [Nouveau dans 6.3 : retour en arrière des mises à jour manuelles d’extensions et de thèmes en échec](https://make.wordpress.org/core/2023/07/11/new-in-6-3-rollback-for-failed-manual-plugin-and-theme-updates/) — fonctionnalité de retour en arrière manuel.
- [Proposition de fusion : retour en arrière après une mise à jour automatique](https://make.wordpress.org/core/2024/04/19/merge-proposal-rollback-auto-update/) — chemin de retour en arrière automatique.

---

### 2.8 Les verrous des moteurs de mise à niveau

Les verrous empêchent des processus de mise à niveau concurrents de corrompre l’état. Le programme de mise à jour automatique et le moteur de mise à niveau du cœur utilisent des noms de verrou et des délais d’expiration différents.

#### Fonctionnement

`WP_Upgrader::create_lock( $lock_name, $release_timeout )` stocke une option de verrou avec un horodatage Unix. Le délai de libération par défaut, quand il est omis, est d’une heure. `WP_Automatic_Updater::run()` utilise le verrou `auto_updater` avec le délai par défaut. `Core_Upgrader` utilise `core_updater` avec un délai de 15 minutes. `release_lock()` supprime l’option. Les verrous périmés bloquent les nouvelles exécutions jusqu’à leur expiration ou jusqu’à la suppression manuelle de l’option de verrou par les API appropriées.

#### Déroulement

1. Une exécution appelle `create_lock()`.
2. Si le verrou existe et n’a pas expiré, la nouvelle exécution s’arrête ou attend selon la logique de l’appelant.
3. À la fin ou en cas de sortie fatale, `release_lock()` s’exécute quand l’appelant l’implémente.
4. Si un processus meurt sans libérer le verrou, celui-ci expire après le délai d’expiration.

#### Référence

| Nom du verrou | Délai d’expiration typique | Utilisé par |
|---|---|---|
| `auto_updater` | Par défaut (une heure) | `WP_Automatic_Updater::run()` |
| `core_updater` | 900 secondes (15 minutes) | `Core_Upgrader` |

#### Ressources pour les développeurs

- [Référence de `WP_Upgrader::create_lock()`](https://developer.wordpress.org/reference/classes/wp_upgrader/create_lock/) — acquisition du verrou.
- [Référence de `WP_Upgrader::release_lock()`](https://developer.wordpress.org/reference/classes/wp_upgrader/release_lock/) — libération du verrou.

---

### 2.9 Le mode maintenance

Le mode maintenance limite l’exécution côté public pendant que les fichiers sont incohérents. Les lots automatiques peuvent maintenir le mode maintenance plus longtemps pour éviter de servir du code à moitié mis à jour.

#### Fonctionnement

`WP_Upgrader::maintenance_mode( true | false )` bascule le fichier `.maintenance` pendant les mises à jour. `wp_is_maintenance_mode()` lit le fichier, et le filtre `enable_maintenance_mode` peut s’y opposer (`wp-includes/load.php:474`). Pour les mises à jour automatiques, le mode maintenance peut rester actif pendant tout le lot, afin que les requêtes du front end ne touchent pas du code partiellement mis à jour pendant les opérations liées au retour en arrière.

#### Déroulement

1. Une mise à niveau commence et active le mode maintenance.
2. WordPress remplace les fichiers.
3. En cas de succès ou d’échec maîtrisé, WordPress désactive le mode maintenance.
4. Les lots longs maintiennent le mode maintenance actif jusqu’à la fin du lot, selon le comportement du cœur pour cette version.

#### Référence

| Mécanisme | Rôle |
|---|---|
| Fichier `.maintenance` | Court-circuite les requêtes du front end pendant la mise à niveau |

#### Ressources pour les développeurs

- [Présentation du processus de mise à niveau de WordPress](https://developer.wordpress.org/advanced-administration/upgrade/wordpress-upgrade-process/) — décrit le comportement du fichier `.maintenance`.

---

### 2.10 Les requêtes de boucle locale dans la chaîne de mise à jour

Les requêtes de boucle locale sont des requêtes HTTP de PHP vers le front end du même site. Elles comptent pour Santé du site et pour le retour en arrière automatique des extensions après une erreur fatale.

#### Fonctionnement

Une requête de boucle locale cible généralement `home_url( '/' )` avec des arguments de requête de scraping. Le test de boucle locale de Santé du site vérifie que les requêtes vers soi-même aboutissent.

La détection des erreurs fatales du programme de mise à jour automatique de WordPress 6.6 et suivantes utilise `wp_remote_get()` sur l’URL d’accueil. Si la réponse est une `WP_Error` de transport, `WP_Automatic_Updater::has_fatal_error()` renvoie vrai (`wp-admin/includes/class-wp-automatic-updater.php:1809-1814`), si bien qu’une panne d’infrastructure peut déclencher une restauration.

Les environnements localhost, les proxys inverses ou un HTTP sortant bloqué peuvent casser les requêtes de boucle locale.

#### Déroulement

1. Le programme de mise à jour termine une modification d’extension.
2. Le cœur envoie une requête HTTP à l’URL d’accueil.
3. Le cœur inspecte la réponse à la recherche de marqueurs d’erreur fatale.
4. Sur un signal positif d’erreur fatale, un retour en arrière peut s’exécuter.
5. Sur une panne de transport, le comportement suit l’implémentation actuelle du cœur.

#### Référence

| Contexte | Rôle |
|---|---|
| Santé du site | Diagnostique la capacité de boucle locale |
| Retour en arrière des mises à jour automatiques de WordPress 6.6 et suivantes | Détection des erreurs fatales après la mise à jour d’une extension active |

#### Ressources pour les développeurs

- [Référence de `wp_remote_get()`](https://developer.wordpress.org/reference/functions/wp_remote_get/) — primitive HTTP GET utilisée dans les contrôles de boucle locale.

---

### 2.11 L’invalidation des caches après les mises à jour

Après des changements de fichiers, WordPress doit rafraîchir les caches d’exécution des extensions et des thèmes pour que l’interface et les API reflètent les nouvelles versions sans requêtes HTTP inutiles vers WordPress.org.

#### Fonctionnement

Après des mises à jour réussies d’extensions ou de thèmes, le cœur appelle `wp_clean_plugins_cache()` et `wp_clean_themes_cache()`. Chaque fonction vide le transient de site concerné par défaut et rafraîchit les caches d’objets ou réanalyse les répertoires de thèmes.

`wp_clean_update_cache()` supprime les transients `update_core`, `update_plugins` et `update_themes` en un seul appel et invoque les nettoyeurs d’extensions et de thèmes quand ils existent. Elle ne contacte pas WordPress.org par elle-même.

`wp_update_plugins()` et `wp_update_themes()` effectuent des requêtes POST immédiates soumises aux limitations internes. Utilisez-les quand vous avez besoin de nouvelles charges utiles d’offre dans la même requête, et pas seulement de caches vidés.

#### Déroulement

1. Une mise à niveau réussit.
2. Les nettoyeurs de cache s’exécutent.
3. Le prochain appel à `get_plugins()` ou à `wp_get_themes()` renvoie des données fraîches.
4. Les compteurs de la barre latérale se rafraîchissent au prochain chargement de l’administration si les transients ont été vidés.
5. Le code personnalisé qui met à niveau en dehors des chemins du cœur doit appeler les mêmes nettoyeurs si les versions semblent périmées.

#### Référence

| Fonction | Fichier | Retourne | Remarques |
|---|---|---|---|
| `wp_clean_plugins_cache()` | `wp-admin/includes/plugin.php` | void | Vide les caches de données des extensions et le transient `update_plugins` |
| `wp_clean_themes_cache()` | `wp-includes/theme.php` | void | Réanalyse les thèmes ; vide le transient `update_themes` |
| `wp_clean_update_cache()` | `wp-includes/update.php` | void | Vide les trois transients de mise à jour et les caches associés |

#### Ressources pour les développeurs

- [Référence de `wp_clean_update_cache()`](https://developer.wordpress.org/reference/functions/wp_clean_update_cache/) — invalidation en un seul appel.

---

### 2.12 La vérification des signatures cryptographiques (Ed25519)

Depuis WordPress 5.2, le cœur peut vérifier les signatures Ed25519 des paquets téléchargés avant de leur faire confiance pour l’installation. Cela atténue les altérations de la chaîne d’approvisionnement entre WordPress.org et le système de fichiers en comparant chaque paquet aux clés distribuées avec WordPress.

#### Fonctionnement

`verify_file_signature()`, dans `wp-admin/includes/file.php`, calcule l’empreinte SHA-384 du fichier téléchargé et vérifie les signatures détachées avec `sodium_crypto_sign_verify_detached()` contre les clés publiques de `wp_trusted_keys()`. Les signatures sont encodées en base64 dans le transport ; les clés sont des clés publiques encodées en base64.

L’extension sodium native de PHP est utilisée quand elle est disponible. Sinon, WordPress peut recourir au polyfill `sodium_compat` fourni, sous réserve d’un contrôle de performance à l’exécution. Si la vérification ne peut pas s’exécuter en temps raisonnable, le cœur renvoie une `WP_Error` avec le code `signature_verification_unsupported` plutôt que d’ignorer silencieusement la cryptographie. Si aucune signature n’accompagne le paquet, la vérification échoue avec `signature_verification_no_signature`. Si aucune signature candidate ne correspond à une clé de confiance, la vérification échoue avec `signature_verification_failed`.

La liste des clés de confiance est filtrable par `wp_trusted_keys`. Les assistants de téléchargement HTTP tels que `download_url()` intègrent ce chemin, si bien que la vérification a lieu après le téléchargement du ZIP et avant que le moteur de mise à niveau ne considère le paquet comme sûr pour l’extraction.

**Comportement d’échec non bloquant :** `WP_Upgrader::run()` traite une `WP_Error` de signature qui porte `softfail-filename` dans ses données d’erreur comme un cas non fatal pour tout type de paquet (`wp-admin/includes/class-wp-upgrader.php:886-904`). `Core_Upgrader::upgrade()` suit le même chemin (`wp-admin/includes/class-core-upgrader.php:134-148`). Le filtre `wp_signature_softfail` décide si un échec est marqué comme non bloquant (`wp-admin/includes/file.php:1348`). L’échec peut apparaître comme retour d’information pendant que le chemin de téléchargement issu des données d’erreur est utilisé, ce qui permet à la mise à niveau de continuer. Les erreurs sans ce marqueur interrompent l’opération.

#### Déroulement

1. L’URL d’un paquet est téléchargée vers un fichier temporaire.
2. Les éléments de signature de la réponse de mise à jour ou des métadonnées associées passent dans `verify_file_signature()`.
3. En cas de succès, l’installation continue.
4. Sur une `WP_Error`, la mise à niveau s’interrompt pour ce paquet, sauf si l’erreur porte `softfail-filename`, que `WP_Upgrader::run()` traite comme un échec non bloquant.
5. Sur les environnements non pris en charge, l’opération échoue avec un code d’erreur défini plutôt que de terminer une installation non vérifiée.

#### Référence

| Fonction | Rôle |
|---|---|
| `verify_file_signature()` | Vérification Ed25519 sur l’empreinte SHA-384 du fichier sur disque |
| `wp_trusted_keys()` | Renvoie les clés publiques de confiance encodées en base64 (filtrables) |
| `sodium_crypto_sign_verify_detached()` | Primitive utilisée quand elle est disponible ; `sodium_compat` peut la remplacer si elle est assez rapide |

#### Ressources pour les développeurs

- [Référence de `verify_file_signature()`](https://developer.wordpress.org/reference/functions/verify_file_signature/) — API de vérification des signatures.
- [Référence de `wp_trusted_keys()`](https://developer.wordpress.org/reference/functions/wp_trusted_keys/) — clés de signature de confiance.
- [Sécurité dans WordPress 5.2](https://make.wordpress.org/core/2019/05/17/security-in-5-2/) — notes de version sur les signatures et la confiance.

---

### 2.13 Le cycle de vie du dossier de préparation et son nettoyage

Les archives téléchargées et les arborescences de travail décompressées utilisent deux emplacements différents. `download_url()` écrit le ZIP dans un répertoire temporaire général par `wp_tempnam()` et `get_temp_dir()`. `WP_Upgrader::unpack_package()` prépare les fichiers décompressés sous `wp-content/upgrade/`. Ce répertoire est l’espace de travail principal sur disque avant que les fichiers ne rejoignent `wp-content/plugins/`, `wp-content/themes/` ou la racine du cœur. Son contenu est transitoire ; des exécutions en échec ou interrompues peuvent laisser des débris qui gênent les mises à niveau ultérieures.

#### Fonctionnement

`download_url()` stocke le fichier entrant dans le répertoire temporaire (répertoire temporaire du système, temporaire des téléversements, `WP_CONTENT_DIR` ou `/tmp/` selon `get_temp_dir()`, sauf si `WP_TEMP_DIR` le remplace). `WP_Upgrader::download_package()` transmet les URL distantes à `download_url()` ; le chemin renvoyé alimente ensuite `unpack_package()`.

`unpack_package()` vide `wp-content/upgrade/` par `WP_Filesystem` mais conserve `index.php`, et crée ce fichier quand il manque (`wp-admin/includes/class-wp-upgrader.php:384-406`). Il construit ensuite un nom de sous-répertoire de travail à partir du nom de base du paquet et d’un suffixe aléatoire de 16 caractères (`get_working_dir_name()`, privée, `:349-359`) et exécute `unzip_file()` dans ce dossier. Après l’extraction, WordPress supprime généralement le ZIP quand `$delete_package` vaut vrai. Si `unzip_file()` échoue, le cœur supprime le répertoire de travail (`:424-430`).

Une extraction bloquée ou partielle laisse des répertoires ou des fichiers partiels sous `upgrade/`. Un disque plein, des erreurs de permissions ou des processus PHP tués peuvent orpheliner des fichiers ZIP temporaires ou des arborescences `upgrade/`. À l’exécution suivante, `unpack_package()` tente de vider `upgrade/` avant de décompresser, mais des collisions ou des chemins résiduels peuvent encore produire une `WP_Error` de la couche de système de fichiers.

Le cœur ne garantit pas le nettoyage immédiat de chaque chemin partiel après chaque échec. Les administrateurs peuvent devoir supprimer le contenu périmé d’`upgrade/` lors d’un diagnostic. Les arborescences temporaires liées au retour en arrière sous `wp-content/upgrade-temp-backup/` suivent leurs propres règles de suppression et de nettoyage par cron (voir §2.7).

#### Déroulement

1. Le téléchargement arrive dans un fichier temporaire (pas nécessairement sous `upgrade/`).
2. `unpack_package()` décompresse l’archive dans `wp-content/upgrade/{package-basename}-{random}/` et conserve `index.php` dans le répertoire parent.
3. En cas de succès, les fichiers rejoignent leur destination finale et WordPress peut supprimer le fichier du paquet.
4. En cas d’échec, des restes peuvent subsister dans le répertoire temporaire et sous `upgrade/`.
5. Une suppression manuelle ou un nettoyage du système de fichiers résout les échecs durs.
6. De l’espace disque libre et des permissions d’écriture restent des prérequis.

#### Référence

| Emplacement | Contenu typique | Risque s’il est orphelin |
|---|---|---|
| Répertoire temporaire général (`get_temp_dir()` ou `wp_tempnam()`) | `.zip` ou `.tmp` téléchargé avant décompression | Gros fichiers résiduels ; pression sur le disque |
| `wp-content/upgrade/` | Vidé avant décompression ; dossiers de travail extraits pendant l’installation | Collisions de noms ; erreurs de permissions ou de disque à l’exécution suivante |
| `wp-content/upgrade-temp-backup/` | Instantanés de retour en arrière (voir §2.7) | Pression sur l’espace ; crochets de nettoyage distincts |

#### Ressources pour les développeurs

- [Référence de `download_url()`](https://developer.wordpress.org/reference/functions/download_url/) — télécharge par `wp_tempnam()` ; le chemin vient de `get_temp_dir()`.
- [Référence de `get_temp_dir()`](https://developer.wordpress.org/reference/functions/get_temp_dir/) — répertoire temporaire accessible en écriture pour les téléchargements.
- [Référence de la classe `WP_Upgrader`](https://developer.wordpress.org/reference/classes/wp_upgrader/) — `unpack_package()` utilise `wp-content/upgrade/` pour l’extraction.

---

## Partie 3 — Les mises à jour automatiques en arrière-plan

### 3.1 Le modèle du programme de mise à jour automatique

`WP_Automatic_Updater` applique les mises à jour selon un calendrier, sans identifiants interactifs. Vous attachez ses décisions et ses résultats séparément de l’usage manuel du moteur de mise à niveau.

#### Fonctionnement

Pendant le cron, `wp_version_check()` peut appeler `do_action( 'wp_maybe_auto_update' )` quand l’exécution n’est pas déjà dans cette action. `wp_maybe_auto_update()` charge les inclusions de l’administration, instancie `WP_Automatic_Updater` et appelle `run()`. La classe utilise `Automatic_Upgrader_Skin`, évalue `is_disabled()` et `should_update()` par élément, choisit les paquets dans les transients et exécute les moteurs de mise à niveau.

Dans `run()`, après l’acquisition du verrou `auto_updater`, le cœur traite les mises à jour dans un ordre fixe : les extensions (depuis `update_plugins` après `wp_update_plugins()`), puis les thèmes (`update_themes` après `wp_update_themes()`), puis le cœur (`wp_version_check()` plus `find_core_auto_update()`), puis les paquets de traduction de `wp_get_translation_updates()`. Sur multisite, `run()` s’arrête sauf si le site courant est le réseau principal et le site principal (`wp-admin/includes/class-wp-automatic-updater.php:647-649`).

Le crochet `automatic_updates_complete` se déclenche à la fin d’un lot uniquement quand `$update_results` n’est pas vide. Si rien ne s’est exécuté ou si rien n’a enregistré de résultat, le crochet ne se déclenche pas. Voir §4.7 pour les e-mails et ce crochet.

#### Déroulement

1. Le cron ou un déclencheur externe exécute `wp-cron.php`.
2. Les vérifications de mise à jour peuvent s’enchaîner sur `wp_maybe_auto_update`.
3. `run()` vérifie `is_disabled()`. Si ce contrôle passe, le programme de mise à jour parcourt la séquence extensions, thèmes, cœur et traductions.
4. Chaque élément est téléchargé et installé par la même pile de moteurs de mise à niveau que les flux manuels, avec un habillage non interactif.
5. Quand des résultats existent, la fin du lot déclenche les chemins de notification et `automatic_updates_complete` (partie 4).

#### Référence

| Classe | Fichier | Hérite de | Rôle |
|---|---|---|---|
| `WP_Automatic_Updater` | `class-wp-automatic-updater.php` | — | Décisions et exécution en arrière-plan ; ordre de `run()` : extensions, thèmes, cœur, traductions |

#### Ressources pour les développeurs

- [Référence de la classe `WP_Automatic_Updater`](https://developer.wordpress.org/reference/classes/wp_automatic_updater/) — référence de la classe.

---

### 3.2 Les conditions de désactivation globale (`is_disabled()`)

Le programme de mise à jour automatique se court-circuite quand le site ne peut pas ou ne doit pas modifier de fichiers, ou quand vous le désactivez explicitement.

#### Fonctionnement

`WP_Automatic_Updater::is_disabled()` renvoie vrai quand `wp_is_file_mod_allowed( 'automatic_updater' )` est faux (par exemple quand `DISALLOW_FILE_MODS` vaut vrai, sauf filtrage), quand `wp_installing()` renvoie vrai, ou quand `AUTOMATIC_UPDATER_DISABLED` vaut vrai ou que le filtre `automatic_updater_disabled` renvoie vrai. Désactiver le programme de mise à jour automatique supprime aussi ses e-mails de notification dans le comportement actuel du cœur.

#### Déroulement

1. `run()` appelle `is_disabled()`.
2. Si la méthode renvoie vrai, aucune mise à jour automatique ne s’exécute.
3. Les points d’entrée qui dépendent des mises à jour en arrière-plan devraient exposer cet état dans leurs diagnostics.

> **Note :** `should_update()` appelle aussi `is_disabled()` en premier pour chaque élément, si bien qu’une désactivation globale s’applique lors de l’évaluation des offres dans `find_core_auto_update()` et ailleurs, pas seulement au début de `run()`.

#### Référence

| Condition | Effet |
|---|---|
| `wp_is_file_mod_allowed( 'automatic_updater' )` renvoie faux | Mises à jour bloquées |
| `wp_installing()` renvoie vrai | Mises à jour bloquées |
| `AUTOMATIC_UPDATER_DISABLED` vaut vrai ou `automatic_updater_disabled` renvoie vrai | Mises à jour bloquées |

#### Ressources pour les développeurs

- [Référence du filtre `automatic_updater_disabled`](https://developer.wordpress.org/reference/hooks/automatic_updater_disabled/) — filtre pour désactiver les mises à jour automatiques.

---

### 3.3 L’éligibilité par élément (`should_update()`)

Chaque élément candidat passe par `should_update( $type, $item, $context )`, qui fusionne la réalité du système de fichiers, la détection VCS, les indicateurs de l’API, les adhésions, les filtres et les contrôles de compatibilité.

#### Fonctionnement

`should_update()` commence par appeler `is_disabled()` à nouveau. Si le programme de mise à jour automatique est désactivé globalement, la méthode renvoie faux avant toute logique par élément, si bien que `find_core_auto_update()` et les autres appelants respectent eux aussi la désactivation globale.

Les contrôles restants combinent : l’accès non interactif au système de fichiers (l’arrière-plan ne peut pas afficher de formulaires FTP) ; la détection d’une copie de travail VCS (voir §3.4) ; les règles d’autorisation par type (`Core_Upgrader::should_update_to_version()` pour le cœur, ou `autoupdate` plus `auto_update_plugins` et `auto_update_themes` pour les extensions et les thèmes, ou `autoupdate` pour les traductions) ; `disable_autoupdate` sur l’offre (voir §1.4) ; `apply_filters( "auto_update_{$type}", … )` ; puis, pour le cœur, les contrôles PHP et MySQL par rapport à l’offre ; pour les extensions et les thèmes, `requires_php` s’il est défini.

Pour le cœur, les contrôles de compatibilité PHP et MySQL s’exécutent après `disable_autoupdate` et `auto_update_core`, pas au début (voir §3.7).

#### Déroulement

1. `should_update()` appelle `is_disabled()`. Si la valeur est vraie, la méthode renvoie faux immédiatement.
2. Le cœur vérifie si le système de fichiers prend en charge les écritures non interactives.
3. La détection de copie de travail VCS s’exécute pour le chemin concerné.
4. Les règles d’autorisation propres au type s’évaluent : politique de branche pour le cœur, ou `autoupdate` plus les tableaux d’adhésion pour les extensions et les thèmes.
5. La propriété `disable_autoupdate` peut forcer le résultat à faux.
6. Le filtre `auto_update_{$type}` s’exécute.
7. Pour le cœur : les contrôles de compatibilité PHP et MySQL s’évaluent par rapport à l’offre. Pour les extensions et les thèmes : `requires_php` s’évalue par rapport au PHP à l’exécution.
8. Un résultat faux à une étape interrompt cet élément. Un résultat vrai poursuit vers le téléchargement et l’installation.
9. L’action `pre_auto_update` se déclenche une fois par élément avant la tentative (voir §6.5).

#### Référence

| Contrôle | Résultat typique en cas d’échec |
|---|---|
| `is_disabled()` | Désactivation globale ; renvoie faux (court-circuit) |
| Système de fichiers | Aucune capacité d’écriture silencieuse ; renvoie faux |
| VCS | Copie de travail détectée ; renvoie faux (sauf filtrage) |
| Autorisation par type (politique de branche, `autoupdate` ou listes d’adhésion) | Renvoie faux |
| `disable_autoupdate` | Force faux avant `auto_update_{$type}` ; le filtre peut réactiver |
| `auto_update_{$type}` | Le filtre renvoie faux ; renvoie faux (le cœur peut encore déclencher l’e-mail de notification) |
| PHP ou MySQL (cœur) ou `requires_php` (extension ou thème) | Environnement incompatible ; renvoie faux |

#### Ressources pour les développeurs

- [Référence de `WP_Automatic_Updater::should_update()`](https://developer.wordpress.org/reference/classes/wp_automatic_updater/should_update/) — méthode d’éligibilité.

---

### 3.4 La détection d’une copie de travail VCS

Les mises à jour automatiques écartent les chemins qui ressemblent à des copies de travail de contrôle de version, pour éviter d’écraser les dispositions d’un développeur ou d’un déploiement.

#### Fonctionnement

`WP_Automatic_Updater::is_vcs_checkout()` remonte depuis le chemin du paquet et vérifie toujours `ABSPATH` à la recherche des marqueurs `.svn`, `.git`, `.hg` ou `.bzr`. Avant de vérifier un répertoire, `is_allowed_dir()` écarte les chemins hors de `open_basedir` (`wp-admin/includes/class-wp-automatic-updater.php:73-107`). Une correspondance désactive les mises à jour automatiques pour cet élément, sans notification d’administration. Le filtre `automatic_updates_is_vcs_checkout` reçoit `( bool $checkout, string $context )` et peut renvoyer faux pour passer outre la détection (par exemple sur une CI ou un hébergement géré).

Un clone Git du cœur ou d’une extension sous `wp-content` peut bloquer silencieusement les mises à jour automatiques pour ce chemin.

#### Déroulement

1. `should_update()` appelle la détection VCS pour le contexte concerné.
2. Si la détection renvoie vrai, l’élément est écarté, sauf si le filtre lève l’indicateur.
3. Les mises à jour manuelles par l’interface d’administration peuvent encore se poursuivre avec les identifiants appropriés.

#### Référence

| Crochet | Type | Paramètres | Remarques |
|---|---|---|---|
| `automatic_updates_is_vcs_checkout` | filtre | `$checkout`, `$context` | Passe outre la détection VCS |

#### Ressources pour les développeurs

- [Référence de `WP_Automatic_Updater::is_vcs_checkout()`](https://developer.wordpress.org/reference/classes/wp_automatic_updater/is_vcs_checkout/) — assistant de détection.

---

### 3.5 Les garde-fous de compatibilité PHP et MySQL

Les offres peuvent déclarer des versions PHP ou de base de données requises. Les mises à jour automatiques refusent les cibles incompatibles avant de commencer une tentative.

#### Fonctionnement

Pour le cœur, `should_update()` compare la version de PHP à `$item->php_version` et celle de MySQL à `$wpdb->db_version()`, sauf si un drop-in remplace MySQL. Une différence renvoie faux sans tentative de mise à jour. Pour les extensions et les thèmes, si `$item->requires_php` est défini et que le PHP à l’exécution est inférieur, le résultat est faux.

Ces contrôles s’exécutent dans `should_update()` après les garde-fous du système de fichiers et de la détection VCS, les règles d’autorisation par type, `disable_autoupdate` et `auto_update_{$type}`, et non comme premier filtre (voir §3.3 et §3.7).

#### Déroulement

1. Des garde-fous antérieurs peuvent déjà renvoyer faux.
2. Si l’élément est encore éligible, `should_update()` applique les contrôles PHP et MySQL (cœur) ou `requires_php` (extensions et thèmes).
3. Un environnement incompatible renvoie faux avant l’exécution d’`update()`.

#### Référence

| Type | Garde-fou |
|---|---|
| Cœur | Comparaison des versions PHP et MySQL avec l’offre |
| Extension ou thème | Comparaison de `requires_php` avec l’environnement à l’exécution |

#### Ressources pour les développeurs

- [Référence de `WP_Automatic_Updater::should_update()`](https://developer.wordpress.org/reference/classes/wp_automatic_updater/should_update/) — décrit le comportement des garde-fous.

---

### 3.6 `find_core_auto_update()` : la sélection de l’offre du cœur

Une seule offre automatique du cœur s’exécute par lot. `find_core_auto_update()` la choisit dans le transient `update_core`.

#### Fonctionnement

La fonction lit le transient de site `update_core`, parcourt le tableau `updates` et écarte toute offre dont `response` n’est pas `autoupdate`. Pour chaque offre restante, elle appelle `should_update( 'core', $update, ABSPATH )`. Elle retient ensuite la version la plus élevée parmi les offres acceptées, par comparaison de version sur la propriété `current`, ou renvoie faux si aucune ne convient.

Une mise à jour automatique du cœur ne s’exécute jamais sans une offre dont `response === 'autoupdate'` et sans un `should_update()` réussi. Les entrées réservées aux mises à jour manuelles dans l’interface ne passent pas par ce sélecteur.

#### Déroulement

1. Le cœur charge le transient `update_core`.
2. Les offres sans `response === 'autoupdate'` sont écartées.
3. Chaque candidat passe par `should_update()`.
4. La version acceptée la plus élevée l’emporte.
5. Si aucune ne convient, la fonction renvoie faux et aucune mise à jour automatique du cœur ne s’exécute dans ce lot.

#### Référence

| Fonction | Fichier | Retourne | Remarques |
|---|---|---|---|
| `find_core_auto_update()` | `wp-admin/includes/update.php` | objet ou faux | Sélectionne l’offre automatique du cœur |

#### Ressources pour les développeurs

- [Référence de `find_core_auto_update()`](https://developer.wordpress.org/reference/functions/find_core_auto_update/) — référence de la fonction.

---

### 3.7 La politique de branche du cœur et l’ordre de résolution

Les mises à jour automatiques du cœur combinent la politique de la constante, les options de site, les filtres de branche, les vetos d’offre et le filtre `auto_update_core` propre à chaque offre. L’ordre ci-dessous est la chaîne de résolution à prendre en compte.

#### Fonctionnement

`find_core_auto_update()` ne considère que les offres dont `response === 'autoupdate'`, puis appelle `should_update( 'core', $update, ABSPATH )` pour chaque candidat.

`Core_Upgrader::should_update_to_version()` applique la constante `WP_AUTO_UPDATE_CORE` quand elle est définie, ou sinon les options de site `auto_update_core_dev`, `auto_update_core_minor` et `auto_update_core_major`. Il évalue aussi l’historique des échecs, la logique de branche et les filtres `allow_dev_auto_core_updates`, `allow_minor_auto_core_updates` et `allow_major_auto_core_updates` selon la branche. Chaque filtre reçoit le booléen dérivé des constantes et des options.

La propriété `disable_autoupdate` de l’offre peut forcer la décision en attente à faux ; des filtres peuvent encore réactiver l’élément. Ensuite, `apply_filters( 'auto_update_core', $update, $item )` s’exécute. Les contrôles de compatibilité PHP et MySQL sur l’offre suivent.

> **Note :** `apply_filters( 'wp_auto_update_core', … )` n’est pas invoqué par `Core_Upgrader` dans le cœur, pour les versions inspectées. Ne confondez pas ce nom de filtre avec la gestion de l’option de site ou de la constante. La politique qui détermine quelles classes de mise à jour du cœur sont autorisées vit dans `should_update_to_version()`. Le crochet dynamique `auto_update_core` est le filtre par offre à l’intérieur de `should_update()`, après le calcul de l’autorisation ou du refus initial pour cette offre.

#### Déroulement

1. Seules les offres dont `response === 'autoupdate'` sont considérées.
2. `Core_Upgrader::should_update_to_version()` évalue la constante, les options `auto_update_core_*`, l’historique des échecs, la logique de branche et les filtres `allow_*_auto_core_updates`, et produit un booléen.
3. `disable_autoupdate` sur l’offre peut forcer faux ; des filtres peuvent réactiver.
4. `apply_filters( 'auto_update_core', $update, $item )` s’exécute.
5. Les contrôles de compatibilité PHP et MySQL sur l’offre suivent.

#### Référence

| Crochet | Type | Paramètres | Remarques |
|---|---|---|---|
| `allow_dev_auto_core_updates` | filtre | bool | Installations de versions de développement |
| `allow_minor_auto_core_updates` | filtre | bool | Même branche x.y |
| `allow_major_auto_core_updates` | filtre | bool | x.y vers x.y+1 |
| `auto_update_core` | filtre | bool, objet | Après la décision initiale du cœur pour l’offre |

#### Ressources pour les développeurs

- [Référence du filtre `auto_update_{$type}`](https://developer.wordpress.org/reference/hooks/auto_update_type/) — inclut `auto_update_core`.

---

### 3.8 Les préférences de mise à jour automatique par extension et par thème

Des options de site listent les extensions et les thèmes qui ont adhéré aux mises à jour automatiques. Ces options fusionnent avec les indicateurs `autoupdate` des offres.

#### Fonctionnement

`auto_update_plugins` stocke un tableau de noms de base d’extensions. `auto_update_themes` stocke les identifiants des thèmes adhérents. L’action AJAX `toggle-auto-updates` enregistre ces options sans exécuter le moteur de mise à niveau. `should_update()` les fusionne avec l’indicateur `autoupdate` de l’offre quand les mises à jour automatiques sont activées pour le type. `wp_is_auto_update_enabled_for_type()` combine `is_disabled()` avec les filtres `plugins_auto_update_enabled` et `themes_auto_update_enabled` (`wp-admin/includes/update.php:1042-1068`).

Multisite restreint les personnes autorisées à modifier ces bascules (voir §5.11 et §5.4).

#### Déroulement

1. Un utilisateur bascule le contrôle de l’interface, ou un changement de politique réseau met à jour les options.
2. Le prochain appel à `should_update()` lit les tableaux d’adhésion.
3. Une offre sans `autoupdate` exige encore une adhésion pour cet élément, sauf si un filtre change le résultat.

#### Référence

| Nom | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `auto_update_plugins` | option de site (tableau) | vide | Noms de base des extensions ayant adhéré aux mises à jour automatiques |
| `auto_update_themes` | option de site (tableau) | vide | Identifiants des thèmes ayant adhéré aux mises à jour automatiques |

#### Ressources pour les développeurs

- [Contrôler l’interface de mise à jour automatique des extensions et des thèmes dans WordPress 5.5](https://make.wordpress.org/core/2020/07/15/controlling-plugin-and-theme-auto-updates-ui-in-wordpress-5-5/) — interface et stockage.

---

### 3.9 Les mises à jour automatiques des traductions et l’enchaînement asynchrone

Les traductions se mettent à jour automatiquement avec leurs propres filtres et peuvent s’installer dans une passe enchaînée après la fin d’une autre mise à niveau.

#### Fonctionnement

Deux filtres s’appliquent dans des contextes différents.

`async_update_translation` s’exécute dans `Language_Pack_Upgrader::async_upgrade()` uniquement après une mise à niveau réussie du cœur, d’une extension ou d’un thème dans la même requête. Il décide s’il faut installer en lot les paquets de traduction dans la passe de suivi ; la décision en attente suit `autoupdate` sur chaque offre de langue. Avant l’installation, `async_upgrade()` s’arrête sans agir si `WP_Automatic_Updater::is_vcs_checkout( WP_CONTENT_DIR )` détecte une copie de travail VCS sous `wp-content`, conformément au comportement prudent de ce chemin de code.

`auto_update_translation` participe à `should_update()` pour les mises à jour automatiques de traductions déclenchées par le cron, comme les autres crochets `auto_update_{$type}`.

Modifier un filtre ne modifie pas automatiquement l’autre.

#### Déroulement

1. Une mise à niveau principale se termine.
2. L’action `upgrader_process_complete` se déclenche.
3. À la priorité 20, la mise à niveau asynchrone des traductions peut s’exécuter (sauf si la détection VCS la court-circuite).
4. Séparément, les mises à jour automatiques déclenchées par le cron évaluent les éléments de traduction par `should_update()`, y compris le filtre `auto_update_translation`.

#### Référence

| Crochet | Type | Paramètres | Remarques |
|---|---|---|---|
| `async_update_translation` | filtre | bool | Enchaînement après mise à niveau dans la même requête |
| `auto_update_translation` | filtre | bool | `should_update()` en arrière-plan pour les traductions |

#### Ressources pour les développeurs

- [Référence du filtre `async_update_translation`](https://developer.wordpress.org/reference/hooks/async_update_translation/) — enchaînement après mise à niveau.

---

## Partie 4 — Notifications et retour d’information

### 4.1 Le modèle de notification

WordPress sépare le retour d’information dans l’administration de l’e-mail. Les mises à jour manuelles s’affichent dans les habillages ou en AJAX ; les mises à jour en arrière-plan peuvent envoyer un e-mail à l’administrateur du site.

#### Fonctionnement

Les utilisateurs connectés voient des notifications, des pastilles de menu et l’écran Mises à jour de WordPress, alimentés par les transients et les capacités. `WP_Automatic_Updater` envoie les e-mails des résultats d’arrière-plan par des méthodes dédiées. La sortie manuelle de WP-CLI reste dans le terminal, sauf si des crochets ajoutent du comportement. Désactiver le programme de mise à jour automatique supprime aussi ses e-mails de notification, d’après la documentation du cœur.

#### Déroulement

1. La découverte met à jour les transients.
2. L’interface d’administration lit les transients et affiche les informations de mise à jour.
3. Si des mises à jour en arrière-plan s’exécutent, un e-mail peut être envoyé après leur fin.
4. L’action `automatic_updates_complete` ne s’exécute que lorsque `WP_Automatic_Updater::run()` enregistre au moins un résultat dans son tableau interne `$update_results`. Si le lot ne produit aucune tentative suivie, le crochet ne se déclenche pas (§4.7).

#### Référence

| Chemin | Canal |
|---|---|
| Administration manuelle | Habillage ou JSON AJAX |
| Arrière-plan | E-mail et crochets facultatifs |
| WP-CLI | Sortie standard |

#### Ressources pour les développeurs

- [Référence de l’action `automatic_updates_complete`](https://developer.wordpress.org/reference/hooks/automatic_updates_complete/) — se déclenche après un lot d’arrière-plan dont les résultats ne sont pas vides.

---

### 4.2 Les notifications et les pastilles de menu dans l’administration

La messagerie dans l’administration utilise des crochets et des assistants, sans `wp_mail()`, pour les notifications ordinaires de disponibilité de mise à jour.

#### Fonctionnement

`update_nag()`, dans `wp-admin/includes/update.php`, s’attache à `admin_notices` et à `network_admin_notices` à la priorité 3. Elle affiche une notification d’avertissement dans l’administration (par `wp_admin_notice()` avec le type `warning` et des classes telles que `update-nag`) quand une mise à niveau du cœur est disponible depuis `get_preferred_from_update_core()` avec `response === 'upgrade'`. Elle s’abstient sur `update-core.php`. Les utilisateurs qui n’ont pas la capacité `update_core` peuvent voir un texte les invitant à prévenir l’administrateur du site.

La pastille de compteur de mises à jour du menu d’administration utilise `wp_get_update_data()` depuis `wp-admin/menu.php`. Le widget D’un coup d’œil utilise `update_right_now_message()` pour afficher un bouton de mise à jour quand cela s’applique.

#### Déroulement

1. La page d’administration s’affiche.
2. Les transients fournissent les compteurs de mise à jour.
3. Les capacités déterminent quels messages apparaissent.
4. Aucun e-mail n’est envoyé par ces mécanismes seuls.

#### Référence

| Mécanisme | Objet |
|---|---|
| `update_nag()` | Notification de mise à niveau du cœur |
| Pastille de menu | Compteurs de mise à jour agrégés |
| Widget D’un coup d’œil | Bouton d’action rapide quand l’utilisateur a les capacités |

#### Ressources pour les développeurs

- [Référence de `wp_get_update_data()`](https://developer.wordpress.org/reference/functions/wp_get_update_data/) — compteurs de mise à jour pour le menu.

---

### 4.3 Santé du site : les tests de diagnostic liés aux mises à jour

Santé du site met en évidence les risques de configuration. Il ne bloque pas les mises à jour.

#### Fonctionnement

`WP_Site_Health` exécute des tests de diagnostic. Les tests de mise à jour en arrière-plan reflètent des facteurs proches de `WP_Automatic_Updater::is_disabled()` : constantes, filtres, détection VCS et indicateurs associés. Les tests concernés sont `background_updates` (`wp-admin/includes/class-wp-site-health.php:1740`), `plugin_theme_auto_updates` (`:1815`), `available_updates_disk_space` (`:1854`), `update_temp_backup_writable` (`:1940`) et `loopback_requests` (`:2079`). Les tests de boucle locale vérifient que le serveur arrive à s’adresser une requête HTTP à lui-même (utile pour les éditeurs de fichiers et pour la détection des erreurs fatales de WordPress 6.6 et suivantes). WordPress 6.3 et suivantes ajoutent des contrôles sur l’écriture dans le dossier de sauvegarde et sur l’espace disque, liés aux sauvegardes temporaires du retour en arrière manuel. Les résultats sont purement informatifs. Vérifiez le comportement dans le code du programme de mise à jour lors d’un diagnostic.

#### Déroulement

1. La page Santé du site charge les tests.
2. Chaque test renvoie un statut et des messages.
3. Les administrateurs utilisent les résultats pour corriger les problèmes d’environnement.

#### Référence

| Sujet | Pertinence |
|---|---|
| Mises à jour en arrière-plan | Reflète les conditions de désactivation |
| Boucle locale | Fonctionnalités asynchrones et retour en arrière |
| Dossier de sauvegarde et espace disque | Espace du retour en arrière manuel |

#### Ressources pour les développeurs

- [Référence de la classe `WP_Site_Health`](https://developer.wordpress.org/reference/classes/wp_site_health/) — classe de Santé du site.

---

### 4.4 Les e-mails de mise à jour du cœur en arrière-plan

Les mises à jour automatiques du cœur envoient un e-mail à l’administrateur du site en cas de succès, d’échec, d’échec critique et parfois d’invitation à une mise à jour manuelle lorsque l’application automatique a été ignorée.

#### Fonctionnement

`WP_Automatic_Updater` implémente des chemins tels que `after_core_update()` et `send_core_update_notification_email()`. Les types incluent la notification `success`, `fail`, `critical` et `manual` (quand elle est conditionnée par l’indicateur `notify_email` de l’API et par des filtres). Les options de site `auto_core_update_notified` et `auto_core_update_failed` dédupliquent les envois ou planifient les nouvelles tentatives.

Le filtre `send_core_update_notification_email` décide s’il faut envoyer l’e-mail « nouvelle version du cœur disponible » (l’invitation manuelle). Le filtre `auto_core_update_send_email` s’applique uniquement aux types `success`, `fail` et `critical` — il ne s’exécute pas pour les e-mails `manual`, car `send_email()` ignore ce filtre quand `$type === 'manual'`. Le filtre `auto_core_update_email` modifie le message composé pour les envois qui passent par `send_email()`.

Le destinataire est généralement `get_site_option( 'admin_email' )`, avec un changement de locale possible.

#### Déroulement

1. Une mise à jour du cœur en arrière-plan se termine ou échoue.
2. Le cœur choisit le type d’e-mail.
3. Les filtres concernés s’exécutent.
4. `wp_mail()` envoie l’e-mail, sauf si un filtre le désactive.
5. Les options de persistance évitent les e-mails en double pour la même version.

#### Référence

| Crochet | Type | Paramètres | Remarques |
|---|---|---|---|
| `send_core_update_notification_email` | filtre | bool, objet | Décide s’il faut envoyer l’e-mail de disponibilité d’une mise à jour manuelle (chemin `notify_email`) |
| `auto_core_update_send_email` | filtre | bool, chaîne, objet, mixte | Filtre les e-mails d’arrière-plan de succès, d’échec et d’échec critique — pas `manual` |
| `auto_core_update_email` | filtre | tableau, chaîne, objet, mixte | Modifie le destinataire, l’objet, le corps et les en-têtes pour `send_email()` |

#### Ressources pour les développeurs

- [Référence du filtre `auto_core_update_email`](https://developer.wordpress.org/reference/hooks/auto_core_update_email/) — filtre de la charge utile de l’e-mail.

---

### 4.5 Les e-mails de mise à jour des extensions et des thèmes en arrière-plan

Les lots automatiques d’extensions et de thèmes peuvent envoyer des résumés en cas de succès, d’échec ou de résultats mixtes.

#### Fonctionnement

Après des mises à jour automatiques d’extensions ou de thèmes, `after_plugin_theme_update()` peut appeler `send_plugin_theme_email()` avec les types `success`, `fail` ou `mixed`.

`auto_plugin_update_send_email` et `auto_theme_update_send_email` reçoivent `( $enabled, $slice )`, où `$slice` est `$update_results['plugin']` ou `$update_results['theme']` (les tableaux d’objets de résultat pour ce type), et non l’arborescence complète de `$update_results`. `auto_plugin_theme_update_email` ajuste la charge utile finale de l’e-mail.

La valeur `auto_plugin_theme_update_emails` est stockée avec `get_option()` et `update_option()` (API des options par site), et non avec `get_site_option()`, pour suivre les versions en échec et limiter les messages répétés. La messagerie de WordPress 6.6 et suivantes peut inclure les résultats du retour en arrière après une erreur fatale détectée par boucle locale.

#### Déroulement

1. Un lot se termine.
2. Le cœur décide s’il faut envoyer un e-mail.
3. Les filtres ajustent ou suppriment l’e-mail.
4. WordPress envoie le message à l’adresse e-mail de l’administrateur, sauf si un filtre l’empêche.

#### Référence

| Crochet | Type | Paramètres | Remarques |
|---|---|---|---|
| `auto_plugin_update_send_email` | filtre | bool, tableau | Filtre d’envoi des e-mails de lot d’extensions ; le second argument est uniquement la liste des résultats d’extensions |
| `auto_theme_update_send_email` | filtre | bool, tableau | Filtre d’envoi des e-mails de lot de thèmes ; le second argument est uniquement la liste des résultats de thèmes |
| `auto_plugin_theme_update_email` | filtre | tableau, chaîne, tableau, tableau | Destinataire, objet, corps et en-têtes finaux ; les deux derniers arguments sont les listes de mises à jour réussies et échouées |

#### Ressources pour les développeurs

- [Référence du filtre `auto_plugin_theme_update_email`](https://developer.wordpress.org/reference/hooks/auto_plugin_theme_update_email/) — filtre combiné des e-mails d’extensions et de thèmes.

---

### 4.6 Les e-mails de débogage (versions de développement)

Les versions de développement de WordPress peuvent envoyer un e-mail de journal de débogage après les mises à jour en arrière-plan, lorsque cette option est activée.

#### Fonctionnement

Si le site exécute une version de développement (une chaîne de version contenant un trait d’union, comme un suffixe de version candidate) et que des résultats de mise à jour en arrière-plan existent, le cœur peut envoyer un e-mail de débogage. Le filtre `automatic_updates_send_debug_email` vaut vrai par défaut uniquement sur les versions de développement. Le filtre `automatic_updates_debug_email` ajuste la charge utile de l’e-mail de débogage.

#### Déroulement

1. Un lot d’arrière-plan se termine sur une version de développement.
2. Le chemin de l’e-mail de débogage évalue les filtres.
3. Un e-mail facultatif est envoyé avec un contenu de diagnostic.

#### Référence

| Crochet | Type | Paramètres | Remarques |
|---|---|---|---|
| `automatic_updates_send_debug_email` | filtre | bool | Active l’e-mail de débogage |
| `automatic_updates_debug_email` | filtre | tableau, tableau, tableau | Ajuste la charge utile, les échecs et les résultats complets |

#### Ressources pour les développeurs

- [Référence du filtre `automatic_updates_debug_email`](https://developer.wordpress.org/reference/hooks/automatic_updates_debug_email/) — contenu de l’e-mail de débogage.

---

### 4.7 `automatic_updates_complete` : la charge utile des résultats

L’action `automatic_updates_complete` transmet un tableau structuré de résultats pour la journalisation et les notifications externes. Quand vous consommez ce crochet, inspectez soigneusement les types, les objets de résultat et les codes d’erreur.

#### Fonctionnement

`do_action( 'automatic_updates_complete', $update_results )` se déclenche à la fin de `WP_Automatic_Updater::run()` quand des résultats existent. Le tableau correspond à la propriété interne `update_results`. Les clés de premier niveau sont des chaînes de type de mise à jour : `core`, `plugin`, `theme` et `translation`. Une clé n’existe que si le lot a tenté au moins une mise à jour de ce type. Chaque valeur est un tableau d’objets de résultat indexé numériquement.

**Quand `messages` et `result` se contredisent en cas d’échec :** Quand une tentative échoue avec une `WP_Error` sur `result` (exemple courant : le code `fs_unavailable` quand `WP_Filesystem` ne peut pas s’initialiser), `messages` peut encore contenir une sortie antérieure ou générique de l’habillage, parfois un texte proche du succès (comme « le site dispose déjà de la dernière version ») qui ne reflète pas l’échec réel. **Si vous construisez des diagnostics destinés aux opérateurs, inspectez `result` avec `is_wp_error()` et exposez le code et le message d’erreur, pas seulement `implode()` de `messages`.** Le texte de l’e-mail de débogage des mises à jour automatiques provient des résultats structurés et de `WP_Error` ; pour retrouver le même journal, il faut fusionner les deux sources.

**Signaux de retour en arrière :** Le cœur peut définir `result` sur une `WP_Error` avec le code `rollback_was_required` quand `Core_Upgrader` revient en arrière après un échec. Les mises à jour automatiques d’extensions sur WordPress 6.6 et suivantes peuvent utiliser des codes tels que `plugin_update_fatal_error_rollback_successful` ou `plugin_update_fatal_error_rollback_failed` sur la même propriété `result`, avec `item` qui identifie l’extension.

#### Déroulement

1. L’exécution du lot se termine.
2. Le cœur remplit des objets de résultat par tentative.
3. `automatic_updates_complete` se déclenche uniquement si `$update_results` n’est pas vide à la fin de `run()`. Sinon, les écouteurs ne s’exécutent jamais.
4. Votre extension peut traiter la journalisation ou des intégrations sortantes depuis ce crochet.

#### Référence

**Forme de premier niveau :**

| Forme | Détails |
|---|---|
| Clés de premier niveau | `'core'`, `'plugin'`, `'theme'`, `'translation'` — présentes uniquement si une tentative a eu lieu |
| Chaque valeur | Tableau numérique d’objets de résultat |

**Chaque objet de résultat :**

| Propriété | Type | Description |
|---|---|---|
| `item` | objet | Offre de mise à jour issue du transient concerné |
| `result` | `true`, `WP_Error` ou autre valeur fausse | Le succès est généralement le booléen `true` ; les échecs sont généralement des `WP_Error` ; d’autres valeurs fausses peuvent indiquer un échec sans `WP_Error` dans certains cas limites |
| `name` | chaîne | Libellé lisible par un humain |
| `messages` | tableau | Chaînes du tampon de messages d’`Automatic_Upgrader_Skin` |

**Champs `item` typiques par type :**

| Type | Identifiants | Version ou paquet | Autres (liste non exhaustive) |
|---|---|---|---|
| `core` | — | `current` (version du ZIP proposé), souvent `version` pour l’affichage | `response` (`autoupdate` pour les candidats automatiques), `locale`, `php_version`, `mysql_version`, `new_files`, URL de téléchargement, `autoupdate`, `disable_autoupdate` quand ils sont présents |
| `plugin` | `plugin`, `slug` | `new_version` | `package`, `url`, `id`, `requires_php`, `autoupdate`, `disable_autoupdate` |
| `theme` | `theme` (slug de feuille de style) | `new_version` | `package`, `url`, `requires_php`, `autoupdate`, `disable_autoupdate` |
| `translation` | `type` (cœur, extension ou thème), `slug`, `language` | `version` | `package`, `autoupdate` — objets issus des tableaux de traduction par `wp_get_translation_updates()` |

#### Ressources pour les développeurs

- [Référence de l’action `automatic_updates_complete`](https://developer.wordpress.org/reference/hooks/automatic_updates_complete/) — référence du crochet.
- [Référence de `WP_Automatic_Updater::run()`](https://developer.wordpress.org/reference/classes/wp_automatic_updater/run/) — là où les résultats sont produits.

---

### 4.8 L’e-mail de récupération après erreur fatale (`WP_Recovery_Mode`)

Les erreurs fatales au démarrage peuvent déclencher le mode de récupération, qui envoie un lien de connexion par e-mail. Ce chemin est distinct de l’e-mail du programme de mise à jour automatique.

#### Fonctionnement

`WP_Recovery_Mode`, dans `wp-includes/class-wp-recovery-mode.php`, gère la détection et l’e-mail quand une erreur fatale casse le site après un changement. Ses déclencheurs, ses options et ses messages diffèrent de ceux des e-mails de `WP_Automatic_Updater`. Ne confondez pas les deux lors du diagnostic d’échecs après mise à jour.

#### Déroulement

1. Une erreur fatale se produit.
2. Le mode de récupération peut s’activer.
3. WordPress envoie à l’administrateur du site un e-mail contenant un lien de connexion sécurisé.
4. L’administrateur résout le problème à l’aide du lien de récupération.
5. Ce processus est distinct des e-mails de succès ou d’échec des mises à jour en arrière-plan.

#### Référence

| Classe | Fichier | Hérite de | Rôle |
|---|---|---|---|
| `WP_Recovery_Mode` | `class-wp-recovery-mode.php` | — | Récupération après erreur fatale et e-mail |

#### Ressources pour les développeurs

- [Référence de la classe `WP_Recovery_Mode`](https://developer.wordpress.org/reference/classes/wp_recovery_mode/) — classe du mode de récupération.

---

## Partie 5 — Points d’entrée et configuration

### 5.1 Vue d’ensemble des points d’entrée

Les mises à jour atteignent la même pile de moteurs de mise à niveau par l’interface d’administration, AJAX (`admin-ajax.php`), le cron (`WP_Automatic_Updater`) et WP-CLI. Le cœur n’expose pas de méthodes XML-RPC qui exécutent la pile de mise à niveau des extensions, des thèmes ou du cœur ; considérez XML-RPC comme hérité pour d’autres flux, pas comme un point d’entrée de mise à jour de premier ordre (§5.7). Le choix du point d’entrée détermine les identifiants, le canal de sortie et le contexte de capacités.

#### Fonctionnement

L’écran Mises à jour de WordPress envoie des formulaires vers les actions de mise à niveau. Les tableaux de liste utilisent des actions de ligne et des actions groupées avec le script `updates`. Les actions AJAX appellent les moteurs de mise à niveau avec `WP_Ajax_Upgrader_Skin`. Le cron invoque `WP_Automatic_Updater`. WP-CLI charge les inclusions de l’administration et appelle directement les primitives. L’API REST fournie ne propose pas d’opération générique « mettre à niveau un paquet installé vers une nouvelle version » (§5.8).

#### Déroulement

1. Un utilisateur ou un processus lance une action.
2. Les capacités et les nonces vérifient la requête.
3. L’habillage approprié s’attache.
4. Le moteur de mise à niveau s’exécute.
5. Le résultat revient par l’habillage, le JSON, le terminal ou l’e-mail selon le point d’entrée.

#### Référence

| Point d’entrée | Sortie |
|---|---|
| Écrans de `wp-admin` | Habillage HTML |
| AJAX | JSON |
| Cron | Habillage d’arrière-plan et e-mail |
| WP-CLI | Terminal |

#### Ressources pour les développeurs

- [Gérer les extensions dans l’administration](https://developer.wordpress.org/apis/managing-plugins/) — contexte de gestion des extensions.

---

### 5.2 L’écran Mises à jour du tableau de bord

`wp-admin/update-core.php` liste les mises à jour du cœur, des extensions, des thèmes et des traductions, et soumet les opérations groupées.

#### Fonctionnement

L’écran se charge par `admin.php`, met en file le script `updates` et vérifie les capacités (`update_core`, `update_plugins`, `update_themes` et `update_languages` selon le cas). Les formulaires envoient des POST vers des actions telles que la mise à niveau du cœur et les mises à jour groupées d’extensions ou de thèmes. Sur multisite, les administrateurs qui ne gèrent pas le réseau peuvent être redirigés vers l’écran Mises à jour du réseau selon la logique d’`update-core.php`.

#### Déroulement

1. Un utilisateur ouvre l’écran Mises à jour de WordPress.
2. Le cœur rafraîchit ou affiche les offres en cache.
3. L’utilisateur soumet un formulaire.
4. Le serveur exécute le moteur de mise à niveau.
5. Une redirection ou un message affiche le résultat.

#### Référence

| Écran | Capacités requises |
|---|---|
| Mises à jour de WordPress | Variables selon l’action : `update_core`, `update_plugins`, `update_themes`, `update_languages` |

#### Ressources pour les développeurs

- [Écran Mises à jour du tableau de bord](https://wordpress.org/documentation/article/dashboard-updates-screen/) — documentation destinée aux utilisateurs de l’écran.

---

### 5.3 Le flux de mise à jour en AJAX

AJAX met à jour les extensions et les thèmes sans rechargement complet de page, avec `admin-ajax.php` et des réponses JSON.

#### Fonctionnement

Le cœur enregistre des actions telles que `update-plugin` et `update-theme` dans `wp-admin/admin-ajax.php`. Les gestionnaires vérifient le nonce `updates`, exigent les capacités, construisent `Plugin_Upgrader` ou `Theme_Upgrader` avec `WP_Ajax_Upgrader_Skin` et exécutent `bulk_upgrade()` même pour un seul élément. Le gestionnaire des extensions appelle `wp_update_plugins()` à chaque mise à jour ; celui des thèmes appelle `wp_update_themes()` uniquement quand le transient `update_themes` est vide (`wp-admin/includes/ajax-actions.php:4364-4367`, `:4703`). Le JSON renvoie un succès ou une erreur pour l’interface asynchrone. L’action `toggle-auto-updates` enregistre `auto_update_plugins` et `auto_update_themes` sans invoquer le moteur de mise à niveau.

#### Déroulement

1. Le navigateur envoie une requête POST au gestionnaire AJAX.
2. WordPress vérifie le nonce et la capacité.
3. Le moteur de mise à niveau s’exécute avec l’habillage AJAX.
4. L’habillage collecte les erreurs et les messages.
5. Le JSON revient au navigateur.
6. L’interface de liste met à jour l’état de la ligne.

#### Référence

| Action | Effet |
|---|---|
| `update-plugin` | Mise à niveau d’une extension par AJAX |
| `update-theme` | Mise à niveau d’un thème par AJAX |
| `toggle-auto-updates` | Bascule uniquement les tableaux d’adhésion ; aucun appel au moteur de mise à niveau |

#### Ressources pour les développeurs

- [AJAX dans les extensions](https://developer.wordpress.org/plugins/javascript/ajax/) — fonctionnement des gestionnaires AJAX de l’administration avec WordPress.

---

### 5.4 L’interface de mise à jour automatique par extension et par thème (WordPress 5.5 et suivantes)

Les tableaux de liste et les détails des thèmes exposent des bascules de mise à jour automatique stockées dans des options de site.

#### Fonctionnement

WordPress 5.5 a ajouté une colonne Mises à jour automatiques et des actions groupées sur l’écran Extensions installées ; les préférences persistent dans `auto_update_plugins`. Les thèmes utilisent `auto_update_themes` et des contrôles dans la fenêtre de détails du thème, sous Apparence > Thèmes.

`plugin_auto_update_setting_html` filtre le balisage du tableau de liste des extensions (`class-wp-plugins-list-table.php`). Pour les écrans de thèmes d’un site unique, `theme_auto_update_setting_template` filtre le modèle de la fenêtre de détails du thème (`wp-admin/themes.php`). La liste des thèmes du réseau utilise `theme_auto_update_setting_html` (`class-wp-ms-themes-list-table.php`).

Qui peut modifier les bascules dépend des capacités et du rôle sur multisite (administrateur du réseau ou du site). Ne supposez pas que seuls les super administrateurs sont concernés dans toutes les configurations.

#### Déroulement

1. Un utilisateur bascule le contrôle.
2. AJAX ou un formulaire enregistre les options.
3. Le prochain appel à `should_update()` lit les tableaux d’adhésion avec les indicateurs des offres.

#### Référence

| Crochet | Type | Paramètres | Remarques |
|---|---|---|---|
| `plugin_auto_update_setting_html` | filtre | chaîne, chaîne, tableau | Liste Extensions installées : HTML de la colonne et du contrôle, fichier de l’extension, données de l’extension |
| `theme_auto_update_setting_template` | filtre | chaîne | Fenêtre de thème d’un site unique : fragment du modèle |
| `theme_auto_update_setting_html` | filtre | chaîne, chaîne, `WP_Theme` | Liste des thèmes du réseau : HTML du contrôle, feuille de style, objet du thème |

#### Ressources pour les développeurs

- [Mises à jour automatiques des extensions et des thèmes](https://wordpress.org/documentation/article/plugins-themes-auto-updates/) — article destiné aux utilisateurs, avec les règles multisite.

---

### 5.5 L’interface de mise à jour automatique des versions majeures du cœur (WordPress 5.6 et suivantes)

L’écran Mises à jour de WordPress contient des contrôles pour les mises à jour automatiques majeures du cœur, stockés séparément des options de branche mineure.

#### Fonctionnement

WordPress 5.6 a ajouté des contrôles d’interface sur `update-core.php` pour adhérer aux mises à jour automatiques majeures du cœur ou s’en retirer. La préférence est stockée dans l’option de site `auto_update_core_major` (sémantique `enabled`, `disabled` ou `unset` par défaut), et non dans une option distincte `wp_auto_update_core`. Voir §1.3, ainsi qu’`update-core.php` et `Core_Upgrader::should_update_to_version()`.

L’action `after_core_auto_updates_settings` se déclenche après les réglages des versions majeures du cœur et reçoit les booléens `dev`, `minor` et `major` (`wp-admin/update-core.php:454`). Quand `WP_AUTO_UPDATE_CORE` est définie dans `wp-config.php`, la constante remplace la politique de branche et les options de site `auto_update_core_*` sont ignorées pour ce chemin de politique.

#### Déroulement

1. Un utilisateur définit une préférence sur l’écran Mises à jour de WordPress.
2. WordPress enregistre l’option, sauf si la constante la remplace.
3. `Core_Upgrader::should_update_to_version()` et `should_update()` appliquent la politique effective avec les filtres de branche.

#### Référence

| Nom | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `auto_update_core_major` | option de site | `unset` | Contrôle de l’interface de mise à jour automatique majeure du cœur (`enabled`, `disabled` ou `unset`) ; voir §1.3 |

#### Ressources pour les développeurs

- [Changements de l’interface de mise à jour automatique des versions majeures du cœur dans WordPress 5.6 (correction)](https://make.wordpress.org/core/2020/11/24/core-major-versions-auto-updates-ui-changes-in-wordpress-5-6-correction/) — note de correction de l’interface.

---

### 5.6 WP-CLI

WP-CLI amorce WordPress et appelle les mêmes primitives de mise à niveau que l’administration après avoir chargé les fichiers requis.

#### Fonctionnement

Les commandes courantes incluent `wp core update`, `wp core update-db`, `wp plugin update`, `wp theme update` et les sous-commandes de mise à jour de `wp language`. WP-CLI n’est pas fourni avec le cœur, mais c’est la surface CLI prise en charge pour la maintenance scriptée. L’installation forcée d’une version du cœur antérieure aux fichiers en cours (`--version` avec `--force`) et ses conséquences sur le schéma sont traitées au §5.13.

#### Déroulement

1. Un opérateur exécute une commande.
2. WP-CLI charge WordPress.
3. Le moteur de mise à niveau ou la routine de mise à niveau s’exécute.
4. La sortie s’affiche dans le terminal.
5. Aucun e-mail du cœur n’est envoyé, sauf si des crochets ajoutent ce comportement.

#### Référence

| Domaine de commande | Exemples |
|---|---|
| Cœur | `wp core update`, `wp core update-db`, `wp core verify-checksums` |
| Paquets | `wp plugin update`, `wp theme update` |
| Langues | `wp language core update`, `wp language plugin update`, `wp language theme update` |

#### Ressources pour les développeurs

- [Index des commandes WP-CLI](https://developer.wordpress.org/cli/commands/) — index du manuel.

---

### 5.7 XML-RPC et mots de passe d’application

Les clients distants hérités peuvent déclencher des flux qui chargent le code de l’administration et exigent des capacités. XML-RPC n’est pas le chemin d’automatisation principal pour les nouveaux projets.

#### Fonctionnement

XML-RPC et les requêtes authentifiées par mot de passe d’application peuvent invoquer les procédures distantes prises en charge, là où elles sont activées. Les mises à jour exigent toujours un accès au système de fichiers et des contrôles de privilèges élevés. Préférez WP-CLI ou des points de terminaison personnalisés maîtrisés pour les nouvelles automatisations.

#### Déroulement

1. Un client s’authentifie.
2. WordPress achemine la requête.
3. Si la procédure effectue des mises à jour, les mêmes contraintes de capacités et de système de fichiers que dans l’interface d’administration s’appliquent.

#### Référence

| Mécanisme | Remarques |
|---|---|
| XML-RPC | Étendue héritée ; conditionné par les capacités |
| Mots de passe d’application | Complément d’authentification REST moderne |

#### Ressources pour les développeurs

- [API des mots de passe d’application](https://developer.wordpress.org/apis/application-passwords/) — API d’authentification.

---

### 5.8 L’API REST : aucun déclencheur de mise à jour natif

Le cœur n’expose pas de point de terminaison REST qui exécute les mises à niveau d’extensions, de thèmes ou du cœur comme opération générique.

#### Fonctionnement

Les routes fournies `/wp/v2/plugins` et `/wp/v2/themes` exposent des métadonnées et prennent en charge l’activation et la désactivation (et l’installation depuis l’annuaire par slug pour les extensions). Les gestionnaires `update_item` (PATCH ou PUT sur une extension ou un thème) ajustent le statut et des champs. Ils ne téléchargent ni n’installent une version plus récente d’un paquet déjà installé.

Les mises à jour programmatiques hors du navigateur utilisent généralement WP-CLI, du code d’administration personnalisé qui charge les classes de moteur de mise à niveau avec une authentification appropriée, ou des schémas calqués sur les gestionnaires d’`admin-ajax.php`.

#### Déroulement

1. Un client REST demande des métadonnées ou des changements de statut autorisés par l’API.
2. La mise à niveau elle-même exige une autre couche d’orchestration.

#### Référence

| Route | Met à niveau un paquet installé vers une version plus récente |
|---|---|
| `/wp/v2/plugins` | Non pris en charge |
| `/wp/v2/themes` | Non pris en charge |

#### Ressources pour les développeurs

- [Référence de l’API REST des extensions](https://developer.wordpress.org/rest-api/reference/plugins/) — opérations prises en charge.
- [Référence de l’API REST des thèmes](https://developer.wordpress.org/rest-api/reference/themes/) — opérations prises en charge.

---

### 5.9 Les méthodes du système de fichiers

`WP_Filesystem` abstrait les accès direct, FTP, FTPS et SSH. Les mises à jour en arrière-plan échouent par défaut sans identifiants non interactifs.

#### Fonctionnement

Le cœur utilise `WP_Filesystem` et `request_filesystem_credentials()`. Les classes de système de fichiers FTP et SSH exposent une propriété publique `$options` depuis 7.1.0 (`wp-admin/includes/class-wp-filesystem-ftpext.php:37`, `class-wp-filesystem-ssh2.php:72`). La constante `FS_METHOD` de `wp-config.php` peut forcer `direct`, `ftpext`, `ftpsockets` ou `ssh2`. Quand le serveur web ne peut pas écrire de fichiers, les flux interactifs affichent le formulaire d’identifiants. Les mises à jour en arrière-plan ne peuvent pas demander d’identifiants. Elles échouent si les identifiants ne sont pas disponibles de façon non interactive. `wp_is_file_mod_allowed( $context )` centralise l’autorisation des modifications, avec pour valeur par défaut `! DISALLOW_FILE_MODS` et le filtre `file_mod_allowed`.

#### Déroulement

1. Le moteur de mise à niveau demande l’accès au système de fichiers.
2. Une méthode directe ou fondée sur des identifiants se connecte.
3. Les écritures se poursuivent ou renvoient une `WP_Error`.
4. Le programme de mise à jour automatique s’arrête quand les modifications de fichiers sont interdites.

#### Référence

| Nom | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `FS_METHOD` | constante | auto | Force une couche de système de fichiers précise |
| `DISALLOW_FILE_MODS` | constante | faux si non définie | Bloque les modifications de fichiers sauf filtrage |

#### Ressources pour les développeurs

- [Présentation de l’API du système de fichiers](https://developer.wordpress.org/apis/filesystem-api/) — accès au système de fichiers.

---

### 5.10 Les constantes de configuration (`wp-config.php`)

Les constantes de `wp-config.php` remplacent les valeurs par défaut du cron, de HTTP, des mises à jour automatiques et du comportement du système de fichiers.

#### Fonctionnement

`AUTOMATIC_UPDATER_DISABLED` désactive la chaîne du programme de mise à jour automatique en arrière-plan, e-mails de notification compris, quand elle vaut vrai. `WP_AUTO_UPDATE_CORE`, en booléen ou en chaîne, délimite les mises à jour automatiques du cœur (`true`, `false`, `minor`, ou des chaînes de canal de préversion selon le comportement de la version). Avant WordPress 5.6, `true` impliquait souvent les seules versions mineures ; depuis 5.6, les nouvelles installations partent sur les mises à jour automatiques majeures du cœur, sauf si l’utilisateur change le réglage.

Quand `WP_AUTO_UPDATE_CORE` n’est pas définie, la politique de branche retombe sur les options `auto_update_core_*` (§1.3). `DISALLOW_FILE_MODS` bloque les changements de fichiers, sauf si le filtre `file_mod_allowed` passe outre. `DISABLE_WP_CRON` arrête le lancement du cron au chargement des pages ; les vérifications planifiées ont besoin de déclencheurs externes sur `wp-cron.php` (§1.5).

`WP_HTTP_BLOCK_EXTERNAL` et `WP_ACCESSIBLE_HOSTS` filtrent le HTTP sortant. Bloquer `api.wordpress.org` ou `downloads.wordpress.org` casse la découverte, sauf si vous les autorisez explicitement. Une sortie uniquement par proxy HTTP d’entreprise exige les constantes `WP_PROXY_*` (§5.12).

#### Déroulement

1. PHP lit les constantes de `wp-config.php` avant l’exécution.
2. Les moteurs de mise à jour les consultent dans `is_disabled()`, `should_update_to_version()` et les appels du client HTTP.
3. Une mauvaise configuration se manifeste par des mises à jour manquantes ou des exécutions d’arrière-plan en échec.

#### Référence

| Nom | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `AUTOMATIC_UPDATER_DISABLED` | bool | faux si non définie | Désactive le programme de mise à jour automatique et ses e-mails de notification |
| `WP_AUTO_UPDATE_CORE` | bool ou chaîne | voir le manuel | Remplace les options de branche quand elle est définie |
| `DISALLOW_FILE_MODS` | bool | faux si non définie | Interdit les modifications de fichiers sauf filtrage |
| `FS_METHOD` | chaîne | auto | Force une couche de système de fichiers précise |
| `DISABLE_WP_CRON` | bool | faux si non définie | Arrête le lancement du cron au chargement des pages |
| `WP_HTTP_BLOCK_EXTERNAL` | bool | faux | Bloque le HTTP sortant sauf liste d’autorisation |
| `WP_ACCESSIBLE_HOSTS` | chaîne | vide | Noms d’hôte autorisés quand les requêtes externes sont bloquées |

#### Ressources pour les développeurs

- [Référence de configuration de `wp-config.php`](https://developer.wordpress.org/apis/wp-config-php/) — référence complète des constantes.

---

### 5.11 Multisite : périmètre des mises à jour et propagation de la mise à niveau de la base de données

Multisite partage une base de code et des transients de site au niveau du réseau, tandis que les mises à niveau de base de données s’appliquent site par site. Après une mise à jour du cœur, les opérateurs doivent exécuter les flux de mise à niveau du réseau.

#### Fonctionnement

De nombreuses actions de l’interface de mise à jour sont réservées à l’administration du réseau ou redirigent depuis l’administration du site selon `update-core.php`. Les capacités dépendent de l’utilisateur et du contexte du site ; les règles de super administrateur s’appliquent aux opérations de réseau. Les transients de mise à jour sont des transients de site, si bien que le réseau partage un seul cache de mise à jour.

Le remplacement des fichiers du cœur s’applique à tout le réseau. `wp_upgrade()` exécute `upgrade_all()` pour les tables du site courant, puis `upgrade_network()` sur le site principal quand `is_multisite() && is_main_site()`. Sur multisite, il écrit aussi `db_version` et `db_last_updated` dans les métadonnées de site (`wp-admin/includes/upgrade.php:684-687`). Les sous-sites ne se mettent pas tous à niveau en un seul appel.

`wp-admin/admin.php` peut détecter une divergence de `db_version` et déclencher `do_mu_upgrade` (filtre actif par défaut) avec `upgrade.php?step=1` sur les grands réseaux, en utilisant un échantillonnage quand le nombre de blogs dépasse 50. Le `db_version` de chaque site est mis à jour quand l’`wp-admin/upgrade.php` de ce site s’exécute.

Dans l’administration du réseau, Mettre à niveau le réseau (`wp-admin/network/upgrade.php`) parcourt les sites par lots de cinq, bascule de blog et envoie des requêtes HTTP GET vers l’`admin_url( 'upgrade.php?step=upgrade_db' )` de chaque site. La commande WP-CLI `wp core update-db --network` exécute la mise à niveau de la base de données sur tous les sites de façon non interactive.

Après une mise à jour réseau des fichiers du cœur, chaque sous-site peut encore afficher une mise à niveau de base de données en attente jusqu’à ce que l’écran Mettre à niveau le réseau, la mise à niveau du site ou la commande CLI de réseau se termine.

#### Déroulement

1. Les fichiers du cœur sont mis à jour une fois (à l’échelle du réseau).
2. Le site principal peut exécuter des routines de mise à niveau partielles.
3. Les sous-sites se mettent à niveau à la demande par l’écran Mettre à niveau le réseau ou par la CLI.
4. Tant que le `db_version` de chaque site ne correspond pas, le schéma peut être en retard sur ce site.

#### Référence

| Mécanisme | Périmètre |
|---|---|
| Base de code | Arborescence unique à l’échelle du réseau |
| `wp_upgrade()` par requête | Tables du site courant plus routines de réseau sur le site principal |
| Écran Mettre à niveau le réseau | Mise à niveau HTTP par site, par lots |
| `wp core update-db --network` | Mise à niveau CLI pour tous les sites |

#### Ressources pour les développeurs

- [Commande CLI `wp core update-db`](https://developer.wordpress.org/cli/commands/core/update-db/) — option `--network`.
- [Référence de `upgrade_network()`](https://developer.wordpress.org/reference/functions/upgrade_network/) — routines de mise à niveau du réseau.

---

### 5.12 La configuration d’un proxy HTTP d’entreprise

Les hôtes qui sortent uniquement par un proxy HTTP ou HTTPS doivent définir les identifiants et les points de terminaison du proxy dans `wp-config.php`. Sans cela, les requêtes de découverte vers `api.wordpress.org` et les téléchargements de paquets échouent, si bien qu’aucune offre n’apparaît et que les paquets ne peuvent pas être téléchargés.

#### Fonctionnement

WordPress lit `WP_PROXY_HOST` et `WP_PROXY_PORT` pour router les requêtes HTTP sortantes par le proxy d’entreprise. `WP_PROXY_USERNAME` et `WP_PROXY_PASSWORD` fournissent facultativement un accès authentifié au proxy. `WP_PROXY_BYPASS_HOSTS` liste les hôtes qui doivent éviter le proxy (séparés par des virgules ; les jokers tels que `*.wordpress.org` sont pris en charge selon `class-wp-http-proxy.php`).

Ces constantes sont consommées par l’API HTTP lors de la construction des requêtes (voir `wp-includes/class-wp-http-proxy.php`). L’interception TLS, les faisceaux d’autorités de certification personnalisés ou les listes d’autorisation au niveau du serveur peuvent encore être nécessaires au-delà des constantes WordPress. La découverte (`wp_version_check()`, `wp_update_plugins()`, `wp_update_themes()`) et les téléchargements de paquets dépendent tous de la même pile HTTP ; un proxy mal configuré bloque les deux.

#### Déroulement

1. PHP charge les constantes de `wp-config.php` avant les appels HTTP.
2. `WP_HTTP` utilise les réglages du proxy pour chaque requête sortante.
3. Si le proxy refuse la connexion ou renvoie 407 sans identifiants valides, les transients restent périmés ou les téléchargements échouent.
4. Après correction des réglages du proxy, un rafraîchissement forcé de la découverte (§1.6) peut être nécessaire pour remplir les offres.

#### Référence

| Nom | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `WP_PROXY_HOST` | chaîne | — | Nom d’hôte ou IP du proxy |
| `WP_PROXY_PORT` | chaîne | — | Port du proxy |
| `WP_PROXY_USERNAME` | chaîne | — | Utilisateur de proxy facultatif |
| `WP_PROXY_PASSWORD` | chaîne | — | Mot de passe de proxy facultatif |
| `WP_PROXY_BYPASS_HOSTS` | chaîne | — | Hôtes séparés par des virgules qui évitent le proxy |

#### Ressources pour les développeurs

- [`WP_PROXY_HOST` et constantes associées](https://developer.wordpress.org/apis/wp-config-php/#wp_proxy) — configuration du proxy dans `wp-config.php`.

---

### 5.13 WP-CLI : les rétrogradations explicites du cœur

WP-CLI peut installer une version précise du cœur WordPress, y compris une version antérieure à celle présente sur disque, lorsqu’il est invoqué avec `--version` et `--force`. Cela contourne la sémantique normale « mise à niveau uniquement » de l’interface d’administration et comporte des risques pour la base de données et la compatibilité.

#### Fonctionnement

Cette section décrit le comportement de WP-CLI, pas une garantie du cœur. WP-CLI n’est pas fourni avec WordPress ; la version installée ici est WP-CLI 2.12.0. Vérifiez les options avec `wp help core update` sur votre version de WP-CLI.

La commande `wp core update` télécharge et installe le ZIP demandé depuis WordPress.org (sous réserve des signatures et des contrôles du système de fichiers dans le contexte CLI). L’option `--force` est obligatoire quand la version cible est inférieure à la version installée, afin que l’opérateur accepte explicitement une rétrogradation.

Rétrograder les fichiers ne rétablit pas automatiquement le schéma de la base de données. `db_version` dans la table des options peut rester supérieur à ce que le code de `wp-includes/version.php` attend, ou l’inverse si l’opérateur exécute les étapes dans le désordre. Les opérateurs doivent exécuter `wp core update-db` après les changements de fichiers lorsque c’est approprié, et comprendre qu’une rétrogradation entre versions majeures peut laisser un schéma ou des données incompatibles. Les sauvegardes automatiques et un environnement de préproduction sont fortement conseillés. La compatibilité des extensions et des thèmes avec l’ancien cœur n’est pas validée par la commande.

#### Déroulement

1. L’opérateur exécute `wp core update --version=X.Y.Z --force`.
2. WP-CLI récupère et extrait les fichiers du cœur.
3. Le remplacement des fichiers se termine.
4. La version de la base de données peut encore refléter la mise à niveau précédente jusqu’à l’exécution de `wp core update-db` ou jusqu’à ce que `wp-admin` charge `wp_upgrade()` pour le chemin de code courant.
5. Une divergence entre les fichiers et le schéma peut provoquer des erreurs fatales ou une corruption discrète des données jusqu’à la réconciliation.

#### Référence

| Sujet | Risque de rétrogradation |
|---|---|
| Version des fichiers contre `db_version` | Schéma plus récent que le code, ou code plus récent que le schéma |
| Option `--force` | Obligatoire pour revenir à une version antérieure ; implique une acceptation explicite |
| Retour en arrière | Pas une annulation atomique garantie ; restaurez depuis une sauvegarde si nécessaire |

#### Ressources pour les développeurs

- [Commande CLI `wp core update`](https://developer.wordpress.org/cli/commands/core/update/) — options, dont `--version` et `--force`.
- [Commande CLI `wp core update-db`](https://developer.wordpress.org/cli/commands/core/update-db/) — mise à niveau du schéma après un changement des fichiers du cœur.

---

## Partie 6 — Référence des crochets et des filtres

### 6.1 Vue d’ensemble de la référence des crochets

Les crochets permettent aux extensions de modifier les charges utiles de découverte, le comportement de téléchargement, les étapes d’installation, les décisions automatiques et les notifications. Cette partie les regroupe par phase. Chaque tableau donne le type de crochet, les paramètres et la ligne du cœur installé qui le déclenche.

#### Fonctionnement

La découverte utilise `pre_set_site_transient_*` et les filtres associés. Le téléchargement et l’installation utilisent `upgrader_pre_download` et les filtres d’`install_package()`. Les mises à jour automatiques utilisent `auto_update_{$type}`, `pre_auto_update` et `automatic_updates_complete`. Les crochets d’e-mail et de système de fichiers affinent le comportement. Consultez les §6.2 à §6.8 pour les tableaux.

#### Déroulement

1. WordPress atteint un point de crochet.
2. Les fonctions de rappel enregistrées s’exécutent dans l’ordre de priorité.
3. Les filtres renvoient des valeurs ; les actions produisent des effets de bord.
4. Les valeurs renvoyées peuvent interrompre des étapes avec une `WP_Error`.

#### Référence

| Catégorie | Section |
|---|---|
| Découverte | §6.2 |
| Téléchargement et installation | §6.3 |
| Formes de `$hook_extra` | §6.4 |
| Programme de mise à jour automatique | §6.5 |
| Politique du cœur | §6.6 |
| Notifications | §6.7 |
| Cache et système de fichiers | §6.8 |

#### Ressources pour les développeurs

- [Présentation des crochets : actions et filtres](https://developer.wordpress.org/plugins/hooks/) — introduction au système de crochets.

---

### 6.2 Les crochets de découverte

Ces crochets modifient ou observent les charges utiles des transients avant leur enregistrement.

#### Fonctionnement

`pre_set_site_transient_update_plugins`, `pre_set_site_transient_update_themes` et `pre_set_site_transient_update_core` permettent à votre code de changer la valeur sur le point d’être enregistrée. Les filtres associés `site_transient_update_*` s’appliquent quand WordPress lit les transients. Les hôtes tiers utilisent `update_plugins_{$hostname}` et `update_themes_{$hostname}` (§1.8).

#### Déroulement

1. Une fonction de vérification construit une charge utile.
2. Les filtres `pre_set_*` s’exécutent.
3. WordPress enregistre le transient.
4. Les lecteurs appliquent les filtres `site_transient_*` au chargement du transient.

#### Référence

| Crochet | Type | Paramètres | Remarques | Source |
|---|---|---|---|---|
| `pre_set_site_transient_update_plugins` | filtre | `mixed $value, string $transient` | Modifie le transient de mise à jour des extensions avant l’enregistrement | `wp-includes/option.php:2654` |
| `pre_set_site_transient_update_themes` | filtre | `mixed $value, string $transient` | Modifie le transient de mise à jour des thèmes avant l’enregistrement | `wp-includes/option.php:2654` |
| `pre_set_site_transient_update_core` | filtre | `mixed $value, string $transient` | Modifie le transient de mise à jour du cœur avant l’enregistrement | `wp-includes/option.php:2654` |
| `site_transient_update_plugins` | filtre | `mixed $value, string $transient` | Filtre la valeur lue | `wp-includes/option.php:2622` |
| `site_transient_update_themes` | filtre | `mixed $value, string $transient` | Filtre la valeur lue | `wp-includes/option.php:2622` |
| `site_transient_update_core` | filtre | `mixed $value, string $transient` | Filtre la valeur lue | `wp-includes/option.php:2622` |
| `update_plugins_{$hostname}` | filtre | `false\|array $update, array $plugin_data, string $plugin_file, string[] $locales` | Métadonnées d’extensions tierces | `wp-includes/update.php:555` |
| `update_themes_{$hostname}` | filtre | `false\|array $update, array $theme_data, string $theme_stylesheet, string[] $locales` | Métadonnées de thèmes tiers | `wp-includes/update.php:833` |
| `core_version_check_locale` | filtre | `string $locale` | Locale de la vérification de version du cœur | `wp-includes/update.php:73` |
| `core_version_check_query_args` | filtre | `array $query` | Arguments de requête envoyés au point de terminaison du cœur | `wp-includes/update.php:209` |
| `plugins_update_check_locales` | filtre | `string[] $locales` | Locales demandées pour les traductions des extensions | `wp-includes/update.php:439` |
| `themes_update_check_locales` | filtre | `string[] $locales` | Locales demandées pour les traductions des thèmes | `wp-includes/update.php:726` |
| `wp_get_update_data` | filtre | `array $update_data, array $titles` | Compteurs et titres des mises à jour | `wp-includes/update.php:1021` |

#### Ressources pour les développeurs

- [Référence du filtre `pre_set_site_transient_{$transient}`](https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/) — famille de crochets dynamiques.

---

### 6.3 Les crochets de téléchargement et d’installation (`upgrader_pre_download` et les crochets d’`install_package()`)

`upgrader_pre_download` s’exécute avant le téléchargement HTTP. Quatre filtres s’exécutent dans `install_package()` autour des opérations de fichiers.

#### Fonctionnement

`upgrader_pre_download` se déclenche dans `WP_Upgrader::download_package()` avant `download_url()`. Renvoyez une chaîne de chemin de système de fichiers local pour remplacer entièrement le téléchargement distant, faux pour continuer normalement, ou une `WP_Error` pour interrompre. Si le filtre renvoie une `WP_Error`, les crochets d’`install_package()` ne s’exécutent pas.

Dans `install_package()`, `upgrader_pre_install` s’exécute avant les opérations de fichiers ; `upgrader_source_selection` ajuste le chemin décompressé ; `upgrader_clear_destination` conditionne le vidage ; `upgrader_post_install` s’exécute une fois les fichiers en place (la réactivation de l’extension a lieu dans le chemin du moteur de mise à niveau des extensions). Renvoyer une `WP_Error` depuis un filtre applicable interrompt l’étape.

#### Déroulement

1. L’étape de téléchargement évalue `upgrader_pre_download`.
2. La décompression s’exécute.
3. `upgrader_pre_install` s’exécute.
4. `upgrader_source_selection` ajuste le chemin source.
5. `upgrader_clear_destination` conditionne le vidage de la destination.
6. `upgrader_post_install` s’exécute.
7. L’action `upgrader_process_complete` se déclenche après le traitement (voir §6.4 pour la forme de la charge utile).

#### Référence

| Crochet | Type | Paramètres | Remarques | Source |
|---|---|---|---|---|
| `upgrader_pre_download` | filtre | `false\|string\|WP_Error $reply, string $package, WP_Upgrader $upgrader, array $hook_extra` | Avant `download_url()` ; un chemin local évite le téléchargement ; une `WP_Error` interrompt | `wp-admin/includes/class-wp-upgrader.php:322` |
| `upgrader_package_options` | filtre | `array $options` | Avant le travail sur le système de fichiers dans `run()` | `wp-admin/includes/class-wp-upgrader.php:849` |
| `upgrader_pre_install` | filtre | `bool\|WP_Error $response, array $hook_extra` | Avant les opérations de fichiers dans `install_package()` | `wp-admin/includes/class-wp-upgrader.php:587` |
| `upgrader_source_selection` | filtre | `string\|WP_Error $source, string $remote_source, WP_Upgrader $upgrader, array $hook_extra` | Après la décompression, avant le déplacement | `wp-admin/includes/class-wp-upgrader.php:632` |
| `upgrader_clear_destination` | filtre | `true\|WP_Error $removed, string $local_destination, string $remote_destination, array $hook_extra` | Avant le vidage de la destination | `wp-admin/includes/class-wp-upgrader.php:694` |
| `upgrader_post_install` | filtre | `bool $response, array $hook_extra, array $result` | Après la mise en place des fichiers | `wp-admin/includes/class-wp-upgrader.php:761` |
| `upgrader_install_package_result` | filtre | `array\|WP_Error $result, array $hook_extra` | Après `install_package()` | `wp-admin/includes/class-wp-upgrader.php:948` |
| `upgrader_process_complete` | action | `WP_Upgrader $upgrader, array $hook_extra` | Après le traitement | `wp-admin/includes/class-wp-upgrader.php:1019` |
| `upgrader_overwrote_package` | action | `string $package, array $new_plugin_data, string $type` | Après l’écrasement d’un paquet d’extension ou de thème | `wp-admin/includes/class-plugin-upgrader.php:169` ; `wp-admin/includes/class-theme-upgrader.php:274` |
| `update_feedback` | filtre | `string $feedback` | Texte de progression de la mise à niveau du cœur | `wp-admin/includes/class-core-upgrader.php:137` |
| `install_plugin_overwrite_comparison` | filtre | `string $table, array $current_plugin_data, array $new_plugin_data` | Table de comparaison de l’écrasement | `wp-admin/includes/class-plugin-installer-skin.php:260` |
| `install_plugin_overwrite_actions` | filtre | `array $install_actions, object $api, array $new_plugin_data` | Liens d’action de l’écrasement | `wp-admin/includes/class-plugin-installer-skin.php:343` |
| `install_theme_overwrite_comparison` | filtre | `string $table, array $current_theme_data, array $new_theme_data` | Table de comparaison de l’écrasement | `wp-admin/includes/class-theme-installer-skin.php:305` |
| `install_theme_overwrite_actions` | filtre | `array $install_actions, object $api, array $new_theme_data` | Liens d’action de l’écrasement | `wp-admin/includes/class-theme-installer-skin.php:388` |

#### Ressources pour les développeurs

- [Référence du filtre `upgrader_pre_download`](https://developer.wordpress.org/reference/hooks/upgrader_pre_download/) — court-circuit du téléchargement.
- [Référence de l’action `upgrader_process_complete`](https://developer.wordpress.org/reference/hooks/upgrader_process_complete/) — se déclenche après le traitement d’un paquet.

---

### 6.4 Référence de la structure de `$hook_extra`

`$hook_extra` transporte le contexte des crochets du moteur de mise à niveau. Sa forme diffère entre les filtres d’`install_package()` et `upgrader_process_complete`.

#### Fonctionnement

Le tableau n’est pas identique sur chaque crochet. Vérifiez la structure sur votre version de WordPress.

`upgrader_pre_download` reçoit le même quatrième paramètre `$hook_extra` que dans `download_package()`. Pour les filtres d’`install_package()` (`upgrader_pre_install`, `upgrader_source_selection`, `upgrader_clear_destination`, `upgrader_post_install`), les clés incluent `action` (`install` ou `update`), `type` (`plugin`, `theme`, `core` ou `translation`), le nom de base `plugin` ou le slug `theme` quand cela s’applique, et `bulk` défini à vrai dans `bulk_upgrade()`.

L’action `upgrader_process_complete` reçoit `$hook_extra` avec `action`, `type` et, pour les exécutions groupées d’extensions ou de thèmes, les clés plurielles `plugins` ou `themes` sous forme de tableaux listant les noms de base ou les slugs traités, plus `bulk` quand cela s’applique. Les clés singulières `plugin` et `theme` concernent l’élément courant dans `install_package()` ; les clés plurielles concernent le contexte du lot sur `upgrader_process_complete`.

Sur l’instance `$upgrader` transmise à `upgrader_process_complete`, `$upgrader->result` contient la valeur de retour du dernier appel à `install_package()` (`true` ou `WP_Error`).

#### Déroulement

1. L’appelant construit `$hook_extra`.
2. Chaque crochet reçoit le tableau.
3. Les consommateurs lisent les clés adaptées au contexte d’un seul élément ou d’un lot.

#### Référence

**Dans les filtres d’`install_package()` :**

| Clé | Présent pour | Valeur | Source |
|---|---|---|---|
| `action` | Tous | `install` ou `update` | `wp-admin/includes/class-wp-upgrader.php:541-550` |
| `type` | Tous | `plugin`, `theme`, `core` ou `translation` | `wp-admin/includes/class-plugin-upgrader.php:230` ; `class-theme-upgrader.php:334` ; `class-core-upgrader.php:221` ; `class-language-pack-upgrader.php:250` |
| `plugin` | Mises à jour et installations d’extensions | Nom de base de l’extension | `wp-admin/includes/class-plugin-upgrader.php:230` |
| `theme` | Mises à jour et installations de thèmes | Slug du thème | `wp-admin/includes/class-theme-upgrader.php:334` |
| `bulk` | Opérations groupées | `true` dans `bulk_upgrade()` | `wp-admin/includes/class-plugin-upgrader.php:385` |
| `temp_backup` | Mises à jour d’extensions ou de thèmes | Tableau avec `slug`, `src`, `dir` | `wp-admin/includes/class-wp-upgrader.php:638` ; `class-plugin-upgrader.php:230` ; `class-theme-upgrader.php:334` |

**Action `upgrader_process_complete` :**

| Clé | Présent pour | Valeur | Source |
|---|---|---|---|
| `action` | Tous | `update` ou `install` | `wp-admin/includes/class-wp-upgrader.php:1019` |
| `type` | Tous | `plugin`, `theme`, `core` ou `translation` | `wp-admin/includes/class-plugin-upgrader.php:412-421` ; `class-theme-upgrader.php:510-519` ; `class-language-pack-upgrader.php:280-289` |
| `plugins` | Mises à jour groupées d’extensions | Tableau de noms de base | `wp-admin/includes/class-plugin-upgrader.php:412-421` |
| `themes` | Mises à jour groupées de thèmes | Tableau de slugs | `wp-admin/includes/class-theme-upgrader.php:510-519` |
| `translations` | Mises à jour groupées de traductions | Tableau de résultats de traduction | `wp-admin/includes/class-language-pack-upgrader.php:280-289` |
| `bulk` | Opérations groupées | `true` dans `bulk_upgrade()` | `wp-admin/includes/class-plugin-upgrader.php:412-421` |

#### Ressources pour les développeurs

- [Référence de l’action `upgrader_process_complete`](https://developer.wordpress.org/reference/hooks/upgrader_process_complete/) — signature et paramètres de l’action.

---

### 6.5 Les crochets du programme de mise à jour automatique

Ces crochets entourent les décisions et la fin du programme de mise à jour automatique.

#### Fonctionnement

`pre_auto_update` est une action qui s’exécute une fois par élément dans `WP_Automatic_Updater::update()`, et non une fois par lot. Les arguments sont la chaîne de type (`core`, `plugin`, `theme` ou `translation`), l’objet de l’offre et le chemin de système de fichiers utilisé pour les contrôles de capacités. Elle ne peut pas annuler une mise à jour ; utilisez les filtres `auto_update_{$type}` pour écarter des éléments.

`automatic_updates_complete` transmet `$update_results` (§4.7).

Pour les offres du cœur, le filtre par offre est `auto_update_core` (le crochet `auto_update_{$type}` avec le type `core`). Il s’exécute dans `should_update()` après la décision initiale de `Core_Upgrader::should_update_to_version()` (politique de branche, `WP_AUTO_UPDATE_CORE`, options de site `auto_update_core_*` et `allow_*_auto_core_updates`).

Le cœur implémente la politique de branche dans `Core_Upgrader::should_update_to_version()` ; il ne repose pas sur un filtre nommé `wp_auto_update_core` (cette chaîne n’est pas un crochet du cœur). L’option de site `auto_update_core_major` (interface des versions majeures de WordPress 5.6 et suivantes) est une préférence stockée. Ne la confondez pas avec le filtre `auto_update_core` ni avec la constante `WP_AUTO_UPDATE_CORE` (§5.5, §5.10, §3.7).

#### Déroulement

1. Avant chaque élément automatique, `pre_auto_update` se déclenche.
2. `should_update()` évalue `auto_update_{$type}`.
3. La mise à jour s’exécute ou est ignorée.
4. Après le lot, `automatic_updates_complete` se déclenche si des résultats existent.

#### Référence

| Crochet | Type | Paramètres | Remarques | Source |
|---|---|---|---|---|
| `pre_auto_update` | action | `string $type, object $item, string $context` | Par élément ; aucune valeur de retour | `wp-admin/includes/class-wp-automatic-updater.php:401` |
| `auto_update_{$type}` | filtre | `bool\|null $update, object $item` | Autorisation ou refus par offre ; inclut `auto_update_core` pour le cœur | `wp-admin/includes/class-wp-automatic-updater.php:268` |
| `automatic_updates_is_vcs_checkout` | filtre | `bool $checkout, string $context` | Passe outre la détection VCS | `wp-admin/includes/class-wp-automatic-updater.php:178` |
| `plugins_auto_update_enabled` | filtre | `bool $enabled` | Activation de l’interface par type | `wp-admin/includes/update.php:1059` |
| `themes_auto_update_enabled` | filtre | `bool $enabled` | Activation de l’interface par type | `wp-admin/includes/update.php:1068` |
| `automatic_updates_complete` | action | `array $update_results` | Charge utile des résultats (§4.7) | `wp-admin/includes/class-wp-automatic-updater.php:783` |

#### Ressources pour les développeurs

- [Référence de l’action `pre_auto_update`](https://developer.wordpress.org/reference/hooks/pre_auto_update/) — action par élément.

---

### 6.6 Les crochets de politique de mise à jour automatique du cœur

La politique de branche utilise les filtres `allow_*`, distincts du filtre `auto_update_core` par offre, de l’option de site `auto_update_core_major` (interface des versions majeures) et des autres options `auto_update_core_*`.

#### Fonctionnement

Dans `Core_Upgrader::should_update_to_version()`, `allow_dev_auto_core_updates`, `allow_minor_auto_core_updates` et `allow_major_auto_core_updates` reçoivent chacun le booléen dérivé de `WP_AUTO_UPDATE_CORE` ou des options `auto_update_core_*` avant de renvoyer. Ils s’exécutent avant `auto_update_core` dans `should_update()` pour les offres du cœur. Voir §3.7 pour l’ordre complet.

#### Déroulement

1. Le cœur détermine la branche (développement, mineure ou majeure).
2. Le filtre `allow_*` correspondant s’exécute.
3. Le résultat alimente `should_update()` avec `auto_update_core`.

#### Référence

| Crochet | Type | Paramètres | Remarques | Source |
|---|---|---|---|---|
| `allow_dev_auto_core_updates` | filtre | `bool $upgrade_dev` | Versions de développement uniquement | `wp-admin/includes/class-core-upgrader.php:357` |
| `allow_minor_auto_core_updates` | filtre | `bool $upgrade_minor` | Même branche x.y | `wp-admin/includes/class-core-upgrader.php:373` |
| `allow_major_auto_core_updates` | filtre | `bool $upgrade_major` | Sauts de branche majeure | `wp-admin/includes/class-core-upgrader.php:386` |
| `after_core_auto_updates_settings` | action | `array $auto_update_settings` | Après les réglages des versions majeures du cœur ; reçoit `dev`, `minor`, `major` | `wp-admin/update-core.php:454` |
| `core_upgrade_preamble` | action | aucun | Avant la liste des mises à niveau du cœur | `wp-admin/update-core.php:1139` |
| `update-core-custom-{$action}` | action | aucun | Action de mise à jour personnalisée du cœur | `wp-admin/update-core.php:1331` |

#### Ressources pour les développeurs

- [Référence du filtre `allow_major_auto_core_updates`](https://developer.wordpress.org/reference/hooks/allow_major_auto_core_updates/) — filtre de branche majeure.

---

### 6.7 Les crochets de notification et d’e-mail

Les crochets d’e-mail ajustent si les messages sont envoyés et ce qu’ils contiennent. Les filtres d’interface personnalisent la présentation des réglages de mise à jour automatique.

#### Fonctionnement

Crochets d’e-mail du cœur : `send_core_update_notification_email`, `auto_core_update_send_email` et `auto_core_update_email`. Crochets d’e-mail des lots d’extensions et de thèmes : `auto_plugin_update_send_email`, `auto_theme_update_send_email` et `auto_plugin_theme_update_email`. Crochets de débogage : `automatic_updates_send_debug_email` et `automatic_updates_debug_email`. Crochets d’interface : `plugin_auto_update_setting_html`, `theme_auto_update_setting_template`, `theme_auto_update_setting_html` et `update_right_now_text` (voir §5.4 et §5.5). Actions des messages de mise à jour : `in_plugin_update_message-{$file}` et `in_theme_update_message-{$theme_key}`. L’action `after_core_auto_updates_settings` appartient au §6.6.

#### Déroulement

1. Le programme de mise à jour décide d’envoyer un e-mail.
2. Les filtres d’autorisation s’exécutent.
3. Les filtres de contenu ajustent les tableaux avant l’envoi par `wp_mail()`.

#### Référence

| Crochet | Type | Paramètres | Remarques | Source |
|---|---|---|---|---|
| `send_core_update_notification_email` | filtre | `bool $notify, object $item` | Notification de mise à jour manuelle du cœur | `wp-admin/includes/class-wp-automatic-updater.php:345` |
| `auto_core_update_send_email` | filtre | `bool $send, string $type, object $core_update, mixed $result` | Après une mise à jour du cœur en arrière-plan | `wp-admin/includes/class-wp-automatic-updater.php:935` |
| `auto_core_update_email` | filtre | `array $email, string $type, object $core_update, mixed $result` | Modifie les champs de l’e-mail du cœur | `wp-admin/includes/class-wp-automatic-updater.php:1147` |
| `auto_plugin_update_send_email` | filtre | `bool $enabled, array $update_results` | Autorisation d’envoi du lot d’extensions | `wp-admin/includes/class-wp-automatic-updater.php:1177` |
| `auto_theme_update_send_email` | filtre | `bool $enabled, array $update_results` | Autorisation d’envoi du lot de thèmes | `wp-admin/includes/class-wp-automatic-updater.php:1200` |
| `auto_plugin_theme_update_email` | filtre | `array $email, string $type, array $successful_updates, array $failed_updates` | Modifie l’e-mail des lots d’extensions et de thèmes | `wp-admin/includes/class-wp-automatic-updater.php:1541` |
| `automatic_updates_send_debug_email` | filtre | `bool $development_version` | Activation de l’e-mail de débogage | `wp-admin/includes/class-wp-automatic-updater.php:766` |
| `automatic_updates_debug_email` | filtre | `array $email, array $failures, array $update_results` | Charge utile de l’e-mail de débogage | `wp-admin/includes/class-wp-automatic-updater.php:1741` |
| `plugin_auto_update_setting_html` | filtre | `string $html, string $plugin_file, array $plugin_data` | HTML du contrôle sur Extensions installées | `wp-admin/includes/class-wp-plugins-list-table.php:1457` |
| `theme_auto_update_setting_template` | filtre | `string $template` | Modèle de la fenêtre de détails du thème | `wp-admin/themes.php:829` |
| `theme_auto_update_setting_html` | filtre | `string $html, string $stylesheet, WP_Theme $theme` | HTML du contrôle dans les thèmes du réseau | `wp-admin/includes/class-wp-ms-themes-list-table.php:887` |
| `in_plugin_update_message-{$file}` | action | `array $plugin_data, object $response` | Message de mise à jour par extension | `wp-admin/includes/update.php:614` |
| `in_theme_update_message-{$theme_key}` | action | `WP_Theme $theme, object $response` | Message de mise à jour par thème | `wp-admin/includes/update.php:837` |
| `update_right_now_text` | filtre | `string $content` | Texte de version du widget D’un coup d’œil | `wp-admin/includes/update.php:377` |

#### Ressources pour les développeurs

- [Référence du filtre `auto_core_update_email`](https://developer.wordpress.org/reference/hooks/auto_core_update_email/) — filtre de l’e-mail du cœur.

---

### 6.8 Les crochets de cache et de système de fichiers

Les garde-fous du système de fichiers et des modifications interagissent avec `WP_Filesystem` et les contrôles d’aptitude à la mise à jour.

#### Fonctionnement

`file_mod_allowed` filtre l’autorisation de modifier des fichiers pour un contexte donné. `automatic_updater_disabled` désactive le programme de mise à jour automatique. Les constantes `DISALLOW_FILE_MODS` et `AUTOMATIC_UPDATER_DISABLED` interviennent avant ou à côté de ces crochets, selon le point d’appel. Les filtres `filesystem_method` et `request_filesystem_credentials` ajustent la façon dont les identifiants sont obtenus pour les flux interactifs.

#### Déroulement

1. Le moteur de mise à niveau demande si les modifications sont autorisées.
2. Les filtres et les constantes se combinent pour produire la réponse.
3. L’API du système de fichiers choisit le transport.
4. Les chemins d’arrière-plan exigent un succès non interactif.

#### Référence

| Crochet | Type | Paramètres | Remarques | Source |
|---|---|---|---|---|
| `file_mod_allowed` | filtre | `bool $file_mod_allowed, string $context` | Conditionne les changements de fichiers selon le contexte | `wp-includes/load.php:1838` |
| `automatic_updater_disabled` | filtre | `bool $disabled` | Désactive le programme de mise à jour automatique | `wp-admin/includes/class-wp-automatic-updater.php:58` |
| `filesystem_method` | filtre | `string $method, array $args, string $context, bool $allow_relaxed_file_ownership` | Choisit la méthode d’accès au système de fichiers | `wp-admin/includes/file.php:2342` |
| `request_filesystem_credentials` | filtre | `mixed $req_cred, string $form_post, string\|false $type, bool\|WP_Error $error, string $context, array\|null $extra_fields, bool $allow_relaxed_file_ownership` | Formulaire d’identifiants et valeurs de retour | `wp-admin/includes/file.php:2400` |
| `filesystem_method_file` | filtre | `string $abstraction_file, string $method` | Fichier de la classe de système de fichiers | `wp-admin/includes/file.php:2203` |
| `fs_ftp_connection_types` | filtre | `array $types, array $credentials, string $type, bool\|WP_Error $error, string $context` | Types de connexion FTP et SSH | `wp-admin/includes/file.php:2554` |
| `wp_opcache_invalidate_file` | filtre | `bool $should_invalidate, string $filepath` | Invalidation d’OPcache | `wp-admin/includes/file.php:2773` |
| `unzip_file_use_ziparchive` | filtre | `bool $use_ziparchive` | Utiliser ZipArchive pour l’extraction | `wp-admin/includes/file.php:1578` |
| `pre_unzip_file` | filtre | `null\|bool $pre, string $file, string $to, bool $needed_dirs, int $required_space` | Court-circuite l’extraction | `wp-admin/includes/file.php:1797` |
| `unzip_file` | filtre | `bool $result, string $file, string $to, bool $needed_dirs, int $required_space` | Filtre le résultat de l’extraction | `wp-admin/includes/file.php:1853` |
| `wp_signature_hosts` | filtre | `array $signed_hostnames` | Hôtes dont les téléchargements sont vérifiés par signature | `wp-admin/includes/file.php:1287` |
| `wp_signature_url` | filtre | `string $signature_url, string $url` | URL de signature d’un téléchargement | `wp-admin/includes/file.php:1316` |
| `wp_signature_softfail` | filtre | `bool $signature_softfail, string $url` | Autorise un échec de signature non bloquant | `wp-admin/includes/file.php:1348` |
| `wp_trusted_keys` | filtre | `array $trusted_keys` | Clés publiques de confiance | `wp-admin/includes/file.php:1562` |
| `https_local_ssl_verify` | filtre | `bool $sslverify, string $url` | Vérification SSL des requêtes de boucle locale | `wp-admin/includes/class-wp-automatic-updater.php:1806` ; `wp-admin/includes/file.php:586` |
| `enable_maintenance_mode` | filtre | `bool $enable, int\|false $upgrading` | Met le mode maintenance en veto | `wp-includes/load.php:474` |

#### Ressources pour les développeurs

- [Référence du filtre `file_mod_allowed`](https://developer.wordpress.org/reference/hooks/file_mod_allowed/) — filtre l’autorisation de modification des fichiers.

---

## Partie 7 — Référence des fichiers et des classes

### 7.1 Les fichiers du cœur impliqués dans le système de mise à jour

Ces chemins sont relatifs à la racine de l’installation WordPress. La colonne Rôle résume les responsabilités.

#### Fonctionnement

La logique de mise à jour s’étend sur `wp-includes` et `wp-admin/includes`. La gestion du cron et des transients vit dans `wp-includes/update.php`. Les assistants réservés à l’administration et les fonctions de sommes de contrôle vivent sous `wp-admin/includes`. Les écrans d’interface vivent sous `wp-admin`.

Les mises à niveau interactives du cœur sont pilotées depuis `wp-admin/update-core.php` (l’écran et `do_core_upgrade()`), tandis que les routines de copie et de renommage de bas niveau vivent dans `wp-admin/includes/update-core.php` (requis pendant une mise à niveau du cœur, après la décompression du nouveau paquet).

#### Déroulement

1. L’ordre de chargement dépend du contexte : front end, administration, AJAX ou CLI.
2. Les inclusions de l’administration se chargent avant l’exécution des moteurs de mise à niveau.
3. Les opérations d’installation, de mise à jour et de suppression des extensions et des thèmes utilisent souvent les gestionnaires AJAX d’`ajax-actions.php`.
4. La mise à niveau et la réinstallation interactives du cœur passent par l’écran `update-core.php` et le fichier d’inclusions, pas par les points d’entrée AJAX.

#### Référence

| Chemin relatif | Rôle |
|---|---|
| `wp-includes/update.php` | Vérifications de version et de paquets, `wp_maybe_auto_update()`, crochets de cron, `wp_get_update_data()` |
| `wp-admin/includes/update.php` | `get_core_updates()`, `find_core_auto_update()`, assistants de masquage, `update_nag()`, `get_core_checksums()` |
| `wp-admin/includes/upgrade.php` | `wp_upgrade()`, `dbDelta()`, `upgrade_all()`, migration du schéma |
| `wp-admin/includes/admin-filters.php` | Attache `update_nag()` aux notifications de l’administration |
| `wp-admin/includes/class-wp-upgrader-skin.php` | API de base des habillages |
| `wp-admin/includes/class-automatic-upgrader-skin.php` | Capture des messages d’arrière-plan et AJAX |
| `wp-admin/includes/class-wp-ajax-upgrader-skin.php` | Erreurs et messages AJAX |
| `wp-admin/includes/class-bulk-upgrader-skin.php` | Interface de progression groupée |
| `wp-admin/includes/class-wp-upgrader.php` | Moteur de mise à niveau de base, `upgrader_process_complete` |
| `wp-admin/includes/class-wp-automatic-updater.php` | Décisions et exécution des mises à jour d’arrière-plan |
| `wp-admin/includes/class-plugin-upgrader.php` | Mise à niveau et installation d’extensions |
| `wp-admin/includes/class-theme-upgrader.php` | Mise à niveau et installation de thèmes |
| `wp-admin/includes/class-core-upgrader.php` | Mise à niveau du cœur ; `Core_Upgrader::check_files()` compare les fichiers installés à `get_core_checksums()` |
| `wp-admin/includes/update-core.php` | Routines de mise à niveau du cœur (notamment `update_core()`) ; requises après la décompression (distinctes de l’écran d’administration `update-core.php`) |
| `wp-admin/includes/class-language-pack-upgrader.php` | Paquets de traduction |
| `wp-admin/includes/file.php` | `WP_Filesystem`, `request_filesystem_credentials()`, `verify_file_signature()`, `wp_trusted_keys()`, intégration de `FS_METHOD` |
| `wp-admin/update-core.php` | Interface d’administration des mises à jour du cœur ; `do_core_upgrade()`, réinstallation |
| `wp-admin/includes/ajax-actions.php` | Gestionnaires AJAX pour l’installation, la mise à jour et la suppression des extensions, des thèmes et du cœur (comme `wp_ajax_update_plugin`) ; pas le chemin interactif de mise à niveau du cœur |
| `wp-includes/load.php` | `wp_is_file_mod_allowed()` |
| `wp-includes/class-wp-plugin-dependencies.php` | `WP_Plugin_Dependencies` (graphe de dépendances pour la liste des extensions, l’activation et les actions AJAX associées) |
| `wp-admin/includes/class-wp-site-health.php` | Diagnostics de `WP_Site_Health` |
| `wp-admin/includes/class-wp-site-health-auto-updates.php` | Tests de mise à jour en arrière-plan de `WP_Site_Health_Auto_Updates` |
| `wp-admin/includes/class-plugin-installer-skin.php` | Écran d’installation et d’écrasement de `Plugin_Installer_Skin` |
| `wp-admin/includes/class-theme-installer-skin.php` | Écran d’installation et d’écrasement de `Theme_Installer_Skin` |
| `wp-admin/includes/class-language-pack-upgrader-skin.php` | Écran de mise à jour des traductions de `Language_Pack_Upgrader_Skin` |
| `wp-admin/includes/class-bulk-plugin-upgrader-skin.php` | Progression groupée de `Bulk_Plugin_Upgrader_Skin` |
| `wp-admin/includes/class-bulk-theme-upgrader-skin.php` | Progression groupée de `Bulk_Theme_Upgrader_Skin` |
| `wp-admin/includes/class-file-upload-upgrader.php` | `File_Upload_Upgrader` pour les paquets téléversés |
| `wp-includes/class-wp-recovery-mode-email-service.php` | E-mail de récupération de `WP_Recovery_Mode_Email_Service` |
| `wp-includes/class-wp-recovery-mode.php` | `WP_Recovery_Mode` |

#### Ressources pour les développeurs

- [Référence du code](https://developer.wordpress.org/reference/) — recherchez les fonctions et les classes de chaque fichier.

---

### 7.2 Les classes clés

Les classes répartissent le téléchargement, l’installation, les décisions automatiques, les habillages et la récupération en responsabilités distinctes.

#### Fonctionnement

Les sous-classes de `WP_Upgrader` spécialisent le comportement par type de paquet. `WP_Automatic_Updater` enveloppe l’application planifiée. Les habillages héritent de `WP_Upgrader_Skin`. `WP_Site_Health_Auto_Updates` exécute les tests de mise à jour en arrière-plan derrière l’écran Santé du site. `WP_Recovery_Mode` gère la récupération après erreur fatale séparément des mises à jour, et `WP_Recovery_Mode_Email_Service` envoie l’e-mail de récupération.

#### Déroulement

1. Le point d’entrée choisit une classe par instanciation.
2. Les méthodes appellent les crochets parents.
3. Les résultats se propagent aux habillages ou aux couches d’e-mail.

#### Référence

| Classe | Fichier | Hérite de | Rôle |
|---|---|---|---|
| `WP_Upgrader` | `class-wp-upgrader.php` | — | Téléchargement et installation de base |
| `Core_Upgrader` | `class-core-upgrader.php` | `WP_Upgrader` | Remplacement des fichiers du cœur |
| `Plugin_Upgrader` | `class-plugin-upgrader.php` | `WP_Upgrader` | Mises à jour et installations d’extensions |
| `Theme_Upgrader` | `class-theme-upgrader.php` | `WP_Upgrader` | Mises à jour et installations de thèmes |
| `Language_Pack_Upgrader` | `class-language-pack-upgrader.php` | `WP_Upgrader` | Paquets de traduction |
| `WP_Automatic_Updater` | `class-wp-automatic-updater.php` | — | Décisions et exécution des mises à jour d’arrière-plan |
| `WP_Site_Health_Auto_Updates` | `class-wp-site-health-auto-updates.php` | — | Tests de mise à jour en arrière-plan derrière Santé du site |
| `WP_Upgrader_Skin` | `class-wp-upgrader-skin.php` | — | Habillage de base |
| `Plugin_Installer_Skin` | `class-plugin-installer-skin.php` | `WP_Upgrader_Skin` | Écran d’installation et d’écrasement d’extension |
| `Theme_Installer_Skin` | `class-theme-installer-skin.php` | `WP_Upgrader_Skin` | Écran d’installation et d’écrasement de thème |
| `Language_Pack_Upgrader_Skin` | `class-language-pack-upgrader-skin.php` | `WP_Upgrader_Skin` | Écran de mise à jour des traductions |
| `Bulk_Plugin_Upgrader_Skin` | `class-bulk-plugin-upgrader-skin.php` | `Bulk_Upgrader_Skin` | Progression groupée d’extensions |
| `Bulk_Theme_Upgrader_Skin` | `class-bulk-theme-upgrader-skin.php` | `Bulk_Upgrader_Skin` | Progression groupée de thèmes |
| `File_Upload_Upgrader` | `class-file-upload-upgrader.php` | — | Gestion des paquets téléversés |
| `WP_Recovery_Mode` | `class-wp-recovery-mode.php` | — | Récupération après erreur fatale |
| `WP_Recovery_Mode_Email_Service` | `class-wp-recovery-mode-email-service.php` | — | Composition et envoi de l’e-mail de récupération |

#### Ressources pour les développeurs

- [Référence de la classe `WP_Automatic_Updater`](https://developer.wordpress.org/reference/classes/wp_automatic_updater/) — classe du programme de mise à jour automatique.

---
