'use client'

import { useCallback, useEffect, useState } from 'react'
import { type ColumnDef } from '@tanstack/react-table'
import { RefreshCw } from 'lucide-react'
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

const columns: ColumnDef<ShipDvxOrder, unknown>[] = [
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
  const [result, setResult] = useState<ShipDvxOrdersResult>({ docs: [] })
  const [loading, setLoading] = useState(false)
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(20)

  const load = useCallback(async (p: number, size: number) => {
    setLoading(true)
    try {
      const res = await fetchShipDvxOrders(p, size)
      setResult(res)
    } catch {
      setResult({ docs: [] })
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load(page, pageSize)
  }, [page, pageSize, load])

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
              {typeof result.totalDocs === 'number'
                ? ` — ${result.totalDocs.toLocaleString('en-US')} đơn`
                : ''}
            </p>
          </div>

          <Button
            type='button'
            variant='outline'
            size='sm'
            className='rounded-[6px]'
            onClick={() => void load(page, pageSize)}
            disabled={loading}
          >
            <RefreshCw className={`size-4 ${loading ? 'animate-spin' : ''}`} />
            Tải lại
          </Button>
        </div>

        <div className='max-w-[1520px]'>
          <LemiexDataTable
            columns={columns}
            data={result.docs ?? []}
            page={result.page ?? page}
            pageSize={result.limit ?? pageSize}
            total={result.totalDocs ?? 0}
            loading={loading}
            loadingText='Đang tải…'
            emptyText='Không có đơn nào'
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
    </>
  )
}
