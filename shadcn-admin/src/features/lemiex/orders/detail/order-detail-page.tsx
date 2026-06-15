'use client'

import { type ReactNode, useEffect, useMemo, useState } from 'react'
import { useRouter } from 'next/navigation'
import {
  ArrowLeft,
  Clock,
  LoaderCircle,
  Pencil,
  Ticket,
  Truck,
  Video,
} from 'lucide-react'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { OrderEditDialog } from '@/features/lemiex/orders/components/order-edit-dialog'
import { OrderFulfillStatusCell } from '@/features/lemiex/orders/components/order-fulfill-status-cell'
import { OrderTimelineDialog } from '@/features/lemiex/orders/components/order-timeline-dialog'
import { FALLBACK_FULFILL_STATUS_OPTIONS } from '@/features/lemiex/orders/constants'
import { useI18n } from '@/context/i18n-provider'
import { API_BASE_URL } from '@/config/api'
import { getUserRoleName } from '@/services/auth/api'
import {
  fetchOrderById,
  fetchOrderFulfillStatusOptions,
  postOrderLabel,
  type OrderDetail,
  type SelectOption,
  sellerCancelOrder,
} from '@/services/orders/api'
import { useAuthStore } from '@/stores/auth-store'

type OrderDetailPageProps = {
  orderId: string
}

function sectionCardClassName() {
  return 'rounded-[6px] border-border/80 shadow-none'
}

function formatCurrency(value?: number | null) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value || 0)
}

function formatDateTime(value?: string | null, fallback = 'N/A') {
  if (!value) return fallback

  try {
    return new Date(value).toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return value
  }
}

function getStatusTone(status?: string | null) {
  switch (status) {
    case 'new_order':
      return 'bg-violet-50 text-violet-700'
    case 'producing':
      return 'bg-cyan-50 text-cyan-700'
    case 'confirm':
      return 'bg-indigo-50 text-indigo-700'
    case 'shipped':
      return 'bg-emerald-50 text-emerald-700'
    case 'on_hold':
      return 'bg-rose-50 text-rose-700'
    case 'cancelled':
    case 'cancelled_refund_shipping':
      return 'bg-slate-100 text-slate-700'
    default:
      return 'bg-muted text-foreground'
  }
}

function getLocalizedStatusLabel(
  status: string | null | undefined,
  messages: ReturnType<typeof useI18n>['messages']['orders']
) {
  if (!status) return messages.status.na
  return (
    messages.fulfillStatuses[status as keyof typeof messages.fulfillStatuses] ||
    messages.paymentStatuses[status as keyof typeof messages.paymentStatuses] ||
    status.replaceAll('_', ' ')
  )
}

function InfoItem({
  label,
  value,
  className = '',
}: {
  label: string
  value: ReactNode
  className?: string
}) {
  return (
    <div className={`space-y-1.5 ${className}`}>
      <div className='text-[12px] font-medium text-muted-foreground'>{label}</div>
      <div className='text-[13px] text-foreground'>{value}</div>
    </div>
  )
}

