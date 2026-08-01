<template>

  <Head title="APK OTA Updates" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        APK OTA Updates
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3 space-y-4">

      <!-- Channel tabs: one Android build per fleet, independent version streams -->
      <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-4" aria-label="APK channel">
          <button
            v-for="c in channels"
            :key="c.key"
            type="button"
            @click="switchChannel(c.key)"
            :class="[
              'whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium',
              c.key === channel
                ? 'border-indigo-500 text-indigo-600'
                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
            ]"
          >
            {{ c.label }}
            <span class="ml-1 font-mono text-xs text-gray-400">{{ c.package_name }}</span>
          </button>
        </nav>
      </div>

      <!-- Fleet version spread -->
      <div class="bg-white rounded-md border px-4 py-3">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-sm font-semibold text-gray-800">Fleet version spread</h3>
            <p class="text-xs text-gray-500">
              {{ fleetCount }} machine(s) on {{ activeChannel.label }}. Each reports its running APK version when it polls for updates.
            </p>
          </div>
          <Button
            v-if="canUpdate"
            class="inline-flex space-x-1 items-center rounded-md border bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
            @click="pushOtaCheck"
          >
            <ArrowPathIcon class="h-4 w-4" />
            <span>Push OTA check to fleet</span>
          </Button>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <span v-if="!fleetVersions.length" class="text-xs text-gray-400">No machines on this channel yet.</span>
          <span
            v-for="row in fleetVersions"
            :key="row.version_code ?? 'unknown'"
            class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700"
          >
            {{ row.version_code ? ('code ' + row.version_code) : 'never checked in' }}
            <span class="ml-1 rounded-full bg-gray-700 px-1.5 text-white">{{ row.total }}</span>
          </span>
        </div>
      </div>

      <!-- Upload a new APK build -->
      <div v-if="canCreate" class="bg-white rounded-md border px-4 py-3">
        <h3 class="text-sm font-semibold text-gray-800 mb-1">Upload new build — {{ activeChannel.label }}</h3>
        <p class="text-xs text-gray-500 mb-3">
          Goes into the <span class="font-mono">{{ activeChannel.package_name }}</span> channel only. Uploads land as <strong>draft</strong>; nothing reaches a machine until you publish.
        </p>
        <form @submit.prevent="submitUpload" class="grid grid-cols-1 md:grid-cols-2 gap-3">

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Signed APK file <span class="text-red-500">*</span></label>
            <input
              type="file"
              accept=".apk,application/vnd.android.package-archive"
              @change="e => uploadForm.apk = e.target.files[0]"
              class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700"
            />
            <p v-if="uploadForm.errors.apk" class="text-xs text-red-500 mt-1">{{ uploadForm.errors.apk }}</p>
            <p class="text-xs text-gray-400 mt-1">Must be signed with this channel's pinned release key. SHA-256 &amp; size are computed on the server.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">versionCode <span class="text-red-500">*</span></label>
            <input v-model="uploadForm.version_code" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="e.g. 302" />
            <p v-if="uploadForm.errors.version_code" class="text-xs text-red-500 mt-1">{{ uploadForm.errors.version_code }}</p>
            <p class="text-xs text-gray-400 mt-1">
              Unique within this channel, and strictly greater than the build it replaces (Android refuses downgrades).
              <span v-if="liveRelease">Live now: code {{ liveRelease.version_code }}.</span>
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">versionName <span class="text-red-500">*</span></label>
            <input v-model="uploadForm.version_name" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="e.g. 3.0.2" />
            <p v-if="uploadForm.errors.version_name" class="text-xs text-red-500 mt-1">{{ uploadForm.errors.version_name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Rollout (‰, 0–1000)</label>
            <input v-model="uploadForm.rollout_permille" type="number" min="0" max="1000" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
            <p class="text-xs text-gray-400 mt-1">{{ permilleLabel(uploadForm.rollout_permille) }} — start small (10 = 1% canary), then ramp after publishing.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Min supported versionCode</label>
            <input v-model="uploadForm.min_supported_version_code" type="number" min="0" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="0" />
            <p class="text-xs text-gray-400 mt-1">Informational — builds at/below this are flagged end-of-life.</p>
          </div>

          <div class="flex items-center mt-6">
            <input id="mandatory" v-model="uploadForm.mandatory" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
            <label for="mandatory" class="ml-2 text-sm text-gray-700">Mandatory (bypass rollout — security fixes only)</label>
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Release notes</label>
            <textarea v-model="uploadForm.release_notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="What changed in this build"></textarea>
          </div>

          <div class="md:col-span-2 flex justify-end">
            <Button
              type="submit"
              :disabled="uploadForm.processing"
              class="inline-flex items-center rounded-md bg-green-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700 disabled:opacity-50"
            >
              {{ uploadForm.processing ? ('Uploading… ' + uploadProgress + '%') : 'Upload as draft' }}
            </Button>
          </div>
        </form>
      </div>

      <!-- Releases table -->
      <div class="bg-white rounded-md border px-4 py-3">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Releases — {{ activeChannel.label }}</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
              <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <th class="px-2 py-2">Version</th>
                <th class="px-2 py-2">Status</th>
                <th class="px-2 py-2">Rollout</th>
                <th class="px-2 py-2">Mandatory</th>
                <th class="px-2 py-2">Size</th>
                <th class="px-2 py-2">SHA-256</th>
                <th class="px-2 py-2">Uploaded</th>
                <th class="px-2 py-2 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="!releases.length">
                <td colspan="8" class="px-2 py-4 text-center text-gray-400">No builds uploaded to this channel yet.</td>
              </tr>
              <tr v-for="r in releases" :key="r.id" :class="{ 'bg-green-50': r.is_live }">
                <td class="px-2 py-2">
                  <div class="font-medium text-gray-800">
                    {{ r.version_name }}
                    <span v-if="r.is_live" class="ml-1 rounded bg-green-600 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-white">live</span>
                  </div>
                  <div class="text-xs text-gray-500">code {{ r.version_code }}</div>
                </td>
                <td class="px-2 py-2">
                  <span :class="statusClass(r.status)" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize">{{ r.status }}</span>
                </td>
                <td class="px-2 py-2">
                  <div v-if="canUpdate" class="flex items-center space-x-1">
                    <input v-model.number="rolloutEdits[r.id]" type="number" min="0" max="1000" class="w-20 rounded-md border-gray-300 text-xs py-1" />
                    <button type="button" class="text-indigo-600 text-xs hover:underline" @click="saveRollout(r)">save</button>
                  </div>
                  <span v-else class="text-xs text-gray-600">{{ r.rollout_percent }}%</span>
                </td>
                <td class="px-2 py-2">
                  <button v-if="canUpdate" type="button" @click="toggleMandatory(r)" :class="r.mandatory ? 'text-red-600' : 'text-gray-400'" class="text-xs hover:underline">
                    {{ r.mandatory ? 'YES' : 'no' }}
                  </button>
                  <span v-else class="text-xs">{{ r.mandatory ? 'YES' : 'no' }}</span>
                </td>
                <td class="px-2 py-2 text-xs text-gray-600">{{ r.size_mb }} MB</td>
                <td class="px-2 py-2">
                  <button type="button" class="font-mono text-xs text-gray-500 hover:text-indigo-600" :title="r.sha256" @click="copy(r.sha256)">{{ shortHash(r.sha256) }}</button>
                </td>
                <td class="px-2 py-2 text-xs text-gray-500">
                  <div>{{ formatDate(r.created_at) }}</div>
                  <div v-if="r.uploaded_by">{{ r.uploaded_by }}</div>
                </td>
                <td class="px-2 py-2">
                  <div class="flex items-center justify-end space-x-2">
                    <button type="button" v-if="canUpdate && r.status !== 'published'" @click="publish(r)" class="rounded bg-green-600 px-2 py-1 text-xs text-white hover:bg-green-700">Publish</button>
                    <button type="button" v-if="canUpdate && r.status === 'published'" @click="unpublish(r)" class="rounded bg-amber-500 px-2 py-1 text-xs text-white hover:bg-amber-600">Unpublish</button>
                    <a :href="r.file_url" target="_blank" class="rounded border px-2 py-1 text-xs text-gray-600 hover:bg-gray-50">APK</a>
                    <button type="button" v-if="canDelete && r.status !== 'published'" @click="destroy(r)" class="rounded border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="text-xs text-gray-400 mt-3">
          The live manifest served to this channel's fleet is its highest-versionCode <strong>published</strong> release. To roll back, publish a higher versionCode carrying the previous good build.
        </p>
      </div>

    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ArrowPathIcon } from '@heroicons/vue/20/solid';
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
  channel: { type: String, default: '' },
  channels: { type: Array, default: () => [] },
  releases: { type: Array, default: () => [] },
  fleetVersions: { type: Array, default: () => [] },
  fleetCount: { type: Number, default: 0 },
});

