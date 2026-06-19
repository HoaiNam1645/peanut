import * as React from 'react'
import {
  CalendarClock,
  FileClock,
  Link2,
  LayoutDashboard,
  Package,
  ReceiptText,
  ShieldCheck,
  Store,
  Ticket,
  Truck,
  Users,
  Wallet,
  Warehouse,
} from 'lucide-react'
import { type NavCollapsible, type NavGroup, type Team } from '@/components/layout/types'
import { type AppLocale } from '@/lib/i18n/types'
import { type LemiexRole } from '@/stores/auth-store'

const STAFF_SCANNER_ROLES: LemiexRole[] = ['QC', 'Packing', 'Shipout']

const DEFAULT_ROLE_PERMISSIONS: Record<LemiexRole, string[]> = {
  Admin: ['*'],
  Support: ['/lemiex/welcome'],
  Seller: [
    '/lemiex/dashboard',
    '/lemiex/orders',
    '/lemiex/orders/*',
    '/lemiex/products',
    '/lemiex/products/*',
    '/lemiex/product-variants',
    '/lemiex/product-variants/*',
    '/lemiex/stores',
    '/lemiex/stores/*',
    '/lemiex/tickets',
    '/lemiex/tickets/*',
    '/lemiex/wallets/transactions',
  ],
  Staff: [
    '/lemiex/orders',
    '/lemiex/orders/*',
    '/lemiex/stock/manage',
    '/lemiex/stock/shortage',
    '/lemiex/stock/shortage-by-variant',
    '/lemiex/stock/audit-logs',
    '/lemiex/payroll',
    '/lemiex/payroll/*',
    '/lemiex/payroll/tiers',
  ],
  QC: ['/lemiex/welcome'],
  Packing: ['/lemiex/welcome'],
  Shipout: ['/lemiex/welcome'],
}

const PAGE_ACCESS_PATTERN_OVERRIDES: Record<string, string[]> = {
  '/lemiex/orders': ['/lemiex/orders', '/lemiex/orders/*'],
  '/lemiex/products': ['/lemiex/products', '/lemiex/products/*'],
  '/lemiex/product-variants': ['/lemiex/product-variants', '/lemiex/product-variants/*'],
  '/lemiex/stores': ['/lemiex/stores', '/lemiex/stores/*'],
  '/lemiex/partner-stores': ['/lemiex/partner-stores', '/lemiex/partner-stores/*'],
  '/lemiex/partner-apps': ['/lemiex/partner-apps', '/lemiex/partner-apps/*'],
  '/lemiex/tickets': ['/lemiex/tickets', '/lemiex/tickets/*'],
  '/lemiex/payroll': ['/lemiex/payroll', '/lemiex/payroll/*'],
  '/lemiex/systems/users': ['/lemiex/systems/users', '/lemiex/systems/users/*'],
  '/lemiex/tiers': ['/lemiex/tiers', '/lemiex/tiers/*'],
}

const PAGE_ACCESS_PERMISSION_NAME_OVERRIDES: Record<string, string> = {
  '/lemiex/dashboard': 'page.dashboard',
  '/lemiex/welcome': 'page.welcome',
  '/lemiex/orders': 'page.orders',
  '/lemiex/products': 'page.products',
  '/lemiex/product-variants': 'page.product_variants',
  '/lemiex/stores': 'page.stores',
  '/lemiex/partner-stores': 'page.partner_stores',
  '/lemiex/partner-apps': 'page.partner_apps',
  '/lemiex/list-sync-orders': 'page.partner_sync_orders',
  '/lemiex/tickets': 'page.tickets',
  '/lemiex/stock/manage': 'page.stock_manage',
  '/lemiex/stock/shortage': 'page.stock_shortage',
  '/lemiex/stock/shortage-by-variant': 'page.stock_shortage_by_variant',
  '/lemiex/stock/audit-logs': 'page.stock_audit_logs',
  '/lemiex/attendances': 'page.attendances',
  '/lemiex/payroll': 'page.payroll',
  '/lemiex/payroll/tiers': 'page.payroll_tiers',
  '/lemiex/wallets/transactions': 'page.wallet_transactions',
  '/lemiex/wallets/pending-fund': 'page.wallet_pending_fund',
  '/lemiex/staff-report': 'page.staff_report',
  '/lemiex/systems/users': 'page.system_users',
  '/lemiex/systems/permissions': 'page.system_permissions',
  '/lemiex/systems/permissions-sidebar': 'page.system_page_access',
  '/lemiex/tiers': 'page.tiers',
}

