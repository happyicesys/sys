<template>

  <Head title="Happy Hour Campaign" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Happy Hour Campaign
        <span class="ml-2 align-middle inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border bg-sky-100 text-sky-800 border-sky-300">
          Smart Freezer
        </span>
        <span class="ml-1 align-middle inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border bg-violet-100 text-violet-800 border-violet-300">
          Vending {{ minVendingApkVersion }}+
        </span>
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3">
        <p class="text-xs text-gray-500">
          During a campaign's hours each machine shows one discounted SKU at a time on its welcome screen
          (Happy Hour page) and charges the promo price for it. Each slot's SKU is picked about 2 minutes before
          the slot starts, from that machine's stock at that moment — by default the highest stock balance %
          (qty ÷ capacity; a tie goes to the higher stock) — and the top SKUs (the number set below) take turns, slot by slot.
          A SKU that sells out ends its slot early.
          Only freezers on app {{ minApkVersion }} or later and touchscreen vending machines (big board) on app
          {{ minVendingApkVersion }} or later can run it. On a vending machine the promo price applies to orders placed
          on the screen only (keypad, cash and coin stay at the machine price), and a machine bound to a UI Setting
          ignores Happy Hour (<ExclamationCircleIcon class="inline w-3.5 h-3.5 text-orange-600 -mt-0.5" />) — it runs
          its UI Setting only — until it is unbound from it.
        </p>

        <div class="flex justify-end mt-3" v-if="permissions.includes('create happy-hour-campaigns')">
          <Button class="bg-green-500 hover:bg-green-600 text-white flex space-x-1" @click="openCreate()">
            <PlusIcon class="w-4 h-4" />
            <span>New campaign</span>
          </Button>
        </div>
      </div>

      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-3 sm:-mx-6 lg:-mx-8 px-3">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <TableHead>#</TableHead>
                  <TableHead>Campaign</TableHead>
                  <!-- Status: the live boxes sort by Machine ID, Site Name or the machine's last 24 h of Happy Hour
                       (Brian, 2026-10-15; L24h qty, most first, by default); a second click flips the order. -->
                  <TableHead>
                    <div>Status</div>
                    <div v-for="(row, r) in liveSortRows" :key="r" class="mt-1 flex justify-center gap-3 font-normal">
                      <button v-for="k in row" :key="k.id" type="button" class="inline-flex items-center text-blue-600 hover:text-blue-800 whitespace-nowrap"
                        :title="k.title" @click="sortLive(k.id)">
                        {{ k.name }}
                        <ChevronUpIcon v-if="liveSort.key === k.id && liveSort.asc" class="w-3.5 h-3.5" />
                        <ChevronDownIcon v-else-if="liveSort.key === k.id" class="w-3.5 h-3.5" />
                        <ChevronUpDownIcon v-else class="w-3.5 h-3.5 text-gray-400" />
                      </button>
                    </div>
                  </TableHead>
                  <TableHead>Days</TableHead>
                  <TableHead>Hours</TableHead>
                  <TableHead>Per Slot</TableHead>
                  <TableHead>Discount</TableHead>
                  <TableHead>Skip When</TableHead>
                  <TableHead>Machines</TableHead>
                  <TableHead>Action</TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr v-for="(c, cIndex) in campaigns" :key="c.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                  <TableData :currentIndex="cIndex" :totalLength="campaigns.length" inputClass="text-center">
                    {{ cIndex + 1 }}
                  </TableData>
                  <TableData :currentIndex="cIndex" :totalLength="campaigns.length" inputClass="text-left">
                    {{ c.name }}
                    <div class="text-xs font-normal text-gray-500">{{ ruleLabel(c.selection_rule) }}</div>
                    <!-- How the campaign is doing: last 24 h over last 30 days (Brian, 2026-10-16). -->
                    <table v-if="c.stats" class="mt-3 text-xs font-normal text-gray-700">
                      <thead>
                        <tr class="text-gray-500">
                          <th class="pr-3 text-left font-normal"></th>
                          <th class="px-1 text-right font-normal whitespace-nowrap">Last 24h</th>
                          <th class="pl-2 text-right font-normal whitespace-nowrap">/ Last 30d</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="row in statRows" :key="row.key">
                          <td class="pr-3 whitespace-nowrap" :title="row.title">{{ row.name }}</td>
                          <td class="px-1 text-right font-semibold whitespace-nowrap">{{ row.format(c.stats.l24h[row.key]) }}</td>
                          <td class="pl-2 text-right whitespace-nowrap">/ {{ row.format(c.stats.l30d[row.key]) }}</td>
                        </tr>
                      </tbody>
                    </table>
                    <button v-if="c.stats" type="button" class="mt-2 text-xs font-normal text-blue-700 hover:underline" @click="openStats(c)">
                      Show graph
                    </button>
                  </TableData>
                  <TableData :currentIndex="cIndex" :totalLength="campaigns.length" inputClass="text-center">
                    <div class="flex items-center justify-center gap-2">
                      <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border" :class="statusClass(c.status)">
                        {{ statusLabels[c.status] }}
                      </span>
                      <!-- How many machines have a slot on offer right now = the boxes below. -->
                      <span v-if="liveGroups[c.id].length" class="text-xs font-semibold text-gray-700 whitespace-nowrap">
                        Total {{ liveGroups[c.id].length }} machine{{ liveGroups[c.id].length === 1 ? '' : 's' }}
                      </span>
                    </div>
                    <!-- What is on offer right now, one box per machine (Brian, 2026-10-12: a flat list mixed the machines). -->
                    <div v-for="g in liveGroups[c.id]" :key="g.vendId" class="mt-2 rounded border border-amber-300 bg-amber-50 px-2 py-1 text-left">
                      <div class="flex items-center justify-between gap-2">
                        <span class="text-xs min-w-0">
                          <!-- The machine on the Operation Dashboard, in a new tab. -->
                          <a
                            :href="'/vends/customers?codes=' + encodeURIComponent(g.code) + '&autoload=true'"
                            target="_blank"
                            class="font-bold text-blue-700 hover:underline"
                          >{{ g.code }}</a>
                          <!-- The Site in the Site editor, in a new tab (Brian, 2026-10-15). -->
                          <a
                            v-if="g.site && g.customerId"
                            :href="'/customers/' + g.customerId + '/edit'"
                            target="_blank"
                            class="ml-1 text-blue-700 hover:underline"
                          >{{ g.site }}</a>
                          <span v-else-if="g.site" class="ml-1 text-gray-700">{{ g.site }}</span>
                          <!-- The Site's RP as the chip ProductMapping draws. Greyed when the machine does not
                               follow it and sells at its VMC board price (167 of 178 campaign machines, 2026-10-15) —
                               then the prices in this box are board prices, not the RP. -->
                          <span
                            v-if="g.rp"
                            class="ml-1 inline-flex rounded px-0.5 py-0.5 text-[11px] leading-none border align-middle"
                            :class="g.boardPrice ? 'bg-gray-100 text-gray-500 border-gray-300' : 'bg-indigo-100 text-indigo-800 border-indigo-300'"
                            v-tooltip="g.boardPrice
                              ? `The Site is on ${g.rp}, but this machine sells at its board price (Is Using Server Price? = No), so these prices are board prices.`
                              : `This machine sells at the Site's ${g.rp} price.`"
                          >{{ g.rp }}</span>
                        </span>
                        <span class="shrink-0 flex items-baseline gap-2">
                          <!-- This machine's last 24 h of Happy Hour, all slots and SKUs: discount given · units sold. -->
                          <span class="text-[11px] font-semibold text-emerald-700 whitespace-nowrap"
                            title="Last 24 h on this machine, every Happy Hour slot: discount given · units sold">L24h {{ money(g.l24hDiscount) }} · {{ g.l24hUnits }}</span>
                          <span class="text-[11px] text-amber-800 whitespace-nowrap">{{ g.from }}–{{ g.to }}</span>
                        </span>
                      </div>
                      <div v-for="s in g.slots" :key="s.id" class="flex items-baseline gap-2 text-xs text-gray-900">
                        <!-- Only the name shortens (… with the full name on hover); qty/capacity and the prices
                             never shrink, so a long SKU name can no longer push them out of the box. -->
                        <span class="min-w-0 flex-1 flex items-baseline gap-1">
                          <span class="font-semibold truncate" :title="s.product">{{ s.product }}</span>
                          <span v-if="s.qty !== null" class="shrink-0 text-gray-600" title="On hand now / capacity">({{ s.qty }}/{{ s.capacity ?? '—' }})</span>
                          <!-- Sold in THIS slot so far — not a running total; the next slot starts at 0. -->
                          <span class="shrink-0 whitespace-nowrap" :class="s.sold ? 'font-bold text-emerald-700' : 'text-gray-400'"
                            title="Sold at the Happy Hour price in this slot so far">HH: {{ s.sold ?? 0 }}</span>
                        </span>
                        <span class="shrink-0 whitespace-nowrap">
                          <span class="text-gray-400 line-through mr-1">{{ money(s.original_price) }}</span>
                          <span class="font-bold text-red-600">{{ money(s.promo_price) }}</span>
                        </span>
                      </div>
                    </div>
                  </TableData>
                  <TableData :currentIndex="cIndex" :totalLength="campaigns.length" inputClass="text-center">
                    {{ c.days_label }}
                    <div class="text-xs font-normal text-gray-500 whitespace-nowrap" v-if="c.starts_on || c.ends_on">
                      {{ c.starts_on || '…' }} → {{ c.ends_on || 'no end' }}
                    </div>
                  </TableData>
                  <TableData :currentIndex="cIndex" :totalLength="campaigns.length" inputClass="text-center">
                    <span class="whitespace-nowrap">{{ c.window_start }}–{{ c.window_end }}</span>
                  </TableData>
                  <TableData :currentIndex="cIndex" :totalLength="campaigns.length" inputClass="text-center">
                    <span class="whitespace-nowrap">{{ c.slot_minutes }} min</span>
                    <div class="text-xs font-normal text-gray-500 whitespace-nowrap">
                      <span v-if="c.skus_per_slot > 1">{{ c.skus_per_slot }} at a time · </span>top {{ c.sku_count }} SKUs
                    </div>
                  </TableData>
                  <TableData :currentIndex="cIndex" :totalLength="campaigns.length" inputClass="text-center">
                    <span class="font-semibold text-rose-600">−{{ c.discount_pct }}%</span>
                  </TableData>
                  <TableData :currentIndex="cIndex" :totalLength="campaigns.length" inputClass="text-center">
                    <span class="whitespace-nowrap">stock ≤ {{ c.min_balance_pct }}%</span>
                    <div class="whitespace-nowrap">or &lt; {{ c.min_qty }} units</div>
                    <div v-if="c.bestseller_hold_hours > 0 && c.bestseller_hold_count > 0" class="text-xs text-gray-500 whitespace-nowrap"
                      v-tooltip="`For ${c.bestseller_hold_hours} h after a Stock In, the machine's top ${c.bestseller_hold_count} sellers of the last ${c.bestseller_lookback_days} days are not discounted`">
                      top {{ c.bestseller_hold_count }} sellers {{ c.bestseller_hold_hours }} h after Stock In
                    </div>
                  </TableData>
                  <TableData :currentIndex="cIndex" :totalLength="campaigns.length" inputClass="text-center">
                    <!-- Live groups first: their members join and leave by themselves. -->
                    <div v-if="c.machine_groups.length" class="flex flex-wrap justify-center gap-1 mb-1">
                      <span v-for="g in c.machine_groups" :key="g"
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold border bg-indigo-50 text-indigo-800 border-indigo-300"
                        v-tooltip="groupTooltip(c, g)">
                        {{ groupLabel(g) }} · {{ c.vends.filter(v => v.via_group === g).length }}
                      </span>
                    </div>
                    <!-- Picked by name in another campaign: that one wins while it is in force. -->
                    <div v-for="o in c.overridden" :key="o.code" class="text-xs text-gray-500 mb-1 whitespace-nowrap"
                      v-tooltip="`Picked by name in “${o.by}”, which takes priority over this campaign's group while it is on`">
                      <span class="line-through">{{ o.code }}</span> → {{ o.by }}
                    </div>
                    <div class="flex flex-wrap justify-center gap-1">
                      <button v-for="v in shownVends(c)" :key="v.id" type="button" @click="openPreview(c, v)"
                        class="inline-flex items-center rounded px-1.5 py-0.5 text-xs border hover:ring-2 hover:ring-indigo-300"
                        :class="chipClass(v)"
                        v-tooltip="unsupportedTooltip(v) ?? pausedTooltip(v) ?? 'Show the lineup now'">
                        {{ v.code }}<span v-if="!v.supported">&nbsp;⚠</span>
                        <ExclamationCircleIcon v-else-if="v.paused" class="ml-0.5 w-3.5 h-3.5 text-orange-600" aria-label="Happy Hour paused" />
                      </button>
                      <button v-if="c.vends.length > shownVends(c).length || expanded[c.id]" type="button"
                        class="text-xs text-indigo-600 hover:text-indigo-800 underline" @click="expanded[c.id] = !expanded[c.id]">
                        {{ expanded[c.id] ? 'show less' : `+${c.vends.length - shownVends(c).length} more` }}
                      </button>
                    </div>
                  </TableData>
                  <TableData :currentIndex="cIndex" :totalLength="campaigns.length" inputClass="text-center">
                    <div class="flex justify-center space-x-1 items-start">
                      <Button v-if="permissions.includes('update happy-hour-campaigns')" type="button" class="bg-gray-300 hover:bg-gray-400 px-2 py-1 text-xs text-gray-800 flex space-x-1" @click="openEdit(c)">
                        <PencilSquareIcon class="w-4 h-4" />
                        <span>Edit</span>
                      </Button>
                      <Button v-if="permissions.includes('delete happy-hour-campaigns')" type="button" class="bg-red-500 hover:bg-red-600 px-2 py-1 text-xs text-white flex space-x-1" @click="destroy(c)">
                        <TrashIcon class="w-4 h-4" />
                        <span>Delete</span>
                      </Button>
                    </div>
                  </TableData>
                </tr>
                <tr v-if="!campaigns.length">
                  <td colspan="10" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                    No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / edit -->
    <Modal :open="showForm" @modalClose="showForm = false">
      <template #header>
        <span>{{ editingId ? 'Edit Happy Hour campaign' : 'New Happy Hour campaign' }}</span>
      </template>
      <template #default>
        <form @submit.prevent="submit">
          <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div class="md:col-span-4">
              <label class="block text-sm font-medium text-gray-700">Name</label>
              <input v-model="form.name" type="text" :class="inputClass" />
              <p v-if="form.errors.name" class="text-red-600 text-xs mt-1">{{ form.errors.name }}</p>
            </div>
            <div class="md:col-span-2 flex items-end">
              <label class="inline-flex items-center space-x-2 text-sm text-gray-700">
                <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300" />
                <span>Active</span>
              </label>
            </div>

            <div class="md:col-span-6">
              <label class="block text-sm font-medium text-gray-700">Machine groups</label>
              <div class="mt-1 flex flex-wrap gap-x-5 gap-y-1">
                <label v-for="g in machineGroups" :key="g.id" class="inline-flex items-center space-x-2 text-sm text-gray-700">
                  <input type="checkbox" :value="g.id" v-model="form.machine_groups" class="rounded border-gray-300" />
                  <span>{{ g.name }}</span>
                </label>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                Every one of your operator's machines of that kind that can run Happy Hour — now, and each new one as it qualifies
                (a big board joins when it reaches app {{ minVendingApkVersion }}). No need to pick them below.
              </p>
              <p v-if="form.errors.machine_groups" class="text-red-600 text-xs mt-1">{{ form.errors.machine_groups }}</p>
            </div>

            <div class="md:col-span-6">
              <label class="block text-sm font-medium text-gray-700">{{ form.machine_groups.length ? 'Other machines' : 'Machines' }}</label>
              <MultiSelect v-model="selectedMachines" :options="machines" mode="tags" trackBy="id" valueProp="id" label="name"
                placeholder="Smart freezers / vending machines" open-direction="bottom" class="mt-1" />
              <p class="text-xs text-gray-500 mt-1" v-if="unsupportedPicked.length">
                ⚠ {{ unsupportedPicked.join(', ') }}: cannot run Happy Hour yet — kept, but no slots until it can.
              </p>
              <p v-for="m in pausedPicked" :key="m.id" class="text-xs text-orange-700 mt-1 flex items-start gap-1">
                <ExclamationCircleIcon class="w-4 h-4 shrink-0" />
                <span>{{ m.paused }}</span>
              </p>
              <p v-if="form.errors.vend_ids" class="text-red-600 text-xs mt-1">{{ form.errors.vend_ids }}</p>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Discount %</label>
              <input v-model.number="form.discount_pct" type="number" min="1" max="90" :class="inputClass" />
              <p v-if="form.errors.discount_pct" class="text-red-600 text-xs mt-1">{{ form.errors.discount_pct }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Duration per slot (minutes)</label>
              <input v-model.number="form.slot_minutes" type="number" min="5" max="720" step="5" :class="inputClass" />
              <p v-if="form.errors.slot_minutes" class="text-red-600 text-xs mt-1">{{ form.errors.slot_minutes }}</p>
            </div>
            <div class="md:col-span-1">
              <label class="block text-sm font-medium text-gray-700">Total SKUs</label>
              <input v-model.number="form.sku_count" type="number" min="1" max="30" :class="inputClass" />
              <p v-if="form.errors.sku_count" class="text-red-600 text-xs mt-1">{{ form.errors.sku_count }}</p>
            </div>
            <div class="md:col-span-1">
              <label class="block text-sm font-medium text-gray-700">At the same time</label>
              <input v-model.number="form.skus_per_slot" type="number" min="1" :max="Math.min(10, form.sku_count || 1)" :class="inputClass" />
              <p v-if="form.errors.skus_per_slot" class="text-red-600 text-xs mt-1">{{ form.errors.skus_per_slot }}</p>
            </div>

            <div :class="form.selection_rule === 'days_of_cover' ? 'md:col-span-3' : 'md:col-span-6'">
              <label class="block text-sm font-medium text-gray-700">Pick SKUs by</label>
              <select v-model="form.selection_rule" :class="inputClass">
                <option v-for="r in rules" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
            </div>
            <!-- Only days of cover reads past sales; balance % ranks the live stock at pick time. -->
            <div class="md:col-span-3" v-if="form.selection_rule === 'days_of_cover'">
              <label class="block text-sm font-medium text-gray-700">Sales look back (days)</label>
              <input v-model.number="form.lookback_days" type="number" min="1" max="90" :class="inputClass" />
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Skip when stock ≤ (%)</label>
              <input v-model.number="form.min_balance_pct" type="number" min="0" max="99" :class="inputClass" />
              <p class="text-xs text-gray-400 mt-0.5">Where Real Capacity is set</p>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Skip when fewer units than</label>
              <input v-model.number="form.min_qty" type="number" min="1" max="999" :class="inputClass" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Round promo price up to</label>
              <select v-model.number="form.price_step_cents" :class="inputClass">
                <option :value="1">1¢</option>
                <option :value="5">5¢</option>
                <option :value="10">10¢</option>
                <option :value="50">50¢</option>
                <option :value="100">$1</option>
              </select>
            </div>

            <!-- Bestseller hold (Brian, 2026-10-16): a Stock In fills every SKU, so balance % would discount the
                 fast sellers first. Either number 0 = off. Sales are counted over the last 7 days. -->
            <div class="md:col-span-3">
              <label class="block text-sm font-medium text-gray-700">After Stock In, hold top sellers for (hours)</label>
              <input v-model.number="form.bestseller_hold_hours" type="number" min="0" max="168" :class="inputClass" />
              <p v-if="form.errors.bestseller_hold_hours" class="text-red-600 text-xs mt-1">{{ form.errors.bestseller_hold_hours }}</p>
              <p class="text-xs text-gray-400 mt-0.5">0 = off</p>
            </div>
            <div class="md:col-span-3">
              <label class="block text-sm font-medium text-gray-700">How many top sellers to hold</label>
              <input v-model.number="form.bestseller_hold_count" type="number" min="0" max="30" :class="inputClass" />
              <p v-if="form.errors.bestseller_hold_count" class="text-red-600 text-xs mt-1">{{ form.errors.bestseller_hold_count }}</p>
              <p class="text-xs text-gray-400 mt-0.5">By units sold on that machine in the last {{ form.bestseller_lookback_days }} days</p>
            </div>

            <div class="md:col-span-6">
              <label class="block text-sm font-medium text-gray-700">Days</label>
              <div class="flex flex-wrap gap-2 mt-1">
                <button v-for="p in dayPresets" :key="p.mask" type="button" @click="form.days_mask = p.mask"
                  class="rounded border px-2 py-1 text-xs"
                  :class="form.days_mask === p.mask ? 'bg-sky-600 text-white border-sky-600' : 'bg-white text-gray-700 border-gray-300'">
                  {{ p.name }}
                </button>
                <span class="mx-1 text-gray-300">|</span>
                <button v-for="(d, i) in dayNames" :key="d" type="button" @click="toggleDay(i)"
                  class="rounded border w-10 py-1 text-xs"
                  :class="(form.days_mask & (1 << i)) ? 'bg-sky-100 text-sky-800 border-sky-300' : 'bg-white text-gray-400 border-gray-200'">
                  {{ d }}
                </button>
              </div>
              <p v-if="form.errors.days_mask" class="text-red-600 text-xs mt-1">{{ form.errors.days_mask }}</p>
            </div>

            <div class="md:col-span-3">
              <label class="block text-sm font-medium text-gray-700">Hours from</label>
              <input v-model="form.window_start" type="time" :class="inputClass" />
            </div>
            <div class="md:col-span-3">
              <label class="block text-sm font-medium text-gray-700">Hours to</label>
              <input v-model="form.window_end" type="time" :class="inputClass" />
              <p v-if="form.errors.window_end" class="text-red-600 text-xs mt-1">{{ form.errors.window_end }}</p>
            </div>
            <div class="md:col-span-3">
              <DatePicker v-model="form.starts_on">Active from (optional)</DatePicker>
            </div>
            <div class="md:col-span-3">
              <DatePicker v-model="form.ends_on" :minDate="form.starts_on">Active to (optional)</DatePicker>
              <p v-if="form.errors.ends_on" class="text-red-600 text-xs mt-1">{{ form.errors.ends_on }}</p>
            </div>

            <div class="md:col-span-4">
              <label class="block text-sm font-medium text-gray-700">Never discount</label>
              <MultiSelect v-model="selectedExcluded" :options="products" mode="tags" trackBy="id" valueProp="id" label="name"
                placeholder="Products to leave out" open-direction="top" class="mt-1" />
            </div>
            <div class="md:col-span-2 flex flex-col justify-end gap-2">
              <!-- On by default (Brian, 2026-10-16): a SKU whose warehouse Available? tick is off is never picked. -->
              <label class="inline-flex items-center space-x-2 text-sm text-gray-700"
                v-tooltip="'A SKU whose Available? tick is off in the warehouse (Product Movement) is skipped when a slot picks its SKUs.'">
                <input type="checkbox" v-model="form.skip_unavailable" class="rounded border-gray-300" />
                <span>No discount on SKUs not Available in the warehouse</span>
              </label>
              <label class="inline-flex items-center space-x-2 text-sm text-gray-700">
                <input type="checkbox" v-model="form.allow_below_cost" class="rounded border-gray-300" />
                <span>Allow below unit cost</span>
              </label>
            </div>
          </div>

          <p class="text-xs text-gray-500 mt-4">
            {{ slotSummary }}
          </p>

          <div class="flex space-x-1 mt-5 justify-end">
            <Button class="bg-gray-300 hover:bg-gray-400 text-gray-700" type="button" @click="showForm = false">Back</Button>
            <Button type="submit" :disabled="form.processing" class="bg-green-500 hover:bg-green-600 text-white">Save</Button>
          </div>
        </form>
      </template>
    </Modal>

    <!-- Campaign graph: daily figures over a chosen range (default the last 7 days). -->
    <Modal :open="showStats" @modalClose="showStats = false">
      <template #header>
        <span>Happy Hour results · {{ statsCampaign?.name }}</span>
      </template>
      <template #default>
        <div class="space-y-4">
          <div class="flex flex-wrap items-end gap-3">
            <DatePicker v-model="statsRange.from" :maxDate="statsRange.to" :clearable="false">From</DatePicker>
            <DatePicker v-model="statsRange.to" :minDate="statsRange.from" :clearable="false">To</DatePicker>
            <div class="flex gap-1 pb-0.5">
              <button v-for="p in statsPresets" :key="p.days" type="button"
                class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100" @click="setStatsPreset(p.days)">
                {{ p.name }}
              </button>
            </div>
          </div>
          <div v-if="statsLoading && !statsData" class="text-sm text-gray-500">Loading…</div>
          <template v-if="statsData">
            <p class="text-xs text-gray-500">
              {{ statsData.from }} → {{ statsData.to }}: sales {{ money(statsData.total.revenue) }}, discount {{ money(statsData.total.discount) }},
              {{ statsData.total.units }} units over {{ statsData.total.machine_days }} machine-days
              ({{ statsData.total.avg_daily_units_per_machine ?? '—' }} units per machine per day). A machine counts on each day it ran a slot.
            </p>
            <CampaignStatsCharts :days="statsData.days" :total="statsData.total" />
          </template>
        </div>
      </template>
    </Modal>

    <!-- Preview lineup -->
    <Modal :open="showPreview" @modalClose="showPreview = false">
      <template #header>
        <span>Lineup now · {{ previewTitle }}</span>
      </template>
      <template #default>
        <div v-if="!preview" class="text-sm text-gray-500">Loading…</div>
        <div v-else class="space-y-4 max-h-[70vh] overflow-y-auto">
          <p class="text-xs text-gray-500">As ranked at {{ preview.at }}. Each slot is ranked again as it comes up, so this order moves with the stock.</p>
          <div v-for="m in preview.machines" :key="m.vend_id">
            <div v-if="!m.supported" class="text-xs text-amber-700">
              ⚠ {{ m.unsupported_reason ?? `app ${m.apk_version_code ?? '?'} — no slots until it runs ${minApkVersion}+` }}
            </div>
            <div v-else-if="m.paused" class="text-xs text-orange-700 flex items-start gap-1">
              <ExclamationCircleIcon class="w-4 h-4 shrink-0" />
              <span>{{ m.paused }}</span>
            </div>
            <div class="mt-1 shadow-sm ring-1 ring-black ring-opacity-5">
              <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-100">
                  <tr class="divide-x divide-gray-200">
                    <TableHead>Rank</TableHead>
                    <TableHead>SKU</TableHead>
                    <TableHead>Stock</TableHead>
                    <TableHead>Sold / Day</TableHead>
                    <TableHead>Days of Cover</TableHead>
                    <TableHead>Price</TableHead>
                    <TableHead>Why Not</TableHead>
                  </tr>
                </thead>
                <tbody class="bg-white">
                  <tr v-for="(row, rowIndex) in m.candidates" :key="row.product_id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100"
                    :class="row.rank ? '' : 'opacity-60'">
                    <TableData :currentIndex="rowIndex" :totalLength="m.candidates.length" inputClass="text-center font-semibold">
                      {{ row.rank ?? '-' }}
                    </TableData>
                    <TableData :currentIndex="rowIndex" :totalLength="m.candidates.length" inputClass="text-left">
                      {{ row.product_code }} {{ row.product_name }}
                    </TableData>
                    <TableData :currentIndex="rowIndex" :totalLength="m.candidates.length" inputClass="text-center">
                      <span class="whitespace-nowrap">{{ row.qty }}<span v-if="row.capacity"> / {{ row.capacity }} ({{ row.balance_pct }}%)</span></span>
                    </TableData>
                    <TableData :currentIndex="rowIndex" :totalLength="m.candidates.length" inputClass="text-center">
                      {{ row.avg_daily_sales }}
                    </TableData>
                    <TableData :currentIndex="rowIndex" :totalLength="m.candidates.length" inputClass="text-center">
                      {{ row.days_of_cover ?? '∞' }}
                    </TableData>
                    <TableData :currentIndex="rowIndex" :totalLength="m.candidates.length" inputClass="text-right">
                      <span class="whitespace-nowrap">
                        <span v-if="row.promo_price" class="line-through text-gray-400 mr-1">{{ money(row.original_price) }}</span>{{ money(row.promo_price ?? row.original_price) }}
                      </span>
                    </TableData>
                    <TableData :currentIndex="rowIndex" :totalLength="m.candidates.length" inputClass="text-left">
                      {{ row.excluded_reason }}
                    </TableData>
                  </tr>
                  <tr v-if="!m.candidates.length">
                    <td colspan="7" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium text-center">
                      No stock rows on this machine
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>
    </Modal>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import CampaignStatsCharts from '@/Components/HappyHour/CampaignStatsCharts.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import { ChevronDownIcon, ChevronUpDownIcon, ChevronUpIcon, ExclamationCircleIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  campaigns: Array,
  machines: Array,
  products: Array,
  rules: Array,
  minApkVersion: Number,
  minVendingApkVersion: Number,
  machineGroups: { type: Array, default: () => [] },
})

