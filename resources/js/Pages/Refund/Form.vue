<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    machineID: { type: String, default: '' },
    machineFound: { type: Boolean, default: false },
    machineName: { type: String, default: null },
    siteName: { type: String, default: null },
    reasonCodes: { type: Array, default: () => [] },
    allowedDays: { type: Array, default: () => ['today', 'yesterday'] },
    maxLookbackDays: { type: Number, default: 14 },
});

// machineID may be missing/invalid (QR without it, or a broken APK) — fall back to a manual entry page.
const machineId = ref(props.machineID || '');
const machineName = ref(props.machineName || null);
const siteName = ref(props.siteName || null);
const machineResolved = ref(props.machineFound);

const step = ref('enter_machine');
const loading = ref(false);
const errorMsg = ref('');

// 'today' | 'yesterday' | 'custom'. When 'custom', the actual date lives in customDate.
const dayMode = ref('today');
const customDate = ref(''); // YYYY-MM-DD
const amount = ref('');
const candidates = ref([]);
const selected = ref(null);
const selectedRawIds = ref([]); // the specific item rows the customer tapped
const reasonCode = ref('not_dispensed');
const reasonText = ref('');
const customerName = ref('');
const refundMethod = ref('paynow');
const payoutDestination = ref('');
const contactEmail = ref('');
const contactPhone = ref('');

// manual review — the day was already chosen at step 2 (today/yesterday/custom),
// so the form only asks for time / amount / product / explanation.
const manualTime = ref('');
const manualText = ref('');
const manualAmount = ref('');
const machineProducts = ref([]);
const productsLoaded = ref(false);
const prodOpen = ref(false);
// manual review can list more than one affected item (product + channel + qty)
const manualItems = ref([]); // [{ product_id, name, channel_code, image_url, price_cents, qty }]
function addManualItem(p) {
    prodOpen.value = false;
    if (!p) return; // "Not sure / not listed" — no structured item, they'll explain in text
    const key = p.product_id + '-' + p.channel_code;
    const existing = manualItems.value.find(i => (i.product_id + '-' + i.channel_code) === key);
    if (existing) { existing.qty = Math.min(10, existing.qty + 1); return; }
    manualItems.value.push({ ...p, qty: 1 });
}
function removeManualItem(idx) { manualItems.value.splice(idx, 1); }
function bumpManualQty(item, delta) { item.qty = Math.min(10, Math.max(1, item.qty + delta)); }
const manualPayMethod = ref('');
// True while the customer is on the manual-review path, so the shared payout
// step (7) knows to submit as a manual claim instead of going to the auto review.
const manualMode = ref(false);
const manualPayMethods = [
    { value: 'PayNow / QR code', label: 'PayNow / QR code' },
    { value: 'Credit / debit card', label: 'Credit / debit card (tap)' },
    { value: 'Cash / coins', label: 'Cash / coins' },
    { value: 'Not sure', label: 'Not sure' },
];

async function loadMachineProducts() {
    if (productsLoaded.value || !machineId.value) return;
    try {
        const { data } = await window.axios.post('/refund/machine-products', { machineID: machineId.value });
        machineProducts.value = data.products || [];
        productsLoaded.value = true;
    } catch (e) { /* dropdown is optional — fail silently */ }
}

// attachments: up to 3 photos OR short videos, camera or gallery
const MAX_PHOTOS = 3;
const MAX_MB = 30;
const photos = ref([]);          // File[]
const photoPreviews = ref([]);   // { url, isVideo }[]

function onPhotos(e) {
    const files = Array.from(e.target.files || []);
    for (const f of files) {
        if (photos.value.length >= MAX_PHOTOS) { errorMsg.value = `You can add up to ${MAX_PHOTOS} files.`; break; }
        const isImg = f.type && f.type.startsWith('image/');
        const isVid = f.type && f.type.startsWith('video/');
        if (!isImg && !isVid) { errorMsg.value = 'Only photos or videos are allowed.'; continue; }
        if (f.size > MAX_MB * 1024 * 1024) { errorMsg.value = `Each file must be under ${MAX_MB} MB.`; continue; }
        photos.value.push(f);
        photoPreviews.value.push({ url: URL.createObjectURL(f), isVideo: isVid });
    }
    e.target.value = ''; // allow re-selecting the same file
}
function removePhoto(i) {
    try { URL.revokeObjectURL(photoPreviews.value[i].url); } catch (e) { /* noop */ }
    photos.value.splice(i, 1);
    photoPreviews.value.splice(i, 1);
}
function appendPhotos(fd) {
    photos.value.forEach(f => fd.append('photos[]', f));
}

const result = ref(null);

// date labels for the Today / Yesterday cards
const fmtDate = (d) => d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
const todayLabel = fmtDate(new Date());
const yesterdayLabel = (() => { const d = new Date(); d.setDate(d.getDate() - 1); return fmtDate(d); })();

// custom "Another date" picker bounds (last N days, no future dates)
const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const maxDateStr = ymd(new Date());
const minDateStr = (() => { const d = new Date(); d.setDate(d.getDate() - (props.maxLookbackDays || 0)); return ymd(d); })();
const showCustomDate = computed(() => (props.maxLookbackDays || 0) > 1);
const fmtCustomLabel = computed(() => (customDate.value ? fmtDate(new Date(customDate.value + 'T00:00:00')) : ''));

// the value sent to the backend: 'today' / 'yesterday' / a YYYY-MM-DD date
const dayValue = computed(() => (dayMode.value === 'custom' ? customDate.value : dayMode.value));
// human label used in copy ("...purchases <label>")
const dayLabel = computed(() => {
    if (dayMode.value === 'today') return 'today';
    if (dayMode.value === 'yesterday') return 'yesterday';
    if (customDate.value) return 'on ' + fmtDate(new Date(customDate.value + 'T00:00:00'));
    return '';
});

// auto-focus the amount field when the amount step opens
const amtInputRef = ref(null);
watch(step, (s) => {
    if (s === 3) nextTick(() => amtInputRef.value && amtInputRef.value.focus());
    if (s === '4c') {
        if (!manualAmount.value && amount.value) manualAmount.value = amount.value;
        loadMachineProducts();
    }
});

const isAuto = computed(() => selected.value && selected.value.is_auto_refund_channel);
const multiItem = computed(() => selected.value && (selected.value.items || []).length > 1);

