# FEAT-F-HUB · Screens hub

| | |
|---|---|
| Edition | Free, reference only |
| Status | Design artifact. Not shipped by default. |
| Rail | Outside the rail |
| Design | [`../reference/screens/src/screens/Hub.jsx`](../reference/screens/src/screens/Hub.jsx) |
| Plan task | `F4-04` |
| Decision | `A-01`, open `D-13` |

## Problem

The field guide has a screens hub: a product header and a two-column card grid
for every destination. It is useful for design review and implementation
navigation. It is not one of the eleven destinations, and the guide places it
outside the rail.

## Outcome

By default, the hub is not shipped. The vendored source remains the map of the
screen set. If the product decides to ship it, this brief becomes a twelfth
destination and must meet the requirements below.

## Default decision

`A-01`: the hub is a design-review artifact. Shipping it would add a
navigation surface that duplicates the rail and has no task of its own.

## If it ships

### Target behavior

1. The page header is `Updatronix screens`, with a short product description.
2. A two-column card grid covers every destination the current user can access.
3. Each card has an icon, a title, a one-sentence description, and badges that
   name the surface type.
4. Selecting a card navigates to the destination.
5. Pro destinations show a `Pro` badge and route to the upgrade page when Pro is
   inactive.

### Data

Derive the cards from the destination registry, not a second hardcoded list.
The registry already carries slug, label, description, group, and `pro`.

### Security

- Respect the destination capability: do not render a card the user cannot open.
- No new REST route.

### Accessibility

- The grid is a list of links, not a grid of clickable divs.
- Each card's accessible name includes the title and the surface-type badges.

### Tests

- Registry-derived card count.
- Capability filtering.
- Pro badge and upgrade routing.

## Acceptance for the default

- The decision is recorded in `02-decisions.md`.
- No hub route, component, or dead data file is shipped.
- The vendored reference remains available to implementers.

## Resolved decision

`D-13`: the hub does not ship. It remains a design reference outside the rail.