const permissions = computed(() => usePage().props.auth?.permissions ?? [])

const inputClass = 'mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md'
const dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const dayPresets = [
  { name: 'Every day', mask: 127 },
  { name: 'Weekdays', mask: 31 },
  { name: 'Weekends', mask: 96 },
]
const statusLabels = { running: 'Running now', scheduled: 'Scheduled', paused: 'Paused', ended: 'Ended' }

const blank = () => ({
  name: '', is_active: true, starts_on: null, ends_on: null, days_mask: 127,
  window_start: '14:00', window_end: '18:00', slot_minutes: 60, sku_count: 4, skus_per_slot: 1,
  selection_rule: 'balance_pct', lookback_days: 14, discount_pct: 40,
  min_balance_pct: 15, min_qty: 2, price_step_cents: 10, allow_below_cost: false, skip_unavailable: true,
  bestseller_hold_hours: 0, bestseller_hold_count: 0, bestseller_lookback_days: 7,
  excluded_product_ids: [], vend_ids: [], machine_groups: [],
})

const form = useForm(blank())
const editingId = ref(null)
const showForm = ref(false)
const selectedMachines = ref([])
const selectedExcluded = ref([])
const showPreview = ref(false)

// The Campaign column's figures (last 24 h / last 30 days) and the graph modal (Brian, 2026-10-16).
const statRows = [
  { key: 'revenue', name: 'Total sales', format: (v) => money(v ?? 0), title: 'Paid for Happy Hour units' },
  { key: 'discount', name: 'Total $disc', format: (v) => money(v ?? 0), title: 'Normal price less the Happy Hour price, per unit sold' },
  { key: 'units', name: 'Total qty', format: (v) => v ?? 0, title: 'Happy Hour units sold' },
  { key: 'avg_daily_units_per_machine', name: 'Avg daily qty / VM', format: (v) => v ?? '—', title: 'Units per machine per day it ran a slot' },
]
const showStats = ref(false)
const statsCampaign = ref(null)
const statsData = ref(null)
const statsLoading = ref(false)
const statsRange = ref({ from: null, to: null })
const statsPresets = [{ name: '7d', days: 7 }, { name: '14d', days: 14 }, { name: '30d', days: 30 }]
let statsRequest = 0

