'use client'

import { useEffect, useMemo, useState } from 'react'
import { useRouter } from 'next/navigation'
import type { ColumnDef } from '@tanstack/react-table'
import {
  ArrowLeft,
  ChevronRight,
  Loader2,
  Package,
  Pencil,
  Tags,
  Trash2,
} from 'lucide-react'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Card, CardContent } from '@/components/ui/card'
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
  fetchProductDetail,
  fetchProductMetadata,
  type ProductDetailResult,
  type ProductTier,
  type ProductVariantSummary,
  deleteProduct,
  updateProductVariant,
  updateProductVariantPricing,
} from '@/services/products/api'
import { getUserRoleName } from '@/services/auth/api'
import { useAuthStore } from '@/stores/auth-store'
import { ProductDetailEditDialog } from './product-detail-edit-dialog'
import { ProductTierPricingDialog } from './product-tier-pricing-dialog'

type Props = {
  id: string
}

const detailFallbackMessages = {
  loading: 'Loading product details...',
  loadError: 'Failed to load product details',
  notFound: 'Product not found',
  back: 'Back to Product Variants',
  active: 'Active',
  inactive: 'Inactive',
  brand: 'Brand',
  style: 'Style',
  warehouse: 'Warehouse',
  category: 'Category',
  print: 'Print',
  embroidery: 'Embroidery',
  created: 'Created',
  updated: 'Updated',
  editProduct: 'Edit Product',
  totalVariants: 'Total Variants',
  totalStock: 'Total Stock',
  priceRange: 'Price Range',
  colors: 'Colors',
  sizes: 'Sizes',
  variantsTitle: 'Variants',
  variantsCount: 'variants',
  noData: '-',
  save: 'Save',
  cancel: 'Cancel',
  edit: 'Edit',
  delete: 'Delete',
  deletePending: 'Delete flow for variant {id} will be connected next.',
  variantUpdated: 'Variant updated successfully',
  updateFailed: 'Failed to update variant',
  pricingSaved: 'Tier pricing updated successfully',
  viewPricing: 'View Pricing',
  setPricing: 'Set Pricing',
  columns: {
    variantId: 'Variant ID',
    color: 'Color',
    size: 'Size',
    stock: 'Stock',
    supplierPrice: 'Supplier Price',
    tierPricing: 'Tier Pricing',
    weight: 'Weight',
    dimensions: 'Dimensions',
    status: 'Status',
    actions: 'Actions',
  },
} as const

type EditableVariant = ProductVariantSummary & {
  tier_pricing?: Record<string, Record<string, number | string | null>> | null
}

function formatCurrency(amount?: number | null) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(amount || 0)
}

function formatDate(dateString?: string | null) {
  if (!dateString) return '-'

  try {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return '-'
  }
}

function countTierPrices(
  tierPricing?: Record<string, Record<string, number | string | null>> | null
) {
  if (!tierPricing) return 0

  return Object.values(tierPricing).reduce((count, tier) => {
    return (
      count +
      Object.values(tier || {}).filter(
        (value) => value !== null && value !== undefined && value !== ''
      ).length
    )
  }, 0)
}

