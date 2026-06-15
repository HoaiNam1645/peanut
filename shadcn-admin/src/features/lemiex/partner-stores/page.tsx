'use client'

import { useEffect, useMemo, useState } from 'react'
import Link from 'next/link'
import type { ColumnDef } from '@tanstack/react-table'
import { ExternalLink, LoaderCircle, Plus, RefreshCw, SquarePen } from 'lucide-react'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useAuthStore } from '@/stores/auth-store'
import { useI18n } from '@/context/i18n-provider'
import { fetchPartnerApps, type PartnerAppRecord } from '@/services/partner-apps/api'
import {
  fetchPartnerStores,
  type PartnerStorePagination,
  type PartnerStoreRecord,
} from '@/services/partner-stores/api'
import {
  applySyncedOrderCounts,
  createFakeOrdersForStore,
} from './fake-sync-orders'
import { PartnerStoreFormDialog } from './partner-store-form-dialog'

const fallbackMessages = {
  title: 'Partner Stores',
  subtitle: 'Create and manage connected partner shops separately from legacy stores.',
  addStore: 'Add Partner Store',
  searchPlaceholder: 'Search by name, code, user, account...',
  loading: 'Loading partner stores...',
  empty: 'No partner stores found',
  failed: 'Failed to load partner stores',
  syncTitle: 'Sync Orders',
  syncDescription: 'Confirm to sync the latest orders from this partner shop.',
  syncConfirm: 'Start Sync',
  syncCancel: 'Cancel',
  syncProgressTitle: 'Syncing orders...',
  syncProgressDescription: 'Please wait while the system processes partner orders.',
  syncDone: 'Orders synced successfully',
  na: 'N/A',
  columns: {
    id: 'ID',
    partner: 'Partner',
    name: 'Name',
    user: 'User',
    status: 'Status',
    totalOrders: 'Total Orders',
    accountNo: 'Account No',
    actions: 'Action',
  },
}

function tone(value?: string | null) {
  if ((value || '').toLowerCase() === 'active') {
    return 'bg-emerald-500/10 text-emerald-700 border-emerald-200'
  }
  if ((value || '').toLowerCase() === 'pending') {
    return 'bg-amber-500/10 text-amber-700 border-amber-200'
  }
  return 'bg-slate-500/10 text-slate-700 border-slate-200'
}

