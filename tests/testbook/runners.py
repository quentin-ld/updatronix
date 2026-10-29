#!/usr/bin/env python3
"""Invariant runners for the Updatronix test book.

Every runner consumes an observation dict produced by a case and raises
AssertionError when the invariant is violated. `--self-test` proves each runner
can fail: it runs a good observation and a deliberately bad one for every
invariant.
"""

from __future__ import annotations

import argparse
import json
import sys
from collections import Counter
from typing import Any, Callable

Observation = dict[str, Any]

GOOD: Observation = {
    "selected": ["a", "b"],
    "installs": ["a", "b"],
    "non_eligible": ["c"],
    "rows": {"c": 1},
    "emails": {"a": 1, "b": 1},
    "item_rows": {"a": 1, "b": 1},
    "pass_rows": 1,
    "results": [
        {"item": "a", "installed": True, "status": "success"},
        {"item": "b", "installed": True, "status": "success"},
    ],
    "manual_updates": {
        "admin_blocked": False,
        "wp_cli_allowed": True,
        "pass_write_allowed": True,
    },
    "manual_switch": False,
    "original_auto_update": ["a"],
    "after_auto_update": ["a"],
    "shield": "off",
    "shield_blocked": False,
    "http_status": 200,
    "fatal": False,
    "network": False,
    "pass_site": "single",
    "subsite_admin_routes": False,
    "license": "active",
    "pro_routes": True,
    "pass_runs": True,
    "uninstall_opt_in": False,
    "rows_before": 2,
    "rows_after": 2,
    "queue_backup": True,
    "controls": 5,
    "reachable": 5,
    "labelled": 5,
    "focus_visible": 5,
    "overflow": False,
}


def inv_01_exactly_once(o: Observation) -> None:
    assert Counter(o["installs"]) == Counter(o["selected"]), (
        "INV-01: an eligible item was installed zero times or more than once"
    )


def inv_02_ineligible(o: Observation) -> None:
    for item in o["non_eligible"]:
        assert item not in o["installs"], f"INV-02: ineligible item {item} was installed"
        assert o["rows"].get(item) == 1, f"INV-02: {item} needs exactly one reason row"


def inv_03_email_and_rows(o: Observation) -> None:
    for item in o["installs"]:
        assert o["emails"].get(item, 0) == 1, f"INV-03: {item} needs one native email"
        assert o["item_rows"].get(item, 0) == 1, f"INV-03: {item} needs one item row"
    assert o["pass_rows"] == 1, "INV-03: one pass summary row expected"


def inv_04_truthful_outcome(o: Observation) -> None:
    for result in o["results"]:
        if not result.get("installed"):
            assert result.get("status") != "success", (
                f"INV-04: {result.get('item')} failed but was reported as success"
            )


def inv_05_manual_guard(o: Observation) -> None:
    if not o["manual_switch"]:
        assert not o["manual_updates"]["admin_blocked"], (
            "INV-05: manual admin update blocked with the switch off"
        )
    assert o["manual_updates"]["wp_cli_allowed"], "INV-05: WP-CLI must remain available"
    assert o["manual_updates"]["pass_write_allowed"], (
        "INV-05: the pass's own write must not be blocked"
    )


def inv_06_native_option(o: Observation) -> None:
    assert o["original_auto_update"] == o["after_auto_update"], (
        "INV-06: Version Lock rewrote the native auto-update option"
    )
    assert o["manual_updates"]["pass_write_allowed"], (
        "INV-06: the pass's own write was blocked"
    )


def inv_07_shield_off(o: Observation) -> None:
    if o["shield"] == "off":
        assert not o["shield_blocked"], "INV-07: Shield off still blocked an update"


def inv_08_no_fatal(o: Observation) -> None:
    assert o["http_status"] < 500, f"INV-08: HTTP {o['http_status']}"
    assert not o["fatal"], "INV-08: fatal error observed"


def inv_09_multisite(o: Observation) -> None:
    if o["network"]:
        assert o["pass_site"] == "main_site", "INV-09: the pass is not main-site only"
        assert not o["subsite_admin_routes"], "INV-09: a subsite admin reached a Pro route"
    else:
        assert o["pass_site"] in {"single", "main_site"}, "INV-09: unexpected single-site pass scope"


def inv_10_license(o: Observation) -> None:
    if o["license"] != "active":
        assert not o["pro_routes"], "INV-10: a Pro route registered without a license"
        assert not o["pass_runs"], "INV-10: the pass ran without a license"


def inv_11_data_deletion(o: Observation) -> None:
    if not o["uninstall_opt_in"]:
        assert o["rows_after"] == o["rows_before"], (
            "INV-11: data changed without the uninstall opt-in"
        )
    assert o["queue_backup"], "INV-11: the queue-table drop is not backup-gated"


def inv_12_a11y(o: Observation) -> None:
    assert o["controls"] == o["reachable"] == o["labelled"] == o["focus_visible"], (
        "INV-12: a control is unreachable, unlabeled, or has no visible focus"
    )
    assert not o["overflow"], "INV-12: horizontal overflow at 390/760"


RUNNERS: dict[str, Callable[[Observation], None]] = {
    "INV-01": inv_01_exactly_once,
    "INV-02": inv_02_ineligible,
    "INV-03": inv_03_email_and_rows,
    "INV-04": inv_04_truthful_outcome,
    "INV-05": inv_05_manual_guard,
    "INV-06": inv_06_native_option,
    "INV-07": inv_07_shield_off,
    "INV-08": inv_08_no_fatal,
    "INV-09": inv_09_multisite,
    "INV-10": inv_10_license,
    "INV-11": inv_11_data_deletion,
    "INV-12": inv_12_a11y,
}

BAD: dict[str, dict[str, Any]] = {
    "INV-01": {"installs": ["a"]},
    "INV-02": {"installs": ["a", "b", "c"]},
    "INV-03": {"emails": {"a": 2, "b": 1}},
    "INV-04": {"results": [{"item": "a", "installed": False, "status": "success"}]},
    "INV-05": {"manual_updates": {"admin_blocked": True, "wp_cli_allowed": True, "pass_write_allowed": True}},
    "INV-06": {"after_auto_update": ["b"]},
    "INV-07": {"shield_blocked": True},
    "INV-08": {"http_status": 500},
    "INV-09": {"network": True, "pass_site": "subsite"},
    "INV-10": {"license": "inactive", "pro_routes": True},
    "INV-11": {"rows_after": 0},
    "INV-12": {"overflow": True},
}


def run_all(observation: Observation) -> list[str]:
    failures = []
    for invariant_id, runner in RUNNERS.items():
        try:
            runner(observation)
        except AssertionError as error:
            failures.append(f"{invariant_id}: {error}")
    return failures


def self_test() -> int:
    failures = []
    for invariant_id, runner in RUNNERS.items():
        runner(GOOD)
        bad = dict(GOOD)
        bad.update(BAD[invariant_id])
        try:
            runner(bad)
        except AssertionError:
            continue
        failures.append(f"{invariant_id}: bad observation did not fail")
    if failures:
        print("\n".join(failures))
        return 1
    print(f"{len(RUNNERS)} runners, {len(RUNNERS) * 2} checks: good passes and bad fails")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--self-test", action="store_true")
    parser.add_argument("--observation", type=str)
    args = parser.parse_args()
    if args.self_test:
        return self_test()
    if args.observation:
        failures = run_all(json.loads(open(args.observation, encoding="utf-8").read()))
        if failures:
            print("\n".join(failures))
            return 1
        print("all invariants hold")
        return 0
    parser.print_help()
    return 0


if __name__ == "__main__":
    sys.exit(main())
