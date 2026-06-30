'use client'

import { useEffect, useMemo, useState } from 'react'
import Link from 'next/link'
import { useParams } from 'next/navigation'
import { Check, ChevronLeft, ChevronRight, Download, Loader2, MapPin } from 'lucide-react'
import { API_BASE_URL } from '@/config/api'
import { cn } from '@/lib/utils'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { LanguageSwitch } from '@/components/language-switch'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'

// ---- types (shape of /api/catalog/products/{id}) ----
type Variant = {
  id: number
  variant_id: string
  sku: string | null
  style: string | null
  color: string | null
  size: string | null
  stock: number
  supplier_price: string | number | null
  weight: number | null
  length: number | null
  width: number | null
  height: number | null
  chest_inch?: number | null
  chest_cm?: number | null
  length_inch?: number | null
  length_cm?: number | null
  neck_inch?: number | null
  neck_cm?: number | null
  tier_pricing?: Record<string, Record<string, string | number>>
}

type ProductDetail = {
  product: {
    id: number
    name: string
    style: string | null
    brand: string | null
    mockup: string | null
    template_url: string | null
    images?: string[] | null
    warehouse_name?: string | null
  }
  summary: {
    colors: string[]
    sizes: string[]
    total_stock: number
    price_range: { min: number | string | null; max: number | string | null }
  }
  variants: Variant[]
}

// ---- helpers ----
const COLOR_HEX: Record<string, string> = {
  black: '#18181b', white: '#ffffff', red: '#dc2626', blue: '#2563eb',
  navy: '#1e3a8a', green: '#16a34a', yellow: '#eab308', orange: '#ea580c',
  purple: '#7c3aed', pink: '#ec4899', gray: '#9ca3af', grey: '#9ca3af',
  brown: '#92400e', beige: '#d6c7a1', maroon: '#7f1d1d', teal: '#0d9488',
  charcoal: '#374151', cream: '#f5f0e1', khaki: '#bda66a', gold: '#d4af37',
  silver: '#c0c0c0', 'sport grey': '#b6b6b6',
}
const swatch = (c?: string | null) =>
  (c ? COLOR_HEX[c.toLowerCase().trim()] : undefined) ?? '#cbd5e1'

function money(v?: number | string | null) {
  const n = Number(v ?? 0)
  if (!Number.isFinite(n)) return '$0.00'
  return `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const SIZE_ORDER = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL', '5XL', '6XL', '7XL']
function sortSizes(sizes: Array<string | null | undefined>): string[] {
  return sizes
    .filter((s): s is string => Boolean(s && String(s).trim()))
    .sort((a, b) => {
      const ia = SIZE_ORDER.indexOf(String(a).toUpperCase())
      const ib = SIZE_ORDER.indexOf(String(b).toUpperCase())
      return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib) || String(a).localeCompare(String(b))
    })
}

function fmtMeasure(inch?: number | null, cm?: number | null): string {
  const i = inch != null && Number(inch) !== 0 ? `${Number(inch).toFixed(1)}"` : null
  const c = cm != null && Number(cm) !== 0 ? `${Number(cm).toFixed(1)}cm` : null
  return [i, c].filter(Boolean).join(' / ') || '—'
}

const TYPE_LABELS: Record<string, string> = {
  base_cost: 'Base cost', seller_shipping: 'Seller shipping', tiktok_shipping: 'TikTok shipping',
  priority_shipping: 'Priority shipping', shipping_cost: 'Shipping',
  additional_standard: '+ Standard item', additional_priority: '+ Priority item',
  front: 'Print: Front', back: 'Print: Back', sleeve_left: 'Print: Left sleeve',
  sleeve_right: 'Print: Right sleeve', special: 'Special',
}
const TIER_ORDER = ['Silver', 'Gold', 'Platinum', 'Diamond']
const priceLabel = (t: string) =>
  TYPE_LABELS[t] ?? t.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())

// ---- chrome ----
function CatalogHeader() {
  return (
    <Header fixed>
      <Search />
      <div className='ms-auto flex items-center space-x-4'>
        <LanguageSwitch />
        <ThemeSwitch />
        <ConfigDrawer />
        <ProfileDropdown />
      </div>
    </Header>
  )
}

