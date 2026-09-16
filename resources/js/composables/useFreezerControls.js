import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
import { FreezerStatus } from '@/support/freezerStatus';

/**
 * Talks to FreezerControlController for one freezer: polls its state, sends one command at a time,
 * and reports the verdict. Kept out of the panel component so the markup is only markup — the panel
 * was 500 lines of layout and transport together, and the polling rules below are the part that is
 * easy to get wrong.
 *
 * Polling: 2 s while a command is outstanding (the answer is what the user is waiting for), 20 s
 * otherwise, and nothing at all while the tab is hidden — a dashboard left open overnight should not
 * wake the machine's 4G link every 20 s.
 *
 * @param {import('vue').Ref<number>|number} vendId
 */
export function useFreezerControls(vendId) {
    const id = () => (typeof vendId === 'object' ? vendId.value : vendId);
    const toast = useToast();

    const loaded = ref(false);
    const sending = ref(false);
    const limit = ref(20);
    const now = ref(Date.now());
    const data = ref({ commands: [], setpoint: { min: -30, max: -5 }, pending: false, supported: false });

    const status = computed(() => FreezerStatus.from(data.value.status));
    const canSend = computed(() => data.value.can_control && data.value.supported && !data.value.pending && !sending.value);
    /** Older than 10 minutes and the panel stops presenting the snapshot as current. */
    const statusStale = computed(() => !data.value.status_at || now.value - Date.parse(data.value.status_at) > 10 * 60 * 1000);
    const lastSetpoint = computed(() => data.value.setpoint?.last ?? null);

    let timer = null;
    let firstLoad = true;
    const onFirstLoad = [];

    function schedule() {
        clearTimeout(timer);
        now.value = Date.now();
        if (document.hidden) return;
        timer = setTimeout(load, data.value.pending ? 2000 : 20000);
    }

    async function load() {
        try {
            const res = await axios.get(`/vends/${id()}/freezer-controls`, { params: { limit: limit.value } });
            const wasPending = data.value.pending;
            data.value = res.data;
            loaded.value = true;
            if (firstLoad) {
                firstLoad = false;
                onFirstLoad.forEach((fn) => fn(res.data));
            }
            // The command finished while we were polling: say how it went, once.
            if (wasPending && !res.data.pending) {
                const last = res.data.commands.find((c) => c.source === 'mark1');
                if (last && last.op !== 'status') {
                    last.status === 'ok'
                        ? toast.success(`${describeCommand(last)}: done`)
                        : toast.warning(`${describeCommand(last)}: ${resultLabel(last.status)}`);
                }
            }
        } catch (e) {
            loaded.value = true;
        }
        schedule();
    }

    async function send(op, args = {}) {
        if (!canSend.value) return;
        sending.value = true;
        try {
            await axios.post(`/vends/${id()}/freezer-controls`, { op, args });
            await load();
        } catch (e) {
            toast.error(e.response?.data?.message || 'Could not send the command.');
        } finally {
            sending.value = false;
        }
    }

    function setLimit(value) {
        limit.value = value;
        load();
    }

    /** Runs once, after the first successful poll — the setpoint stepper opens on the stored value. */
    function whenLoaded(fn) {
        onFirstLoad.push(fn);
    }

    function onVisibility() {
        if (!document.hidden) load();
    }

    onMounted(() => {
        load();
        document.addEventListener('visibilitychange', onVisibility);
    });
    onUnmounted(() => {
        clearTimeout(timer);
        document.removeEventListener('visibilitychange', onVisibility);
    });

    return { data, status, loaded, sending, canSend, statusStale, lastSetpoint, limit, now, load, send, setLimit, whenLoaded };
}

const OP_LABELS = {
    status: 'Sync status',
    lock: 'Lock door',
    unlock: 'Unlock door',
    fan: 'Cabinet fan',
    light: 'Light',
    compressor: 'Compressor',
    comprmode: 'Compressor control',
    setpoint: 'Setpoint',
    volume: 'Volume',
    logs: 'Pull logs',
    boot: 'Machine booted',
};

const RESULT_LABELS = {
    pending: 'waiting',
    ok: 'done',
    refused: 'refused',
    indeterminate: 'no answer from host',
    unsupported: 'not supported',
    busy: 'busy (sale)',
    invalid: 'invalid',
    expired: 'expired',
    duplicate: 'duplicate',
    error: 'error',
    timeout: 'no answer',
};

/** One row of the timeline in words — shared by the table and the toast. */
export function describeCommand(command) {
    const args = command.args || {};
    if (command.op && command.op.startsWith('error:')) return `Error logged by ${command.op.slice(6)}`;
    if ('on' in args) return `${OP_LABELS[command.op]} ${args.on ? 'on' : 'off'}`;
    if ('celsius' in args) return `${OP_LABELS[command.op]} ${args.celsius} °C`;
    if ('step' in args) return `${OP_LABELS[command.op]} ${args.step}`;
    if (command.op === 'logs') {
        return 'Pull logs' + (args.minutes ? ` · last ${args.minutes} min` : '') + (args.grep ? ` · "${args.grep}"` : '');
    }
    return OP_LABELS[command.op] || command.op;
}

export function resultLabel(status) {
    return RESULT_LABELS[status] || status;
}

export function resultBadge(status) {
    if (status === 'ok') return 'bg-green-100 text-green-800';
    if (status === 'pending') return 'bg-sky-100 text-sky-800 animate-pulse';
    if (['timeout', 'indeterminate', 'busy'].includes(status)) return 'bg-amber-100 text-amber-800';
    if (status === 'duplicate') return 'bg-gray-100 text-gray-700';
    return 'bg-red-100 text-red-800';
}
