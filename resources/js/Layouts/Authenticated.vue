<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BreezeDropdown from '@/Components/Dropdown.vue';
import BreezeDropdownLink from '@/Components/DropdownLink.vue';
import BreezeResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import MonthlySalesPopup from '@/Components/MonthlySalesPopup.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ArrowsPointingInIcon, BuildingOfficeIcon, ChevronDoubleLeftIcon, ChevronDoubleRightIcon, ClipboardDocumentListIcon, CodeBracketSquareIcon, Cog8ToothIcon, CircleStackIcon, CalendarDaysIcon, CommandLineIcon, CreditCardIcon, DocumentTextIcon, FolderIcon, IdentificationIcon, LinkIcon, MapPinIcon, RectangleStackIcon, TruckIcon, UserCircleIcon, UserGroupIcon, BookOpenIcon, TicketIcon } from '@heroicons/vue/20/solid'
import { useStorage } from '@vueuse/core'

const page = usePage()

const navigation = computed(() => [
    {
        name: 'Dashboards',
        icon: ClipboardDocumentListIcon,
        current: false,
        href: 'dashboard',
        // Array = "any of these" (see canSee() below). prod_owner holds ONLY
        // 'read dashboard-performance-lite' and NOT 'read dashboard', so gating
        // the section on 'read dashboard' alone hid the one child it can open —
        // the child link was correct, the parent swallowed it. Exactly the trap
        // the Operations group below already documents.
        //
        // Checked on live 2026-08-06: prod_owner is the ONLY role holding any
        // Dashboards child permission without 'read dashboard', so widening this
        // changes nothing for the other ten roles.
        permission: ['read dashboard', 'read dashboard-performance-lite'],
        tagline: null,
        children: [
            {name: 'Performance', href: '/dashboard/performance', permission: 'read dashboard-performance'},
            // Own permission, matching DashboardController's gate on indexLite —
            // 'read dashboard-performance' here would show the link to every role
            // that has the full Performance page and then 403 prod_owner, which
            // holds ONLY the Lite one. Same pattern as Dashboard (Lite) below.
            {name: 'Performance (Lite)', href: '/dashboard/performance-lite', permission: 'read dashboard-performance-lite'},
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
        // Array = "any of these". prod_owner only holds the Lite permission, so
        // gating the section on 'read vends' alone would hide the one child it
        // can actually open. See canSee() below.
        permission: ['read vends', 'read vend-customers-lite'],
        tagline: null,
        children: [
            {name: 'Dashboard', href: '/vends/customers', permission: 'read vends'},
            // Own permission, matching VendController's gate on
            // indexCustomerLite — 'read vends' here would show the link to every
            // role that has the full Dashboard and then 403 prod_owner.
            {name: 'Dashboard (Lite)', href: '/vends/customers-lite', permission: 'read vend-customers-lite'},
            // Sheet puts Ops Performance and Site Grouping at superadmin/admin/supervisor,
            // but 'read vends' is the Ops Dashboard permission held by nine roles.
            // 'admin-access vends' is already exactly superadmin/admin/supervisor.
            {name: 'Ops Performance', href: '/vends/ops-performance', permission: 'admin-access vends'},
            {name: 'Site Grouping', href: '/vends/grouping', permission: 'admin-access vends'},
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
            // Its own permission (NOT transactions-sales) so All Transactions and
            // Daily Summary can be granted separately - the sheet gives prod_owner
            // the former and not the latter. Every other role holds both.
            {name: 'Daily Summary', href: '/vends/transactions-daily-summary', permission: 'read transactions-daily-summary'},
            {name: 'Payment Gateway Txn', href: '/vends/payment-gateway-transactions', permission: 'read transactions-payment-gateway'},
            {name: 'Refund Requests', href: '/refunds', permission: 'read refunds'},
            {name: 'Refund Settlement', href: '/refund-settlements', permission: 'read refunds'},
            {name: 'Card Settlement', href: '/card-settlements', permission: 'read card-settlements'},
            {name: 'Card Terminal Bindings', href: '/card-terminal-bindings', permission: 'read card-settlements'},
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
            {name: 'APK OTA Updates', href: '/apk-releases', permission: 'read apk-releases'},
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
            // Sheet: Sites is superadmin/admin/supervisor/technician/operator_admin/
            // operator_supervisor, but Summary & Comm / Performance / Tags are staff-only
            // (superadmin/admin/supervisor/technician). All four were on 'read customers',
            // so the operator roles saw all four. 'admin-access customers' already holds
            // exactly the staff four, so no new permission is needed.
            {name: 'Sites', href: '/customers', permission: 'read customers'},
            {name: 'Summary & Comm', href: '/customers/summary', permission: 'admin-access customers'},
            {name: 'Site Settlement', href: '/site-settlements', permission: 'admin-access customers'},
            {name: 'Performance', href: '/customers/performance', permission: 'admin-access customers'},
            {name: 'Tags', href: '/tags?classname=App\\Models\\Customer', permission: 'admin-access customers'},
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
            // The mark1 ledger page. Without CMS it is THE warehouse page; with CMS it
            // still carries the products whose qty is kept manually (CityBox SKUs).
            {name: page.props.isCmsUrlSet ? 'Warehouse Qty (self-system ledger)' : 'Warehouse Qty & Planning', href: '/products/movements', permission: 'read products'},
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
        // Array = "any of these" (see canSee()). Was the plain string
        // 'read product-campaign-labels', which is Product Management > Product Labels -
        // a permission technician / operator_admin / operator_supervisor do not hold. They
        // DID hold every 'vouchers' permission, so the parent was swallowing a child they
        // could open: the same trap Dashboards and Operations above already document.
        // Listing both children's permissions is what stops it recurring if the two lists
        // ever diverge again.
        permission: ['read campaigns', 'read vouchers'],
        tagline: null,
        children: [
            {name: 'Settings', href: '/campaigns', permission: 'read campaigns'},
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
            {name: 'Machine Sticker', href: '/machine-stickers', permission: 'read machine-stickers'},
            {name: 'Cashless Terminals', href: '/cashless-terminals', permission: 'read cashless-terminals'},
            {name: 'SimCard Package', href: '/telcos', permission: 'read telcos'},
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
            // These carried no `permission` at all, so canSee() returned true for
            // anyone who passed the section gate - i.e. the margin reports were visible
            // to operator_admin / operator_supervisor. Gated explicitly per the sheet.
            {name: 'Stock Count Dashboard', href: '/reports/stock-count-dashboard', permission: 'read reports'},
            {name: 'Daily Stock Count', href: '/reports/stock-count', permission: 'read reports'},
            {name: 'Machine Monthly Snapshot', href: '/reports/snapshot', permission: 'read reports-gp'},
            {name: 'Sales Report', href: '/reports/sales/operator', permission: 'read reports'},
            {name: 'GP by VM', href: '/reports/gp/vend', permission: 'read reports-gp'},
            {name: 'GP by Product', href: '/reports/gp/product', permission: 'read reports-gp'},
            {name: 'Sales Performance by Product', href: '/reports/sales-performance/product', permission: 'read reports-gp'},
            // {name: 'GP by Category', href: '/reports/gp/category'},
            {name: 'GP by Location Type', href: '/reports/gp/location-type', permission: 'read reports-gp'},
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
            {name: 'MCP Access', href: '/mcp-tokens', permission: 'read mcp-tokens'},
            {name: 'Visitor History', href: '/visitor-history', permission: 'read visitor-history'},
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

// Desktop sidebar collapse (icon rail). Collapsed is the default. Persisted
// per-browser in localStorage so it survives Inertia visits and full reloads
// without a server round-trip.
//
// The key carries a version suffix because useStorage writes its default on
// first read: every browser that loaded the previous build already holds
// 'mark1-sidebar-collapsed' = false, and a stored value always beats a new
// default. Bumping the key is what lets "default collapsed" actually reach
// those browsers once; their choice from then on sticks under the new key.
const sidebarCollapsed = useStorage('mark1-sidebar-collapsed-v2', true)
const logoUrl = computed(() => page.props.logoUrl)
const permissions = page.props.auth.permissions

// A nav item's `permission` is normally a single string. It may also be an
// ARRAY, meaning "any one of these is enough" — needed where one section holds
// children with unrelated permissions (Operations: 'read vends' for Dashboard /
// Ops Performance / Site Grouping, 'read vend-customers-lite' for Dashboard
// (Lite)). A plain string behaves exactly as it did before.
function canSee(item) {
  if (!item) return false
  if (!item.permission) return true
  return Array.isArray(item.permission)
    ? item.permission.some((p) => permissions.includes(p))
    : permissions.includes(item.permission)
}
// Rail mode: the section icon links to its first visible child, so a click goes
// somewhere useful; hovering opens the flyout to pick a specific page.
function firstChildHref(item) {
    const child = (item.children || []).find((c) => canSee(c))
    return child ? child.href : null
}
const roles = page.props.auth.roles
const smallLogoUrl = page.props.smallLogoUrl

// Rail flyout open/close is driven HERE, not by floating-vue's hover triggers.
// With `triggers: ['hover']` + `popper-triggers: ['hover']`, floating-vue's
// hide() returns early (no timer at all) when the pointer leaves the icon
// "aiming" at the flyout, and only the flyout's own mouseleave hides it later.
// If the pointer never actually enters the flyout — curved or fast move past
// it, out of the window — the flyout stays open for good, and hovering the
// next icon opens a second one alongside (the stacked-flyouts bug).
// One `openFlyout` value means at most one flyout at a time; leaving either the
// icon or the flyout starts a short grace timer that entering either cancels,
// which is what carries the pointer across the 4px gap.
const openFlyout = ref(null)
let flyoutHideTimer = null
function flyoutEnter(name) {
    clearTimeout(flyoutHideTimer)
    openFlyout.value = name
}
function flyoutLeave() {
    clearTimeout(flyoutHideTimer)
    flyoutHideTimer = setTimeout(() => { openFlyout.value = null }, 200)
}
function closeFlyout() {
    clearTimeout(flyoutHideTimer)
    openFlyout.value = null
}
// floating-vue still hides on its own for click-outside (autoHide) and
// v-close-popper; mirror that back so `shown` doesn't re-open it.
function onFlyoutShown(name, shown) {
    if (!shown && openFlyout.value === name) closeFlyout()
}
// Expanding the rail unmounts the dropdowns; without this the remembered
// section would pop open by itself the moment the rail is collapsed again.
watch(sidebarCollapsed, closeFlyout)

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

// --- Visitor History dwell-time beacon ---------------------------------------
// The server logs WHICH page was opened (LogVisitorActivity); only the browser
// knows how long it stayed on screen — and for the last page before a tab closes
// no further request is ever made, so the server can never learn it on its own.
//
// The tracked visit id is held in a plain `let`, NOT read from page.props at send
// time: by the moment this layout unmounts, Inertia has already swapped in the
// next page's props, so reading the computed would stamp the old page's duration
// onto the new page's row. Two triggers are needed because Inertia only remounts
// the layout when the page component changes — a `preserveState` visit to the
// same component (every filter/pagination request in this app) reuses the
// instance, and the prop change is then the only signal that one page ended.
const visitId = computed(() => page.props.visitorVisit)

// Don't spend a request on a page the user bounced straight off, and don't let a
// tab-switcher fire one ping per switch.
const MIN_REPORT_MS = 2000
const MIN_GAP_MS = 10000

let trackedVisitId = null
let visitStartedAt = 0
let activeMs = 0
let activeSince = 0
let lastSentAt = 0

function accrueActive() {
    if (activeSince) {
        activeMs += Date.now() - activeSince
        activeSince = 0
    }
}

function startTracking(id) {
    trackedVisitId = id || null
    visitStartedAt = Date.now()
    activeMs = 0
    activeSince = document.visibilityState === 'visible' ? Date.now() : 0
    lastSentAt = 0
}

// sendBeacon cannot set headers, so the CSRF token travels in the JSON body
// (VerifyCsrfToken reads _token off the JSON input source). The XSRF-TOKEN
// cookie is the fallback for the case where the meta tag is missing.
function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    if (meta) return meta

    const cookie = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='))
    return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : null
}

function sendVisitBeacon(reason) {
    if (!trackedVisitId || !visitStartedAt) return

    const now = Date.now()
    const total = now - visitStartedAt

    // Under 2s the server's own inferred duration is just as accurate, so the
    // request buys nothing.
    if (total < MIN_REPORT_MS) return
    if (reason === 'hidden' && now - lastSentAt < MIN_GAP_MS) return
    lastSentAt = now

    // Snapshot without mutating the clocks — a later ping must still be able to
    // report a larger total (hidden, then visible again, then navigate). Pings
    // are idempotent: the server simply overwrites the row with the newest read.
    const active = activeMs + (activeSince ? now - activeSince : 0)
    const payload = JSON.stringify({
        visit: trackedVisitId,
        total_ms: total,
        active_ms: active,
        reason,
        _token: csrfToken(),
    })

    try {
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/visitor-history/ping', new Blob([payload], { type: 'application/json' }))
        } else {
            fetch('/visitor-history/ping', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true,
                credentials: 'same-origin',
            }).catch(() => {})
        }
    } catch (e) {
        // A visit log is never worth breaking navigation over.
    }
}

function onVisibilityChange() {
    if (document.visibilityState === 'hidden') {
        accrueActive()
        // On mobile Safari/Chrome this is the only reliable "page is going away"
        // signal — pagehide is not guaranteed to fire.
        sendVisitBeacon('hidden')
    } else {
        activeSince = Date.now()
    }
}

function onPageHide() {
    accrueActive()
    sendVisitBeacon('unload')
}

// preserveState visit that reuses this instance: close the old row, start the new.
watch(visitId, (newId) => {
    accrueActive()
    sendVisitBeacon('navigate')
    startTracking(newId)
})

onMounted(() => {
    startTracking(visitId.value)
    document.addEventListener('visibilitychange', onVisibilityChange)
    window.addEventListener('pagehide', onPageHide)
})

onBeforeUnmount(() => {
    clearTimeout(flyoutHideTimer)
    // Layout is being torn down (the page component changed) — report before the
    // instance goes away, then blank the id so nothing can double-report.
    accrueActive()
    sendVisitBeacon('navigate')
    trackedVisitId = null

    document.removeEventListener('visibilitychange', onVisibilityChange)
    window.removeEventListener('pagehide', onPageHide)
})

</script>

<template>
    <div>
        <MonthlySalesPopup v-if="isHipl" />
        <div class="min-h-screen w-full md:flex bg-gray-100">
            <div
                :class="[sidebarCollapsed ? 'md:w-16' : 'md:w-1/6 xl:w-2/12 2xl:w-1/12 2xl:min-w-48', 'hidden md:block flex-none flex-col border-r border-gray-200 pt-5 pb-4 bg-white transition-all duration-200']">

                <div class="flex items-center justify-center flex-shrink-0 px-1 object-scale-down">
                    <Link href="/">
                        <div class="h-fit w-fit">
                            <img v-if="sidebarCollapsed" class="h-10 w-10 object-contain" :src="smallLogoUrl" alt="Company Logo">
                            <img v-else :class="[useContainLogo ? 'object-contain h-24 w-36 p-2' : 'object-cover h-24 w-36']" :src="logoUrl" alt="Company Logo">
                        </div>
                    </Link>
                </div>
                <div class="mt-2 flex px-2" :class="sidebarCollapsed ? 'justify-center' : 'justify-end'">
                    <button type="button" @click="sidebarCollapsed = !sidebarCollapsed"
                        v-tooltip.right="sidebarCollapsed ? 'Expand menu' : ''"
                        :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                        class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <ChevronDoubleRightIcon v-if="sidebarCollapsed" class="h-5 w-5" aria-hidden="true" />
                        <ChevronDoubleLeftIcon v-else class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>
                <div class="mt-2 flex-grow flex flex-col border-t border-gray-200 pt-2">
                    <nav class="flex-1 px-2 space-y-1 bg-white" aria-label="Sidebar">
                        <template v-for="item in navigation" :key="item.name">
                            <div v-if="!item.children">
                                <Link :href="route(item.href)"
                                    v-if="canSee(item)"
                                    v-tooltip.right="sidebarCollapsed ? [item.name, item.tagline].filter(Boolean).join(' — ') : ''"
                                    :class="[isItemActive(item) ? 'bg-gray-100 text-gray-900' : 'bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900', sidebarCollapsed ? 'justify-center px-2' : 'pl-2', 'group w-full flex items-center py-2 text-sm font-medium rounded-md']">
                                <component :is="item.icon"
                                    :class="[isItemActive(item) ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500', sidebarCollapsed ? '' : 'mr-3', 'flex-shrink-0 h-6 w-6']"
                                    aria-hidden="true" />
                                <span v-if="!sidebarCollapsed" class="flex flex-col">
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
                                <!-- Rail mode: hovering the section icon floats out its children,
                                     so a page can be chosen WITHOUT expanding the sidebar; the
                                     icon itself links to the section's first visible child.
                                     Deliberately not a DisclosureButton — that would toggle the
                                     section's own open state, which has to survive the trip
                                     through the rail.

                                     No floating-vue triggers: open state is `openFlyout` (see the
                                     script for why its hover triggers leak stuck flyouts). -->
                                <VDropdown v-if="sidebarCollapsed && canSee(item)" class="w-full"
                                    placement="right-start" :distance="4"
                                    :triggers="[]" :popper-triggers="[]"
                                    :shown="openFlyout === item.name"
                                    @update:shown="onFlyoutShown(item.name, $event)">
                                    <!-- No v-tooltip here: a hover tooltip and this popover are
                                         two separate poppers on one element and visibly overlap
                                         while the flyout fades in. The flyout's own header
                                         carries the section name instead. -->
                                    <Link :href="firstChildHref(item) || '#'" :aria-label="item.name"
                                        @mouseenter="flyoutEnter(item.name)" @mouseleave="flyoutLeave"
                                        :class="[isItemActive(item) ? 'bg-gray-100' : 'bg-white hover:bg-gray-50', 'relative group w-full flex items-center justify-center px-2 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500']">
                                        <component :is="item.icon"
                                            :class="[isItemActive(item) ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500', 'flex-shrink-0 h-6 w-6']"
                                            aria-hidden="true" />
                                        <span v-if="sectionMentionBadge(item) > 0"
                                            class="absolute top-0.5 left-1.5 h-2 w-2 rounded-full bg-indigo-500"></span>
                                        <span v-if="sectionBadge(item) > 0"
                                            class="absolute top-0.5 right-1.5 h-2 w-2 rounded-full bg-red-500"></span>
                                    </Link>
                                    <template #popper>
                                        <div class="w-60 py-2" @mouseenter="flyoutEnter(item.name)" @mouseleave="flyoutLeave">
                                            <div class="px-4 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                                {{ item.name }}
                                            </div>
                                            <template v-for="subItem in item.children" :key="subItem.name">
                                                <Link v-if="canSee(subItem)" :href="subItem.href" v-close-popper
                                                    :class="[isSubItemActive(item, subItem) ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', 'flex items-center px-4 py-2 text-sm font-medium']">
                                                    <span>{{ subItem.name }}</span>
                                                    <span class="ml-auto flex items-center gap-1 pl-2">
                                                        <span v-if="subMentionBadge(subItem.href) > 0"
                                                            class="inline-flex items-center justify-center rounded-full bg-indigo-500 px-2 py-0.5 text-[10px] font-semibold leading-none text-white">
                                                            @{{ subMentionBadge(subItem.href) }}
                                                        </span>
                                                        <span v-if="subBadge(subItem.href) > 0"
                                                            class="inline-flex items-center justify-center rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-semibold leading-none text-white">
                                                            {{ subBadge(subItem.href) }}
                                                        </span>
                                                    </span>
                                                </Link>
                                            </template>
                                        </div>
                                    </template>
                                </VDropdown>
                                <DisclosureButton
                                    v-if="!sidebarCollapsed && canSee(item)"
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
                                <DisclosurePanel v-if="!sidebarCollapsed" class="-ml-2 space-y-1 py-2 bg-gray-100">
                                    <Link v-for="subItem in item.children" :key="subItem.name" as="a"
                                            :href="subItem.href">
                                        <DisclosureButton
                                            :class="[isSubItemActive(item, subItem) ? 'bg-gray-200 text-gray-900' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200', 'group w-full flex items-center justify-start text-left pl-4 pr-2 py-2 text-sm font-medium rounded-md']"
                                            v-if="canSee(subItem)"
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
                            v-if="canSee(item)"
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
                            <DisclosureButton :class="[isItemActive(item) ? 'text-gray-900 font-bold' : '', 'pt-2 pb-2 mb-1 pl-4 space-y-1 flex w-full justify-start text-left']" v-if="canSee(item)">
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
                                        v-if="canSee(subItem)"
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
