<template>

  <Head title="AI Recognition" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        AI Recognition
        <span class="ml-2 align-middle inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border bg-sky-100 text-sky-800 border-sky-300">
          Smart Freezer
        </span>
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div v-if="$page.props.flash.success" class="mb-2 rounded-md border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-800">
        {{ $page.props.flash.success }}
      </div>
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <p class="text-xs text-gray-500">
          Each row is one Smart Freezer door session: the camera videos Zijia pushed, what their AI saw
          taken, and how that compares with what the customer paid for. A sale paid on a T05 card terminal
          is charged by this verdict in one capture (above the checkout hold too); NETS and QR sales were charged
          at the machine. Nothing here changes stock.
        </p>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-7 gap-2 mt-3">
          <div class="md:col-span-2">
            <SearchInput placeholderStr="Order no / device / machine" v-model="filters.search" @keyup.enter="onSearchFilterUpdated()">
              Search
            </SearchInput>
          </div>
          <div class="md:col-span-2">
            <SearchInput placeholderStr="Machine ID (e.g. 50001, or 50001,2009)" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
              Machine ID
            </SearchInput>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Status
            </label>
            <MultiSelect
              v-model="filters.status"
              :options="statusOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Verdict
            </label>
            <MultiSelect
              v-model="filters.verdict"
              :options="verdictOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Processed By
            </label>
            <MultiSelect
              v-model="filters.processed_by"
              :options="processedByOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div class="md:col-span-2">
            <DatePicker v-model="filters.date_from">
              Date From
            </DatePicker>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Time From
            </label>
            <input type="time" v-model="filters.time_from" @keyup.enter="onSearchFilterUpdated()"
              class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
            />
          </div>
          <div class="md:col-span-2">
            <DatePicker v-model="filters.date_to" :minDate="filters.date_from">
              Date To
            </DatePicker>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Time To
            </label>
            <input type="time" v-model="filters.time_to" @keyup.enter="onSearchFilterUpdated()"
              class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
            />
          </div>
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="onSearchFilterUpdated()"
            >
              <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
              <span>
                Search
              </span>
            </Button>
          </div>
          <div class="flex flex-col space-y-2">
            <p class="text-sm text-gray-700 leading-5 flex space-x-1">
              <span>Showing</span>
              <span class="font-medium">{{ recognitions.meta.from ?? 0 }}</span>
              <span>to</span>
              <span class="font-medium">{{ recognitions.meta.to ?? 0 }}</span>
              <span>of</span>
              <span class="font-medium">{{ recognitions.meta.total }}</span>
              <span>results</span>
            </p>
            <MultiSelect
              v-model="filters.numberPerPage"
              :options="numberPerPageOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
              @selected="onSearchFilterUpdated"
            >
            </MultiSelect>
          </div>
        </div>
      </div>

      <!-- T05 holds waiting for a person whose session has no row below (Zijia never pushed its videos). -->
      <div v-if="sessionlessHolds.length" class="-mx-3 sm:-mx-6 lg:-mx-8 mt-3 bg-amber-50 rounded-md border border-amber-300 px-3 py-2">
        <p class="text-sm font-medium text-amber-900">
          T05 card holds with no AI session ({{ sessionlessHolds.length }})
          <span class="font-normal">— the AI never received this door session, so nothing is charged until someone keys it in.</span>
        </p>
        <div class="mt-2 flex flex-col divide-y divide-amber-200 text-xs">
          <div v-for="hold in sessionlessHolds" :key="hold.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 py-1.5">
            <span class="font-medium">{{ hold.vend_label }}</span>
            <span class="font-mono">{{ hold.session_ref }}</span>
            <span>hold {{ formatCents(hold.hold_cents) }}</span>
            <span class="text-gray-600">door closed {{ hold.door_closed_at }}</span>
            <button v-if="canCharge" type="button"
              class="ml-auto inline-flex items-center rounded-md border border-indigo-600 bg-indigo-600 px-2 py-1 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 whitespace-nowrap"
              @click="openCharge(sessionlessRow(hold))"
            >
              Key in charge
            </button>
          </div>
        </div>
      </div>

      <!-- Legend: Zijia's orderStatus codes (§6.2), what they mean, and who settled the session. -->
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 mt-3 bg-white rounded-md border px-3 py-2">
        <button type="button" class="flex w-full items-center justify-between text-sm font-medium text-gray-700" @click="toggleLegend">
          <span>AI status codes <span class="font-normal text-gray-500">— what "AI: …" in the Status column means</span></span>
          <span class="text-xs text-blue-600">{{ showLegend ? 'Hide' : 'Show' }}</span>
        </button>
        <div v-if="showLegend" class="mt-2 overflow-x-auto">
          <table class="min-w-full text-xs">
            <thead>
              <tr class="text-left text-gray-500">
                <th class="py-1 pr-3 font-medium">Code</th>
                <th class="py-1 pr-3 font-medium">Zijia</th>
                <th class="py-1 pr-3 font-medium">Shown as</th>
                <th class="py-1 pr-3 font-medium">In plain words</th>
                <th class="py-1 font-medium">Processed by</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="item in orderStatusLegend" :key="item.code">
                <td class="py-1 pr-3 font-mono font-semibold">{{ item.code }}</td>
                <td class="py-1 pr-3 whitespace-nowrap">{{ item.name_zh }}</td>
                <td class="py-1 pr-3 whitespace-nowrap">{{ item.label }}</td>
                <td class="py-1 pr-3 text-gray-600">{{ item.plain }}</td>
                <td class="py-1">
                  <span class="inline-flex items-center whitespace-nowrap rounded px-1.5 py-0.5 font-bold border" :class="processedByBadgeClass(item.processed_by)">
                    {{ processedByLabels[item.processed_by] }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="mt-2 text-xs text-gray-500">
            A finer reason may follow the dash, e.g. "recognition error — 503 goods not listed in the model (商品未上架)": Zijia's
            jsOrderStatus, using the same codes. Any code other than 0 leaves a T05 card hold for a person to key in the charge.
          </p>
        </div>
      </div>

      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-3 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <TableHead>
                    #
                  </TableHead>
                  <TableHeadSort modelName="created_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('created_at')">
                    Door Session
                  </TableHeadSort>
                  <TableHead>
                    Machine
                  </TableHead>
                  <TableHead>
                    Order No
                  </TableHead>
                  <TableHead>
                    Videos
                  </TableHead>
                  <TableHeadSort modelName="status" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('status')">
                    Status
                  </TableHeadSort>
                  <TableHead>
                    Paid For
                  </TableHead>
                  <TableHead>
                    AI Saw Taken
                  </TableHead>
                  <TableHeadSort modelName="verdict" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('verdict')">
                    Verdict vs Paid
                  </TableHeadSort>
                  <TableHead>
                    Sale / Card charge
                  </TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr v-for="(row, rowIndex) in recognitions.data" :key="row.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100 align-top">
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    {{ recognitions.meta.from + rowIndex }}
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    <div class="flex flex-col space-y-1">
                      <span>{{ row.created_at }}</span>
                      <span v-if="row.completed_at" class="text-xs text-gray-500" title="When the AI result came back">
                        result {{ row.completed_at.substring(11) }}
                      </span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    <div class="flex flex-col space-y-1">
                      <span v-if="row.vend_label" class="font-medium">{{ row.vend_label }}</span>
                      <span v-else class="text-amber-700 text-xs" title="The push named no freezer of ours">Not matched</span>
                      <span v-if="row.device_id" class="text-xs text-gray-500" title="Device no / IMEI in the push">{{ row.device_id }}</span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div class="flex flex-col space-y-1 text-xs">
                      <span class="whitespace-nowrap" title="Zijia's order no for the door session (tradeId sent to the AI)">
                        <span class="text-gray-500">Zijia:</span> <span class="font-mono">{{ row.trade_id }}</span>
                      </span>
                      <span v-if="row.session_ref" class="whitespace-nowrap" title="Our txnRef (SFREF on the TRADE)">
                        <span class="text-gray-500">Ours:</span> <span class="font-mono">{{ row.session_ref }}</span>
                      </span>
                      <span v-if="row.request_id" class="whitespace-nowrap" title="The AI service's request id">
                        <span class="text-gray-500">AI req:</span> <span class="font-mono">{{ row.request_id }}</span>
                      </span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div class="flex flex-col space-y-1 text-xs">
                      <a v-for="(url, urlIndex) in row.videos" :key="url" :href="url" target="_blank" rel="noopener noreferrer"
                        class="text-blue-600 hover:underline whitespace-nowrap" :title="url"
                      >
                        ▶ {{ videoLabel(url, urlIndex) }}
                      </a>
                      <a v-for="clip in row.clips" :key="'clip-' + clip.id" :href="clip.url" target="_blank" rel="noopener noreferrer"
                        class="text-emerald-700 hover:underline whitespace-nowrap"
                        title="Our own recording (not sent to the AI), from payment until the door closed"
                      >
                        ▶ Ours · Cam {{ clip.camera_id }}<span v-if="clip.seconds !== null"> · {{ clip.seconds }}s</span>
                      </a>
                      <span v-if="!row.videos.length && !row.clips.length" class="text-gray-400">—</span>
                      <span v-if="row.pushes > 1" class="text-gray-400" title="Zijia pushed this session more than once">{{ row.pushes }} pushes</span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div class="flex flex-col space-y-1">
                      <span class="inline-flex w-fit items-center rounded px-1.5 py-0.5 text-xs font-bold border" :class="statusBadgeClass(row.status)">
                        {{ statusLabels[row.status] || row.status }}
                      </span>
                      <span v-if="row.processed_by" class="inline-flex w-fit items-center whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-bold border"
                        :class="processedByBadgeClass(row.processed_by)" :title="processedByTitles[row.processed_by]"
                      >
                        {{ processedByLabels[row.processed_by] }}
                      </span>
                      <span v-if="row.algorithm_status" class="text-xs text-gray-700" title="The AI's own status">AI: {{ row.algorithm_status }}</span>
                      <span v-if="row.status_reason && row.status_reason !== ('algorithm: ' + row.algorithm_status)" class="text-xs text-gray-500">{{ row.status_reason }}</span>
                      <span v-if="row.callback_verified === false" class="text-xs text-red-700">Result signature NOT verified</span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <!-- The sale's TRADE: what the customer paid for. A product with no barcode in mark1 is
                         never sent to the AI, so its "not taken" cannot be judged. -->
                    <div v-if="row.purchased.length" class="flex flex-col space-y-1 text-xs">
                      <span v-for="line in row.purchased" :key="line.product_id || line.name">
                        {{ line.qty }} × {{ line.name }}
                        <span v-if="line.unit_cents !== null" class="text-gray-500">{{ formatCents(line.unit_cents) }}</span>
                        <span v-if="!line.has_barcode" class="ml-1 inline-flex rounded px-1 py-0.5 text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-300"
                              title="No barcode in mark1, so the AI was not asked to look for this product">no barcode — AI not asked</span>
                      </span>
                    </div>
                    <span v-else class="text-gray-400" :title="row.sale ? '' : 'Shown once the sale is matched to this session'">—</span>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div v-if="row.algorithm_status !== null" class="flex flex-col space-y-1 text-xs">
                      <span v-for="item in row.items" :key="item.code">
                        {{ item.number }} × {{ item.name || item.code }}
                        <span v-if="item.name" class="text-gray-400 font-mono">{{ item.code }}</span>
                      </span>
                      <span v-if="!row.items.length" class="text-gray-500">Nothing</span>
                    </div>
                    <span v-else class="text-gray-400">—</span>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div v-if="row.verdict" class="flex flex-col space-y-1">
                      <span class="inline-flex w-fit items-center rounded px-1.5 py-0.5 text-xs font-bold border" :class="verdictBadgeClass(row.verdict)">
                        {{ verdictLabels[row.verdict] || row.verdict }}
                      </span>
                      <span v-for="(line, lineIndex) in row.verdict_lines" :key="lineIndex" class="text-xs"
                        :class="line.delta ? 'text-red-700 font-medium' : 'text-gray-600'"
                      >
                        {{ line.name || line.code || ('product ' + line.product_id) }}: paid {{ line.paid }}, taken {{ line.taken }}
                      </span>
                    </div>
                    <span v-else class="text-gray-400">—</span>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    <div v-if="row.sale" class="flex flex-col space-y-1 text-xs">
                      <a :href="saleUrl(row.sale)" target="_blank" class="text-blue-600 hover:underline font-mono whitespace-nowrap">{{ row.sale.order_id }}</a>
                      <span v-if="row.sale.date" class="text-gray-500 whitespace-nowrap" title="Sale time (the TRADE)">{{ row.sale.date }} {{ row.sale.time }}</span>
                      <span>{{ formatCents(row.sale.amount) }}</span>
                    </div>
                    <span v-else class="text-gray-400">—</span>
                    <div v-if="row.card" class="mt-1 flex flex-col space-y-0.5 text-xs" :title="row.card.reason || ''">
                      <span class="inline-flex w-fit mx-auto items-center rounded px-1.5 py-0.5 font-bold border" :class="cardBadgeClass(row.card)">
                        {{ cardLabel(row.card) }}
                      </span>
                      <span v-if="row.card.increment_refused" class="text-gray-600 whitespace-nowrap">Payrallel refused the increase; hold charged</span>
                      <span v-if="row.card.owed_cents" class="text-red-700 whitespace-nowrap">Not charged yet {{ formatCents(row.card.owed_cents) }}</span>
                      <span v-if="row.card.error" class="text-red-700">{{ row.card.error }}</span>
                      <span v-if="row.card.by_person" class="text-gray-600 whitespace-nowrap">Keyed in by {{ row.card.by_person }}</span>
                      <!-- The AI answered without a verdict: the hold waits for a person, never charged on its own. -->
                      <button v-if="row.card.awaits_person && canCharge" type="button"
                        class="mx-auto mt-1 inline-flex items-center rounded-md border border-indigo-600 bg-indigo-600 px-2 py-1 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 whitespace-nowrap"
                        @click="openCharge(row)"
                      >
                        Key in charge
                      </button>
                    </div>
                  </TableData>
                </tr>
                <tr v-if="!recognitions.data.length">
                  <td colspan="10" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                    No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="recognitions.data.length" :links="recognitions.links" :meta="recognitions.meta"></Paginator>
          </div>
        </div>
      </div>
    </div>
    <Modal :open="charge.open" @modalClose="closeCharge">
      <template #header>
        {{ charge.step === 1 ? 'Key in the final charge' : 'Confirm the charge' }}
      </template>
      <div v-if="charge.row" class="flex flex-col space-y-3 text-sm">
        <div class="grid grid-cols-2 gap-x-4 gap-y-1">
          <span class="text-gray-500">Machine</span><span class="font-medium">{{ charge.row.vend_label }}</span>
          <span class="text-gray-500">Order No</span><span class="font-mono">{{ charge.row.sale ? charge.row.sale.order_id : charge.row.session_ref }}</span>
          <span class="text-gray-500">Card hold (T05)</span><span class="font-medium">{{ formatCents(charge.row.card.hold_cents) }}</span>
          <span class="text-gray-500">AI</span><span>{{ charge.row.algorithm_status || '—' }}</span>
          <span class="text-gray-500">Paid for</span>
          <span>
            <span v-for="line in charge.row.purchased" :key="line.product_id || line.name" class="block">{{ line.qty }} × {{ line.name }}</span>
            <span v-if="!charge.row.purchased.length" class="text-gray-400">—</span>
          </span>
        </div>

        <template v-if="charge.step === 1">
          <p class="text-gray-600">
            {{ charge.row.card.person_reason === 'ai_no_answer' ? 'The AI never answered this session' : 'The AI could not judge this session' }},
            so the hold is waiting for you — it is never charged on its own. Check the videos (if any), then key in what
            the customer should pay. 0 releases the hold.
          </p>
          <div>
            <label class="block text-sm font-medium text-gray-700">Final chargeable amount</label>
            <div class="mt-1 flex items-center space-x-2">
              <input type="text" inputmode="decimal" v-model="charge.amount" placeholder="0.00" @keyup.enter="nextCharge"
                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-40 text-sm border-gray-300 rounded-md"
              />
              <button type="button" class="text-xs text-blue-600 hover:underline" @click="charge.amount = formatCents(charge.row.card.hold_cents)">
                Use the hold ({{ formatCents(charge.row.card.hold_cents) }})
              </button>
            </div>
            <p v-if="charge.error" class="mt-1 text-sm text-red-700">{{ charge.error }}</p>
          </div>
          <div class="flex justify-end space-x-2 pt-2">
            <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50" @click="closeCharge">Cancel</button>
            <button type="button" class="rounded-md border border-indigo-600 bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="nextCharge">Next</button>
          </div>
        </template>

        <template v-else>
          <div class="rounded-md border px-3 py-3" :class="charge.cents === 0 ? 'border-gray-300 bg-gray-50' : 'border-amber-300 bg-amber-50'">
            <p v-if="charge.cents === 0" class="font-semibold text-gray-800">
              Release the {{ formatCents(charge.row.card.hold_cents) }} hold — the customer is charged nothing.
            </p>
            <p v-else class="font-semibold text-gray-800">
              Charge {{ formatCents(charge.cents) }} to the customer's card on the T05?
            </p>
            <p v-if="charge.cents > charge.row.card.hold_cents" class="mt-1 text-amber-800">
              This is above the {{ formatCents(charge.row.card.hold_cents) }} hold. Payrallel is asked to raise it; if they refuse,
              only {{ formatCents(charge.row.card.hold_cents) }} is charged and the rest is recorded as not charged.
            </p>
            <p class="mt-1 text-gray-600">This is sent to Payrallel at once and cannot be undone here.</p>
          </div>
          <p v-if="charge.error" class="text-sm text-red-700">{{ charge.error }}</p>
          <div v-if="charge.answered" class="flex justify-end pt-2">
            <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50" @click="closeCharge">Close</button>
          </div>
          <div v-else class="flex justify-end space-x-2 pt-2">
            <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50" :disabled="charge.sending" @click="charge.step = 1; charge.error = ''">Back</button>
            <button type="button" class="rounded-md border border-red-600 bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
              :disabled="charge.sending" @click="submitCharge"
            >
              {{ charge.sending ? 'Sending…' : (charge.cents === 0 ? 'Release hold' : 'Charge ' + formatCents(charge.cents)) }}
            </button>
          </div>
        </template>
      </div>
    </Modal>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Paginator from '@/Components/Paginator.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
  recognitions: Object,
  statuses: Array,
  verdicts: Array,
  canCharge: Boolean,
  sessionlessHolds: { type: Array, default: () => [] },
  orderStatusLegend: Array,
  filters: Object,
})

