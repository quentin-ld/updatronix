# API développeur Updatronix

[English](en.md) · [Index de la documentation](../README.md) · [Documentation Updatronix](../updatronix/fr.md) · [Documentation Updatronix 3000](../updatronix-pro/fr.md)

| Champ | Valeur |
|---|---|
| Extension gratuite | 2.0.1 |
| Extension Pro | 0.8.1 |
| WordPress | 6.2 ou version ultérieure (vérifié sur 7.1.4) |
| PHP | 8.1 ou version ultérieure |
| Dernière vérification | 2026-11-07 |
| Statut | vérifié |

Traduit depuis la version anglaise vérifiée contre Free 2.0.1, Pro 0.8.1 et WordPress 7.1.4.

Ce guide s’adresse au code qui s’intègre à Updatronix : actions, filtres, routes REST, API PHP publique et modèle de données stocké. Il couvre l’extension gratuite et Updatronix 3000 (Pro).

La surface d’intégration prise en charge comprend les crochets, les filtres et les routes REST. Une classe PHP que ce guide ne liste pas dans l’API PHP publique est interne. N’appelez pas une méthode interne comme une API stable.

## Périmètre

- Les actions et filtres de l’édition gratuite, les actions et filtres de l’édition Pro, et les deux filtres que les deux extensions partagent.
- Les espaces de noms REST `updatronix/v1` et `updatronix-pro/v1`.
- Les symboles PHP stables : `updatronix_get_admin_tabs()` et `Updatronix_External_Log`.
- La table de journal, les options, les transients, la règle de conservation et la version du schéma qu’une intégration peut lire.
- Non couvert : l’interface d’administration, les classes internes, la file d’attente supprimée par l’édition Pro, l’e-mail de synthèse supprimé par l’édition Pro, et toute modification de code.

Le décompte est de 62 entrées de crochets Updatronix : neuf actions gratuites, 29 filtres propres à l’édition gratuite, 22 filtres propres à l’édition Pro et deux filtres partagés. Le crochet du cœur `wp_maybe_auto_update` est une note, pas une entrée ; le compter donne les 63 lignes que nomme le brief de la tâche.

## Actions

Les actions notifient votre code après un événement. La fonction de rappel reçoit les arguments indiqués dans la signature ci-dessous. Une action ne renvoie rien.

### Édition gratuite

#### `updatronix_admin_page_content`

- **Signature :** `do_action( 'updatronix_admin_page_content', string $active_tab )`
- **Paramètres :** `$active_tab` est le slug de l’onglet d’administration courant.
- **@since :** 1.1.1
- **Source :** `inc/admin/menu.php:169`
```php
add_action( 'updatronix_admin_page_content', function ( string $active_tab ) {
	if ( 'logs' === $active_tab ) {
		echo '<p>Your panel.</p>';
	}
} );
```

#### `updatronix_after_export_logs`

- **Signature :** `do_action( 'updatronix_after_export_logs', array $data )`
- **Paramètres :** `$data` contient `user_id`, `site_id`, `row_count`, `merged_count`, `truncated`, `truncation_reason`, `columns`, `merge`, `filters_fingerprint`, `generated_at`, `locale`, `cursor_count` et `version`.
- **@since :** 1.1.0
- **Source :** `inc/classes/class-updatronix-export.php:800`
```php
add_action( 'updatronix_after_export_logs', function ( array $data ) {
	error_log( 'Exported ' . (int) $data['row_count'] . ' rows.' );
} );
```

#### `updatronix_after_log`

- **Signature :** `do_action( 'updatronix_after_log', int $log_id, array $data )`
- **Paramètres :** `$log_id` est l’ID de la nouvelle ligne ; `$data` est l’instantané stocké. Les 12 clés d’origine sont toujours présentes ; les clés de décision et d’identité les suivent.
- **@since :** 1.0.0 ; 1.6.0 a ajouté les clés de décision et d’identité.
- **Source :** `inc/classes/class-updatronix-logger.php:563`
```php
add_action( 'updatronix_after_log', function ( int $log_id, array $data ) {
	// Reproduisez la ligne, notifiez un service externe ou mettez à jour un tableau de bord.
}, 10, 2 );
```

#### `updatronix_after_save_network_schedule`

- **Signature :** `do_action( 'updatronix_after_save_network_schedule' )`
- **Paramètres :** aucun.
- **@since :** 1.1.0
- **Source :** `inc/settings/options.php:549`
```php
add_action( 'updatronix_after_save_network_schedule', function () {
	// La récurrence, l’heure ou la fenêtre de délai de la planification a changé.
} );
```

#### `updatronix_after_save_settings`

- **Signature :** `do_action( 'updatronix_after_save_settings' )`
- **Paramètres :** aucun.
- **@since :** since not declared
- **Source :** `inc/settings/options.php:629`, `:645`
```php
add_action( 'updatronix_after_save_settings', function () {
	// Une écriture des réglages est terminée. Le sous-arbre de la planification a sa propre action.
} );
```

#### `updatronix_before_update_check`

- **Signature :** `do_action( 'updatronix_before_update_check' )`
- **Paramètres :** aucun.
- **@since :** since not declared
- **Source :** `inc/classes/class-updatronix-cron.php:391`
```php
add_action( 'updatronix_before_update_check', function () {
	// Préparez vos propres données de mise à jour avant que WordPress lance la vérification.
} );
```

#### `updatronix_enqueue_admin_assets`

- **Signature :** `do_action( 'updatronix_enqueue_admin_assets', string $admin_page )`
- **Paramètres :** `$admin_page` est le suffixe de hook de la page d’administration courante.
- **@since :** 1.1.1
- **Source :** `inc/admin/enqueue.php:374`
```php
add_action( 'updatronix_enqueue_admin_assets', function ( string $admin_page ) {
	wp_enqueue_script( 'my-updatronix-panel', plugin_dir_url( __FILE__ ) . 'panel.js', array( 'updatronix-scripts' ), '1.0.0', true );
} );
```

#### `updatronix_external_update_logged`

- **Signature :** `do_action( 'updatronix_external_update_logged', int $log_id, array $args )`
- **Paramètres :** `$log_id` est l’ID de la ligne stockée ; `$args` est la charge utile normalisée de la mise à jour externe.
- **@since :** 1.4.0
- **Source :** `inc/classes/class-updatronix-external-log.php:224`
```php
add_action( 'updatronix_external_update_logged', function ( int $log_id, array $args ) {
	// Notifiez un tableau de bord de déploiement que la mise à jour externe a été enregistrée.
}, 10, 2 );
```

#### `updatronix_log_updated`

- **Signature :** `do_action( 'updatronix_log_updated', int $log_id, array $payload )`
- **Paramètres :** `$log_id` est l’ID de la ligne ; `$payload` contient les colonnes mises à jour, avec `context` décodé.
- **@since :** 1.6.0
- **Source :** `inc/classes/class-updatronix-logger.php:879`
```php
add_action( 'updatronix_log_updated', function ( int $log_id, array $payload ) {
	// Actualisez votre miroir avec le contexte de décision enrichi.
}, 10, 2 );
```

### Édition Pro

L’édition Pro n’enregistre aucune action.

### Partagées

Les deux extensions ne partagent aucune action.

### Crochet du cœur déclenché par Pro

`wp_maybe_auto_update` est une action du cœur de WordPress. L’édition Pro la déclenche depuis `../updatronix-pro/inc/update-engine/class-updatronix-update-pass-runner.php:89`, et Safe mode l’écoute depuis `../updatronix-pro/inc/update-shield/class-updatronix-safe-mode.php:179`. Ce n’est pas une action Updatronix et elle n’a ni signature Updatronix ni `@since` Updatronix. Pour le contrat du cœur, consultez la référence développeur de WordPress, ou la [référence du système de mise à jour de WordPress](../wordpress-update-system/fr.md).

## Filtres

Les filtres reçoivent une valeur, peuvent la modifier et doivent la renvoyer. La forme de la valeur et le contrat de retour sont indiqués pour chaque entrée. Un filtre qui renvoie `null` là où l’extension attend un tableau est ignoré ou revient à la valeur d’origine ; renvoyez le même type.

### Édition gratuite

#### `updatronix_admin_brand`

- **Signature :** `apply_filters( 'updatronix_admin_brand', array $brand )`
- **Valeur :** `array{name: string, menu_label: string, logoUrl: string, color: string, menuColor: string, replace: bool}`
- **Retour :** le tableau de marque.
- **@since :** since not declared
- **Source :** `inc/admin/enqueue.php:385`
```php
add_filter( 'updatronix_admin_brand', function ( array $brand ) {
	$brand['name'] = 'My update service';
	$brand['replace'] = true;
	return $brand;
} );
```

#### `updatronix_admin_menu_label`

- **Signature :** `apply_filters( 'updatronix_admin_menu_label', string $label )`
- **Valeur :** le libellé du menu.
- **Retour :** la chaîne du libellé.
- **@since :** 1.0.0 ; le site d’appel de l’administration du réseau est 1.1.0.
- **Source :** `inc/admin/menu.php:59`, `:96`
```php
add_filter( 'updatronix_admin_menu_label', function ( string $label ) {
	return 'Update center';
} );
```

#### `updatronix_admin_tabs`

- **Signature :** `apply_filters( 'updatronix_admin_tabs', array $tabs )`
- **Valeur :** `array<string, array{slug: string, label: string, icon: string, priority?: int}>`
- **Retour :** la table des onglets ; les onglets sont triés par `priority` après filtrage.
- **@since :** 1.1.1
- **Source :** `inc/core/tabs.php:89`
```php
add_filter( 'updatronix_admin_tabs', function ( array $tabs ) {
	$tabs['my-tab'] = array( 'slug' => 'my-tab', 'label' => 'My tab', 'icon' => '', 'priority' => 50 );
	return $tabs;
} );
```

#### `updatronix_auto_update_can_toggle`

- **Signature :** `apply_filters( 'updatronix_auto_update_can_toggle', bool $can_toggle, string $type, string $id, bool $enable )`
- **Valeur :** `true` par défaut. `$type` vaut `plugin` ou `theme` ; `$id` est le fichier de l’extension ou la feuille de style du thème ; `$enable` est l’état demandé.
- **Retour :** `true` pour autoriser, `false` pour opposer un veto avec un 403, ou un `WP_Error` dont le message devient le message du 403.
- **@since :** since not declared
- **Source :** `inc/classes/class-updatronix-settings.php:1466`, `:1530`
```php
add_filter( 'updatronix_auto_update_can_toggle', function ( $can_toggle, string $type, string $id, bool $enable ) {
	if ( 'plugin' === $type && 'koko-analytics/koko-analytics.php' === $id ) {
		return false;
	}
	return $can_toggle;
}, 10, 4 );
```

#### `updatronix_auto_update_delay_seconds`

- **Signature :** `apply_filters( 'updatronix_auto_update_delay_seconds', int $delay_seconds, string $type, object $item )`
- **Valeur :** la fenêtre de temporisation en secondes issue des réglages de planification.
- **Retour :** le délai en secondes ; une valeur nulle ou négative autorise la mise à jour immédiatement.
- **@since :** 1.1.0
- **Source :** `inc/classes/class-updatronix-autoupdatedelay.php:343`
```php
add_filter( 'updatronix_auto_update_delay_seconds', function ( int $delay_seconds, string $type, object $item ) {
	return 3600; // Une heure pour chaque offre.
}, 10, 3 );
```

#### `updatronix_auto_update_plugin_data`