export const PAGE_ACCESS_GROUP_NAME = 'Page Access'

type LemiexNavItem = NavGroup['items'][number]
export type PageAccessTreeNode = {
  id: string
  title: string
  url?: string
  patterns?: string[]
  children?: PageAccessTreeNode[]
}

const LEMIEX_SIDEBAR_LABELS = {
  vi: {
    teamName: 'Không gian THEUNIV',
    teamPlan: 'Sidebar theo vai trò',
    overview: 'Tổng quan',
    commerce: 'Thương mại',
    operations: 'Vận hành',
    supportTools: 'Công cụ hỗ trợ',
    administration: 'Quản trị',
    dashboard: 'Bảng điều khiển',
    welcome: 'Chào mừng',
    orders: 'Đơn hàng',
    designs: 'Thiết kế',
    products: 'Sản phẩm',
    catalog: 'Danh mục',
    productVariants: 'Biến thể sản phẩm',
    stores: 'Cửa hàng',
    partnerStores: 'Shop đối tác (Đang phát triển)',
    partnerApps: 'Partner Apps (Đang phát triển)',
    syncedOrders: 'Đơn đã sync (Đang phát triển)',
    tickets: 'Khiếu nại',
    stockManagement: 'Quản lý kho',
    stockDashboard: 'Tổng quan kho',
    manageStock: 'Quản lý tồn kho',
    productions: 'Sản xuất',
    shortageReport: 'Báo cáo thiếu hàng',
    shortageByVariant: 'Thiếu hàng theo biến thể',
    auditLogs: 'Lịch sử kiểm tra',
    hrPayroll: 'Nhân sự & lương',
    attendances: 'Chấm công',
    payrollReport: 'Báo cáo lương',
    salaryTiers: 'Bậc lương',
    embroideryProgress: 'Tiến độ thêu',
    trackings: 'Theo dõi đơn',
    videos: 'Video',
    wallets: 'Ví',
    transactions: 'Giao dịch',
    pendingFund: 'Tiền chờ duyệt',
    refunds: 'Hoàn tiền',
    surcharge: 'Phụ thu',
    debits: 'Công nợ',
    staffReport: 'Báo cáo nhân sự',
    systems: 'Hệ thống',
    users: 'Người dùng',
    permissions: 'Phân quyền',
    permissionsSidebar: 'Phân quyền trang',
    tiers: 'Tiers',
  },
  en: {
    teamName: 'THEUNIV Workspace',
    teamPlan: 'Role-aware sidebar',
    overview: 'Overview',
    commerce: 'Commerce',
    operations: 'Operations',
    supportTools: 'Support Tools',
    administration: 'Administration',
    dashboard: 'Dashboard',
    welcome: 'Welcome',
    orders: 'Orders',
    designs: 'Designs',
    products: 'Products',
    catalog: 'Catalog',
    productVariants: 'Product Variants',
    stores: 'Stores',
    partnerStores: 'Partner Stores (In Progress)',
    partnerApps: 'Partner Apps (In Progress)',
    syncedOrders: 'Synced Orders (In Progress)',
    tickets: 'Tickets',
    stockManagement: 'Stock Management',
    stockDashboard: 'Dashboard',
    manageStock: 'Manage Stock',
    productions: 'Productions',
    shortageReport: 'Shortage Report',
    shortageByVariant: 'Shortage by Variant',
    auditLogs: 'Audit Logs',
    hrPayroll: 'HR & Payroll',
    attendances: 'Attendances',
    payrollReport: 'Payroll Report',
    salaryTiers: 'Salary Tiers',
    embroideryProgress: 'Embroidery Progress',
    trackings: 'Trackings',
    videos: 'Videos',
    wallets: 'Wallets',
    transactions: 'Transactions',
    pendingFund: 'Pending Fund',
    refunds: 'Refunds',
    surcharge: 'Surcharge',
    debits: 'Debits',
    staffReport: 'Staff Report',
    systems: 'Systems',
    users: 'Users',
    permissions: 'Permissions',
    permissionsSidebar: 'Page Access',
    tiers: 'Tiers',
  },
} satisfies Record<AppLocale, Record<string, string>>

