#!/usr/bin/env python3
"""Seed the deterministic fixture tier on the two DDEV test sites.

This script owns three things the runner depends on:

1. the live fixture files and local update offers on each disposable site;
2. `wp-content/testbook-base`, the file baseline `restore_files()` uses;
3. optionally, the DB snapshots `testbook-base2` and `testbook-ms`.

Run it twice in a row to prove idempotence. Use `--refresh-snapshots` after a
fixture change so the snapshot and the file baseline describe the same tree.
"""

from __future__ import annotations

import argparse
import json
import os
import shutil
import subprocess
import sys
import zipfile
from pathlib import Path

import yaml

SITES = [
    Path("/home/quentin/_DDEV/updatronix-test"),
    Path("/home/quentin/_DDEV/updatronix-ms"),
]
SNAPSHOTS = {
    "updatronix-test": "testbook-base2",
    "updatronix-ms": "testbook-ms",
}
FREE_ROOT = Path(__file__).resolve().parents[2]
PRO_ROOT = FREE_ROOT.parent / "updatronix-pro"
RUNTIME_FREE = ( "assets/build", "inc", "languages", "updatronix.php", "uninstall.php", "readme.txt", "LICENSE" )
RUNTIME_PRO = ( "assets/build", "inc", "languages", "updatronix-pro.php", "uninstall.php", "readme.txt", "LICENSE" )
CONTAINER = "/var/www/html"
OUT = FREE_ROOT / "tests/testbook/community-versions.yaml"

