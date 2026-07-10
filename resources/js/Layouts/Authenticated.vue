<script setup>
import { computed, ref } from 'vue';
import BreezeDropdown from '@/Components/Dropdown.vue';
import BreezeDropdownLink from '@/Components/DropdownLink.vue';
import BreezeResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import MonthlySalesPopup from '@/Components/MonthlySalesPopup.vue';
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
    //     tagline: 'Site',
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
        name: 'Operations',
        icon: CommandLineIcon,
        current: false,
        href: 'vends.customer',
        permission: 'read vends',
        tagline: null,
        children: [
            {name: 'Dashboard', href: '/vends/customers', permission: 'read vends'},
            {name: 'Ops Performance', href: '/vends/ops-performance', permission: 'read vends'},
        ]
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
            {name: 'Daily Summary', href: '/vends/transactions-daily-summary', permission: 'read transactions-sales'},
            {name: 'Payment Gateway Txn', href: '/vends/payment-gateway-transactions', permission: 'read transactions-payment-gateway'},
            {name: 'Refund Requests', href: '/refunds', permission: 'read refunds'},
            {name: 'Refund Settlement', href: '/refund-settlements', permission: 'read refunds'},
        ]
    },
    {
        name: 'Daily Jobs',
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
            {name: 'UI Setting (Marketing & Campaign Remote Setting)', href: '/apk-settings', permission: 'read apk-settings'},
            {name: 'Setting Charts', href: '/vend-configs', permission: 'read vend-configs'},
            {name: 'Machine Prefix', href: '/vend-prefixes', permission: 'read vend-prefixes'},
            {name: 'Modem IMEI', href: '/modem-units', permission: 'read modem-imei'},
            {name: 'Smart Freezer Settings', href: '/smart-freezer-settings', permission: 'read machine-settings'},
            // {name: 'Campaigns', href: '/campaigns'},
        ]
    },
    {
        name: 'Site Management',
        icon: IdentificationIcon,
        current: false,
        href: 'customers',
        permission: 'read customers',
        tagline: null,
        children: [
            {name: 'Sites', href: '/customers', permission: 'read customers'},
            {name: 'Summary & Comm', href: '/customers/summary', permission: 'read customers'},
            {name: 'Performance', href: '/customers/performance', permission: 'read customers'},
            {name: 'Tags', href: '/tags?classname=App\\Models\\Customer', permission: 'read customers'},
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
            {name: 'Card Terminals', href: '/card-terminals', permission: 'read card-terminals'},
            {name: 'Cashless Terminals', href: '/cashless-terminals', permission: 'read cashless-terminals'},
            {name: 'Telco', href: '/telcos', permission: 'read telcos'},
            {name: 'SIM Card', href: '/simcards', permission: 'read simcards'},
            {name: 'Machine Contract Type', href: '/vend-contracts', permission: 'read vend-contracts'},
            {name: 'Location Types', href: '/location-types', permission: 'read location-types'},
            {name: 'HID Card', href: '/hid-cards', permission: 'read hid-cards'},
            {name: 'Refilling Routes', href: '/zones', permission: 'read zones'},
            {name: 'Banks', href: '/banks', permission: 'read banks'},
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
            {name: 'Operator Group', href: '/operator-groups', permission: 'read operator-groups'},
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

// Messenger-style unread-note badges, keyed by menu href (shared by
// HandleInertiaRequests::share → NoteNotificationService). subBadge() reads a
// single link's count; sectionBadge() rolls children up onto the collapsed
// parent so the red dot is visible even when the section is closed.
const noteBadges = computed(() => page.props.noteBadges || {})
function subBadge(href) {
    if (!href) return 0
    return noteBadges.value[href.split('?')[0]] || 0
}
function sectionBadge(item) {
    if (!item || !item.children) return 0
    return item.children.reduce((sum, sub) => sum + subBadge(sub.href), 0)
}
// Separate @-mention badges (indigo), keyed by href just like noteBadges.
// Shown alongside the red unread badge so mentions stand out.
const noteMentionBadges = computed(() => page.props.noteMentionBadges || {})
function subMentionBadge(href) {
    if (!href) return 0
    return noteMentionBadges.value[href.split('?')[0]] || 0
}
function sectionMentionBadge(item) {
    if (!item || !item.children) return 0
    return item.children.reduce((sum, sub) => sum + subMentionBadge(sub.href), 0)
}
const defaultLogoUrl = computed(() => page.props.defaultLogoUrl)
const useContainLogo = computed(() => logoUrl.value !== defaultLogoUrl.value)

// Post-login "This month sales" popup — HIPL group only.
const isHipl = computed(() => page.props.auth?.operator?.code === 'HIPL')

// --- Active-nav highlighting -------------------------------------------------
// Current path without query string.
const currentPath = computed(() => (page.url || '/').split('?')[0])

// Resolve a nav href to a URL path. Children use literal paths ('/foo');
// parents use Ziggy route names ('vends.customer').
function resolvePath(href) {
    if (!href) return ''
    if (href.startsWith('/')) return href
    try { return new URL(route(href)).pathname } catch (e) { return '/' + href }
}

// A candidate path matches if it equals the current path or is a parent
// segment of it.
function pathMatches(path) {
    if (!path) return false
    if (path === '/') return currentPath.value === '/'
    return currentPath.value === path || currentPath.value.startsWith(path + '/')
}

// The single active path: the LONGEST matching path across the whole nav.
// Picking the longest disambiguates overlapping prefixes — e.g. on
// '/vends/customers', the Operations sub-tab ('/vends/customers') wins over
// Machine Management's "Machines View" ('/vends'), so only one item lights up.
const activePath = computed(() => {
    let best = '', bestLen = -1
    for (const item of navigation.value) {
        const candidates = (item.children && item.children.length)
            ? item.children.map(c => c.href)
            : [item.href]
        for (const href of candidates) {
            const p = resolvePath(href)
            if (pathMatches(p) && p.length > bestLen) { best = p; bestLen = p.length }
        }
    }
    return best
})

// A top-level item is active when it (leaf) or any of its children owns the
// active path.
function isItemActive(item) {
    if (!activePath.value) return false
    if (item.children && item.children.length) {
        return item.children.some(c => resolvePath(c.href) === activePath.value)
    }
    return resolvePath(item.href) === activePath.value
}

// A sub-item is active when it owns the active path.
function isSubItemActive(item, subItem) {
    return !!activePath.value && resolvePath(subItem.href) === activePath.value
}

</script>

<template>
    <div>
        <MonthlySalesPopup v-if="isHipl" />
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
                                    :class="[isItemActive(item) ? 'bg-gray-100 text-gray-900' : 'bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900', 'group w-full flex items-center pl-2 py-2 text-sm font-medium rounded-md']">
                                <component :is="item.icon"
                                    :class="[isItemActive(item) ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500', 'mr-3 flex-shrink-0 h-6 w-6']"
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
                            <Disclosure as="div" v-else class="flex flex-col justify-start space-y-1" v-slot="{ open }" :default-open="isItemActive(item)">
                                <DisclosureButton
                                    v-if="permissions.includes(item.permission)"
                                    :class="[isItemActive(item) ? 'bg-gray-100 text-gray-900' : 'bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900', 'group w-full flex items-center pl-2 pr-1 py-2 text-left text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500']">
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
                                    <span v-if="!open && sectionMentionBadge(item) > 0"
                                        class="mr-1 inline-flex items-center justify-center rounded-full bg-indigo-500 px-2 py-0.5 text-[10px] font-semibold leading-none text-white">
                                        @{{ sectionMentionBadge(item) }}
                                    </span>
                                    <span v-if="!open && sectionBadge(item) > 0"
                                        class="mr-2 inline-flex items-center justify-center rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-semibold leading-none text-white">
                                        {{ sectionBadge(item) }}
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
                                            :class="[isSubItemActive(item, subItem) ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200', 'group w-full flex items-center justify-start text-left pl-4 pr-2 py-2 text-sm font-medium rounded-md']"
                                            v-if="subItem && (!subItem.permission || (subItem.permission && permissions.includes(subItem.permission)))"
                                            >
                                            <span>{{ subItem.name }}</span>
                                            <span class="ml-auto flex items-center gap-1">
                                                <span v-if="subMentionBadge(subItem.href) > 0"
                                                    class="inline-flex items-center justify-center rounded-full bg-indigo-500 px-2 py-0.5 text-[10px] font-semibold leading-none text-white">
                                                    @{{ subMentionBadge(subItem.href) }}
                                                </span>
                                                <span v-if="subBadge(subItem.href) > 0"
                                                    class="inline-flex items-center justify-center rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-semibold leading-none text-white">
                                                    {{ subBadge(subItem.href) }}
                                                </span>
                                            </span>
                                        </DisclosureButton>
                                    </Link>
                                </DisclosurePanel>
                            </Disclosure>
                        </template>
                    </nav>
                </div>
            </div>

            <!-- <div class="flex-auto mx-auto"> -->
            <!-- flex-1 + min-w-0 so the content column fills the space LEFT by the
                 sidebar instead of claiming a fixed fraction of the FULL width.
                 The old fractional widths overflowed at 2xl (1536–2304px): the
                 sidebar's min-w-48 (192px) exceeds its w-1/12 basis, yet the
                 content kept w-11/12 of the full width, so the two summed past
                 100% and pushed a body-level horizontal scrollbar. min-w-0 also
                 lets wide tables scroll inside their own container. -->
            <div class="md:flex-1 min-w-0">
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
                            :href="route(item.href)" :active="isItemActive(item)">
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
                        <Disclosure as="div" v-else class="space-y-1" v-slot="{ open }" :default-open="isItemActive(item)">
                            <DisclosureButton :class="[isItemActive(item) ? 'text-gray-900 font-bold' : '', 'pt-2 pb-2 mb-1 pl-4 space-y-1 flex w-full justify-start text-left']" v-if="permissions.includes(item.permission)">
                                <span class="flex flex-col">
                                    <span>
                                        {{ item.name }}
                                    </span>
                                    <span class="text-[12px] text-gray-500">
                                        {{ item.tagline }}
                                    </span>
                                </span>
                                <span v-if="!open && sectionMentionBadge(item) > 0"
                                    class="ml-2 inline-flex items-center justify-center rounded-full bg-indigo-500 px-2 py-0.5 text-[10px] font-semibold leading-none text-white">
                                    @{{ sectionMentionBadge(item) }}
                                </span>
                                <span v-if="!open && sectionBadge(item) > 0"
                                    class="ml-1 inline-flex items-center justify-center rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-semibold leading-none text-white">
                                    {{ sectionBadge(item) }}
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
                                        :class="[isSubItemActive(item, subItem) ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50', 'group w-full flex items-center justify-start text-left pl-14 pr-2 py-3 text-sm font-medium rounded-md']">
                                        <span>{{ subItem.name }}</span>
                                        <span class="ml-auto flex items-center gap-1">
                                            <span v-if="subMentionBadge(subItem.href) > 0"
                                                class="inline-flex items-center justify-center rounded-full bg-indigo-500 px-2 py-0.5 text-[10px] font-semibold leading-none text-white">
                                                @{{ subMentionBadge(subItem.href) }}
                                            </span>
                                            <span v-if="subBadge(subItem.href) > 0"
                                                class="inline-flex items-center justify-center rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-semibold leading-none text-white">
                                                {{ subBadge(subItem.href) }}
                                            </span>
                                        </span>
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