const statusLabels = {
  pending: 'Waiting',
  submitting: 'Sending…',
  submitted: 'Sent to AI',
  completed: 'Result in',
  failed: 'Failed',
}

const verdictLabels = {
  match: 'Match',
  took_more: 'Took more than paid',
  took_less: 'Took less than paid',
  mixed: 'Mixed',
  unrecognised: 'Unrecognised',
  incomplete: 'Cannot judge',
}

// orderStatus 0 = the AI answered on its own; any other code = a person at Zijia settled it.
const processedByLabels = {
  ai: 'AI',
  human: 'Human',
}

const processedByTitles = {
  ai: 'AI status 0 (normal): the AI answered on its own',
  human: 'AI status not 0: the AI could not answer on its own, so a person settled it',
}

function processedByBadgeClass(processedBy) {
  return {
    ai: 'bg-indigo-50 text-indigo-700 border-indigo-300',
    human: 'bg-orange-50 text-orange-800 border-orange-300',
  }[processedBy] || 'bg-gray-100 text-gray-700 border-gray-300'
}

function statusBadgeClass(status) {
  return {
    pending: 'bg-gray-100 text-gray-700 border-gray-300',
    submitting: 'bg-amber-100 text-amber-800 border-amber-300',
    submitted: 'bg-blue-100 text-blue-800 border-blue-300',
    completed: 'bg-green-100 text-green-800 border-green-300',
    failed: 'bg-red-100 text-red-800 border-red-300',
  }[status] || 'bg-gray-100 text-gray-700 border-gray-300'
}

