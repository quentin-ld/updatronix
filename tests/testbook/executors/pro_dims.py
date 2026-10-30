#!/usr/bin/env python3
"""Pro dimension executors: White Label first, then pass/shield/flow/license."""

from __future__ import annotations

import json

from testbook_site import ROOT, Site, base_observation, php_json, php_list

from . import CaseSpec
from .dimensions import _a11y_metrics, _run_playwright
from .live import (
	PLUGIN_ALPHA,
	PLUGIN_BROKEN,
	PLUGIN_INCOMPATIBLE,
	item_rows,
	mail,
	ok,
	pass_rows,
	pump,
	reset_item,
	version,
)
from .shield import shield_notify_matrix
from .vlock import label_matrix
from .export_dims import export_columns, export_empty, export_format, export_merge

WHITE_LABEL = "/updatronix-pro/v1/white-label"


def _wl_get( site: Site ) -> dict:
	return site.rest( "GET", WHITE_LABEL, login="testbook" )


def _wl_save( site: Site, payload: dict ) -> dict:
	return site.rest( "POST", WHITE_LABEL, payload, login="testbook" )


def _wl_settings( site: Site ) -> dict:
	return site.json_eval(
		"<?php echo wp_json_encode( Updatronix_White_Label::get_settings() );"
	)


def _brand( site: Site ) -> dict:
	return site.json_eval(
		r"""<?php
echo wp_json_encode(
	array(
		'filtered' => Updatronix_White_Label::filter_brand(
			array(
				'name'      => 'Updatronix',
				'menu_label' => 'Updatronix',
				'logoUrl'   => 'default-logo.png',
				'color'     => '#000000',
				'menuColor' => '#000000',
			)
		),
		'menu'    => Updatronix_White_Label::filter_menu_label( 'Updatronix' ),
		'email'   => Updatronix_White_Label::filter_email_brand_name( 'Updatronix' ),
	)
);
"""
	)


WL_DEFAULTS = {
	"brand_name": "",
	"menu_label": "",
	"logo_id": 0,
	"brand_color": "#3858E9",
	"menu_color": "#1E1E1E",
	"replace_branding": False,
	"apply_to_emails": False,
}


def _wl_surface( site: Site ) -> tuple[dict, dict, dict]:
	a11y_path = f".cache/testbook/a11y-{site.name}-white-label.json"
	links_path = f".cache/testbook/links-{site.name}-white-label.json"
	stdout = _run_playwright(
		site,
		"tests/testbook/playwright-surfaces.config.js",
		{
			"TESTBOOK_SURFACE": "white-label",
			"TESTBOOK_A11Y_PATH": a11y_path,
			"TESTBOOK_LINKS_PATH": links_path,
		},
	)
	target = ROOT / links_path
	if not target.exists():
		raise RuntimeError( f"white-label metrics were not written: {links_path}" )
	metrics = json.loads( target.read_text( encoding="utf-8" ) )
	a11y = _a11y_metrics( a11y_path )
	data = base_observation( site.observe() )
	data.update( { "http_status": 200, "fatal": False } )
	for key in ( "controls", "reachable", "labelled", "focus_visible", "overflow" ):
		data[ key ] = a11y[ key ]
	return data, metrics, { "playwright": stdout.splitlines()[-3:], "a11y": a11y }


def _save_brand( site: Site ) -> dict:
	return _wl_save(
		site,
		{
			"replace_branding": True,
			"brand_name": "Acme Updates",
			"menu_label": "Acme Tools",
			"brand_color": "#123456",
			"menu_color": "#654321",
			"apply_to_emails": True,
		},
	)


def white_label_surface( site: Site ) -> tuple[dict, dict]:
	"""TB-UI-010: the real surface, its previews, and the bad-logo rejection."""
	reset_item( site )
	saved = _save_brand( site )
	ok( saved["status"] == 200, f"the brand save failed: {saved}" )
	data, metrics, detail = _wl_surface( site )
	body = metrics["body"]
	lower = body.lower()
	for marker in ( "brand name", "menu label", "logo", "preview", "reset" ):
		ok( marker in lower, f"the white label surface is missing {marker}: {body[:600]}" )
	ok( "Acme Updates" in body, f"the preview does not show the saved brand: {body[:600]}" )
	ok( "admin menu" in lower and "email" in lower, "the preview is missing a panel" )
	ok(
		"[Acme Updates] Safe mode blocked automatic updates" in body,
		f"the email preview is not branded: {body[:600]}",
	)
	ok( metrics["resetButtons"] == 1, f"the reset control count is {metrics['resetButtons']}" )
	ok( not data["overflow"], "the white label surface overflows" )

	created: list[int] = []
	try:
		svg = _make_attachment( site, 128, 128, "svg" )
		created.append( int( svg["id"] ) )
		rejected = _wl_save( site, { "logo_id": svg["id"] } )
		ok( rejected["status"] == 400, f"the SVG logo was accepted: {rejected}" )
	finally:
		for attachment in created:
			site.eval_php( f"<?php wp_delete_attachment( {attachment}, true ); echo 'ok';" )
	return data, { **detail, "saved": saved["data"]["settings"], "markers": body[:200] }


def white_label_lifecycle( site: Site ) -> tuple[dict, dict]:
	"""TB-PRO-006: save, reset, sanitize, preview, permissions, and license."""
	reset_item( site )
	saved = _save_brand( site )
	ok( saved["status"] == 200, f"the brand save failed: {saved}" )
	data, metrics, detail = _wl_surface( site )
	body = metrics["body"]
	ok(
		"Acme Updates" in body
		and "admin menu" in body.lower()
		and "[Acme Updates] Safe mode blocked automatic updates" in body,
		f"the preview does not show the email brand: {body[:600]}",
	)

	editor = _wl_get( site )
	editor_as = site.rest( "GET", WHITE_LABEL, login="editor" )
	ok( editor_as["status"] == 403, f"the editor reached white label: {editor_as}" )
	editor_post = site.rest( "POST", WHITE_LABEL, { "brand_name": "Editor" }, login="editor" )
	ok( editor_post["status"] == 403, f"the editor saved white label: {editor_post}" )

	site.set_license( "inactive" )
	try:
		no_license = _wl_get( site )
	finally:
		site.set_license( "active", "TB-FIXTURE-KEY" )
	ok(
		no_license["status"] in ( 403, 404 ),
		f"white label is readable without a license: {no_license}",
	)
	ok( editor["status"] == 200, f"the administrator cannot read white label: {editor}" )

	markup = _wl_save(
		site,
		{ "brand_name": '<b>Acme</b><script>alert(1)</script>', "menu_label": "<i>Tools</i>" },
	)
	ok( markup["status"] == 200, f"the markup save failed: {markup}" )
	ok( "<" not in markup["data"]["settings"]["brand_name"], f"markup survived the brand: {markup}" )

	created: list[int] = []
	try:
		svg = _make_attachment( site, 128, 128, "svg" )
		created.append( int( svg["id"] ) )
		rejected = _wl_save( site, { "logo_id": svg["id"] } )
		ok( rejected["status"] == 400, f"the invalid logo was accepted: {rejected}" )
	finally:
		for attachment in created:
			site.eval_php( f"<?php wp_delete_attachment( {attachment}, true ); echo 'ok';" )

	reset = _wl_save( site, WL_DEFAULTS )
	ok( reset["status"] == 200, f"the reset failed: {reset}" )
	ok( reset["data"]["settings"] == { **WL_DEFAULTS }, f"the reset left {reset['data']['settings']}" )
	return data, {
		**detail,
		"saved": saved["data"]["settings"],
		"reset": reset["data"]["settings"],
		"editor": editor_as["status"],
		"no_license": no_license["status"],
	}


