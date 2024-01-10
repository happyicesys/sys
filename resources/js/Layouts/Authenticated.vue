<script setup>
import { ref } from 'vue';
import BreezeDropdown from '@/Components/Dropdown.vue';
import BreezeDropdownLink from '@/Components/DropdownLink.vue';
import BreezeResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ArrowsPointingInIcon, ClipboardDocumentListIcon, CodeBracketSquareIcon, Cog8ToothIcon, CircleStackIcon, CalendarDaysIcon, CommandLineIcon, CreditCardIcon, DocumentTextIcon, LinkIcon, RectangleStackIcon, TruckIcon, UserCircleIcon, UserGroupIcon } from '@heroicons/vue/20/solid'

const navigation = [
    {
        name: 'Dashboard',
        icon: ClipboardDocumentListIcon,
        current: false,
        href: 'dashboard',
        permission: 'read dashboard',
    },
    {
        name: 'Vending Machines',
        icon: CommandLineIcon,
        current: false,
        href: 'vends',
        permission: 'read vends',
    },
    {
        name: 'Transactions',
        icon: CreditCardIcon,
        current: false,
        href: 'vends-transactions',
        permission: 'read transactions',
    },
    {
        name: 'Products',
        icon: RectangleStackIcon,
        current: false,
        href: 'products',
        permission: 'read products',
    },
    {
        name: 'Product Mapping',
        icon: LinkIcon,
        current: false,
        href: 'product-mappings',
        permission: 'read product-mappings',
    },
    {
        name: 'Report',
        icon: DocumentTextIcon,
        current: false,
        href: 'reports',
        permission: 'read reports',
        children: [
            {name: 'Month End Stock Count', href: '/reports/stock-count'},

            {name: 'Sales Report', href: '/reports/sales/operator'},

            {name: 'GP by VM', href: '/reports/gp/vend'},
            {name: 'GP by Product', href: '/reports/gp/product'},
            {name: 'GP by Category', href: '/reports/gp/category'},
            {name: 'GP by Location Type', href: '/reports/gp/location-type'},
        ]
    },
    // {
    //     name: 'Customers',
    //     icon: UserGroupIcon,
    //     current: false,
    //     href: 'customers'
    // },
    // {
    //     name: 'Products',
    //     icon: RectangleStackIcon,
    //     current: false,
    //     children: [
    //         {name: 'List', href: '/products'},
    //         {name: 'Unit Cost', href: '/products/unit-costs'},
    //     ]
    // },
    // {
    //     name: 'Profiles',
    //     icon: BuildingOfficeIcon,
    //     current: false,
    //     href: 'profiles'
    // },
    {
        name: 'Operators',
        icon: UserGroupIcon,
        current: false,
        href: 'operators',
        permission: 'read operators',
    },
    {
        name: 'Resource Center',
        icon: CircleStackIcon,
        current: false,
        href: 'resource-centers',
        permission: 'read resource-centers',
    },
    {
        name: 'Users',
        icon: UserCircleIcon,
        current: false,
        href: 'users',
        permission: 'read users',
    },
    {
        name: 'Operations',
        icon: ArrowsPointingInIcon,
        current: false,
        href: 'holidays',
        permission: 'admin-access vends',
        children: [
            {name: 'Vend & Criteria Bindings', href: '/vend-criteria-bindings'},
            {name: 'Criteria', href: '/vend-criterias'},
            {name: 'Weightage (Location Type)', href: '/location-types'},
            {name: 'Weightage (Error Code)', href: '/vend-channel-errors'},
            {name: 'Holidays', href: '/holidays'},
        ]
    },
    {
        name: 'Device Management',
        icon: Cog8ToothIcon,
        current: false,
        href: 'settings',
        permission: 'admin-access vends',
    },
    // {
    //     name: 'OAuth & API',
    //     icon: CodeBracketSquareIcon,
    //     current: false,
    //     href: 'oauth-clients',
    //     permission: 'admin-access vends',
    // },
    {
        name: 'Delivery Platform',
        icon: TruckIcon,
        current: false,
        href: 'delivery-product-mappings',
        permission: 'admin-access vends',
        children: [
            {name: 'Orders', href: '/delivery-platform-orders'},
            {name: 'Product Mapping', href: '/delivery-product-mappings'},
            {name: 'Campaign', href: '/delivery-platform-campaigns'}
        ]
    },
    // {
    //     name: 'Map',
    //     icon: MapIcon,
    //     current: false,
    //     href: 'maps',
    //     permission: 'read vends',
    // },
    // {
    //     name: 'Data Settings',
    //     icon: FolderIcon,
    //     current: false,
    //     children: [
    //         // {name: 'Bank', href: '/banks'},
    //         // {name: 'Cashless Providers', href: '/cashless-providers'},
    //         // {name: 'Cashless Terminals', href: '/cashless-terminals'},
    //         {name: 'Country & Currency', href: '/countries'},
    //         {name: 'Cust Categories', href: '/categories?classname=App\\Models\\Customer'},
    //         {name: 'Cust Category Groups', href: '/category-groups'},
    //         {name: 'Payment Methods', href: '/payment-methods'},
    //         // {name: 'Payment Terms', href: '/payment-terms'},
    //         {name: 'Permission', href: '/permissions'},
    //         {name: 'Role', href: '/roles'},
    //         // {name: 'Simcard', href: '/simcards'},
    //         {name: 'Status', href: '/statuses'},
    //         // {name: 'Tags', href: '/tags'},
    //         // {name: 'Telco', href: '/telcos'},
    //         // {name: 'Tax', href: '/taxes'},
    //         {name: 'UOM', href: '/uoms'},
    //         // {name: 'Zone', href: '/zones'},
    //     ],
    // },
]

