'use client'

import { useEffect, useMemo, useState } from 'react'
import { useRouter } from 'next/navigation'
import { ArrowLeft, Loader2, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
  createProduct,
  fetchProductFilterOptions,
  fetchProductMetadata,
  type CreateProductPayload,
  type CreateProductVariantPayload,
  type ProductFilterOptions,
  type ProductMetadata,
  type ProductPricePayload,
} from '@/services/products/api'

type LocalPrice = Omit<ProductPricePayload, 'id'> & { id: number }
type LocalVariant = Omit<CreateProductVariantPayload, 'prices'> & {
  id: number
  prices: LocalPrice[]
}

function card() {
  return 'rounded-[6px] border-border/80 shadow-none'
}

function field() {
  return 'h-9 w-full rounded-[6px] text-[13px]'
}

function label() {
  return 'text-[12px] font-medium text-foreground'
}

function fieldWrap() {
  return 'space-y-1.5'
}

function nextId() {
  return Date.now() + Math.floor(Math.random() * 1000)
}

function defaultPrice(): LocalPrice {
  return {
    id: nextId(),
    tier_id: 0,
    type: 'base_cost',
    price: 0,
  }
}

function defaultVariant(style?: string): LocalVariant {
  return {
    id: nextId(),
    variant_id: '',
    sku: '',
    style: style || '',
    color: null,
    size: '',
    stock: 0,
    active: true,
    weight: null,
    length: null,
    width: null,
    height: null,
    supplier_price: null,
    chest_inch: null,
    chest_cm: null,
    length_inch: null,
    length_cm: null,
    neck_inch: null,
    neck_cm: null,
    prices: [],
  }
}

