'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import { ArrowDownRight, ArrowUpDown, ArrowUpRight, Package, Trophy } from 'lucide-react'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useI18n } from '@/context/i18n-provider'
import { getLemiexRole } from '@/features/lemiex/layout/sidebar-data'
import { fetchDashboardStatistics, type DashboardData } from '@/services/dashboard/api'
import { useAuthStore } from '@/stores/auth-store'
import { cn } from '@/lib/utils'

const fallbackMessages = {
  title: 'Dashboard',
  subtitle: 'Overview of orders, revenue, stock, and recent system activity.',
  loading: 'Loading dashboard...',
  failedLoad: 'Failed to load dashboard statistics',
  timeRangeLabel: 'Time range',
  today: 'Today',
  yesterday: 'Yesterday',
  last7Days: '7D',
  last30Days: '30D',
  last90Days: '90D',
  lastYear: '1Y',
  sellerScope: 'Seller view',
  sellerScopeDescription: 'Statistics are scoped to your own store activity.',
  totalOrders: 'Orders',
  totalRevenue: 'Revenue',
  productsVariants: 'Products',
  totalStock: 'Stock',
  ordersThisPeriod: '{count} orders this period',
  revenueThisPeriod: '{amount} this period',
  variants: '{count} variants · {active} active',
  lowStockWarning: '{count} variants are low on stock',
  totalDeposits: 'Deposits',
  totalWithdrawals: 'Withdrawals',
  totalPayments: 'Payments',
  pendingTransactions: 'Pending',
  transactionsThisPeriod: '{count} transactions this period',
  productSalesQuantity: 'Product sales quantity',
  top5Products: 'Top product performance over time',
  revenueByPaymentStatus: 'Revenue by payment status',
  dailyBreakdown: 'Daily revenue breakdown',
  dailyOrders: 'Daily orders',
  ordersPerDay: 'Orders created per day',
  transactionsOverview: 'Transactions overview',
  dailyTransactions: 'Daily transaction amounts by type',
  noSalesData: 'No product sales data',
  noRevenueData: 'No revenue data',
  noOrderData: 'No daily order data',
  noTransactionData: 'No transaction data',
  ordersByPaymentStatus: 'Orders by payment status',
  ordersByFulfillStatus: 'Orders by fulfill status',
  topProducts: 'Top products',
  recentOrders: 'Recent orders',
  noRecentOrders: 'No recent orders',
  noTopProducts: 'No top products',
  orderId: 'Order ID',
  store: 'Store',
  items: 'Items',
  paymentStatus: 'Payment',
  fulfillStatus: 'Fulfill',
  created: 'Created',
  viewAll: 'View all',
  vsPrevious: 'vs previous period',
  empty: 'No data available',
  units: 'units',
  ordersTotalRow: 'Total',
  ordersShippingRow: 'Shipping',
  ordersDeliveredRow: 'Delivered',
  ordersOnHoldRow: 'On Hold',
  revenueTotalRow: 'Total Revenue',
  revenuePeriodRow: 'This Period',
  revenuePaidRow: 'Paid',
  revenuePendingRow: 'Pending Approval',
  productsStockTitle: 'Products & Stock',
  productsRow: 'Products',
  variantsRow: 'Variants',
  stockRow: 'Stock',
  lowStockRow: 'Low Stock',
  financialsTitle: 'Financials',
  depositsRow: 'Deposits',
  withdrawalsRow: 'Withdrawals',
  paymentsRow: 'Payments',
  txPeriodRow: 'Transactions This Period',
  paymentBreakdownTitle: 'Orders by Payment',
  ordersUnit: 'orders',
  rankingProductsTitle: 'Product Ranking',
  rankingSellersTitle: 'Seller Ranking',
  rankingUpdated: 'Updated:',
  rankCol: 'Rank',
  productNameCol: 'Product Name',
  soldQtyCol: 'Units Sold',
  sellerNameCol: 'Seller',
  totalItemsCol: 'Total Items',
  noSellerData: 'No seller data',
  funnelCellSize: '1 cell = {size} orders',
  flowNewOrder: 'New Order',
  flowConfirmed: 'Confirmed',
  flowProducing: 'Producing',
  flowShipped: 'Shipped',
  shopStatsTitle: 'Order Stats by Shop',
  shopColIndex: '#',
  shopColName: 'Shop',
  shopColTotal: 'Total Orders',
  shopColRefund: 'Refunded',
  shopColPaid: 'Paid',
  shopColProcessing: 'Processing',
  shopColOnHold: 'On Hold',
  shopColSellers: 'Sellers',
  noShopData: 'No data',
}