def white_label_save_reset( site: Site ) -> tuple[dict, dict]:
	"""TB-WL-001: save, reset, invalid logos, sanitized markup, and preview."""
	reset_item( site )
	saved = _save_brand( site )
	ok( saved["status"] == 200, f"the brand save failed: {saved}" )
	settings = saved["data"]["settings"]
	ok( settings["brand_name"] == "Acme Updates", f"the brand is {settings['brand_name']}" )
	ok( settings["menu_label"] == "Acme Tools", f"the menu label is {settings['menu_label']}" )
	ok( settings["brand_color"] == "#123456", f"the brand color is {settings['brand_color']}" )
	ok( settings["menu_color"] == "#654321", f"the menu color is {settings['menu_color']}" )

	markup = _wl_save( site, { "brand_name": "<em>Acme</em>", "menu_label": "<b>Tools</b>" } )
	ok( markup["status"] == 200, f"the markup save failed: {markup}" )
	ok( markup["data"]["settings"]["brand_name"] == "Acme", f"the brand is {markup['data']['settings']['brand_name']}" )
	ok( markup["data"]["settings"]["menu_label"] == "Tools", f"the menu is {markup['data']['settings']['menu_label']}" )

	created: list[int] = []
	try:
		small = _make_attachment( site, 60, 60, "png" )
		created.append( int( small["id"] ) )
		bad = _wl_save( site, { "logo_id": small["id"] } )
		ok( bad["status"] == 400, f"the 60px logo was accepted: {bad}" )
	finally:
		for attachment in created:
			site.eval_php( f"<?php wp_delete_attachment( {attachment}, true ); echo 'ok';" )

	data, metrics, detail = _wl_surface( site )
	ok( "Acme" in metrics["body"], f"the preview does not show the saved brand: {metrics['body'][:400]}" )
	ok(
		"admin menu" in metrics["body"].lower(),
		f"the preview does not show the admin menu: {metrics['body'][:400]}",
	)

	reset = _wl_save( site, WL_DEFAULTS )
	ok( reset["status"] == 200, f"the reset failed: {reset}" )
	ok( reset["data"]["settings"] == { **WL_DEFAULTS }, f"the reset left {reset['data']['settings']}" )
	ok( reset["data"]["logo_url"] == "", f"the reset kept a logo: {reset['data']['logo_url']}" )
	return data, { **detail, "saved": settings, "reset": reset["data"]["settings"] }


def white_label_enabled( site: Site ) -> tuple[dict, dict]:
	"""TB-WL-104: the master switch gates every brand filter."""
	reset_item( site )
	off = _brand( site )
	ok( off["filtered"]["name"] == "Updatronix", f"the filter changed the brand while off: {off}" )
	ok( off["filtered"].get( "replace" ) is not True, f"the filter replaced the brand while off: {off}" )
	ok( off["menu"] == "Updatronix", f"the menu label changed while off: {off}" )
	ok( off["email"] == "Updatronix", f"the email brand changed while off: {off}" )

	saved = _wl_save(
		site,
		{
			"replace_branding": True,
			"brand_name": "Acme Updates",
			"menu_label": "Acme Tools",
			"brand_color": "#123456",
			"menu_color": "#654321",
		},
	)
	ok( saved["status"] == 200, f"the white label save failed: {saved}" )
	ok( saved["data"]["settings"]["replace_branding"] is True, f"the switch did not stick: {saved}" )
	on = _brand( site )
	ok( on["filtered"]["name"] == "Acme Updates", f"the brand name is {on['filtered']['name']}" )
	ok( on["filtered"]["menu_label"] == "Acme Tools", f"the menu label is {on['filtered'].get('menu_label')}" )
	ok( on["filtered"]["color"] == "#123456", f"the brand color is {on['filtered']['color']}" )
	ok( on["filtered"]["menuColor"] == "#654321", f"the menu color is {on['filtered']['menuColor']}" )
	ok( on["filtered"].get( "replace" ) is True, f"the replace flag is {on['filtered'].get('replace')}" )
	ok( on["menu"] == "Acme Tools", f"the menu filter is {on['menu']}" )

	disabled = _wl_save( site, { "replace_branding": False } )
	ok( disabled["status"] == 200, f"the disable save failed: {disabled}" )
	back = _brand( site )
	ok( back["filtered"]["name"] == "Updatronix", f"the brand stayed replaced: {back}" )
	return base_observation( site.observe() ), { "off": off, "on": on, "saved": saved }


def white_label_brand( site: Site ) -> tuple[dict, dict]:
	"""TB-WL-101: the brand name is trimmed, stripped, and capped at 60."""
	reset_item( site )
	long_name = "A" * 61
	capped = _wl_save( site, { "replace_branding": True, "brand_name": long_name } )
	ok( capped["status"] == 200, f"the capped save failed: {capped}" )
	ok( len( capped["data"]["settings"]["brand_name"] ) == 60, f"the brand name is {len(capped['data']['settings']['brand_name'])} characters" )

	markup = _wl_save(
		site,
		{ "brand_name": '<b>Acme</b><script>alert(1)</script>', "menu_label": '<i>Tools</i>' },
	)
	ok( markup["status"] == 200, f"the markup save failed: {markup}" )
	brand = markup["data"]["settings"]["brand_name"]
	ok( "<" not in brand and ">" not in brand, f"markup survived the brand name: {brand}" )
	ok( "Acme" in brand, f"the brand name lost its text: {brand}" )
	menu = markup["data"]["settings"]["menu_label"]
	ok( "<" not in menu and ">" not in menu, f"markup survived the menu label: {menu}" )

	scalar = _wl_save( site, { "brand_name": [ "not", "scalar" ] } )
	ok( scalar["status"] == 200, f"the non-scalar save failed: {scalar}" )
	ok( scalar["data"]["settings"]["brand_name"] == "", f"a non-scalar name was accepted: {scalar}" )

	empty = _wl_save( site, { "brand_name": "" } )
	ok( empty["status"] == 200, f"the empty save failed: {empty}" )
	ok( empty["data"]["settings"]["brand_name"] == "", f"an empty name was stored: {empty}" )
	filtered = _brand( site )
	ok( filtered["filtered"]["name"] == "Updatronix", f"an empty brand name replaced the default: {filtered}" )
	return base_observation( site.observe() ), { "capped": capped["data"]["settings"], "markup": markup["data"]["settings"] }


def white_label_colors( site: Site ) -> tuple[dict, dict]:
	"""TB-WL-102: hex colors are normalized and invalid values fall back."""
	reset_item( site )
	short = _wl_save( site, { "replace_branding": True, "brand_color": "#abc", "menu_color": "#ABC" } )
	ok( short["status"] == 200, f"the short-hex save failed: {short}" )
	ok( short["data"]["settings"]["brand_color"] == "#ABC", f"the short hex is {short['data']['settings']['brand_color']}" )
	ok( short["data"]["settings"]["menu_color"] == "#ABC", f"the menu short hex is {short['data']['settings']['menu_color']}" )

	invalid = _wl_save( site, { "brand_color": "red", "menu_color": "#12345" } )
	ok( invalid["status"] == 200, f"the invalid-color save failed: {invalid}" )
	ok( invalid["data"]["settings"]["brand_color"] == "#3858E9", f"an invalid brand color was stored: {invalid}" )
	ok( invalid["data"]["settings"]["menu_color"] == "#1E1E1E", f"an invalid menu color was stored: {invalid}" )

	valid = _wl_save( site, { "brand_color": "#123456", "menu_color": "#654321" } )
	ok( valid["status"] == 200, f"the valid-color save failed: {valid}" )
	filtered = _brand( site )
	ok( filtered["filtered"]["color"] == "#123456", f"the brand color is {filtered['filtered']['color']}" )
	ok( filtered["filtered"]["menuColor"] == "#654321", f"the menu color is {filtered['filtered']['menuColor']}" )
	return base_observation( site.observe() ), { "short": short["data"]["settings"], "invalid": invalid["data"]["settings"], "valid": valid["data"]["settings"] }


def white_label_email( site: Site ) -> tuple[dict, dict]:
	"""TB-WL-103: the brand reaches email only with both switches on."""
	reset_item( site )
	off = _wl_save( site, { "replace_branding": True, "apply_to_emails": False, "brand_name": "Acme Updates" } )
	ok( off["status"] == 200, f"the email-off save failed: {off}" )
	off_brand = _brand( site )
	ok( off_brand["email"] == "Updatronix", f"email was branded while off: {off_brand}" )

	on = _wl_save( site, { "apply_to_emails": True } )
	ok( on["status"] == 200, f"the email-on save failed: {on}" )
	on_brand = _brand( site )
	ok( on_brand["email"] == "Acme Updates", f"email was not branded: {on_brand}" )

	replace_off = _wl_save( site, { "replace_branding": False, "apply_to_emails": True } )
	ok( replace_off["status"] == 200, f"the replace-off save failed: {replace_off}" )
	plain = _brand( site )
	ok( plain["email"] == "Updatronix", f"email was branded without replace_branding: {plain}" )
	return base_observation( site.observe() ), { "off": off_brand, "on": on_brand, "plain": plain }