export function CatalogProductDetail() {
  const params = useParams<{ id: string }>()
  const id = params?.id
  const [data, setData] = useState<ProductDetail | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!id) return
    let active = true
    setLoading(true)
    setError(null)
    fetch(`${API_BASE_URL}/catalog/products/${id}`, { headers: { Accept: 'application/json' } })
      .then(async (r) => {
        const body = await r.json().catch(() => ({}))
        if (!r.ok || body?.status === false) throw new Error(body?.message || 'Failed to load')
        return body.data as ProductDetail
      })
      .then((d) => active && setData(d))
      .catch((e) => active && setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [id])

  return (
    <>
      <CatalogHeader />
      <Main>
        {loading ? (
          <div className='grid gap-7 lg:grid-cols-2'>
            <Skeleton className='aspect-square rounded-md' />
            <div className='space-y-4'>
              <Skeleton className='h-6 w-2/3' />
              <Skeleton className='h-9 w-1/3' />
              <Skeleton className='h-24 w-full' />
              <Skeleton className='h-32 w-full' />
            </div>
          </div>
        ) : error ? (
          <div>
            <div className='rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive'>
              {error}
            </div>
            <Link href='/' className='mt-4 inline-block text-sm font-medium text-primary hover:underline'>
              ← Back to catalog
            </Link>
          </div>
        ) : data ? (
          <Detail data={data} />
        ) : (
          <div className='grid min-h-[50vh] place-items-center'>
            <Loader2 className='size-6 animate-spin text-muted-foreground' />
          </div>
        )}
      </Main>
    </>
  )
}