const isoDay = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

function setStatsPreset(days) {
  const to = new Date()
  const from = new Date()
  from.setDate(to.getDate() - (days - 1))
  statsRange.value = { from: isoDay(from), to: isoDay(to) }
}

function openStats(c) {
  statsCampaign.value = c
  statsData.value = null
  setStatsPreset(7)
  showStats.value = true
}

async function loadStats() {
  if (!showStats.value || !statsCampaign.value || !statsRange.value.from || !statsRange.value.to) return
  const ticket = ++statsRequest
  statsLoading.value = true
  try {
    const { data } = await axios.get(`/happy-hour-campaigns/${statsCampaign.value.id}/stats`, { params: statsRange.value })
    if (ticket === statsRequest) statsData.value = data
  } finally {
    if (ticket === statsRequest) statsLoading.value = false
  }
}

watch(statsRange, loadStats, { deep: true })
const preview = ref(null)
const previewTitle = ref('')
// Campaigns whose machine chips are all shown (a live group can hold every big board).
const expanded = ref({})
const CHIP_LIMIT = 12

const groupLabel = (id) => props.machineGroups.find(g => g.id === id)?.name ?? id
const groupTooltip = (c, g) => `${groupLabel(g)}: ${c.vends.filter(v => v.via_group === g).length} machine(s) now — joins and leaves by itself`
// Hand-picked machines always; a group's members up to the limit unless expanded.
function shownVends(c) {
  if (expanded.value[c.id]) return c.vends
  const hand = c.vends.filter(v => !v.via_group)
  return [...hand, ...c.vends.filter(v => v.via_group).slice(0, Math.max(0, CHIP_LIMIT - hand.length))]
}

