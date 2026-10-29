#!/usr/bin/env python3
"""Generate and validate the Updatronix test book.

Usage:
  python3 tests/testbook/testbook.py generate
  python3 tests/testbook/testbook.py validate
"""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parents[2]
TB = ROOT / "tests/testbook"
CASE_IDS = TB / "case-ids.yaml"
GEN_CASES = TB / "cases.generated.yaml"
COMBOS = TB / "combinations.yaml"
BOOK = ROOT / "docs/testing/test-book.md"

GROUP_CODE = {
    "free.settings": "SET",
    "free.schedule": "SCHED",
    "free.auto_updates": "AUTO",
    "free.logging": "LOG",
    "free.exports": "EXPORT",
    "free.health": "HEALTH",
    "free.ui": "UI",
    "pro.shield": "SHIELD",
    "pro.version_lock": "VLOCK",
    "pro.pass": "PASS",
    "pro.planning": "PLAN",
    "pro.flow": "FLOW",
    "pro.license": "LIC",
    "pro.white_label": "WL",
    "update": "UPDATE",
    "environment": "ENV",
    "roles": "ROLE",
    "multisite": "MS",
}
REQUIRED = ("id", "title", "owner", "risk", "tags", "automation")


def load(path: Path):
    return yaml.safe_load(path.read_text(encoding="utf-8"))


def dump(path: Path, data) -> None:
    path.write_text(yaml.safe_dump(data, sort_keys=False, allow_unicode=True), encoding="utf-8")


def ensure_case_ids(dims: list[dict]) -> dict[str, str]:
    ids = load(CASE_IDS) if CASE_IDS.exists() else {}
    by_group: dict[str, list[dict]] = {}
    for dim in dims:
        by_group.setdefault(dim["group"], []).append(dim)
    for group, group_dims in by_group.items():
        code = GROUP_CODE[group]
        used = [
            int(value.rsplit("-", 1)[1])
            for value in ids.values()
            if value.startswith(f"TB-{code}-")
        ]
        next_n = max(used, default=100) + 1
        for dim in sorted(group_dims, key=lambda d: d["id"]):
            if dim["id"] not in ids:
                ids[dim["id"]] = f"TB-{code}-{next_n:03d}"
                next_n += 1
    dump(CASE_IDS, ids)
    return ids


def values_for(dim: dict) -> list:
    if dim.get("domain") == "set":
        values = list(dim.get("values", []))
        return [[], values[:1], values]
    raw = dim.get("values") or dim.get("points") or []
    if not raw and dim.get("range"):
        raw = [dim["range"][0], dim["range"][1]]
    out = []
    for value in raw:
        if value not in out:
            out.append(value)
    return out[:6]


def pairwise(dims: list[dict]) -> tuple[list[dict], int, int]:
    selected = [
        d
        for d in dims
        if d.get("kind") in {"setting", "feature_flag", "environment"}
        and d.get("group") != "update"
        and len(values_for(d)) >= 2
    ]
    raw_domains = {d["id"]: values_for(d) for d in selected}
    value_by_key = {
        dim_id: {json.dumps(value, sort_keys=True): value for value in values}
        for dim_id, values in raw_domains.items()
    }
    domains = {
        dim_id: list(value_by_key[dim_id].keys()) for dim_id in raw_domains
    }
    defaults = {d["id"]: domains[d["id"]][0] for d in selected}
    pairs = set()
    for i, first in enumerate(selected):
        for second in selected[i + 1 :]:
            for v1 in domains[first["id"]]:
                for v2 in domains[second["id"]]:
                    pairs.add((first["id"], v1, second["id"], v2))
    uncovered = set(pairs)
    combos: list[dict] = []
    while uncovered and len(combos) < 300:
        d1, v1, d2, v2 = min(uncovered, key=repr)
        combo = dict(defaults)
        combo[d1] = v1
        combo[d2] = v2
        for dim in selected:
            key = dim["id"]
            if key in (d1, d2):
                continue
            best, best_count = defaults[key], -1
            for value in domains[key]:
                count = sum(
                    1
                    for other, other_value in combo.items()
                    if other != key and (other, other_value, key, value) in uncovered
                )
                if count > best_count:
                    best, best_count = value, count
            combo[key] = best
        covered = {
            p
            for p in uncovered
            if p[0] in combo
            and p[2] in combo
            and combo[p[0]] == p[1]
            and combo[p[2]] == p[3]
        }
        covered = covered or {(d1, v1, d2, v2)}
        uncovered -= covered
        combos.append(combo)
    for d1, v1, d2, v2 in list(uncovered):
        combo = dict(defaults)
        combo[d1], combo[d2] = v1, v2
        combos.append(combo)
    unique = []
    seen = set()
    for combo in combos:
        key = repr(tuple(combo[d["id"]] for d in selected))
        if key not in seen:
            seen.add(key)
            unique.append(
                {
                    dim_id: value_by_key[dim_id][value_key]
                    for dim_id, value_key in combo.items()
                }
            )
    return unique, len(pairs), len(pairs) - len(uncovered)


