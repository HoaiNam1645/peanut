'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'next/navigation'
import { type ColumnDef } from '@tanstack/react-table'
import { Eye, RefreshCw } from 'lucide-react'
import {
  fetchShipDvxOrders,
  type ShipDvxOrder,
  type ShipDvxOrdersResult,
} from '@/services/orders/api'
import { Button } from '@/components/ui/button'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

function partnerName(p: ShipDvxOrder['shippingPartner']): string {
  if (!p) return '-'
  if (typeof p === 'string') return p
  return p.name ?? '-'
}

function statusClass(status?: string): string {
  switch (status) {
    case 'GENERATED':
    case 'DELIVERED':
    case 'PROCESSED':
      return 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
    case 'FAILED':
    case 'ORDER_FAILED':
    case 'CANCELLED':
      return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
    case 'PENDING':
      return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
    default:
      return 'bg-muted text-muted-foreground'
  }
}

const baseColumns: ColumnDef<ShipDvxOrder, unknown>[] = [
  {
    accessorKey: 'orderNumber',
    header: 'Order Number',
    cell: ({ row }) => (
      <span className='font-medium'>{row.original.orderNumber ?? '-'}</span>
    ),
  },
  {
    accessorKey: 'orderType',
    header: 'Type',
    cell: ({ row }) => (
      <span className='text-[13px] text-muted-foreground'>
        {row.original.orderType ?? '-'}
      </span>
    ),
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => (
      <span
        className={`inline-block rounded-[6px] px-2 py-0.5 text-xs font-medium ${statusClass(row.original.status)}`}
      >
        {row.original.status ?? '-'}
      </span>
    ),
  },
  {
    accessorKey: 'shippingPartner',
    header: 'Partner',
    cell: ({ row }) => partnerName(row.original.shippingPartner),
  },
  {
    accessorKey: 'barcode',
    header: 'Barcode',
    cell: ({ row }) => (
      <span className='font-mono text-xs'>{row.original.barcode ?? '-'}</span>
    ),
  },
  {
    accessorKey: 'calculatedPrice',
    header: 'Cước',
    meta: { thClassName: 'text-right', tdClassName: 'text-right' },
    cell: ({ row }) =>
      typeof row.original.calculatedPrice === 'number' ? (
        <span className='font-medium'>${row.original.calculatedPrice.toFixed(2)}</span>
      ) : (
        '-'
      ),
  },
  {
    accessorKey: 'chargeableWeight',
    header: 'Cân (g)',
    meta: { thClassName: 'text-right', tdClassName: 'text-right' },
    cell: ({ row }) => row.original.chargeableWeight ?? '-',
  },
  {
    accessorKey: 'recipient',
    header: 'Người nhận',
    cell: ({ row }) => {
      const r = row.original.recipient
      if (!r) return '-'
      return (
        <span>
          {r.name ?? '-'}
          {r.state ? (
            <span className='text-muted-foreground'> ({r.state})</span>
          ) : null}
        </span>
      )
    },
  },
  {
    accessorKey: 'createdAt',
    header: 'Tạo lúc',
    cell: ({ row }) => (
      <span className='text-[13px] text-muted-foreground'>
        {row.original.createdAt
          ? new Date(row.original.createdAt).toLocaleString('vi-VN')
          : '-'}
      </span>
    ),
  },
]

