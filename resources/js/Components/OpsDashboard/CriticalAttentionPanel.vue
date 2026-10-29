<template>
	<!-- Operation Dashboard "Needs attention" (Brian, 2026-10-17): before Search, the machines that
	     need someone now, grouped by what is wrong. Rules live in App\Services\OpsDashboard\
	     CriticalAttention; this only draws them. Styled like the Last 30 days / Current panels. -->
	<section class="overflow-hidden rounded-xl bg-rose-50 ring-1 ring-gray-300 shadow-sm">
		<header class="flex flex-wrap items-baseline gap-x-2 gap-y-1 border-b border-gray-300 bg-rose-100 px-4 py-2.5">
			<h3 class="text-base font-semibold text-gray-800">{{ $t('Needs attention') }}</h3>
			<span v-if="data" class="text-sm font-medium text-rose-700">
				{{ $t(':flagged of :total machines', { flagged: data.flagged_count, total: data.machine_count }) }}
			</span>
			<span v-if="loading" class="text-sm text-gray-500">{{ $t('Checking…') }}</span>
			<button
				type="button"
				class="ml-auto text-sm font-medium text-indigo-600 hover:underline disabled:text-gray-400"
				:disabled="loading"
				@click="load"
			>
				{{ $t('Refresh') }}
			</button>
		</header>

		<p v-if="error" class="px-4 py-3 text-sm text-red-700">{{ error }}</p>
		<p v-else-if="!data" class="px-4 py-3 text-sm text-gray-500">{{ $t('Checking machines…') }}</p>
		<dl v-else class="grid grid-cols-1 gap-px bg-gray-300 sm:grid-cols-2 lg:grid-cols-3 [&>div]:bg-rose-50">
			<div v-for="group in data.groups" :key="group.key" class="px-4 py-3">
				<dt class="text-sm font-medium leading-snug text-gray-500">{{ group.label }}</dt>
				<dd class="mt-1 flex items-baseline gap-2">
					<!-- The count narrows the full rows below to this category (Brian, 2026-10-18: the
					     per-group Show was missed once it went; the list stays fully shown). -->
					<button
						v-if="group.count"
						type="button"
						class="group inline-flex items-baseline gap-1.5 text-left"
						:aria-label="$t('Show the :count entries of :group in the grid', { count: group.count, group: group.label })"
						@click="emit('show', { title: group.label, vendIds: group.machines.map((m) => m.vend_id), codes: group.machines.map((m) => m.code_label) })"
					>
						<span class="text-[1.375rem] leading-7 font-semibold tabular-nums underline decoration-dotted underline-offset-4 group-hover:decoration-solid" :class="countClass(group)">{{ group.count }}</span>
						<span class="text-sm font-medium text-indigo-600 group-hover:underline">{{ $t('show entries') }}</span>
					</button>
					<span v-else class="text-[1.375rem] leading-7 font-semibold tabular-nums" :class="countClass(group)">0</span>
				</dd>
				<!-- Every flagged machine, "code - site", straight away (Brian, 2026-10-18: no Show / more
				     click). A line narrows the full rows below to that machine. -->
				<ul v-if="group.count" class="mt-1.5 space-y-1">
					<li v-for="machine in group.machines" :key="machine.vend_id">
						<button
							type="button"
							class="w-full rounded px-1.5 py-1.5 text-left text-sm ring-1 ring-inset hover:opacity-80"
							:class="chipClass(group)"
							:aria-label="`${machine.code_label}, ${machine.site || $t('no site')}: ${machine.detail}`"
							@click="emit('show', { title: `${machine.code_label} - ${machine.site || $t('No site')}`, vendIds: [machine.vend_id], codes: [machine.code_label] })"
						>
							<span class="flex items-baseline gap-1.5">
								<span class="min-w-0 flex-1">
									<span class="font-semibold tabular-nums">{{ machine.code_label }}</span>
									<span> - {{ machine.site || $t('No site') }}</span>
								</span>
								<span class="shrink-0 whitespace-nowrap text-xs opacity-80">{{ machine.detail }}</span>
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
import { t } from '@/i18n';

const props = defineProps({
	// The page's own query string, so the section counts the same machines as the cards.
	params: { type: Object, default: () => ({}) },
});

// loaded: the page fetches these machines' full grid rows once it knows them.
const emit = defineEmits(['show', 'loaded']);

const data = ref(null);
const loading = ref(false);
const error = ref('');

async function load() {
	loading.value = true;
	error.value = '';
	try {
		const response = await axios.post('/vends/customers/critical-attention', props.params);
		data.value = response.data;
		emit('loaded', response.data);
	} catch (e) {
		error.value = t('Could not check the machines just now. Refresh to try again.');
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

function chipClass(group) {
	return group.severity === 'critical'
		? 'bg-red-50 text-red-800 ring-red-200'
		: 'bg-amber-50 text-amber-800 ring-amber-200';
}

onMounted(load);
</script>
