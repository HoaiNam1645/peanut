'use client'

import { useEffect, useState } from 'react'
import { Loader2 } from 'lucide-react'
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
import { useI18n } from '@/context/i18n-provider'
import {
  fetchProductFilterOptions,
  type ProductDetailResult,
  updateProduct,
} from '@/services/products/api'
import { LemiexFileUploadInput } from '@/features/lemiex/components/lemiex-file-upload-input'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  product: ProductDetailResult['product'] | null
  onSaved: (product: ProductDetailResult['product']) => void
}

type FormState = {
  name: string
  style: string
  status: boolean
  category_type: 'wood'
  mockup: string
  template_url: string
  brand: string
  warehouse_name: string
}

type EditProductMessages = {
  title: string
  description: string
  name: string
  namePlaceholder: string
  style: string
  stylePlaceholder: string
  brand: string
  brandPlaceholder: string
  warehouse: string
  warehousePlaceholder: string
  category: string
  embroidery: string
  print: string
  status: string
  active: string
  inactive: string
  mockup: string
  mockupPlaceholder: string
  templateUrl: string
  templateUrlPlaceholder: string
  cancel: string
  save: string
  saving: string
  success: string
  failed: string
  productNameRequired: string
}

const defaultForm: FormState = {
  name: '',
  style: '',
  status: true,
  category_type: 'wood',
  mockup: '',
  template_url: '',
  brand: '',
  warehouse_name: '',
}

const editProductFallbackMessages: EditProductMessages = {
  title: 'Edit Product',
  description: 'Update product information and display details.',
  name: 'Product Name',
  namePlaceholder: 'e.g., Unisex Heavy Cotton Tee',
  style: 'Style',
  stylePlaceholder: 'e.g., G5000',
  brand: 'Brand',
  brandPlaceholder: 'e.g., Gildan',
  warehouse: 'Warehouse',
  warehousePlaceholder: 'e.g., Main Warehouse',
  category: 'Category',
  embroidery: 'Embroidery',
  print: 'Print',
  status: 'Status',
  active: 'Active',
  inactive: 'Inactive',
  mockup: 'Mockup URL',
  mockupPlaceholder: 'https://example.com/mockup.jpg',
  templateUrl: 'Template URL',
  templateUrlPlaceholder: 'https://example.com/template-link',
  cancel: 'Cancel',
  save: 'Update Product',
  saving: 'Updating...',
  success: 'Product updated successfully',
  failed: 'Failed to update product',
  productNameRequired: 'Product name is required',
}