const page = usePage();
const toast = useToast();
const permissions = page.props.auth?.permissions ?? [];
const canCreate = permissions.includes('create apk-releases');
const canUpdate = permissions.includes('update apk-releases');
const canDelete = permissions.includes('delete apk-releases');

const activeChannel = computed(
  () => props.channels.find(c => c.key === props.channel) ?? { key: props.channel, label: props.channel, package_name: '' }
);
const liveRelease = computed(() => props.releases.find(r => r.is_live) ?? null);

const uploadForm = useForm({
  channel: props.channel,
  apk: null,
  version_code: '',
  version_name: '',
  rollout_permille: 10,
  min_supported_version_code: 0,
  mandatory: false,
  release_notes: '',
});
const uploadProgress = ref(0);

// Keep the upload bound to whichever tab is open.
watch(() => props.channel, (c) => { uploadForm.channel = c; });

// Editable rollout value per release row.
const rolloutEdits = reactive({});
const seedRollouts = () => props.releases.forEach(r => { rolloutEdits[r.id] = r.rollout_permille; });
onMounted(seedRollouts);
watch(() => props.releases, seedRollouts, { deep: true });

// Surface server flash messages and blocked actions as toasts.
watch(() => page.props.flash?.success, (msg) => { if (msg) toast.success(msg); });
watch(() => page.props.errors?.release, (msg) => { if (msg) toast.error(msg); });