const PRODUCT_COLORS = ['#0f766e', '#2563eb', '#f97316', '#e11d48', '#7c3aed']
const TRANSACTION_COLORS: Record<string, string> = {
  deposit: '#059669',
  withdrawal: '#dc2626',
  payment: '#2563eb',
  refund: '#f59e0b',
  other: '#64748b',
}
const PAYMENT_STATUS_COLORS: Record<string, string> = {
  paid: '#059669',
  pending: '#f59e0b',
  processing: '#2563eb',
  completed: '#10b981',
  cancelled: '#ef4444',
  refunded: '#8b5cf6',
  unpaid: '#dc2626',
}
const PRODUCTION_FLOW = [
  { key: 'new_order', color: '#6366f1' },
  { key: 'confirm', color: '#2563eb' },
  { key: 'producing', color: '#0891b2' },
  { key: 'shipped', color: '#059669' },
]


function formatCurrency(amount: number | null | undefined) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(amount || 0)
}

function formatNumber(value: number | null | undefined) {
  return new Intl.NumberFormat('en-US').format(value || 0)
}

function formatShortDate(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return `${date.getDate()}/${date.getMonth() + 1}`
}

function getGrowthColor(growth: number) {
  if (growth > 0) return 'text-emerald-600'
  if (growth < 0) return 'text-rose-600'
  return 'text-muted-foreground'
}

type ProductSalesTooltipEntry = {
  dataKey?: string | number
  name?: string
  color?: string
  value?: number | string
}

function ProductSalesTooltip({
  active,
  payload,
  label,
}: {
  active?: boolean
  payload?: ProductSalesTooltipEntry[]
  label?: string
}) {
  if (!active || !payload?.length) return null

  const rows = payload
    .filter((entry: ProductSalesTooltipEntry) => typeof entry.value === 'number' && Number(entry.value) > 0)
    .sort((a: ProductSalesTooltipEntry, b: ProductSalesTooltipEntry) => Number(b.value) - Number(a.value))

  if (rows.length === 0) return null

  return (
    <div className='min-w-[260px] rounded-[12px] border border-border/80 bg-background/95 p-4 shadow-xl backdrop-blur-sm'>
      <div className='mb-3 text-sm font-semibold text-foreground'>{label}</div>
      <div className='space-y-2.5'>
        {rows.map((entry: ProductSalesTooltipEntry) => (
          <div key={String(entry.dataKey)} className='flex items-center justify-between gap-4'>
            <div className='flex min-w-0 items-center gap-2.5'>
              <span
                className='size-2.5 shrink-0 rounded-full'
                style={{ backgroundColor: entry.color || '#64748b' }}
              />
              <span
                className='truncate text-sm font-medium'
                style={{ color: entry.color || '#0f172a' }}
              >
                {entry.name}
              </span>
            </div>
            <span className='text-sm font-semibold text-foreground'>
              {formatNumber(Number(entry.value))}
            </span>
          </div>
        ))}
      </div>
    </div>
  )
}

function ProductSalesLegend({
  items,
}: {
  items: string[]
}) {
  return (
    <div className='flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-border/70 pt-4'>
      {items.map((name, index) => (
        <div key={name} className='flex items-center gap-2'>
          <span
            className='size-2.5 rounded-full'
            style={{ backgroundColor: PRODUCT_COLORS[index % PRODUCT_COLORS.length] }}
          />
          <span className='text-xs font-medium text-muted-foreground'>{name}</span>
        </div>
      ))}
    </div>
  )
}

function rankProductSeries(
  rows: Array<Record<string, string | number>>,
  keys: string[]
) {
  return [...keys]
    .map((key) => ({
      key,
      total: rows.reduce(
        (sum, row) => sum + (typeof row[key] === 'number' ? Number(row[key]) : 0),
        0
      ),
    }))
    .sort((a, b) => b.total - a.total)
    .map((item) => item.key)
}

const FUNNEL_CELL_SIZE = 50

function FulfillFunnel({
  entries,
  emptyText,
  flowLabels,
}: {
  entries: Array<[string, number]>
  emptyText: string
  flowLabels: Record<string, string>
}) {
  const countMap = Object.fromEntries(entries)
  const flowCounts = PRODUCTION_FLOW.map((s) => countMap[s.key] || 0)
  const max = Math.max(...flowCounts, 1)
  const totalCells = Math.ceil(max / FUNNEL_CELL_SIZE) || 1
  const total = entries.reduce((sum, [, v]) => sum + v, 0)

  if (total === 0) {
    return (
      <div className='py-6 text-center text-sm text-muted-foreground'>
        {emptyText}
      </div>
    )
  }

  return (
    <div className='space-y-4'>
      <div className='space-y-1'>
        {PRODUCTION_FLOW.map((stage, index) => {
          const count = countMap[stage.key] || 0
          const fullCells = Math.floor(count / FUNNEL_CELL_SIZE)
          const remainder = (count % FUNNEL_CELL_SIZE) / FUNNEL_CELL_SIZE

          return (
            <div key={stage.key}>
              <div className='flex items-center gap-3'>
                <div className='w-[88px] shrink-0 text-right text-[11px] font-medium text-muted-foreground'>
                  {flowLabels[stage.key] || stage.key}
                </div>
                <div className='flex flex-1 gap-0.5'>
                  {Array.from({ length: totalCells }).map((_, i) => {
                    const isFull = i < fullCells
                    const isPartial = i === fullCells && remainder > 0
                    return (
                      <div
                        key={i}
                        className='relative h-8 flex-1 overflow-hidden rounded-[3px] bg-muted/50'
                      >
                        {isFull ? (
                          <div
                            className='absolute inset-0'
                            style={{ backgroundColor: stage.color }}
                          />
                        ) : isPartial ? (
                          <div
                            className='absolute inset-y-0 left-0'
                            style={{
                              width: `${remainder * 100}%`,
                              backgroundColor: stage.color,
                            }}
                          />
                        ) : null}
                      </div>
                    )
                  })}
                </div>
                <div className='w-10 shrink-0 text-right text-[12px] font-semibold tabular-nums'>
                  {formatNumber(count)}
                </div>
              </div>
              {index < PRODUCTION_FLOW.length - 1 ? (
                <div className='ml-[100px] h-2.5 w-px bg-border/50' />
              ) : null}
            </div>
          )
        })}
      </div>

    </div>
  )
}

