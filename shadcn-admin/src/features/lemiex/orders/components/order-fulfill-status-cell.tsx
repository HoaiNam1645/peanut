'use client'

import { useState } from 'react'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useI18n } from '@/context/i18n-provider'
import {
  changeOrderFulfillStatus,
  sellerCancelOrder,
  type SelectOption,
} from '@/services/orders/api'
import { getUserRoleName } from '@/services/auth/api'
import { type LemiexOrderRow } from '@/features/lemiex/orders/types'
import { type AuthUser } from '@/stores/auth-store'

const statusTextColors: Record<string, string> = {
  new_order: 'text-blue-700 dark:text-blue-300',
  confirm: 'text-indigo-700 dark:text-indigo-300',
  pending_stock: 'text-amber-700 dark:text-amber-300',
  in_stock: 'text-emerald-700 dark:text-emerald-300',
  producing: 'text-cyan-700 dark:text-cyan-300',
  qc_pass: 'text-teal-700 dark:text-teal-300',
  packed: 'text-violet-700 dark:text-violet-300',
  shipped: 'text-green-700 dark:text-green-300',
  on_hold: 'text-yellow-700 dark:text-yellow-300',
  return_to_support: 'text-purple-700 dark:text-purple-300',
  cancelled: 'text-zinc-700 dark:text-zinc-300',
  cancelled_refund_shipping: 'text-rose-700 dark:text-rose-300',
  closed: 'text-slate-700 dark:text-slate-300',
}

function formatStatusLabel(
  value: string | null | undefined,
  fulfillStatuses: Record<string, string>,
  unknownLabel: string
) {
  if (!value) return unknownLabel
  return fulfillStatuses[value] || value.replaceAll('_', ' ')
}

function getSellerFulfillStatusOptions(
  options: SelectOption[],
  currentStatus: string | null | undefined
) {
  const allowed =
    currentStatus === 'on_hold'
      ? ['confirm', 'cancelled']
      : ['on_hold', 'cancelled']

  return options.filter((option) => allowed.includes(option.value))
}

export function OrderFulfillStatusCell({
  order,
  user,
  options,
  onUpdated,
}: {
  order: LemiexOrderRow
  user: AuthUser | null
  options: SelectOption[]
  onUpdated: () => void
}) {
  const { messages } = useI18n()
  const ordersMessages = messages.orders
  const role = getUserRoleName(user)
  const [pending, setPending] = useState(false)

  const canAdminEdit = role === 'Admin' || role === 'Staff' || role === 'Designer'
  const canSellerEdit =
    role === 'Seller' &&
    (order.fulfill_status === 'new_order' ||
      order.fulfill_status === 'on_hold')
  const canEdit = canAdminEdit || canSellerEdit
  const sellerOptions = getSellerFulfillStatusOptions(
    options,
    order.fulfill_status
  )
  const availableOptions = canSellerEdit
    ? [
        ...(order.fulfill_status &&
        !sellerOptions.some((option) => option.value === order.fulfill_status)
          ? [
              {
                value: order.fulfill_status,
                label: order.fulfill_status,
              },
            ]
          : []),
        ...sellerOptions,
      ]
    : options
  const showSellerCancel =
    role === 'Seller' &&
    order.fulfill_status === 'new_order' &&
    order.payment_status !== 'paid'

  async function handleStatusChange(nextStatus: string) {
    if (!nextStatus || nextStatus === order.fulfill_status) return

    setPending(true)
    try {
      const response = await changeOrderFulfillStatus(order.id, nextStatus)
      toast.success(
        typeof response.message === 'string'
          ? response.message
          : ordersMessages.refresh
      )
      onUpdated()
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : ordersMessages.loadErrorTitle
      )
    } finally {
      setPending(false)
    }
  }

  async function handleSellerCancel() {
    const confirmed = window.confirm(
      `Are you sure you want to cancel order #${order.id}?`
    )

    if (!confirmed) return

    setPending(true)
    try {
      const response = await sellerCancelOrder(order.id)
      toast.success(
        typeof response.message === 'string'
          ? response.message
          : ordersMessages.editForm.cancel
      )
      onUpdated()
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : ordersMessages.loadErrorTitle
      )
    } finally {
      setPending(false)
    }
  }

  if (!canEdit) {
    return (
      <div className='flex w-full flex-col items-start justify-start gap-2'>
        <Badge
          className={`rounded-[6px] border border-border/60 bg-transparent ${statusTextColors[order.fulfill_status || ''] || 'text-foreground'}`}
          variant='outline'
        >
          {formatStatusLabel(
            order.fulfill_status,
            ordersMessages.fulfillStatuses as Record<string, string>,
            ordersMessages.status.unknown
          )}
        </Badge>

        {showSellerCancel ? (
          <Button
            type='button'
            size='sm'
            variant='destructive'
            className='h-7 rounded-[6px] px-2 text-[11px]'
            onClick={() => void handleSellerCancel()}
            disabled={pending}
          >
            {ordersMessages.editForm.cancel}
          </Button>
        ) : null}
      </div>
    )
  }

  return (
    <div
      className='flex w-full flex-col items-start justify-start gap-2'
      onClick={(event) => event.stopPropagation()}
    >
      <Select
        value={order.fulfill_status || ''}
        onValueChange={(value) => void handleStatusChange(value)}
        disabled={pending}
      >
        <SelectTrigger
          className={`h-8 min-w-[148px] rounded-[6px] text-[12px] ${statusTextColors[order.fulfill_status || ''] || ''}`}
        >
          <SelectValue
            placeholder={formatStatusLabel(
              order.fulfill_status,
              ordersMessages.fulfillStatuses as Record<string, string>,
              ordersMessages.status.unknown
            )}
          />
        </SelectTrigger>
        <SelectContent>
          {availableOptions.map((option) => (
            <SelectItem
              key={option.value}
              value={option.value}
              className={`text-[12px] ${statusTextColors[option.value] || ''}`}
            >
              {formatStatusLabel(
                option.value,
                ordersMessages.fulfillStatuses as Record<string, string>,
                ordersMessages.status.unknown
              )}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {showSellerCancel ? (
        <Button
          type='button'
          size='sm'
          variant='destructive'
          className='h-7 rounded-[6px] px-2 text-[11px]'
          onClick={() => void handleSellerCancel()}
          disabled={pending}
        >
          {ordersMessages.editForm.cancel}
        </Button>
      ) : null}
    </div>
  )
}