export function ProductDetailEditDialog({
  open,
  onOpenChange,
  product,
  onSaved,
}: Props) {
  const { messages } = useI18n()
  const createMessages = messages.productVariants.createForm
  const detailMessages = messages.productVariants.detail
  const m: EditProductMessages = {
    ...editProductFallbackMessages,
    title:
      typeof detailMessages.editProduct === 'string'
        ? detailMessages.editProduct
        : editProductFallbackMessages.title,
    name: createMessages.productName,
    namePlaceholder: createMessages.productNamePlaceholder,
    style: createMessages.style,
    stylePlaceholder: createMessages.stylePlaceholder,
    brand: createMessages.brand,
    brandPlaceholder: createMessages.brandPlaceholder,
    warehouse: createMessages.warehouse,
    warehousePlaceholder: createMessages.warehousePlaceholder,
    category: createMessages.category,
    embroidery: detailMessages.embroidery,
    print: detailMessages.print,
    status: createMessages.status,
    active: detailMessages.active,
    inactive: detailMessages.inactive,
    mockup: createMessages.mockupUrl,
    templateUrl: editProductFallbackMessages.templateUrl,
    templateUrlPlaceholder: editProductFallbackMessages.templateUrlPlaceholder,
    cancel: createMessages.cancel,
    productNameRequired: createMessages.productNameRequired,
  }

  const [form, setForm] = useState<FormState>(defaultForm)
  const [loading, setLoading] = useState(false)
  const [filterOptions, setFilterOptions] = useState<{
    brands: string[]
    styles: string[]
  }>({ brands: [], styles: [] })

  useEffect(() => {
    if (!product) return
    setForm({
      name: product.name || '',
      style: product.style || '',
      status: product.status ?? true,
      category_type: 'wood',
      mockup: product.mockup || '',
      template_url: product.template_url || '',
      brand: product.brand || '',
      warehouse_name: product.warehouse_name || '',
    })
  }, [product])

  useEffect(() => {
    if (!open) return
    void fetchProductFilterOptions()
      .then((data) =>
        setFilterOptions({
          brands: data.brands || [],
          styles: data.styles || [],
        })
      )
      .catch(() => {})
  }, [open])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!product) return
    if (!form.name.trim()) {
      toast.error(m.productNameRequired)
      return
    }

    setLoading(true)
    try {
      const response = await updateProduct(product.id, form)
      if (response.code === 200 && response.data) {
        toast.success(m.success)
        onSaved(response.data)
        onOpenChange(false)
      } else {
        toast.error(response.message || m.failed)
      }
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.failed)
    } finally {
      setLoading(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-[840px] rounded-[8px] p-0 shadow-xl'>
        <form onSubmit={handleSubmit}>
          <DialogHeader className='border-b px-6 py-5'>
            <DialogTitle className='text-xl'>{m.title}</DialogTitle>
            <DialogDescription>{m.description}</DialogDescription>
          </DialogHeader>

          <div className='space-y-5 px-6 py-5'>
            <div className='grid gap-4 md:grid-cols-2'>
              <div className='space-y-2 md:col-span-2'>
                <label className='text-sm font-medium'>{m.name}</label>
                <Input
                  className='h-11 rounded-[6px] px-4 text-[14px]'
                  value={form.name}
                  onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                  placeholder={m.namePlaceholder}
                />
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.style}</label>
                <Input
                  className='h-11 rounded-[6px] px-4 text-[14px]'
                  list='detail-edit-product-styles'
                  value={form.style}
                  onChange={(e) => setForm((p) => ({ ...p, style: e.target.value }))}
                  placeholder={m.stylePlaceholder}
                />
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.brand}</label>
                <Input
                  className='h-11 rounded-[6px] px-4 text-[14px]'
                  list='detail-edit-product-brands'
                  value={form.brand}
                  onChange={(e) => setForm((p) => ({ ...p, brand: e.target.value }))}
                  placeholder={m.brandPlaceholder}
                />
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.warehouse}</label>
                <Input
                  className='h-11 rounded-[6px] px-4 text-[14px]'
                  value={form.warehouse_name}
                  onChange={(e) =>
                    setForm((p) => ({ ...p, warehouse_name: e.target.value }))
                  }
                  placeholder={m.warehousePlaceholder}
                />
              </div>

              <div className='space-y-2'>
                <label className='text-sm font-medium'>{m.status}</label>
                <Select
                  value={form.status ? 'true' : 'false'}
                  onValueChange={(value) =>
                    setForm((p) => ({ ...p, status: value === 'true' }))
                  }
                >
                  <SelectTrigger className='h-11 w-full rounded-[6px] px-4 text-[14px]'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='true'>{m.active}</SelectItem>
                    <SelectItem value='false'>{m.inactive}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className='space-y-2 md:col-span-2'>
                <label className='text-sm font-medium'>{m.mockup}</label>
                <LemiexFileUploadInput
                  type='mockup'
                  value={form.mockup}
                  onChange={(value) => setForm((p) => ({ ...p, mockup: value }))}
                  placeholder={m.mockupPlaceholder}
                  showPreview
                />
              </div>

              <div className='space-y-2 md:col-span-2'>
                <label className='text-sm font-medium'>{m.templateUrl}</label>
                <Input
                  className='h-11 rounded-[6px] px-4 text-[14px]'
                  value={form.template_url}
                  onChange={(e) =>
                    setForm((p) => ({ ...p, template_url: e.target.value }))
                  }
                  placeholder={m.templateUrlPlaceholder}
                />
              </div>
            </div>

            <datalist id='detail-edit-product-brands'>
              {filterOptions.brands.map((brand) => (
                <option key={brand} value={brand} />
              ))}
            </datalist>
            <datalist id='detail-edit-product-styles'>
              {filterOptions.styles.map((style) => (
                <option key={style} value={style} />
              ))}
            </datalist>
          </div>

          <DialogFooter className='border-t px-6 py-4'>
            <Button
              type='button'
              variant='outline'
              className='rounded-[6px]'
              onClick={() => onOpenChange(false)}
            >
              {m.cancel}
            </Button>
            <Button type='submit' className='rounded-[6px]' disabled={loading}>
              {loading ? (
                <>
                  <Loader2 className='size-4 animate-spin' />
                  {m.saving}
                </>
              ) : (
                m.save
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
