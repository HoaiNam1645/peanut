'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import type { ColumnDef } from '@tanstack/react-table'
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
  fetchTicketById,
  fetchTickets,
  fetchTicketSellers,
  fetchTicketSupports,
  updateTicketStatus,
  type TicketFilters,
  type TicketPagination,
  type TicketRecord,
  type TicketUser,
} from '@/services/tickets/api'
import { CreateTicketDialog } from './create-ticket-dialog'

const ALL_VALUE = '__all__'

const fallbackMessages = {
  title: 'Support Tickets',
  subtitle: 'Manage support tickets',
  totalTickets: 'total tickets',
  tabs: { all: 'All Tickets', new: 'New', solved: 'Solved' },
  filters: {
    ticketId: 'Ticket ID',
    orderId: 'Order ID',
    subject: 'Subject',
    allSellers: 'All Sellers',
    allSupport: 'All Support',
  },
  columns: {
    id: 'ID',
    orderId: 'Order ID',
    subject: 'Subject',
    status: 'Status',
    userReply: 'User Reply',
    lastReply: 'Last Reply',
    owner: 'Owner',
    updated: 'Updated',
    actions: 'Actions',
  },
  status: { new: 'New', solved: 'Solved' },
  actions: { view: 'View', solve: 'Solve' },
  noTicketsTitle: 'No tickets found',
  noTicketsDescriptionFiltered: 'Try adjusting your filters',
  noTicketsDescriptionEmpty: 'No tickets available',
  loadFailed: 'Failed to load tickets',
  statusUpdated: 'Ticket status updated successfully!',
  statusUpdateFailed: 'Failed to update ticket status',
  createSuccess: 'Support ticket created successfully!',
}

function buildQueryString(
  params: Record<string, string | number | boolean | undefined | null>
) {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === '' || value === undefined || value === null) return
    query.set(key, String(value))
  })
  return query.toString()
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

