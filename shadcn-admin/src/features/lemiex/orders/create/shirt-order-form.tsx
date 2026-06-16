'use client'

import { useEffect, useMemo, useState } from 'react'
import { useRouter } from 'next/navigation'
import { useFieldArray, useForm } from 'react-hook-form'
import {
  ArrowLeft,
  LoaderCircle,
  Package2,
  Plus,
  Save,
  Trash2,
  Truck,
} from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexFileUploadInput } from '@/features/lemiex/components/lemiex-file-upload-input'
import { useI18n } from '@/context/i18n-provider'
import {
  createOrder,
  fetchFulfillmentPriorities,
  fetchShippingMethods,
  fetchStores,
  type CreateLabelShipPayload,
  type CreateOrderLineItemPayload,
  type CreateOrderPrintFilePayload,
  type CreateSellerShipPayload,
  type FulfillmentPriorityOption,
  type ShippingMethodOption,
  type StoreOption,
} from '@/services/orders/api'
import {
  COUNTRY_OPTIONS,
  DESIGN_POSITION_OPTIONS,
  ORDER_STATUS_OPTIONS,
  SHIPPING_SERVICE_OPTIONS,
} from './constants'
import { ProductVariantPicker } from './product-variant-picker'

type ShirtOrderMode = 'label-ship' | 'seller-ship'

type ShirtOrderFormProps = {
  mode: ShirtOrderMode
}

type AddressFormValues = {
  name: string
  phone: string
  street1: string
  street2: string
  city: string
  state: string
  zip: string
  country: string
}

type PrintFileFormValues = CreateOrderPrintFilePayload

type LineItemFormValues = CreateOrderLineItemPayload

type ShirtOrderFormValues = {
  ref_id: string
  api_key: string
  seller_ref: string
  order_status: string
  shipping_method: string
  shipping_service: string
  shipping_label: string
  fulfillment_priority: string
  note: string
  address: AddressFormValues
  line_items: LineItemFormValues[]
}

const defaultPrintFile = (): PrintFileFormValues => ({
  key: 'front',
  url: '',
  url_emb: '',
  url_pes: '',
  embroidery_type: '',
})

const defaultLineItem = (): LineItemFormValues => ({
  variant_id: '',
  product_name: '',
  quantity: 1,
  mockup: '',
  mockup_back: '',
  mockup_sleeve_left: '',
  mockup_sleeve_right: '',
  print_files: [defaultPrintFile()],
})

const defaultValues: ShirtOrderFormValues = {
  ref_id: '',
  api_key: '',
  seller_ref: '',
  order_status: 'new_order',
  shipping_method: 'standard',
  shipping_service: 'USPS',
  shipping_label: '',
  fulfillment_priority: 'normal',
  note: '',
  address: {
    name: '',
    phone: '',
    street1: '',
    street2: '',
    city: '',
    state: '',
    zip: '',
    country: 'US',
  },
  line_items: [defaultLineItem()],
}

function sectionCardClassName() {
  return 'rounded-[6px] border-border/80 shadow-none'
}

function fieldClassName() {
  return 'h-9 rounded-[6px] text-[13px]'
}

function resolvePreviewUrl(url?: string) {
  if (!url) return ''
  if (url.startsWith('//')) return `https:${url}`
  return url
}

function modeMeta(mode: ShirtOrderMode) {
  return mode
}

function replaceMessage(template: string, values: Record<string, string | number>) {
  return Object.entries(values).reduce(
    (message, [key, value]) =>
      message.replaceAll(`{${key}}`, String(value)),
    template
  )
}

function validateFormValues(
  mode: ShirtOrderMode,
  values: ShirtOrderFormValues,
  validationMessages: {
    orderRefRequired: string
    apiKeyRequired: string
    shippingLabelRequired: string
    shippingAddressRequired: string
    variantRequired: string
    productNameRequired: string
    mockupRequired: string
    designFileRequired: string
  }
) {
  if (!values.ref_id.trim()) return validationMessages.orderRefRequired
  if (!values.api_key.trim()) return validationMessages.apiKeyRequired
  if (mode === 'label-ship' && !values.shipping_label.trim()) {
    return validationMessages.shippingLabelRequired
  }

  if (mode === 'seller-ship' || mode === 'label-ship') {
    const requiredAddressFields: Array<keyof AddressFormValues> = [
      'name',
      'street1',
      'city',
      'state',
      'zip',
      'country',
    ]

    for (const field of requiredAddressFields) {
      if (!values.address[field]?.trim()) {
        return validationMessages.shippingAddressRequired
      }
    }
  }

  for (const item of values.line_items) {
    if (!item.variant_id.trim()) return validationMessages.variantRequired
    if (!item.product_name.trim()) return validationMessages.productNameRequired
    if (!item.mockup.trim()) return validationMessages.mockupRequired
    if (!item.print_files.length) return validationMessages.designFileRequired
  }

  return null
}