SEED = r"""<?php
delete_site_transient( 'update_plugins' );
delete_site_transient( 'update_themes' );
delete_site_transient( 'translation_updates' );
$base = get_site_url() . '/wp-content/uploads/testbook/';
$p = get_site_transient( 'update_plugins' );
if ( ! is_object( $p ) ) { $p = new stdClass(); }
if ( ! isset( $p->response ) || ! is_array( $p->response ) ) { $p->response = array(); }
$p->response['fixture-alpha/fixture-alpha.php'] = (object) array(
    'id' => 'wordpress.org/plugins/fixture-alpha/', 'slug' => 'fixture-alpha',
    'plugin' => 'fixture-alpha/fixture-alpha.php', 'new_version' => '2.0.0',
    'url' => 'https://example.test/fixture-alpha', 'package' => $base . 'fixture-alpha-2.0.0.zip',
    'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
    'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
);
$p->response['fixture-incompatible/fixture-incompatible.php'] = (object) array(
    'id' => 'wordpress.org/plugins/fixture-incompatible/', 'slug' => 'fixture-incompatible',
    'plugin' => 'fixture-incompatible/fixture-incompatible.php', 'new_version' => '2.0.0',
    'url' => 'https://example.test/fixture-incompatible', 'package' => $base . 'fixture-incompatible-2.0.0.zip',
    'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
    'tested' => '7.1', 'requires_php' => '9.9', 'requires' => '99.0', 'compatibility' => new stdClass(),
);
$p->response['fixture-bravo/fixture-bravo.php'] = (object) array(
    'id' => 'wordpress.org/plugins/fixture-bravo/', 'slug' => 'fixture-bravo',
    'plugin' => 'fixture-bravo/fixture-bravo.php', 'new_version' => '2.0.0',
    'url' => 'https://example.test/fixture-bravo', 'package' => $base . 'fixture-bravo-2.0.0.zip',
    'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
    'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
);
$p->response['fixture-locked/fixture-locked.php'] = (object) array(
    'id' => 'wordpress.org/plugins/fixture-locked/', 'slug' => 'fixture-locked',
    'plugin' => 'fixture-locked/fixture-locked.php', 'new_version' => '2.0.0',
    'url' => 'https://example.test/fixture-locked', 'package' => $base . 'fixture-locked-2.0.0.zip',
    'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
    'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
);
$p->response['fixture-broken/fixture-broken.php'] = (object) array(
    'id' => 'wordpress.org/plugins/fixture-broken/', 'slug' => 'fixture-broken',
    'plugin' => 'fixture-broken/fixture-broken.php', 'new_version' => '2.0.0',
    'url' => 'https://example.test/fixture-broken', 'package' => $base . 'fixture-broken-2.0.0-missing.zip',
    'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
    'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
);
$p->response['fixture-corrupt/fixture-corrupt.php'] = (object) array(
    'id' => 'wordpress.org/plugins/fixture-corrupt/', 'slug' => 'fixture-corrupt',
    'plugin' => 'fixture-corrupt/fixture-corrupt.php', 'new_version' => '2.0.0',
    'url' => 'https://example.test/fixture-corrupt', 'package' => $base . 'fixture-corrupt-2.0.0.zip',
    'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
    'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
);
$p->response['fixture-partial/fixture-partial.php'] = (object) array(
    'id' => 'wordpress.org/plugins/fixture-partial/', 'slug' => 'fixture-partial',
    'plugin' => 'fixture-partial/fixture-partial.php', 'new_version' => '2.0.0',
    'url' => 'https://example.test/fixture-partial', 'package' => $base . 'fixture-partial-2.0.0.zip',
    'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
    'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
);
$p->translations = array(
    array(
        'type' => 'plugin', 'slug' => 'fixture-alpha', 'language' => 'fr_FR', 'version' => '1.0.0',
        'updated' => '2026-10-29 00:00:00', 'package' => $base . 'fixture-alpha-fr_FR.zip', 'autoupdate' => true,
    ),
);
$p->last_checked = time();
set_site_transient( 'update_plugins', $p );
$t = get_site_transient( 'update_themes' );
if ( ! is_object( $t ) ) { $t = new stdClass(); }
if ( ! isset( $t->response ) || ! is_array( $t->response ) ) { $t->response = array(); }
$t->response['fixture-theme'] = array(
    'theme' => 'fixture-theme', 'new_version' => '2.0.0', 'url' => 'https://example.test/fixture-theme',
    'package' => $base . 'fixture-theme-2.0.0.zip', 'requires' => '6.2', 'requires_php' => '8.0',
);
$t->last_checked = time();
set_site_transient( 'update_themes', $t );
$tr = get_site_transient( 'translation_updates' );
if ( ! is_array( $tr ) ) { $tr = array(); }
$found = false;
foreach ( $tr as $existing ) {
    if ( isset( $existing->slug, $existing->language ) && 'fixture-alpha' === $existing->slug && 'fr_FR' === $existing->language ) { $found = true; break; }
}
if ( ! $found ) {
    $tr[] = (object) array(
        'type' => 'plugin', 'slug' => 'fixture-alpha', 'language' => 'fr_FR', 'version' => '1.0.0',
        'updated' => '2026-10-29 00:00:00', 'package' => $base . 'fixture-alpha-fr_FR.zip', 'autoupdate' => true,
    );
}
set_site_transient( 'translation_updates', $tr );
echo wp_json_encode( array( 'plugins' => array_keys( $p->response ), 'themes' => array_keys( $t->response ), 'translations' => count( $tr ) ) );
"""