- **Signature :** `apply_filters( 'updatronix_auto_update_plugin_data', array $plugin_data, string $file )`
- **Valeur :** la ligne d’extension que le panneau des mises à jour automatiques affiche.
- **Retour :** le tableau de la ligne.
- **@since :** since not declared
- **Source :** `inc/classes/class-updatronix-autoupdates.php:342`
```php
add_filter( 'updatronix_auto_update_plugin_data', function ( array $plugin_data, string $file ) {
	$plugin_data['description'] .= ' Managed by our platform.';
	return $plugin_data;
}, 10, 2 );
```

#### `updatronix_auto_update_theme_data`

- **Signature :** `apply_filters( 'updatronix_auto_update_theme_data', array $theme_data, string $stylesheet )`
- **Valeur :** la ligne de thème que le panneau des mises à jour automatiques affiche.
- **Retour :** le tableau de la ligne.
- **@since :** since not declared
- **Source :** `inc/classes/class-updatronix-autoupdates.php:420`
```php
add_filter( 'updatronix_auto_update_theme_data', function ( array $theme_data, string $stylesheet ) {
	$theme_data['description'] .= ' Managed by our platform.';
	return $theme_data;
}, 10, 2 );
```

#### `updatronix_can_manage_pro`

- **Signature :** `apply_filters( 'updatronix_can_manage_pro', bool $can_manage_pro )`
- **Valeur :** `false` par défaut ; l’édition Pro renvoie son contrôle de capacité.
- **Retour :** si l’utilisateur courant peut gérer les écrans Pro.
- **@since :** since not declared
- **Source :** `inc/admin/enqueue.php:462`
```php
add_filter( 'updatronix_can_manage_pro', function ( bool $can_manage_pro ) {
	return $can_manage_pro || current_user_can( 'manage_options' );
} );
```

#### `updatronix_export_formats`

- **Signature :** `apply_filters( 'updatronix_export_formats', array $formats )`
- **Valeur :** les formats d’export enregistrés, indexés par slug.
- **Retour :** la table des formats. L’édition Pro ajoute `csv`, `json` et `xml` ; l’édition gratuite sert `txt`.
- **@since :** 1.3.0
- **Source :** `inc/settings/options.php:846`
```php
add_filter( 'updatronix_export_formats', function ( array $formats ) {
	$formats['jsonl'] = 'JSON Lines';
	return $formats;
} );
```

#### `updatronix_export_max_rows`

- **Signature :** `apply_filters( 'updatronix_export_max_rows', int $max_rows )`
- **Valeur :** le plafond total de lignes pour un export.
- **Retour :** le plafond sous forme d’entier.
- **@since :** since not declared
- **Source :** `inc/classes/class-updatronix-export.php:346`
```php
add_filter( 'updatronix_export_max_rows', function ( int $max_rows ) {
	return 20000;
} );
```

#### `updatronix_export_rate_limit_bypass_cap`

- **Signature :** `apply_filters( 'updatronix_export_rate_limit_bypass_cap', string $capability, int $user_id, int $site_id )`
- **Valeur :** une chaîne vide, qui désactive le contournement.
- **Retour :** un slug de capacité ; un utilisateur qui la détient ignore le limiteur d’export.
- **@since :** 1.1.0
- **Source :** `inc/classes/class-updatronix-export-rate-limiter.php:140`
```php
add_filter( 'updatronix_export_rate_limit_bypass_cap', function ( string $capability, int $user_id, int $site_id ) {
	return 'export_updatronix_logs';
}, 10, 3 );
```

#### `updatronix_export_rate_limit_per_hour`

- **Signature :** `apply_filters( 'updatronix_export_rate_limit_per_hour', int $limit, int $user_id, int $site_id )`
- **Valeur :** la limite horaire par défaut.
- **Retour :** la limite, bornée à `[1, 100000]`.
- **@since :** 1.1.0
- **Source :** `inc/classes/class-updatronix-export-rate-limiter.php:85`
```php
add_filter( 'updatronix_export_rate_limit_per_hour', function ( int $limit, int $user_id, int $site_id ) {
	return 500;
}, 10, 3 );
```

#### `updatronix_export_rate_limit_per_minute`

- **Signature :** `apply_filters( 'updatronix_export_rate_limit_per_minute', int $limit, int $user_id, int $site_id )`
- **Valeur :** la limite par minute par défaut.
- **Retour :** la limite, bornée à `[1, 100000]`.
- **@since :** 1.1.0
- **Source :** `inc/classes/class-updatronix-export-rate-limiter.php:63`
```php
add_filter( 'updatronix_export_rate_limit_per_minute', function ( int $limit, int $user_id, int $site_id ) {
	return 20;
}, 10, 3 );
```

#### `updatronix_external_update_sources`

- **Signature :** `apply_filters( 'updatronix_external_update_sources', array $sources )`
- **Valeur :** `ftp`, `sftp`, `ssh`, `cli`, `custom`.
- **Retour :** les clés de source acceptées.
- **@since :** 1.4.0
- **Source :** `inc/classes/class-updatronix-external-log.php:50`
```php
add_filter( 'updatronix_external_update_sources', function ( array $sources ) {
	$sources[] = 'deploy';
	return $sources;
} );
```

#### `updatronix_filter_should_delay_offer`

- **Signature :** `apply_filters( 'updatronix_filter_should_delay_offer', bool $should_delay, string $type, object $item, int $first_seen, int $delay_seconds )`
- **Valeur :** la décision de délai calculée.
- **Retour :** `true` pour garder l’offre dans la fenêtre de temporisation, `false` pour la libérer.
- **@since :** 1.1.0
- **Source :** `inc/classes/class-updatronix-autoupdatedelay.php:386`
```php
add_filter( 'updatronix_filter_should_delay_offer', function ( bool $should_delay, string $type, object $item, int $first_seen, int $delay_seconds ) {
	return false; // Libérez cette offre maintenant.
}, 10, 5 );
```

#### `updatronix_health_checks`

- **Signature :** `apply_filters( 'updatronix_health_checks', array $checks, array $failed )`
- **Valeur :** les lignes de contrôle de Santé ; `$failed` contient les lignes de journal en échec dans la fenêtre de conservation.
- **Retour :** les lignes de contrôle. Chaque ligne a `id`, `label`, `value`, `status` (`good`, `attention`, `critical`), `intent`, `detail`, et éventuellement `href` et `link_label`.
- **@since :** 1.5.0
- **Source :** `inc/classes/class-updatronix-health.php:323`
```php
add_filter( 'updatronix_health_checks', function ( array $checks, array $failed ) {
	$checks[] = array(
		'id'     => 'my-check',
		'label'  => 'My check',
		'value'  => 'OK',
		'status' => 'good',
		'intent' => 'inform',
		'detail' => 'Checked by the integration.',
	);
	return $checks;
}, 10, 2 );
```

#### `updatronix_license_active`

- **Signature :** `apply_filters( 'updatronix_license_active', bool $active )`
- **Valeur :** `false` par défaut ; l’édition Pro renvoie son état de licence.
- **Retour :** si la licence est active.
- **@since :** since not declared
- **Source :** `inc/admin/enqueue.php:458`
```php
add_filter( 'updatronix_license_active', function ( bool $active ) {
	return $active;
} );
```

#### `updatronix_log_action_types`

- **Signature :** `apply_filters( 'updatronix_log_action_types', array $types )`
- **Valeur :** la liste d’autorisation des types d’action.
- **Retour :** la liste d’autorisation. Ajout uniquement : conservez les types existants et ajoutez les vôtres.
- **@since :** 1.4.0
- **Source :** `inc/classes/class-updatronix-security.php:164`
```php
add_filter( 'updatronix_log_action_types', function ( array $types ) {
	$types[] = 'deploy';
	return $types;
} );
```

#### `updatronix_log_context`

- **Signature :** `apply_filters( 'updatronix_log_context', array $event, string $log_type, string $item_slug, string $target_version )`
- **Valeur :** l’événement du journal de décision avant nettoyage.
- **Retour :** le tableau de l’événement. Le journaliseur natif le stocke avant l’insertion.
- **@since :** 1.6.0
- **Source :** `inc/classes/class-updatronix-logger.php:303`
```php
add_filter( 'updatronix_log_context', function ( array $event, string $log_type, string $item_slug, string $target_version ) {
	$event['context']['deployment'] = 'blue';
	return $event;
}, 10, 4 );
```

#### `updatronix_log_context_keys`

- **Signature :** `apply_filters( 'updatronix_log_context_keys', array $keys )`
- **Valeur :** la liste d’autorisation du contexte expurgé.
- **Retour :** la liste d’autorisation. Ajoutez une clé avant de la stocker dans `context`.
- **@since :** 1.6.0
- **Source :** `inc/classes/class-updatronix-security.php:581`
```php
add_filter( 'updatronix_log_context_keys', function ( array $keys ) {
	$keys[] = 'deployment';
	return $keys;
} );
```

#### `updatronix_log_decision_layer_enabled`

- **Signature :** `apply_filters( 'updatronix_log_decision_layer_enabled', bool $enabled )`
- **Valeur :** si le composant d’écriture de la couche de décision est actif.
- **Retour :** le booléen.
- **@since :** 1.6.0
- **Source :** `inc/classes/class-updatronix-logger.php:233`
```php
add_filter( 'updatronix_log_decision_layer_enabled', function ( bool $enabled ) {
	return true;
} );
```

#### `updatronix_log_decision_states`

- **Signature :** `apply_filters( 'updatronix_log_decision_states', array $states )`
- **Valeur :** la liste d’autorisation des états de décision.
- **Retour :** la liste d’autorisation.
- **@since :** 1.6.0
- **Source :** `inc/classes/class-updatronix-security.php:322`
```php
add_filter( 'updatronix_log_decision_states', function ( array $states ) {
	$states[] = 'deferred';
	return $states;
} );
```

#### `updatronix_log_origins`

- **Signature :** `apply_filters( 'updatronix_log_origins', array $origins )`
- **Valeur :** la liste d’autorisation des origines.
- **Retour :** la liste d’autorisation.
- **@since :** 1.6.0
- **Source :** `inc/classes/class-updatronix-security.php:345`
```php
add_filter( 'updatronix_log_origins', function ( array $origins ) {
	$origins[] = 'deploy';
	return $origins;
} );
```

#### `updatronix_log_query_args`

- **Signature :** `apply_filters( 'updatronix_log_query_args', array $args )`
- **Valeur :** les arguments de requête du journal.
- **Retour :** les arguments. Ajout uniquement : vous pouvez ajouter `action_type`, `action_types`, `created_at_after` ou `created_at_before`.
- **@since :** 1.4.0 ; le second site d’appel à `:1079` n’a pas de balise locale.
- **Source :** `inc/classes/class-updatronix-logger.php:1009`, `:1079`
```php
add_filter( 'updatronix_log_query_args', function ( array $args ) {
	$args['created_at_after'] = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
	return $args;
} );
```

#### `updatronix_log_reason_codes`

- **Signature :** `apply_filters( 'updatronix_log_reason_codes', array $codes )`
- **Valeur :** la liste d’autorisation canonique des codes de raison.
- **Retour :** la liste d’autorisation. Ajout uniquement.
- **@since :** 1.6.0
- **Source :** `inc/classes/class-updatronix-security.php:302`
```php
add_filter( 'updatronix_log_reason_codes', function ( array $codes ) {
	$codes[] = 'deployment_policy';
	return $codes;
} );
```

#### `updatronix_log_stages`

- **Signature :** `apply_filters( 'updatronix_log_stages', array $stages )`
- **Valeur :** la liste d’autorisation des étapes de décision.
- **Retour :** la liste d’autorisation.
- **@since :** 1.6.0
- **Source :** `inc/classes/class-updatronix-security.php:368`
```php
add_filter( 'updatronix_log_stages', function ( array $stages ) {
	$stages[] = 'deploy';
	return $stages;
} );
```