// Hover on a ⚠ machine chip: why it gets no Happy Hour slots, and what changes that.
// Same shape as ProductMapping's long tooltips: floating-vue sets no max-width, so the width is an
// inline style on the content (a utility class loses to its own `max-width: inherit`).
const escapeHtml = (text) => String(text).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]))

const tooltipBox = (body) => ({ content: `<div style="max-width:20rem;text-align:left;line-height:1.35;">${body}</div>`, html: true })

const unsupportedTooltip = (v) => {
  if (v.supported) return null
  const code = escapeHtml(v.code)
  if (!v.is_active) {
    return tooltipBox(`<strong>${code} is inactive.</strong><br>Happy Hour never runs on an inactive machine. Set it active in Machine Settings and it joins this campaign.`)
  }
  if (v.machine_type === 'vending_machine') {
    const min = props.minVendingApkVersion
    return tooltipBox(`<strong>${code}: ${escapeHtml(v.unsupported_reason ?? 'cannot run Happy Hour')}.</strong><br>`
      + `Happy Hour runs on the big touchscreen board on app ${min} or later, so no slots are planned for it and it keeps selling at the normal price.<br>`
      + `A big board joins this campaign by itself once it updates to app ${min}+ — nothing to change here.`)
  }
  const min = props.minApkVersion
  return tooltipBox(`<strong>${code} runs freezer app ${escapeHtml(v.apk_version_code ?? 'unknown')}.</strong><br>`
    + `Happy Hour needs app ${min} or later, so no slots are planned for it and it keeps selling at the normal price.<br>`
    + `It joins this campaign by itself once it updates to app ${min}+ — nothing to change here.`)
}

