import { type OrderListItem } from '@/services/orders/api'

export type LemiexOrdersFilters = {
  order_id: string
  ref_id: string
  tracking_number: string
  product_name: string
  variant_id: string
  style: string
  color: string
  size: string
  seller_id: string
  fulfill_status: string[]
  payment_status: string[]
  exclude_status: string[]
  label_status: string[]
  date_from: string
  date_to: string
  shipped_date_from: string
  shipped_date_to: string
  sort_by: string
  sort_order: 'asc' | 'desc'
  missing_shipping_info: boolean
}

export type LemiexOrdersPageState = {
  page: number
  perPage: number
  filters: LemiexOrdersFilters
}

export type OrdersOption = {
  label: string
  value: string
}

export type LemiexOrderRow = OrderListItem