#### `updatronix_native_update_time_enabled`

- **Signature :** `apply_filters( 'updatronix_native_update_time_enabled', bool $enabled )`
- **Valeur :** `true` par défaut.
- **Retour :** si Updatronix remplace le texte natif de l’heure de mise à jour.
- **@since :** 1.3.0
- **Source :** `inc/admin/native-update-time.php:35`
```php
add_filter( 'updatronix_native_update_time_enabled', function ( bool $enabled ) {
	return false;
} );
```

#### `updatronix_schedule_cron_disabled`

- **Signature :** `apply_filters( 'updatronix_schedule_cron_disabled', bool $disabled )`
- **Valeur :** si `DISABLE_WP_CRON` vaut vrai.
- **Retour :** si la planification considère le lancement de WP-Cron comme désactivé.
- **@since :** 1.3.0
- **Source :** `inc/classes/class-updatronix-schedule-context.php:213`
```php
add_filter( 'updatronix_schedule_cron_disabled', function ( bool $disabled ) {
	return $disabled;
} );
```

#### `updatronix_skip_auto_update_delay`

- **Signature :** `apply_filters( 'updatronix_skip_auto_update_delay', bool $skip, string $type, object $item )`
- **Valeur :** `false` par défaut.
- **Retour :** `true` pour libérer cette offre immédiatement.
- **@since :** since not declared
- **Source :** `inc/classes/class-updatronix-autoupdatedelay.php:334`
```php
add_filter( 'updatronix_skip_auto_update_delay', function ( bool $skip, string $type, object $item ) {
	return $skip;
}, 10, 3 );
```

### Édition Pro

#### `updatronix_email_brand_name`

- **Signature :** `apply_filters( 'updatronix_email_brand_name', string $name )`
- **Valeur :** le nom du site.
- **Retour :** le nom de marque utilisé dans les e-mails en texte brut.
- **@since :** 0.1.0
- **Source :** `../updatronix-pro/inc/update-shield/class-updatronix-safe-mode.php:883`, `:1973`
```php
add_filter( 'updatronix_email_brand_name', function ( string $name ) {
	return 'My update service';
} );
```

#### `updatronix_pro_abandoned_data`

- **Signature :** `apply_filters( 'updatronix_pro_abandoned_data', mixed $data, string $type, string $id )`
- **Valeur :** `null`, qui utilise la recherche normale sur WordPress.org.
- **Retour :** `null` ou un tableau qui doit contenir `last_updated`.
- **@since :** 0.9.0
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-abandoned-detector.php:319`
```php
add_filter( 'updatronix_pro_abandoned_data', function ( $data, string $type, string $id ) {
	if ( 'plugin' === $type && 'my-plugin/my-plugin.php' === $id ) {
		return array( 'last_updated' => '2024-01-01' );
	}
	return $data;
}, 10, 3 );
```

#### `updatronix_pro_abandoned_threshold_days`

- **Signature :** `apply_filters( 'updatronix_pro_abandoned_threshold_days', int $days )`
- **Valeur :** le seuil par défaut, 365 jours.
- **Retour :** le seuil en jours.
- **@since :** 0.9.0
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-abandoned-detector.php:74`
```php
add_filter( 'updatronix_pro_abandoned_threshold_days', function ( int $days ) {
	return 180;
} );
```

#### `updatronix_pro_blocked_history_query`

- **Signature :** `apply_filters( 'updatronix_pro_blocked_history_query', array $query )`
- **Valeur :** la requête de l’historique des blocages avant qu’elle n’atteigne le journaliseur de l’édition gratuite.
- **Retour :** le tableau de la requête.
- **@since :** 0.9.0
- **Source :** `../updatronix-pro/inc/update-shield/class-updatronix-update-shield.php:291`
```php
add_filter( 'updatronix_pro_blocked_history_query', function ( array $query ) {
	$query['per_page'] = 20;
	return $query;
} );
```

#### `updatronix_pro_composer_project_roots`

- **Signature :** `apply_filters( 'updatronix_pro_composer_project_roots', array $roots )`
- **Valeur :** les racines de projet Composer candidates.
- **Retour :** les chemins des racines.
- **@since :** 0.13.0
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-version-control-detector.php:1199`
```php
add_filter( 'updatronix_pro_composer_project_roots', function ( array $roots ) {
	$roots[] = '/srv/my-project';
	return $roots;
} );
```

#### `updatronix_pro_flow_max_chains`

- **Signature :** `apply_filters( 'updatronix_pro_flow_max_chains', int $max_chains )`
- **Valeur :** le nombre maximal de chaînes par défaut.
- **Retour :** le maximum, sous forme d’entier.
- **@since :** 0.10.0 ; le second site d’appel à `class-updatronix-update-flow.php:461` n’a pas de balise locale.
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-flow-planner.php:119`, `../updatronix-pro/inc/update-flow/class-updatronix-update-flow.php:461`
```php
add_filter( 'updatronix_pro_flow_max_chains', function ( int $max_chains ) {
	return 20;
} );
```

#### `updatronix_pro_flow_max_steps`

- **Signature :** `apply_filters( 'updatronix_pro_flow_max_steps', int $max_steps )`
- **Valeur :** le nombre maximal d’étapes par défaut, 50.
- **Retour :** le maximum, sous forme d’entier.
- **@since :** 0.10.0 ; le second site d’appel à `class-updatronix-update-flow.php:462` n’a pas de balise locale.
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-flow-planner.php:120`, `../updatronix-pro/inc/update-flow/class-updatronix-update-flow.php:462`
```php
add_filter( 'updatronix_pro_flow_max_steps', function ( int $max_steps ) {
	return 100;
} );
```

#### `updatronix_pro_is_version_controlled`

- **Signature :** `apply_filters( 'updatronix_pro_is_version_controlled', bool $is_controlled, string $type, string $id, string $path )`
- **Valeur :** le résultat du détecteur.
- **Retour :** si l’élément est sous contrôle de version.
- **@since :** 0.9.0
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-version-control-detector.php:1727`
```php
add_filter( 'updatronix_pro_is_version_controlled', function ( bool $is_controlled, string $type, string $id, string $path ) {
	return $is_controlled;
}, 10, 4 );
```

#### `updatronix_pro_license_api_url`

- **Signature :** `apply_filters( 'updatronix_pro_license_api_url', string $url )`
- **Valeur :** le point de terminaison de l’API de licence.
- **Retour :** l’URL du point de terminaison.
- **@since :** 0.2.0
- **Source :** `../updatronix-pro/inc/core/license-options.php:41`
```php
add_filter( 'updatronix_pro_license_api_url', function ( string $url ) {
	return 'https://license.example.com/api';
} );
```

#### `updatronix_pro_native_vcs_cross_check`

- **Signature :** `apply_filters( 'updatronix_pro_native_vcs_cross_check', ?string $result, string $context )`
- **Valeur :** `null` pour utiliser WordPress.
- **Retour :** `yes`, `no`, `missing` ou `null`.
- **@since :** 0.13.0
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-version-control-detector.php:938`
```php
add_filter( 'updatronix_pro_native_vcs_cross_check', function ( ?string $result, string $context ) {
	return null;
}, 10, 2 );
```

#### `updatronix_pro_open_basedir_roots`

- **Signature :** `apply_filters( 'updatronix_pro_open_basedir_roots', ?array $roots )`
- **Valeur :** `null` pour utiliser `ini_get()`.
- **Retour :** `null` ou un tableau de chemins de racines.
- **@since :** 0.13.0
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-version-control-detector.php:274`
```php
add_filter( 'updatronix_pro_open_basedir_roots', function ( ?array $roots ) {
	return array( '/srv/www' );
} );
```

#### `updatronix_pro_shield_defaults`

- **Signature :** `apply_filters( 'updatronix_pro_shield_defaults', array $defaults )`
- **Valeur :** les valeurs par défaut d’Update Shield.
- **Retour :** le tableau des valeurs par défaut.
- **@since :** 0.1.0
- **Source :** `../updatronix-pro/inc/settings/options.php:51`
```php
add_filter( 'updatronix_pro_shield_defaults', function ( array $defaults ) {
	$defaults['safe_mode'] = true;
	return $defaults;
} );
```

#### `updatronix_pro_shield_state_item_limit`

- **Signature :** `apply_filters( 'updatronix_pro_shield_state_item_limit', int $limit )`
- **Valeur :** le nombre maximal d’éléments dans l’état d’Update Shield.
- **Retour :** la limite.
- **@since :** 0.11.0
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-shield-state.php:68`
```php
add_filter( 'updatronix_pro_shield_state_item_limit', function ( int $limit ) {
	return 500;
} );
```

#### `updatronix_pro_update_cache_ttl`

- **Signature :** `apply_filters( 'updatronix_pro_update_cache_ttl', int $ttl )`
- **Valeur :** la durée de vie du cache de découverte des mises à jour, en secondes ; la valeur par défaut est de 12 heures.
- **Retour :** la durée de vie.
- **@since :** 0.5.0
- **Source :** `../updatronix-pro/inc/license/class-updatronix-pro-license-updater.php:276`
```php
add_filter( 'updatronix_pro_update_cache_ttl', function ( int $ttl ) {
	return HOUR_IN_SECONDS;
} );
```

#### `updatronix_pro_update_engine_enabled`

- **Signature :** `apply_filters( 'updatronix_pro_update_engine_enabled', bool $enabled )`
- **Valeur :** `true` par défaut.
- **Retour :** l’interrupteur d’arrêt du moteur de mise à jour.
- **@since :** 0.8.0
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-update-engine-bootstrap.php:61`
```php
add_filter( 'updatronix_pro_update_engine_enabled', function ( bool $enabled ) {
	return $enabled;
} );
```

#### `updatronix_pro_update_package`

- **Signature :** `apply_filters( 'updatronix_pro_update_package', string $package, mixed $data, string $basename )`
- **Valeur :** l’URL du paquet issue de l’API de licence ; elle peut être vide.
- **Retour :** l’URL du paquet à utiliser.
- **@since :** 0.2.0
- **Source :** `../updatronix-pro/inc/license/class-updatronix-pro-license-updater.php:383`
```php
add_filter( 'updatronix_pro_update_package', function ( string $package, $data, string $basename ) {
	return $package;
}, 10, 3 );
```

#### `updatronix_pro_update_pass_enabled`

- **Signature :** `apply_filters( 'updatronix_pro_update_pass_enabled', bool $enabled )`
- **Valeur :** l’interrupteur stocké, avec une constante fausse comme arrêt forcé.
- **Retour :** si la passe est activée. Le filtre peut remplacer la valeur stockée dans un sens ou dans l’autre.
- **@since :** 0.8.0 (journal des modifications `../updatronix-pro/readme.txt:111`) ; le site d’appel à `class-updatronix-update-pass.php:1204` n’a pas de balise locale.
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-update-pass.php:1204`
- **Note :** l’option de réseau du même nom est documentée dans le modèle de données.
```php
add_filter( 'updatronix_pro_update_pass_enabled', function ( bool $enabled ) {
	return $enabled;
} );
```

#### `updatronix_pro_update_pass_executing`

- **Signature :** `apply_filters( 'updatronix_pro_update_pass_executing', bool $executing )`
- **Valeur :** `false` par défaut.
- **Retour :** si la passe du moteur ou son exécuteur est en train d’écrire.
- **@since :** 0.13.0
- **Source :** `../updatronix-pro/inc/update-shield/class-updatronix-version-lock-guard.php:214`
```php
add_filter( 'updatronix_pro_update_pass_executing', function ( bool $executing ) {
	return $executing;
} );
```

#### `updatronix_pro_update_pass_flow_order`

- **Signature :** `apply_filters( 'updatronix_pro_update_pass_flow_order', bool $enabled )`
- **Valeur :** `true` par défaut.
- **Retour :** si la passe consomme le plan d’Update Flow.
- **@since :** 0.13.0
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-update-pass-order.php:580`
```php
add_filter( 'updatronix_pro_update_pass_flow_order', function ( bool $enabled ) {
	return $enabled;
} );
```