MU = r"""<?php
/**
 * Test-book offer and abandoned fixtures. The filters keep the local offers
 * alive when WordPress refreshes its update transients.
 */
add_filter( 'updatronix_pro_abandoned_data', static function ( $data, $type, $id ) {
    if ( 'plugin' === $type && 0 === strpos( (string) $id, 'fixture-abandoned/' ) ) {
        return array( 'last_updated' => '2020-01-01 00:00:00' );
    }
    return $data;
}, 10, 3 );

add_filter( 'updatronix_pro_composer_project_roots', static function ( $roots ) {
    $roots[] = WP_CONTENT_DIR . '/testbook-composer-project';
    return $roots;
} );

add_filter( 'site_transient_update_plugins', static function ( $value ) {
    if ( ! is_object( $value ) ) { $value = new stdClass(); }
    if ( ! isset( $value->response ) || ! is_array( $value->response ) ) { $value->response = array(); }
    $base = get_site_url() . '/wp-content/uploads/testbook/';
    $value->response['fixture-alpha/fixture-alpha.php'] = (object) array(
        'id' => 'wordpress.org/plugins/fixture-alpha/', 'slug' => 'fixture-alpha',
        'plugin' => 'fixture-alpha/fixture-alpha.php', 'new_version' => '2.0.0',
        'url' => 'https://example.test/fixture-alpha', 'package' => $base . 'fixture-alpha-2.0.0.zip',
        'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
        'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
    );
    $value->response['fixture-incompatible/fixture-incompatible.php'] = (object) array(
        'id' => 'wordpress.org/plugins/fixture-incompatible/', 'slug' => 'fixture-incompatible',
        'plugin' => 'fixture-incompatible/fixture-incompatible.php', 'new_version' => '2.0.0',
        'url' => 'https://example.test/fixture-incompatible', 'package' => $base . 'fixture-incompatible-2.0.0.zip',
        'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
        'tested' => '7.1', 'requires_php' => '9.9', 'requires' => '99.0', 'compatibility' => new stdClass(),
    );
    $value->response['fixture-bravo/fixture-bravo.php'] = (object) array(
        'id' => 'wordpress.org/plugins/fixture-bravo/', 'slug' => 'fixture-bravo',
        'plugin' => 'fixture-bravo/fixture-bravo.php', 'new_version' => '2.0.0',
        'url' => 'https://example.test/fixture-bravo', 'package' => $base . 'fixture-bravo-2.0.0.zip',
        'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
        'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
    );
    $value->response['fixture-locked/fixture-locked.php'] = (object) array(
        'id' => 'wordpress.org/plugins/fixture-locked/', 'slug' => 'fixture-locked',
        'plugin' => 'fixture-locked/fixture-locked.php', 'new_version' => '2.0.0',
        'url' => 'https://example.test/fixture-locked', 'package' => $base . 'fixture-locked-2.0.0.zip',
        'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
        'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
    );
    $value->response['fixture-broken/fixture-broken.php'] = (object) array(
        'id' => 'wordpress.org/plugins/fixture-broken/', 'slug' => 'fixture-broken',
        'plugin' => 'fixture-broken/fixture-broken.php', 'new_version' => '2.0.0',
        'url' => 'https://example.test/fixture-broken', 'package' => $base . 'fixture-broken-2.0.0-missing.zip',
        'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
        'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
    );
    $value->response['fixture-corrupt/fixture-corrupt.php'] = (object) array(
        'id' => 'wordpress.org/plugins/fixture-corrupt/', 'slug' => 'fixture-corrupt',
        'plugin' => 'fixture-corrupt/fixture-corrupt.php', 'new_version' => '2.0.0',
        'url' => 'https://example.test/fixture-corrupt', 'package' => $base . 'fixture-corrupt-2.0.0.zip',
        'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
        'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
    );
    $value->response['fixture-partial/fixture-partial.php'] = (object) array(
        'id' => 'wordpress.org/plugins/fixture-partial/', 'slug' => 'fixture-partial',
        'plugin' => 'fixture-partial/fixture-partial.php', 'new_version' => '2.0.0',
        'url' => 'https://example.test/fixture-partial', 'package' => $base . 'fixture-partial-2.0.0.zip',
        'icons' => array(), 'banners' => array(), 'banners_rtl' => array(),
        'tested' => '7.1', 'requires_php' => '8.0', 'requires' => '6.2', 'compatibility' => new stdClass(),
    );
    if ( ! isset( $value->translations ) || ! is_array( $value->translations ) ) { $value->translations = array(); }
    $value->translations[] = array(
        'type' => 'plugin', 'slug' => 'fixture-alpha', 'language' => 'fr_FR', 'version' => '1.0.0',
        'updated' => '2026-10-29 00:00:00', 'package' => $base . 'fixture-alpha-fr_FR.zip', 'autoupdate' => true,
    );
    $value->last_checked = time();
    return $value;
} );

add_filter( 'site_transient_update_themes', static function ( $value ) {
    if ( ! is_object( $value ) ) { $value = new stdClass(); }
    if ( ! isset( $value->response ) || ! is_array( $value->response ) ) { $value->response = array(); }
    $base = get_site_url() . '/wp-content/uploads/testbook/';
    $value->response['fixture-theme'] = array(
        'theme' => 'fixture-theme', 'new_version' => '2.0.0', 'url' => 'https://example.test/fixture-theme',
        'package' => $base . 'fixture-theme-2.0.0.zip', 'requires' => '6.2', 'requires_php' => '8.0',
    );
    $value->last_checked = time();
    return $value;
} );

add_filter( 'site_transient_update_core', static function ( $value ) {
    if ( ! is_object( $value ) ) { $value = new stdClass(); }
    if ( ! isset( $value->updates ) || ! is_array( $value->updates ) ) { $value->updates = array(); }
    $base = get_site_url() . '/wp-content/uploads/testbook/';
    foreach ( $value->updates as $update ) {
        if ( isset( $update->version ) && '7.1.3' === $update->version ) { return $value; }
    }
    $value->updates[] = (object) array(
        'response' => 'upgrade', 'download' => $base . 'wordpress-7.1.3.zip', 'locale' => 'en_US',
        'packages' => array( 'full' => $base . 'wordpress-7.1.3.zip', 'no_content' => $base . 'wordpress-7.1.3.zip', 'new_bundled' => $base . 'wordpress-7.1.3.zip', 'partial' => false, 'rollback' => false ),
        'current' => '7.1.4', 'version' => '7.1.3', 'php_version' => '8.1', 'mysql_version' => '5.7', 'new_bundled' => '7.1', 'partial_version' => false,
    );
    $value->last_checked = time();
    return $value;
} );

add_filter( 'site_transient_translation_updates', static function ( $value ) {
    if ( ! is_array( $value ) ) { $value = array(); }
    $base = get_site_url() . '/wp-content/uploads/testbook/';
    foreach ( $value as $update ) {
        if ( isset( $update->slug, $update->language ) && 'fixture-alpha' === $update->slug && 'fr_FR' === $update->language ) { return $value; }
    }
    $value[] = (object) array(
        'type' => 'plugin', 'slug' => 'fixture-alpha', 'language' => 'fr_FR', 'version' => '1.0.0',
        'updated' => '2026-10-29 00:00:00', 'package' => $base . 'fixture-alpha-fr_FR.zip', 'autoupdate' => true,
    );
    return $value;
} );
"""