// Group identical items (same product + channel + price) so duplicates the customer
// can't tell apart collapse into one row. The system targets the errored unit(s) first.
const itemGroups = computed(() => {
    if (!selected.value) return [];
    const map = {};
    for (const it of (selected.value.items || [])) {
        const key = [it.product_id, it.vend_channel_code, it.unit_price_cents, it.product_name].join('|');
        (map[key] = map[key] || { key, sample: it, items: [] }).items.push(it);
    }
    return Object.values(map).map((g) => {
        const avail = g.items.filter((i) => i.vend_transaction_item_id && !i.is_refunded);
        const errored = avail.filter((i) => i.had_channel_error).map((i) => i.vend_transaction_item_id);
        const normal = avail.filter((i) => !i.had_channel_error).map((i) => i.vend_transaction_item_id);
        return {
            key: g.key,
            name: g.sample.product_name,
            sku: g.sample.product_sku,
            channel: g.sample.vend_channel_code,
            price: (g.sample.unit_price_cents || 0) / 100,
            total: g.items.length,
            availableCount: avail.length,
            erroredCount: errored.length,
            refundedCount: g.items.length - avail.length,
            memberIds: avail.map((i) => i.vend_transaction_item_id),
            orderedIds: [...errored, ...normal], // errored first = "smart pinpoint"
        };
    });
});

const titles = {
    2: 'When?', 3: 'Amount', 4: 'Your Purchase', '4b': 'Not Found',
    '4c': 'Manual Review', 5: 'Problem Item(s)', 6: 'What Happened?', 7: 'Refund Payout',
    8: 'Review', 9: 'Done', enter_machine: 'Machine ID',
};
const title = computed(() => titles[step.value] || 'Refund');

// progress (steps 2..8 -> 7 dots; the current step lights up)
const order = [2, 3, 4, 5, 6, 7, 8];
const progressOn = computed(() => {
    // 4b / 4c are string variants of step 4
    const s = typeof step.value === 'number' ? step.value : 4;
    const i = order.indexOf(s);
    return i === -1 ? 0 : i + 1;
});

function emailValid(v) {
    return !!v && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v).trim());
}

// Singapore mobile: 8 digits starting 8 or 9 (optionally +65 / 65 prefixed)
function sgMobileValid(v) {
    let s = String(v || '').replace(/[\s-]/g, '').replace(/^\+?65/, '');
    return /^[89]\d{7}$/.test(s);
}

// switch refund payout method; reset the destination so a leftover value
// (e.g. an SG mobile) can't carry over into the PayPal email field or vice versa.
// PayPal defaults to the contact email they already gave — they can still edit it.
function setMethod(m) {
    if (refundMethod.value === m) return;
    refundMethod.value = m;
    payoutDestination.value = (m === 'paypal' && emailValid(contactEmail.value)) ? contactEmail.value.trim() : '';
    errorMsg.value = '';
}

function firstError(e, fallback) {
    const r = e && e.response && e.response.data;
    if (r && r.errors) { const k = Object.keys(r.errors)[0]; if (k) return r.errors[k][0]; }
    return (r && r.message) || fallback;
}

// ATM-style cents entry: user types digits only, decimal auto-places.
// e.g. "9" -> 0.09, "90" -> 0.90, "900" -> 9.00
function onAmountInput(e) {
    const digits = String(e.target.value).replace(/\D/g, '');
    const cents = digits ? Math.min(parseInt(digits, 10), 100000 * 100) : 0;
    amount.value = cents ? (cents / 100).toFixed(2) : '';
    e.target.value = amount.value;
}

function onManualAmountInput(e) {
    const digits = String(e.target.value).replace(/\D/g, '');
    const cents = digits ? Math.min(parseInt(digits, 10), 100000 * 100) : 0;
    manualAmount.value = cents ? (cents / 100).toFixed(2) : '';
    e.target.value = manualAmount.value;
}

// Machine ID is a 4-digit code — strip anything non-numeric and cap at 4 digits.
function onMachineIdInput(e) {
    const digits = String(e.target.value).replace(/\D/g, '').slice(0, 4);
    machineId.value = digits;
    e.target.value = digits;
    if (!props.machineFound) machineResolved.value = false;
}

// Combined first step: capture Machine ID (if not already resolved from the QR
// link) + the customer's name, then resolve and jump straight to the hero.
async function startRefund() {
    errorMsg.value = '';
    if (!customerName.value || !customerName.value.trim()) { errorMsg.value = 'Please enter your name.'; return; }

    // Machine already confirmed via the QR link — no need to re-enter/re-check the ID.
    if (machineResolved.value && machineId.value) { step.value = 2; return; }

    const code = (machineId.value || '').trim();
    if (!/^\d{4}$/.test(code)) { errorMsg.value = 'Please enter the 4-digit Machine ID.'; return; }
    loading.value = true;
    try {
        const { data } = await window.axios.post('/refund/resolve', { machineID: code });
        if (data.found) {
            machineId.value = data.machineID;
            machineName.value = data.machineName;
            siteName.value = data.siteName;
            machineResolved.value = true;
            step.value = 2;
        } else {
            errorMsg.value = "We couldn't find that Machine ID. Please check the number on the machine and try again.";
        }
    } catch (e) {
        errorMsg.value = 'Something went wrong. Please try again.';
    } finally {
        loading.value = false;
    }
}

async function fetchCandidates() {
    errorMsg.value = '';
    if (!amount.value || parseFloat(amount.value) <= 0) { errorMsg.value = 'Please enter the amount you paid.'; return; }
    loading.value = true;
    try {
        const { data } = await window.axios.post('/refund/candidates', {
            machineID: machineId.value, day: dayValue.value, amount: amount.value,
        });
        candidates.value = data.candidates || [];
        step.value = candidates.value.length ? 4 : '4b';
    } catch (e) {
        errorMsg.value = 'Something went wrong. Please try again.';
    } finally {
        loading.value = false;
    }
}

function chooseCandidate(c) {
    selected.value = c;
    selectedRawIds.value = []; // nothing pre-checked
}

function toggleRaw(id) {
    if (!id) return;
    const i = selectedRawIds.value.indexOf(id);
    if (i === -1) selectedRawIds.value.push(id); else selectedRawIds.value.splice(i, 1);
}

// Resolved ids submitted to the server. The customer taps any identical row(s);
// within each identical group we remap the count to the errored unit(s) first, so
// the failed item is what actually gets bound — without showing them which one.
const selectedItemIds = computed(() => {
    const out = [];
    for (const g of itemGroups.value) {
        const k = g.memberIds.filter((id) => selectedRawIds.value.includes(id)).length;
        if (k > 0) out.push(...g.orderedIds.slice(0, k));
    }
    return out;
});

