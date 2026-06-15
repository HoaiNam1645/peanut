'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ColumnDef } from '@tanstack/react-table'
import { Check, X } from 'lucide-react'
import { toast } from 'sonner'
import { useI18n } from '@/context/i18n-provider'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Textarea } from '@/components/ui/textarea'
import {
  approvePendingFund,
  fetchPendingFundRequests,
  rejectPendingFund,
  type PendingFundRequest,
  type WalletPagination,
} from '@/services/wallets/api'

const fallbackMessages = {
  title: 'Pending Fund Requests',
  subtitle: 'Review and approve fund deposit requests from sellers',
  showing: 'Showing {count} pending request(s)',
  loading: 'Loading...',
  noRequests: 'No pending requests',
  allCaught: 'All fund requests have been processed.',
  fetchError: 'Failed to load pending requests',
  confirmApprove: 'Are you sure you want to approve this fund request?',
  approveSuccess: 'Fund request approved successfully!',
  approveFailed: 'Failed to approve request',
  rejectSuccess: 'Fund request rejected',
  rejectFailed: 'Failed to reject request',
  approve: 'Approve',
  reject: 'Reject',
  columns: {
    id: 'ID',
    seller: 'Seller',
    type: 'Type',
    amount: 'Amount',
    transactionId: 'Transaction ID',
    note: 'Note',
    date: 'Date',
    actions: 'Actions',
  },
  rejectModal: {
    title: 'Reject Fund Request',
    subtitle: 'Please provide a reason for rejection (optional)',
    placeholder: 'Enter rejection reason...',
    cancel: 'Cancel',
    confirm: 'Confirm Reject',
  },
  type: {
    deposit: 'Deposit',
    refund: 'Refund',
  },
  na: 'N/A',
}

function formatCurrency(amount?: number | null) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(Number(amount || 0))
}

function formatDate(dateString?: string | null, empty = 'N/A') {
  if (!dateString) return empty
  try {
    return new Date(dateString).toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return empty
  }
}

function typeClass(type?: string | null) {
  switch (String(type || '').toLowerCase()) {
    case 'deposit':
      return 'border-blue-200 bg-blue-500/10 text-blue-700'
    case 'refund':
      return 'border-emerald-200 bg-emerald-500/10 text-emerald-700'
    default:
      return 'border-slate-200 bg-slate-500/10 text-slate-700'
  }
}

