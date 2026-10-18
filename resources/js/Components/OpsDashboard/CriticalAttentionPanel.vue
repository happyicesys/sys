<template>
	<!-- Operation Dashboard "Needs attention" (Brian, 2026-10-17): before Search, the machines that
	     need someone now, grouped by what is wrong. Rules live in App\Services\OpsDashboard\
	     CriticalAttention; this only draws them. Styled like the Last 30 days / Current panels. -->
	<section class="overflow-hidden rounded-xl bg-rose-50 ring-1 ring-gray-300 shadow-sm">
		<header class="flex flex-wrap items-baseline gap-x-2 gap-y-1 border-b border-gray-300 bg-rose-100 px-4 py-2.5">
			<h3 class="text-sm font-semibold text-gray-800">Needs attention</h3>
			<span v-if="data" class="text-xs font-medium text-rose-700">
				{{ data.flagged_count }} of {{ data.machine_count }} machines
			</span>
			<span v-if="loading" class="text-xs text-gray-500">Checking…</span>
			<button
				type="button"
				class="ml-auto text-xs font-medium text-indigo-600 hover:underline disabled:text-gray-400"
				:disabled="loading"
				@click="load"
			>
				Refresh
			</button>
		</header>

		<p v-if="error" class="px-4 py-3 text-sm text-red-700">{{ error }}</p>
		<p v-else-if="!data" class="px-4 py-3 text-sm text-gray-500">Checking machines…</p>
		<dl v-else class="grid grid-cols-1 gap-px bg-gray-300 sm:grid-cols-2 lg:grid-cols-3 [&>div]:bg-rose-50">
			<div v-for="group in data.groups" :key="group.key" class="px-4 py-3">
				<dt class="text-xs font-medium leading-tight text-gray-500">{{ group.label }}</dt>
				<dd class="mt-1 text-[1.375rem] leading-7 font-semibold tabular-nums" :class="countClass(group)">
					{{ group.count }}
				</dd>
				<!-- Every flagged machine, "code - site", straight away (Brian, 2026-10-18: no Show / more
				     click). A line opens that machine in the grid below. -->
				<ul v-if="group.count" class="mt-1.5 space-y-1">
					<li v-for="machine in group.machines" :key="machine.vend_id">
						<button
							type="button"
							class="w-full rounded px-1.5 py-1 text-left text-xs ring-1 ring-inset hover:opacity-80"
							:class="chipClass(group)"
							:aria-label="`${machine.code_label}, ${machine.site || 'no site'}: ${machine.detail}`"
							@click="emit('show', [machine.code_label])"
						>
							<span class="flex items-baseline gap-1.5">
								<span class="min-w-0 flex-1">
									<span class="font-semibold tabular-nums">{{ machine.code_label }}</span>
									<span> - {{ machine.site || 'No site' }}</span>
								</span>
								<span class="shrink-0 whitespace-nowrap text-[10px] opacity-75">{{ machine.detail }}</span>
							</span>
							<!-- Its live status, so nobody has to open the machine (Brian, 2026-10-18;
							     App\Support\MachineStatusSummary — the grid's Machine Status badges). -->
							<span v-if="machine.status && machine.status.length" class="mt-1 flex flex-wrap gap-1">
								<span
									v-for="badge in machine.status"
									:key="badge.label"
									class="inline-flex items-center gap-0.5 whitespace-nowrap rounded px-1 py-px text-[10px] leading-4 text-gray-800"
									:class="statusClass(badge.tone)"
								>
									<span class="font-semibold">{{ badge.label }}</span>
									<span>{{ badge.value }}</span>
								</span>
							</span>
						</button>
					</li>
				</ul>
			</div>
		</dl>
	</section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
	// The page's own query string, so the section counts the same machines as the cards.
	params: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['show']);

const data = ref(null);
const loading = ref(false);
const error = ref('');

async function load() {
	loading.value = true;
	error.value = '';
	try {
		const response = await axios.post('/vends/customers/critical-attention', props.params);
		data.value = response.data;
	} catch (e) {
		error.value = 'Could not check the machines just now. Refresh to try again.';
	} finally {
		loading.value = false;
	}
}

function countClass(group) {
	if (!group.count) {
		return 'text-gray-400';
	}
	return group.severity === 'critical' ? 'text-red-700' : 'text-amber-700';
}

function statusClass(tone) {
	return { ok: 'bg-green-200', bad: 'bg-red-200', idle: 'bg-gray-200' }[tone] || 'bg-gray-200';
}

function chipClass(group) {
	return group.severity === 'critical'
		? 'bg-red-50 text-red-800 ring-red-200'
		: 'bg-amber-50 text-amber-800 ring-amber-200';
}

onMounted(load);
</script>
