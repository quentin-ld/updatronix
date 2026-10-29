# Pro test-book supplement

`updatronix-pro` adds cases, not a second book. The shared catalog in
`tests/testbook/` owns the dimensions, invariants, fixtures, and the generated
Free/Pro cases; this directory owns only the Pro lifecycle and network flows
that need the Pro plugin and the live license.

- `cases.yaml` — eight Pro cases (`TB-PRO-*`) that extend `../cases.yaml`.
- The Pro checkout reads the shared `dimensions.yaml`, `invariants.yaml`, and
  `fixtures.yaml` from the sibling Free checkout. The path is resolved from the
  harness project root; the book is not copied into Pro.
- The live license key is supplied through `UPDATRONIX_PRO_TEST_LICENSE_KEY` and
  is never stored here.
- A Pro-only dimension added to the shared catalog must get a case in
  `../cases.yaml` or here; the freshness check fails otherwise.