export function LemiexCreateProduct() {
  const router = useRouter()
  const { messages } = useI18n()
  const productMessages = messages.productVariants
  const formMessages = productMessages.createForm

  const [loading, setLoading] = useState(false)
  const [booting, setBooting] = useState(true)
  const [metadata, setMetadata] = useState<ProductMetadata>({
    tiers: [],
    price_types: [],
  })
  const [filterOptions, setFilterOptions] = useState<ProductFilterOptions>({
    brands: [],
    styles: [],
    colors: [],
    sizes: [],
  })
  const [productData, setProductData] = useState<CreateProductPayload>({
    name: '',
    style: '',
    status: true,
    category_type: 'wood',
    mockup: '',
    template_url: '',
    brand: '',
    warehouse_name: '',
    variants: [],
  })
  const [variants, setVariants] = useState<LocalVariant[]>([])

  useEffect(() => {
    let active = true

    async function bootstrap() {
      setBooting(true)
      try {
        const [nextMetadata, nextFilterOptions] = await Promise.all([
          fetchProductMetadata(),
          fetchProductFilterOptions(),
        ])

        if (!active) return
        setMetadata(nextMetadata)
        setFilterOptions(nextFilterOptions)
      } catch (error) {
        if (!active) return
        toast.error(error instanceof Error ? error.message : formMessages.createFailed)
      } finally {
        if (active) setBooting(false)
      }
    }

    void bootstrap()
    return () => {
      active = false
    }
  }, [formMessages.createFailed])

  const hasVariants = variants.length > 0

  const firstTierId = useMemo(
    () => metadata.tiers[0]?.id || 0,
    [metadata.tiers]
  )
  const firstPriceType = useMemo(
    () => metadata.price_types[0] || 'base_cost',
    [metadata.price_types]
  )

  function updateProductField<K extends keyof Omit<CreateProductPayload, 'variants'>>(
    field: K,
    value: CreateProductPayload[K]
  ) {
    setProductData((prev) => ({ ...prev, [field]: value }))
    if (field === 'style') {
      setVariants((prev) =>
        prev.map((variant) => ({
          ...variant,
          style: String(value || ''),
        }))
      )
    }
  }

  function addVariant() {
    setVariants((prev) => [...prev, defaultVariant(productData.style)])
  }

  function removeVariant(id: number) {
    setVariants((prev) => prev.filter((variant) => variant.id !== id))
  }

  function updateVariant<K extends keyof LocalVariant>(
    id: number,
    field: K,
    value: LocalVariant[K]
  ) {
    setVariants((prev) =>
      prev.map((variant) =>
        variant.id === id ? { ...variant, [field]: value } : variant
      )
    )
  }

  function addPrice(variantId: number) {
    setVariants((prev) =>
      prev.map((variant) =>
        variant.id === variantId
          ? {
              ...variant,
              prices: [
                ...variant.prices,
                {
                  ...defaultPrice(),
                  tier_id: firstTierId,
                  type: firstPriceType,
                },
              ],
            }
          : variant
      )
    )
  }

  function removePrice(variantId: number, priceId: number) {
    setVariants((prev) =>
      prev.map((variant) =>
        variant.id === variantId
          ? {
              ...variant,
              prices: variant.prices.filter((price) => price.id !== priceId),
            }
          : variant
      )
    )
  }

  function updatePrice<K extends keyof LocalPrice>(
    variantId: number,
    priceId: number,
    field: K,
    value: LocalPrice[K]
  ) {
    setVariants((prev) =>
      prev.map((variant) =>
        variant.id === variantId
          ? {
              ...variant,
              prices: variant.prices.map((price) =>
                price.id === priceId ? { ...price, [field]: value } : price
              ),
            }
          : variant
      )
    )
  }

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (!productData.name.trim()) {
      toast.error(formMessages.productNameRequired)
      return
    }

    if (variants.some((variant) => !variant.variant_id.trim())) {
      toast.error(formMessages.variantIdRequired)
      return
    }

    const submitData: CreateProductPayload = {
      ...productData,
      mockup: productData.mockup?.trim() || undefined,
      template_url: productData.template_url?.trim() || undefined,
      brand: productData.brand?.trim() || undefined,
      warehouse_name: productData.warehouse_name?.trim() || undefined,
      style: productData.style?.trim() || undefined,
      variants: variants.map(({ id, prices, ...variant }) => ({
        ...variant,
        sku: variant.sku?.trim() || undefined,
        prices: prices.map(({ id: priceId, ...price }) => price),
      })),
    }

    setLoading(true)
    try {
      await createProduct(submitData)
      toast.success(formMessages.createSuccess)
      router.push('/lemiex/product-variants')
    } catch (error) {
      if (
        typeof error === 'object' &&
        error &&
        'errors' in error &&
        error.errors &&
        typeof error.errors === 'object'
      ) {
        const entries = Object.values(error.errors as Record<string, string[]>)
        const first = entries[0]
        if (Array.isArray(first) && first[0]) {
          toast.error(first[0])
          setLoading(false)
          return
        }
      }

      toast.error(error instanceof Error ? error.message : formMessages.createFailed)
    } finally {
      setLoading(false)
    }
  }

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

      <Main fluid className='px-4 py-5 sm:px-5 lg:px-6 xl:px-7'>
        <form className='w-full space-y-5' onSubmit={handleSubmit}>

          {/* Page header */}
          <div className='flex items-center justify-between gap-4 border-b pb-4'>
            <div className='flex items-center gap-3'>
              <Button
                type='button'
                variant='ghost'
                size='icon'
                className='size-8 shrink-0 rounded-[6px] text-muted-foreground'
                onClick={() => router.push('/lemiex/product-variants')}
              >
                <ArrowLeft className='size-4' />
              </Button>
              <div>
                <h1 className='text-xl font-semibold tracking-tight'>{formMessages.title}</h1>
                <p className='text-[12px] text-muted-foreground'>{formMessages.description}</p>
              </div>
            </div>
            <div className='flex items-center gap-2'>
              <Button
                type='button'
                variant='outline'
                className='rounded-[6px]'
                onClick={() => router.push('/lemiex/product-variants')}
              >
                {formMessages.cancel}
              </Button>
              <Button type='submit' className='rounded-[6px]' disabled={loading || booting}>
                {loading ? (
                  <>
                    <Loader2 className='size-4 animate-spin' />
                    {formMessages.creating}
                  </>
                ) : (
                  formMessages.create
                )}
              </Button>
            </div>
          </div>

          {/* Product Info */}
          <Card className={card()}>
            <CardHeader className='border-b border-border/60 px-5 py-3.5'>
              <CardTitle className='text-[14px] font-semibold'>{formMessages.productInfo}</CardTitle>
            </CardHeader>
            <CardContent className='grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2 lg:grid-cols-3'>
              {/* Row 1: Name (2/3) + Style (1/3) */}
              <div className={`${fieldWrap()} lg:col-span-2`}>
                <label className={label()}>{formMessages.productName}</label>
                <Input
                  className={field()}
                  value={productData.name}
                  onChange={(e) => updateProductField('name', e.target.value)}
                  placeholder={formMessages.productNamePlaceholder}
                />
              </div>
              <div className={fieldWrap()}>
                <label className={label()}>{formMessages.style}</label>
                <Input
                  className={field()}
                  list='product-style-options'
                  value={productData.style || ''}
                  onChange={(e) => updateProductField('style', e.target.value)}
                  placeholder={formMessages.stylePlaceholder}
                />
              </div>

              {/* Row 2: Supplier + Warehouse + Status */}
              <div className={fieldWrap()}>
                <label className={label()}>{formMessages.brand}</label>
                <Input
                  className={field()}
                  list='product-brand-options'
                  value={productData.brand || ''}
                  onChange={(e) => updateProductField('brand', e.target.value)}
                  placeholder={formMessages.brandPlaceholder}
                />
              </div>
              <div className={fieldWrap()}>
                <label className={label()}>{formMessages.warehouse}</label>
                <Input
                  className={field()}
                  value={productData.warehouse_name || ''}
                  onChange={(e) => updateProductField('warehouse_name', e.target.value)}
                  placeholder={formMessages.warehousePlaceholder}
                />
              </div>
              <div className={fieldWrap()}>
                <label className={label()}>{formMessages.status}</label>
                <Select
                  value={productData.status ? 'true' : 'false'}
                  onValueChange={(v) => updateProductField('status', v === 'true')}
                >
                  <SelectTrigger className={field()}>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='true'>{formMessages.active}</SelectItem>
                    <SelectItem value='false'>{formMessages.inactive}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              {/* Row 3: Mockup full width */}
              <div className={`${fieldWrap()} sm:col-span-2 lg:col-span-3`}>
                <label className={label()}>{formMessages.mockupUrl}</label>
                <Input
                  className={field()}
                  value={productData.mockup || ''}
                  onChange={(e) => updateProductField('mockup', e.target.value)}
                  placeholder='https://example.com/mockup.jpg'
                />
              </div>

              <div className={`${fieldWrap()} sm:col-span-2 lg:col-span-3`}>
                <label className={label()}>Template URL</label>
                <Input
                  className={field()}
                  value={productData.template_url || ''}
                  onChange={(e) => updateProductField('template_url', e.target.value)}
                  placeholder='https://example.com/template-link'
                />
              </div>

              <datalist id='product-style-options'>
                {filterOptions.styles.map((s) => <option key={s} value={s} />)}
              </datalist>
              <datalist id='product-brand-options'>
                {filterOptions.brands.map((b) => <option key={b} value={b} />)}
              </datalist>
            </CardContent>
          </Card>

          {/* Variants */}
          <Card className={card()}>
            <CardHeader className='flex flex-row items-center justify-between border-b border-border/60 px-5 py-3.5 space-y-0'>
              <CardTitle className='text-[14px] font-semibold'>{formMessages.variants}</CardTitle>
              <Button
                type='button'
                variant='outline'
                size='sm'
                className='rounded-[6px]'
                onClick={addVariant}
              >
                <Plus className='size-3.5' />
                {formMessages.addVariant}
              </Button>
            </CardHeader>
            <CardContent className='space-y-4 px-5 py-4'>
              {!hasVariants ? (
                <div className='rounded-[6px] border border-dashed px-5 py-8 text-center text-[13px] text-muted-foreground'>
                  {formMessages.noVariantsYet}
                </div>
              ) : null}

              {variants.map((variant, index) => (
                <div key={variant.id} className='rounded-[6px] border border-border/80'>
                  {/* Variant header */}
                  <div className='flex items-center justify-between border-b border-border/60 px-4 py-2.5'>
                    <span className='text-[13px] font-semibold text-foreground'>
                      {formMessages.variant} #{index + 1}
                    </span>
                    <Button
                      type='button'
                      variant='ghost'
                      size='icon'
                      className='size-7 rounded-[6px] text-muted-foreground hover:text-destructive'
                      onClick={() => removeVariant(variant.id)}
                    >
                      <Trash2 className='size-3.5' />
                    </Button>
                  </div>

                  <div className='space-y-4 p-4'>
                    {/* Row 1: IDs + Size + Stock */}
                    <div className='grid grid-cols-2 gap-3 sm:grid-cols-4'>
                      <div className={fieldWrap()}>
                        <label className={label()}>{formMessages.variantId}</label>
                        <Input
                          className={field()}
                          value={variant.variant_id}
                          onChange={(e) => updateVariant(variant.id, 'variant_id', e.target.value)}
                          placeholder={formMessages.variantIdPlaceholder}
                        />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>{formMessages.sku}</label>
                        <Input
                          className={field()}
                          value={variant.sku || ''}
                          onChange={(e) => updateVariant(variant.id, 'sku', e.target.value)}
                          placeholder={formMessages.skuPlaceholder}
                        />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>{formMessages.size}</label>
                        <Input
                          className={field()}
                          list={`variant-sizes-${variant.id}`}
                          value={variant.size || ''}
                          onChange={(e) => updateVariant(variant.id, 'size', e.target.value)}
                          placeholder={formMessages.sizePlaceholder}
                        />
                        <datalist id={`variant-sizes-${variant.id}`}>
                          {filterOptions.sizes.map((s) => <option key={s} value={s} />)}
                        </datalist>
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>{formMessages.stock}</label>
                        <Input
                          className={field()}
                          type='number'
                          min={0}
                          value={variant.stock ?? 0}
                          onChange={(e) => updateVariant(variant.id, 'stock', Number(e.target.value || 0))}
                        />
                      </div>
                    </div>

                    {/* Row 2: Price + Weight + Dimensions + Status */}
                    <div className='grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6'>
                      <div className={fieldWrap()}>
                        <label className={label()}>{formMessages.supplierPrice}</label>
                        <Input
                          className={field()}
                          type='number'
                          min={0}
                          step='0.01'
                          value={variant.supplier_price ?? ''}
                          onChange={(e) =>
                            updateVariant(variant.id, 'supplier_price', e.target.value === '' ? null : Number(e.target.value || 0))
                          }
                          placeholder='0.00'
                        />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>{formMessages.weight}</label>
                        <Input
                          className={field()}
                          type='number'
                          min={0}
                          value={variant.weight ?? ''}
                          onChange={(e) =>
                            updateVariant(variant.id, 'weight', e.target.value === '' ? null : Number(e.target.value || 0))
                          }
                          placeholder='g'
                        />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>Length (mm)</label>
                        <Input
                          className={field()}
                          type='number'
                          min={0}
                          value={variant.length ?? ''}
                          onChange={(e) =>
                            updateVariant(variant.id, 'length', e.target.value === '' ? null : Number(e.target.value || 0))
                          }
                          placeholder='0'
                        />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>Width (mm)</label>
                        <Input
                          className={field()}
                          type='number'
                          min={0}
                          value={variant.width ?? ''}
                          onChange={(e) =>
                            updateVariant(variant.id, 'width', e.target.value === '' ? null : Number(e.target.value || 0))
                          }
                          placeholder='0'
                        />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>Height (mm)</label>
                        <Input
                          className={field()}
                          type='number'
                          min={0}
                          value={variant.height ?? ''}
                          onChange={(e) =>
                            updateVariant(variant.id, 'height', e.target.value === '' ? null : Number(e.target.value || 0))
                          }
                          placeholder='0'
                        />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>{formMessages.status}</label>
                        <Select
                          value={variant.active ? 'true' : 'false'}
                          onValueChange={(v) => updateVariant(variant.id, 'active', v === 'true')}
                        >
                          <SelectTrigger className={field()}>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value='true'>{formMessages.active}</SelectItem>
                            <SelectItem value='false'>{formMessages.inactive}</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>

                    {/* Garment measurements (size chart) */}
                    <div className='grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6'>
                      <div className={fieldWrap()}>
                        <label className={label()}>Chest (inch)</label>
                        <Input className={field()} type='number' min={0} step='0.01' value={variant.chest_inch ?? ''}
                          onChange={(e) => updateVariant(variant.id, 'chest_inch', e.target.value === '' ? null : Number(e.target.value || 0))}
                          placeholder='0' />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>Chest (cm)</label>
                        <Input className={field()} type='number' min={0} step='0.01' value={variant.chest_cm ?? ''}
                          onChange={(e) => updateVariant(variant.id, 'chest_cm', e.target.value === '' ? null : Number(e.target.value || 0))}
                          placeholder='0' />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>Length (inch)</label>
                        <Input className={field()} type='number' min={0} step='0.01' value={variant.length_inch ?? ''}
                          onChange={(e) => updateVariant(variant.id, 'length_inch', e.target.value === '' ? null : Number(e.target.value || 0))}
                          placeholder='0' />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>Length (cm)</label>
                        <Input className={field()} type='number' min={0} step='0.01' value={variant.length_cm ?? ''}
                          onChange={(e) => updateVariant(variant.id, 'length_cm', e.target.value === '' ? null : Number(e.target.value || 0))}
                          placeholder='0' />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>Neck (inch)</label>
                        <Input className={field()} type='number' min={0} step='0.01' value={variant.neck_inch ?? ''}
                          onChange={(e) => updateVariant(variant.id, 'neck_inch', e.target.value === '' ? null : Number(e.target.value || 0))}
                          placeholder='0' />
                      </div>
                      <div className={fieldWrap()}>
                        <label className={label()}>Neck (cm)</label>
                        <Input className={field()} type='number' min={0} step='0.01' value={variant.neck_cm ?? ''}
                          onChange={(e) => updateVariant(variant.id, 'neck_cm', e.target.value === '' ? null : Number(e.target.value || 0))}
                          placeholder='0' />
                      </div>
                    </div>

                    {/* Pricing */}
                    <div className='space-y-2 rounded-[6px] border border-border/70 bg-muted/10 p-3'>
                      <div className='flex items-center justify-between'>
                        <span className='text-[12px] font-semibold text-foreground'>{formMessages.pricing}</span>
                        <Button
                          type='button'
                          variant='outline'
                          size='sm'
                          className='h-7 rounded-[6px] text-[12px]'
                          onClick={() => addPrice(variant.id)}
                        >
                          <Plus className='size-3' />
                          {formMessages.addPrice}
                        </Button>
                      </div>

                      {variant.prices.length === 0 ? (
                        <p className='py-2 text-center text-[12px] text-muted-foreground'>
                          {formMessages.noPricesAdded}
                        </p>
                      ) : null}

                      <div className='space-y-2'>
                        {variant.prices.map((price) => (
                          <div
                            key={price.id}
                            className='grid items-center gap-2 grid-cols-[1fr_1fr_100px_32px]'
                          >
                            <Select
                              value={String(price.tier_id)}
                              onValueChange={(v) => updatePrice(variant.id, price.id, 'tier_id', Number(v))}
                            >
                              <SelectTrigger className={field()}>
                                <SelectValue placeholder={formMessages.tier} />
                              </SelectTrigger>
                              <SelectContent>
                                {metadata.tiers.map((tier) => (
                                  <SelectItem key={tier.id} value={String(tier.id)}>
                                    {tier.name}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                            <Select
                              value={price.type}
                              onValueChange={(v) => updatePrice(variant.id, price.id, 'type', v)}
                            >
                              <SelectTrigger className={field()}>
                                <SelectValue placeholder={formMessages.priceType} />
                              </SelectTrigger>
                              <SelectContent>
                                {metadata.price_types.map((type) => (
                                  <SelectItem key={type} value={type}>
                                    {type.replaceAll('_', ' ')}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                            <Input
                              className={field()}
                              type='number'
                              min={0}
                              step='0.01'
                              value={price.price}
                              onChange={(e) => updatePrice(variant.id, price.id, 'price', Number(e.target.value || 0))}
                              placeholder='0.00'
                            />
                            <Button
                              type='button'
                              variant='ghost'
                              size='icon'
                              className='size-9 rounded-[6px] text-muted-foreground hover:text-destructive'
                              onClick={() => removePrice(variant.id, price.id)}
                            >
                              <Trash2 className='size-3.5' />
                            </Button>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

        </form>
      </Main>
    </>
  )
}
