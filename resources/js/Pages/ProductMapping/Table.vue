<template>
  <div class="flex flex-col sm:col-span-5">
    <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
      <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
          <table class="table-fixed min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                  #
                </th>
                <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="customer.vend && customer.vend.product_mapping_id">
                  Image
                </th>
                <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="customer.vend && customer.vend.product_mapping_id">
                  Product
                </th>
                <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                  <div class="flex justify-center">
                    <span>
                      P1
                    </span>
                    <span v-if="profile && profile.base_currency">
                      ({{ profile.base_currency.currency_symbol }})
                    </span>
                    <ExclamationCircleIcon class="w-5 h-5 self-center pl-1" v-tooltip="'Actual Price on Vending Machine'"></ExclamationCircleIcon>
                  </div>
                </th>
                <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                  <div class="flex justify-center">
                    <span>
                      P2
                    </span>
                    <span v-if="profile && profile.base_currency">
                      ({{ profile.base_currency.currency_symbol }})
                    </span>
                    <ExclamationCircleIcon class="w-5 h-5 self-center pl-1" v-tooltip="'Discounted Price on 2nd Purchase'"></ExclamationCircleIcon>
                  </div>
                </th>
                <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                  Ref Price {{ form.selling_price_type ? form.selling_price_type.id : '' }}
                </th>
              </tr>
            </thead>
            <tbody class="bg-white">
              <tr v-for="(channel, channelIndex) in vendChannels" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-900">
                  {{ channel.code }}
                </td>
                <td class="whitespace-nowrap text-sm font-semibold text-gray-900 text-center" v-if="customer.vend && customer.vend.product_mapping_id">
                  <div class="flex justify-center items-center">
                    <img class="h-16 w-16 rounded-full" :src="channel.product.thumbnail.full_url" alt="" v-if="channel.product && channel.product.thumbnail"/>
                  </div>
                </td>
                <td class="py-4 text-sm font-semibold text-center text-gray-900" v-if="customer.vend && customer.vend.product_mapping_id">
                  <span v-if="channel.product && channel.product.code">
                    {{ channel.product.code }}
                  </span>
                  <span class="break-normal text-xs" v-if="channel.product && channel.product.name">
                    <br> {{ channel.product.name }}
                  </span>
                </td>
                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="compareSellingPrice(channel)">
                  {{ (channel.amount / Math.pow(10, operatorCountry.currency_exponent)).toLocaleString(undefined, { minimumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent, maximumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent }) }}
                </td>
                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800" v-if="vendChannels.some(channel => 'amount2' in channel)">
                  {{ (channel.amount2 / Math.pow(10, operatorCountry.currency_exponent)).toLocaleString(undefined, { minimumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent, maximumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent }) }}
                </td>
                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800">
                  {{ channel.product && channel.product.selling_prices[0] ? (channel.product.selling_prices[0].amount / Math.pow(10, operatorCountry.currency_exponent)).toLocaleString(undefined, { minimumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent, maximumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent }) : null }}
                </td>
              </tr>
              <tr v-if="!vendChannels || !vendChannels.length">
                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs font-normal sm:pl-6 text-center text-gray-900" colspan="6">
                  No Results Found
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
  customer: Object,
  vendChannels: Array,
  operatorCountry: Object,
  profile: Object,
  form: Object,
});

function compareSellingPrice(channel) {
  if (channel.product && channel.product.selling_prices[0] && channel.product.selling_prices[0].amount) {
    if (channel.amount !== channel.product.selling_prices[0].amount) {
      return 'text-red-500';
    }
  }
  return 'text-gray-800';
}

const vendChannelsData = ref([]);

onMounted(() => {
  vendChannelsData.value = props.vendChannels;
});
</script>

<style scoped>
/* Add any scoped styles if needed */
</style>
