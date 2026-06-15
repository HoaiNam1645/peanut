'use client'

import { format, parseISO } from 'date-fns'
import { useEffect, useMemo, useState } from 'react'
import { ChevronDown, ChevronUp, Copy, Search, X } from 'lucide-react'
import { DatePicker } from '@/components/date-picker'
import { useI18n } from '@/context/i18n-provider'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  FALLBACK_FULFILL_STATUS_OPTIONS,
  PAYMENT_STATUS_OPTIONS,
  SORT_BY_OPTIONS,
  SORT_ORDER_OPTIONS,
} from '@/features/lemiex/orders/constants'
import { type LemiexOrdersFilters } from '@/features/lemiex/orders/types'
import {
  fetchColorOptions,
  fetchOrderFulfillStatusOptions,
  fetchSellerOptions,
  fetchSizeOptions,
  fetchStyleOptions,
  type SelectOption,
} from '@/services/orders/api'
import { getUserRoleName } from '@/services/auth/api'
import { type AuthUser } from '@/stores/auth-store'

type OrdersFiltersProps = {
  filters: LemiexOrdersFilters
  user: AuthUser | null
  onApply: (filters: LemiexOrdersFilters) => void
  onReset: () => void
  onGetIds: (filters: LemiexOrdersFilters) => void
}

const SELLER_VISIBLE_FULFILL_STATUSES = [
  'new_order',
  'confirm',
  'producing',
  'shipped',
  'on_hold',
  'cancelled_refund_shipping',
  'cancelled',
  'closed',
]

function hasAdvancedActiveFilters(filters: LemiexOrdersFilters) {
  return Boolean(
    filters.style ||
      filters.color ||
      filters.size ||
      filters.seller_id ||
      filters.product_name ||
      filters.date_from ||
      filters.date_to ||
      filters.shipped_date_from ||
      filters.shipped_date_to ||
      filters.missing_shipping_info ||
      filters.fulfill_status.length > 0 ||
      filters.payment_status.length > 0 ||
      filters.exclude_status.length > 0 ||
      filters.sort_by !== 'created_at' ||
      filters.sort_order !== 'asc'
  )
}

function FilterChipGroup({
  title,
  options,
  value,
  onChange,
  showCount = false,
}: {
  title: string
  options: SelectOption[]
  value: string[]
  onChange: (nextValue: string[]) => void
  showCount?: boolean
}) {
  return (
    <div className='space-y-3'>
      <div className='text-[13px] font-semibold uppercase tracking-wide text-foreground/80'>
        {title}
      </div>
      <div className='flex flex-wrap gap-3'>
        {options.map((option) => {
          const isSelected = value.includes(option.value)

          return (
            <button
              key={option.value}
              type='button'
              onClick={() =>
                onChange(
                  isSelected
                    ? value.filter((item) => item !== option.value)
                    : [...value, option.value]
                )
              }
              className={`rounded-full border px-5 py-2 text-[13px] transition-colors ${
                isSelected
                  ? 'border-foreground bg-foreground text-background'
                  : 'border-border bg-background text-foreground hover:bg-muted'
              }`}
            >
              {isSelected ? '× ' : ''}
              {option.label}
              {showCount ? ` (${option.count ?? 0})` : ''}
            </button>
          )
        })}
      </div>
    </div>
  )
}

