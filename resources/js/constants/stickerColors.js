// Shared Machine Sticker (VendSticker) colour helpers.
//
// A sticker's colour is stored on `vend_stickers.color` as a 6-digit hex
// string (`#RRGGBB`) or NULL when no colour has been picked yet.
//
// Every surface that renders a sticker badge (Data Management > Machine
// Sticker, Vend Serial Number index, ...) must build its inline style from
// `stickerBadgeStyle()` so all pages look identical and stay readable.
//
// NOTE: the badge colour is applied as an INLINE STYLE on purpose.
// Tailwind purges unseen class names, so a dynamic `bg-[${hex}]` /
// `bg-${name}-100` would be stripped from the production build.

// Preset palette offered in the colour picker. Chosen to cover the physical
// sticker colours in the field plus a few neutrals.
export const STICKER_PRESET_COLORS = [
  { name: 'White', hex: '#FFFFFF' },
  { name: 'Light Grey', hex: '#E5E7EB' },
  { name: 'Grey', hex: '#9CA3AF' },
  { name: 'Black', hex: '#111827' },
  { name: 'Red', hex: '#EF4444' },
  { name: 'Orange', hex: '#F97316' },
  { name: 'Yellow', hex: '#FACC15' },
  { name: 'Green', hex: '#22C55E' },
  { name: 'Teal', hex: '#14B8A6' },
  { name: 'Sky', hex: '#0EA5E9' },
  { name: 'Blue', hex: '#3B82F6' },
  { name: 'Indigo', hex: '#6366F1' },
  { name: 'Purple', hex: '#A855F7' },
  { name: 'Pink', hex: '#EC4899' },
  { name: 'Brown', hex: '#92400E' },
]

// Look of a sticker that has no colour assigned: plain white chip, grey text.
const NEUTRAL_BADGE_STYLE = {
  backgroundColor: '#FFFFFF',
  color: '#374151',
  border: '1px solid #D1D5DB',
}

/**
 * Accepts '#abc', 'abc', '#AABBCC', 'aabbcc' (any casing, surrounding spaces).
 * Returns '#AABBCC' or null when the value is not a usable hex colour.
 */
export function normalizeHex(value) {
  if (typeof value !== 'string') {
    return null
  }

  const raw = value.trim().replace(/^#/, '')

  if (/^[0-9a-fA-F]{3}$/.test(raw)) {
    return ('#' + raw[0] + raw[0] + raw[1] + raw[1] + raw[2] + raw[2]).toUpperCase()
  }

  if (/^[0-9a-fA-F]{6}$/.test(raw)) {
    return ('#' + raw).toUpperCase()
  }

  return null
}

function hexToRgb(hex) {
  const normalized = normalizeHex(hex)
  if (!normalized) {
    return null
  }

  return {
    r: parseInt(normalized.slice(1, 3), 16),
    g: parseInt(normalized.slice(3, 5), 16),
    b: parseInt(normalized.slice(5, 7), 16),
  }
}

function clampChannel(value) {
  return Math.max(0, Math.min(255, Math.round(value)))
}

function toHexChannel(value) {
  return clampChannel(value).toString(16).padStart(2, '0').toUpperCase()
}

/**
 * Lighten (positive percent) or darken (negative percent) a hex colour.
 * Returns the input unchanged when it cannot be parsed.
 */
export function shadeHex(hex, percent) {
  const rgb = hexToRgb(hex)
  if (!rgb) {
    return hex
  }

  const factor = 1 + (percent / 100)

  return '#' + toHexChannel(rgb.r * factor) + toHexChannel(rgb.g * factor) + toHexChannel(rgb.b * factor)
}

// WCAG relative luminance.
function relativeLuminance({ r, g, b }) {
  const [rs, gs, bs] = [r, g, b].map((channel) => {
    const c = channel / 255
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4)
  })

  return (0.2126 * rs) + (0.7152 * gs) + (0.0722 * bs)
}

function contrastRatio(luminanceA, luminanceB) {
  const lighter = Math.max(luminanceA, luminanceB)
  const darker = Math.min(luminanceA, luminanceB)

  return (lighter + 0.05) / (darker + 0.05)
}

// Pure black / pure white are used on purpose: they push the worst-case
// contrast ratio for an arbitrary user-picked colour above the WCAG AA
// 4.5:1 body-text threshold (gray-900 text only reaches ~4.2:1).
const DARK_TEXT = '#000000'
const LIGHT_TEXT = '#FFFFFF'
const DARK_TEXT_LUMINANCE = 0
const LIGHT_TEXT_LUMINANCE = 1

/**
 * Picks black or white text — whichever contrasts better against `hex`.
 */
export function readableTextColor(hex) {
  const rgb = hexToRgb(hex)
  if (!rgb) {
    return DARK_TEXT
  }

  const background = relativeLuminance(rgb)

  return contrastRatio(background, DARK_TEXT_LUMINANCE) >= contrastRatio(background, LIGHT_TEXT_LUMINANCE)
    ? DARK_TEXT
    : LIGHT_TEXT
}

/**
 * Inline style for a sticker badge/pill.
 * `color` may be null/''/invalid — those fall back to the neutral white chip.
 */
export function stickerBadgeStyle(color) {
  const hex = normalizeHex(color)

  if (!hex) {
    return { ...NEUTRAL_BADGE_STYLE }
  }

  return {
    backgroundColor: hex,
    color: readableTextColor(hex),
    // Keeps white / very light stickers visible on a white row.
    border: '1px solid ' + shadeHex(hex, -15),
  }
}

/**
 * Inline style for a small square/round colour swatch.
 */
export function stickerSwatchStyle(color) {
  const hex = normalizeHex(color)

  if (!hex) {
    return { backgroundColor: '#FFFFFF', border: '1px dashed #9CA3AF' }
  }

  return { backgroundColor: hex, border: '1px solid ' + shadeHex(hex, -20) }
}
