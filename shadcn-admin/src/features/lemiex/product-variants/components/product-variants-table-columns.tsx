'use client'

import { type ColumnDef } from '@tanstack/react-table'
import { Eye, Package, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { ProductWithVariants } from '@/services/products/api'
import type { useI18n } from '@/context/i18n-provider'

type ProductVariantsMessages =
  ReturnType<typeof useI18n>['messages']['productVariants']

type GetProductVariantsColumnsOptions = {
  isSeller: boolean
  messages: ProductVariantsMessages
  onView: (product: ProductWithVariants) => void
  onStock: (product: ProductWithVariants) => void
  onDelete: (product: ProductWithVariants) => void
}

function formatCurrency(amount?: number | null) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(amount || 0)
}

export function getProductVariantsColumns({
  isSeller,
  messages,
  onView,
  onStock,
  onDelete,
}: GetProductVariantsColumnsOptions): ColumnDef<ProductWithVariants>[] {
  const columns: ColumnDef<ProductWithVariants>[] = [
    {
      accessorKey: 'product',
      header: messages.columns.product,
      meta: {
        thClassName: 'min-w-[320px]',
        tdClassName: 'align-middle',
      },
      cell: ({ row }) => {
        const product = row.original

        return (
          <div className='flex items-center gap-3'>
            <div className='flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-[6px] border bg-muted/30'>
              {product.mockup ? (
                <img
                  src={product.mockup}
                  alt={product.name}
                  className='h-full w-full object-cover'
                />
              ) : (
                <Package className='size-5 text-muted-foreground' />
              )}
            </div>

            <div className='min-w-0 space-y-1'>
              <div className='line-clamp-2 text-[14px] font-semibold leading-5 text-foreground'>
                {product.name}
              </div>
              <div className='text-[12px] text-muted-foreground'>
                {product.brand || messages.status.noBrand}
              </div>
              <div className='text-[12px] font-semibold uppercase tracking-wide text-foreground'>
                {product.style || messages.status.noStyle}
              </div>
              <div className='text-[12px] text-muted-foreground'>
                {product.template_url ? (
                  <a
                    href={product.template_url}
                    target='_blank'
                    rel='noreferrer'
                    className='break-all text-primary hover:underline'
                  >
                    {messages.columns.templateUrl}: {product.template_url}
                  </a>
                ) : (
                  `${messages.columns.templateUrl}: ${messages.status.noTemplate}`
                )}
              </div>
            </div>
          </div>
        )
      },
    },
    {
      accessorKey: 'sizes',
      header: messages.columns.sizes,
      meta: {
        thClassName: 'min-w-[160px] text-center',
        tdClassName: 'align-middle text-center',
      },
      cell: ({ row }) => {
        const sizes = row.original.sizes || []

        if (!sizes.length) {
          return (
            <span className='text-xs text-muted-foreground'>
              {messages.status.noSizes}
            </span>
          )
        }

        return (
          <div className='mx-auto flex w-full max-w-[160px] flex-wrap items-center justify-center gap-2'>
            {sizes.map((size) => (
              <Badge
                key={size}
                variant='outline'
                className='min-w-10 justify-center rounded-[6px] px-2 py-1 text-[12px] font-medium'
              >
                {size}
              </Badge>
            ))}
          </div>
        )
      },
    },
    {
      accessorKey: 'variants',
      header: messages.columns.variants,
      meta: {
        thClassName: 'min-w-[120px] text-center',
        tdClassName: 'align-middle text-center',
      },
      cell: ({ row }) => (
        <div className='space-y-1 text-center'>
          <div className='text-[16px] font-semibold leading-none'>
            {row.original.total_variants || 0}
          </div>
          <div className='text-xs text-muted-foreground'>
            {row.original.active_variants || 0} {messages.status.active}
          </div>
        </div>
      ),
    },
    {
      accessorKey: 'total_stock',
      header: messages.columns.totalStock,
      meta: {
        thClassName: 'min-w-[130px] text-center',
        tdClassName: 'align-middle text-center',
      },
      cell: ({ row }) => {
        const totalStock = row.original.total_stock || 0

        return (
          <span
            className={[
              'inline-flex min-w-[72px] justify-center rounded-[6px] px-3 py-1 text-[16px] font-semibold leading-none',
              totalStock > 100
                ? 'bg-emerald-50 text-emerald-700'
                : totalStock > 0
                  ? 'bg-amber-50 text-amber-700'
                  : 'bg-rose-50 text-rose-700',
            ].join(' ')}
          >
            {totalStock}
          </span>
        )
      },
    },
    {
      accessorKey: 'price_range',
      header: messages.columns.priceRange,
      meta: {
        thClassName: 'min-w-[150px] text-center',
        tdClassName: 'align-middle text-center',
      },
      cell: ({ row }) => {
        const priceRange = row.original.price_range

        if (!priceRange?.min && !priceRange?.max) {
          return (
            <span className='text-xs text-muted-foreground'>{messages.status.na}</span>
          )
        }

        if (priceRange?.min === priceRange?.max) {
          return <span className='font-semibold'>{formatCurrency(priceRange?.min)}</span>
        }

        return (
          <div className='space-y-1 text-center'>
            <div className='font-semibold'>{formatCurrency(priceRange?.min)}</div>
            <div className='text-xs text-muted-foreground'>{messages.status.to}</div>
            <div className='font-semibold'>{formatCurrency(priceRange?.max)}</div>
          </div>
        )
      },
    },
    {
      accessorKey: 'status',
      header: messages.columns.status,
      meta: {
        thClassName: 'min-w-[110px] text-center',
        tdClassName: 'align-middle text-center',
      },
      cell: ({ row }) =>
        row.original.status ? (
          <Badge className='min-w-[84px] justify-center rounded-[6px] bg-emerald-50 px-3 py-1 text-[13px] text-emerald-700 hover:bg-emerald-50'>
            {messages.status.activeLabel}
          </Badge>
        ) : (
          <Badge className='min-w-[84px] justify-center rounded-[6px] bg-rose-50 px-3 py-1 text-[13px] text-rose-700 hover:bg-rose-50'>
            {messages.status.inactiveLabel}
          </Badge>
        ),
    },
    {
      accessorKey: 'actions',
      header: messages.columns.actions,
      meta: {
        thClassName: isSeller ? 'min-w-[120px] text-center' : 'min-w-[350px] text-center',
        tdClassName: 'align-middle text-center',
      },
      cell: ({ row }) => {
        const product = row.original

        return (
          <div className='flex flex-nowrap items-center justify-center gap-2'>
            {!isSeller ? (
              <Button
                size='sm'
                variant='outline'
                className='h-8 min-w-[96px] rounded-[6px] text-[13px]'
                onClick={() => onStock(product)}
              >
                <Package className='size-4' />
                {messages.actions.stock}
              </Button>
            ) : null}

            <Button
              size='sm'
              variant='outline'
              className='h-8 min-w-[96px] rounded-[6px] text-[13px]'
              onClick={() => onView(product)}
            >
              <Eye className='size-4' />
              {messages.actions.view}
            </Button>

            {!isSeller ? (
              <Button
                size='sm'
                variant='outline'
                className='h-8 min-w-[96px] rounded-[6px] border-rose-200 text-[13px] text-rose-600 hover:bg-rose-50 hover:text-rose-700'
                onClick={() => onDelete(product)}
              >
                <Trash2 className='size-4' />
                {messages.actions.delete}
              </Button>
            ) : null}
          </div>
        )
      },
    },
  ]

  return columns
}
