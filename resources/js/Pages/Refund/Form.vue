<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    machineID: { type: String, default: '' },
    machineFound: { type: Boolean, default: false },
    machineName: { type: String, default: null },
    reasonCodes: { type: Array, default: () => [] },
    allowedDays: { type: Array, default: () => ['today', 'yesterday'] },
});

// machineID may be missing/invalid (QR without it, or a broken APK) — fall back to a manual entry page.
const machineId = ref(props.machineID || '');
const machineName = ref(props.machineName || null);
const machineResolved = ref(props.machineFound);

const step = ref(props.machineFound ? 1 : 'enter_machine');
const loading = ref(false);
const errorMsg = ref('');

const day = ref('today');
const amount = ref('');
const candidates = ref([]);
const selected = ref(null);
const selectedItemIds = ref([]);
const reasonCode = ref('not_dispensed');
const reasonText = ref('');
const refundMethod = ref('paynow');
const payoutDestination = ref('');
const contactEmail = ref('');
const contactPhone = ref('');

// manual review
const manualDate = ref('');
const manualTime = ref('');
const manualText = ref('');

const result = ref(null);

const isAuto = computed(() => selected.value && selected.value.is_auto_refund_channel);
const multiItem = computed(() => selected.value && (selected.value.items || []).length > 1);

const titles = {
    1: 'Refund', 2: 'When?', 3: 'Amount', 4: 'Your Purchase', '4b': 'Not Found',
    '4c': 'Manual Review', 5: 'Problem Item(s)', 6: 'What Happened?', 7: 'Refund Method',
    8: 'Review', 9: 'Done', enter_machine: 'Machine ID',
};
const title = computed(() => titles[step.value] || 'Refund');

// progress (steps 2..8 -> 7 dots)
const order = [1, 2, 3, 4, 5, 6, 7, 8, 9];
const progressOn = computed(() => {
    const s = typeof step.value === 'number' ? step.value : 4;
    return Math.max(0, order.indexOf(s) - 1);
});

function cleanAmount(e) {
    let v = String(e.target.value).replace(/[^0-9.]/g, '');
    v = v.replace(/(\..*)\./g, '$1');
    const i = v.indexOf('.');
    if (i !== -1) v = v.slice(0, i + 1) + v.slice(i + 1).replace(/\./g, '').slice(0, 2);
    amount.value = v;
}