def dimension_case(dim: dict, cid: str) -> dict:
    values = values_for(dim)
    return {
        "id": cid,
        "title": f"Dimension: {dim['label']} ({dim['id']})",
        "owner": dim["owner"],
        "risk": dim["risk"],
        "tags": list(dim["tags"]) + ["@dimension"],
        "dimensions": [dim["id"]],
        "values": values,
        "steps": [
            f"Apply {dim['id']} at {values} through the owning surface.",
            "Run the case's oracle: UI plus the real WordPress effect.",
        ],
        "assertions": [
            f"The behavior matches {dim['source']}.",
            "The invariants that apply to this dimension hold.",
        ],
        "oracle": ["ui", "integration"],
        "automation": "planned",
    }


def write_book(dims, ids, generated, cases, pro, invariants, combos, pair_count) -> None:
    all_cases = generated + cases["surface_cases"] + cases["flow_cases"] + cases["triples"] + pro["cases"]
    lines = [
        "<!-- generated by tests/testbook/testbook.py generate; do not edit -->",
        "# Updatronix test book",
        "",
        f"Generated 2026-10-29 from `tests/testbook/`. **{len(dims)} dimensions, "
        f"{len(all_cases)} cases, {len(invariants['invariants'])} invariants, "
        f"{len(combos)} combinations.**",
        "",
        "This is the agent-facing book. The catalog is the source of truth; the",
        "Markdown is generated. The full book is mandatory before a release, a",
        "merge to `main`, or a push to `main`; a smoke subset may run on other",
        "pushes. A release with a failing book is not ready.",
        "",
        "## How to run",
        "",
        "```bash",
        "python3 tests/testbook/testbook.py generate   # regenerate cases/combinations/book",
        "python3 tests/testbook/testbook.py validate   # catalog + freshness + coverage",
        "python3 tests/testbook/runners.py --self-test # every invariant can fail",
        "```",
        "",
        "The Playwright/multisite runner is wired in phase 4. Until then the book",
        "is a validated contract, not a green execution claim.",
        "",
        "## Invariants",
        "",
        "| ID | Statement | Runner |",
        "|---|---|---|",
    ]
    for inv in invariants["invariants"]:
        lines.append(f"| {inv['id']} | {inv['title']} | `{inv['runner']}` |")
    lines += ["", "## Dimensions", "", "| Dimension | Owner | Risk | Values | Case |", "|---|---|---|---|---|"]
    for dim in dims:
        values = json.dumps(values_for(dim), ensure_ascii=False)
        lines.append(f"| `{dim['id']}` | {dim['owner']} | {dim['risk']} | {values[:80]} | {ids[dim['id']]} |")
    lines += ["", "## Cases", "", "| ID | Title | Owner | Risk | Tags | Automation |", "|---|---|---|---|---|---|"]
    for case in all_cases:
        lines.append(
            f"| {case['id']} | {case['title']} | {case['owner']} | {case['risk']} | "
            f"{' '.join(case['tags'])} | {case.get('automation', 'planned')} |"
        )
    lines += [
        "",
        "## Combination model",
        "",
        f"{len(combos)} rows cover all {pair_count} pairs of the combinable dimensions,",
        "plus the named high-risk triples. The pairwise rows are `PAIR-*`; the",
        "triples are `COMBO-*`. The full matrix is in `tests/testbook/combinations.yaml`.",
        "",
        "| ID | Kind | Dimensions |",
        "|---|---|---|",
    ]
    for combo in combos[:12]:
        lines.append(f"| {combo['id']} | {combo['kind']} | {json.dumps(combo.get('dimensions', {}), ensure_ascii=False)[:100]} |")
    lines += [
        "",
        "## Fixtures and environments",
        "",
        "The dedicated single-site and multisite DDEV sites, the deterministic",
        "fixtures, the pinned community set, the real update packages, the mail",
        "catcher, the cron/clock control, and the reset protocol are in",
        "`tests/testbook/fixtures.yaml`. The working dev site is never mutated.",
        "",
        "## How to add a case",
        "",
        "1. Add the setting/feature/state to `dimensions.yaml` (id, values, source, risk, tags).",
        "2. Add the dimension to a case in `cases.yaml` or add a surface/flow/triple case.",
        "3. Run `python3 tests/testbook/testbook.py generate`.",
        "4. Run `python3 tests/testbook/testbook.py validate`.",
        "5. Use the `test-author` skill: name the source change that would fail the case and watch it fail.",
        "6. A new setting key, route, screen, or flag without a `freshness.yaml` mapping fails validate.",
        "",
        "## Release sign-off",
        "",
        "- Full book: `python3 tests/testbook/testbook.py validate` plus the phase-4 runner.",
        "- Multisite: the dedicated multisite site, not the single-site filter.",
        "- E2E, JS, `lint`, `build`, Plugin Check, POT and zip are in the release skill.",
        "- A feature without a case is not releasable.",
        "",
        "## Case-ID and tag scheme",
        "",
        "`TB-<GROUP>-<NNN>` for cases, `PAIR-<NNN>` for pairwise rows,",
        "`COMBO-<NNN>` for triples, `INV-<NN>` for invariants. Core tags:",
        "`@free`, `@pro`, `@multisite`, `@license`, `@pass`, `@flow`,",
        "`@version-lock`, `@schedule`, `@logger`, `@export`, `@a11y`, `@combo`,",
        "`@invariant`, `@smoke`, `@release-only`.",
    ]
    BOOK.parent.mkdir(parents=True, exist_ok=True)
    BOOK.write_text("\n".join(lines) + "\n", encoding="utf-8")


