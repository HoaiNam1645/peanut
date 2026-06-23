'use client'

import { type ColumnDef } from '@tanstack/react-table'
import Link from 'next/link'
import { ArrowUpRight } from 'lucide-react'
import { useI18n } from '@/context/i18n-provider'
import { Badge } from '@/components/ui/badge'
import { OrderActionsCell } from '@/features/lemiex/orders/components/order-actions-cell'
import { OrderFulfillStatusCell } from '@/features/lemiex/orders/components/order-fulfill-status-cell'
import { OrderItemsCell } from '@/features/lemiex/orders/components/order-items-cell'
import { OrderDesignsCell } from '@/features/lemiex/orders/components/order-designs-cell'
import { FALLBACK_FULFILL_STATUS_OPTIONS } from '@/features/lemiex/orders/constants'
import {
  SelectAllOrdersCheckbox,
  SelectOrderCheckbox,
} from '@/features/lemiex/orders/components/orders-selection-context'
import { type LemiexOrderRow } from '@/features/lemiex/orders/types'
import { getUserRoleName } from '@/services/auth/api'
import { type SelectOption } from '@/services/orders/api'
import { type AuthUser } from '@/stores/auth-store'

function formatStatusLabel(
  value: string | null | undefined,
  messages: ReturnType<typeof useI18n>['messages']['orders']
) {
  if (!value) return messages.status.unknown
  const localized =
    messages.fulfillStatuses[
      value as keyof typeof messages.fulfillStatuses
    ] ||
    messages.paymentStatuses[
      value as keyof typeof messages.paymentStatuses
    ]
  if (localized) return localized
  return value.replaceAll('_', ' ')
}