async function resolveMachine() {
    errorMsg.value = '';
    const code = (machineId.value || '').trim();
    if (!code) { errorMsg.value = 'Please enter the machine ID.'; return; }
    loading.value = true;
    try {
        const { data } = await window.axios.post('/refund/resolve', { machineID: code });
        if (data.found) {
            machineId.value = data.machineID;
            machineName.value = data.machineName;
            machineResolved.value = true;
            step.value = 1;
        } else {
            errorMsg.value = "We couldn't find that machine ID. Please check the number on the machine and try again.";
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
            machineID: machineId.value, day: day.value, amount: amount.value,
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
    // default: all items selected
    selectedItemIds.value = (c.items || [])
        .filter(i => i.vend_transaction_item_id && !i.is_refunded)
        .map(i => i.vend_transaction_item_id);
}

function toggleItem(id) {
    if (!id) return;
    const idx = selectedItemIds.value.indexOf(id);
    if (idx === -1) selectedItemIds.value.push(id); else selectedItemIds.value.splice(idx, 1);
}

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
    if (s === 1) { step.value = 2; return; }
    if (s === 2) { step.value = 3; return; }
    if (s === 3) { fetchCandidates(); return; }
    if (s === 4) {
        if (!selected.value) { errorMsg.value = 'Please pick your purchase.'; return; }
        step.value = multiItem.value ? 5 : 6;
        return;
    }
    if (s === 5) {
        if (selectedItemIds.value.length === 0) { errorMsg.value = 'Select at least one item.'; return; }
        step.value = 6; return;
    }
    if (s === 6) { step.value = isAuto.value ? 8 : 7; return; }
    if (s === 7) { step.value = 8; return; }
    if (s === 8) { submit(); return; }
    if (s === '4c') { submitManual(); return; }
    if (s === 9) { window.location.reload(); return; }
}

function back() {
    const s = step.value;
    if (s === 2) step.value = 1;
    else if (s === 3) step.value = 2;
    else if (s === 4 || s === '4b') step.value = 3;
    else if (s === '4c') step.value = '4b';
    else if (s === 5) step.value = 4;
    else if (s === 6) step.value = multiItem.value ? 5 : 4;
    else if (s === 7) step.value = 6;
    else if (s === 8) step.value = isAuto.value ? 6 : 7;
}

async function submit() {
    loading.value = true; errorMsg.value = '';
    try {
        const payload = {
            machineID: machineId.value,
            vend_transaction_id: selected.value.vend_transaction_id,
            payment_gateway_log_id: selected.value.payment_gateway_log_id,
            selected_item_ids: selectedItemIds.value,
            reason_code: reasonCode.value,
            reason_text: reasonText.value,
            refund_method: isAuto.value ? null : refundMethod.value,
            payout_destination: isAuto.value ? null : payoutDestination.value,
            contact_email: contactEmail.value,
            contact_phone: contactPhone.value,
        };
        const { data } = await window.axios.post('/refund', payload);
        result.value = data;
        step.value = 9;
    } catch (e) {
        errorMsg.value = (e.response && e.response.data && e.response.data.message) || 'Submission failed. Please try again.';
    } finally {
        loading.value = false;
    }
}

async function submitManual() {
    loading.value = true; errorMsg.value = '';
    try {
        const { data } = await window.axios.post('/refund', {
            machineID: machineId.value,
            is_manual: true,
            entered_day: manualDate.value || day.value,
            entered_amount: amount.value || null,
            approx_time: manualTime.value,
            reason_text: manualText.value,
            contact_email: contactEmail.value,
        });
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
        <div class="mac" v-if="machineResolved">📍 {{ machineName || ('Machine ' + machineId) }}</div>
        <div class="progress" v-if="machineResolved && step !== 9 && step !== 'enter_machine'">
            <i v-for="n in 7" :key="n" :class="{ on: n <= progressOn }"></i>
        </div>
    </div>

    <div class="body">
        <p v-if="errorMsg" class="err">{{ errorMsg }}</p>

        <!-- enter machine id (no/invalid machineID in the QR) -->
        <div v-if="step === 'enter_machine'">
            <div class="emptywrap" style="padding-top:14px">
                <div class="e">🥤</div>
                <div class="h2">Which machine?</div>
                <p class="p" style="text-align:center">Enter the <b>Machine ID</b> printed on the vending machine (usually near the QR sticker or on the front panel).</p>
            </div>
            <label class="fld">Machine ID</label>
            <input class="inp" v-model="machineId" placeholder="e.g. SG-00123" @keyup.enter="resolveMachine" autocapitalize="characters" />
            <button class="btn" style="margin-top:14px" @click="resolveMachine" :disabled="loading">{{ loading ? 'Checking…' : 'Continue' }}</button>
        </div>

        <!-- 1 hero -->
        <div v-else-if="step === 1" class="hero">
            <div class="ic">🍦😟</div>
            <h3>Ice cream didn't come out?</h3>
            <p>No worries — we'll help you get your money back in a few quick taps.</p>
            <button class="heroBtn" @click="next">Request a refund<small>Takes less than a minute</small></button>
        </div>

        <!-- 2 day -->
        <div v-else-if="step === 2">
            <div class="h2">When did you buy?</div>
            <p class="p">This helps us find your purchase quickly.</p>
            <div class="bigcard" :class="{ sel: day === 'today' }" @click="day = 'today'"><span class="e">☀️</span><div><b>Today</b></div></div>
            <div class="bigcard" :class="{ sel: day === 'yesterday' }" @click="day = 'yesterday'"><span class="e">🌙</span><div><b>Yesterday</b></div></div>
        </div>

        <!-- 3 amount -->
        <div v-else-if="step === 3">
            <div class="h2">How much did you pay?</div>
            <p class="p">Enter the amount shown on the machine or your bank app.</p>
            <div class="amtbox"><span class="cur">$</span><input class="amtinput" type="text" inputmode="decimal" :value="amount" @input="cleanAmount" placeholder="0.00" /></div>
            <p class="p" style="text-align:center;margin-top:10px">Type the exact amount so we can find your purchase.</p>
        </div>

        <!-- 4 candidates -->
        <div v-else-if="step === 4">
            <div class="h2">Is this your purchase?</div>
            <p class="p">We found these <b>${{ amount }}</b> purchases <b>{{ day }}</b>. Tap yours.</p>
            <div v-for="(c, idx) in candidates" :key="idx" class="txn" :class="{ sel: selected === c }" @click="chooseCandidate(c)">
                <div class="top"><span class="amt">${{ Number(c.amount).toFixed(2) }}</span><span class="pill">{{ c.payment_method || c.payment_channel }}</span></div>
                <div class="prod" v-if="c.items && c.items.length">{{ c.items.map(i => i.product_name).join(', ') }}</div>
                <div class="meta">
                    <span>⏱ {{ c.datetime }}</span>
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
                <p class="p" style="text-align:center">No <b>${{ amount }}</b> purchase {{ day }} at this machine. Try a different amount or day — or send it for manual checking.</p>
            </div>
            <button class="btn ghost" @click="step = 3">↩ Change amount / day</button>
            <button class="btn" style="margin-top:10px" @click="step = '4c'">Submit for manual review →</button>
        </div>

        <!-- 4c manual -->
        <div v-else-if="step === '4c'">
            <div class="h2">Tell us what happened</div>
            <p class="p">Our team will check this against the machine records and get back to you.</p>
            <label class="fld">When did you buy?</label>
            <div style="display:flex;gap:8px">
                <input class="inp" type="date" v-model="manualDate" />
                <input class="inp" type="time" v-model="manualTime" />
            </div>
            <label class="fld">Explain what happened</label>
            <textarea class="inp bigta" rows="6" v-model="manualText" placeholder="e.g. I paid $3.50 by PayNow around 2pm, the machine showed 'dispensing' but nothing came out."></textarea>
            <label class="fld">Email for updates</label>
            <input class="inp" type="email" v-model="contactEmail" placeholder="you@email.com" />
        </div>

        <!-- 5 items -->
        <div v-else-if="step === 5">
            <div class="h2">Which item had a problem?</div>
            <p class="p">Tap the item(s) that didn't come out. We'll only review a refund for what you select.</p>
            <div v-for="(it, idx) in selected.items" :key="idx" class="chk"
                 :class="{ sel: selectedItemIds.includes(it.vend_transaction_item_id), disabled: it.is_refunded || !it.vend_transaction_item_id }"
                 @click="(!it.is_refunded && it.vend_transaction_item_id) && toggleItem(it.vend_transaction_item_id)">
                <div class="cb"></div>
                <div class="ci"><b>{{ it.product_name }}</b><small>${{ ((it.unit_price_cents||0)/100).toFixed(2) }}<span v-if="it.vend_channel_code"> · Channel {{ it.vend_channel_code }}</span></small></div>
                <span v-if="it.had_channel_error" class="pill warn">Vend error</span>
                <span v-else-if="it.is_refunded" class="pill bad">Refunded</span>
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
        </div>

        <!-- 7 payout -->
        <div v-else-if="step === 7">
            <div class="h2">Where should we send your refund?</div>
            <p class="p">Pick how you'd like to receive the money.</p>
            <div class="opt" :class="{ sel: refundMethod === 'paynow' }" @click="refundMethod = 'paynow'"><div class="r"></div><div><b>PayNow</b><small>Fastest · mobile, NRIC or UEN</small></div></div>
            <div class="opt" :class="{ sel: refundMethod === 'paypal' }" @click="refundMethod = 'paypal'"><div class="r"></div><div><b>PayPal</b><small>Refund to your PayPal email</small></div></div>
            <label class="fld">{{ refundMethod === 'paynow' ? 'PayNow mobile / NRIC / UEN' : 'PayPal email' }}</label>
            <input class="inp" v-model="payoutDestination" :placeholder="refundMethod === 'paynow' ? 'e.g. 9123 4567' : 'you@paypal.com'" />
            <label class="fld">Email for updates</label>
            <input class="inp" type="email" v-model="contactEmail" placeholder="you@email.com" />
        </div>

        <!-- 8 review -->
        <div v-else-if="step === 8">
            <div class="h2">Review your request</div>
            <p class="p">Please confirm the details below.</p>
            <div class="banner info" v-if="isAuto">✅ This was paid via <b>Nayax</b> — your refund is processed <b>automatically</b> to your card. No PayNow needed.</div>
            <div class="review">
                <div class="row"><span>Machine</span><b>{{ machineId }}</b></div>
                <div class="row"><span>Purchase</span><b>${{ Number(selected.amount).toFixed(2) }} · {{ day }}</b></div>
                <div class="row"><span>Refund</span><b>${{ selectedAmount }}</b></div>
                <div class="row"><span>Reason</span><b>{{ (reasonCodes.find(r => r.code === reasonCode) || {}).label }}</b></div>
                <div class="row" v-if="!isAuto"><span>Refund to</span><b>{{ refundMethod }} · {{ payoutDestination }}</b></div>
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

    <div class="footer" v-if="step !== 1 && step !== 'enter_machine' && step !== '4b'">
        <button class="btn" @click="next" :disabled="loading">
            {{ loading ? 'Please wait…' : (step === 8 || step === '4c') ? 'Submit request' : step === 9 ? 'Done' : 'Continue' }}
        </button>
        <button class="btn ghost" v-if="step !== 9 && step !== '4c'" @click="back">Back</button>
        <button class="btn ghost" v-if="step === '4c'" @click="step = '4b'">Back</button>
    </div>

    <div class="brandfoot"><img src="/img/logo.png" alt="HappyIce" /></div>
</div>
</template>

<style scoped>
:root { --brand:#0ea5a4; }
.screen{max-width:430px;margin:0 auto;min-height:100vh;background:#f1f5f9;display:flex;flex-direction:column;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a}
.appbar{background:linear-gradient(135deg,#0ea5a4,#0f766e);color:#fff;padding:26px 18px 18px;text-align:center}
.ttl{font-size:18px;font-weight:800}
.mac{margin-top:8px;font-size:12px;opacity:.92}
.progress{display:flex;gap:4px;margin-top:14px}
.progress i{height:4px;flex:1;background:rgba(255,255,255,.3);border-radius:3px}
.progress i.on{background:#fff}
.body{flex:1;overflow-y:auto;padding:18px 16px}
.err{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;font-size:12.5px;padding:10px 12px;border-radius:10px;margin-bottom:12px}
.h2{font-size:17px;font-weight:800;margin-bottom:4px}
.p{font-size:12.5px;color:#64748b;line-height:1.5;margin-bottom:14px}
.hero{text-align:center;padding-top:30px}
.hero .ic{font-size:60px}
.hero h3{font-size:20px;font-weight:800;margin:12px 14px 8px}
.hero p{font-size:13px;color:#64748b;margin:0 18px 22px;line-height:1.5}
.heroBtn{display:block;width:100%;background:#0ea5a4;color:#fff;border:0;border-radius:18px;padding:18px;font-size:16px;font-weight:800;cursor:pointer;box-shadow:0 10px 22px -6px rgba(14,165,164,.7)}
.heroBtn small{display:block;font-weight:600;opacity:.9;font-size:12px;margin-top:4px}
.bigcard{display:flex;align-items:center;gap:14px;background:#fff;border:2px solid #e2e8f0;border-radius:18px;padding:18px;margin-bottom:13px;cursor:pointer}
.bigcard.sel{border-color:#0ea5a4;box-shadow:0 0 0 4px rgba(14,165,164,.15)}
.bigcard .e{font-size:30px}.bigcard b{font-size:16px}
.amtbox{background:#fff;border:2px solid #0ea5a4;border-radius:18px;padding:22px;display:flex;align-items:center;justify-content:center;gap:4px}
.amtbox .cur{font-size:26px;color:#64748b;font-weight:800}
.amtbox .amtinput{border:0;outline:none;background:transparent;font-family:inherit;font-size:46px;font-weight:800;width:200px;text-align:center;color:#0f172a}
.txn{background:#fff;border:1.5px solid #e2e8f0;border-radius:16px;padding:13px;margin-bottom:11px;cursor:pointer}
.txn.sel{border-color:#0ea5a4;box-shadow:0 0 0 3px rgba(14,165,164,.15)}
.txn .top{display:flex;justify-content:space-between;align-items:center}
.txn .amt{font-weight:800;font-size:16px}
.txn .prod{font-size:13px;font-weight:600;margin-top:3px}
.txn .meta{font-size:11.5px;color:#64748b;margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.pill{font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:999px;background:#f1f5f9;color:#64748b}
.pill.warn{background:#fef3c7;color:#92400e}.pill.bad{background:#fee2e2;color:#991b1b}
.nolink{display:block;text-align:center;font-size:12.5px;color:#2563eb;font-weight:700;margin-top:6px;text-decoration:none}
.chk{display:flex;align-items:center;gap:11px;background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:13px;margin-bottom:10px;cursor:pointer}
.chk.sel{border-color:#0ea5a4;box-shadow:0 0 0 3px rgba(14,165,164,.15)}
.chk.disabled{opacity:.5;cursor:not-allowed}
.chk .cb{width:22px;height:22px;border-radius:7px;border:2px solid #e2e8f0;flex:0 0 auto;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff}
.chk.sel .cb{background:#0ea5a4;border-color:#0ea5a4}
.chk.sel .cb::after{content:"✓"}
.chk .ci{flex:1}.chk .ci b{font-size:13.5px}.chk .ci small{display:block;color:#64748b;font-size:11.5px;margin-top:2px}
.opt{display:flex;gap:10px;align-items:flex-start;background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:13px;margin-bottom:10px;cursor:pointer}
.opt.sel{border-color:#0ea5a4;box-shadow:0 0 0 3px rgba(14,165,164,.15)}
.opt .r{width:18px;height:18px;border-radius:50%;border:2px solid #e2e8f0;flex:0 0 auto;margin-top:1px}
.opt.sel .r{border-color:#0ea5a4;background:radial-gradient(circle,#0ea5a4 40%,#fff 45%)}
.opt b{font-size:13.5px}.opt small{display:block;color:#64748b;font-size:11.5px;margin-top:2px}
label.fld{display:block;font-size:12px;font-weight:700;margin:12px 0 5px}
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
.footer{padding:12px 16px 6px;background:#fff}
.btn{width:100%;border:0;background:#0ea5a4;color:#fff;font-weight:800;font-size:15px;padding:14px;border-radius:14px;cursor:pointer;box-shadow:0 8px 18px -6px rgba(14,165,164,.7)}
.btn:disabled{opacity:.6}
.btn.ghost{background:#eef2f6;color:#334155;border:1.5px solid #cbd5e1;box-shadow:none;margin-top:8px;font-size:13.5px;padding:11px;font-weight:700}
.brandfoot{display:flex;align-items:center;justify-content:center;padding:14px 16px 18px;background:#fff;border-top:1px solid #e2e8f0}
.brandfoot img{height:64px;opacity:.95}
</style>