export function LemiexPartnerStoresPage() {
  const { messages } = useI18n()
  const currentUser = useAuthStore((state) => state.auth.user)
  const roleName =
    typeof currentUser?.role === 'string'
      ? currentUser.role
      : currentUser?.role && typeof currentUser.role === 'object'
        ? String(currentUser.role.name || '')
        : String(currentUser?.role_name || '')
  const canManage = roleName === 'Admin' || roleName === 'Seller'
  const ui = useMemo(
    () =>
      ((messages as { partnerStoresPage?: typeof fallbackMessages }).partnerStoresPage ??
        fallbackMessages),
    [messages]
  )

  const [stores, setStores] = useState<PartnerStoreRecord[]>([])
  const [apps, setApps] = useState<PartnerAppRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [refreshKey, setRefreshKey] = useState(0)
  const [pagination, setPagination] = useState<PartnerStorePagination>({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  })
  const [createOpen, setCreateOpen] = useState(false)
  const [editingStore, setEditingStore] = useState<PartnerStoreRecord | null>(null)
  const [syncingStore, setSyncingStore] = useState<PartnerStoreRecord | null>(null)
  const [syncStage, setSyncStage] = useState<'confirm' | 'progress'>('confirm')
  const [syncProgress, setSyncProgress] = useState(0)

  useEffect(() => {
    let active = true

    async function loadData() {
      setLoading(true)
      try {
        const [partnerApps, result] = await Promise.all([
          fetchPartnerApps(),
          fetchPartnerStores({
            page: pagination.current_page,
            per_page: pagination.per_page,
            search,
          }),
        ])

        if (!active) return
        setApps(partnerApps)
        setStores(applySyncedOrderCounts(result.stores))
        setPagination(result.pagination)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : ui.failed)
      } finally {
        if (active) setLoading(false)
      }
    }

    void loadData()
    return () => {
      active = false
    }
  }, [pagination.current_page, pagination.per_page, refreshKey, search, ui.failed])

  useEffect(() => {
    if (!syncingStore || syncStage !== 'progress') return

    setSyncProgress(0)

    const interval = window.setInterval(() => {
      setSyncProgress((prev) => {
        if (prev >= 100) {
          window.clearInterval(interval)
          return 100
        }

        const next = Math.min(prev + Math.floor(Math.random() * 18) + 8, 100)
        return next
      })
    }, 180)

    return () => {
      window.clearInterval(interval)
    }
  }, [syncStage, syncingStore])

  useEffect(() => {
    if (!syncingStore || syncStage !== 'progress' || syncProgress < 100) return

    const timeout = window.setTimeout(() => {
      if (syncingStore) {
        const syncResult = createFakeOrdersForStore(syncingStore)
        setStores((prev) =>
          prev.map((store) =>
            store.id === syncingStore.id
              ? { ...store, total_order: syncResult.count }
              : store
          )
        )
      }
      toast.success(ui.syncDone)
      setSyncingStore(null)
      setSyncStage('confirm')
      setSyncProgress(0)
    }, 500)

    return () => {
      window.clearTimeout(timeout)
    }
  }, [syncProgress, syncStage, syncingStore, ui.syncDone])

  const columns = useMemo<ColumnDef<PartnerStoreRecord>[]>(
    () => [
      {
        id: 'id',
        header: ui.columns.id,
        cell: ({ row }) => <strong>#{row.original.id}</strong>,
      },
      {
        id: 'partner',
        header: ui.columns.partner,
        cell: ({ row }) => row.original.partnerApp?.name || row.original.partner_app?.name || ui.na,
      },
      {
        id: 'name',
        header: ui.columns.name,
        cell: ({ row }) => (
          <div className='space-y-1'>
            <div className='font-semibold'>{row.original.name}</div>
            <div className='text-xs text-muted-foreground'>{row.original.code}</div>
          </div>
        ),
      },
      {
        id: 'user',
        header: ui.columns.user,
        cell: ({ row }) => (
          <div className='space-y-1'>
            <div className='text-sm font-medium'>{row.original.user?.username || ui.na}</div>
            <div className='text-xs text-muted-foreground'>{row.original.user?.email || ui.na}</div>
          </div>
        ),
      },
      {
        id: 'status',
        header: ui.columns.status,
        cell: ({ row }) => (
          <Badge variant='outline' className={tone(row.original.status)}>
            {row.original.status || ui.na}
          </Badge>
        ),
      },
      {
        id: 'total_order',
        header: ui.columns.totalOrders,
        cell: ({ row }) => {
          const totalOrder = row.original.total_order ?? 0

          return totalOrder > 0 ? (
            <Link
              href={`/lemiex/list-sync-orders?store_id=${row.original.id}`}
              className='font-medium text-primary underline-offset-4 hover:underline'
            >
              {totalOrder}
            </Link>
          ) : (
            totalOrder
          )
        },
      },
      {
        id: 'account_no',
        header: ui.columns.accountNo,
        cell: ({ row }) => row.original.account_no || '-',
      },
      {
        id: 'actions',
        header: ui.columns.actions,
        cell: ({ row }) =>
          canManage ? (
            <div className='flex items-center justify-center gap-2'>
              <Button
                variant='outline'
                size='icon'
                className='rounded-[6px]'
                onClick={() => {
                  setSyncingStore(row.original)
                  setSyncStage('confirm')
                  setSyncProgress(0)
                }}
              >
                <RefreshCw className='size-4' />
              </Button>
              <Button
                variant='outline'
                size='icon'
                className='rounded-[6px]'
                onClick={() => setEditingStore(row.original)}
              >
                <SquarePen className='size-4' />
              </Button>
              <Button asChild variant='outline' size='icon' className='rounded-[6px]'>
                <Link href={`/lemiex/list-sync-orders?store_id=${row.original.id}`}>
                  <ExternalLink className='size-4' />
                </Link>
              </Button>
            </div>
          ) : null,
      },
    ],
    [
      canManage,
      ui.na,
      ui.columns.accountNo,
      ui.columns.actions,
      ui.columns.id,
      ui.columns.name,
      ui.columns.partner,
      ui.columns.status,
      ui.columns.totalOrders,
      ui.columns.user,
    ]
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
          <div className='flex items-end justify-between gap-4'>
            <div className='space-y-1'>
              <h1 className='text-3xl font-semibold tracking-tight'>{ui.title}</h1>
              <p className='text-sm text-muted-foreground'>{ui.subtitle}</p>
            </div>
            {canManage ? (
              <Button className='rounded-[6px]' onClick={() => setCreateOpen(true)}>
                <Plus className='size-4' />
                {ui.addStore}
              </Button>
            ) : null}
          </div>

          <div className='rounded-[6px] border bg-card p-5 shadow-sm'>
            <Input
              className='h-10 rounded-[6px]'
              placeholder={ui.searchPlaceholder}
              value={search}
              onChange={(event) => {
                setSearch(event.target.value)
                setPagination((prev) => ({ ...prev, current_page: 1 }))
              }}
            />
          </div>

          <LemiexDataTable
            columns={columns}
            data={stores}
            page={pagination.current_page}
            pageSize={pagination.per_page}
            total={pagination.total}
            loading={loading}
            loadingText={ui.loading}
            emptyText={ui.empty}
            getRowId={(row) => String(row.id)}
            onPageChange={(page) =>
              setPagination((prev) => ({ ...prev, current_page: page }))
            }
            onPageSizeChange={(pageSize) =>
              setPagination((prev) => ({
                ...prev,
                per_page: pageSize,
                current_page: 1,
              }))
            }
          />
        </div>
      </Main>

      <PartnerStoreFormDialog
        open={createOpen}
        mode='create'
        store={null}
        apps={apps}
        currentUser={currentUser}
        onOpenChange={setCreateOpen}
        onComplete={(message) => {
          toast.success(message)
          setRefreshKey((prev) => prev + 1)
        }}
      />

      <PartnerStoreFormDialog
        open={Boolean(editingStore)}
        mode='edit'
        store={editingStore}
        apps={apps}
        currentUser={currentUser}
        onOpenChange={(open) => {
          if (!open) setEditingStore(null)
        }}
        onComplete={(message) => {
          toast.success(message)
          setRefreshKey((prev) => prev + 1)
          setEditingStore(null)
        }}
      />

      <Dialog
        open={Boolean(syncingStore)}
        onOpenChange={(open) => {
          if (!open) {
            setSyncingStore(null)
            setSyncStage('confirm')
            setSyncProgress(0)
          }
        }}
      >
        <DialogContent className='rounded-[6px] sm:max-w-md'>
          {syncStage === 'confirm' ? (
            <>
              <DialogHeader>
                <DialogTitle>{ui.syncTitle}</DialogTitle>
                <DialogDescription>
                  {ui.syncDescription}
                  {syncingStore ? ` (${syncingStore.name})` : ''}
                </DialogDescription>
              </DialogHeader>
              <DialogFooter>
                <Button
                  type='button'
                  variant='outline'
                  className='rounded-[6px]'
                  onClick={() => {
                    setSyncingStore(null)
                    setSyncStage('confirm')
                    setSyncProgress(0)
                  }}
                >
                  {ui.syncCancel}
                </Button>
                <Button
                  type='button'
                  className='rounded-[6px]'
                  onClick={() => {
                    setSyncStage('progress')
                    setSyncProgress(0)
                  }}
                >
                  {ui.syncConfirm}
                </Button>
              </DialogFooter>
            </>
          ) : (
            <>
              <DialogHeader>
                <DialogTitle>{ui.syncProgressTitle}</DialogTitle>
                <DialogDescription>{ui.syncProgressDescription}</DialogDescription>
              </DialogHeader>

              <div className='space-y-3 py-2'>
                <div className='flex items-center justify-between text-sm'>
                  <div className='flex items-center gap-2 text-muted-foreground'>
                    <LoaderCircle className='size-4 animate-spin' />
                    <span>{syncingStore?.name}</span>
                  </div>
                  <span className='font-medium'>{syncProgress}%</span>
                </div>
                <div className='h-2.5 overflow-hidden rounded-full bg-muted'>
                  <div
                    className='h-full rounded-full bg-primary transition-[width] duration-200 ease-out'
                    style={{ width: `${syncProgress}%` }}
                  />
                </div>
              </div>
            </>
          )}
        </DialogContent>
      </Dialog>
    </>
  )
}