const selectedAmount = computed(() => {
    if (!selected.value) return '0.00';
    const items = (selected.value.items || []);
    if (items.length <= 1) return Number(selected.value.amount).toFixed(2);
    const cents = items
        .filter(i => selectedItemIds.value.includes(i.vend_transaction_item_id))
        .reduce((s, i) => s + (i.unit_price_cents || 0), 0);
    return (cents / 100).toFixed(2);
});

function next() {
    errorMsg.value = '';
    const s = step.value;
    if (s === 2) {
        if (dayMode.value === 'custom' && !customDate.value) { errorMsg.value = 'Please pick the date you bought.'; return; }
        step.value = 3; return;
    }
    if (s === 3) { manualMode.value = false; fetchCandidates(); return; }
    if (s === 4) {
        if (!selected.value) { errorMsg.value = 'Please pick your purchase.'; return; }
        step.value = multiItem.value ? 5 : 6;
        return;
    }
    if (s === 5) {
        if (selectedItemIds.value.length === 0) { errorMsg.value = 'Select at least one item.'; return; }
        step.value = 6; return;
    }
    if (s === 6) {
        if (photos.value.length === 0) { errorMsg.value = 'Please add at least one photo or a short video.'; return; }
        if (!emailValid(contactEmail.value)) { errorMsg.value = 'Please enter a valid email so we can update you on your refund.'; return; }
        step.value = isAuto.value ? 8 : 7; return;
    }
    if (s === 7) {
        if (refundMethod.value === 'paypal') {
            if (!payoutDestination.value || !payoutDestination.value.trim()) {
                errorMsg.value = 'Please enter your PayPal email address.';
                return;
            }
            if (!emailValid(payoutDestination.value)) {
                errorMsg.value = 'Please enter a valid PayPal email address.';
                return;
            }
        } else {
            if (!payoutDestination.value || !payoutDestination.value.trim()) {
                errorMsg.value = 'Please enter your PayNow mobile number.';
                return;
            }
            if (!sgMobileValid(payoutDestination.value)) {
                errorMsg.value = 'Please enter a valid Singapore mobile number (8 digits starting with 8 or 9).';
                return;
            }
        }
        // Manual claims submit straight from the payout step; matched claims go
        // to the review screen first.
        if (manualMode.value) { submitManual(); return; }
        step.value = 8; return;
    }
    if (s === 8) { submit(); return; }
    if (s === '4c') {
        if (photos.value.length === 0) { errorMsg.value = 'Please add at least one photo or a short video.'; return; }
        if (!emailValid(contactEmail.value)) { errorMsg.value = 'Please enter a valid email so we can update you.'; return; }
        step.value = 7; return;
    }
    if (s === 9) { window.location.reload(); return; }
}

function back() {
    const s = step.value;
    if (s === 2) step.value = 'enter_machine';
    else if (s === 3) step.value = 2;
    else if (s === 4 || s === '4b') step.value = 3;
    else if (s === '4c') step.value = '4b';
    else if (s === 5) step.value = 4;
    else if (s === 6) step.value = multiItem.value ? 5 : 4;
    else if (s === 7) step.value = manualMode.value ? '4c' : 6;
    else if (s === 8) step.value = isAuto.value ? 6 : 7;
}

async function submit() {
    loading.value = true; errorMsg.value = '';
    try {
        const fd = new FormData();
        fd.append('machineID', machineId.value);
        if (selected.value.vend_transaction_id) fd.append('vend_transaction_id', selected.value.vend_transaction_id);
        if (selected.value.payment_gateway_log_id) fd.append('payment_gateway_log_id', selected.value.payment_gateway_log_id);
        selectedItemIds.value.forEach(id => fd.append('selected_item_ids[]', id));
        fd.append('reason_code', reasonCode.value);
        if (reasonText.value) fd.append('reason_text', reasonText.value);
        if (!isAuto.value) {
            fd.append('refund_method', refundMethod.value);
            if (payoutDestination.value) fd.append('payout_destination', payoutDestination.value);
        }
        if (customerName.value) fd.append('contact_name', customerName.value);
        if (contactEmail.value) fd.append('contact_email', contactEmail.value);
        if (contactPhone.value) fd.append('contact_phone', contactPhone.value);
        fd.append('entered_day', dayValue.value);
        if (amount.value) fd.append('entered_amount', amount.value);
        appendPhotos(fd);

        const { data } = await window.axios.post('/refund', fd);
        result.value = data;
        step.value = 9;
    } catch (e) {
        errorMsg.value = firstError(e, 'Submission failed. Please try again.');
    } finally {
        loading.value = false;
    }
}

