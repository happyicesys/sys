import { ref } from 'vue';
import axios from 'axios';
import moment from 'moment';
import { useToast } from 'vue-toastification';

/**
 * The SAVED machine's on-hand qty per SKU (VendStockQtyController), with each row's last hand
 * overwrites. Shared by the chiller's Stock Qty table and the freezer's planogram, which shows
 * the same rows inside its basket cells instead of as a separate table (Brian, 2026-10-03).
 *
 * @param {import('vue').Ref<number>|(() => number)|number} vendId
 */
export function useStockQty(vendId) {
    const id = () => (typeof vendId === 'function' ? vendId() : (typeof vendId === 'object' ? vendId.value : vendId));
    const toast = useToast();

    const channels = ref([]);
    const refusal = ref(null);
    const isChiller = ref(false);
    const loading = ref(false);

    function load() {
        if (!id()) return Promise.resolve();
        loading.value = true;
        return axios.get('/vends/' + id() + '/stock-qty')
            .then((res) => {
                channels.value = res.data.channels || [];
                refusal.value = res.data.refusal;
                isChiller.value = !!res.data.is_chiller;
            })
            .catch(() => toast.error('Could not load stock qty', { timeout: 4000 }))
            .finally(() => { loading.value = false });
    }

    return { channels, refusal, isChiller, loading, load };
}

/** One hand overwrite as "brian · 260930 11:03 pm (3 → 7)". */
export function stockQtyLine(entry) {
    const at = entry.at ? moment(entry.at).format('YYMMDD hh:mm a') : '';
    return (entry.who || 'unknown') + ' · ' + at + ' (' + entry.from + ' → ' + entry.to + ')';
}