def _make_attachment( site: Site, width: int, height: int, kind: str = "png" ) -> int:
	return site.json_eval(
		f"""<?php
require_once ABSPATH . 'wp-admin/includes/image.php';
wp_set_current_user( get_user_by( 'login', 'testbook' )->ID );
$uploads = wp_upload_dir();
$path    = $uploads['path'] . '/tb-logo-{kind}-{width}x{height}.{kind}';
if ( 'svg' === '{kind}' ) {{
	file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}"></svg>' );
	$mime = 'image/svg+xml';
}} else {{
	$image = imagecreatetruecolor( {width}, {height} );
	if ( 'big' === '{kind}' ) {{
		for ( $x = 0; $x < {width}; $x++ ) {{
			for ( $y = 0; $y < {height}; $y++ ) {{
				imagesetpixel( $image, $x, $y, mt_rand( 0, 0xFFFFFF ) );
			}}
		}}
	}}
	imagepng( $image, $path );
	imagedestroy( $image );
	$mime = 'image/png';
}}
$id = wp_insert_attachment(
	array(
		'post_mime_type' => $mime,
		'post_title'     => 'TB white label logo',
		'post_status'    => 'inherit',
	),
	$path
);
if ( is_wp_error( $id ) ) {{
	echo wp_json_encode( array( 'error' => $id->get_error_message() ) );
	return;
}}
wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
echo wp_json_encode(
	array(
		'id'     => (int) $id,
		'valid'  => Updatronix_White_Label::validate_logo_id( (int) $id ),
		'width'  => {width},
		'height' => {height},
		'bytes'  => (int) filesize( $file = get_attached_file( $id ) ),
	)
);
"""
	)


def white_label_logo( site: Site ) -> tuple[dict, dict]:
	"""TB-WL-105: PNG is accepted; SVG, tiny, and oversized files are rejected."""
	reset_item( site )
	created: list[int] = []
	try:
		valid = _make_attachment( site, 128, 128, "png" )
		created.append( int( valid["id"] ) )
		ok( valid["valid"] is True, f"a valid PNG was rejected: {valid}" )

		svg = _make_attachment( site, 128, 128, "svg" )
		created.append( int( svg["id"] ) )
		ok( svg["valid"] is False, f"an SVG was accepted: {svg}" )

		tiny = _make_attachment( site, 60, 60, "png" )
		created.append( int( tiny["id"] ) )
		ok( tiny["valid"] is False, f"a 60x60 logo was accepted: {tiny}" )

		big = _make_attachment( site, 1200, 1200, "big" )
		created.append( int( big["id"] ) )
		ok( big["valid"] is False, f"an oversized logo was accepted: {big}" )
		ok( big["bytes"] > 1048576, f"the oversized fixture is only {big['bytes']} bytes" )

		saved = _wl_save( site, { "logo_id": valid["id"] } )
		ok( saved["status"] == 200, f"the logo save failed: {saved}" )
		ok( int( saved["data"]["settings"]["logo_id"] ) == int( valid["id"] ), f"the logo id is {saved['data']['settings']['logo_id']}" )
		ok( bool( saved["data"]["logo_url"] ), f"the logo URL is empty: {saved['data']}" )

		rejected = _wl_save( site, { "logo_id": svg["id"] } )
		ok( rejected["status"] == 400, f"the SVG id was accepted by the route: {rejected}" )
		ok( "updatronix_invalid_logo" == ( rejected["data"].get( "code" ) if isinstance( rejected["data"], dict ) else "" ), f"the rejection reason is {rejected['data']}" )

		sanitized = site.json_eval(
			f"""<?php
$clean = Updatronix_White_Label::sanitize_settings(
	array( 'logo_id' => {int(svg['id'])} ),
	array( 'logo_id' => {int(valid['id'])} )
);
echo wp_json_encode( $clean );
"""
		)
		ok( int( sanitized["logo_id"] ) == 0, f"an invalid logo survived sanitization: {sanitized}" )
	finally:
		for attachment in created:
			site.eval_php( f"<?php wp_delete_attachment( {attachment}, true ); echo 'ok';" )
	return base_observation( site.observe() ), { "valid": valid, "svg": svg, "tiny": tiny, "big": big, "saved": saved["data"] }


def white_label_menu_label( site: Site ) -> tuple[dict, dict]:
	"""TB-WL-106: the menu label is capped at 40 and only replaces when on."""
	reset_item( site )
	long_label = "M" * 41
	capped = _wl_save( site, { "replace_branding": True, "menu_label": long_label } )
	ok( capped["status"] == 200, f"the capped save failed: {capped}" )
	ok( len( capped["data"]["settings"]["menu_label"] ) == 40, f"the menu label is {len(capped['data']['settings']['menu_label'])} characters" )
	applied = _brand( site )
	ok( applied["menu"] == "M" * 40, f"the menu filter is {applied['menu']}" )
	ok( "menu_label" in applied["filtered"] and applied["filtered"]["menu_label"] == "M" * 40, f"the brand payload is {applied['filtered']}" )

	off = _wl_save( site, { "replace_branding": False } )
	ok( off["status"] == 200, f"the disable save failed: {off}" )
	plain = _brand( site )
	ok( plain["menu"] == "Updatronix", f"the menu label stayed replaced: {plain}" )
	return base_observation( site.observe() ), { "capped": capped["data"]["settings"], "applied": applied, "plain": plain }


def _lock( site: Site, held: bool ) -> None:
	site.eval_php(
		"<?php "
		+ ( "update_option( 'auto_updater.lock', time() );" if held else "delete_option( 'auto_updater.lock' );" )
		+ " echo 'ok';"
	)


def _pass_state( site: Site ) -> dict:
	return site.json_eval(
		r"""<?php
$pass = Updatronix_Update_Pass::load();
echo wp_json_encode(
	array(
		'exists'     => null !== $pass,
		'expired'    => null !== $pass ? $pass->is_expired() : null,
		'lock_waits' => null !== $pass ? $pass->lock_waits() : null,
		'order'      => null !== $pass ? $pass->order() : array(),
		'nodes'      => null !== $pass ? $pass->nodes() : array(),
		'last'       => Updatronix_Update_Pass::get_last_pass(),
	)
);
"""
	)


def pass_lock_matrix( site: Site ) -> tuple[dict, dict]:
	"""TB-PASS-103: clear, busy-first, busy-retry, and busy-give-up."""
	reset_item( site )
	_lock( site, False )
	result, _ = pump( site, [ PLUGIN_ALPHA ] )
	clear = version( result, PLUGIN_ALPHA )
	ok( clear == "2.0.0", "the clear-lock pass did not install the item" )

	reset_item( site )
	seeded = site.seed_pass( [ PLUGIN_ALPHA ] )
	ok( seeded.get( "created" ), f"the pass was not seeded: {seeded}" )
	_lock( site, True )
	waits = []
	try:
		site.pass_follow_up( [ PLUGIN_ALPHA ] )
		state = _pass_state( site )
		waits.append( state["lock_waits"] )
		ok( state["exists"] is True, "the busy pass disappeared after the first wait" )
		ok( state["lock_waits"] == 1, f"the first wait count is {state['lock_waits']}" )
		ok( site.plugin_version( "fixture-alpha" ) == "1.0.0", "an item installed while the lock was busy" )

		for _ in range( 2 ):
			site.pass_follow_up( [ PLUGIN_ALPHA ] )
			state = _pass_state( site )
			waits.append( state["lock_waits"] )
	finally:
		_lock( site, False )
	ok( waits[0] == 1 and waits[1] == 2, f"the wait counters are {waits}" )
	gave_up = _pass_state( site )
	ok( gave_up["exists"] is False, f"the pass is still active after the wait limit: {gave_up}" )
	summary = gave_up["last"]
	ok( int( summary.get( "counts", {} ).get( "skipped", 0 ) ) == 1, f"the skip is not counted: {summary}" )
	ok(
		summary.get( "status" ) in ( "completed", "incomplete" ),
		f"the skipped pass is {summary.get('status')}",
	)
	obs = site.observe()
	rows = item_rows( obs, [ "fixture-alpha" ] )
	ok( any( row.get( "reason_code" ) == "native_lock_busy" for row in rows ), f"the lock wait row is missing: {rows}" )
	ok( not [ row for row in rows if row.get( "status" ) == "failed" ], f"the lock wait was recorded as a failure: {rows}" )

	site.set_auto_update( plugins=[ PLUGIN_ALPHA ] )
	recovered = site.json_eval(
		r"""<?php
Updatronix_Item_State::reset_cache();
$built = Updatronix_Update_Pass_Order::build();
echo wp_json_encode( array( 'node' => $built['nodes']['plugin:fixture-alpha/fixture-alpha.php'] ?? array() ) );
"""
	)
	ok(
		recovered["node"].get( "state" ) == "offer_eligible",
		f"the skipped item is not eligible for the next pass: {recovered}",
	)
	return base_observation( site.observe() ), {
		"clear": clear,
		"waits": waits,
		"give_up": gave_up,
		"summary": summary,
	}


