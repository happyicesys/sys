<template>
  <Head :title="$t('Grab Promotions')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Grab Promotions') }}
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div v-if="flashSuccess" class="mb-3 rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">
        {{ flashSuccess }}
      </div>
      <div v-if="flashError" class="mb-3 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800">
        {{ flashError }}
      </div>

      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3">
        <div class="flex flex-wrap items-start gap-3">
          <div class="flex-1 min-w-[16rem]">
            <p class="text-sm text-gray-600">
              {{ $t('What Grab runs on each store, read from Grab every 10 minutes and after every change made here. Grab is the only source: a new promotion, a change or an end is sent to Grab store by store, and the list shows what Grab answers.') }}
            </p>
            <p class="text-sm text-gray-600 mt-1">
              <span v-if="reads.last_read_at">{{ $t('Last read :time, :count stores.', { time: formatDateTime(reads.last_read_at), count: reads.stores }) }}</span>
              <span v-else>{{ $t('Not read from Grab yet.') }}</span>
            </p>
          </div>
          <button v-if="can.create" type="button" :class="primaryClass" class="inline-flex items-center gap-1" @click="openCreate">
            <PlusIcon class="h-4 w-4" aria-hidden="true" />
            {{ $t('New promotion') }}
          </button>
        </div>

        <!-- What ConnectVend asked Grab, and Grab's answer. -->
        <div v-if="sends.length" class="mt-3 rounded-md border border-gray-200">
          <div class="px-3 py-2 text-sm font-semibold text-gray-800 border-b bg-gray-50">{{ $t('Sent to Grab in the last 24 hours') }}</div>
          <ul class="divide-y">
            <li v-for="send in sends" :key="send.batch" class="px-3 py-2 text-sm">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">{{ actionName[send.action] }}</span>
                <span v-if="send.name" class="text-gray-700">{{ send.name }}</span>
                <span class="text-gray-500">{{ $t(':user, :time', { user: send.user ?? $t('System'), time: formatDateTime(send.created_at) }) }}</span>
                <span v-if="send.open" class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-blue-100 text-blue-800">
                  {{ $t('Waiting for Grab: :open of :total', { open: send.open, total: send.total }) }}
                </span>
                <span v-if="send.ok" class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800">
                  {{ $t('Grab accepted :ok of :total', { ok: send.ok, total: send.total }) }}
                </span>
                <span v-if="send.failed.length" class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-800">
                  {{ $t('Grab refused :count', { count: send.failed.length }) }}
                </span>
              </div>
              <ul v-if="send.failed.length" class="mt-1 space-y-0.5">
                <li v-for="failed in send.failed" :key="failed.merchant_id" class="text-xs text-red-800 flex flex-wrap gap-2">
                  <span class="font-mono">{{ failed.merchant_id }}</span>
                  <MachineIdLink v-if="failed.vend_code" :code="failed.vend_code" />
                  <span>{{ failed.message }}</span>
                </li>
              </ul>
            </li>
          </ul>
        </div>

        <div v-if="reads.failing.length" class="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2">
          <div class="text-sm font-semibold text-amber-900">
            {{ $tc(':count store could not be read on its last try; its promotions below may be out of date.|:count stores could not be read on their last try; their promotions below may be out of date.', reads.failing.length, { count: reads.failing.length }) }}
          </div>
          <ul class="mt-1 space-y-1">
            <li v-for="read in reads.failing" :key="read.merchant_id" class="text-sm text-amber-900 flex flex-wrap items-center gap-2">
              <span class="font-mono">{{ read.merchant_id }}</span>
              <MachineIdLink v-if="read.vend_code" :code="read.vend_code" />
              <span>{{ read.status ? 'HTTP ' + read.status : $t('No answer') }}</span>
              <span v-if="read.message" class="text-amber-800">{{ read.message }}</span>
              <span v-if="read.last_ok_at" class="text-amber-800">{{ $t('last good read :time', { time: formatDateTime(read.last_ok_at) }) }}</span>
              <button type="button" class="text-indigo-700 underline" @click="refresh(read.ref_id)">{{ $t('Read again') }}</button>
            </li>
          </ul>
        </div>

        <div class="flex flex-wrap gap-2 mt-3">
          <button
            v-for="chip in viewChips"
            :key="chip.key"
            type="button"
            class="rounded-full border px-3 py-1 text-sm"
            :class="filters.view === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
            @click="filters.view = chip.key; filters.source = 'all'; filters.state = 'all'; search()"
          >{{ chip.name }}</button>
          <span class="border-l mx-1"></span>
          <button
            v-for="chip in stateChips"
            :key="chip.key"
            type="button"
            class="rounded-full border px-3 py-1 text-sm"
            :class="filters.state === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
            @click="filters.state = chip.key; search()"
          >{{ chip.name }} <span class="text-gray-500">{{ totals[chip.key] ?? 0 }}</span></button>
        </div>
        <div class="flex flex-wrap gap-2 mt-2">
          <button
            v-for="chip in sourceChips"
            :key="chip.key"
            type="button"
            class="rounded-full border px-3 py-1 text-sm"
            :class="[
              filters.source === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50',
              chip.key === 'leftover' && totals.leftover ? 'font-semibold text-red-700' : '',
            ]"
            @click="filters.source = chip.key; search()"
          >{{ chip.name }} <span class="text-gray-500">{{ chip.key === 'all' ? totals.all : (totals[chip.key] ?? 0) }}</span></button>
        </div>

        <div class="mt-3 flex flex-wrap items-end gap-2">
          <div class="w-full md:w-80">
            <SearchInput :placeholderStr="$t('Store, machine or promotion name')" v-model="filters.search" @keyup.enter="search()">
              {{ $t('Search') }}
            </SearchInput>
          </div>
          <Button
            class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-2 text-sm font-medium text-white hover:bg-green-600"
            @click="search()"
          >
            <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
            <span>{{ $t('Search') }}</span>
          </Button>
          <p class="ml-auto text-sm text-gray-700">
            {{ $t('Showing :from to :to of :total results', { from: promotions.meta.from ?? 0, to: promotions.meta.to ?? 0, total: promotions.meta.total }) }}
          </p>
        </div>
      </div>

      <div class="mt-4 -mx-3 sm:-mx-6 lg:-mx-8 shadow-sm ring-1 ring-black ring-opacity-5 overflow-x-auto bg-white">
        <table class="min-w-full border-separate" style="border-spacing: 0">
          <thead class="bg-gray-100">
            <tr class="divide-x divide-gray-200">
              <TableHead>{{ $t('Promotion') }}</TableHead>
              <TableHead>{{ $t('On') }}</TableHead>
              <TableHead>{{ $t('When') }}</TableHead>
              <TableHead>{{ $t('Grab stores') }}</TableHead>
              <TableHead>{{ $t('Status') }}</TableHead>
              <TableHead>{{ $t('Made by') }}</TableHead>
              <TableHead></TableHead>
            </tr>
          </thead>
          <tbody>
            <template v-for="(promotion, index) in rows" :key="promotion.key">
              <tr class="divide-x divide-gray-200 align-top cursor-pointer hover:bg-indigo-50" :class="index % 2 ? 'bg-gray-50' : 'bg-white'" @click="toggle(promotion.key)">
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                  <div class="font-medium">{{ deal(promotion) }}</div>
                  <div v-if="promotion.name" class="text-xs text-gray-500">{{ promotion.name }}</div>
                  <div v-if="limits(promotion)" class="text-xs text-gray-500">{{ limits(promotion) }}</div>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left text-sm">
                  <template v-if="promotion.discount?.scope?.type === 'order'">{{ $t('Whole order') }}</template>
                  <template v-else>
                    <div v-for="object in promotion.objects.slice(0, 3)" :key="object.id">{{ object.name || object.id }}</div>
                    <div v-if="promotion.objects.length > 3" class="text-xs text-gray-500">{{ $t('+ :count more', { count: promotion.objects.length - 3 }) }}</div>
                  </template>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left whitespace-nowrap text-sm">
                  <div>{{ formatDateTime(promotion.starts_at) }}</div>
                  <div class="text-gray-500">{{ $t('to :time', { time: formatDateTime(promotion.ends_at) }) }}</div>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left text-sm">
                  <template v-if="promotion.stores.length === 1">
                    <div class="font-mono whitespace-nowrap">{{ promotion.stores[0].merchant_id }}</div>
                    <MachineIdLink v-if="promotion.stores[0].vend_code" :code="promotion.stores[0].vend_code" />
                  </template>
                  <template v-else>
                    <div class="font-medium">{{ $tc(':count store|:count stores', promotion.stores.length, { count: promotion.stores.length }) }}</div>
                    <div class="text-xs text-gray-500">{{ storeMachines(promotion) }}</div>
                  </template>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                  <span v-if="promotion.gone_at" class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700">
                    {{ $t('Gone from Grab :time', { time: formatDateTime(promotion.gone_at) }) }}
                  </span>
                  <span v-else-if="promotion.state === 'ongoing'" class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800">{{ $t('Live now') }}</span>
                  <span v-else class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-blue-100 text-blue-800">{{ $t('Upcoming') }}</span>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                  <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="sourceClass[promotion.source]">
                    {{ sourceName[promotion.source] }}
                  </span>
                  <div v-if="promotion.made_by_user" class="text-xs text-gray-500 mt-1">{{ promotion.made_by_user }}</div>
                  <div v-if="promotion.source === 'leftover'" class="text-xs text-red-700 mt-1">{{ leftoverReason(promotion) }}</div>
                  <div v-else-if="!promotion.made_by_user && promotion.created_by" class="text-xs text-gray-500 mt-1">{{ $t('Grab says: :who', { who: promotion.created_by }) }}</div>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left whitespace-nowrap">
                  <div v-if="!promotion.gone_at" class="flex flex-col gap-1" @click.stop>
                    <button v-if="canEdit(promotion)" type="button" :class="actionClass" @click="openEdit(promotion)">{{ $t('Edit') }}</button>
                    <button v-if="can.delete" type="button" :class="dangerClass" @click="openEnd(promotion, promotion.stores)">{{ $t('End') }}</button>
                  </div>
                </TableData>
              </tr>
              <tr v-if="open[promotion.key]" class="bg-white">
                <td colspan="7" class="px-3 py-2 border-b border-gray-200">
                  <table class="text-sm mb-2">
                    <thead>
                      <tr class="text-left text-xs text-gray-500">
                        <th class="pr-4 py-1 font-medium">{{ $t('Grab store') }}</th>
                        <th class="pr-4 py-1 font-medium">{{ $t('Machine') }}</th>
                        <th class="pr-4 py-1 font-medium">{{ $t('Status') }}</th>
                        <th class="pr-4 py-1 font-medium">{{ $t('Grab campaign ID') }}</th>
                        <th class="pr-4 py-1 font-medium">{{ $t('Last seen on Grab') }}</th>
                        <th class="py-1"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="store in promotion.stores" :key="store.id" class="border-t">
                        <td class="pr-4 py-1 font-mono">{{ store.merchant_id }} <span v-if="store.operator_code" class="text-xs text-gray-500 font-sans">{{ store.operator_code }}</span></td>
                        <td class="pr-4 py-1"><MachineIdLink v-if="store.vend_code" :code="store.vend_code" /><span v-else class="text-xs text-gray-500">{{ $t('No machine') }}</span></td>
                        <td class="pr-4 py-1 text-xs">{{ store.gone_at ? $t('Gone :time', { time: formatDateTime(store.gone_at) }) : (store.state === 'ongoing' ? $t('Live now') : $t('Upcoming')) }}</td>
                        <td class="pr-4 py-1 font-mono text-xs">{{ store.grab_campaign_id }}</td>
                        <td class="pr-4 py-1 text-xs">{{ formatDateTime(store.last_seen_at) }}</td>
                        <td class="py-1 whitespace-nowrap">
                          <button v-if="store.ref_id" type="button" class="text-xs text-indigo-700 underline mr-3" @click="refresh(store.ref_id)">{{ $t('Read again') }}</button>
                          <button v-if="can.delete && !store.gone_at && promotion.stores.length > 1" type="button" class="text-xs text-red-700 underline" @click="openEnd(promotion, [store])">{{ $t('End on this store') }}</button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <div v-if="promotion.objects.length > 3" class="text-xs text-gray-700 mb-2">
                    <span v-for="object in promotion.objects" :key="object.id" class="inline-block mr-3">{{ object.name || object.id }}</span>
                  </div>
                  <details class="text-xs">
                    <summary class="cursor-pointer text-gray-600">{{ $t('As Grab lists it') }}</summary>
                    <pre class="bg-gray-50 border rounded p-2 overflow-x-auto max-h-64 mt-1">{{ JSON.stringify(promotion.campaign_json, null, 2) }}</pre>
                  </details>
                </td>
              </tr>
            </template>
            <tr v-if="!rows.length">
              <td colspan="7" class="py-6 text-center text-sm text-gray-500">
                {{ filters.view === 'ended' ? $t('No promotion has left Grab in the last :days days.', { days: keepGoneDays }) : $t('Grab lists no live or upcoming promotion on these stores.') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Paginator v-if="rows.length" :links="promotions.links" :meta="promotions.meta" class="mt-3" />
    </div>

    <!-- New promotion / Edit: sent to Grab store by store. -->
    <Modal :open="modal === 'form'" @modalClose="closeModal">
      <template #header>{{ editing ? $t('Edit promotion on Grab') : $t('New Grab promotion') }}</template>
      <div class="space-y-3 text-sm">
        <div v-if="!editing">
          <label class="block font-medium text-gray-700">{{ $t('Grab menu') }}</label>
          <select v-model="form.delivery_product_mapping_id" :class="inputClass" @change="loadMenu(form.delivery_product_mapping_id, true)">
            <option :value="null" disabled>{{ $t('Pick the menu the stores sell') }}</option>
            <option v-for="option in menus" :key="option.id" :value="option.id">{{ $tc(':name (:count store)|:name (:count stores)', option.stores, { name: option.name, count: option.stores }) }} · #{{ option.id }}</option>
          </select>
          <InputError :message="form.errors.delivery_product_mapping_id" />
        </div>
        <p v-else class="text-gray-600">
          {{ $tc('Saved on Grab for :count store this promotion runs on.|Saved on Grab for each of the :count stores this promotion runs on.', form.ids.length, { count: form.ids.length }) }}
          <span v-if="menu">{{ $t('Menu: :name', { name: menu.name }) }}</span>
        </p>
        <p v-if="menuLoading" class="text-gray-500">{{ $t('Loading…') }}</p>

        <template v-if="menu">
          <div v-if="!editing">
            <div class="flex items-center justify-between">
              <label class="block font-medium text-gray-700">{{ $t('Grab stores') }}</label>
              <span class="text-xs">
                <button type="button" class="text-indigo-700 underline mr-2" @click="form.ref_ids = menu.stores.filter(s => s.has_account).map(s => s.ref_id)">{{ $t('All') }}</button>
                <button type="button" class="text-indigo-700 underline" @click="form.ref_ids = []">{{ $t('None') }}</button>
              </span>
            </div>
            <div class="mt-1 max-h-40 overflow-y-auto border rounded-md p-2 grid grid-cols-1 sm:grid-cols-2 gap-1">
              <label v-for="store in menu.stores" :key="store.ref_id" class="flex items-center gap-2" :class="store.has_account ? '' : 'text-gray-400'">
                <input v-model="form.ref_ids" type="checkbox" :value="store.ref_id" :disabled="!store.has_account" class="rounded border-gray-300" />
                <span class="font-mono text-xs">{{ store.merchant_id }}</span>
                <span>{{ store.vend_code }}</span>
                <span v-if="!store.has_account" class="text-xs">{{ $t('no Grab account') }}</span>
              </label>
            </div>
            <InputError :message="form.errors.ref_ids" />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-medium text-gray-700">{{ $t('Promotion type') }}</label>
              <select v-model="form.type" :class="inputClass" @change="onTypeChange">
                <option v-for="option in campaignTypes" :key="option.id" :value="option.id">{{ typeName[option.id] ?? option.name }}</option>
              </select>
              <InputError :message="form.errors.type" />
            </div>
            <div>
              <label class="block font-medium text-gray-700">{{ $t('Applies to') }}</label>
              <select v-model="form.scope" :class="inputClass" @change="onScopeChange">
                <option v-for="option in currentType?.scope ?? []" :key="option.id" :value="option.id">{{ scopeName[option.id] ?? option.name }}</option>
              </select>
              <InputError :message="form.errors.scope" />
            </div>
          </div>

          <div v-if="currentScope && currentScope.id !== 'order'">
            <label class="block font-medium text-gray-700">
              {{ currentScope.id === 'category' ? $t('Category') : (currentScope.singleObjectOnly ? $t('Item (one)') : $t('Items')) }}
            </label>
            <div v-if="currentScope.id === 'category'" class="mt-1 max-h-40 overflow-y-auto border rounded-md p-2 space-y-1">
              <label v-for="category in menu.categories" :key="category.id" class="flex items-center gap-2">
                <input v-model="form.object_ids" type="checkbox" :value="category.id" class="rounded border-gray-300" />
                <span>{{ category.name }}</span>
              </label>
            </div>
            <div v-else class="mt-1 max-h-48 overflow-y-auto border rounded-md p-2 grid grid-cols-1 sm:grid-cols-2 gap-1">
              <label v-for="item in menu.items" :key="item.id" class="flex items-center gap-2">
                <input
                  v-if="currentScope.singleObjectOnly"
                  type="radio"
                  :checked="form.object_ids[0] === item.id"
                  class="border-gray-300"
                  @change="form.object_ids = [item.id]"
                />
                <input v-else v-model="form.object_ids" type="checkbox" :value="item.id" class="rounded border-gray-300" />
                <span><span class="mr-1 text-xs text-gray-500">{{ $t('Slot :slot', { slot: item.slot }) }}</span><span class="mr-1">{{ item.product_code }}</span><span class="text-gray-600">{{ item.product_name }}</span></span>
              </label>
            </div>
            <InputError :message="form.errors.object_ids" />
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div v-if="currentType?.qty">
              <label class="block font-medium text-gray-700">{{ $t('Items in bundle') }}</label>
              <input v-model="form.qty" type="number" min="2" step="1" :class="inputClass" />
              <InputError :message="form.errors.qty" />
            </div>
            <div v-if="currentType?.promo_value">
              <label class="block font-medium text-gray-700">{{ valueLabel }}</label>
              <input v-model="form.value" type="number" min="0" :step="isPercent ? 1 : 0.01" :class="inputClass" />
              <InputError :message="form.errors.value" />
            </div>
            <div v-if="currentType?.cap">
              <label class="block font-medium text-gray-700">{{ $t('Up to ($)') }}</label>
              <input v-model="form.cap" type="number" min="0" step="0.01" :class="inputClass" />
              <InputError :message="form.errors.cap" />
            </div>
            <div v-if="currentScope?.minBasketAmount">
              <label class="block font-medium text-gray-700">{{ $t('Min. spend ($)') }}</label>
              <input v-model="form.min_basket" type="number" min="0" step="0.01" :class="inputClass" />
              <InputError :message="form.errors.min_basket" />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block font-medium text-gray-700">{{ $t('For') }}</label>
              <select v-model="form.eater_type" :class="inputClass">
                <option v-for="option in currentScope?.eaterType ?? []" :key="option.id" :value="option.id">{{ eaterName[option.id] ?? option.name }}</option>
              </select>
              <InputError :message="form.errors.eater_type" />
            </div>
            <div>
              <label class="block font-medium text-gray-700">{{ $t('Uses in all (blank = no limit)') }}</label>
              <input v-model="form.total_count" type="number" min="0" step="1" :class="inputClass" />
              <InputError :message="form.errors.total_count" />
            </div>
            <div>
              <label class="block font-medium text-gray-700">{{ $t('Uses per customer (blank = no limit)') }}</label>
              <input v-model="form.total_count_per_user" type="number" min="0" step="1" :class="inputClass" />
              <InputError :message="form.errors.total_count_per_user" />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-medium text-gray-700">{{ $t('Starts') }}</label>
              <input v-model="form.starts_at" type="datetime-local" :class="inputClass" />
              <InputError :message="form.errors.starts_at" />
            </div>
            <div>
              <label class="block font-medium text-gray-700">{{ $t('Ends') }}</label>
              <input v-model="form.ends_at" type="datetime-local" :class="inputClass" />
              <InputError :message="form.errors.ends_at" />
            </div>
          </div>

          <div>
            <label class="block font-medium text-gray-700">{{ $t('Name on Grab') }}</label>
            <input v-model="form.name" type="text" maxlength="255" :class="inputClass" @input="nameTouched = true" />
            <p class="text-xs text-gray-500 mt-1">{{ $t('Filled in from the deal; change it if you like.') }}</p>
            <InputError :message="form.errors.name" />
          </div>
          <InputError :message="form.errors.ids" />
        </template>

        <div class="flex justify-end gap-2 pt-2">
          <button
            type="button"
            :class="primaryClass"
            :disabled="form.processing || !menu || (!editing && !form.ref_ids.length)"
            @click="submitForm"
          >
            {{ editing ? $tc('Save on :count Grab store|Save on :count Grab stores', form.ids.length, { count: form.ids.length }) : $tc('Send to :count Grab store|Send to :count Grab stores', form.ref_ids.length, { count: form.ref_ids.length }) }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- End on Grab -->
    <Modal :open="modal === 'end'" @modalClose="closeModal">
      <template #header>{{ $t('End promotion on Grab') }}</template>
      <div class="space-y-3 text-sm">
        <p class="text-gray-700">
          {{ $tc('Grab is asked to end ":name" on :count store. It disappears from the list once Grab confirms.|Grab is asked to end ":name" on :count stores. It disappears from the list once Grab confirms.', endForm.ids.length, { name: current ? deal(current) : '', count: endForm.ids.length }) }}
        </p>
        <p v-if="current && current.source !== 'connectvend' && current.source !== 'leftover'" class="text-amber-800">
          {{ $t('This promotion was not made in ConnectVend; Grab may refuse to end it here. If it does, end it where it was made.') }}
        </p>
        <InputError :message="endForm.errors.ids" />
        <div class="flex justify-end">
          <button type="button" :class="dangerButtonClass" :disabled="endForm.processing" @click="submitEnd">{{ $t('End on Grab') }}</button>
        </div>
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
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import { MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/20/solid';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import moment from 'moment';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
  promotions: Object,
  totals: Object,
  reads: Object,
  sends: Array,
  menus: Array,
  campaignTypes: Array,
  filters: Object,
  keepGoneDays: Number,
  can: Object,
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)
const flashError = computed(() => page.props.flash?.error)
const rows = computed(() => props.promotions.data ?? [])
const filters = ref({ ...props.filters })
const open = ref({})

const inputClass = 'mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md'
const actionClass = 'rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-50'
const dangerClass = 'rounded border border-red-300 bg-white px-2 py-1 text-xs text-red-700 hover:bg-red-50'
const primaryClass = 'rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50'
const dangerButtonClass = 'rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50'

const viewChips = [
  { key: 'live', name: t('On Grab now') },
  { key: 'ended', name: t('Gone from Grab') },
]
const stateChips = [
  { key: 'all', name: t('All') },
  { key: 'ongoing', name: t('Live now') },
  { key: 'upcoming', name: t('Upcoming') },
]
const sourceName = {
  connectvend: t('Made in ConnectVend'),
  leftover: t('Leftover'),
  merchant_app: t('Grab merchant app'),
  grab_team: t('Grab team'),
  unknown: t('Not made in ConnectVend'),
}
const sourceClass = {
  connectvend: 'bg-green-100 text-green-800',
  leftover: 'bg-red-100 text-red-800',
  merchant_app: 'bg-blue-100 text-blue-800',
  grab_team: 'bg-purple-100 text-purple-800',
  unknown: 'bg-amber-100 text-amber-800',
}
const sourceChips = [
  { key: 'all', name: t('Anyone') },
  ...['connectvend', 'leftover', 'merchant_app', 'grab_team', 'unknown'].map(key => ({ key, name: sourceName[key] })),
]
const actionName = {
  create: t('New promotion'),
  update: t('Change'),
  end: t('End'),
}
const typeName = {
  net: t('Dollar off'),
  percentage: t('Percent off'),
  bundleSameFixPrice: t('Buy several of one item for a fixed price'),
  bundleDiffFixPrice: t('Any mix of items for a fixed price'),
  bundleSameNet: t('Buy several of one item, dollar off'),
  bundleDiffNet: t('Any mix of items, dollar off'),
  bundleSamePercentage: t('Buy several of one item, percent off'),
  bundleDiffPercentage: t('Any mix of items, percent off'),
  delivery: t('Delivery fee off'),
  freeItem: t('Free item'),
}
const scopeName = {
  items: t('Items'),
  category: t('Category'),
  order: t('Whole order'),
}
const eaterName = {
  all: t('All customers'),
  new: t('New customers only'),
}

function search() {
  router.get('/grab-promotions', filters.value, { preserveState: true, replace: true })
}

function refresh(refId) {
  router.post('/grab-promotions/stores/' + refId + '/refresh', {}, { preserveScroll: true })
}

function toggle(key) {
  open.value = { ...open.value, [key]: !open.value[key] }
}

function formatDateTime(value) {
  return value ? moment(value).format('DD MMM YY HH:mm') : '—'
}

function storeMachines(promotion) {
  const codes = promotion.stores.map(store => store.vend_code).filter(Boolean)
  return codes.slice(0, 6).join(', ') + (codes.length > 6 ? ' …' : '')
}

// Grab sends amounts in dollars; shown as sent.
function money(value) {
  return '$' + Number(value ?? 0).toFixed(2)
}

function deal(promotion) {
  return dealText(promotion.discount ?? {}, promotion.conditions?.bundleQuantity)
}

function dealText(discount, qty) {
  const value = discount.value
  const cap = discount.cap ? ' ' + t('(up to :cap)', { cap: money(discount.cap) }) : ''
  switch (discount.type) {
    case 'net': return t(':amount off', { amount: money(value) })
    case 'percentage': return t(':pct% off', { pct: value }) + cap
    case 'fixPrice': return t('Now :price', { price: money(value) })
    case 'bundleSameFixPrice': return t('Buy :qty for :price', { qty, price: money(value) })
    case 'bundleDiffFixPrice': return t('Any :qty for :price', { qty, price: money(value) })
    case 'bundleSameNet': return t('Buy :qty, :amount off', { qty, amount: money(value) })
    case 'bundleDiffNet': return t('Any :qty, :amount off', { qty, amount: money(value) })
    case 'bundleSamePercentage': return t('Buy :qty, :pct% off', { qty, pct: value })
    case 'bundleDiffPercentage': return t('Any :qty, :pct% off', { qty, pct: value })
    case 'delivery': return t(':amount off delivery', { amount: money(value) })
    case 'freeItem': return t('Free item')
    default: return discount.type ?? '—'
  }
}

function limits(promotion) {
  const parts = []
  if (promotion.conditions?.minBasketAmount) {
    parts.push(t('min. spend :amount', { amount: money(promotion.conditions.minBasketAmount) }))
  }
  if (promotion.conditions?.eaterType && promotion.conditions.eaterType !== 'all') {
    parts.push(t('customers: :who', { who: eaterName[promotion.conditions.eaterType] ?? promotion.conditions.eaterType }))
  }
  if (promotion.quotas?.totalCount) {
    parts.push(t(':count uses in all', { count: promotion.quotas.totalCount }))
  }
  if (promotion.quotas?.totalCountPerUser) {
    parts.push(t(':count per customer', { count: promotion.quotas.totalCountPerUser }))
  }
  return parts.join(' · ')
}

function leftoverReason(promotion) {
  if (promotion.leftover_binding_closed) {
    return t('The old campaign pages ended it when the store left machine :code; Grab still runs it', { code: promotion.leftover_vend_code ?? '?' })
  }
  return t('The old campaign pages show it ended; Grab still runs it')
}

function canEdit(promotion) {
  return props.can.update && !promotion.gone_at && promotion.mapping_id
    && !['merchant_app', 'grab_team'].includes(promotion.source)
}

// Grab's answers arrive within seconds; the page follows them while any send is open.
let poll = null
function followSends() {
  const waiting = (props.sends ?? []).some(send => send.open > 0)
  if (waiting && !poll) {
    poll = setInterval(() => router.reload({ only: ['sends', 'promotions', 'totals', 'reads'] }), 3000)
  } else if (!waiting && poll) {
    clearInterval(poll)
    poll = null
  }
}
watch(() => props.sends, followSends, { immediate: true, deep: true })
onBeforeUnmount(() => poll && clearInterval(poll))

// Modals
const modal = ref(null)
const current = ref(null)
const editing = ref(false)
const menu = ref(null)
const menuLoading = ref(false)
const nameTouched = ref(false)

function closeModal() {
  modal.value = null
}

const form = useForm({
  delivery_product_mapping_id: null,
  ref_ids: [],
  ids: [],
  type: 'bundleDiffFixPrice',
  scope: 'items',
  eater_type: 'all',
  object_ids: [],
  qty: 2,
  value: '',
  cap: '',
  min_basket: '',
  total_count: '',
  total_count_per_user: '',
  starts_at: '',
  ends_at: '',
  name: '',
})
const endForm = useForm({ ids: [] })

const currentType = computed(() => props.campaignTypes.find(option => option.id === form.type))
const currentScope = computed(() => currentType.value?.scope.find(option => option.id === form.scope))
const isPercent = computed(() => (form.type ?? '').toLowerCase().includes('percentage'))
const valueLabel = computed(() => {
  if (isPercent.value) return t('Percent off')
  if ((form.type ?? '').endsWith('FixPrice')) return t('Bundle price ($)')
  return t('Amount off ($)')
})

// The name Grab shows the merchant: the deal in words until someone types one.
const suggestedName = computed(() => {
  const text = dealText({ type: form.type, value: form.value || 0, cap: form.cap || 0 }, form.qty)
  return form.scope === 'order' ? text : text + ' ' + (scopeName[form.scope] ?? '')
})
watch(suggestedName, (name) => {
  if (!nameTouched.value) form.name = name.trim()
})

function onTypeChange() {
  const scopes = currentType.value?.scope ?? []
  if (!scopes.some(option => option.id === form.scope)) form.scope = scopes[0]?.id ?? ''
  onScopeChange()
}

function onScopeChange() {
  const eaters = currentScope.value?.eaterType ?? []
  if (!eaters.some(option => option.id === form.eater_type)) form.eater_type = eaters[0]?.id ?? 'all'
  if (form.scope === 'order') form.object_ids = []
  if (currentScope.value?.singleObjectOnly && form.object_ids.length > 1) form.object_ids = form.object_ids.slice(0, 1)
}

async function loadMenu(id, selectAll) {
  menu.value = null
  if (!id) return
  menuLoading.value = true
  try {
    const { data } = await axios.get('/grab-promotions/menus/' + id)
    menu.value = data
    if (selectAll) form.ref_ids = data.stores.filter(store => store.has_account).map(store => store.ref_id)
  } catch (e) {
    form.setError('delivery_product_mapping_id', t('This menu could not be loaded.'))
  } finally {
    menuLoading.value = false
  }
}

function local(value) {
  return value ? moment(value).format('YYYY-MM-DDTHH:mm') : ''
}

function openCreate() {
  form.reset()
  form.clearErrors()
  editing.value = false
  nameTouched.value = false
  menu.value = null
  form.starts_at = moment().add(10, 'minutes').format('YYYY-MM-DDTHH:mm')
  form.ends_at = moment().add(1, 'month').endOf('day').format('YYYY-MM-DDTHH:mm')
  form.name = suggestedName.value.trim()
  if (props.menus.length === 1) {
    form.delivery_product_mapping_id = props.menus[0].id
    loadMenu(form.delivery_product_mapping_id, true)
  }
  modal.value = 'form'
}

function openEdit(promotion) {
  const discount = promotion.discount ?? {}
  const conditions = promotion.conditions ?? {}
  form.clearErrors()
  editing.value = true
  current.value = promotion
  form.delivery_product_mapping_id = promotion.mapping_id
  form.ids = promotion.stores.filter(store => !store.gone_at).map(store => store.id)
  form.ref_ids = []
  form.type = discount.type
  form.scope = discount.scope?.type ?? 'items'
  form.object_ids = (discount.scope?.objectIDs ?? []).map(String)
  form.eater_type = conditions.eaterType ?? 'all'
  form.qty = conditions.bundleQuantity || 2
  form.value = discount.value ?? ''
  form.cap = discount.cap || ''
  form.min_basket = conditions.minBasketAmount || ''
  form.total_count = promotion.quotas?.totalCount || ''
  form.total_count_per_user = promotion.quotas?.totalCountPerUser || ''
  form.starts_at = local(promotion.starts_at)
  form.ends_at = local(promotion.ends_at)
  form.name = promotion.name ?? ''
  nameTouched.value = true
  loadMenu(promotion.mapping_id, false)
  modal.value = 'form'
}

function submitForm() {
  form.post(editing.value ? '/grab-promotions/update' : '/grab-promotions', {
    preserveScroll: true,
    onSuccess: () => closeModal(),
  })
}

function openEnd(promotion, stores) {
  current.value = promotion
  endForm.clearErrors()
  endForm.ids = stores.filter(store => !store.gone_at).map(store => store.id)
  modal.value = 'end'
}

function submitEnd() {
  endForm.post('/grab-promotions/end', { preserveScroll: true, onSuccess: () => closeModal() })
}
</script>
