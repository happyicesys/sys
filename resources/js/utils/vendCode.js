// Machine ID as people see it: vends.code_prefix + vends.code ("C6003" for a
// CityBox chiller, "2031" for everything mark1 numbers itself). Mirrors
// App\Support\VendCode::label. Accepts a VendResource payload (code_label) or a
// raw vend row (code_prefix + code).
export function vendCodeLabel(vend) {
  if (!vend) return ''
  if (vend.code_label) return vend.code_label
  return (vend.code_prefix || '') + (vend.code ?? '')
}
