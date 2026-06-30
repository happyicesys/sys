<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    ticket: { type: Object, required: true },
    emailTemplates: { type: Object, default: () => ({}) },
    emailTemplateContents: { type: Object, default: () => ({}) },
    statuses: { type: Object, default: () => ({}) },
});

const page = usePage();
const can = (p) => (page.props.auth?.roles || []).includes('superadmin') || (page.props.auth?.permissions || []).includes(p);

const t = computed(() => props.ticket);
// All badges read from the FROZEN validation snapshot taken at submission
// (system_validation_json / stored ticket fields), so they never change later.
const sv = computed(() => props.ticket.system_validation || {});
const itemCount = computed(() => (typeof sv.value.item_count !== 'undefined' ? sv.value.item_count : (props.ticket.items || []).length));
const hasChannelError = computed(() => (typeof sv.value.had_channel_error !== 'undefined'
    ? !!sv.value.had_channel_error
    : (props.ticket.items || []).some((i) => i.had_channel_error)));
const isManualClaim = computed(() => (typeof sv.value.is_manual !== 'undefined' ? !!sv.value.is_manual : !!props.ticket.is_manual));
const alreadyRefunded = computed(() => !!(sv.value.txn_already_refunded || props.ticket.auto_refund_detected));
const isVideo = (a) => a && a.mime && a.mime.startsWith('video/');
const lightbox = ref(null);
function openLightbox(a) { lightbox.value = a; }
function closeLightbox() { lightbox.value = null; }
const showReject = ref(false);
const rejectRemarks = ref('');
const emailTemplate = ref(Object.keys(props.emailTemplates)[0] || '');
const busy = ref(false);
const showTemplate = ref(false);
const currentTemplate = computed(() => props.emailTemplateContents[emailTemplate.value] || {});

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
function deleteTicket() {
    router.delete(base.value, {
        onBefore: () => confirm('Permanently delete this refund ticket? This cannot be undone.'),
    });
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

    <div v-if="$page.props.errors && Object.keys($page.props.errors).length" class="mx-2 sm:mx-5 mt-3">
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-md px-4 py-3">
            {{ Object.values($page.props.errors)[0] }}
        </div>
    </div>

    <div class="m-2 sm:mx-5 sm:my-3 grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- left: claim + validation + items + logs -->
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-md border p-4">
                <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Customer submission</h3>
                <div class="grid grid-cols-2 gap-y-1.5 text-sm">
                    <div class="text-gray-500">Submitted via</div><div class="font-medium">{{ t.is_manual ? 'Manual entry (no match found)' : 'Scanned QR' }}</div>
                    <div class="text-gray-500">Day chosen</div><div class="font-medium capitalize">{{ t.entered_day || '—' }}</div>
                    <div class="text-gray-500">Amount entered</div><div class="font-medium">{{ t.entered_amount ? '$' + t.entered_amount : '—' }}</div>
                    <div v-if="t.is_manual" class="text-gray-500">Approx. time</div><div v-if="t.is_manual" class="font-medium">{{ t.approx_time || '—' }}</div>
                    <div class="text-gray-500">Reason</div><div class="font-medium">{{ t.reason_code || '—' }}</div>
                    <div class="text-gray-500">Customer note</div><div class="font-medium">{{ t.reason_text || '—' }}</div>
                    <div class="text-gray-500">Refund method</div><div class="font-medium">{{ t.refund_method }}</div>
                    <div class="text-gray-500">Payout to</div><div class="font-medium">{{ t.payout_destination || '—' }}</div>
                    <div class="text-gray-500">Contact email</div><div class="font-medium">{{ t.contact_email || '—' }}</div>
                    <div class="text-gray-500">Contact phone</div><div class="font-medium">{{ t.contact_phone || '—' }}</div>
                    <div class="text-gray-500">Submitted at</div><div class="font-medium">{{ t.created_at }}</div>
                </div>
                <div class="mt-3 pt-3 border-t grid grid-cols-2 gap-y-1.5 text-sm">
                    <div class="text-gray-500">Refund amount (owed)</div><div class="font-semibold">${{ t.amount }}</div>
                    <div class="text-gray-500">Payment channel</div><div class="font-medium">{{ t.payment_channel }}</div>
                </div>
            </div>

            <div class="rounded-md border p-4" :class="recClass(t.recommendation)">
                <h3 class="text-xs uppercase tracking-wide mb-2 opacity-80">System validation — RefundTicket</h3>
                <div class="font-bold capitalize mb-3">Recommend: {{ t.recommendation }}</div>
                <div class="flex flex-wrap gap-2">
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white border border-gray-200 text-gray-700">{{ itemCount }} item(s)</span>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full border"
                        :class="hasChannelError ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-gray-50 border-gray-200 text-gray-600'">
                        {{ hasChannelError ? '⚠ Channel error detected' : '✓ No channel error' }}
                    </span>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full border"
                        :class="isManualClaim ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-gray-50 border-gray-200 text-gray-600'">
                        {{ isManualClaim ? '✍ Manual claim' : '🔗 Auto-matched' }}
                    </span>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full border"
                        :class="alreadyRefunded ? 'bg-cyan-50 border-cyan-200 text-cyan-800' : 'bg-gray-50 border-gray-200 text-gray-600'">
                        {{ alreadyRefunded ? '↩ Already refunded' : '✓ Not yet refunded' }}
                    </span>
                    <span v-if="t.is_auto_refund_channel" class="text-xs font-semibold px-2.5 py-1 rounded-full bg-cyan-50 border border-cyan-200 text-cyan-800">⚡ Nayax auto-refund</span>
                </div>
                <p v-if="t.system_validation && t.system_validation.evaluated_at" class="text-xs text-gray-400 mt-3">Evaluated {{ t.system_validation.evaluated_at }}</p>
            </div>

            <div class="bg-white rounded-md border p-4">
                <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Items flagged</h3>
                <table class="min-w-full text-sm">
                    <thead class="text-xs text-gray-500"><tr><th class="text-left py-1">Product</th><th class="text-left">Price</th><th class="text-left">Channel error</th><th class="text-left">Advice</th><th class="text-left">Decision</th></tr></thead>
                    <tbody>
                        <tr v-for="it in t.items" :key="it.id" class="border-t">
                            <td class="py-2">{{ it.product_name }}<span v-if="it.product_sku" class="block text-xs text-gray-400">{{ it.product_sku }}<span v-if="it.vend_channel_code"> · Ch {{ it.vend_channel_code }}</span></span></td>
                            <td>${{ it.unit_price }}</td>
                            <td>
                                <span v-if="it.had_channel_error" class="text-amber-700">
                                    {{ it.channel_error_desc || ('code ' + it.vend_channel_error_code) }}
                                    <span class="text-gray-400">(code {{ it.vend_channel_error_code }}<span v-if="it.channel_error_weightage">, weight {{ it.channel_error_weightage }}</span>)</span>
                                </span>
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

            <div v-if="t.related_transactions && t.related_transactions.length" class="bg-white rounded-md border p-4">
                <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Related transactions</h3>
                <div v-for="r in t.related_transactions" :key="r.id" class="border rounded-md p-3 mb-2 last:mb-0">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold text-sm">${{ r.amount }} <span class="text-gray-400 font-normal">· {{ r.payment_method || '—' }}</span></div>
                        <a :href="r.link" target="_blank" class="text-teal-600 text-xs font-semibold hover:underline">View in Sales Transactions ↗</a>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 text-xs text-gray-600 mt-2">
                        <div><span class="text-gray-400">Order ID:</span> {{ r.order_id || '—' }}</div>
                        <div><span class="text-gray-400">Machine:</span> {{ r.machine }}</div>
                        <div><span class="text-gray-400">Date:</span> {{ r.datetime || '—' }}</div>
                        <div><span class="text-gray-400">Dispensed:</span> {{ r.dispensed_qty }}/{{ r.qty }}<span v-if="r.is_refunded" class="text-red-600"> · refunded</span></div>
                    </div>
                    <div v-if="r.items && r.items.length" class="mt-2 text-xs text-gray-600">
                        <div v-for="(it, i) in r.items" :key="i">• {{ it.product }} <span class="text-gray-400">(${{ it.price }}<span v-if="it.channel"> · Ch {{ it.channel }}</span>)</span></div>
                    </div>
                </div>
            </div>

            <div v-if="t.attachments && t.attachments.length" class="bg-white rounded-md border p-4">
                <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Customer attachments ({{ t.attachments.length }})</h3>
                <div class="flex flex-wrap gap-3">
                    <button v-for="a in t.attachments" :key="a.id" type="button"
                        @click="openLightbox(a)"
                        class="relative block w-28 h-28 rounded-md overflow-hidden border bg-black/5 group">
                        <video v-if="isVideo(a)" :src="a.url" preload="metadata" muted class="w-full h-full object-cover"></video>
                        <img v-else :src="a.url" :alt="a.original_name" class="w-full h-full object-cover" />
                        <span v-if="isVideo(a)" class="absolute inset-0 flex items-center justify-center text-white text-2xl drop-shadow">▶</span>
                        <span class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition"></span>
                    </button>
                </div>
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
                <button v-if="can('update refunds') && ['approved','pending_transfer_info'].includes(s)" @click="post(base + '/complete')" :disabled="busy" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-green-600 text-white">✓ Mark refund done</button>
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
                <div class="flex gap-2">
                    <button @click="showTemplate = true" class="flex-1 bg-white border border-gray-300 text-gray-700 text-sm rounded px-3 py-1.5">Review template</button>
                    <button @click="post(base + '/email', { template: emailTemplate })" :disabled="busy" class="flex-1 bg-gray-700 text-white text-sm rounded px-3 py-1.5">Send / log email</button>
                </div>
                <p class="text-xs text-gray-400 mt-2" v-if="t.last_email_template">Last: {{ t.last_email_template }} <span v-if="t.last_email_sent_at">· {{ t.last_email_sent_at }}</span></p>
            </div>

            <div v-if="can('update refunds')" class="bg-gray-50 rounded-md border border-red-200 p-4">
                <h3 class="text-xs uppercase tracking-wide text-red-500 mb-2">Danger zone</h3>
                <button @click="deleteTicket" :disabled="busy" class="w-full bg-red-600 text-white text-sm font-semibold rounded px-3 py-2 hover:bg-red-700">🗑 Delete ticket (permanent)</button>
                <p class="text-xs text-gray-400 mt-2">Removes the ticket, its items, attachments and logs. For testing/cleanup.</p>
            </div>
        </div>
    </div>

    <!-- email template preview -->
    <div v-if="showTemplate" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showTemplate = false">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <h3 class="font-semibold text-gray-800 text-sm">{{ emailTemplates[emailTemplate] }}</h3>
                <button @click="showTemplate = false" class="text-gray-400 text-2xl leading-none">&times;</button>
            </div>
            <div class="px-5 py-4">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Subject</div>
                <div class="font-medium text-gray-800 mb-3">{{ currentTemplate.subject }}</div>
                <div class="text-xs text-gray-500 uppercase tracking-wide">Body</div>
                <pre class="whitespace-pre-wrap text-sm text-gray-700 font-sans mt-1">{{ currentTemplate.body }}</pre>
                <p class="text-xs text-gray-400 mt-3">Preview of the email that will be sent to {{ t.contact_email || 'the customer' }}.</p>
            </div>
            <div class="px-5 py-3 border-t flex justify-end gap-2">
                <button @click="showTemplate = false" class="bg-gray-100 border text-gray-700 text-sm rounded px-4 py-1.5">Close</button>
                <button @click="showTemplate = false; post(base + '/email', { template: emailTemplate })" :disabled="busy" class="bg-teal-600 text-white text-sm font-semibold rounded px-4 py-1.5">Send this email</button>
            </div>
        </div>
    </div>

    <!-- attachment lightbox -->
    <div v-if="lightbox" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4" @click.self="closeLightbox">
        <button type="button" @click="closeLightbox" class="absolute top-4 right-5 text-white text-3xl leading-none">&times;</button>
        <video v-if="isVideo(lightbox)" :src="lightbox.url" controls autoplay class="max-h-[90vh] max-w-[92vw] rounded-md bg-black"></video>
        <img v-else :src="lightbox.url" :alt="lightbox.original_name" class="max-h-[90vh] max-w-[92vw] rounded-md object-contain" />
    </div>
</BreezeAuthenticatedLayout>
</template>
