'use client'

import { useEffect, useMemo, useState } from 'react'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import { FileUp, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Input } from '@/components/ui/input'
import { LemiexDataTable } from '@/features/lemiex/components/lemiex-data-table'
import { useI18n } from '@/context/i18n-provider'
import { getUserRoleName } from '@/services/auth/api'
import {
  fetchProductsWithVariants,
  type ProductVariantsFilters,
  type ProductVariantsListResult,
  type ProductWithVariants,
} from '@/services/products/api'
import { useAuthStore } from '@/stores/auth-store'
import { ProductVariantsStockDialog } from '@/features/lemiex/product-variants/components/product-variants-stock-dialog'
import { getProductVariantsColumns } from '@/features/lemiex/product-variants/components/product-variants-table-columns'
import { ProductImportDialog } from '@/features/lemiex/product-variants/components/product-import-dialog'

type ProductVariantsPageState = {
  page: number
  perPage: number
  filters: ProductVariantsFilters
}

const DEFAULT_STATE: ProductVariantsPageState = {
  page: 1,
  perPage: 50,
  filters: {
    search: '',
    style: '',
    brand: '',
    status: '',
    sort_by: 'created_at',
    sort_order: 'desc',
  },
}

function parseSearchParams(searchParams: URLSearchParams): ProductVariantsPageState {
  return {
    page: Number(searchParams.get('page') || 1),
    perPage: Number(searchParams.get('per_page') || 50),
    filters: {
      search: searchParams.get('search') || '',
      style: searchParams.get('style') || '',
      brand: searchParams.get('brand') || '',
      status: searchParams.get('status') || '',
      sort_by: searchParams.get('sort_by') || 'created_at',
      sort_order:
        (searchParams.get('sort_order') as 'asc' | 'desc' | null) || 'desc',
    },
  }
}

function buildSearchParams(state: ProductVariantsPageState) {
  const params = new URLSearchParams()

  if (state.page > 1) params.set('page', String(state.page))
  if (state.perPage !== 50) params.set('per_page', String(state.perPage))

  Object.entries(state.filters).forEach(([key, value]) => {
    if (!value) return
    params.set(key, value)
  })

  return params
}

function hasActiveFilters(filters: ProductVariantsFilters) {
  return Object.entries(filters).some(([key, value]) => {
    if (key === 'sort_by' || key === 'sort_order') return false
    return value !== ''
  })
}