function Detail({ data }: { data: ProductDetail }) {
  const { product, summary, variants } = data
  const colors = useMemo(() => (summary.colors ?? []).filter((c) => c && c.trim()), [summary.colors])
  const sizes = useMemo(() => sortSizes(summary.sizes ?? []), [summary.sizes])
  const hasColors = colors.length > 0

  const [selColor, setSelColor] = useState('')
  const [selSize, setSelSize] = useState('')

  const gallery = useMemo(() => {
    const all = [product.mockup, ...(product.images ?? [])].filter(
      (u): u is string => !!u && u.trim() !== ''
    )
    return Array.from(new Set(all))
  }, [product.mockup, product.images])
  const [imgIdx, setImgIdx] = useState(0)
  const mainImg = gallery[imgIdx] ?? gallery[0] ?? null
  const prevImg = () => setImgIdx((i) => (i - 1 + gallery.length) % gallery.length)
  const nextImg = () => setImgIdx((i) => (i + 1) % gallery.length)

  useEffect(() => {
    const first = variants.find((v) => Number(v.stock) > 0) ?? variants[0]
    if (first) {
      setSelColor(first.color ?? '')
      setSelSize(first.size ?? '')
    }
  }, [variants])
  useEffect(() => setImgIdx(0), [gallery])

  const sizesForColor = useMemo(() => {
    if (!hasColors) return sizes
    const set = new Set(
      variants.filter((v) => (v.color ?? '') === selColor && v.size).map((v) => v.size as string)
    )
    return sortSizes([...set])
  }, [variants, selColor, sizes, hasColors])

  const selected = useMemo(
    () =>
      variants.find(
        (v) => (hasColors ? (v.color ?? '') === selColor : true) && (v.size ?? '') === selSize
      ) ?? null,
    [variants, selColor, selSize, hasColors]
  )

  const min = Number(summary.price_range?.min ?? 0)
  const max = Number(summary.price_range?.max ?? 0)

  return (
    <div className='flex flex-1 flex-col gap-8'>
      {/* breadcrumb */}
      <nav className='flex items-center gap-1.5 text-[13px] text-muted-foreground'>
        <Link href='/' className='hover:text-foreground'>Catalog</Link>
        <ChevronRight className='size-3.5' />
        <span className='truncate font-medium text-foreground'>{product.name}</span>
      </nav>

      <div className='grid gap-7 lg:grid-cols-2'>
        {/* Gallery */}
        <div className='lg:sticky lg:top-20 lg:self-start'>
          <div className='group relative aspect-square overflow-hidden rounded-md border bg-muted'>
            {mainImg ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={mainImg} alt={product.name} className='h-full w-full object-cover' />
            ) : (
              <div className='size-full bg-muted' />
            )}
            {gallery.length > 1 ? (
              <>
                <button
                  type='button'
                  onClick={prevImg}
                  aria-label='Previous image'
                  className='absolute top-1/2 left-2.5 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-background/85 text-foreground shadow-sm ring-1 ring-border backdrop-blur transition hover:bg-background'
                >
                  <ChevronLeft className='size-5' />
                </button>
                <button
                  type='button'
                  onClick={nextImg}
                  aria-label='Next image'
                  className='absolute top-1/2 right-2.5 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-background/85 text-foreground shadow-sm ring-1 ring-border backdrop-blur transition hover:bg-background'
                >
                  <ChevronRight className='size-5' />
                </button>
                <div className='absolute bottom-2.5 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-2 py-0.5 text-[11px] font-medium text-white'>
                  {imgIdx + 1}/{gallery.length}
                </div>
              </>
            ) : null}
          </div>

          {gallery.length > 1 ? (
            <div className='mt-3 flex flex-wrap gap-2.5'>
              {gallery.map((img, i) => (
                <button
                  key={i}
                  type='button'
                  onMouseEnter={() => setImgIdx(i)}
                  onClick={() => setImgIdx(i)}
                  className={cn(
                    'size-16 overflow-hidden rounded-md border bg-muted transition',
                    imgIdx === i ? 'border-primary ring-2 ring-primary/30' : 'hover:border-foreground/30'
                  )}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={img} alt='' className='h-full w-full object-cover' />
                </button>
              ))}
            </div>
          ) : null}

          {product.template_url ? (
            <a
              href={product.template_url}
              target='_blank'
              rel='noopener noreferrer'
              className='mt-3 flex items-center justify-center gap-2 rounded-md border bg-card py-2.5 text-[13px] font-semibold text-foreground/80 transition hover:border-primary hover:text-primary'
            >
              <Download className='size-4' /> Download mockup &amp; template
            </a>
          ) : null}
        </div>

        {/* Buy box */}
        <div>
          <div className='flex flex-wrap items-center gap-2'>
            {product.style ? <Badge variant='secondary'>{product.style}</Badge> : null}
            {product.brand ? <Badge>{product.brand}</Badge> : null}
            {product.warehouse_name ? (
              <Badge variant='secondary'>
                <MapPin className='mr-1 inline size-3' /> {product.warehouse_name}
              </Badge>
            ) : null}
          </div>

          <h1 className='mt-2.5 text-[26px] font-bold leading-tight tracking-tight'>
            {product.name}
          </h1>

          <div className='mt-3 flex items-baseline gap-2'>
            <span className='text-[28px] font-extrabold tracking-tight text-primary'>
              {selected?.supplier_price
                ? money(selected.supplier_price)
                : min === max
                  ? money(min)
                  : `${money(min)} – ${money(max)}`}
            </span>
            <span className='text-[13px] text-muted-foreground'>base cost</span>
          </div>

          {hasColors ? (
            <div className='mt-5'>
              <div className='mb-2 flex items-center gap-2 text-[13px] font-semibold'>
                Color
                <span className='font-normal text-muted-foreground'>· {selColor || '—'}</span>
              </div>
              <div className='flex flex-wrap gap-2'>
                {colors.map((c) => (
                  <button
                    key={c}
                    onClick={() => setSelColor(c)}
                    title={c}
                    className={cn(
                      'size-8 rounded-full ring-1 ring-inset ring-black/10 transition',
                      selColor === c && 'ring-2 ring-primary ring-offset-2 ring-offset-background'
                    )}
                    style={{ background: swatch(c) }}
                  />
                ))}
              </div>
            </div>
          ) : null}

          {sizes.length > 0 ? (
            <div className='mt-5'>
              <div className='mb-2 text-[13px] font-semibold'>Size</div>
              <div className='flex flex-wrap gap-2'>
                {sizes.map((s) => {
                  const available = sizesForColor.includes(s)
                  return (
                    <button
                      key={s}
                      disabled={!available}
                      onClick={() => setSelSize(s)}
                      className={cn(
                        'min-w-11 rounded-md border px-3 py-2 text-[13px] font-semibold transition',
                        selSize === s
                          ? 'border-primary bg-primary text-primary-foreground'
                          : 'bg-card text-foreground/80 hover:border-foreground/30',
                        !available && 'cursor-not-allowed line-through opacity-35'
                      )}
                    >
                      {s}
                    </button>
                  )
                })}
              </div>
            </div>
          ) : null}

          {/* Description + details + highlights */}
          <div className='mt-7 space-y-5 border-t pt-6'>
            <p className='text-[14px] leading-relaxed text-muted-foreground'>
              {product.name} — made to order with premium print &amp; embroidery. No order
              minimums, fast production, and worldwide fulfillment.
            </p>

            <div>
              <h3 className='mb-2 text-[12px] font-bold tracking-wider text-muted-foreground uppercase'>
                Product details
              </h3>
              <dl className='grid grid-cols-1 gap-x-8 sm:grid-cols-2'>
                {(
                  [
                    ['Brand', product.brand],
                    ['Style', product.style],
                    ['Fulfillment', product.warehouse_name],
                    ['Colors', colors.length ? String(colors.length) : null],
                    ['Sizes', sizes.length ? sizes.join(', ') : null],
                    ['Variants', String(variants.length)],
                  ] as [string, string | null | undefined][]
                )
                  .filter(([, v]) => v)
                  .map(([k, v]) => (
                    <div
                      key={k}
                      className='flex justify-between gap-3 border-b border-border/60 py-1.5 text-[13.5px]'
                    >
                      <dt className='shrink-0 text-muted-foreground'>{k}</dt>
                      <dd className='min-w-0 flex-1 truncate text-right font-medium' title={v ?? undefined}>
                        {v}
                      </dd>
                    </div>
                  ))}
              </dl>
            </div>

            <ul className='grid grid-cols-1 gap-2 text-[13.5px] text-muted-foreground sm:grid-cols-2'>
              {['Made on demand', 'No order minimums', 'Fast production & shipping', 'Worldwide fulfillment'].map(
                (h) => (
                  <li key={h} className='flex items-center gap-2'>
                    <Check className='size-4 shrink-0 text-primary' strokeWidth={2.5} />
                    {h}
                  </li>
                )
              )}
            </ul>
          </div>
        </div>
      </div>

      <TierPricing variant={selected} />
      <VariantsTable variants={variants} />
    </div>
  )
}

