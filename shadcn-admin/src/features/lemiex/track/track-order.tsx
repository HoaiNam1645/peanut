'use client'

import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type DragEvent,
} from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import {
  AlertTriangle,
  Camera,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Loader2,
  Package,
  Palette,
} from 'lucide-react'
import { toast } from 'sonner'
import { API_BASE_URL, API_ENDPOINTS } from '@/config/api'
import { useI18n } from '@/context/i18n-provider'
import { apiRequest } from '@/lib/client'
import { cn } from '@/lib/utils'
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
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog'
import { VisuallyHidden } from '@radix-ui/react-visually-hidden'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { FallbackImage } from './fallback-image'

// -------------------- Types --------------------

type Needle = {
  code: string
  name?: string
  rgb_hex?: string
}

type DesignColor = {
  sequence?: number
  needle_number?: number | null
  rgb_hex?: string
  code?: string
  name?: string
  chart?: string
}

type Design = {
  position: string
  pes_filename?: string
  pdf_url?: string | null
  stitch_count?: number
  width_mm?: number
  height_mm?: number
  color_count?: number
  status?: boolean
  qc_status?: boolean
  packing_status?: boolean
  shipout_status?: boolean
  needle_assignment?: Record<string, Needle | null> | null
  colors?: DesignColor[]
  [key: string]: unknown
}

type OrderItem = {
  id: number
  variant_id?: string | number
  product_name?: string
  mockup?: string
  mockup_back?: string
  product?: {
    product_name?: string
    style?: string
    size?: string
    stock?: number
    color?: string
    color_image?: string
    color_images?: string[]
    variant_id?: string | number
    [key: string]: unknown
  }
  designs?: Design[]
  [key: string]: unknown
}

type OrderTrackingData = {
  order?: {
    id?: number
    fulfill_status?: string
    order_type?: string
    customer?: { name?: string }
    [key: string]: unknown
  }
  items?: OrderItem[]
}

type TrackingResponse = {
  success: boolean
  data: OrderTrackingData
  message?: string
}

type StatusResponse = {
  status: boolean
  message?: string
}

type ConfirmData = {
  itemId: number
  metaKey: string
  newStatus: boolean
  stage?: 'staff' | 'qc' | 'packing' | 'shipout'
  checkbox?: HTMLInputElement | null
}

// Avoid bringing in the html5-qrcode types at module scope (the library is
// dynamically imported on demand inside the scanner flow).
import type { Html5Qrcode as Html5QrCodeInstance } from 'html5-qrcode'

// -------------------- Helpers --------------------

const STATUS_COLORS: Record<string, string> = {
  new_order: '#3b82f6',
  producing: '#f59e0b',
  shipped: '#10b981',
  delivered: '#059669',
  cancelled: '#6b7280',
  closed: '#475569',
  return_to_support: '#ef4444',
}

function getContrastColor(hexColor?: string) {
  if (!hexColor) return '#64748b'
  const hex = hexColor.replace('#', '')
  const r = parseInt(hex.substr(0, 2), 16)
  const g = parseInt(hex.substr(2, 2), 16)
  const b = parseInt(hex.substr(4, 2), 16)
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
  return luminance > 0.5 ? '#1e293b' : '#ffffff'
}

function format(template: string, vars: Record<string, string | number>) {
  return template.replace(/\{(\w+)\}/g, (_, key) =>
    vars[key] !== undefined ? String(vars[key]) : `{${key}}`
  )
}

function humanize(value?: string | null): string {
  if (!value) return 'N/A'
  const trimmed = value.trim()
  if (!trimmed || trimmed.toLowerCase() === 'unknown') return 'N/A'
  return trimmed
}

// Maps shadcn-admin role names → numeric IDs used by the workflow logic.
// Legacy project stored numeric `role_id`; shadcn-admin auth store keeps roles
// as a string name (e.g. "Admin") on `user.role.name` or `user.role_name`.
const ROLE_NAME_TO_ID: Record<string, number> = {
  Admin: 1,
  Staff: 3,
  Support: 5,
  QC: 8,
  Packing: 9,
  Shipout: 10,
  Seller: 2,
}

function readUserRoleId(): number | null {
  if (typeof window === 'undefined') return null
  try {
    // shadcn-admin auth store key
    const raw =
      window.localStorage.getItem('lemiex_auth_user') ??
      window.localStorage.getItem('user') // legacy fallback
    if (!raw) return null
    const parsed = JSON.parse(raw) as {
      role_id?: number
      role_name?: string | null
      role?:
        | { name?: string }
        | string
        | null
    }

    // Legacy numeric id first
    if (typeof parsed.role_id === 'number') return parsed.role_id

    // shadcn-admin shape: role.name or role_name
    let name: string | undefined
    if (typeof parsed.role === 'string') {
      name = parsed.role
    } else if (parsed.role && typeof parsed.role === 'object') {
      name = parsed.role.name
    }
    name ??= parsed.role_name ?? undefined

    if (name && ROLE_NAME_TO_ID[name] !== undefined) {
      return ROLE_NAME_TO_ID[name]
    }
    return null
  } catch {
    return null
  }
}

// -------------------- Component --------------------

