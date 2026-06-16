'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { useI18n } from '@/context/i18n-provider'
import { getUserRoleName } from '@/services/auth/api'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { type OrderListItem } from '@/services/orders/api'
import { type AuthUser } from '@/stores/auth-store'
import { OrderEditDialog } from '@/features/lemiex/orders/components/order-edit-dialog'
import { OrderTimelineDialog } from '@/features/lemiex/orders/components/order-timeline-dialog'

type OrderActionsCellProps = {
  order: OrderListItem
  user: AuthUser | null
  onOrderUpdated?: () => void
  onBuyLabel?: (orderId: number | string) => void
}

export function OrderActionsCell({
  order,
  user,
  onOrderUpdated,
  onBuyLabel,
}: OrderActionsCellProps) {
  const router = useRouter()
  const [ticketExistsOpen, setTicketExistsOpen] = useState(false)
  const [timelineOpen, setTimelineOpen] = useState(false)
  const [editOpen, setEditOpen] = useState(false)
  const { messages } = useI18n()
  const ordersMessages = messages.orders
  const role = getUserRoleName(user)
  const canEdit =
    role === 'Admin' ||
    role === 'Staff' ||
    (role === 'Seller' &&
      ['new_order', 'on_hold'].includes(order.fulfill_status || ''))
  const canUseSupport =
    role === 'Admin' || role === 'Seller' || role === 'Support'
  // Has label + tracking (TikTok) → "Create shipping"; otherwise → "Buy label"
  const hasLabel =
    Boolean(order.shipping?.tracking_id) &&
    Boolean(order.shipping?.label_url || order.shipping_label)

  const handleSupportClick = () => {
    if (order.has_ticket || order.support_ticket?.id) {
      setTicketExistsOpen(true)
      return
    }

    router.push(`/lemiex/tickets?order_id=${order.id}&action=create`)
  }

  return (
    <>
      <div className='flex flex-col items-start gap-2'>
        {[ordersMessages.actions.view].map((label) => (
          <Button
            key={label}
            type='button'
            size='sm'
            variant='outline'
            className='h-7 min-w-[92px] justify-center rounded-[6px] px-2.5 text-[11px]'
            onClick={() => router.push(`/lemiex/orders/${order.id}`)}
          >
            {label}
          </Button>
        ))}

        <Button
          type='button'
          size='sm'
          variant='outline'
          className='h-7 min-w-[92px] justify-center rounded-[6px] px-2.5 text-[11px]'
          onClick={() => setTimelineOpen(true)}
        >
          {ordersMessages.actions.timeline}
        </Button>

        {canEdit ? (
          <Button
            type='button'
            size='sm'
            variant='outline'
            className='h-7 min-w-[92px] justify-center rounded-[6px] px-2.5 text-[11px]'
            onClick={() => setEditOpen(true)}
          >
            {ordersMessages.actions.edit}
          </Button>
        ) : null}

        {canUseSupport ? (
          <Button
            type='button'
            size='sm'
            variant='outline'
            className='h-7 min-w-[92px] justify-center rounded-[6px] px-2.5 text-[11px]'
            onClick={handleSupportClick}
          >
            {ordersMessages.actions.support}
          </Button>
        ) : null}

        {onBuyLabel ? (
          <Button
            type='button'
            size='sm'
            className='h-7 min-w-[92px] justify-center rounded-[6px] px-2.5 text-[11px]'
            onClick={() => onBuyLabel(order.id)}
          >
            {hasLabel ? 'Mua vận chuyển' : 'Mua label'}
          </Button>
        ) : null}
      </div>

      <Dialog open={ticketExistsOpen} onOpenChange={setTicketExistsOpen}>
        <DialogContent className='rounded-[6px] sm:max-w-md'>
          <DialogHeader>
            <DialogTitle>{ordersMessages.actions.ticketExistsTitle}</DialogTitle>
            <DialogDescription>
              {ordersMessages.actions.ticketExistsDesc}
            </DialogDescription>
          </DialogHeader>

          <DialogFooter className='sm:justify-start'>
            <Button
              type='button'
              className='rounded-[6px]'
              onClick={() => {
                setTicketExistsOpen(false)
                router.push(`/lemiex/tickets?order_id=${order.id}`)
              }}
            >
              {ordersMessages.actions.viewExistingTickets}
            </Button>
            <Button
              type='button'
              variant='outline'
              className='rounded-[6px]'
              onClick={() => {
                setTicketExistsOpen(false)
                router.push(`/lemiex/tickets?order_id=${order.id}&action=create`)
              }}
            >
              {ordersMessages.actions.createNewTicket}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <OrderTimelineDialog
        open={timelineOpen}
        onOpenChange={setTimelineOpen}
        orderId={order.id}
        orderLabel={order.order_stt || order.id}
      />

      <OrderEditDialog
        open={editOpen}
        onOpenChange={setEditOpen}
        orderId={order.id}
        onUpdated={onOrderUpdated}
        user={user}
      />
    </>
  )
}
