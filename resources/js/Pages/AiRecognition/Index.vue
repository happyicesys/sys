<template>

  <Head :title="$t('AI Recognition')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('AI Recognition') }}
        <span class="ml-2 align-middle inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border bg-sky-100 text-sky-800 border-sky-300">
          {{ $t('Smart Freezer') }}
        </span>
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div v-if="$page.props.flash.success" class="mb-2 rounded-md border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-800">
        {{ $page.props.flash.success }}
      </div>
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 my-3">
      <span class="relative z-10 -mb-px inline-block rounded-t-md border border-b-white bg-white px-4 py-1.5 text-sm font-semibold text-gray-800">{{ $t('AI Dashboard') }}</span>
      <div class="bg-white rounded-b-md rounded-tr-md border px-3 md:px-3 py-3">
        <span class="inline-flex items-center gap-1 text-xs text-gray-500" tabindex="0" v-tooltip="$t('Each row is one Smart Freezer door session: the camera videos Zijia pushed, what their AI saw taken, and how that compares with what the customer paid for. A sale paid on a T05 card terminal settles on its own only when the AI matches what was paid (charged) or saw nothing taken (the hold is released); anything else waits for a person to key in the charge. NETS and QR sales were charged at the machine.')">
          <InformationCircleIcon class="h-4 w-4" aria-hidden="true" />{{ $t('About this page') }}
        </span>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-7 gap-2 mt-3">
          <div class="md:col-span-2">
            <SearchInput :placeholderStr="$t('Order no / device / machine')" v-model="filters.search" @keyup.enter="onSearchFilterUpdated()">
              {{ $t('Search') }}
            </SearchInput>
          </div>
          <div class="md:col-span-2">
            <SearchInput :placeholderStr="$t('Machine ID (e.g. 50001, or 50001,2009)')" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
              {{ $t('Machine ID') }}
            </SearchInput>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              {{ $t('Status') }}
            </label>
            <MultiSelect
              v-model="filters.status"
              :options="statusOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              :placeholder="$t('Select')"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              {{ $t('Verdict') }}
            </label>
            <MultiSelect
              v-model="filters.verdict"
              :options="verdictOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              :placeholder="$t('Select')"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              {{ $t('Processed By') }}
            </label>
            <MultiSelect
              v-model="filters.processed_by"
              :options="processedByOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              :placeholder="$t('Select')"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div class="md:col-span-2">
            <DatePicker v-model="filters.date_from">
              {{ $t('Date From') }}
            </DatePicker>
          </div>
          <div class="md:col-span-2">
            <DatePicker v-model="filters.date_to" :minDate="filters.date_from">
              {{ $t('Date To') }}
            </DatePicker>
          </div>
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="onSearchFilterUpdated()"
            >
              <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
              <span>
                {{ $t('Search') }}
              </span>
            </Button>
          </div>
          <div class="flex flex-col space-y-2">
            <p class="text-sm text-gray-700 leading-5 flex space-x-1">
              <span>{{ $t('Showing') }}</span>
              <span class="font-medium">{{ recognitions.meta.from ?? 0 }}</span>
              <span>{{ $t('to') }}</span>
              <span class="font-medium">{{ recognitions.meta.to ?? 0 }}</span>
              <span>{{ $t('of') }}</span>
              <span class="font-medium">{{ recognitions.meta.total }}</span>
              <span>{{ $t('results') }}</span>
            </p>
            <MultiSelect
              v-model="filters.numberPerPage"
              :options="numberPerPageOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              :placeholder="$t('Select')"
              open-direction="bottom"
              class="mt-1"
              @selected="onSearchFilterUpdated"
            >
            </MultiSelect>
          </div>
        </div>

        <!-- Totals over everything the filters list. Each session lands in one outcome and its units in one count. -->
        <dl :class="['mt-4 grid grid-cols-1 gap-2 text-sm', dashboard.claude ? 'md:grid-cols-5' : 'md:grid-cols-4']">
          <div class="overflow-hidden rounded-lg bg-gray-100 px-4 py-3 shadow">
            <p class="text-base font-bold text-gray-800">{{ $t('Total Sales $') }}</p>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('What was collected: T05 charges, and sales paid at the machine.')">{{ $t('Charged $') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ formatCents(dashboard.charged_cents) }}</dd>
            </div>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('What went back to the card or the gateway. A claim paid by hand is not included.')">{{ $t('Refund $') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ formatCents(dashboard.refunded_cents) }}</dd>
            </div>
          </div>
          <div class="overflow-hidden rounded-lg bg-gray-100 px-4 py-3 shadow">
            <p class="text-base font-bold text-gray-800">{{ $t('Transaction Count') }}</p>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('Money was collected.')">{{ $t('Successful') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">
                {{ dashboard.successful.toLocaleString() }}
                <span v-if="dashboard.successful_pct !== null" class="text-xs font-normal text-gray-500">({{ dashboard.successful_pct.toFixed(2) }}%)</span>
              </dd>
            </div>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('The hold was released and nothing was charged.')">{{ $t('Void') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ dashboard.void.toLocaleString() }}</dd>
            </div>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('Nothing was collected and the door failed, or the session was paid but never became a sale.')">{{ $t('Machine error') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ dashboard.machine_error.toLocaleString() }}</dd>
            </div>
            <div v-if="dashboard.other" class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('Still waiting for a charge, or a door session with no payment.')">{{ $t('Other') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ dashboard.other.toLocaleString() }}</dd>
            </div>
            <div class="mt-1 flex justify-between gap-2 border-t border-gray-300 pt-1">
              <dt>{{ $t('Total count') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ dashboard.total.toLocaleString() }}</dd>
            </div>
          </div>
          <div class="overflow-hidden rounded-lg bg-gray-100 px-4 py-3 shadow">
            <p class="text-base font-bold text-gray-800">{{ $t('AI Accuracy') }}</p>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('Units the AI counted right: it matched what was paid, or a person\'s count agreed with it.')">{{ $t('Correct qty') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">
                {{ dashboard.correct_qty.toLocaleString() }}
                <span v-if="dashboard.correct_pct !== null" class="text-xs font-normal text-gray-500">({{ dashboard.correct_pct.toFixed(2) }}%)</span>
              </dd>
            </div>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('Units a person changed with Adjust Qty. One product swapped for another counts as one.')">{{ $t('Adjusted Qty') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ dashboard.adjusted_qty.toLocaleString() }}</dd>
            </div>
            <div v-if="dashboard.unchecked_qty" class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('Units where the AI differs from what was paid and nobody has adjusted the count yet. They do not count as correct.')">{{ $t('Not checked yet') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ dashboard.unchecked_qty.toLocaleString() }}</dd>
            </div>
            <div class="mt-1 flex justify-between gap-2 border-t border-gray-300 pt-1">
              <dt>{{ $t('Total Qty') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ dashboard.total_qty.toLocaleString() }}</dd>
            </div>
          </div>
          <div class="overflow-hidden rounded-lg bg-gray-100 px-4 py-3 shadow">
            <p class="text-base font-bold text-gray-800">{{ $t('Invoice QR') }}</p>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('Sales whose invoice QR was opened on a phone. We know the phone, not the person.')">{{ $t('Scanned') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">
                {{ dashboard.invoice_scanned.toLocaleString() }}
                <span v-if="dashboard.invoice_scanned_pct !== null" class="text-xs font-normal text-gray-500">({{ dashboard.invoice_scanned_pct.toFixed(2) }}%)</span>
              </dd>
            </div>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('The page showed the final amount on that phone. A T05 amount comes after the AI.')">{{ $t('Saw final amount') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ dashboard.invoice_final_seen.toLocaleString() }}</dd>
            </div>
            <div class="mt-1 flex justify-between gap-2 border-t border-gray-300 pt-1">
              <dt tabindex="0" v-tooltip="$t('Paid sales from freezer app 41 on. Testing Mode sales are left out.')">{{ $t('Paid sales') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ dashboard.invoice_sales.toLocaleString() }}</dd>
            </div>
          </div>
          <!-- Claude counts the same sessions beside Zijia, as a trial: it decides nothing (Brian, 2026-11-13). -->
          <div v-if="dashboard.claude" class="overflow-hidden rounded-lg bg-violet-50 px-4 py-3 shadow">
            <p class="text-base font-bold text-violet-900" tabindex="0" v-tooltip="$t('Claude counts the same videos as a trial, to compare with Zijia. It never charges, refunds or moves stock.')">{{ $t('Claude vs Zijia (trial)') }}</p>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('Sessions where the count equals what was paid for.')">{{ $t('Matches paid') }}:</dt>
              <dd class="text-right tabular-nums text-gray-900">
                <span class="font-semibold">{{ pctText(dashboard.claude.claude_match_paid_pct) }}</span>
                <span class="text-xs text-gray-500"> · {{ $t('Zijia :pct', { pct: pctText(dashboard.claude.zijia_match_paid_pct) }) }}</span>
              </dd>
            </div>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('Sessions a person counted with Adjust Qty, where the AI gave the same count. The person is taken as right.')">{{ $t('Right by a person') }}:</dt>
              <dd class="text-right tabular-nums text-gray-900">
                <span class="font-semibold">{{ dashboard.claude.claude_right }}/{{ dashboard.claude.person_counted }}</span>
                <span class="text-xs text-gray-500"> · {{ $t('Zijia :n', { n: dashboard.claude.zijia_right + '/' + dashboard.claude.zijia_person_counted }) }}</span>
              </dd>
            </div>
            <div class="mt-1 flex justify-between gap-2">
              <dt tabindex="0" v-tooltip="$t('Sessions both answered with the same products and units.')">{{ $t('Same count') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">{{ dashboard.claude.agree }}/{{ dashboard.claude.both_answered }}</dd>
            </div>
            <div class="mt-1 flex justify-between gap-2 border-t border-violet-200 pt-1">
              <dt tabindex="0" v-tooltip="$t('What the Claude calls cost, in Singapore dollars at the rate in the settings.')">{{ $t('Claude cost') }}:</dt>
              <dd class="font-semibold tabular-nums text-gray-900">S${{ dashboard.claude.cost_sgd.toFixed(2) }}</dd>
            </div>
          </div>
        </dl>
      </div>
      </div>

      <!-- T05 holds waiting for a person whose session has no row below (Zijia never pushed its videos). -->
      <!-- Which of each freezer's products the AI can name today (ZijiaSkuLinks); gaps are fixed on SKU Links. -->
      <div v-if="aiCoverage.length" class="-mx-3 sm:-mx-6 lg:-mx-8 mt-3 bg-white rounded-md border px-3 py-2">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
          <span class="font-medium text-gray-700">{{ $t('AI\'s product list') }}</span>
          <span v-for="machine in aiCoverage" :key="machine.vend_id" class="tabular-nums" :class="machine.missing.length ? 'text-amber-800 font-medium' : 'text-green-700'">
            <MachineIdLink :code="machine.code_label" />: {{ $t(':on_list of :total', { on_list: machine.on_list, total: machine.total }) }}
          </span>
          <Link href="/ai-recognition/sku-links" class="ml-auto font-medium text-indigo-600 hover:underline">{{ $t('SKU Links') }}</Link>
        </div>
      </div>

      <!-- The Claude trial beside Zijia: which model counts, roughly what a session costs, and whether it is on (Brian, 2026-11-13). -->
      <div v-if="claudeModel" class="-mx-3 sm:-mx-6 lg:-mx-8 mt-3 bg-violet-50 rounded-md border border-violet-200 px-3 py-2">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-violet-900">
          <span class="font-medium" tabindex="0" v-tooltip="$t('Claude counts the same videos as a trial, to compare with Zijia. It never charges, refunds or moves stock.')">{{ $t('Claude trial') }}</span>
          <span v-if="!claudeModel.on" class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-700" tabindex="0" v-tooltip="$t('Switched off on the server, or no Anthropic API key is set.')">{{ $t('Off') }}</span>
          <label for="claude-model" class="text-xs text-violet-800">{{ $t('Model') }}</label>
          <select id="claude-model" v-model="claudeModelPick" :disabled="!claudeModel.can_change || claudeModelSaving"
            class="rounded-md border-violet-300 py-1 pl-2 pr-8 text-sm disabled:bg-violet-50 disabled:text-violet-900" @change="saveClaudeModel">
            <option v-for="option in claudeModel.options" :key="option.id" :value="option.id">
              {{ option.estimate_sgd !== null ? $t(':model · about S$:cost a session', { model: option.label, cost: option.estimate_sgd.toFixed(3) }) : option.label }}
            </option>
          </select>
          <span v-if="claudeModel.set_by" class="text-xs text-violet-700">{{ $t('Set by :name, :time', { name: claudeModel.set_by, time: claudeModel.set_at }) }}</span>
        </div>
      </div>

      <div v-if="sessionlessHolds.length" class="-mx-3 sm:-mx-6 lg:-mx-8 mt-3 bg-amber-50 rounded-md border border-amber-300 px-3 py-2">
        <p class="text-sm font-medium text-amber-900">
          {{ $t('T05 card holds with no AI session (:count)', { count: sessionlessHolds.length }) }}
          <span class="font-normal">{{ $t('— the AI never received this door session, so nothing is charged until someone keys it in.') }}</span>
        </p>
        <div class="mt-2 flex flex-col divide-y divide-amber-200 text-xs">
          <div v-for="hold in sessionlessHolds" :key="hold.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 py-1.5">
            <span class="font-medium"><MachineIdLink :code="hold.vend_label" /></span>
            <span class="font-mono">{{ hold.session_ref }}</span>
            <span>{{ $t('hold :amount', { amount: formatCents(hold.hold_cents) }) }}</span>
            <span class="text-gray-600">{{ $t('door closed :time', { time: hold.door_closed_at }) }}</span>
            <button v-if="canCharge" type="button"
              class="ml-auto inline-flex items-center rounded-md border border-indigo-600 bg-indigo-600 px-2 py-1 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 whitespace-nowrap"
              @click="openCharge(sessionlessRow(hold))"
            >
              {{ $t('Key in charge') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Legend: Zijia's orderStatus codes (§6.2), what they mean, and who settled the session. -->
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 mt-3 bg-white rounded-md border px-3 py-2">
        <button type="button" class="flex w-full items-center justify-between text-sm font-medium text-gray-700" @click="toggleLegend">
          <span>{{ $t('AI status and door error codes') }} <span class="font-normal text-gray-500">{{ $t('— what "AI: …" in the Status column and "Door error" in Verification mean') }}</span></span>
          <span class="text-xs text-blue-600">{{ showLegend ? $t('Hide') : $t('Show') }}</span>
        </button>
        <div v-if="showLegend" class="mt-2 overflow-x-auto">
          <table class="min-w-full text-xs">
            <thead>
              <tr class="text-left text-gray-500">
                <th class="py-1 pr-3 font-medium">{{ $t('Code') }}</th>
                <th class="py-1 pr-3 font-medium">{{ $t('Zijia') }}</th>
                <th class="py-1 pr-3 font-medium">{{ $t('Shown as') }}</th>
                <th class="py-1 pr-3 font-medium">{{ $t('In plain words') }}</th>
                <th class="py-1 font-medium">{{ $t('Processed by') }}</th>
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
                    {{ $t(processedByLabels[item.processed_by]) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
          <table class="mt-3 min-w-full text-xs">
            <thead>
              <tr class="text-left text-gray-500">
                <th class="py-1 pr-3 font-medium">{{ $t('Door error') }}</th>
                <th class="py-1 font-medium">{{ $t('In plain words') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="item in doorFaultLegend" :key="item.code">
                <td class="py-1 pr-3 font-mono font-semibold">{{ item.code }}</td>
                <td class="py-1 text-gray-600">{{ item.plain }}</td>
              </tr>
            </tbody>
          </table>
          <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600">
            <span class="font-semibold">{{ $t('Verdict') }}:</span>
            <span class="inline-flex items-center gap-1"><CheckCircleIcon class="h-4 w-4 text-green-600" aria-hidden="true" />{{ $t(CHECK_KINDS.ok.meaning) }}</span>
            <span class="inline-flex items-center gap-1"><span class="rounded-full bg-red-100 px-1.5 font-bold text-red-700">+1</span>{{ $t(CHECK_KINDS.extra.meaning) }}</span>
            <span class="inline-flex items-center gap-1"><span class="rounded-full bg-amber-100 px-1.5 font-bold text-amber-800">−1</span>{{ $t(CHECK_KINDS.missing.meaning) }}</span>
            <span class="inline-flex items-center gap-1"><QuestionMarkCircleIcon class="h-4 w-4 text-gray-400" aria-hidden="true" />{{ $t(CHECK_KINDS.unknown.meaning) }}</span>
          </p>
          <p class="mt-2 text-xs text-gray-500">
            {{ $t('A finer reason may follow the dash, e.g. "recognition error — 503 goods not listed in the model (商品未上架)": Zijia\'s jsOrderStatus, using the same codes. Any code other than 0 leaves a T05 card hold for a person to key in the charge.') }}
          </p>
        </div>
      </div>

      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-3 sm:-mx-6 lg:-mx-8">
          <!-- Shorter than the screen by the page footer, so the page cannot scroll the pinned header out of sight. -->
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll max-h-[calc(100vh-1rem)]">
            <p v-if="correctionNotice" class="mb-2 rounded-md border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-800">{{ correctionNotice }}</p>
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <TableHead>
                    #
                  </TableHead>
                  <TableHeadSort modelName="transaction_time" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('transaction_time')">
                    {{ $t('Transaction DateTime') }}
                  </TableHeadSort>
                  <TableHead>
                    {{ $t('Order ID') }}
                  </TableHead>
                  <TableHead>
                    {{ $t('Machine / Site / RP') }}
                  </TableHead>
                  <TableHeadSort modelName="created_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('created_at')">
                    <span :title="$t('When the door videos reached us, usually minutes after the sale')">{{ $t('Videos Received') }}</span>
                  </TableHeadSort>
                  <TableHeadSort modelName="status" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('status')">
                    {{ $t('Status') }}
                  </TableHeadSort>
                  <TableHeadSort modelName="verdict" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('verdict')">
                    {{ $t('Verification') }}
                  </TableHeadSort>
                  <TableHead>
                    <span class="text-violet-800" tabindex="0" v-tooltip="$t('Claude counts the same videos as a trial, to compare with Zijia. It never charges, refunds or moves stock.')">{{ $t('Claude Verdict') }}</span>
                  </TableHead>
                  <TableHead>
                    {{ $t('Sale / Card charge') }}
                  </TableHead>
                  <TableHead>
                    {{ $t('Settlement') }}
                  </TableHead>
                  <TableHead>
                    {{ $t("Customer's copy") }}
                  </TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <!-- Door closed, Zijia's videos not here yet: no AI row exists until they are (Brian, 2026-11-05). -->
                <tr v-for="sale in waitingForVideo" :key="'waiting-' + sale.id" class="divide-x divide-y-2 divide-gray-300 bg-sky-50 align-top text-sm">
                  <td class="px-3 py-2 text-center text-gray-400">—</td>
                  <td class="px-3 py-2 text-center whitespace-nowrap">{{ sale.time }}</td>
                  <td class="px-3 py-2 text-xs">
                    <span class="block font-mono text-sm font-semibold text-gray-900 whitespace-nowrap">{{ sale.order_id }}</span>
                    <span v-if="sale.is_test_sale" class="inline-flex w-fit items-center rounded border border-fuchsia-300 bg-fuchsia-100 px-1 text-[10px] font-bold text-fuchsia-800">{{ $t('TEST') }}</span>
                    <span class="block whitespace-nowrap"><span class="text-gray-500">{{ $t('Ours:') }}</span> <span class="font-mono">{{ sale.session_ref }}</span></span>
                  </td>
                  <td class="px-3 py-2 font-medium"><MachineIdLink :code="sale.vend_label" /></td>
                  <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap">
                    {{ $t('Waiting for video') }}
                    <span class="block text-gray-500">{{ $t(':count min so far', { count: sale.waited_minutes }) }}</span>
                  </td>
                  <td class="px-3 py-2"><span class="inline-flex rounded border border-sky-300 bg-sky-100 px-1.5 py-0.5 text-xs font-semibold text-sky-800 whitespace-nowrap">{{ $t('Waiting') }}</span></td>
                  <td class="px-3 py-2 text-xs">
                    <span v-for="item in sale.items" :key="item.name" class="block">{{ item.qty }} × {{ item.name }}</span>
                    <span class="mt-1 block text-gray-500">{{ $t('The AI check starts when the video arrives.') }}</span>
                  </td>
                  <td class="px-3 py-2"></td>
                  <td class="px-3 py-2 text-center font-medium">{{ formatCents(sale.amount_cents) }}</td>
                  <td class="px-3 py-2"></td>
                </tr>
                <tr v-for="(row, rowIndex) in recognitions.data" :key="row.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100 align-top">
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    {{ recognitions.meta.from + rowIndex }}
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    <span v-if="row.sale && row.sale.date" class="whitespace-nowrap">{{ row.sale.date }} {{ row.sale.time }}</span>
                    <span v-else class="text-gray-400" :title="$t('Shown once the sale is matched to this session')">—</span>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div class="flex flex-col space-y-1 text-xs">
                      <a v-if="row.sale" :href="saleUrl(row.sale)" target="_blank" class="text-sm font-semibold text-blue-600 hover:underline font-mono whitespace-nowrap">{{ row.sale.order_id }}</a>
                      <span v-else class="text-gray-400" :title="$t('Shown once the sale is matched to this session')">—</span>
                      <span v-if="row.sale?.is_test_sale"
                        class="inline-flex w-fit items-center rounded border border-fuchsia-300 bg-fuchsia-100 px-1 text-[10px] font-bold text-fuchsia-800 hover:cursor-help"
                        :title="$t('Testing Mode sale: every item $0.20, no stock deducted')">{{ $t('TEST') }}</span>
                      <span class="whitespace-nowrap" :title="$t('Zijia\'s order no for the door session (tradeId sent to the AI)')">
                        <span class="text-gray-500">Zijia:</span> <span class="font-mono">{{ row.trade_id }}</span>
                      </span>
                      <span v-if="row.session_ref" class="whitespace-nowrap" :title="$t('Our txnRef (SFREF on the TRADE)')">
                        <span class="text-gray-500">{{ $t('Ours:') }}</span> <span class="font-mono">{{ row.session_ref }}</span>
                      </span>
                      <span v-if="row.door_try > 1 && row.status !== 'skipped'"
                        class="inline-flex w-fit items-center whitespace-nowrap rounded border border-amber-300 bg-amber-50 px-1 text-[10px] font-bold text-amber-800 hover:cursor-help"
                        :title="$t('The lock did not open at first; the freezer tried again and the door opened on this try. Only this try was sent to the AI.')">{{ $t('Door opened on try :n', { n: row.door_try }) }}</span>
                      <span v-if="row.status === 'skipped' && row.door_try" class="whitespace-nowrap text-[10px] text-gray-600">{{ $t('Door never opened on this try') }}</span>
                      <span v-if="row.request_id" class="whitespace-nowrap" :title="$t('The AI service\'s request id')">
                        <span class="text-gray-500">{{ $t('AI req:') }}</span> <span class="font-mono">{{ row.request_id }}</span>
                      </span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div class="flex w-32 flex-col space-y-1">
                      <span v-if="row.vend_label" class="font-medium"><MachineIdLink :code="row.vend_label" /></span>
                      <span v-else class="text-amber-700 text-xs" :title="$t('The push named no freezer of ours')">{{ $t('Not matched') }}</span>
                      <span v-if="row.device_id" class="text-xs text-gray-500" :title="$t('Device no / IMEI in the push')">{{ row.device_id }}</span>
                    </div>
                    <div v-if="row.site" class="mt-2 flex w-32 flex-col space-y-1 whitespace-normal">
                      <span class="text-xs">{{ row.site.name }}</span>
                      <span v-if="row.site.rp" class="inline-flex w-fit items-center rounded border border-gray-300 bg-white px-1.5 py-0.5 text-xs font-semibold text-gray-700">{{ row.site.rp }}</span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    <div class="flex flex-col space-y-1">
                      <span class="whitespace-nowrap">{{ row.created_at }}</span>
                      <span v-if="row.completed_at" class="text-xs text-gray-500" :title="$t('When the AI result came back')">
                        {{ $t('result :time', { time: row.completed_at.substring(11) }) }}
                      </span>
                      <span v-if="row.result_seconds !== null" class="text-xs text-gray-500 whitespace-nowrap" tabindex="0" v-tooltip="$t('How long the AI result took, from when the videos reached us')">Δ {{ durationText(row.result_seconds) }}</span>
                    </div>
                    <div class="mt-2 flex flex-col space-y-1 text-xs">
                      <a v-for="(url, urlIndex) in row.videos" :key="url" :href="row.video_links?.[url]?.href ?? url" target="_blank" rel="noopener noreferrer"
                        class="text-blue-600 hover:underline whitespace-nowrap"
                        :title="$t('Zijia\'s video, sent to the AI')"
                      >
                        ▶ {{ videoLabel(url, urlIndex) }}<span v-if="row.video_links?.[url]?.seconds != null"> · {{ row.video_links[url].seconds }}s</span>
                      </a>
                      <a v-for="clip in row.clips" :key="'clip-' + clip.id" :href="clip.url" target="_blank" rel="noopener noreferrer"
                        class="text-emerald-700 hover:underline whitespace-nowrap"
                        :title="$t('Our own recording (not sent to the AI), from payment until the door closed')"
                      >
                        ▶ {{ $t('Ours · Cam :camera', { camera: clip.camera_id }) }}<span v-if="clip.seconds !== null"> · {{ clip.seconds }}s</span>
                      </a>
                      <span v-if="!row.videos.length && !row.clips.length" class="text-gray-400">—</span>
                    </div>
                  </TableData>
                  <!-- Kept narrow: the long AI reason lives in a hover popup, Verification gets the room. An open "Sent to AI" list widens it. -->
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div class="flex w-24 flex-col space-y-1">
                      <span class="inline-flex w-fit items-center rounded px-1.5 py-0.5 text-xs font-bold border" :class="statusBadgeClass(row.status)">
                        {{ statusLabels[row.status] ? $t(statusLabels[row.status]) : row.status }}
                      </span>
                      <span v-if="row.processed_by" class="inline-flex w-fit items-center whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-bold border"
                        :class="processedByBadgeClass(row.processed_by)" :title="$t(processedByTitles[row.processed_by])"
                      >
                        {{ $t(processedByLabels[row.processed_by]) }}
                      </span>
                      <span v-if="row.algorithm_status" class="text-xs text-gray-700" tabindex="0" v-tooltip="$t('The AI\'s own status') + ': ' + row.algorithm_status">{{ $t('AI: :status', { status: row.algorithm_status.split(' — ')[0] }) }}</span>
                      <InformationCircleIcon v-if="row.status_reason && row.status_reason !== ('algorithm: ' + row.algorithm_status)" class="h-4 w-4 text-gray-400" tabindex="0" :aria-label="row.status_reason" v-tooltip="row.status_reason" />
                      <span v-if="row.callback_verified === false" class="text-xs text-red-700">{{ $t('Result signature NOT verified') }}</span>
                    </div>
                    <!-- The product list sent with the videos (goodsList): the AI can only name a product
                         on it. Paid-for products are marked, and so is what the AI saw taken. -->
                    <div v-if="row.submitted" class="mt-2 flex flex-col space-y-1 text-xs">
                      <span class="text-gray-500">{{ $t('Sent to AI') }}</span>
                      <button type="button" class="inline-flex w-fit items-center whitespace-nowrap rounded border border-gray-300 bg-white px-1.5 py-0.5 font-semibold text-gray-700 hover:bg-gray-50"
                        :title="$t('The products sent to the AI with this door session: it can only name a product on this list')"
                        @click="toggleSubmitted(row.id)"
                      >
                        {{ $tc(':count product|:count products', row.submitted.length) }} {{ openSubmitted.has(row.id) ? '▴' : '▾' }}
                      </button>
                      <template v-if="openSubmitted.has(row.id)">
                        <span v-for="good in row.submitted" :key="good.code" class="whitespace-nowrap"
                          :class="paidBarcodes(row).has(good.code) ? 'font-semibold text-gray-900' : 'text-gray-600'"
                        >
                          {{ good.name || $t('Not a ConnectVend product') }}
                          <span class="text-gray-400 font-mono">{{ good.code }}</span>
                          <span v-if="paidBarcodes(row).has(good.code)" class="ml-1 inline-flex rounded px-1 text-[10px] font-semibold bg-sky-50 text-sky-800 border border-sky-300">{{ $t('paid') }}</span>
                          <span v-if="seenBarcodes(row).has(good.code)" class="ml-1 inline-flex rounded px-1 text-[10px] font-semibold bg-violet-50 text-violet-800 border border-violet-300">{{ $t('AI saw') }}</span>
                        </span>
                      </template>
                    </div>
                  </TableData>
                  <!-- Verification: one line per product (Brian's user, 2026-11-03): photo, price, paid, AI saw, verdict, and a person's count once adjusted. -->
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <span v-if="verdictNote(row)" class="mb-1 inline-flex w-fit items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700" tabindex="0" v-tooltip="row.status_reason || row.algorithm_status || ''">
                      <QuestionMarkCircleIcon class="h-4 w-4" aria-hidden="true" />{{ $t(verdictNote(row)) }}
                    </span>
                    <!-- Fixed widths, so every row's columns line up down the page whatever its contents. -->
                    <table v-if="row.ai_check.length" class="w-[24rem] table-fixed text-xs">
                      <colgroup>
                        <col class="w-14" /><col class="w-[5.5rem]" /><col class="w-12" /><col class="w-14" /><col class="w-14" /><col class="w-[4.5rem]" />
                      </colgroup>
                      <thead class="text-[11px] font-medium text-gray-500">
                        <tr>
                          <th class="pb-1 text-left font-medium">{{ $t('Item') }}</th>
                          <th class="pb-1 text-center font-medium">{{ $t('Price') }}</th>
                          <th class="pb-1 text-center font-medium">{{ $t('Paid') }}</th>
                          <th class="pb-1 text-center font-medium">{{ $t('AI saw') }}</th>
                          <th class="pb-1 text-center font-medium">{{ $t('Verdict') }}</th>
                          <th class="pb-1 text-center font-medium text-blue-700">{{ $t('Adjusted') }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="line in row.ai_check" :key="line.product_id || line.name" class="border-t border-gray-100">
                          <td class="py-1" tabindex="0" v-tooltip="line.code ? line.code + ' · ' + line.name : line.name">
                            <img v-if="line.photo" :src="line.photo" :alt="line.name" loading="lazy" class="h-12 w-12 rounded object-contain bg-white" />
                            <span v-else class="flex h-12 w-12 items-center justify-center rounded bg-gray-100 text-center text-[10px] leading-tight text-gray-600 overflow-hidden">{{ line.code || line.name }}</span>
                          </td>
                          <td class="py-1 text-center text-gray-600">
                            {{ line.unit_cents !== null ? formatCents(line.unit_cents) : '—' }}
                            <span v-if="line.happy_hour" class="mx-auto mt-0.5 flex w-fit items-center whitespace-nowrap rounded border border-amber-300 bg-amber-100 px-1 py-0.5 text-[10px] font-semibold leading-none text-amber-800" tabindex="0" v-tooltip="$t('Happy Hour')">★ {{ line.happy_hour }}</span>
                          </td>
                          <td class="py-1 text-center text-sm">{{ line.paid }}</td>
                          <td class="py-1 text-center text-sm">
                            <span v-if="line.state === 'na'" class="text-gray-400">{{ $t('N/A') }}</span>
                            <template v-else>{{ line.taken !== null ? line.taken : '—' }}</template>
                          </td>
                          <td class="py-1 text-center" tabindex="0" v-tooltip="$t(CHECK_KINDS[line.state].meaning)">
                            <CheckCircleIcon v-if="line.state === 'ok'" class="mx-auto h-5 w-5 text-green-600" :aria-label="$t(CHECK_KINDS.ok.meaning)" />
                            <QuestionMarkCircleIcon v-else-if="line.state === 'unknown'" class="mx-auto h-5 w-5 text-gray-400" :aria-label="$t(CHECK_KINDS.unknown.meaning)" />
                            <span v-else-if="line.state === 'extra'" :class="['inline-flex min-w-[2rem] justify-center rounded-full px-1.5 py-0.5 text-sm font-bold', needsPerson(row) ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500']">+{{ line.delta }}</span>
                            <span v-else-if="line.state === 'missing'" :class="['inline-flex min-w-[2rem] justify-center rounded-full px-1.5 py-0.5 text-sm font-bold', needsPerson(row) ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500']">−{{ -line.delta }}</span>
                            <span v-else-if="line.state === 'none'" class="text-gray-400">—</span>
                            <span v-else-if="line.state === 'na'" class="text-gray-400">{{ $t('N/A') }}</span>
                            <span v-else class="text-gray-400">…</span>
                          </td>
                          <td class="py-1 text-center text-sm">
                            <!-- Blue only where the person's count differs from the AI's; equal = they confirmed it (Brian, 2026-11-12). -->
                            <span v-if="line.adjusted !== null && line.adjusted !== line.taken" class="inline-flex min-w-[2rem] justify-center rounded-full bg-blue-600 px-1.5 py-0.5 text-sm font-bold text-white" tabindex="0" v-tooltip="$t('Changed from what the AI saw')">{{ line.adjusted }}</span>
                            <span v-else-if="line.adjusted !== null" class="inline-flex min-w-[2rem] justify-center rounded-full border border-gray-300 bg-white px-1.5 py-0.5 text-sm font-bold text-gray-700" tabindex="0" v-tooltip="$t('Same as what the AI saw')">{{ line.adjusted }}</span>
                            <span v-else-if="line.state === 'na'" class="text-gray-400">{{ $t('N/A') }}</span>
                            <span v-else class="text-gray-400" tabindex="0" v-tooltip="$t('Not adjusted')">—</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <span v-else-if="row.algorithm_status !== null" class="text-xs text-gray-500">{{ $t('Nothing taken') }}</span>
                    <span v-else class="text-gray-400">—</span>
                    <!-- Paid, but the session never became a sale. The cart is the table above once the machine has reported it (app 48+); until then only the hold's amount is known. -->
                    <div v-if="row.paid_without_sale || row.failed_session || row.door_error" class="mt-1 flex flex-col space-y-0.5 text-xs">
                      <span v-if="row.paid_without_sale" class="font-semibold text-gray-900">{{ row.paid_without_sale.method ? $t('Card approved :amount · :method', { amount: formatCents(row.card.hold_cents), method: row.paid_without_sale.method }) : $t('Card approved :amount', { amount: formatCents(row.card.hold_cents) }) }}</span>
                      <span v-else-if="row.failed_session" class="font-semibold text-gray-900">{{ $t('Payment approved :amount · :method', { amount: formatCents(row.failed_session.amount_cents), method: row.failed_session.method }) }}</span>
                      <span v-if="row.paid_without_sale && !row.failed_session" class="text-gray-500">{{ $t('Items stay on the machine, not sent to ConnectVend') }}</span>
                      <!-- The code is in the legend above the table; the rest is the machine's own words. -->
                      <span v-if="row.door_error" class="cursor-help rounded border border-red-200 bg-red-50 px-1.5 py-1 font-semibold text-red-800" tabindex="0" v-tooltip="row.door_error.plain">{{ $t('Door error :time', { time: row.door_error.at }) }}: <span class="font-mono">{{ row.door_error.code }}</span><template v-if="row.door_error.text"> — {{ row.door_error.text }}</template></span>
                      <span v-else class="cursor-help text-amber-700" tabindex="0" v-tooltip="$t('The app files one door error every 2 minutes, so a failure soon after another is not logged.')">{{ $t('Door did not complete · no error logged') }}</span>
                      <!-- How the failed door went (Brian, 2026-11-12): tries, how long the customer waited, the sorry page. App 57+ reports every step on the machine's clock; older apps only the tries their error line names. -->
                      <div v-if="row.door_timeline" class="rounded border border-gray-200 bg-gray-50 px-1.5 py-1 text-gray-700">
                        <div class="flex flex-wrap gap-x-2 font-semibold text-gray-900">
                          <span v-if="row.door_timeline.tries">{{ $tc('Door tried :count time|Door tried :count times', row.door_timeline.tries) }}</span>
                          <span v-else class="cursor-help text-gray-500" tabindex="0" v-tooltip="$t('The app did not report its door tries. Freezer app 57 and later reports every try.')">{{ $t('Door tries not reported') }}</span>
                          <span v-if="row.door_timeline.waited">· {{ $t('customer waited :seconds', { seconds: row.door_timeline.waited }) }}</span>
                          <span v-if="row.door_timeline.sorry_at">· {{ $t('sorry page :time', { time: row.door_timeline.sorry_at }) }}</span>
                        </div>
                        <ol v-if="row.door_timeline.steps.length" class="mt-0.5 space-y-0.5" :aria-label="$t('Door steps, timed from the first door order')">
                          <li v-for="(step, i) in row.door_timeline.steps" :key="i" class="flex gap-2"
                            :class="{ 'text-red-700': ['refused', 'not_delivered', 'unlock_failed', 'gave_up'].includes(step.kind), 'font-semibold': step.kind === 'sorry' }">
                            <span class="w-12 flex-none text-right font-mono tabular-nums text-gray-500">{{ step.offset }}</span>
                            <span>{{ step.text }}</span>
                          </li>
                        </ol>
                        <div v-if="row.door_timeline.card_approved_at || row.door_timeline.card_released_at" class="mt-0.5 cursor-help text-gray-500" tabindex="0" v-tooltip="$t('Card times are on the ConnectVend clock; the door steps are on the machine clock, which can be a few seconds apart.')">
                          <span v-if="row.door_timeline.card_approved_at">{{ $t('Card approved :time', { time: row.door_timeline.card_approved_at }) }}</span>
                          <span v-if="row.door_timeline.card_released_at"> · {{ $t('hold released :time', { time: row.door_timeline.card_released_at }) }}</span>
                        </div>
                      </div>
                    </div>
                    <!-- A person's count sits beside the AI's verdict, which is never overwritten. -->
                    <!-- A count equal to the AI's confirms it: white, so the row never reads as the AI being wrong (Brian, 2026-11-12). -->
                    <div v-if="row.correction" class="mt-1 flex flex-wrap items-center gap-x-2 rounded border px-1.5 py-1 text-xs"
                      :class="row.correction.confirms_ai ? 'border-gray-300 bg-white text-gray-700' : 'border-blue-700 bg-blue-600 text-white'">
                      <span v-if="row.correction.confirms_ai" class="flex items-center gap-1 font-semibold" tabindex="0" v-tooltip="$t('The count a person keyed in is the same as what the AI saw: the AI was right.')"><CheckCircleIcon class="h-4 w-4 flex-none text-green-600" aria-hidden="true" />{{ $t('Confirmed by :name', { name: row.correction.by }) }}</span>
                      <span v-else class="flex items-center gap-1 font-semibold"><PencilSquareIcon class="h-4 w-4 flex-none" aria-hidden="true" />{{ $t('Adjusted by :name', { name: row.correction.by }) }}</span>
                      <span class="whitespace-nowrap">{{ row.correction.at }}</span>
                    </div>
                  </TableData>
                  <!-- Claude Verdict (Brian, 2026-11-13): Claude's count of the same videos, a trial beside Zijia's. It decides nothing:
                       Adjust Qty, the T05 charge and stock still follow Zijia's answer. -->
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div v-if="row.claude" class="flex w-56 flex-col gap-1 text-xs text-violet-900">
                      <template v-if="row.claude.status === 'done'">
                        <span v-if="row.claude.verdict" :class="['inline-flex w-fit items-center rounded-full px-2 py-0.5 font-semibold', verdictBadgeClass(row.claude.verdict)]">{{ $t(verdictLabels[row.claude.verdict] || row.claude.verdict) }}</span>
                        <span v-else class="w-fit cursor-help rounded-full bg-gray-100 px-2 py-0.5 text-gray-600" tabindex="0" v-tooltip="row.claude.reason || ''">{{ $t('Waiting for the sale') }}</span>
                        <span v-for="item in row.claude.items" :key="item.product_id">{{ item.number }} × {{ item.name }}</span>
                        <span v-for="(item, i) in row.claude.unknown" :key="'u' + i" class="text-amber-800">{{ item.taken }} × {{ item.description }}</span>
                        <span v-if="!row.claude.items.length && !row.claude.unknown.length" class="text-gray-600">{{ $t('Nothing taken') }}</span>
                        <span v-if="row.claude.same_as_zijia === true" class="font-semibold text-green-700">{{ $t('Same as Zijia') }}</span>
                        <span v-else-if="row.claude.same_as_zijia === false" class="font-semibold text-red-700">{{ $t('Differs from Zijia') }}</span>
                        <span v-if="row.claude.same_as_person === true" class="font-semibold text-green-700">{{ $t('Same as the person\'s count') }}</span>
                        <span v-else-if="row.claude.same_as_person === false" class="font-semibold text-red-700">{{ $t('Differs from the person\'s count') }}</span>
                        <span class="flex flex-wrap gap-x-1.5 text-violet-700">
                          <span v-if="row.claude.confidence !== null" class="cursor-help" tabindex="0" v-tooltip="row.claude.notes || ''">{{ $t('sure :pct%', { pct: row.claude.confidence }) }}</span>
                          <span v-if="row.claude.needs_review" class="text-amber-800">· {{ $t('wants a person') }}</span>
                        </span>
                      </template>
                      <span v-else-if="row.claude.status === 'pending' || row.claude.status === 'running'" class="text-gray-500">{{ $t('counting…') }}</span>
                      <span v-else class="w-fit cursor-help text-gray-500" tabindex="0" v-tooltip="row.claude.reason || ''">{{ row.claude.status === 'skipped' ? $t('not sent') : $t('failed') }}</span>
                      <span v-if="row.claude.model || row.claude.cost_sgd !== null" class="text-gray-500">
                        {{ (row.claude.model || '').replace(/^claude-/, '') }}<template v-if="row.claude.cost_sgd !== null"> · S${{ row.claude.cost_sgd }}</template>
                      </span>
                    </div>
                    <span v-else class="text-gray-400" tabindex="0" v-tooltip="$t('Claude did not count this session.')">—</span>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    <div v-if="row.sale" class="flex flex-col space-y-1 text-xs">
                      <span>{{ formatCents(row.sale.amount) }}</span>
                    </div>
                    <span v-else class="text-gray-400">—</span>
                    <div v-if="row.card" class="mt-1 flex flex-col space-y-0.5 text-xs" :title="row.card.reason || ''">
                      <span class="inline-flex w-fit mx-auto items-center whitespace-nowrap rounded px-1.5 py-0.5 font-bold border" :class="cardBadgeClass(row.card)">
                        {{ cardLabel(row.card) }}
                      </span>
                      <span v-if="row.card.increment_refused" class="text-gray-600 whitespace-nowrap">{{ $t('Payrallel refused the increase; hold charged') }}</span>
                      <span v-if="row.card.owed_cents" class="text-red-700 whitespace-nowrap">{{ $t('Not charged yet :amount', { amount: formatCents(row.card.owed_cents) }) }}</span>
                      <span v-if="row.card.error" class="text-red-700">{{ row.card.error }}</span>
                      <span v-if="row.card.by_person" class="mx-auto inline-flex w-fit items-center whitespace-nowrap rounded border border-blue-700 bg-blue-600 px-1.5 py-0.5 font-bold text-white">{{ $t('Keyed in by :name', { name: row.card.by_person }) }}</span>
                      <span v-for="refund in row.card.refunds" :key="refund.id" class="text-rose-700 whitespace-nowrap">
                        {{ $t('Refunded :amount', { amount: formatCents(refund.amount_cents) }) }}
                        <span class="block text-gray-600">{{ refund.at }} · {{ refund.by }}</span>
                      </span>
                      <button v-if="row.card.state === 'captured' && canRefund" type="button"
                        class="mx-auto mt-1 inline-flex items-center rounded-md border px-2 py-1 text-xs font-semibold whitespace-nowrap"
                        :class="refundButtonClass(row)"
                        @click="refundIntentId = row.card.id"
                      >
                        {{ $t('Refund') }}
                      </button>
                      <!-- Anything but a matching verdict: the hold waits for a person, never charged on its own. -->
                      <button v-if="row.card.awaits_person && canCharge && !row.can_correct" type="button"
                        class="mx-auto mt-1 inline-flex items-center rounded-md border border-indigo-600 bg-indigo-600 px-2 py-1 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 whitespace-nowrap"
                        @click="openCharge(row)"
                      >
                        {{ $t('Key in charge') }}
                      </button>
                    </div>
                    <span v-if="row.correction && row.correction.to_refund_cents" class="mt-1 block text-xs font-semibold text-rose-700 whitespace-nowrap">{{ $t('To refund :amount', { amount: formatCents(row.correction.to_refund_cents) }) }}</span>
                    <button v-if="row.can_correct && canCharge" type="button"
                      class="mx-auto mt-1 inline-flex items-center rounded-md border px-2 py-1 text-xs font-semibold whitespace-nowrap"
                      :class="adjustButtonClass(row)"
                      @click="correctRow = row"
                    >
                      {{ $t('Adjust Qty') }}
                    </button>
                  </TableData>
                  <!-- Left blank on purpose (Brian, 2026-11-05: he builds it later). See T05_SETTLEMENT_PLAN_2026-11-05.md. -->
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center"></TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div v-if="row.invoice.opened_at" class="flex w-28 flex-col space-y-0.5 text-xs">
                      <span class="w-fit rounded border border-green-300 bg-green-100 px-1.5 py-0.5 font-bold text-green-800">{{ $t('Scanned') }}</span>
                      <span class="text-gray-700">{{ row.invoice.opened_at }}</span>
                      <span v-if="row.invoice.device" class="text-gray-500">{{ row.invoice.device === 'Other' ? $t('Other') : row.invoice.device }}<template v-if="row.invoice.opens > 1"> · {{ $t(':count opens', { count: row.invoice.opens }) }}</template></span>
                      <!-- A row with no device was opened before this was recorded, so what it saw is unknown. -->
                      <span v-if="row.invoice.final_seen_at" class="text-green-700">{{ $t('Saw final amount') }}</span>
                      <span v-else-if="row.invoice.device" class="text-amber-700">{{ $t('Final amount not seen') }}</span>
                    </div>
                    <span v-else-if="row.invoice.offered" class="whitespace-nowrap text-xs text-gray-500">{{ $t('Not scanned') }}</span>
                    <span v-else class="text-gray-400">—</span>
                  </TableData>
                </tr>
                <tr v-if="!recognitions.data.length">
                  <td colspan="11" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                    {{ $t('No Results Found') }}
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="recognitions.data.length" :links="recognitions.links" :meta="recognitions.meta"></Paginator>
          </div>
        </div>
      </div>
    </div>
    <Modal :open="correctRow !== null" @modalClose="correctRow = null">
      <template #header>{{ $t('Adjust qty taken') }}</template>
      <AiCorrectionPanel v-if="correctRow !== null" :key="correctRow.id" :recognition-id="correctRow.id" @saved="correctionSaved" @key-in="keyInstead" />
    </Modal>
    <Modal :open="refundIntentId !== null" @modalClose="closeRefund">
      <template #header>{{ $t('T05 card refund') }}</template>
      <T05RefundPanel v-if="refundIntentId !== null" :key="refundIntentId" :intent-id="refundIntentId" :can-act="canRefund" @changed="refundChanged = true" />
    </Modal>

    <Modal :open="charge.open" @modalClose="closeCharge">
      <template #header>
        {{ charge.step === 1 ? $t('Key in the final charge') : $t('Confirm the charge') }}
      </template>
      <div v-if="charge.row" class="flex flex-col space-y-3 text-sm">
        <div class="grid grid-cols-2 gap-x-4 gap-y-1">
          <span class="text-gray-500">{{ $t('Machine') }}</span><span class="font-medium"><MachineIdLink :code="charge.row.vend_label" /></span>
          <span class="text-gray-500">{{ $t('Order No') }}</span><span class="font-mono">{{ charge.row.sale ? charge.row.sale.order_id : charge.row.session_ref }}</span>
          <span class="text-gray-500">{{ $t('Card hold (T05)') }}</span><span class="font-medium">{{ formatCents(charge.row.card.hold_cents) }}</span>
          <span class="text-gray-500">{{ $t('AI') }}</span><span>{{ charge.row.algorithm_status || '—' }}</span>
          <span class="text-gray-500">{{ $t('Paid for') }}</span>
          <span>
            <span v-for="line in charge.row.purchased" :key="line.product_id || line.name" class="block">{{ line.qty }} × {{ line.name }}</span>
            <span v-if="!charge.row.purchased.length" class="text-gray-400">—</span>
          </span>
        </div>

        <template v-if="charge.step === 1">
          <p class="text-gray-600">
            {{ charge.row.card.person_reason === 'ai_mismatch' ? $t('The AI count differs from what was paid, so the hold is waiting for you — it is never charged on its own. Check the videos (if any), then key in what the customer should pay. 0 releases the hold.') : charge.row.card.person_reason === 'ai_no_answer' ? $t('The AI never answered this session, so the hold is waiting for you — it is never charged on its own. Check the videos (if any), then key in what the customer should pay. 0 releases the hold.') : $t('The AI could not judge this session, so the hold is waiting for you — it is never charged on its own. Check the videos (if any), then key in what the customer should pay. 0 releases the hold.') }}
          </p>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('Final chargeable amount') }}</label>
            <div class="mt-1 flex items-center space-x-2">
              <input type="text" inputmode="decimal" v-model="charge.amount" placeholder="0.00" @keyup.enter="nextCharge"
                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-40 text-sm border-gray-300 rounded-md"
              />
              <button type="button" class="text-xs text-blue-600 hover:underline" @click="charge.amount = formatCents(charge.row.card.hold_cents)">
                {{ $t('Use the hold (:amount)', { amount: formatCents(charge.row.card.hold_cents) }) }}
              </button>
              <button v-if="charge.row.card.ai_suggested_cents != null" type="button" class="text-xs text-blue-600 hover:underline" @click="charge.amount = formatCents(charge.row.card.ai_suggested_cents)">
                {{ $t('Use the AI count (:amount)', { amount: formatCents(charge.row.card.ai_suggested_cents) }) }}
              </button>
            </div>
            <p v-if="charge.error" class="mt-1 text-sm text-red-700">{{ charge.error }}</p>
          </div>
          <div class="flex justify-end space-x-2 pt-2">
            <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50" @click="closeCharge">{{ $t('Cancel') }}</button>
            <button type="button" class="rounded-md border border-indigo-600 bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="nextCharge">{{ $t('Next') }}</button>
          </div>
        </template>

        <template v-else>
          <div class="rounded-md border px-3 py-3" :class="charge.cents === 0 ? 'border-gray-300 bg-gray-50' : 'border-amber-300 bg-amber-50'">
            <p v-if="charge.cents === 0" class="font-semibold text-gray-800">
              {{ $t('Release the :amount hold — the customer is charged nothing.', { amount: formatCents(charge.row.card.hold_cents) }) }}
            </p>
            <p v-else class="font-semibold text-gray-800">
              {{ $t("Charge :amount to the customer's card on the T05?", { amount: formatCents(charge.cents) }) }}
            </p>
            <p v-if="charge.cents > charge.row.card.hold_cents" class="mt-1 text-amber-800">
              {{ $t('This is above the :hold hold. Payrallel is asked to raise it; if they refuse, only :hold is charged and the rest is recorded as not charged.', { hold: formatCents(charge.row.card.hold_cents) }) }}
            </p>
            <p class="mt-1 text-gray-600">{{ $t('This is sent to Payrallel at once and cannot be undone here.') }}</p>
          </div>
          <p v-if="charge.error" class="text-sm text-red-700">{{ charge.error }}</p>
          <div v-if="charge.answered" class="flex justify-end pt-2">
            <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50" @click="closeCharge">{{ $t('Close') }}</button>
          </div>
          <div v-else class="flex justify-end space-x-2 pt-2">
            <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50" :disabled="charge.sending" @click="charge.step = 1; charge.error = ''">{{ $t('Back') }}</button>
            <button type="button" class="rounded-md border border-red-600 bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
              :disabled="charge.sending" @click="submitCharge"
            >
              {{ charge.sending ? $t('Sending…') : (charge.cents === 0 ? $t('Release hold') : $t('Charge :amount', { amount: formatCents(charge.cents) })) }}
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
import MachineIdLink from '@/Components/MachineIdLink.vue';
import T05RefundPanel from '@/Components/T05RefundPanel.vue';
import AiCorrectionPanel from '@/Components/AiCorrectionPanel.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { CheckCircleIcon, InformationCircleIcon, MagnifyingGlassIcon, PencilSquareIcon, QuestionMarkCircleIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { t, tk } from '@/i18n';

const props = defineProps({
  recognitions: Object,
  dashboard: Object,
  statuses: Array,
  verdicts: Array,
  canCharge: Boolean,
  canRefund: Boolean,
  sessionlessHolds: { type: Array, default: () => [] },
  waitingForVideo: { type: Array, default: () => [] },
  aiCoverage: { type: Array, default: () => [] },
  claudeModel: { type: Object, default: null },
  orderStatusLegend: Array,
  doorFaultLegend: { type: Array, default: () => [] },
  filters: Object,
})

const statusLabels = {
  pending: tk('Waiting'),
  submitting: tk('Sending…'),
  submitted: tk('Sent to AI'),
  completed: tk('Result in'),
  failed: tk('Failed'),
  skipped: tk('Not sent to AI'),
}

const verdictLabels = {
  match: tk('Match'),
  took_more: tk('Took more'),
  took_less: tk('Took less'),
  mixed: tk('Items differ'),
  unrecognised: tk('Unknown item'),
  incomplete: tk('Cannot judge'),
}

// What a verdict symbol means, on the line's hover and in the legend.
// "0d 0h 4m", whole minutes rounded down.
const durationText = (seconds) => `${Math.floor(seconds / 86400)}d ${Math.floor(seconds % 86400 / 3600)}h ${Math.floor(seconds % 3600 / 60)}m`

const CHECK_KINDS = {
  ok: { meaning: tk('Paid and taken') },
  extra: { meaning: tk('Taken, not paid') },
  missing: { meaning: tk('Paid, not taken') },
  unknown: { meaning: tk('AI could not check') },
  pending: { meaning: tk('Waiting for the AI') },
  none: { meaning: tk('Not paid, and the AI did not see it') },
  na: { meaning: tk('The sale failed, so there is nothing to check') },
}

// The lines say what happened; only these two say something the lines cannot.
function verdictNote(row) {
  if (row.failed_session) return null
  if (row.verdict === 'incomplete') return verdictLabels.incomplete
  if (row.verdict === 'unrecognised') return verdictLabels.unrecognised
  if (!row.verdict && row.algorithm_status !== null) return tk('No verdict')

  return null
}

// orderStatus 0 = the AI answered on its own; any other code = a person at Zijia settled it.
const processedByLabels = {
  ai: tk('AI'),
  human: tk('Human'),
}

const processedByTitles = {
  ai: tk('AI status 0 (normal): the AI answered on its own'),
  human: tk('AI status not 0: the AI could not answer on its own, so a person settled it'),
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
    skipped: 'bg-gray-100 text-gray-500 border-gray-300',
  }[status] || 'bg-gray-100 text-gray-700 border-gray-300'
}

function verdictBadgeClass(verdict) {
  return {
    match: 'bg-green-100 text-green-800',
    took_more: 'bg-red-100 text-red-800',
    took_less: 'bg-amber-100 text-amber-800',
    mixed: 'bg-red-100 text-red-800',
    unrecognised: 'bg-gray-100 text-gray-700',
    incomplete: 'bg-gray-100 text-gray-700',
  }[verdict] || 'bg-gray-100 text-gray-700'
}

// "…-d1c3-25f-1280x720.mp4" → "Door 1 · Cam 3"; anything else → "Video n".
function videoLabel(url, index) {
  const m = url.match(/-d(\d+)c(\d+)-/)
  return m ? t('Door :door · Cam :camera', { door: m[1], camera: m[2] }) : t('Video :n', { n: index + 1 })
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

// The Claude trial's model: saved at once on change; a refused save puts the page's value back.
const claudeModelPick = ref(props.claudeModel?.current ?? null)
const claudeModelSaving = ref(false)
watch(() => props.claudeModel?.current, (current) => { claudeModelPick.value = current ?? null })
function saveClaudeModel() {
  claudeModelSaving.value = true
  router.post('/ai-recognition/claude-model', { model: claudeModelPick.value }, {
    preserveScroll: true,
    preserveState: true,
    only: ['claudeModel', 'flash', 'errors'],
    onError: () => { claudeModelPick.value = props.claudeModel?.current ?? null },
    onFinish: () => { claudeModelSaving.value = false },
  })
}

// A share for the Claude box; a dash when there is nothing to divide by yet.
function pctText(pct) {
  return pct === null || pct === undefined ? '–' : pct.toFixed(1) + '%'
}

// The session's T05 hold, charged by the verdict (CardPaymentService::settleAwaitingAi).
function cardLabel(card) {
  if (card.uncertain_cents) return t('T05 charge :amount unconfirmed — check Payrallel', { amount: formatCents(card.uncertain_cents) })
  if (card.awaits_person && card.person_reason === 'ai_mismatch') return t('T05 hold :amount · AI count differs — key in the charge', { amount: formatCents(card.hold_cents) })
  if (card.awaits_person && card.person_reason === 'ai_unsure') return t('T05 hold :amount · AI could not judge — key in the charge', { amount: formatCents(card.hold_cents) })
  if (card.awaits_person) return card.person_reason === 'ai_no_answer' ? t('T05 hold :amount · AI never answered — key in the charge', { amount: formatCents(card.hold_cents) }) : t('T05 hold :amount · AI gave no verdict — key in the charge', { amount: formatCents(card.hold_cents) })
  if (card.state === 'awaiting_ai' && card.by_person) return t('T05 :amount keyed in · retrying', { amount: formatCents(card.judged_cents) })
  if (card.state === 'awaiting_ai') return t('T05 hold :amount · awaiting AI', { amount: formatCents(card.hold_cents) })
  if (card.state === 'captured') return t('T05 charged :amount (hold :hold)', { amount: formatCents(card.captured_cents), hold: formatCents(card.hold_cents) })
  if (card.state === 'voided') return t('T05 hold :amount released', { amount: formatCents(card.hold_cents) })
  return t('T05 :state', { state: card.state })
}

// Three looks, so a row says at a glance where it stands (Brian, 2026-11-04): red = the AI found a
// difference and nobody has dealt with it, blue = a person did this, grey = nothing to do, a released hold included.
function isHandled(row) {
  return !!(row.correction || row.card?.by_person || row.card?.refunds?.length)
}

function needsPerson(row) {
  if (isHandled(row) || row.card?.state === 'voided') return false
  return !!row.card?.awaits_person || (!!row.verdict && row.verdict !== 'match')
}

const BUTTON_RED = 'border-red-600 bg-red-600 text-white shadow-sm hover:bg-red-700'
const BUTTON_BLUE = 'border-blue-700 bg-blue-600 text-white shadow-sm hover:bg-blue-700'
const BUTTON_QUIET = 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'

// Each button turns blue only for its own action: an adjusted row with no refund keeps a plain Refund.
function adjustButtonClass(row) {
  if (row.correction) return row.correction.confirms_ai ? BUTTON_QUIET : BUTTON_BLUE
  return needsPerson(row) ? BUTTON_RED : BUTTON_QUIET
}

// Money still to go back outranks everything else on the row.
function refundButtonClass(row) {
  if (row.correction?.to_refund_cents) return BUTTON_RED
  if (row.card?.refunds?.length) return BUTTON_BLUE
  return needsPerson(row) ? 'border-red-600 bg-white text-red-700 hover:bg-red-50' : BUTTON_QUIET
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
  sortKey: props.filters.sortKey || 'transaction_time',
  // Query-string round-trips turn the boolean into "true"/"false" strings.
  sortBy: String(props.filters.sortBy ?? false) === 'true',
  numberPerPage: 100,
})
const numberPerPageOptions = ref([])
const statusOptions = ref([])
const verdictOptions = ref([])
const processedByOptions = ref([])

// While a sale waits for its video the row is about to change, so the table keeps itself current.
// Not with a popup open: nothing may shift under someone keying in a charge.
const WAITING_REFRESH_MS = 30000
let waitingRefresh = null

onUnmounted(() => clearInterval(waitingRefresh))

onMounted(() => {
  waitingRefresh = setInterval(() => {
    if (props.waitingForVideo.length && !charge.value.open && correctRow.value === null && refundIntentId.value === null) {
      router.reload({ only: ['recognitions', 'dashboard', 'waitingForVideo'] })
    }
  }, WAITING_REFRESH_MS)
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: t('All') },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  statusOptions.value = [
    { id: 'all', name: t('All') },
    ...props.statuses.map((s) => ({ id: s, name: statusLabels[s] ? t(statusLabels[s]) : s })),
  ]
  filters.value.status = statusOptions.value.find((o) => o.id === filters.value.status) || statusOptions.value[0]
  verdictOptions.value = [
    { id: 'all', name: t('All') },
    { id: 'none', name: t('No verdict yet') },
    ...props.verdicts.map((v) => ({ id: v, name: verdictLabels[v] ? t(verdictLabels[v]) : v })),
  ]
  filters.value.verdict = verdictOptions.value.find((o) => o.id === filters.value.verdict) || verdictOptions.value[0]
  processedByOptions.value = [
    { id: 'all', name: t('All') },
    { id: 'ai', name: t(processedByLabels.ai) },
    { id: 'human', name: t(processedByLabels.human) },
    { id: 'none', name: t('No result yet') },
  ]
  filters.value.processed_by = processedByOptions.value.find((o) => o.id === filters.value.processed_by) || processedByOptions.value[0]
})

// The legend is open until someone hides it; remembered per browser (storage may be blocked).
// Rows whose "Sent to AI" list is open.
const openSubmitted = ref(new Set())

function toggleSubmitted(id) {
  const open = new Set(openSubmitted.value)
  open.has(id) ? open.delete(id) : open.add(id)
  openSubmitted.value = open
}

// Asked twice per listed product on every render: built once per row (rows are new objects after each reload).
const barcodeSets = new WeakMap()
function barcodesOf(row) {
  let sets = barcodeSets.get(row)
  if (!sets) {
    sets = {
      paid: new Set(row.purchased.map((line) => line.barcode).filter(Boolean)),
      seen: new Set(row.items.map((item) => item.code)),
    }
    barcodeSets.set(row, sets)
  }
  return sets
}

function paidBarcodes(row) {
  return barcodesOf(row).paid
}

function seenBarcodes(row) {
  return barcodesOf(row).seen
}

const showLegend = ref(false)
try {
  showLegend.value = localStorage.getItem('aiRecognition.legend') === 'shown'
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
    algorithm_status: t('never answered'),
    card: { id: hold.id, hold_cents: hold.hold_cents, person_reason: hold.person_reason },
  }
}

function closeCharge() {
  if (!charge.value.sending) charge.value.open = false
}

const refundIntentId = ref(null)
const refundChanged = ref(false)
const correctRow = ref(null)
const correctionNotice = ref('')

function correctionSaved(result) {
  correctRow.value = null
  correctionNotice.value = result.message
  router.reload({ only: ['recognitions', 'dashboard'] })
  // What the count says should go back: nothing is sent until the person presses Refund there.
  if (result.to_refund_cents > 0 && result.intent_id && props.canRefund) {
    refundIntentId.value = result.intent_id
  }
}

function keyInstead() {
  const row = correctRow.value
  correctRow.value = null
  openCharge(row)
}

function closeRefund() {
  refundIntentId.value = null
  if (refundChanged.value) {
    refundChanged.value = false
    router.reload({ only: ['recognitions'] })
  }
}

function parseCents(text) {
  const m = String(text).trim().match(/^(\d{1,6})(?:\.(\d{1,2}))?$/)
  if (!m) return null
  return parseInt(m[1], 10) * 100 + parseInt((m[2] || '0').padEnd(2, '0'), 10)
}

function nextCharge() {
  const cents = parseCents(charge.value.amount)
  if (cents === null) {
    charge.value.error = t('Key in an amount like 7.60 (0 releases the hold).')
    return
  }
  if (cents > 100000) {
    charge.value.error = t('That is above 1000.00 — check the amount.')
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
      charge.value.error = errors.amount_cents || t('The charge was not sent.')
      charge.value.answered = true
    },
    onFinish: () => {
      charge.value.sending = false
    },
  })
}

// A cleared DatePicker hands back the text "Invalid date", which the server refuses and the page reopens on today.
function pickedDate(date) {
  return date === 'Invalid date' ? '' : date
}

function onSearchFilterUpdated() {
  router.get('/ai-recognition', {
    ...filters.value,
    date_from: pickedDate(filters.value.date_from),
    date_to: pickedDate(filters.value.date_to),
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