function TierPricing({ variant }: { variant: Variant | null }) {
  const tp = variant?.tier_pricing
  if (!variant || !tp || Object.keys(tp).length === 0) {
    return (
      <div className='rounded-md border bg-card p-5'>
        <h3 className='mb-1 text-sm font-bold'>Tier pricing</h3>
        <p className='text-[13px] text-muted-foreground'>
          {variant ? 'No tier pricing for this variant.' : 'Select a variant to see tier pricing.'}
        </p>
      </div>
    )
  }

  const tiers = Object.keys(tp).sort((a, b) => {
    const ia = TIER_ORDER.indexOf(a)
    const ib = TIER_ORDER.indexOf(b)
    return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib)
  })
  const types = Array.from(new Set(tiers.flatMap((t) => Object.keys(tp[t] ?? {}))))

  return (
    <div className='rounded-md border bg-card p-5'>
      <div className='mb-3'>
        <div className='flex items-center gap-2'>
          <h3 className='text-sm font-bold'>Tier pricing</h3>
          <span className='text-[12px] text-muted-foreground'>· {variant.sku ?? variant.variant_id}</span>
        </div>
        <p className='mt-0.5 text-[12px] text-muted-foreground'>
          Base cost &amp; shipping for each seller tier — for the selected variant.
        </p>
      </div>

      {/* Mobile: per-tier cards */}
      <div className='grid grid-cols-2 gap-2.5 sm:hidden'>
        {tiers.map((t) => (
          <div key={t} className='rounded-md border bg-background/50 p-3'>
            <div className='mb-2 text-[11px] font-semibold tracking-wider text-primary uppercase'>{t}</div>
            <dl className='space-y-1.5'>
              {types.map((ty) => {
                const val = tp[t]?.[ty]
                return (
                  <div key={ty} className='flex items-baseline justify-between gap-2'>
                    <dt className='text-[11px] leading-tight text-muted-foreground'>{priceLabel(ty)}</dt>
                    <dd className='shrink-0 text-[12px] font-semibold tabular-nums'>
                      {val !== undefined && val !== null ? money(val) : '—'}
                    </dd>
                  </div>
                )
              })}
            </dl>
          </div>
        ))}
      </div>

      {/* Desktop table */}
      <div className='hidden overflow-x-auto sm:block'>
        <table className='w-full min-w-[420px] text-[13px]'>
          <thead>
            <tr className='border-b text-left text-[11px] font-semibold tracking-wider text-muted-foreground uppercase'>
              <th className='py-2 pr-3'>Price type</th>
              {tiers.map((t) => (
                <th key={t} className='py-2 pr-3 text-right'>{t}</th>
              ))}
            </tr>
          </thead>
          <tbody className='divide-y'>
            {types.map((ty) => (
              <tr key={ty}>
                <td className='py-2 pr-3 text-muted-foreground'>{priceLabel(ty)}</td>
                {tiers.map((t) => {
                  const val = tp[t]?.[ty]
                  return (
                    <td key={t} className='py-2 pr-3 text-right font-semibold tabular-nums'>
                      {val !== undefined && val !== null ? money(val) : '—'}
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

function VariantsTable({ variants }: { variants: Variant[] }) {
  return (
    <div className='overflow-hidden rounded-md border'>
      <div className='border-b bg-muted px-4 py-2.5'>
        <h2 className='text-sm font-bold'>
          Variants <span className='font-normal text-muted-foreground'>· {variants.length}</span>
        </h2>
        <p className='mt-0.5 text-[12px] text-muted-foreground'>
          Every color / size option with its specs (weight, dimensions, measurements).
        </p>
      </div>
      <div className='overflow-x-auto'>
        <table className='w-full min-w-[640px] text-[13px]'>
          <thead>
            <tr className='border-b text-left text-[11px] font-semibold tracking-wider text-muted-foreground uppercase'>
              <th className='px-4 py-2.5'>Variant ID</th>
              <th className='px-4 py-2.5'>Color</th>
              <th className='px-4 py-2.5'>Size</th>
              <th className='px-4 py-2.5'>Weight</th>
              <th className='px-4 py-2.5'>Dimensions</th>
              <th className='px-4 py-2.5'>Chest (in/cm)</th>
              <th className='px-4 py-2.5'>Length (in/cm)</th>
              <th className='px-4 py-2.5'>Neck (in/cm)</th>
            </tr>
          </thead>
          <tbody className='divide-y'>
            {variants.map((v) => (
              <tr key={v.id} className='hover:bg-muted/50'>
                <td className='px-4 py-2.5 font-mono text-[12px] font-medium'>{v.sku ?? v.variant_id}</td>
                <td className='px-4 py-2.5'>
                  {v.color ? (
                    <span className='inline-flex items-center gap-1.5'>
                      <span
                        className='size-3 rounded-full ring-1 ring-inset ring-black/10'
                        style={{ background: swatch(v.color) }}
                      />
                      {v.color}
                    </span>
                  ) : (
                    '—'
                  )}
                </td>
                <td className='px-4 py-2.5'>{v.size || '—'}</td>
                <td className='px-4 py-2.5 tabular-nums text-muted-foreground'>
                  {v.weight ? `${v.weight} g` : '—'}
                </td>
                <td className='px-4 py-2.5 tabular-nums text-muted-foreground'>
                  {v.length ? `${v.length}×${v.width}×${v.height}` : '—'}
                </td>
                <td className='px-4 py-2.5 tabular-nums text-muted-foreground'>{fmtMeasure(v.chest_inch, v.chest_cm)}</td>
                <td className='px-4 py-2.5 tabular-nums text-muted-foreground'>{fmtMeasure(v.length_inch, v.length_cm)}</td>
                <td className='px-4 py-2.5 tabular-nums text-muted-foreground'>{fmtMeasure(v.neck_inch, v.neck_cm)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
