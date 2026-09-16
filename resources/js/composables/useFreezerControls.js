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
    /**
     * Sending anything at all: permission, a new enough app, and no command already outstanding.
     * `canSend` adds "the machine is online" on top, because a command to an offline freezer is
     * accepted here and then expires unanswered a minute later — the buttons say so instead
     * (Brian, 2026-09-16). Sync stays on `canSync`: it changes nothing and is how you find out
     * whether the machine is really gone.
     */
    const canSync = computed(() => data.value.can_control && data.value.supported && !data.value.pending && !sending.value);
    const canSend = computed(() => canSync.value && !!data.value.is_online);

    /** Why the controls are disabled, for the hover — empty when they are not. */
    const blockedReason = computed(() => {
        if (!data.value.can_control) return 'You do not have permission to send commands to this machine.';
        if (!data.value.supported) return "This machine's app is too old for remote controls.";
        if (data.value.pending || sending.value) return 'Waiting for the machine to answer the last command.';
        if (!data.value.is_online) return 'The machine is offline, so it cannot receive commands. Press Sync now to check.';
        return '';
    });
    /** Older than 10 minutes and the panel stops presenting the snapshot as current. */
    const statusStale = computed(() => !data.value.status_at || now.value - Date.parse(data.value.status_at) > 10 * 60 * 1000);
    const lastSetpoint = computed(() => data.value.setpoint?.last ?? null);

    /**
     * The op we are waiting on, so the row that was pressed can show a spinner instead of the page
     * looking inert for the seconds the freezer takes to answer. Set the moment we POST and kept
     * from the server's own view of the outstanding command afterwards, so a reload (or a second
     * tab) still shows which control is busy.
     */
    const inFlight = ref(null);
    const busyOp = computed(() => {
        if (inFlight.value) return inFlight.value;
        if (!data.value.pending) return null;
        const outstanding = data.value.commands.find((c) => c.source === 'mark1' && c.status === 'pending');
        return outstanding ? outstanding.op : null;
    });

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
        if (op === 'status' ? !canSync.value : !canSend.value) return;
        sending.value = true;
        inFlight.value = op;
        try {
            await axios.post(`/vends/${id()}/freezer-controls`, { op, args });
            await load();
        } catch (e) {
            toast.error(e.response?.data?.message || 'Could not send the command.');
        } finally {
            sending.value = false;
            inFlight.value = null;
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

    return { data, status, loaded, sending, busyOp, canSend, canSync, blockedReason, statusStale, lastSetpoint, limit, now, load, send, setLimit, whenLoaded };
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
    selfcheck: 'Self-check',
    restart: 'Restart app',
    reboot: 'Reboot Android',
    photo: 'Take photo',
    diag: 'Diagnostics',
    sdkcall: 'SDK call',
    power: 'Mains',
    camera: 'Camera',
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
    if (command.op === 'photo') return `Take photo · camera ${args.cameraId ?? 0}`;
    if (command.op === 'diag') return `Diagnostics · ${args.probe || '?'}`;
    if (command.op === 'sdkcall') return `SDK call · ${args.action || '?'}`;
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
