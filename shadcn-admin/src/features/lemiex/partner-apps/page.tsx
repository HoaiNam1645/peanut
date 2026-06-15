'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ColumnDef } from '@tanstack/react-table'
import { Copy, Link2, Plus, SquarePen } from 'lucide-react'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { useAuthStore } from '@/stores/auth-store'
import { useI18n } from '@/context/i18n-provider'
import { fetchPartnerApps, type PartnerAppRecord } from '@/services/partner-apps/api'
import { PartnerAppFormDialog } from './partner-app-form-dialog'

const fallbackMessages = {
  title: 'Partner Apps',
  subtitle: 'Copy auth links and manage partner app connection settings.',
  addApp: 'Add Partner App',
  loading: 'Loading partner apps...',
  empty: 'No partner apps found',
  copied: 'Auth link copied',
  noAuthLink: 'This partner app does not have an auth link yet',
  na: 'N/A',
  columns: {
    name: 'Name',
    linkAuth: 'Link Auth',
    proxyStatus: 'Proxy Status',
    status: 'Status',
    actions: 'Actions',
  },
  copyLink: 'Copy Link Auth',
  edit: 'Edit',
}

function tone(value?: string | null) {
  if ((value || '').toLowerCase() === 'active' || (value || '').toLowerCase() === 'live') {
    return 'bg-emerald-500/10 text-emerald-700 border-emerald-200'
  }
  return 'bg-slate-500/10 text-slate-700 border-slate-200'
}

export function LemiexPartnerAppsPage() {
  const { messages } = useI18n()
  const currentUser = useAuthStore((state) => state.auth.user)
  const roleName =
    typeof currentUser?.role === 'string'
      ? currentUser.role
      : currentUser?.role && typeof currentUser.role === 'object'
        ? String(currentUser.role.name || '')
        : String(currentUser?.role_name || '')
  const isAdmin = roleName === 'Admin'
  const ui = useMemo(
    () =>
      ((messages as { partnerAppsPage?: typeof fallbackMessages }).partnerAppsPage ??
        fallbackMessages),
    [messages]
  )

  const [apps, setApps] = useState<PartnerAppRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [createOpen, setCreateOpen] = useState(false)
  const [editingApp, setEditingApp] = useState<PartnerAppRecord | null>(null)
  const [refreshKey, setRefreshKey] = useState(0)

  useEffect(() => {
    let active = true

    async function loadApps() {
      setLoading(true)
      try {
        const data = await fetchPartnerApps()
        if (!active) return
        setApps(data)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : ui.loading)
      } finally {
        if (active) setLoading(false)
      }
    }

    void loadApps()
    return () => {
      active = false
    }
  }, [refreshKey, ui.loading])

  const handleCopy = useCallback(
    async (authUrl?: string | null) => {
      if (!authUrl) {
        toast.error(ui.noAuthLink)
        return
      }
      await navigator.clipboard.writeText(authUrl)
      toast.success(ui.copied)
    },
    [ui.copied, ui.noAuthLink]
  )

  const columns = useMemo<ColumnDef<PartnerAppRecord>[]>(
    () => [
      {
        id: 'name',
        header: ui.columns.name,
        cell: ({ row }) => <span className='font-semibold'>{row.original.name}</span>,
      },
      {
        id: 'link_auth',
        header: ui.columns.linkAuth,
        cell: ({ row }) => (
          <Button
            variant='outline'
            className='rounded-[6px]'
            onClick={() => void handleCopy(row.original.auth_url)}
          >
            <Copy className='size-4' />
            {ui.copyLink}
          </Button>
        ),
        meta: {
          thClassName: 'min-w-[220px]',
          tdClassName: 'min-w-[220px]',
        },
      },
      {
        id: 'proxy_status',
        header: ui.columns.proxyStatus,
        cell: ({ row }) => (
          <Badge variant='outline' className={tone(row.original.proxy_status)}>
            {row.original.proxy_status || ui.na}
          </Badge>
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
        id: 'actions',
        header: ui.columns.actions,
        cell: ({ row }) =>
          isAdmin ? (
            <Button
              variant='outline'
              size='icon'
              className='rounded-[6px]'
              onClick={() => setEditingApp(row.original)}
            >
              <SquarePen className='size-4' />
            </Button>
          ) : null,
      },
    ],
    [
      handleCopy,
      isAdmin,
      ui.na,
      ui.columns.actions,
      ui.columns.linkAuth,
      ui.columns.name,
      ui.columns.proxyStatus,
      ui.columns.status,
      ui.copyLink,
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
            {isAdmin ? (
              <Button className='rounded-[6px]' onClick={() => setCreateOpen(true)}>
                <Plus className='size-4' />
                {ui.addApp}
              </Button>
            ) : null}
          </div>

          <LemiexDataTable
            columns={columns}
            data={apps}
            page={1}
            pageSize={apps.length || 10}
            total={apps.length}
            loading={loading}
            loadingText={ui.loading}
            emptyText={ui.empty}
            getRowId={(row) => String(row.id)}
            onPageChange={() => {}}
            onPageSizeChange={() => {}}
          />
        </div>
      </Main>

      <PartnerAppFormDialog
        open={createOpen}
        mode='create'
        app={null}
        onOpenChange={setCreateOpen}
        onComplete={(message) => {
          toast.success(message)
          setRefreshKey((prev) => prev + 1)
        }}
      />

      <PartnerAppFormDialog
        open={Boolean(editingApp)}
        mode='edit'
        app={editingApp}
        onOpenChange={(open) => {
          if (!open) setEditingApp(null)
        }}
        onComplete={(message) => {
          toast.success(message)
          setRefreshKey((prev) => prev + 1)
          setEditingApp(null)
        }}
      />
    </>
  )
}