export function LemiexProductVariants() {
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const currentUser = useAuthStore((state) => state.auth.user)
  const { messages } = useI18n()
  const productMessages = messages.productVariants

  const userRole = getUserRoleName(currentUser)
  const isSeller = userRole === 'Seller'

  const queryKey = searchParams.toString()
  const state = useMemo(
    () => parseSearchParams(new URLSearchParams(queryKey)),
    [queryKey]
  )

  const [result, setResult] = useState<ProductVariantsListResult>({
    products: [],
    pagination: {
      currentPage: state.page,
      lastPage: 1,
      perPage: state.perPage,
      total: 0,
    },
  })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [stockDialogProduct, setStockDialogProduct] =
    useState<ProductWithVariants | null>(null)
  const [importDialogOpen, setImportDialogOpen] = useState(false)

  function updateUrl(nextState: ProductVariantsPageState) {
    const params = buildSearchParams(nextState)
    const next = params.toString()
    router.replace(next ? `${pathname}?${next}` : pathname, { scroll: false })
  }

  function updateFilters(
    updater: (filters: ProductVariantsFilters) => ProductVariantsFilters
  ) {
    updateUrl({
      ...state,
      page: 1,
      filters: updater(state.filters),
    })
  }

  useEffect(() => {
    let active = true

    async function loadProducts() {
      setLoading(true)
      setError(null)

      try {
        const next = await fetchProductsWithVariants({
          page: state.page,
          per_page: state.perPage,
          category: 'wood',
          ...state.filters,
        })

        if (!active) return
        setResult(next)
      } catch (fetchError) {
        if (!active) return
        setError(
          fetchError instanceof Error
            ? fetchError.message
            : productMessages.loadError
        )
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void loadProducts()

    return () => {
      active = false
    }
  }, [state, productMessages.loadError])

  const columns = useMemo(
    () =>
      getProductVariantsColumns({
        isSeller,
        messages: productMessages,
        onView: (product) => router.push(`/lemiex/product-variants/${product.id}`),
        onStock: (product) => setStockDialogProduct(product),
        onDelete: (product) =>
          toast.info(productMessages.actions.deletePending.replace('{name}', product.name)),
      }),
    [isSeller, productMessages, router]
  )

  const totalProducts = result.pagination.total

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

      <Main
        fluid
        className='flex flex-1 flex-col gap-4 px-4 py-6 sm:px-5 lg:px-6 xl:px-7'
      >
        <div className='space-y-6'>
          <div className='flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between'>
            <div className='space-y-1'>
              <h1 className='text-3xl font-semibold tracking-tight'>
                {productMessages.title}
              </h1>
              <p className='text-muted-foreground'>
                {totalProducts} {productMessages.count}
              </p>
            </div>

            {!isSeller ? (
              <div className='flex flex-nowrap items-center gap-2 overflow-x-auto'>
                <Button
                  variant='outline'
                  className='rounded-[6px]'
                  onClick={() => setImportDialogOpen(true)}
                >
                  <FileUp className='size-4' />
                  {productMessages.actions.importCsv}
                </Button>
                <Button
                  className='rounded-[6px]'
                  onClick={() => router.push('/lemiex/product-variants/create')}
                >
                  <Plus className='size-4' />
                  {productMessages.actions.createProduct}
                </Button>
              </div>
            ) : null}
          </div>

          <div className='flex flex-col gap-4'>
            <div className='rounded-[6px] border bg-card p-4'>
              <div className='grid gap-4 md:grid-cols-2 xl:grid-cols-[1.3fr_1.3fr_1.3fr_0.9fr_0.9fr]'>
                <div className='space-y-2'>
                  <label className='text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
                    {productMessages.filters.search}
                  </label>
                  <Input
                    value={state.filters.search}
                    onChange={(event) =>
                      updateFilters((filters) => ({
                        ...filters,
                        search: event.target.value,
                      }))
                    }
                    className='h-10 rounded-[6px]'
                    placeholder={productMessages.filters.searchPlaceholder}
                  />
                </div>

                <div className='space-y-2'>
                  <label className='text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
                    {productMessages.filters.style}
                  </label>
                  <Input
                    value={state.filters.style}
                    onChange={(event) =>
                      updateFilters((filters) => ({
                        ...filters,
                        style: event.target.value,
                      }))
                    }
                    className='h-10 rounded-[6px]'
                    placeholder={productMessages.filters.stylePlaceholder}
                  />
                </div>

                <div className='space-y-2'>
                  <label className='text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
                    {productMessages.filters.brand}
                  </label>
                  <Input
                    value={state.filters.brand}
                    onChange={(event) =>
                      updateFilters((filters) => ({
                        ...filters,
                        brand: event.target.value,
                      }))
                    }
                    className='h-10 rounded-[6px]'
                    placeholder={productMessages.filters.brandPlaceholder}
                  />
                </div>

                <div className='space-y-2'>
                  <label className='text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
                    {productMessages.filters.status}
                  </label>
                  <Select
                    value={state.filters.status || '__all__'}
                    onValueChange={(value) =>
                      updateFilters((filters) => ({
                        ...filters,
                        status: value === '__all__' ? '' : value,
                      }))
                    }
                  >
                    <SelectTrigger className='h-10 rounded-[6px]'>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value='__all__'>
                        {productMessages.filters.allStatus}
                      </SelectItem>
                      <SelectItem value='1'>
                        {productMessages.status.activeLabel}
                      </SelectItem>
                      <SelectItem value='0'>
                        {productMessages.status.inactiveLabel}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className='space-y-2'>
                  <label className='text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
                    {productMessages.filters.sortBy}
                  </label>
                  <Select
                    value={`${state.filters.sort_by}-${state.filters.sort_order}`}
                    onValueChange={(value) => {
                      const [sort_by, sort_order] = value.split('-')
                      updateFilters((filters) => ({
                        ...filters,
                        sort_by,
                        sort_order: sort_order as 'asc' | 'desc',
                      }))
                    }}
                  >
                    <SelectTrigger className='h-10 rounded-[6px]'>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value='created_at-desc'>
                        {productMessages.filters.newestFirst}
                      </SelectItem>
                      <SelectItem value='created_at-asc'>
                        {productMessages.filters.oldestFirst}
                      </SelectItem>
                      <SelectItem value='name-asc'>
                        {productMessages.filters.nameAz}
                      </SelectItem>
                      <SelectItem value='name-desc'>
                        {productMessages.filters.nameZa}
                      </SelectItem>
                      <SelectItem value='brand-asc'>
                        {productMessages.filters.brandAz}
                      </SelectItem>
                      <SelectItem value='brand-desc'>
                        {productMessages.filters.brandZa}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              {hasActiveFilters(state.filters) ? (
                <div className='mt-3 flex justify-end'>
                  <Button
                    variant='outline'
                    className='h-9 rounded-[6px]'
                    onClick={() => updateUrl(DEFAULT_STATE)}
                  >
                    {productMessages.filters.clearFilters}
                  </Button>
                </div>
              ) : null}
            </div>
          </div>

          <LemiexDataTable
            columns={columns}
            data={result.products}
            page={result.pagination.currentPage}
            pageSize={result.pagination.perPage}
          total={result.pagination.total}
          loading={loading}
          loadingText={productMessages.loading}
          emptyText={error || productMessages.empty}
            onPageChange={(page) => updateUrl({ ...state, page })}
            onPageSizeChange={(pageSize) =>
              updateUrl({
                ...state,
                page: 1,
                perPage: pageSize,
              })
            }
            pageSizeOptions={[50, 100, 150, 200]}
          />
        </div>
      </Main>

      <ProductVariantsStockDialog
        open={Boolean(stockDialogProduct)}
        product={stockDialogProduct}
        onOpenChange={(open) => {
          if (!open) {
            setStockDialogProduct(null)
          }
        }}
        onUpdated={async () => {
          const refreshed = await fetchProductsWithVariants({
            page: state.page,
            per_page: state.perPage,
            category: 'wood',
            ...state.filters,
          })
          setResult(refreshed)
        }}
      />

      <ProductImportDialog
        open={importDialogOpen}
        onOpenChange={setImportDialogOpen}
        onSuccess={async () => {
          const refreshed = await fetchProductsWithVariants({
            page: state.page,
            per_page: state.perPage,
            category: 'wood',
            ...state.filters,
          })
          setResult(refreshed)
        }}
      />
    </>
  )
}
