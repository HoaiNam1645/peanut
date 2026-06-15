const DEFAULT_ACCEPT_HEADER = 'application/json'

export const DEFAULT_GET_CACHE_TTL_MS = 10_000

type CacheEntry = {
  expiresAt: number
  data: unknown
}

type CacheMatcher = string | RegExp | ((key: string) => boolean)

export type ApiRequestOptions = RequestInit & {
  cacheTtlMs?: number
  useCache?: boolean
  dedupe?: boolean
}

const inflightRequests = new Map<string, Promise<unknown>>()
const responseCache = new Map<string, CacheEntry>()

function readStoredToken() {
  if (typeof window === 'undefined') return ''
  return window.localStorage.getItem('lemiex_access_token') || ''
}

function cloneData<T>(value: T): T {
  if (value === null || value === undefined) return value

  try {
    if (typeof structuredClone === 'function') {
      return structuredClone(value)
    }
  } catch {
    // Fall through to JSON clone.
  }

  return JSON.parse(JSON.stringify(value)) as T
}

function normalizeHeaders(headers?: HeadersInit) {
  const normalized = new Headers(headers)

  if (!normalized.has('Accept')) {
    normalized.set('Accept', DEFAULT_ACCEPT_HEADER)
  }

  const token = readStoredToken()
  if (token && !normalized.has('Authorization')) {
    normalized.set('Authorization', `Bearer ${token}`)
  }

  return normalized
}

function normalizeBody(body?: BodyInit | null) {
  if (!body) return ''

  if (typeof body === 'string') return body
  if (body instanceof URLSearchParams) return body.toString()
  if (typeof FormData !== 'undefined' && body instanceof FormData) {
    return JSON.stringify(
      Array.from(body.entries()).map(([key, value]) => [key, String(value)])
    )
  }

  if (typeof Blob !== 'undefined' && body instanceof Blob) {
    return JSON.stringify({
      type: body.type,
      size: body.size,
    })
  }

  if (typeof ArrayBuffer !== 'undefined' && body instanceof ArrayBuffer) {
    return `array-buffer:${body.byteLength}`
  }

  if (ArrayBuffer.isView(body)) {
    return `array-buffer-view:${body.byteLength}`
  }

  return String(body)
}

function createRequestKey(input: string, init: RequestInit, headers: Headers) {
  const method = (init.method || 'GET').toUpperCase()
  const authHeader = headers.get('Authorization') || ''
  const body = normalizeBody(init.body)

  return JSON.stringify({
    method,
    input,
    authHeader,
    body,
  })
}

function isMutationMethod(method: string) {
  return ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)
}

async function parseResponseBody(response: Response) {
  if (response.status === 204) return null

  const contentType = response.headers.get('content-type') || ''

  if (contentType.includes('application/json')) {
    return response.json()
  }

  const text = await response.text()
  return text || null
}

function matchesCacheKey(key: string, matcher: CacheMatcher) {
  if (typeof matcher === 'string') return key.includes(matcher)
  if (matcher instanceof RegExp) return matcher.test(key)
  return matcher(key)
}

function cleanupExpiredCacheEntries() {
  const now = Date.now()

  responseCache.forEach((entry, key) => {
    if (entry.expiresAt <= now) {
      responseCache.delete(key)
    }
  })
}

export function invalidateApiCache(matcher?: CacheMatcher) {
  if (!matcher) {
    responseCache.clear()
    return
  }

  responseCache.forEach((_, key) => {
    if (matchesCacheKey(key, matcher)) {
      responseCache.delete(key)
    }
  })
}

export async function apiRequest<T>(
  input: string,
  options?: ApiRequestOptions
): Promise<T> {
  cleanupExpiredCacheEntries()

  const init = options || {}
  const method = (init.method || 'GET').toUpperCase()
  const headers = normalizeHeaders(init.headers)
  const isGet = method === 'GET'
  const useCache = init.useCache ?? isGet
  const dedupe = init.dedupe ?? isGet
  const cacheTtlMs = init.cacheTtlMs ?? DEFAULT_GET_CACHE_TTL_MS
  const requestKey = createRequestKey(input, { ...init, method }, headers)

  if (isGet && useCache) {
    const cached = responseCache.get(requestKey)
    if (cached && cached.expiresAt > Date.now()) {
      return cloneData(cached.data as T)
    }
  }

  if (isGet && dedupe) {
    const inflight = inflightRequests.get(requestKey)
    if (inflight) {
      return inflight.then((data) => cloneData(data as T))
    }
  }

  const requestPromise = (async () => {
    const response = await fetch(input, {
      credentials: 'include',
      ...init,
      method,
      headers,
    })

    const data = (await parseResponseBody(response)) as T & {
      message?: string
    }

    if (!response.ok) {
      throw new Error(
        (data as { message?: string } | null)?.message ||
          'Không thể tải dữ liệu'
      )
    }

    if (isGet && useCache) {
      responseCache.set(requestKey, {
        data: cloneData(data),
        expiresAt: Date.now() + cacheTtlMs,
      })
    }

    if (isMutationMethod(method)) {
      invalidateApiCache()
    }

    return data as T
  })()

  if (isGet && dedupe) {
    inflightRequests.set(requestKey, requestPromise)
  }

  try {
    const data = await requestPromise
    return cloneData(data)
  } finally {
    if (isGet && dedupe) {
      inflightRequests.delete(requestKey)
    }
  }
}
