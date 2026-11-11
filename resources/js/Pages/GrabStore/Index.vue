<template>
  <Head :title="$t('Grab Stores')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Grab Stores') }}
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div v-if="flashSuccess" class="mb-3 rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">
        {{ flashSuccess }}
      </div>

      <div class="-mx-3 sm:-mx-6 lg:-mx-8 rounded-md border border-sky-200 bg-sky-50 my-3 px-3 py-2 text-sm text-sky-900">
        <p>{{ $t('Each Grab store sells from one machine. Here you put a store on a machine, move it, pause it or switch it off. Prices and menus are set on Grab Menu & Prices.') }}</p>
        <p class="mt-1">{{ $t('Pause = a short break; it comes back by itself at the time you set, or when you press Resume. Switch off = closed until someone switches it on. Remove from machine = no machine sells it now.') }}</p>
      </div>

      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3">
        <!-- Totals: one click filters the list. -->
        <div class="flex flex-wrap gap-2 mb-4">
          <button
            v-for="chip in statusChips"
            :key="chip.key"
            type="button"
            class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-sm"
            :class="filters.status === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
            @click="setStatus(chip.key)"
          >
            <span>{{ chip.name }}</span>
            <span class="font-semibold">{{ totals[chip.key] ?? 0 }}</span>
          </button>
          <div class="flex-1"></div>
          <Button
            v-if="canUpdate"
            class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-600"
            @click="openAdd()"
          >
            <PlusIcon class="h-4 w-4" aria-hidden="true" />
            <span>{{ $t('Add store') }}</span>
          </Button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
          <OperatorFilter v-model="filters.operators" />
          <SearchInput :placeholderStr="$t('4 to 5 Digits Number')" v-model="filters.codes" @keyup.enter="search()">
            {{ $t('Machine ID') }}
            <span class="text-[9px]">
              {{ $t('("," for multiple)') }}
            </span>
          </SearchInput>
          <SearchInput :placeholderStr="$t('Grab store')" v-model="filters.search" @keyup.enter="search()">
            {{ $t('Search') }}
          </SearchInput>
        </div>
        <div class="mt-3 flex items-center justify-between">
          <Button
            class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-2 text-sm font-medium text-white hover:bg-green-600"
            @click="search()"
          >
            <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
            <span>{{ $t('Search') }}</span>
          </Button>
          <div class="text-right">
            <p class="text-sm text-gray-700">
              {{ $t('Showing :from to :to of :total results', { from: stores.meta.from ?? 0, to: stores.meta.to ?? 0, total: stores.meta.total }) }}
            </p>
            <p class="text-sm text-gray-700">
              {{ $t('Grab sales of these stores: :week last 7 days · :month last 30 days', { week: money(salesTotals?.sales_7d), month: money(salesTotals?.sales_30d) }) }}
            </p>
          </div>
        </div>
      </div>

      <div class="mt-4 -mx-3 sm:-mx-6 lg:-mx-8 shadow-sm ring-1 ring-black ring-opacity-5 overflow-x-auto bg-white">
        <table class="min-w-full border-separate" style="border-spacing: 0">
          <thead class="bg-gray-100">
            <tr class="divide-x divide-gray-200">
              <TableHead>#</TableHead>
              <TableHead>{{ $t('Grab store') }}</TableHead>
              <TableHead>{{ $t('Machine') }}</TableHead>
              <TableHead>{{ $t('Status') }}</TableHead>
              <TableHead>{{ $t('Machine ID on Grab') }}</TableHead>
              <TableHead>{{ $t('Orders') }}</TableHead>
              <TableHead v-for="column in salesColumns" :key="column.key">
                <button type="button" class="inline-flex items-center gap-1 hover:text-indigo-700" :title="$t('Sort by this, highest first')" @click="sortBy(column.key)">
                  <span>{{ column.name }}</span>
                  <span :class="filters.sort === column.key ? 'text-indigo-700' : 'text-gray-300'">▼</span>
                </button>
              </TableHead>
              <TableHead></TableHead>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-for="(store, index) in rows" :key="store.id" class="divide-x divide-gray-200 align-top" :class="index % 2 ? 'bg-gray-50' : 'bg-white'">
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-center">
                {{ (stores.meta.from ?? 1) + index }}
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                <div class="font-mono whitespace-nowrap">{{ store.ref_number }}</div>
                <div class="text-xs text-gray-500">{{ store.operator?.code }}</div>
                <div v-if="store.remarks" class="text-xs text-gray-500 whitespace-pre-wrap">{{ store.remarks }}</div>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                <template v-if="store.binding">
                  <MachineIdLink :vend="store.binding.vend" :code="store.binding.vend ? null : store.binding.vend_code" />
                  <div class="text-xs text-gray-600">{{ store.binding.site }}</div>
                  <div class="text-xs text-gray-500">{{ $t('Since :time', { time: formatDateTime(store.binding.since) }) }}</div>
                  <div v-if="store.binding.machine_menu" class="text-xs text-gray-400">{{ $t('Menu: what is in this machine') }}</div>
                  <div v-else class="text-xs text-gray-400">{{ $t('Old Grab menu: :template', { template: store.binding.template }) }}</div>
                </template>
                <span v-else class="text-gray-400">{{ $t('No machine') }}</span>
                <div
                  v-for="other in store.other_open_bindings"
                  :key="other.id"
                  class="mt-1 rounded border border-amber-300 bg-amber-50 px-2 py-1 text-xs text-amber-800"
                >
                  {{ $t('Also still open on machine :code since :since.', { code: other.vend_code, since: formatDateTime(other.since) }) }}
                  <button
                    v-if="canUpdate"
                    type="button"
                    class="ml-1 underline font-medium"
                    @click="closeLeftover(store, other)"
                  >{{ $t('Take it off :code', { code: other.vend_code }) }}</button>
                </div>
                <div v-if="store.grab_change" class="mt-1 rounded border border-sky-300 bg-sky-50 px-2 py-1 text-xs text-sky-900">
                  {{ $t('Waiting for Grab: :what, sent :when by :who', { what: changeKindNames[store.grab_change.kind] ?? store.grab_change.kind, when: formatDateTime(store.grab_change.requested_at), who: store.grab_change.requested_by ?? '—' }) }}
                  <span v-if="store.grab_change.go_live_on">· {{ $t('goes live :date', { date: store.grab_change.go_live_on }) }}</span>
                  <button v-if="canUpdate" type="button" class="ml-1 underline font-medium" @click="markChangeDone(store)">{{ $t('Done') }}</button>
                </div>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass[store.status]">
                  {{ statusNames[store.status] }}
                </span>
                <div v-if="store.status === 'paused'" class="text-xs text-gray-600 mt-1">
                  <span v-if="store.binding.pause_reason">{{ store.binding.pause_reason }}</span>
                  <span v-if="store.binding.paused_until"> · {{ $t('until :time', { time: formatDateTime(store.binding.paused_until) }) }}</span>
                </div>
                <div v-if="store.status === 'off' && store.binding" class="text-xs text-gray-500 mt-1">
                  {{ $t('Paused on Grab while off') }}
                </div>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-center">
                <template v-if="store.grab_machine_code">
                  <span :class="store.grab_machine_code_mismatch ? 'text-red-600 font-semibold' : 'text-gray-700'">
                    {{ store.grab_machine_code }}
                  </span>
                  <div v-if="store.grab_machine_code_mismatch" class="text-xs text-red-600">
                    {{ $t('Not this machine: email Grab') }}
                  </div>
                </template>
                <span v-else class="text-gray-400">—</span>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-center">
                <div>{{ store.orders_30d }} <span class="text-xs text-gray-500">/ 30d</span></div>
                <div class="text-xs text-gray-500">{{ store.last_order_at ? $t('Last :time', { time: formatDateTime(store.last_order_at) }) : $t('No orders') }}</div>
                <a
                  v-if="store.errors_7d"
                  :href="`/grab-errors?search=${encodeURIComponent(store.ref_number)}`"
                  class="mt-1 inline-block rounded bg-red-50 px-1.5 py-0.5 text-xs font-semibold text-red-700 hover:underline"
                  @click.stop
                >{{ $tc(':count Grab error / 7d|:count Grab errors / 7d', store.errors_7d) }}</a>
                <a
                  v-if="store.promotions_live"
                  :href="`/grab-promotions?search=${encodeURIComponent(store.ref_number)}`"
                  class="mt-1 inline-block rounded bg-indigo-50 px-1.5 py-0.5 text-xs font-semibold text-indigo-700 hover:underline"
                  @click.stop
                >{{ $tc(':count promotion on Grab|:count promotions on Grab', store.promotions_live) }}</a>
              </TableData>
              <TableData
                v-for="column in salesColumns"
                :key="column.key"
                :currentIndex="index"
                :totalLength="rows.length"
                inputClass="text-right whitespace-nowrap"
              >
                <template v-if="store.binding">
                  <div class="font-semibold">{{ money(store[column.key]) }}</div>
                  <div class="text-xs text-gray-500">{{ $tc(':count order|:count orders', store[column.key + '_orders']) }}</div>
                  <div v-if="daysOnMachine(store) < column.days" class="text-xs text-amber-700">
                    {{ $tc('Only :count day on this machine|Only :count days on this machine', daysOnMachine(store)) }}
                  </div>
                </template>
                <span v-else class="text-gray-400">—</span>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                <div class="flex flex-wrap gap-1">
                  <button type="button" :class="actionClass" @click="openHistory(store)">{{ $t('History') }}</button>
                  <button v-if="store.binding" type="button" :class="actionClass" @click="openMenu(store)">{{ $t('On Grab now') }}</button>
                  <template v-if="canUpdate">
                    <button type="button" :class="actionClass" @click="openEdit(store)">{{ $t('Edit') }}</button>
                    <button v-if="store.can_ask_grab" type="button" :class="actionClass" @click="openChange(store)">{{ $t('Update on Grab…') }}</button>
                    <button v-if="store.binding" type="button" :class="actionClass" @click="openMove(store)">{{ $t('Move') }}</button>
                    <button v-else type="button" :class="actionClass" @click="openBind(store)">{{ $t('Put on machine') }}</button>
                    <button v-if="store.binding && store.binding.is_active" type="button" :class="actionClass" @click="openPause(store)">{{ $t('Pause') }}</button>
                    <button v-if="store.binding && !store.binding.is_active" type="button" :class="actionClass" @click="resume(store)">{{ $t('Resume') }}</button>
                    <button v-if="store.binding" type="button" :class="actionClass" @click="openUnbind(store)">{{ $t('Remove from machine') }}</button>
                    <button type="button" :class="store.is_off ? actionClass : dangerClass" @click="toggleOff(store)">
                      {{ store.is_off ? $t('Switch on') : $t('Switch off') }}
                    </button>
                  </template>
                </div>
              </TableData>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="7" class="py-6 text-center text-sm text-gray-500">{{ $t('No Grab stores match.') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Paginator v-if="rows.length" :links="stores.links" :meta="stores.meta" class="mt-3" />
    </div>

    <!-- Add store -->
    <Modal :open="modal === 'add'" @modalClose="closeModal">
      <template #header>{{ $t('Add Grab store') }}</template>
      <div class="space-y-3 text-sm">
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Operator') }}</label>
          <select v-model="addForm.operator_id" :class="inputClass">
            <option v-for="option in operatorOptions" :key="option.id" :value="option.id">{{ option.full_name ?? option.name }}</option>
          </select>
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Grab merchant ID') }}</label>
          <input v-model="addForm.ref_number" type="text" :class="inputClass" placeholder="4-C7..." />
          <p class="text-xs text-gray-500 mt-1">{{ $t('ConnectVend asks Grab about it before saving.') }}</p>
          <InputError :message="addForm.errors.ref_number" />
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Machine ID (optional)') }}</label>
          <input v-model="addForm.vend_code" type="text" :class="inputClass" />
          <InputError :message="addForm.errors.vend_code" />
        </div>
        <div v-if="addForm.vend_code">
          <label class="block font-medium text-gray-700">{{ $t('Old Grab menu (template)') }}</label>
          <select v-model="addForm.delivery_product_mapping_id" :class="inputClass">
            <option v-for="template in templatesFor(addForm.operator_id)" :key="template.id" :value="template.id">{{ template.name }}</option>
          </select>
          <InputError :message="addForm.errors.delivery_product_mapping_id" />
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Remarks') }}</label>
          <textarea v-model="addForm.remarks" rows="2" :class="inputClass"></textarea>
        </div>
        <label class="flex items-center gap-2 text-gray-700">
          <input v-model="addForm.skip_grab_check" type="checkbox" class="rounded border-gray-300" />
          {{ $t('Save without checking with Grab') }}
          <span class="text-xs text-gray-500">({{ $t('the store is not live on Grab yet') }})</span>
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" :class="actionClass" @click="closeModal">{{ $t('Cancel') }}</button>
          <button type="button" :class="primaryClass" :disabled="addForm.processing" @click="submitAdd">{{ $t('Save') }}</button>
        </div>
      </div>
    </Modal>

    <!-- Put on machine / Move -->
    <Modal :open="modal === 'move' || modal === 'bind'" @modalClose="closeModal">
      <template #header>
        {{ modal === 'move' ? $t('Move :ref to another machine', { ref: current?.ref_number }) : $t('Put :ref on a machine', { ref: current?.ref_number }) }}
      </template>
      <div class="space-y-3 text-sm">
        <p v-if="modal === 'move'" class="text-gray-600">
          {{ $t('Machine :code stops selling this store and the new machine starts at the same moment. The store stays open on Grab.', { code: current?.binding?.vend_code }) }}
        </p>
        <p v-if="modal === 'move' && current?.promotions_live" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
          {{ $t('Promotions made in ConnectVend on this store end on Grab (ones made in the Grab app stay). Add them again afterwards if you still want them.') }}
        </p>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Machine ID') }}</label>
          <input v-model="moveForm.vend_code" type="text" :class="inputClass" />
          <InputError :message="moveForm.errors.vend_code" />
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Old Grab menu (template)') }}</label>
          <p v-if="current?.binding?.machine_menu" class="text-xs text-gray-500">{{ $t('This store lists what is in its machine, so this is only used if it goes back to its old menu.') }}</p>
          <select v-model="moveForm.delivery_product_mapping_id" :class="inputClass">
            <option v-for="template in templatesFor(current?.operator_id, current?.binding?.template_id)" :key="template.id" :value="template.id">{{ template.name }}</option>
          </select>
          <InputError :message="moveForm.errors.delivery_product_mapping_id" />
        </div>
        <div v-if="modal === 'move'">
          <label class="block font-medium text-gray-700">{{ $t('Why') }}</label>
          <select v-model="moveForm.reason" :class="inputClass">
            <option v-for="key in moveReasons" :key="key" :value="key">{{ $t(unbindReasons[key]) }}</option>
          </select>
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Remarks') }}</label>
          <input v-model="moveForm.remarks" type="text" :class="inputClass" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" :class="actionClass" @click="closeModal">{{ $t('Cancel') }}</button>
          <button type="button" :class="primaryClass" :disabled="moveForm.processing" @click="submitMove">{{ modal === 'move' ? $t('Move') : $t('Save') }}</button>
        </div>
      </div>
    </Modal>

    <!-- Update on Grab: prepare the request for the GrabMerchant app or Grab's Help Centre form, then keep track of it. -->
    <Modal :open="modal === 'change'" @modalClose="closeModal">
      <template #header>{{ $t('Update :ref on Grab', { ref: current?.ref_number }) }}</template>
      <div class="space-y-3 text-sm">
        <p class="text-gray-600">{{ $t('Grab does not take new requests by email. Send the change in the GrabMerchant app or on Grab\'s Help Centre form, then press "I\'ve sent it" so ConnectVend keeps track of it.') }}</p>
        <p v-if="change.loading" class="text-gray-500">{{ $t('Loading…') }}</p>
        <template v-else>
          <p v-if="change.draft?.open" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
            {{ $t('A request is already waiting for Grab (:what, sent :when). Sending another adds a new one.', { what: changeKindNames[change.draft.open.kind] ?? change.draft.open.kind, when: formatDateTime(change.draft.open.requested_at) }) }}
          </p>
          <div>
            <label class="block font-medium text-gray-700">{{ $t('What should Grab change?') }}</label>
            <select v-model="changeForm.kind" :class="inputClass">
              <option v-for="(name, key) in changeKindNames" :key="key" :value="key">{{ name }}</option>
            </select>
          </div>
          <template v-if="changeForm.kind === 'address'">
            <div>
              <label class="block font-medium text-gray-700">{{ $t('New address') }}</label>
              <input v-model="changeForm.address" type="text" :class="inputClass" />
              <p class="text-xs text-gray-500 mt-1">{{ $t('From the Site of machine :code (:site). Check it, and say where the machine stands (e.g. lobby, level 1).', { code: change.draft?.vend_code ?? '—', site: change.draft?.site ?? '—' }) }}</p>
              <InputError :message="changeForm.errors.address" />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block font-medium text-gray-700">{{ $t('Postal code') }}</label>
                <input v-model="changeForm.postal_code" type="text" :class="inputClass" />
              </div>
              <div>
                <label class="block font-medium text-gray-700">{{ $t('Goes live on') }}</label>
                <input v-model="changeForm.go_live_on" type="date" :class="inputClass" />
              </div>
            </div>
          </template>
          <div v-else>
            <label class="block font-medium text-gray-700">{{ $t('What exactly should change?') }}</label>
            <textarea v-model="changeForm.details" rows="2" :class="inputClass" :placeholder="$t('e.g. new name: Ice Cream Vending Machine (Unmanned Store) - Tampines St 92')"></textarea>
            <InputError :message="changeForm.errors.details" />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div class="rounded-md border px-3 py-2">
              <div class="font-semibold text-gray-800">{{ $t('One store: GrabMerchant app (fastest)') }}</div>
              <ol v-if="changeForm.kind === 'address'" class="mt-1 list-decimal pl-5 space-y-0.5 text-gray-700">
                <li>{{ $t('Open the GrabMerchant app and pick this store (:ref).', { ref: current?.ref_number }) }}</li>
                <li>{{ $t('Tap the Store tile, then Address.') }}</li>
                <li>{{ $t('Enter the new address above and move the pin to the store entrance.') }}</li>
                <li>{{ $t('Pick the go-live date (it changes at 9:00 that morning) and tap Submit.') }}</li>
              </ol>
              <p v-else class="mt-1 text-gray-700">{{ $t('Open the GrabMerchant app, pick this store, tap the Store tile and change it there. If the app does not offer it, use the Help Centre form.') }}</p>
              <p class="mt-1 text-xs text-gray-500">{{ $t('Grab allows 2 address changes a month.') }}</p>
              <button type="button" :class="primaryClass" class="mt-2" :disabled="changeForm.processing" @click="submitChange('app')">{{ $t('I\'ve sent it in the app') }}</button>
            </div>
            <div class="rounded-md border px-3 py-2">
              <div class="font-semibold text-gray-800">{{ $t('Several stores, or not in the app: Help Centre form') }}</div>
              <p class="mt-1 text-gray-700">{{ $t('Copy the text below, open Grab\'s form, choose what to update and paste it in. Log in first.') }}</p>
              <textarea readonly rows="6" class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 font-mono text-xs" :value="changeText"></textarea>
              <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" :class="actionClass" @click="copyChangeText">{{ change.copied ? $t('Copied') : $t('Copy text') }}</button>
                <a :href="change.draft?.help_centre_url" target="_blank" rel="noopener" :class="actionClass">{{ $t('Open Grab\'s form') }}</a>
              </div>
              <button type="button" :class="primaryClass" class="mt-2" :disabled="changeForm.processing" @click="submitChange('form')">{{ $t('I\'ve sent the form') }}</button>
            </div>
          </div>
          <p class="text-xs text-gray-500">{{ $t('Grab replies to grabcollection.sg@happyice.com.sg. For an address change they may send a relocation form to sign and stamp: reply on that same email.') }}</p>
        </template>
        <div class="flex justify-end">
          <button type="button" :class="actionClass" @click="closeModal">{{ $t('Cancel') }}</button>
        </div>
      </div>
    </Modal>

    <!-- Pause -->
    <Modal :open="modal === 'pause'" @modalClose="closeModal">
      <template #header>{{ $t('Pause :ref', { ref: current?.ref_number }) }}</template>
      <div class="space-y-3 text-sm">
        <p class="text-gray-600">{{ $t('The store is paused on Grab: customers cannot order from it.') }}</p>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Reason') }}</label>
          <input v-model="pauseForm.reason" type="text" :class="inputClass" :placeholder="$t('e.g. machine under repair')" />
          <InputError :message="pauseForm.errors.reason" />
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Until (optional)') }}</label>
          <div class="flex flex-wrap gap-1 mt-1">
            <button v-for="preset in pausePresets" :key="preset.hours" type="button" :class="actionClass" @click="setPauseFor(preset.hours)">{{ preset.name }}</button>
          </div>
          <input v-model="pauseForm.until" type="datetime-local" :class="inputClass" />
          <p class="text-xs text-gray-500 mt-1">{{ $t('Empty = paused until someone presses Resume.') }}</p>
          <InputError :message="pauseForm.errors.until" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" :class="actionClass" @click="closeModal">{{ $t('Cancel') }}</button>
          <button type="button" :class="primaryClass" :disabled="pauseForm.processing" @click="submitPause">{{ $t('Pause') }}</button>
        </div>
      </div>
    </Modal>

    <!-- Remove from machine -->
    <Modal :open="modal === 'unbind'" @modalClose="closeModal">
      <template #header>{{ $t('Remove :ref from machine :code', { ref: current?.ref_number, code: current?.binding?.vend_code }) }}</template>
      <div class="space-y-3 text-sm">
        <p class="text-gray-600">{{ $t('No machine will sell this store, so it is paused on Grab. Its history is kept.') }}</p>
        <p v-if="current?.promotions_live" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
          {{ $t('Promotions made in ConnectVend on this store end on Grab (ones made in the Grab app stay).') }}
        </p>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Why') }}</label>
          <select v-model="unbindForm.reason" :class="inputClass">
            <option v-for="(name, key) in unbindReasons" :key="key" :value="key">{{ $t(name) }}</option>
          </select>
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Remarks') }}</label>
          <input v-model="unbindForm.remarks" type="text" :class="inputClass" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" :class="actionClass" @click="closeModal">{{ $t('Cancel') }}</button>
          <button type="button" :class="dangerClass" :disabled="unbindForm.processing" @click="submitUnbind">{{ $t('Remove from machine') }}</button>
        </div>
      </div>
    </Modal>

    <!-- Edit store -->
    <Modal :open="modal === 'edit'" @modalClose="closeModal">
      <template #header>{{ $t('Edit Grab store :ref', { ref: current?.ref_number }) }}</template>
      <div class="space-y-3 text-sm">
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Operator') }}</label>
          <select v-model="editForm.operator_id" :class="inputClass" :disabled="!!current?.binding">
            <option v-if="current?.operator && !operatorOptions.some(option => option.id === current.operator_id)" :value="current.operator_id">{{ current.operator.name }}</option>
            <option v-for="option in operatorOptions" :key="option.id" :value="option.id">{{ option.full_name ?? option.name }}</option>
          </select>
          <p v-if="current?.binding" class="text-xs text-gray-500 mt-1">{{ $t('Take the store off its machine to change the operator: the operator decides which Grab account serves it.') }}</p>
          <InputError :message="editForm.errors.operator_id" />
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Grab merchant ID') }}</label>
          <input v-model="editForm.ref_number" type="text" :class="inputClass" />
          <p v-if="merchantIdChanged" class="text-xs text-gray-500 mt-1">{{ $t('ConnectVend asks Grab about the new merchant ID before saving.') }}</p>
          <InputError :message="editForm.errors.ref_number" />
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Remarks') }}</label>
          <textarea v-model="editForm.remarks" rows="2" :class="inputClass"></textarea>
          <InputError :message="editForm.errors.remarks" />
        </div>
        <label v-if="merchantIdChanged" class="flex items-center gap-2 text-gray-700">
          <input v-model="editForm.skip_grab_check" type="checkbox" class="rounded border-gray-300" />
          {{ $t('Save without Grab check') }}
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" :class="actionClass" @click="closeModal">{{ $t('Cancel') }}</button>
          <button type="button" :class="primaryClass" :disabled="editForm.processing" @click="submitEdit">{{ $t('Save') }}</button>
        </div>
      </div>
    </Modal>

    <!-- What Grab gets for this store now (read only) -->
    <Modal :open="modal === 'menu'" @modalClose="closeModal">
      <template #header>{{ $t('On Grab now: :ref on machine :code', { ref: current?.ref_number, code: current?.binding?.vend_code }) }}</template>
      <div class="space-y-3 text-sm">
        <div v-if="menu.loading" class="text-gray-500">{{ $t('Loading…') }}</div>
        <p v-else-if="menu.failed" class="text-red-600">{{ $t('Could not load the menu.') }}</p>
        <template v-else-if="menu.data">
          <p v-if="menu.data.error" class="text-red-600">{{ menu.data.error }}</p>
          <template v-else>
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">
                {{ $t(':available of :items items can be ordered', { available: menu.data.counts.available, items: menu.data.counts.items }) }}
              </span>
              <span v-if="!menu.data.selling" class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                {{ $t('The store is not selling: Grab shows it closed.') }}
              </span>
              <span v-else-if="menu.data.grab_has_it === true" class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">{{ $t('Grab has this menu') }}</span>
              <span v-else-if="menu.data.grab_has_it === false" class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $t('Grab has not pulled this version yet') }}</span>
            </div>
            <p class="text-xs text-gray-500">{{ menu.data.machine_menu ? $t('The menu exactly as Grab pulls it, built now from what is in the machine and its stock. Read only.') : $t('The menu exactly as Grab pulls it, built now from the old Grab menu (template) and the machine\'s stock. Read only.') }}</p>
            <div class="overflow-x-auto">
              <table class="min-w-full text-xs border">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="px-2 py-1 text-left">{{ $t('Slot') }}</th>
                    <th class="px-2 py-1 text-left">{{ $t('Product') }}</th>
                    <th class="px-2 py-1 text-left">{{ $t('Grab sub-category') }}</th>
                    <th class="px-2 py-1 text-right">{{ $t('Grab price') }}</th>
                    <th class="px-2 py-1 text-right">{{ $t('In machine') }}</th>
                    <th class="px-2 py-1 text-right">{{ $t('On open orders') }}</th>
                    <th class="px-2 py-1 text-right">{{ $t('Offered on Grab') }}</th>
                    <th class="px-2 py-1 text-left">{{ $t('On Grab') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, lineIndex) in menu.data.lines" :key="lineIndex" class="border-t align-top">
                    <td class="px-2 py-1 font-mono">{{ line.slot ?? '—' }}</td>
                    <td class="px-2 py-1">
                      <span v-if="line.product_code" class="mr-1 text-gray-500">{{ line.product_code }}</span>{{ line.name }}
                      <div v-if="!line.has_photo" class="text-amber-700">{{ $t('No photo') }}</div>
                    </td>
                    <td class="px-2 py-1">{{ line.sub_category }}</td>
                    <td class="px-2 py-1 text-right whitespace-nowrap">{{ money(line.price) }}</td>
                    <td class="px-2 py-1 text-right">{{ line.machine_qty ?? '—' }}</td>
                    <td class="px-2 py-1 text-right">{{ line.held || '' }}</td>
                    <td class="px-2 py-1 text-right">{{ line.max_stock }}</td>
                    <td class="px-2 py-1">
                      <span v-if="line.available" class="font-semibold text-green-700">{{ $t('Can order') }}</span>
                      <template v-else>
                        <span class="font-semibold text-gray-600">{{ $t('Unavailable') }}</span>
                        <div v-if="line.reason" class="text-gray-500">{{ menuReasons[line.reason] ?? line.reason }}</div>
                      </template>
                    </td>
                  </tr>
                  <tr v-if="!menu.data.lines.length">
                    <td colspan="8" class="px-2 py-3 text-center text-gray-500">{{ $t('The menu has no items.') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </template>
      </div>
    </Modal>

    <!-- History -->
    <Modal :open="modal === 'history'" @modalClose="closeModal">
      <template #header>{{ $t('Grab store :ref', { ref: current?.ref_number }) }}</template>
      <div class="space-y-4 text-sm">
        <div class="flex items-center gap-2">
          <button type="button" :class="actionClass" :disabled="grabCheck.loading" @click="askGrab">{{ $t('Ask Grab now') }}</button>
          <span v-if="grabCheck.loading" class="text-gray-500">{{ $t('Asking Grab…') }}</span>
          <span v-else-if="grabCheck.result" :class="grabCheck.result.ok ? 'text-green-700' : 'text-red-600'">{{ grabCheck.result.message }}</span>
        </div>

        <div v-if="history.loading" class="text-gray-500">{{ $t('Loading…') }}</div>
        <template v-else-if="history.data">
          <div>
            <h4 class="font-semibold text-gray-800 mb-1">{{ $t('Machines over time') }}</h4>
            <div class="overflow-x-auto">
              <table class="min-w-full text-xs border">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="px-2 py-1 text-left">{{ $t('Machine') }}</th>
                    <th class="px-2 py-1 text-left">{{ $t('Site') }}</th>
                    <th class="px-2 py-1 text-left">{{ $t('From') }}</th>
                    <th class="px-2 py-1 text-left">{{ $t('To') }}</th>
                    <th class="px-2 py-1 text-left">{{ $t('By') }}</th>
                    <th class="px-2 py-1 text-right">{{ $t('Orders') }}</th>
                    <th class="px-2 py-1 text-right">{{ $t('Sales') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="binding in history.data.bindings" :key="binding.id" class="border-t align-top">
                    <td class="px-2 py-1">
                      <MachineIdLink :vend="binding.vend" :code="binding.vend ? null : binding.vend_code" />
                      <div class="text-gray-400">{{ binding.template }}</div>
                    </td>
                    <td class="px-2 py-1">{{ binding.site }}</td>
                    <td class="px-2 py-1 whitespace-nowrap">{{ formatDateTime(binding.from) }}</td>
                    <td class="px-2 py-1 whitespace-nowrap">
                      <span v-if="binding.to">{{ formatDateTime(binding.to) }}</span>
                      <span v-else class="font-semibold text-green-700">{{ $t('now') }}</span>
                      <div v-if="binding.unbind_reason_label" class="text-gray-500">{{ $t(binding.unbind_reason_label) }}</div>
                      <div v-if="binding.unbind_remarks" class="text-gray-500">{{ binding.unbind_remarks }}</div>
                    </td>
                    <td class="px-2 py-1">
                      <div>{{ binding.bound_by ?? $t('not recorded') }}</div>
                      <div v-if="binding.to" class="text-gray-500">{{ $t('Taken off by :name', { name: binding.unbound_by ?? $t('not recorded') }) }}</div>
                    </td>
                    <td class="px-2 py-1 text-right">{{ binding.orders_count }}</td>
                    <td class="px-2 py-1 text-right">{{ money(binding.orders_subtotal) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div>
            <h4 class="font-semibold text-gray-800 mb-1">{{ $t('What happened') }}</h4>
            <ul v-if="history.data.events.length" class="divide-y border rounded">
              <li v-for="event in history.data.events" :key="event.id" class="px-2 py-1 flex gap-2">
                <span class="whitespace-nowrap text-gray-500">{{ formatDateTime(event.at) }}</span>
                <span class="flex-1">
                  <span class="font-medium">{{ eventText(event) }}</span>
                  <span v-if="event.reason" class="text-gray-600"> — {{ event.reason }}</span>
                  <span v-if="grabNote(event)" class="text-gray-500"> ({{ grabNote(event) }})</span>
                </span>
                <span class="whitespace-nowrap text-gray-500">{{ event.is_system ? $t('system') : event.user }}</span>
              </li>
            </ul>
            <p v-else class="text-gray-500">{{ $t('Nothing recorded yet. Changes are recorded from now on.') }}</p>
          </div>

          <div>
            <h4 class="font-semibold text-gray-800 mb-1">{{ $t('Grab errors (last 30 days)') }}</h4>
            <ul v-if="history.data.errors?.length" class="divide-y border rounded">
              <li v-for="error in history.data.errors" :key="error.id" class="px-2 py-1 flex gap-2">
                <span class="whitespace-nowrap text-gray-500">{{ formatDateTime(error.at) }}</span>
                <span class="whitespace-nowrap font-medium">{{ $t(error.action_name) }}</span>
                <span class="whitespace-nowrap" :class="error.status === null || error.status >= 500 ? 'text-red-600' : 'text-amber-700'">{{ error.status ?? $t('No answer') }}</span>
                <span class="flex-1 text-gray-600 break-all">{{ error.message }}</span>
              </li>
            </ul>
            <p v-else class="text-gray-500">{{ $t('No Grab errors for this store.') }}</p>
          </div>
        </template>
      </div>
    </Modal>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import InputError from '@/Components/InputError.vue';
import MachineIdLink from '@/Components/MachineIdLink.vue';
import Modal from '@/Components/Modal.vue';
import OperatorFilter from '@/Components/OperatorFilter.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import { MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/20/solid';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import moment from 'moment';
import { computed, ref } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
  stores: Object,
  totals: Object,
  filters: Object,
  templateOptions: Array,
  unbindReasons: Object,
  canUpdate: Boolean,
  salesTotals: Object,
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)
const rows = computed(() => props.stores.data ?? [])

const inputClass = 'mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md'
const actionClass = 'rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-50'
const dangerClass = 'rounded border border-red-300 bg-white px-2 py-1 text-xs text-red-700 hover:bg-red-50'
const primaryClass = 'rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50'

const statusNames = {
  selling: t('Selling'),
  paused: t('Paused'),
  off: t('Switched off'),
  no_machine: t('No machine'),
}
const statusClass = {
  selling: 'bg-green-100 text-green-800',
  paused: 'bg-amber-100 text-amber-800',
  off: 'bg-gray-200 text-gray-700',
  no_machine: 'bg-gray-100 text-gray-500',
}
const statusChips = [
  { key: 'all', name: t('All') },
  { key: 'selling', name: t('Selling') },
  { key: 'paused', name: t('Paused') },
  { key: 'off', name: t('Switched off') },
  { key: 'no_machine', name: t('No machine') },
]
const moveReasons = ['moved', 'replaced', 'template', 'correction']
const pausePresets = [
  { name: t('1 hour'), hours: 1 },
  { name: t('4 hours'), hours: 4 },
  { name: t('24 hours'), hours: 24 },
]
const eventNames = {
  created: t('Store added'),
  bound: t('Put on machine :code'),
  unbound: t('Taken off machine :code'),
  paused: t('Paused'),
  resumed: t('Resumed'),
  switched_off: t('Switched off'),
  switched_on: t('Switched on'),
  renamed: t('Merchant ID changed'),
  edited: t('Store details changed'),
  menu_source: t('Menu changed'),
  price_rule: t('Price rule changed'),
  grab_change_requested: t('Asked Grab to update'),
  grab_change_done: t('Grab update done'),
}
const menuReasons = {
  no_rp: t('No RP price (Grab Menu & Prices)'),
  no_custom_price: t('No Grab price (Grab Menu & Prices)'),
  no_sub_category: t('No Grab sub-category (Grab Menu & Prices)'),
  store_paused: t('Store paused'),
  item_off: t('Switched off in the Grab template'),
  slot_fault: t('Slot fault on the machine'),
  no_capacity: t('Slot has no capacity set'),
  sold_out: t('Sold out (after units on open orders)'),
  next_sync: t('Back on Grab at the next stock sync'),
}

// The URL carries operator ids; the filter's tags need the option objects.
const operatorChoices = [{ id: 'all', full_name: t('All') }, ...(page.props.operatorFilterOptions ?? [])]

const filters = ref({
  operators: [].concat(props.filters.operators ?? [])
    .map(id => operatorChoices.find(option => option.id == id))
    .filter(Boolean),
  search: props.filters.search ?? '',
  codes: props.filters.codes ?? '',
  status: props.filters.status ?? 'all',
  numberPerPage: props.filters.numberPerPage ?? 100,
  sort: props.filters.sort ?? null,
})

// Grab sales of the store's machine now: delivered orders less Grab promo.
const salesColumns = [
  { key: 'sales_7d', days: 7, name: t('Grab sales, last 7 days') },
  { key: 'sales_30d', days: 30, name: t('Grab sales, last 30 days') },
]

function sortBy(key) {
  filters.value.sort = filters.value.sort === key ? null : key
  search()
}

// Whole days the machine has been on this store, so a new one is not read as a slow one.
function daysOnMachine(store) {
  if (!store.binding?.since) {
    return Infinity
  }
  return Math.max(0, moment().diff(moment(store.binding.since), 'days'))
}

function search() {
  router.get('/grab-stores', {
    operators: filters.value.operators.map(operator => operator.id),
    search: filters.value.search,
    codes: filters.value.codes,
    status: filters.value.status,
    numberPerPage: filters.value.numberPerPage,
    sort: filters.value.sort,
  }, { preserveState: true, replace: true })
}

function setStatus(key) {
  filters.value.status = key
  search()
}

const operatorOptions = computed(() => page.props.operatorFilterOptions ?? [])

function templatesFor(operatorId, alsoId = null) {
  return (props.templateOptions ?? []).filter(template => template.operator_id === operatorId || template.id === alsoId)
}

function formatDateTime(value) {
  return value ? moment(value).format('DD MMM YY HH:mm') : '—'
}

function money(cents) {
  return '$' + ((cents ?? 0) / 100).toFixed(2)
}

// Modals: one at a time, on the row they were opened from.
const modal = ref(null)
const current = ref(null)

function closeModal() {
  modal.value = null
}

const addForm = useForm({
  operator_id: null, ref_number: '', remarks: '', vend_code: '', delivery_product_mapping_id: null, skip_grab_check: false,
})
const moveForm = useForm({ vend_code: '', delivery_product_mapping_id: null, reason: 'moved', remarks: '' })
const pauseForm = useForm({ reason: '', until: '' })
const unbindForm = useForm({ reason: 'removed', remarks: '' })
const editForm = useForm({ operator_id: null, ref_number: '', remarks: '', skip_grab_check: false })
const merchantIdChanged = computed(() => (editForm.ref_number ?? '').trim().toUpperCase() !== (current.value?.ref_number ?? ''))

const ok = { preserveScroll: true, onSuccess: () => closeModal() }

// Update on Grab (HIPL stores): what to change, prepared for the GrabMerchant app or Grab's Help Centre form.
const changeKindNames = {
  address: t('Address (machine moved to another site)'),
  name: t('Store name'),
  hours: t('Opening hours or phone'),
  other: t('Something else'),
}
const change = ref({ loading: false, draft: null, copied: false })
const changeForm = useForm({ kind: 'address', route: 'app', address: '', postal_code: '', go_live_on: '', details: '' })
// Typed into Grab's form, so it stays in English whatever the screen language.
const changeText = computed(() => {
  const d = change.value.draft ?? {}
  const lines = [
    'Request to update store information',
    `Store ID: ${d.merchant_id ?? current.value?.ref_number ?? ''}`,
    `Store: ${d.site ?? ''} (vending machine ${d.vend_code ?? ''})`,
  ]
  if (changeForm.kind === 'address') {
    lines.push('Change: new store address (the machine has moved)')
    // A Site's saved address often ends "SINGAPORE 528882" already: add the postal code only when it is missing.
    const postal = (changeForm.postal_code ?? '').trim()
    const withPostal = postal && !changeForm.address.includes(postal) ? `${changeForm.address}, Singapore ${postal}` : changeForm.address
    lines.push(`New address: ${withPostal}`)
    if (changeForm.go_live_on) lines.push(`Please make it live on: ${changeForm.go_live_on}`)
  } else {
    lines.push(`Change: ${{ name: 'store name', hours: 'opening hours / phone', other: 'store information' }[changeForm.kind]}`)
    lines.push(changeForm.details)
  }
  lines.push('Contact: grabcollection.sg@happyice.com.sg')
  return lines.join('\n')
})
async function openChange(store) {
  current.value = store
  changeForm.reset()
  changeForm.clearErrors()
  change.value = { loading: true, draft: null, copied: false }
  modal.value = 'change'
  try {
    const { data } = await axios.get(`/grab-stores/${store.id}/change-request`)
    change.value.draft = data
    changeForm.address = data.address ?? ''
    changeForm.postal_code = data.postal_code ?? ''
    changeForm.go_live_on = data.go_live_on ?? ''
  } finally {
    change.value.loading = false
  }
}
async function copyChangeText() {
  try {
    await navigator.clipboard.writeText(changeText.value)
    change.value.copied = true
  } catch (e) {
    change.value.copied = false
  }
}
function submitChange(route) {
  changeForm.route = route
  changeForm.post(`/grab-stores/${current.value.id}/change-request`, ok)
}
function markChangeDone(store) {
  if (!window.confirm(t('Does Grab now show the change for :ref? This closes the request.', { ref: store.ref_number }))) {
    return
  }
  router.post(`/grab-stores/${store.id}/change-request/done`, {}, { preserveScroll: true })
}

function openAdd() {
  addForm.reset()
  addForm.clearErrors()
  addForm.operator_id = page.props.auth?.user?.operator_id ?? operatorOptions.value[0]?.id ?? null
  addForm.delivery_product_mapping_id = templatesFor(addForm.operator_id)[0]?.id ?? null
  modal.value = 'add'
}

function submitAdd() {
  addForm.post('/grab-stores', ok)
}

function openEdit(store) {
  current.value = store
  editForm.clearErrors()
  editForm.operator_id = store.operator_id
  editForm.ref_number = store.ref_number
  editForm.remarks = store.remarks ?? ''
  editForm.skip_grab_check = false
  modal.value = 'edit'
}

function submitEdit() {
  editForm.post(`/grab-stores/${current.value.id}/update`, ok)
}

// On Grab now
const menu = ref({ loading: false, failed: false, data: null })

async function openMenu(store) {
  current.value = store
  menu.value = { loading: true, failed: false, data: null }
  modal.value = 'menu'
  try {
    const { data } = await axios.get(`/grab-stores/${store.id}/menu`)
    menu.value = { loading: false, failed: false, data }
  } catch (e) {
    menu.value = { loading: false, failed: true, data: null }
  }
}

function openBind(store) {
  current.value = store
  moveForm.reset()
  moveForm.clearErrors()
  moveForm.delivery_product_mapping_id = templatesFor(store.operator_id)[0]?.id ?? null
  modal.value = 'bind'
}

function openMove(store) {
  current.value = store
  moveForm.reset()
  moveForm.clearErrors()
  moveForm.delivery_product_mapping_id = store.binding.template_id
  modal.value = 'move'
}

function submitMove() {
  if (modal.value === 'move') {
    moveForm.post(`/grab-stores/bindings/${current.value.binding.id}/move`, ok)
  } else {
    moveForm.post(`/grab-stores/${current.value.id}/bind`, ok)
  }
}

function openPause(store) {
  current.value = store
  pauseForm.reset()
  pauseForm.clearErrors()
  modal.value = 'pause'
}

function setPauseFor(hours) {
  pauseForm.until = moment().add(hours, 'hours').format('YYYY-MM-DDTHH:mm')
}

function submitPause() {
  pauseForm.post(`/grab-stores/bindings/${current.value.binding.id}/pause`, ok)
}

function resume(store) {
  router.post(`/grab-stores/bindings/${store.binding.id}/resume`, {}, { preserveScroll: true })
}

function openUnbind(store) {
  current.value = store
  unbindForm.reset()
  unbindForm.clearErrors()
  modal.value = 'unbind'
}

function submitUnbind() {
  unbindForm.post(`/grab-stores/bindings/${current.value.binding.id}/unbind`, ok)
}

function closeLeftover(store, other) {
  if (!window.confirm(t('Take the store off machine :code too? It keeps selling from machine :live, and its promotions stay.', { code: other.vend_code, live: store.binding?.vend_code }))) {
    return
  }
  // Stored data: always English, never the viewer's language (or edit-mode markers).
  router.post(`/grab-stores/bindings/${other.id}/unbind`, { reason: 'correction', remarks: 'Leftover open binding closed' }, { preserveScroll: true })
}

function toggleOff(store) {
  const question = store.is_off
    ? t('Switch :ref on? It reopens on Grab if it is on a machine.', { ref: store.ref_number })
    : t('Switch :ref off? It is paused on Grab at once and stops selling.', { ref: store.ref_number })
  if (!window.confirm(question)) {
    return
  }
  router.post(`/grab-stores/${store.id}/status`, { active: store.is_off }, { preserveScroll: true })
}

// History
const history = ref({ loading: false, data: null })
const grabCheck = ref({ loading: false, result: null })

async function openHistory(store) {
  current.value = store
  grabCheck.value = { loading: false, result: null }
  history.value = { loading: true, data: null }
  modal.value = 'history'
  try {
    const { data } = await axios.get(`/grab-stores/${store.id}/history`)
    history.value = { loading: false, data }
  } catch (e) {
    history.value = { loading: false, data: null }
  }
}

async function askGrab() {
  grabCheck.value = { loading: true, result: null }
  try {
    const { data } = await axios.post(`/grab-stores/${current.value.id}/check-grab`)
    grabCheck.value = { loading: false, result: data }
  } catch (e) {
    grabCheck.value = { loading: false, result: { ok: false, message: t('Could not ask Grab.') } }
  }
}

function eventText(event) {
  const name = eventNames[event.event] ?? event.event
  const code = event.vend_code ?? event.data?.vend_code ?? ''
  if (event.event === 'edited') {
    const parts = []
    if (event.data?.operator_id) {
      parts.push(t('operator changed'))
    }
    if (event.data?.remarks) {
      parts.push(t('remarks: :text', { text: event.data.remarks.to ?? '—' }))
    }
    return parts.length ? `${name}: ${parts.join(', ')}` : name
  }
  if (event.event === 'menu_source') {
    return `${name}: ${event.data?.machine_menu ? t('lists its machine') : t('back on the old Grab menu')}`
  }
  if (event.event === 'grab_change_requested') {
    const kind = changeKindNames[event.data?.kind] ?? event.data?.kind ?? ''
    const route = event.data?.route === 'form' ? t('Help Centre form') : t('GrabMerchant app')
    const what = event.data?.address ?? event.data?.details ?? ''
    return `${name}: ${kind} · ${route}${what ? ' · ' + what : ''}${event.data?.go_live_on ? ' · ' + t('goes live :date', { date: event.data.go_live_on }) : ''}`
  }
  if (event.event === 'price_rule') {
    if (!event.data?.own) {
      return `${name}: ${t('back on the company rule')}`
    }
    const note = event.data?.note ? ` (“${event.data.note}”)` : ''
    return `${name}: ${t('own rule')}${note}`
  }
  if (event.event === 'renamed') {
    return `${name}: ${event.data?.from ?? ''} → ${event.data?.to ?? ''}`
  }
  if (event.event === 'paused' && event.data?.until) {
    return `${name} ${t('until :time', { time: formatDateTime(event.data.until) })}`
  }
  return name.replace(':code', code)
}

function grabNote(event) {
  const grab = event.data?.grab
  if (!grab) {
    return null
  }
  if (typeof grab === 'string') {
    return t('Grab: :result', { result: grab })
  }
  return Object.entries(grab).map(([code, result]) => `${code}: ${result}`).join(', ')
}
</script>