function verdictBadgeClass(verdict) {
  return {
    match: 'bg-green-100 text-green-800 border-green-300',
    took_more: 'bg-red-100 text-red-800 border-red-300',
    took_less: 'bg-amber-100 text-amber-800 border-amber-300',
    mixed: 'bg-red-100 text-red-800 border-red-300',
    unrecognised: 'bg-gray-100 text-gray-700 border-gray-300',
    incomplete: 'bg-gray-100 text-gray-700 border-gray-300',
  }[verdict] || 'bg-gray-100 text-gray-700 border-gray-300'
}

// "…-d1c3-25f-1280x720.mp4" → "Door 1 · Cam 3"; anything else → "Video n".
function videoLabel(url, index) {
  const m = url.match(/-d(\d+)c(\d+)-/)
  return m ? 'Door ' + m[1] + ' · Cam ' + m[2] : 'Video ' + (index + 1)
}

function saleUrl(sale) {
  const q = new URLSearchParams({ order_id: sale.order_id })
  if (sale.date) {
    q.set('date_from', sale.date)
    q.set('date_to', sale.date)
  }
  return '/vends/transactions?' + q.toString()
}

// Money is integer cents; divide only for display.
function formatCents(cents) {
  return (cents / 100).toFixed(2)
}

// The session's T05 hold, charged by the verdict (CardPaymentService::settleAwaitingAi).
function cardLabel(card) {
  if (card.uncertain_cents) return `T05 charge ${formatCents(card.uncertain_cents)} unconfirmed — check Payrallel`
  if (card.awaits_person) return `T05 hold ${formatCents(card.hold_cents)} · ${card.person_reason === 'ai_no_answer' ? 'AI never answered' : 'AI gave no verdict'} — key in the charge`
  if (card.state === 'awaiting_ai' && card.by_person) return `T05 ${formatCents(card.judged_cents)} keyed in · retrying`
  if (card.state === 'awaiting_ai') return `T05 hold ${formatCents(card.hold_cents)} · awaiting AI`
  if (card.state === 'captured') return `T05 charged ${formatCents(card.captured_cents)} (hold ${formatCents(card.hold_cents)})`
  if (card.state === 'voided') return `T05 hold ${formatCents(card.hold_cents)} released`
  return `T05 ${card.state}`
}