export function OrdersFilters({
  filters,
  user,
  onApply,
  onReset,
  onGetIds,
}: OrdersFiltersProps) {
  const { messages } = useI18n()
  const ordersMessages = messages.orders
  const role = getUserRoleName(user)
  const isSeller = role === 'Seller'
  const [draft, setDraft] = useState(filters)
  const [isExpanded, setIsExpanded] = useState(hasAdvancedActiveFilters(filters))
  const [styles, setStyles] = useState<SelectOption[]>([])
  const [colors, setColors] = useState<SelectOption[]>([])
  const [sizes, setSizes] = useState<SelectOption[]>([])
  const [sellers, setSellers] = useState<SelectOption[]>([])
  const [fulfillStatuses, setFulfillStatuses] = useState<SelectOption[]>(
    FALLBACK_FULFILL_STATUS_OPTIONS
  )
  useEffect(() => {
    // Only sync the draft with applied filters. Do NOT force the popup open
    // here: applying a filter / searching changes `filters`, and re-deriving
    // `isExpanded` from it would re-open the popup right after we close it.
    setDraft(filters)
  }, [filters])

  useEffect(() => {
    // The seller list (/users?role_id=2) is Admin/Staff-only; calling it as a
    // seller returns 403. Sellers only ever see their own orders, so the seller
    // filter is irrelevant to them — skip the request entirely.
    void Promise.allSettled([
      fetchStyleOptions(),
      isSeller ? Promise.resolve<SelectOption[]>([]) : fetchSellerOptions(),
    ]).then(([stylesResult, sellersResult]) => {
      if (stylesResult.status === 'fulfilled') setStyles(stylesResult.value)
      if (sellersResult.status === 'fulfilled') setSellers(sellersResult.value)
    })
  }, [isSeller])

  // Fulfill status counts honor the APPLIED date range so the chip totals match
  // the filtered orders list. Re-fetch whenever the applied range changes.
  useEffect(() => {
    void fetchOrderFulfillStatusOptions({
      date_from: filters.date_from || undefined,
      date_to: filters.date_to || undefined,
    }).then((statuses) => {
      if (statuses.length > 0) {
        setFulfillStatuses(statuses.filter(Boolean) as SelectOption[])
      }
    })
  }, [filters.date_from, filters.date_to])

  useEffect(() => {
    if (!draft.style) {
      setColors([])
      setSizes([])
      return
    }

    void fetchColorOptions(draft.style).then(setColors).catch(() => {
      setColors([])
    })
  }, [draft.style])

  useEffect(() => {
    if (!draft.style || !draft.color) {
      setSizes([])
      return
    }

    void fetchSizeOptions(draft.style, draft.color).then(setSizes).catch(() => {
      setSizes([])
    })
  }, [draft.style, draft.color])

  const advancedCount = useMemo(
    () =>
      [
        draft.style,
        draft.color,
        draft.size,
        draft.seller_id,
        draft.product_name,
        draft.date_from,
        draft.date_to,
        draft.shipped_date_from,
        draft.shipped_date_to,
        draft.missing_shipping_info ? '1' : '',
        draft.fulfill_status.join(','),
        draft.payment_status.join(','),
        draft.exclude_status.join(','),
      ].filter(Boolean).length,
    [draft]
  )

  const fieldClassName =
    'h-11 w-full rounded-lg border-border bg-background px-3 text-[13px] shadow-none'
  const compactFieldClassName =
    'h-[35px] w-full rounded-md border-border bg-background px-3 text-[13px] shadow-none'
  const paymentStatusOptions = PAYMENT_STATUS_OPTIONS.map((option) => ({
    ...option,
    label:
      ordersMessages.paymentStatuses[
        option.value as keyof typeof ordersMessages.paymentStatuses
      ] || option.label,
  }))
  const sortByOptions = SORT_BY_OPTIONS.map((option) => ({
    ...option,
    label:
      ordersMessages.sortBy[option.value as keyof typeof ordersMessages.sortBy] ||
      option.label,
  }))
  const sortOrderOptions = SORT_ORDER_OPTIONS.map((option) => ({
    ...option,
    label:
      ordersMessages.sortOrder[
        option.value as keyof typeof ordersMessages.sortOrder
      ] || option.label,
  }))
  const fallbackFulfillStatusOptions = FALLBACK_FULFILL_STATUS_OPTIONS.map((option) => ({
    ...option,
    label:
      ordersMessages.fulfillStatuses[
        option.value as keyof typeof ordersMessages.fulfillStatuses
      ] || option.label,
  }))
  const localizedFulfillStatuses = fulfillStatuses.map((option) => ({
    ...option,
    label:
      ordersMessages.fulfillStatuses[
        option.value as keyof typeof ordersMessages.fulfillStatuses
      ] || option.label,
  }))
  const visibleFulfillStatuses = (
    localizedFulfillStatuses.length > 0
      ? localizedFulfillStatuses
      : fallbackFulfillStatusOptions
  ).filter((option) =>
    isSeller
      ? SELLER_VISIBLE_FULFILL_STATUSES.includes(option.value)
      : true
  )
  const dateFromValue = draft.date_from ? parseISO(draft.date_from) : undefined
  const dateToValue = draft.date_to ? parseISO(draft.date_to) : undefined
  const shippedDateFromValue = draft.shipped_date_from
    ? parseISO(draft.shipped_date_from)
    : undefined
  const shippedDateToValue = draft.shipped_date_to
    ? parseISO(draft.shipped_date_to)
    : undefined

  return (
    <Card className='gap-0 rounded-[6px] py-0 shadow-none'>
      <CardContent className='space-y-3 px-4 py-4 sm:px-5 sm:py-4'>
        <div className='grid gap-3 xl:grid-cols-[1fr_1fr_1fr_1fr_auto_auto_auto_auto]'>
          <div className='space-y-2'>
            <Label htmlFor='order_id' className='text-[13px]'>{ordersMessages.filters.orderId}</Label>
            <Input
              id='order_id'
              className={compactFieldClassName}
              value={draft.order_id}
              onChange={(event) =>
                setDraft((prev) => ({ ...prev, order_id: event.target.value }))
              }
              placeholder={ordersMessages.filters.placeholders.orderId}
            />
          </div>

          <div className='space-y-2'>
            <Label htmlFor='variant_id' className='text-[13px]'>{ordersMessages.filters.variantId}</Label>
            <Input
              id='variant_id'
              className={compactFieldClassName}
              value={draft.variant_id}
              onChange={(event) =>
                setDraft((prev) => ({ ...prev, variant_id: event.target.value }))
              }
              placeholder={ordersMessages.filters.placeholders.variantId}
            />
          </div>

          <div className='space-y-2'>
            <Label htmlFor='ref_id' className='text-[13px]'>{ordersMessages.filters.refId}</Label>
            <Input
              id='ref_id'
              className={compactFieldClassName}
              value={draft.ref_id}
              onChange={(event) =>
                setDraft((prev) => ({ ...prev, ref_id: event.target.value }))
              }
              placeholder={ordersMessages.filters.placeholders.refId}
            />
          </div>

          <div className='space-y-2'>
            <Label htmlFor='tracking_number' className='text-[13px]'>{ordersMessages.filters.trackingNumber}</Label>
            <Input
              id='tracking_number'
              className={compactFieldClassName}
              value={draft.tracking_number}
              onChange={(event) =>
                setDraft((prev) => ({
                  ...prev,
                  tracking_number: event.target.value,
                }))
              }
              placeholder={ordersMessages.filters.placeholders.trackingNumber}
            />
          </div>

          <div className='flex items-end'>
            <Button
              type='button'
              className='h-[35px] w-full rounded-md px-3 text-[13px]'
              onClick={() => {
                onApply(draft)
                setIsExpanded(false)
              }}
            >
              <Search className='size-4' />
              {ordersMessages.filters.search}
            </Button>
          </div>

          <div className='flex items-end'>
            <Button type='button' variant='outline' className='h-[35px] w-full rounded-md px-3 text-[13px]' onClick={onReset}>
              <X className='size-4' />
              {ordersMessages.filters.clearAll}
            </Button>
          </div>

          <div className='flex items-end'>
            <Button
              type='button'
              variant='outline'
              className='h-[35px] w-full rounded-md px-3 text-[13px]'
              onClick={() => {
                onGetIds(draft)
                setIsExpanded(false)
              }}
            >
              <Copy className='size-4' />
              {ordersMessages.filters.getIds}
            </Button>
          </div>

          <div className='flex items-end'>
            <Button
              type='button'
              variant='outline'
              className='h-[35px] w-full rounded-md px-3 text-[13px]'
              onClick={() => setIsExpanded((value) => !value)}
            >
              {isExpanded ? <ChevronUp className='size-4' /> : <ChevronDown className='size-4' />}
              {ordersMessages.filters.filters}{advancedCount > 0 ? ` (${advancedCount})` : ''}
            </Button>
          </div>
        </div>

        <Collapsible open={isExpanded}>
          <CollapsibleContent className='space-y-8 border-t pt-6'>
            <FilterChipGroup
              title={ordersMessages.filters.excludeStatus}
              options={fallbackFulfillStatusOptions}
              value={draft.exclude_status}
              onChange={(value) => setDraft((prev) => ({ ...prev, exclude_status: value }))}
            />

            <div className='space-y-3'>
              <div className='text-[13px] font-semibold uppercase tracking-wide text-foreground/80'>
                {ordersMessages.filters.shippingInfo}
              </div>
              <button
                type='button'
                onClick={() =>
                  setDraft((prev) => ({
                    ...prev,
                    missing_shipping_info: !prev.missing_shipping_info,
                  }))
                }
                className={`rounded-full border px-5 py-2 text-[13px] transition-colors ${
                  draft.missing_shipping_info
                    ? 'border-foreground bg-foreground text-background'
                    : 'border-border bg-background text-foreground hover:bg-muted'
                }`}
              >
                {draft.missing_shipping_info ? '× ' : ''}
                {ordersMessages.filters.missingShippingInfo}
              </button>
            </div>

            <FilterChipGroup
              title={ordersMessages.filters.fulfillStatus}
              options={visibleFulfillStatuses}
              value={draft.fulfill_status}
              onChange={(value) => setDraft((prev) => ({ ...prev, fulfill_status: value }))}
              showCount
            />

            <FilterChipGroup
              title={ordersMessages.filters.paymentStatus}
              options={paymentStatusOptions}
              value={draft.payment_status}
              onChange={(value) => setDraft((prev) => ({ ...prev, payment_status: value }))}
            />

            <div className='space-y-5 border-t pt-8'>
              <div className='text-[13px] font-semibold uppercase tracking-wide text-foreground/80'>
                {ordersMessages.filters.productAttributes}
              </div>

              <div className='grid gap-4 xl:grid-cols-3'>
                <div className='space-y-2'>
                  <Label className='text-[13px]'>{ordersMessages.filters.style}</Label>
                  <Select
                    value={draft.style || '__all__'}
                    onValueChange={(value) =>
                      setDraft((prev) => ({
                        ...prev,
                        style: value === '__all__' ? '' : value,
                        color: '',
                        size: '',
                      }))
                    }
                  >
                    <SelectTrigger className={fieldClassName}>
                      <SelectValue placeholder={ordersMessages.filters.placeholders.selectStyle} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value='__all__'>{ordersMessages.filters.selectStyle}</SelectItem>
                      {styles.map((style) => (
                        <SelectItem key={style.value} value={style.value}>
                          {style.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className='space-y-2'>
                  <Label className='text-[13px]'>{ordersMessages.filters.color}</Label>
                  <Select
                    value={draft.color || '__all__'}
                    onValueChange={(value) =>
                      setDraft((prev) => ({
                        ...prev,
                        color: value === '__all__' ? '' : value,
                        size: '',
                      }))
                    }
                  >
                    <SelectTrigger className={fieldClassName}>
                      <SelectValue placeholder={ordersMessages.filters.placeholders.selectColor} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value='__all__'>{ordersMessages.filters.selectColor}</SelectItem>
                      {colors.map((color) => (
                        <SelectItem key={color.value} value={color.value}>
                          {color.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className='space-y-2'>
                  <Label className='text-[13px]'>{ordersMessages.filters.size}</Label>
                  <Select
                    value={draft.size || '__all__'}
                    onValueChange={(value) =>
                      setDraft((prev) => ({
                        ...prev,
                        size: value === '__all__' ? '' : value,
                      }))
                    }
                  >
                    <SelectTrigger className={fieldClassName}>
                      <SelectValue placeholder={ordersMessages.filters.placeholders.selectSize} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value='__all__'>{ordersMessages.filters.selectSize}</SelectItem>
                      {sizes.map((size) => (
                        <SelectItem key={size.value} value={size.value}>
                          {size.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className='grid gap-4 xl:grid-cols-3'>
                <div className='space-y-2'>
                  <Label className='text-[13px]'>{ordersMessages.filters.seller}</Label>
                  <Select
                    value={draft.seller_id || '__all__'}
                    onValueChange={(value) =>
                      setDraft((prev) => ({
                        ...prev,
                        seller_id: value === '__all__' ? '' : value,
                      }))
                    }
                  >
                    <SelectTrigger className={fieldClassName}>
                      <SelectValue placeholder={ordersMessages.filters.placeholders.allSellers} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value='__all__'>{ordersMessages.filters.allSellers}</SelectItem>
                      {sellers.map((seller) => (
                        <SelectItem key={seller.value} value={seller.value}>
                          {seller.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className='space-y-2'>
                  <Label htmlFor='product_name' className='text-[13px]'>{ordersMessages.filters.productName}</Label>
                  <Input
                    id='product_name'
                    className={fieldClassName}
                    value={draft.product_name}
                    onChange={(event) =>
                      setDraft((prev) => ({
                        ...prev,
                        product_name: event.target.value,
                      }))
                    }
                    placeholder={ordersMessages.filters.placeholders.productName}
                  />
                </div>
              </div>

              <div className='grid gap-4 xl:grid-cols-2'>
                <div className='space-y-2'>
                  <Label htmlFor='date_from' className='text-[13px]'>{ordersMessages.filters.dateFrom}</Label>
                  <DatePicker
                    selected={dateFromValue}
                    onSelect={(date) =>
                      setDraft((prev) => ({
                        ...prev,
                        date_from: date ? format(date, 'yyyy-MM-dd') : '',
                      }))
                    }
                    placeholder={ordersMessages.filters.dateFrom}
                    className={fieldClassName}
                  />
                </div>

                <div className='space-y-2'>
                  <Label htmlFor='date_to' className='text-[13px]'>{ordersMessages.filters.dateTo}</Label>
                  <DatePicker
                    selected={dateToValue}
                    onSelect={(date) =>
                      setDraft((prev) => ({
                        ...prev,
                        date_to: date ? format(date, 'yyyy-MM-dd') : '',
                      }))
                    }
                    placeholder={ordersMessages.filters.dateTo}
                    className={fieldClassName}
                  />
                </div>
              </div>

              <div className='space-y-3'>
                <div className='flex flex-wrap items-center justify-between gap-2'>
                  <div className='text-[13px] font-semibold uppercase tracking-wide text-foreground/80'>
                    {ordersMessages.filters.shippedDateRange}
                  </div>
                  <button
                    type='button'
                    onClick={() => {
                      const today = format(new Date(), 'yyyy-MM-dd')
                      setDraft((prev) => ({
                        ...prev,
                        shipped_date_from: today,
                        shipped_date_to: today,
                      }))
                    }}
                    className='rounded-full border border-border bg-background px-3 py-1 text-[12px] text-foreground transition-colors hover:bg-muted'
                  >
                    {ordersMessages.filters.shippedToday}
                  </button>
                </div>
                <p className='text-[12px] leading-relaxed text-muted-foreground'>
                  {ordersMessages.filters.shippedDateHint}
                </p>
                <div className='grid gap-4 xl:grid-cols-2'>
                  <div className='space-y-2'>
                    <Label htmlFor='shipped_date_from' className='text-[13px]'>{ordersMessages.filters.shippedDateFrom}</Label>
                    <DatePicker
                      selected={shippedDateFromValue}
                      onSelect={(date) =>
                        setDraft((prev) => ({
                          ...prev,
                          shipped_date_from: date ? format(date, 'yyyy-MM-dd') : '',
                        }))
                      }
                      placeholder={ordersMessages.filters.shippedDateFrom}
                      className={fieldClassName}
                    />
                  </div>

                  <div className='space-y-2'>
                    <Label htmlFor='shipped_date_to' className='text-[13px]'>{ordersMessages.filters.shippedDateTo}</Label>
                    <DatePicker
                      selected={shippedDateToValue}
                      onSelect={(date) =>
                        setDraft((prev) => ({
                          ...prev,
                          shipped_date_to: date ? format(date, 'yyyy-MM-dd') : '',
                        }))
                      }
                      placeholder={ordersMessages.filters.shippedDateTo}
                      className={fieldClassName}
                    />
                  </div>
                </div>
              </div>

              <div className='grid gap-4 xl:grid-cols-2'>
                <div className='space-y-2'>
                  <Label className='text-[13px]'>{ordersMessages.filters.sortBy}</Label>
                  <Select
                    value={draft.sort_by}
                    onValueChange={(value) =>
                      setDraft((prev) => ({ ...prev, sort_by: value }))
                    }
                  >
                    <SelectTrigger className={fieldClassName}>
                      <SelectValue placeholder={ordersMessages.filters.placeholders.createdDate} />
                    </SelectTrigger>
                    <SelectContent>
                      {sortByOptions.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className='space-y-2'>
                  <Label className='text-[13px]'>{ordersMessages.filters.sortOrder}</Label>
                  <Select
                    value={draft.sort_order}
                    onValueChange={(value) =>
                      setDraft((prev) => ({
                        ...prev,
                        sort_order: value as 'asc' | 'desc',
                      }))
                    }
                  >
                    <SelectTrigger className={fieldClassName}>
                      <SelectValue placeholder={ordersMessages.filters.placeholders.ascending} />
                    </SelectTrigger>
                    <SelectContent>
                      {sortOrderOptions.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </div>
          </CollapsibleContent>
        </Collapsible>
      </CardContent>
    </Card>
  )
}
