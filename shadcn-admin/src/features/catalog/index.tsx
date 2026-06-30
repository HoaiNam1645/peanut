'use client'

import { useEffect, useMemo, useState } from 'react'
import Link from 'next/link'
import { PackageSearch, Search as SearchIcon } from 'lucide-react'
import { API_BASE_URL } from '@/config/api'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { LanguageSwitch } from '@/components/language-switch'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
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
  created_at?: string | null
  variants?: Array<{ sku?: string | null; variant_id?: string | null }>
  price_range?: { min?: number | null; max?: number | null } | null
}

function money(value?: number | null) {
  const n = Number(value ?? 0)
  if (!Number.isFinite(n)) return '$0.00'
  return `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function isRecent(created?: string | null) {
  if (!created) return false
  const t = new Date(created).getTime()
  if (Number.isNaN(t)) return false
  return Date.now() - t < 30 * 24 * 60 * 60 * 1000
}

export function Catalog() {
  const [items, setItems] = useState<CatalogProduct[]>([])
  const [loading, setLoading] = useState(true)
  const [q, setQ] = useState('')

  useEffect(() => {
    let active = true
    setLoading(true)
    fetch(`${API_BASE_URL}/catalog/products?per_page=100`, {
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
      (p) => p.name?.toLowerCase().includes(s) || (p.brand ?? '').toLowerCase().includes(s)
    )
  }, [items, q])

  return (
    <>
      <Header fixed>
        <Search />
        <div className='ms-auto flex items-center space-x-4'>
          <LanguageSwitch />
          <ThemeSwitch />
          <ConfigDrawer />
          <ProfileDropdown />
        </div>
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-3'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>Catalog</h2>
            <p className='text-muted-foreground'>
              Browse products — click a card to view details
            </p>
          </div>
          <div className='relative w-full sm:max-w-xs'>
            <SearchIcon className='pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground' />
            <Input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder='Search products...'
              className='pl-9'
            />
          </div>
        </div>

        {loading ? (
          <div className='grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5'>
            {Array.from({ length: 10 }).map((_, i) => (
              <Card key={i} className='gap-0 overflow-hidden rounded-md p-0'>
                <Skeleton className='aspect-[4/5] w-full rounded-none' />
                <div className='space-y-2 p-3'>
                  <Skeleton className='h-4 w-3/4' />
                  <Skeleton className='h-4 w-1/3' />
                </div>
              </Card>
            ))}
          </div>
        ) : filtered.length === 0 ? (
          <div className='flex flex-col items-center justify-center rounded-md border border-dashed py-20 text-center'>
            <PackageSearch className='mb-3 size-9 text-muted-foreground/60' strokeWidth={1.5} />
            <p className='text-sm font-medium'>No products found</p>
            <p className='text-sm text-muted-foreground'>Try a different search.</p>
          </div>
        ) : (
          <>
            <p className='text-sm text-muted-foreground'>
              {filtered.length} {filtered.length === 1 ? 'product' : 'products'}
            </p>
            <div className='grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5'>
              {filtered.map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </>
        )}
      </Main>
    </>
  )
}

function ProductCard({ product }: { product: CatalogProduct }) {
  const min = Number(product.price_range?.min ?? 0)
  const max = Number(product.price_range?.max ?? 0)
  const hasPrice = min > 0 || max > 0
  const showFrom = hasPrice && min !== max
  const sizes = (product.sizes ?? []).filter(Boolean)
  const sku = product.variants?.[0]?.sku ?? product.variants?.[0]?.variant_id ?? null
  const outOfStock = (product.total_stock ?? 0) <= 0
  const isNew = isRecent(product.created_at) && !outOfStock

  return (
    <Link href={`/product/${product.id}`} className='group block'>
      <Card className='gap-0 overflow-hidden rounded-md p-0 transition-shadow hover:shadow-md'>
        <div className='relative aspect-[4/5] overflow-hidden bg-muted'>
          {product.mockup ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={product.mockup}
              alt={product.name}
              loading='lazy'
              className='size-full object-cover transition-transform duration-500 group-hover:scale-105'
            />
          ) : (
            <div className='grid size-full place-items-center text-muted-foreground/40'>
              <PackageSearch className='size-8' strokeWidth={1.5} />
            </div>
          )}
          {isNew ? (
            <span className='absolute bottom-2.5 left-2.5 rounded bg-orange-500 px-2 py-0.5 text-[11px] font-semibold text-white'>
              New
            </span>
          ) : null}
          {outOfStock ? (
            <span className='absolute bottom-2.5 left-2.5 rounded bg-foreground/80 px-2 py-0.5 text-[11px] font-semibold text-background'>
              Sold out
            </span>
          ) : null}
        </div>
        <CardContent className='space-y-2 p-4'>
          <h3 className='line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug group-hover:text-primary'>
            {product.name}
          </h3>

          <div className='text-[17px] font-bold text-emerald-600 dark:text-emerald-500'>
            {hasPrice ? (
              <>
                {showFrom ? (
                  <span className='mr-1 text-xs font-medium text-muted-foreground'>from</span>
                ) : null}
                {money(min)}
              </>
            ) : (
              <span className='text-muted-foreground'>—</span>
            )}
          </div>

          {sku ? (
            <div className='text-[12px] text-muted-foreground'>
              <span className='font-semibold text-foreground'>SKU:</span> {sku}
            </div>
          ) : null}

          {sizes.length > 0 ? (
            <span className='inline-block rounded bg-muted px-2.5 py-1 text-[11px] font-medium text-muted-foreground'>
              {sizes.length} {sizes.length === 1 ? 'size' : 'sizes'}
            </span>
          ) : null}
        </CardContent>
      </Card>
    </Link>
  )
}