def pass_lock_retry( site: Site ) -> tuple[dict, dict]:
	"""TB-PASS-002: a busy lock waits, then the retry installs once."""
	reset_item( site )
	seeded = site.seed_pass( [ PLUGIN_ALPHA ] )
	ok( seeded.get( "created" ), f"the pass was not seeded: {seeded}" )
	_lock( site, True )
	try:
		site.pass_follow_up( [ PLUGIN_ALPHA ] )
		busy = _pass_state( site )
		ok( busy["exists"] is True, "the held pass disappeared" )
		ok( busy["lock_waits"] == 1, f"the wait count is {busy['lock_waits']}" )
	finally:
		_lock( site, False )
	site.set_auto_update( plugins=[ PLUGIN_ALPHA ] )
	site.pass_follow_up( [ PLUGIN_ALPHA ] )
	ok( site.plugin_version( "fixture-alpha" ) == "2.0.0", "the retry did not install the item" )
	obs = site.observe()
	rows = item_rows( obs, [ "fixture-alpha" ] )
	success_rows = [ row for row in rows if row.get( "status" ) == "success" ]
	ok( len( success_rows ) == 1, f"the retry duplicated the item row: {rows}" )
	ok( len( pass_rows( obs ) ) == 1, f"the retry wrote {len(pass_rows(obs))} pass rows" )
	last = _pass_state( site )["last"]
	ok( last.get( "status" ) == "completed", f"the retry pass is {last.get('status')}" )
	return base_observation( obs ), { "busy": busy, "after": last, "rows": rows }


def pass_retry_next( site: Site ) -> tuple[dict, dict]:
	"""TB-PASS-003: a failed item is retried on the next pass only."""
	reset_item( site )
	first, obs = pump( site, [ PLUGIN_ALPHA, PLUGIN_BROKEN ], max_requests=4 )
	ok( version( first, PLUGIN_ALPHA ) == "2.0.0", "the healthy item did not install" )
	ok( version( first, PLUGIN_BROKEN ) == "1.0.0", "the broken item installed" )
	ok( first["last"].get( "status" ) == "partial", f"the first pass is {first['last'].get('status')}" )
	ok(
		int( first["last"].get( "counts", {} ).get( "failed", 0 ) ) >= 1,
		f"the first pass did not count the failure: {first['last']}",
	)

	second = site.pump_pass( keep=[ PLUGIN_ALPHA, PLUGIN_BROKEN ], max_requests=3 )
	ok( version( second, PLUGIN_BROKEN ) == "1.0.0", "the retry installed the broken item" )
	selected = [ entry.get( "selected" ) for entry in second["trace"] ]
	ok( "plugin:" + PLUGIN_BROKEN in selected, f"the broken item was not retried: {selected}" )
	ok( "plugin:" + PLUGIN_ALPHA not in selected, f"the healthy item was retried: {selected}" )
	ok( version( second, PLUGIN_ALPHA ) == "2.0.0", "the healthy item changed on the retry" )
	obs = site.observe()
	ok( len( [ row for row in item_rows( obs, [ "fixture-alpha" ] ) if row.get( "status" ) == "success" ] ) == 1, "the healthy item has duplicate success rows" )
	return base_observation( obs ), { "first": first["last"], "second": second["last"], "selected": selected }


def pass_last_pass( site: Site ) -> tuple[dict, dict]:
	"""TB-PASS-102: absent, present, expired, and logging-disabled."""
	reset_item( site )
	absent = site.json_eval(
		"<?php echo wp_json_encode( array( 'last' => Updatronix_Update_Pass::get_last_pass(), 'delete' => Updatronix_Update_Pass::delete_last_pass() ) );"
	)
	ok( absent["last"] == [], f"a last pass exists before any run: {absent}" )

	summary = {
		"pass_id": "0123456789abcdef0123456789abcdef",
		"started_at": "2026-10-30 10:00:00",
		"finished_at": "2026-10-30 10:01:00",
		"status": "completed",
		"counts": { "total": 2, "done": 2, "failed": 0, "uncertain": 0 },
		"total": 2,
		"done": 2,
		"failed": 0,
		"uncertain": 0,
	}
	stored = site.json_eval(
		f"""<?php
$summary = json_decode( '{php_json(summary)}', true );
$cached  = Updatronix_Update_Pass::cache_last_pass( $summary );
echo wp_json_encode( array( 'cached' => $cached, 'last' => Updatronix_Update_Pass::get_last_pass() ) );
"""
	)
	ok( stored["cached"] is True, f"the last pass was not cached: {stored}" )
	ok( stored["last"].get( "pass_id" ) == summary["pass_id"], f"the cache lost the pass id: {stored}" )
	ok( stored["last"].get( "counts" ) == summary["counts"], f"the cache lost the counts: {stored}" )

	expired = site.json_eval(
		r"""<?php
$seeded = Updatronix_Update_Pass::create(
	array( 'plugin:fixture-alpha/fixture-alpha.php' ),
	array(
		'plugin:fixture-alpha/fixture-alpha.php' => array(
			'type' => 'plugin', 'id' => 'fixture-alpha/fixture-alpha.php', 'target' => '2.0.0',
			'state' => Updatronix_Update_Pass::STATE_OFFER_ELIGIBLE, 'predecessors' => array(),
			'waiting_for' => '', 'eligible_at' => 0, 'attempts' => 0, 'reason' => '',
			'reason_code' => '', 'from' => '1.0.0',
		),
	),
	array(
		'pass_id'        => str_repeat( 'a', 32 ),
		'started_at'     => '2026-10-29 10:00:00',
		'next_check_at'  => time() - 60,
		'last_heartbeat' => time() - 3600,
	)
);
$pass = Updatronix_Update_Pass::load();
echo wp_json_encode(
	array(
		'created' => null !== $seeded,
		'expired' => null !== $pass ? $pass->is_expired() : null,
		'last'    => Updatronix_Update_Pass::get_last_pass(),
	)
);
"""
	)
	ok( expired["created"] is True, f"the expired pass was not seeded: {expired}" )
	ok( expired["expired"] is True, f"the old pass is not expired: {expired}" )
	ok( expired["last"].get( "pass_id" ) == summary["pass_id"], "the last pass cache changed when a pass expired" )

	site.set_settings( { "logging_enabled": False } )
	try:
		result, _ = pump( site, [ PLUGIN_ALPHA ] )
	finally:
		site.set_settings( { "logging_enabled": True } )
	ok( result["last"].get( "pass_id" ), f"the pass wrote no last pass with logging off: {result['last']}" )
	ok( int( result["last"].get( "counts", {} ).get( "total", 0 ) ) == 1, f"the summary is {result['last']}" )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "the pass did not run with logging off" )
	return base_observation( site.observe() ), { "absent": absent, "stored": stored, "expired": expired, "logging_off": result["last"] }


def pass_max_attempts( site: Site ) -> tuple[dict, dict]:
	"""TB-PASS-104: the attempt cap is 2 and the filter is clamped."""
	reset_item( site )
	state = site.json_eval(
		r"""<?php
$out = array( 'default' => Updatronix_Update_Pass::max_attempts() );
$one = static fn() => 1;
add_filter( 'updatronix_pro_update_pass_max_attempts', $one );
$out['one'] = Updatronix_Update_Pass::max_attempts();
remove_filter( 'updatronix_pro_update_pass_max_attempts', $one );
$high = static fn() => 99;
add_filter( 'updatronix_pro_update_pass_max_attempts', $high );
$out['clamped'] = Updatronix_Update_Pass::max_attempts();
remove_filter( 'updatronix_pro_update_pass_max_attempts', $high );
$pass = Updatronix_Update_Pass::create(
	array( 'plugin:fixture-broken/fixture-broken.php' ),
	array(
		'plugin:fixture-broken/fixture-broken.php' => array(
			'type' => 'plugin', 'id' => 'fixture-broken/fixture-broken.php', 'target' => '2.0.0',
			'state' => Updatronix_Update_Pass::STATE_OFFER_ELIGIBLE, 'predecessors' => array(),
			'waiting_for' => '', 'eligible_at' => 0, 'attempts' => 0, 'reason' => '',
			'reason_code' => '', 'from' => '1.0.0',
		),
	),
	array(
		'pass_id'        => str_repeat( 'b', 32 ),
		'started_at'     => '2026-10-30 10:00:00',
		'next_check_at'  => time() + 3600,
		'last_heartbeat' => time(),
	)
);
$key = 'plugin:fixture-broken/fixture-broken.php';
$out['attempts'] = array();
for ( $i = 0; $i < 4; $i++ ) {
	$pass->mark_attempted( $key );
	$out['attempts'][] = (int) ( $pass->node( $key )['attempts'] ?? 0 );
}
echo wp_json_encode( $out );
"""
	)
	ok( state["default"] == 2, f"the default cap is {state['default']}" )
	ok( state["one"] == 1, f"the filter did not lower the cap: {state}" )
	ok( state["clamped"] == 2, f"the filter was not clamped: {state}" )
	ok( state["attempts"] == [ 1, 2, 2, 2 ], f"the attempts are {state['attempts']}" )
	return base_observation( site.observe() ), state


