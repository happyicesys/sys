// Shared SimCard Package (Telco) colour helpers.
//
// A package's colour is stored on `telcos.color` as one of the five keys
// below, or NULL when nobody has picked one.
//
// The palette is deliberately five colours (Brian, 2026-09-09). Green, grey
// and pink/red are NOT offered: the Operation Dashboard already spends those
// three on machine status (online / N-A / offline), so tinting a package with
// one would read as a machine state.
//
// Every surface that renders a package badge (Data Management > SimCard
// Package, the Operation Dashboard's Machine Status column) must build its
// style from `telcoBadgeStyle()` so a package looks the same everywhere.
//
// NOTE: the tint is applied as an INLINE STYLE on purpose — the same reason
// stickerColors.js gives. Tailwind only scans .vue files here, so a dynamic
// `bg-${color}-100` would be purged from the production build.

// Light tints, one step apart from each other and readable under gray-800
// text. `blue` is the shade the badge has always used, which is why it is
// also what a package with no colour falls back to.
export const TELCO_COLORS = [
  { id: 'yellow', name: 'Yellow', hex: '#FEF08A' },
  { id: 'orange', name: 'Orange', hex: '#FED7AA' },
  { id: 'white', name: 'White', hex: '#FFFFFF' },
  { id: 'purple', name: 'Purple', hex: '#E9D5FF' },
  { id: 'blue', name: 'Blue', hex: '#DBEAFE' },
]

// bg-blue-100 / gray-300 — the badge's look before colours existed.
const DEFAULT_HEX = '#DBEAFE'
const BORDER_HEX = '#D1D5DB'
const TEXT_HEX = '#1F2937'

/** The palette entry for a stored key, or null for null/unknown values. */
export function telcoColor(color) {
  return TELCO_COLORS.find((option) => option.id === color) || null
}

/** Human label for the Index's Color column: 'Yellow', or 'Default' when unset. */
export function telcoColorName(color) {
  const option = telcoColor(color)

  return option ? option.name : 'Default'
}

/**
 * Inline style for a package badge/pill. `color` may be null or a key that is
 * no longer in the palette — both fall back to the default blue tint.
 *
 * A border is always drawn so the White tint stays visible on a white row.
 */
export function telcoBadgeStyle(color) {
  const option = telcoColor(color)

  return {
    backgroundColor: option ? option.hex : DEFAULT_HEX,
    color: TEXT_HEX,
    border: '1px solid ' + BORDER_HEX,
  }
}

/** Inline style for the small square swatch beside the colour's name. */
export function telcoSwatchStyle(color) {
  const option = telcoColor(color)

  return {
    backgroundColor: option ? option.hex : DEFAULT_HEX,
    border: '1px solid ' + BORDER_HEX,
  }
}
