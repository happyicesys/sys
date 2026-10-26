<template>
  <Head :title="$t('Tutorials')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Tutorials') }}
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Management Section -->
        <div v-if="hasPermission('read tutorials-operators')" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6 bg-white border-b border-gray-200">
            <h3 class="text-lg font-medium text-red-600 mb-4">{{ $t('Management to know') }}</h3>
            <div class="space-y-6">
              <div v-for="(item, index) in managementItems" :key="index" class="border-b pb-4 last:border-b-0 last:pb-0">
                <div class="flex justify-between items-start">
                  <div>
                    <h4 class="font-medium text-gray-900">{{ item.id || (index + 1) }}) {{ item.title }}</h4>
                    <ul class="mt-2 list-disc list-inside text-sm text-gray-600 pl-4">
                      <li v-for="(sub, subIndex) in item.subtitles" :key="subIndex">{{ sub }}</li>
                    </ul>
                  </div>
                  <a v-if="item.link" :href="item.link" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                    {{ $t('Watch Video') }}
                    <ArrowTopRightOnSquareIcon class="w-4 h-4 ml-1" />
                  </a>

                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Drivers Section -->
        <div v-if="hasPermission('read tutorials-drivers')" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <h3 class="text-lg font-medium text-red-600 mb-4">{{ $t('Drivers to know') }}</h3>
            <div class="space-y-6">
              <div v-for="(item, index) in driverItems" :key="index" class="border-b pb-4 last:border-b-0 last:pb-0">
                <div class="flex justify-between items-start">
                  <div>
                    <h4 class="font-medium text-gray-900">{{ item.id || (index + 1) }}) {{ item.title }}</h4>
                    <ul class="mt-2 list-decimal list-inside text-sm text-gray-600 pl-4">
                      <li v-for="(sub, subIndex) in item.subtitles" :key="subIndex">{{ sub }}</li>
                    </ul>
                  </div>
                  <a :href="item.link" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                    {{ $t('Watch Video') }}
                    <ArrowTopRightOnSquareIcon class="w-4 h-4 ml-1" />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/20/solid';
import { t } from '@/i18n';

const page = usePage();
const permissions = page.props.auth.permissions;

function hasPermission(permission) {
    return permissions.includes(permission);
}

const managementItems = [
    {
        title: t('VendingMachine (CustomerView) Tab'),
        link: 'https://www.youtube.com/watch?v=GiloQqqvAcA',
        subtitles: [
            t('Machine status in a glance'),
            t('Active machines that currently in operation'),
            t('Machine View = All machines (in operation & not in operation)')
        ]
    },
    {
        title: t('Site Management Tab'),
        link: 'https://www.youtube.com/watch?v=LieUqAR9V-A',
        subtitles: [
            t('Create new customer profile'),
            t('Bind Machine to Site')
        ]
    },
    {
        title: t('Machine Management Tab'),
        link: 'https://www.youtube.com/watch?v=4mg3jn_aXi4',
        subtitles: [
            t('Machine View'),
            t('Machine Setting'),
            t('APK setting (upload video & photo)')
        ]
    },
    {
        title: t('Data Management Tab'),
        link: 'https://www.youtube.com/watch?v=XdD8GOEed0M',
        subtitles: [
            t('Machine Serial Number'),
            t('Cashless Terminal'),
            t('SIM Card'),
            t('Location Types'),
            t('Refilling Routes')
        ]
    },
    {
        title: t('Product Management Tab'),
        link: 'https://www.youtube.com/watch?v=9O6iD8yW2ZY',
        subtitles: [
            t('Create new Product'),
            t('Mappings: Define Products to be in that menu'),
            t('Categories'),
            t('Product Labels')
        ]
    },
    {
        title: t('Product Availability (Warehouse)'),
        link: 'https://www.youtube.com/watch?v=sfQRlfmhCqA',
        subtitles: [
            t('Stock Incoming & Incoming History'),
            t('Warehouse Log'),
            t('Last7d sold qty'),
            t('Qty in Warehouse - Picked Qty = Remaining Qty'),
            t('Planning: Qty needed and Capped Qty per Channel')
        ]
    },
    {
        title: t('Job assignment and stock planning'),
        link: 'https://www.youtube.com/watch?v=YJvXJQYcxgE',
        subtitles: [
            t('Assign Job to Driver'),
            t('Check Qty needed (planning for Jobs on Date)'),
            t('Order Incoming if Qty not enough'),
            t('Set capped qty if certain sku not enough/phase-out soon'),
            t('Route optimization')
        ]
    },
    {
        title: t('Machine Health Dashboard'),
        link: '',
        subtitles: []
    },
    {
        title: t('Change to new SKU and Mapping'),
        link: 'https://youtu.be/kCXIEQUFlNI',
        subtitles: [
            t('Create new Mapping. Replicate from current Mapping, replace old sku with new sku'),
            t('Link Current Mapping to New Mapping'),
            t('Implementation of New Mapping: Job > Stock Action > Implement New Mapping'),
            t('Picker: Pick goods including the new sku'),
            t('Driver refill to machine. Return old sku to warehouse'),
            t('Digital screen will updated with new sku')
        ]
    }
];

const driverItems = [
    {
        title: t('Stock refill instruction for Model E/F/EG'),
        link: 'https://www.youtube.com/watch?v=rMF1coC9zZ4',
        subtitles: [
            t('Take goods from Truck'),
            t("Open machine's lock"),
            t('Frost defrost'),
            t('Open freezer door'),
            t('Pull out rack (start from Left rack)'),
            t('Refill goods into machine (within 15 mins)'),
            t('Clean: use rag, moisture and seal area'),
            t("Click 'Stock-in' in Job"),
            t('Update inventory qty to VMC'),
            t('Clear error'),
            t('Refill spoon'),
            t('External cleaning')
        ]
    },
    {
        id: '1a',
        title: t('Revised: update inventory qty to VMC and Clear Error'),
        link: 'https://youtube.com/shorts/ogIv8Q-PuAU',
        subtitles: []
    }
];

</script>