export function LemiexPendingFundPage() {
  const { messages } = useI18n()
  const m = messages.pendingFundPage ?? fallbackMessages

  const [requests, setRequests] = useState<PendingFundRequest[]>([])
  const [loading, setLoading] = useState(true)
  const [processing, setProcessing] = useState<number | string | null>(null)
  const [pagination, setPagination] = useState<WalletPagination>({
    current_page: 1,
    per_page: 20,
    total: 0,
    last_page: 1,
  })
  const [showRejectModal, setShowRejectModal] = useState(false)
  const [rejectReason, setRejectReason] = useState('')
  const [rejectingId, setRejectingId] = useState<number | string | null>(null)

  useEffect(() => {
    let active = true
    async function loadRequests() {
      try {
        setLoading(true)
        const response = await fetchPendingFundRequests({
          page: pagination.current_page,
          per_page: pagination.per_page,
        })
        if (!active) return
        setRequests(response.requests)
        setPagination(response.pagination)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : m.fetchError)
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }
    void loadRequests()
    return () => {
      active = false
    }
  }, [m.fetchError, pagination.current_page, pagination.per_page])

  const refreshCurrentPage = useCallback(async () => {
    const response = await fetchPendingFundRequests({
      page: pagination.current_page,
      per_page: pagination.per_page,
    })
    setRequests(response.requests)
    setPagination(response.pagination)
  }, [pagination.current_page, pagination.per_page])

  const handleApprove = useCallback(async (transactionId: number | string) => {
    if (processing) return
    if (!window.confirm(m.confirmApprove)) return
    try {
      setProcessing(transactionId)
      await approvePendingFund(transactionId)
      toast.success(m.approveSuccess)
      await refreshCurrentPage()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.approveFailed)
    } finally {
      setProcessing(null)
    }
  }, [
    m.approveFailed,
    m.approveSuccess,
    m.confirmApprove,
    processing,
    refreshCurrentPage,
  ])

  async function handleReject() {
    if (processing || !rejectingId) return
    try {
      setProcessing(rejectingId)
      setShowRejectModal(false)
      await rejectPendingFund(rejectingId, rejectReason)
      toast.success(m.rejectSuccess)
      await refreshCurrentPage()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.rejectFailed)
    } finally {
      setProcessing(null)
      setRejectingId(null)
      setRejectReason('')
    }
  }

  const columns = useMemo<ColumnDef<PendingFundRequest>[]>(
    () => [
      {
        id: 'id',
        header: m.columns.id,
        cell: ({ row }) => <strong>#{row.original.id}</strong>,
        meta: { thClassName: 'min-w-[80px] text-center', tdClassName: 'text-center' },
      },
      {
        id: 'seller',
        header: m.columns.seller,
        cell: ({ row }) => (
          <div className='space-y-1'>
            <div className='text-sm font-medium'>{row.original.seller?.username || m.na}</div>
            <div className='text-xs text-muted-foreground'>
              {row.original.seller?.email || ''}
            </div>
          </div>
        ),
        meta: { thClassName: 'min-w-[220px]' },
      },
      {
        id: 'type',
        header: m.columns.type,
        cell: ({ row }) => (
          <Badge variant='outline' className={typeClass(row.original.type)}>
            {m.type[
              String(row.original.type || '').toLowerCase() as keyof typeof m.type
            ] || String(row.original.type || m.na)}
          </Badge>
        ),
        meta: { thClassName: 'min-w-[120px] text-center', tdClassName: 'text-center' },
      },
      {
        id: 'amount',
        header: m.columns.amount,
        cell: ({ row }) => <strong className='text-emerald-600'>{formatCurrency(row.original.amount)}</strong>,
        meta: { thClassName: 'min-w-[120px] text-right', tdClassName: 'text-right' },
      },
      {
        id: 'transaction_id',
        header: m.columns.transactionId,
        cell: ({ row }) => (
          <span className='font-mono text-xs text-muted-foreground'>
            {row.original.transaction_id || '-'}
          </span>
        ),
        meta: { thClassName: 'min-w-[180px]' },
      },
      {
        id: 'note',
        header: m.columns.note,
        cell: ({ row }) => {
          const note = row.original.note || ''
          return (
            <span className='text-xs text-muted-foreground' title={note}>
              {note ? (note.length > 30 ? `${note.slice(0, 30)}...` : note) : '-'}
            </span>
          )
        },
        meta: { thClassName: 'min-w-[180px]' },
      },
      {
        id: 'created_at',
        header: m.columns.date,
        cell: ({ row }) => formatDate(row.original.created_at, m.na),
        meta: { thClassName: 'min-w-[170px]' },
      },
      {
        id: 'actions',
        header: m.columns.actions,
        cell: ({ row }) => (
          <div className='flex justify-center gap-2'>
            <Button
              className='h-9 rounded-[6px]'
              size='sm'
              onClick={() => void handleApprove(row.original.id)}
              disabled={processing === row.original.id}
            >
              <Check className='size-4' />
              {m.approve}
            </Button>
            <Button
              variant='outline'
              className='h-9 rounded-[6px] border-rose-200 text-rose-700 hover:bg-rose-50 hover:text-rose-800'
              size='sm'
              onClick={() => {
                setRejectingId(row.original.id)
                setRejectReason('')
                setShowRejectModal(true)
              }}
              disabled={processing === row.original.id}
            >
              <X className='size-4' />
              {m.reject}
            </Button>
          </div>
        ),
        meta: { thClassName: 'min-w-[220px] text-center', tdClassName: 'text-center' },
      },
    ],
    [handleApprove, m, processing]
  )

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

      <Main fluid className='flex flex-1 flex-col gap-4 px-4 py-6 sm:px-5 lg:px-6 xl:px-7'>
        <div className='space-y-6'>
          <div className='space-y-1'>
            <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
            <p className='text-sm text-muted-foreground'>
              {pagination.total > 0
                ? m.showing.replace('{count}', String(pagination.total))
                : m.subtitle}
            </p>
          </div>

          {loading ? null : requests.length === 0 ? (
            <Card className='rounded-[6px] border-dashed'>
              <CardContent className='flex min-h-[220px] flex-col items-center justify-center gap-2 p-6 text-center'>
                <h2 className='text-lg font-semibold'>{m.noRequests}</h2>
                <p className='text-sm text-muted-foreground'>{m.allCaught}</p>
              </CardContent>
            </Card>
          ) : (
            <LemiexDataTable
              columns={columns}
              data={requests}
              page={pagination.current_page}
              pageSize={pagination.per_page}
              total={pagination.total}
              loading={loading}
              loadingText={m.loading}
              emptyText={m.noRequests}
              getRowId={(row) => String(row.id)}
              onPageChange={(page) =>
                setPagination((prev) => ({ ...prev, current_page: page }))
              }
              onPageSizeChange={(pageSize) =>
                setPagination((prev) => ({
                  ...prev,
                  current_page: 1,
                  per_page: pageSize,
                }))
              }
            />
          )}
        </div>
      </Main>

      <Dialog open={showRejectModal} onOpenChange={setShowRejectModal}>
        <DialogContent className='rounded-[6px] sm:max-w-[480px]'>
          <DialogHeader>
            <DialogTitle>{m.rejectModal.title}</DialogTitle>
          </DialogHeader>
          <div className='space-y-3'>
            <p className='text-sm text-muted-foreground'>{m.rejectModal.subtitle}</p>
            <Textarea
              rows={4}
              value={rejectReason}
              onChange={(event) => setRejectReason(event.target.value)}
              placeholder={m.rejectModal.placeholder}
              className='rounded-[6px]'
            />
          </div>
          <DialogFooter>
            <Button
              variant='outline'
              className='rounded-[6px]'
              onClick={() => setShowRejectModal(false)}
            >
              {m.rejectModal.cancel}
            </Button>
            <Button className='rounded-[6px]' onClick={() => void handleReject()}>
              {m.rejectModal.confirm}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