const reloadOpts = {
  preserveScroll: true,
  onError: () => toast.error('Something went wrong.'),
};

function switchChannel(key) {
  if (key === props.channel) return;
  router.get('/apk-releases', { channel: key }, { preserveScroll: true, preserveState: false });
}

function submitUpload() {
  uploadForm
    .transform(d => ({ ...d, mandatory: d.mandatory ? 1 : 0 }))
    .post('/apk-releases/releases', {
      forceFormData: true,
      preserveScroll: true,
      onProgress: (e) => { uploadProgress.value = e ? Math.round(e.percentage) : 0; },
      onSuccess: () => { uploadForm.reset(); uploadForm.channel = props.channel; uploadProgress.value = 0; },
      onError: () => { uploadProgress.value = 0; toast.error('Upload failed — check the fields.'); },
    });
}

function publish(r) {
  if (!confirm(`Publish v${r.version_name} (code ${r.version_code}) to ${activeChannel.value.label}?`)) return;
  router.post(`/apk-releases/releases/${r.id}/publish`, {}, reloadOpts);
}
function unpublish(r) { router.post(`/apk-releases/releases/${r.id}/unpublish`, {}, reloadOpts); }
function toggleMandatory(r) { router.post(`/apk-releases/releases/${r.id}/mandatory`, {}, reloadOpts); }
function saveRollout(r) {
  router.post(`/apk-releases/releases/${r.id}/rollout`, { rollout_permille: rolloutEdits[r.id] ?? 0 }, reloadOpts);
}
function destroy(r) {
  if (!confirm(`Delete APK v${r.version_name} (code ${r.version_code})? This removes the file too.`)) return;
  router.delete(`/apk-releases/releases/${r.id}`, reloadOpts);
}
function pushOtaCheck() {
  if (!confirm(`Notify all active machines on ${activeChannel.value.label} to check for updates now?`)) return;
  router.post('/apk-releases/push-ota-check', { channel: props.channel }, reloadOpts);
}

function copy(text) { navigator.clipboard?.writeText(text); toast.success('SHA-256 copied.'); }
function shortHash(h) { return h ? h.slice(0, 10) + '…' : ''; }
function permilleLabel(p) { const n = Number(p) || 0; return (n / 10).toFixed(1) + '% of the fleet'; }
function statusClass(s) {
  return {
    published: 'bg-green-100 text-green-700',
    draft: 'bg-gray-100 text-gray-600',
    archived: 'bg-gray-200 text-gray-500',
  }[s] || 'bg-gray-100 text-gray-600';
}
function formatDate(d) { return d ? new Date(d).toLocaleString() : ''; }
</script>