def plugin_php( name: str, version: str ) -> str:
	return f"<?php\n/*\nPlugin Name: {name}\nVersion: {version}\nRequires PHP: 8.0\n*/\n"


def theme_css( version: str ) -> str:
	return f"/*\nTheme Name: Fixture Theme\nVersion: {version}\nRequires PHP: 8.0\n*/\n"


def make_zip( path: Path, entries: dict[str, bytes] ) -> None:
	path.parent.mkdir( parents=True, exist_ok=True )
	with zipfile.ZipFile( path, "w", zipfile.ZIP_DEFLATED ) as archive:
		for name, data in entries.items():
			archive.writestr( name, data )


def remove_path( path: Path ) -> None:
	if path.is_symlink() or path.is_file():
		path.unlink( missing_ok=True )
	elif path.exists():
		shutil.rmtree( path )


def sync_runtime( site: Path ) -> None:
	for source, target, items in (
		( FREE_ROOT, site / "wp-content/plugins/updatronix", RUNTIME_FREE ),
		( PRO_ROOT, site / "wp-content/plugins/updatronix-pro", RUNTIME_PRO ),
	):
		target.mkdir( parents=True, exist_ok=True )
		for item in items:
			src = source / item
			dst = target / item
			if src.is_dir():
				shutil.copytree( src, dst, dirs_exist_ok=True )
			elif src.exists():
				shutil.copy2( src, dst )