#### `updatronix_pro_update_pass_max_attempts`

- **Signature :** `apply_filters( 'updatronix_pro_update_pass_max_attempts', int $attempts )`
- **Valeur :** le plafond de tentatives par nœud.
- **Retour :** le plafond, borné à `[1, MAX_ATTEMPTS]`.
- **@since :** since not declared
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-update-pass.php:1217`
```php
add_filter( 'updatronix_pro_update_pass_max_attempts', function ( int $attempts ) {
	return 3;
} );
```

#### `updatronix_pro_update_pass_order`

- **Signature :** `apply_filters( 'updatronix_pro_update_pass_order', array $order, array $nodes )`
- **Valeur :** les clés ordonnées.
- **Retour :** l’ordre filtré. Les clés absentes de `$nodes` sont supprimées.
- **@since :** 0.13.0
- **Source :** `../updatronix-pro/inc/update-engine/class-updatronix-update-pass-order.php:154`
```php
add_filter( 'updatronix_pro_update_pass_order', function ( array $order, array $nodes ) {
	return $order;
}, 10, 2 );
```

#### `updatronix_pro_version_lock_update_context`

- **Signature :** `apply_filters( 'updatronix_pro_version_lock_update_context', string $context, string $action )`
- **Valeur :** `'bulk'` ou `'single'`.
- **Retour :** le contexte de mise à jour manuelle.
- **@since :** 0.13.0
- **Source :** `../updatronix-pro/inc/update-shield/class-updatronix-version-lock-guard.php:240`
```php
add_filter( 'updatronix_pro_version_lock_update_context', function ( string $context, string $action ) {
	return $context;
}, 10, 2 );
```

### Partagés

#### `updatronix_schedule_item_state`

- **Signature :** `apply_filters( 'updatronix_schedule_item_state', array $facts, string $type, string $id )`
- **Valeur :** `lock`, `policy`, `flow` et `queue`, chacun avec un `state`.
- **Retour :** le tableau des faits. L’édition Pro possède l’unique consommateur ; il ne doit pas écrire.
- **@since :** 1.3.0 ; le site d’appel Pro à `class-updatronix-flow-planner.php:600` n’a pas de balise locale.
- **Source :** édition gratuite `inc/classes/class-updatronix-schedule-context.php:293` ; Pro `../updatronix-pro/inc/update-engine/class-updatronix-flow-planner.php:600`
```php
add_filter( 'updatronix_schedule_item_state', function ( array $facts, string $type, string $id ) {
	$facts['flow']['state'] = 'ready';
	return $facts;
}, 10, 3 );
```

#### `updatronix_schedule_offer_ttl`

- **Signature :** `apply_filters( 'updatronix_schedule_offer_ttl', int $ttl )`
- **Valeur :** la durée de vie de la fraîcheur de l’offre, six heures par défaut.
- **Retour :** la durée de vie en secondes.
- **@since :** 1.3.0 ; le site d’appel Pro à `class-updatronix-flow-readiness.php:752` n’a pas de balise locale.
- **Source :** édition gratuite `inc/classes/class-updatronix-item-state.php:247` ; Pro `../updatronix-pro/inc/update-flow/class-updatronix-flow-readiness.php:752`
```php
add_filter( 'updatronix_schedule_offer_ttl', function ( int $ttl ) {
	return 2 * HOUR_IN_SECONDS;
} );
```

## API REST

Les deux espaces de noms exigent un utilisateur authentifié disposant de la capacité nommée sur la route. Une requête HTTP authentifiée par cookie envoie le nonce `wp_rest` dans l’en-tête `X-WP-Nonce` ; `apiFetch` l’ajoute. Les exemples utilisent `wp_set_current_user( 6 )` et `rest_do_request()`, qui définissent directement l’utilisateur.

Les routes de l’édition gratuite exigent `manage_updatronix` ; sur multisite, elles exigent aussi un Super administrateur (`inc/classes/class-updatronix-security.php:920-926`). Les routes de l’édition Pro exigent `manage_updatronix_pro` et, sur multisite, un Super administrateur (`../updatronix-pro/inc/core/constants.php:33-34`). Update Shield, Update Flow et White label exigent aussi une licence active. Les routes de licence non, afin qu’une clé puisse être activée.

Les routes en lecture seule ont été vérifiées par un test rapide en HTTP 200. Les routes d’écriture ont été testées pour leur contrat de permission avec un utilisateur anonyme : 401, ou 400 à la validation des arguments pour `/license/activate`. Les exemples testés illustrent les écritures réussies. Les commandes et la sortie exactes se trouvent dans `.agents/tasks/2026-11-07-docs/examples-developer-api-en.md`.

### Édition gratuite : journaux

#### `GET /logs`

- **Méthodes :** GET.
- **Fonction de rappel :** `Updatronix_Settings::rest_get_logs()` (`inc/classes/class-updatronix-settings.php:482-572`).
- **Permission :** `rest_can_manage_logs` ; capacité `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** `per_page` entier, 50 par défaut, 1–200 ; `page` entier, 1 par défaut, minimum 1 ; `log_type` énumération `''`, `core`, `plugin`, `theme`, `translation`, `system` ; `performed_as` énumération `''`, `manual`, `automatic`, `upload` ; `status` énumération `''`, `success`, `error`, `cancelled`, `delayed`, `info`, `warning` ; `action_type`, `reason_code`, `decision_state`, `origin`, `pass_id`, `chain_id` chaînes ; `user` entier, minimum 0 ; `created_at_after`, `created_at_before`, `next_eligible_at_after`, `next_eligible_at_before` chaînes date-heure ; `retained`, `network` entiers, 0 ou 1 ; `filters` chaîne ou tableau ; `site_id` entier, `null` par défaut ; `search` chaîne, `''` par défaut, 200 maximum.
- **Réponse 200 :** `{ "logs": [ <log objects> ], "total": <int> }`. Un objet de journal est la ligne stockée plus les champs d’affichage de `enrich_log_for_display()` (`:914-983`).
- **Erreurs :** 401/403 depuis la fonction de rappel de permission ; 400 pour un argument invalide.
- **Contraintes :** `site_id` restreint la lecture ; la valeur par défaut est la portée globale du réseau pour un Super administrateur (`:1732-1754`).
- **Testé :** exemple 2 et le test rapide, HTTP 200, `total=17`, cinq lignes.
```php
$request = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
$request->set_query_params( array( 'per_page' => 5 ) );
$response = rest_do_request( $request );
```

#### `GET|DELETE /logs/{id}`

