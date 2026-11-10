import { t } from '@/i18n';

// Shared by the Grab Menu & Prices components: money, a price rule in plain words, and the server's pricing maths.

export const inputClass = 'mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md'
export const primaryClass = 'rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50'
export const secondaryClass = 'rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50'
export const dangerClass = 'rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50 disabled:opacity-50'

export function money(cents) {
  return cents === null || cents === undefined ? '—' : '$' + (Number(cents) / 100).toFixed(2)
}

export function rpName(rpTypes, rpType) {
  return rpTypes.find(rp => rp.id === Number(rpType))?.name ?? 'RP'
}

function stepName(cents) {
  return Number(cents) === 100 ? t('the dollar') : t(':cents¢', { cents })
}

// "RP2 × 1.25, rounded up to 10¢" — the same words everywhere a rule is shown.
export function ruleText(rule, rpTypes) {
  if (!rule) return ''
  const rp = rpName(rpTypes, rule.rp_type)
  if (rule.pricing_mode === 'custom') return t('A typed price for each product')
  if (rule.pricing_mode === 'rp_multiplier') {
    return t(':rp × :multiplier, rounded up to :step', { rp, multiplier: Number(rule.multiplier || 1), step: stepName(rule.round_up_cents) })
  }
  return t('The :rp price as it is', { rp })
}

// The same integer maths as GrabPriceRule::multiply on the server.
export function multiply(cents, multiplier, stepCents) {
  const bp = Math.round(Number(multiplier || 1) * 10000)
  const step = Math.max(1, Number(stepCents) || 10)
  return Math.ceil((cents * bp) / (step * 10000)) * step
}

// What the rule would charge for a product whose RP is $cents (null when the rule has no example: custom).
export function examplePrice(rule, cents) {
  if (!rule || rule.pricing_mode === 'custom') return null
  return rule.pricing_mode === 'rp_multiplier' ? multiply(cents, rule.multiplier, rule.round_up_cents) : cents
}

export function problemName(problem, rp = 'RP') {
  return {
    no_rp: t('No :rp price, so not on Grab', { rp }),
    no_custom_price: t('No typed price, so not on Grab'),
    no_sub_category: t('No Grab sub-category, so not on Grab'),
  }[problem] ?? problem
}

// The rule fields a form edits and posts, from a rule the server sent.
export function ruleFields(rule) {
  return {
    pricing_mode: rule?.pricing_mode ?? 'rp',
    rp_type: rule?.rp_type ?? 2,
    multiplier: rule?.multiplier ?? 1,
    round_up_cents: rule?.round_up_cents ?? 10,
  }
}

export function sameRule(a, b) {
  if (!a || !b || a.pricing_mode !== b.pricing_mode) return false
  if (a.pricing_mode === 'custom') return true
  if (Number(a.rp_type) !== Number(b.rp_type)) return false
  if (a.pricing_mode === 'rp') return true
  return Number(a.multiplier) === Number(b.multiplier) && Number(a.round_up_cents) === Number(b.round_up_cents)
}

// Search: every word must appear in the text.
export function matchesWords(text, search) {
  const words = String(search ?? '').toLowerCase().split(/\s+/).filter(Boolean)
  const haystack = String(text ?? '').toLowerCase()
  return words.every(word => haystack.includes(word))
}