export function LemiexTickets() {
  const { messages } = useI18n()
  const m = messages.ticketsPage ?? fallbackMessages
  const currentUser = useAuthStore((state) => state.auth.user)
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const roleName =
    typeof currentUser?.role === 'string'
      ? currentUser.role
      : currentUser?.role && typeof currentUser.role === 'object'
        ? String(currentUser.role.name || '')
        : String(currentUser?.role_name || '')

  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [sellers, setSellers] = useState<TicketUser[]>([])
  const [supports, setSupports] = useState<TicketUser[]>([])
  const [activeTab, setActiveTab] = useState(searchParams.get('tab') || 'all')
  const [pagination, setPagination] = useState<TicketPagination>({
    current_page: Number(searchParams.get('page') || 1),
    last_page: 1,
    per_page: Number(searchParams.get('per_page') || 20),
    total: 0,
  })
  const [filters, setFilters] = useState<TicketFilters>({
    ticket_id: searchParams.get('ticket_id') || '',
    order_id: searchParams.get('order_id') || '',
    subject: searchParams.get('subject') || '',
    seller_id: searchParams.get('seller_id') || '',
    support_id: searchParams.get('support_id') || '',
  })
  const [createOpen, setCreateOpen] = useState(
    searchParams.get('action') === 'create' && Boolean(searchParams.get('order_id'))
  )

  const syncQuery = useCallback(
    (
      nextFilters: TicketFilters,
      nextPage: number,
      nextPerPage: number,
      nextTab: string,
      nextAction?: string
    ) => {
      const next = buildQueryString({
        ...nextFilters,
        tab: nextTab !== 'all' ? nextTab : '',
        action: nextAction || '',
        page: nextPage > 1 ? nextPage : '',
        per_page: nextPerPage !== 20 ? nextPerPage : '',
      })
      router.replace(next ? `${pathname}?${next}` : pathname, { scroll: false })
    },
    [pathname, router]
  )

  useEffect(() => {
    let active = true

    async function loadOptions() {
      try {
        if (roleName === 'Admin' || roleName === 'Support') {
          const [nextSellers, nextSupports] = await Promise.all([
            fetchTicketSellers(),
            fetchTicketSupports(),
          ])
          if (!active) return
          setSellers(nextSellers)
          setSupports(nextSupports)
        }
      } catch {
        if (!active) return
      }
    }

    void loadOptions()
    return () => {
      active = false
    }
  }, [roleName])

  const loadTickets = useCallback(async () => {
    try {
      setLoading(true)
      const params: TicketFilters & { page: number; per_page: number } = {
        page: pagination.current_page,
        per_page: pagination.per_page,
        ...filters,
      }

      if (activeTab === 'new') params.status = 0
      if (activeTab === 'solved') params.status = 1

      const response = await fetchTickets(params)
      setTickets(response.tickets)
      setPagination(response.pagination)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.loadFailed)
    } finally {
      setLoading(false)
    }
  }, [activeTab, filters, m.loadFailed, pagination.current_page, pagination.per_page])

  useEffect(() => {
    void loadTickets()
  }, [loadTickets])

  useEffect(() => {
    const action = searchParams.get('action')
    const orderId = searchParams.get('order_id')
    setCreateOpen(action === 'create' && Boolean(orderId))
  }, [searchParams])

  const handleUpdateStatus = useCallback(async (ticketId: number | string, newStatus: number) => {
    try {
      await updateTicketStatus(ticketId, newStatus)
      toast.success(m.statusUpdated)
      await loadTickets()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.statusUpdateFailed)
    }
  }, [loadTickets, m.statusUpdateFailed, m.statusUpdated])

  const columns = useMemo<ColumnDef<TicketRecord>[]>(
    () => [
      {
        id: 'id',
        header: m.columns.id,
        cell: ({ row }) => <strong>#{row.original.id}</strong>,
        meta: { thClassName: 'min-w-[90px] text-center', tdClassName: 'text-center' },
      },
      {
        id: 'order',
        header: m.columns.orderId,
        cell: ({ row }) => row.original.order?.order_stt || 'N/A',
        meta: { thClassName: 'min-w-[120px]' },
      },
      {
        id: 'subject',
        header: m.columns.subject,
        cell: ({ row }) => (
          <div className='max-w-[320px] truncate font-medium'>{row.original.subject || 'N/A'}</div>
        ),
        meta: { thClassName: 'min-w-[220px]' },
      },
      {
        id: 'status',
        header: m.columns.status,
        cell: ({ row }) => (
          <Badge
            variant='outline'
            className={
              Number(row.original.status) === 0
                ? 'border-amber-200 bg-amber-500/10 text-amber-700'
                : 'border-emerald-200 bg-emerald-500/10 text-emerald-700'
            }
          >
            {Number(row.original.status) === 0 ? m.status.new : m.status.solved}
          </Badge>
        ),
        meta: { thClassName: 'min-w-[110px] text-center', tdClassName: 'text-center' },
      },
      {
        id: 'user_reply',
        header: m.columns.userReply,
        cell: ({ row }) => row.original.user_reply?.username || 'N/A',
        meta: { thClassName: 'min-w-[140px]' },
      },
      {
        id: 'last_reply',
        header: m.columns.lastReply,
        cell: ({ row }) => (
          <div className='max-w-[280px] truncate text-muted-foreground'>
            {row.original.last_reply || 'No messages'}
          </div>
        ),
        meta: { thClassName: 'min-w-[220px]' },
      },
      ...(roleName === 'Admin' || roleName === 'Support'
        ? ([
            {
              id: 'owner',
              header: m.columns.owner,
              cell: ({ row }) => row.original.owner?.username || 'N/A',
              meta: { thClassName: 'min-w-[140px]' },
            },
          ] satisfies ColumnDef<TicketRecord>[])
        : []),
      {
        id: 'updated_at',
        header: m.columns.updated,
        cell: ({ row }) => formatDate(row.original.updated_at),
        meta: { thClassName: 'min-w-[180px]' },
      },
      {
        id: 'actions',
        header: m.columns.actions,
        cell: ({ row }) => (
          <div className='flex justify-center gap-2'>
            <Button
              variant='outline'
              className='h-9 rounded-[6px]'
              onClick={() => router.push(`/lemiex/tickets/${row.original.id}`)}
            >
              {m.actions.view}
            </Button>
            {Number(row.original.status) === 0 ? (
              <Button
                className='h-9 rounded-[6px]'
                onClick={() => void handleUpdateStatus(row.original.id, 1)}
              >
                {m.actions.solve}
              </Button>
            ) : null}
          </div>
        ),
        meta: { thClassName: 'min-w-[180px] text-center', tdClassName: 'text-center' },
      },
    ],
    [handleUpdateStatus, m.actions.solve, m.actions.view, m.columns.actions, m.columns.id, m.columns.lastReply, m.columns.orderId, m.columns.owner, m.columns.status, m.columns.subject, m.columns.updated, m.columns.userReply, m.status.new, m.status.solved, roleName, router]
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
              {pagination.total > 0 ? `${pagination.total} ${m.totalTickets}` : m.subtitle}
            </p>
          </div>

          <div className='flex gap-2'>
            {(['all', 'new', 'solved'] as const).map((tab) => (
              <Button
                key={tab}
                variant={activeTab === tab ? 'default' : 'outline'}
                className='h-10 rounded-[6px]'
                onClick={() => {
                  setActiveTab(tab)
                  setPagination((prev) => ({ ...prev, current_page: 1 }))
                  syncQuery(filters, 1, pagination.per_page, tab, createOpen ? 'create' : '')
                }}
              >
                {m.tabs[tab]}
              </Button>
            ))}
          </div>

          <Card className='rounded-[6px] shadow-sm'>
            <CardContent className='p-5'>
              <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_220px_220px]'>
                <Input
                  className='h-10 rounded-[6px]'
                  placeholder={m.filters.ticketId}
                  value={filters.ticket_id || ''}
                  onChange={(event) => {
                    const next = { ...filters, ticket_id: event.target.value }
                    setFilters(next)
                    setPagination((prev) => ({ ...prev, current_page: 1 }))
                    syncQuery(next, 1, pagination.per_page, activeTab, createOpen ? 'create' : '')
                  }}
                />
                <Input
                  className='h-10 rounded-[6px]'
                  placeholder={m.filters.orderId}
                  value={filters.order_id || ''}
                  onChange={(event) => {
                    const next = { ...filters, order_id: event.target.value }
                    setFilters(next)
                    setPagination((prev) => ({ ...prev, current_page: 1 }))
                    syncQuery(next, 1, pagination.per_page, activeTab, createOpen ? 'create' : '')
                  }}
                />
                <Input
                  className='h-10 rounded-[6px]'
                  placeholder={m.filters.subject}
                  value={filters.subject || ''}
                  onChange={(event) => {
                    const next = { ...filters, subject: event.target.value }
                    setFilters(next)
                    setPagination((prev) => ({ ...prev, current_page: 1 }))
                    syncQuery(next, 1, pagination.per_page, activeTab, createOpen ? 'create' : '')
                  }}
                />

                {roleName === 'Admin' || roleName === 'Support' ? (
                  <div className='w-full'>
                    <Select
                      value={filters.seller_id || ALL_VALUE}
                      onValueChange={(value) => {
                        const next = { ...filters, seller_id: value === ALL_VALUE ? '' : value }
                        setFilters(next)
                        setPagination((prev) => ({ ...prev, current_page: 1 }))
                        syncQuery(next, 1, pagination.per_page, activeTab, createOpen ? 'create' : '')
                      }}
                    >
                      <SelectTrigger className='h-10 w-full rounded-[6px]'>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value={ALL_VALUE}>{m.filters.allSellers}</SelectItem>
                        {sellers.map((seller) => (
                          <SelectItem key={String(seller.id)} value={String(seller.id)}>
                            {seller.username || 'N/A'}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                ) : null}

                {roleName === 'Admin' ? (
                  <div className='w-full'>
                    <Select
                      value={filters.support_id || ALL_VALUE}
                      onValueChange={(value) => {
                        const next = { ...filters, support_id: value === ALL_VALUE ? '' : value }
                        setFilters(next)
                        setPagination((prev) => ({ ...prev, current_page: 1 }))
                        syncQuery(next, 1, pagination.per_page, activeTab, createOpen ? 'create' : '')
                      }}
                    >
                      <SelectTrigger className='h-10 w-full rounded-[6px]'>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value={ALL_VALUE}>{m.filters.allSupport}</SelectItem>
                        {supports.map((support) => (
                          <SelectItem key={String(support.id)} value={String(support.id)}>
                            {support.username || 'N/A'}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                ) : null}
              </div>
            </CardContent>
          </Card>

          <LemiexDataTable
            columns={columns}
            data={tickets}
            page={pagination.current_page}
            pageSize={pagination.per_page}
            total={pagination.total}
            loading={loading}
            loadingText={m.loadFailed.replace('Failed', 'Loading')}
            emptyText={
              Object.values(filters).some(Boolean) || activeTab !== 'all'
                ? m.noTicketsDescriptionFiltered
                : m.noTicketsDescriptionEmpty
            }
            getRowId={(row) => String(row.id)}
            onPageChange={(page) => {
              setPagination((prev) => ({ ...prev, current_page: page }))
              syncQuery(filters, page, pagination.per_page, activeTab, createOpen ? 'create' : '')
            }}
            onPageSizeChange={(pageSize) => {
              setPagination((prev) => ({ ...prev, current_page: 1, per_page: pageSize }))
              syncQuery(filters, 1, pageSize, activeTab, createOpen ? 'create' : '')
            }}
          />
        </div>
      </Main>

      <CreateTicketDialog
        open={createOpen}
        orderId={searchParams.get('order_id')}
        messages={m.createDialog}
        onOpenChange={(open) => {
          setCreateOpen(open)
          syncQuery(filters, pagination.current_page, pagination.per_page, activeTab, open ? 'create' : '')
        }}
        onSuccess={async () => {
          toast.success(m.createSuccess)
          await loadTickets()

          const orderId = searchParams.get('order_id')
          if (orderId) {
            try {
              const detail = await fetchTickets({
                page: 1,
                per_page: 1,
                order_id: orderId,
                status: 0,
              })
              const firstTicket = detail.tickets?.[0]
              if (firstTicket?.id) {
                router.push(`/lemiex/tickets/${firstTicket.id}`)
                return
              }
            } catch {
              // Ignore redirect failure and keep list refreshed.
            }
          }
        }}
      />
    </>
  )
}