// The failsafe: a machine bound to a UI Setting ignores Happy Hour (server-written text).
const pausedTooltip = (v) => v.paused ? tooltipBox(escapeHtml(v.paused)) : null

const chipClass = (v) => !v.supported
  ? 'bg-amber-50 text-amber-800 border-amber-300'
  : (v.paused ? 'bg-orange-50 text-orange-800 border-orange-300' : 'bg-gray-50 text-gray-800 border-gray-300')

const unsupportedPicked = computed(() => selectedMachines.value.filter(m => !m.supported).map(m => m.name))
const pausedPicked = computed(() => selectedMachines.value.filter(m => m.supported && m.paused))

const slotSummary = computed(() => {
  const [h1, m1] = (form.window_start || '0:0').split(':').map(Number)
  const [h2, m2] = (form.window_end || '0:0').split(':').map(Number)
  const minutes = (h2 * 60 + m2) - (h1 * 60 + m1)
  if (minutes <= 0 || !form.slot_minutes) return ''
  const slots = Math.ceil(minutes / form.slot_minutes)
  const total = form.sku_count || 1
  const together = Math.min(form.skus_per_slot || 1, total)
  const turns = together > 1
    ? `${together} SKUs at a time from the top ${total} (ranks ${groupsLabel(total, together)} …)`
    : `the top ${total} SKU${total === 1 ? '' : 's'} take turns`
  const hold = form.bestseller_hold_hours > 0 && form.bestseller_hold_count > 0
    ? ` For ${form.bestseller_hold_hours} h after each Stock In, the machine's top ${form.bestseller_hold_count} seller${form.bestseller_hold_count === 1 ? '' : 's'} of the last ${form.bestseller_lookback_days} days ${form.bestseller_hold_count === 1 ? 'is' : 'are'} not discounted.`
    : ''
  return `${slots} slot${slots === 1 ? '' : 's'} a day of ${form.slot_minutes} min; ${turns}, at ${form.discount_pct}% off.${hold}`
})

