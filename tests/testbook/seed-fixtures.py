#!/usr/bin/env python3
"""Seed the deterministic fixture tier on the two DDEV test sites."""

from __future__ import annotations

import json
import subprocess
import sys
import zipfile
from pathlib import Path

import yaml

SITES = [
    Path("/home/quentin/_DDEV/updatronix-test"),
    Path("/home/quentin/_DDEV/updatronix-ms"),
]
CONTAINER = "/var/www/html"
OUT = Path("/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix/tests/testbook/community-versions.yaml")

SEED = r"""<?php
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
$tr[] = (object) array(
    'type' => 'plugin', 'slug' => 'fixture-alpha', 'language' => 'fr_FR', 'version' => '2.0.0',
    'updated' => '2026-10-29 00:00:00', 'package' => $base . 'fixture-alpha-fr_FR.zip', 'autoupdate' => true,
);
set_site_transient( 'translation_updates', $tr );
echo wp_json_encode( array( 'plugins' => array_keys( $p->response ), 'themes' => array_keys( $t->response ), 'translations' => count( $tr ) ) );
"""

MU = r"""<?php
add_filter( 'updatronix_pro_abandoned_data', static function ( $data, $type, $id ) {
    if ( 'plugin' === $type && 0 === strpos( (string) $id, 'fixture-abandoned/' ) ) {
        return array( 'last_updated' => '2020-01-01 00:00:00' );
    }
    return $data;
}, 10, 3 );
"""


def plugin_php(name: str, version: str) -> str:
    return f"<?php\n/*\nPlugin Name: {name}\nVersion: {version}\nRequires PHP: 8.0\n*/\n"


def theme_css(version: str) -> str:
    return f"/*\nTheme Name: Fixture Theme\nVersion: {version}\nRequires PHP: 8.0\n*/\n"


def make_zip(path: Path, entries: dict[str, bytes]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(path, "w", zipfile.ZIP_DEFLATED) as archive:
        for name, data in entries.items():
            archive.writestr(name, data)


def seed(site: Path) -> dict:
    plugins = site / "wp-content/plugins"
    uploads = site / "wp-content/uploads/testbook"
    for name in ("fixture-alpha", "fixture-bravo", "fixture-incompatible", "fixture-abandoned"):
        directory = plugins / name
        directory.mkdir(parents=True, exist_ok=True)
        (directory / f"{name}.php").write_text(plugin_php(name, "1.0.0"), encoding="utf-8")
    theme = site / "wp-content/themes/fixture-theme"
    theme.mkdir(parents=True, exist_ok=True)
    (theme / "style.css").write_text(theme_css("1.0.0"), encoding="utf-8")
    for name, marker in (("fixture-svn", ".svn"), ("fixture-hg", ".hg"), ("fixture-bzr", ".bzr")):
        directory = plugins / name
        directory.mkdir(parents=True, exist_ok=True)
        (directory / f"{name}.php").write_text(plugin_php(name, "1.0.0"), encoding="utf-8")
        (directory / marker).mkdir(exist_ok=True)
    composer = plugins / "fixture-composer"
    (composer / "vendor/composer").mkdir(parents=True, exist_ok=True)
    (composer / "fixture-composer.php").write_text(plugin_php("fixture-composer", "1.0.0"), encoding="utf-8")
    (composer / "composer.json").write_text('{"name":"fixture/project","config":{"vendor-dir":"vendor"}}', encoding="utf-8")
    (composer / "vendor/composer/installed.json").write_text(
        json.dumps({"packages": [{"name": "fixture/composer", "type": "wordpress-plugin", "version": "1.0.0", "install-path": f"{CONTAINER}/wp-content/plugins/fixture-composer"}]}),
        encoding="utf-8",
    )
    make_zip(uploads / "fixture-alpha-2.0.0.zip", {"fixture-alpha/fixture-alpha.php": plugin_php("fixture-alpha", "2.0.0").encode()})
    make_zip(uploads / "fixture-incompatible-2.0.0.zip", {"fixture-incompatible/fixture-incompatible.php": plugin_php("fixture-incompatible", "2.0.0").encode()})
    make_zip(uploads / "fixture-theme-2.0.0.zip", {"fixture-theme/style.css": theme_css("2.0.0").encode()})
    make_zip(uploads / "fixture-alpha-fr_FR.zip", {"fixture-alpha-fr_FR.mo": b"", "fixture-alpha-fr_FR.po": b'msgid ""\nmsgstr ""\n'})
    mu = site / "wp-content/mu-plugins/testbook-fixtures.php"
    mu.write_text(MU, encoding="utf-8")
    seed_file = site / "testbook-seed.php"
    seed_file.write_text(SEED, encoding="utf-8")
    result = subprocess.run(["ddev", "wp", "eval-file", "testbook-seed.php"], cwd=site, check=True, capture_output=True, text=True)
    seed_file.unlink()
    return json.loads(result.stdout.strip().splitlines()[-1])


def main() -> int:
    versions = {}
    for index, site in enumerate(SITES):
        result = seed(site)
        print(site.name, result)
        if index == 0:
            plugins = json.loads(subprocess.run(["ddev", "wp", "plugin", "list", "--format=json"], cwd=site, check=True, capture_output=True, text=True).stdout)
            themes = json.loads(subprocess.run(["ddev", "wp", "theme", "list", "--format=json"], cwd=site, check=True, capture_output=True, text=True).stdout)
            versions = {
                "plugins": {p["name"]: p["version"] for p in plugins if p["name"] in {"woocommerce", "contact-form-7", "wordpress-seo", "akismet"}},
                "themes": {t["name"]: t["version"] for t in themes if t["name"] in {"twentytwentyfour", "twentytwentyone"}},
            }
    OUT.write_text(yaml.safe_dump({"schema": "updatronix-testbook/community-versions/v1", "provisioned": "2026-10-29", **versions}, sort_keys=False), encoding="utf-8")
    print(f"wrote {OUT}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
