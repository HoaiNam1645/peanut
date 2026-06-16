'use client'

import { useCallback, useEffect, useState } from 'react'
import { LoaderCircle, RefreshCw } from 'lucide-react'
import {
  fetchShipDvxOrders,
  type ShipDvxOrder,
  type ShipDvxOrdersResult,
} from '@/services/orders/api'
import { Button } from '@/components/ui/button'

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
      return 'bg-green-100 text-green-700'
    case 'FAILED':
    case 'ORDER_FAILED':
    case 'CANCELLED':
      return 'bg-red-100 text-red-700'
    case 'PENDING':
      return 'bg-amber-100 text-amber-700'
    default:
      return 'bg-muted text-foreground'
  }
}

export function LemiexShipDvxOrders() {
  const [result, setResult] = useState<ShipDvxOrdersResult>({ docs: [] })
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [page, setPage] = useState(1)
  const limit = 20

  const load = useCallback(async (p: number) => {
    setLoading(true)
    setError(null)
    try {
      const res = await fetchShipDvxOrders(p, limit)
      setResult(res)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Không tải được đơn ShipDVX')
      setResult({ docs: [] })
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load(page)
  }, [page, load])

  const docs = result.docs ?? []
  const totalPages = result.totalPages ?? 1

  return (
    <div className='space-y-4 p-4'>
      <div className='flex items-center justify-between'>
        <div>
          <h1 className='text-xl font-semibold'>Đơn ShipDVX</h1>
          <p className='text-sm text-muted-foreground'>
            Đơn lấy trực tiếp từ API ShipDVX (GET /v1/partner/orders)
            {typeof result.totalDocs === 'number' ? ` — tổng ${result.totalDocs}` : ''}
          </p>
        </div>
        <Button
          type='button'
          variant='outline'
          size='sm'
          onClick={() => void load(page)}
          disabled={loading}
        >
          <RefreshCw className={`size-4 ${loading ? 'animate-spin' : ''}`} />
          Tải lại
        </Button>
      </div>

      {error ? (
        <div className='rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700'>
          {error}
        </div>
      ) : null}

      <div className='overflow-x-auto rounded-md border'>
        <table className='w-full text-left text-sm'>
          <thead className='bg-muted/40 text-xs uppercase text-muted-foreground'>
            <tr>
              <th className='px-3 py-2'>Order Number</th>
              <th className='px-3 py-2'>Type</th>
              <th className='px-3 py-2'>Status</th>
              <th className='px-3 py-2'>Partner</th>
              <th className='px-3 py-2'>Barcode</th>
              <th className='px-3 py-2 text-right'>Price</th>
              <th className='px-3 py-2 text-right'>Cân (g)</th>
              <th className='px-3 py-2'>Người nhận</th>
              <th className='px-3 py-2'>Tạo lúc</th>
            </tr>
          </thead>
          <tbody>
            {loading && docs.length === 0 ? (
              <tr>
                <td colSpan={9} className='px-3 py-8 text-center text-muted-foreground'>
                  <LoaderCircle className='mx-auto size-5 animate-spin' />
                </td>
              </tr>
            ) : docs.length === 0 ? (
              <tr>
                <td colSpan={9} className='px-3 py-8 text-center text-muted-foreground'>
                  Không có đơn nào
                </td>
              </tr>
            ) : (
              docs.map((o) => (
                <tr key={o._id ?? o.id ?? o.orderNumber} className='border-t'>
                  <td className='px-3 py-2 font-medium'>{o.orderNumber ?? '-'}</td>
                  <td className='px-3 py-2'>{o.orderType ?? '-'}</td>
                  <td className='px-3 py-2'>
                    <span className={`rounded px-2 py-0.5 text-xs ${statusClass(o.status)}`}>
                      {o.status ?? '-'}
                    </span>
                  </td>
                  <td className='px-3 py-2'>{partnerName(o.shippingPartner)}</td>
                  <td className='px-3 py-2 font-mono text-xs'>{o.barcode ?? '-'}</td>
                  <td className='px-3 py-2 text-right'>
                    {typeof o.calculatedPrice === 'number' ? `$${o.calculatedPrice.toFixed(2)}` : '-'}
                  </td>
                  <td className='px-3 py-2 text-right'>{o.chargeableWeight ?? '-'}</td>
                  <td className='px-3 py-2'>
                    {o.recipient?.name ?? '-'}
                    {o.recipient?.state ? ` (${o.recipient.state})` : ''}
                  </td>
                  <td className='px-3 py-2 text-xs text-muted-foreground'>
                    {o.createdAt ? new Date(o.createdAt).toLocaleString('vi-VN') : '-'}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <div className='flex items-center justify-between'>
        <span className='text-sm text-muted-foreground'>
          Trang {result.page ?? page} / {totalPages}
        </span>
        <div className='flex gap-2'>
          <Button
            type='button'
            variant='outline'
            size='sm'
            disabled={loading || (result.hasPrevPage === false) || page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
          >
            Trước
          </Button>
          <Button
            type='button'
            variant='outline'
            size='sm'
            disabled={loading || (result.hasNextPage === false) || page >= totalPages}
            onClick={() => setPage((p) => p + 1)}
          >
            Sau
          </Button>
        </div>
      </div>
    </div>
  )
}