// "1+2, 3+4, then 1+2 again" for 4 SKUs, 2 at a time (the first full loop).
function groupsLabel(total, together) {
  const groups = []
  for (let i = 0; i < total; i += together) {
    groups.push(Array.from({ length: Math.min(together, total - i) }, (_, k) => i + k + 1).join('+'))
  }
  return groups.join(', ') + ', then ' + groups[0] + ' again'
}

function ruleLabel(id) {
  return props.rules.find(r => r.id === id)?.name ?? id
}

function statusClass(status) {
  return {
    running: 'bg-green-100 text-green-800 border-green-300',
    scheduled: 'bg-sky-100 text-sky-800 border-sky-300',
    paused: 'bg-gray-100 text-gray-700 border-gray-300',
    ended: 'bg-gray-50 text-gray-400 border-gray-200',
  }[status]
}

function money(cents) {
  return cents === null || cents === undefined ? '' : '$' + (cents / 100).toFixed(2)
}

function vendCode(c, vendId) {
  return c.vends.find(v => v.id === vendId)?.code ?? vendId
}

// Live slots grouped per machine, in the campaign's machine order; each group shows its own hours.
function liveByMachine(c) {
  const groups = new Map()
  for (const s of c.live ?? []) {
    if (!groups.has(s.vend_id)) {
      const vend = c.vends.find(v => v.id === s.vend_id)
      groups.set(s.vend_id, { vendId: s.vend_id, code: vendCode(c, s.vend_id), site: vend?.site ?? null, customerId: vend?.customer_id ?? null, rp: vend?.rp ?? null, boardPrice: vend?.board_price ?? false, l24hUnits: vend?.l24h_units ?? 0, l24hDiscount: vend?.l24h_discount ?? 0, from: s.starts_at, to: s.ends_at, slots: [] })
    }
    const g = groups.get(s.vend_id)
    g.slots.push(s)
    if (s.starts_at < g.from) g.from = s.starts_at
    if (s.ends_at > g.to) g.to = s.ends_at
  }
  const order = c.vends.map(v => v.id)
  return [...groups.values()].sort((a, b) => order.indexOf(a.vendId) - order.indexOf(b.vendId))
}