function cardBadgeClass(card) {
  if (card.uncertain_cents) return 'bg-red-50 text-red-700 border-red-300'
  if (card.awaits_person) return 'bg-indigo-50 text-indigo-800 border-indigo-300'
  if (card.state === 'awaiting_ai') return 'bg-amber-50 text-amber-800 border-amber-300'
  if (card.state === 'voided') return 'bg-gray-100 text-gray-700 border-gray-300'
  if (card.owed_cents) return 'bg-red-50 text-red-700 border-red-300'
  return 'bg-green-50 text-green-700 border-green-300'
}

const filters = ref({
  search: props.filters.search || '',
  codes: props.filters.codes || '',
  status: props.filters.status || 'all',
  verdict: props.filters.verdict || 'all',
  processed_by: props.filters.processed_by || 'all',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  time_from: props.filters.time_from || '',
  time_to: props.filters.time_to || '',
  sortKey: props.filters.sortKey || 'created_at',
  // Query-string round-trips turn the boolean into "true"/"false" strings.
  sortBy: String(props.filters.sortBy ?? false) === 'true',
  numberPerPage: 100,
})
const numberPerPageOptions = ref([])
const statusOptions = ref([])
const verdictOptions = ref([])
const processedByOptions = ref([])

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  statusOptions.value = [
    { id: 'all', name: 'All' },
    ...props.statuses.map((s) => ({ id: s, name: statusLabels[s] || s })),
  ]
  filters.value.status = statusOptions.value.find((o) => o.id === filters.value.status) || statusOptions.value[0]
  verdictOptions.value = [
    { id: 'all', name: 'All' },
    { id: 'none', name: 'No verdict yet' },
    ...props.verdicts.map((v) => ({ id: v, name: verdictLabels[v] || v })),
  ]
  filters.value.verdict = verdictOptions.value.find((o) => o.id === filters.value.verdict) || verdictOptions.value[0]
  processedByOptions.value = [
    { id: 'all', name: 'All' },
    { id: 'ai', name: processedByLabels.ai },
    { id: 'human', name: processedByLabels.human },
    { id: 'none', name: 'No result yet' },
  ]
  filters.value.processed_by = processedByOptions.value.find((o) => o.id === filters.value.processed_by) || processedByOptions.value[0]
})

