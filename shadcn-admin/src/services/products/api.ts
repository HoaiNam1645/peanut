import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { apiRequest } from '@/lib/client'

export type ProductVariantSummary = {
  id: number
  product_id: number
  variant_id: string
  sku?: string | null
  style?: string | null
  color?: string | null
  size?: string | null
  stock?: number | null
  active?: boolean | null
  supplier_price?: number | null
  weight?: number | null
  length?: number | null
  width?: number | null
  height?: number | null
  tier_pricing?: Record<string, Record<string, number | string | null>> | null
}

export type ProductDetailSummary = {
  total_variants: number
  active_variants: number
  total_stock: number
  colors: string[]
  sizes: string[]
  price_range: {
    min?: number | null
    max?: number | null
  }
}

export type ProductDetailResult = {
  product: ProductWithVariants & {
    category_type?: 'embroidery' | 'print' | string | null
    updated_at?: string | null
  }
  summary: ProductDetailSummary | null
  variants: ProductVariantSummary[]
}

export type ProductWithVariants = {
  id: number
  name: string
  style?: string | null
  brand?: string | null
  mockup?: string | null
  template_url?: string | null
  warehouse_name?: string | null
  status?: boolean | null
  created_at?: string | null
  colors: string[]
  sizes: string[]
  total_stock: number
  active_variants: number
  total_variants: number
  price_range?: {
    min?: number | null
    max?: number | null
  } | null
  variants: ProductVariantSummary[]
}

export type ProductVariantsListResult = {
  products: ProductWithVariants[]
  pagination: {
    currentPage: number
    lastPage: number
    perPage: number
    total: number
  }
}

type CsvPreviewProduct = {
  name?: string
  style?: string
  brand?: string
  is_new?: boolean
  variants_count?: number
}

export type ProductImportPreview = {
  total_products?: number
  total_variants?: number
  new_products?: number
  existing_products?: number
  products?: CsvPreviewProduct[]
  preview?: Record<string, unknown>[]
}

export type ProductImportResult = {
  imported?: number
  failed?: number
  errors?: Array<string | Record<string, unknown>>
}

export type ProductVariantsTab = 'embroidery' | 'print' | 'wood'

export type ProductVariantsFilters = {
  search: string
  style: string
  brand: string
  status: string
  sort_by: string
  sort_order: 'asc' | 'desc'
}

export type ProductFilterOptions = {
  brands: string[]
  styles: string[]
  colors: string[]
  sizes: string[]
}

export type ProductTier = {
  id: number
  name: string
}

export type ProductMetadata = {
  tiers: ProductTier[]
  price_types: string[]
}

export type ProductPricePayload = {
  id?: number
  tier_id: number
  type: string
  price: number
}

export type CreateProductVariantPayload = {
  variant_id: string
  sku?: string
  style?: string
  color?: string | null
  size?: string
  stock?: number
  active?: boolean
  weight?: number | null
  length?: number | null
  width?: number | null
  height?: number | null
  supplier_price?: number | null
  prices?: ProductPricePayload[]
}

export type CreateProductPayload = {
  name: string
  style?: string
  status: boolean
  category_type: 'embroidery' | 'print' | 'wood'
  mockup?: string
  template_url?: string
  brand?: string
  warehouse_name?: string
  variants: CreateProductVariantPayload[]
}

type ProductsWithVariantsResponse = {
  code?: number
  status?: boolean
  message?: string
  data?: {
    data?: ProductWithVariants[]
    current_page?: number
    last_page?: number
    per_page?: number
    total?: number
  }
}

type ProductFilterOptionsResponse = {
  code?: number
  status?: boolean
  data?: ProductFilterOptions
}

type ProductMetadataResponse = {
  code?: number
  status?: boolean
  data?: ProductMetadata
}

type CreateProductResponse = {
  code?: number
  status?: boolean
  message?: string
  data?: {
    id?: number
    name?: string
  }
}

type ProductDetailResponse = {
  code?: number
  status?: boolean
  message?: string
  data?: ProductDetailResult
}

type UpdateProductResponse = {
  code?: number
  status?: boolean
  message?: string
  data?: ProductDetailResult['product']
}

type UpdateProductVariantResponse = {
  code?: number
  status?: boolean
  message?: string
  data?: ProductVariantSummary
}

type DeleteProductResponse = {
  code?: number
  status?: boolean
  message?: string
  data?: unknown
}

type UpdateStockPayload = {
  type: 'add_stock' | 'sub_stock'
  name: number
  color: string
  size: string
  stock: number
}

function buildQueryString(
  params: Record<string, string | number | boolean | undefined>
) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === '' || value === undefined || value === null) return
    query.set(key, String(value))
  })

  return query.toString()
}

export async function fetchProductsWithVariants(
  params: {
    page: number
    per_page: number
    category: ProductVariantsTab
  } & ProductVariantsFilters
): Promise<ProductVariantsListResult> {
  const query = buildQueryString(params)
  const data = await apiRequest<ProductsWithVariantsResponse>(
    `${API_BASE_URL}${API_ENDPOINTS.PRODUCTS_WITH_VARIANTS}?${query}`,
    {
      method: 'GET',
    }
  )

  const payload = data.data

  return {
    products: payload?.data || [],
    pagination: {
      currentPage: payload?.current_page || params.page,
      lastPage: payload?.last_page || 1,
      perPage: payload?.per_page || params.per_page,
      total: payload?.total || 0,
    },
  }
}

