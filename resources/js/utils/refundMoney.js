// Amounts on the refund pages, in the machine's money (`RefundRegion::toArray()`): "$3.50", or "Rp 20,000"
// where the currency has no decimals. Takes a number or the "20,000.00" the server formats.
export function formatRefundMoney(value, region) {
    const n = Number(String(value ?? 0).replace(/,/g, '')) || 0;
    return region?.whole_units
        ? `${region.symbol} ${Math.round(n).toLocaleString('en-US')}`
        : `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