export function LemiexProductVariantDetailPage({ id }: Props) {
  const router = useRouter()
  const { messages } = useI18n()
  const m = messages.productVariants.detail || detailFallbackMessages
  const currentUser = useAuthStore((state) => state.auth.user)
  const role = getUserRoleName(currentUser)
  const isSeller = role === 'Seller'

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [detail, setDetail] = useState<ProductDetailResult | null>(null)
  const [tiers, setTiers] = useState<ProductTier[]>([])
  const [editingVariantId, setEditingVariantId] = useState<number | null>(null)
  const [editingVariant, setEditingVariant] = useState<EditableVariant | null>(null)
  const [editProductOpen, setEditProductOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [pricingOpen, setPricingOpen] = useState(false)
  const [pricingVariant, setPricingVariant] = useState<ProductVariantSummary | null>(null)
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)

  useEffect(() => {
    let active = true

    async function load() {
      setLoading(true)
      setError(null)
      try {
        const [detailData, metadata] = await Promise.all([
          fetchProductDetail(id),
          fetchProductMetadata(),
        ])

        if (!active) return
        setDetail(detailData)
        setTiers(metadata.tiers || [])
      } catch (fetchError) {
        if (!active) return
        setError(fetchError instanceof Error ? fetchError.message : m.loadError)
      } finally {
        if (active) setLoading(false)
      }
    }

    void load()

    return () => {
      active = false
    }
  }, [id, m.loadError])

  const product = detail?.product || null
  const summary = detail?.summary || null
  const variants = detail?.variants || []

  useEffect(() => {
    const nextLastPage = Math.max(1, Math.ceil(variants.length / pageSize))
    if (page > nextLastPage) {
      setPage(nextLastPage)
    }
  }, [page, pageSize, variants.length])

  const priceRangeLabel = useMemo(() => {
    if (!summary?.price_range) return '-'
    const { min, max } = summary.price_range
    if (min === undefined && max === undefined) return '-'
    if (min === max) return formatCurrency(min)
    return `${formatCurrency(min)} - ${formatCurrency(max)}`
  }, [summary])

  const categoryLabel =
    product?.category_type === 'print'
      ? m.print
      : product?.category_type === 'embroidery'
        ? m.embroidery
        : m.noData

  const paginatedVariants = useMemo(() => {
    const start = (page - 1) * pageSize
    return variants.slice(start, start + pageSize)
  }, [page, pageSize, variants])

  async function handleDeleteProduct() {
    if (!product) return
    setDeleting(true)
    try {
      const res = await deleteProduct(product.id)
      toast.success(res.message || 'Đã xoá sản phẩm')
      setDeleteOpen(false)
      router.push('/lemiex/product-variants')
    } catch (e) {
      // apiRequest throws with the backend message (e.g. "đang dùng trong N đơn")
      toast.error(e instanceof Error ? e.message : 'Xoá sản phẩm thất bại')
    } finally {
      setDeleting(false)
    }
  }

  function startEditVariant(variant: ProductVariantSummary) {
    setEditingVariantId(variant.id)
    setEditingVariant({ ...variant })
  }

  function cancelEditVariant() {
    setEditingVariantId(null)
    setEditingVariant(null)
  }

  function updateEditingVariant<K extends keyof EditableVariant>(
    field: K,
    value: EditableVariant[K]
  ) {
    setEditingVariant((prev) => (prev ? { ...prev, [field]: value } : prev))
  }

  async function saveVariant(variantId: number) {
    if (!editingVariant) return

    const payload = {
      variant_id: editingVariant.variant_id,
      sku: editingVariant.sku || '',
      color: editingVariant.color || '',
      size: editingVariant.size || '',
      stock: editingVariant.stock || 0,
      supplier_price: editingVariant.supplier_price ?? null,
      weight: editingVariant.weight ?? null,
      length: editingVariant.length ?? null,
      width: editingVariant.width ?? null,
      height: editingVariant.height ?? null,
      chest_inch: editingVariant.chest_inch ?? null,
      chest_cm: editingVariant.chest_cm ?? null,
      length_inch: editingVariant.length_inch ?? null,
      length_cm: editingVariant.length_cm ?? null,
      neck_inch: editingVariant.neck_inch ?? null,
      neck_cm: editingVariant.neck_cm ?? null,
      active: editingVariant.active ?? true,
    }

    try {
      const response = await updateProductVariant(variantId, payload)

      if (response.code === 200 && response.data) {
        const updatedVariant = response.data
        toast.success(m.variantUpdated)
        setDetail((prev) =>
          prev
            ? {
                ...prev,
                variants: prev.variants.map((variant) =>
                  variant.id === variantId
                    ? {
                        ...variant,
                        ...payload,
                        ...updatedVariant,
                        length: updatedVariant.length ?? payload.length,
                        width: updatedVariant.width ?? payload.width,
                        height: updatedVariant.height ?? payload.height,
                        weight: updatedVariant.weight ?? payload.weight,
                        supplier_price:
                          updatedVariant.supplier_price ?? payload.supplier_price,
                      }
                    : variant
                ),
              }
            : prev
        )
        cancelEditVariant()
        return
      }

      toast.error(response.message || m.updateFailed)
    } catch (saveError) {
      toast.error(saveError instanceof Error ? saveError.message : m.updateFailed)
    }
  }

  async function handleSavePricing(variantId: string, prices: any[]) {
    await updateProductVariantPricing(variantId, prices)
    toast.success(m.pricingSaved)
    const detailData = await fetchProductDetail(id)
    setDetail(detailData)
  }

  const columns = useMemo<ColumnDef<ProductVariantSummary, unknown>[]>(
    () => [
      {
        accessorKey: 'variant_id',
        header: m.columns.variantId,
        meta: {
          thClassName: 'w-[220px]',
        },
        cell: ({ row }) => {
          const variant = row.original
          const isEditing = editingVariantId === variant.id && editingVariant
          const activeVariant = isEditing ? editingVariant : variant

          if (isEditing) {
            return (
              <div className='space-y-2'>
                <Input
                  className='h-9 rounded-[6px]'
                  value={activeVariant.variant_id || ''}
                  onChange={(e) => updateEditingVariant('variant_id', e.target.value)}
                />
                <Input
                  className='h-9 rounded-[6px]'
                  value={activeVariant.sku || ''}
                  onChange={(e) => updateEditingVariant('sku', e.target.value)}
                  placeholder='SKU'
                />
              </div>
            )
          }

          return (
            <div className='space-y-1'>
              <div className='font-medium text-foreground'>{variant.variant_id}</div>
              <div className='text-xs text-muted-foreground'>{variant.sku || m.noData}</div>
            </div>
          )
        },
      },
      {
        accessorKey: 'size',
        header: m.columns.size,
        meta: {
          thClassName: 'text-center w-[100px]',
          tdClassName: 'text-center',
        },
        cell: ({ row }) => {
          const variant = row.original
          const isEditing = editingVariantId === variant.id && editingVariant
          const activeVariant = isEditing ? editingVariant : variant

          if (isEditing) {
            return (
              <Input
                className='mx-auto h-9 max-w-[90px] rounded-[6px]'
                value={activeVariant.size || ''}
                onChange={(e) => updateEditingVariant('size', e.target.value)}
              />
            )
          }

          return (
            <Badge variant='outline' className='rounded-[6px]'>
              {variant.size || m.noData}
            </Badge>
          )
        },
      },
      {
        accessorKey: 'stock',
        header: m.columns.stock,
        meta: {
          thClassName: 'text-center w-[110px]',
          tdClassName: 'text-center',
        },
        cell: ({ row }) => {
          const variant = row.original
          const isEditing = editingVariantId === variant.id && editingVariant
          const activeVariant = isEditing ? editingVariant : variant

          if (isEditing) {
            return (
              <Input
                className='mx-auto h-9 max-w-[92px] rounded-[6px]'
                type='number'
                min={0}
                value={activeVariant.stock ?? 0}
                onChange={(e) => updateEditingVariant('stock', Number(e.target.value || 0))}
              />
            )
          }

          return (
            <span
              className={[
                'inline-flex min-w-[64px] justify-center rounded-[6px] px-3 py-1 text-[13px] font-semibold',
                (variant.stock || 0) > 50
                  ? 'bg-emerald-50 text-emerald-700'
                  : (variant.stock || 0) > 0
                    ? 'bg-amber-50 text-amber-700'
                    : 'bg-rose-50 text-rose-700',
              ].join(' ')}
            >
              {variant.stock || 0}
            </span>
          )
        },
      },
      {
        accessorKey: 'supplier_price',
        header: m.columns.supplierPrice,
        meta: {
          thClassName: 'text-right w-[140px]',
          tdClassName: 'text-right',
        },
        cell: ({ row }) => {
          const variant = row.original
          const isEditing = editingVariantId === variant.id && editingVariant
          const activeVariant = isEditing ? editingVariant : variant

          if (isEditing) {
            return (
              <Input
                className='ms-auto h-9 max-w-[120px] rounded-[6px] text-right'
                type='number'
                min={0}
                step='0.01'
                value={activeVariant.supplier_price ?? ''}
                onChange={(e) =>
                  updateEditingVariant(
                    'supplier_price',
                    e.target.value === '' ? null : Number(e.target.value || 0)
                  )
                }
              />
            )
          }

          return <span className='font-medium'>{formatCurrency(variant.supplier_price)}</span>
        },
      },
      {
        accessorKey: 'tier_pricing',
        header: m.columns.tierPricing,
        meta: {
          thClassName: 'text-center w-[150px]',
          tdClassName: 'text-center',
        },
        cell: ({ row }) => {
          const variant = row.original
          const pricingCount = countTierPrices(variant.tier_pricing)

          return (
            <Button
              variant='outline'
              size='sm'
              className='rounded-[6px]'
              onClick={() => {
                setPricingVariant(variant)
                setPricingOpen(true)
              }}
            >
              <span className='text-[13px]'>
                {pricingCount > 0 ? `${pricingCount} prices` : m.setPricing}
              </span>
              <ChevronRight className='size-4' />
            </Button>
          )
        },
      },
      {
        accessorKey: 'weight',
        header: m.columns.weight,
        meta: {
          thClassName: 'text-center w-[100px]',
          tdClassName: 'text-center',
        },
        cell: ({ row }) => {
          const variant = row.original
          const isEditing = editingVariantId === variant.id && editingVariant
          const activeVariant = isEditing ? editingVariant : variant

          if (isEditing) {
            return (
              <Input
                className='mx-auto h-9 max-w-[92px] rounded-[6px]'
                type='number'
                min={0}
                value={activeVariant.weight ?? ''}
                onChange={(e) =>
                  updateEditingVariant(
                    'weight',
                    e.target.value === '' ? null : Number(e.target.value || 0)
                  )
                }
              />
            )
          }

          return <span>{variant.weight ? `${variant.weight}g` : m.noData}</span>
        },
      },
      {
        accessorKey: 'dimensions',
        header: m.columns.dimensions,
        meta: {
          thClassName: 'text-center w-[180px]',
          tdClassName: 'text-center',
        },
        cell: ({ row }) => {
          const variant = row.original
          const isEditing = editingVariantId === variant.id && editingVariant
          const activeVariant = isEditing ? editingVariant : variant

          if (isEditing) {
            return (
              <div className='grid grid-cols-3 gap-2'>
                <Input
                  className='h-9 rounded-[6px]'
                  type='number'
                  min={0}
                  value={activeVariant.length ?? ''}
                  onChange={(e) =>
                    updateEditingVariant(
                      'length',
                      e.target.value === '' ? null : Number(e.target.value || 0)
                    )
                  }
                />
                <Input
                  className='h-9 rounded-[6px]'
                  type='number'
                  min={0}
                  value={activeVariant.width ?? ''}
                  onChange={(e) =>
                    updateEditingVariant(
                      'width',
                      e.target.value === '' ? null : Number(e.target.value || 0)
                    )
                  }
                />
                <Input
                  className='h-9 rounded-[6px]'
                  type='number'
                  min={0}
                  value={activeVariant.height ?? ''}
                  onChange={(e) =>
                    updateEditingVariant(
                      'height',
                      e.target.value === '' ? null : Number(e.target.value || 0)
                    )
                  }
                />
              </div>
            )
          }

          return variant.length != null &&
            variant.width != null &&
            variant.height != null ? (
            <span>
              {variant.length} × {variant.width} × {variant.height}
            </span>
          ) : (
            m.noData
          )
        },
      },
      {
        accessorKey: 'chest',
        header: 'Chest (in/cm)',
        meta: {
          thClassName: 'text-center w-[150px]',
          tdClassName: 'text-center',
        },
        cell: ({ row }) => {
          const variant = row.original
          const isEditing = editingVariantId === variant.id && editingVariant
          const activeVariant = isEditing ? editingVariant : variant

          if (isEditing) {
            return (
              <div className='grid grid-cols-2 gap-2'>
                <Input
                  className='h-9 rounded-[6px]'
                  type='number'
                  min={0}
                  step='0.01'
                  placeholder='in'
                  value={activeVariant.chest_inch ?? ''}
                  onChange={(e) =>
                    updateEditingVariant('chest_inch', e.target.value === '' ? null : Number(e.target.value || 0))
                  }
                />
                <Input
                  className='h-9 rounded-[6px]'
                  type='number'
                  min={0}
                  step='0.01'
                  placeholder='cm'
                  value={activeVariant.chest_cm ?? ''}
                  onChange={(e) =>
                    updateEditingVariant('chest_cm', e.target.value === '' ? null : Number(e.target.value || 0))
                  }
                />
              </div>
            )
          }

          return variant.chest_inch != null || variant.chest_cm != null ? (
            <span>
              {variant.chest_inch ?? '—'} / {variant.chest_cm ?? '—'}
            </span>
          ) : (
            m.noData
          )
        },
      },
      {
        accessorKey: 'garment_length',
        header: 'Length (in/cm)',
        meta: {
          thClassName: 'text-center w-[150px]',
          tdClassName: 'text-center',
        },
        cell: ({ row }) => {
          const variant = row.original
          const isEditing = editingVariantId === variant.id && editingVariant
          const activeVariant = isEditing ? editingVariant : variant

          if (isEditing) {
            return (
              <div className='grid grid-cols-2 gap-2'>
                <Input
                  className='h-9 rounded-[6px]'
                  type='number'
                  min={0}
                  step='0.01'
                  placeholder='in'
                  value={activeVariant.length_inch ?? ''}
                  onChange={(e) =>
                    updateEditingVariant('length_inch', e.target.value === '' ? null : Number(e.target.value || 0))
                  }
                />
                <Input
                  className='h-9 rounded-[6px]'
                  type='number'
                  min={0}
                  step='0.01'
                  placeholder='cm'
                  value={activeVariant.length_cm ?? ''}
                  onChange={(e) =>
                    updateEditingVariant('length_cm', e.target.value === '' ? null : Number(e.target.value || 0))
                  }
                />
              </div>
            )
          }

          return variant.length_inch != null || variant.length_cm != null ? (
            <span>
              {variant.length_inch ?? '—'} / {variant.length_cm ?? '—'}
            </span>
          ) : (
            m.noData
          )
        },
      },
      {
        accessorKey: 'neck',
        header: 'Neck (in/cm)',
        meta: {
          thClassName: 'text-center w-[150px]',
          tdClassName: 'text-center',
        },
        cell: ({ row }) => {
          const variant = row.original
          const isEditing = editingVariantId === variant.id && editingVariant
          const activeVariant = isEditing ? editingVariant : variant

          if (isEditing) {
            return (
              <div className='grid grid-cols-2 gap-2'>
                <Input
                  className='h-9 rounded-[6px]'
                  type='number'
                  min={0}
                  step='0.01'
                  placeholder='in'
                  value={activeVariant.neck_inch ?? ''}
                  onChange={(e) =>
                    updateEditingVariant('neck_inch', e.target.value === '' ? null : Number(e.target.value || 0))
                  }
                />
                <Input
                  className='h-9 rounded-[6px]'
                  type='number'
                  min={0}
                  step='0.01'
                  placeholder='cm'
                  value={activeVariant.neck_cm ?? ''}
                  onChange={(e) =>
                    updateEditingVariant('neck_cm', e.target.value === '' ? null : Number(e.target.value || 0))
                  }
                />
              </div>
            )
          }

          return variant.neck_inch != null || variant.neck_cm != null ? (
            <span>
              {variant.neck_inch ?? '—'} / {variant.neck_cm ?? '—'}
            </span>
          ) : (
            m.noData
          )
        },
      },
      {
        accessorKey: 'active',
        header: m.columns.status,
        meta: {
          thClassName: 'text-center w-[120px]',
          tdClassName: 'text-center',
        },
        cell: ({ row }) => {
          const variant = row.original
          const isEditing = editingVariantId === variant.id && editingVariant
          const activeVariant = isEditing ? editingVariant : variant

          if (isEditing) {
            return (
              <Select
                value={activeVariant.active ? 'true' : 'false'}
                onValueChange={(value) => updateEditingVariant('active', value === 'true')}
              >
                <SelectTrigger className='mx-auto h-9 w-[116px] rounded-[6px]'>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='true'>{m.active}</SelectItem>
                  <SelectItem value='false'>{m.inactive}</SelectItem>
                </SelectContent>
              </Select>
            )
          }

          return (
            <Badge
              className={
                variant.active
                  ? 'rounded-[6px] bg-emerald-50 text-emerald-700 hover:bg-emerald-50'
                  : 'rounded-[6px] bg-rose-50 text-rose-700 hover:bg-rose-50'
              }
            >
              {variant.active ? m.active : m.inactive}
            </Badge>
          )
        },
      },
      {
        id: 'actions',
        header: m.columns.actions,
        meta: {
          thClassName: 'text-center w-[170px]',
          tdClassName: 'text-center',
        },
        cell: ({ row }) => {
          const variant = row.original
          const isEditing = editingVariantId === variant.id && editingVariant

          if (isEditing) {
            return (
              <div className='flex items-center justify-center gap-2'>
                <Button size='sm' className='rounded-[6px]' onClick={() => saveVariant(variant.id)}>
                  {m.save}
                </Button>
                <Button size='sm' variant='outline' className='rounded-[6px]' onClick={cancelEditVariant}>
                  {m.cancel}
                </Button>
              </div>
            )
          }

          if (isSeller) {
            return <span className='text-muted-foreground'>-</span>
          }

          return (
            <div className='flex items-center justify-center gap-2'>
              <Button size='sm' variant='outline' className='rounded-[6px]' onClick={() => startEditVariant(variant)}>
                <Pencil className='size-4' />
                {m.edit}
              </Button>
              <Button
                size='sm'
                variant='outline'
                className='rounded-[6px] border-rose-200 text-rose-600 hover:bg-rose-50 hover:text-rose-700'
                onClick={() => toast.info(m.deletePending.replace('{id}', variant.variant_id))}
              >
                <Trash2 className='size-4' />
                {m.delete}
              </Button>
            </div>
          )
        },
      },
    ],
    [
      editingVariant,
      editingVariantId,
      isSeller,
      m.active,
      m.cancel,
      m.columns.actions,
      m.columns.color,
      m.columns.dimensions,
      m.columns.size,
      m.columns.status,
      m.columns.stock,
      m.columns.supplierPrice,
      m.columns.tierPricing,
      m.columns.variantId,
      m.columns.weight,
      m.delete,
      m.deletePending,
      m.edit,
      m.inactive,
      m.noData,
      m.save,
      m.setPricing,
      m.viewPricing,
    ]
  )

  if (loading) {
    return (
      <>
        <Header fixed>
          <Search />
        <div className='ml-auto flex items-center space-x-4'>
          <LanguageSwitch />
          <ThemeSwitch />
          <ProfileDropdown />
        </div>
        </Header>
        <Main fluid className='px-4 py-6 sm:px-5 lg:px-6 xl:px-7'>
          <div className='flex min-h-[40vh] items-center justify-center'>
            <div className='flex items-center gap-3 text-sm text-muted-foreground'>
              <Loader2 className='size-4 animate-spin' />
              {m.loading}
            </div>
          </div>
        </Main>
      </>
    )
  }

  if (error || !product) {
    return (
      <>
        <Header fixed>
          <Search />
        </Header>
        <Main fluid className='px-4 py-6 sm:px-5 lg:px-6 xl:px-7'>
          <div className='mx-auto max-w-[880px] rounded-[8px] border border-rose-200 bg-rose-50 p-4 text-rose-700'>
            {error || m.notFound}
          </div>
          <div className='mt-4'>
            <Button variant='outline' className='rounded-[6px]' onClick={() => router.push('/lemiex/product-variants')}>
              <ArrowLeft className='size-4' />
              {m.back}
            </Button>
          </div>
        </Main>
      </>
    )
  }

  return (
    <>
      <Header fixed>
        <Search />
      </Header>

      <Main fluid className='space-y-4 px-4 py-4 pb-8 sm:px-5 lg:px-6 xl:px-7'>
        <div className='mx-auto w-full max-w-[1560px] space-y-5'>
          <div className='flex items-center gap-3'>
            <Button variant='outline' className='rounded-[6px]' onClick={() => router.push('/lemiex/product-variants')}>
              <ArrowLeft className='size-4' />
              {m.back}
            </Button>
          </div>

          <Card className='rounded-[12px] border-border/80 shadow-none'>
            <CardContent className='space-y-5 p-5'>
              <div className='flex flex-col gap-5 xl:flex-row xl:items-start'>
                <div className='flex size-[220px] max-w-full shrink-0 items-center justify-center overflow-hidden rounded-[16px] border bg-muted/20 p-3 lg:size-[232px]'>
                  {product.mockup ? (
                    <img src={product.mockup} alt={product.name} className='h-full w-full object-cover' />
                  ) : (
                    <Package className='size-10 text-muted-foreground' />
                  )}
                </div>

                <div className='min-w-0 flex-1 space-y-4'>
                  <div className='flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between'>
                    <div className='space-y-3'>
                      <div className='flex flex-wrap items-center gap-3'>
                        <h1 className='text-[32px] leading-[1.05] font-semibold tracking-tight'>{product.name}</h1>
                        <Badge className='rounded-full bg-muted px-3 py-1 text-[11px] text-foreground hover:bg-muted'>
                          {product.status ? m.active : m.inactive}
                        </Badge>
                      </div>

                      <div className='flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px] text-muted-foreground'>
                        <div className='flex items-center gap-2'>
                          <span className='text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground/80'>{m.brand}:</span>
                          <span className='text-[13px] font-medium text-foreground'>{product.brand || m.noData}</span>
                        </div>
                        <div className='flex items-center gap-2'>
                          <span className='text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground/80'>{m.style}:</span>
                          <span className='text-[13px] font-medium text-foreground'>{product.style || m.noData}</span>
                        </div>
                        <div className='flex items-center gap-2'>
                          <span className='text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground/80'>{m.warehouse}:</span>
                          <span className='text-[13px] font-medium text-foreground'>{product.warehouse_name || m.noData}</span>
                        </div>
                        <div className='flex items-center gap-2'>
                          <span className='text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground/80'>{m.category}:</span>
                          <span className='text-[13px] font-medium text-foreground'>{categoryLabel}</span>
                        </div>
                        <div className='flex items-center gap-2'>
                          <span className='text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground/80'>
                            Template:
                          </span>
                          {product.template_url ? (
                            <a
                              href={product.template_url}
                              target='_blank'
                              rel='noreferrer'
                              className='max-w-[320px] truncate text-[13px] font-medium text-primary hover:underline'
                            >
                              {product.template_url}
                            </a>
                          ) : (
                            <span className='text-[13px] font-medium text-foreground'>{m.noData}</span>
                          )}
                        </div>
                      </div>

                      <div className='grid gap-x-10 gap-y-2 border-t pt-4 text-[13px] text-muted-foreground sm:grid-cols-2'>
                        <div className='space-y-1'>
                          <div className='text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground/80'>{m.created}</div>
                          <div className='text-[13px] text-foreground'>{formatDate(product.created_at)}</div>
                        </div>
                        <div className='space-y-1'>
                          <div className='text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground/80'>{m.updated}</div>
                          <div className='text-[13px] text-foreground'>{formatDate(product.updated_at)}</div>
                        </div>
                      </div>
                    </div>

                    {!isSeller ? (
                      <div className='flex items-center gap-2'>
                        <Button className='h-10 rounded-[8px] px-4 text-[12px]' onClick={() => setEditProductOpen(true)}>
                          <Pencil className='size-4' />
                          {m.editProduct}
                        </Button>
                        <Button
                          variant='destructive'
                          className='h-10 rounded-[8px] px-4 text-[12px]'
                          onClick={() => setDeleteOpen(true)}
                        >
                          <Trash2 className='size-4' />
                          Xoá sản phẩm
                        </Button>
                      </div>
                    ) : null}
                  </div>
                </div>
              </div>

              <div className='border-t pt-5'>
                <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-4'>
                  <Card className='rounded-[14px] border-border/70 shadow-none'>
                    <CardContent className='flex min-h-[108px] items-center gap-4 p-5'>
                      <div className='flex size-10 items-center justify-center rounded-[12px] bg-muted'>
                        <Package className='size-4.5 text-foreground' />
                      </div>
                      <div className='space-y-1'>
                        <div className='text-[22px] leading-none font-semibold'>{summary?.total_variants || 0}</div>
                        <div className='text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground'>{m.totalVariants}</div>
                        <div className='text-[13px] text-muted-foreground'>{summary?.active_variants || 0} {m.active}</div>
                      </div>
                    </CardContent>
                  </Card>

                  <Card className='rounded-[14px] border-border/70 shadow-none'>
                    <CardContent className='flex min-h-[108px] items-center gap-4 p-5'>
                      <div className='flex size-10 items-center justify-center rounded-[12px] bg-muted'>
                        <Package className='size-4.5 text-foreground' />
                      </div>
                      <div className='space-y-1'>
                        <div className='text-[22px] leading-none font-semibold'>{summary?.total_stock || 0}</div>
                        <div className='text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground'>{m.totalStock}</div>
                      </div>
                    </CardContent>
                  </Card>

                  <Card className='rounded-[14px] border-border/70 shadow-none'>
                    <CardContent className='flex min-h-[108px] items-center gap-4 p-5'>
                      <div className='flex size-10 items-center justify-center rounded-[12px] bg-muted'>
                        <Tags className='size-4.5 text-foreground' />
                      </div>
                      <div className='space-y-1'>
                        <div className='text-[20px] leading-tight font-semibold'>{priceRangeLabel}</div>
                        <div className='text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground'>{m.priceRange}</div>
                      </div>
                    </CardContent>
                  </Card>

                  <Card className='rounded-[14px] border-border/70 shadow-none'>
                    <CardContent className='flex min-h-[108px] items-center gap-4 p-5'>
                      <div className='flex size-10 items-center justify-center rounded-[12px] bg-muted'>
                        <Tags className='size-4.5 text-foreground' />
                      </div>
                      <div className='space-y-1'>
                        <div className='text-[22px] leading-none font-semibold'>{summary?.colors?.length || 0}</div>
                        <div className='text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground'>{m.colors}</div>
                        <div className='text-[13px] text-muted-foreground'>{summary?.sizes?.length || 0} {m.sizes}</div>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              </div>
            </CardContent>
          </Card>

          <div className='rounded-[8px]'>
            <div className='p-0'>
              <LemiexDataTable
                columns={columns}
                data={paginatedVariants}
                page={page}
                pageSize={pageSize}
                total={variants.length}
                onPageChange={setPage}
                onPageSizeChange={(nextPageSize) => {
                  setPage(1)
                  setPageSize(nextPageSize)
                }}
                emptyText={m.notFound}
              />
            </div>
          </div>
        </div>

        <ProductDetailEditDialog
          open={editProductOpen}
          onOpenChange={setEditProductOpen}
          product={product}
          onSaved={(updated) =>
            setDetail((prev) => (prev ? { ...prev, product: { ...prev.product, ...updated } } : prev))
          }
        />

        <ProductTierPricingDialog
          open={pricingOpen}
          onOpenChange={setPricingOpen}
          variant={pricingVariant}
          tiers={tiers}
          readOnly={isSeller}
          onSave={handleSavePricing}
        />

        <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
          <AlertDialogContent className='rounded-[10px]'>
            <AlertDialogHeader>
              <AlertDialogTitle>Xoá sản phẩm?</AlertDialogTitle>
              <AlertDialogDescription>
                Xoá &quot;{product?.name}&quot; và toàn bộ {summary?.total_variants || 0} biến thể
                (kèm giá, lịch sử kho). Hành động này không thể hoàn tác. Nếu sản phẩm đang được
                dùng trong đơn hàng, hệ thống sẽ chặn.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel className='rounded-[8px]' disabled={deleting}>
                Huỷ
              </AlertDialogCancel>
              <AlertDialogAction
                className='rounded-[8px] bg-destructive text-destructive-foreground hover:bg-destructive/90'
                disabled={deleting}
                onClick={(e) => {
                  e.preventDefault()
                  void handleDeleteProduct()
                }}
              >
                {deleting ? 'Đang xoá…' : 'Xoá'}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </Main>
    </>
  )
}
