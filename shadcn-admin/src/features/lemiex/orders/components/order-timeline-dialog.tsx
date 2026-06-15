'use client'

import { useEffect, useState } from 'react'
import { format } from 'date-fns'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useI18n } from '@/context/i18n-provider'
import {
  fetchOrderTimeline,
  type OrderTimelineEvent,
} from '@/services/orders/api'

type OrderTimelineDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  orderId: number | string
  orderLabel?: string | number | null
}

export function OrderTimelineDialog({
  open,
  onOpenChange,
  orderId,
  orderLabel,
}: OrderTimelineDialogProps) {
  const { messages } = useI18n()
  const timelineMessages = messages.orders.timelineModal
  const orderMessages = messages.orders
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [timeline, setTimeline] = useState<OrderTimelineEvent[]>([])

  useEffect(() => {
    if (!open) return

    let active = true

    const run = async () => {
      setLoading(true)
      setError('')

      try {
        const response = await fetchOrderTimeline(orderId)
        if (!active) return
        setTimeline(response)
      } catch (timelineError) {
        if (!active) return
        setError(
          timelineError instanceof Error
            ? timelineError.message
            : timelineMessages.loadError
        )
      } finally {
        if (active) setLoading(false)
      }
    }

    void run()

    return () => {
      active = false
    }
  }, [open, orderId, timelineMessages.loadError])

  const formatTimelineDate = (value?: string | null) => {
    if (!value) return orderMessages.status.na

    try {
      return format(new Date(value), 'MMM dd, yyyy HH:mm:ss')
    } catch {
      return value
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        onOpenChange(nextOpen)
        if (!nextOpen) {
          setError('')
          setTimeline([])
        }
      }}
    >
      <DialogContent className='rounded-[6px] sm:max-w-4xl'>
        <DialogHeader>
          <DialogTitle>{timelineMessages.title}</DialogTitle>
          <DialogDescription>
            {timelineMessages.orderPrefix} #{orderLabel || orderId}
          </DialogDescription>
        </DialogHeader>

        <div className='max-h-[65vh] overflow-auto rounded-[6px] border border-border/70'>
          {loading ? (
            <div className='flex min-h-40 items-center justify-center px-4 py-8 text-[13px] text-muted-foreground'>
              {timelineMessages.loading}
            </div>
          ) : error ? (
            <div className='flex min-h-40 items-center justify-center px-4 py-8 text-[13px] text-destructive'>
              {error}
            </div>
          ) : timeline.length === 0 ? (
            <div className='flex min-h-40 items-center justify-center px-4 py-8 text-[13px] text-muted-foreground'>
              {timelineMessages.empty}
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{timelineMessages.columns.action}</TableHead>
                  <TableHead>{timelineMessages.columns.description}</TableHead>
                  <TableHead>{timelineMessages.columns.createdAt}</TableHead>
                  <TableHead>{timelineMessages.columns.updatedAt}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {timeline.map((event, index) => (
                  <TableRow key={String(event.id ?? `${orderId}-${index}`)}>
                    <TableCell className='text-[13px] font-medium'>
                      {event.action || orderMessages.status.na}
                    </TableCell>
                    <TableCell className='max-w-[360px] whitespace-normal text-[13px] text-muted-foreground'>
                      {event.note || orderMessages.status.na}
                    </TableCell>
                    <TableCell className='text-[13px] text-muted-foreground'>
                      {formatTimelineDate(event.created_at)}
                    </TableCell>
                    <TableCell className='text-[13px] text-muted-foreground'>
                      {formatTimelineDate(event.updated_at)}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </div>

        <DialogFooter className='sm:justify-end'>
          <Button
            type='button'
            variant='outline'
            className='rounded-[6px]'
            onClick={() => onOpenChange(false)}
          >
            {timelineMessages.close}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
