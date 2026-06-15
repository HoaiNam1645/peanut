'use client'

import { useEffect, useMemo, useState } from 'react'
import type { ColumnDef } from '@tanstack/react-table'
import { Download } from 'lucide-react'
import { toast } from 'sonner'
import { useI18n } from '@/context/i18n-provider'
import { useAuthStore } from '@/stores/auth-store'
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
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  exportWalletTransactions,
  fetchTransactionSellers,
  fetchWalletTransactions,
  type WalletPagination,
  type WalletSeller,
  type WalletTransaction,
  type WalletTransactionFilters,
} from '@/services/wallets/api'

const ALL_VALUE = '__all__'

const fallbackMessages = {
  title: 'Wallet Transactions',
  subtitle: 'Transaction history',
  totalTransactions: 'total transactions',
  exportAll: 'Export All',
  exportPayments: 'Export Payments',
  exportDeposits: 'Export Deposits',
  exportRefunds: 'Export Refunds',
  tabs: {
    all: 'All Transactions',
    payments: 'Payments (Debit)',
    deposits: 'Deposits (Credit)',
    refunds: 'Refunds',
  },
  filters: {
    allSellers: 'All Sellers',
    fromDate: 'From Date',
    toDate: 'To Date',
    search: 'Search...',
  },
  columns: {
    id: 'ID',
    transactionId: 'Transaction ID',
    seller: 'Seller',
    orderId: 'Order ID',
    store: 'Store',
    type: 'Type',
    amount: 'Amount',
    balance: 'Balance',
    note: 'Note',
    status: 'Status',
    date: 'Date',
  },
  status: {
    completed: 'Completed',
    pending: 'Pending',
    failed: 'Failed',
  },
  type: {
    add_fund: 'Add Fund',
    order_payment: 'Order Payment',
    refund: 'Refund',
  },
  summary: {
    total: 'Total',
    page: 'This page',
  },
  loading: 'Loading transactions...',
  noTransactionsTitle: 'No transactions found',
  noTransactionsDescriptionFiltered: 'Try adjusting your filters',
  noTransactionsDescriptionEmpty: 'No transactions available',
  loadFailed: 'Failed to load transactions',
  loadSellersFailed: 'Failed to load sellers',
  exporting: 'Exporting transactions...',
  exportSuccess: 'Transactions exported successfully!',
  exportFailed: 'Failed to export transactions',
  na: 'N/A',
  none: 'No messages',
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

function statusClass(status?: string | null) {
  switch (String(status || '').toLowerCase()) {
    case 'completed':
      return 'border-emerald-200 bg-emerald-500/10 text-emerald-700'
    case 'failed':
      return 'border-rose-200 bg-rose-500/10 text-rose-700'
    default:
      return 'border-amber-200 bg-amber-500/10 text-amber-700'
  }
}

function typeClass(type?: string | null) {
  switch (type) {
    case 'add_fund':
      return 'border-blue-200 bg-blue-500/10 text-blue-700'
    case 'order_payment':
      return 'border-fuchsia-200 bg-fuchsia-500/10 text-fuchsia-700'
    case 'refund':
      return 'border-emerald-200 bg-emerald-500/10 text-emerald-700'
    default:
      return 'border-slate-200 bg-slate-500/10 text-slate-700'
  }
}

export function LemiexWalletTransactionsPage() {
  const { messages } = useI18n()
  const currentUser = useAuthStore((state) => state.auth.user)
  const roleName =
    typeof currentUser?.role === 'string'
      ? currentUser.role
      : currentUser?.role && typeof currentUser.role === 'object'
        ? String(currentUser.role.name || '')
        : String(currentUser?.role_name || '')
  const isAdminFinance = roleName === 'Admin' || roleName === 'Finance'
  const m = messages.walletTransactionsPage ?? fallbackMessages

  const [transactions, setTransactions] = useState<WalletTransaction[]>([])
  const [sellers, setSellers] = useState<WalletSeller[]>([])
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState<WalletTransactionFilters>({
    seller_id: '',
    date_from: '',
    date_to: '',
    type: '',
    status: '',
    search: '',
  })
  const [pagination, setPagination] = useState<WalletPagination>({
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: 0,
  })
  const [summary, setSummary] = useState({
    total_amount: 0,
    page_amount: 0,
  })

  useEffect(() => {
    let active = true
    async function loadSellers() {
      if (!isAdminFinance) return
      try {
        const nextSellers = await fetchTransactionSellers()
        if (!active) return
        setSellers(nextSellers)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : m.loadSellersFailed)
      }
    }
    void loadSellers()
    return () => {
      active = false
    }
  }, [isAdminFinance, m.loadSellersFailed])

  useEffect(() => {
    let active = true
    async function loadTransactions() {
      try {
        setLoading(true)
        const response = await fetchWalletTransactions({
          page: pagination.current_page,
          per_page: pagination.per_page,
          ...filters,
        })
        if (!active) return
        setTransactions(response.transactions)
        setPagination(response.pagination)
        setSummary(response.summary)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : m.loadFailed)
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }
    void loadTransactions()
    return () => {
      active = false
    }
  }, [filters, m.loadFailed, pagination.current_page, pagination.per_page])

  const exportLabel = useMemo(() => {
    if (filters.type === 'payment') return m.exportPayments
    if (filters.type === 'deposit') return m.exportDeposits
    if (filters.type === 'refund') return m.exportRefunds
    return m.exportAll
  }, [filters.type, m.exportAll, m.exportDeposits, m.exportPayments, m.exportRefunds])

  async function handleExport() {
    try {
      toast.info(m.exporting)
      const response = await exportWalletTransactions(filters)
      const blob = new Blob([response.csv], { type: 'text/csv' })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = response.filename
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
      toast.success(m.exportSuccess)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.exportFailed)
    }
  }

  const columns = useMemo<ColumnDef<WalletTransaction>[]>(
    () => [
      {
        id: 'id',
        header: m.columns.id,
        cell: ({ row }) => <strong>#{row.original.id}</strong>,
        meta: { thClassName: 'min-w-[80px] text-center', tdClassName: 'text-center' },
      },
      {
        id: 'transaction_id',
        header: m.columns.transactionId,
        cell: ({ row }) => (
          <span className='font-mono text-xs'>
            {row.original.transaction_id || m.na}
          </span>
        ),
        meta: { thClassName: 'min-w-[180px]' },
      },
      ...(isAdminFinance
        ? [
            {
              id: 'seller',
              header: m.columns.seller,
              cell: ({ row }) => row.original.seller?.username || m.na,
              meta: { thClassName: 'min-w-[140px]' },
            } satisfies ColumnDef<WalletTransaction>,
          ]
        : []),
      {
        id: 'order',
        header: m.columns.orderId,
        cell: ({ row }) => row.original.order?.order_stt || '-',
        meta: { thClassName: 'min-w-[120px]' },
      },
      {
        id: 'store',
        header: m.columns.store,
        cell: ({ row }) => row.original.order?.store?.name || '-',
        meta: { thClassName: 'min-w-[130px]' },
      },
      {
        id: 'type',
        header: m.columns.type,
        cell: ({ row }) => (
          <Badge variant='outline' className={typeClass(row.original.type)}>
            {m.type[row.original.type as keyof typeof m.type] ||
              String(row.original.type || m.na)}
          </Badge>
        ),
        meta: { thClassName: 'min-w-[130px] text-center', tdClassName: 'text-center' },
      },
      {
        id: 'amount',
        header: m.columns.amount,
        cell: ({ row }) => (
          <strong className={Number(row.original.amount || 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'}>
            {formatCurrency(row.original.amount)}
          </strong>
        ),
        meta: { thClassName: 'min-w-[120px] text-right', tdClassName: 'text-right' },
      },
      {
        id: 'balance',
        header: m.columns.balance,
        cell: ({ row }) => (
          <strong className='text-violet-700'>
            {formatCurrency(row.original.remaining_balance)}
          </strong>
        ),
        meta: { thClassName: 'min-w-[130px] text-right', tdClassName: 'text-right' },
      },
      {
        id: 'note',
        header: m.columns.note,
        cell: ({ row }) => {
          const note = row.original.note || ''
          return (
            <span className='text-xs text-muted-foreground' title={note}>
              {note ? (note.length > 40 ? `${note.slice(0, 40)}...` : note) : '-'}
            </span>
          )
        },
        meta: { thClassName: 'min-w-[220px]' },
      },
      {
        id: 'status',
        header: m.columns.status,
        cell: ({ row }) => (
          <Badge variant='outline' className={statusClass(row.original.status)}>
            {m.status[
              String(row.original.status || 'pending').toLowerCase() as keyof typeof m.status
            ] || String(row.original.status || m.na)}
          </Badge>
        ),
        meta: { thClassName: 'min-w-[120px] text-center', tdClassName: 'text-center' },
      },
      {
        id: 'date',
        header: m.columns.date,
        cell: ({ row }) => formatDate(row.original.created_at, m.na),
        meta: { thClassName: 'min-w-[170px]' },
      },
    ],
    [isAdminFinance, m]
  )

  const hasFilters = Boolean(
    filters.seller_id ||
      filters.date_from ||
      filters.date_to ||
      filters.type ||
      filters.status ||
      filters.search
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
          <div className='flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between'>
            <div className='space-y-1'>
              <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
              <p className='text-sm text-muted-foreground'>
                {pagination.total > 0 ? `${pagination.total} ${m.totalTransactions}` : m.subtitle}
              </p>
            </div>
            <Button className='h-10 rounded-[6px]' onClick={handleExport}>
              <Download className='size-4' />
              {exportLabel}
            </Button>
          </div>

          <div className='flex flex-wrap gap-2'>
            {[
              { key: '', label: m.tabs.all },
              { key: 'payment', label: m.tabs.payments },
              { key: 'deposit', label: m.tabs.deposits },
              { key: 'refund', label: m.tabs.refunds },
            ].map((tab) => (
              <Button
                key={tab.key || 'all'}
                variant={filters.type === tab.key ? 'default' : 'outline'}
                className='h-10 rounded-[6px]'
                onClick={() => {
                  setFilters((prev) => ({ ...prev, type: tab.key }))
                  setPagination((prev) => ({ ...prev, current_page: 1 }))
                }}
              >
                {tab.label}
              </Button>
            ))}
          </div>

          <Card className='rounded-[6px]'>
            <CardContent
              className={`grid gap-3 p-4 ${
                isAdminFinance ? 'lg:grid-cols-[220px_repeat(3,minmax(0,1fr))]' : 'lg:grid-cols-3'
              }`}
            >
              {isAdminFinance ? (
                <Select
                  value={filters.seller_id || ALL_VALUE}
                  onValueChange={(value) => {
                    setFilters((prev) => ({
                      ...prev,
                      seller_id: value === ALL_VALUE ? '' : value,
                    }))
                    setPagination((prev) => ({ ...prev, current_page: 1 }))
                  }}
                >
                  <SelectTrigger className='h-9 w-full rounded-[6px]'>
                    <SelectValue placeholder={m.filters.allSellers} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL_VALUE}>{m.filters.allSellers}</SelectItem>
                    {sellers.map((seller) => (
                      <SelectItem key={String(seller.id)} value={String(seller.id)}>
                        {seller.username || m.na}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              ) : null}

              <Input
                type='date'
                value={filters.date_from || ''}
                onChange={(event) => {
                  setFilters((prev) => ({ ...prev, date_from: event.target.value }))
                  setPagination((prev) => ({ ...prev, current_page: 1 }))
                }}
                className='h-9 rounded-[6px]'
                placeholder={m.filters.fromDate}
              />

              <Input
                type='date'
                value={filters.date_to || ''}
                onChange={(event) => {
                  setFilters((prev) => ({ ...prev, date_to: event.target.value }))
                  setPagination((prev) => ({ ...prev, current_page: 1 }))
                }}
                className='h-9 rounded-[6px]'
                placeholder={m.filters.toDate}
              />

              <Input
                value={filters.search || ''}
                onChange={(event) => {
                  setFilters((prev) => ({ ...prev, search: event.target.value }))
                  setPagination((prev) => ({ ...prev, current_page: 1 }))
                }}
                className='h-9 rounded-[6px]'
                placeholder={m.filters.search}
              />
            </CardContent>
          </Card>

          {loading ? null : transactions.length === 0 ? (
            <Card className='rounded-[6px] border-dashed'>
              <CardContent className='flex min-h-[220px] flex-col items-center justify-center gap-2 p-6 text-center'>
                <h2 className='text-lg font-semibold'>{m.noTransactionsTitle}</h2>
                <p className='text-sm text-muted-foreground'>
                  {hasFilters
                    ? m.noTransactionsDescriptionFiltered
                    : m.noTransactionsDescriptionEmpty}
                </p>
              </CardContent>
            </Card>
          ) : (
            <>
              <LemiexDataTable
                columns={columns}
                data={transactions}
                page={pagination.current_page}
                pageSize={pagination.per_page}
                total={pagination.total}
                loading={loading}
                loadingText={m.loading}
                emptyText={m.noTransactionsTitle}
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

              <Card className='rounded-[6px]'>
                <CardContent className='flex flex-col gap-4 p-4 text-sm sm:flex-row sm:items-center'>
                  <div className='flex items-center gap-2'>
                    <span className='text-muted-foreground'>{m.summary.total}:</span>
                    <strong
                      className={
                        summary.total_amount >= 0 ? 'text-emerald-600' : 'text-rose-600'
                      }
                    >
                      {formatCurrency(summary.total_amount)}
                    </strong>
                  </div>
                  <div className='hidden h-5 w-px bg-border sm:block' />
                  <div className='flex items-center gap-2'>
                    <span className='text-muted-foreground'>{m.summary.page}:</span>
                    <strong
                      className={
                        summary.page_amount >= 0 ? 'text-emerald-600' : 'text-rose-600'
                      }
                    >
                      {formatCurrency(summary.page_amount)}
                    </strong>
                  </div>
                </CardContent>
              </Card>
            </>
          )}
        </div>
      </Main>
    </>
  )
}