// The legend is open until someone hides it; remembered per browser (storage may be blocked).
const showLegend = ref(true)
try {
  showLegend.value = localStorage.getItem('aiRecognition.legend') !== 'hidden'
} catch (e) {}

function toggleLegend() {
  showLegend.value = !showLegend.value
  try {
    localStorage.setItem('aiRecognition.legend', showLegend.value ? 'shown' : 'hidden')
  } catch (e) {}
}

// Key in charge: amount → confirm → POST. Money is integer cents; the typed text is parsed
// as a string, never through a float.
const charge = ref({ open: false, step: 1, row: null, amount: '', cents: 0, error: '', sending: false, answered: false })

function openCharge(row) {
  charge.value = { open: true, step: 1, row, amount: '', cents: 0, error: '', sending: false, answered: false }
}

// A sessionless hold, shaped like a table row for the popup.
function sessionlessRow(hold) {
  return {
    vend_label: hold.vend_label,
    session_ref: hold.session_ref,
    sale: null,
    purchased: [],
    algorithm_status: 'never answered',
    card: { id: hold.id, hold_cents: hold.hold_cents, person_reason: hold.person_reason },
  }
}

function closeCharge() {
  if (!charge.value.sending) charge.value.open = false
}

function parseCents(text) {
  const m = String(text).trim().match(/^(\d{1,6})(?:\.(\d{1,2}))?$/)
  if (!m) return null
  return parseInt(m[1], 10) * 100 + parseInt((m[2] || '0').padEnd(2, '0'), 10)
}