def generate() -> int:
    dims_data = load(TB / "dimensions.yaml")
    dims = dims_data["dimensions"]
    cases = load(TB / "cases.yaml")
    pro = load(TB / "pro/cases.yaml")
    invariants = load(TB / "invariants.yaml")
    ids = ensure_case_ids(dims)
    generated = [dimension_case(dim, ids[dim["id"]]) for dim in dims]
    dump(GEN_CASES, {"schema": "updatronix-testbook/generated-cases/v1", "cases": generated})
    combos, pair_count, covered = pairwise(dims)
    rows = []
    for index, combo in enumerate(combos, 1):
        rows.append({"id": f"PAIR-{index:03d}", "kind": "pairwise", "dimensions": combo})
    for triple in cases["triples"]:
        rows.append(
            {
                "id": triple["id"],
                "kind": "triple",
                "dimensions": triple["dimensions"],
                "why": triple.get("why", ""),
            }
        )
    dump(
        COMBOS,
        {
            "schema": "updatronix-testbook/combinations/v1",
            "generated_by": "tests/testbook/testbook.py generate",
            "pair_count": pair_count,
            "covered_pairs": covered,
            "uncovered_pairs": pair_count - covered,
            "pairwise_rows": len(combos),
            "triple_rows": len(cases["triples"]),
            "combinations": rows,
        },
    )
    write_book(dims, ids, generated, cases, pro, invariants, rows, pair_count)
    print(f"cases: {len(generated)} dimension + {len(cases['surface_cases'])} surface + {len(cases['flow_cases'])} flow + {len(cases['triples'])} triple + {len(pro['cases'])} pro")
    print(f"combinations: {len(combos)} pairwise rows, {len(cases['triples'])} triples, {covered}/{pair_count} pairs covered")
    print(f"wrote {GEN_CASES}, {COMBOS}, {BOOK}")
    return 0


def discover_settings() -> tuple[list[str], list[str]]:
    def section(text: str, start: str, end: str) -> str:
        i = text.find(start)
        if i < 0:
            return ""
        j = text.find(end, i + len(start))
        return text[i : j if j >= 0 else len(text)]

    free = (ROOT / "inc/settings/options.php").read_text(encoding="utf-8")
    pro = (ROOT.parent / "updatronix-pro/inc/settings/options.php").read_text(encoding="utf-8")
    key = re.compile(r"^\s*'([a-z0-9_]+)'\s*=>", re.M)
    return (
        sorted(set(key.findall(section(free, "const UPDATRONIX_SETTINGS_DEFAULTS = array(", ");")))),
        sorted(set(key.findall(section(pro, "UPDATRONIX_PRO_SHIELD_DEFAULTS", ");")))),
    )


def discover_routes() -> list[str]:
    pattern = re.compile(r"register_rest_route\s*\(\s*[^,]+,\s*'([^']+)'", re.S)
    routes = set()
    for root in (ROOT / "inc", ROOT.parent / "updatronix-pro/inc"):
        for path in root.rglob("*.php"):
            routes.update(pattern.findall(path.read_text(encoding="utf-8", errors="replace")))
    return sorted(routes)


