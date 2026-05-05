<script setup>
import { computed, ref } from 'vue';
import BreezeDropdown from '@/Components/Dropdown.vue';
import BreezeDropdownLink from '@/Components/DropdownLink.vue';
import BreezeResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ArrowsPointingInIcon, BuildingOfficeIcon, ClipboardDocumentListIcon, CodeBracketSquareIcon, Cog8ToothIcon, CircleStackIcon, CalendarDaysIcon, CommandLineIcon, CreditCardIcon, DocumentTextIcon, FolderIcon, IdentificationIcon, LinkIcon, MapPinIcon, RectangleStackIcon, TruckIcon, UserCircleIcon, UserGroupIcon, BookOpenIcon, TicketIcon } from '@heroicons/vue/20/solid'

const page = usePage()

const navigation = computed(() => [
    {
        name: 'Dashboards',
        icon: ClipboardDocumentListIcon,
        current: false,
        href: 'dashboard',
        permission: 'read dashboard',
        tagline: null,
        children: [
            {name: 'Performance', href: '/dashboard/performance', permission: 'read dashboard-performance'},
            {name: 'Machine Health', href: '/reports/machine-health', permission: 'read dashboard-machine-health'},
        ]
    },

    // {
    //     name: 'Vending Machines',
    //     icon: CommandLineIcon,
    //     current: false,
    //     href: 'vends.customer',
    //     permission: 'read vends',
    //     tagline: 'Customer',
    // },
    // {
    //     name: 'Vending Machines',
    //     icon: CommandLineIcon,
    //     current: false,
    //     href: 'vends',
    //     permission: 'read vends',
    //     tagline: 'Device',
    // },
    {
        name: 'Vending Machines',
        icon: CommandLineIcon,
        current: false,
        href: 'vends.customer',
        permission: 'read vends',
        tagline: null,
        // children: [
        //     {name: 'View By Customers', href: '/vends/customers', permission: 'read vend-customers'},
        // ]
    },
    {
        name: 'Transactions',
        icon: CreditCardIcon,
        current: false,
        href: 'vends-transactions',
        permission: 'read transactions',
        tagline: null,
        children: [
            {name: 'All Transactions', href: '/vends/transactions', permission: 'read transactions-sales'},
            {name: 'Payment Gateway Txn', href: '/vends/payment-gateway-transactions', permission: 'read transactions-payment-gateway'},
        ]
    },
    {
        name: 'Daily Operations',
        icon: ArrowsPointingInIcon,
        current: false,
        href: 'ops-jobs',
        permission: 'read operations',
        tagline: null,
        children: [
            {name: 'Jobs', href: '/ops-jobs', permission: 'read operation-jobs'},
            {name: 'Summary', href: '/ops-jobs/summary', permission: 'read operation-job-summaries'}, // Changed permission to match seeder
        ]
    },
    // {
    //     name: 'Operations',
    //     icon: ArrowsPointingInIcon,
    //     current: false,
    //     href: 'holidays',
    //     permission: 'read operations',
    //     tagline: null,
    //     children: [
    //         {name: 'Jobs', href: '/ops-jobs'},
    //         // {name: 'Vend & Criteria Bindings', href: '/vend-criteria-bindings'},
    //         // {name: 'Criteria', href: '/vend-criterias'},
    //         // {name: 'Weightage (Location Type)', href: '/location-types'},
    //         // {name: 'Weightage (Error Code)', href: '/vend-channel-errors'},
    //         // {name: 'Holidays', href: '/holidays'},
    //     ]
    // },
    // {
    //     name: 'Machine Management',
    //     icon: Cog8ToothIcon,
    //     current: false,
    //     href: 'settings',
    //     permission: 'admin-access vends',
    //     tagline: null,
    // },
    {
        name: 'Machine Management',
        icon: Cog8ToothIcon,
        current: false,
        href: 'settings',
        permission: 'read vend-settings',
        tagline: null,
        children: [
            {name: 'Machines View', href: '/vends', permission: 'read machine-view'},
            {name: 'Machines Settings', href: '/settings', permission: 'read machine-settings'},
            {name: 'Machine Alert Parameters', href: '/machine-alert-parameters', permission: 'read machine-alert-parameters'},
            {name: 'APK Settings', href: '/apk-settings', permission: 'read apk-settings'},
            {name: 'Setting Charts', href: '/vend-configs', permission: 'read vend-configs'},
            {name: 'Machine Prefix', href: '/vend-prefixes', permission: 'read vend-prefixes'},
            {name: 'Modem IMEI', href: '/modem-units', permission: 'read modem-imei'},
            // {name: 'Campaigns', href: '/campaigns'},
        ]
    },
    {
        name: 'Customer Management',
        icon: IdentificationIcon,
        current: false,
        href: 'customers',
        permission: 'read customers',
        tagline: null,
        children: [
            {name: 'Customers', href: '/customers', permission: 'read customers'},
        ]
    },
    {
        name: 'Product Management',
        icon: RectangleStackIcon,
        current: false,
        href: 'products',
        permission: 'read products',
        tagline: null,
        children: [
            {name: 'Products', href: '/products', permission: 'read products'},
            {name: 'Mappings', href: '/product-mappings', permission: 'read product-mappings'},
            ...(page.props.isCmsUrlSet ? [{name: 'Warehouse Qty (via API) & Planning', href: '/products/availability', permission: 'read product-availability'}] : []),
            ...(!page.props.isCmsUrlSet ? [{name: 'Warehouse Qty & Planning', href: '/products/movements', permission: 'read products'}] : []),
            {name: 'Categories', href: '/category-groups?classname=App\\Models\\Product', 'permission': 'read product-categories'},
            {name: 'SubCategories', href: '/categories?classname=App\\Models\\Product', 'permission': 'read product-subcategories'},
            {name: 'Product Labels', href: '/tags?classname=App\\Models\\Product', 'permission': 'read product-campaign-labels'},
        ]
    },
    {
        name: 'Campaign Management',
        icon: TicketIcon,
        current: false,
        href: 'campaigns',
        permission: 'read product-campaign-labels',
        tagline: null,
        children: [
            {name: 'Settings', href: '/campaigns', permission: 'read product-campaign-labels'},
            {name: 'Voucher', href: '/vouchers', permission: 'read vouchers'},
        ]
    },
    {
        name: 'Data Management',
        icon: FolderIcon,
        current: false,
        href: 'data-management',
        permission: 'read data-settings',
        children: [
            {name: 'Machine Serial No', href: '/vend-serial-numbers', permission: 'read serial-numbers'},
            {name: 'Machine Models', href: '/vend-models', permission: 'read vend-models'},
            {name: 'Machine Key No', href: '/keys', permission: 'read keys'},
            {name: 'Modem Models', href: '/modem-types', permission: 'read modem-models'},
            {name: 'Cashless Terminal Models', href: '/cashless-providers', permission: 'read cashless-providers'},
            {name: 'Cashless Terminals', href: '/cashless-terminals', permission: 'read cashless-terminals'},
            {name: 'Telco', href: '/telcos', permission: 'read telcos'},
            {name: 'SIM Card', href: '/simcards', permission: 'read simcards'},
            {name: 'Machine Contract Type', href: '/vend-contracts', permission: 'read vend-contracts'},
            {name: 'Location Types', href: '/location-types', permission: 'read location-types'},
            {name: 'HID Card', href: '/hid-cards', permission: 'read hid-cards'},
            {name: 'Refilling Routes', href: '/zones', permission: 'read zones'},
        ],
    },
    {
        name: 'Delivery Platform',
        icon: TruckIcon,
        current: false,
        href: 'delivery-product-mappings',
        permission: 'read delivery-platforms',
        tagline: null,
        children: [
            {name: 'Grab Platform ID', href: '/delivery-platform-ref-numbers', permission: 'read delivery-platform-vends'},
            {name: 'Machines', href: '/delivery-product-mapping-vends', permission: 'read delivery-platform-vends'},
            {name: 'Grab Orders', href: '/delivery-platform-orders', permission: 'read delivery-platform-orders'},
            {name: 'Grab Product Mapping', href: '/delivery-product-mappings', permission: 'read delivery-platform-product-mappings'},
            {name: 'Grab Campaign', href: '/delivery-platform-campaigns', permission: 'read delivery-platform-campaigns'},
        ]
    },
    // {
    //     name: 'Products',
    //     icon: RectangleStackIcon,
    //     current: false,
    //     href: 'products',
    //     permission: 'read products',
    //     tagline: null,
    // },
    // {
    //     name: 'Product Mapping',
    //     icon: LinkIcon,
    //     current: false,
    //     href: 'product-mappings',
    //     permission: 'read product-mappings',
    //     tagline: null,
    // },
    {
        name: 'Report',
        icon: DocumentTextIcon,
        current: false,
        href: 'reports',
        permission: 'read reports',
        tagline: null,
        children: [
            {name: 'Stock Count Dashboard', href: '/reports/stock-count-dashboard'},
            {name: 'Daily Stock Count', href: '/reports/stock-count'},
            {name: 'Machine Monthly Snapshot', href: '/reports/snapshot'},
            {name: 'Sales Report', href: '/reports/sales/operator'},
            {name: 'GP by VM', href: '/reports/gp/vend'},
            {name: 'GP by Product', href: '/reports/gp/product'},
            {name: 'Sales Performance by Product', href: '/reports/sales-performance/product'},
            // {name: 'GP by Category', href: '/reports/gp/category'},
            {name: 'GP by Location Type', href: '/reports/gp/location-type'},
        ]
    },
    // {
    //     name: 'Profiles',
    //     icon: BuildingOfficeIcon,
    //     current: false,
    //     href: 'profiles',
    //     permission: 'read vends',
    // },
    // {
    //     name: 'Operators',
    //     icon: UserGroupIcon,
    //     current: false,
    //     href: 'operators',
    //     permission: 'read operators',
    //     tagline: null,
    // },
    // {
    //     name: 'Resource Center',
    //     icon: CircleStackIcon,
    //     current: false,
    //     href: 'resource-centers',
    //     permission: 'read resource-centers',
    //     tagline: null,
    // },
    // {
    //     name: 'Users',
    //     icon: UserCircleIcon,
    //     current: false,
    //     href: 'users',
    //     permission: 'read users',
    //     tagline: null,
    // },
    {
        name: 'Admin',
        icon: UserCircleIcon,
        current: false,
        href: 'admins',
        permission: 'read users',
        tagline: null,
        children: [
            {name: 'Operators', href: '/operators', permission: 'read operators'},
            {name: 'Users', href: '/users', permission: 'read users'},
        ]
    },
    {
        name: 'Tutorial (with CMS)',
        icon: BookOpenIcon,
        current: false,
        href: 'resource-centers',
        permission: 'read resource-centers',
        tagline: null,
    },

    {
        name: 'Tutorial',
        icon: BookOpenIcon,
        current: false,
        href: 'tutorials',
        permission: 'read tutorials',
        tagline: null,
    },


    // {
    //     name: 'OAuth & API',
    //     icon: CodeBracketSquareIcon,
    //     current: false,
    //     href: 'oauth-clients',
    //     permission: 'admin-access vends',
    // },
    // {
    //     name: 'Map',
    //     icon: MapIcon,
    //     current: false,
    //     href: 'maps',
    //     permission: 'read vends',
    // },
]);