def pass_nodes( site: Site ) -> tuple[dict, dict]:
	"""TB-PASS-105: one and fifty nodes fit; overflow is reported."""
	reset_item( site )
	state = site.json_eval(
		r"""<?php
$make = static function ( array $ids ): array {
	$nodes = array();
	foreach ( $ids as $id ) {
		$nodes[ 'plugin:' . $id ] = array(
			'type' => 'plugin', 'id' => $id, 'target' => '2.0.0',
			'state' => Updatronix_Update_Pass::STATE_OFFER_ELIGIBLE, 'predecessors' => array(),
			'waiting_for' => '', 'eligible_at' => 0, 'attempts' => 0, 'reason' => '',
			'reason_code' => '', 'from' => '1.0.0',
		);
	}
	return $nodes;
};
$one = array( 'plugin:fixture-alpha/fixture-alpha.php' );
$one_pass = Updatronix_Update_Pass::create(
	$one,
	$make( array( 'fixture-alpha/fixture-alpha.php' ) ),
	array(
		'pass_id' => str_repeat( 'c', 32 ), 'started_at' => '2026-10-30 10:00:00',
		'next_check_at' => time() + 3600, 'last_heartbeat' => time(),
	)
);
$one_data = $one_pass->data();
Updatronix_Update_Pass::delete_last_pass();
$fifty_ids = array();
for ( $i = 1; $i <= 50; $i++ ) {
	$fifty_ids[] = 'fixture-' . $i . '/fixture-' . $i . '.php';
}
$fifty_pass = Updatronix_Update_Pass::create(
	array_map( static fn( $id ) => 'plugin:' . $id, $fifty_ids ),
	$make( $fifty_ids ),
	array(
		'pass_id' => str_repeat( 'd', 32 ), 'started_at' => '2026-10-30 10:00:00',
		'next_check_at' => time() + 3600, 'last_heartbeat' => time(),
	)
);
$fifty_data = $fifty_pass->data();
$fifty_summary = $fifty_pass->summary();
$over_ids = array_slice( $fifty_ids, 0, 51 );
for ( $i = 51; $i <= 51; $i++ ) {
	$over_ids[] = 'fixture-' . $i . '/fixture-' . $i . '.php';
}
$over = Updatronix_Update_Pass::create(
	array_map( static fn( $id ) => 'plugin:' . $id, $over_ids ),
	$make( $over_ids ),
	array(
		'pass_id' => str_repeat( 'e', 32 ), 'started_at' => '2026-10-30 10:00:00',
		'next_check_at' => time() + 3600, 'last_heartbeat' => time(),
	)
);
echo wp_json_encode(
	array(
		'one'    => array( 'order' => count( $one_data['order'] ), 'nodes' => count( $one_data['nodes'] ), 'total' => $one_pass->summary()['total'] ?? null ),
		'fifty'  => array( 'order' => count( $fifty_data['order'] ), 'nodes' => count( $fifty_data['nodes'] ), 'limit' => Updatronix_Update_Pass::MAX_NODES ),
		'overflow' => array( 'created' => null !== $over, 'over' => is_wp_error( $over ) ? $over->get_error_code() : $over['order'] ?? 'created' ),
	)
);
"""
	)
	ok( state["one"]["order"] == 1 and state["one"]["nodes"] == 1, f"the one-node pass is {state['one']}" )
	ok( state["fifty"]["order"] == 50 and state["fifty"]["nodes"] == 50, f"the fifty-node pass is {state['fifty']}" )
	ok( state["fifty"]["limit"] == 50, f"the node limit is {state['fifty']['limit']}" )
	ok( state["overflow"]["created"] is False, f"a 51-node pass was created: {state['overflow']}" )
	return base_observation( site.observe() ), state


def _shield_get( site: Site, route: str, params: dict | None = None ) -> dict:
	return site.json_eval(
		f"""<?php
$user = get_user_by( 'login', 'testbook' );
if ( $user ) {{ wp_set_current_user( $user->ID ); }}
$request = new WP_REST_Request( 'GET', '{route}' );
$params  = json_decode( '{php_json(params or {})}', true );
foreach ( $params as $key => $value ) {{ $request->set_param( $key, $value ); }}
$response = rest_do_request( $request );
$data     = $response->get_data();
echo wp_json_encode( array( 'status' => $response->get_status(), 'data' => $data ) );
"""
	)


def _shield_save( site: Site, values: dict ) -> dict:
	return site.rest( "POST", "/updatronix-pro/v1/shield/settings", values, login="testbook" )


def shield_blocked_history( site: Site ) -> tuple[dict, dict]:
	"""TB-SHIELD-101: the 30-day window, empty, and paginated history."""
	reset_item( site )
	site.set_shield( { "safe_mode": True } )
	seeded = site.json_eval(
		r"""<?php
global $wpdb;
$table = Updatronix_Database::get_table_name();
$make  = static function ( string $name, string $when ) use ( $wpdb, $table ): int {
	$id = Updatronix_Logger::log_event(
		array(
			'log_type'       => 'plugin',
			'action_type'    => 'incompatible',
			'item_name'      => $name,
			'item_slug'      => 'tb/' . sanitize_key( $name ) . '.php',
			'version_before' => '1.0.0',
			'target_version' => '2.0.0',
			'status'         => 'warning',
			'message'        => $name,
			'performed_as'   => 'automatic',
			'reason_code'    => 'shield_incompatible_php',
			'decision_state' => 'blocked',
			'origin'         => 'automatic',
			'decision_stage' => 'policy',
		)
	);
	$wpdb->query( $wpdb->prepare( "UPDATE %i SET created_at = %s WHERE id = %d", $table, $when, $id ) );
	return $id;
};
$recent = $make( 'TB shield recent', wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
$old    = $make( 'TB shield old', wp_date( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) );
for ( $i = 1; $i <= 55; $i++ ) {
	$make( 'TB shield many ' . $i, wp_date( 'Y-m-d H:i:s', time() - $i * HOUR_IN_SECONDS ) );
}
echo wp_json_encode( array( 'recent' => $recent, 'old' => $old ) );
"""
	)
	first = _shield_get( site, "/updatronix-pro/v1/shield/blocked", { "per_page": 50, "page": 1 } )
	ok( first["status"] == 200, f"the blocked history failed: {first}" )
	ok( int( first["data"]["total"] ) == 56, f"the 30-day total is {first['data']['total']}" )
	ok( len( first["data"]["items"] ) == 50, f"page one returned {len(first['data']['items'])}" )
	items = { str( item.get( "item" ) ) for item in first["data"]["items"] }
	ok( "TB shield old" not in items, "the 40-day-old block is inside the 30-day window" )
	second = _shield_get( site, "/updatronix-pro/v1/shield/blocked", { "per_page": 50, "page": 2 } )
	ok( len( second["data"]["items"] ) == 6, f"page two returned {len(second['data']['items'])}" )

	site.eval_php( "<?php Updatronix_Logger::delete_all_logs(); echo 'ok';" )
	empty = _shield_get( site, "/updatronix-pro/v1/shield/blocked" )
	ok( empty["status"] == 200 and int( empty["data"]["total"] ) == 0, f"the empty history is {empty}" )
	ok( empty["data"]["items"] == [], f"the empty history returned items: {empty}" )
	return base_observation( site.observe() ), { "first": first["data"]["total"], "second": len( second["data"]["items"] ), "seeded": seeded }


