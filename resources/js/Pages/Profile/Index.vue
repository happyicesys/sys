<template>

  <Head title="Profile" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              Profiles
          </h2>
      </template>

      <div class="m-2 sm:mx-5 sm:my-3 px-2 sm:px-4 lg:px-6">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-5 px-3 md:px-6 py-6 ">
          <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0">
              <SearchInput placeholderStr="Name" v-model="searchFilters.name" @input="onSearchFilterUpdated()">
                  Name
              </SearchInput>
              <SearchInput placeholderStr="UEN" v-model="searchFilters.uen"
                  @input="onSearchFilterUpdated()">
                  UEN
              </SearchInput>
          </div>

          <div class="flex justify-end mt-5">
              <div class="flex flex-col space-y-2">
                  <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                      <span>Showing</span>
                      <span class="font-medium">{{ profiles.meta.from }}</span>
                      <span>to</span>
                      <span class="font-medium">{{ profiles.meta.to }}</span>
                      <span>of</span>
                      <span class="font-medium">{{ profiles.meta.total }}</span>
                      <span>results</span>
                  </p>
                  <div class="flex justify-end">
                      <OptionDropdown
                          @option-selected="onNumberPerPageSelected"
                          :options="[100, 200, 500, 'All']"
                      >
                      </OptionDropdown>
                  </div>
              </div>
          </div>
      </div>

       <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
              <table class="min-w-full border-separate" style="border-spacing: 0">
                  <thead class="bg-gray-100">
                      <tr class="divide-x divide-gray-200">
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              #</th>
                          <th
                              scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              <div class="flex justify-center">
                                  <a href="#" class="text-blue-600 hover:text-blue-800" @click="sortTable('name')">
                                      Name
                                  </a>
                                  <div class="pt-0.5 pl-0.5 text-blue-600 hover:text-blue-800">
                                      <span v-if="searchFilters.sortKey === 'name' && searchFilters.sortBy">
                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                          </svg>
                                      </span>
                                      <span v-if="searchFilters.sortKey === 'name' && !searchFilters.sortBy">
                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                              <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                          </svg>
                                      </span>
                                  </div>
                              </div>
                          </th>
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              Alias
                          </th>
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              UEN
                          </th>
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              Address
                          </th>
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pr-4 pl-3 backdrop-blur backdrop-filter sm:pr-6 lg:pr-8">
                              <span class="sr-only">Edit</span>
                          </th>
                      </tr>
                  </thead>
                  <tbody class="bg-white">
                      <tr v-for="(profile, profileIndex) in profiles.data" :key="profile.id"
                          class="divide-x divide-gray-200">
                          <td :class="[profileIndex !== profiles.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                              class="text-right">
                              {{ profiles.meta.from + profileIndex }}
                          </td>
                          <td :class="[profileIndex !== profiles.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                              class="text-right">
                              {{ profile.name }}
                          </td>
                          <td :class="[profileIndex !== profiles.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                              class="text-right">
                              {{ profile.alias }}
                          </td>
                          <td :class="[profileIndex !== profiles.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                              class="text-right">
                              {{ profile.uen }}
                          </td>
                          <td :class="[profileIndex !== profiles.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                              class="text-right">
                              {{ profile.uen }}
                          </td>
                          <td
                              :class="[profileIndex !== profiles.length - 1 ? 'border-b border-gray-200' : '', 'relative whitespace-nowrap py-4 pr-4 pl-3 text-right text-sm font-medium sm:pr-6 lg:pr-8']">
                              <a href="#" class="text-indigo-600 hover:text-indigo-900">Edit<span
                                      class="sr-only">, {{ profile.name }}</span></a>
                          </td>
                      </tr>
                  </tbody>
              </table>
              <Paginator :links="profiles.links" :meta="profiles.meta"></Paginator>
          </div>
      </div>
      </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import OptionDropdown from '@/Components/OptionDropdown.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Select from '@/Components/Select.vue';
import { debounce } from 'lodash';

export default {
  components: {
      BreezeAuthenticatedLayout,
      Button,
      OptionDropdown,
      Paginator,
      SearchInput,
      Select,
  },
  props: {
      vends: Object,
      filters: Object,
  },
  data() {
      return {
          searchFilters: {
              code: this.filters.code,
              serialNum: this.filters.serialNum,
              name: this.filters.name,
              tempHigherThan: this.filters.tempHigherThan,
              sortKey: this.filters.sortKey,
              sortBy: this.filters.sortBy,
              numberPerPage: this.filters.numberPerPage,
          },
      }
  },
  mounted() {
      this.searchFilters.numberPerPage = 100
  },
  computed: {

  },
  methods: {
      onSearchFilterUpdated: debounce(function() {
          this.$inertia.get('/vends', this.searchFilters, {
              preserveState: true,
              replace: true,
          })
      }, 500),
      onVendTempClicked(vendId) {
          this.$inertia.get('/vend/' + vendId + '/temp')
      },
      onVendChannelErrorLogEmailClicked() {
          this.$inertia.get('/vends/channel-error-logs-email')
      },
      sortTable(sortKey) {
          this.searchFilters.sortKey = sortKey
          this.searchFilters.sortBy = !this.searchFilters.sortBy
          this.onSearchFilterUpdated()
      },
      onNumberPerPageSelected(option) {
          this.searchFilters.numberPerPage = option
          this.onSearchFilterUpdated()
      },
      getTotalQty(vend) {
          return vend.vend_channels
                  .filter(function(channel) {
                      return channel.capacity > 0 && channel.code < 1000
                  })
                  .reduce(function(total, value) {
                      return total + value.qty
                  }, 0)
      },
      getTotalCapacity(vend) {
          return vend.vend_channels
                  .filter(function(channel) {
                      return channel.capacity > 0 && channel.code < 1000
                  })
                  .reduce(function(total, value) {
                      return total + value.capacity
                  }, 0)
      },
  },
}
</script>