// Sort of the live boxes. Machine ID numeric (2046 < 2301) and Site Name A–Z start ascending (a box with no Site
// sorts last either way); the last-24 h figures start with the most first. Default: L24h qty, most first
// (Brian, 2026-10-15: "we know who sold the most in 24 hours"). Ties fall back to Machine ID.
const liveSortRows = [
  [{ id: 'code', name: 'ID' }, { id: 'site', name: 'Site Name' }],
  [
    { id: 'l24hDiscount', name: 'L24h $disc', title: 'Happy Hour discount given on the machine in the last 24 h' },
    { id: 'l24hUnits', name: 'L24h qty', title: 'Happy Hour units sold on the machine in the last 24 h' },
  ],
]
const numericSortKeys = ['l24hDiscount', 'l24hUnits']
const liveSort = ref({ key: 'l24hUnits', asc: false })

function sortLive(key) {
  liveSort.value = { key, asc: liveSort.value.key === key ? !liveSort.value.asc : !numericSortKeys.includes(key) }
}

const byCode = (a, b) => String(a.code).localeCompare(String(b.code), undefined, { numeric: true })

const liveGroups = computed(() => {
  const { key, asc } = liveSort.value
  const out = {}
  for (const c of props.campaigns) {
    const groups = liveByMachine(c)
    if (key) {
      groups.sort((a, b) => {
        if (numericSortKeys.includes(key)) {
          return ((asc ? 1 : -1) * ((a[key] ?? 0) - (b[key] ?? 0))) || byCode(a, b)
        }
        const x = a[key] == null ? '' : String(a[key])
        const y = b[key] == null ? '' : String(b[key])
        if (!x || !y) return (x ? 0 : 1) - (y ? 0 : 1)
        const cmp = x.localeCompare(y, undefined, { numeric: true, sensitivity: 'base' })
        return asc ? cmp : -cmp
      })
    }
    out[c.id] = groups
  }
  return out
})