const showingNavigationDropdown = ref(false);
const roles = usePage().props.auth.roles
const permissions = usePage().props.auth.permissions

</script>

<template>
    <div>
        <div class="min-h-screen md:flex bg-gray-100">
            <div
                class="hidden md:block flex-none flex-col border-r border-gray-200 pt-5 pb-4 bg-white md:w-1/6 xl:w-2/12">

                <div class="flex items-center justify-center flex-shrink-0 px-1 object-scale-down">
                    <Link href="/">
                        <div class="h-fit w-fit">
                            <img class="object-cover h-24 w-36" src="/img/logo.png" alt="Company Logo">
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
                                {{ item.name }}
                                </Link>
                            </div>
                            <Disclosure as="div" v-else class="space-y-1" v-slot="{ open }">
                                <DisclosureButton
                                    v-if="permissions.includes(item.permission)"
                                    :class="[item.current ? 'bg-gray-100 text-gray-900' : 'bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900', 'group w-full flex items-center pl-2 pr-1 py-2 text-left text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500']">
                                    <component :is="item.icon"
                                        class="mr-3 flex-shrink-0 h-6 w-6 text-gray-400 group-hover:text-gray-500"
                                        aria-hidden="true" />
                                    <span class="flex-1">
                                        {{ item.name }}
                                    </span>
                                    <svg :class="[open ? 'text-gray-400 rotate-90' : 'text-gray-300', 'ml-3 flex-shrink-0 h-5 w-5 transform group-hover:text-gray-400 transition-colors ease-in-out duration-150']"
                                        viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M6 6L14 10L6 14V6Z" fill="currentColor" />
                                    </svg>
                                </DisclosureButton>
                                <DisclosurePanel class="space-y-1 py-2 bg-gray-100">
                                    <Link v-for="subItem in item.children" :key="subItem.name" as="a"
                                            :href="subItem.href">
                                        <DisclosureButton
                                            class="group w-full flex items-center pl-3 pr-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-gray-900 hover:bg-gray-200"
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
            <div class="md:w-5/6 xl:w-10/12">
                <!-- Page Heading -->
                <header class="bg-white shadow flex justify-between" v-if="$slots.header">
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
                                {{ item.name }}
                            </BreezeResponsiveNavLink>
                        </div>
                        <Disclosure as="div" v-else class="space-y-1" v-slot="{ open }">
                            <DisclosureButton class="pt-2 pb-2 mb-1 pl-4 space-y-1 flex" v-if="permissions.includes(item.permission)">
                                <span class="">
                                    {{ item.name }}
                                </span>
                                <svg :class="[open ? 'text-gray-400 rotate-90' : 'text-gray-300', 'ml-3 flex-shrink-0 h-5 w-5 transform group-hover:text-gray-400 transition-colors ease-in-out duration-150']"
                                    viewBox="0 0 20 20" aria-hidden="true">
                                    <path d="M6 6L14 10L6 14V6Z" fill="currentColor" />
                                </svg>
                            </DisclosureButton>
                            <DisclosurePanel class="py-1 space-y-1">
                                <Link v-for="subItem in item.children" :key="subItem.name" as="a"
                                        :href="subItem.href">
                                    <DisclosureButton
                                        class="group w-full flex items-center pl-11 pr-2 py-3 text-sm font-medium text-gray-600 rounded-md hover:text-gray-900 hover:bg-gray-50">
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
                </main>
            </div>
        </div>
    </div>
</template>