def seed( site: Path ) -> dict:
	sync_runtime( site )
	plugins = site / "wp-content/plugins"
	uploads = site / "wp-content/uploads/testbook"

	unreadable = plugins / "fixture-unreadable"
	if unreadable.exists():
		unreadable.chmod( 0o755 )

	for name in (
		"fixture-alpha",
		"fixture-bravo",
		"fixture-incompatible",
		"fixture-abandoned",
		"fixture-broken",
		"fixture-corrupt",
		"fixture-partial",
	):
		directory = plugins / name
		directory.mkdir( parents=True, exist_ok=True )
		( directory / f"{name}.php" ).write_text( plugin_php( name, "1.0.0" ), encoding="utf-8" )

	locked = plugins / "fixture-locked"
	locked.mkdir( parents=True, exist_ok=True )
	( locked / "fixture-locked.php" ).write_text( plugin_php( "fixture-locked", "1.0.0" ), encoding="utf-8" )
	( locked / ".git" ).mkdir( exist_ok=True )

	for name, marker in ( ( "fixture-svn", ".svn" ), ( "fixture-hg", ".hg" ), ( "fixture-bzr", ".bzr" ) ):
		directory = plugins / name
		directory.mkdir( parents=True, exist_ok=True )
		( directory / f"{name}.php" ).write_text( plugin_php( name, "1.0.0" ), encoding="utf-8" )
		( directory / marker ).mkdir( exist_ok=True )

	composer = plugins / "fixture-composer"
	composer.mkdir( parents=True, exist_ok=True )
	( composer / "fixture-composer.php" ).write_text( plugin_php( "fixture-composer", "1.0.0" ), encoding="utf-8" )
	( composer / "composer.json" ).unlink( missing_ok=True )
	shutil.rmtree( composer / "vendor", ignore_errors=True )

	# A .git marker above the item root, inside the item's own subtree, so the
	# other plugins are not accidentally version-locked.
	ancestor = plugins / "fixture-git-ancestor-parent"
	( ancestor / ".git" ).mkdir( parents=True, exist_ok=True )
	( ancestor / "plugin" ).mkdir( parents=True, exist_ok=True )
	( ancestor / "plugin/fixture-git-ancestor.php" ).write_text(
		plugin_php( "fixture-git-ancestor", "1.0.0" ), encoding="utf-8"
	)

	# Symlinked item roots, one inside WP_PLUGIN_DIR and one beside it.
	inside = plugins / "fixture-symlink-inside"
	remove_path( inside )
	inside.symlink_to( "fixture-alpha", target_is_directory=True )
	outside_target = site / "wp-content/testbook-symlink-target"
	outside_target.mkdir( parents=True, exist_ok=True )
	( outside_target / "fixture-symlink-outside.php" ).write_text(
		plugin_php( "fixture-symlink-outside", "1.0.0" ), encoding="utf-8"
	)
	outside = plugins / "fixture-symlink-outside"
	remove_path( outside )
	outside.symlink_to( "../testbook-symlink-target", target_is_directory=True )

	unreadable.mkdir( parents=True, exist_ok=True )
	( unreadable / "fixture-unreadable.php" ).write_text(
		plugin_php( "fixture-unreadable", "1.0.0" ), encoding="utf-8"
	)

	theme = site / "wp-content/themes/fixture-theme"
	theme.mkdir( parents=True, exist_ok=True )
	( theme / "style.css" ).write_text( theme_css( "1.0.0" ), encoding="utf-8" )
	( theme / "index.php" ).write_text( "<?php\n// Silence is golden.\n", encoding="utf-8" )

	project = site / "wp-content/testbook-composer-project"
	( project / "vendor/composer" ).mkdir( parents=True, exist_ok=True )
	( project / "composer.json" ).write_text(
		'{"name":"fixture/project","config":{"vendor-dir":"vendor"}}', encoding="utf-8"
	)
	( project / "vendor/composer/installed.json" ).write_text(
		json.dumps(
			{
				"packages": [
					{
						"name": "fixture/composer",
						"type": "wordpress-plugin",
						"version": "1.0.0",
						"install-path": f"{CONTAINER}/wp-content/plugins/fixture-composer",
					}
				]
			}
		),
		encoding="utf-8",
	)

	make_zip( uploads / "fixture-alpha-2.0.0.zip", { "fixture-alpha/fixture-alpha.php": plugin_php( "fixture-alpha", "2.0.0" ).encode() } )
	make_zip( uploads / "fixture-incompatible-2.0.0.zip", { "fixture-incompatible/fixture-incompatible.php": plugin_php( "fixture-incompatible", "2.0.0" ).encode() } )
	make_zip( uploads / "fixture-bravo-2.0.0.zip", { "fixture-bravo/fixture-bravo.php": plugin_php( "fixture-bravo", "2.0.0" ).encode() } )
	make_zip( uploads / "fixture-locked-2.0.0.zip", { "fixture-locked/fixture-locked.php": plugin_php( "fixture-locked", "2.0.0" ).encode() } )
	make_zip( uploads / "fixture-broken-2.0.0.zip", { "fixture-broken/readme.txt": b"not a plugin\n" } )
	# A real invalid zip: the bytes are not a ZIP archive at all.
	( uploads / "fixture-corrupt-2.0.0.zip" ).write_bytes( b"this is not a zip archive\n" )
	# A partial package: readme only, no plugin file.
	make_zip( uploads / "fixture-partial-2.0.0.zip", { "fixture-partial/readme.txt": b"not a plugin\n" } )
	make_zip(
		uploads / "fixture-theme-2.0.0.zip",
		{
			"fixture-theme/style.css": theme_css( "2.0.0" ).encode(),
			"fixture-theme/index.php": b"<?php\n// Silence is golden.\n",
		},
	)
	make_zip(
		uploads / "fixture-alpha-fr_FR.zip",
		{ "fixture-alpha-fr_FR.mo": b"", "fixture-alpha-fr_FR.po": b'msgid ""\nmsgstr ""\n' },
	)

	mu = site / "wp-content/mu-plugins/testbook-fixtures.php"
	mu.write_text( MU, encoding="utf-8" )
	seed_file = site / "testbook-seed.php"
	seed_file.write_text( SEED, encoding="utf-8" )
	result = subprocess.run(
		[ "ddev", "wp", "eval-file", "testbook-seed.php" ],
		cwd=site,
		check=True,
		capture_output=True,
		text=True,
	)
	seed_file.unlink()
	return json.loads( result.stdout.strip().splitlines()[-1] )