function nextCharge() {
  const cents = parseCents(charge.value.amount)
  if (cents === null) {
    charge.value.error = 'Key in an amount like 7.60 (0 releases the hold).'
    return
  }
  if (cents > 100000) {
    charge.value.error = 'That is above 1000.00 — check the amount.'
    return
  }
  charge.value.cents = cents
  charge.value.error = ''
  charge.value.step = 2
}

function submitCharge() {
  charge.value.sending = true
  router.post(`/ai-recognition/card-holds/${charge.value.row.card.id}/charge`, { amount_cents: charge.value.cents }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: (page) => {
      if (page.props.errors && page.props.errors.amount_cents) {
        charge.value.error = page.props.errors.amount_cents
        return
      }
      charge.value.open = false
    },
    // The server has the final word (refused / unknown / no longer waiting): nothing is resent from here.
    onError: (errors) => {
      charge.value.error = errors.amount_cents || 'The charge was not sent.'
      charge.value.answered = true
    },
    onFinish: () => {
      charge.value.sending = false
    },
  })
}

function onSearchFilterUpdated() {
  router.get('/ai-recognition', {
    ...filters.value,
    status: filters.value.status ? filters.value.status.id : 'all',
    verdict: filters.value.verdict ? filters.value.verdict.id : 'all',
    processed_by: filters.value.processed_by ? filters.value.processed_by.id : 'all',
    numberPerPage: filters.value.numberPerPage.id,
  }, {
    preserveState: true,
    replace: true,
  })
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}
</script>