type LineItemCardProps = {
  index: number
  control: ReturnType<typeof useForm<ShirtOrderFormValues>>['control']
  register: ReturnType<typeof useForm<ShirtOrderFormValues>>['register']
  setValue: ReturnType<typeof useForm<ShirtOrderFormValues>>['setValue']
  watch: ReturnType<typeof useForm<ShirtOrderFormValues>>['watch']
  canRemove: boolean
  onRemove: () => void
}

function LineItemCard({
  index,
  control,
  register,
  setValue,
  watch,
  canRemove,
  onRemove,
}: LineItemCardProps) {
  const { messages } = useI18n()
  const createFormMessages = messages.orders.createForm
  const optionLabels = createFormMessages.optionLabels
  const item = watch(`line_items.${index}`)
  const {
    fields: printFileFields,
    append,
    remove,
  } = useFieldArray({
    control,
    name: `line_items.${index}.print_files`,
  })

  return (
    <Card className={sectionCardClassName()}>
      <CardHeader className='flex flex-col gap-3 border-b border-border/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between'>
        <div className='space-y-1'>
          <CardTitle className='text-[14px] font-semibold'>
            {replaceMessage(createFormMessages.productCardTitle, {
              index: index + 1,
            })}
          </CardTitle>
          <p className='text-[12px] text-muted-foreground'>
            {createFormMessages.productCardDesc}
          </p>
        </div>
        {canRemove ? (
          <Button
            type='button'
            variant='outline'
            size='sm'
            className='w-full rounded-[6px] sm:w-auto'
            onClick={onRemove}
          >
            <Trash2 className='h-3.5 w-3.5' />
            {createFormMessages.remove}
          </Button>
        ) : null}
      </CardHeader>
      <CardContent className='space-y-5 px-4 py-4'>
        <div className='grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px]'>
          <div className='space-y-4'>
            <div className='space-y-1.5'>
              <label className='text-[12px] font-medium text-foreground'>
                {createFormMessages.productVariant}
              </label>
              <ProductVariantPicker
                value={item?.variant_id}
                onVariantResolved={(variant) => {
                  setValue(
                    `line_items.${index}.variant_id`,
                    variant.variant_id,
                    {
                      shouldDirty: true,
                      shouldTouch: true,
                    }
                  )
                  setValue(
                    `line_items.${index}.product_name`,
                    variant.full_name || variant.product_name || variant.name || '',
                    {
                      shouldDirty: true,
                      shouldTouch: true,
                    }
                  )
                }}
              />
            </div>

            <div className='grid gap-4 sm:grid-cols-[minmax(0,1fr)_120px]'>
              <div className='space-y-1.5'>
                <label className='text-[12px] font-medium text-foreground'>
                  {createFormMessages.variantId}
                </label>
                <Input
                  {...register(`line_items.${index}.variant_id`, {
                    required: true,
                  })}
                  readOnly
                  placeholder={createFormMessages.placeholders.variantId}
                  className={`${fieldClassName()} bg-muted/35`}
                />
              </div>
              <div className='space-y-1.5'>
                <label className='text-[12px] font-medium text-foreground'>
                  {createFormMessages.quantity}
                </label>
                <Input
                  {...register(`line_items.${index}.quantity`, {
                    required: true,
                    valueAsNumber: true,
                    min: 1,
                  })}
                  type='number'
                  min={1}
                  className={fieldClassName()}
                />
              </div>
            </div>

            <div className='space-y-1.5'>
                <label className='text-[12px] font-medium text-foreground'>
                  {createFormMessages.productName}
                </label>
              <Input
                {...register(`line_items.${index}.product_name`, {
                  required: true,
                })}
                placeholder={createFormMessages.placeholders.productName}
                className={fieldClassName()}
              />
            </div>

            <div className='grid gap-4 sm:grid-cols-2'>
              <div className='space-y-1.5 sm:col-span-2'>
                <label className='text-[12px] font-medium text-foreground'>
                  {createFormMessages.mockupFrontUrl}
                </label>
                <LemiexFileUploadInput
                  value={item?.mockup || ''}
                  onChange={(value) =>
                    setValue(`line_items.${index}.mockup`, value, {
                      shouldDirty: true,
                      shouldTouch: true,
                    })
                  }
                  type='mockup'
                  placeholder={createFormMessages.placeholders.mockupFront}
                  required
                  hint={createFormMessages.upload.uploadImageOrPaste}
                />
              </div>
              <div className='space-y-1.5 sm:col-span-2'>
                <label className='text-[12px] font-medium text-foreground'>
                  {createFormMessages.mockupBackUrl}
                </label>
                <LemiexFileUploadInput
                  value={item?.mockup_back || ''}
                  onChange={(value) =>
                    setValue(`line_items.${index}.mockup_back`, value, {
                      shouldDirty: true,
                      shouldTouch: true,
                    })
                  }
                  type='mockup'
                  placeholder={createFormMessages.placeholders.mockupBack}
                />
              </div>
            </div>
          </div>

          <div className='order-first space-y-3 xl:order-last'>
            <div className='rounded-[6px] border border-dashed border-border bg-muted/15 p-3'>
              <div className='mb-2 flex items-center gap-2 text-[12px] font-medium text-foreground'>
                <Package2 className='h-3.5 w-3.5' />
                {createFormMessages.mockupPreview}
              </div>
              {item?.mockup ? (
                <div className='overflow-hidden rounded-[6px] border border-border/80 bg-background'>
                  <img
                    src={resolvePreviewUrl(item.mockup)}
                    alt={item.product_name || createFormMessages.upload.previewAlt}
                    className='h-auto w-full object-cover'
                    loading='lazy'
                  />
                </div>
              ) : (
                <div className='flex aspect-square items-center justify-center rounded-[6px] border border-dashed border-border/80 bg-background text-[12px] text-muted-foreground'>
                  {createFormMessages.addFrontMockupUrl}
                </div>
              )}
            </div>
          </div>
        </div>

        <div className='space-y-3 rounded-[6px] border border-border/70 bg-muted/10 p-4'>
          <div className='flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
            <div className='min-w-0'>
              <h3 className='text-[13px] font-semibold text-foreground'>
                {createFormMessages.designFiles}
              </h3>
              <p className='text-[12px] text-muted-foreground'>
                {createFormMessages.designFilesDesc}
              </p>
            </div>
            <Button
              type='button'
              size='sm'
              className='w-full rounded-[6px] sm:w-auto'
              onClick={() => append({ ...defaultPrintFile(), key: 'back' })}
            >
              <Plus className='h-3.5 w-3.5' />
              {createFormMessages.addDesignSide}
            </Button>
          </div>

          <div className='space-y-3'>
            {printFileFields.map((field, fileIndex) => (
              <div
                key={field.id}
                className='rounded-[6px] border border-border/80 bg-background p-3'
              >
                <div className='mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
                  <div className='text-[13px] font-medium text-foreground'>
                    {replaceMessage(createFormMessages.designTitle, {
                      index: fileIndex + 1,
                    })}
                  </div>
                  {printFileFields.length > 1 ? (
                    <Button
                      type='button'
                      variant='outline'
                      size='sm'
                      className='w-full rounded-[6px] sm:w-auto'
                      onClick={() => remove(fileIndex)}
                      >
                        <Trash2 className='h-3.5 w-3.5' />
                        {createFormMessages.remove}
                      </Button>
                  ) : null}
                </div>

                <div className='grid gap-4 sm:grid-cols-2'>
                  <div className='space-y-1.5'>
                    <label className='text-[12px] font-medium text-foreground'>
                      {createFormMessages.position}
                    </label>
                    <Select
                      value={watch(`line_items.${index}.print_files.${fileIndex}.key`)}
                      onValueChange={(value) =>
                        setValue(
                          `line_items.${index}.print_files.${fileIndex}.key`,
                          value,
                          { shouldDirty: true, shouldTouch: true }
                        )
                      }
                    >
                      <SelectTrigger className='w-full rounded-[6px] text-[13px]'>
                        <SelectValue
                          placeholder={createFormMessages.placeholders.selectPosition}
                        />
                      </SelectTrigger>
                      <SelectContent>
                        {DESIGN_POSITION_OPTIONS.map((option) => (
                          <SelectItem key={option.value} value={option.value}>
                            {optionLabels.designPosition[
                              option.value as keyof typeof optionLabels.designPosition
                            ] || option.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className='space-y-1.5'>
                    <label className='text-[12px] font-medium text-foreground'>
                      {createFormMessages.designFileUrl}
                    </label>
                    <LemiexFileUploadInput
                      value={
                        watch(
                          `line_items.${index}.print_files.${fileIndex}.url`
                        ) || ''
                      }
                      onChange={(value) =>
                        setValue(
                          `line_items.${index}.print_files.${fileIndex}.url`,
                          value,
                          { shouldDirty: true, shouldTouch: true }
                        )
                      }
                      type='design'
                      accept='image/png,.png'
                      placeholder={createFormMessages.placeholders.designFileUrl}
                      position={watch(
                        `line_items.${index}.print_files.${fileIndex}.key`
                      )}
                    />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

export function ShirtOrderForm({ mode }: ShirtOrderFormProps) {
  const router = useRouter()
  const { messages } = useI18n()
  const createFormMessages = messages.orders.createForm
  const optionLabels = createFormMessages.optionLabels
  const resolvedMode = modeMeta(mode)
  const [stores, setStores] = useState<StoreOption[]>([])
  const [fulfillmentPriorities, setFulfillmentPriorities] = useState<
    FulfillmentPriorityOption[]
  >([])
  const [shippingMethods, setShippingMethods] = useState<ShippingMethodOption[]>([])
  const [loadingMeta, setLoadingMeta] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  const form = useForm<ShirtOrderFormValues>({
    defaultValues,
  })

  const { control, handleSubmit, register, setValue, watch } = form
  const { fields, append, remove } = useFieldArray({
    control,
    name: 'line_items',
  })

  const selectedStoreKey = watch('api_key')
  const selectedPriority = watch('fulfillment_priority')
  const selectedShippingMethod = watch('shipping_method')
  const storesWithApiKeys = useMemo(
    () => stores.filter((store) => Boolean(store.api_key)),
    [stores]
  )

  useEffect(() => {
    let active = true

    const run = async () => {
      setLoadingMeta(true)
      try {
        const [storesData, prioritiesData, shippingMethodsData] =
          await Promise.all([
            fetchStores(),
            fetchFulfillmentPriorities(),
            mode === 'seller-ship' ? fetchShippingMethods() : Promise.resolve([]),
          ])

        if (!active) return

        const nextStores = storesData.filter(Boolean) as StoreOption[]
        const nextFulfillmentPriorities = prioritiesData.filter(
          Boolean
        ) as FulfillmentPriorityOption[]
        const nextShippingMethods = shippingMethodsData.filter(
          Boolean
        ) as ShippingMethodOption[]

        setStores(nextStores)
        setFulfillmentPriorities(nextFulfillmentPriorities)
        setShippingMethods(nextShippingMethods)

        if (nextStores.length === 1 && nextStores[0]?.api_key && !form.getValues('api_key')) {
          setValue('api_key', nextStores[0].api_key, { shouldDirty: true })
        }
      } catch (error) {
        if (!active) return
        setStores([])
        setFulfillmentPriorities([
          {
            value: 'normal',
            label: createFormMessages.fulfillmentPriority,
            description: '',
          },
        ])
        setShippingMethods([
          {
            value: 'standard',
            label: createFormMessages.standardShippingMethod,
            description: '',
          },
        ])
        console.error(error)
      } finally {
        if (active) setLoadingMeta(false)
      }
    }

    void run()
    return () => {
      active = false
    }
  }, [form, mode, setValue])

  const selectedStore = useMemo(
    () => storesWithApiKeys.find((store) => store.api_key === selectedStoreKey) || null,
    [selectedStoreKey, storesWithApiKeys]
  )

  const selectedPriorityDescription = useMemo(
    () =>
      fulfillmentPriorities.find((priority) => priority.value === selectedPriority)
        ?.description || '',
    [fulfillmentPriorities, selectedPriority]
  )

  const selectedShippingMethodDescription = useMemo(
    () =>
      shippingMethods.find((method) => method.value === selectedShippingMethod)
        ?.description || '',
    [selectedShippingMethod, shippingMethods]
  )

  const onSubmit = handleSubmit(async (values) => {
    setSubmitting(true)
    try {
      const validationMessage = validateFormValues(
        mode,
        values,
        createFormMessages.validation
      )
      if (validationMessage) {
        toast.error(validationMessage)
        return
      }

      const lineItems = values.line_items.map((item) => ({
        ...item,
        quantity: Number(item.quantity) || 1,
        print_files: item.print_files.map((file) => ({
          key: file.key,
          url: file.url || '',
          url_emb: '',
          url_pes: '',
          embroidery_type: '',
        })),
      }))

      if (mode === 'label-ship') {
        const payload: CreateLabelShipPayload = {
          order_type: 'label_ship',
          product_type: 'Print',
          ref_id: values.ref_id,
          api_key: values.api_key,
          seller_ref: values.seller_ref,
          order_status: values.order_status,
          shipping_method: 'standard',
          shipping_service: values.shipping_service,
          shipping_label: values.shipping_label,
          fulfillment_priority: values.fulfillment_priority,
          note: values.note,
          address: values.address,
          line_items: lineItems,
        }

        const response = await createOrder(payload)
        toast.success(
          response.data?.order_id
            ? replaceMessage(createFormMessages.submit.successWithId, {
                id: String(response.data.order_id),
              })
            : createFormMessages.submit.success
        )
      } else {
        const payload: CreateSellerShipPayload = {
          order_type: 'seller_ship',
          product_type: 'Print',
          ref_id: values.ref_id,
          api_key: values.api_key,
          seller_ref: values.seller_ref,
          order_status: values.order_status,
          shipping_method: values.shipping_method,
          shipping_service: 'USPS',
          fulfillment_priority: values.fulfillment_priority,
          note: values.note,
          address: values.address,
          line_items: lineItems,
        }

        const response = await createOrder(payload)
        toast.success(
          response.data?.order_id
            ? replaceMessage(createFormMessages.submit.successWithId, {
                id: String(response.data.order_id),
              })
            : createFormMessages.submit.success
        )
      }

      router.push('/lemiex/orders')
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : createFormMessages.submit.failed
      )
    } finally {
      setSubmitting(false)
    }
  })

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
      <Main fluid className='space-y-5 px-3 py-5 sm:px-5 lg:px-6 xl:px-7'>
        <div className='flex flex-col gap-3 md:gap-4 lg:flex-row lg:items-start lg:justify-between'>
          <div className='space-y-2'>
            <div className='space-y-1'>
              <h1 className='text-2xl font-semibold tracking-tight sm:text-3xl'>
                {resolvedMode === 'label-ship'
                  ? createFormMessages.labelShipTitle
                  : createFormMessages.sellerShipTitle}
              </h1>
              <p className='max-w-3xl text-[13px] leading-6 text-muted-foreground'>
                {resolvedMode === 'label-ship'
                  ? createFormMessages.labelShipSubtitle
                  : createFormMessages.sellerShipSubtitle}
              </p>
            </div>
          </div>

          <Button
            type='button'
            variant='outline'
            className='w-full rounded-[6px] sm:w-auto'
            onClick={() => router.push('/lemiex/orders')}
          >
            <ArrowLeft className='h-3.5 w-3.5' />
            {createFormMessages.backToOrders}
          </Button>
        </div>

        <form className='space-y-5' onSubmit={onSubmit}>
          <Card className={sectionCardClassName()}>
            <CardContent className='space-y-0 p-0'>
              {/* Order Information */}
              <div className='px-4 py-4'>
                <p className='mb-3 text-[13px] font-semibold text-foreground'>
                  {createFormMessages.orderInformation}
                </p>
                <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-4'>
                  <div className='space-y-1.5'>
                    <label className='text-[12px] font-medium text-foreground'>
                      {createFormMessages.orderReferenceId}
                    </label>
                    <Input
                      {...register('ref_id', { required: true })}
                      placeholder={createFormMessages.placeholders.orderRefId}
                      className={fieldClassName()}
                    />
                  </div>

                  <div className='space-y-1.5'>
                    <label className='text-[12px] font-medium text-foreground'>
                      {createFormMessages.storeApiKey}
                    </label>
                    {loadingMeta ? (
                      <div className='flex h-9 items-center gap-2 rounded-[6px] border border-border px-3 text-[13px] text-muted-foreground'>
                        <LoaderCircle className='h-3.5 w-3.5 animate-spin' />
                        {createFormMessages.loadingStores}
                      </div>
                    ) : storesWithApiKeys.length > 0 ? (
                      <>
                        <Select
                          value={watch('api_key')}
                          onValueChange={(value) =>
                            setValue('api_key', value, {
                              shouldDirty: true,
                              shouldTouch: true,
                            })
                          }
                        >
                          <SelectTrigger className='w-full rounded-[6px] text-[13px]'>
                            <SelectValue
                              placeholder={createFormMessages.placeholders.selectStore}
                            />
                          </SelectTrigger>
                          <SelectContent>
                            {storesWithApiKeys.map((store) => (
                              <SelectItem
                                key={store.id}
                                value={store.api_key || ''}
                              >
                                {store.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <p className='text-[12px] text-muted-foreground'>
                          {selectedStore
                            ? replaceMessage(createFormMessages.selectedStore, {
                                name: selectedStore.name,
                              })
                            : replaceMessage(createFormMessages.storesAvailable, {
                                count: storesWithApiKeys.length,
                              })}
                        </p>
                      </>
                    ) : (
                      <>
                        <Input
                          {...register('api_key', { required: true })}
                          placeholder={createFormMessages.placeholders.manualApiKey}
                          className={fieldClassName()}
                        />
                        <p className='text-[12px] text-amber-600'>
                          {createFormMessages.noStoresFound}
                        </p>
                      </>
                    )}
                  </div>

                  <div className='space-y-1.5'>
                    <label className='text-[12px] font-medium text-foreground'>
                      {createFormMessages.sellerReference}
                    </label>
                    <Input
                      {...register('seller_ref')}
                      placeholder={createFormMessages.placeholders.sellerRef}
                      className={fieldClassName()}
                    />
                  </div>

                  <div className='space-y-1.5'>
                    <label className='text-[12px] font-medium text-foreground'>
                      {createFormMessages.orderStatus}
                    </label>
                    <Select
                      value={watch('order_status')}
                      onValueChange={(value) =>
                        setValue('order_status', value, {
                          shouldDirty: true,
                          shouldTouch: true,
                        })
                      }
                    >
                      <SelectTrigger className='w-full rounded-[6px] text-[13px]'>
                        <SelectValue
                          placeholder={createFormMessages.placeholders.selectStatus}
                        />
                      </SelectTrigger>
                      <SelectContent>
                        {ORDER_STATUS_OPTIONS.map((option) => (
                          <SelectItem key={option.value} value={option.value}>
                            {optionLabels.orderStatus[
                              option.value as keyof typeof optionLabels.orderStatus
                            ] || option.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </div>

              {/* Shipping Information */}
              <div className='border-t border-border/60 px-4 py-4'>
                <p className='mb-3 text-[13px] font-semibold text-foreground'>
                  {createFormMessages.shippingInformation}
                </p>
                <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-4'>
                  {mode === 'seller-ship' ? (
                    <div className='space-y-1.5'>
                      <label className='text-[12px] font-medium text-foreground'>
                        {createFormMessages.shippingMethod}
                      </label>
                      <Select
                        value={watch('shipping_method')}
                        onValueChange={(value) =>
                          setValue('shipping_method', value, {
                            shouldDirty: true,
                            shouldTouch: true,
                          })
                        }
                      >
                        <SelectTrigger className='w-full rounded-[6px] text-[13px]'>
                          <SelectValue
                            placeholder={
                              createFormMessages.placeholders.selectShippingMethod
                            }
                          />
                        </SelectTrigger>
                        <SelectContent>
                          {shippingMethods.map((method) => (
                            <SelectItem key={method.value} value={method.value}>
                              {method.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      {selectedShippingMethodDescription ? (
                        <p className='text-[12px] text-muted-foreground'>
                          {selectedShippingMethodDescription}
                        </p>
                      ) : null}
                    </div>
                  ) : (
                    <div className='space-y-1.5'>
                      <label className='text-[12px] font-medium text-foreground'>
                        {createFormMessages.shippingMethod}
                      </label>
                      <Input
                        value={createFormMessages.standardShippingMethod}
                        readOnly
                        className={`${fieldClassName()} bg-muted/35`}
                      />
                    </div>
                  )}

                  <div className='space-y-1.5'>
                    <label className='text-[12px] font-medium text-foreground'>
                      {createFormMessages.shippingService}
                    </label>
                    {mode === 'label-ship' ? (
                      <Select
                        value={watch('shipping_service')}
                        onValueChange={(value) =>
                          setValue('shipping_service', value, {
                            shouldDirty: true,
                            shouldTouch: true,
                          })
                        }
                      >
                        <SelectTrigger className='w-full rounded-[6px] text-[13px]'>
                          <SelectValue
                            placeholder={
                              createFormMessages.placeholders.selectShippingService
                            }
                          />
                        </SelectTrigger>
                        <SelectContent>
                          {SHIPPING_SERVICE_OPTIONS.map((service) => (
                            <SelectItem key={service.value} value={service.value}>
                              {optionLabels.shippingService[
                                service.value as keyof typeof optionLabels.shippingService
                              ] || service.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    ) : (
                      <Input
                        value={createFormMessages.fixedUsps}
                        readOnly
                        className={`${fieldClassName()} bg-muted/35`}
                      />
                    )}
                  </div>

                  <div className='space-y-1.5'>
                    <label className='text-[12px] font-medium text-foreground'>
                      {createFormMessages.fulfillmentPriority}
                    </label>
                    <Select
                      value={watch('fulfillment_priority')}
                      onValueChange={(value) =>
                        setValue('fulfillment_priority', value, {
                          shouldDirty: true,
                          shouldTouch: true,
                        })
                      }
                    >
                      <SelectTrigger className='w-full rounded-[6px] text-[13px]'>
                        <SelectValue
                          placeholder={createFormMessages.placeholders.selectPriority}
                        />
                      </SelectTrigger>
                      <SelectContent>
                        {fulfillmentPriorities.map((priority) => (
                          <SelectItem key={priority.value} value={priority.value}>
                            {priority.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {selectedPriorityDescription ? (
                      <p className='text-[12px] text-muted-foreground'>
                        {selectedPriorityDescription}
                      </p>
                    ) : null}
                  </div>

                  {mode === 'label-ship' ? (
                    <div className='space-y-1.5 md:col-span-2 xl:col-span-1'>
                      <label className='text-[12px] font-medium text-foreground'>
                        {createFormMessages.shippingLabelUrl}
                      </label>
                      <Input
                        {...register('shipping_label', { required: true })}
                        placeholder={createFormMessages.placeholders.shippingLabel}
                        className={fieldClassName()}
                      />
                      <p className='text-[12px] text-muted-foreground'>
                        {createFormMessages.shippingLabelHint}
                      </p>
                    </div>
                  ) : null}

                  <div className='space-y-1.5 md:col-span-2 xl:col-span-4'>
                    <label className='text-[12px] font-medium text-foreground'>
                      {createFormMessages.orderNotes}
                    </label>
                    <Textarea
                      {...register('note')}
                      placeholder={createFormMessages.placeholders.notes}
                      className='min-h-[72px] rounded-[6px] text-[13px]'
                    />
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {mode === 'seller-ship' || mode === 'label-ship' ? (
            <Card className={sectionCardClassName()}>
              <CardHeader className='border-b border-border/60 px-4 py-3'>
                <CardTitle className='text-[14px] font-semibold'>
                  {createFormMessages.shippingAddress}
                </CardTitle>
              </CardHeader>
              <CardContent className='grid gap-4 px-4 py-4 md:grid-cols-2 xl:grid-cols-4'>
                <div className='space-y-1.5'>
                  <label className='text-[12px] font-medium text-foreground'>
                    {createFormMessages.recipientName}
                  </label>
                  <Input
                    {...register('address.name', { required: true })}
                    placeholder={createFormMessages.placeholders.recipientName}
                    className={fieldClassName()}
                  />
                </div>
                <div className='space-y-1.5'>
                  <label className='text-[12px] font-medium text-foreground'>
                    {createFormMessages.phoneNumber}
                  </label>
                  <Input
                    {...register('address.phone')}
                    placeholder={createFormMessages.placeholders.phone}
                    className={fieldClassName()}
                  />
                </div>
                <div className='space-y-1.5 xl:col-span-2'>
                  <label className='text-[12px] font-medium text-foreground'>
                    {createFormMessages.streetAddress}
                  </label>
                  <Input
                    {...register('address.street1', { required: true })}
                    placeholder={createFormMessages.placeholders.street1}
                    className={fieldClassName()}
                  />
                </div>
                <div className='space-y-1.5 xl:col-span-2'>
                  <label className='text-[12px] font-medium text-foreground'>
                    {createFormMessages.apartmentSuite}
                  </label>
                  <Input
                    {...register('address.street2')}
                    placeholder={createFormMessages.placeholders.street2}
                    className={fieldClassName()}
                  />
                </div>
                <div className='space-y-1.5'>
                  <label className='text-[12px] font-medium text-foreground'>
                    {createFormMessages.city}
                  </label>
                  <Input
                    {...register('address.city', { required: true })}
                    placeholder={createFormMessages.placeholders.city}
                    className={fieldClassName()}
                  />
                </div>
                <div className='space-y-1.5'>
                  <label className='text-[12px] font-medium text-foreground'>
                    {createFormMessages.stateProvince}
                  </label>
                  <Input
                    {...register('address.state', { required: true })}
                    placeholder={createFormMessages.placeholders.state}
                    className={fieldClassName()}
                  />
                </div>
                <div className='space-y-1.5'>
                  <label className='text-[12px] font-medium text-foreground'>
                    {createFormMessages.zipCode}
                  </label>
                  <Input
                    {...register('address.zip', { required: true })}
                    placeholder={createFormMessages.placeholders.zip}
                    className={fieldClassName()}
                  />
                </div>
                <div className='space-y-1.5'>
                  <label className='text-[12px] font-medium text-foreground'>
                    {createFormMessages.country}
                  </label>
                  <Select
                    value={watch('address.country')}
                    onValueChange={(value) =>
                      setValue('address.country', value, {
                        shouldDirty: true,
                        shouldTouch: true,
                      })
                    }
                  >
                    <SelectTrigger className='w-full rounded-[6px] text-[13px]'>
                      <SelectValue
                        placeholder={createFormMessages.placeholders.selectCountry}
                      />
                    </SelectTrigger>
                    <SelectContent>
                      {COUNTRY_OPTIONS.map((country) => (
                        <SelectItem key={country.value} value={country.value}>
                          {optionLabels.country[
                            country.value as keyof typeof optionLabels.country
                          ] || country.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </CardContent>
            </Card>
          ) : null}

          <div className='space-y-4'>
            <div className='flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
              <div className='space-y-1'>
                <h2 className='text-[15px] font-semibold text-foreground'>
                  {createFormMessages.productsAndDesignFiles}
                </h2>
                <p className='text-[12px] text-muted-foreground'>
                  {createFormMessages.productsAndDesignFilesDesc}
                </p>
              </div>
              <Button
                type='button'
                className='w-full rounded-[6px] sm:w-auto'
                onClick={() => append(defaultLineItem())}
              >
                <Plus className='h-3.5 w-3.5' />
                {createFormMessages.addProduct}
              </Button>
            </div>

            <div className='space-y-4'>
              {fields.map((field, index) => (
                <LineItemCard
                  key={field.id}
                  index={index}
                  control={control}
                  register={register}
                  setValue={setValue}
                  watch={watch}
                  canRemove={fields.length > 1}
                  onRemove={() => remove(index)}
                />
              ))}
            </div>
          </div>

          <div className='flex flex-col gap-3 border-t border-border/70 pt-1 sm:flex-row sm:justify-end'>
            <Button
              type='button'
              variant='outline'
              className='w-full rounded-[6px] sm:w-auto'
              onClick={() => router.push('/lemiex/orders')}
            >
              {createFormMessages.cancel}
            </Button>
            <Button
              type='submit'
              className='w-full rounded-[6px] sm:w-auto'
              disabled={submitting}
            >
              {submitting ? (
                <>
                  <LoaderCircle className='h-3.5 w-3.5 animate-spin' />
                  {createFormMessages.creating}
                </>
              ) : (
                <>
                  <Save className='h-3.5 w-3.5' />
                  {createFormMessages.createOrder}
                </>
              )}
            </Button>
          </div>
        </form>
      </Main>
    </>
  )
}
