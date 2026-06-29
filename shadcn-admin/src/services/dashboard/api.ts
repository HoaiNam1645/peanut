import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

type DashboardResponse = {
  code?: number
  status?: boolean
  success?: boolean
  message?: string
  data?: DashboardData
}

type DashboardOverview = {
  total_orders?: number
  orders_this_period?: number
  orders_growth?: number
  total_revenue?: number
  revenue_this_period?: number
  revenue_growth?: number
  total_products?: number
  total_variants?: number
  active_variants?: number
  total_stock?: number
  low_stock_variants?: number
  total_users?: number
  new_users_this_period?: number
  wallet_balance?: number
}

type DashboardRecentOrder = {
  id: number
  ref_id?: string | null
  store_name?: string | null
  payment_status?: string | null
  fulfill_status?: string | null
  total_items?: number | null
  created_at?: string | null
}

type DashboardTopProduct = {
  product_name?: string | null
  total_quantity?: number | null
  sizes?: Array<{ size?: string | null; quantity?: number | null }> | null
}

type DashboardChartPoint = {
  date: string
  [key: string]: string | number
}

type DashboardShopStat = {
  shop_id?: number | null
  shop_name?: string | null
  total?: number
  refund?: number
  refund_pct?: number
  paid?: number
  processing?: number
  on_hold?: number
  seller_count?: number
  paid_amount?: number
  processing_amount?: number
  on_hold_amount?: number
}

type DashboardTransactionSummary = {
  total_deposits?: number
  total_withdrawals?: number
  total_payments?: number
  pending_transactions?: number
  transactions_this_period?: number
}

export type DashboardData = {
  overview?: DashboardOverview
  orders_by_payment_status?: Record<string, number>
  orders_by_fulfill_status?: Record<string, number>
  recent_orders?: DashboardRecentOrder[]
  top_products?: DashboardTopProduct[]
  product_sales_chart?: DashboardChartPoint[]
  revenue_chart?: DashboardChartPoint[]
  order_count_chart?: DashboardChartPoint[]
  transaction_chart?: DashboardChartPoint[]
  transaction_summary?: DashboardTransactionSummary
  top_product_names?: string[]
  shop_stats?: DashboardShopStat[]
  time_range?: number
  is_seller?: boolean
}

function isSuccess(response: DashboardResponse) {
  return Boolean(response.status || response.success || response.code === 200)
}

export async function fetchDashboardStatistics(timeRange: string) {
  const params = new URLSearchParams({ time_range: timeRange })
  const response = await apiRequest<DashboardResponse>(
    `${API_BASE_URL}${API_ENDPOINTS.DASHBOARD_STATISTICS}?${params.toString()}`,
    {
      method: 'GET',
    }
  )

  if (!isSuccess(response)) {
    throw new Error(response.message || 'Failed to load dashboard statistics')
  }

  return response.data || {}
}
