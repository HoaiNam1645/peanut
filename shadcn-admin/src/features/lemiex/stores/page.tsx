'use client'

import { useEffect, useMemo, useState } from 'react'
import type { ColumnDef } from '@tanstack/react-table'
import { Plus, SquarePen } from 'lucide-react'
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
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { fetchStoresList, type StorePagination, type StoreRecord } from '@/services/stores/api'
import { StoreFormDialog } from './store-form-dialog'

const ALL_VALUE = '__all__'

const fallbackMessages = {
  title: 'Stores Management',
  subtitle: 'Manage all stores',
  totalStores: 'total stores',
  addStore: 'Add New Store',
  searchPlaceholder: 'Search by store name, username, or email...',
  allStatus: 'All Status',
  status: {
    active: 'Active',
    unconfirmed: 'Unconfirmed',
    banned: 'Banned',
  },
  loading: 'Loading stores...',
  noStores: 'No stores found',
  noStoresAvailable: 'No stores available',
  failedToLoad: 'Failed to load stores',
  columns: {
    id: 'ID',
    user: 'User',
    storeName: 'Store Name',
    status: 'Status',
    createdAt: 'Created At',
    actions: 'Actions',
  },
  editStore: 'Edit Store',
}

function formatDate(dateString?: string | null) {
  if (!dateString) return 'N/A'
  try {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  } catch {
    return 'N/A'
  }
}

function getStatusTone(status?: string | null) {
  switch (status) {
    case 'Active':
      return 'bg-emerald-500/10 text-emerald-700 border-emerald-200'
    case 'Banned':
      return 'bg-rose-500/10 text-rose-700 border-rose-200'
    default:
      return 'bg-amber-500/10 text-amber-700 border-amber-200'
  }
}

