<template>
  <!--
    Which payment options this operator's machines show on their QR menu (Brian, 2026-10-29). Built by
    App\Services\Payment\OperatorPaymentMenu; a switch saves at once. A normal option shows unless switched
    off, an opt-in one (Ace & Apex) only where switched on — so an operator nobody touched keeps the normal menu.
  -->
  <div class="col-span-12 sm:col-span-6">
    <div class="relative pt-2 pb-1 md:pt-5 md:pb-3">
      <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <div class="w-full border-t border-gray-300"></div>
      </div>
      <div class="relative flex justify-center">
        <span class="px-3 bg-white text-lg font-medium text-gray-900">{{ $t('Machine Payment Menu') }}</span>
      </div>
    </div>

    <p class="mt-2 text-sm text-gray-700">
      {{ $t('The QR payment options this operator’s machines show. A machine picks up a change at its next app restart.') }}
    </p>
    <div class="text-sm text-red-600" v-if="error">{{ error }}</div>

    <p v-if="!menus.length" class="mt-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
      {{ $t('Add a QR payment gateway above to choose its payment options.') }}
    </p>

    <div v-for="menu in menus" :key="menu.gateway_id" class="mt-3">
      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
        <span class="font-semibold text-gray-900">{{ menu.gateway }}<template v-if="menu.country"> · {{ menu.country }}</template></span>
        <span class="text-gray-700">{{ $t('Machines show :count option(s)', { count: menu.shown_count }) }}</span>
      </div>
      <p v-if="menu.falls_back" class="mt-1 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
        {{ $t('Every option is switched off, so the machines show the normal menu.') }}
      </p>

      <div class="mt-2 overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="w-12 px-3 py-3 text-center text-sm font-semibold text-gray-900">#</th>
                  <th scope="col" class="px-3 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Payment Option') }}</th>
                  <th scope="col" class="px-3 py-3 text-center text-sm font-semibold text-gray-900">{{ $t('Code') }}</th>
                  <th scope="col" class="px-3 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Default') }}</th>
                  <th scope="col" class="px-3 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Last Changed') }}</th>
                  <th scope="col" class="w-48 px-3 py-3 text-center text-sm font-semibold text-gray-900">{{ $t('On Machines') }}</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="(method, index) in menu.methods" :key="method.id" :class="method.on_menu ? '' : 'bg-gray-50 text-gray-400'">
                  <td class="whitespace-nowrap px-3 py-3 text-sm text-center">{{ index + 1 }}</td>
                  <td class="px-3 py-3 text-sm">
                    <div class="flex items-center gap-3">
                      <img v-if="method.image_url" :src="method.image_url" alt="" class="h-10 w-10 flex-none rounded object-contain bg-white" />
                      <div>
                        <div class="font-semibold" :class="method.on_menu ? 'text-gray-900' : ''">{{ method.name }}</div>
                        <span v-if="method.is_gateway_default" class="rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">{{ $t('Default option') }}</span>
                      </div>
                    </div>
                  </td>
                  <td class="whitespace-nowrap px-3 py-3 text-sm text-center tabular-nums">{{ method.code }}</td>
                  <td class="px-3 py-3 text-sm">
                    {{ method.shown_by_default ? $t('Shown to every operator') : $t('Hidden unless switched on') }}
                  </td>
                  <td class="px-3 py-3 text-sm">
                    <template v-if="method.changed_at"><template v-if="method.changed_by">{{ method.changed_by }} · </template>{{ formatTime(method.changed_at) }}</template>
                    <template v-else>—</template>
                  </td>
                  <td class="whitespace-nowrap px-3 py-2 text-sm text-center">
                    <div class="inline-flex rounded-md shadow-sm" role="group">
                      <button
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium border rounded-l-md"
                        :class="method.is_shown ? 'bg-green-600 border-green-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'"
                        :disabled="!canEdit || busyId === method.id || method.is_shown"
                        @click="setShown(method, true)"
                      >
                        {{ $t('Show') }}
                      </button>
                      <button
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium border-t border-b border-r rounded-r-md"
                        :class="!method.is_shown ? 'bg-red-600 border-red-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'"
                        :disabled="!canEdit || busyId === method.id || !method.is_shown"
                        @click="setShown(method, false)"
                      >
                        {{ $t('No Show') }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import moment from 'moment'
import { t } from '@/i18n'

const props = defineProps({
  operatorId: { type: [Number, String], required: true },
  paymentMenus: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: false },
})

const menus = ref([...props.paymentMenus])
watch(() => props.paymentMenus, (value) => { menus.value = [...(value || [])] })

const busyId = ref(null)
const error = ref('')

function formatTime(iso) {
  return moment(iso).format('YYMMDD h:mm a')
}

async function setShown(method, show) {
  if (!props.canEdit) return
  busyId.value = method.id
  error.value = ''
  try {
    const { data } = await axios.post(`/operators/${props.operatorId}/payment-menu`, { payment_method_id: method.id, is_shown: show })
    menus.value = data.paymentMenus || []
  } catch (e) {
    error.value = e?.response?.data?.message || t('Could not save. Please try again.')
  } finally {
    busyId.value = null
  }
}
</script>