def shield_flag_abandoned( site: Site ) -> tuple[dict, dict]:
	"""TB-SHIELD-104: abandoned flags only surface while the switch is on."""
	reset_item( site )
	key = "fixture-abandoned/fixture-abandoned.php"
	site.json_eval(
		f"""<?php
$cache = array(
	'{key}' => array(
		'last_updated' => gmdate( 'Y-m-d H:i:s', time() - 800 * DAY_IN_SECONDS ),
		'checked_at'   => time(),
	),
);
set_site_transient( Updatronix_Abandoned_Detector::CACHE_KEY, $cache, DAY_IN_SECONDS );
wp_cache_flush();
echo wp_json_encode( array( 'ok' => true ) );
"""
	)
	on = _shield_save( site, { "flag_abandoned": True } )
	ok( on["status"] == 200, f"the shield save failed: {on}" )
	on_data = site.rest( "GET", "/updatronix-pro/v1/shield/settings", login="testbook" )
	ok( on_data["data"]["flag_abandoned"] is True, f"the switch is {on_data['data']['flag_abandoned']}" )
	abandoned = on_data["data"]["shield_status"]["abandoned"]
	ok( key in abandoned, f"the abandoned fixture is not flagged: {abandoned}" )
	ok( int( abandoned[key]["days"] ) >= 30, f"the abandoned age is {abandoned[key]['days']} days" )

	off = _shield_save( site, { "flag_abandoned": False } )
	ok( off["status"] == 200, f"the disable save failed: {off}" )
	off_data = site.rest( "GET", "/updatronix-pro/v1/shield/settings", login="testbook" )
	ok( off_data["data"]["flag_abandoned"] is False, f"the switch stayed on: {off_data['data']['flag_abandoned']}" )
	ok( off_data["data"]["shield_status"]["abandoned"] == [], f"abandoned flags survived the switch: {off_data['data']['shield_status']['abandoned']}" )
	return base_observation( site.observe() ), { "on": on_data["data"]["shield_status"]["abandoned"], "off": off_data["data"]["shield_status"]["abandoned"] }


def shield_notify_email( site: Site ) -> tuple[dict, dict]:
	"""TB-SHIELD-106: the email switch decides the blocked mail."""
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": True,
			"control_wp_version": False,
			"incompatible_behavior": "cancel",
			"re_enable": False,
			"notify_on_block": True,
			"notify_email": "testbook@example.test",
		}
	)
	result, obs = pump( site, [ PLUGIN_INCOMPATIBLE ], max_requests=2 )
	on_mail = mail( site, obs )
	ok( version( result, PLUGIN_INCOMPATIBLE ) == "1.0.0", "the incompatible item installed" )
	ok( bool( on_mail ), "notify_email produced no mail" )
	ok( any( "testbook@example.test" in str( entry.get( "to" ) ) for entry in on_mail ), f"the mail recipient is {on_mail}" )

	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": True,
			"control_wp_version": False,
			"incompatible_behavior": "cancel",
			"re_enable": False,
			"notify_on_block": True,
			"notify_email": False,
		}
	)
	off_result, off_obs = pump( site, [ PLUGIN_INCOMPATIBLE ], max_requests=2 )
	off_mail = mail( site, off_obs )
	ok( version( off_result, PLUGIN_INCOMPATIBLE ) == "1.0.0", "the block was lost with the mail off" )
	ok( not off_mail, f"notify_email false still mailed: {off_mail}" )
	return base_observation( off_obs ), { "on": len( on_mail ), "off": len( off_mail ) }


def shield_notify_on_block( site: Site ) -> tuple[dict, dict]:
	"""TB-SHIELD-107: the block-event switch decides the mail."""
	return shield_notify_matrix( site )


def shield_re_enable( site: Site ) -> tuple[dict, dict]:
	"""TB-SHIELD-108: a passing release restores the native toggle only when on."""
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": True,
			"control_wp_version": False,
			"incompatible_behavior": "disable",
			"re_enable": True,
			"notify_on_block": False,
		}
	)
	site.set_auto_update( plugins=[ PLUGIN_INCOMPATIBLE ] )
	site.pump_pass( keep=[ PLUGIN_INCOMPATIBLE ], max_requests=2 )
	ok( PLUGIN_INCOMPATIBLE not in site.auto_update_plugins(), "the disable behavior kept the toggle" )
	ledger = site.json_eval(
		"<?php echo wp_json_encode( Updatronix_Shield_Ledger::all() );"
	)
	ok( bool( ledger ), f"the block was not recorded in the ledger: {ledger}" )

	on = site.json_eval(
		r"""<?php
add_filter( 'site_transient_update_plugins', static function ( $value ) {
	if ( is_object( $value ) && isset( $value->response['fixture-incompatible/fixture-incompatible.php'] ) ) {
		$value->response['fixture-incompatible/fixture-incompatible.php']->requires_php = '8.0';
		$value->response['fixture-incompatible/fixture-incompatible.php']->new_version  = '2.0.1';
	}
	return $value;
}, 50 );
wp_cache_flush();
$count = Updatronix_Shield_Enforcement::maybe_reenable();
echo wp_json_encode( array( 'count' => $count, 'auto' => get_option( 'auto_update_plugins', array() ), 'ledger' => Updatronix_Shield_Ledger::all() ) );
"""
	)
	ok( on["count"] == 1, f"the re-enable count is {on['count']}" )
	ok( PLUGIN_INCOMPATIBLE in ( on["auto"] or [] ), f"the toggle was not restored: {on['auto']}" )
	ok( not on["ledger"], f"the ledger entry survived: {on['ledger']}" )

	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": True,
			"control_wp_version": False,
			"incompatible_behavior": "disable",
			"re_enable": False,
			"notify_on_block": False,
		}
	)
	site.set_auto_update( plugins=[ PLUGIN_INCOMPATIBLE ] )
	site.pump_pass( keep=[ PLUGIN_INCOMPATIBLE ], max_requests=2 )
	off = site.json_eval(
		r"""<?php
add_filter( 'site_transient_update_plugins', static function ( $value ) {
	if ( is_object( $value ) && isset( $value->response['fixture-incompatible/fixture-incompatible.php'] ) ) {
		$value->response['fixture-incompatible/fixture-incompatible.php']->requires_php = '8.0';
		$value->response['fixture-incompatible/fixture-incompatible.php']->new_version  = '2.0.1';
	}
	return $value;
}, 50 );
wp_cache_flush();
$count = Updatronix_Shield_Enforcement::maybe_reenable();
echo wp_json_encode( array( 'count' => $count, 'auto' => get_option( 'auto_update_plugins', array() ) ) );
"""
	)
	ok( off["count"] == 0, f"the disabled re-enable ran: {off}" )
	ok( PLUGIN_INCOMPATIBLE not in ( off["auto"] or [] ), f"the toggle was restored with the switch off: {off['auto']}" )
	return base_observation( site.observe() ), { "on": on, "off": off }


def flow_chain_count( site: Site ) -> tuple[dict, dict]:
	"""TB-FLOW-101: twenty chains fit and twenty-one are reported."""
	reset_item( site )
	state = site.json_eval(
		r"""<?php
$make = static function ( int $count ): array {
	$chains = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$chains[] = array(
			'id'      => 'tb-chain-' . $i,
			'name'    => 'TB chain ' . $i,
			'enabled' => true,
			'steps'   => array( 'plugin:fixture-alpha/fixture-alpha.php', 'plugin:fixture-bravo/fixture-bravo.php' ),
		);
	}
	return $chains;
};
$twenty = Updatronix_Update_Flow::validate_chains( $make( 20 ) );
$twenty_one = Updatronix_Update_Flow::validate_chains( $make( 21 ) );
$saved = Updatronix_Update_Flow::validate_chains( $make( 3 ) );
update_site_option( Updatronix_Update_Flow::OPTION, array( 'version' => 1, 'chains' => $saved ) );
echo wp_json_encode(
	array(
		'twenty'     => is_wp_error( $twenty ) ? $twenty->get_error_code() : count( $twenty ),
		'twenty_one' => is_wp_error( $twenty_one ) ? $twenty_one->get_error_code() : count( $twenty_one ),
		'saved'      => count( Updatronix_Update_Flow::get_saved_chains() ),
	)
);
"""
	)
	ok( state["twenty"] == 20, f"twenty chains failed: {state}" )
	ok( state["twenty_one"] == "flow_chain_limit", f"twenty-one chains: {state['twenty_one']}" )
	ok( state["saved"] == 3, f"the saved chains are {state['saved']}" )
	return base_observation( site.observe() ), state


