'use client'

import { useEffect, useMemo, useState } from 'react'
import { LoaderCircle, Plus, Replace, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { useI18n } from '@/context/i18n-provider'
import { LemiexFileUploadInput } from '@/features/lemiex/components/lemiex-file-upload-input'
import { DESIGN_POSITION_OPTIONS, SHIPPING_SERVICE_OPTIONS } from '@/features/lemiex/orders/create/constants'
import { ProductVariantPicker } from '@/features/lemiex/orders/create/product-variant-picker'
import { getUserRoleName } from '@/services/auth/api'
import {
  fetchOrderById,
  type OrderDetail,
  type ProductVariantDetail,
  type UpdateOrderPayload,
  updateOrder,
} from '@/services/orders/api'
import { type AuthUser } from '@/stores/auth-store'

type OrderEditDialogProps = {
  orderId: number | string
  open: boolean
  onOpenChange: (open: boolean) => void
  onUpdated?: () => void
  user: AuthUser | null
}

type EditFormState = {
  shipping_method: string
  shipping_service: string
  shipping_label: string
  note: string
  name: string
  street1: string
  street2: string
  city: string
  state: string
  zip: string
  country: string
  phone: string
}

type EditDesignState = {
  position: string
  pdf_url: string
  emb_url: string
  pes_url: string
}

type EditItemState = {
  id?: number | string
  product_name: string
  quantity: number
  variant_id: string
  color: string
  size: string
  mockup: string
  mockup_back: string
  designs: EditDesignState[]
  // Original variant snapshot — used to detect a swap and to revert. color/size
  // above stay the original values (display only); they refresh after saving.
  orig_variant_id: string
  orig_product_name: string
  // Descriptive name of the newly picked variant, shown after a swap.
  variant_label: string
}

const SHIPPING_METHOD_OPTIONS = [{ value: 'standard', label: 'Standard' }] as const

function fieldClassName() {
  return 'h-9 rounded-[6px] text-[13px]'
}

function designPositions(isPrintOrder: boolean) {
  return isPrintOrder
    ? [
        { value: 'front', label: 'Front' },
        { value: 'wrap', label: 'Wrap' },
      ]
    : DESIGN_POSITION_OPTIONS
}

function cleanValue(value: string | null | undefined) {
  if (value === null || value === undefined || value === '') return null
  return typeof value === 'string' ? value.trim() : value
}

function initFormState(order: OrderDetail): EditFormState {
  const shipping = order.shipping || {}
  const address = shipping.address || {}
  const fullName =
    address.name ||
    `${order.first_name || ''} ${order.last_name || ''}`.trim() ||
    ''

  return {
    shipping_method: order.shipping_method || shipping.method || '',
    shipping_service: order.shipping_service || shipping.service || '',
    shipping_label: order.shipping_label || shipping.label_url || '',
    note: order.note || '',
    name: fullName,
    street1: address.street1 || order.address_1 || '',
    street2: address.street2 || order.address_2 || '',
    city: address.city || order.city || '',
    state: address.state || order.state || '',
    zip: address.zip || order.postcode || '',
    country: address.country || order.country || '',
    phone: address.phone || order.phone || '',
  }
}

function initItems(order: OrderDetail): EditItemState[] {
  return (order.items || []).map((item) => ({
    id: item.id,
    product_name: item.product_name || '',
    quantity: Number(item.quantity || 0),
    variant_id: item.variant_id || '',
    color: item.color || item.variant?.color || '',
    size: item.size || item.variant?.size || '',
    mockup: item.mockup || '',
    mockup_back: item.mockup_back || '',
    designs: (item.designs || []).map((design) => ({
      position: design.position || '',
      pdf_url: design.pdf_url || '',
      emb_url: design.emb_url || '',
      pes_url: design.pes_url || '',
    })),
    orig_variant_id: item.variant_id || '',
    orig_product_name: item.product_name || '',
    variant_label: '',
  }))
}

export function OrderEditDialog({
  orderId,
  open,
  onOpenChange,
  onUpdated,
  user,
}: OrderEditDialogProps) {
  const { messages } = useI18n()
  const editMessages = messages.orders.editForm
  const detailMessages = messages.orders.detail
  const role = getUserRoleName(user)

  const [order, setOrder] = useState<OrderDetail | null>(null)
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [loadError, setLoadError] = useState('')
  const [blockReason, setBlockReason] = useState<string | null>(null)
  const [formData, setFormData] = useState<EditFormState | null>(null)
  const [items, setItems] = useState<EditItemState[]>([])
  const [openVariantPickers, setOpenVariantPickers] = useState<Set<number>>(
    () => new Set()
  )

  const isPrintOrder = order?.order_type === 'Tumbler'
  const availablePositions = useMemo(
    () => designPositions(Boolean(isPrintOrder)),
    [isPrintOrder]
  )
  // Variant can only be swapped before the order enters production AND before it
  // is paid/refunded, matching the backend gate (avoids stock/production side
  // effects and breaking an already-settled payment).
  const canChangeVariant =
    ['new_order', 'on_hold'].includes(order?.fulfill_status || '') &&
    !['paid', 'full_refund', 'partial_refund'].includes(
      order?.payment_status || ''
    )

  useEffect(() => {
    if (!open) return

    let active = true

    const run = async () => {
      setLoading(true)
      setLoadError('')
      setBlockReason(null)

      try {
        const fullOrder = await fetchOrderById(orderId)
        if (!active) return

        setOrder(fullOrder)
        setFormData(initFormState(fullOrder))
        setItems(initItems(fullOrder))
        setOpenVariantPickers(new Set())

        if (role === 'Admin' || role === 'Staff') {
          setBlockReason(null)
        } else if (
          role === 'Seller' &&
          !['new_order', 'on_hold'].includes(fullOrder.fulfill_status || '')
        ) {
          setBlockReason(
            editMessages.sellerBlockReason.replace(
              '{status}',
              String(fullOrder.fulfill_status || '')
            )
          )
        } else {
          setBlockReason(null)
        }
      } catch (error) {
        if (!active) return
        setLoadError(
          error instanceof Error ? error.message : editMessages.loadingFailed
        )
      } finally {
        if (active) setLoading(false)
      }
    }

    void run()
    return () => {
      active = false
    }
  }, [editMessages.loadingFailed, editMessages.sellerBlockReason, open, orderId, role])

  function updateFormField<K extends keyof EditFormState>(
    key: K,
    value: EditFormState[K]
  ) {
    setFormData((prev) => (prev ? { ...prev, [key]: value } : prev))
  }

  function updateItem(index: number, key: keyof EditItemState, value: unknown) {
    setItems((prev) =>
      prev.map((item, itemIndex) =>
        itemIndex === index ? { ...item, [key]: value } : item
      )
    )
  }

  function toggleVariantPicker(index: number) {
    setOpenVariantPickers((prev) => {
      const next = new Set(prev)
      if (next.has(index)) {
        next.delete(index)
      } else {
        next.add(index)
      }
      return next
    })
  }

  function handleVariantResolved(index: number, variant: ProductVariantDetail) {
    const label =
      variant.full_name || variant.product_name || variant.name || variant.variant_id
    setItems((prev) =>
      prev.map((item, itemIndex) =>
        itemIndex === index
          ? {
              ...item,
              variant_id: variant.variant_id,
              product_name:
                variant.full_name ||
                variant.product_name ||
                variant.name ||
                item.product_name,
              variant_label: label,
            }
          : item
      )
    )
  }

  function revertVariant(index: number) {
    setItems((prev) =>
      prev.map((item, itemIndex) =>
        itemIndex === index
          ? {
              ...item,
              variant_id: item.orig_variant_id,
              product_name: item.orig_product_name,
              variant_label: '',
            }
          : item
      )
    )
  }

  function updateDesign(
    itemIndex: number,
    designIndex: number,
    key: keyof EditDesignState,
    value: string
  ) {
    setItems((prev) =>
      prev.map((item, currentItemIndex) => {
        if (currentItemIndex !== itemIndex) return item
        return {
          ...item,
          designs: item.designs.map((design, currentDesignIndex) =>
            currentDesignIndex === designIndex
              ? { ...design, [key]: value }
              : design
          ),
        }
      })
    )
  }

  function addDesign(itemIndex: number) {
    setItems((prev) =>
      prev.map((item, currentItemIndex) =>
        currentItemIndex === itemIndex
          ? {
              ...item,
              designs: [
                ...item.designs,
                { position: '', pdf_url: '', emb_url: '', pes_url: '' },
              ],
            }
          : item
      )
    )
  }

  function removeDesign(itemIndex: number, designIndex: number) {
    setItems((prev) =>
      prev.map((item, currentItemIndex) =>
        currentItemIndex === itemIndex
          ? {
              ...item,
              designs: item.designs.filter(
                (_, currentDesignIndex) => currentDesignIndex !== designIndex
              ),
            }
          : item
      )
    )
  }

  async function handleSave() {
    if (!order || !formData || blockReason) return

    const calculatedOrderType = !order.convert_label ? 'seller_ship' : 'label_ship'

    const payload: UpdateOrderPayload = {
      id: order.id,
      order_type: calculatedOrderType,
      ref_id: order.ref_id,
      // api_key is resolved server-side from the order's store; it is never
      // exposed to the client (hidden from sellers). Sending an empty value is
      // safe — the backend ignores it (nullable, not an updatable field).
      api_key: order.store?.api_key ?? '',
      order_status: order.fulfill_status,
      shipping_method: cleanValue(formData.shipping_method),
      shipping_service: cleanValue(formData.shipping_service),
      note: cleanValue(formData.note),
      shipping_label:
        cleanValue(formData.shipping_label) ||
        cleanValue(order.shipping_label) ||
        cleanValue(order.convert_label) ||
        cleanValue(order.shipping?.label_url) ||
        null,
      address: {
        name: cleanValue(formData.name),
        street1: cleanValue(formData.street1),
        street2: cleanValue(formData.street2),
        city: cleanValue(formData.city),
        state: cleanValue(formData.state),
        zip: cleanValue(formData.zip),
        country: cleanValue(formData.country),
        phone: cleanValue(formData.phone),
      },
      line_items: items.map((item) => ({
        id: item.id ?? null,
        variant_id: item.variant_id,
        product_name: item.product_name,
        quantity: Number(item.quantity) || 0,
        mockup: cleanValue(item.mockup),
        mockup_back: cleanValue(item.mockup_back),
        print_files: item.designs
          .filter(
            (design) =>
              design.position &&
              (design.pdf_url || design.emb_url || design.pes_url)
          )
          .map((design) => {
            const file = {
              key: design.position,
              url: cleanValue(design.pdf_url),
            } as UpdateOrderPayload['line_items'][number]['print_files'][number]

            if (!isPrintOrder) {
              file.url_emb = cleanValue(design.emb_url)
              file.url_pes = cleanValue(design.pes_url)
            }

            return file
          }),
      })),
    }

    try {
      setSaving(true)
      const response = await updateOrder(payload)

      if (response.message === 'No changes detected') {
        toast.info(editMessages.noChanges)
      } else {
        toast.success(editMessages.saveSuccess)
      }

      onUpdated?.()
      onOpenChange(false)
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : editMessages.saveFailed
      )
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='!w-[88vw] !max-w-[1380px] max-h-[84vh] overflow-hidden rounded-[6px] p-0 sm:!max-w-[1380px]'>
        <DialogHeader className='border-b border-border/60 px-4 py-3 text-left'>
          <DialogTitle className='text-[16px] font-semibold'>
            {editMessages.title} #{order?.order_stt || orderId}
          </DialogTitle>
          <DialogDescription className='text-[12px]'>
            {editMessages.reference}: {order?.ref_id || detailMessages.noData}
          </DialogDescription>
        </DialogHeader>

        <div className='max-h-[calc(84vh-140px)] space-y-4 overflow-y-auto px-3 py-3 pb-24 sm:px-4 sm:py-4 sm:pb-24 lg:px-5'>
          {loading ? (
            <div className='flex min-h-[220px] items-center justify-center text-[13px] text-muted-foreground'>
              <span className='inline-flex items-center gap-2'>
                <LoaderCircle className='h-4 w-4 animate-spin' />
                {editMessages.loading}
              </span>
            </div>
          ) : loadError ? (
            <div className='rounded-[6px] border border-destructive/20 bg-destructive/5 px-4 py-3 text-[13px] text-destructive'>
              {loadError}
            </div>
          ) : formData ? (
            <>
              {blockReason ? (
                <div className='rounded-[6px] border border-destructive/20 bg-destructive/5 px-4 py-3 text-[13px] text-destructive'>
                  <strong>{editMessages.cannotEdit}: </strong>
                  {blockReason}
                </div>
              ) : null}

              <div className='space-y-5'>
                <div className='overflow-hidden rounded-[6px] border border-border/80'>
                  <div className='border-b border-border/60 px-4 py-3 text-[14px] font-semibold'>
                    {editMessages.generalInformation}
                  </div>
                  <div className='space-y-4 px-4 py-4'>
                    <div className='space-y-1.5'>
                      <label className='text-[12px] font-medium'>{editMessages.note}</label>
                      <Textarea
                        value={formData.note}
                        onChange={(event) => updateFormField('note', event.target.value)}
                        className='min-h-[96px] rounded-[6px] text-[13px]'
                      />
                    </div>
                  </div>
                </div>

                <div className='overflow-hidden rounded-[6px] border border-border/80'>
                  <div className='border-b border-border/60 px-4 py-3 text-[14px] font-semibold'>
                    {editMessages.shippingDetails}
                  </div>
                  <div className='grid gap-4 px-4 py-4 md:grid-cols-2'>
                    <div className='space-y-1.5'>
                      <label className='text-[12px] font-medium'>{editMessages.shippingMethod}</label>
                      <Select
                        value={formData.shipping_method}
                        onValueChange={(value) => updateFormField('shipping_method', value)}
                      >
                        <SelectTrigger className='w-full rounded-[6px] text-[13px]'>
                          <SelectValue placeholder={editMessages.shippingMethod} />
                        </SelectTrigger>
                        <SelectContent>
                          {SHIPPING_METHOD_OPTIONS.map((method) => (
                            <SelectItem key={method.value} value={method.value}>
                              {method.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className='space-y-1.5'>
                      <label className='text-[12px] font-medium'>{editMessages.shippingService}</label>
                      <>
                        <Input
                          value={formData.shipping_service}
                          onChange={(event) =>
                            updateFormField('shipping_service', event.target.value)
                          }
                          list='edit-order-shipping-services'
                          className={fieldClassName()}
                        />
                        <datalist id='edit-order-shipping-services'>
                          {SHIPPING_SERVICE_OPTIONS.map((service) => (
                            <option key={service.value} value={service.value} />
                          ))}
                        </datalist>
                      </>
                    </div>
                    <div className='space-y-1.5 md:col-span-2'>
                      <label className='text-[12px] font-medium'>{editMessages.shippingLabelUrl}</label>
                      <Input
                        value={formData.shipping_label}
                        onChange={(event) => updateFormField('shipping_label', event.target.value)}
                        placeholder='https://...'
                        className={fieldClassName()}
                      />
                    </div>
                  </div>
                </div>

                <div className='overflow-hidden rounded-[6px] border border-border/80'>
                  <div className='border-b border-border/60 px-4 py-3 text-[14px] font-semibold'>
                    {editMessages.addressInformation}
                  </div>
                  <div className='grid gap-4 px-4 py-4 md:grid-cols-2 xl:grid-cols-4'>
                    <div className='min-w-0 space-y-1.5'>
                      <label className='text-[12px] font-medium'>{editMessages.fullName}</label>
                      <Input value={formData.name} onChange={(e) => updateFormField('name', e.target.value)} className={fieldClassName()} />
                    </div>
                    <div className='min-w-0 space-y-1.5 xl:col-span-2'>
                      <label className='text-[12px] font-medium'>{editMessages.addressLine1}</label>
                      <Input value={formData.street1} onChange={(e) => updateFormField('street1', e.target.value)} className={fieldClassName()} />
                    </div>
                    <div className='min-w-0 space-y-1.5'>
                      <label className='text-[12px] font-medium'>{editMessages.addressLine2}</label>
                      <Input value={formData.street2} onChange={(e) => updateFormField('street2', e.target.value)} className={fieldClassName()} />
                    </div>
                    <div className='min-w-0 space-y-1.5'>
                      <label className='text-[12px] font-medium'>{editMessages.city}</label>
                      <Input value={formData.city} onChange={(e) => updateFormField('city', e.target.value)} className={fieldClassName()} />
                    </div>
                    <div className='min-w-0 space-y-1.5'>
                      <label className='text-[12px] font-medium'>{editMessages.state}</label>
                      <Input value={formData.state} onChange={(e) => updateFormField('state', e.target.value)} className={fieldClassName()} />
                    </div>
                    <div className='min-w-0 space-y-1.5'>
                      <label className='text-[12px] font-medium'>{editMessages.zipCode}</label>
                      <Input value={formData.zip} onChange={(e) => updateFormField('zip', e.target.value)} className={fieldClassName()} />
                    </div>
                    <div className='min-w-0 space-y-1.5'>
                      <label className='text-[12px] font-medium'>{editMessages.country}</label>
                      <Input value={formData.country} onChange={(e) => updateFormField('country', e.target.value)} className={fieldClassName()} />
                    </div>
                    <div className='min-w-0 space-y-1.5'>
                      <label className='text-[12px] font-medium'>{editMessages.phone}</label>
                      <Input value={formData.phone} onChange={(e) => updateFormField('phone', e.target.value)} className={fieldClassName()} />
                    </div>
                  </div>
                </div>

                <div className='overflow-hidden rounded-[6px] border border-border/80'>
                  <div className='border-b border-border/60 px-4 py-3 text-[14px] font-semibold'>
                    {editMessages.orderItems}
                  </div>
                  <div className='space-y-4 px-4 py-4'>
                    {!canChangeVariant ? (
                      <div className='rounded-[6px] border border-border/60 bg-muted/20 px-3 py-2 text-[12px] text-muted-foreground'>
                        {editMessages.variantChangeLocked}
                      </div>
                    ) : null}
                    {items.map((item, itemIndex) => (
                      <div
                        key={String(item.id || itemIndex)}
                        className='overflow-hidden rounded-[6px] border border-border/80 bg-background'
                      >
                        <div className='flex flex-col gap-3 border-b border-border/60 px-4 py-3 lg:flex-row lg:items-start lg:justify-between'>
                          <div className='min-w-0'>
                            <div className='text-[15px] font-semibold'>{item.product_name}</div>
                            <div className='mt-1 flex flex-wrap gap-2 text-[12px] text-muted-foreground'>
                              {item.color ? <span>{item.color}</span> : null}
                              {item.size ? <span>{item.size}</span> : null}
                              <span>Qty: {item.quantity}</span>
                            </div>
                            <div className='mt-1 text-[12px] text-muted-foreground'>
                              {editMessages.currentVariant}:{' '}
                              <span className='font-medium text-foreground'>
                                {item.variant_id || detailMessages.noData}
                              </span>
                            </div>
                          </div>
                          {canChangeVariant ? (
                            <Button
                              type='button'
                              variant='outline'
                              size='sm'
                              className='shrink-0 rounded-[6px]'
                              onClick={() => toggleVariantPicker(itemIndex)}
                            >
                              <Replace className='h-3.5 w-3.5' />
                              {editMessages.changeVariant}
                            </Button>
                          ) : null}
                        </div>

                        {canChangeVariant && openVariantPickers.has(itemIndex) ? (
                          <div className='space-y-3 border-b border-border/60 bg-muted/10 px-4 py-4'>
                            <ProductVariantPicker
                              value={item.variant_id}
                              onVariantResolved={(variant) =>
                                handleVariantResolved(itemIndex, variant)
                              }
                            />
                            {item.variant_id !== item.orig_variant_id ? (
                              <div className='flex flex-col gap-2 rounded-[6px] border border-primary/30 bg-primary/5 px-3 py-2 text-[12px] sm:flex-row sm:items-center sm:justify-between'>
                                <div className='min-w-0'>
                                  <span className='text-muted-foreground'>
                                    {editMessages.newVariant}:{' '}
                                  </span>
                                  <span className='font-medium text-foreground'>
                                    {item.variant_label || item.product_name} ({item.variant_id})
                                  </span>
                                  <div className='mt-0.5 text-muted-foreground'>
                                    {editMessages.variantChangedHint}
                                  </div>
                                </div>
                                <Button
                                  type='button'
                                  variant='ghost'
                                  size='sm'
                                  className='shrink-0 rounded-[6px]'
                                  onClick={() => revertVariant(itemIndex)}
                                >
                                  {editMessages.revertVariant}
                                </Button>
                              </div>
                            ) : null}
                          </div>
                        ) : null}

                        <div className='px-4 py-4 lg:px-5'>
                          <div className='grid gap-5 xl:grid-cols-[minmax(320px,0.9fr)_minmax(0,1.4fr)]'>
                            <div className='rounded-[6px] border border-border/70 bg-muted/5'>
                              <div className='border-b border-border/60 px-4 py-3 text-[12px] font-semibold uppercase tracking-wide text-muted-foreground'>
                                {editMessages.mockupImages}
                              </div>
                              <div className='space-y-4 px-4 py-4'>
                                <div className='min-w-0 space-y-1.5'>
                                  <label className='text-[12px] font-medium'>{editMessages.frontViewUrl}</label>
                                  <LemiexFileUploadInput
                                    value={item.mockup}
                                    onChange={(value) => updateItem(itemIndex, 'mockup', value)}
                                    type='mockup'
                                    placeholder='https://...'
                                    orderId={order?.id}
                                    showPreview={false}
                                  />
                                </div>
                                <div className='min-w-0 space-y-1.5'>
                                  <label className='text-[12px] font-medium'>{editMessages.backViewUrl}</label>
                                  <LemiexFileUploadInput
                                    value={item.mockup_back}
                                    onChange={(value) => updateItem(itemIndex, 'mockup_back', value)}
                                    type='mockup'
                                    placeholder='https://...'
                                    orderId={order?.id}
                                    showPreview={false}
                                  />
                                </div>
                              </div>
                            </div>

                            <div className='rounded-[6px] border border-border/70 bg-muted/5'>
                              <div className='flex flex-col gap-3 border-b border-border/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between'>
                                <div className='text-[12px] font-semibold uppercase tracking-wide text-muted-foreground'>
                                  {editMessages.printFilesDesigns}
                                </div>
                                <Button
                                  type='button'
                                  variant='outline'
                                  size='sm'
                                  className='w-full rounded-[6px] sm:w-auto'
                                  onClick={() => addDesign(itemIndex)}
                                >
                                  <Plus className='h-3.5 w-3.5' />
                                  {editMessages.addPosition}
                                </Button>
                              </div>

                              <div className='space-y-4 px-4 py-4'>
                                {item.designs.map((design, designIndex) => (
                                  <div
                                    key={`${String(item.id || itemIndex)}-${designIndex}`}
                                    className='rounded-[6px] border border-border/80 bg-background p-4'
                                  >
                                    <div className='mb-4 flex flex-col gap-2 sm:flex-row sm:items-center'>
                                      <div className='min-w-0 flex-1'>
                                        <Select
                                          value={design.position}
                                          onValueChange={(value) =>
                                            updateDesign(itemIndex, designIndex, 'position', value)
                                          }
                                        >
                                          <SelectTrigger className='w-full rounded-[6px] text-[13px]'>
                                            <SelectValue placeholder={editMessages.positionPlaceholder} />
                                          </SelectTrigger>
                                          <SelectContent>
                                            {availablePositions.map((position) => (
                                              <SelectItem key={position.value} value={position.value}>
                                                {position.label}
                                              </SelectItem>
                                            ))}
                                          </SelectContent>
                                        </Select>
                                      </div>
                                      <Button
                                        type='button'
                                        variant='outline'
                                        size='icon'
                                        className='h-9 w-full shrink-0 rounded-[6px] sm:w-9'
                                        onClick={() => removeDesign(itemIndex, designIndex)}
                                      >
                                        <Trash2 className='h-3.5 w-3.5' />
                                      </Button>
                                    </div>

                                    <div className='space-y-3'>
                                      <div className='space-y-1.5'>
                                        <label className='text-[12px] font-semibold uppercase tracking-wide text-muted-foreground'>
                                          {editMessages.url}
                                        </label>
                                        <LemiexFileUploadInput
                                          value={design.pdf_url}
                                          onChange={(value) =>
                                            updateDesign(itemIndex, designIndex, 'pdf_url', value)
                                          }
                                          type='design'
                                          accept='image/png,.png'
                                          placeholder='https://...'
                                          orderId={order?.id}
                                          itemId={item.id}
                                          position={design.position}
                                        />
                                        {design.pdf_url ? (
                                          <a
                                            href={design.pdf_url}
                                            target='_blank'
                                            rel='noreferrer'
                                            className='inline-flex h-8 items-center justify-center rounded-[6px] border border-border px-3 text-[12px] hover:bg-muted'
                                          >
                                            {editMessages.viewFile}
                                          </a>
                                        ) : null}
                                      </div>

                                    </div>
                                  </div>
                                ))}

                                {item.designs.length === 0 ? (
                                  <div className='rounded-[6px] border border-dashed border-border px-4 py-6 text-center text-[13px] text-muted-foreground'>
                                    {editMessages.noPrintFiles}
                                  </div>
                                ) : null}
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </>
          ) : null}
        </div>

        <DialogFooter className='sticky bottom-0 z-20 border-t border-border/60 bg-background px-4 py-3 shadow-[0_-8px_24px_rgba(15,23,42,0.06)]'>
          <Button
            type='button'
            variant='outline'
            className='rounded-[6px]'
            onClick={() => onOpenChange(false)}
          >
            {editMessages.cancel}
          </Button>
          <Button
            type='button'
            className='rounded-[6px]'
            onClick={() => void handleSave()}
            disabled={loading || saving || Boolean(blockReason) || Boolean(loadError)}
          >
            {saving ? (
              <>
                <LoaderCircle className='h-3.5 w-3.5 animate-spin' />
                {editMessages.saving}
              </>
            ) : (
              editMessages.saveChanges
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