def discover_flags() -> list[str]:
    pattern = re.compile(r"apply_filters\s*\(\s*'(updatronix(?:_pro)?_[a-z0-9_]+)'")
    flags = set()
    for root in (ROOT / "inc", ROOT.parent / "updatronix-pro/inc"):
        for path in root.rglob("*.php"):
            flags.update(pattern.findall(path.read_text(encoding="utf-8", errors="replace")))
    return sorted(f for f in flags if f.endswith("_enabled") or "kill" in f)


def discover_screens() -> list[str]:
    text = (ROOT / "inc/core/tabs.php").read_text(encoding="utf-8")
    return sorted(set(re.findall(r"'slug'\s*=>\s*'([^']+)'", text)))


def validate() -> int:
    dims = load(TB / "dimensions.yaml")["dimensions"]
    invariants = load(TB / "invariants.yaml")["invariants"]
    cases = load(TB / "cases.yaml")
    pro = load(TB / "pro/cases.yaml")
    generated = load(GEN_CASES)["cases"] if GEN_CASES.exists() else []
    ids = load(CASE_IDS) if CASE_IDS.exists() else {}
    freshness = load(TB / "freshness.yaml")
    errors = []
    if len(dims) != 88:
        errors.append(f"expected 88 dimensions, found {len(dims)}")
    for dim in dims:
        if dim["id"] not in ids:
            errors.append(f"dimension without case id: {dim['id']}")
        if not any(c.get("dimensions") == [dim["id"]] for c in generated):
            errors.append(f"dimension without generated case: {dim['id']}")
    all_cases = generated + cases["surface_cases"] + cases["flow_cases"] + cases["triples"] + pro["cases"]
    seen = set()
    ids_set = set()
    for case in all_cases:
        for key in REQUIRED:
            if key not in case:
                errors.append(f"{case.get('id', '<no-id>')}: missing {key}")
        if case.get("id") in seen:
            errors.append(f"duplicate case id: {case.get('id')}")
        seen.add(case.get("id"))
        ids_set.add(case.get("id"))
        if not all(str(tag).startswith("@") for tag in case.get("tags", [])):
            errors.append(f"{case.get('id')}: invalid tag")
    sys.path.insert(0, str(TB))
    from runners import RUNNERS

    for inv in invariants:
        if inv["id"] not in RUNNERS:
            errors.append(f"invariant without runner: {inv['id']}")
    free_keys, pro_keys = discover_settings()
    for key in free_keys:
        if key not in freshness.get("settings_keys", {}):
            errors.append(f"new Free setting key unmapped: {key}")
    for key in pro_keys:
        if key not in freshness.get("shield_keys", {}):
            errors.append(f"new Pro Shield key unmapped: {key}")
    for route in discover_routes():
        if route not in freshness.get("routes", {}):
            errors.append(f"new route unmapped: {route}")
    for flag in discover_flags():
        if flag not in freshness.get("feature_flags", {}):
            errors.append(f"new feature flag unmapped: {flag}")
    for screen in discover_screens():
        if screen not in freshness.get("screens", {}):
            errors.append(f"new screen unmapped: {screen}")
    for group in ("settings_keys", "shield_keys", "routes", "screens", "feature_flags"):
        for key, target in freshness.get(group, {}).items():
            if target in ids_set:
                continue
            if target in ids and ids[target] in ids_set:
                continue
            errors.append(f"freshness {group}.{key}: target {target} has no case")
    if not BOOK.exists() or not BOOK.read_text(encoding="utf-8").startswith("<!-- generated"):
        errors.append("book is missing or not generated")
    if COMBOS.exists():
        combo_data = load(COMBOS)
        if combo_data.get("uncovered_pairs"):
            errors.append(f"pairwise uncovered: {combo_data['uncovered_pairs']}")
    if errors:
        print("VALIDATION FAILED")
        for error in errors:
            print(f"- {error}")
        return 1
    print(f"VALIDATION OK: {len(dims)} dimensions, {len(all_cases)} cases, {len(invariants)} invariants, {len(discover_routes())} routes, {len(discover_flags())} flags, {len(free_keys)} Free keys, {len(pro_keys)} Shield keys")
    return 0


def main() -> int:
    command = sys.argv[1] if len(sys.argv) > 1 else "help"
    if command == "generate":
        return generate()
    if command == "validate":
        return validate()
    print(__doc__)
    return 0


if __name__ == "__main__":
    sys.exit(main())
