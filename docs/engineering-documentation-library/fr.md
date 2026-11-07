# WordPress — Bibliothèque de documentation d’ingénierie

[English](en.md) · [Index de la documentation](../README.md)

| Champ | Valeur |
|---|---|
| Dernière mise à jour | 2026-11-07 |
| Source | [`en.md`](en.md) |
| Statut | vérifié |

Traduit depuis la version anglaise vérifiée contre WordPress 7.1.4 le 2026-11-07.

> **Voir aussi :** `AGENTS.md` à la racine de l’extension — faits du projet, ordre de lint, protection i18n, fichiers figés et métadonnées des notes. Toujours appliqué dans les systèmes d’agents compatibles.

**Document unique :** les sections choisies (tableaux, directives pour les agents, pointeurs de projet) vivent ici. Le texte des pages récupérées derrière les liens **Ressources clés** n’est pas conservé dans ce dépôt : suivez les liens ou récupérez la page.

Ce fichier est la moitié choisie : les sections, les listes de ressources, les directives pour les agents et les pointeurs de projet. C’est la partie que le lecteur veut, et elle est assez petite pour être ouverte.

Pour les règles de rédaction de la maison — majuscules, ponctuation, mise en forme, liste de mots, liens — utilisez les références que porte la compétence `writing` dans `.agents/skills/writing/references/` : six fichiers de catégorie plus la liste de mots découpée par lettre sous `references/word-list/`. Ouvrez celui dont vous avez besoin ; ils se consultent à la demande plutôt que de se lire en entier.

Cette bibliothèque sert de référence de développement pour les extensions et les thèmes WordPress : sécurité, optimisation, accessibilité, API, tests et normes de documentation. Elle s’adresse aux développeurs humains comme aux agents autonomes, et elle ancre chaque décision d’architecture dans les normes officielles de WordPress, les protocoles du W3C et les bonnes pratiques.

---

## Sommaire