const showingNavigationDropdown = ref(false);
const logoUrl = computed(() => page.props.logoUrl)
const permissions = page.props.auth.permissions
const roles = page.props.auth.roles
const smallLogoUrl = page.props.smallLogoUrl
const defaultLogoUrl = computed(() => page.props.defaultLogoUrl)
const useContainLogo = computed(() => logoUrl.value !== defaultLogoUrl.value)


</script>

<template>
    <div>
        <div class="min-h-screen w-full md:flex bg-gray-100">
            <div
                class="hidden md:block flex-none flex-col border-r border-gray-200 pt-5 pb-4 bg-white md:w-1/6 xl:w-2/12 2xl:w-1/12 2xl:min-w-48">

                <div class="flex items-center justify-center flex-shrink-0 px-1 object-scale-down">
                    <Link href="/">
                        <div class="h-fit w-fit">
                            <img :class="[useContainLogo ? 'object-contain h-24 w-36 p-2' : 'object-cover h-24 w-36']" :src="logoUrl" alt="Company Logo">
                        </div>
                    </Link>
                </div>
                <div class="mt-5 flex-grow flex flex-col border-t border-gray-200 pt-2">
                    <nav class="flex-1 px-2 space-y-1 bg-white" aria-label="Sidebar">
                        <template v-for="item in navigation" :key="item.name">
                            <div v-if="!item.children">
                                <Link :href="route(item.href)"
                                    v-if="permissions.includes(item.permission)"
                                    :class="[$page.url === '/' + item.href ? 'bg-gray-100 text-gray-900' : 'bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900', 'group w-full flex items-center pl-2 py-2 text-sm font-medium rounded-md']">
                                <component :is="item.icon"
                                    :class="[$page.url === '/' + item.href ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500', 'mr-3 flex-shrink-0 h-6 w-6']"
                                    aria-hidden="true" />
                                <span class="flex flex-col">
                                    <span>
                                        {{ item.name }}
                                    </span>
                                    <span class="text-[12px] text-gray-500">
                                        {{ item.tagline }}
                                    </span>
                                </span>
                                </Link>
                            </div>
                            <Disclosure as="div" v-else class="flex flex-col justify-start space-y-1" v-slot="{ open }">
                                <DisclosureButton
                                    v-if="permissions.includes(item.permission)"
                                    :class="[item.current ? 'bg-gray-100 text-gray-900' : 'bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900', 'group w-full flex items-center pl-2 pr-1 py-2 text-left text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500']">
                                    <component :is="item.icon"
                                        class="mr-3 flex-shrink-0 h-6 w-6 text-gray-400 group-hover:text-gray-500"
                                        aria-hidden="true" />
                                    <span class="flex flex-1 flex-col">
                                        <span>
                                            {{ item.name }}
                                        </span>
                                        <span class="text-[12px] text-gray-500">
                                            {{ item.tagline }}
                                        </span>
                                    </span>
                                    <svg :class="[open ? 'text-gray-400 rotate-90' : 'text-gray-300', 'ml-3 flex-shrink-0 h-5 w-5 transform group-hover:text-gray-400 transition-colors ease-in-out duration-150']"
                                        viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M6 6L14 10L6 14V6Z" fill="currentColor" />
                                    </svg>
                                </DisclosureButton>
                                <DisclosurePanel class="-ml-2 space-y-1 py-2 bg-gray-100">
                                    <Link v-for="subItem in item.children" :key="subItem.name" as="a"
                                            :href="subItem.href">
                                        <DisclosureButton
                                            class="group w-full flex items-center justify-start text-left pl-4 pr-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-gray-900 hover:bg-gray-200"
                                            v-if="subItem && (!subItem.permission || (subItem.permission && permissions.includes(subItem.permission)))"
                                            >
                                            {{ subItem.name }}
                                        </DisclosureButton>
                                    </Link>
                                </DisclosurePanel>
                            </Disclosure>
                        </template>
                    </nav>
                </div>
            </div>

            <!-- <div class="flex-auto mx-auto"> -->
            <div class="md:w-5/6 xl:w-10/12 2xl:w-11/12">
                <!-- Page Heading -->
                <header class="bg-white shadow flex justify-between" v-if="$slots.header">
                    <span class="md:hidden">
                        <div class="flex items-center justify-center flex-shrink-0 px-1 object-scale-down">
                            <Link href="/">
                                <div class="h-fit w-fit py-2 px-1">
                                    <img class="h-14 w-16" :src="smallLogoUrl" alt="Company Logo">
                                </div>
                            </Link>
                        </div>
                    </span>
                    <div class="max-w-7xl  my-auto py-4 px-4 lg:px-8">
                        <slot name="header" />
                    </div>
                    <div>
                        <nav class="bg-white border-b border-gray-100">
                            <!-- Primary Navigation Menu -->
                            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                                <div class="flex justify-between h-16">
                                    <div class="hidden md:flex sm:items-center sm:ml-6">
                                        <!-- Settings Dropdown -->
                                        <div class="ml-3 relative">
                                            <BreezeDropdown align="right" width="48">
                                                <template #trigger>
                                                    <span class="inline-flex rounded-md">
                                                        <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                                            {{ $page.props.auth && $page.props.auth.user ? $page.props.auth.user.name : null }}

                                                            <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    </span>
                                                </template>

                                                <template #content>
                                                    <BreezeDropdownLink :href="route('self')" method="get" as="button">
                                                        Account Settings
                                                    </BreezeDropdownLink>
                                                    <BreezeDropdownLink :href="route('logout')" method="post" as="button">
                                                        Log Out
                                                    </BreezeDropdownLink>
                                                </template>
                                            </BreezeDropdown>
                                        </div>
                                    </div>

                                    <!-- Hamburger -->
                                    <div class="my-auto md:hidden">
                                        <button @click="showingNavigationDropdown = ! showingNavigationDropdown" class="inline-flex items-center justify-center p-3 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out bg-gray-100">
                                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                                <path :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                                <path :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </nav>
                    </div>
                </header>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="md:hidden bg-gray-50">

                    <template v-for="item in navigation" :key="item.name">
                        <div v-if="!item.children" class="py-1 space-y-1">
                            <BreezeResponsiveNavLink
                            v-if="permissions.includes(item.permission)"
                            :href="route(item.href)" :active="route().current(item.href)">
                                <span class="flex flex-col">
                                    <span>
                                        {{ item.name }}
                                    </span>
                                    <span class="text-[12px] text-gray-500">
                                        {{ item.tagline }}
                                    </span>
                                </span>
                            </BreezeResponsiveNavLink>
                        </div>
                        <Disclosure as="div" v-else class="space-y-1" v-slot="{ open }">
                            <DisclosureButton class="pt-2 pb-2 mb-1 pl-4 space-y-1 flex w-full justify-start text-left" v-if="permissions.includes(item.permission)">
                                <span class="flex flex-col">
                                    <span>
                                        {{ item.name }}
                                    </span>
                                    <span class="text-[12px] text-gray-500">
                                        {{ item.tagline }}
                                    </span>
                                </span>
                                <svg :class="[open ? 'text-gray-400 rotate-90' : 'text-gray-300', 'ml-3 flex-shrink-0 h-5 w-5 transform group-hover:text-gray-400 transition-colors ease-in-out duration-150']"
                                    viewBox="0 0 20 20" aria-hidden="true">
                                    <path d="M6 6L14 10L6 14V6Z" fill="currentColor" />
                                </svg>
                            </DisclosureButton>
                            <DisclosurePanel class="-ml-2 py-1 space-y-1">
                                <Link v-for="subItem in item.children" :key="subItem.name" as="a"
                                        :href="subItem.href"
                                >
                                    <DisclosureButton
                                        v-if="subItem && (!subItem.permission || (subItem.permission && permissions.includes(subItem.permission)))"
                                        class="group w-full flex items-center justify-start text-left pl-14 pr-2 py-3 text-sm font-medium text-gray-600 rounded-md hover:text-gray-900 hover:bg-gray-50">
                                        {{ subItem.name }}
                                    </DisclosureButton>
                                </Link>
                            </DisclosurePanel>
                        </Disclosure>
                    </template>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-gray-300">
                        <div class="px-4">
                            <div class="font-medium text-base text-gray-800">{{ $page.props.auth && $page.props.auth.user ? $page.props.auth.user.name : null }}</div>
                            <div class="font-medium text-sm text-gray-500">{{ $page.props.auth && $page.props.auth.user ? $page.props.auth.user.email :null }}</div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <BreezeResponsiveNavLink :href="route('self')" method="get" as="button">
                                Account Settings
                            </BreezeResponsiveNavLink>
                        </div>

                        <div class="mt-3 space-y-1">
                            <BreezeResponsiveNavLink :href="route('logout')" method="post" as="button">
                                Log Out
                            </BreezeResponsiveNavLink>
                        </div>
                    </div>
                </div>

                <!-- Page Content -->
                <main class="bg-gray-100">
                    <slot />
                    <div class="flex bg-gray-100 items-center px-3 py-6 text-sm">
                        © Copyright 2024 Happy Ice Pte Ltd. All Rights Reserved.
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
