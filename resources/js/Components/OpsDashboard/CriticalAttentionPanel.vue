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
				<dt class="flex items-start justify-between gap-2 text-xs font-medium leading-tight text-gray-500">
					<span>{{ group.label }}</span>
					<button
						v-if="group.count"
						type="button"
						class="shrink-0 font-medium text-indigo-600 hover:underline"
						@click="emit('show', group.machines.map((m) => m.code_label))"
					>
						Show
					</button>
				</dt>
				<dd class="mt-1 text-[1.375rem] leading-7 font-semibold tabular-nums" :class="countClass(group)">
					{{ group.count }}
				</dd>
				<ul v-if="group.count" class="mt-1.5 flex flex-wrap gap-1">
					<li v-for="machine in visibleMachines(group)" :key="machine.vend_id">
						<button
							type="button"
							class="rounded px-1.5 py-0.5 text-xs font-medium tabular-nums ring-1 ring-inset hover:opacity-80"
							:class="chipClass(group)"
							:title="`${machine.site || 'No site'} · ${machine.detail}`"
							:aria-label="`${machine.code_label}, ${machine.site || 'no site'}: ${machine.detail}`"
							@click="emit('show', [machine.code_label])"
						>
							{{ machine.code_label }}
						</button>
					</li>
					<li v-if="group.count > CHIP_LIMIT && !expanded[group.key]">
						<button type="button" class="px-1 text-xs text-gray-600 underline" @click="expanded[group.key] = true">
							+{{ group.count - CHIP_LIMIT }} more
						</button>
					</li>
				</ul>
			</div>
		</dl>
	</section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
	// The page's own query string, so the section counts the same machines as the cards.
	params: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['show']);

const CHIP_LIMIT = 12;

const data = ref(null);
const loading = ref(false);
const error = ref('');
const expanded = reactive({});

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

function visibleMachines(group) {
	return expanded[group.key] ? group.machines : group.machines.slice(0, CHIP_LIMIT);
}

function countClass(group) {
	if (!group.count) {
		return 'text-gray-400';
	}
	return group.severity === 'critical' ? 'text-red-700' : 'text-amber-700';
}

function chipClass(group) {
	return group.severity === 'critical'
		? 'bg-red-50 text-red-800 ring-red-200'
		: 'bg-amber-50 text-amber-800 ring-amber-200';
}

onMounted(load);
</script>