1. [Développement d’extensions WordPress](#développement-dextensions-wordpress)
2. [Développement de thèmes WordPress](#développement-de-thèmes-wordpress)
3. [Thèmes de blocs et édition complète du site (FSE)](#thèmes-de-blocs-et-édition-complète-du-site-fse)
4. [Crochets WordPress (actions et filtres)](#crochets-wordpress-actions-et-filtres)
5. [API REST de WordPress](#api-rest-de-wordpress)
6. [API HTTP](#api-http)
7. [API de base de données de WordPress](#api-de-base-de-données-de-wordpress)
8. [API des réglages de WordPress](#api-des-réglages-de-wordpress)
9. [Types de publication personnalisés et taxonomies](#types-de-publication-personnalisés-et-taxonomies)
10. [Utilisateurs, rôles et capacités](#utilisateurs-rôles-et-capacités)
11. [WP-Cron](#wp-cron)
12. [Internationalisation et localisation (i18n / l10n)](#internationalisation-et-localisation-i18n--l10n)
13. [API de confidentialité](#api-de-confidentialité)
14. [JavaScript, jQuery et Ajax dans WordPress](#javascript-jquery-et-ajax-dans-wordpress)
15. [Bonnes pratiques de sécurité WordPress](#bonnes-pratiques-de-sécurité-wordpress)
16. [Nettoyage et échappement des données](#nettoyage-et-échappement-des-données)
17. [Nonces et contrôles de capacité](#nonces-et-contrôles-de-capacité)
18. [Développement de blocs Gutenberg](#développement-de-blocs-gutenberg)
19. [Composants React de WordPress](#composants-react-de-wordpress)
20. [Accessibilité (WCAG 2.1+)](#accessibilité-wcag-21)
21. [Directives d’accessibilité de WordPress](#directives-daccessibilité-de-wordpress)
22. [HTML sémantique et ARIA](#html-sémantique-et-aria)
23. [Optimisation des performances](#optimisation-des-performances)
24. [Mise en cache WordPress](#mise-en-cache-wordpress)
25. [Core Web Vitals](#core-web-vitals)
26. [Normes de codage WordPress](#normes-de-codage-wordpress)
27. [Compatibilité PHP](#compatibilité-php)
28. [WP-CLI](#wp-cli)
29. [Mises à jour natives de WordPress (cœur)](#mises-à-jour-natives-de-wordpress-cœur)
30. [Tests WordPress](#tests-wordpress)
31. [PHPUnit pour WordPress](#phpunit-pour-wordpress)
32. [Tests Playwright / E2E](#tests-playwright--e2e)
33. [Plugin Check (conformité WordPress.org)](#plugin-check-conformité-wordpressorg)
34. [Directives pour les agents IA](#directives-pour-les-agents-ia)
35. [Normes de documentation WordPress](#normes-de-documentation-wordpress)
36. [Directives de documentation pour les agents](#directives-de-documentation-pour-les-agents)

---

## Développement d’extensions WordPress

**Quand consulter :** au démarrage d’un projet d’extension, pour comprendre la structure de base (fichier principal avec en-tête, répertoire, format des fichiers PHP) et les bonnes pratiques générales de développement.

### Ressources clés
- [Manuel des extensions](https://developer.wordpress.org/plugins/)
- [Notions de base des extensions](https://developer.wordpress.org/plugins/plugin-basics/)
- [Exigences d’en-tête](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/)
- [Bonnes pratiques](https://developer.wordpress.org/plugins/plugin-basics/best-practices/)
- [Crochets d’activation et de désactivation](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/)
- [Méthodes de désinstallation](https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/)
- [Détermination des répertoires de l’extension et du contenu](https://developer.wordpress.org/plugins/plugin-basics/determining-plugin-and-content-directories/)

### Schémas de mise en œuvre
| Exigence                  | Schéma de mise en œuvre                                                               | Justification                                                             |
|---------------------------|----------------------------------------------------------------------------------------|---------------------------------------------------------------------------|
| Barrière de sécurité      | `if ( ! defined( 'ABSPATH' ) ) { exit; }`                                              | Empêche la traversée de chemin et l’exécution directe du fichier.         |
| Nom du fichier principal  | `plugin-name/plugin-name.php`                                                          | Garantit la cohérence avec l’identification par répertoire.               |
| Préfixage                 | `unique_prefix_`                                                                       | Évite les collisions dans l’espace de noms global.                        |
| Localisation              | `load_plugin_textdomain()`                                                             | Facilite l’i18n via des fichiers .mo et .po standardisés.                 |
| Crochet d’activation      | `register_activation_hook()`                                                           | Exécute les tâches d’installation (création de tables, options par défaut) à l’activation. |
| Crochet de désactivation  | `register_deactivation_hook()`                                                         | Nettoie les tâches planifiées, vide les règles de réécriture.             |
| Désinstallation           | `uninstall.php`                                                                        | Garantit un état de base de données propre lors de la suppression totale. |
| Fonctions utilitaires de chemin      | `plugin_dir_path()`, `plugin_dir_url()`, `plugins_url()`                              | Résolution de chemin portable et sûre en multisite.                       |

**Directives pour les agents :**
- Ajoutez toujours une barrière de sécurité au début de chaque fichier PHP.
- Utilisez `uninstall.php` pour supprimer les données, pas les crochets de désactivation.
- Préfixez toutes les fonctions, classes et options pour éviter les collisions de noms.
- Ne codez jamais les chemins en dur ; utilisez `plugin_dir_path( __FILE__ )` et `plugin_dir_url( __FILE__ )`.

---

## Développement de thèmes WordPress

**Quand consulter :** lors de la création ou de la modification d’un thème WordPress (classique ou de blocs).

### Ressources clés
- [Manuel des thèmes](https://developer.wordpress.org/themes/)
- [Notions de base des thèmes](https://developer.wordpress.org/themes/classic-themes/basics/)
- [Fonctions du thème](https://developer.wordpress.org/themes/classic-themes/basics/theme-functions/)
- [Hiérarchie des modèles](https://developer.wordpress.org/themes/classic-themes/basics/template-hierarchy/)
- [Fichiers de modèle](https://developer.wordpress.org/themes/classic-themes/basics/template-files/)
- [Inclusion de CSS et JavaScript](https://developer.wordpress.org/themes/classic-themes/basics/including-css-javascript/)
- [Thèmes enfants](https://developer.wordpress.org/themes/advanced-topics/child-themes/)
- [Test du thème](https://developer.wordpress.org/themes/releasing-your-theme/testing/)
- [Exigences du répertoire des thèmes](https://developer.wordpress.org/themes/releasing-your-theme/required-theme-files/)

### Structure d’un thème classique
| Fichier / schéma            | Rôle                                                                 |
|----------------------------|----------------------------------------------------------------------|
| `style.css`                | En-tête de métadonnées du thème et styles de base.                   |
| `functions.php`            | Amorçage : mises en file, supports, crochets, zones de widgets.      |
| `index.php`                | Modèle de secours (obligatoire).                                     |
| `template-parts/`          | Partiels réutilisables inclus via `get_template_part()`.             |
| `inc/`                     | Modules PHP chargés depuis `functions.php`.                          |
| `add_theme_support()`      | Adhésion aux fonctionnalités du cœur (post-thumbnails, html5, title-tag, etc.). |
| Crochet `wp_enqueue_scripts` | Crochet correct pour mettre en file les styles et les scripts.      |

**Directives pour les agents :**
- Mettez toujours les styles et les scripts en file via `wp_enqueue_scripts` (ou `admin_enqueue_scripts` pour l’administration).
- Utilisez `get_template_part()` pour les partiels ; n’utilisez jamais `include` ou `require` directement pour les parties de modèle.
- Déclarez `add_theme_support( 'title-tag' )` au lieu de coder `<title>` en dur.
- Utilisez `wp_localize_script()` ou `wp_add_inline_script()` pour transmettre des données PHP au JavaScript.

---

## Thèmes de blocs et édition complète du site (FSE)

**Quand consulter :** lors de la construction ou de l’utilisation de thèmes à base de blocs avec l’éditeur de site, `theme.json`, les parties de modèle et les compositions de blocs.

### Ressources clés
- [Manuel des thèmes de blocs](https://developer.wordpress.org/block-editor/how-to-guides/themes/)
- [Réglages et styles globaux (theme.json)](https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/)
- [Référence de theme.json](https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/)
- [Fichiers de modèle de blocs](https://developer.wordpress.org/themes/templates/)
- [Compositions de blocs](https://developer.wordpress.org/themes/features/block-patterns/)
- [Variations de style de bloc](https://developer.wordpress.org/themes/features/block-style-variations/)
- [Architecture de l’éditeur de site (manuel de l’éditeur de blocs)](https://developer.wordpress.org/block-editor/explanations/architecture/full-site-editing-templates/)
- [API d’interactivité](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/)

### Structure
| Concept                   | Détail de mise en œuvre                                                                 |
|---------------------------|-----------------------------------------------------------------------------------------|
| `theme.json`              | Configuration canonique des réglages de l’éditeur, des palettes de couleurs, des tailles de police, des espacements et des bordures. |
| `/templates/*.html`       | Modèles en balisage de blocs (remplacent les modèles PHP des thèmes classiques).        |
| `/parts/*.html`           | Parties de modèle réutilisables (en-tête, pied de page, barre latérale) modifiables dans l’éditeur de site. |
| `/patterns/*.php`         | Compositions de blocs enregistrées, disponibles dans l’inséreur.                        |
| `appearanceTools`         | Un seul indicateur `theme.json` active les bordures, le remplissage, la marge, la couleur des liens, etc. |
| Propriétés CSS personnalisées | Les préréglages de `theme.json` sont convertis automatiquement en variables CSS `--wp--preset--*`. |
| `register_block_style()`  | Fonction PHP pour ajouter des variations de style à n’importe quel bloc.                 |
| Synchronisation des modèles | Les modèles modifiés sont dupliqués dans le type de publication `wp_template` ; le fichier d’origine reste en secours. |

**Directives pour les agents :**
- Préférez `theme.json` à `add_theme_support()` pour toute la configuration de l’éditeur de blocs.
- Ne codez jamais en dur les couleurs ou les tailles de police qui appartiennent aux préréglages de `theme.json`.
- Utilisez les variables CSS `--wp--preset--color--{slug}` pour référencer les couleurs de la palette du thème.
- Les thèmes de blocs prennent en charge automatiquement `post-thumbnails`, `editor-styles`, `responsive-embeds` et `html5`. Ne les redéclarez pas.
- La prise en charge de `title-tag` est automatique dans les thèmes de blocs : ne l’ajoutez pas manuellement.
- Enregistrez les compositions dans `/patterns/` avec l’API basée sur les fichiers (métadonnées dans le commentaire d’en-tête) ; aucune inscription PHP n’est nécessaire depuis WP 6.0.

---

## Crochets WordPress (actions et filtres)

**Quand consulter :** pour étendre ou modifier le comportement de WordPress via des extensions ou des thèmes.

### Ressources clés
- [Manuel des crochets](https://developer.wordpress.org/plugins/hooks/)
- [Actions](https://developer.wordpress.org/plugins/hooks/actions/)
- [Filtres](https://developer.wordpress.org/plugins/hooks/filters/)
- [Crochets personnalisés](https://developer.wordpress.org/plugins/hooks/custom-hooks/)
- [Sujets avancés](https://developer.wordpress.org/plugins/hooks/advanced-topics/)

| Type de crochet | Exigence de retour | Cas d’usage principal                                       |
|-----------------|--------------------|--------------------------------------------------------------|
| Action          | Retourne void      | Afficher des scripts, envoyer des e-mails, mettre à jour la base de données. |
| Filtre          | Doit retourner une valeur | Modifier du contenu, ajuster des tableaux, modifier des requêtes. |

**Directives pour les agents :**
- Distinguez les actions (déclenchées par un événement, sans retour) des filtres (traitement de données, qui doivent retourner une valeur).
- Évitez les effets de bord dans les filtres (modifier des globales, afficher du contenu).
- Utilisez la priorité pour contrôler l’ordre d’exécution.
- Utilisez `do_action_ref_array()` et `apply_filters_ref_array()` pour passer des arguments par référence.
- Retirez toujours les crochets avec la même priorité et la même référence d’objet que celles utilisées pour les ajouter.

---

## API REST de WordPress

**Quand consulter :** pour développer des interfaces découplées, des routes JSON personnalisées ou une récupération de données pilotée par JavaScript.

### Ressources clés
- [Manuel de l’API REST](https://developer.wordpress.org/rest-api/)
- [Routes et points de terminaison](https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/)
- [Requêtes](https://developer.wordpress.org/plugins/rest-api/requests/)
- [Réponses](https://developer.wordpress.org/plugins/rest-api/responses-2/)
- [Schéma](https://developer.wordpress.org/plugins/rest-api/schema/)
- [Authentification](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)
- [Étendre l’API REST](https://developer.wordpress.org/rest-api/extending-the-rest-api/)
- [Ajouter des points de terminaison personnalisés](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)

| Méthode HTTP | Action API                | Méthode du contrôleur             |
|--------------|---------------------------|-----------------------------------|
| GET          | Récupérer une ressource   | `get_items()` ou `get_item()`     |
| POST         | Créer une ressource       | `create_item()`                   |
| PUT/PATCH    | Mettre à jour une ressource | `update_item()`                 |
| DELETE       | Supprimer une ressource   | `delete_item()`                   |

**Directives pour les agents :**
- Utilisez des espaces de noms (par exemple `myplugin/v1`) pour éviter les collisions de routes.
- Fournissez toujours un `permission_callback` pour chaque route personnalisée.
- Définissez des schémas JSON détaillés pour les arguments et les réponses des points de terminaison.
- Étendez `WP_REST_Controller` pour une structure de contrôleur cohérente.
- Enregistrez les routes sur `rest_api_init`, pas sur `init`.

---

## API HTTP

**Quand consulter :** pour effectuer des requêtes HTTP sortantes vers des API ou des services externes.

### Ressources clés
- [API HTTP (manuel des extensions)](https://developer.wordpress.org/plugins/http-api/)
- [Référence de la classe WP_HTTP](https://developer.wordpress.org/reference/classes/wp_http/)
- [`wp_remote_get()`](https://developer.wordpress.org/reference/functions/wp_remote_get/)
- [`wp_remote_post()`](https://developer.wordpress.org/reference/functions/wp_remote_post/)
- [`wp_remote_request()`](https://developer.wordpress.org/reference/functions/wp_remote_request/)

| Fonction               | Méthode HTTP | Cas d’usage principal                              |
|------------------------|--------------|-----------------------------------------------------|
| `wp_remote_get()`      | GET          | Récupérer des données depuis une API externe.       |
| `wp_remote_post()`     | POST         | Envoyer des données (formulaire, corps JSON) à un point de terminaison. |
| `wp_remote_head()`     | HEAD         | Vérifier les en-têtes ou la fraîcheur sans télécharger le corps. |
| `wp_remote_request()`  | Toute        | Contrôle total de la méthode, des en-têtes, du corps et du délai d’expiration. |
| `wp_safe_remote_get()` | GET          | Identique à `wp_remote_get()`, mais bloque les adresses IP locales. |

**Directives pour les agents :**
- Vérifiez toujours `WP_Error` après chaque appel HTTP : `is_wp_error( $response )`.
- Utilisez `wp_remote_retrieve_response_code()` pour valider le statut HTTP avant d’analyser le corps.
- Utilisez `wp_remote_retrieve_body()` pour extraire le corps de la réponse.
- Mettez les réponses en cache avec l’API des transients pour éviter de marteler les services externes.
- Utilisez `wp_safe_remote_get()` pour les URL fournies par l’utilisateur afin de prévenir les attaques SSRF.
- Définissez un argument `timeout` raisonnable (5 s par défaut ; augmentez-le pour les API lentes).

---

## API de base de données de WordPress

**Quand consulter :** pour gérer les données stockées dans WordPress (options, métadonnées, transients, accès SQL direct).

### Ressources clés
- [Référence de la classe `$wpdb`](https://developer.wordpress.org/reference/classes/wpdb/)
- [API des options](https://developer.wordpress.org/plugins/settings/options-api/)
- [API des transients](https://developer.wordpress.org/apis/transients/)
- [API des métadonnées](https://developer.wordpress.org/plugins/metadata/)
- [Gérer les métadonnées des publications](https://developer.wordpress.org/plugins/metadata/managing-post-metadata/)

| Type de stockage  | Idéal pour                         | API native                           |
|-------------------|------------------------------------|--------------------------------------|
| Options           | Réglages de configuration statiques | `get_option()`, `update_option()`   |
| Métadonnées       | Propriétés liées aux publications ou aux utilisateurs | `add_post_meta()`, `get_user_meta()` |
| Transients        | Données temporaires ou en cache    | `set_transient()`, `get_transient()` |
| Tables personnalisées | Jeux de données volumineux et spécifiques | `$wpdb->insert()`, `$wpdb->query()` |

**Directives pour les agents :**
- Utilisez `$wpdb->prepare()` pour toutes les requêtes dynamiques afin de prévenir l’injection SQL.
- Auditez les options chargées automatiquement : gardez leur taille totale sous 800 Ko.
- Utilisez `$wpdb->get_results()`, `$wpdb->get_row()` et `$wpdb->get_var()` au lieu de `query()` brut pour les requêtes SELECT.
- Utilisez toujours les constantes de nom de table (`$wpdb->posts`, `$wpdb->postmeta`, etc.) plutôt que des noms de table codés en dur.
- Lors de la création de tables personnalisées, utilisez `dbDelta()` (depuis `wp-admin/includes/upgrade.php`) pour gérer les changements de schéma en toute sécurité.

---

## API des réglages de WordPress

**Quand consulter :** pour ajouter des pages de réglages d’administration dont les champs sont enregistrés et nettoyés par l’API du cœur.

### Ressources clés
- [API des réglages](https://developer.wordpress.org/plugins/settings/settings-api/)
- [Utiliser l’API des réglages](https://developer.wordpress.org/plugins/settings/using-settings-api/)
- [Page de réglages personnalisée](https://developer.wordpress.org/plugins/settings/custom-settings-page/)
- [API des options](https://developer.wordpress.org/plugins/settings/options-api/)

| Composant              | Fonction                  | Crochet            |
|------------------------|---------------------------|--------------------|
| Enregistrement de page | `add_options_page()`      | `admin_menu`       |
| Groupe de réglages     | `register_setting()`      | `admin_init`       |
| Section                | `add_settings_section()`  | `admin_init`       |
| Champ                  | `add_settings_field()`    | `admin_init`       |
| Rendu du formulaire    | `settings_fields()` + `do_settings_sections()` | Fonction de rappel |

**Directives pour les agents :**
- Enregistrez les réglages pendant `admin_init`, pas `admin_menu`.
- Fournissez toujours un `sanitize_callback` dans `register_setting()`.
- Utilisez `options-general.php` comme parent des pages de réglages, sauf si un menu d’administration personnalisé est nécessaire.

---

## Types de publication personnalisés et taxonomies

**Quand consulter :** pour enregistrer des types de publication personnalisés ou des taxonomies personnalisées destinés à des modèles de contenu métier.

### Ressources clés
- [Types de publication personnalisés](https://developer.wordpress.org/plugins/post-types/)
- [Enregistrer des types de publication personnalisés](https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/)
- [Utiliser les types de publication personnalisés](https://developer.wordpress.org/plugins/post-types/working-with-custom-post-types/)
- [Taxonomies](https://developer.wordpress.org/plugins/taxonomies/)
- [Utiliser les taxonomies personnalisées](https://developer.wordpress.org/plugins/taxonomies/working-with-custom-taxonomies/)

| Concept                  | Fonction / crochet                                  | Remarques                                          |
|--------------------------|-----------------------------------------------------|----------------------------------------------------|
| Enregistrer un type de publication | `register_post_type()` sur `init`          | Utilisez une priorité précoce (< 10) pour garantir la disponibilité. |
| Enregistrer une taxonomie | `register_taxonomy()` sur `init`                  | Liez-la au type de publication via l’argument `object_type`. |
| Vider les règles de réécriture | `flush_rewrite_rules()` à l’activation uniquement | Ne l’appelez jamais à chaque requête : le coût de performance est élevé. |
| Boîtes de métadonnées personnalisées | `add_meta_box()` sur `add_meta_boxes`      | Préférez `registerPlugin` de l’éditeur de blocs pour l’édition complète du site. |
| Prise en charge de l’API REST | `'show_in_rest' => true`                     | Nécessaire pour la compatibilité avec l’éditeur de blocs. |

**Directives pour les agents :**
- Définissez toujours `'show_in_rest' => true` pour les types de publication qui doivent fonctionner avec l’éditeur de blocs.
- Appelez `flush_rewrite_rules()` uniquement sur les crochets d’activation ou de désactivation de l’extension, jamais sur `init`.
- Utilisez `post_type_supports()` pour ajouter des fonctionnalités (titre, éditeur, miniature, etc.) après l’enregistrement.
- Préférez des types de capacité liés au type de publication (`'capability_type' => 'my_post_type'`) pour des permissions fines.

---
## Utilisateurs, rôles et capacités

**Quand consulter :** pour gérer l’authentification, l’autorisation, l’accès par rôle ou les métadonnées des utilisateurs.

### Ressources clés
- [Utilisateurs](https://developer.wordpress.org/plugins/users/)
- [Rôles et capacités](https://developer.wordpress.org/plugins/users/roles-and-capabilities/)
- [Utiliser les utilisateurs](https://developer.wordpress.org/plugins/users/working-with-users/)
- [Utiliser les métadonnées des utilisateurs](https://developer.wordpress.org/plugins/users/working-with-user-metadata/)
- [Référence de la classe `WP_User`](https://developer.wordpress.org/reference/classes/wp_user/)

| Tâche                        | Fonction / méthode                            |
|------------------------------|----------------------------------------------|
| Obtenir l’utilisateur courant | `wp_get_current_user()`, `get_current_user_id()` |
| Vérifier une capacité        | `current_user_can( 'capability' )`           |
| Ajouter une capacité personnalisée | `$role->add_cap( 'my_cap' )` à l’activation |
| Ajouter un rôle personnalisé | `add_role()` à l’activation                  |
| Supprimer un rôle à la désinstallation | `remove_role()` dans `uninstall.php` |
| Métadonnées utilisateur      | `get_user_meta()`, `update_user_meta()`      |

**Directives pour les agents :**
- Vérifiez toujours les capacités, pas les rôles (`current_user_can( 'edit_posts' )`, pas `is_admin()`).
- Ajoutez les capacités personnalisées dans le crochet d’activation ; retirez-les dans `uninstall.php`.
- Ne stockez jamais de données utilisateur sensibles en clair dans les métadonnées utilisateur.

---

## WP-Cron

**Quand consulter :** pour planifier des tâches récurrentes ou différées dans WordPress.

### Ressources clés
- [WP-Cron (manuel des extensions)](https://developer.wordpress.org/plugins/cron/)
- [Planifier des événements WP-Cron](https://developer.wordpress.org/plugins/cron/scheduling-wp-cron-events/)
- [Brancher WP-Cron sur le planificateur de tâches du système](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/)
- [Test simple de WP-Cron](https://developer.wordpress.org/plugins/cron/simple-testing/)
- [Commandes `cron` de WP-CLI](https://developer.wordpress.org/cli/commands/cron/)

| Fonction                         | Rôle                                                 |
|----------------------------------|------------------------------------------------------|
| `wp_schedule_event()`            | Planifier un événement récurrent.                    |
| `wp_schedule_single_event()`     | Planifier un événement unique.                       |
| `wp_unschedule_event()`          | Supprimer un événement planifié précis.              |
| `wp_clear_scheduled_hook()`      | Supprimer tous les événements d’un crochet donné.    |
| `wp_next_scheduled()`            | Vérifier si un événement est déjà planifié.          |
| `wp_get_schedule()`              | Récupérer la récurrence d’un événement planifié.     |

**Directives pour les agents :**
- Vérifiez toujours `wp_next_scheduled()` avant de planifier pour éviter les événements en double.
- Effacez les crochets planifiés dans le crochet de désactivation de l’extension via `wp_clear_scheduled_hook()`.
- WP-Cron ne s’exécute qu’au chargement d’une page ; pour une précision temporelle élevée, branchez-le sur le cron système via `wp-cron.php` avec `DISABLE_WP_CRON`.
- Utilisez WP-CLI (`wp cron event run`, `wp cron event list`) pour tester et déboguer les événements cron.

---

## Internationalisation et localisation (i18n / l10n)

**Quand consulter :** pour rendre les chaînes d’une extension ou d’un thème traduisibles.

### Ressources clés
- [Internationalisation (manuel des extensions)](https://developer.wordpress.org/plugins/internationalization/)
- [Comment internationaliser votre extension](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/)
- [Localisation (manuel des extensions)](https://developer.wordpress.org/plugins/internationalization/localization/)
- [Sécurité des chaînes traduisibles](https://developer.wordpress.org/plugins/internationalization/security/#escape-internationalized-strings)
- [Polyglots : guide des auteurs d’extensions et de thèmes](https://make.wordpress.org/polyglots/handbook/plugin-theme-authors-guide/)
- [i18n JavaScript (`@wordpress/i18n`)](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/)
- [`wp i18n make-pot` (WP-CLI)](https://developer.wordpress.org/cli/commands/i18n/make-pot/)

| Fonction                       | Cas d’usage                                         |
|--------------------------------|-----------------------------------------------------|
| `__( 'string', 'textdomain' )` | Traduction simple d’une chaîne.                     |
| `_e( 'string', 'textdomain' )` | Traduire et afficher.                               |
| `_n()`                         | Forme singulière ou plurielle.                      |
| `_x()`                         | Traduction avec contexte.                           |
| `esc_html__()`                 | Traduire et échapper pour une sortie HTML (préféré). |
| `esc_attr__()`                 | Traduire et échapper pour une sortie d’attribut.    |
| `wp_set_script_translations()` | Charger les traductions JavaScript d’un script enregistré. |
| `load_plugin_textdomain()`     | Charger le fichier `.mo` de l’extension.            |

**Directives pour les agents :**
- N’utilisez jamais de chaînes variables ou concaténées dans les fonctions i18n : elles cassent l’analyse statique.
- Les fonctions i18n **ne sont pas** des fonctions d’échappement. Échappez toujours la sortie séparément, ou utilisez `esc_html__()` et `esc_attr__()`.
- Pour les chaînes JavaScript, utilisez `@wordpress/i18n` (`__`, `_n`, `_x` du paquet) et appelez `wp_set_script_translations()` avec le domaine de texte et le chemin corrects.
- Générez les fichiers `.pot` avec `wp i18n make-pot` (WP-CLI) ; n’utilisez pas Poedit ni PoEdit manuellement pour la génération.
- Chargez le domaine de texte sur le crochet `init` (ou `after_setup_theme` pour les thèmes), et non `plugins_loaded`, dans la plupart des configurations modernes.

---

## API de confidentialité

**Quand consulter :** pour traiter des données personnelles, la conformité au RGPD, les flux d’export et d’effacement.

### Ressources clés
- [Confidentialité (manuel des extensions)](https://developer.wordpress.org/plugins/privacy/)
- [Ajouter un exportateur de données personnelles](https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-exporter-to-your-plugin/)
- [Ajouter un effaceur de données personnelles](https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-eraser-to-your-plugin/)
- [Options, crochets et capacités liés à la confidentialité](https://developer.wordpress.org/plugins/privacy/privacy-related-options-hooks-and-capabilities/)
- [Suggérer un texte de politique de confidentialité](https://developer.wordpress.org/plugins/privacy/suggesting-text-for-the-site-privacy-policy/)

| Crochet / fonction                    | Rôle                                                  |
|----------------------------------------|-------------------------------------------------------|
| `wp_privacy_personal_data_exporters`   | Filtre pour enregistrer une fonction de rappel d’exportation. |
| `wp_privacy_personal_data_erasers`     | Filtre pour enregistrer une fonction de rappel d’effacement. |
| `wp_add_privacy_policy_content()`      | Suggérer du texte pour la page de politique de confidentialité du site. |
| `wp_get_user_request()`                | Récupérer une demande de données confirmée.           |

**Directives pour les agents :**
- Enregistrez les exportateurs et les effaceurs sur les filtres `wp_privacy_personal_data_exporters` et `wp_privacy_personal_data_erasers`.
- Fournissez un texte de politique de confidentialité suggéré via `wp_add_privacy_policy_content()` lorsque l’extension collecte des données personnelles.
- Les effaceurs doivent être idempotents et retourner `['items_removed' => bool, 'items_retained' => bool, 'messages' => [], 'done' => bool]`.

---

## JavaScript, jQuery et Ajax dans WordPress

**Quand consulter :** pour ajouter du JavaScript côté public ou dans l’administration, effectuer des appels Ajax ou utiliser jQuery.

### Ressources clés
- [JavaScript, jQuery et Ajax (manuel des extensions)](https://developer.wordpress.org/plugins/javascript/)
- [Ajax (manuel des extensions)](https://developer.wordpress.org/plugins/javascript/ajax/)
- [Mettre les scripts en file](https://developer.wordpress.org/themes/classic-themes/basics/including-css-javascript/)
- [`wp_enqueue_script()`](https://developer.wordpress.org/reference/functions/wp_enqueue_script/)
- [`wp_localize_script()`](https://developer.wordpress.org/reference/functions/wp_localize_script/)
- [`wp_add_inline_script()`](https://developer.wordpress.org/reference/functions/wp_add_inline_script/)
- [API Heartbeat](https://developer.wordpress.org/plugins/javascript/heartbeat-api/)

| Schéma                       | Mise en œuvre                                                |
|------------------------------|--------------------------------------------------------------|
| Mise en file avec dépendance | `wp_enqueue_script( $handle, $src, ['jquery'], $ver, true )` |
| Transmettre des données PHP au JavaScript | `wp_localize_script()` ou `wp_add_inline_script()` |
| Ajax d’administration (hérité) | Crochet sur `wp_ajax_{action}` / `wp_ajax_nopriv_{action}` |
| URL d’Ajax d’administration  | `admin_url( 'admin-ajax.php' )` (transmise via `wp_localize_script`) |
| Ajax via REST (moderne)      | Utilisez `wp_remote_request` côté client via les points de terminaison de l’API REST |
| Nonce dans Ajax              | Générer avec `wp_create_nonce()`, vérifier avec `check_ajax_referer()` |

**Directives pour les agents :**
- Préférez les points de terminaison de l’API REST à `admin-ajax.php` pour les nouvelles intégrations JavaScript : de meilleures performances et une authentification standardisée.
- Transmettez toujours le nonce via `wp_localize_script()` et vérifiez-le côté serveur avec `check_ajax_referer()`.
- Mettez les scripts en file avec `true` comme cinquième argument pour les charger dans le pied de page.
- Utilisez `wp_add_inline_script()` au lieu de `wp_localize_script()` pour transmettre des données structurées complexes.

---

## Bonnes pratiques de sécurité WordPress

**Quand consulter :** pour les revues de sécurité du code, les audits ou la modélisation des menaces.

### Ressources clés
- [Sécurité (manuel des extensions)](https://developer.wordpress.org/plugins/security/)
- [Validation des données](https://developer.wordpress.org/plugins/security/data-validation/)
- [Vérifier les capacités des utilisateurs](https://developer.wordpress.org/plugins/security/checking-user-capabilities/)
- [Nonces](https://developer.wordpress.org/plugins/security/nonces/)
- [Sécuriser la sortie (échappement)](https://developer.wordpress.org/plugins/security/securing-output/)
- [Sécuriser l’entrée (nettoyage)](https://developer.wordpress.org/plugins/security/securing-input/)

| Catégorie de menace      | Stratégie d’atténuation                          |
|--------------------------|--------------------------------------------------|
| XSS                      | Échappement tardif (`esc_html`, `esc_attr`, `wp_kses`) |
| Injection SQL            | `$wpdb->prepare()`                               |
| CSRF                     | Nonces (`wp_verify_nonce`, `check_admin_referer`) |
| Élévation de privilèges  | `current_user_can()` avant chaque action sensible |
| SSRF                     | `wp_safe_remote_get()` pour les URL fournies par l’utilisateur |
| Traversée de chemin      | Barrière `ABSPATH` + validation `realpath()`     |
| Redirection ouverte      | `wp_safe_redirect()` au lieu de `wp_redirect()`  |

**Directives pour les agents :**
- Considérez toute entrée comme malveillante : validez, nettoyez et échappez sans condition.
- Utilisez `current_user_can()` avant de traiter toute action privilégiée.
- Utilisez `wp_safe_redirect()` pour prévenir les vulnérabilités de redirection ouverte.
- Ne faites jamais confiance à `$_SERVER['HTTP_REFERER']` pour les contrôles de sécurité : utilisez des nonces.

---

## Nettoyage et échappement des données

**Quand consulter :** avant de traiter une entrée utilisateur ou d’afficher une sortie.

### Ressources clés
- [Sécuriser l’entrée (nettoyage)](https://developer.wordpress.org/plugins/security/securing-input/)
- [Sécuriser la sortie (échappement)](https://developer.wordpress.org/plugins/security/securing-output/)
- [Validation des données](https://developer.wordpress.org/plugins/security/data-validation/)

| Contexte         | Nettoyage (entrée)            | Échappement (sortie)       |
|------------------|-------------------------------|----------------------------|
| Texte brut       | `sanitize_text_field()`       | `esc_html()`               |
| Attribut HTML    | `sanitize_text_field()`       | `esc_attr()`               |
| URL              | `esc_url_raw()`               | `esc_url()`                |
| E-mail           | `sanitize_email()`            | `esc_attr()`               |
| Entier           | `intval()` / `absint()`       | `intval()`                 |
| Contenu HTML     | `wp_kses_post()` / `wp_kses()`| `wp_kses_post()`           |
| Zone de texte    | `sanitize_textarea_field()`   | `esc_textarea()`           |
| Nom de fichier   | `sanitize_file_name()`        | `esc_attr()`               |
| SQL              | `$wpdb->prepare()`            | N/A                        |
| Sortie JS        | N/A                           | `esc_js()`                 |
| Texte traduit    | N/A                           | `esc_html__()`, `esc_attr__()` |

`antispambot()` obfusque une adresse e-mail pour l’affichage ; ce n’est pas une fonction d’échappement.

**Directives pour les agents :**
- Pratiquez l’« échappement tardif » : échappez au plus près du point de sortie.
- Utilisez la fonction la plus restrictive pour le contexte.
- `wp_kses_post()` autorise le sous-ensemble de HTML permis dans le contenu des publications ; préférez-la à `wp_kses()` avec une liste manuelle de balises autorisées.

---

## Nonces et contrôles de capacité

**Quand consulter :** pour sécuriser des formulaires, des gestionnaires Ajax ou toute action qui modifie l’état.

### Ressources clés
- [Nonces](https://developer.wordpress.org/plugins/security/nonces/)
- [Vérifier les capacités des utilisateurs](https://developer.wordpress.org/plugins/security/checking-user-capabilities/)

| Fonction                           | Contexte                                 |
|------------------------------------|------------------------------------------|
| `wp_create_nonce( 'action' )`      | Générer une valeur de nonce pour les formulaires ou Ajax. |
| `wp_nonce_field( 'action' )`       | Afficher un champ de nonce masqué dans un formulaire. |
| `wp_verify_nonce( $nonce, 'action' )` | Vérifier un nonce (retourne 1 ou 2, pas un booléen). |
| `check_admin_referer( 'action' )`  | Vérifier le nonce et le référent ; s’arrêter en cas d’échec. |
| `check_ajax_referer( 'action' )`   | Vérifier le nonce dans les gestionnaires Ajax ; s’arrêter en cas d’échec. |
| `current_user_can( 'cap' )`        | Vérifier une capacité avant toute action. |

**Directives pour les agents :**
- Les nonces expirent (24 h par défaut, en deux fenêtres de 12 h) : ne mettez pas en cache les pages qui contiennent des valeurs de nonce.
- Vérifiez les capacités **avant** le nonce pour ne pas divulguer d’information sur sa validité.
- Vérifiez les capacités, pas les rôles : `current_user_can( 'manage_options' )`, pas `is_admin()`.

---

## Développement de blocs Gutenberg

**Quand consulter :** pour créer des blocs personnalisés destinés à l’éditeur de blocs.

### Ressources clés
- [Manuel de l’éditeur de blocs](https://developer.wordpress.org/block-editor/)
- [Référence de l’API des blocs](https://developer.wordpress.org/block-editor/reference-guides/block-api/)
- [Référence de block.json](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/)
- [Supports de bloc](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/)
- [Compositions de blocs](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-patterns/)
- [Variations de bloc](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/)
- [Transformations de bloc](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-transforms/)
- [`@wordpress/create-block`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-create-block/)
- [`@wordpress/scripts`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)

| Fonctionnalité du bloc | Détail de mise en œuvre                                              |
|------------------------|-----------------------------------------------------------------------|
| `block.json`           | Métadonnées canoniques : nom, titre, catégorie, attributs, supports. |
| Attributs              | À définir dans `block.json` ; les associer au balisage enregistré via `source` + `selector`. |
| Composant d’édition    | Composant React pour l’interface de l’éditeur ; utilise `useBlockProps()`. |
| Fonction d’enregistrement | Produit le HTML statique stocké dans `post_content` ; doit être déterministe. |
| Blocs dynamiques       | `render_callback` en PHP ; `save` retourne `null` ; aucun HTML statique n’est stocké. |
| Supports de bloc       | Fonctionnalités de l’éditeur activées sur demande (`color`, `typography`, `spacing`, `align`). |
| `useBlockProps()`      | Crochet obligatoire ; fusionne les attributs de bloc du cœur (classe, id, etc.). |

**Directives pour les agents :**
- Utilisez toujours `block.json` comme source unique de vérité pour les métadonnées du bloc.
- Utilisez `@wordpress/create-block` pour générer la structure des nouveaux blocs.
- Utilisez des blocs dynamiques (`render_callback` en PHP) pour tout contenu qui doit être à jour au rendu.
- Validez rigoureusement la sortie statique de `save` : une erreur de sérialisation casse le contenu existant.
- Enregistrez les ressources du bloc via les champs `editorScript`, `script`, `style` et `editorStyle` de `block.json`.

---

## Composants React de WordPress

**Quand consulter :** pour construire des interfaces d’administration ou des contrôles de bloc en JavaScript/React.

### Ressources clés
- [Référence des composants (`@wordpress/components`)](https://developer.wordpress.org/block-editor/reference-guides/components/)
- [Module de données (`@wordpress/data`)](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-data/)
- [Dépôts de données de l’éditeur de blocs](https://developer.wordpress.org/block-editor/reference-guides/data/)

| Catégorie de composant | Composants clés                             | Cas d’usage                            |
|------------------------|---------------------------------------------|----------------------------------------|
| Entrées de base        | `TextControl`, `SelectControl`, `CheckboxControl` | Réglages et champs de formulaire. |
| Sélecteurs             | `ColorPicker`, `FontSizePicker`, `GradientPicker` | Contrôles de design.            |
| Mise en page           | `Flex`, `Grid`, `HStack`, `VStack`          | Structurer les panneaux de la barre latérale. |
| Retour d’information   | `Notice`, `Spinner`, `Tooltip`              | Messages d’état et états de chargement. |
| Navigation             | `TabPanel`, `NavigableMenu`, `Dropdown`     | Interfaces à plusieurs sections.       |
| Panneaux               | `Panel`, `PanelBody`, `PanelRow`            | Sections de réglages repliables.       |

**Directives pour les agents :**
- Utilisez `@wordpress/components` pour une interface cohérente avec l’éditeur de blocs.
- Accédez aux données de l’éditeur de blocs via les dépôts `@wordpress/data` (`core`, `core/editor`, `core/blocks`).
- Utilisez les crochets `useSelect()` et `useDispatch()` pour un accès réactif aux dépôts.
- Vérifiez les contextes manquants (par exemple `SlotFillProvider`) si des composants ne s’affichent pas.

---

## Accessibilité (WCAG 2.1+)

**Quand consulter :** pour garantir la conformité aux normes internationales d’accessibilité.

### Ressources clés
- [WCAG 2.1 (W3C)](https://www.w3.org/TR/WCAG21/)
- [WCAG 2.2 (W3C)](https://www.w3.org/TR/WCAG22/)
- [Référence rapide WCAG](https://www.w3.org/WAI/WCAG22/quickref/?versions=2.1)
- [Comprendre WCAG 2.1](https://www.w3.org/WAI/WCAG21/Understanding/)

| Principe WCAG | Exigence technique                           | Action de l’agent                      |
|----------------|----------------------------------------------|----------------------------------------|
| Perceptible    | Texte alternatif pour les images ; rapports de contraste ≥ 4,5:1. | Utilisez un texte alternatif descriptif sur toutes les images. |
| Utilisable     | Navigation clavier complète ; focus visible. | Assurez l’ordre de tabulation ; testez sans souris. |
| Compréhensible | Instructions claires ; messages d’erreur descriptifs. | Utilisez des libellés de formulaire explicites ; erreurs en ligne. |
| Robuste        | Compatible avec les technologies d’assistance. | Utilisez du HTML sémantique ; testez avec NVDA/VoiceOver. |

**Directives pour les agents :**
- Visez au minimum la conformité de niveau AA.
- Vérifiez le contraste des couleurs avec des outils comme le WebAIM Contrast Checker.
- Assurez un indicateur de focus visible sur tous les éléments interactifs.
- Remarque : WCAG 2.2 a ajouté le critère SC 2.4.11 (Focus Appearance) et le SC 2.5.8 (Target Size Minimum, 24 × 24 px).

---
## Directives d’accessibilité de WordPress

**Quand consulter :** pour les exigences d’accessibilité propres à WordPress dans l’administration et la sortie publique.

### Ressources clés
- [Accessibilité de WordPress](https://developer.wordpress.org/themes/classic-themes/functionality/accessibility/)
- [Normes de codage d’accessibilité de WordPress](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/accessibility/)
- [Accessibilité dans l’éditeur de blocs](https://developer.wordpress.org/block-editor/how-to-guides/accessibility/)
- [Make WordPress Accessible](https://make.wordpress.org/accessibility/)

| Exigence              | Norme de mise en œuvre                        |
|-----------------------|-----------------------------------------------|
| Rapport de contraste  | Minimum 4,5:1 pour le texte standard.         |
| Liens                 | Soulignés s’ils se trouvent dans des blocs de contenu. |
| Indicateurs de focus  | Visibles pour tous les éléments accessibles au clavier. |
| Libellés de formulaire | Utilisez des balises `<label>` ; évitez le placeholder seul. |
| Liens d’évitement     | Requis pour les pages d’administration et les interfaces publiques riches. |
| Fenêtres modales et boîtes de dialogue | Piégez le focus à l’intérieur ; restaurez-le à la fermeture. |
| Messages d’erreur     | Associés par programme aux champs de formulaire. |

**Directives pour les agents :**
- Ajoutez un lien d’évitement « Aller au contenu principal » sur tous les modèles de la partie publique.
- Distinguez les images décoratives (`alt=""`) des images informatives.
- Utilisez des régions `aria-live` pour les mises à jour dynamiques du contenu.
- Dans l’administration WP, suivez la convention établie de la classe CSS `screen-reader-text` pour le contenu masqué visuellement.

---

## HTML sémantique et ARIA

**Quand consulter :** pour écrire un balisage accessible et sémantique.

### Ressources clés
- [Guide des pratiques de rédaction ARIA (APG)](https://www.w3.org/WAI/ARIA/apg/)
- [ARIA dans HTML](https://www.w3.org/TR/html-aria/)
- [WAI-ARIA 1.2 (W3C)](https://www.w3.org/TR/wai-aria-1.2/)

| Règle                                  | Détail                                                                 |
|----------------------------------------|------------------------------------------------------------------------|
| Première règle d’ARIA                  | N’utilisez pas ARIA si la sémantique HTML native suffit.               |
| Éléments interactifs                   | Seuls les `role` intrinsèquement interactifs ont besoin d’une prise en charge clavier. |
| Éléments enfants requis                | Certains rôles exigent des rôles enfants précis (par exemple `listbox` → `option`). |
| `aria-label` vs `aria-labelledby`      | Préférez `aria-labelledby` si un libellé visible existe.               |
| Régions live                           | Utilisez `aria-live="polite"` pour les mises à jour non urgentes ; `"assertive"` avec parcimonie. |

**Directives pour les agents :**
- Préférez les éléments HTML natifs aux rôles ARIA (par exemple `<button>`, pas `<div role="button">`).
- Assurez l’utilisabilité au clavier de tous les contrôles ARIA interactifs (Entrée, Espace, flèches selon le schéma APG).
- N’utilisez pas `aria-hidden="true"` sur des éléments focalisables.

---

## Optimisation des performances

**Quand consulter :** pour améliorer la vitesse du site, le TTFB et l’efficacité des ressources.

### Ressources clés
- [Manuel des performances](https://developer.wordpress.org/advanced-administration/performance/)
- [Extension Performance Lab (officielle)](https://wordpress.org/plugins/performance-lab/)
- [Query Monitor (débogage)](https://wordpress.org/plugins/query-monitor/)

| Couche d’optimisation | Stratégie                             | Justification                         |
|-----------------------|---------------------------------------|---------------------------------------|
| PHP                   | Utilisez une branche PHP prise en charge (8.3 ou plus récente) et OPcache. | Exécution plus rapide, mémoire réduite. |
| Base de données       | Optimisez les requêtes ; nettoyez les transients. | Évite l’encombrement et les requêtes lentes. |
| Front-end             | Minifiez le CSS et le JavaScript ; chargez les images en différé. | Réduit le TTFB et le LCP. |
| Chargement des scripts | `async`/`defer` partout où c’est possible. | Rendu de page non bloquant.      |
| Optimisation des images | `loading="lazy"`, WebP, tailles correctes. | Réduit la bande passante et le CLS. |

**Directives pour les agents :**
- Donnez la priorité à la mise en cache des pages publiques.
- Utilisez le cache d’objets (Redis/Memcached) pour les sites à fort trafic.
- Auditez les schémas de requêtes N+1 avec Query Monitor.
- Utilisez `wp_is_mobile()` avec prudence : elle repose sur l’analyse de l’agent utilisateur ; préférez CSS ou les indications du client.

---

## Mise en cache WordPress

**Quand consulter :** pour un stockage temporaire de données afin de réduire la charge de la base de données.

### Ressources clés
- [API des transients](https://developer.wordpress.org/apis/transients/)
- [API du cache d’objets](https://developer.wordpress.org/reference/classes/wp_object_cache/)
- [Cache d’objets persistant](https://developer.wordpress.org/advanced-administration/performance/cache/)

| Type de cache      | API                                          | Prise en charge de la durée de vie | Persistant |
|--------------------|----------------------------------------------|------------------------------------|------------|
| Transients         | `set_transient()`, `get_transient()`         | Oui                                | Oui (avec cache d’objets) |
| Cache d’objets     | `wp_cache_set()`, `wp_cache_get()`           | Oui                                | Uniquement avec un drop-in |
| Transients de site | `set_site_transient()` (compatible multisite) | Oui                               | Oui        |

**Directives pour les agents :**
- Utilisez les transients pour les données à expiration naturelle (réponses d’API, agrégats calculés).
- Utilisez le cache d’objets (`wp_cache_*`) pour mettre en cache les calculs coûteux le temps d’une requête.
- Quand un drop-in de cache d’objets persistant est présent, les transients l’utilisent automatiquement : évitez de doubler les stratégies de cache.
- Supprimez toujours les transients lors des changements de données concernés ; n’attendez pas l’expiration.

---

## Core Web Vitals

**Quand consulter :** pour améliorer les métriques d’expérience utilisateur liées au classement Google Search et à l’UX.

### Ressources clés
- [Core Web Vitals (Google)](https://web.dev/articles/vitals)
- [INP (Interaction to Next Paint)](https://web.dev/articles/inp)
- [CLS (Cumulative Layout Shift)](https://web.dev/articles/cls)
- [LCP (Largest Contentful Paint)](https://web.dev/articles/lcp)

| Métrique | Cible   | Stratégie d’optimisation                        |
|----------|---------|-------------------------------------------------|
| LCP      | < 2,5 s | Préchargez l’image principale ; CSS critique en ligne ; CDN. |
| INP      | < 200 ms | Différez le JavaScript non essentiel ; évitez les tâches longues. |
| CLS      | < 0,1   | Définissez `width`/`height` explicites sur les images et les intégrations. |

**Directives pour les agents :**
- Remarque : INP a remplacé FID comme Core Web Vital en mars 2024.
- Donnez la priorité aux ressources visibles sans défilement ; différez le reste.
- Utilisez `fetchpriority="high"` sur l’élément image du LCP.
- Évitez d’insérer des éléments DOM au-dessus du contenu existant après le premier rendu (CLS).

---

## Normes de codage WordPress

**Quand consulter :** pour un code cohérent, maintenable et conforme à WordPress.org.

### Ressources clés
- [Normes de codage WordPress](https://developer.wordpress.org/coding-standards/)
- [Normes de codage PHP](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- [Normes de codage JavaScript](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- [Normes de codage CSS](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
- [Normes de codage HTML](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/html/)
- [Normes de documentation en ligne](https://developer.wordpress.org/coding-standards/inline-documentation-standards/)
- [WPCS (normes de codage WordPress pour PHPCS)](https://github.com/WordPress/WordPress-Coding-Standards)

| Catégorie de norme | Règle                                                  |
|--------------------|--------------------------------------------------------|
| Nommage (PHP)      | `snake_case` pour les fonctions ; `PascalCase` pour les classes. |
| Balises PHP        | Utilisez toujours `<?php ?>` ; jamais la forme abrégée `<?= ?>`. |
| Indentation        | Des tabulations, pas des espaces.                      |
| Espaces            | Autour des opérateurs, après les virgules, à l’intérieur des crochets de tableau. |
| Conditions Yoda    | `if ( true === $var )` : la valeur à gauche.           |
| Style d’accolade   | Accolade ouvrante sur la même ligne (variante K&R).    |

**Directives pour les agents :**
- Utilisez PHP_CodeSniffer avec le jeu de règles `WordPress` (`composer require --dev wp-coding-standards/wpcs`).
- Exécutez PHPCS en CI pour détecter les violations avant la fusion.
- Utilisez `@wordpress/eslint-plugin` pour le lint JavaScript.

---

## Compatibilité PHP

**Quand consulter :** pour garantir la compatibilité avec la version PHP minimale de WordPress et les versions prises en charge.

### Ressources clés
- [Guides de migration PHP (php.net)](https://www.php.net/manual/en/migration84.php)
- [Vérification de compatibilité PHP (sniff PHPCS)](https://github.com/PHPCompatibility/PHPCompatibility)
- [Exigences PHP de WordPress](https://wordpress.org/about/requirements/)

| Version PHP | Statut de prise en charge             | Fonctionnalités notables                     |
|-------------|---------------------------------------|----------------------------------------------|
| 7.4         | Fin de vie (nov. 2022)                | Fonctions fléchées, propriétés typées.       |
| 8.0         | Fin de vie (nov. 2023)                | Expression match, arguments nommés, nullsafe. |
| 8.1         | Fin de vie (31 déc. 2025)             | Énumérations, Fibers, propriétés readonly.   |
| 8.2         | Correctifs de sécurité uniquement jusqu’au 31 déc. 2026 | Classes readonly, type `true`. |
| 8.3         | Correctifs de sécurité uniquement jusqu’au 31 déc. 2027 | Constantes de classe typées, `json_validate()`. |
| 8.4         | Support actif jusqu’au 31 déc. 2026   | Crochets de propriété, visibilité asymétrique. |
| 8.5         | Support actif jusqu’au 31 déc. 2027   | Opérateur pipe, `#[\NoDiscard]`.             |

**Directives pour les agents :**
- WordPress 7.1.4 exige PHP 7.4 ou plus récent. WordPress.org recommande 8.3 ou plus récent.
- Exécutez le sniff `PHPCompatibility` avec WPCS pour détecter les problèmes propres à une version.
- Évitez les fonctions obsolètes : consultez les journaux de modification de PHP pour chaque version mineure.

---

## WP-CLI

**Quand consulter :** pour automatiser des tâches WordPress dans le terminal, en CI/CD ou dans l’outillage de développement.

### Ressources clés
- [Référence des commandes WP-CLI](https://developer.wordpress.org/cli/commands/)
- [Manuel WP-CLI](https://make.wordpress.org/cli/handbook/)
- [Créer des commandes WP-CLI](https://make.wordpress.org/cli/handbook/guides/commands-cookbook/)
- [Commandes `wp plugin`](https://developer.wordpress.org/cli/commands/plugin/)
- [Commandes `wp i18n`](https://developer.wordpress.org/cli/commands/i18n/)
- [Commandes `wp cron`](https://developer.wordpress.org/cli/commands/cron/)
- [Commandes `wp scaffold`](https://developer.wordpress.org/cli/commands/scaffold/)

| Catégorie de commande    | Commandes clés                                                 |
|--------------------------|----------------------------------------------------------------|
| Gestion des extensions   | `wp plugin install`, `wp plugin activate`, `wp plugin update`  |
| Base de données          | `wp db export`, `wp db import`, `wp search-replace`            |
| Utilisateurs             | `wp user create`, `wp user list`, `wp user update`             |
| i18n                     | `wp i18n make-pot`, `wp i18n make-json`, `wp i18n update-po`   |
| Cron                     | `wp cron event list`, `wp cron event run`, `wp cron schedule list` |
| Échafaudage              | `wp scaffold plugin`, `wp scaffold block`, `wp scaffold theme` |
| Évaluation               | `wp eval`, `wp eval-file`                                      |
| Plugin Check             | `wp plugin check {slug}` (nécessite l’extension Plugin Check)  |

**Directives pour les agents :**
- Utilisez `wp search-replace` avec `--dry-run` d’abord pour remplacer des URL dans la base de données.
- Utilisez `wp scaffold plugin` pour amorcer de nouvelles extensions avec la structure et le readme corrects.
- Utilisez `wp i18n make-pot` comme méthode canonique de génération des POT (intégrée aux scripts de blocs).
- Passez `--allow-root` uniquement dans des conteneurs Docker ou CI ; jamais en production en tant que root.

---

## Mises à jour natives de WordPress (cœur)

**Quand consulter :** pour concevoir le comportement d’une extension autour des mises à jour du cœur, des extensions, des thèmes ou des traductions, y compris les mises à jour automatiques en arrière-plan, les flux de l’administration, la parité WP-CLI, les identifiants du système de fichiers ou la comparaison avec des extensions tierces de « gestionnaire de mises à jour ».

### Référence de projet canonique

> **Pointeur.** [`../wordpress-update-system/fr.md`](../wordpress-update-system/fr.md) est la référence canonique des mises à jour natives de WordPress. Elle est vérifiée contre WordPress 7.1.4 et le cœur installé, et elle est en lecture seule sauf autorisation de modification du propriétaire du projet.

- **[`../wordpress-update-system/fr.md`](../wordpress-update-system/fr.md)** — **Cœur de WordPress uniquement :** découverte (§1.1–§1.10) vs application (§2.1–§2.13), **habillages de mise à niveau** (§2.5), **constantes de `wp-config.php`** (§5.10), **retour en arrière** (§2.7), **verrous** (§2.8), **Santé du site** (§4.3), **`WP_Plugin_Dependencies`** (§2.6), points d’entrée (§5.1–§5.13), **notifications dans l’administration** vs **`wp_mail`** (§4.1–§4.6), **`automatic_updates_complete`** / charge utile des résultats (**§4.7**), politique de branche (méthode `Core_Upgrader::should_update_to_version()` et filtre `auto_update_core` en **§1.3**, **§3.7**), référence complète des crochets et des filtres (§6.1–§6.8), chemins des fichiers du cœur et référence des classes (§7.1–§7.2).
- **Extension Updatronix :** la façon dont cette base de code consomme le cœur (crochets, journalisation, REST, réglages) vit dans **`inc/`** et **`updatronix.php`**. Les crochets, filtres et routes REST publics sont documentés dans [`../developer-api/fr.md`](../developer-api/fr.md).

### Ressources clés (officielles)

- [API du système de fichiers](https://developer.wordpress.org/apis/filesystem/)
- [API HTTP](https://developer.wordpress.org/plugins/http-api/) (les vérifications de mise à jour utilisent `wp_remote_post` / `wp_remote_get`)
- [Commandes WP-CLI](https://developer.wordpress.org/cli/commands/) (`wp core update`, `wp plugin update`, `wp theme update`, `wp cron`)
- [Manuel des extensions — Cron](https://developer.wordpress.org/plugins/cron/) (relation entre `wp-cron.php` et les vérifications planifiées)

**Directives pour les agents :**

- Traitez `wp_update_plugins()` / `wp_update_themes()` / `wp_version_check()` comme de la **découverte uniquement** ; l’application des mises à jour passe toujours par les sous-classes de `WP_Upgrader` ou par `WP_Automatic_Updater`.
- Les points d’extension de « politique » (désactiver les mises à jour automatiques, différer la visibilité) sont principalement des **filtres** sur `automatic_updater_disabled`, `auto_update_*` et les crochets de **transients de site** ; voir la référence du projet pour les pointeurs au niveau des fichiers.
- Les mises à jour groupées suivent l’**ordre de l’appelant** ; utilisez les API publiques de **`WP_Plugin_Dependencies`** pour construire des files conscientes des dépendances quand c’est nécessaire.

---
## Tests WordPress

**Quand consulter :** pour mettre en place et exécuter des tests automatisés des fonctionnalités d’une extension.

### Ressources clés
- [Tests automatisés (manuel du cœur)](https://make.wordpress.org/core/handbook/testing/automated-testing/)
- [`wp-env` (environnement local)](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
- [Tests Jest (`@wordpress/jest-preset-default`)](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-jest-preset-default/)

| Niveau de test | Outil      | Couverture                              |
|----------------|------------|-----------------------------------------|
| Unitaire (PHP) | PHPUnit    | Crochets, filtres, points de terminaison REST, classes. |
| Unitaire (JS)  | Jest       | Logique d’édition et d’enregistrement des blocs, modules utilitaires. |
| E2E            | Playwright | Parcours complets de l’administration et de la partie publique dans le navigateur. |

**Directives pour les agents :**
- Utilisez `@wordpress/env` (`wp-env`) pour des environnements de test locaux reproductibles.
- Lancez `wp-env start` avant PHPUnit pour démarrer l’instance WordPress de test.
- Gardez les tests unitaires centrés sur une seule fonction ou classe ; simulez `$wpdb` et les globales.

---

## PHPUnit pour WordPress

**Quand consulter :** pour les tests unitaires PHP de la logique d’une extension.

### Ressources clés
- [Écrire des tests PHPUnit (manuel du cœur)](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/)
- [Documentation PHPUnit](https://phpunit.de/documentation.html)
- [`WP_UnitTestCase`](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/)
- [`WP_Ajax_UnitTestCase`](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/)
- [`WP_REST_TestCase`](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/)

**Directives pour les agents :**
- Étendez `WP_UnitTestCase` pour les cas de test propres à WordPress.
- Utilisez `WP_Ajax_UnitTestCase` pour tester les gestionnaires Ajax.
- Utilisez `WP_REST_TestCase` pour tester les points de terminaison de l’API REST.
- Utilisez `$this->factory` pour créer des données de test (publications, utilisateurs, termes) sans SQL brut.
- Les changements de base de données effectués dans les tests sont annulés automatiquement par `WP_UnitTestCase`.

---

## Tests Playwright / E2E

**Quand consulter :** pour les tests d’interface de bout en bout des parcours d’administration et de la partie publique.

### Ressources clés
- [`@wordpress/scripts` (inclut les utilitaires Playwright)](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)
- [`@wordpress/e2e-test-utils-playwright`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-e2e-test-utils-playwright/)
- [Documentation Playwright](https://playwright.dev/docs/intro)

**Directives pour les agents :**
- Utilisez `@wordpress/e2e-test-utils-playwright` pour les utilitaires propres à WP (connexion, visite de l’administration, etc.).
- Configurez `WP_BASE_URL` dans `playwright.config.ts` (généralement `http://localhost:8889` avec `wp-env`).
- Exécutez les tests Playwright contre `wp-env`, jamais contre la production.

---

## Plugin Check (conformité WordPress.org)

**Quand consulter :** avant de soumettre une extension au répertoire WordPress.org ou pour un audit de conformité.

### Ressources clés
- [Extension Plugin Check](https://wordpress.org/plugins/plugin-check/)
- [Plugin Check sur GitHub](https://github.com/WordPress/plugin-check)
- [Directives de revue des extensions](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Commande Plugin Check de WP-CLI](https://github.com/WordPress/plugin-check#how-to-use)

| Catégorie      | Objet du contrôle                      |
|----------------|----------------------------------------|
| Répertoire des extensions | Conformité aux règles du répertoire. |
| Sécurité       | Détection des vulnérabilités courantes. |
| Performance    | Options chargées automatiquement ; efficacité des requêtes. |
| Accessibilité  | Contrôles structurels de base.         |
| i18n           | Chaînes traduisibles, domaine de texte. |
| Readme         | Structure valide du fichier `readme.txt`. |

**Directives pour les agents :**
- Exécutez `wp plugin check {plugin-slug}` via WP-CLI avant toute soumission à WordPress.org.
- Intégrez Plugin Check à la CI/CD pour détecter les régressions tôt.
- Traitez tous les éléments de gravité `ERROR` ; examinez les éléments `WARNING` au cas par cas.

---

## Directives pour les agents IA

**Protocole de consultation de la documentation :**
- Consultez toujours la documentation officielle de WordPress avant de faire des suppositions.
- Citez des sources précises pour les solutions de code.
- Vérifiez les normes et les éléments obsolètes avant de mettre en œuvre.

**Quand consulter la bibliothèque :**
- Initialisation d’un projet, mise en œuvre d’une fonctionnalité, revue de sécurité, audit de performance.
- Préférez les sources officielles (`developer.wordpress.org`, `make.wordpress.org`, `www.w3.org`) aux sources secondaires.

**Liste de vérification :**
- Le crochet ou la fonction est-il toujours disponible dans la version actuelle de WordPress ?
- Le schéma est-il compatible avec l’exigence PHP minimale du projet ?
- L’API concernée a-t-elle été rendue obsolète ou remplacée dans les versions récentes de WordPress ?

---

## Normes de documentation WordPress

**Quand consulter :** quand un agent produit du contenu destiné à l’utilisateur — readme.txt, texte d’aide en ligne, infobulles, étapes de tutoriel, notes de version ou toute prose lue par des humains hors de l’équipe de développement.

**Dans ce dépôt :** pour les règles de niveau article de HelpHub (majuscules, ponctuation, mise en forme, liste de mots, liens), utilisez les références que porte la compétence `writing` dans `.agents/skills/writing/references/` — le guide de style officiel de la documentation WordPress, découpé en six catégories, `general-guidelines.md`, `language-and-grammar.md`, `punctuation.md`, `formatting.md`, `linking.md` et `developer-content.md`, plus la liste de mots découpée par lettre sous `references/word-list/`. Chaque section porte une URL **`Source:`** vers la page canonique en ligne. Pour la **structure des blocs PHPDoc et JSDoc** (balises obligatoires, en-têtes de fichier, exemples de crochets), utilisez les **normes de codage WordPress** ci-dessus et les [normes de documentation en ligne](https://developer.wordpress.org/coding-standards/inline-documentation-standards/) ; les références ne remplacent pas DevHub pour cela.

### Ressources clés
- [Manuel de l’équipe documentation](https://make.wordpress.org/docs/handbook/documentation-team-handbook/)
- [Guide de grammaire](https://make.wordpress.org/docs/handbook/documentation-team-handbook/handbooks-grammar-guide/)
- [Guide de style et de mise en forme](https://make.wordpress.org/docs/handbook/documentation-team-handbook/handbooks-style-and-formatting-guide/)
- [Guide de ton et de voix](https://make.wordpress.org/docs/handbook/documentation-team-handbook/tone-and-voice-guide/)
- [Politique de liens externes](https://make.wordpress.org/docs/handbook/documentation-team-handbook/external-linking-policy/)
- [Modèle de tutoriel](https://make.wordpress.org/docs/handbook/documentation-team-handbook/tutorial-template/)
- [Manuel des API communes](https://developer.wordpress.org/apis/)

---

### Guide de grammaire

**Quand consulter :** avant d’écrire ou de modifier une prose destinée à l’utilisateur, en particulier en cas de doute sur la ponctuation, les majuscules ou les conventions de formulation de la documentation WordPress.

**Ressources clés**
- [Guide de grammaire de la documentation](https://make.wordpress.org/docs/handbook/documentation-team-handbook/handbooks-grammar-guide/)

| Règle | Norme |
|------|----------|
| Orthographe | Anglais américain (`-ize`, `-or` ; pas `-ise`, `-our`). |
| Voix | Voix active de préférence ; évitez les tournures passives. |
| Virgule d’Oxford | Toujours l’utiliser : `apples, oranges, and bananas`. |
| Guillemets | Uniquement pour les citations directes et les titres de documents ; jamais pour l’emphase ou les commandes. |
| Code en ligne | Utilisez des accents graves pour les commandes, fonctions et noms de fichiers ; jamais de guillemets. |
| WordPress | Mettez toujours le P en majuscule : `WordPress`. |
| Noms d’éléments d’interface | Mettez une majuscule aux noms précis : le bouton `Enregistrer`, la page `Articles`. |
| Noms génériques | Pas de majuscule : thème, extension, page, bloc, utilisateur, administrateur. |
| Emphase | Utilisez le gras ou l’italique ; jamais les majuscules ni les guillemets. |
| Trait d’union des adjectifs | `back-end` / `front-end` (adjectif) ; `back end` / `front end` (nom). |
| Adverbes en `-ly` | Jamais de trait d’union : `a nicely formatted document`. |
| Connexion | `login` (nom ou adjectif), `log in` (verbe), `log in to` (et non `log into`). |
| Page d’accueil | Un seul mot : `homepage`. |
| Introduction de liste | N’utilisez les deux-points que si une phrase complète les précède. |
| Cohérence | Utilisez le même terme pour le même concept dans tout le document. |

**Directives pour les agents :**
- Faites une passe de grammaire finale selon les règles ci-dessus sur tout texte destiné à l’utilisateur avant de marquer une tâche terminée.
- À la première mention de l’éditeur, précisez `éditeur de blocs` ou `éditeur classique` ; utilisez ensuite `éditeur` seul.
- Reformulez les phrases ambiguës plutôt que de compter sur la seule virgule d’Oxford pour les désambiguïser.

---

### Guide de style et de mise en forme

**Quand consulter :** pour structurer une page de documentation, choisir les niveaux de titre, mettre en forme les références d’interface ou intégrer des exemples de code et des captures d’écran.

**Ressources clés**
- [Guide de style et de mise en forme des manuels et de HelpHub](https://make.wordpress.org/docs/handbook/documentation-team-handbook/handbooks-style-and-formatting-guide/)

| Règle | Norme |
|------|----------|
| Personne | Toujours la deuxième personne (`you`) ; jamais la première (`we`). |
| Ton | Familier mais compétent : un ami qui explique clairement. |
| Paragraphes | Un point majeur par paragraphe ; courts et faciles à parcourir. |
| Périmètre du sujet | Un sujet par article ; renvoyez vers les sujets connexes. |
| Hiérarchie des titres | `h2` à `h6` dans un ordre strictement séquentiel ; ne sautez jamais de niveau. |
| Gras | Pour les instructions importantes : **Allez dans Articles > Ajouter un article**. |
| Chemins de menu | Format `Parent > Enfant` (par exemple `Extensions > Ajouter une extension`). |
| Code en ligne | Entourez fonctions, crochets, classes et variables de balises `<code>` et liez à la référence de code. |
| Blocs de code | Utilisez les codes courts propres au langage (`[php]`, `[html]`, `[css]`, `[js]`). |
| Encadrés | Utilisez les codes courts `[info]`, `[tip]`, `[alert]`, `[warning]` selon le cas. |
| Images | Captures d’écran en taille réelle ; redimensionnez le navigateur pour ne capturer que la zone utile. |
| Accessibilité des images | Texte alternatif descriptif obligatoire ; ne vous reposez pas uniquement sur la capture. |
| Texte du lien | Descriptif ; jamais `here` ni `this` ; unique par page sauf même cible. |
| Abréviations | Développez à la première utilisation : `Système de gestion de contenu (CMS)`. |
| GIF | Évitez la lecture automatique ; préférez une vidéo avec lecture/pause, ou une miniature avec déclencheur. |
| Liens Codex | Remplacez-les par les équivalents `developer.wordpress.org`, sauf si la page n’a pas été migrée. |

**Directives pour les agents :**
- Définissez toujours les niveaux de titre dans l’ordre : un `h4` doit être précédé d’un `h3`.
- Pour les captures d’écran, redimensionnez le navigateur pour ne capturer que la zone utile ; n’utilisez jamais de miniatures à cliquer pour agrandir.
- Remplacez tout lien Codex par son équivalent DevHub avant publication.
- Tenez-vous à un sujet par document ou section ; renvoyez vers les sujets connexes au lieu de digresser.

---

### Guide de ton et de voix

**Quand consulter :** pour écrire une prose destinée à l’utilisateur, afin d’assurer le bon degré de formalité, d’inclusivité et de traductibilité.

**Ressources clés**
- [Guide de ton et de voix](https://make.wordpress.org/docs/handbook/documentation-team-handbook/tone-and-voice-guide/)

| Attribut | Ligne directrice |
|-----------|-----------|
| Voix | Amicale mais professionnelle ; cohérente dans tous les documents. |
| Contractions | Employez-les (`don't`, `you'll`) pour un ton accessible. |
| Personne | Toujours la deuxième personne (`you`). |
| Concision | Expliquez en aussi peu de mots que possible ; allez droit au but. |
| Voix active | Préférez les tournures actives ; évitez la voix passive. |
| Jargon | Évitez-le ; s’il est inévitable, définissez-le à la première utilisation. |
| Argot et abréviations | N’utilisez jamais d’argot internet ni d’abréviations web. |
| Points d’exclamation | Utilisez-les avec parcimonie ; un par document au maximum. |
| Références culturelles | Évitez les idiomes, les métaphores sportives et les références culturelles (le contenu est traduit dans le monde entier). |
| Ton de la documentation utilisateur | Amical, informel, clair, concis, précis ; expliquez les termes techniques sans condescendance. |
| Ton de la documentation développeur | Direct et précis ; supposez une connaissance technique ; limitez les tournures conversationnelles. |
| Références de code | Soyez aussi direct que possible ; évitez les introductions narratives du type « Parlons de… ». |

**Directives pour les agents :**
- Avant de finaliser une prose, vérifiez : est-elle amicale ? directe ? un locuteur non natif de l’anglais la comprendrait-il sans contexte culturel ?
- Pour la documentation développeur, commencez par le nom de la fonction ou du crochet et son rôle ; pas de préambule.
- Pour la documentation utilisateur, énoncez brièvement le contexte avant d’expliquer la fonctionnalité.
- Gardez des paragraphes courts et un vocabulaire cohérent pour faciliter la localisation.

---

### Politique de liens externes

**Quand consulter :** avant d’ajouter un lien hypertexte vers du contenu externe (hors WordPress.org) dans la documentation, les fichiers readme ou le texte d’aide.

**Ressources clés**
- [Politique de liens externes](https://make.wordpress.org/docs/handbook/documentation-team-handbook/external-linking-policy/)
- [Archives des discussions sur la politique](https://make.wordpress.org/docs/tag/external-linking-policy/)

| Règle | Détail |
|------|--------|
| Position par défaut | Tous les liens externes sont interdits, sauf ceux de la liste blanche préapprouvée. |
| Nouveaux liens | Aucun nouveau lien externe ne doit être ajouté pour le moment. |
| Sites préapprouvés | Le contenu de la page liée ne doit pas vendre ni promouvoir un produit ou un service. |
| Extensions, thèmes, hébergement | Ne promouvez ni ne recommandez jamais d’extension, de thème, de service ou d’hébergeur précis. |
| Périmètre de la documentation | HelpHub et DevHub ne documentent que ce qui est dans le cœur ou prévu pour le cœur. |
| Texte du lien | Descriptif ; jamais `click here`, `here` ni `this`. |

**Directives pour les agents :**
- Par défaut, ne liez que les domaines `wordpress.org`, `developer.wordpress.org`, `make.wordpress.org` et `w3.org`.
- Si un lien externe est réellement nécessaire (par exemple PHP.net, MDN), signalez-le pour une revue manuelle avec un commentaire `<!-- REVIEW: external link -->`.
- Ne liez jamais de pages commerciales, de tutoriels de blogs tiers ou de services hébergés.
- Aucune recommandation d’extension, de thème ou d’hébergement dans une sortie de documentation.

---

### Modèle de tutoriel

**Quand consulter :** pour écrire un tutoriel, un guide pratique ou des instructions étape par étape, dans la documentation, un readme ou l’aide en ligne.

**Ressources clés**
- [Modèle de tutoriel](https://make.wordpress.org/docs/handbook/documentation-team-handbook/tutorial-template/)

**Sections obligatoires :**

| Section | Contenu |
|---------|---------|
| **Aperçu** | Présentez ce que le lecteur va accomplir ; fournissez une liste ordonnée des résultats d’apprentissage. |
| **Ce qu’il vous faut** | Listez les prérequis : outils, connaissances, environnement. |
| **Étapes** (numérotées) | Une tâche claire par étape ; chaque étape a un titre : `Étape N – Titre de la tâche`. |
| **Pour aller plus loin** | Liste de ressources pour approfondir. |

**Règles de mise en forme des étapes :**

| Règle | Détail |
|------|--------|
| Granularité de la tâche | Chaque étape contient exactement une tâche claire. |
| Titres d’étape | Numérotés, concis, dans la balise de titre appropriée. |
| Ordre logique | Les étapes suivent un ordre strict ; ne revenez jamais en arrière. |
| Captures d’écran | Incluez-les quand c’est utile, avec un texte alternatif descriptif. |
| Exemples de code | Entourez-les du code court de coloration syntaxique correct (`[php]`, `[html]`, `[js]`). |
| Personne et concision | Deuxième personne ; aussi peu de mots que possible. |

**Directives pour les agents :**
- Incluez toujours Aperçu, Prérequis, Étapes numérotées et Pour aller plus loin dans un tutoriel.
- Numérotez les étapes dans l’ordre ; ne créez jamais de branche et ne revenez pas à une étape précédente.
- Chaque titre d’étape doit énoncer la tâche avec concision (par exemple `Étape 3 – Configurer la clé d’API`).
- Les captures d’écran doivent avoir un texte alternatif descriptif et ne doivent pas être le seul moyen de transmettre l’information.

---

### Manuel des API communes

**Quand consulter :** pour consulter la documentation canonique d’une API du cœur de WordPress ou déterminer l’URL correcte du manuel pour une référence d’API.

**Ressources clés**
- [Manuel des API communes](https://developer.wordpress.org/apis/)
- [Équipe documentation — Manuel des API communes](https://make.wordpress.org/docs/handbook/documentation-team-handbook/common-apis-handbook/)

Le manuel des API communes, à l’adresse `developer.wordpress.org/apis/`, est l’index central de toutes les API du cœur de WordPress. Il regroupe une documentation auparavant dispersée dans le Codex.

**API citées dans cette bibliothèque (URL de base canonique `https://developer.wordpress.org/apis/`) :**

| API | Suffixe de chemin |
|-----|-------------|
| Widgets du tableau de bord | `dashboard-widgets/` |
| Base de données (`$wpdb`) | `database/` |
| Système de fichiers | `filesystem/` |
| HTTP | `making-http-requests/` |
| Métadonnées | `metadata/` |
| Options | `options/` |
| API des extensions (crochets) | `hooks/` |
| Quicktags | `quicktags/` |
| Réécriture | `rewrite/` |
| Réglages | `settings/` |
| Shortcode | `shortcode/` |
| Transients | `transients/` |
| XML-RPC | `xml-rpc/` |

L’en-tête de fichier est documenté dans le manuel des extensions sous [Exigences d’en-tête](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/). L’API des widgets est documentée dans le manuel des thèmes sous [Widgets](https://developer.wordpress.org/themes/classic-themes/functionality/widgets/).

**Conventions transversales des API WordPress :**

| Schéma | Convention |
|---------|-----------|
| Retours d’erreur | La plupart des fonctions de récupération retournent `false`, `null` ou une valeur vide en cas d’échec, pas des exceptions. |
| Objets d’erreur | Les fonctions qui peuvent échouer de manière significative retournent des instances de `WP_Error`. |
| Nommage des crochets | Schéma `{object_type}_{action}` (par exemple `save_post`, `delete_term`, `update_option`). |
| Convention des filtres | Les filtres reçoivent la valeur à modifier comme premier paramètre et doivent la retourner. |
| Contrôle des capacités | Les fonctions d’API qui modifient l’état ne vérifient pas les capacités en interne ; l’appelant doit appeler `current_user_can()`. |
| Nettoyage | Les fonctions d’API ne nettoient généralement pas l’entrée ; l’appelant doit nettoyer avant de transmettre les données. |

**Directives pour les agents :**
- Liez toujours à `developer.wordpress.org/apis/` (et non au Codex) pour référencer une API du cœur.
- Pour toute fonction d’API WordPress, vérifiez son type de retour (`WP_Error` ou `false`) et traitez les échecs explicitement.
- Ne supposez jamais qu’une fonction d’API effectue des contrôles de capacité ou un nettoyage à votre place : ajoutez toujours les vôtres.

---

## Directives de documentation pour les agents

Cette section définit comment les agents doivent contribuer à cette bibliothèque au fil du temps. Le but est d’accumuler des connaissances réutilisables qui profitent à **tout** projet WordPress ; les notes propres à un projet vont dans `.agents/notes/`.

### Ce qu’il faut documenter ici

Quand un agent rencontre un élément qui mérite d’être conservé d’un projet à l’autre, ajoutez-le à la section concernée ci-dessus (ou créez une section si aucune ne convient). Exemples :

- **Schémas d’API WordPress** : comportement non évident, pièges non documentés, particularités propres à une version.
- **Constats sur les outils de build** : schémas de configuration `@wordpress/scripts`, conflits de règles ESLint/Stylelint, astuces de personnalisation Webpack.
- **Schémas de sécurité** : cas limites de nettoyage, stratégies d’échappement pour des contextes inhabituels, détails du cycle de vie des nonces.
- **Schémas de performance** : techniques d’optimisation des requêtes, stratégies de cache, schémas de chargement des ressources.
- **Schémas d’accessibilité** : schémas ARIA pour les composants de l’administration WP, constats de test avec lecteur d’écran, solutions de navigation au clavier.
- **Schémas de test** : particularités de configuration PHPUnit, sélecteurs Playwright pour l’administration WP, astuces de configuration d’environnement.
- **Schémas i18n** : échecs de chargement des traductions JavaScript, violations du linter PCP, résolution de chemin de `wp_set_script_translations()`.
- **Rédaction de documentation** : quand un agent produit du contenu destiné à l’utilisateur (readme.txt, texte d’aide en ligne, infobulles, étapes de tutoriel, notes de version), il doit suivre la section Normes de documentation WordPress de cette bibliothèque ; les règles de grammaire, de ton, de mise en forme et de liens s’appliquent.

### Ce qu’il ne faut pas documenter ici

- Décisions d’architecture propres à un projet → `.agents/notes/`
- Détails de mise en œuvre propres à une fonctionnalité → `.agents/notes/`
- Constats temporaires ou notes de débogage → `.agents/notes/`

### Format

Ajoutez les entrées sous la section existante la plus pertinente. Utilisez le format établi : une brève note « Quand consulter », des ressources clés (liens), des schémas de mise en œuvre (tableaux le cas échéant) et des directives pour les agents. Gardez les entrées concises et actionnables.

> Tout agent qui modifie ce fichier doit mettre à jour la date « Dernière mise à jour » en tête de fichier avec la date du jour (AAAA-MM-JJ). **Ne modifiez que la région choisie : tout ce fichier.** Ajouter ou retirer un lien **Ressources clés** ne change que ce fichier, et rien hors de ce dépôt ne le lit.

### Réflexes de mise à jour de la documentation

**Quand consulter :** avant de clore une tâche qui ajoute, retire ou modifie un comportement visible par l’utilisateur, des crochets, des points de terminaison REST, des options, des schémas ou des chaînes traduisibles.

**Réflexes pour tout projet WordPress :**

| Événement | Action |
|-------|--------|
| Nouvelle fonctionnalité, correction de bogue ou refactorisation avec un changement visible | Ajoutez une entrée de journal des modifications dans le `readme.txt` ou les notes de version du projet. |
| Nouveau PHPDoc sur une fonction, un crochet ou un gestionnaire REST public | Incluez `@since`, un `@param` typé, un `@return` typé et une description d’une phrase. |
| Chaîne traduisible ajoutée, retirée ou modifiée | Signalez la chaîne pour retraduction, puis régénérez le fichier POT avec `wp i18n make-pot`. |
| Nouveau point de terminaison REST ajouté | Documentez la route et ajoutez une entrée de journal des modifications. |
| Nouveau crochet public exposé | Ajoutez un PHPDoc sur le crochet, avec `@since` et un exemple, plus une entrée de journal des modifications. |
| Incrément de version | Alignez la version de l’en-tête de l’extension, la constante de version de l’extension et `Stable tag:`, puis promouvez le bloc de journal des modifications en entrée versionnée. |

**Règle stricte sur les chaînes traduisibles :** ne modifiez jamais en silence. Signalez le changement à un humain et ajoutez une entrée de journal des modifications.

**Règles propres au projet.** Les rôles de propriétaire Updatronix, `composer run make:pot`, `UPDATRONIX_VERSION` et la liste des fichiers figés se trouvent dans [`AGENTS.md`](../../AGENTS.md). Utilisez ce fichier pour ce projet ; cette bibliothèque reste générique.