function LemiexLogo(props: React.ComponentProps<'div'>) {
  const { className, ...rest } = props

  return React.createElement(
    'div',
    { className, ...rest },
    React.createElement(ShieldCheck, {
      className: 'size-4',
    })
  )
}

function slugifyPageAccessPath(path: string) {
  return path
    .replace(/^\/lemiex\//, '')
    .replace(/^\//, '')
    .replace(/\/+/g, '_')
    .replace(/[^a-zA-Z0-9_]/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_+|_+$/g, '')
    .toLowerCase()
}

function collectMenuUrls(items: LemiexNavItem[]): string[] {
  return items.flatMap((item) => {
    if ('url' in item && item.url) return [item.url]
    if ('items' in item && item.items) return collectMenuUrls(item.items)
    return []
  })
}

export type LemiexRolePermission = {
  id?: number
  name?: string | null
  display_name?: string | null
  group?: string | null
  route?: string | null
  method?: string | null
}

type UserPermissionsSource =
  | Array<LemiexRolePermission | string | null | undefined>
  | string
  | { permissions?: Array<LemiexRolePermission | string | null | undefined> | null }
  | null
  | undefined

function getRoutePatterns(path: string) {
  if (
    path === '/lemiex/systems/permissions' ||
    path === '/lemiex/systems/permissions-sidebar'
  ) {
    return []
  }

  return PAGE_ACCESS_ROUTE_PATTERNS[path] || [path]
}

function getDefaultRolePermissionNames(role: LemiexRole) {
  const routes = DEFAULT_ROLE_PERMISSIONS[role]
  if (routes.includes('*')) return ['*']

  return routes
    .filter((route) => !route.endsWith('/*'))
    .map((route) => PAGE_ACCESS_PERMISSION_BY_PATH[route])
    .filter(Boolean)
}

export function extractPermissionNames(source?: UserPermissionsSource) {
  const permissions = Array.isArray(source)
    ? source
    : source && typeof source === 'object' && 'permissions' in source
      ? source.permissions || []
      : []

  return permissions
    .map((permission) =>
      typeof permission === 'string'
        ? permission
        : permission && typeof permission === 'object'
          ? permission.name || ''
          : ''
    )
    .filter(Boolean)
}

export function getRolePagePermissions() {
  return (Object.keys(DEFAULT_ROLE_PERMISSIONS) as LemiexRole[]).reduce(
    (acc, role) => {
      acc[role] = getDefaultRolePermissionNames(role)
      return acc
    },
    {} as Record<LemiexRole, string[]>
  )
}

function createLemiexNavGroups(locale: AppLocale): NavGroup[] {
  const labels = LEMIEX_SIDEBAR_LABELS[locale]

  return [
    {
      title: labels.overview,
      items: [
        {
          title: labels.dashboard,
          url: '/lemiex/dashboard',
          icon: LayoutDashboard,
        },
      ],
    },
    {
      title: labels.commerce,
      items: [
        {
          title: labels.orders,
          url: '/lemiex/orders',
          icon: ReceiptText,
        },
        {
          title: 'Đơn ShipDVX',
          url: '/lemiex/shipdvx-orders',
          icon: Truck,
        },
        {
          title: labels.products,
          icon: Package,
          items: [
            {
              title: labels.catalog,
              url: '/lemiex/products',
            },
            {
              title: labels.productVariants,
              url: '/lemiex/product-variants',
            },
          ],
        },
        {
          title: labels.stores,
          url: '/lemiex/stores',
          icon: Store,
        },
        {
          title: labels.tickets,
          url: '/lemiex/tickets',
          icon: Ticket,
        },
      ],
    },
    {
      title: labels.operations,
      items: [
        {
          title: labels.stockManagement,
          icon: Warehouse,
          items: [
            {
              title: labels.manageStock,
              url: '/lemiex/stock/manage',
            },
            {
              title: labels.shortageReport,
              url: '/lemiex/stock/shortage',
            },
            {
              title: labels.shortageByVariant,
              url: '/lemiex/stock/shortage-by-variant',
            },
            {
              title: labels.auditLogs,
              url: '/lemiex/stock/audit-logs',
            },
          ],
        },
      ],
    },
    {
      title: labels.supportTools,
      items: [
        {
          title: labels.wallets,
          icon: Wallet,
          items: [
            {
              title: labels.transactions,
              url: '/lemiex/wallets/transactions',
            },
            {
              title: labels.pendingFund,
              url: '/lemiex/wallets/pending-fund',
            },
          ],
        },
      ],
    },
    {
      title: labels.administration,
      items: [
        {
          title: labels.staffReport,
          url: '/lemiex/staff-report',
          icon: FileClock,
        },
        {
          title: labels.systems,
          icon: ShieldCheck,
          items: [
            {
              title: labels.users,
              url: '/lemiex/systems/users',
            },
            {
              title: labels.permissions,
              url: '/lemiex/systems/permissions',
            },
            {
              title: labels.permissionsSidebar,
              url: '/lemiex/systems/permissions-sidebar',
            },
          ],
        },
        {
          title: labels.tiers,
          url: '/lemiex/tiers',
          icon: ShieldCheck,
        },
      ],
    },
  ]
}

const LEMIEX_PAGE_ACCESS_PATHS = Array.from(
  new Set(createLemiexNavGroups('en').flatMap((group) => collectMenuUrls(group.items)))
)

export const PAGE_ACCESS_ROUTE_PATTERNS: Record<string, string[]> = Object.fromEntries(
  LEMIEX_PAGE_ACCESS_PATHS.map((path) => [path, PAGE_ACCESS_PATTERN_OVERRIDES[path] || [path]])
)

export const PAGE_ACCESS_PERMISSION_BY_PATH: Record<string, string> = Object.fromEntries(
  LEMIEX_PAGE_ACCESS_PATHS.map((path) => [
    path,
    PAGE_ACCESS_PERMISSION_NAME_OVERRIDES[path] || `page.${slugifyPageAccessPath(path)}`,
  ])
)

function hasAccess(role: string, path: string, permissionNames?: string[]) {
  // The welcome page is the universal fallback landing route. It holds no
  // sensitive data and is what getDefaultLemiexRoute* returns when a user has
  // no other accessible page. It MUST always be viewable for any authenticated
  // user — otherwise a user with no matching page permission is redirected here
  // and then blocked here too, producing a blank screen / redirect loop.
  if (path === '/lemiex/welcome') return true

  // Permission management UIs are Admin-only by design
  if (
    path === '/lemiex/systems/permissions' ||
    path.startsWith('/lemiex/systems/permissions/') ||
    path === '/lemiex/permissions' ||
    path === '/lemiex/systems/permissions-sidebar' ||
    path.startsWith('/lemiex/systems/permissions-sidebar/')
  ) {
    return role === 'Admin'
  }

  // Admin & HR auto-bypass (matches backend CheckPermission middleware)
  if (role === 'Admin' || role === 'HR') return true

  const resolvedRole = normalizeLemiexRole(role)
  const hasExplicitPermissions = Boolean(permissionNames && permissionNames.length > 0)

  // Unknown/custom role: must rely entirely on explicit permissions
  // Built-in role: use explicit permissions if any, else fall back to defaults
  const effectivePermissionNames = hasExplicitPermissions
    ? (permissionNames as string[])
    : resolvedRole
      ? getDefaultRolePermissionNames(resolvedRole)
      : []

  if (effectivePermissionNames.length === 0) return false
  if (effectivePermissionNames.includes('*')) return true

  const allowedRoutes = effectivePermissionNames
    .map((permissionName) =>
      Object.entries(PAGE_ACCESS_PERMISSION_BY_PATH).find(
        ([, value]) => value === permissionName
      )?.[0]
    )
    .filter(Boolean) as string[]

  return allowedRoutes.some((route) => {
    if (route === '*') return true
    if (route === path) return true
    const patterns = getRoutePatterns(route)
    if (
      patterns.some((pattern) =>
        pattern.endsWith('/*')
          ? path.startsWith(pattern.slice(0, -2))
          : pattern === path
      )
    )
      return true
    return false
  })
}

export function canAccessLemiexPath(
  role: string,
  path: string,
  permissionNames?: string[]
) {
  return hasAccess(role, path, permissionNames)
}

function filterNavItem(
  role: string,
  item: LemiexNavItem,
  permissionNames?: string[]
): LemiexNavItem | null {
  if ('url' in item && item.url) {
    return hasAccess(role, item.url, permissionNames) ? item : null
  }

  if (!('items' in item) || !item.items) return null

  const children = item.items
    .map((child) => filterNavItem(role, child, permissionNames))
    .filter(Boolean) as NavCollapsible['items']

  if (children.length === 0) return null

  return {
    ...item,
    items: children,
  }
}

type LemiexRoleInput =
  | LemiexRole
  | string
  | { name?: string | null; display_name?: string | null }
  | null
  | undefined

/**
 * Returns the role name as a string. Built-in roles return their LemiexRole literal,
 * custom roles return their raw name. For falsy/empty input returns empty string.
 *
 * IMPORTANT: Unlike before, this no longer defaults to 'Admin' for unknown roles —
 * that was a security bug that gave custom roles full access.
 */
export function getLemiexRole(
  role: LemiexRoleInput | LemiexRoleInput[]
): string {
  const resolvedRole = Array.isArray(role) ? role[0] : role
  const roleName =
    typeof resolvedRole === 'string'
      ? resolvedRole
      : resolvedRole && typeof resolvedRole === 'object'
        ? resolvedRole.name
        : null

  return roleName ?? ''
}

export function isScannerRole(role: string) {
  const resolvedRole = normalizeLemiexRole(role)
  return resolvedRole ? STAFF_SCANNER_ROLES.includes(resolvedRole) : false
}

export function getDefaultLemiexRoute(role: string) {
  if (isScannerRole(role)) return '/lemiex/welcome'
  if (role === 'Admin' || role === 'HR') return '/lemiex/dashboard'

  const resolvedRole = normalizeLemiexRole(role)
  if (!resolvedRole) return '/lemiex/welcome'
  const permissionNames = getDefaultRolePermissionNames(resolvedRole)
  const firstAllowedRoute = permissionNames
    .map((permissionName) =>
      Object.entries(PAGE_ACCESS_PERMISSION_BY_PATH).find(
        ([, value]) => value === permissionName
      )?.[0]
    )
    .find(Boolean)

  return firstAllowedRoute || '/lemiex/welcome'
}

export function getDefaultLemiexRouteForPermissions(
  role: string,
  permissionNames?: string[]
) {
  if (isScannerRole(role)) return '/lemiex/welcome'
  if (role === 'Admin' || role === 'HR') return '/lemiex/dashboard'

  const resolvedRole = normalizeLemiexRole(role)
  const effectivePermissionNames =
    permissionNames && permissionNames.length > 0
      ? permissionNames
      : resolvedRole
        ? getDefaultRolePermissionNames(resolvedRole)
        : []

  const firstAllowedRoute = effectivePermissionNames
    .map((permissionName) =>
      Object.entries(PAGE_ACCESS_PERMISSION_BY_PATH).find(
        ([, value]) => value === permissionName
      )?.[0]
    )
    .find(Boolean)

  return firstAllowedRoute || '/lemiex/welcome'
}

function normalizeLemiexRole(role: string | null | undefined): LemiexRole | null {
  if (!role) return null

  if ((Object.keys(DEFAULT_ROLE_PERMISSIONS) as LemiexRole[]).includes(role as LemiexRole)) {
    return role as LemiexRole
  }

  return null
}

export function getLemiexTeam(locale: AppLocale = 'vi'): Team {
  const labels = LEMIEX_SIDEBAR_LABELS[locale]

  return {
    id: 'lemiex',
    name: labels.teamName,
    logo: LemiexLogo,
    plan: labels.teamPlan,
    defaultUrl: '/lemiex/dashboard',
  }
}

export function getLemiexNavGroups(
  locale: AppLocale = 'vi',
  role: string = 'Admin',
  permissionNames?: string[]
): NavGroup[] {
  return createLemiexNavGroups(locale)
    .map((group) => ({
      ...group,
      items: group.items
        .map((item) => filterNavItem(role, item, permissionNames))
        .filter(Boolean) as NavGroup['items'],
    }))
    .filter((group) => group.items.length > 0)
}

function mapNavItemToPageAccessNode(item: LemiexNavItem): PageAccessTreeNode {
  if ('url' in item && item.url) {
    return {
      id: item.url,
      title: item.title,
      url: item.url,
      patterns: getRoutePatterns(item.url),
    }
  }

  const children = ('items' in item && item.items ? item.items : []).map((child) =>
    mapNavItemToPageAccessNode(child)
  )

  return {
    id: item.title,
    title: item.title,
    children,
  }
}

export function getLemiexPageAccessTree(locale: AppLocale = 'vi'): PageAccessTreeNode[] {
  return createLemiexNavGroups(locale).map((group) => ({
    id: group.title,
    title: group.title,
    children: group.items.map((item) => mapNavItemToPageAccessNode(item)),
  }))
}
