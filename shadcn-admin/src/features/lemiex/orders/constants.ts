import { type LemiexOrdersFilters, type OrdersOption } from './types'

export const DEFAULT_ORDERS_FILTERS: LemiexOrdersFilters = {
  order_id: '',
  ref_id: '',
  tracking_number: '',
  product_name: '',
  variant_id: '',
  style: '',
  color: '',
  size: '',
  seller_id: '',
  fulfill_status: [],
  payment_status: [],
  exclude_status: [],
  date_from: '',
  date_to: '',
  shipped_date_from: '',
  shipped_date_to: '',
  sort_by: 'created_at',
  sort_order: 'asc',
  missing_shipping_info: false,
}

export const PAYMENT_STATUS_OPTIONS: OrdersOption[] = [
  { value: 'pending', label: 'pending' },
  { value: 'paid', label: 'paid' },
  { value: 'partial_refund', label: 'partial_refund' },
  { value: 'refunded', label: 'refunded' },
  { value: 'failed', label: 'failed' },
]

export const FALLBACK_FULFILL_STATUS_OPTIONS: OrdersOption[] = [
  { value: 'new_order', label: 'New Order' },
  { value: 'confirm', label: 'Confirm' },
  { value: 'pending_stock', label: 'Pending Stock' },
  { value: 'in_stock', label: 'In Stock' },
  { value: 'producing', label: 'Producing' },
  { value: 'qc_pass', label: 'QC Pass' },
  { value: 'packed', label: 'Packed' },
  { value: 'shipped', label: 'Shipped' },
  { value: 'on_hold', label: 'On Hold' },
  { value: 'return_to_support', label: 'Return To Support' },
  { value: 'cancelled', label: 'Cancelled' },
  {
    value: 'cancelled_refund_shipping',
    label: 'Cancelled (Refund Shipping)',
  },
  { value: 'closed', label: 'Closed' },
  { value: 'test_order', label: 'Test Order' },
]

export const SORT_BY_OPTIONS: OrdersOption[] = [
  { value: 'created_at', label: 'created_at' },
  { value: 'updated_at', label: 'updated_at' },
  { value: 'shipped_at', label: 'shipped_at' },
  { value: 'id', label: 'id' },
  { value: 'ref_id', label: 'ref_id' },
]

export const SORT_ORDER_OPTIONS: OrdersOption[] = [
  { value: 'asc', label: 'asc' },
  { value: 'desc', label: 'desc' },
]

