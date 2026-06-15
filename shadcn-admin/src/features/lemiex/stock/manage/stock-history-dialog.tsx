'use client'

import { useEffect, useState } from 'react'
import { History, Loader2 } from 'lucide-react'
import {
  fetchStockVariantHistory,
  type StockHistoryRecord,
  type StockVariant,
} from '@/services/stock/api'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'

type StockHistoryDialogMessages = {
  title: string
  currentStock: string
  loading: string
  noRecords: string
  increase: string
  decrease: string
  adjust: string
  import: string
  skuUpdated: string
  styleUpdated: string
  activated: string
  deactivated: string
  bulkUpdate: string
  bulkOperation: string
  operation: string
  showingLast: string
  sku: string
  style: string
  active: string
  empty: string
  variantId: string
}

const badgeToneMap: Record<string, string> = {
  increase: 'bg-emerald-500/10 text-emerald-700 border-emerald-200',
  decrease: 'bg-rose-500/10 text-rose-700 border-rose-200',
  adjust: 'bg-blue-500/10 text-blue-700 border-blue-200',
  import: 'bg-violet-500/10 text-violet-700 border-violet-200',
  update_sku: 'bg-amber-500/10 text-amber-700 border-amber-200',
  update_style: 'bg-pink-500/10 text-pink-700 border-pink-200',
  activate: 'bg-emerald-500/10 text-emerald-700 border-emerald-200',
  deactivate: 'bg-rose-500/10 text-rose-700 border-rose-200',
  bulk_update: 'bg-violet-500/10 text-violet-700 border-violet-200',
}

function formatDate(dateString?: string | null) {
  if (!dateString) return 'N/A'

  try {
    return new Date(dateString).toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return 'N/A'
  }
}

function getActionLabel(
  action: string,
  m: Omit<StockHistoryDialogMessages, 'title' | 'currentStock' | 'loading' | 'noRecords' | 'showingLast' | 'sku' | 'style' | 'active' | 'empty' | 'variantId'>
) {
  const labels: Record<string, string> = {
    increase: m.increase,
    decrease: m.decrease,
    adjust: m.adjust,
    import: m.import,
    update_sku: m.skuUpdated,
    update_style: m.styleUpdated,
    activate: m.activated,
    deactivate: m.deactivated,
    bulk_update: m.bulkUpdate,
  }

  return labels[action] || action
}

