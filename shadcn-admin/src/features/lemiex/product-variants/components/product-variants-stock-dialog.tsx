'use client'

import { useEffect, useMemo, useState } from 'react'
import { toast } from 'sonner'
import { Loader2, Minus, Plus } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { ProductWithVariants } from '@/services/products/api'
import { updateProductStock } from '@/services/products/api'
import { useI18n } from '@/context/i18n-provider'

type ProductVariantsStockDialogProps = {
  open: boolean
  product: ProductWithVariants | null
  onOpenChange: (open: boolean) => void
  onUpdated: () => Promise<void> | void
}

export function ProductVariantsStockDialog({
  open,
  product,
  onOpenChange,
  onUpdated,
}: ProductVariantsStockDialogProps) {
  const { messages } = useI18n()
  const productMessages = messages.productVariants
  const [type, setType] = useState<'add_stock' | 'sub_stock'>('add_stock')
  const [selectedColor, setSelectedColor] = useState('')
  const [selectedSize, setSelectedSize] = useState('')
  const [stock, setStock] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const availableSizes = useMemo(() => {
    if (!product || !selectedColor) return product?.sizes || []

    const sizes = product.variants
      .filter((variant) => variant.color === selectedColor)
      .map((variant) => variant.size)
      .filter(Boolean) as string[]

    return Array.from(new Set(sizes))
  }, [product, selectedColor])

  useEffect(() => {
    if (!open || !product) return

    setType('add_stock')
    setSelectedColor(product.colors?.[0] || '')
    setSelectedSize(product.sizes?.[0] || '')
    setStock('')
  }, [open, product])

  useEffect(() => {
    if (!availableSizes.length) {
      setSelectedSize('')
      return
    }

    if (!availableSizes.includes(selectedSize)) {
      setSelectedSize(availableSizes[0])
    }
  }, [availableSizes, selectedSize])

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (!product || !selectedColor || !selectedSize || Number(stock) <= 0) {
      toast.error(productMessages.stockDialog.validation)
      return
    }

    setSubmitting(true)

    try {
      await updateProductStock({
        type,
        name: product.id,
        color: selectedColor,
        size: selectedSize,
        stock: Number(stock),
      })

      toast.success(
        type === 'add_stock'
          ? productMessages.stockDialog.addSuccess
          : productMessages.stockDialog.subtractSuccess
      )

      setStock('')
      await onUpdated()
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : productMessages.stockDialog.updateFailed
      )
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-[560px]'>
        <DialogHeader>
          <DialogTitle>{productMessages.stockDialog.title}</DialogTitle>
          <DialogDescription>
            {product?.name || productMessages.stockDialog.description}
          </DialogDescription>
        </DialogHeader>

        <form className='space-y-5' onSubmit={handleSubmit}>
          <div className='grid gap-3 sm:grid-cols-2'>
            <Button
              type='button'
              variant={type === 'add_stock' ? 'default' : 'outline'}
              className='h-11 justify-center gap-2 rounded-[6px]'
              onClick={() => setType('add_stock')}
            >
              <Plus className='size-4' />
              {productMessages.stockDialog.addStock}
            </Button>
            <Button
              type='button'
              variant={type === 'sub_stock' ? 'default' : 'outline'}
              className='h-11 justify-center gap-2 rounded-[6px]'
              onClick={() => setType('sub_stock')}
            >
              <Minus className='size-4' />
              {productMessages.stockDialog.subtractStock}
            </Button>
          </div>

          <div className='grid gap-4 sm:grid-cols-[1fr_1fr]'>
            <div className='space-y-2'>
              <label className='text-sm font-medium'>
                {productMessages.stockDialog.color}
              </label>
              <Select value={selectedColor} onValueChange={setSelectedColor}>
                <SelectTrigger className='h-10 rounded-[6px]'>
                  <SelectValue
                    placeholder={productMessages.stockDialog.selectColor}
                  />
                </SelectTrigger>
                <SelectContent>
                  {product?.colors.map((color) => (
                    <SelectItem key={color} value={color}>
                      {color}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className='space-y-2'>
              <label className='text-sm font-medium'>
                {productMessages.stockDialog.size}
              </label>
              <Select value={selectedSize} onValueChange={setSelectedSize}>
                <SelectTrigger className='h-10 rounded-[6px]'>
                  <SelectValue
                    placeholder={productMessages.stockDialog.selectSize}
                  />
                </SelectTrigger>
                <SelectContent>
                  {availableSizes.map((size) => (
                    <SelectItem key={size} value={size}>
                      {size}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className='space-y-2'>
            <label className='text-sm font-medium'>
              {productMessages.stockDialog.quantity}
            </label>
            <Input
              type='number'
              min={1}
              value={stock}
              onChange={(event) => setStock(event.target.value)}
              placeholder={productMessages.stockDialog.quantityPlaceholder}
              className='h-10 rounded-[6px]'
            />
          </div>

          <DialogFooter className='gap-2 sm:justify-end'>
            <Button
              type='button'
              variant='outline'
              className='rounded-[6px]'
              onClick={() => onOpenChange(false)}
            >
              {messages.profile.cancel}
            </Button>
            <Button type='submit' className='rounded-[6px]' disabled={submitting}>
              {submitting ? (
                <>
                  <Loader2 className='size-4 animate-spin' />
                  {productMessages.stockDialog.updating}
                </>
              ) : type === 'add_stock' ? (
                productMessages.stockDialog.addStock
              ) : (
                productMessages.stockDialog.subtractStock
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