def ensure_users( site: Path ) -> dict:
	"""Create the role fixture users once; the snapshot keeps them."""
	php = r"""<?php
$out = array();
$role_users = array( 'editor' => 'editor', 'subscriber' => 'subscriber' );
foreach ( $role_users as $login => $role ) {
	$user = get_user_by( 'login', $login );
	if ( $user ) {
		$id = (int) $user->ID;
	} else {
		$id = wp_insert_user( array(
			'user_login' => $login,
			'user_pass' => 'testbook-local',
			'user_email' => $login . '@example.test',
			'role' => $role,
		) );
	}
	if ( is_wp_error( $id ) ) { $out[ $login ] = $id->get_error_code(); continue; }
	$user = new WP_User( $id );
	$user->set_role( $role );
	if ( is_multisite() ) { add_user_to_blog( 1, $id, $role ); }
	$out[ $login ] = $id;
}
if ( is_multisite() ) {
	$login = 'subsite_admin';
	$user  = get_user_by( 'login', $login );
	if ( $user ) {
		$id = (int) $user->ID;
	} else {
		$id = wp_insert_user( array(
			'user_login' => $login,
			'user_pass' => 'testbook-local',
			'user_email' => $login . '@example.test',
			'role' => 'administrator',
		) );
	}
	if ( ! is_wp_error( $id ) ) {
		add_user_to_blog( 2, (int) $id, 'administrator' );
		$out[ $login ] = (int) $id;
	}
}
echo wp_json_encode( $out );
"""
	path = site / "testbook-users.php"
	code = php.lstrip()
	if code.startswith( "<?php" ):
		code = code[5:]
	path.write_text( "<?php\n" + code, encoding="utf-8" )
	try:
		completed = subprocess.run(
			[ "ddev", "wp", "eval-file", "testbook-users.php" ],
			cwd=site,
			check=True,
			capture_output=True,
			text=True,
		)
	finally:
		path.unlink( missing_ok=True )
	return json.loads( completed.stdout.strip().splitlines()[-1] )


