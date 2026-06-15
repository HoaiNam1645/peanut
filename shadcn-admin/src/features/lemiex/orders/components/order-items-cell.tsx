'use client'

import { memo, useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { useI18n } from '@/context/i18n-provider'
import { getUserRoleName } from '@/services/auth/api'
import {
  remakeDesignFiles,
  remakeOrderQr,
  type OrderListItem,
} from '@/services/orders/api'
import { type AuthUser } from '@/stores/auth-store'
import { filenameFromUrl, openAndDownload } from '@/lib/download-file'

function getGoogleDriveId(url?: string | null) {
  if (!url) return null
  const match = url.match(/\/d\/([a-zA-Z0-9_-]+)/) || url.match(/id=([a-zA-Z0-9_-]+)/)
  return match ? match[1] : null
}

function getPreviewUrl(url?: string | null) {
  if (!url) return null
  const googleDriveId = getGoogleDriveId(url)
  if (googleDriveId) return `https://lh3.googleusercontent.com/d/${googleDriveId}=s220`
  return url
}

function formatStitchCount(value?: number | null) {
  if (!value) return ''
  return ` (${value.toLocaleString('en-US')})`
}

type OrderItemsCellProps = {
  order: OrderListItem
  user: AuthUser | null
}

function LazyItemCard({
  children,
  fallback,
}: {
  children: React.ReactNode
  fallback: React.ReactNode
}) {
  const containerRef = useRef<HTMLDivElement | null>(null)
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const node = containerRef.current
    if (!node || visible) return

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setVisible(true)
            observer.disconnect()
          }
        })
      },
      {
        rootMargin: '240px 0px',
        threshold: 0.01,
      }
    )

    observer.observe(node)

    return () => observer.disconnect()
  }, [visible])

  return <div ref={containerRef}>{visible ? children : fallback}</div>
}

function LazyMockupImage({
  src,
  alt,
}: {
  src?: string | null
  alt: string
}) {
  const imageRef = useRef<HTMLDivElement | null>(null)
  const [shouldLoad, setShouldLoad] = useState(false)

  useEffect(() => {
    const node = imageRef.current
    if (!node || shouldLoad || !src) return

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setShouldLoad(true)
            observer.disconnect()
          }
        })
      },
      {
        rootMargin: '180px 0px',
        threshold: 0.01,
      }
    )

    observer.observe(node)

    return () => observer.disconnect()
  }, [shouldLoad, src])

  return (
    <div
      ref={imageRef}
      className='flex h-16 w-16 items-center justify-center overflow-hidden rounded-[6px] border bg-background'
    >
      {shouldLoad && src ? (
        <img
          src={src}
          alt={alt}
          className='h-16 w-16 object-cover'
          loading='lazy'
          decoding='async'
        />
      ) : (
        <div className='h-full w-full animate-pulse bg-muted/60' />
      )}
    </div>
  )
}