// ShipDVX shipping-creation status shown next to Label/Convert so you can tell
// at a glance which orders have already had shipping created (and its outcome).
const SHIP_STATUS_STYLE: Record<string, { label: string; className: string }> = {
  PENDING: { label: 'VC: chờ', className: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' },
  GENERATED: { label: 'Đã tạo VC', className: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' },
  SCANNED: { label: 'VC: đã quét', className: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' },
  PROCESSING: { label: 'VC: đang xử lý', className: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' },
  PROCESSED: { label: 'VC: đã xử lý', className: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' },
  DELIVERED: { label: 'VC: đã giao', className: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' },
  WARNING: { label: 'VC: cảnh báo', className: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' },
  FAILED: { label: 'VC: lỗi', className: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' },
  ORDER_FAILED: { label: 'VC: lỗi', className: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' },
  ERROR: { label: 'VC: lỗi', className: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' },
  CANCELLED: { label: 'VC: huỷ', className: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' },
}

function ShipStatusBadge({
  status,
  orderNumber,
}: {
  status?: string | null
  orderNumber?: string | null
}) {
  const key = (status ?? '').toUpperCase()
  if (!key) {
    return (
      <span className='inline-flex rounded-[4px] bg-zinc-100 px-2 py-1 text-[10px] font-semibold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400'>
        Chưa tạo VC
      </span>
    )
  }
  const style = SHIP_STATUS_STYLE[key] ?? {
    label: `VC: ${key}`,
    className: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
  }
  const base = `inline-flex items-center gap-0.5 rounded-[4px] px-2 py-1 text-[10px] font-semibold ${style.className}`
  // Once shipping is created, link to the matching order on the ShipDVX page.
  if (orderNumber) {
    return (
      <Link
        href={`/lemiex/shipdvx-orders?q=${encodeURIComponent(orderNumber)}`}
        onClick={(e) => e.stopPropagation()}
        title='Xem đơn trên ShipDVX'
        className={`${base} transition hover:ring-1 hover:ring-current/40`}
      >
        {style.label}
        <ArrowUpRight className='size-3 opacity-70' />
      </Link>
    )
  }
  return <span className={base}>{style.label}</span>
}

function formatDateTime(
  value: string | null | undefined,
  messages: ReturnType<typeof useI18n>['messages']['orders']
) {
  if (!value) return messages.status.na

  try {
    return new Date(value).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  } catch {
    return value
  }
}

// Ship-out time needs the hour/minute (not just the date) so the daily noon
// batch can be reconciled against the carrier's manifest precisely. Pinned to the
// workshop's timezone so the displayed time matches the noon-cutoff filter logic
// regardless of where the dashboard is opened.
function formatShippedAt(value: string | null | undefined) {
  if (!value) return null

  try {
    return new Date(value).toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      timeZone: 'Asia/Ho_Chi_Minh',
    })
  } catch {
    return value
  }
}

function formatCurrency(value?: number | null) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value || 0)
}

function getVariantSummary(
  order: LemiexOrderRow,
  messages: ReturnType<typeof useI18n>['messages']['orders']
) {
  const variants = order.items
    ?.map((item) => item.variant_id)
    .filter((item): item is string => Boolean(item))

  if (!variants || variants.length === 0) return messages.status.noVariant
  return variants.join(',\n')
}

export function getOrdersTableColumns(
  user: AuthUser | null,
  messages: ReturnType<typeof useI18n>['messages']['orders'],
  fulfillStatusOptions: SelectOption[],
  onOrderUpdated: () => void
): ColumnDef<LemiexOrderRow>[] {
  const role = getUserRoleName(user)
  const showSellerColumn = role === 'Admin' || role === 'Staff'
  const showTicketColumn = role !== 'Staff'
  const isDesigner = role === 'Designer'
  const effectiveFulfillStatusOptions =
    fulfillStatusOptions.length > 0
      ? fulfillStatusOptions
      : FALLBACK_FULFILL_STATUS_OPTIONS

  const columns: ColumnDef<LemiexOrderRow>[] = [
    {
      id: 'select',
      header: () => <SelectAllOrdersCheckbox />,
      meta: { thClassName: 'w-[44px]', tdClassName: 'w-[44px]' },
      cell: ({ row }) => <SelectOrderCheckbox orderId={row.original.id} />,
    },
    {
      accessorKey: 'id',
      header: messages.headers.order,
      meta: { thClassName: 'min-w-[220px]' },
      cell: ({ row }) => {
        const order = row.original
        return (
          <div className='space-y-2'>
            <div className='flex items-center gap-2'>
              <span className='text-[13px] font-semibold'>#{order.id}</span>
              <Badge
                className='rounded-[4px] text-[10px]'
                variant={order.fulfillment_priority === 'priority' ? 'destructive' : 'secondary'}
              >
                {order.fulfillment_priority === 'priority'
                  ? messages.status.priority
                  : messages.status.normal}
              </Badge>
            </div>
            <div className='text-[12px] text-muted-foreground break-all leading-relaxed'>
              {order.ref_id || messages.status.noRefId}
            </div>
            <div className='text-[11px] text-muted-foreground/70 font-mono whitespace-pre-line break-words'>
              {getVariantSummary(order, messages)}
            </div>
            {(showSellerColumn || showTicketColumn) ? (
              <div className='flex flex-wrap items-center gap-1'>
                {showSellerColumn ? (
                  <Badge variant='outline' className='rounded-[4px] text-[10px]'>
                    {order.seller?.username || order.seller?.name || messages.status.na}
                  </Badge>
                ) : null}
                {showTicketColumn ? (
                  order.support_ticket?.id ? (
                    <Badge variant='outline' className='rounded-[4px] text-[10px]'>
                      #{order.support_ticket.id}
                    </Badge>
                  ) : order.has_ticket ? (
                    <Badge className='rounded-[4px] text-[10px]' variant='destructive'>
                      {messages.status.hasTicket}
                    </Badge>
                  ) : null
                ) : null}
              </div>
            ) : null}
          </div>
        )
      },
    },
  ]

  columns.push(
    {
      id: 'status',
      header: messages.headers.fulfillStatus,
      meta: { thClassName: 'min-w-[200px]' },
      cell: ({ row }) => (
        <OrderFulfillStatusCell
          order={row.original}
          user={user}
          options={effectiveFulfillStatusOptions}
          onUpdated={onOrderUpdated}
        />
      ),
    },
    {
      id: 'items',
      header: messages.headers.items,
      meta: { thClassName: 'min-w-[320px]' },
      cell: ({ row }) => (
        <OrderItemsCell order={row.original} user={user} />
      ),
    },
    ...(isDesigner
      ? [
          {
            id: 'designs',
            header: 'Design (đầy đủ)',
            meta: { thClassName: 'min-w-[440px]' },
            cell: ({ row }) => <OrderDesignsCell order={row.original} />,
          } as ColumnDef<LemiexOrderRow>,
        ]
      : []),
    {
      id: 'logistics',
      header: messages.headers.tracking,
      meta: { thClassName: 'min-w-[160px]' },
      cell: ({ row }) => {
        const trackingId = row.original.shipping?.tracking_id
        const shippedAt = formatShippedAt(row.original.timestamps?.shipped_at)
        return (
          <div className='space-y-2'>
            {trackingId ? (
              <a
                href={`https://t.17track.net/en#nums=${trackingId}`}
                target='_blank'
                rel='noreferrer'
                className='inline-flex rounded-[4px] bg-muted px-2 py-1 text-[12px] font-medium text-foreground hover:underline'
              >
                {trackingId}
              </a>
            ) : (
              <span className='text-[12px] text-muted-foreground'>{messages.status.noTracking}</span>
            )}

            <div className='flex flex-wrap items-center gap-1.5'>
              {row.original.shipping?.label_url ? (
                <a
                  href={row.original.shipping.label_url}
                  target='_blank'
                  rel='noreferrer'
                  className='inline-flex rounded-[4px] bg-violet-50 px-2 py-1 text-[10px] font-semibold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300'
                >
                  {messages.status.label}
                </a>
              ) : null}
              {row.original.convert_label ? (
                <a
                  href={row.original.convert_label}
                  target='_blank'
                  rel='noreferrer'
                  className='inline-flex rounded-[4px] bg-fuchsia-50 px-2 py-1 text-[10px] font-semibold text-fuchsia-700 dark:bg-fuchsia-950/40 dark:text-fuchsia-300'
                >
                  {messages.status.convert}
                </a>
              ) : null}
              <ShipStatusBadge
                status={row.original.label_status}
                orderNumber={row.original.ref_id}
              />
            </div>

            <div className='text-[11px] text-muted-foreground'>
              {formatDateTime(
                row.original.timestamps?.created_at || row.original.created_at,
                messages
              )}
            </div>

            {shippedAt ? (
              <div className='text-[11px] font-medium text-emerald-600 dark:text-emerald-400'>
                {messages.sortBy.shipped_at}: {shippedAt}
              </div>
            ) : null}
          </div>
        )
      },
    },
    {
      id: 'cost',
      header: messages.headers.totalCost,
      meta: { thClassName: 'min-w-[150px]' },
      cell: ({ row }) => (
        <div className='space-y-1.5 text-[12px]'>
          <Badge variant='outline' className='rounded-[4px] text-[10px] capitalize'>
            {formatStatusLabel(row.original.payment_status, messages)}
          </Badge>
          <div className='text-emerald-700 dark:text-emerald-400'>
            {messages.headers.printCost}: {formatCurrency(row.original.pricing?.print_cost)}
          </div>
          <div className='text-emerald-700 dark:text-emerald-400'>
            {messages.headers.shipping}: {formatCurrency(row.original.pricing?.shipping_cost)}
          </div>
          <div className='border-t border-border/60 pt-1 text-[13px] font-semibold text-rose-600 dark:text-rose-400'>
            {formatCurrency(row.original.pricing?.total_cost ?? row.original.total_cost)}
          </div>
        </div>
      ),
    },
    {
      id: 'actions',
      header: messages.headers.actions,
      meta: { thClassName: 'min-w-[110px]' },
      cell: ({ row }) => (
        <OrderActionsCell
          order={row.original}
          user={user}
          onOrderUpdated={onOrderUpdated}
        />
      ),
    }
  )

  return columns
}
