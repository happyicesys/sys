<template>

  <Head :title="$t('Card Settlement :date', { date: report.cutover_date || report.id })" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-start justify-between flex-wrap gap-x-4 gap-y-3">
        <!-- Filename on its own line, downloads beneath it: the two used to sit
             inline with the action buttons, which crowded a long NETS filename. -->
        <div class="min-w-0">
          <div class="text-xs text-gray-400 uppercase tracking-wide">
            <Link href="/card-settlements" class="text-blue-700 hover:underline">{{ $t('Card Settlement') }}</Link>
          </div>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight break-all">{{ report.original_filename }}</h2>
          <div class="mt-1.5 flex items-center flex-wrap gap-2">
            <a
              v-if="report.file_url" :href="report.file_url"
              class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-50"
            >
              <ArrowDownTrayIcon class="w-3.5 h-3.5 text-gray-400"></ArrowDownTrayIcon>
              <span>{{ $t('Download original file') }}</span>
            </a>
            <a
              :href="report.converted_url"
              class="inline-flex items-center gap-1 rounded border border-blue-200 bg-blue-50 px-2 py-1 text-xs text-blue-700 hover:bg-blue-100"
              :title="$t('Same lines with the time as plain 23:12:41 text — Excel cannot turn it back into 12:41.0')"
            >
              <ArrowDownTrayIcon class="w-3.5 h-3.5 text-blue-400"></ArrowDownTrayIcon>
              <span>{{ $t('Download converted time file') }}</span>
            </a>
          </div>
        </div>
        <div class="flex items-center flex-wrap gap-1 shrink-0">
          <Button
            class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
            :class="report.status === 'matching' ? 'opacity-50 cursor-not-allowed' : ''"
            @click="rematch()"
          >
            <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
            <span>
              {{ $t('Rematch') }}
            </span>
          </Button>
          <!-- Hidden once the report is synced and nothing is left to stamp; a
               Rematch (or a line resolved by hand) puts work back and brings it
               straight back. The "Synced" summary tile still shows the outcome. -->
          <Button
            v-if="canSync"
            class="bg-green-500 hover:bg-green-600 px-3 py-2 text-xs text-white flex space-x-1"
            :class="report.status === 'matching' || !report.matched_count ? 'opacity-50 cursor-not-allowed' : ''"
            @click="sync()"
          >
            <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
            <span>
              {{ $t('Sync :count Matched', { count: report.matched_count }) }}
            </span>
          </Button>
          <span
            v-else
            class="inline-flex items-center gap-1 rounded border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-700"
            :title="report.synced_by ? $t('Synced :time by :name', { time: report.synced_at || '', name: report.synced_by }) : $t('Synced :time', { time: report.synced_at || '' })"
          >
            <CheckCircleIcon class="w-4 h-4 text-green-500"></CheckCircleIcon>
            <span>{{ $t('Synced') }}</span>
          </span>
          <!-- A synced report cannot be deleted while its stamps sit on the sales:
               Undo sync takes them back first (CardSettlementUnsyncService). -->
          <Button
            v-if="report.status === 'synced' && canUpdate"
            class="bg-amber-200 hover:bg-amber-300 px-3 py-2 text-xs text-amber-900 flex space-x-1"
            @click="unsync()"
          >
            <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
            <span>
              {{ $t('Undo Sync') }}
            </span>
          </Button>
          <Button
            v-if="report.status !== 'synced' && report.status !== 'matching'"
            class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
            @click="destroyReport()"
          >
            <TrashIcon class="w-4 h-4"></TrashIcon>
            <span>
              {{ $t('Delete') }}
            </span>
          </Button>
        </div>
      </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <!-- summary -->
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 text-sm">
        <div>
          <div class="text-xs text-gray-400 uppercase">{{ $t('Provider / Account') }}</div>
          <span class="uppercase">{{ report.provider }}</span> · {{ report.merchant_account || '—' }}
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">{{ $t('Cutover Date') }}</div>
          {{ report.cutover_date || '—' }}
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">{{ $t('Status') }}</div>
          <span
            class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border"
            :class="statusBadgeClass(report.status)"
          >
            {{ report.status }}
          </span>
        </div>
        <!-- One number per tile, so Purchases / Matched / Queries / Duplicates
             read exactly as they do on the Index row for this report. -->
        <div>
          <div class="text-xs text-gray-400 uppercase">{{ $t('Purchases') }}</div>
          {{ report.purchase_rows }}
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">{{ $t('Reversals') }}</div>
          <span :class="report.reversal_rows ? 'text-red-600 font-medium' : 'text-gray-400'">{{ report.reversal_rows }}</span>
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">{{ $t('Matched') }}</div>
          <span class="text-green-700 font-medium">{{ report.matched_count }}</span>
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">{{ $t('Queries') }}</div>
          <span :class="queriesCount ? 'text-amber-700 font-bold' : ''">{{ queriesCount }}</span>
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">{{ $t('Duplicates') }}</div>
          <span :class="report.duplicate_count ? '' : 'text-gray-400'">{{ report.duplicate_count }}</span>
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">{{ $t('Ignored') }}</div>
          <span :class="report.ignored_count ? '' : 'text-gray-400'">{{ report.ignored_count }}</span>
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">{{ $t('Synced / Refund-marked') }}</div>
          <span v-if="report.synced_at">
            {{ report.synced_count }} / <span :class="report.refunded_count ? 'text-red-600 font-medium' : ''">{{ report.refunded_count }}</span>
            <span class="text-xs text-gray-400">· {{ report.synced_at }}<span v-if="report.synced_by"> {{ $t('by :name', { name: report.synced_by }) }}</span></span>
          </span>
          <span v-else class="text-gray-400">{{ $t('not yet') }}</span>
        </div>
      </div>

      <div v-if="report.error_message" class="-mx-3 sm:-mx-6 lg:-mx-8 bg-red-50 border border-red-200 text-red-700 rounded-md p-3 my-3 text-sm">
        {{ report.error_message }}
      </div>

      <!-- Excel-damaged file: the hour is gone from the file itself (Excel shows a fake
           "12:xx:xx AM" in its formula bar). Rows are matched on minute:second only. -->
      <div v-if="report.partial_time_rows" class="-mx-3 sm:-mx-6 lg:-mx-8 bg-amber-50 border border-amber-200 rounded-md p-3 my-3 text-sm text-amber-800">
        <span class="font-semibold">{{ $t(':count lines have no hour', { count: report.partial_time_rows }) }}</span> —
        {{ $t('this file was opened and re-saved in Excel, which turns "23:12:41" into "12:41.0". Those lines were matched on minute:second within the hour (same terminal, same amount), so ambiguous ones need a manual pick. For exact matching, re-download the raw CSV from MerchantConnect and upload that instead.') }}
      </div>

      <!-- unbound terminals: two different chores, shown apart; each line carries the matcher's machine suggestion -->
      <div v-if="unknownTerminals.length" class="-mx-3 sm:-mx-6 lg:-mx-8 bg-red-50 border border-red-200 rounded-md p-3 my-3 text-sm">
        <div class="flex items-center justify-between mb-1">
          <div class="font-semibold text-red-800">{{ $t('Terminal IDs not created yet') }}</div>
          <label v-if="unknownTerminals.some((t) => t.suggested_vend_code)" class="flex items-center space-x-1 text-xs text-red-700 cursor-pointer">
            <input type="checkbox" class="cursor-pointer rounded border-red-300 text-red-600 focus:ring-red-500"
              :checked="allSelectedIn(unknownTerminals)" @change="toggleAllIn(unknownTerminals)" />
            <span>{{ $t('Select all suggested') }}</span>
          </label>
        </div>
        <div class="text-red-800 space-y-0.5">
          <div v-for="t in unknownTerminals" :key="t.terminal_id" class="flex items-start space-x-2">
            <input v-if="t.suggested_vend_code" type="checkbox"
              class="mt-1 cursor-pointer rounded border-red-300 text-red-600 focus:ring-red-500"
              :checked="selectedUnbound.includes(t.terminal_id)"
              :title="$t('Bind :terminal to machine :machine', { terminal: t.terminal_id, machine: t.suggested_vend_code })"
              @change="toggleUnbound(t.terminal_id)" />
            <span v-else class="mt-1 inline-block w-4"></span>
            <div>
              <span class="font-mono">{{ t.terminal_id }}</span> <span class="text-red-600">({{ $t(':count rows', { count: t.row_count }) }})</span>
              <template v-if="t.suggested_vend_code">
                {{ $t('— likely on') }} <b>{{ t.suggested_vend_code }}</b>{{ $t(': :hits of :count lines fit that machine exactly, from', { hits: t.suggested_hits, count: t.row_count }) }} <b>{{ t.from_date }}</b>
              </template>
              <span v-else class="text-red-600">{{ $t('— no single machine fits its lines; bind it by hand') }}</span>
            </div>
          </div>
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <Button v-if="unknownTerminals.some((t) => t.suggested_vend_code)" type="button"
            class="bg-red-600 hover:bg-red-700 px-3 py-2 text-xs text-white flex space-x-1"
            :class="report.status === 'matching' || !selectedIn(unknownTerminals).length ? 'opacity-50 cursor-not-allowed' : ''"
            @click="bindUnbound(unknownTerminals)">
            <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
            <span>{{ $tc('Create & bind :count terminal & rematch|Create & bind :count terminals & rematch', selectedIn(unknownTerminals).length) }}</span>
          </Button>
          <span class="text-red-700">{{ $t('…or by hand: create it under') }} <Link href="/card-terminal-units" class="underline font-medium">{{ $t('Data Management → Card Terminal') }}</Link>{{ $t(", put it on its machine from that machine's Settings page, and hit Rematch.") }}</span>
        </div>
      </div>
      <div v-if="unassignedTerminals.length" class="-mx-3 sm:-mx-6 lg:-mx-8 bg-amber-50 border border-amber-200 rounded-md p-3 my-3 text-sm">
        <div class="flex items-center justify-between mb-1">
          <div class="font-semibold text-amber-800">{{ $t('Terminal IDs not bound to any machine yet') }}</div>
          <label v-if="unassignedTerminals.some((t) => t.suggested_vend_code)" class="flex items-center space-x-1 text-xs text-amber-700 cursor-pointer">
            <input type="checkbox" class="cursor-pointer rounded border-amber-300 text-amber-600 focus:ring-amber-500"
              :checked="allSelectedIn(unassignedTerminals)" @change="toggleAllIn(unassignedTerminals)" />
            <span>{{ $t('Select all suggested') }}</span>
          </label>
        </div>
        <div class="text-amber-800 space-y-0.5">
          <div v-for="t in unassignedTerminals" :key="t.terminal_id" class="flex items-start space-x-2">
            <input v-if="t.suggested_vend_code" type="checkbox"
              class="mt-1 cursor-pointer rounded border-amber-300 text-amber-600 focus:ring-amber-500"
              :checked="selectedUnbound.includes(t.terminal_id)"
              :title="$t('Bind :terminal to machine :machine', { terminal: t.terminal_id, machine: t.suggested_vend_code })"
              @change="toggleUnbound(t.terminal_id)" />
            <span v-else class="mt-1 inline-block w-4"></span>
            <div>
              <span class="font-mono">{{ t.terminal_id }}</span> <span class="text-amber-600">({{ $t(':count rows', { count: t.row_count }) }})</span>
              <template v-if="t.suggested_vend_code">
                {{ $t('— likely on') }} <b>{{ t.suggested_vend_code }}</b>{{ $t(': :hits of :count lines fit that machine exactly, from', { hits: t.suggested_hits, count: t.row_count }) }} <b>{{ t.from_date }}</b>
              </template>
              <span v-else class="text-amber-600">{{ $t('— no single machine fits its lines; bind it by hand') }}</span>
            </div>
          </div>
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <Button v-if="unassignedTerminals.some((t) => t.suggested_vend_code)" type="button"
            class="bg-amber-600 hover:bg-amber-700 px-3 py-2 text-xs text-white flex space-x-1"
            :class="report.status === 'matching' || !selectedIn(unassignedTerminals).length ? 'opacity-50 cursor-not-allowed' : ''"
            @click="bindUnbound(unassignedTerminals)">
            <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
            <span>{{ $tc('Bind :count terminal & rematch|Bind :count terminals & rematch', selectedIn(unassignedTerminals).length) }}</span>
          </Button>
          <span class="text-amber-700">{{ $t("…or by hand: open the machine's Settings page, pick the Terminal ID there (Bound From = the date it went on), and hit Rematch.") }}</span>
        </div>
      </div>
      <!-- suspected wrong bindings: the sale exists, on a different machine -->
      <div v-if="suspectBindings.length" class="-mx-3 sm:-mx-6 lg:-mx-8 bg-orange-50 border border-orange-200 rounded-md p-3 my-3 text-sm">
        <div class="flex items-center justify-between mb-1">
          <div class="font-semibold text-orange-800">{{ $t('Terminals that look bound to the wrong machine') }}</div>
          <label class="flex items-center space-x-1 text-xs text-orange-700 cursor-pointer">
            <input
              type="checkbox"
              class="cursor-pointer rounded border-orange-300 text-orange-600 focus:ring-orange-500"
              :checked="allSuspectsSelected"
              @change="toggleAllSuspects"
            />
            <span>{{ $t('Select all') }}</span>
          </label>
        </div>
        <div class="text-orange-800 space-y-0.5">
          <div v-for="s in suspectBindings" :key="s.terminal_id" class="flex items-start space-x-2">
            <input
              v-if="!s.weak"
              type="checkbox"
              class="mt-1 cursor-pointer rounded border-orange-300 text-orange-600 focus:ring-orange-500"
              :checked="selectedTerminals.includes(s.terminal_id)"
              :title="$t('Move :terminal to machine :machine', { terminal: s.terminal_id, machine: s.suggested_vend_code })"
              @change="toggleSuspect(s.terminal_id)"
            />
            <span v-else class="mt-1 inline-block w-4" :title="$t('Only one line fits — not enough evidence to move the terminal automatically')"></span>
            <div>
            <span class="font-mono">{{ s.terminal_id }}</span> {{ $t('is bound to') }} <b>{{ s.bound_vend_code }}</b>{{ $t(', but its sales are on') }}
            <b>{{ s.suggested_vend_code }}</b> — {{ $t(':hits of :count unmatched lines fit that machine exactly, from', { hits: s.suggested_hits, count: s.row_count }) }}
            <b>{{ s.from_date }}</b>.<span v-if="s.weak" class="ml-1 text-orange-600">{{ $t('One line is a coincidence, not a move — bind by hand if you know it moved.') }}</span>
            <!-- Impact across EVERY report from that date, counted before the
                 move: what it recovers vs what it would knock back to a query. -->
            <span class="ml-1 inline-flex items-center rounded px-1.5 py-0.5 text-xs font-semibold border"
              :class="s.would_break_synced ? 'bg-red-100 text-red-800 border-red-300'
                : (s.would_break ? 'bg-amber-100 text-amber-800 border-amber-300'
                : 'bg-green-100 text-green-800 border-green-300')">
              {{ $t('fixes :fix · breaks :break', { fix: s.would_fix, break: s.would_break }) }}<span v-if="s.would_break_synced"> ({{ $t(':count already synced — skipped', { count: s.would_break_synced }) }})</span>
            </span>
            </div>
          </div>
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <Button
            type="button"
            class="bg-orange-600 hover:bg-orange-700 px-3 py-2 text-xs text-white flex space-x-1"
            :class="report.status === 'matching' || !selectedTerminals.length ? 'opacity-50 cursor-not-allowed' : ''"
            @click="fixBindings()"
          >
            <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
            <span>{{ $tc('Move :count terminal & rematch|Move :count terminals & rematch', selectedTerminals.length) }}</span>
          </Button>
          <span class="text-orange-700">
            {{ $t("…or do it by hand from the RIGHT machine's Settings page — pick this Terminal ID there and set Bound From to the date it moved, then hit Rematch.") }}
          </span>
        </div>
      </div>

      <!-- row status chips + batch actions on the checked lines -->
      <div class="flex flex-wrap items-center justify-between gap-2 my-3">
        <div class="flex flex-wrap gap-2">
          <span v-for="c in chips" :key="c.key"
            class="text-xs font-semibold px-3 py-1.5 rounded-full border bg-white cursor-pointer"
            :class="rowStatus === c.key ? 'border-green-500 text-green-700' : 'border-gray-200 text-gray-600'"
            @click="pickStatus(c.key)">
            {{ c.label }}
          </span>
        </div>
      </div>

      <div class="mt-3 flex flex-col">
       <div class="-my-2 -mx-3 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-100">
                  <tr class="divide-x divide-gray-200">
                    <TableHead>
                      #
                    </TableHead>
                    <TableHead>
                      {{ $t('Terminal') }}
                    </TableHead>
                    <TableHead>
                      {{ $t('Machine ID') }}
                    </TableHead>
                    <TableHead>
                      {{ $t('Date') }}
                    </TableHead>
                    <TableHead>
                      {{ $t('Time') }}
                    </TableHead>
                    <TableHead>
                      {{ $t('Card') }}
                    </TableHead>
                    <TableHead>
                      {{ $t('Amount') }}
                    </TableHead>
                    <TableHead>
                      {{ $t('Status') }}
                    </TableHead>
                    <TableHead>
                      {{ $t('Matched Sale') }}
                    </TableHead>
                    <TableHead>
                      {{ $t('Note / Resolve') }}
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(row, rowIndex) in rows.data" :key="row.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        {{ row.row_no }}
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        {{ row.terminal_id }}
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        <a
                          v-if="row.vend_code"
                          class="text-blue-700 hover:underline"
                          target="_blank"
                          :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'"
                        >
                          {{ row.vend_code }}
                        </a>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        {{ row.transaction_date }}
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        <span
                          v-if="row.time_is_partial"
                          class="text-amber-700"
                          :title="$t('Hour lost (file was re-saved in Excel) — matched by minute:second within the hour')"
                        >
                          ??{{ row.transaction_time ? row.transaction_time.slice(2) : '' }}
                        </span>
                        <span v-else>{{ row.transaction_time }}</span>
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        {{ row.card_issuer }}
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-right">
                        <span :class="row.is_reversal ? 'text-red-600 font-medium' : ''">{{ row.amount.toFixed(2) }}</span>
                        <span
                          v-if="row.is_reversal"
                          class="ml-1 inline-flex items-center rounded px-1 py-0.5 text-[10px] font-bold border bg-red-100 text-red-800 border-red-300"
                          :title="$t('Terminal reversal line (Reversal Code = Y)')"
                        >
                          {{ $t('REVERSAL') }}
                        </span>
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        <span
                          class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border"
                          :class="rowStatusBadgeClass(row.status)"
                        >
                          {{ row.status_label }}
                        </span>
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-left">
                        <!-- reversal line: points at the purchase line it undoes -->
                        <template v-if="row.is_reversal">
                          <template v-if="row.reverses_row">
                            <div>{{ $t('Reverses row #:row', { row: row.reverses_row.row_no }) }}
                              <span v-if="row.reverses_row.report_id !== report.id" class="text-xs text-gray-500">({{ $t('report :id', { id: row.reverses_row.report_id }) }})</span>
                            </div>
                            <div class="text-xs text-gray-500">{{ $t(':delta after the purchase', { delta: row.match_time_delta + 's' }) }}</div>
                          </template>
                          <span v-else class="text-gray-400">—</span>
                        </template>
                        <template v-else-if="row.matched_txn">
                          <div>#{{ row.matched_txn.id }} · {{ row.matched_txn.transaction_datetime }}</div>
                          <div class="text-xs text-gray-500">
                            Δ {{ row.match_time_delta !== null ? row.match_time_delta + 's' : $t('manual') }}
                            <span v-if="row.matched_txn.is_refunded" class="text-red-600 font-medium"> · {{ $t('refunded') }}</span>
                            <span v-if="row.matched_txn.synced" class="text-green-600 font-medium"> · {{ $t('synced') }}</span>
                          </div>
                          <div v-if="row.reversed_by_row" class="text-xs text-red-600 font-medium">
                            {{ $t('Reversed by row #:row', { row: row.reversed_by_row.row_no }) }}
                            <span v-if="!row.matched_txn.is_refunded" class="text-gray-500 font-normal">{{ $t('→ marked refunded on Sync') }}</span>
                          </div>
                          <div
                            v-else-if="row.matched_txn.auto_refund_source === 'card_terminal_reversal'"
                            class="text-xs text-amber-700"
                            :title="$t('ConnectVend inferred a reversal from the TRADE frame, but this report has no reversal line for it — the reader may have retained the credit instead')"
                          >
                            {{ $t('⚠ refunded by inference, no reversal in report') }}
                          </div>
                        </template>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-left">
                        <div v-if="row.resolution_note" class="text-xs text-gray-500 mb-1"
                          :title="row.resolution_note === 'All matching sales already claimed'
                            ? $t('Every sale that fits this line is already held by another line — NETS charged more times than ConnectVend recorded sales here. Likely a double charge (customer tapped twice); check the two lines and refund if so.')
                            : (row.resolution_note === 'No matching sale in window'
                              ? $t('ConnectVend has no card sale on this machine at this time/amount — the machine may have been offline (TRADE never arrived) or the terminal is bound to the wrong machine.')
                              : '')">
                          {{ row.resolution_note }}
                        </div>
                        <!-- candidates to pick from (ambiguous / claimed-elsewhere rows) -->
                        <div v-if="(row.status === 3 || row.status === 2) && row.candidates && row.candidates.length" class="space-y-1">
                          <div v-for="c in row.candidates" :key="c.vend_transaction_id" class="flex items-center space-x-1 text-xs">
                            <!-- A sale another line already holds cannot be picked: it would
                                 only bounce. Show who holds it, so the pair can be checked
                                 for a double charge. -->
                            <span
                              v-if="c.claimed_by_row"
                              class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold bg-gray-200 text-gray-600"
                              :title="c.claimed_by_row.same_report ? $t('Held by row #:row — NETS charged more times than ConnectVend recorded sales; check for a double charge', { row: c.claimed_by_row.row_no }) : $t('Held by row #:row of report :report — NETS charged more times than ConnectVend recorded sales; check for a double charge', { row: c.claimed_by_row.row_no, report: c.claimed_by_row.report_id })"
                            >
                              {{ $t('held by row #:row', { row: c.claimed_by_row.row_no }) }}<span v-if="!c.claimed_by_row.same_report"> ({{ $t('report :id', { id: c.claimed_by_row.report_id }) }})</span>
                            </span>
                            <Button
                              v-else
                              type="button" class="bg-green-500 hover:bg-green-600 px-2 py-1 text-xs text-white"
                              @click="pickCandidate(row, c)"
                            >
                              {{ $t('Pick') }}
                            </Button>
                            <span>#{{ c.vend_transaction_id }} · {{ c.transaction_datetime }}<span v-if="c.other_vend" class="text-orange-700 font-medium"> · {{ $t('on machine :machine', { machine: c.vend_code }) }}</span><span v-if="c.is_refunded" class="text-red-600"> · {{ $t('refunded') }}</span></span>
                          </div>
                        </div>
                        <div v-if="(row.status === 2 || row.status === 3) && !row.is_reversal" class="flex items-center space-x-1 mt-1">
                          <input v-model="manualTxnId[row.id]" :placeholder="$t('Txn ID / Order ID')" :title="$t('Either the sale\'s numeric Txn ID (#…, as shown here) or its Order ID from Sales Transactions')"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-xs border-gray-300 rounded-md w-24 px-2 py-1"
                            @keyup.enter="resolveManual(row)" />
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-2 py-1 text-xs text-gray-800"
                            @click="resolveManual(row)"
                          >
                            {{ $t('Assign') }}
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!rows.data.length">
                  <td colspan="10" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      {{ rowStatus === 'queries' ? $t('No open queries — everything matched or duplicated.') : $t('No Results Found') }}
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="rows.data.length" :links="rows.links" :meta="rows.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Paginator from '@/Components/Paginator.vue';
import { ArrowDownTrayIcon, ArrowPathIcon, ArrowUturnLeftIcon, CheckCircleIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import { t } from '@/i18n';

const props = defineProps({
  report: { type: Object, required: true },
  rows: { type: Object, required: true },
  rowFilters: { type: Object, default: () => ({}) },
  unboundTerminals: { type: Array, default: () => [] },
  suspectBindings: { type: Array, default: () => [] },
  statusLabels: { type: Object, default: () => ({}) },
})

const toast = useToast()
const rowStatus = ref(props.rowFilters.row_status ?? 'queries')

// "No terminal binding" split by whether the TID exists in Data Management at all.
const unknownTerminals = computed(() => props.unboundTerminals.filter((t) => !t.unit_exists))
const unassignedTerminals = computed(() => props.unboundTerminals.filter((t) => t.unit_exists))

// Accept the matcher's machine suggestion for unbound TIDs. Suggested ones
// start ticked; TIDs with no single fitting machine have no box at all.
const selectedUnbound = ref([])
watch(() => props.unboundTerminals, (list) => {
  selectedUnbound.value = (list || []).filter((t) => t.suggested_vend_code).map((t) => t.terminal_id)
}, { immediate: true })
const selectedIn = (list) => list.filter((t) => t.suggested_vend_code && selectedUnbound.value.includes(t.terminal_id))
const allSelectedIn = (list) => {
  const suggestable = list.filter((t) => t.suggested_vend_code)
  return suggestable.length > 0 && suggestable.every((t) => selectedUnbound.value.includes(t.terminal_id))
}
function toggleUnbound(terminalId) {
  selectedUnbound.value = selectedUnbound.value.includes(terminalId)
    ? selectedUnbound.value.filter((t) => t !== terminalId)
    : [...selectedUnbound.value, terminalId]
}
function toggleAllIn(list) {
  const ids = list.filter((t) => t.suggested_vend_code).map((t) => t.terminal_id)
  selectedUnbound.value = allSelectedIn(list)
    ? selectedUnbound.value.filter((t) => !ids.includes(t))
    : [...new Set([...selectedUnbound.value, ...ids])]
}
function bindUnbound(list) {
  const chosen = selectedIn(list)
  if (props.report.status === 'matching' || !chosen.length) return
  const creating = chosen.some((t) => !t.unit_exists)
  const lines = chosen.map((term) => '  ' + t(':terminal → :machine from :date (:hits of :count lines fit)', { terminal: term.terminal_id, machine: term.suggested_vend_code, date: term.from_date, hits: term.suggested_hits, count: term.row_count })).join('\n')
  const approval = confirm(
    (creating ? t('Create :count terminal(s) in Data Management and bind to the machine their sales are on, then rematch?', { count: chosen.length }) : t('Bind :count terminal(s) to the machine their sales are on, then rematch?', { count: chosen.length })) +
    '\n\n' + lines + '\n\n' +
    t('Each binding starts on the first date this report saw the terminal. It affects every settlement report, not just this one.')
  )
  if (!approval) {
    return
  }
  router.post('/card-settlements/' + props.report.id + '/bind-unbound', { terminal_ids: chosen.map((t) => t.terminal_id) }, {
    preserveScroll: true,
    onSuccess: () => toast.success(t("Terminals bound — rematch queued"), { timeout: 4000 }),
    onError: () => toast.error(t("Failed to bind the terminals"), { timeout: 3000 }),
  })
}

// Row status codes (CardSettlementRow::STATUS_*)
const chips = [
  { key: 'queries', label: t('Queries') },
  { key: '1', label: t('Matched') },
  { key: '2', label: t('Unmatched') },
  { key: '3', label: t('Ambiguous') },
  { key: 'reversals', label: t('Reversals') },
  { key: '5', label: t('Duplicates') },
  // Lines whose sale is channel error 99 — mostly the orphans a Sync created
  // for money the machine never reported a TRADE for.
  { key: 'na', label: t('Machine transaction not found (NA)') },
  { key: 'all', label: t('All rows') },
]

function statusBadgeClass(status) {
  return {
    uploaded: 'bg-gray-100 text-gray-700 border-gray-300',
    matching: 'bg-amber-100 text-amber-800 border-amber-300',
    review: 'bg-blue-100 text-blue-800 border-blue-300',
    synced: 'bg-green-100 text-green-800 border-green-300',
    failed: 'bg-red-100 text-red-800 border-red-300',
  }[status] || 'bg-gray-100 text-gray-700 border-gray-300'
}

function rowStatusBadgeClass(status) {
  return {
    1: 'bg-green-100 text-green-800 border-green-300',
    2: 'bg-amber-100 text-amber-800 border-amber-300',
    3: 'bg-orange-100 text-orange-800 border-orange-300',
    4: 'bg-gray-100 text-gray-500 border-gray-300',
    5: 'bg-gray-100 text-gray-500 border-gray-300',
  }[status] || 'bg-gray-100 text-gray-700 border-gray-300'
}

// computed, not a const: Pick / Assign reload props with preserveState,
// and the tile must follow the refreshed counts without a hard reload.
const queriesCount = computed(() => props.report.unmatched_count + props.report.ambiguous_count)

// The Sync button only earns its place while a Sync would still do something:
// a report that is synced with nothing left to stamp shows a plain badge instead.
const canSync = computed(() =>
  props.report.status !== 'synced' || props.report.pending_sync_count > 0
)

function pickStatus(key) {
  rowStatus.value = key
  router.get('/card-settlements/' + props.report.id, { row_status: key }, { preserveState: true, preserveScroll: true, replace: true })
}

function rematch() {
  if (props.report.status === 'matching') return
  router.post('/card-settlements/' + props.report.id + '/rematch', {}, {
    preserveScroll: true,
    onSuccess: () => toast.success(t("Rematch queued"), { timeout: 3000 }),
  })
}

// One-click repair of every suspected wrong binding, then rematch. Bindings are
// dated and global, so this changes how EVERY report resolves those terminals —
// hence the confirm, and the server's per-terminal report in the flash message.
// Pick which suggestions to act on. A suggestion that would BREAK existing
// matches starts unchecked — the bulk click must never take one by accident;
// it has to be an explicit tick.
const selectedTerminals = ref([])
watch(() => props.suspectBindings, (list) => {
  selectedTerminals.value = (list || []).filter((s) => !s.would_break && !s.weak).map((s) => s.terminal_id)
}, { immediate: true })

const allSuspectsSelected = computed(() =>
  props.suspectBindings.filter((s) => !s.weak).length > 0 && selectedTerminals.value.length === props.suspectBindings.filter((s) => !s.weak).length)

function toggleSuspect(terminalId) {
  selectedTerminals.value = selectedTerminals.value.includes(terminalId)
    ? selectedTerminals.value.filter((t) => t !== terminalId)
    : [...selectedTerminals.value, terminalId]
}

function toggleAllSuspects() {
  selectedTerminals.value = allSuspectsSelected.value ? [] : props.suspectBindings.filter((s) => !s.weak).map((s) => s.terminal_id)
}

function fixBindings() {
  if (props.report.status === 'matching' || !selectedTerminals.value.length) return
  const chosen = props.suspectBindings.filter((s) => selectedTerminals.value.includes(s.terminal_id))
  const lines = chosen
    .map((s) => '  ' + t(':terminal: :from → :to from :date (fixes :fix, breaks :break)', { terminal: s.terminal_id, from: s.bound_vend_code, to: s.suggested_vend_code, date: s.from_date, fix: s.would_fix, break: s.would_break }) +
      (s.would_break_synced ? ' — ' + t('SKIPPED, :count already synced', { count: s.would_break_synced }) : ''))
    .join('\n')
  const approval = confirm(
    t('Move :count terminal(s) onto the machine their sales are on, and rematch?', { count: chosen.length }) + '\n\n' +
    lines + '\n\n' +
    t('This closes each current binding and opens a new one from that date. It affects every settlement report, not just this one. Anything ambiguous is left alone and listed back to you.')
  )
  if (!approval) {
    return
  }
  router.post('/card-settlements/' + props.report.id + '/fix-bindings', { terminal_ids: selectedTerminals.value }, {
    preserveScroll: true,
    onSuccess: () => toast.success(t("Bindings updated — rematch queued"), { timeout: 4000 }),
    onError: () => toast.error(t("Failed to update bindings"), { timeout: 3000 }),
  })
}

function sync() {
  if (!canSync.value || props.report.status === 'matching' || !props.report.matched_count) return
  const approval = confirm(t('Stamp "settlement synced" onto :count matched transaction(s)?', { count: props.report.matched_count }));
  if (!approval) {
      return;
  }
  router.post('/card-settlements/' + props.report.id + '/sync', {}, {
    preserveScroll: true,
    onSuccess: () => toast.success(t("Matched transactions synced"), { timeout: 3000 }),
    onError: () => toast.error(t("Failed to sync"), { timeout: 3000 }),
  })
}

const page = usePage()
const canUpdate = computed(() => (page.props.auth?.permissions || []).includes('update card-settlements'))

function unsync() {
  const approval = confirm(
    t('Undo the sync of :file?', { file: props.report.original_filename }) + '\n\n' +
    t('The sales it matched lose their "settlement synced" stamp, NA sales it created with no TRADE are removed, and auto-refund ticks it set (reversals, NA in NETS) are cleared, releasing their refund tickets. Its days stop counting as final until it, or a replacement file, is synced.') + '\n\n' +
    t('The report goes back to review, where you can Delete it or Sync it again.')
  );
  if (!approval) {
    return;
  }
  router.post('/card-settlements/' + props.report.id + '/unsync', {}, {
    preserveScroll: true,
    onSuccess: () => toast.success(t("Sync undone"), { timeout: 3000 }),
    onError: () => toast.error(t("Failed to undo sync"), { timeout: 3000 }),
  })
}

function destroyReport() {
  const approval = confirm(t('Are you sure to delete this report and all its rows?'));
  if (!approval) {
      return;
  }
  router.delete('/card-settlements/' + props.report.id, {
    onSuccess: () => toast.success(t("Report deleted successfully"), { timeout: 3000 }),
    onError: () => toast.error(t("Failed to delete report"), { timeout: 3000 }),
  })
}

function resolveTo(row, txnId) {
  router.post('/card-settlements/' + props.report.id + '/rows/' + row.id + '/resolve',
    { vend_transaction_id: txnId }, {
      preserveScroll: true,
      onSuccess: () => toast.success(t("Row resolved"), { timeout: 3000 }),
      onError: () => toast.error(t("Failed to resolve row"), { timeout: 3000 }),
    })
}

// A candidate on a DIFFERENT machine than the terminal is bound to means the
// binding sheet is probably wrong — make the user say so before the line moves.
function pickCandidate(row, c) {
  if (c.other_vend && String(c.vend_code) !== String(row.vend_code)) {
    const approval = confirm(
      t('Moving this line from machine :from to machine :to.', { from: row.vend_code, to: c.vend_code }) + '\n\n' +
      t('Terminal :terminal is bound to :from but this sale is on :to.', { terminal: row.terminal_id, from: row.vend_code, to: c.vend_code }) + ' ' +
      t('If the terminal has moved, also update it on Card Terminal Bindings so future reports match on their own.') + '\n\n' +
      t('Do you confirm?')
    )
    if (!approval) {
      return
    }
  }
  resolveTo(row, c.vend_transaction_id)
}

const manualTxnId = ref({})
function resolveManual(row) {
  const id = String(manualTxnId.value[row.id] || '').trim().replace(/^#/, '')
  if (!/^\d{1,20}$/.test(id)) return
  resolveTo(row, id)
}
</script>
