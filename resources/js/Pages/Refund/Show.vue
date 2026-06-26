<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    ticket: { type: Object, required: true },
    emailTemplates: { type: Object, default: () => ({}) },
    statuses: { type: Object, default: () => ({}) },
});

const page = usePage();
const can = (p) => (page.props.auth?.roles || []).includes('superadmin') || (page.props.auth?.permissions || []).includes(p);

const t = computed(() => props.ticket);
const showReject = ref(false);
const rejectRemarks = ref('');
const emailTemplate = ref(Object.keys(props.emailTemplates)[0] || '');
const busy = ref(false);

function post(url, data = {}) {
    busy.value = true;
    router.post(url, data, { preserveScroll: true, onFinish: () => (busy.value = false) });
}
const base = computed(() => '/refunds/' + t.value.id);

function doReject() {
    post(base.value + '/reject', { remarks: rejectRemarks.value });
    showReject.value = false;
}
function toggleItem(item) {
    post(base.value + '/items/' + item.id, { approved: !item.approved });
}

const recClass = (r) => ({ proceed: 'text-green-700 bg-green-50 border-green-200', review: 'text-amber-700 bg-amber-50 border-amber-200', reject: 'text-red-700 bg-red-50 border-red-200' }[r] || 'text-gray-600 bg-gray-50 border-gray-200');
const statusClass = (s) => ({ submitted: 'bg-yellow-100 text-yellow-800', auto_resolved: 'bg-cyan-100 text-cyan-800', verified: 'bg-blue-100 text-blue-800', rejected: 'bg-red-100 text-red-800', pending_approval: 'bg-indigo-100 text-indigo-800', approved: 'bg-indigo-100 text-indigo-800', pending_transfer_info: 'bg-orange-100 text-orange-800', scheduled: 'bg-violet-100 text-violet-800', completed: 'bg-green-100 text-green-800' }[s] || 'bg-gray-100 text-gray-700');

const isPaynow = computed(() => t.value.refund_method === 'paynow');
const s = computed(() => t.value.status);
</script>