export function LemiexStoresPage() {
  const { messages } = useI18n()
  const currentUser = useAuthStore((state) => state.auth.user)
  const roleName =
    typeof currentUser?.role === 'string'
      ? currentUser.role
      : currentUser?.role && typeof currentUser.role === 'object'
        ? String(currentUser.role.name || '')
        : String(currentUser?.role_name || '')
  const canManageStores = roleName === 'Admin' || roleName === 'Seller'
  const m = messages.storesPage ?? fallbackMessages

  const [stores, setStores] = useState<StoreRecord[]>([])
  const [filters, setFilters] = useState({ search: '', status: '' })
  const [pagination, setPagination] = useState<StorePagination>({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  })
  const [loading, setLoading] = useState(true)
  const [createOpen, setCreateOpen] = useState(false)
  const [editingStore, setEditingStore] = useState<StoreRecord | null>(null)
  const [refreshKey, setRefreshKey] = useState(0)

  useEffect(() => {
    let active = true

    async function loadStores() {
      setLoading(true)
      try {
        const result = await fetchStoresList({
          page: pagination.current_page,
          per_page: pagination.per_page,
          ...filters,
        })
        if (!active) return
        setStores(result.stores)
        setPagination(result.pagination)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : m.failedToLoad)
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void loadStores()

    return () => {
      active = false
    }
  }, [filters, m.failedToLoad, pagination.current_page, pagination.per_page, refreshKey])

  const columns = useMemo<ColumnDef<StoreRecord>[]>(
    () => [
      {
        id: 'id',
        header: m.columns.id,
        cell: ({ row }) => <strong>#{row.original.id}</strong>,
        meta: {
          thClassName: 'min-w-[80px] text-center',
          tdClassName: 'min-w-[80px] text-center',
        },
      },
      {
        id: 'user',
        header: m.columns.user,
        cell: ({ row }) => (
          <div className='space-y-1'>
            <div className='text-sm font-medium'>
              {row.original.user?.username || 'N/A'}
            </div>
            <div className='text-xs text-muted-foreground'>
              {row.original.user?.email || 'N/A'}
            </div>
          </div>
        ),
        meta: {
          thClassName: 'min-w-[220px]',
          tdClassName: 'min-w-[220px]',
        },
      },
      {
        id: 'name',
        header: m.columns.storeName,
        cell: ({ row }) => <span className='font-semibold'>{row.original.name || 'N/A'}</span>,
        meta: {
          thClassName: 'min-w-[220px]',
          tdClassName: 'min-w-[220px]',
        },
      },
      {
        id: 'status',
        header: m.columns.status,
        cell: ({ row }) => (
          <Badge variant='outline' className={getStatusTone(row.original.status)}>
            {row.original.status || m.status.unconfirmed}
          </Badge>
        ),
        meta: {
          thClassName: 'min-w-[130px] text-center',
          tdClassName: 'min-w-[130px] text-center',
        },
      },
      {
        id: 'created_at',
        header: m.columns.createdAt,
        cell: ({ row }) => formatDate(row.original.created_at),
        meta: {
          thClassName: 'min-w-[150px] text-center',
          tdClassName: 'min-w-[150px] text-center',
        },
      },
      {
        id: 'actions',
        header: m.columns.actions,
        cell: ({ row }) => (
          <div className='flex justify-center'>
            <Button
              variant='outline'
              size='icon'
              className='rounded-[6px]'
              onClick={() => setEditingStore(row.original)}
            >
              <SquarePen className='size-4' />
            </Button>
          </div>
        ),
        meta: {
          thClassName: 'min-w-[100px] text-center',
          tdClassName: 'min-w-[100px] text-center',
        },
      },
    ],
    [
      m.columns.actions,
      m.columns.createdAt,
      m.columns.id,
      m.columns.status,
      m.columns.storeName,
      m.columns.user,
      m.status.unconfirmed,
    ]
  )

  function handleDialogComplete(result: { ok: boolean; message: string }) {
    if (result.ok) {
      toast.success(result.message)
      setRefreshKey((prev) => prev + 1)
    } else {
      toast.error(result.message)
    }
    setCreateOpen(false)
    setEditingStore(null)
    setPagination((prev) => ({ ...prev, current_page: 1 }))
  }

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
                {pagination.total > 0
                  ? `${pagination.total} ${m.totalStores}`
                  : m.subtitle}
              </p>
            </div>

            {canManageStores ? (
              <Button className='rounded-[6px]' onClick={() => setCreateOpen(true)}>
                <Plus className='size-4' />
                {m.addStore}
              </Button>
            ) : null}
          </div>

          <div className='rounded-[6px] border bg-card p-5 shadow-sm'>
            <div className='grid gap-4 md:grid-cols-[minmax(0,1fr)_220px]'>
              <Input
                className='h-10 rounded-[6px]'
                placeholder={m.searchPlaceholder}
                value={filters.search}
                onChange={(event) => {
                  setFilters((prev) => ({ ...prev, search: event.target.value }))
                  setPagination((prev) => ({ ...prev, current_page: 1 }))
                }}
              />

              <Select
                value={filters.status || ALL_VALUE}
                onValueChange={(value) => {
                  setFilters((prev) => ({
                    ...prev,
                    status: value === ALL_VALUE ? '' : value,
                  }))
                  setPagination((prev) => ({ ...prev, current_page: 1 }))
                }}
              >
                <SelectTrigger className='h-10 rounded-[6px]'>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL_VALUE}>{m.allStatus}</SelectItem>
                  <SelectItem value='Active'>{m.status.active}</SelectItem>
                  <SelectItem value='Unconfirmed'>{m.status.unconfirmed}</SelectItem>
                  <SelectItem value='Banned'>{m.status.banned}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <LemiexDataTable
            columns={columns}
            data={stores}
            page={pagination.current_page}
            pageSize={pagination.per_page}
            total={pagination.total}
            loading={loading}
            loadingText={m.loading}
            emptyText={stores.length === 0 ? m.noStores : m.noStoresAvailable}
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

      <StoreFormDialog
        open={createOpen}
        mode='create'
        store={null}
        currentUser={currentUser}
        onOpenChange={setCreateOpen}
        onComplete={handleDialogComplete}
      />

      <StoreFormDialog
        open={Boolean(editingStore)}
        mode='edit'
        store={editingStore}
        currentUser={currentUser}
        onOpenChange={(open) => {
          if (!open) setEditingStore(null)
        }}
        onComplete={handleDialogComplete}
      />
    </>
  )
}
