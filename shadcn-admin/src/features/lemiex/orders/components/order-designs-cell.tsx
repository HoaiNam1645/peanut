'use client'

import { memo } from 'react'
import { Badge } from '@/components/ui/badge'
import { type OrderListItem } from '@/services/orders/api'

// Designer-only cell: shows the full design info for every item of an order
// (size + large mockup/design previews + file links) so a designer doesn't have
// to open the order detail page.

function getGoogleDriveId(url?: string | null) {
  if (!url) return null
  const match = url.match(/\/d\/([a-zA-Z0-9_-]+)/) || url.match(/id=([a-zA-Z0-9_-]+)/)
  return match ? match[1] : null
}

function getPreviewUrl(url?: string | null) {
  if (!url) return null
  const id = getGoogleDriveId(url)
  if (id) return `https://lh3.googleusercontent.com/d/${id}=s400`
  return url
}

function DesignThumb({ url, label }: { url: string; label: string }) {
  return (
    <a
      href={url}
      target='_blank'
      rel='noreferrer'
      className='block shrink-0'
      title={label}
    >
      <div className='h-28 w-28 overflow-hidden rounded-[6px] border bg-background'>
        <img
          src={getPreviewUrl(url) ?? url}
          alt={label}
          loading='lazy'
          decoding='async'
          className='h-28 w-28 object-contain'
        />
      </div>
      <div className='mt-0.5 w-28 truncate text-center text-[10px] font-medium uppercase text-muted-foreground'>
        {label}
      </div>
    </a>
  )
}

function OrderDesignsCellComponent({ order }: { order: OrderListItem }) {
  const items = order.items || []
  if (items.length === 0) {
    return <span className='text-xs text-muted-foreground'>—</span>
  }

  return (
    <div className='space-y-3'>
      {items.map((item, idx) => {
        const designs = item.designs || []
        const hasFiles = designs.some(
          (d) => d.dst_url || d.pes_url || d.emb_url
        )
        return (
          <div
            key={`${order.id}-${item.id ?? idx}`}
            className='space-y-2 rounded-[6px] border bg-muted/10 p-2.5'
          >
            <div className='flex flex-wrap items-center gap-2'>
              <Badge className='rounded-[6px] bg-primary px-2 py-0.5 text-[12px] font-bold text-primary-foreground'>
                Size {item.size || item.variant?.size || '—'}
              </Badge>
              <span className='text-[12px] font-semibold'>
                {item.product_name || 'Item'}
              </span>
              <span className='text-[11px] text-muted-foreground'>
                ×{item.quantity || 0}
              </span>
              {item.variant_id ? (
                <span className='font-mono text-[10px] text-muted-foreground'>
                  {item.variant_id}
                </span>
              ) : null}
            </div>

            <div className='flex flex-wrap gap-2'>
              {item.mockup ? <DesignThumb url={item.mockup} label='Mockup' /> : null}
              {item.mockup_back ? (
                <DesignThumb url={item.mockup_back} label='Mockup sau' />
              ) : null}
              {designs.map((d, di) =>
                d.pdf_url ? (
                  <DesignThumb
                    key={`d-${di}`}
                    url={d.pdf_url}
                    label={d.position || 'design'}
                  />
                ) : null
              )}
            </div>

            {hasFiles ? (
              <div className='flex flex-wrap gap-1.5'>
                {designs.map((d, di) => (
                  <span key={`f-${di}`} className='inline-flex items-center gap-1'>
                    {d.dst_url ? (
                      <a
                        href={d.dst_url}
                        target='_blank'
                        rel='noreferrer'
                        className='rounded bg-blue-500 px-1.5 py-0.5 text-[10px] font-semibold text-white'
                      >
                        {(d.position || '').toUpperCase()}·DST
                      </a>
                    ) : null}
                    {d.pes_url ? (
                      <a
                        href={d.pes_url}
                        target='_blank'
                        rel='noreferrer'
                        className='rounded bg-pink-500 px-1.5 py-0.5 text-[10px] font-semibold text-white'
                      >
                        PES
                      </a>
                    ) : null}
                    {d.emb_url ? (
                      <a
                        href={d.emb_url}
                        target='_blank'
                        rel='noreferrer'
                        className='rounded bg-sky-500 px-1.5 py-0.5 text-[10px] font-semibold text-white'
                      >
                        EMB
                      </a>
                    ) : null}
                  </span>
                ))}
              </div>
            ) : null}
          </div>
        )
      })}
    </div>
  )
}

export const OrderDesignsCell = memo(OrderDesignsCellComponent)
