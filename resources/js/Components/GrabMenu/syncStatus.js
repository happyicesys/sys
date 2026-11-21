import { t } from '@/i18n'
import moment from 'moment'

// Grab Menu Sync wording, shared by the Grab Menu Sync page and Grab Menu & Prices' "On Grab" column.

const LOOKS = {
  synced: { badge: 'bg-green-100 text-green-800', text: 'text-green-700', label: () => t('Synced') },
  waiting: { badge: 'bg-gray-100 text-gray-700', text: 'text-gray-500', label: () => t('Waiting to send') },
  sent: { badge: 'bg-blue-100 text-blue-800', text: 'text-blue-700', label: () => t('Sent, waiting for Grab') },
  pulled: { badge: 'bg-blue-100 text-blue-800', text: 'text-blue-700', label: () => t('Grab took it, confirming') },
  turned_away: { badge: 'bg-amber-100 text-amber-800', text: 'text-amber-700', label: () => t('Turned away, retrying') },
  failed: { badge: 'bg-red-100 text-red-800', text: 'text-red-700', label: () => t('Not synced') },
  skipped: { badge: 'bg-gray-100 text-gray-600', text: 'text-gray-500', label: () => t('Not sent') },
}

// Still moving: the page keeps checking while any row is in one of these.
export const MOVING = ['waiting', 'sent', 'pulled', 'turned_away']

export function syncLook(status) {
  const look = LOOKS[status] ?? LOOKS.waiting
  return { ...look, label: look.label() }
}

export function when(value) {
  if (!value) return ''
  const at = moment(value)
  return at.isSame(moment(), 'day') ? at.format('HH:mm') : at.format('D MMM HH:mm')
}

// The short line under the badge: when it happened, or when it is asked again.
export function syncDetail(row) {
  switch (row.status) {
    case 'synced':
      return row.last_checked_at && moment(row.last_checked_at).isAfter(row.synced_at ?? 0)
        ? t('Since :at · checked :checked', { at: when(row.synced_at), checked: when(row.last_checked_at) })
        : t('Since :at', { at: when(row.synced_at) })
    case 'turned_away':
      return row.retry_at
        ? t('Grab said :code · asked :count times · next at :at', { code: row.last_code ?? t('nothing'), count: row.attempts, at: when(row.retry_at) })
        : t('Grab said :code', { code: row.last_code ?? t('nothing') })
    case 'sent':
      return t('Asked at :at', { at: when(row.sent_at) })
    case 'pulled':
      return t('Grab took it at :at', { at: when(row.pulled_at) })
    case 'failed':
      return row.last_code ? t('Grab said :code · asked :count times', { code: row.last_code, count: row.attempts }) : ''
    case 'waiting':
      return t('Queued at :at', { at: when(row.updated_at) })
    default:
      return ''
  }
}