export function LemiexShipDvxOrders() {
  const searchParams = useSearchParams()
  const [result, setResult] = useState<ShipDvxOrdersResult>({ docs: [] })
  const [loading, setLoading] = useState(false)
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(20)
  const [selected, setSelected] = useState<ShipDvxOrder | null>(null)
  const [search, setSearch] = useState(searchParams.get('q') ?? '')

  const columns = useMemo<ColumnDef<ShipDvxOrder, unknown>[]>(
    () => [
      ...baseColumns,
      {
        id: 'journey',
        header: 'Hành trình',
        cell: ({ row }) => (
          <Button
            type='button'
            variant='outline'
            size='sm'
            className='h-7 rounded-[6px] text-xs'
            onClick={() => setSelected(row.original)}
          >
            <Eye className='size-3.5' />
            Xem
          </Button>
        ),
      },
    ],
    []
  )

  const load = useCallback(
    async (p: number, size: number, filtering: boolean) => {
      setLoading(true)
      try {
        // The provider list has no server-side search; when filtering we pull a
        // larger batch (page 1) so the target order is included for client filtering.
        const res = filtering
          ? await fetchShipDvxOrders(1, 100)
          : await fetchShipDvxOrders(p, size)
        setResult(res)
      } catch {
        setResult({ docs: [] })
      } finally {
        setLoading(false)
      }
    },
    []
  )

  const isFiltering = search.trim().length > 0

  useEffect(() => {
    void load(page, pageSize, isFiltering)
  }, [page, pageSize, isFiltering, load])

  const visibleDocs = useMemo(() => {
    const docs = result.docs ?? []
    const s = search.trim().toLowerCase()
    if (!s) return docs
    return docs.filter((d) => (d.orderNumber ?? '').toLowerCase().includes(s))
  }, [result.docs, search])

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

      <Main fluid className='flex flex-1 flex-col gap-3 px-4 sm:px-5 lg:px-6 xl:px-7'>
        <div className='flex flex-wrap items-center justify-between gap-3'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>Đơn ShipDVX</h2>
            <p className='text-sm text-muted-foreground'>
              Đơn vận chuyển lấy trực tiếp từ ShipDVX
              {isFiltering
                ? ` — lọc "${search.trim()}": ${visibleDocs.length} kết quả`
                : typeof result.totalDocs === 'number'
                  ? ` — ${result.totalDocs.toLocaleString('en-US')} đơn`
                  : ''}
            </p>
          </div>

          <div className='flex items-center gap-2'>
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder='Lọc theo orderNumber…'
              className='h-9 w-56 rounded-[6px] border border-input bg-background px-3 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring'
            />
            {isFiltering ? (
              <Button
                type='button'
                variant='ghost'
                size='sm'
                className='rounded-[6px]'
                onClick={() => setSearch('')}
              >
                Xoá lọc
              </Button>
            ) : null}
            <Button
              type='button'
              variant='outline'
              size='sm'
              className='rounded-[6px]'
              onClick={() => void load(page, pageSize, isFiltering)}
              disabled={loading}
            >
              <RefreshCw className={`size-4 ${loading ? 'animate-spin' : ''}`} />
              Tải lại
            </Button>
          </div>
        </div>

        <div className='max-w-[1520px]'>
          <LemiexDataTable
            columns={columns}
            data={visibleDocs}
            page={isFiltering ? 1 : (result.page ?? page)}
            pageSize={
              isFiltering
                ? Math.max(visibleDocs.length, 1)
                : (result.limit ?? pageSize)
            }
            total={isFiltering ? visibleDocs.length : (result.totalDocs ?? 0)}
            loading={loading}
            loadingText='Đang tải…'
            emptyText={isFiltering ? 'Không tìm thấy đơn khớp' : 'Không có đơn nào'}
            getRowId={(row, i) =>
              String(row._id ?? row.id ?? row.orderNumber ?? i)
            }
            onPageChange={setPage}
            onPageSizeChange={(s) => {
              setPageSize(s)
              setPage(1)
            }}
          />
        </div>
      </Main>

      <Dialog
        open={Boolean(selected)}
        onOpenChange={(o) => {
          if (!o) setSelected(null)
        }}
      >
        <DialogContent className='rounded-[8px] sm:max-w-lg'>
          <DialogHeader>
            <DialogTitle>Hành trình đơn {selected?.orderNumber ?? ''}</DialogTitle>
            <DialogDescription>
              {partnerName(selected?.shippingPartner)} · Barcode:{' '}
              {selected?.barcode ?? '-'} · Hiện tại: {selected?.status ?? '-'}
            </DialogDescription>
          </DialogHeader>

          <div className='max-h-[60vh] overflow-y-auto pr-1'>
            {selected?.statusHistory && selected.statusHistory.length > 0 ? (
              <ol className='relative ml-1 space-y-4 border-l border-border pl-5'>
                {selected.statusHistory.map((h, i) => (
                  <li key={i} className='relative'>
                    <span className='absolute -left-[23px] top-1 size-3 rounded-full bg-primary ring-2 ring-background' />
                    <div className='flex flex-wrap items-center gap-2'>
                      <span
                        className={`inline-block rounded-[6px] px-2 py-0.5 text-xs font-medium ${statusClass(h.status)}`}
                      >
                        {h.status ?? '-'}
                      </span>
                      <span className='text-xs text-muted-foreground'>
                        {h.timestamp
                          ? new Date(h.timestamp).toLocaleString('vi-VN')
                          : ''}
                      </span>
                    </div>
                    {h.note ? (
                      <div className='mt-1 text-[13px] text-foreground'>{h.note}</div>
                    ) : null}
                  </li>
                ))}
              </ol>
            ) : (
              <p className='py-6 text-center text-sm text-muted-foreground'>
                Chưa có lịch sử trạng thái cho đơn này.
              </p>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}