function RankingTable({
  title,
  subtitle,
  icon: Icon,
  iconBgClass,
  iconColorClass,
  rankLabel,
  nameLabel,
  metricLabel,
  rows,
  emptyText,
}: {
  title: string
  subtitle?: string
  icon: React.ComponentType<{ className?: string }>
  iconBgClass: string
  iconColorClass: string
  rankLabel: string
  nameLabel: string
  metricLabel: string
  rows: Array<{ name: string; value: string }>
  emptyText: string
}) {
  return (
    <Card className='rounded-[10px] gap-0 py-4'>
      <CardContent className='space-y-4 px-5'>
        <div className='flex items-start gap-3'>
          <div
            className={cn(
              'flex size-9 shrink-0 items-center justify-center rounded-[8px]',
              iconBgClass
            )}
          >
            <Icon className={cn('size-4', iconColorClass)} />
          </div>
          <div className='min-w-0 space-y-0.5'>
            <div className='truncate text-[14px] font-semibold text-foreground'>
              {title}
            </div>
            {subtitle ? (
              <div className='truncate text-[11px] text-muted-foreground'>
                {subtitle}
              </div>
            ) : null}
          </div>
        </div>

        {rows.length > 0 ? (
          <div>
            <div className='grid grid-cols-[60px_1fr_120px] items-center gap-3 border-b border-border/60 pb-2 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground'>
              <span>{rankLabel}</span>
              <span>{nameLabel}</span>
              <span className='text-right'>{metricLabel}</span>
            </div>
            <div className='divide-y divide-border/40'>
              {rows.map((row, index) => (
                <div
                  key={`${row.name}-${index}`}
                  className='grid grid-cols-[60px_1fr_120px] items-center gap-3 py-2.5'
                >
                  <span
                    className={cn(
                      'text-[13px] font-semibold',
                      index === 0
                        ? 'text-amber-600 dark:text-amber-400'
                        : index === 1
                          ? 'text-slate-500 dark:text-slate-300'
                          : index === 2
                            ? 'text-orange-600 dark:text-orange-400'
                            : 'text-muted-foreground'
                    )}
                  >
                    #{index + 1}
                  </span>
                  <span className='truncate text-[13px] font-medium text-foreground'>
                    {row.name}
                  </span>
                  <span className='text-right text-[13px] font-semibold tabular-nums text-foreground'>
                    {row.value}
                  </span>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className='py-8 text-center text-sm text-muted-foreground'>
            {emptyText}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

type StatRow = {
  label: string
  value: string
  tone?: 'default' | 'positive' | 'negative' | 'muted'
  growth?: number
}

function CompactStatsCard({
  title,
  rows,
}: {
  title: string
  rows: StatRow[]
}) {
  return (
    <Card className='rounded-[10px] gap-0 py-4'>
      <CardContent className='space-y-3 px-5'>
        <div className='text-[11px] font-semibold uppercase tracking-wider text-muted-foreground'>
          {title}
        </div>
        <div className='space-y-2'>
          {rows.map((row) => (
            <div
              key={row.label}
              className='flex items-center justify-between text-[13px]'
            >
              <span className='text-muted-foreground'>{row.label}</span>
              <div className='flex items-center gap-2'>
                <span
                  className={cn(
                    'font-semibold tabular-nums',
                    row.tone === 'positive' && 'text-emerald-600 dark:text-emerald-400',
                    row.tone === 'negative' && 'text-rose-600 dark:text-rose-400',
                    row.tone === 'muted' && 'text-muted-foreground'
                  )}
                >
                  {row.value}
                </span>
                {typeof row.growth === 'number' ? (
                  <span
                    className={cn(
                      'inline-flex items-center text-[10px] font-medium',
                      getGrowthColor(row.growth)
                    )}
                  >
                    {row.growth > 0 ? (
                      <ArrowUpRight className='size-3' />
                    ) : row.growth < 0 ? (
                      <ArrowDownRight className='size-3' />
                    ) : null}
                    {Math.abs(row.growth).toFixed(1)}%
                  </span>
                ) : null}
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}

function StatusBreakdownCard({
  title,
  totalLabel,
  total,
  entries,
  colorMap,
  emptyText,
}: {
  title: string
  totalLabel: string
  total: number
  entries: Array<[string, number]>
  colorMap: Record<string, string>
  emptyText: string
}) {
  return (
    <Card className='rounded-[10px] gap-0 py-4'>
      <CardContent className='space-y-3 px-5'>
        <div className='flex items-center justify-between'>
          <span className='text-[11px] font-semibold uppercase tracking-wider text-muted-foreground'>
            {title}
          </span>
          <span className='text-[11px] text-muted-foreground'>{totalLabel}</span>
        </div>
        <div className='text-2xl font-semibold tabular-nums'>
          {formatNumber(total)}
        </div>
        {entries.length > 0 ? (
          <div className='space-y-2 pt-1'>
            {entries.slice(0, 6).map(([key, count]) => {
              const pct = total > 0 ? (count / total) * 100 : 0
              const color = colorMap[key] || '#94a3b8'
              return (
                <div key={key} className='space-y-1'>
                  <div className='flex items-center justify-between text-[12px]'>
                    <span className='flex items-center gap-2 capitalize text-muted-foreground'>
                      <span
                        className='size-1.5 rounded-full'
                        style={{ backgroundColor: color }}
                      />
                      {key.replaceAll('_', ' ')}
                    </span>
                    <span className='font-semibold tabular-nums'>
                      {formatNumber(count)}
                    </span>
                  </div>
                  <div className='h-1 overflow-hidden rounded-full bg-muted/60'>
                    <div
                      className='h-full rounded-full transition-[width]'
                      style={{ width: `${pct}%`, backgroundColor: color }}
                    />
                  </div>
                </div>
              )
            })}
          </div>
        ) : (
          <div className='py-4 text-center text-sm text-muted-foreground'>
            {emptyText}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function DashboardSkeleton() {
  return (
    <div className='space-y-6'>
      <div className='flex items-center justify-between'>
        <div className='space-y-2'>
          <Skeleton className='h-10 w-48' />
          <Skeleton className='h-4 w-80' />
        </div>
        <Skeleton className='h-10 w-60' />
      </div>

      <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-4'>
        {Array.from({ length: 4 }).map((_, index) => (
          <Skeleton key={index} className='h-40 rounded-[10px]' />
        ))}
      </div>

      <div className='grid gap-4 xl:grid-cols-2'>
        <Skeleton className='h-[360px] rounded-[10px]' />
        <Skeleton className='h-[360px] rounded-[10px]' />
      </div>
    </div>
  )
}

export function LemiexDashboard() {
  const { messages } = useI18n()
  const user = useAuthStore((state) => state.auth.user)
  const role = getLemiexRole(user?.role ?? user?.role_name)
  const isStaff = role === 'Staff'
  const isSeller = role === 'Seller'
  const m = messages.dashboardPage ?? fallbackMessages

  const [timeRange, setTimeRange] = useState('30')
  const [loading, setLoading] = useState(true)
  const [stats, setStats] = useState<DashboardData | null>(null)

  const loadDashboard = useCallback(async () => {
    try {
      setLoading(true)
      const data = await fetchDashboardStatistics(timeRange)
      setStats(data)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.failedLoad)
    } finally {
      setLoading(false)
    }
  }, [m.failedLoad, timeRange])

  useEffect(() => {
    void loadDashboard()
  }, [loadDashboard])

  const overview = stats?.overview || {}
  const paymentStatusEntries = Object.entries(stats?.orders_by_payment_status || {})
  const fulfillStatusEntries = Object.entries(stats?.orders_by_fulfill_status || {})
  const recentOrders = stats?.recent_orders || []
  const topProducts = stats?.top_products || []
  const topSellers = useMemo(() => {
    const sellerMap = new Map<string, number>()
    recentOrders.forEach((order) => {
      const name = order.store_name || 'Unknown'
      sellerMap.set(name, (sellerMap.get(name) || 0) + (order.total_items || 1))
    })
    return Array.from(sellerMap.entries())
      .sort((a, b) => b[1] - a[1])
      .slice(0, 10)
      .map(([name, count]) => ({ name, count }))
  }, [recentOrders])
  const shopStats = stats?.shop_stats || []
  const productSalesChart = useMemo(
    () => stats?.product_sales_chart || [],
    [stats?.product_sales_chart]
  )
  const productSeries = useMemo(
    () => stats?.top_product_names || [],
    [stats?.top_product_names]
  )
  const rankedProductSeries = useMemo(
    () => rankProductSeries(productSalesChart, productSeries),
    [productSalesChart, productSeries]
  )
  const displayedProductSeries = useMemo(
    () => rankedProductSeries.slice(0, 4),
    [rankedProductSeries]
  )
  const revenueChart = useMemo(() => stats?.revenue_chart || [], [stats?.revenue_chart])
  const orderCountChart = stats?.order_count_chart || []
  const transactionChart = useMemo(
    () => stats?.transaction_chart || [],
    [stats?.transaction_chart]
  )
  const transactionSummary = stats?.transaction_summary

  const paymentStatuses = useMemo(() => {
    const keys = new Set<string>()
    revenueChart.forEach((point) => {
      Object.entries(point).forEach(([key, value]) => {
        if (key !== 'date' && typeof value === 'number' && value > 0) keys.add(key)
      })
    })
    return Array.from(keys)
  }, [revenueChart])

  const transactionTypes = useMemo(() => {
    const keys = new Set<string>()
    transactionChart.forEach((point) => {
      Object.entries(point).forEach(([key]) => {
        if (key !== 'date') keys.add(key)
      })
    })
    return Array.from(keys)
  }, [transactionChart])

  return (
    <>
      <Header fixed>
        <Search />
        <div className='ml-auto flex items-center space-x-4'>
          <LanguageSwitch />
          <ThemeSwitch />
          <ProfileDropdown />
        </div>
      </Header>

      <Main fluid className='space-y-6 px-4 py-6 @7xl/content:px-6'>
        {loading ? (
          <DashboardSkeleton />
        ) : (
          <>
            <div className='flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between'>
              <div className='space-y-1'>
                <h1 className='text-3xl font-semibold tracking-tight'>
                  {m.title}
                </h1>
                <p className='text-sm text-muted-foreground'>
                  {m.subtitle}
                </p>
              </div>

              <div className='flex w-full flex-col items-start gap-3 lg:w-auto lg:min-w-[280px] lg:items-end'>
                <div className='space-y-2 lg:text-right'>
                  <div className='text-xs font-medium uppercase tracking-wide text-muted-foreground'>
                    {m.timeRangeLabel}
                  </div>
                  <Tabs value={timeRange} onValueChange={setTimeRange}>
                    <TabsList>
                      <TabsTrigger value='1'>{m.today}</TabsTrigger>
                      <TabsTrigger value='2'>{m.yesterday}</TabsTrigger>
                      <TabsTrigger value='7'>{m.last7Days}</TabsTrigger>
                      <TabsTrigger value='30'>{m.last30Days}</TabsTrigger>
                      <TabsTrigger value='90'>{m.last90Days}</TabsTrigger>
                      <TabsTrigger value='365'>{m.lastYear}</TabsTrigger>
                    </TabsList>
                  </Tabs>
                </div>
                {isSeller ? (
                  <p className='max-w-[280px] text-right text-xs text-muted-foreground'>
                    {m.sellerScopeDescription}
                  </p>
                ) : null}
              </div>
            </div>

            <div className={cn('grid gap-4', isStaff ? 'lg:grid-cols-2' : 'lg:grid-cols-3')}>
              <CompactStatsCard
                title={m.totalOrders}
                rows={[
                  {
                    label: m.ordersTotalRow,
                    value: formatNumber(overview.total_orders),
                  },
                  {
                    label: m.ordersShippingRow,
                    value: formatNumber(
                      stats?.orders_by_fulfill_status?.shipped || 0
                    ),
                  },
                  {
                    label: m.ordersDeliveredRow,
                    value: formatNumber(
                      stats?.orders_by_fulfill_status?.delivered || 0
                    ),
                    tone: 'positive',
                  },
                  {
                    label: m.ordersOnHoldRow,
                    value: formatNumber(
                      stats?.orders_by_fulfill_status?.on_hold || 0
                    ),
                    tone: 'negative',
                  },
                ]}
              />

              {!isStaff ? (
                <CompactStatsCard
                  title={m.totalRevenue}
                  rows={[
                    {
                      label: m.revenueTotalRow,
                      value: formatCurrency(overview.total_revenue),
                      tone: 'positive',
                    },
                    {
                      label: m.revenuePeriodRow,
                      value: formatCurrency(overview.revenue_this_period),
                      tone: 'positive',
                      growth: overview.revenue_growth,
                    },
                    ...(transactionSummary
                      ? [
                          {
                            label: m.revenuePaidRow,
                            value: formatCurrency(transactionSummary.total_payments),
                          } as StatRow,
                          {
                            label: m.revenuePendingRow,
                            value: formatNumber(
                              transactionSummary.pending_transactions
                            ),
                            tone: 'muted',
                          } as StatRow,
                        ]
                      : []),
                  ]}
                />
              ) : null}

              <CompactStatsCard
                title={m.productsStockTitle}
                rows={[
                  {
                    label: m.productsRow,
                    value: formatNumber(overview.total_products),
                  },
                  {
                    label: m.variantsRow,
                    value: `${formatNumber(overview.total_variants)} · ${formatNumber(overview.active_variants)} active`,
                  },
                  {
                    label: m.stockRow,
                    value: formatNumber(overview.total_stock),
                  },
                  {
                    label: m.lowStockRow,
                    value: formatNumber(overview.low_stock_variants),
                    tone:
                      (overview.low_stock_variants || 0) > 0
                        ? 'negative'
                        : 'muted',
                  },
                ]}
              />
            </div>

            {!isStaff ? (
              <div className='grid gap-4 lg:grid-cols-2'>
                <StatusBreakdownCard
                  title={m.paymentBreakdownTitle}
                  totalLabel={`${formatNumber(overview.total_orders)} ${m.ordersUnit}`}
                  total={overview.total_orders || 0}
                  entries={paymentStatusEntries}
                  colorMap={PAYMENT_STATUS_COLORS}
                  emptyText={m.empty}
                />
                {transactionSummary ? (
                  <CompactStatsCard
                    title={m.financialsTitle}
                    rows={[
                      {
                        label: m.depositsRow,
                        value: formatCurrency(transactionSummary.total_deposits),
                        tone: 'positive',
                      },
                      {
                        label: m.withdrawalsRow,
                        value: formatCurrency(transactionSummary.total_withdrawals),
                        tone: 'negative',
                      },
                      {
                        label: m.paymentsRow,
                        value: formatCurrency(transactionSummary.total_payments),
                      },
                      {
                        label: m.txPeriodRow,
                        value: formatNumber(
                          transactionSummary.transactions_this_period
                        ),
                        tone: 'muted',
                      },
                    ]}
                  />
                ) : null}
              </div>
            ) : null}

            {/* Section 3: Ranking tables */}
            <div className='grid gap-4 lg:grid-cols-2'>
              <RankingTable
                title={m.rankingProductsTitle}
                subtitle={`${m.rankingUpdated} ${new Date().toLocaleDateString('vi-VN')}`}
                icon={Package}
                iconBgClass='bg-violet-100 dark:bg-violet-950/40'
                iconColorClass='text-violet-700 dark:text-violet-300'
                rankLabel={m.rankCol}
                nameLabel={m.productNameCol}
                metricLabel={m.soldQtyCol}
                rows={topProducts.slice(0, 10).map((product) => ({
                  name: product.product_name || m.empty,
                  value: `${formatNumber(product.total_quantity)} ${m.units}`,
                }))}
                emptyText={m.noTopProducts}
              />

              {!isSeller ? (
                <RankingTable
                  title={m.rankingSellersTitle}
                  subtitle={`${m.rankingUpdated} ${new Date().toLocaleDateString('vi-VN')}`}
                  icon={Trophy}
                  iconBgClass='bg-amber-100 dark:bg-amber-950/40'
                  iconColorClass='text-amber-700 dark:text-amber-300'
                  rankLabel={m.rankCol}
                  nameLabel={m.sellerNameCol}
                  metricLabel={m.totalItemsCol}
                  rows={topSellers.map((seller) => ({
                    name: seller.name,
                    value: formatNumber(seller.count),
                  }))}
                  emptyText={m.noSellerData}
                />
              ) : null}
            </div>

            <div className={cn('grid gap-4', isStaff ? 'xl:grid-cols-1' : 'xl:grid-cols-2')}>
              <Card className='gap-0 rounded-[10px]'>
                <CardHeader>
                  <CardTitle>{m.productSalesQuantity}</CardTitle>
                  <CardDescription>{m.top5Products}</CardDescription>
                </CardHeader>
                <CardContent className='space-y-4 px-4 pb-4'>
                  {productSalesChart.length > 0 ? (
                    <>
                      <div className='h-[340px] rounded-[12px] border border-border/70 bg-background p-2'>
                        <ResponsiveContainer width='100%' height='100%'>
                          <LineChart
                            data={productSalesChart}
                            margin={{ top: 8, right: 16, bottom: 8, left: 0 }}
                          >
                            <CartesianGrid
                              stroke='rgba(148, 163, 184, 0.28)'
                              strokeDasharray='4 4'
                              vertical={false}
                            />
                            <XAxis
                              dataKey='date'
                              tickFormatter={formatShortDate}
                              fontSize={12}
                              tickLine={false}
                              axisLine={false}
                              dy={6}
                              tick={{ fill: '#64748b' }}
                            />
                            <YAxis
                              fontSize={12}
                              allowDecimals={false}
                              tickLine={false}
                              axisLine={false}
                              width={36}
                              tick={{ fill: '#64748b' }}
                            />
                            <Tooltip
                              cursor={{ stroke: 'rgba(59, 130, 246, 0.35)', strokeDasharray: '4 4' }}
                              content={<ProductSalesTooltip />}
                            />
                            {displayedProductSeries.map((name, index) => (
                              <Line
                                key={name}
                                dataKey={name}
                                type='linear'
                                stroke={PRODUCT_COLORS[index % PRODUCT_COLORS.length]}
                                strokeWidth={index === 0 ? 3.5 : 2.25}
                                dot={false}
                                activeDot={{
                                  r: 6,
                                  fill: PRODUCT_COLORS[index % PRODUCT_COLORS.length],
                                  stroke: '#ffffff',
                                  strokeWidth: 3,
                                }}
                              />
                            ))}
                          </LineChart>
                        </ResponsiveContainer>
                      </div>
                      <ProductSalesLegend items={displayedProductSeries} />
                    </>
                  ) : (
                    <div className='flex h-[340px] items-center justify-center text-sm text-muted-foreground'>
                      {m.noSalesData}
                    </div>
                  )}
                </CardContent>
              </Card>

              {!isStaff ? (
                <Card className='gap-0 rounded-[10px]'>
                  <CardHeader>
                    <CardTitle>{m.revenueByPaymentStatus}</CardTitle>
                    <CardDescription>{m.dailyBreakdown}</CardDescription>
                  </CardHeader>
                  <CardContent className='h-[340px] px-4 pb-4'>
                    {revenueChart.length > 0 && paymentStatuses.length > 0 ? (
                      <ResponsiveContainer width='100%' height='100%'>
                        <BarChart data={revenueChart}>
                          <CartesianGrid strokeDasharray='3 3' vertical={false} />
                          <XAxis dataKey='date' tickFormatter={formatShortDate} fontSize={12} />
                          <YAxis
                            fontSize={12}
                            tickFormatter={(value) => `$${Math.round(Number(value) / 1000)}k`}
                          />
                        <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                          {paymentStatuses.map((status, index) => (
                            <Bar
                              key={status}
                              dataKey={status}
                              stackId='revenue'
                              fill={
                                PAYMENT_STATUS_COLORS[status] ||
                                PRODUCT_COLORS[index % PRODUCT_COLORS.length]
                              }
                            />
                          ))}
                        </BarChart>
                      </ResponsiveContainer>
                    ) : (
                      <div className='flex h-full items-center justify-center text-sm text-muted-foreground'>
                        {m.noRevenueData}
                      </div>
                    )}
                  </CardContent>
                </Card>
              ) : null}
            </div>

            <div className={cn('grid gap-4', isStaff ? 'xl:grid-cols-1' : 'xl:grid-cols-2')}>
              <Card className='gap-0 rounded-[10px]'>
                <CardHeader>
                  <CardTitle>{m.dailyOrders}</CardTitle>
                  <CardDescription>{m.ordersPerDay}</CardDescription>
                </CardHeader>
                <CardContent className='h-[320px] px-4 pb-4'>
                  {orderCountChart.length > 0 ? (
                    <ResponsiveContainer width='100%' height='100%'>
                      <LineChart data={orderCountChart} margin={{ top: 8, right: 12, bottom: 12, left: 0 }}>
                        <CartesianGrid strokeDasharray='4 4' vertical={false} />
                        <XAxis dataKey='date' tickFormatter={formatShortDate} fontSize={12} tickLine={false} axisLine={false} dy={6} />
                        <YAxis allowDecimals={false} fontSize={12} tickLine={false} axisLine={false} width={32} />
                        <Tooltip />
                        <Line
                          type='monotone'
                          dataKey='orders'
                          stroke='#2563eb'
                          strokeWidth={2}
                          dot={false}
                          activeDot={{ r: 5, fill: '#2563eb', stroke: '#fff', strokeWidth: 2 }}
                        />
                      </LineChart>
                    </ResponsiveContainer>
                  ) : (
                    <div className='flex h-full items-center justify-center text-sm text-muted-foreground'>
                      {m.noOrderData}
                    </div>
                  )}
                </CardContent>
              </Card>

              {!isStaff ? (
                <Card className='gap-0 rounded-[10px]'>
                  <CardHeader>
                    <CardTitle>{m.transactionsOverview}</CardTitle>
                    <CardDescription>{m.dailyTransactions}</CardDescription>
                  </CardHeader>
                  <CardContent className='h-[320px] px-4 pb-4'>
                    {transactionChart.length > 0 && transactionTypes.length > 0 ? (
                      <ResponsiveContainer width='100%' height='100%'>
                        <BarChart data={transactionChart}>
                          <CartesianGrid strokeDasharray='3 3' vertical={false} />
                          <XAxis dataKey='date' tickFormatter={formatShortDate} fontSize={12} />
                          <YAxis
                            fontSize={12}
                            tickFormatter={(value) => `$${Math.round(Number(value) / 1000)}k`}
                          />
                          <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                          {transactionTypes.map((type) => (
                            <Bar
                              key={type}
                              dataKey={type}
                              stackId='transactions'
                              fill={TRANSACTION_COLORS[type] || TRANSACTION_COLORS.other}
                            />
                          ))}
                        </BarChart>
                      </ResponsiveContainer>
                    ) : (
                      <div className='flex h-full items-center justify-center text-sm text-muted-foreground'>
                        {m.noTransactionData}
                      </div>
                    )}
                  </CardContent>
                </Card>
              ) : null}
            </div>

            {/* Production pipeline funnel — full width */}
            <Card className='gap-0 rounded-[10px]'>
              <CardHeader>
                <CardTitle>{m.ordersByFulfillStatus}</CardTitle>
              </CardHeader>
              <CardContent className='px-5 pb-5'>
                <FulfillFunnel
                  entries={fulfillStatusEntries}
                  emptyText={m.empty}
                  flowLabels={{
                    new_order: m.flowNewOrder,
                    confirm: m.flowConfirmed,
                    producing: m.flowProducing,
                    shipped: m.flowShipped,
                  }}
                />
              </CardContent>
            </Card>

            <Card className='gap-0 rounded-[10px]'>
              <CardContent className='space-y-3 px-5 py-4'>
                <p className='text-[13px] font-semibold text-foreground'>
                  {m.shopStatsTitle}
                </p>
                {shopStats.length > 0 ? (
                  <div className='overflow-x-auto'>
                    <table className='w-full text-[13px]'>
                      <thead>
                        <tr className='border-b border-border/60 text-[12px] font-medium text-muted-foreground'>
                          <th className='py-3 pr-3 text-left font-medium'>{m.shopColIndex}</th>
                          <th className='py-3 pr-3 text-left font-medium'>{m.shopColName}</th>
                          <th className='py-3 pr-3 text-left font-medium'>
                            <span className='inline-flex items-center gap-1'>
                              {m.shopColTotal}
                              <ArrowUpDown className='size-3 opacity-60' />
                            </span>
                          </th>
                          <th className='py-3 pr-3 text-left font-medium'>
                            <span className='inline-flex items-center gap-1'>
                              {m.shopColRefund}
                              <ArrowUpDown className='size-3 opacity-60' />
                            </span>
                          </th>
                          <th className='py-3 pr-3 text-left font-medium'>
                            <span className='inline-flex items-center gap-1'>
                              {m.shopColPaid}
                              <ArrowUpDown className='size-3 opacity-60' />
                            </span>
                          </th>
                          <th className='py-3 pr-3 text-left font-medium'>
                            <span className='inline-flex items-center gap-1'>
                              {m.shopColProcessing}
                              <ArrowUpDown className='size-3 opacity-60' />
                            </span>
                          </th>
                          <th className='py-3 pr-3 text-left font-medium'>
                            <span className='inline-flex items-center gap-1'>
                              {m.shopColOnHold}
                              <ArrowUpDown className='size-3 opacity-60' />
                            </span>
                          </th>
                          <th className='py-3 text-left font-medium'>{m.shopColSellers}</th>
                        </tr>
                      </thead>
                      <tbody className='divide-y divide-border/40'>
                        {shopStats.map((shop, index) => (
                          <tr key={shop.shop_id ?? shop.shop_name ?? index}>
                            <td className='py-3 pr-3 text-muted-foreground'>
                              {index + 1}
                            </td>
                            <td className='py-3 pr-3 font-medium text-foreground'>
                              {shop.shop_name || '—'}
                            </td>
                            <td className='py-3 pr-3 tabular-nums text-foreground'>
                              {formatNumber(shop.total)}
                            </td>
                            <td className='py-3 pr-3 tabular-nums'>
                              {(shop.refund || 0) > 0 ? (
                                <span>
                                  {formatNumber(shop.refund)}{' '}
                                  <span className='text-muted-foreground'>
                                    ({(shop.refund_pct || 0).toFixed(1)}%)
                                  </span>
                                </span>
                              ) : (
                                <span className='text-muted-foreground/50'>—</span>
                              )}
                            </td>
                            <td className='py-3 pr-3 tabular-nums'>
                              <span className='text-emerald-600 dark:text-emerald-400'>
                                {formatCurrency(shop.paid_amount)}
                              </span>
                              <span className='ml-1 text-muted-foreground'>
                                ({formatNumber(shop.paid)})
                              </span>
                            </td>
                            <td className='py-3 pr-3 tabular-nums'>
                              <span className='text-sky-600 dark:text-sky-400'>
                                {formatCurrency(shop.processing_amount)}
                              </span>
                              <span className='ml-1 text-muted-foreground'>
                                ({formatNumber(shop.processing)})
                              </span>
                            </td>
                            <td className='py-3 pr-3 tabular-nums'>
                              {(shop.on_hold || 0) > 0 ? (
                                <>
                                  <span className='text-amber-600 dark:text-amber-400'>
                                    {formatCurrency(shop.on_hold_amount)}
                                  </span>
                                  <span className='ml-1 text-muted-foreground'>
                                    ({formatNumber(shop.on_hold)})
                                  </span>
                                </>
                              ) : (
                                <span className='text-muted-foreground/50'>—</span>
                              )}
                            </td>
                            <td className='py-3 tabular-nums text-foreground'>
                              {formatNumber(shop.seller_count)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <div className='py-8 text-center text-sm text-muted-foreground'>
                    {m.noShopData}
                  </div>
                )}
              </CardContent>
            </Card>
          </>
        )}
      </Main>
    </>
  )
}