- **Méthodes :** GET, DELETE.
- **Fonction de rappel :** `rest_get_log()` (`:580-606`), `rest_delete_log()` (`:1190-1224`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** `id` entier, obligatoire, minimum 1.
- **Réponse 200 :** GET `{ "log": <enriched log> }` ; DELETE `{ "deleted": true }`.
- **Erreurs :** 404 `{ "message": "The requested log entry could not be found." }` ; DELETE 500 `{ "message": "The log entry could not be deleted..." }`.
- **Contraintes :** même portée de site que `/logs`. Ne supprimez qu’une ligne de démonstration.
- **Testé :** test rapide GET HTTP 200 ; contrat de permission DELETE 401 avec un utilisateur anonyme. Les exemples 1 et 3 suppriment leurs lignes de démonstration via le journaliseur, pas via la route.
```php
$request = new WP_REST_Request( 'GET', '/updatronix/v1/logs/17' );
$response = rest_do_request( $request );

$delete = new WP_REST_Request( 'DELETE', '/updatronix/v1/logs/17' );
$deleted = rest_do_request( $delete );
```

#### `POST /logs/cleanup`

- **Méthodes :** POST.
- **Fonction de rappel :** `rest_cleanup_logs()` (`:1232-1239`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** aucun.
- **Réponse 200 :** `{ "deleted": <int> }`.
- **Erreurs :** 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** supprime les lignes plus anciennes que `retention_days`. Ne l’exécutez pas sur des données de démonstration sans seuil sûr.
- **Testé :** contrat de permission 401 avec un utilisateur anonyme ; l’écriture destructive n’a pas été exécutée.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/logs/cleanup' );
$response = rest_do_request( $request );
```

#### `DELETE /logs/all`

- **Méthodes :** DELETE.
- **Fonction de rappel :** `rest_delete_all_logs()` (`:1247-1251`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** aucun.
- **Réponse 200 :** `{ "deleted": <int> }`.
- **Erreurs :** 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** destructive ; elle supprime chaque ligne de journal dans la portée. Exécutez-la uniquement sur un journal jetable.
- **Testé :** contrat de permission 401 avec un utilisateur anonyme ; l’écriture destructive n’a pas été exécutée.
```php
$request = new WP_REST_Request( 'DELETE', '/updatronix/v1/logs/all' );
$response = rest_do_request( $request );
```

### Édition gratuite : réglages

#### `GET|POST|PUT|PATCH /settings`

- **Méthodes :** GET, POST, PUT, PATCH.
- **Fonction de rappel :** `rest_get_settings()` (`:452-463`), `rest_update_settings()` (`:1259-1344`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** `logging_enabled` booléen ; `delete_data_on_uninstall` booléen ; `retention_days` entier 1–365 ; `notifications_mode` énumération `default`, `disabled` ; `notify_enabled` booléen ; `notify_emails` chaîne, `UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES` maximum ; `notify_on` tableau de `core`, `plugin_theme`, `debug`, `technical` ; `schedule` objet avec `update_check.recurrence` énumération `''`, `hourly`, `twicedaily`, `daily`, `weekly`, `update_check.time` motif `^(([01][0-9]|2[0-3]):[0-5][0-9])?$`, `delay_updates.enabled` booléen, `delay_updates.delay_value` entier 0–365.
- **Réponse 200 :** GET `{ "options": <array>, "schedule_meta": <array>, "log_taxonomy": <array> }` ; écriture `{ "options": <array>, "schedule_meta": <array> }`.
- **Erreurs :** 403 `{ "code": "updatronix_forbidden", "message": "...", "data": { "status": 403 } }` lorsque `delete_data_on_uninstall` est activé sans `manage_options`.
- **Contraintes :** `delete_data_on_uninstall` exige en plus `manage_options`. La fusion de la planification est partielle.
- **Testé :** test rapide GET HTTP 200 ; contrat de permission d’écriture 401 avec un utilisateur anonyme.
```php
$read = new WP_REST_Request( 'GET', '/updatronix/v1/settings' );
$data = rest_do_request( $read )->get_data();

$write = new WP_REST_Request( 'POST', '/updatronix/v1/settings' );
$write->set_body_params( array( 'retention_days' => 90 ) );
$updated = rest_do_request( $write );
```

### Édition gratuite : mises à jour automatiques

#### `GET /auto-updates`

- **Méthodes :** GET.
- **Fonction de rappel :** `rest_get_auto_updates()` (`:1365-1367`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** aucun.
- **Réponse 200 :** `Updatronix_AutoUpdates::get_data()` : `{ "constants": <array>, "dismissed_constants": <array>, "core": <array>, "plugins": <array>, "themes": <array>, "translations": <array> }` (`inc/classes/class-updatronix-autoupdates.php:46-64`).
- **Erreurs :** 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** aucune au-delà de la capacité et de la règle multisite.
- **Testé :** test rapide HTTP 200.
```php
$request = new WP_REST_Request( 'GET', '/updatronix/v1/auto-updates' );
$response = rest_do_request( $request );
```

#### `POST /auto-updates/core`

- **Méthodes :** POST.
- **Fonction de rappel :** `rest_set_core_mode()` (`:1420-1447`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** `mode` chaîne, obligatoire, énumération `all`, `minor`, `disabled`.
- **Réponse 200 :** la forme de `get_data()`.
- **Erreurs :** 403 lorsque `AUTOMATIC_UPDATER_DISABLED` ou `DISALLOW_FILE_MODS` est défini, ou lorsqu’une constante du cœur contrôle le mode (`auto_update_globally_locked()`, `:1380-1412`).
- **Contraintes :** les constantes globales l’emportent sur la requête.
- **Testé :** contrat de permission 401 avec un utilisateur anonyme.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/core' );
$request->set_body_params( array( 'mode' => 'minor' ) );
$response = rest_do_request( $request );
```

#### `POST /auto-updates/plugin`

- **Méthodes :** POST.
- **Fonction de rappel :** `rest_toggle_plugin()` (`:1455-1511`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** `plugin` chaîne, obligatoire ; `enable` booléen, obligatoire.
- **Réponse 200 :** la forme de `get_data()`.
- **Erreurs :** 403 pour un verrou global ou un veto de `updatronix_auto_update_can_toggle` ; 404 lorsque l’extension n’est pas installée.
- **Contraintes :** le filtre `updatronix_auto_update_can_toggle` peut renvoyer `false` ou un `WP_Error` ; les deux deviennent 403.
- **Testé :** exemple 4, HTTP 403 avec le veto du filtre ; contrat de permission du test rapide 401 avec un utilisateur anonyme.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/plugin' );
$request->set_body_params( array( 'plugin' => 'koko-analytics/koko-analytics.php', 'enable' => false ) );
$response = rest_do_request( $request );
```

#### `POST /auto-updates/theme`

- **Méthodes :** POST.
- **Fonction de rappel :** `rest_toggle_theme()` (`:1519-1575`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** `stylesheet` chaîne, obligatoire ; `enable` booléen, obligatoire.
- **Réponse 200 :** la forme de `get_data()`.
- **Erreurs :** 403 pour un verrou global ou un veto de filtre ; 404 lorsque le thème n’est pas installé.
- **Contraintes :** même chemin de filtre que l’interrupteur d’extension.
- **Testé :** contrat de permission 401 avec un utilisateur anonyme.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/theme' );
$request->set_body_params( array( 'stylesheet' => 'twentytwentyfive', 'enable' => false ) );
$response = rest_do_request( $request );
```

#### `POST /auto-updates/translation`

- **Méthodes :** POST.
- **Fonction de rappel :** `rest_toggle_translation()` (`:1583-1595`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** `enable` booléen, obligatoire.
- **Réponse 200 :** la forme de `get_data()`.
- **Erreurs :** 403 pour un verrou global.
- **Contraintes :** aucune au-delà de la capacité et de la règle multisite.
- **Testé :** contrat de permission 401 avec un utilisateur anonyme.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/translation' );
$request->set_body_params( array( 'enable' => true ) );
$response = rest_do_request( $request );
```

#### `POST /auto-updates/dismiss-constant`

- **Méthodes :** POST.
- **Fonction de rappel :** `rest_dismiss_constant()` (`:1606-1625`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** `constant` chaîne, obligatoire.
- **Réponse 200 :** la forme de `get_data()`.
- **Erreurs :** 400 `{ "message": "Updatronix does not recognize that constant." }` lorsque le nom n’est pas dans la liste d’autorisation.
- **Contraintes :** seule la liste d’autorisation des constantes masquables est acceptée.
- **Testé :** contrat de permission 401 avec un utilisateur anonyme.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/dismiss-constant' );
$request->set_body_params( array( 'constant' => 'AUTOMATIC_UPDATER_DISABLED' ) );
$response = rest_do_request( $request );
```

#### `POST /run-check`

- **Méthodes :** POST.
- **Fonction de rappel :** `rest_run_check()` (`:1637-1656`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** aucun.
- **Réponse 200 :** `{ "message": "Update check started." }`.
- **Erreurs :** 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** rafraîchit les données de mise à jour du cœur, des extensions, des thèmes et des traductions ; la planification ne change pas. Elle figure dans le code mais pas dans la liste de routes du brief.
- **Testé :** contrat de permission 401 avec un utilisateur anonyme.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/run-check' );
$response = rest_do_request( $request );
```

### Édition gratuite : santé

#### `GET /health`

- **Méthodes :** GET.
- **Fonction de rappel :** `rest_get_health()` (`:1663-1665`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** aucun.
- **Réponse 200 :** `{ "generated_at": <int>, "generated_at_human": <string>, "summary": <array>, "checks": <array>, "open_issues": <array>, "resolved": <array>, "counts": { "total": <int>, "passing": <int>, "open": <int> }, "logging_enabled": <bool>, "has_log_table": <bool> }` (`inc/classes/class-updatronix-health.php:70-105`).
- **Erreurs :** 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** l’instantané est mis en cache pendant 300 secondes dans `updatronix_health_snapshot`.
- **Testé :** test rapide HTTP 200.
```php
$request = new WP_REST_Request( 'GET', '/updatronix/v1/health' );
$response = rest_do_request( $request );
```

#### `POST /health/check`

- **Méthodes :** POST.
- **Fonction de rappel :** `rest_run_health_check()` (`:1672-1674`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** aucun.
- **Réponse 200 :** l’instantané reconstruit, de même forme que `/health`.
- **Erreurs :** 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** force une reconstruction et réécrit le cache de 300 secondes.
- **Testé :** contrat de permission 401 avec un utilisateur anonyme.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/health/check' );
$response = rest_do_request( $request );
```

#### `POST /health/retry`

- **Méthodes :** POST.
- **Fonction de rappel :** `rest_retry_health()` (`:1682-1707`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** `log_id` entier, obligatoire, minimum 1.
- **Réponse 200 :** `{ "queued": true, "log_id": <int>, "message": <string> }`.
- **Erreurs :** 404 `updatronix_health_missing` ; 400 `updatronix_health_not_failed`, `updatronix_health_expired`, `updatronix_health_unsupported` ; 403 `updatronix_health_forbidden` ; 409 `updatronix_health_busy` ; 429 `updatronix_health_rate_limited` ; 500 `updatronix_health_schedule_failed`. Le corps est `{ "code": <code>, "message": <message> }`.
- **Contraintes :** uniquement les lignes d’extension et de thème avec le statut `error`, créées dans les 30 derniers jours, et avec `update_plugins` ou `update_themes`. La nouvelle tentative est mise en file une fois par élément, avec un verrou de 15 minutes et une limite de trois par heure (`inc/classes/class-updatronix-health.php:936-1063`).
- **Testé :** contrat de permission 401 avec un utilisateur anonyme.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/health/retry' );
$request->set_body_params( array( 'log_id' => 1 ) );
$response = rest_do_request( $request );
```

#### `GET /health/diagnostics`

- **Méthodes :** GET.
- **Fonction de rappel :** `rest_get_health_diagnostics()` (`:1714-1716`).
- **Permission :** `rest_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** aucun.
- **Réponse 200 :** `{ "environment": [ { "label": <string>, "value": <string> } ], "text": <string> }` (`inc/classes/class-updatronix-health.php:834-928`).
- **Erreurs :** 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** inclut WordPress, PHP, la base de données, le serveur, la mémoire, le temps d’exécution, la taille de téléversement, le fuseau horaire, multisite, la version d’Updatronix, la table de journal, la vérification des mises à jour et les valeurs de journalisation.
- **Testé :** test rapide HTTP 200.
```php
$request = new WP_REST_Request( 'GET', '/updatronix/v1/health/diagnostics' );
$response = rest_do_request( $request );
```

### Édition gratuite : export

#### `POST /logs/export`

- **Méthodes :** POST.
- **Fonction de rappel :** `Updatronix_Export::rest_export()` (`inc/classes/class-updatronix-export.php:251-512`).
- **Permission :** `Updatronix_Security::user_can_manage_logs` ; `manage_updatronix` ; Super administrateur sur multisite.
- **Arguments :** `view` objet, obligatoire : `site_id` entier ; `filters` tableau ; `sort` objet avec `field` énumération `date`, `created_at`, `log_type`, `item_name`, `status`, `performed_as` et `direction` énumération `asc`, `desc` ; `per_page` entier 1–200 ; `page` entier minimum 1 ; `search` chaîne 200 maximum. `merge` booléen, `true` par défaut. `columns` objet. `format` chaîne, énumération des formats du serveur, `txt` par défaut. `cursor` chaîne, `''` par défaut.
- **Réponse 200 :** `{ "status": "ready", "body": <string>, "transient_key": <string>, "truncated": <bool>, "truncation_reason": <string>, "next_cursor": <string|null>, "truncated_included": <int>, "truncated_total": <int|null>, "view_applied": <array>, "meta": { "generated_at": <int>, "rows_in_chunk": <int> } }`. Une réponse fragmentée a `next_cursor` et `truncated: false` (`:624-683`) ; la réponse terminale met `next_cursor` à null (`:711-846`).
- **Erreurs :** 400 `view_invalid` ; 429 limite de débit ; 403 depuis la fonction de rappel de permission.
- **Contraintes :** plafonnée par les formats du serveur, le limiteur de débit et le curseur de fragment. L’édition gratuite renvoie du texte brut ; l’édition Pro ajoute les formats structurés.
- **Testé :** test rapide HTTP 200 ; le transient d’export et le pointeur utilisateur ont été supprimés après l’exécution.
```php
$request = new WP_REST_Request( 'POST', '/updatronix/v1/logs/export' );
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body( wp_json_encode( array( 'view' => array( 'per_page' => 10 ), 'format' => 'txt' ) ) );
$response = rest_do_request( $request );
```

### Édition Pro : Update Shield

#### `GET|POST /shield/settings`

- **Méthodes :** GET, POST.
- **Fonction de rappel :** `Updatronix_Update_Shield::get_shield_settings()` et `save_shield_settings()` (`../updatronix-pro/inc/update-shield/class-updatronix-update-shield.php:164-253`).
- **Permission :** `check_shield_permission` ; `manage_updatronix_pro` ; Super administrateur sur multisite ; licence active ou mode développement.
- **Arguments :** `safe_mode` objet avec `enabled` booléen ; `notify_on_block` booléen ; `control_php_version` booléen ; `control_wp_version` booléen ; `incompatible_behavior` énumération `disable`, `cancel` ; `re_enable` booléen ; `notify_email` booléen ; `flag_abandoned` booléen ; `version_control_lock` booléen ; `version_lock_manual_updates` booléen. Tous facultatifs.
- **Réponse 200 :** `{ "safe_mode": { "enabled": <bool> }, "control_php_version": <bool>, "control_wp_version": <bool>, "notify_on_block": <bool>, "incompatible_behavior": "disable"|"cancel", "re_enable": <bool>, "notify_email": <bool>, "pass_state": <array>, "flag_abandoned": <bool>, "version_control_lock": <bool>, "version_lock_manual_updates": <bool>, "version_control_state": <mixed>, "abandoned_state": <mixed>, "reenable_state": <mixed>, "shield_status": { "blocked_plugins_count": <int>, "blocked_themes_count": <int>, "blocked_core": <bool>, "blocked_plugins": <array>, "blocked_themes": <array>, "blocked_core_details": <mixed>, "abandoned": <array> } }`.
- **Erreurs :** 401/403 depuis la fonction de rappel de permission ; 400 pour un argument invalide.
- **Contraintes :** licence requise ; le mode développement est accepté. L’enregistrement est une fusion.
- **Testé :** exemple 5 et le test rapide, GET HTTP 200 ; contrat de permission POST 401 avec un utilisateur anonyme.
```php
$read = new WP_REST_Request( 'GET', '/updatronix-pro/v1/shield/settings' );
$data = rest_do_request( $read )->get_data();

$write = new WP_REST_Request( 'POST', '/updatronix-pro/v1/shield/settings' );
$write->set_header( 'Content-Type', 'application/json' );
$write->set_body( wp_json_encode( array( 'safe_mode' => array( 'enabled' => true ) ) ) );
$updated = rest_do_request( $write );
```

#### `POST /shield/recheck`

- **Méthodes :** POST.
- **Fonction de rappel :** `recheck_compatibility()` (`class-updatronix-update-shield.php:361-455`).
- **Permission :** `check_shield_permission` ; `manage_updatronix_pro` ; Super administrateur sur multisite ; licence active ou mode développement.
- **Arguments :** aucun.
- **Réponse 200 :** `{ "shield_status": { "blocked_plugins_count": <int>, "blocked_themes_count": <int>, "blocked_core": <bool>, "blocked_plugins": <array>, "blocked_themes": <array>, "blocked_core_details": <mixed>, "abandoned": <array>, "requeued": 0 } }`.
- **Erreurs :** 502 `{ "code": "shield_recheck_unreachable", "message": "WordPress.org could not be reached. Try again later." }` ; 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** requêtes sortantes vers WordPress.org avec un délai d’expiration de 15 secondes. Elle désactive les mises à jour automatiques des éléments bloqués comme effet de bord.
- **Testé :** contrat de permission 401 avec un utilisateur anonyme ; l’écriture sortante n’a pas été exécutée.
```php
$request = new WP_REST_Request( 'POST', '/updatronix-pro/v1/shield/recheck' );
$response = rest_do_request( $request );
```

#### `GET /shield/blocked`

- **Méthodes :** GET.
- **Fonction de rappel :** `get_blocked_history()` (`class-updatronix-update-shield.php:267-352`).
- **Permission :** `check_shield_permission` ; `manage_updatronix_pro` ; Super administrateur sur multisite ; licence active ou mode développement.
- **Arguments :** `page` entier, minimum 1, 1 par défaut ; `per_page` entier, minimum 1, maximum 50, 50 par défaut.
- **Réponse 200 :** `{ "items": [ { "id": <int>, "item": <string>, "type": <string>, "versions": <string>, "reason": <string>, "date": <string>, "dateFormatted": <string>, "log_url": <string> } ], "days": 30, "page": <int>, "per_page": <int>, "total": <int>, "total_pages": <int> }`.
- **Erreurs :** 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** lit les 30 derniers jours de lignes avec les types d’action `prevented`, `incompatible` ou `safe_mode_disabled` ; filtrable via `updatronix_pro_blocked_history_query`.
- **Testé :** test rapide HTTP 200.
```php
$request = new WP_REST_Request( 'GET', '/updatronix-pro/v1/shield/blocked' );
$request->set_query_params( array( 'per_page' => 10 ) );
$response = rest_do_request( $request );
```

### Édition Pro : Update Flow

#### `GET|POST /flow/chains`

- **Méthodes :** GET, POST.
- **Fonction de rappel :** `Updatronix_Update_Flow::get_chains()` et `save_chains()` (`../updatronix-pro/inc/update-flow/class-updatronix-update-flow.php:187-198`, `:332-373`).
- **Permission :** `check_permission` ; `manage_updatronix_pro` ; Super administrateur sur multisite ; licence active.
- **Arguments :** aucun dans la définition de la route ; POST lit le corps JSON `{ "chains": <array> }`.
- **Réponse 200 :** `{ "chains": <array>, "items": <array>, "active_pass": <array|null>, "last_pass": <array>, "automatic_updates": { "enabled": <bool>, "stored": <bool>, "editable": <bool>, "reason": <string> } }`.
- **Erreurs :** 400 `{ "code": <code>, "message": <message>, "errors": <mixed> }` pour une chaîne invalide ou un élément non installé ; 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** les chaînes sont validées pour les cycles et les éléments installés ; l’option est à l’échelle du réseau.
- **Testé :** test rapide GET HTTP 200 ; contrat de permission POST 401 avec un utilisateur anonyme.
```php
$read = new WP_REST_Request( 'GET', '/updatronix-pro/v1/flow/chains' );
$data = rest_do_request( $read )->get_data();

$write = new WP_REST_Request( 'POST', '/updatronix-pro/v1/flow/chains' );
$write->set_header( 'Content-Type', 'application/json' );
$write->set_body( wp_json_encode( array( 'chains' => array() ) ) );
$updated = rest_do_request( $write );
```

#### `POST /flow/automatic-updates`

- **Méthodes :** POST.
- **Fonction de rappel :** `save_automatic_updates()` (`class-updatronix-update-flow.php:241-275`).
- **Permission :** `check_permission` ; `manage_updatronix_pro` ; Super administrateur sur multisite ; licence active.
- **Arguments :** `enabled` booléen, obligatoire dans le corps JSON.
- **Réponse 200 :** `{ "enabled": <bool>, "stored": <bool>, "editable": <bool>, "reason": <string>, "active_pass": <array|null>, "last_pass": <array> }`.
- **Erreurs :** 400 `flow_invalid_enabled` lorsque `enabled` est absent ou n’est pas booléen ; 500 `flow_pass_missing` lorsque la classe de passe est indisponible ; 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** la route fonctionne même lorsqu’une chaîne est temporairement invalide ; un interrupteur sur arrêt arrête la passe active.
- **Testé :** contrat de permission 401 avec un utilisateur anonyme.
```php
$request = new WP_REST_Request( 'POST', '/updatronix-pro/v1/flow/automatic-updates' );
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body( wp_json_encode( array( 'enabled' => false ) ) );
$response = rest_do_request( $request );
```

#### `GET /flow/items`

- **Méthodes :** GET.
- **Fonction de rappel :** `get_items_endpoint()` (`class-updatronix-update-flow.php:319-324`).
- **Permission :** `check_permission` ; `manage_updatronix_pro` ; Super administrateur sur multisite ; licence active.
- **Arguments :** aucun.
- **Réponse 200 :** `{ "items": <array> }`.
- **Erreurs :** 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** licence requise.
- **Testé :** test rapide HTTP 200.
```php
$request = new WP_REST_Request( 'GET', '/updatronix-pro/v1/flow/items' );
$response = rest_do_request( $request );
```

### Édition Pro : White label

#### `GET|POST /white-label`

- **Méthodes :** GET, POST.
- **Fonction de rappel :** `Updatronix_White_Label::get_white_label()` et `save_white_label()` (`../updatronix-pro/inc/white-label/class-updatronix-white-label.php:132-163`).
- **Permission :** `check_permission` ; `manage_updatronix_pro` ; Super administrateur sur multisite ; licence active.
- **Arguments :** aucun dans la définition de la route ; POST lit le corps JSON. `logo_id` est validé comme PNG, JPEG ou WebP lorsqu’il change.
- **Réponse 200 :** `{ "settings": <array>, "logo_url": <string>, "defaults": <array>, "site_name": <string>, "can_upload_files": <bool>, "is_multisite": <bool>, "limits": { "brand_name": <int>, "menu_label": <int>, "logo_bytes": <int>, "logo_min_px": <int> } }`.
- **Erreurs :** 400 `updatronix_invalid_logo` ; 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** l’enregistrement fusionne avec les réglages existants ; la marque est à l’échelle du réseau.
- **Testé :** test rapide GET HTTP 200 ; contrat de permission POST 401 avec un utilisateur anonyme.
```php
$read = new WP_REST_Request( 'GET', '/updatronix-pro/v1/white-label' );
$data = rest_do_request( $read )->get_data();

$write = new WP_REST_Request( 'POST', '/updatronix-pro/v1/white-label' );
$write->set_header( 'Content-Type', 'application/json' );
$write->set_body( wp_json_encode( array( 'brand_name' => 'Demo' ) ) );
$updated = rest_do_request( $write );
```

### Édition Pro : licence

#### `GET /license/status`

- **Méthodes :** GET.
- **Fonction de rappel :** `Updatronix_Pro_License_REST::get_status()` (`../updatronix-pro/inc/license/class-updatronix-pro-license-rest.php:136-180`).
- **Permission :** `check_permission` ; `manage_updatronix_pro` ; Super administrateur sur multisite. Aucune licence active requise.
- **Arguments :** aucun.
- **Réponse 200 :** `{ "status": <string>, "registeredDomain": <string|null>, "maskedKey": <string>, "lastChecked": <string|null>, "lastCheckedFormatted": <string|null>, "dev_mode": <bool>, "decryption_error": <bool> }`.
- **Erreurs :** 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** aucune licence requise, donc la route signale un état inactif. La clé est toujours masquée.
- **Testé :** exemple 5 et le test rapide, HTTP 200 ; `license_state=active`.
```php
$request = new WP_REST_Request( 'GET', '/updatronix-pro/v1/license/status' );
$response = rest_do_request( $request );
```

#### `POST /license/activate`

- **Méthodes :** POST.
- **Fonction de rappel :** `activate()` (`class-updatronix-pro-license-rest.php:193-302`).
- **Permission :** `check_permission` ; `manage_updatronix_pro` ; Super administrateur sur multisite. Aucune licence active requise.
- **Arguments :** `license_key` chaîne, obligatoire, nettoyée avec `sanitize_text_field`, validée avec `Updatronix_Pro_License_Storage::is_valid_key_format`.
- **Réponse 200 :** `{ "status": "active", "registeredDomain": <string>, "maskedKey": <string>, "message": "License activated." }`.
- **Erreurs :** 400 `{ "status": "error", "code": <string>, "message": <string> }` pour un rejet de l’API ; 429 `{ "status": "error", "code": "e429", "message": <string>, "retry_after": <int> }` pour le délai de 60 secondes ; 500 `license_save_failed` ; 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** la clé n’est jamais renvoyée non masquée. L’activation est limitée à une tentative par 60 secondes.
- **Testé :** contrat de permission 400 à la validation des arguments avec un utilisateur anonyme ; aucune clé n’a été envoyée à l’API de licence. Une vraie clé n’est jamais affichée.
```php
$request = new WP_REST_Request( 'POST', '/updatronix-pro/v1/license/activate' );
$request->set_body_params( array( 'license_key' => '<var>YOUR_LICENSE_KEY</var>' ) );
$response = rest_do_request( $request );
```

#### `POST /license/deactivate`

- **Méthodes :** POST.
- **Fonction de rappel :** `deactivate()` (`class-updatronix-pro-license-rest.php:316-415`).
- **Permission :** `check_permission` ; `manage_updatronix_pro` ; Super administrateur sur multisite. Aucune licence active requise.
- **Arguments :** aucun.
- **Réponse 200 :** `{ "status": "inactive", "message": "License deactivated." }`.
- **Erreurs :** 400 `rest_license_inactive` lorsque la licence est déjà inactive ; 400 pour un rejet de l’API ; 500 `license_save_failed` ; 401/403 depuis la fonction de rappel de permission.
- **Contraintes :** une erreur de déchiffrement efface la clé locale et renvoie 200. Une vraie désactivation change l’état de la licence ; enregistrez-le et restaurez-le si l’instance de démonstration a une licence active.
- **Testé :** contrat de permission 401 avec un utilisateur anonyme ; la licence de démonstration active n’a pas été modifiée.
```php
$request = new WP_REST_Request( 'POST', '/updatronix-pro/v1/license/deactivate' );
$response = rest_do_request( $request );
```

## API PHP publique

Les crochets, les filtres et les routes REST forment la surface prise en charge. Les quatre symboles PHP stables ci-dessous font exception. Ce sont `updatronix_get_admin_tabs()`, la classe `Updatronix_External_Log`, et ses méthodes `sources()` et `log()`. Toute autre classe est interne et peut changer sans dépréciation.

### Symboles stables

#### `updatronix_get_admin_tabs()`

- **Signature :** `function updatronix_get_admin_tabs(): array`
- **Retour :** `array<string, array{slug: string, label: string, icon: string, priority?: int}>` ; les onglets sont triés par priorité.
- **@since :** 1.1.1
- **Source :** `inc/core/tabs.php:62-77`
- Utilisez le filtre `updatronix_admin_tabs` pour ajouter, supprimer ou réordonner des onglets.
```php
$tabs = updatronix_get_admin_tabs();
```

#### `Updatronix_External_Log::sources()`

- **Signature :** `public static function sources(): array`
- **Retour :** les clés de source acceptées ; les valeurs par défaut sont `ftp`, `sftp`, `ssh`, `cli` et `custom`.
- **@since :** 1.4.0
- **Source :** `inc/classes/class-updatronix-external-log.php:32-68`
```php
$sources = Updatronix_External_Log::sources();
```

#### `Updatronix_External_Log::log()`

- **Signature :** `public static function log( array $args ): int|\WP_Error`
- **Arguments :** `item_name` obligatoire. Facultatifs : `source` (`custom` par défaut), `log_type` (`plugin` par défaut), `item_slug`, `version_before`, `version_after`, `status` (`success` par défaut), `message`, `event_key`, et les champs de décision `target_version`, `reason_code`, `decision_state`, `origin`, `decision_stage`, `pass_id`, `chain_id`, `predecessor`, `next_eligible_at`, `attempt`, `network` et `context`.
- **Retour :** l’ID du journal, ou `WP_Error` en cas d’échec de validation ou de stockage.
- **@since :** 1.4.0 ; 1.6.0 a ajouté les champs de décision facultatifs.
- **Source :** `inc/classes/class-updatronix-external-log.php:70-227`
- Déclenche `updatronix_external_update_logged` après l’insertion.
```php
$log_id = Updatronix_External_Log::log(
	array(
		'item_name'      => 'Deploy 2026-11-07',
		'source'         => 'custom',
		'version_before' => '1.0.0',
		'version_after'  => '1.0.1',
	)
);
```

### Classes internes

Toute autre classe est interne. Cela inclut les classes de l’édition gratuite `Updatronix_Logger`, `Updatronix_Settings`, `Updatronix_Security`, `Updatronix_Health`, `Updatronix_AutoUpdates`, `Updatronix_AutoUpdateDelay`, `Updatronix_Cron`, `Updatronix_Notifications`, `Updatronix_Update_Logger`, `Updatronix_Export`, `Updatronix_Export_Request_Schema`, `Updatronix_Export_Query_Builder`, `Updatronix_Export_Body_Builder`, `Updatronix_Export_Transient_Manager`, `Updatronix_Export_Rate_Limiter`, `Updatronix_Export_Cursor`, `Updatronix_Export_Audit`, `Updatronix_Database`, `Updatronix_Log_Retention`, `Updatronix_Log_Taxonomy`, `Updatronix_Log_Event`, `Updatronix_Log_Query`, `Updatronix_UpdateLogState`, `Updatronix_Schedule_Context`, `Updatronix_Item_State`, `Updatronix_Schedule_State`, `Updatronix_ErrorHandler`, `Updatronix_Core_Update_Log_Versions` et `Updatronix_Automatic_Update_Result_Notes` ; et les classes de l’édition Pro `Updatronix_Pro_Connector`, `Updatronix_Update_Shield`, `Updatronix_Update_Flow`, `Updatronix_Update_Pass_Order`, `Updatronix_Schedule_Facts`, `Updatronix_Flow_Readiness`, `Updatronix_White_Label` et `Updatronix_Pro_License_REST`. Utilisez le crochet, le filtre ou la route REST que ce guide nomme pour la même fonction.

## Modèle de données

### Table de journal

- Table : `{prefix}updatronix_logs` (`Updatronix_Database::TABLE_LOGS`, `inc/classes/class-updatronix-database.php:49` ; le nom préfixé vient de `get_table_name()`, `:77-81`).
- La table se trouve sur le site principal sur multisite ; `install_schema()` passe par `updatronix_with_main_site()` (`:177-238`).
- Version du schéma : `3` (`SCHEMA_VERSION`, `:35`).
- La table comporte 30 colonnes.

| Colonne | Type et valeur par défaut | Stabilité |
|---|---|---|
| `id` | `bigint(20) unsigned NOT NULL AUTO_INCREMENT` | Stable |
| `site_id` | `bigint(20) unsigned NOT NULL DEFAULT 1` | Stable |
| `log_type` | `varchar(20) NOT NULL DEFAULT 'plugin'` | Stable |
| `action_type` | `varchar(32) NOT NULL DEFAULT 'update'` | Stable |
| `item_name` | `varchar(255) NOT NULL DEFAULT ''` | Stable |
| `item_slug` | `varchar(255) NOT NULL DEFAULT ''` | Stable |
| `version_before` | `varchar(64) NOT NULL DEFAULT ''` | Stable |
| `version_after` | `varchar(64) NOT NULL DEFAULT ''` | Stable |
| `target_version` | `varchar(64) NOT NULL DEFAULT ''` | Stable |
| `status` | `varchar(20) NOT NULL DEFAULT 'success'` | Stable |
| `event_key` | `varchar(191) DEFAULT NULL`, unique | Stable |
| `message` | `longtext DEFAULT NULL` | Interne |
| `trace` | `longtext DEFAULT NULL` | Interne |
| `reason_code` | `varchar(48) NOT NULL DEFAULT ''` | Stable |
| `decision_state` | `varchar(20) NOT NULL DEFAULT ''` | Stable |
| `origin` | `varchar(20) NOT NULL DEFAULT ''` | Stable |
| `decision_stage` | `varchar(20) NOT NULL DEFAULT ''` | Stable |
| `pass_id` | `char(32) NOT NULL DEFAULT ''` | Stable |
| `chain_id` | `varchar(64) DEFAULT NULL` | Stable |
| `predecessor` | `varchar(191) DEFAULT NULL` | Stable |
| `next_eligible_at` | `datetime DEFAULT NULL` | Stable |
| `attempt` | `smallint unsigned NOT NULL DEFAULT 0` | Stable |
| `network` | `tinyint(1) NOT NULL DEFAULT 0` | Stable |
| `context` | `longtext DEFAULT NULL` | Interne |
| `retained` | `tinyint(1) NOT NULL DEFAULT 0` | Stable, en lecture seule |
| `user_id` | `bigint(20) unsigned NOT NULL DEFAULT 0` | Stable |
| `performed_by` | `varchar(20) NOT NULL DEFAULT 'system'` | Stable |
| `performed_as` | `varchar(20) NOT NULL DEFAULT 'manual'` | Stable |
| `update_context` | `varchar(20) NOT NULL DEFAULT ''` | Stable |
| `created_at` | `datetime NOT NULL DEFAULT '0000-00-00 00:00:00'` | Stable |

Index : `PRIMARY KEY (id)`, `site_id`, `log_type`, `status`, `created_at`, `UNIQUE KEY event_key`, `created_type_status (created_at, log_type, status)`, `reason_created (reason_code, created_at)`, `state_created (decision_state, created_at)`, `origin_created (origin, created_at)`, `pass_chain (pass_id, chain_id)`, `item_lookup (site_id, log_type, item_slug, target_version)` et `chain_created (chain_id, created_at)`.

`message`, `trace` et `context` sont de forme libre et peuvent contenir une sortie de processus, des chemins ou des jetons expurgés. Lisez-les via la route REST ; ne les analysez pas comme un schéma stable. `retained` est écrit par la tâche de conservation ; lisez-le, ne l’écrivez pas.

### Options

| Option | Portée et forme | Stabilité |
|---|---|---|
| `updatronix_settings` | JSON par site. `logging_enabled` booléen vrai ; `retention_days` entier 90 (1–365) ; `notifications_mode` `default`\|`disabled` ; `notify_enabled` booléen faux ; `notify_emails` chaîne ; `notify_on` tableau de `core`, `plugin_theme`, `debug`, `technical` ; `auto_update_translations` booléen vrai ; `dismissed_constants` tableau ; `delete_data_on_uninstall` booléen faux ; `schedule` lu depuis la planification du réseau | Stable en lecture ; écriture via `/settings` |
| `updatronix_network_schedule` | Option de site du réseau sur multisite ; option non chargée automatiquement sur un site unique. `update_check.recurrence` `''`\|`hourly`\|`twicedaily`\|`daily`\|`weekly` ; `update_check.time` `HH:MM` ou `''` ; `delay_updates.enabled` booléen faux ; `delay_updates.delay_value` entier 0–365 | Stable en lecture |
| `updatronix_update_logger_state` | État d’événement en attente non chargé automatiquement, indexé par clé d’événement ; purgé après 1800 secondes | Interne |
| `updatronix_log_db_version` | La chaîne de version du schéma stockée, actuellement `3` | Interne |
| `updatronix_export_audit` | Tampon circulaire FIFO non chargé automatiquement, au plus 100 entrées | Interne |
| `auto_update_plugins` | Option de site native ; tableau de fichiers d’extension | Stable, natif de WordPress |
| `auto_update_themes` | Option de site native ; tableau de feuilles de style | Stable, natif de WordPress |
| `auto_update_core_major` | Option de site native ; `unset`\|`enabled`\|`disabled` | Stable, natif de WordPress |
| `auto_update_core_minor` | Option de site native ; `enabled`\|`disabled` | Stable, natif de WordPress |
| `auto_update_core_dev` | Option de site native ; `enabled`\|`disabled` | Stable, natif de WordPress |

Les options Pro sont des options de site à l’échelle du réseau et sont internes : `updatronix_pro_shield_settings`, `updatronix_pro_license`, `updatronix_pro_white_label`, `updatronix_pro_flow_chains`, `updatronix_pro_update_pass`, `updatronix_pro_update_pass_enabled`, `updatronix_pro_last_pass`, `updatronix_pro_shield_ledger` et `updatronix_pro_version_lock_notice_seen`. Utilisez les routes REST Pro.

Le filtre `updatronix_pro_update_pass_enabled` partage son nom avec l’option ci-dessus. L’option stocke l’interrupteur ; le filtre remplace la valeur effective.

### Transients

| Clé | Durée de vie | Objet |
|---|---|---|
| `updatronix_health_snapshot` | 300 secondes | Cache de l’instantané de santé ; lisez l’instantané via `/health` |
| `updatronix_log_schema_upgrade_lock` | 60 secondes | Verrou de mise à niveau du schéma |
| `updatronix_log_export_{site}_{user}_{32 hex}` | 900 secondes | Charge utile de continuation de l’export |
| `updatronix_export_rl_min_{site}_{user}` | 60 secondes | Limiteur de débit de l’export |
| `updatronix_export_rl_hour_{site}_{user}` | 3600 secondes | Limiteur de débit de l’export |
| `updatronix_changelog_{md5(type\|slug)}` | 1 heure vide ; 12 heures rempli | Cache du journal des modifications des détails du journal |
| `updatronix_health_retry_lock_{md5}` | 15 minutes | Verrou de nouvelle tentative de santé |
| `updatronix_health_retry_rate_{md5}` | 1 heure | Compteur de débit des nouvelles tentatives de santé |
| `updatronix_pro_activation_throttle` | 60 secondes | Délai d’attente avant réactivation de la licence Pro |
| `updatronix_pro_abandoned_cache` | 7 jours ; 200 entrées maximum | Cache des extensions abandonnées Pro |

Le groupe de cache d’objets `updatronix_logs` est un cache d’objets, pas un transient. Les transients du cœur de WordPress `update_plugins`, `update_themes` et `update_core` sont lus et rafraîchis par la vérification d’Update Shield ; ils n’appartiennent pas à Updatronix.

### Conservation

- Fenêtre : `retention_days` 1–365, 90 par défaut (`inc/settings/options.php:31`, `:431-437`).
- `Updatronix_Log_Retention::cleanup( $days )` exécute trois étapes (`inc/classes/class-updatronix-log-retention.php:19-33`) :
  1. `pin()` marque la ligne la plus récente par `(site_id, log_type, item_slug, target_version)` plus ancienne que le seuil, lorsque `decision_state` vaut `blocked`, `failed` ou `uncertain`, et définit `retained = 1` (`:46-93`).
  2. `trim( 500 )` conserve au plus 500 lignes retenues et supprime les lignes retenues plus anciennes (`:97-125` ; `RETAINED_CAP = 500`, `:21`).
  3. `delete_expired()` supprime les lignes plus anciennes que le seuil avec `retained = 0`, sauf une ligne de résumé de passe dont le `pass_id` a une ligne retenue (`:133-168`).
- Déclencheurs : la tâche cron de conservation et `POST /updatronix/v1/logs/cleanup`.
- Test : `tests/Integration/LogRetentionTest.php:89,97,122`.

### Version du schéma et chemin de mise à niveau

- `SCHEMA_VERSION = '3'` (`inc/classes/class-updatronix-database.php:35`) ; `DB_VERSION` est un alias (`:42`).
- La version stockée est l’option `updatronix_log_db_version` (`OPTION_DB_VERSION`, `:25` ; `inc/core/constants.php:56`).
- `maybe_upgrade()` compare la version stockée, prend `updatronix_log_schema_upgrade_lock` pendant 60 secondes, exécute `install_schema()` avec `dbDelta`, remplit rétroactivement le préfixe de délai et écrit la nouvelle version (`:128-156`). Un second appel ne fait rien.
- `create_table()` exécute la même installation à l’activation (`:99-115`).
- `install_schema()` est idempotent ; `dbDelta` ajoute les colonnes et les index manquants (`:177-238`). `backfill_delay_prefix()` ne remplit rétroactivement que le préfixe déterministe `udly|` de la clé d’événement (`:250`).
- Test : `tests/Integration/LogSchemaMigrationTest.php:78,147,159,188,217`.

### Champs stables et internes

- Stables : le nom de la table ; toutes les colonnes sauf `message`, `trace` et `context` ; la version du schéma ; les clés `updatronix_settings` et `updatronix_network_schedule` ; les options natives `auto_update_*` ; et la règle de conservation.
- Internes : `message`, `trace` et `context` ; tous les transients d’état en attente, d’export, de limite de débit, de journal des modifications, de nouvelle tentative et de limitation de licence ; le verrou de mise à niveau ; les options Pro du tableau ci-dessus ; et toute écriture dans `retained`.

## Exemples

Les cinq exemples ont été exécutés sur le site DDEV avec `wp_set_current_user( 6 )` et `rest_do_request()`. Les commandes complètes, la sortie et le nettoyage se trouvent dans `.agents/tasks/2026-11-07-docs/examples-developer-api-en.md`.

### Exemple 1 : journaliser une mise à jour externe personnalisée

```php
wp_set_current_user( 6 );

add_action( 'updatronix_external_update_logged', function ( $log_id, $args ) {
	// La mise à jour externe est stockée ; notifiez votre tableau de bord de déploiement.
}, 10, 2 );

$result = Updatronix_External_Log::log(
	array(
		'item_name'      => 'Demo External Update',
		'item_slug'      => 'demo/external-update',
		'source'         => 'custom',
		'version_before' => '1.0.0',
		'version_after'  => '1.0.1',
		'status'         => 'success',
		'event_key'      => 'demo|external|' . time(),
	)
);

if ( is_wp_error( $result ) ) {
	// Gérez l’erreur de validation ou de stockage.
}
```

Observé : `log_id=20`, `hook_fired=yes`, `hook_source=custom`, `cleanup_deleted=yes`. La ligne de démonstration a été supprimée.

### Exemple 2 : lire le journal via `updatronix/v1/logs`

```php
wp_set_current_user( 6 );

$request = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
$request->set_query_params( array( 'per_page' => 5 ) );
$response = rest_do_request( $request );
$data     = $response->get_data();

echo $response->get_status(); // 200
echo $data['total'];          // 17
```

Observé : `http_status=200`, `total=17`, `rows=5`, `first_id=17`.

### Exemple 3 : réagir à une entrée enregistrée via `updatronix_after_log`

```php
wp_set_current_user( 6 );

add_action( 'updatronix_after_log', function ( $log_id, $data ) {
	// Reproduisez la nouvelle ligne.
}, 10, 2 );

$id = Updatronix_Logger::log(
	'plugin',
	'update',
	'Demo Hook Entry',
	'demo/hook-entry',
	'1.0.0',
	'1.0.1',
	'success',
	'Demo after_log entry.',
	'',
	'manual',
	'single',
	'demo|after-log|' . time()
);
```

Observé : `log_id=21`, `hook_fired=yes`, `hook_log_id=21`, `hook_item_name=Demo Hook Entry`, `cleanup_deleted=yes`.

`Updatronix_Logger` est interne. Pour une intégration, la surface prise en charge est l’action `updatronix_after_log` ; l’exemple utilise le journaliseur seulement pour créer la ligne qui la déclenche.

### Exemple 4 : modifier une décision de mise à jour automatique par un filtre

```php
wp_set_current_user( 6 );

add_filter( 'updatronix_auto_update_can_toggle', function ( $can_toggle, $type, $id, $enable ) {
	if ( 'plugin' === $type && 'koko-analytics/koko-analytics.php' === $id ) {
		return false;
	}
	return $can_toggle;
}, 99, 4 );

$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/plugin' );
$request->set_body_params(
	array(
		'plugin' => 'koko-analytics/koko-analytics.php',
		'enable' => false,
	)
);
$response = rest_do_request( $request );
```

Observé : `http_status=403`, `message=This item is managed by an external update manager and cannot be toggled here.` Aucune option n’a été écrite ; le filtre a été retiré après l’appel.

### Exemple 5 : lire l’état d’Update Shield de l’édition Pro

```php
wp_set_current_user( 6 );

$request  = new WP_REST_Request( 'GET', '/updatronix-pro/v1/shield/settings' );
$response = rest_do_request( $request );
$data     = $response->get_data();

echo $response->get_status(); // 200 lorsque la licence est active
echo $data['safe_mode']['enabled'];
```

Observé : `shield_http=200`, `safe_mode=on`, `blocked_plugins=1` ; `license_http=200`, `license_state=active`, `license_dev_mode=no`. Si la licence est inactive, la route renvoie 403 ; c’est le contrat documenté.

## Versions et dépréciations

### Convention `@since`

- Chaque crochet, filtre et méthode stable porte `@since <version de l’extension>` dans son docblock. La version est la première publication qui a livré le symbole.
- Lorsque la source n’a pas de balise et que le journal des modifications n’en établit aucune, ce guide indique « since not declared ». Il n’invente jamais de version.
- Onze noms de crochets n’ont pas de version déclarée : `updatronix_after_save_settings`, `updatronix_before_update_check`, `updatronix_admin_brand`, `updatronix_auto_update_can_toggle`, `updatronix_auto_update_plugin_data`, `updatronix_auto_update_theme_data`, `updatronix_can_manage_pro`, `updatronix_export_max_rows`, `updatronix_license_active`, `updatronix_skip_auto_update_delay` et `updatronix_pro_update_pass_max_attempts`.
- Six sites d’appel n’ont pas de balise locale alors que le nom du crochet a une version ailleurs : `updatronix_log_query_args`, `updatronix_pro_flow_max_chains`, `updatronix_pro_flow_max_steps`, `updatronix_pro_update_pass_enabled`, `updatronix_schedule_item_state` et `updatronix_schedule_offer_ttl`. Le guide utilise la version établie du crochet et signale l’écart du site d’appel.

### Versions des extensions

- Extension gratuite : 2.0.1.
- Extension Pro : 0.8.1.
- Le schéma du journal est versionné séparément : `SCHEMA_VERSION = 3`.
- Le guide est vérifié contre WordPress 7.1.4 et PHP 8.4.27 ; les minimums pris en charge sont WordPress 6.2 et PHP 8.1.

### Renommage et suppression

- Un crochet, un filtre, une route ou une méthode stable est déprécié avant d’être supprimé. L’ancien nom reste au moins une version mineure et le nouveau nom est documenté à côté.
- La suppression d’un crochet est un changement incompatible ; elle n’est pas retirée du guide en silence.
- Le crochet du cœur `wp_maybe_auto_update` suit la politique du cœur de WordPress ; la politique de dépréciation de ce guide ne s’y applique pas.
- Les classes internes ne sont pas couvertes par la politique de dépréciation. Utilisez plutôt le crochet, le filtre ou la route REST.

## Vérification

### Décomptes

| Élément | Nombre | Source |
|---|---|---|
| Entrées de crochets Updatronix | 62 | 9 actions gratuites + 29 filtres propres à l’édition gratuite + 22 filtres propres à l’édition Pro + 2 filtres partagés |
| Note sur le crochet du cœur | 1 | `wp_maybe_auto_update` |
| Lignes de crochets si la note du cœur est comptée | 63 | 62 + 1 |
| Routes REST de l’édition gratuite | 17 | `routes-verified-developer-api-en.md` |
| Routes REST de l’édition Pro | 10 | `routes-verified-developer-api-en.md` |
| Total des routes REST | 27 | 17 + 10 |
| Symboles PHP stables | 4 | API PHP publique |
| Colonnes de la table de journal | 30 | `inc/classes/class-updatronix-database.php:185-229` |
| Version du schéma du journal | 3 | `:35` |

### Exemples testés

Les exemples 1 à 5 ont été exécutés sur le site DDEV. Les routes en lecture seule ont renvoyé 200 lors du test rapide ; les routes d’écriture ont renvoyé 401 (ou 400 à la validation des arguments) pour un utilisateur anonyme. Les lignes de démonstration ont été supprimées : `total_rows=17`, `demo_rows=0`, `export_pointer=empty`.

### Résultats du vérificateur

- `bin/harness exec php tools/check_docs.php` : 12 fichiers, 0 erreur, 0 avertissement.
- `bin/harness docs check` : `docs/user: OK`.
- `git diff --check` : aucune erreur d’espace blanc.
- Le guide comporte neuf sections H2, comme `docs/developer-api/en.md`.

### Constats

1. Onze noms de crochets n’ont pas de `@since` ; ils sont documentés comme « since not declared » et listés ci-dessus.
2. Six sites d’appel n’ont pas de `@since` local ; la version du crochet est établie à un autre site d’appel ou dans le journal des modifications.
3. `/run-check` est enregistré dans le code mais absent de la liste de routes du brief ; il est inclus.
4. Le schéma comporte 30 colonnes, pas 28.
5. Le décompte de 63 crochets inclut la note sur le crochet du cœur `wp_maybe_auto_update` ; les entrées Updatronix sont 62.
6. Chaque route a une fonction de rappel de permission.
7. La licence de démonstration est active, donc la route Update Shield a renvoyé 200 ; le contrat 403 est documenté pour une licence inactive.
8. Le test de `updatronix_schedule_item_state` via `pro_facts()` n’a pas modifié la décision ; l’exemple de filtre testé utilise `updatronix_auto_update_can_toggle` et la route REST.