export function StockHistoryDialog({
  open,
  variant,
  messages,
  onOpenChange,
}: {
  open: boolean
  variant: StockVariant | null
  messages: StockHistoryDialogMessages
  onOpenChange: (open: boolean) => void
}) {
  const [loading, setLoading] = useState(false)
  const [history, setHistory] = useState<StockHistoryRecord[]>([])

  useEffect(() => {
    let active = true

    async function loadHistory() {
      if (!open || !variant) return

      setLoading(true)
      try {
        const next = await fetchStockVariantHistory(variant.id)
        if (!active) return
        setHistory(next)
      } catch {
        if (!active) return
        setHistory([])
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void loadHistory()

    return () => {
      active = false
    }
  }, [open, variant])

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[90vh] max-w-4xl gap-0 overflow-hidden p-0'>
        <DialogHeader className='border-b px-6 py-5'>
          <DialogTitle className='flex items-center gap-2 text-xl'>
            <History className='size-5' />
            {messages.title}
          </DialogTitle>
          <DialogDescription className='flex flex-wrap items-center gap-x-4 gap-y-2 pt-2 text-sm'>
            <span>
              {messages.variantId}: <strong>{variant?.variant_id || 'N/A'}</strong>
            </span>
            <span>
              {messages.sku}: <strong>{variant?.sku || '-'}</strong>
            </span>
            <span>
              {messages.currentStock}: <strong>{variant?.stock ?? 0}</strong>
            </span>
          </DialogDescription>
        </DialogHeader>

        <ScrollArea className='max-h-[calc(90vh-7rem)]'>
          <div className='space-y-4 p-6'>
            {loading ? (
              <div className='flex min-h-48 items-center justify-center text-sm text-muted-foreground'>
                <Loader2 className='mr-2 size-4 animate-spin' />
                {messages.loading}
              </div>
            ) : history.length === 0 ? (
              <div className='rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground'>
                {messages.noRecords}
              </div>
            ) : (
              history.map((record) => {
                const metadata = record.metadata || {}
                const fieldName = metadata.field?.toLowerCase()
                const isStockChange =
                  record.before_quantity !== null &&
                  record.before_quantity !== undefined &&
                  record.after_quantity !== null &&
                  record.after_quantity !== undefined
                const isFieldChange =
                  metadata.field && metadata.old_value !== undefined

                return (
                  <div
                    key={record.id}
                    className='rounded-2xl border bg-card p-4 shadow-xs'
                  >
                    <div className='flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between'>
                      <Badge
                        variant='outline'
                        className={badgeToneMap[record.action] || 'bg-muted'}
                      >
                        {getActionLabel(record.action, messages)}
                      </Badge>
                      <div className='text-xs text-muted-foreground'>
                        {formatDate(record.created_at)}
                      </div>
                    </div>

                    <div className='mt-4 space-y-3 text-sm'>
                      {isStockChange ? (
                        <div className='flex flex-wrap items-center gap-2'>
                          <span className='rounded-md bg-muted px-2 py-1 font-medium'>
                            {record.before_quantity}
                          </span>
                          <span className='text-muted-foreground'>→</span>
                          <span className='rounded-md bg-primary/10 px-2 py-1 font-semibold text-primary'>
                            {record.after_quantity}
                          </span>
                          <span className='text-muted-foreground'>
                            (
                            {(record.after_quantity || 0) - (record.before_quantity || 0) >
                            0
                              ? '+'
                              : ''}
                            {(record.after_quantity || 0) - (record.before_quantity || 0)})
                          </span>
                        </div>
                      ) : null}

                      {isFieldChange ? (
                        <div className='flex flex-wrap items-center gap-2'>
                          <span className='text-muted-foreground'>
                            {messages[
                              (fieldName || 'style') as keyof Pick<
                                StockHistoryDialogMessages,
                                'sku' | 'style' | 'active'
                              >
                            ] || metadata.field}
                            :
                          </span>
                          <span className='rounded-md bg-muted px-2 py-1'>
                            {metadata.old_value === null || metadata.old_value === ''
                              ? messages.empty
                              : String(metadata.old_value)}
                          </span>
                          <span className='text-muted-foreground'>→</span>
                          <span className='rounded-md bg-primary/10 px-2 py-1 text-primary'>
                            {metadata.new_value === null || metadata.new_value === ''
                              ? messages.empty
                              : String(metadata.new_value)}
                          </span>
                        </div>
                      ) : null}

                      {metadata.bulk_action ? (
                        <Badge variant='secondary'>{messages.bulkOperation}</Badge>
                      ) : null}

                      {metadata.operation ? (
                        <div className='text-muted-foreground'>
                          {messages.operation}: <strong>{metadata.operation}</strong>
                          {metadata.amount_added
                            ? ` (+${metadata.amount_added})`
                            : ''}
                          {metadata.amount_subtracted
                            ? ` (-${metadata.amount_subtracted})`
                            : ''}
                        </div>
                      ) : null}

                      {record.user ? (
                        <div className='text-muted-foreground'>
                          {record.user.username || 'Unknown'} ({record.user.email || '—'})
                        </div>
                      ) : null}

                      {record.reason ? <div>{record.reason}</div> : null}
                    </div>
                  </div>
                )
              })
            )}

            {!loading && history.length > 0 ? (
              <div className='text-xs text-muted-foreground'>
                {messages.showingLast}
              </div>
            ) : null}
          </div>
        </ScrollArea>
      </DialogContent>
    </Dialog>
  )
}
