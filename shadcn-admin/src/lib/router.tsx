'use client'

import * as React from 'react'
import LinkNext from 'next/link'
import { usePathname, useRouter, useSearchParams } from 'next/navigation'

type SearchRecord = Record<string, unknown>

type NavigateOptions = {
  to?: string
  search?:
    | true
    | SearchRecord
    | ((prev: SearchRecord) => SearchRecord | Partial<SearchRecord>)
  replace?: boolean
}

export type LinkProps = {
  to: string
  search?: SearchRecord
  disabled?: boolean
  className?: string
  children?: React.ReactNode
  onClick?: React.MouseEventHandler<HTMLElement>
} & Omit<React.ComponentProps<typeof LinkNext>, 'href'>

function parseScalar(value: string) {
  if (/^\d+$/.test(value)) return Number(value)
  if (value.includes(',')) return value.split(',').filter(Boolean)
  return value
}

function parseSearch(searchParams: URLSearchParams): SearchRecord {
  const result: SearchRecord = {}

  Array.from(new Set(searchParams.keys())).forEach((key) => {
    const values = searchParams.getAll(key)
    if (values.length > 1) {
      result[key] = values.map(parseScalar)
      return
    }

    const value = values[0]
    if (value !== undefined) result[key] = parseScalar(value)
  })

  return result
}

function buildHref(pathname: string, search?: SearchRecord) {
  if (!search) return pathname

  const params = new URLSearchParams()

  Object.entries(search).forEach(([key, value]) => {
    if (
      value === undefined ||
      value === null ||
      value === '' ||
      (Array.isArray(value) && value.length === 0)
    ) {
      return
    }

    if (Array.isArray(value)) {
      params.set(key, value.map(String).join(','))
      return
    }

    params.set(key, String(value))
  })

  const query = params.toString()
  return query ? `${pathname}?${query}` : pathname
}

export const Link = React.forwardRef<HTMLAnchorElement, LinkProps>(
  ({ to, search, disabled, onClick, children, ...props }, ref) => {
    const href = buildHref(to, search)

    if (disabled) {
      return (
        <span
          ref={ref as never}
          aria-disabled='true'
          onClick={onClick}
          {...(props as React.HTMLAttributes<HTMLSpanElement>)}
        >
          {children}
        </span>
      )
    }

    return (
      <LinkNext ref={ref} href={href} onClick={onClick as never} {...props}>
        {children}
      </LinkNext>
    )
  }
)

Link.displayName = 'Link'

export function useNavigate() {
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()

  return React.useCallback(
    (options: NavigateOptions) => {
      const currentSearch = parseSearch(new URLSearchParams(searchParams.toString()))

      let nextSearch = currentSearch
      if (options.search && options.search !== true) {
        nextSearch =
          typeof options.search === 'function'
            ? options.search(currentSearch)
            : options.search
      }

      const href = buildHref(options.to ?? pathname, nextSearch)

      if (options.replace) {
        router.replace(href)
        return
      }

      router.push(href)
    },
    [pathname, router, searchParams]
  )
}

export function useLocation<T = { pathname: string; href: string }>(opts?: {
  select?: (location: { pathname: string; href: string }) => T
}) {
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const href = searchParams.toString() ? `${pathname}?${searchParams.toString()}` : pathname
  const location = React.useMemo(() => ({ pathname, href }), [href, pathname])

  return opts?.select ? opts.select(location) : (location as T)
}

export function useSearch<T = SearchRecord>(_opts?: unknown): T {
  const searchParams = useSearchParams()
  return React.useMemo(
    () => parseSearch(new URLSearchParams(searchParams.toString())) as T,
    [searchParams]
  )
}

type RouterState = {
  status: 'idle' | 'pending'
  pathname: string
}

export function useRouterState(): RouterState {
  const pathname = usePathname()
  return React.useMemo(() => ({ status: 'idle' as const, pathname }), [pathname])
}

export function useRouterCompat() {
  const pathname = usePathname()
  const searchParams = useSearchParams()

  return {
    history: {
      go: (delta: number) => window.history.go(delta),
      location: {
        href: searchParams.toString() ? `${pathname}?${searchParams.toString()}` : pathname,
      },
    },
  }
}

export { useRouterCompat as useRouter }

export function getRouteApi(_path: string) {
  return {
    useSearch,
    useNavigate,
  }
}

export function Outlet() {
  return null
}