<template>
<Head :title="'Refund ' + t.reference" />
<BreezeAuthenticatedLayout>
    <template #header>
        <div class="flex items-center gap-3">
            <Link href="/refunds" class="text-teal-600 text-sm">← Refunds</Link>
            <h2 class="font-semibold text-xl text-gray-800">{{ t.reference }} · {{ t.vend_code }}</h2>
            <span class="text-xs font-bold px-2 py-1 rounded-full" :class="statusClass(s)">{{ statuses[s] || s }}</span>
        </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- left: claim + validation + items + logs -->
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-md border p-4">
                <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Claim</h3>
                <div class="grid grid-cols-2 gap-y-1 text-sm">
                    <div class="text-gray-500">Reason</div><div class="font-medium">{{ t.reason_code }} <span v-if="t.reason_text" class="text-gray-500">— {{ t.reason_text }}</span></div>
                    <div class="text-gray-500">Day / amount entered</div><div class="font-medium">{{ t.entered_day }} · ${{ t.entered_amount }}</div>
                    <div class="text-gray-500">Refund amount</div><div class="font-medium">${{ t.amount }}</div>
                    <div class="text-gray-500">Channel</div><div class="font-medium">{{ t.payment_channel }}</div>
                    <div class="text-gray-500">Method</div><div class="font-medium">{{ t.refund_method }}</div>
                    <div class="text-gray-500">Payout to</div><div class="font-medium">{{ t.payout_destination || '—' }}</div>
                    <div class="text-gray-500">Contact</div><div class="font-medium">{{ t.contact_email || t.contact_phone || '—' }}</div>
                </div>
            </div>

            <div class="rounded-md border p-4" :class="recClass(t.recommendation)">
                <h3 class="text-xs uppercase tracking-wide mb-1 opacity-80">System validation — RefundTicket</h3>
                <div class="font-bold capitalize">Recommend: {{ t.recommendation }}</div>
                <div class="text-xs mt-2" v-if="t.is_auto_refund_channel">⚡ Paid via Nayax — refunded automatically by the processor (no manual payout).</div>
                <div class="text-xs mt-1" v-if="t.auto_refund_detected">↩ One or more items were already refunded — guarded against double refund.</div>
                <pre class="text-xs mt-2 whitespace-pre-wrap opacity-70">{{ JSON.stringify(t.system_validation, null, 1) }}</pre>
            </div>

            <div class="bg-white rounded-md border p-4">
                <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Items flagged</h3>
                <table class="min-w-full text-sm">
                    <thead class="text-xs text-gray-500"><tr><th class="text-left py-1">Product</th><th class="text-left">Price</th><th class="text-left">Channel error</th><th class="text-left">Advice</th><th class="text-left">Decision</th></tr></thead>
                    <tbody>
                        <tr v-for="it in t.items" :key="it.id" class="border-t">
                            <td class="py-2">{{ it.product_name }}</td>
                            <td>${{ it.unit_price }}</td>
                            <td>
                                <span v-if="it.had_channel_error" class="text-amber-700">code {{ it.vend_channel_error_code }} (w{{ it.channel_error_weightage }})</span>
                                <span v-else class="text-gray-400">none</span>
                            </td>
                            <td class="capitalize">{{ it.item_recommendation }}</td>
                            <td>
                                <button v-if="can('update refunds')" @click="toggleItem(it)" :disabled="busy"
                                    class="text-xs px-2 py-1 rounded border"
                                    :class="it.approved ? 'bg-green-50 border-green-300 text-green-700' : it.approved === false ? 'bg-red-50 border-red-300 text-red-700' : 'bg-gray-50 border-gray-200 text-gray-500'">
                                    {{ it.approved ? 'Approved' : it.approved === false ? 'Excluded' : 'Undecided' }}
                                </button>
                                <span v-else class="text-xs text-gray-400">{{ it.approved === null ? '—' : it.approved ? 'Approved' : 'Excluded' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-md border p-4">
                <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Audit trail</h3>
                <div v-for="(l, i) in t.logs" :key="i" class="text-xs text-gray-600 border-l-2 border-gray-200 pl-3 py-1">
                    <b class="text-gray-800">{{ l.actor_label }}</b> {{ l.note }}
                    <span class="text-gray-400">· {{ l.created_at }}</span>
                </div>
                <div v-if="!t.logs.length" class="text-xs text-gray-400">No activity yet.</div>
            </div>
        </div>

        <!-- right: actions -->
        <div class="space-y-3">
            <div class="bg-gray-50 rounded-md border p-4 space-y-2">
                <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-1">Actions</h3>

                <button v-if="can('verify refunds') && s === 'submitted'" @click="post(base + '/verify')" :disabled="busy" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-teal-600 text-white">✓ Verify (valid)</button>
                <button v-if="can('update refunds') && ['verified','pending_transfer_info'].includes(s)" @click="post(base + '/submit-approval')" :disabled="busy" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-indigo-600 text-white">→ Submit for approval</button>
                <button v-if="can('approve refunds') && s === 'pending_approval'" @click="post(base + '/approve')" :disabled="busy" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-indigo-600 text-white">✓ Manager approve</button>
                <button v-if="can('payout refunds') && ['approved','pending_transfer_info'].includes(s) && isPaynow" @click="post('/refunds/batch/generate', { ticket_ids: [t.id] })" :disabled="busy" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-violet-600 text-white">📧 Schedule PayNow payout (CSV)</button>
                <a v-if="can('payout refunds') && t.payout_batch_id" :href="'/refunds/batch/' + t.payout_batch_id + '/download'" class="block w-full text-left text-sm font-semibold px-3 py-2 rounded bg-white border border-violet-300 text-violet-700">⬇ Download PayNow CSV</a>
                <button v-if="can('update refunds') && ['scheduled','approved','pending_transfer_info'].includes(s)" @click="post(base + '/complete')" :disabled="busy" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-green-600 text-white">✓ Mark refund done</button>
                <button v-if="can('update refunds') && ['verified','approved','pending_approval'].includes(s)" @click="post(base + '/request-info')" :disabled="busy" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-white border border-amber-300 text-amber-800">✉ Request valid PayNow</button>
                <button v-if="can('verify refunds') && !['rejected','completed'].includes(s)" @click="showReject = !showReject" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-white border border-red-300 text-red-700">✕ Reject — can't refund</button>

                <div v-if="showReject" class="pt-1">
                    <textarea v-model="rejectRemarks" rows="2" class="w-full border rounded px-2 py-1 text-sm" placeholder="Reason for rejection"></textarea>
                    <button @click="doReject" :disabled="busy" class="mt-1 w-full bg-red-600 text-white text-sm rounded px-3 py-1.5">Confirm reject</button>
                </div>
            </div>

            <div v-if="can('update refunds')" class="bg-gray-50 rounded-md border p-4">
                <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Send customer email</h3>
                <select v-model="emailTemplate" class="w-full border rounded px-2 py-1.5 text-sm mb-2">
                    <option v-for="(label, key) in emailTemplates" :key="key" :value="key">{{ label }}</option>
                </select>
                <button @click="post(base + '/email', { template: emailTemplate })" :disabled="busy" class="w-full bg-gray-700 text-white text-sm rounded px-3 py-1.5">Send / log email</button>
                <p class="text-xs text-gray-400 mt-2" v-if="t.last_email_template">Last: {{ t.last_email_template }} <span v-if="t.last_email_sent_at">· {{ t.last_email_sent_at }}</span></p>
            </div>
        </div>
    </div>
</BreezeAuthenticatedLayout>
</template>
