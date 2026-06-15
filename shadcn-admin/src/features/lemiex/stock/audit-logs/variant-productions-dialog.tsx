'use client'

import { FileText, Package2 } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { VariantProductionRecord } from '@/services/stock-audit-log/api'

type VariantProductionsDialogMessages = {
  title: string
  variantId: string
  productionId: string
  orderId: string
  orderRef: string
  quantity: string
  units: string
  noProductions: string
  close: string
  status: {
    pending: string
    pickup: string
    mapped: string
    completed: string
    cancelled: string
    unknown: string
  }
}

const statusToneMap: Record<string, string> = {
  pending: 'border-amber-300 bg-amber-500/5 text-amber-700',
  pickup: 'border-blue-300 bg-blue-500/5 text-blue-700',
  mapped: 'border-violet-300 bg-violet-500/5 text-violet-700',
  completed: 'border-emerald-300 bg-emerald-500/5 text-emerald-700',
  cancelled: 'border-rose-300 bg-rose-500/5 text-rose-700',
}

function getStatusLabel(
  status: string | null | undefined,
  messages: VariantProductionsDialogMessages['status']
) {
  if (!status) return messages.unknown
  return messages[status as keyof VariantProductionsDialogMessages['status']] || status
}

export function VariantProductionsDialog({
  open,
  variantId,
  productions,
  messages,
  onOpenChange,
}: {
  open: boolean
  variantId: string | null
  productions: VariantProductionRecord[]
  messages: VariantProductionsDialogMessages
  onOpenChange: (open: boolean) => void
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[90vh] max-w-[66rem] gap-0 overflow-hidden rounded-[8px] p-0'>
        <DialogHeader className='border-b px-7 py-6'>
          <DialogTitle className='text-xl font-semibold'>
            {messages.title}
          </DialogTitle>
          <DialogDescription className='pt-2 text-sm'>
            {messages.variantId}: <strong>{variantId || 'N/A'}</strong>
          </DialogDescription>
        </DialogHeader>

        <div className='max-h-[calc(90vh-8rem)] overflow-y-auto px-7 py-6'>
          {productions.length === 0 ? (
            <div className='rounded-[8px] border border-dashed p-10 text-center'>
              <Package2 className='mx-auto mb-3 size-8 text-muted-foreground' />
              <p className='text-sm text-muted-foreground'>
                {messages.noProductions}
              </p>
            </div>
          ) : (
            <div className='space-y-5'>
              {productions.map((production, index) => {
                const status = production.status || 'unknown'

                return (
                  <div
                    key={`${production.production_id || 'production'}-${index}`}
                    className='rounded-[18px] border border-violet-300/80 bg-card p-6'
                  >
                    <div className='flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between'>
                      <div className='flex items-center gap-3'>
                        <div className='rounded-full bg-violet-500/10 p-2 text-violet-600'>
                          <FileText className='size-5' />
                        </div>
                        <div className='text-[2rem] font-semibold leading-none tracking-tight'>
                          {messages.productionId}
                          {production.production_id || 'N/A'}
                        </div>
                      </div>

                      <Badge
                        variant='outline'
                        className={`rounded-full px-5 py-2 text-sm font-semibold uppercase tracking-wide ${statusToneMap[status] || 'bg-muted text-muted-foreground'}`}
                      >
                        {getStatusLabel(status, messages.status)}
                      </Badge>
                    </div>

                    <div className='mt-5 space-y-4'>
                      <div className='flex items-center justify-between rounded-[18px] bg-muted/35 px-6 py-5'>
                        <span className='text-[15px] font-medium text-muted-foreground'>
                          {messages.orderId}:
                        </span>
                        <span className='text-[2rem] font-semibold leading-none tracking-tight'>
                          #{production.order_id || 'N/A'}
                        </span>
                      </div>

                      <div className='flex items-center justify-between rounded-[18px] bg-muted/35 px-6 py-5'>
                        <span className='text-[15px] font-medium text-muted-foreground'>
                          {messages.orderRef}:
                        </span>
                        <span className='text-[2rem] font-semibold leading-none tracking-tight'>
                          {production.order_ref || 'N/A'}
                        </span>
                      </div>

                      <div className='flex items-center justify-between rounded-[18px] bg-muted/35 px-6 py-5'>
                        <span className='text-[15px] font-medium text-muted-foreground'>
                          {messages.quantity}:
                        </span>
                        <span className='rounded-[14px] bg-violet-500/10 px-5 py-3 text-[1.75rem] font-semibold leading-none text-violet-600'>
                          {Number(production.quantity || 0).toFixed(2)} {messages.units}
                        </span>
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>

        <div className='border-t px-7 py-4'>
          <div className='flex justify-end'>
            <Button
              type='button'
              variant='outline'
              className='rounded-[6px]'
              onClick={() => onOpenChange(false)}
            >
              {messages.close}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