export function OrderDetailPage({ orderId }: OrderDetailPageProps) {
  const router = useRouter()
  const { messages } = useI18n()
  const ordersMessages = messages.orders
  const detailMessages = messages.orders.detail
  const currentUser = useAuthStore((state) => state.auth.user)
  const role = getUserRoleName(currentUser)

  const [order, setOrder] = useState<OrderDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [timelineOpen, setTimelineOpen] = useState(false)
  const [ticketExistsOpen, setTicketExistsOpen] = useState(false)
  const [editOpen, setEditOpen] = useState(false)
  const [updatingLabel, setUpdatingLabel] = useState(false)
  const [downloadingAllQr, setDownloadingAllQr] = useState(false)
  const [statusOptions, setStatusOptions] = useState<SelectOption[]>([])

  const canUseSupport =
    role === 'Admin' || role === 'Seller' || role === 'Support'
  const canUpdateLabel =
    role === 'Admin' || role === 'Support' || role === 'Seller'
  const canEdit =
    role === 'Admin' ||
    role === 'Staff' ||
    (role === 'Seller' &&
      ['new_order', 'on_hold'].includes(order?.fulfill_status || ''))
  const canSeeSeller = role === 'Admin' || role === 'Staff'
  const canSeeVideos = role === 'Admin' || role === 'Staff'

  const loadOrder = async () => {
    try {
      setLoading(true)
      setError('')
      const response = await fetchOrderById(orderId)
      setOrder(response)
    } catch (detailError) {
      setError(
        detailError instanceof Error
          ? detailError.message
          : ordersMessages.loadErrorTitle
      )
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadOrder()
  }, [orderId])

  useEffect(() => {
    let active = true

    const run = async () => {
      try {
        const options = await fetchOrderFulfillStatusOptions()
        if (active) setStatusOptions(options.filter(Boolean) as SelectOption[])
      } catch {
        if (active) setStatusOptions(FALLBACK_FULFILL_STATUS_OPTIONS)
      }
    }

    void run()
    return () => {
      active = false
    }
  }, [])

  const qrCodes = useMemo(() => {
    if (!order?.items) return []
    return order.items.flatMap((item, itemIndex) =>
      (item.qr_codes || []).map((url, qrIndex) => ({
        url,
        label: `Item ${itemIndex + 1} - QR ${qrIndex + 1}`,
      }))
    )
  }, [order?.items])

  const mergeImages = useMemo(() => {
    if (!order?.items) return []
    return order.items.flatMap((item, itemIndex) =>
      (item.merge_images || []).map((url, imageIndex) => ({
        url,
        label: `Item ${itemIndex + 1} - Merge ${imageIndex + 1}`,
      }))
    )
  }, [order?.items])

  const handleSupportClick = () => {
    if (!order) return

    if (order.has_ticket || order.support_ticket?.id) {
      setTicketExistsOpen(true)
      return
    }

    router.push(`/lemiex/tickets?order_id=${order.id}&action=create`)
  }

  const handleUpdateLabel = async () => {
    if (!order) return

    try {
      setUpdatingLabel(true)
      await postOrderLabel(order.id)
      toast.success(detailMessages.updateLabelSuccess)
      await loadOrder()
    } catch (updateError) {
      toast.error(
        updateError instanceof Error
          ? updateError.message
          : detailMessages.updateLabelFailed
      )
    } finally {
      setUpdatingLabel(false)
    }
  }

  const handleSellerCancel = async () => {
    if (!order) return

    const confirmed = window.confirm(
      detailMessages.sellerCancelConfirm.replace('{id}', String(order.id))
    )
    if (!confirmed) return

    try {
      await sellerCancelOrder(order.id)
      toast.success(detailMessages.sellerCancelSuccess)
      await loadOrder()
    } catch (cancelError) {
      toast.error(
        cancelError instanceof Error
          ? cancelError.message
          : detailMessages.sellerCancelFailed
      )
    }
  }

  const downloadQrFile = async (url: string, filename: string) => {
    try {
      const proxyUrl = `${API_BASE_URL}/proxy/download?url=${encodeURIComponent(url)}&filename=${encodeURIComponent(filename)}`
      const response = await fetch(proxyUrl)

      if (!response.ok) {
        throw new Error('Download failed')
      }

      const blob = await response.blob()
      const objectUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = objectUrl
      link.download = filename
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(objectUrl)
    } catch {
      window.open(url, '_blank', 'noopener,noreferrer')
    }
  }

  const handleDownloadAllQr = async () => {
    if (qrCodes.length === 0 || !order) return

    try {
      setDownloadingAllQr(true)
      let successCount = 0
      const orderIdentifier = order.order_stt || order.id

      for (const qr of qrCodes) {
        try {
          await downloadQrFile(qr.url, `order_${orderIdentifier}_qr_${successCount + 1}.png`)
          successCount += 1
          await new Promise((resolve) => window.setTimeout(resolve, 350))
        } catch {
          // Swallow per-file errors so the loop can continue.
        }
      }

      toast.success(
        detailMessages.downloadAllSuccess
          .replace('{success}', String(successCount))
          .replace('{total}', String(qrCodes.length))
      )
    } finally {
      setDownloadingAllQr(false)
    }
  }

  if (loading) {
    return (
      <>
        <Header>
          <Search />
        <div className='ml-auto flex items-center space-x-4'>
          <LanguageSwitch />
          <ThemeSwitch />
          <ProfileDropdown />
        </div>
        </Header>
        <Main fluid className='space-y-4 px-3 py-4 sm:px-4 lg:px-6 xl:px-7'>
          <div className='flex min-h-[40vh] items-center justify-center text-[13px] text-muted-foreground'>
            <span className='inline-flex items-center gap-2'>
              <LoaderCircle className='h-4 w-4 animate-spin' />
              {detailMessages.loadingOrder}
            </span>
          </div>
        </Main>
      </>
    )
  }

  if (error || !order) {
    return (
      <>
        <Header>
          <Search />
        </Header>
        <Main fluid className='space-y-4 px-3 py-4 sm:px-4 lg:px-6 xl:px-7'>
          <Card className={sectionCardClassName()}>
            <CardContent className='space-y-4 px-4 py-5'>
              <div className='text-[15px] font-semibold text-foreground'>
                {detailMessages.orderNotFound}
              </div>
              <div className='text-[13px] text-muted-foreground'>
                {error || detailMessages.noData}
              </div>
              <Button
                type='button'
                variant='outline'
                className='rounded-[6px]'
                onClick={() => router.push('/lemiex/orders')}
              >
                <ArrowLeft className='h-3.5 w-3.5' />
                {detailMessages.backToOrders}
              </Button>
            </CardContent>
          </Card>
        </Main>
      </>
    )
  }

  return (
    <>
      <Header>
        <Search />
      </Header>

      <Main fluid className='space-y-4 px-3 py-4 sm:px-4 lg:px-6 xl:px-7'>
        {/* Page header: back + title + status in one row */}
        <div className='flex flex-wrap items-center gap-3'>
          <Button
            type='button'
            variant='outline'
            size='sm'
            className='rounded-[6px]'
            onClick={() => router.push('/lemiex/orders')}
          >
            <ArrowLeft className='h-3.5 w-3.5' />
            {detailMessages.backToOrders}
          </Button>

          <h1 className='text-[20px] font-semibold tracking-tight text-foreground sm:text-[24px]'>
            #{order.order_stt || order.id}
          </h1>
          {order.ref_id ? (
            <span className='text-[13px] text-muted-foreground'>{order.ref_id}</span>
          ) : null}

          <div className='ml-auto flex flex-wrap items-center gap-2'>
            {role === 'Admin' || role === 'Staff' ? (
              <OrderFulfillStatusCell
                order={order}
                user={currentUser}
                options={
                  statusOptions.length > 0
                    ? statusOptions
                    : FALLBACK_FULFILL_STATUS_OPTIONS
                }
                onUpdated={loadOrder}
              />
            ) : (
              <>
                <Badge
                  className={`rounded-[6px] px-2.5 py-1 text-[12px] font-semibold ${getStatusTone(order.fulfill_status)}`}
                >
                  {getLocalizedStatusLabel(order.fulfill_status, ordersMessages)}
                </Badge>
                {role === 'Seller' &&
                order.fulfill_status === 'new_order' &&
                order.payment_status !== 'paid' ? (
                  <Button
                    type='button'
                    variant='destructive'
                    size='sm'
                    className='rounded-[6px]'
                    onClick={handleSellerCancel}
                  >
                    {detailMessages.cancelOrder}
                  </Button>
                ) : null}
              </>
            )}
          </div>
        </div>

        <div className='grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]'>
          <div className='space-y-4'>
            {/* Order Info + Seller Info merged */}
            <Card className={sectionCardClassName()}>
              <CardContent className='space-y-0 p-0'>
                <div className='px-4 py-4'>
                  <p className='mb-3 text-[13px] font-semibold text-foreground'>
                    {detailMessages.orderInfo}
                  </p>
                  <div className='grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-3'>
                    <InfoItem
                      label={detailMessages.orderStt}
                      value={order.order_stt || order.id}
                    />
                    <InfoItem
                      label={detailMessages.referenceId}
                      value={order.ref_id || detailMessages.noData}
                    />
                    {order.seller_ref ? (
                      <InfoItem
                        label={detailMessages.sellerRef}
                        value={order.seller_ref}
                      />
                    ) : null}
                    <InfoItem
                      label={detailMessages.paymentStatus}
                      value={
                        <Badge variant='outline' className='rounded-[4px] text-[11px] capitalize'>
                          {getLocalizedStatusLabel(order.payment_status, ordersMessages)}
                        </Badge>
                      }
                    />
                    <InfoItem
                      label={detailMessages.createdAt}
                      value={formatDateTime(
                        order.timestamps?.created_at || order.created_at,
                        ordersMessages.status.na
                      )}
                    />
                  </div>
                </div>

                <div className='border-t border-border/60 px-4 py-4'>
                  <p className='mb-3 text-[13px] font-semibold text-foreground'>
                    {detailMessages.shippingInfo}
                  </p>
                  <div className='grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-3'>
                    <InfoItem
                      label={detailMessages.service}
                      value={order.shipping?.service || ordersMessages.status.na}
                    />
                    <InfoItem
                      label={detailMessages.method}
                      value={order.shipping?.method || ordersMessages.status.na}
                    />
                    {order.shipping?.tracking_id ? (
                      <InfoItem
                        label={detailMessages.trackingId}
                        value={
                          <a
                            href={`https://t.17track.net/en#nums=${order.shipping.tracking_id}`}
                            target='_blank'
                            rel='noreferrer'
                            className='inline-flex rounded-[6px] bg-muted px-2 py-1 text-[13px] font-medium text-foreground hover:underline'
                          >
                            {order.shipping.tracking_id}
                          </a>
                        }
                      />
                    ) : null}
                    {(canSeeSeller && order.shipping?.label_url) || (canSeeSeller && order.convert_label) ? (
                      <InfoItem
                        label={detailMessages.shippingLabel}
                        value={
                          <div className='flex flex-wrap gap-1.5'>
                            {canSeeSeller && order.shipping?.label_url ? (
                              <a
                                href={order.shipping.label_url}
                                target='_blank'
                                rel='noreferrer'
                                className='inline-flex rounded-[6px] bg-violet-50 px-2 py-1 text-[12px] font-semibold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300'
                              >
                                {detailMessages.viewLabel}
                              </a>
                            ) : null}
                            {canSeeSeller && order.convert_label ? (
                              <a
                                href={order.convert_label}
                                target='_blank'
                                rel='noreferrer'
                                className='inline-flex rounded-[6px] bg-fuchsia-50 px-2 py-1 text-[12px] font-semibold text-fuchsia-700 dark:bg-fuchsia-950/40 dark:text-fuchsia-300'
                              >
                                {detailMessages.viewConvert}
                              </a>
                            ) : null}
                          </div>
                        }
                      />
                    ) : null}
                    {canSeeSeller && order.shipping?.address?.street1 ? (
                      <InfoItem
                        label={detailMessages.address}
                        className='sm:col-span-2 xl:col-span-3'
                        value={
                          <div className='whitespace-pre-line text-[13px] leading-6'>
                            {order.shipping.address.street1}
                            {order.shipping.address.street2
                              ? `, ${order.shipping.address.street2}`
                              : ''}
                            {'\n'}
                            {order.shipping.address.city}, {order.shipping.address.state}{' '}
                            {order.shipping.address.zip}
                            {'\n'}
                            {order.shipping.address.country}
                          </div>
                        }
                      />
                    ) : null}
                  </div>
                </div>

                {canSeeSeller && order.seller ? (
                  <div className='border-t border-border/60 px-4 py-4'>
                    <p className='mb-3 text-[13px] font-semibold text-foreground'>
                      {detailMessages.sellerInfo}
                    </p>
                    <div className='grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-4'>
                      <InfoItem
                        label={detailMessages.username}
                        value={
                          order.seller.username ||
                          order.seller.name ||
                          detailMessages.noData
                        }
                      />
                      {role === 'Admin' && order.seller.email ? (
                        <InfoItem
                          label={detailMessages.email}
                          value={order.seller.email}
                        />
                      ) : null}
                      {order.seller.tier ? (
                        <InfoItem label={detailMessages.tier} value={order.seller.tier} />
                      ) : null}
                      {order.seller.store_name ? (
                        <InfoItem
                          label={detailMessages.store}
                          value={order.seller.store_name}
                        />
                      ) : null}
                    </div>
                  </div>
                ) : null}
              </CardContent>
            </Card>

            <Card className={sectionCardClassName()}>
              <CardContent className='space-y-3 px-3 py-3 sm:px-4 sm:py-4'>
                <p className='text-[13px] font-semibold text-foreground'>
                  {detailMessages.orderItems}
                </p>
                {(order.items || []).map((item, index) => (
                  <div
                    key={String(item.id || index)}
                    className='rounded-[6px] border border-border/80 bg-background p-3 sm:p-4'
                  >
                    <div className='flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between'>
                      <div className='space-y-1'>
                        <div className='text-[14px] font-semibold text-foreground'>
                          #{index + 1} {item.product_name || ordersMessages.status.unnamedItem}
                        </div>
                        <div className='text-[12px] text-muted-foreground'>
                          VarID: {item.variant_id || ordersMessages.status.noVariant}
                        </div>
                      </div>
                      <Badge className='w-fit rounded-[6px] bg-emerald-50 text-emerald-700'>
                        {detailMessages.quantity}: {item.quantity || 0}
                      </Badge>
                    </div>

                    {item.variant ? (
                      <div className='mt-3 flex flex-wrap gap-2'>
                        {item.variant.style ? (
                          <Badge variant='outline' className='rounded-[6px]'>
                            Style: {item.variant.style}
                          </Badge>
                        ) : null}
                        {item.variant.color ? (
                          <Badge variant='outline' className='rounded-[6px]'>
                            Color: {item.variant.color}
                          </Badge>
                        ) : null}
                        {item.variant.size ? (
                          <Badge variant='outline' className='rounded-[6px]'>
                            Size: {item.variant.size}
                          </Badge>
                        ) : null}
                        {item.variant.sku ? (
                          <Badge variant='outline' className='rounded-[6px]'>
                            SKU: {item.variant.sku}
                          </Badge>
                        ) : null}
                      </div>
                    ) : null}

                    <div className='mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3'>
                      {item.mockup ? (
                        <a
                          href={item.mockup}
                          target='_blank'
                          rel='noreferrer'
                          className='overflow-hidden rounded-[6px] border border-border/80'
                        >
                          <img
                            src={item.mockup}
                            alt={`${item.product_name || 'Item'} front`}
                            className='aspect-square w-full object-cover'
                            loading='lazy'
                          />
                        </a>
                      ) : null}
                      {item.mockup_back ? (
                        <a
                          href={item.mockup_back}
                          target='_blank'
                          rel='noreferrer'
                          className='overflow-hidden rounded-[6px] border border-border/80'
                        >
                          <img
                            src={item.mockup_back}
                            alt={`${item.product_name || 'Item'} back`}
                            className='aspect-square w-full object-cover'
                            loading='lazy'
                          />
                        </a>
                      ) : null}
                      {(item.designs || []).map((design, designIndex) =>
                        design.pdf_url ? (
                          <a
                            key={`${String(item.id || index)}-pdf-${designIndex}`}
                            href={design.pdf_url}
                            target='_blank'
                            rel='noreferrer'
                            className='overflow-hidden rounded-[6px] border border-border/80'
                          >
                            <img
                              src={design.pdf_url}
                              alt={`${item.product_name || 'Item'} ${design.position || 'design'}`}
                              className='aspect-square w-full object-cover'
                              loading='lazy'
                            />
                          </a>
                        ) : null
                      )}
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>

            {canSeeSeller && qrCodes.length > 0 ? (
              <Card className={sectionCardClassName()}>
                <CardContent className='space-y-3 px-4 py-4'>
                  <div className='flex flex-wrap items-center justify-between gap-2'>
                    <p className='text-[13px] font-semibold text-foreground'>
                      {detailMessages.qrCodes} ({qrCodes.length})
                    </p>
                    <Button
                      type='button'
                      variant='outline'
                      size='sm'
                      className='rounded-[6px]'
                      onClick={handleDownloadAllQr}
                      disabled={downloadingAllQr}
                    >
                      {downloadingAllQr
                        ? detailMessages.downloadingAll
                        : detailMessages.downloadAll}
                    </Button>
                  </div>
                  <div className='grid gap-3 sm:grid-cols-2 xl:grid-cols-3'>
                  {qrCodes.map((qr, index) => (
                    <div
                      key={`${qr.label}-${index}`}
                      className='space-y-3 rounded-[6px] border border-border/80 p-3'
                    >
                      <a
                        href={qr.url}
                        target='_blank'
                        rel='noreferrer'
                        className='flex justify-center rounded-[6px] border border-border/70 bg-muted/20 p-2'
                      >
                        <img
                          src={qr.url}
                          alt={qr.label}
                          className='h-36 w-36 object-contain'
                          loading='lazy'
                        />
                      </a>
                      <div className='space-y-2'>
                        <div className='text-[12px] text-muted-foreground'>
                          {qr.label}
                        </div>
                        <Button
                          type='button'
                          variant='outline'
                          size='sm'
                          className='w-full rounded-[6px] text-[12px]'
                          onClick={() =>
                            void downloadQrFile(
                              qr.url,
                              `order_${order.order_stt || order.id}_qr_${index + 1}.png`
                            )
                          }
                        >
                          {detailMessages.download}
                        </Button>
                      </div>
                    </div>
                  ))}
                  </div>
                </CardContent>
              </Card>
            ) : null}

            {mergeImages.length > 0 ? (
              <Card className={sectionCardClassName()}>
                <CardContent className='space-y-3 px-4 py-4'>
                  <p className='text-[13px] font-semibold text-foreground'>
                    {detailMessages.mergedImages} ({mergeImages.length})
                  </p>
                  <div className='grid gap-3 sm:grid-cols-2 xl:grid-cols-3'>
                    {mergeImages.map((image, index) => (
                      <button
                        type='button'
                        key={`${image.label}-${index}`}
                        onClick={() => {
                          window.open(image.url, '_blank', 'noopener,noreferrer')
                          void downloadQrFile(
                            image.url,
                            `order_${order.order_stt || order.id}_merge_${index + 1}.png`
                          )
                        }}
                        className='space-y-2 rounded-[6px] border border-border/80 p-3 text-left hover:bg-muted/30'
                      >
                        <img
                          src={image.url}
                          alt={image.label}
                          className='aspect-square w-full rounded-[6px] object-cover'
                          loading='lazy'
                        />
                        <div className='text-[12px] text-muted-foreground'>{image.label}</div>
                      </button>
                    ))}
                  </div>
                </CardContent>
              </Card>
            ) : null}
          </div>

          <div className='space-y-4'>
            <Card className={sectionCardClassName()}>
              <CardContent className='flex flex-col gap-2 px-4 py-4'>
                <p className='text-[13px] font-semibold text-foreground'>
                  {detailMessages.actionsTitle}
                </p>
                <Button
                  type='button'
                  variant='outline'
                  className='w-full justify-start rounded-[6px] text-sky-700 hover:text-sky-800 dark:text-sky-300 dark:hover:text-sky-200'
                  onClick={() => setTimelineOpen(true)}
                >
                  <Clock className='h-3.5 w-3.5' />
                  {ordersMessages.actions.timeline}
                </Button>

                {canEdit ? (
                  <Button
                    type='button'
                    variant='outline'
                    className='w-full justify-start rounded-[6px] text-amber-700 hover:text-amber-800 dark:text-amber-300 dark:hover:text-amber-200'
                    onClick={() => setEditOpen(true)}
                  >
                    <Pencil className='h-3.5 w-3.5' />
                    {ordersMessages.actions.edit}
                  </Button>
                ) : null}

                {canUseSupport ? (
                  <Button
                    type='button'
                    variant='outline'
                    className='w-full justify-start rounded-[6px] text-violet-700 hover:text-violet-800 dark:text-violet-300 dark:hover:text-violet-200'
                    onClick={handleSupportClick}
                  >
                    <Ticket className='h-3.5 w-3.5' />
                    {ordersMessages.actions.support}
                  </Button>
                ) : null}

                {canUpdateLabel ? (
                  <Button
                    type='button'
                    variant='outline'
                    className='w-full justify-start rounded-[6px] text-emerald-700 hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200'
                    onClick={handleUpdateLabel}
                    disabled={updatingLabel}
                  >
                    {updatingLabel ? (
                      <LoaderCircle className='h-3.5 w-3.5 animate-spin' />
                    ) : (
                      <Truck className='h-3.5 w-3.5' />
                    )}
                    {updatingLabel
                      ? detailMessages.updatingLabel
                      : detailMessages.updateLabel}
                  </Button>
                ) : null}

                {canSeeVideos ? (
                  <Button
                    type='button'
                    variant='outline'
                    className='w-full justify-start rounded-[6px] text-fuchsia-700 hover:text-fuchsia-800 dark:text-fuchsia-300 dark:hover:text-fuchsia-200'
                    onClick={() => router.push(`/lemiex/videos?order_id=${order.id}`)}
                  >
                    <Video className='h-3.5 w-3.5' />
                    {detailMessages.videos}
                  </Button>
                ) : null}
              </CardContent>
            </Card>

            {order.pricing ? (
              <Card className={sectionCardClassName()}>
                <CardContent className='space-y-3 px-4 py-4 text-[13px]'>
                  <p className='text-[13px] font-semibold text-foreground'>
                    {detailMessages.pricing}
                  </p>
                  <div className='flex items-center justify-between'>
                    <span className='text-muted-foreground'>{detailMessages.printCost}</span>
                    <span className='font-medium text-emerald-700 dark:text-emerald-400'>
                      {formatCurrency(order.pricing.print_cost)}
                    </span>
                  </div>
                  <div className='flex items-center justify-between'>
                    <span className='text-muted-foreground'>{detailMessages.shippingCost}</span>
                    <span className='font-medium text-emerald-700 dark:text-emerald-400'>
                      {formatCurrency(order.pricing.shipping_cost)}
                    </span>
                  </div>
                  {(order.pricing.extra_fee || 0) > 0 ? (
                    <div className='flex items-center justify-between'>
                      <span className='text-muted-foreground'>{detailMessages.extraFee}</span>
                      <span className='font-medium text-amber-700 dark:text-amber-400'>
                        {formatCurrency(order.pricing.extra_fee)}
                      </span>
                    </div>
                  ) : null}
                  {(order.pricing.refund_fee || 0) > 0 ? (
                    <div className='flex items-center justify-between'>
                      <span className='text-muted-foreground'>{detailMessages.refundFee}</span>
                      <span className='font-medium text-rose-600 dark:text-rose-400'>
                        -{formatCurrency(order.pricing.refund_fee)}
                      </span>
                    </div>
                  ) : null}
                  <div className='flex items-center justify-between border-t border-border/70 pt-3 text-[14px] font-semibold'>
                    <span>{detailMessages.totalCost}</span>
                    <span className='text-rose-600 dark:text-rose-400'>
                      {formatCurrency(order.pricing.total_cost)}
                    </span>
                  </div>
                </CardContent>
              </Card>
            ) : null}
          </div>
        </div>
      </Main>

      <OrderTimelineDialog
        open={timelineOpen}
        onOpenChange={setTimelineOpen}
        orderId={order.id}
        orderLabel={order.order_stt || order.id}
      />

      <OrderEditDialog
        open={editOpen}
        onOpenChange={setEditOpen}
        orderId={order.id}
        onUpdated={loadOrder}
        user={currentUser}
      />

      <Dialog open={ticketExistsOpen} onOpenChange={setTicketExistsOpen}>
        <DialogContent className='rounded-[6px] sm:max-w-md'>
          <DialogHeader>
            <DialogTitle>{ordersMessages.actions.ticketExistsTitle}</DialogTitle>
            <DialogDescription>
              {ordersMessages.actions.ticketExistsDesc}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className='sm:justify-start'>
            <Button
              type='button'
              className='rounded-[6px]'
              onClick={() => {
                setTicketExistsOpen(false)
                router.push(`/lemiex/tickets?order_id=${order.id}`)
              }}
            >
              {ordersMessages.actions.viewExistingTickets}
            </Button>
            <Button
              type='button'
              variant='outline'
              className='rounded-[6px]'
              onClick={() => {
                setTicketExistsOpen(false)
                router.push(`/lemiex/tickets?order_id=${order.id}&action=create`)
              }}
            >
              {ordersMessages.actions.createNewTicket}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