def flow_chain_enabled( site: Site ) -> tuple[dict, dict]:
	"""TB-FLOW-102: only enabled chains shape the plan."""
	reset_item( site )
	state = site.json_eval(
		r"""<?php
$chains = array(
	array(
		'id'      => 'tb-enabled',
		'name'    => 'Enabled chain',
		'enabled' => true,
		'steps'   => array( 'plugin:fixture-alpha/fixture-alpha.php', 'plugin:fixture-bravo/fixture-bravo.php' ),
	),
	array(
		'id'      => 'tb-disabled',
		'name'    => 'Disabled chain',
		'enabled' => false,
		'steps'   => array( 'theme:fixture-theme', 'plugin:fixture-alpha/fixture-alpha.php' ),
	),
);
$clean = Updatronix_Update_Flow::validate_chains( $chains );
update_site_option( Updatronix_Update_Flow::OPTION, array( 'version' => 1, 'chains' => $clean ) );
$saved = Updatronix_Update_Flow::get_saved_chains();
$all   = ( new Updatronix_Flow_Planner() )->plan( array( 'origin' => 'flow', 'enabled_only' => false ) );
$on    = ( new Updatronix_Flow_Planner() )->plan( array( 'origin' => 'flow', 'enabled_only' => true ) );
echo wp_json_encode(
	array(
		'saved'   => array_map( static fn( $chain ) => array( 'id' => $chain['id'], 'enabled' => $chain['enabled'] ), $saved ),
		'all'     => count( $all->flow_edges ),
		'enabled' => count( $on->flow_edges ),
		'ids'     => array_keys( $on->flow_snapshot ),
	)
);
"""
	)
	saved = { chain["id"]: chain["enabled"] for chain in state["saved"] }
	ok( saved.get( "tb-enabled" ) is True and saved.get( "tb-disabled" ) is False, f"the flags are {saved}" )
	ok( state["enabled"] < state["all"], f"the disabled chain still shapes the enabled plan: {state}" )
	ok( state["enabled"] >= 1, f"the enabled chain produced no edge: {state}" )
	return base_observation( site.observe() ), state


def flow_step_count( site: Site ) -> tuple[dict, dict]:
	"""TB-FLOW-106: fifty steps fit and fifty-one are reported."""
	reset_item( site )
	state = site.json_eval(
		r"""<?php
$steps = array();
for ( $i = 1; $i <= 50; $i++ ) {
	$steps[] = 'plugin:fixture-step-' . $i . '/fixture-step-' . $i . '.php';
}
$fifty = Updatronix_Update_Flow::validate_chains(
	array( array( 'id' => 'tb-steps', 'name' => 'Steps', 'enabled' => true, 'steps' => $steps ) )
);
$over = Updatronix_Update_Flow::validate_chains(
	array( array( 'id' => 'tb-steps-over', 'name' => 'Steps over', 'enabled' => true, 'steps' => array_merge( $steps, array( 'plugin:fixture-alpha/fixture-alpha.php' ) ) ) )
);
$short = Updatronix_Update_Flow::validate_chains(
	array( array( 'id' => 'tb-short', 'name' => 'Short', 'enabled' => true, 'steps' => array( 'plugin:fixture-alpha/fixture-alpha.php' ) ) )
);
echo wp_json_encode(
	array(
		'fifty' => is_wp_error( $fifty ) ? $fifty->get_error_code() : count( $fifty[0]['steps'] ),
		'over'  => is_wp_error( $over ) ? $over->get_error_code() : count( $over[0]['steps'] ),
		'short' => is_wp_error( $short ) ? array( 'code' => $short->get_error_code(), 'data' => $short->get_error_data() ) : count( $short[0]['steps'] ),
	)
);
"""
	)
	ok( state["fifty"] == 50, f"fifty steps failed: {state['fifty']}" )
	ok( state["over"] == "flow_step_limit", f"fifty-one steps: {state['over']}" )
	short = state["short"]
	short_code = short["code"] if isinstance( short, dict ) else short
	ok( short_code == "flow_steps_min", f"a one-step chain: {short}" )
	return base_observation( site.observe() ), state


def license_lifecycle( site: Site ) -> tuple[dict, dict]:
	"""TB-LIC-101: activate, deactivate, clear, and revalidate."""
	reset_item( site )
	key = "UPD-PRO-ABCDEFGH-IJKLMNOP-QRSTUVWX"
	active = site.json_eval(
		f"""<?php
$saved = Updatronix_Pro_License_Storage::save_license_data(
	array(
		'license_key'       => '{key}',
		'status'            => 'active',
		'registered_domain' => wp_parse_url( home_url(), PHP_URL_HOST ),
		'last_checked'      => time(),
	)
);
echo wp_json_encode(
	array(
		'saved'   => $saved,
		'active'  => Updatronix_Pro_Bootstrap::is_license_active(),
		'valid'   => ( new Updatronix_Pro_License_Handler() )->is_license_valid(),
		'masked'  => Updatronix_Pro_License_Storage::mask_license_key( Updatronix_Pro_License_Storage::get_license_data()['license_key'] ),
		'pro_routes' => count( array_filter( array_keys( rest_get_server()->get_routes() ), static fn( $route ) => 0 === strpos( $route, '/updatronix-pro/v1/' ) ) ),
	)
);
"""
	)
	ok( active["saved"] is True, f"the license was not saved: {active}" )
	ok( active["active"] is True and active["valid"] is True, f"the license is not active/valid: {active}" )
	ok( active["masked"].startswith( "UPD-PRO-" ) and key not in active["masked"], f"the key is not masked: {active['masked']}" )
	ok( int( active["pro_routes"] ) > 0, "no Pro routes registered with an active license" )

	status = _shield_get( site, "/updatronix-pro/v1/license/status" )
	ok( status["status"] == 200 and status["data"]["status"] == "active", f"the status route is {status}" )

	deactivated = site.json_eval(
		"""<?php
$saved = Updatronix_Pro_License_Storage::save_license_data( array( 'status' => 'inactive' ) );
echo wp_json_encode( array( 'saved' => $saved, 'active' => Updatronix_Pro_Bootstrap::is_license_active() ) );
"""
	)
	ok( deactivated["active"] is False, f"the inactive license is still active: {deactivated}" )
	off_status = _shield_get( site, "/updatronix-pro/v1/license/status" )
	ok( off_status["data"]["status"] == "inactive", f"the inactive status is {off_status}" )

	cleared = site.json_eval(
		"""<?php
$deleted = Updatronix_Pro_License_Storage::delete_license_data();
$data    = Updatronix_Pro_License_Storage::get_license_data();
echo wp_json_encode( array( 'deleted' => $deleted, 'status' => $data['status'], 'key' => $data['license_key'], 'encrypted' => $data['encrypted_key'] ) );
"""
	)
	ok( cleared["status"] == "inactive" and cleared["key"] == "" and cleared["encrypted"] == "", f"the cleared license is {cleared}" )
	return base_observation( site.observe() ), { "active": active, "deactivated": deactivated, "cleared": cleared }


def license_salt_throttle( site: Site ) -> tuple[dict, dict]:
	"""TB-LIC-002: salt rotation, clear, throttle, and revalidate."""
	reset_item( site )
	rotated = site.json_eval(
		r"""<?php
update_site_option(
	UPDATRONIX_PRO_OPTION_LICENSE,
	array(
		'encrypted_key'     => 'not-a-valid-ciphertext',
		'status'            => 'active',
		'registered_domain' => wp_parse_url( home_url(), PHP_URL_HOST ),
		'last_checked'      => time(),
	)
);
$data = Updatronix_Pro_License_Storage::get_license_data();
echo wp_json_encode(
	array(
		'error'  => Updatronix_Pro_License_Storage::has_decryption_error(),
		'status' => $data['status'],
		'key'    => $data['license_key'],
		'active' => Updatronix_Pro_Bootstrap::is_license_active(),
	)
);
"""
	)
	ok( rotated["error"] is True, f"the rotated salt was not detected: {rotated}" )
	ok( rotated["status"] == "inactive" and rotated["key"] == "", f"the rotated key is still readable: {rotated}" )
	ok( rotated["active"] is False, "Pro is active after a salt rotation" )

	cleared = site.json_eval(
		"""<?php
$saved = Updatronix_Pro_License_Storage::save_license_data( array( 'license_key' => '' ) );
$data  = Updatronix_Pro_License_Storage::get_license_data();
echo wp_json_encode( array( 'saved' => $saved, 'encrypted' => $data['encrypted_key'] ) );
"""
	)
	ok( cleared["saved"] is True and cleared["encrypted"] == "", f"the key was not cleared: {cleared}" )

	throttled = site.json_eval(
		"""<?php
set_site_transient( 'updatronix_pro_activation_throttle', time(), HOUR_IN_SECONDS );
wp_set_current_user( get_user_by( 'login', 'testbook' )->ID );
$request = new WP_REST_Request( 'POST', '/updatronix-pro/v1/license/activate' );
$request->set_param( 'license_key', 'UPD-PRO-ABCDEFGH-IJKLMNOP-QRSTUVWX' );
$response = rest_do_request( $request );
delete_site_transient( 'updatronix_pro_activation_throttle' );
echo wp_json_encode( array( 'status' => $response->get_status(), 'data' => $response->get_data() ) );
"""
	)
	ok( throttled["status"] == 429, f"the throttled activation returned {throttled['status']}" )
	ok( "throttle" in str( throttled["data"] ).lower() or "wait" in str( throttled["data"] ).lower(), f"the throttle copy is {throttled['data']}" )

	revalidated = site.json_eval(
		f"""<?php
Updatronix_Pro_License_Storage::save_license_data(
	array(
		'license_key'       => 'UPD-PRO-ABCDEFGH-IJKLMNOP-QRSTUVWX',
		'status'            => 'active',
		'registered_domain' => wp_parse_url( home_url(), PHP_URL_HOST ),
		'last_checked'      => time(),
	)
);
echo wp_json_encode( array( 'valid' => ( new Updatronix_Pro_License_Handler() )->is_license_valid() ) );
"""
	)
	ok( revalidated["valid"] is True, f"the license did not revalidate: {revalidated}" )
	return base_observation( site.observe() ), { "rotated": rotated, "cleared": cleared, "throttled": throttled, "revalidated": revalidated }


