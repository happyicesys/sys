// Machine ID as people see it: vends.code_prefix + vends.code ("C6003" for a
// CityBox chiller, "2031" for everything mark1 numbers itself). Mirrors
// App\Support\VendCode::label. Accepts a VendResource payload (code_label) or a
// raw vend row (code_prefix + code).
export function vendCodeLabel(vend) {
  if (!vend) return ''
  if (vend.code_label) return vend.code_label
  return (vend.code_prefix || '') + (vend.code ?? '')
}

// The machine's Operation Dashboard row in a new tab: its label in the Machine ID filter,
// searched at once. The trailing comma makes the filter match that code exactly
// (VendCode::whereSearch), not every code starting with it. Product-restricted viewers
// and Lite-only roles get Dashboard (Lite) instead, since the full Dashboard 403s them
// (BlockWhenProductRestricted). Null when the viewer can open neither.
export function opsDashboardUrl(code, auth) {
  if (code === null || code === undefined || code === '') return null
  const permissions = auth?.permissions || []
  const query = '?codes=' + encodeURIComponent(String(code) + ',') + '&autoload=true'
  if (permissions.includes('read vends') && !auth?.productRestricted) return '/vends/customers' + query
  if (permissions.includes('read vend-customers-lite')) return '/vends/customers-lite' + query
  return null
}