function OrderItemsCellComponent({ order, user }: OrderItemsCellProps) {
  const { messages } = useI18n()
  const ordersMessages = messages.orders
  const [selectedItemIds, setSelectedItemIds] = useState<Array<number | string>>([])
  const [selectedMetaIds, setSelectedMetaIds] = useState<Array<number | string>>([])
  const [remakingQr, setRemakingQr] = useState(false)
  const [remakingDesign, setRemakingDesign] = useState(false)

  const role = getUserRoleName(user)
  const showControls = role === 'Admin' || role === 'Staff'
  const items = order.items || []

  if (items.length === 0) {
    return (
      <div className='space-y-1'>
        <div className='text-sm'>{ordersMessages.status.noItems}</div>
        <div className='text-xs text-muted-foreground'>
          {ordersMessages.status.itemCount.replace('{count}', '0')}
        </div>
      </div>
    )
  }

  const handleToggleItem = (itemId?: number | string) => {
    if (!itemId) return

    setSelectedItemIds((prev) =>
      prev.includes(itemId) ? prev.filter((id) => id !== itemId) : [...prev, itemId]
    )
  }

  const handleToggleMeta = (metaId?: number | string | null) => {
    if (!metaId) return

    setSelectedMetaIds((prev) =>
      prev.includes(metaId) ? prev.filter((id) => id !== metaId) : [...prev, metaId]
    )
  }

  const handleRemakeQr = async () => {
    if (selectedItemIds.length === 0) return

    try {
      setRemakingQr(true)
      await remakeOrderQr(selectedItemIds)
      toast.success(
        `${ordersMessages.actions.remakeQr} (${selectedItemIds.length})`
      )
      setSelectedItemIds([])
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : ordersMessages.actions.remakeQr
      )
    } finally {
      setRemakingQr(false)
    }
  }

  const handleRemakeDesign = async () => {
    if (selectedMetaIds.length === 0) return

    try {
      setRemakingDesign(true)
      await remakeDesignFiles(selectedMetaIds)
      toast.success(
        `${ordersMessages.actions.remakeDesign} (${selectedMetaIds.length})`
      )
      setSelectedMetaIds([])
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : ordersMessages.actions.remakeDesign
      )
    } finally {
      setRemakingDesign(false)
    }
  }

  return (
    <div className='space-y-3'>
      {showControls && (selectedItemIds.length > 0 || selectedMetaIds.length > 0) ? (
        <div className='flex flex-col items-start gap-2'>
          {selectedMetaIds.length > 0 ? (
            <Button
              type='button'
              size='sm'
              className='h-7 min-w-[120px] justify-center rounded-[6px] text-[11px]'
              disabled={remakingDesign}
              onClick={handleRemakeDesign}
            >
              {remakingDesign
                ? '...'
                : `${ordersMessages.actions.remakeDesign} (${selectedMetaIds.length})`}
            </Button>
          ) : null}

          {selectedItemIds.length > 0 ? (
            <Button
              type='button'
              size='sm'
              variant='outline'
              className='h-7 min-w-[120px] justify-center rounded-[6px] text-[11px]'
              disabled={remakingQr}
              onClick={handleRemakeQr}
            >
              {remakingQr
                ? '...'
                : `${ordersMessages.actions.remakeQr} (${selectedItemIds.length})`}
            </Button>
          ) : null}
        </div>
      ) : null}

      {items.map((item, index) => (
        <LazyItemCard
          key={`${order.id}-${item.id || index}`}
          fallback={
            <div className='rounded-[6px] border bg-muted/10 p-3'>
              <div className='flex items-start justify-between gap-3'>
                <div className='min-w-0 space-y-1'>
                  <div className='flex items-start gap-2'>
                    <span className='inline-flex size-6 shrink-0 items-center justify-center rounded-[6px] bg-primary/10 text-xs font-semibold text-primary'>
                      {index + 1}
                    </span>
                    <div className='min-w-0 max-w-[260px] sm:max-w-[320px]'>
                      <div className='truncate text-sm font-semibold'>
                        {item.product_name || ordersMessages.status.unnamedItem}
                      </div>
                      <div className='text-xs text-muted-foreground'>
                        VarID: {item.variant_id || ordersMessages.status.na}
                      </div>
                    </div>
                  </div>
                </div>

                <Badge
                  className='rounded-[6px] bg-emerald-500 px-2 py-1 text-[11px] text-white'
                  variant='secondary'
                >
                  Qty: {item.quantity || 0}
                </Badge>
              </div>

              <div className='mt-3 h-16 rounded-[6px] border bg-background/60' />
            </div>
          }
        >
          <div className='rounded-[6px] border bg-muted/10 p-3'>
            <div className='flex items-start justify-between gap-3'>
              <div className='min-w-0 space-y-1'>
                <div className='flex items-start gap-2'>
                  {showControls ? (
                    <Checkbox
                      checked={item.id ? selectedItemIds.includes(item.id) : false}
                      onCheckedChange={() => handleToggleItem(item.id)}
                      className='mt-0.5'
                    />
                  ) : null}

                  <span className='inline-flex size-6 shrink-0 items-center justify-center rounded-[6px] bg-primary/10 text-xs font-semibold text-primary'>
                    {index + 1}
                  </span>
                  <div className='min-w-0 max-w-[260px] sm:max-w-[320px]'>
                      <div className='truncate text-sm font-semibold'>
                        {item.product_name || ordersMessages.status.unnamedItem}
                      </div>
                      <div className='text-xs text-muted-foreground'>
                        VarID: {item.variant_id || ordersMessages.status.na}
                      </div>
                    </div>
                  </div>
              </div>

              <Badge
                className='rounded-[6px] bg-emerald-100 px-2 py-1 text-[11px] text-emerald-700'
                variant='secondary'
              >
                Qty: {item.quantity || 0}
              </Badge>
            </div>

            {item.mockup ? (
              <div className='mt-3 flex items-start gap-3'>
                <LazyMockupImage
                  src={getPreviewUrl(item.mockup)}
                  alt={item.product_name || 'Mockup'}
                />
              </div>
            ) : null}

            {item.designs && item.designs.length > 0 ? (
              <div className='mt-3 space-y-2'>
                {item.designs.map((design, designIndex) => (
                  <div
                    key={`${order.id}-${item.id || index}-design-${designIndex}`}
                    className='flex w-full max-w-full flex-wrap items-center gap-2 overflow-hidden rounded-[6px] border bg-background px-2.5 py-2'
                  >
                    {showControls ? (
                      <Checkbox
                        checked={
                          design.meta_id ? selectedMetaIds.includes(design.meta_id) : false
                        }
                        onCheckedChange={() => handleToggleMeta(design.meta_id)}
                      />
                    ) : null}

                    <span className='rounded-[6px] bg-muted px-2 py-1 text-[11px] font-semibold uppercase'>
                      {design.position || ordersMessages.status.front}
                    </span>

                    {design.dst_url ? (
                      <a
                        href={design.dst_url}
                        target='_blank'
                        rel='noreferrer'
                        className='rounded-[6px] bg-blue-500 px-2 py-1 text-[11px] font-semibold text-white'
                      >
                        DST{formatStitchCount(design.stitch_count)}
                      </a>
                    ) : null}

                    {design.emb_url ? (
                      <a
                        href={design.emb_url}
                        target='_blank'
                        rel='noreferrer'
                        className='rounded-[6px] bg-sky-500 px-2 py-1 text-[11px] font-semibold text-white'
                      >
                        EMB
                      </a>
                    ) : null}

                    {design.pes_url ? (
                      <a
                        href={design.pes_url}
                        target='_blank'
                        rel='noreferrer'
                        className='rounded-[6px] bg-pink-500 px-2 py-1 text-[11px] font-semibold text-white'
                      >
                        PES
                      </a>
                    ) : null}

                    {design.pdf_url ? (
                      <a
                        href={design.pdf_url}
                        target='_blank'
                        rel='noreferrer'
                        className='rounded-[6px] bg-indigo-100 px-2 py-1 text-[11px] font-semibold text-indigo-700'
                      >
                        PDF
                      </a>
                    ) : null}

                    {designIndex === 0 && item.merge_images && item.merge_images.length > 0
                      ? item.merge_images.map((mergeUrl, mergeIndex) => (
                          <button
                            type='button'
                            key={`merge-${mergeIndex}`}
                            onClick={() =>
                              openAndDownload(
                                mergeUrl,
                                filenameFromUrl(
                                  mergeUrl,
                                  `order_${order.id}_item_${item.id || index}_merge_${mergeIndex + 1}.png`
                                )
                              )
                            }
                            className='rounded-[6px] bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-700 hover:bg-amber-200'
                          >
                            MERGE{item.merge_images!.length > 1 ? ` ${mergeIndex + 1}` : ''}
                          </button>
                        ))
                      : null}
                  </div>
                ))}
              </div>
            ) : null}
          </div>
        </LazyItemCard>
      ))}
    </div>
  )
}

export const OrderItemsCell = memo(OrderItemsCellComponent)