def pro_export_formats( site: Site ) -> tuple[dict, dict]:
	"""TB-PRO-008: Pro adds csv/json/xml only while the license is active."""
	reset_item( site )
	active = site.json_eval( "<?php echo wp_json_encode( array( 'offered' => updatronix_get_export_formats(), 'server' => updatronix_get_server_export_formats() ) );" )
	ok( active["offered"] == [ "txt", "csv", "json", "xml" ], f"the Pro formats are {active['offered']}" )
	ok( active["server"] == [ "txt", "json" ], f"the server formats are {active['server']}" )

	site.set_license( "inactive" )
	try:
		off = site.json_eval( "<?php echo wp_json_encode( array( 'offered' => updatronix_get_export_formats(), 'server' => updatronix_get_server_export_formats() ) );" )
	finally:
		site.set_license( "active", "TB-FIXTURE-KEY" )
	ok( off["offered"] == [ "txt" ], f"Pro formats are offered without a license: {off['offered']}" )
	ok( off["server"] == [ "txt" ], f"the server accepts a structured format without a license: {off['server']}" )

	data, detail = export_format( site )
	return data, { **detail, "active": active, "inactive": off }


def export_surface_matrix( site: Site ) -> tuple[dict, dict]:
	"""TB-EXPORT-001: formats, columns, and merge through one flow."""
	_, formats = export_format( site )
	_, columns = export_columns( site )
	data, merge = export_merge( site )
	return data, { "formats": formats, "columns": columns, "merge": merge }


def export_empty_matrix( site: Site ) -> tuple[dict, dict]:
	"""TB-EXPORT-002: the empty, expired, rate-limited, and chunked states."""
	return export_empty( site )


def _flow_surface( site: Site ) -> dict:
	links_path = f".cache/testbook/links-{site.name}-flow.json"
	a11y_path = f".cache/testbook/a11y-{site.name}-flow.json"
	_run_playwright(
		site,
		"tests/testbook/playwright-surfaces.config.js",
		{
			"TESTBOOK_SURFACE": "flow",
			"TESTBOOK_A11Y_PATH": a11y_path,
			"TESTBOOK_LINKS_PATH": links_path,
		},
	)
	target = ROOT / links_path
	if not target.exists():
		raise RuntimeError( f"flow metrics were not written: {links_path}" )
	return json.loads( target.read_text( encoding="utf-8" ) )


def pass_summary_states( site: Site ) -> tuple[dict, dict]:
	"""TB-PASS-004: absent, completed, and stale/expired summaries."""
	reset_item( site )
	absent = _flow_surface( site )
	ok( "Last pass" not in absent["body"], f"the absent summary still renders: {absent['body'][:400]}" )
	empty = _pass_state( site )
	ok( empty["last"] in ( {}, [] ), f"a last pass exists before any run: {empty['last']}" )

	summary = {
		"pass_id": "0123456789abcdef0123456789abcdef",
		"started_at": "2026-10-30 10:00:00",
		"finished_at": "2026-10-30 10:01:00",
		"status": "completed",
		"counts": { "total": 2, "done": 2, "failed": 0, "uncertain": 0 },
		"total": 2,
		"done": 2,
		"failed": 0,
		"uncertain": 0,
	}
	site.json_eval(
		f"""<?php
Updatronix_Update_Pass::cache_last_pass( json_decode( '{php_json(summary)}', true ) );
echo wp_json_encode( array( 'ok' => true ) );
"""
	)
	completed = _flow_surface( site )
	lower = completed["body"].lower()
	ok( "last pass" in lower, f"the completed summary is missing: {completed['body'][:400]}" )
	ok( "completed" in lower, f"the completed status is missing: {completed['body'][:400]}" )
	ok( "0123456789abcdef0123456789abcdef" in completed["body"] or "0123456789" in completed["body"], "the pass id is missing from the card" )
	ok( "run now" not in lower, "the Last pass card offers a Run now control" )

	expired = site.json_eval(
		r"""<?php
Updatronix_Update_Pass::create(
	array( 'plugin:fixture-alpha/fixture-alpha.php' ),
	array(
		'plugin:fixture-alpha/fixture-alpha.php' => array(
			'type' => 'plugin', 'id' => 'fixture-alpha/fixture-alpha.php', 'target' => '2.0.0',
			'state' => Updatronix_Update_Pass::STATE_OFFER_ELIGIBLE, 'predecessors' => array(),
			'waiting_for' => '', 'eligible_at' => 0, 'attempts' => 0, 'reason' => '',
			'reason_code' => '', 'from' => '1.0.0',
		),
	),
	array(
		'pass_id' => str_repeat( 'f', 32 ), 'started_at' => '2026-10-29 10:00:00',
		'next_check_at' => time() - 60, 'last_heartbeat' => time() - 3600,
	)
);
$pass = Updatronix_Update_Pass::load();
$health = Updatronix_Health::get_snapshot( true );
$state  = array();
foreach ( $health['checks'] as $check ) {
	if ( 'pass_state' === $check['id'] ) {
		$state = $check;
	}
}
echo wp_json_encode( array( 'expired' => null !== $pass ? $pass->is_expired() : null, 'health' => $state, 'last' => Updatronix_Update_Pass::get_last_pass() ) );
"""
	)
	ok( expired["expired"] is True, f"the stale pass did not expire: {expired}" )
	ok( expired["health"].get( "value" ) == "Idle", f"the stale pass renders as {expired['health'].get('value')}" )
	ok( expired["last"].get( "pass_id" ) == summary["pass_id"], "the stale pass replaced the last-pass cache" )
	stale_surface = _flow_surface( site )
	ok( "last pass" in stale_surface["body"].lower(), "the stale pass hid the completed summary" )
	return base_observation( site.observe() ), { "absent": absent["body"][:120], "completed": completed["body"][:200], "expired": expired }


CASES = [
	CaseSpec(
		"TB-UI-010",
		white_label_surface,
		( "single", ),
		( "INV-08", ),
		phase="ui",
		reset="state",
	),
	CaseSpec(
		"TB-PRO-006",
		white_label_lifecycle,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-PASS-002",
		pass_lock_retry,
		( "single", ),
		( "INV-08", ),
		phase="pass",
		reset="full",
	),
	CaseSpec(
		"TB-PASS-003",
		pass_retry_next,
		( "single", ),
		( "INV-08", ),
		phase="pass",
		reset="full",
	),
	CaseSpec(
		"TB-PASS-102",
		pass_last_pass,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-PASS-103",
		pass_lock_matrix,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="full",
	),
	CaseSpec(
		"TB-PASS-104",
		pass_max_attempts,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-PASS-105",
		pass_nodes,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-WL-001",
		white_label_save_reset,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-SHIELD-101",
		shield_blocked_history,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-SHIELD-104",
		shield_flag_abandoned,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-SHIELD-106",
		shield_notify_email,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="full",
	),
	CaseSpec(
		"TB-SHIELD-107",
		shield_notify_on_block,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="full",
	),
	CaseSpec(
		"TB-SHIELD-108",
		shield_re_enable,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="full",
	),
	CaseSpec(
		"TB-FLOW-101",
		flow_chain_count,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-FLOW-102",
		flow_chain_enabled,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-FLOW-106",
		flow_step_count,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-VLOCK-103",
		label_matrix,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-LIC-101",
		license_lifecycle,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-LIC-002",
		license_salt_throttle,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-PRO-008",
		pro_export_formats,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-EXPORT-001",
		export_surface_matrix,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-EXPORT-002",
		export_empty_matrix,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-PASS-004",
		pass_summary_states,
		( "single", ),
		( "INV-08", ),
		phase="pass",
		reset="state",
	),
	CaseSpec(
		"TB-WL-101",
		white_label_brand,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-WL-102",
		white_label_colors,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-WL-103",
		white_label_email,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-WL-104",
		white_label_enabled,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-WL-105",
		white_label_logo,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-WL-106",
		white_label_menu_label,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
]
