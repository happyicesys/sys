// Internet-link (SIM card) display helpers.
//
// Source of truth: the APK reports its link in the VENDER packet ("Internet"
// key: big board v302+, smart freezer) and SyncVendParameter promotes it onto
// vends.internet_source / internet_provider / internet_network /
// internet_signal / internet_signal_max / internet_updated_at.
//
// Shared by Vend/CustomerIndex.vue (the Error column's "SIM Card" block) and
// Simcard/Index.vue (the "Signal Strength" column), so both surfaces name the
// carrier and color the signal identically.

/**
 * Title line: "StarHub 4G" / "Wi-Fi HappyIce" / "LAN" / "No Link".
 * Carrier or SSID first because that is what ops recognise; the generation is
 * the detail. `link` is any object carrying the vends.internet_* fields.
 */
export function internetLinkTitle(link) {
    const source = link.internet_source;
    const provider = link.internet_provider;
    const network = link.internet_network;
    if (source === 'none') return 'No Link';
    if (source === 'lan') return 'LAN';
    if (source === 'wifi') return provider ? 'Wi-Fi ' + provider : 'Wi-Fi';
    if (source === 'telco') {
        const parts = [provider || 'Telco'];
        if (network) parts.push(network);
        return parts.join(' ');
    }
    return provider || network || 'Internet';
}

/**
 * Signal on the canonical 5-bar scale, or null when the machine could not
 * read one (LAN, or a ROM with no signal API). Devices declare their own
 * scale (internet_signal_max), so a non-5 scale is normalised to /5.
 */
export function signalLevel(link) {
    if (link.internet_signal === null || link.internet_signal === undefined) return null;
    const max = link.internet_signal_max || 5;
    return max === 5 ? link.internet_signal : Math.round((link.internet_signal / max) * 5);
}

/** "3/5" text for the signal pill, or null when unreadable. */
export function signalBars(link) {
    const lvl = signalLevel(link);
    return lvl === null ? null : lvl + '/5';
}

/**
 * Signal pill color — absolute bars, per Daniel (2026-08-24):
 * 1-2 red, 3 yellow, 4-5 green. No link at all is red; a link whose signal
 * can't be read (LAN) is neutral gray — absence of evidence is not a bad link.
 */
export function signalBadgeClass(link) {
    if (link.internet_source === 'none') return 'bg-red-200';
    const lvl = signalLevel(link);
    if (lvl === null) return 'bg-gray-200';
    if (lvl <= 2) return 'bg-red-200';
    if (lvl === 3) return 'bg-yellow-200';
    return 'bg-green-200';
}