export async function updateProductStock(payload: UpdateStockPayload) {
  return apiRequest<{
    code?: number
    status?: boolean
    message?: string
    data?: {
      variant_id?: string
      product_id?: number
      color?: string
      size?: string
      old_stock?: number
      change_amount?: number
      new_stock?: number
      action?: string
    }
  }>(`${API_BASE_URL}${API_ENDPOINTS.PRODUCT_UPDATE_STOCK}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify(payload),
  })
}

export async function fetchProductFilterOptions(): Promise<ProductFilterOptions> {
  const data = await apiRequest<ProductFilterOptionsResponse>(
    `${API_BASE_URL}${API_ENDPOINTS.PRODUCT_FILTER_OPTIONS}`,
    {
      method: 'GET',
    }
  )

  return data.data || { brands: [], styles: [], colors: [], sizes: [] }
}

export async function fetchProductMetadata(): Promise<ProductMetadata> {
  const data = await apiRequest<ProductMetadataResponse>(
    `${API_BASE_URL}${API_ENDPOINTS.PRODUCT_METADATA}`,
    {
      method: 'GET',
    }
  )

  return data.data || { tiers: [], price_types: [] }
}

export async function createProduct(payload: CreateProductPayload) {
  return apiRequest<CreateProductResponse>(`${API_BASE_URL}${API_ENDPOINTS.PRODUCTS}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify(payload),
  })
}

export async function fetchProductDetail(productId: string | number) {
  const data = await apiRequest<ProductDetailResponse>(
    `${API_BASE_URL}${API_ENDPOINTS.PRODUCTS}/${productId}`,
    {
      method: 'GET',
    }
  )

  return (
    data.data || {
      product: {
        id: Number(productId),
        name: '',
        colors: [],
        sizes: [],
        total_stock: 0,
        active_variants: 0,
        total_variants: 0,
        variants: [],
      },
      summary: null,
      variants: [],
    }
  )
}

export async function updateProduct(
  productId: string | number,
  payload: Omit<CreateProductPayload, 'variants'> & {
    status: boolean
  }
) {
  return apiRequest<UpdateProductResponse>(
    `${API_BASE_URL}${API_ENDPOINTS.PRODUCTS}/${productId}`,
    {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
    }
  )
}

export async function deleteProduct(productId: string | number) {
  return apiRequest<DeleteProductResponse>(
    `${API_BASE_URL}${API_ENDPOINTS.PRODUCTS}/${productId}`,
    {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
      },
    }
  )
}

export async function updateProductVariant(
  variantId: string | number,
  payload: Partial<CreateProductVariantPayload>
) {
  return apiRequest<UpdateProductVariantResponse>(
    `${API_BASE_URL}${API_ENDPOINTS.PRODUCT_VARIANTS}/${variantId}`,
    {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
    }
  )
}

export async function deleteProductVariant(variantId: string | number) {
  return apiRequest<DeleteProductResponse>(
    `${API_BASE_URL}${API_ENDPOINTS.PRODUCT_VARIANTS}/${variantId}`,
    {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
      },
    }
  )
}

export async function updateProductVariantPricing(
  variantId: string | number,
  prices: ProductPricePayload[]
) {
  return apiRequest<{
    code?: number
    status?: boolean
    message?: string
    data?: unknown
  }>(`${API_BASE_URL}${API_ENDPOINTS.PRODUCT_VARIANTS}/${variantId}/pricing`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({ prices }),
  })
}

function readStoredToken() {
  if (typeof window === 'undefined') return ''
  return window.localStorage.getItem('lemiex_access_token') || ''
}

async function fetchBlobWithAuth(endpoint: string) {
  const token = readStoredToken()
  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    method: 'GET',
    credentials: 'include',
    headers: {
      Accept: 'text/csv,application/octet-stream,*/*',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  })

  if (!response.ok) {
    throw new Error('Không thể tải file')
  }

  return response.blob()
}

export async function downloadProductImportTemplate() {
  return fetchBlobWithAuth('/products/import/template')
}

export async function downloadCurrentProductImportData() {
  return fetchBlobWithAuth('/products/import/export')
}

export async function previewProductImport(file: File) {
  const formData = new FormData()
  formData.append('file', file)

  const token = readStoredToken()
  const response = await fetch(`${API_BASE_URL}/products/import/preview`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  })

  const data = (await response.json()) as {
    code?: number
    status?: boolean
    message?: string
    errors?: string[] | Record<string, string[]>
    total_rows?: number
    data?: ProductImportPreview
  }

  if (!response.ok) {
    throw data
  }

  return data
}

export async function importProductsFromCsv(file: File) {
  const formData = new FormData()
  formData.append('file', file)

  const token = readStoredToken()
  const response = await fetch(`${API_BASE_URL}/products/import`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: formData,
  })

  const data = (await response.json()) as {
    code?: number
    status?: boolean
    message?: string
    errors?: string[] | Record<string, string[]>
    total_rows?: number
    data?: ProductImportResult
  }

  if (!response.ok) {
    throw data
  }

  return data
}