function toggleDay(i) {
  form.days_mask ^= (1 << i)
}

function openCreate() {
  editingId.value = null
  Object.assign(form, blank())
  form.clearErrors()
  selectedMachines.value = []
  selectedExcluded.value = []
  showForm.value = true
}

function openEdit(c) {
  editingId.value = c.id
  form.clearErrors()
  Object.assign(form, {
    ...blank(),
    ...Object.fromEntries(Object.keys(blank()).filter(k => k in c).map(k => [k, c[k]])),
  })
  form.machine_groups = [...(c.machine_groups ?? [])]
  // Only hand-picked machines go in the picker; a group's members follow the group.
  selectedMachines.value = props.machines.filter(m => c.vends.some(v => v.id === m.id && !v.via_group))
  selectedExcluded.value = props.products.filter(p => c.excluded_product_ids.includes(p.id))
  showForm.value = true
}

function submit() {
  form.vend_ids = selectedMachines.value.map(m => m.id)
  form.excluded_product_ids = selectedExcluded.value.map(p => p.id)
  const url = editingId.value ? `/happy-hour-campaigns/${editingId.value}` : '/happy-hour-campaigns'
  form.post(url, {
    preserveScroll: true,
    onSuccess: () => { showForm.value = false },
  })
}

function destroy(c) {
  if (!confirm(`Delete "${c.name}"? Its remaining slots today stop at once.`)) return
  router.delete(`/happy-hour-campaigns/${c.id}`, { preserveScroll: true })
}

// A machine chip opens that machine's ranking right now (what the next slot would pick from).
async function openPreview(c, v) {
  preview.value = null
  previewTitle.value = `${v.code} · ${c.name}`
  showPreview.value = true
  const { data } = await axios.get(`/happy-hour-campaigns/${c.id}/preview`, { params: { vend_id: v.id } })
  preview.value = data
}
</script>
