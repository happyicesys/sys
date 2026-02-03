<template>
  <Head title="Tutorials" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Tutorials
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Management Section -->
        <div v-if="hasPermission('read tutorials-operators')" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6 bg-white border-b border-gray-200">
            <h3 class="text-lg font-medium text-red-600 mb-4">Management to know</h3>
            <div class="space-y-6">
              <div v-for="(item, index) in managementItems" :key="index" class="border-b pb-4 last:border-b-0 last:pb-0">
                <div class="flex justify-between items-start">
                  <div>
                    <h4 class="font-medium text-gray-900">{{ index + 1 }}) {{ item.title }}</h4>
                    <ul class="mt-2 list-disc list-inside text-sm text-gray-600 pl-4">
                      <li v-for="(sub, subIndex) in item.subtitles" :key="subIndex">{{ sub }}</li>
                    </ul>
                  </div>
                  <a v-if="item.link" :href="item.link" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                    Watch Video
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
            <h3 class="text-lg font-medium text-red-600 mb-4">Drivers to know</h3>
            <div class="space-y-6">
              <div v-for="(item, index) in driverItems" :key="index" class="border-b pb-4 last:border-b-0 last:pb-0">
                <div class="flex justify-between items-start">
                  <div>
                    <h4 class="font-medium text-gray-900">{{ index + 1 }}) {{ item.title }}</h4>
                    <ul class="mt-2 list-decimal list-inside text-sm text-gray-600 pl-4">
                      <li v-for="(sub, subIndex) in item.subtitles" :key="subIndex">{{ sub }}</li>
                    </ul>
                  </div>
                  <a :href="item.link" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                    Watch Video
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

const page = usePage();
const permissions = page.props.auth.permissions;

function hasPermission(permission) {
    return permissions.includes(permission);
}

const managementItems = [
    {
        title: 'VendingMachine (CustomerView) Tab',
        link: 'https://www.youtube.com/watch?v=GiloQqqvAcA',
        subtitles: [
            'Machine status in a glance',
            'Active machines that currently in operation',
            'Machine View = All machines (in operation & not in operation)'
        ]
    },
    {
        title: 'Customer Management Tab',
        link: 'https://www.youtube.com/watch?v=LieUqAR9V-A',
        subtitles: [
            'Create new customer profile',
            'Bind Machine to Customer'
        ]
    },
    {
        title: 'Machine Management Tab',
        link: 'https://www.youtube.com/watch?v=4mg3jn_aXi4',
        subtitles: [
            'Machine View',
            'Machine Setting',
            'APK setting (upload video & photo)'
        ]
    },
    {
        title: 'Data Management Tab',
        link: 'https://www.youtube.com/watch?v=XdD8GOEed0M',
        subtitles: [
            'Machine Serial Number',
            'Cashless Terminal',
            'SIM Card',
            'Location Types',
            'Refilling Routes'
        ]
    },
    {
        title: 'Product Management Tab',
        link: 'https://www.youtube.com/watch?v=9O6iD8yW2ZY',
        subtitles: [
            'Create new Product',
            'Mappings: Define Products to be in that menu',
            'Categories',
            'Product Labels'
        ]
    },
    {
        title: 'Product Availability (Warehouse)',
        link: 'https://www.youtube.com/watch?v=sfQRlfmhCqA',
        subtitles: [
            'Stock Incoming & Incoming History',
            'Warehouse Log',
            'Last7d sold qty',
            'Qty in Warehouse - Picked Qty = Remaining Qty',
            'Planning: Qty needed and Capped Qty per Channel'
        ]
    },
    {
        title: 'Job assignment and stock planning',
        link: 'https://www.youtube.com/watch?v=YJvXJQYcxgE',
        subtitles: [
            'Assign Job to Driver',
            'Check Qty needed (planning for Jobs on Date)',
            'Order Incoming if Qty not enough',
            'Set capped qty if certain sku not enough/phase-out soon',
            'Route optimization'
        ]
    },
    {
        title: 'Machine Health Dashboard',
        link: '',
        subtitles: []
    },
    {
        title: 'Find Menu (fr Customer); Find Setting Chart (fr Machine)',
        link: '',
        subtitles: []
    }
];

const driverItems = [
    {
        title: 'Stock refill instruction for Model E/F/EG',
        link: 'https://www.youtube.com/watch?v=rMF1coC9zZ4',
        subtitles: [
            'Take goods from Truck',
            'Open machine\'s lock',
            'Frost defrost',
            'Open freezer door',
            'Pull out rack (start from Left rack)',
            'Refill goods into machine (within 15 mins)',
            'Clean: use rag, moisture and seal area',
            'Click \'Stock-in\' in Job',
            'Update inventory qty to VMC',
            'Clear error',
            'Refill spoon',
            'External cleaning'
        ]
    }
];

</script>

