'use client'

import { useEffect, useMemo, useState } from 'react'
import { PackageSearch, Search } from 'lucide-react'
import { API_BASE_URL } from '@/config/api'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'

type CatalogProduct = {
  id: number
  name: string
  brand?: string | null
  mockup?: string | null
  colors?: string[]
  sizes?: string[]
  total_stock?: number
  price_range?: { min?: number | null; max?: number | null } | null
}

function money(value?: number | null) {
  const n = Number(value ?? 0)
  if (!Number.isFinite(n)) return '$0.00'
  return `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

export default function CatalogHomePage() {
  const [items, setItems] = useState<CatalogProduct[]>([])
  const [loading, setLoading] = useState(true)
  const [q, setQ] = useState('')

  useEffect(() => {
    let active = true
    setLoading(true)
    fetch(`${API_BASE_URL}/catalog/products?per_page=60`, {
      headers: { Accept: 'application/json' },
    })
      .then((r) => r.json())
      .then((res) => {
        if (active) setItems(Array.isArray(res?.data?.data) ? res.data.data : [])
      })
      .catch(() => {
        if (active) setItems([])
      })
      .finally(() => {
        if (active) setLoading(false)
      })
    return () => {
      active = false
    }
  }, [])

  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase()
    if (!s) return items
    return items.filter(
      (p) =>
        p.name?.toLowerCase().includes(s) || (p.brand ?? '').toLowerCase().includes(s)
    )
  }, [items, q])

  return (
    <div className='min-h-svh bg-background text-foreground'>
      <header className='border-b bg-card'>
        <div className='mx-auto flex max-w-7xl flex-col gap-3 px-4 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6'>
          <div>
            <h1 className='text-xl font-bold tracking-tight'>Catalog</h1>
            <p className='text-sm text-muted-foreground'>
              Browse our products, variants and pricing
            </p>
          </div>
          <div className='relative w-full sm:max-w-xs'>
            <Search className='pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground' />
            <Input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder='Search products...'
              className='pl-9'
            />
          </div>
        </div>
      </header>

      <main className='mx-auto max-w-7xl px-4 py-6 sm:px-6'>
        {loading ? (
          <div className='grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'>
            {Array.from({ length: 10 }).map((_, i) => (
              <Card key={i} className='overflow-hidden p-0'>
                <Skeleton className='aspect-square w-full rounded-none' />
                <div className='space-y-2 p-3'>
                  <Skeleton className='h-4 w-3/4' />
                  <Skeleton className='h-4 w-1/3' />
                </div>
              </Card>
            ))}
          </div>
        ) : filtered.length === 0 ? (
          <div className='flex flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center'>
            <PackageSearch className='mb-3 size-9 text-muted-foreground/60' strokeWidth={1.5} />
            <p className='text-sm font-medium'>No products found</p>
            <p className='text-sm text-muted-foreground'>Try a different search.</p>
          </div>
        ) : (
          <>
            <p className='mb-4 text-sm text-muted-foreground'>
              {filtered.length} {filtered.length === 1 ? 'product' : 'products'}
            </p>
            <div className='grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'>
              {filtered.map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </>
        )}
      </main>
    </div>
  )
}

function ProductCard({ product }: { product: CatalogProduct }) {
  const min = Number(product.price_range?.min ?? 0)
  const max = Number(product.price_range?.max ?? 0)
  const hasPrice = min > 0 || max > 0
  const sizes = (product.sizes ?? []).filter(Boolean)
  const outOfStock = (product.total_stock ?? 0) <= 0

  return (
    <Card className='group gap-0 overflow-hidden p-0 transition-shadow hover:shadow-md'>
      <div className='relative aspect-square overflow-hidden bg-muted'>
        {product.mockup ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={product.mockup}
            alt={product.name}
            loading='lazy'
            className='size-full object-cover transition-transform duration-300 group-hover:scale-105'
          />
        ) : (
          <div className='grid size-full place-items-center text-muted-foreground/40'>
            <PackageSearch className='size-8' strokeWidth={1.5} />
          </div>
        )}
        {outOfStock ? (
          <Badge variant='secondary' className='absolute top-2 left-2'>
            Sold out
          </Badge>
        ) : null}
      </div>
      <CardContent className='space-y-1.5 p-3'>
        <h3 className='line-clamp-2 min-h-[2.5rem] text-sm font-medium leading-snug'>
          {product.name}
        </h3>
        <div className='flex items-center justify-between gap-2'>
          <span className='text-base font-bold text-primary'>
            {hasPrice ? (
              <>
                {min !== max ? (
                  <span className='text-xs font-normal text-muted-foreground'>from </span>
                ) : null}
                {money(min)}
              </>
            ) : (
              <span className='text-muted-foreground'>—</span>
            )}
          </span>
          {sizes.length > 0 ? (
            <span className='text-xs text-muted-foreground'>{sizes.length} sizes</span>
          ) : null}
        </div>
      </CardContent>
    </Card>
  )
}
