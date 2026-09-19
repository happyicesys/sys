// The machine-bound stops that ride on an ops job beside a normal top-up item.
// One table so the job page, the route page and the rows agree on the wording,
// the colours and the URLs. Keys are the server's stop types
// (App\Support\OpsJobStopRegistry).
export const STOP_TYPES = {
  service_notice: {
    label: 'Service Notice',
    badge: 'SERVICE',
    basePath: '/service-notices',
    rowClasses: ['bg-amber-50', 'bg-amber-100'],
    badgeClass: 'bg-amber-600 text-white',
    textClass: 'text-amber-800',
    inputClass: 'border-amber-300 focus:ring-amber-500 focus:border-amber-500',
    buttonClass: 'bg-amber-500 hover:bg-amber-600 text-white',
  },
  stock_check: {
    label: 'Stock Count',
    badge: 'COUNT',
    basePath: '/stock-checks',
    rowClasses: ['bg-cyan-50', 'bg-cyan-100'],
    badgeClass: 'bg-cyan-700 text-white',
    textClass: 'text-cyan-800',
    inputClass: 'border-cyan-300 focus:ring-cyan-500 focus:border-cyan-500',
    buttonClass: 'bg-cyan-600 hover:bg-cyan-700 text-white',
  },
}

export function isStopRow(row) {
  return !!row && Object.prototype.hasOwnProperty.call(STOP_TYPES, row.stop_type)
}

// Pending 1 · done 3 · cancelled 99 — the same three states for both stop types.
export function stopStatusClass(status) {
  if (status === 3) return 'bg-green-100 text-green-800 border-green-300'
  if (status === 99) return 'bg-gray-200 text-gray-600 border-gray-300 line-through'
  return 'bg-yellow-100 text-yellow-800 border-yellow-300'
}

// Laravel's 422 body -> { field: 'first message' }
export function firstErrors(error) {
  const errors = error?.response?.data?.errors
  if (!errors) {
    return { _: error?.response?.data?.message || 'Something went wrong. Please try again.' }
  }
  return Object.fromEntries(Object.entries(errors).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value]))
}