async function submitManual() {
    if (!emailValid(contactEmail.value)) { errorMsg.value = 'Please enter a valid email so we can update you.'; return; }
    loading.value = true; errorMsg.value = '';
    try {
        const fd = new FormData();
        fd.append('machineID', machineId.value);
        fd.append('is_manual', '1');
        fd.append('entered_day', dayValue.value);
        const amt = manualAmount.value || amount.value;
        if (amt) fd.append('entered_amount', amt);
        if (manualTime.value) fd.append('approx_time', manualTime.value);
        // Note now carries ONLY the customer's own remark — items and payment
        // method are captured in their own fields (manual_items_summary / manual_pay_method).
        if (manualText.value) fd.append('reason_text', manualText.value);
        // Structured, human-readable summary of the affected items for the SKU-name field.
        const itemsSummary = manualItems.value
            .map(i => `${i.name}${i.channel_code ? ' (Channel #' + i.channel_code + ')' : ''} × ${i.qty}`)
            .join('; ');
        if (itemsSummary) fd.append('manual_items_summary', itemsSummary);
        if (manualPayMethod.value) fd.append('manual_pay_method', manualPayMethod.value);
        // How to refund them (PayNow / PayPal) — same as the auto-matched flow.
        fd.append('refund_method', refundMethod.value);
        if (payoutDestination.value) fd.append('payout_destination', payoutDestination.value);
        if (customerName.value) fd.append('contact_name', customerName.value);
        if (contactEmail.value) fd.append('contact_email', contactEmail.value);
        appendPhotos(fd);

        const { data } = await window.axios.post('/refund', fd);
        result.value = data;
        step.value = 9;
    } catch (e) {
        errorMsg.value = 'Submission failed. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
<Head title="Refund Request" />
<div class="screen">
    <div class="appbar">
        <div class="ttl">{{ title }}</div>
        <div class="sitecard" v-if="machineResolved">
            <span class="pin">📍</span>
            <div class="sitewrap">
                <div class="sitename">{{ siteName || machineName || ('Machine ' + machineId) }}</div>
                <div class="sitemeta">
                    <span v-if="siteName && machineName">{{ machineName }} · </span>Machine ID {{ machineId }}
                </div>
            </div>
        </div>
        <div class="progress" v-if="machineResolved && step !== 9 && step !== 'enter_machine'">
            <i v-for="n in 7" :key="n" :class="{ on: n <= progressOn }"></i>
        </div>
    </div>

    <div class="body">
        <p v-if="errorMsg" class="err">{{ errorMsg }}</p>

        <!-- enter machine id + name (no/invalid machineID in the QR) -->
        <div v-if="step === 'enter_machine'">
            <div class="emptywrap" style="padding-top:14px">
                <div class="e">
                    <svg width="60" height="76" viewBox="0 0 60 76" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;margin:0 auto 16px">
                        <rect x="6" y="3" width="48" height="70" rx="8" fill="#0f766e"/>
                        <rect x="11" y="8" width="26" height="46" rx="4" fill="#ecfdfb"/>
                        <line x1="11" y1="20" x2="37" y2="20" stroke="#99f6e4" stroke-width="2"/>
                        <line x1="11" y1="31" x2="37" y2="31" stroke="#99f6e4" stroke-width="2"/>
                        <line x1="11" y1="42" x2="37" y2="42" stroke="#99f6e4" stroke-width="2"/>
                        <circle cx="17" cy="14.5" r="3" fill="#f97316"/>
                        <circle cx="25" cy="14.5" r="3" fill="#38bdf8"/>
                        <circle cx="33" cy="14.5" r="3" fill="#f43f5e"/>
                        <rect x="14" y="24" width="6" height="6" rx="1.5" fill="#38bdf8"/>
                        <rect x="23" y="24" width="6" height="6" rx="1.5" fill="#f43f5e"/>
                        <rect x="30" y="24" width="6" height="6" rx="1.5" fill="#f97316"/>
                        <rect x="14" y="35" width="6" height="6" rx="1.5" fill="#f43f5e"/>
                        <rect x="23" y="35" width="6" height="6" rx="1.5" fill="#f97316"/>
                        <rect x="30" y="35" width="6" height="6" rx="1.5" fill="#38bdf8"/>
                        <rect x="42" y="9" width="8" height="10" rx="2" fill="#5eead4"/>
                        <circle cx="46" cy="25" r="2" fill="#5eead4"/>
                        <circle cx="46" cy="32" r="2" fill="#5eead4"/>
                        <rect x="11" y="58" width="38" height="9" rx="2" fill="#0b5c56"/>
                    </svg>
                </div>
                <div class="h2">Which machine?</div>
                <p class="p" style="text-align:center">Enter the <b>Machine ID</b> printed on the vending machine (usually near the QR sticker or on the front panel), and your name to start.</p>
            </div>
            <template v-if="!machineResolved">
                <label class="fld">Machine ID <span class="req">(required)</span></label>
                <input class="inp" v-model="machineId" inputmode="numeric" maxlength="4" placeholder="e.g. 1234" @input="onMachineIdInput" @keyup.enter="startRefund" />
            </template>
            <label class="fld">Your name <span class="req">(required)</span></label>
            <input class="inp" v-model="customerName" placeholder="Enter your name" @keyup.enter="startRefund" autocapitalize="words" />
            <button class="btn" style="margin-top:14px" @click="startRefund" :disabled="loading">{{ loading ? 'Checking…' : 'Continue' }}</button>
        </div>

        <!-- 2 day -->
        <div v-else-if="step === 2">
            <div class="h2">When did you buy?</div>
            <p class="p">This helps us find your purchase quickly.</p>
            <div class="bigcard" :class="{ sel: dayMode === 'today' }" @click="dayMode = 'today'"><span class="e">☀️</span><div><b>Today</b><small>{{ todayLabel }}</small></div></div>
            <div class="bigcard" :class="{ sel: dayMode === 'yesterday' }" @click="dayMode = 'yesterday'"><span class="e">🌙</span><div><b>Yesterday</b><small>{{ yesterdayLabel }}</small></div></div>
            <div v-if="showCustomDate" class="bigcard" :class="{ sel: dayMode === 'custom' }" @click="dayMode = 'custom'"><span class="e">📅</span><div><b>Another date</b><small>{{ dayMode === 'custom' && customDate ? fmtCustomLabel : 'Pick the day you bought' }}</small></div></div>
            <div v-if="showCustomDate && dayMode === 'custom'">
                <input class="inp" type="date" v-model="customDate" :min="minDateStr" :max="maxDateStr" />
                <p class="p" style="margin-top:6px">You can request a refund for purchases in the last {{ maxLookbackDays }} days.</p>
            </div>
        </div>

        <!-- 3 amount -->
        <div v-else-if="step === 3">
            <div class="h2">How much did you pay?</div>
            <p class="p">Enter the amount shown on the machine or your bank app.</p>
            <div class="amtbox"><span class="cur">$</span><input ref="amtInputRef" class="amtinput" type="text" inputmode="numeric" :value="amount" @input="onAmountInput" placeholder="0.00" /></div>
        </div>

        <!-- 4 candidates -->
        <div v-else-if="step === 4">
            <div class="h2">Is this your purchase?</div>
            <p class="p">We found these <b>${{ amount }}</b> purchases <b>{{ dayLabel }}</b>. Tap yours.</p>
            <div v-for="(c, idx) in candidates" :key="idx" class="txn" :class="{ sel: selected === c }" @click="chooseCandidate(c)">
                <div class="top"><span class="pill">{{ c.payment_method || c.payment_channel }}</span><span class="amt">${{ Number(c.amount).toFixed(2) }}</span></div>
                <div class="prodlist" v-if="c.items && c.items.length">
                    <div v-for="(it, ix) in c.items" :key="ix" class="prodline">
                        <span class="prodthumb">
                            <img v-if="it.product_image_url" :src="it.product_image_url" alt="" loading="lazy" />
                            <span v-else>🥡</span>
                        </span>
                        <span class="pname">{{ it.product_name }}<span v-if="it.vend_channel_code" class="chan">Channel #{{ it.vend_channel_code }}</span></span>
                    </div>
                </div>
                <div class="meta">
                    <span>🕘 {{ c.datetime_label || c.datetime }}</span>
                    <span v-if="c.is_auto_refund_channel" class="pill warn">Auto-refund (Nayax)</span>
                    <span v-if="c.already_refunded" class="pill bad">Already refunded</span>
                </div>
            </div>
            <a class="nolink" href="#" @click.prevent="step = '4b'">None of these / can't find it →</a>
        </div>

        <!-- 4b not found -->
        <div v-else-if="step === '4b'">
            <div class="emptywrap">
                <div class="e">🔍</div>
                <div class="h2">We couldn't find that purchase</div>
                <p class="p" style="text-align:center">No <b>${{ amount }}</b> purchase {{ dayLabel }} at this machine. Try a different amount or day — or send it for manual checking.</p>
            </div>
            <button class="btn ghost" @click="step = 3">↩ Change amount / day</button>
            <button class="btn" style="margin-top:10px" @click="manualMode = true; step = '4c'">Submit for manual review →</button>
        </div>

        <!-- 4c manual -->
        <div v-else-if="step === '4c'">
            <div class="h2">Tell us what happened</div>
            <p class="p">Our team will check this against the machine records and get back to you.</p>

            <label class="fld">Around what time? <span class="dayhint">({{ dayLabel }})</span></label>
            <input class="inp" type="time" v-model="manualTime" />

            <label class="fld">Total amount paid</label>
            <div class="amtrow"><span class="cur2">$</span><input class="inp amt2" type="text" inputmode="numeric" :value="manualAmount" @input="onManualAmountInput" placeholder="0.00" /></div>

            <label class="fld">How did you pay?</label>
            <select class="inp" v-model="manualPayMethod">
                <option value="" disabled>Select payment method…</option>
                <option v-for="m in manualPayMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>

            <label class="fld">Affected Items? <span class="dayhint">(add all affected items)</span></label>

            <!-- items already added -->
            <div v-for="(it, idx) in manualItems" :key="it.product_id + '-' + it.channel_code" class="itemrow">
                <span class="ddthumb"><img v-if="it.image_url" :src="it.image_url" alt="" /><span v-else>🥡</span></span>
                <span class="ddinfo">
                    <span class="ddname">{{ it.name }}</span>
                    <span class="ddmeta">
                        <span class="ddchan" v-if="it.channel_code">Channel #{{ it.channel_code }}</span>
                        <span class="ddprice-inline" v-if="it.price_cents">${{ (it.price_cents / 100).toFixed(2) }}</span>
                    </span>
                </span>
                <div class="stepper sm">
                    <button type="button" @click="bumpManualQty(it, -1)">−</button>
                    <b>{{ it.qty }}</b>
                    <button type="button" @click="bumpManualQty(it, 1)">+</button>
                </div>
                <button type="button" class="itemrm" @click="removeManualItem(idx)">×</button>
            </div>

            <div class="proddd">
                <button type="button" class="inp ddbtn" @click="prodOpen = !prodOpen">
                    <span class="ddname muted">{{ manualItems.length ? 'Add another item…' : 'Select a product…' }}</span>
                    <span class="caret">▾</span>
                </button>
                <div v-if="prodOpen" class="ddlist">
                    <div class="ddopt" @click="addManualItem(null)">
                        <span class="ddthumb">❔</span><span class="ddname muted">Not sure / not listed</span>
                    </div>
                    <div v-for="p in machineProducts" :key="p.product_id + '-' + p.channel_code" class="ddopt" @click="addManualItem(p)">
                        <span class="ddthumb"><img v-if="p.image_url" :src="p.image_url" alt="" loading="lazy" /><span v-else>🥡</span></span>
                        <span class="ddinfo">
                            <span class="ddname">{{ p.name }}</span>
                            <span class="ddchan" v-if="p.channel_code">Channel #{{ p.channel_code }}</span>
                        </span>
                        <span class="ddprice" v-if="p.price_cents">${{ (p.price_cents / 100).toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <label class="fld">Explain what happened</label>
            <textarea class="inp bigta" rows="6" v-model="manualText" placeholder="e.g. I paid $3.50 by PayNow around 2pm, the machine showed 'dispensing' but nothing came out."></textarea>
            <label class="fld">Email <span class="req">(required)</span></label>
            <input class="inp" type="email" v-model="contactEmail" placeholder="you@email.com" />
            <label class="fld">Photos or video <span class="req">(required)</span> <span class="dayhint">— up to {{ MAX_PHOTOS }}, max {{ MAX_MB }} MB each</span></label>
            <div class="photogrid">
                <div v-for="(p, i) in photoPreviews" :key="i" class="thumb">
                    <video v-if="p.isVideo" :src="p.url" class="thumbmedia" muted playsinline></video>
                    <img v-else :src="p.url" class="thumbmedia" alt="attachment" />
                    <span v-if="p.isVideo" class="vbadge">▶</span>
                    <button type="button" class="rm" @click="removePhoto(i)">×</button>
                </div>
                <label v-if="photos.length < MAX_PHOTOS" class="addphoto">
                    <input type="file" accept="image/*,video/*" multiple class="hidden-file" @change="onPhotos" />
                    <span>📷</span><small>Add</small>
                </label>
            </div>
        </div>

        <!-- 5 items (identical items collapse into one clean row; failed unit bound server-side) -->
        <div v-else-if="step === 5">
            <div class="h2">Which item had a problem?</div>
            <p class="p">Tap the item that didn't come out.</p>
            <div v-for="(it, idx) in selected.items" :key="idx" class="chk"
                 :class="{ sel: selectedRawIds.includes(it.vend_transaction_item_id), disabled: it.is_refunded || !it.vend_transaction_item_id }"
                 @click="(!it.is_refunded && it.vend_transaction_item_id) && toggleRaw(it.vend_transaction_item_id)">
                <div class="cb"></div>
                <div class="pthumb">
                    <img v-if="it.product_image_url" :src="it.product_image_url" alt="" loading="lazy" />
                    <span v-else>🥡</span>
                </div>
                <div class="ci">
                    <b>{{ it.product_name }}</b>
                    <small v-if="it.vend_channel_code">Channel #{{ it.vend_channel_code }}</small>
                </div>
                <span v-if="it.is_refunded" class="pill bad">Refunded</span>
                <span v-else class="iprice">${{ ((it.unit_price_cents || 0) / 100).toFixed(2) }}</span>
            </div>
        </div>

        <!-- 6 reason -->
        <div v-else-if="step === 6">
            <div class="h2">What happened?</div>
            <p class="p">Helps us verify faster.</p>
            <label class="fld">Reason</label>
            <select class="inp" v-model="reasonCode">
                <option v-for="r in reasonCodes" :key="r.code" :value="r.code">{{ r.label }}</option>
            </select>
            <label class="fld">Add a note (optional)</label>
            <textarea class="inp" rows="3" v-model="reasonText" placeholder="Anything else we should know?"></textarea>
            <label class="fld">Photo or video <span class="req">(required)</span></label>
            <p class="p" style="margin-top:0">A photo or short video of the machine or screen helps us verify. Up to {{ MAX_PHOTOS }}, max {{ MAX_MB }} MB each.</p>
            <div class="photogrid">
                <div v-for="(p, i) in photoPreviews" :key="i" class="thumb">
                    <video v-if="p.isVideo" :src="p.url" class="thumbmedia" muted playsinline></video>
                    <img v-else :src="p.url" class="thumbmedia" alt="attachment" />
                    <span v-if="p.isVideo" class="vbadge">▶</span>
                    <button type="button" class="rm" @click="removePhoto(i)">×</button>
                </div>
                <label v-if="photos.length < MAX_PHOTOS" class="addphoto">
                    <input type="file" accept="image/*,video/*" multiple class="hidden-file" @change="onPhotos" />
                    <span>📷</span><small>Add</small>
                </label>
            </div>
            <label class="fld">Email <span class="req">(required)</span></label>
            <input class="inp" type="email" v-model="contactEmail" placeholder="you@email.com" />
            <p class="p" style="margin-top:6px">We'll email you updates on your refund.</p>
        </div>

        <!-- 7 payout (PayNow mobile or PayPal) -->
        <div v-else-if="step === 7">
            <div class="h2">How should we refund you?</div>
            <p class="p">Choose your preferred refund method.</p>
            <div class="methodsel">
                <button type="button" class="mopt" :class="{ sel: refundMethod === 'paynow' }" @click="setMethod('paynow')">
                    <span class="micon">🇸🇬</span>
                    <span class="mtxt"><b>PayNow</b><small>Singapore mobile number</small></span>
                </button>
                <button type="button" class="mopt" :class="{ sel: refundMethod === 'paypal' }" @click="setMethod('paypal')">
                    <span class="micon">🌐</span>
                    <span class="mtxt"><b>PayPal</b><small>For overseas / foreign cards</small></span>
                </button>
            </div>

            <template v-if="refundMethod === 'paynow'">
                <label class="fld">PayNow mobile number</label>
                <input class="inp" type="tel" inputmode="tel" v-model="payoutDestination" placeholder="e.g. 9123 4567" />
                <p class="p" style="margin-top:6px">Make sure this number is registered for PayNow.</p>
            </template>
            <template v-else>
                <label class="fld">PayPal email address</label>
                <input class="inp" type="email" inputmode="email" v-model="payoutDestination" placeholder="you@email.com" />
                <p class="p" style="margin-top:6px">We'll send your refund to this PayPal account.</p>
            </template>
        </div>

        <!-- 8 review -->
        <div v-else-if="step === 8">
            <div class="h2">Review your request</div>
            <p class="p">Please confirm the details below.</p>
            <div class="banner info" v-if="isAuto">✅ This was paid via <b>Nayax</b> — your refund is processed <b>automatically</b> to your card. No PayNow needed.</div>
            <div class="review">
                <div class="row" v-if="siteName"><span>Site</span><b>{{ siteName }}</b></div>
                <div class="row"><span>Machine</span><b>{{ machineId }}</b></div>
                <div class="row"><span>Purchase</span><b>${{ Number(selected.amount).toFixed(2) }} · {{ selected.datetime_label || dayLabel }}</b></div>
                <div class="row"><span>Refund</span><b>${{ selectedAmount }}</b></div>
                <div class="row"><span>Reason</span><b>{{ (reasonCodes.find(r => r.code === reasonCode) || {}).label }}</b></div>
                <div class="row" v-if="!isAuto"><span>Refund to</span><b>{{ refundMethod === 'paypal' ? 'PayPal' : 'PayNow' }} · {{ payoutDestination }}</b></div>
            </div>
        </div>

        <!-- 9 confirmation -->
        <div v-else-if="step === 9" class="conf">
            <div class="check">✓</div>
            <div class="h2" style="font-size:18px">{{ result && result.auto_resolved ? 'Already being refunded!' : 'Request submitted!' }}</div>
            <p class="p" style="text-align:center" v-if="result && result.auto_resolved">Our records show this was refunded automatically. No further action needed.</p>
            <p class="p" style="text-align:center" v-else>Save your reference number — quote it in any follow-up.</p>
            <div class="ref">{{ result ? result.reference : '' }}</div>
            <p class="p" style="text-align:center">We'll notify you by email about your refund.</p>
        </div>
    </div>

    <div class="footer" v-if="step !== 'enter_machine' && step !== '4b'">
        <button class="btn" @click="next" :disabled="loading">
            {{ loading ? 'Please wait…' : (step === 8 || (step === 7 && manualMode)) ? 'Submit request' : step === 9 ? 'Done' : 'Continue' }}
        </button>
        <button class="btn ghost" v-if="step !== 9 && step !== '4c'" @click="back">Back</button>
        <button class="btn ghost" v-if="step === '4c'" @click="step = '4b'">Back</button>
    </div>

    <div class="brandfoot"><img src="/img/logo.png" alt="HappyIce" /></div>
</div>
</template>

<style scoped>
:root { --brand:#0ea5a4; }
.screen{max-width:430px;margin:0 auto;min-height:100vh;background:linear-gradient(180deg,#ecfdfb 0,#f1f5f9 140px);display:flex;flex-direction:column;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a}
.appbar{background:linear-gradient(135deg,#10b4b2,#0f766e);color:#fff;padding:26px 18px 26px;text-align:center;border-radius:0 0 26px 26px;box-shadow:0 10px 24px -12px rgba(15,118,110,.6)}
.ttl{font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;opacity:.85}
.sitecard{display:inline-flex;align-items:center;gap:9px;margin-top:12px;background:rgba(255,255,255,.16);backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,.25);padding:9px 16px;border-radius:14px;text-align:left;max-width:100%}
.sitecard .pin{font-size:17px}
.sitecard .sitename{font-size:15.5px;font-weight:800;line-height:1.2}
.sitecard .sitemeta{font-size:11px;opacity:.9;margin-top:2px}
.progress{display:flex;gap:4px;margin-top:16px}
.progress i{height:4px;flex:1;background:rgba(255,255,255,.28);border-radius:3px;transition:.3s}
.progress i.on{background:#fff}
.body{flex:1;overflow-y:auto;padding:20px 16px}
.err{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;font-size:12.5px;padding:10px 12px;border-radius:10px;margin-bottom:12px}
.h2{font-size:17px;font-weight:800;margin-bottom:4px}
.p{font-size:12.5px;color:#64748b;line-height:1.5;margin-bottom:14px}
.hero{text-align:center;padding-top:24px}
.icbadge{width:104px;height:104px;border-radius:50%;margin:0 auto 6px;display:flex;align-items:center;justify-content:center;background:radial-gradient(circle at 50% 35%,#ffffff,#e0f2f1);box-shadow:0 12px 30px -10px rgba(15,118,110,.4),inset 0 0 0 1px #d1fae5}
.icbadge .ic{font-size:40px;line-height:1;letter-spacing:-2px}
.hero h3{font-size:23px;font-weight:800;margin:14px 14px 10px;line-height:1.25;letter-spacing:-.01em}
.hero p{font-size:13.5px;color:#64748b;margin:0 22px 26px;line-height:1.55}
.heroBtn{display:block;width:100%;background:linear-gradient(135deg,#10b4b2,#0e9b99);color:#fff;border:0;border-radius:18px;padding:19px;font-size:16.5px;font-weight:800;cursor:pointer;box-shadow:0 12px 24px -8px rgba(14,165,164,.8);transition:transform .08s}
.heroBtn:active{transform:scale(.98)}
.heroBtn small{display:block;font-weight:600;opacity:.92;font-size:12px;margin-top:4px}
.trust{margin-top:18px;font-size:11.5px;color:#94a3b8}
.bigcard{display:flex;align-items:center;gap:14px;background:#fff;border:2px solid #e2e8f0;border-radius:18px;padding:18px;margin-bottom:13px;cursor:pointer}
.bigcard.sel{border-color:#0ea5a4;box-shadow:0 0 0 4px rgba(14,165,164,.15)}
.bigcard .e{font-size:30px}.bigcard b{font-size:16px;display:block;line-height:1.25}
.bigcard small{display:block;color:#64748b;font-size:12.5px;margin-top:3px}
.amtbox{background:#fff;border:2px solid #0ea5a4;border-radius:18px;padding:22px;display:flex;align-items:center;justify-content:center;gap:4px}
.amtbox .cur{font-size:26px;color:#64748b;font-weight:800}
.amtbox .amtinput{border:0;outline:none;background:transparent;font-family:inherit;font-size:46px;font-weight:800;width:200px;text-align:center;color:#0f172a}
.amtbox .amtinput:focus,.amtbox .amtinput:focus-visible{outline:none !important;box-shadow:none !important;border:0 !important;--tw-ring-shadow:0 0 #0000 !important;--tw-ring-offset-shadow:0 0 #0000 !important;--tw-ring-color:transparent !important}
.txn{background:#fff;border:1.5px solid #e8edf2;border-radius:18px;padding:16px;margin-bottom:12px;cursor:pointer;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:border-color .15s,box-shadow .15s,transform .12s}
.txn:hover{border-color:#cbd5e1;box-shadow:0 4px 14px rgba(15,23,42,.08)}
.txn.sel{border-color:#0ea5a4;box-shadow:0 0 0 3px rgba(14,165,164,.18),0 6px 18px rgba(14,165,164,.12)}
.txn .top{display:flex;justify-content:space-between;align-items:center}
.txn .amt{font-weight:800;font-size:20px;letter-spacing:-.01em;color:#0f172a}
.txn .prod{font-size:13px;font-weight:600;margin-top:3px}
.prodlist{margin-top:12px;display:flex;flex-direction:column;gap:8px}
.prodline{display:flex;align-items:center;gap:12px}
.prodline .prodthumb{width:52px;height:52px;border-radius:14px;flex:0 0 auto;overflow:hidden;background:#f1f5f9;border:1px solid #e8edf2;display:flex;align-items:center;justify-content:center;font-size:24px}
.prodline .prodthumb img{width:100%;height:100%;object-fit:cover}
.prodline .pname{font-size:14.5px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.prodline .pname .chan{font-size:10.5px;font-weight:700;color:#0e7490;background:#ecfeff;border-radius:999px;padding:2px 9px}
.prodline .psku{font-size:10.5px;font-weight:700;color:#64748b;background:#f1f5f9;border-radius:6px;padding:1px 7px;white-space:nowrap}
.txn .meta{font-size:12px;color:#64748b;margin-top:12px;padding-top:11px;border-top:1px solid #f1f5f9;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.pill{font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:999px;background:#f1f5f9;color:#64748b}
.pill.warn{background:#fef3c7;color:#92400e}.pill.bad{background:#fee2e2;color:#991b1b}
.nolink{display:block;text-align:center;font-size:12.5px;color:#2563eb;font-weight:700;margin-top:6px;text-decoration:none}
.methodsel{display:flex;gap:10px;margin:10px 0 6px}
.mopt{flex:1;display:flex;align-items:center;gap:10px;background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:12px;cursor:pointer;text-align:left;transition:border-color .15s,box-shadow .15s;font-family:inherit}
.mopt:hover{border-color:#cbd5e1}
.mopt.sel{border-color:#0ea5a4;box-shadow:0 0 0 3px rgba(14,165,164,.15)}
.mopt .micon{font-size:22px;flex:0 0 auto}
.mopt .mtxt{display:flex;flex-direction:column;line-height:1.25}
.mopt .mtxt b{font-size:14px;color:#0f172a}
.mopt .mtxt small{font-size:11px;color:#64748b;margin-top:1px}
.chk{display:flex;align-items:center;gap:11px;background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:13px;margin-bottom:10px;cursor:pointer}
.chk.sel{border-color:#0ea5a4;box-shadow:0 0 0 3px rgba(14,165,164,.15)}
.chk.disabled{opacity:.5;cursor:not-allowed}
.chk .cb{width:22px;height:22px;border-radius:7px;border:2px solid #e2e8f0;flex:0 0 auto;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff}
.chk.sel .cb{background:#0ea5a4;border-color:#0ea5a4}
.chk.sel .cb::after{content:"✓"}
.chk .pthumb{width:44px;height:44px;border-radius:12px;flex:0 0 auto;overflow:hidden;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:20px}
.chk .pthumb img{width:100%;height:100%;object-fit:cover}
.chk .iprice{flex:0 0 auto;margin-left:auto;font-size:17px;font-weight:800;color:#0f172a}
.chk .ci{flex:1;min-width:0}.chk .ci b{font-size:13.5px}.chk .ci small{display:block;color:#64748b;font-size:11.5px;margin-top:2px}
.chk .ci .qtybadge{margin-left:6px;font-size:11px;font-weight:700;color:#0e7490;background:#ecfeff;border-radius:6px;padding:1px 7px}
.chk .ci .errhint{color:#b45309;font-weight:600}
.qtyrow{display:flex;align-items:center;gap:8px;margin-top:9px;font-size:12px;color:#334155}
.qtyrow .qbtn{width:28px;height:28px;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;font-size:16px;font-weight:800;line-height:1;cursor:pointer;color:#0f766e}
.qtyrow .qval{min-width:18px;text-align:center;font-size:14px}
.qtyrow .qmax{color:#94a3b8}
.opt{display:flex;gap:10px;align-items:flex-start;background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:13px;margin-bottom:10px;cursor:pointer}
.opt.sel{border-color:#0ea5a4;box-shadow:0 0 0 3px rgba(14,165,164,.15)}
.opt .r{width:18px;height:18px;border-radius:50%;border:2px solid #e2e8f0;flex:0 0 auto;margin-top:1px}
.opt.sel .r{border-color:#0ea5a4;background:radial-gradient(circle,#0ea5a4 40%,#fff 45%)}
.opt b{font-size:13.5px}.opt small{display:block;color:#64748b;font-size:11.5px;margin-top:2px}
label.fld{display:block;font-size:12px;font-weight:700;margin:12px 0 5px}
label.fld .dayhint{color:#64748b;font-weight:600}
.amtrow{display:flex;align-items:center;gap:8px}
.amtrow .cur2{font-size:16px;font-weight:800;color:#64748b}
.amtrow .amt2{font-size:17px;font-weight:800;max-width:140px}
.proddd{position:relative}
.ddbtn{display:flex;align-items:center;gap:10px;cursor:pointer;text-align:left}
.ddsel{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
.ddbtn .caret{margin-left:auto;color:#64748b}
.ddname{font-size:13.5px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ddname.muted{color:#94a3b8;font-weight:500;flex:1}
.ddinfo{display:flex;flex-direction:column;gap:1px;min-width:0;flex:1}
.ddchan{font-size:11px;font-weight:700;color:#0e7490;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ddmeta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ddprice-inline{font-size:11px;font-weight:800;color:#0f172a;background:#f1f5f9;border-radius:6px;padding:1px 7px;white-space:nowrap}
.ddthumb{width:34px;height:34px;border-radius:9px;flex:0 0 auto;overflow:hidden;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:16px}
.ddthumb img{width:100%;height:100%;object-fit:cover}
.ddlist{position:absolute;z-index:30;left:0;right:0;top:calc(100% + 4px);background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;max-height:260px;overflow:auto;box-shadow:0 10px 24px rgba(2,6,23,.12)}
.ddopt{display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer}
.ddopt:hover{background:#f8fafc}
.ddopt .ddprice{margin-left:auto;font-size:12.5px;font-weight:700;color:#0f172a}
.qtyrow{display:flex;align-items:center;justify-content:space-between;margin-top:10px}
.qtylbl{font-size:12px;font-weight:700}
.stepper{display:flex;align-items:center;gap:14px;background:#fff;border:1.5px solid #e2e8f0;border-radius:11px;padding:6px 10px}
.stepper button{width:28px;height:28px;border-radius:8px;border:0;background:#f1f5f9;font-size:16px;font-weight:800;color:#0f172a;cursor:pointer}
.stepper b{min-width:18px;text-align:center}
.stepper.sm{gap:8px;padding:3px 6px;flex:0 0 auto}
.stepper.sm button{width:24px;height:24px;font-size:15px}
.itemrow{display:flex;align-items:center;gap:10px;background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;padding:8px 10px;margin-top:8px}
.itemrow .ddinfo{flex:1;min-width:0}
.itemrm{width:26px;height:26px;flex:0 0 auto;border:0;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:16px;line-height:1;font-weight:800;cursor:pointer}
label.fld .req{color:#dc2626;font-weight:700}
.inp{width:100%;border:1.5px solid #e2e8f0;border-radius:11px;padding:11px 12px;font-size:13.5px;background:#fff;font-family:inherit}
.inp:focus{outline:none;border-color:#0ea5a4}
textarea.bigta{min-height:140px;resize:vertical;line-height:1.5}
.review{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:6px 14px;margin-bottom:14px}
.review .row{display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px solid #e2e8f0;font-size:13px}
.review .row:last-child{border-bottom:0}.review .row span{color:#64748b}.review .row b{font-weight:700;text-align:right}
.banner{border-radius:14px;padding:13px;font-size:12.5px;line-height:1.5;margin-bottom:14px}
.banner.info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af}
.conf{text-align:center;padding-top:26px}
.check{width:74px;height:74px;border-radius:50%;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:40px;margin:0 auto 16px}
.ref{font-size:24px;font-weight:800;letter-spacing:.04em;margin:6px 0 2px}
.emptywrap{text-align:center;padding-top:30px}.emptywrap .e{font-size:54px}
.photogrid{display:flex;flex-wrap:wrap;gap:10px;margin-top:4px}
.thumb{position:relative;width:92px;height:92px;border-radius:12px;overflow:hidden;border:1.5px solid #e2e8f0}
.thumbmedia{width:100%;height:100%;object-fit:cover;display:block;background:#000}
.thumb .rm{position:absolute;top:3px;right:3px;width:22px;height:22px;border:0;border-radius:50%;background:rgba(15,23,42,.75);color:#fff;font-size:15px;line-height:1;cursor:pointer}
.thumb .vbadge{position:absolute;bottom:4px;left:4px;background:rgba(15,23,42,.7);color:#fff;font-size:10px;padding:1px 6px;border-radius:999px}
.addphoto{width:92px;height:92px;border-radius:12px;border:2px dashed #cbd5e1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;cursor:pointer;color:#64748b;background:#fff}
.addphoto span{font-size:24px}.addphoto small{font-size:11px;font-weight:600}
.hidden-file{display:none}
.footer{padding:12px 16px 6px;background:#fff}
.btn{width:100%;border:0;background:#0ea5a4;color:#fff;font-weight:800;font-size:15px;padding:14px;border-radius:14px;cursor:pointer;box-shadow:0 8px 18px -6px rgba(14,165,164,.7)}
.btn:disabled{opacity:.6}
.btn.ghost{background:#eef2f6;color:#334155;border:1.5px solid #cbd5e1;box-shadow:none;margin-top:8px;font-size:13.5px;padding:11px;font-weight:700}
.brandfoot{display:flex;align-items:center;justify-content:center;padding:14px 16px 18px;background:#fff;border-top:1px solid #e2e8f0}
.brandfoot img{height:64px;opacity:.95}
</style>