def sync_baseline( site: Path ) -> None:
	"""Mirror the fixture-* files into `wp-content/testbook-base`."""
	base = site / "wp-content/testbook-base"
	sources: dict[str, list[Path]] = {
		"plugins": sorted( path for path in ( site / "wp-content/plugins" ).iterdir() if path.name.startswith( "fixture-" ) ),
		"themes": [ site / "wp-content/themes/fixture-theme" ],
		"mu-plugins": sorted( ( site / "wp-content/mu-plugins" ).glob( "testbook-*.php" ) ),
		"uploads": sorted( ( site / "wp-content/uploads/testbook" ).glob( "*" ) ),
	}
	for sub, paths in sources.items():
		target = base / sub
		target.mkdir( parents=True, exist_ok=True )
		names = { path.name for path in paths }
		for existing in list( target.iterdir() ):
			if existing.name not in names:
				remove_path( existing )
		for source in paths:
			destination = target / source.name
			remove_path( destination )
			if source.is_symlink():
				os.symlink( os.readlink( source ), destination )
			elif source.is_dir():
				shutil.copytree( source, destination, symlinks=True )
			else:
				shutil.copy2( source, destination )


def apply_unreadable( site: Path ) -> None:
	( site / "wp-content/plugins/fixture-unreadable" ).chmod( 0o000 )


def refresh_snapshot( site: Path, name: str ) -> None:
	subprocess.run(
		[ "ddev", "snapshot", "--cleanup", "--name", name, "-y" ],
		cwd=site,
		capture_output=True,
		text=True,
		check=False,
	)
	completed = subprocess.run(
		[ "ddev", "snapshot", "--name", name, "-y" ],
		cwd=site,
		capture_output=True,
		text=True,
	)
	if completed.returncode != 0:
		raise RuntimeError(
			f"could not refresh snapshot {name}: "
			f"{completed.stderr.strip() or completed.stdout.strip()}"
		)


def main() -> int:
	parser = argparse.ArgumentParser( description=__doc__ )
	parser.add_argument( "--refresh-snapshots", action="store_true", help="rebuild testbook-base2 and testbook-ms" )
	parser.add_argument( "--site", action="append", help="only seed this project path" )
	args = parser.parse_args()

	sites = SITES
	if args.site:
		wanted = { str( Path( value ).resolve() ) for value in args.site }
		sites = [ site for site in SITES if str( site.resolve() ) in wanted ]

	versions = {}
	for index, site in enumerate( sites ):
		result = seed( site )
		users = ensure_users( site )
		sync_baseline( site )
		apply_unreadable( site )
		print( site.name, result, "users", users )
		if index == 0:
			plugins = json.loads(
				subprocess.run(
					[ "ddev", "wp", "plugin", "list", "--format=json" ],
					cwd=site,
					check=True,
					capture_output=True,
					text=True,
				).stdout
			)
			themes = json.loads(
				subprocess.run(
					[ "ddev", "wp", "theme", "list", "--format=json" ],
					cwd=site,
					check=True,
					capture_output=True,
					text=True,
				).stdout
			)
			versions = {
				"plugins": {
					plugin["name"]: plugin["version"]
					for plugin in plugins
					if plugin["name"] in { "woocommerce", "contact-form-7", "wordpress-seo", "akismet" }
				},
				"themes": {
					theme["name"]: theme["version"]
					for theme in themes
					if theme["name"] in { "twentytwentyfour", "twentytwentyone" }
				},
			}
		if args.refresh_snapshots:
			refresh_snapshot( site, SNAPSHOTS[ site.name ] )
			print( f"refreshed snapshot {SNAPSHOTS[site.name]}" )

	OUT.write_text(
		yaml.safe_dump(
			{ "schema": "updatronix-testbook/community-versions/v1", "provisioned": "2026-10-29", **versions },
			sort_keys=False,
		),
		encoding="utf-8",
	)
	print( f"wrote {OUT}" )
	return 0


if __name__ == "__main__":
	sys.exit( main() )