export function TrackOrder({ orderId }: { orderId: string }) {
  const { messages } = useI18n()
  const t = messages.trackOrder

  const router = useRouter()
  const searchParams = useSearchParams()
  const stt = searchParams.get('stt')
  const itemIdFromUrl = searchParams.get('item_id')
  const itemSttFromUrl = searchParams.get('item_stt')

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [orderData, setOrderData] = useState<OrderTrackingData | null>(null)
  const [expandedItems, setExpandedItems] = useState<Record<number, boolean>>(
    {}
  )

  const [updatingDesign, setUpdatingDesign] = useState<string | null>(null)

  const [showConfirmModal, setShowConfirmModal] = useState(false)
  const [confirmData, setConfirmData] = useState<ConfirmData | null>(null)

  const [needleAssignments, setNeedleAssignments] = useState<
    Record<string, (Needle | null)[]>
  >({})
  const [draggedNeedle, setDraggedNeedle] = useState<{
    designKey: string
    index: number
    needle: Needle | null
  } | null>(null)
  const [selectedNeedle, setSelectedNeedle] = useState<{
    designKey: string
    index: number
    needle: Needle
  } | null>(null)

  const [validatedColorImages, setValidatedColorImages] = useState<
    Record<number, string>
  >({})

  const [currentPage, setCurrentPage] = useState(1)
  const itemsPerPage = 1

  const [showScanner, setShowScanner] = useState(false)
  const [scannerError, setScannerError] = useState<string | null>(null)
  const html5QrCodeRef = useRef<Html5QrCodeInstance | null>(null)

  // -------------------- Data fetching --------------------

  const fetchOrderTracking = useCallback(
    async (preservePage = false) => {
      try {
        setLoading(true)
        setError(null)

        const params: string[] = []
        if (stt) params.push(`stt=${stt}`)
        if (itemIdFromUrl) params.push(`item_id=${itemIdFromUrl}`)
        const qs = params.length > 0 ? `?${params.join('&')}` : ''

        const data = await apiRequest<TrackingResponse>(
          `${API_BASE_URL}${API_ENDPOINTS.ORDER_TRACK}/${orderId}${qs}`,
          {
            method: 'GET',
            useCache: false,
            dedupe: false,
            headers: {
              'Content-Type': 'application/json',
              'ngrok-skip-browser-warning': 'true',
            },
          }
        )

        if (data.success) {
          setOrderData(data.data)

          if (preservePage) {
            setExpandedItems({ 0: true })
            return
          }

          const items = data.data.items || []
          if (items.length > 0) {
            if (itemSttFromUrl) {
              const pageNum = parseInt(itemSttFromUrl, 10)
              if (pageNum >= 1 && pageNum <= items.length) {
                setExpandedItems({ 0: true })
                setCurrentPage(pageNum)
              } else {
                setExpandedItems({ 0: true })
                setCurrentPage(1)
              }
            } else if (itemIdFromUrl) {
              const itemIndex = items.findIndex(
                (item) => item.id === parseInt(itemIdFromUrl, 10)
              )
              if (itemIndex >= 0) {
                setExpandedItems({ 0: true })
                setCurrentPage(itemIndex + 1)
              } else {
                setExpandedItems({ 0: true })
                setCurrentPage(1)
              }
            } else {
              setExpandedItems({ 0: true })
              setCurrentPage(1)
            }
          }
        } else {
          setError(data.message || t.notFound)
        }
      } catch (err) {
        console.error('Error fetching order tracking:', err)
        setError(t.failedLoad)
      } finally {
        setLoading(false)
      }
    },
    [orderId, stt, itemIdFromUrl, itemSttFromUrl, t.notFound, t.failedLoad]
  )

  useEffect(() => {
    fetchOrderTracking()
  }, [fetchOrderTracking])

  // -------------------- Scanner --------------------

  const stopScanner = useCallback(async () => {
    if (html5QrCodeRef.current) {
      try {
        await html5QrCodeRef.current.stop()
        html5QrCodeRef.current = null
      } catch (err) {
        console.error('Error stopping scanner:', err)
      }
    }
    setShowScanner(false)
  }, [])

  useEffect(() => {
    return () => {
      if (html5QrCodeRef.current) {
        html5QrCodeRef.current.stop().catch(() => {})
        html5QrCodeRef.current = null
      }
    }
  }, [])

  const handleQrResult = useCallback(
    (url: string) => {
      stopScanner()
      const match = url.match(/\/track\/(\d+)/)
      if (match) {
        const newOrderId = match[1]
        let queryParams = ''
        try {
          const urlObj = new URL(url)
          const sttParam = urlObj.searchParams.get('stt')
          const itemId = urlObj.searchParams.get('item_id')
          const itemStt = urlObj.searchParams.get('item_stt')
          const params: string[] = []
          if (sttParam) params.push(`stt=${sttParam}`)
          if (itemId) params.push(`item_id=${itemId}`)
          if (itemStt) params.push(`item_stt=${itemStt}`)
          if (params.length > 0) queryParams = `?${params.join('&')}`
        } catch {
          // ignore
        }
        router.push(`/track/${newOrderId}${queryParams}`)
      } else {
        toast.error(t.scanner.invalidQr)
      }
    },
    [router, stopScanner, t.scanner.invalidQr]
  )

  const startScanner = useCallback(async () => {
    setScannerError(null)

    if (
      typeof window !== 'undefined' &&
      window.location.protocol !== 'https:' &&
      window.location.hostname !== 'localhost'
    ) {
      setScannerError(t.scanner.httpsRequired)
      setShowScanner(true)
      return
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setScannerError(t.scanner.notSupported)
      setShowScanner(true)
      return
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' },
      })
      stream.getTracks().forEach((track) => track.stop())
    } catch (permErr) {
      const err = permErr as Error & { name?: string }
      console.error('Camera permission error:', err)
      if (err.name === 'NotAllowedError') {
        setScannerError(t.scanner.permissionDenied)
      } else if (err.name === 'NotFoundError') {
        setScannerError(t.scanner.notFound)
      } else if (err.name === 'NotReadableError') {
        setScannerError(t.scanner.inUse)
      } else {
        setScannerError(
          format(t.scanner.error, { error: err.message || 'Unknown' })
        )
      }
      setShowScanner(true)
      return
    }

    setShowScanner(true)

    setTimeout(async () => {
      try {
        const mod = await import('html5-qrcode')
        const Html5Qrcode = mod.Html5Qrcode
        const html5QrCode = new Html5Qrcode('qr-reader')
        html5QrCodeRef.current = html5QrCode

        const containerWidth =
          document.getElementById('qr-reader')?.offsetWidth || 350
        const qrboxSize = Math.min(containerWidth - 40, 280)

        try {
          await html5QrCode.start(
            { facingMode: 'environment' },
            {
              fps: 10,
              qrbox: { width: qrboxSize, height: qrboxSize },
              aspectRatio: 1.0,
            },
            (decodedText: string) => handleQrResult(decodedText),
            () => {}
          )
        } catch (backCamErr) {
          console.warn('Back camera failed, trying front camera:', backCamErr)
          await html5QrCode.start(
            { facingMode: 'user' },
            {
              fps: 10,
              qrbox: { width: qrboxSize, height: qrboxSize },
              aspectRatio: 1.0,
            },
            (decodedText: string) => handleQrResult(decodedText),
            () => {}
          )
        }
      } catch (err) {
        const e = err as Error
        console.error('Failed to start scanner:', e)
        setScannerError(
          format(t.scanner.startFailed, { error: e.message || 'Unknown error' })
        )
        setShowScanner(false)
      }
    }, 200)
  }, [
    handleQrResult,
    t.scanner.error,
    t.scanner.httpsRequired,
    t.scanner.inUse,
    t.scanner.notFound,
    t.scanner.notSupported,
    t.scanner.permissionDenied,
    t.scanner.startFailed,
  ])

  // -------------------- Pagination --------------------

  const totalItems = orderData?.items?.length || 0
  const totalPages = Math.ceil(totalItems / itemsPerPage)

  const getCurrentPageItem = () => {
    if (!orderData?.items) return []
    const startIndex = (currentPage - 1) * itemsPerPage
    return orderData.items.slice(startIndex, startIndex + itemsPerPage)
  }

  const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages) {
      setCurrentPage(page)
      setExpandedItems({ 0: true })
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }

  // -------------------- Needle handling --------------------

  const swapNeedles = (
    designKey: string,
    fromIndex: number,
    toIndex: number
  ) => {
    setNeedleAssignments((prev) => {
      const current = prev[designKey] || new Array<Needle | null>(12).fill(null)
      const newAssignment = [...current]
      const temp = newAssignment[fromIndex]
      newAssignment[fromIndex] = newAssignment[toIndex]
      newAssignment[toIndex] = temp
      return { ...prev, [designKey]: newAssignment }
    })
  }

  const handleNeedleDragStart = (
    e: DragEvent<HTMLDivElement>,
    designKey: string,
    index: number,
    needle: Needle | null
  ) => {
    if (!needle) {
      e.preventDefault()
      return
    }
    setDraggedNeedle({ designKey, index, needle })
    e.dataTransfer.effectAllowed = 'move'
    e.currentTarget.classList.add('opacity-50')
  }

  const handleNeedleDragEnd = (e: DragEvent<HTMLDivElement>) => {
    e.currentTarget.classList.remove('opacity-50')
    setDraggedNeedle(null)
    document
      .querySelectorAll('[data-needle-drop-target="true"]')
      .forEach((el) => {
        el.removeAttribute('data-needle-drop-target')
      })
  }

  const handleNeedleDragOver = (
    e: DragEvent<HTMLDivElement>,
    designKey: string,
    index: number
  ) => {
    e.preventDefault()
    if (
      draggedNeedle &&
      draggedNeedle.designKey === designKey &&
      draggedNeedle.index !== index
    ) {
      e.currentTarget.setAttribute('data-needle-drop-target', 'true')
    }
  }

  const handleNeedleDragLeave = (e: DragEvent<HTMLDivElement>) => {
    e.currentTarget.removeAttribute('data-needle-drop-target')
  }

  const handleNeedleDrop = (
    e: DragEvent<HTMLDivElement>,
    designKey: string,
    index: number
  ) => {
    e.preventDefault()
    e.currentTarget.removeAttribute('data-needle-drop-target')
    if (
      draggedNeedle &&
      draggedNeedle.designKey === designKey &&
      draggedNeedle.index !== index
    ) {
      swapNeedles(designKey, draggedNeedle.index, index)
    }
    setDraggedNeedle(null)
  }

  const handleNeedleTap = (
    designKey: string,
    index: number,
    needle: Needle | null
  ) => {
    if (!selectedNeedle) {
      if (needle) setSelectedNeedle({ designKey, index, needle })
      return
    }
    if (
      selectedNeedle.designKey === designKey &&
      selectedNeedle.index === index
    ) {
      setSelectedNeedle(null)
      return
    }
    if (selectedNeedle.designKey === designKey) {
      swapNeedles(designKey, selectedNeedle.index, index)
      setSelectedNeedle(null)
      return
    }
    if (needle) {
      setSelectedNeedle({ designKey, index, needle })
    } else {
      setSelectedNeedle(null)
    }
  }

  const getNeedleAssignment = (
    itemId: number,
    designPosition: string,
    originalAssignment?: Record<string, Needle | null> | null
  ): (Needle | null)[] => {
    const key = `${itemId}_${designPosition}`
    if (!needleAssignments[key]) {
      const assignment = new Array<Needle | null>(12).fill(null)
      if (originalAssignment) {
        Object.entries(originalAssignment).forEach(([needleNum, needle]) => {
          const index = parseInt(needleNum, 10) - 1
          if (index >= 0 && index < 12 && needle) {
            assignment[index] = needle
          }
        })
      }
      // Defer state set to avoid update during render
      queueMicrotask(() => {
        setNeedleAssignments((prev) =>
          prev[key] ? prev : { ...prev, [key]: assignment }
        )
      })
      return assignment
    }
    return needleAssignments[key]
  }

  const getUpdatedColors = (
    colors: DesignColor[],
    itemId: number,
    designPosition: string,
    originalAssignment?: Record<string, Needle | null> | null
  ) => {
    const assignment = getNeedleAssignment(
      itemId,
      designPosition,
      originalAssignment
    )
    return colors.map((color) => {
      let needleNumber: number | null = null
      assignment.forEach((needle, index) => {
        if (needle && needle.code === color.code) {
          needleNumber = index + 1
        }
      })
      return { ...color, needle_number: needleNumber }
    })
  }

  // -------------------- Status mutations --------------------

  // Open the confirm modal for a given production stage on a design position.
  const requestStage = (
    itemId: number,
    position: string,
    stage: 'staff' | 'qc' | 'packing' | 'shipout'
  ) => {
    // Not logged in → go to login first, then come back to this track page.
    if (!loggedIn) {
      const ret = encodeURIComponent(
        window.location.pathname + window.location.search
      )
      router.push(`/login?redirect=${ret}`)
      return
    }
    const designKey = `${itemId}_${position}`
    if (updatingDesign === designKey) return

    setConfirmData({
      itemId,
      metaKey: position,
      newStatus: true,
      stage,
      checkbox: null,
    })
    setShowConfirmModal(true)
  }

  // Open the order's shipping label in a new tab to print it.
  const openShippingLabel = (): boolean => {
    const o = orderData?.order
    const labelUrl =
      (o?.shipping_label as string | undefined) ||
      (o?.convert_label as string | undefined) ||
      ''
    if (!labelUrl) {
      toast.error('Đơn chưa có label để in')
      return false
    }
    window.open(labelUrl, '_blank')
    return true
  }

  const handleConfirmYes = async () => {
    if (!confirmData) return
    const { itemId, metaKey, newStatus, checkbox, stage } = confirmData
    const designKey = `${itemId}_${metaKey}`
    setShowConfirmModal(false)
    setUpdatingDesign(designKey)

    // Final (shipout) step prints the label. Open the print tab synchronously on this
    // user click so the popup blocker allows it, then point it at the label after success.
    let printWin: Window | null = null
    if (stage === 'shipout') {
      printWin = window.open('', '_blank')
    }

    try {
      const data = await apiRequest<StatusResponse>(
        `${API_BASE_URL}${API_ENDPOINTS.ORDER_CHANGE_STATUS_ITEMS}`,
        {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'ngrok-skip-browser-warning': 'true',
          },
          body: JSON.stringify({
            item_id: itemId,
            meta_key: metaKey,
            status: newStatus,
            stage,
          }),
        }
      )

      if (data.status) {
        toast.success(t.status.readySuccess)
        if (stage === 'shipout') {
          const o = orderData?.order
          const labelUrl =
            (o?.shipping_label as string | undefined) ||
            (o?.convert_label as string | undefined) ||
            ''
          if (printWin && labelUrl) {
            printWin.location.href = labelUrl
          } else {
            printWin?.close()
            if (!labelUrl) toast.error('Đơn chưa có label để in')
          }
        }
        await fetchOrderTracking(true)
      } else {
        printWin?.close()
        toast.error(data.message || t.status.updateFailed)
        if (checkbox) checkbox.checked = false
      }
    } catch (err) {
      printWin?.close()
      console.error('Error updating design status:', err)
      toast.error(t.status.updateFailed)
      if (checkbox) checkbox.checked = false
    } finally {
      setUpdatingDesign(null)
      setConfirmData(null)
    }
  }

  const handleConfirmNo = () => {
    if (confirmData?.checkbox) confirmData.checkbox.checked = false
    setShowConfirmModal(false)
    setConfirmData(null)
  }

  // -------------------- Render helpers --------------------

  const isPrintOrder = orderData?.order?.order_type === 'Tumbler'

  const getStatusColor = (status?: string) =>
    (status && STATUS_COLORS[status]) || '#6b7280'

  const getStatusText = (status?: string) => {
    const keys: Record<string, keyof typeof t.status> = {
      new_order: 'newOrder',
      producing: 'inProduction',
      shipped: 'shipped',
      delivered: 'delivered',
      cancelled: 'cancelled',
      closed: 'closed',
      return_to_support: 'returnToSupport',
    }
    if (!status) return 'Unknown'
    const key = keys[status]
    return key ? t.status[key] : status
  }

  const getDesignDisplayName = (item: OrderItem, design: Design) => {
    const positionLabel = design.position?.toUpperCase() || 'DESIGN'
    if (isPrintOrder) return `${positionLabel} IMAGE`
    return `${positionLabel}: ${
      design.pes_filename || `${orderId}_${item.id}_${design.position}.pes`
    }`
  }

  const roleId = useMemo(() => readUserRoleId(), [])
  // Track view is public; actions require login. We show the action button to logged-out
  // viewers and send them to login on click (the API enforces auth + role/permission).
  const loggedIn = useMemo(
    () =>
      typeof window !== 'undefined' &&
      !!window.localStorage.getItem('lemiex_access_token'),
    []
  )

  // -------------------- Early returns --------------------

  if (loading) {
    return (
      <div className='flex min-h-svh items-center justify-center bg-background p-6'>
        <div className='flex flex-col items-center gap-3 text-muted-foreground'>
          <Loader2 className='size-8 animate-spin' />
          <p>{t.loading}</p>
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className='flex min-h-svh items-center justify-center bg-background p-6'>
        <Card className='w-full max-w-md'>
          <CardContent className='flex flex-col items-center gap-4 p-8 text-center'>
            <AlertTriangle className='size-12 text-destructive' />
            <h2 className='text-xl font-semibold'>{t.notFound}</h2>
            <p className='text-muted-foreground'>{error}</p>
            <Button onClick={() => fetchOrderTracking()}>{t.tryAgain}</Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  // -------------------- Main render --------------------

  return (
    <div className='mx-auto min-h-svh w-full max-w-3xl bg-background px-3 py-4 sm:px-6 sm:py-6'>
      {/* QR Scanner Dialog */}
      <Dialog
        open={showScanner}
        onOpenChange={(open) => {
          if (!open) stopScanner()
        }}
      >
        <DialogContent className='max-w-md p-4 sm:p-6'>
          <VisuallyHidden>
            <DialogTitle>{t.scanner.tapToScan}</DialogTitle>
          </VisuallyHidden>
          <div
            id='qr-reader'
            className='mx-auto aspect-square w-full max-w-sm overflow-hidden rounded-md bg-black'
          />
          {scannerError && (
            <div className='mt-3 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive'>
              {scannerError}
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* Confirm "Mark as Ready" modal */}
      <AlertDialog
        open={showConfirmModal}
        onOpenChange={(open) => {
          if (!open) handleConfirmNo()
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {confirmData
                ? format(t.confirmModal.markAsReady, {
                    position: confirmData.metaKey.toUpperCase(),
                  })
                : ''}
            </AlertDialogTitle>
            <AlertDialogDescription className='sr-only'>
              {t.confirmModal.markAsReady}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={handleConfirmNo}>
              {t.confirmModal.cancel}
            </AlertDialogCancel>
            <AlertDialogAction onClick={handleConfirmYes}>
              {t.confirmModal.confirm}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Search/QR Scanner Bar */}
      <div className='mb-4'>
        <div
          role='button'
          tabIndex={0}
          onClick={startScanner}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') startScanner()
          }}
          className='relative flex cursor-pointer items-center'
        >
          <Input
            readOnly
            placeholder={t.scanner.tapToScan}
            className='pr-12 text-sm cursor-pointer'
          />
          <span className='pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground'>
            <Camera className='size-5' />
          </span>
        </div>
      </div>

      {/* Order Header */}
      <Card className='mb-4'>
        <CardContent className='space-y-2 p-4 text-sm'>
          <div className='flex items-center justify-between gap-3'>
            <span className='text-muted-foreground'>{t.labels.order}:</span>
            <span className='font-semibold'>{orderData?.order?.id}</span>
          </div>
          <div className='flex items-center justify-between gap-3'>
            <span className='text-muted-foreground'>{t.labels.user}:</span>
            <span className='font-medium'>
              {orderData?.order?.customer?.name || 'QC'}
            </span>
          </div>
          <div className='flex items-center justify-between gap-3'>
            <span className='text-muted-foreground'>
              {t.labels.orderStatus}:
            </span>
            <Badge
              className='rounded-md px-2.5 py-1 text-xs font-semibold text-white'
              style={{
                backgroundColor: getStatusColor(
                  orderData?.order?.fulfill_status
                ),
              }}
            >
              {getStatusText(orderData?.order?.fulfill_status)}
            </Badge>
          </div>
        </CardContent>
      </Card>

      {/* Order Items Section */}
      <div className='mb-4'>
        <div className='mb-3 flex items-center gap-2 text-sm font-semibold'>
          <Package className='size-4' />
          <span>{t.labels.orderItems}</span>
          <span className='ml-auto text-muted-foreground'>
            {currentPage}/{totalItems}
          </span>
        </div>

        <div className='space-y-4'>
          {getCurrentPageItem().map((item, index) => {
            const expanded = expandedItems[index]
            return (
              <Card key={item.id} className='overflow-hidden'>
                <button
                  type='button'
                  onClick={() =>
                    setExpandedItems((prev) => ({
                      ...prev,
                      [index]: !prev[index],
                    }))
                  }
                  className='flex w-full items-center justify-between gap-3 border-b bg-muted/40 p-4 text-left transition-colors hover:bg-muted'
                >
                  <div className='flex min-w-0 flex-wrap items-center gap-2 text-sm font-medium'>
                    <span className='shrink-0'>
                      {t.labels.item} #
                      {(currentPage - 1) * itemsPerPage + index + 1}
                    </span>
                    <Badge
                      variant='secondary'
                      className='font-mono text-xs'
                    >
                      {t.labels.variantId}:{' '}
                      {item.product?.variant_id || item.variant_id}
                    </Badge>
                    {(item.product?.product_name || item.product_name) ? (
                      <span className='min-w-0 truncate text-foreground'>
                        {item.product?.product_name || item.product_name}
                      </span>
                    ) : null}
                  </div>
                  <ChevronDown
                    className={cn(
                      'size-4 shrink-0 text-muted-foreground transition-transform',
                      expanded ? 'rotate-180' : 'rotate-[-90deg]'
                    )}
                  />
                </button>

                {expanded && (
                  <CardContent className='space-y-5 p-4'>
                    {/* Product info */}
                    <div className='rounded-md border bg-muted/30 p-3 text-center'>
                      <div className='text-xs font-semibold uppercase text-muted-foreground'>
                        {t.labels.styleSizeStock}
                      </div>
                      <div className='mt-1 text-sm font-semibold text-primary'>
                        {humanize(item.product?.style)} -{' '}
                        {humanize(item.product?.size)} -{' '}
                        {item.product?.stock ?? 0}
                      </div>
                    </div>

                    {/* Color image */}
                    {(item.product?.color_image ||
                      (item.product?.color_images?.length ?? 0) > 0) && (
                      <div className='flex justify-center'>
                        <div className='overflow-hidden rounded-md border bg-muted/30'>
                          <FallbackImage
                            src={item.product?.color_image}
                            fallbackUrls={item.product?.color_images || []}
                            alt={`${item.product?.style} - ${item.product?.color}`}
                            loading='lazy'
                            decoding='async'
                            className='max-h-64 w-auto object-contain'
                            onValidUrl={(url) =>
                              setValidatedColorImages((prev) => ({
                                ...prev,
                                [item.id]: url,
                              }))
                            }
                          />
                        </div>
                      </div>
                    )}

                    {/* Mockups */}
                    {(item.mockup || item.mockup_back) && (
                      <div className='grid grid-cols-2 gap-3'>
                        {item.mockup && (
                          <div className='overflow-hidden rounded-md border bg-muted/30'>
                            {/* eslint-disable-next-line @next/next/no-img-element */}
                            <img
                              src={item.mockup}
                              alt='Front Mockup'
                              loading='lazy'
                              decoding='async'
                              className='w-full object-contain'
                            />
                          </div>
                        )}
                        {item.mockup_back && (
                          <div className='overflow-hidden rounded-md border bg-muted/30'>
                            {/* eslint-disable-next-line @next/next/no-img-element */}
                            <img
                              src={item.mockup_back}
                              alt='Back Mockup'
                              loading='lazy'
                              decoding='async'
                              className='w-full object-contain'
                            />
                          </div>
                        )}
                      </div>
                    )}

                    {/* Designs */}
                    {item.designs && item.designs.length > 0 && (
                      <div className='space-y-4'>
                        <div className='flex items-center gap-2 text-sm font-semibold'>
                          <Palette className='size-4' />
                          <span>{t.labels.designPositions}</span>
                        </div>

                        {item.designs.map((design, dIndex) => {
                          const designKey = `${item.id}_${design.position}`
                          const isUpdating = updatingDesign === designKey
                          const orderStatus = orderData?.order?.fulfill_status

                          // Stage the logged-in user is allowed to action. Supervisor roles
                          // (Admin/Staff/Support = 1/3/5) can do ANY stage (1 worker covers all);
                          // dedicated roles (QC=8/Packing=9/Shipout=10) only their own.
                          const isSupervisor =
                            roleId !== null && [1, 3, 5].includes(roleId)
                          const dedicatedStage =
                            roleId === 8
                              ? 'qc'
                              : roleId === 9
                                ? 'packing'
                                : roleId === 10
                                  ? 'shipout'
                                  : null
                          // Logged-out viewers see the action button too; clicking it routes
                          // them to login (then the API enforces the real role/permission).
                          const canDo = (s: string) =>
                            !loggedIn || isSupervisor || dedicatedStage === s

                          const stageButton = (
                            s: 'staff' | 'qc' | 'packing' | 'shipout',
                            label: string
                          ) => (
                            <Button
                              size='sm'
                              disabled={isUpdating}
                              onClick={() =>
                                requestStage(item.id, design.position, s)
                              }
                            >
                              {isUpdating ? (
                                <Loader2 className='mr-1.5 size-3.5 animate-spin' />
                              ) : (
                                <span className='mr-1.5'>✓</span>
                              )}
                              {label}
                            </Button>
                          )
                          const waitLabel = (txt: string) => (
                            <span className='inline-flex items-center rounded-md bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'>
                              {txt}
                            </span>
                          )

                          // Progression: staff → QC → packing → shipout. One button at a time.
                          let actionNode: React.ReactNode = null
                          if (orderStatus === 'cancelled') {
                            actionNode = (
                              <span className='inline-flex items-center rounded-md bg-destructive/10 px-3 py-1.5 text-xs font-semibold text-destructive'>
                                {t.status.cancelledOrder}
                              </span>
                            )
                          } else if (orderStatus === 'closed') {
                            actionNode = (
                              <span className='inline-flex items-center rounded-md bg-muted px-3 py-1.5 text-xs font-semibold text-muted-foreground'>
                                {t.status.closedOrder}
                              </span>
                            )
                          } else if (!design.status) {
                            actionNode = canDo('staff')
                              ? stageButton('staff', 'Nhân viên xong')
                              : waitLabel('⏳ Chờ nhân viên')
                          } else if (!design.qc_status) {
                            actionNode = canDo('qc')
                              ? stageButton('qc', 'QC pass')
                              : waitLabel('⏳ Chờ KCS')
                          } else if (!design.packing_status) {
                            actionNode = canDo('packing')
                              ? stageButton('packing', 'Đóng gói xong')
                              : waitLabel('⏳ Chờ đóng gói')
                          } else if (!design.shipout_status) {
                            actionNode = canDo('shipout')
                              ? stageButton('shipout', 'Đã gửi & In label')
                              : waitLabel('⏳ Chờ vận chuyển')
                          } else {
                            actionNode = (
                              <div className='flex flex-wrap items-center gap-2'>
                                <span className='inline-flex items-center rounded-md bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'>
                                  ✓ Hoàn thành
                                </span>
                                <Button
                                  size='sm'
                                  variant='outline'
                                  onClick={() => openShippingLabel()}
                                >
                                  🖨 In label
                                </Button>
                              </div>
                            )
                          }

                          return (
                            <div
                              key={dIndex}
                              className='space-y-3 rounded-md border bg-card p-4'
                            >
                              <div className='flex flex-wrap items-center justify-between gap-2'>
                                <div className='text-sm font-semibold'>
                                  {getDesignDisplayName(item, design)}
                                  {isUpdating && (
                                    <span className='ml-2 text-xs text-primary'>
                                      <Loader2 className='mr-1 inline size-3 animate-spin' />
                                      {t.labels.updating}
                                    </span>
                                  )}
                                </div>
                              </div>

                              {/* Workflow Bar */}
                              <div className='flex items-center gap-1'>
                                {(
                                  [
                                    {
                                      done: !!design.status,
                                      label: t.labels.staff,
                                    },
                                    {
                                      done: !!design.qc_status,
                                      label: t.labels.qc,
                                    },
                                    {
                                      done: !!design.packing_status,
                                      label: t.labels.pack,
                                    },
                                    {
                                      done: !!design.shipout_status,
                                      label: t.labels.ship,
                                    },
                                  ] as const
                                ).map((step, sIdx, arr) => (
                                  <div
                                    key={sIdx}
                                    className='flex flex-1 items-center gap-1'
                                  >
                                    <div
                                      className={cn(
                                        'flex flex-1 flex-col items-center rounded-md px-2 py-2 text-xs font-medium',
                                        step.done
                                          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                          : 'bg-muted text-muted-foreground'
                                      )}
                                    >
                                      <span className='text-sm leading-none'>
                                        {step.done ? '✓' : '○'}
                                      </span>
                                      <span className='mt-1'>{step.label}</span>
                                    </div>
                                    {sIdx < arr.length - 1 && (
                                      <span
                                        className={cn(
                                          'shrink-0 px-0.5 text-sm font-semibold leading-none',
                                          arr[sIdx + 1].done
                                            ? 'text-emerald-500'
                                            : 'text-muted-foreground/50'
                                        )}
                                        aria-hidden='true'
                                      >
                                        →
                                      </span>
                                    )}
                                  </div>
                                ))}
                              </div>

                              <div className='flex justify-end'>
                                {actionNode}
                              </div>

                              {/* PDF Preview with mockup background */}
                              {design.pdf_url && (
                                <div
                                  className='flex items-center justify-center overflow-hidden rounded-md'
                                  style={{
                                    backgroundImage: validatedColorImages[
                                      item.id
                                    ]
                                      ? `url('${validatedColorImages[item.id]}')`
                                      : 'none',
                                    backgroundPosition: 'center',
                                    backgroundRepeat: 'no-repeat',
                                    backgroundSize: '280%',
                                    backgroundColor: validatedColorImages[
                                      item.id
                                    ]
                                      ? 'transparent'
                                      : '#f1f5f9',
                                    minHeight: '280px',
                                  }}
                                >
                                  <div className='flex items-center justify-center p-6'>
                                    {/* eslint-disable-next-line @next/next/no-img-element */}
                                    <img
                                      src={design.pdf_url}
                                      alt={`${design.position} design`}
                                      loading='lazy'
                                      decoding='async'
                                      className='max-h-64 w-auto object-contain'
                                    />
                                  </div>
                                </div>
                              )}

                              {/* Needle Assignment */}
                              {!isPrintOrder && design.needle_assignment && (
                                <div className='rounded-md border bg-muted/20 p-3'>
                                  <div className='mb-2 text-center text-xs font-semibold text-muted-foreground'>
                                    {format(t.needle.title, {
                                      action: selectedNeedle
                                        ? t.needle.tapToSwap
                                        : t.needle.dragOrTap,
                                    })}
                                  </div>
                                  <div className='grid grid-cols-6 gap-2'>
                                    {getNeedleAssignment(
                                      item.id,
                                      design.position,
                                      design.needle_assignment
                                    ).map((needle, i) => {
                                      const bgColor =
                                        needle?.rgb_hex || '#e2e8f0'
                                      const textColor = needle
                                        ? getContrastColor(bgColor)
                                        : '#94a3b8'
                                      const dKey = `${item.id}_${design.position}`
                                      const isSelected =
                                        selectedNeedle?.designKey === dKey &&
                                        selectedNeedle?.index === i
                                      const isDraggable = needle !== null

                                      return (
                                        <div
                                          key={i}
                                          draggable={isDraggable}
                                          style={{
                                            backgroundColor: bgColor,
                                            color: textColor,
                                          }}
                                          title={
                                            needle
                                              ? `${needle.code} - ${needle.name || ''}`
                                              : `Needle ${i + 1}`
                                          }
                                          className={cn(
                                            'flex aspect-square cursor-pointer select-none flex-col items-center justify-center rounded-md border text-[10px] font-semibold transition-all data-[needle-drop-target=true]:ring-2 data-[needle-drop-target=true]:ring-primary',
                                            isDraggable && 'shadow-sm',
                                            isSelected &&
                                              'ring-2 ring-primary ring-offset-2'
                                          )}
                                          onDragStart={(e) =>
                                            handleNeedleDragStart(
                                              e,
                                              dKey,
                                              i,
                                              needle
                                            )
                                          }
                                          onDragEnd={handleNeedleDragEnd}
                                          onDragOver={(e) =>
                                            handleNeedleDragOver(e, dKey, i)
                                          }
                                          onDragLeave={handleNeedleDragLeave}
                                          onDrop={(e) =>
                                            handleNeedleDrop(e, dKey, i)
                                          }
                                          onClick={() =>
                                            handleNeedleTap(dKey, i, needle)
                                          }
                                        >
                                          <span className='leading-none'>
                                            {i + 1}
                                          </span>
                                          {needle && (
                                            <span className='mt-0.5 leading-none'>
                                              {needle.code}
                                            </span>
                                          )}
                                        </div>
                                      )
                                    })}
                                  </div>
                                  {selectedNeedle && (
                                    <div className='mt-2 text-center text-xs text-primary'>
                                      {format(t.needle.hint, {
                                        needle: selectedNeedle.index + 1,
                                      })}
                                    </div>
                                  )}
                                </div>
                              )}

                              {/* Color Stop Sequence */}
                              {!isPrintOrder &&
                                design.colors &&
                                design.colors.length > 0 && (
                                  <div>
                                    <div className='mb-2 text-xs font-semibold uppercase text-muted-foreground'>
                                      {t.colorSequence.title}
                                    </div>
                                    <div className='overflow-x-auto rounded-md border'>
                                      <Table>
                                        <TableHeader>
                                          <TableRow>
                                            <TableHead className='h-8 px-2 text-xs'>
                                              {t.colorSequence.sequence}
                                            </TableHead>
                                            <TableHead className='h-8 px-2 text-xs'>
                                              {t.colorSequence.needle}
                                            </TableHead>
                                            <TableHead className='h-8 px-2 text-xs'>
                                              {t.colorSequence.color}
                                            </TableHead>
                                            <TableHead className='h-8 px-2 text-xs'>
                                              {t.colorSequence.code}
                                            </TableHead>
                                            <TableHead className='h-8 px-2 text-xs'>
                                              {t.colorSequence.name}
                                            </TableHead>
                                            <TableHead className='h-8 px-2 text-xs'>
                                              {t.colorSequence.chart}
                                            </TableHead>
                                          </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                          {getUpdatedColors(
                                            design.colors,
                                            item.id,
                                            design.position,
                                            design.needle_assignment
                                          ).map((color, cIndex) => (
                                            <TableRow key={cIndex}>
                                              <TableCell className='px-2 py-1.5 text-xs'>
                                                {color.sequence || cIndex + 1}
                                              </TableCell>
                                              <TableCell className='px-2 py-1.5 text-xs'>
                                                {color.needle_number || '-'}
                                              </TableCell>
                                              <TableCell className='px-2 py-1.5'>
                                                <div
                                                  className='size-5 rounded border'
                                                  style={{
                                                    backgroundColor:
                                                      color.rgb_hex || '#ccc',
                                                  }}
                                                />
                                              </TableCell>
                                              <TableCell className='px-2 py-1.5 text-xs font-mono'>
                                                {color.code || '-'}
                                              </TableCell>
                                              <TableCell className='px-2 py-1.5 text-xs'>
                                                {color.name || '-'}
                                              </TableCell>
                                              <TableCell className='px-2 py-1.5 text-xs'>
                                                {color.chart || '-'}
                                              </TableCell>
                                            </TableRow>
                                          ))}
                                        </TableBody>
                                      </Table>
                                    </div>
                                  </div>
                                )}
                            </div>
                          )
                        })}
                      </div>
                    )}
                  </CardContent>
                )}
              </Card>
            )
          })}
        </div>
      </div>

      {/* Pagination */}
      {totalItems > 0 && (
        <div className='flex items-center justify-center gap-1'>
          <Button
            variant='outline'
            size='icon'
            onClick={() => goToPage(currentPage - 1)}
            disabled={currentPage === 1}
          >
            <ChevronLeft className='size-4' />
          </Button>
          {Array.from({ length: totalPages }).map((_, i) => (
            <Button
              key={i + 1}
              variant={currentPage === i + 1 ? 'default' : 'outline'}
              size='sm'
              onClick={() => goToPage(i + 1)}
              className='min-w-9'
            >
              {i + 1}
            </Button>
          ))}
          <Button
            variant='outline'
            size='icon'
            onClick={() => goToPage(currentPage + 1)}
            disabled={currentPage === totalPages}
          >
            <ChevronRight className='size-4' />
          </Button>
        </div>
      )}
    </div>
  )
}
