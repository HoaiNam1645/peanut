'use client'

import { useEffect, useRef, useState } from 'react'
import { LoaderCircle } from 'lucide-react'
import {
  fetchProducts,
  fetchProductSizes,
  fetchProductVariant,
  type ProductOption,
  type ProductVariantDetail,
  type SelectOption,
} from '@/services/orders/api'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useI18n } from '@/context/i18n-provider'

type ProductVariantPickerProps = {
  value?: string
  onVariantResolved: (variant: ProductVariantDetail) => void
}

export function ProductVariantPicker({
  value,
  onVariantResolved,
}: ProductVariantPickerProps) {
  const { messages } = useI18n()
  const pickerMessages = messages.orders.createForm.productPicker
  const [products, setProducts] = useState<ProductOption[]>([])
  const [sizes, setSizes] = useState<SelectOption[]>([])
  const [selectedProductId, setSelectedProductId] = useState('')
  const [selectedSize, setSelectedSize] = useState('')
  const [loading, setLoading] = useState({
    products: false,
    sizes: false,
    variant: false,
  })
  const onVariantResolvedRef = useRef(onVariantResolved)
  useEffect(() => { onVariantResolvedRef.current = onVariantResolved })

  // Load products on mount
  useEffect(() => {
    let active = true
    setLoading((prev) => ({ ...prev, products: true }))
    fetchProducts()
      .then((res) => { if (active) setProducts(res) })
      .catch(() => { if (active) setProducts([]) })
      .finally(() => { if (active) setLoading((prev) => ({ ...prev, products: false })) })
    return () => { active = false }
  }, [])

  // Load sizes when product changes
  useEffect(() => {
    let active = true
    if (!selectedProductId) {
      setSizes([])
      setSelectedSize('')
      return
    }
    setLoading((prev) => ({ ...prev, sizes: true }))
    setSizes([])
    setSelectedSize('')
    fetchProductSizes(selectedProductId)
      .then((res) => { if (active) setSizes(res) })
      .catch(() => { if (active) setSizes([]) })
      .finally(() => { if (active) setLoading((prev) => ({ ...prev, sizes: false })) })
    return () => { active = false }
  }, [selectedProductId])

  // Resolve variant when product + size selected
  useEffect(() => {
    let active = true
    if (!selectedProductId || !selectedSize) return
    setLoading((prev) => ({ ...prev, variant: true }))
    fetchProductVariant(selectedProductId, selectedSize)
      .then((variant) => { if (active && variant) onVariantResolvedRef.current(variant) })
      .finally(() => { if (active) setLoading((prev) => ({ ...prev, variant: false })) })
    return () => { active = false }
  }, [selectedProductId, selectedSize])

  return (
    <div className='grid gap-3 sm:grid-cols-2'>
      <div className='space-y-1.5'>
        <label className='text-[12px] font-medium text-foreground'>
          {pickerMessages.product}
        </label>
        <Select value={selectedProductId} onValueChange={setSelectedProductId}>
          <SelectTrigger className='w-full rounded-[6px] text-[13px]'>
            <SelectValue
              placeholder={
                loading.products ? pickerMessages.loadingProducts : pickerMessages.selectProduct
              }
            />
          </SelectTrigger>
          <SelectContent>
            {products.map((product) => (
              <SelectItem key={product.id} value={String(product.id)}>
                {product.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className='space-y-1.5'>
        <label className='text-[12px] font-medium text-foreground'>
          {pickerMessages.size}
        </label>
        <Select
          value={selectedSize}
          onValueChange={setSelectedSize}
          disabled={!selectedProductId || loading.sizes}
        >
          <SelectTrigger className='w-full rounded-[6px] text-[13px]'>
            <SelectValue
              placeholder={
                loading.sizes ? pickerMessages.loadingSizes : pickerMessages.selectSize
              }
            />
          </SelectTrigger>
          <SelectContent>
            {sizes.map((size) => (
              <SelectItem key={size.value} value={size.value}>
                {size.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className='sm:col-span-2'>
        <div className='flex min-h-9 items-center rounded-[6px] border border-dashed border-border bg-muted/20 px-3 text-[12px] text-muted-foreground'>
          {loading.variant ? (
            <span className='inline-flex items-center gap-2'>
              <LoaderCircle className='h-3.5 w-3.5 animate-spin' />
              {pickerMessages.resolvingVariant}
            </span>
          ) : value ? (
            <span>
              {pickerMessages.variantId}:{' '}
              <span className='font-medium text-foreground'>{value}</span>
            </span>
          ) : (
            pickerMessages.chooseAll
          )}
        </div>
      </div>
    </div>
  )
}
