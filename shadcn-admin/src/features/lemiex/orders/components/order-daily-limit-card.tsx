'use client'

import { useEffect, useState } from 'react'
import { Loader2, SlidersHorizontal } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { API_BASE_URL } from '@/config/api'
import { apiRequest } from '@/lib/client'
import { getUserRoleName } from '@/services/auth/api'
import { fetchLemiexUsers } from '@/services/lemiex-users/api'
import { useAuthStore } from '@/stores/auth-store'

// 3-segment path so it can never collide with /orders/{id} routes.
const DAILY_LIMIT_ENDPOINT = '/orders/config/daily-limit'
// Seller role id in the backend (UserRole::SELLER).
const SELLER_ROLE_ID = '2'
// Sentinel value for the "all sellers" overview (shadcn SelectItem can't use '').
const ALL_SELLERS = '__all__'

type DailyLimit = {
  scope: 'global' | 'seller'
  seller_id: number | null
  base_limit: number
  extra_today: number
  effective_limit: number
  used_today: number
  remaining_today: number
  date: string
}

type SellerOption = { id: number; username: string; email?: string }

/**
 * Admin-only control on the Orders page to view/adjust the daily order
 * creation limit. Both the base floor and the "extra" are PER-SELLER: pick a
 * seller to set their base (falls back to a default if unset) and to open extra
 * slots for today only (auto-resets tomorrow, enforced server-side). With no
 * seller selected, the stats show a system-wide overview and editing is locked.
 */
export function OrderDailyLimitCard() {
  const currentUser = useAuthStore((state) => state.auth.user)
  const isAdmin = getUserRoleName(currentUser) === 'Admin'

  const [open, setOpen] = useState(false)
  const [info, setInfo] = useState<DailyLimit | null>(null)
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [baseInput, setBaseInput] = useState('')
  const [extraInput, setExtraInput] = useState('')
  const [sellers, setSellers] = useState<SellerOption[]>([])
  // '' = global overview (no seller chosen yet).
  const [selectedSellerId, setSelectedSellerId] = useState('')

  async function load(sellerId: string) {
    setLoading(true)
    try {
      const qs = sellerId ? `?seller_id=${sellerId}` : ''
      const res = await apiRequest<{ data: DailyLimit }>(
        `${API_BASE_URL}${DAILY_LIMIT_ENDPOINT}${qs}`,
        { method: 'GET', useCache: false }
      )
      setInfo(res.data)
      setBaseInput(String(res.data.base_limit))
      setExtraInput(String(res.data.extra_today))
    } catch {
      // Silent: the card simply won't show live numbers if the fetch fails.
    } finally {
      setLoading(false)
    }
  }

  async function loadSellers() {
    try {
      const { users } = await fetchLemiexUsers({
        page: 1,
        per_page: 500,
        role_id: SELLER_ROLE_ID,
      })
      setSellers(
        users.map((u) => ({
          id: Number(u.id),
          username: u.username ?? String(u.id),
          email: u.email ?? undefined,
        }))
      )
    } catch {
      // Silent: the select simply stays empty if sellers can't be fetched.
    }
  }

  useEffect(() => {
    if (!isAdmin) return
    void load('')
  }, [isAdmin])

  function handleOpen() {
    setOpen(true)
    void load(selectedSellerId)
    if (sellers.length === 0) void loadSellers()
  }

  function handleSellerChange(value: string) {
    const next = value === ALL_SELLERS ? '' : value
    setSelectedSellerId(next)
    void load(next)
  }

  async function handleSave() {
    setSaving(true)
    try {
      const body: Record<string, unknown> = {
        seller_id: Number(selectedSellerId),
        base_limit: Number(baseInput) || undefined,
        extra_today: Math.max(0, Number(extraInput) || 0),
      }

      const res = await apiRequest<{ data: DailyLimit; message?: string }>(
        `${API_BASE_URL}${DAILY_LIMIT_ENDPOINT}`,
        {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        }
      )
      setInfo(res.data)
      setBaseInput(String(res.data.base_limit))
      setExtraInput(String(res.data.extra_today))
      toast.success(res.message || 'Đã cập nhật giới hạn đơn hàng.')
      setOpen(false)
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : 'Cập nhật giới hạn thất bại'
      )
    } finally {
      setSaving(false)
    }
  }

  if (!isAdmin) return null

  const sellerSelected = selectedSellerId !== ''
  const selectedSeller = sellers.find(
    (s) => String(s.id) === selectedSellerId
  )
  const totalToday =
    (Number(baseInput) || 0) + Math.max(0, Number(extraInput) || 0)

  return (
    <>
      <Button
        type='button'
        variant='outline'
        className='rounded-[6px]'
        onClick={handleOpen}
      >
        <SlidersHorizontal className='size-4' />
        {info
          ? `Giới hạn hôm nay: ${info.used_today}/${info.effective_limit}`
          : 'Giới hạn đơn/ngày'}
      </Button>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className='max-w-[460px] rounded-[8px] p-0 shadow-xl'>
          <DialogHeader className='border-b px-6 py-5'>
            <DialogTitle className='text-xl'>
              Giới hạn đơn sản xuất / ngày
            </DialogTitle>
            <DialogDescription>
              Chọn người bán để đặt mức nền và mở thêm đơn riêng cho họ. Phần mở
              thêm chỉ áp dụng hôm nay và tự về 0 vào ngày mai.
            </DialogDescription>
          </DialogHeader>

          <div className='space-y-5 px-6 py-5'>
            {info ? (
              <>
                <div className='text-[12px] text-muted-foreground'>
                  {sellerSelected
                    ? `Người bán: ${selectedSeller?.username ?? selectedSellerId}`
                    : 'Tổng toàn hệ thống hôm nay'}
                </div>
                <div className='grid grid-cols-3 gap-3 text-center'>
                  <div className='rounded-[6px] border px-3 py-2'>
                    <div className='text-[12px] text-muted-foreground'>
                      Đã tạo
                    </div>
                    <div className='text-lg font-semibold'>
                      {info.used_today}
                    </div>
                  </div>
                  <div className='rounded-[6px] border px-3 py-2'>
                    <div className='text-[12px] text-muted-foreground'>
                      Giới hạn
                    </div>
                    <div className='text-lg font-semibold'>
                      {info.effective_limit}
                    </div>
                  </div>
                  <div className='rounded-[6px] border px-3 py-2'>
                    <div className='text-[12px] text-muted-foreground'>
                      Còn lại
                    </div>
                    <div className='text-lg font-semibold'>
                      {info.remaining_today}
                    </div>
                  </div>
                </div>
              </>
            ) : null}

            <div className='space-y-2'>
              <label className='text-sm font-medium'>Người bán</label>
              <Select
                value={selectedSellerId || ALL_SELLERS}
                onValueChange={handleSellerChange}
              >
                <SelectTrigger className='h-11 rounded-[6px] px-4 text-[14px]'>
                  <SelectValue placeholder='Tổng quan (tất cả người bán)' />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL_SELLERS}>
                    Tổng quan (tất cả người bán)
                  </SelectItem>
                  {sellers.map((s) => (
                    <SelectItem key={s.id} value={String(s.id)}>
                      {s.username}
                      {s.email ? ` (${s.email})` : ''}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className='space-y-2'>
              <label className='text-sm font-medium'>Mức nền (đơn/ngày)</label>
              <Input
                type='number'
                min={1}
                disabled={!sellerSelected}
                className='h-11 rounded-[6px] px-4 text-[14px]'
                value={baseInput}
                onChange={(e) => setBaseInput(e.target.value)}
                placeholder='50'
              />
              {sellerSelected ? (
                <p className='text-[12px] text-muted-foreground'>
                  Mức nền riêng của {selectedSeller?.username ?? 'người bán này'},
                  áp dụng mỗi ngày.
                </p>
              ) : (
                <p className='text-[12px] text-muted-foreground'>
                  Chọn người bán để đặt mức nền riêng. Người bán chưa đặt sẽ dùng
                  mức mặc định ({info?.base_limit ?? 50} đơn/ngày).
                </p>
              )}
            </div>

            <div className='space-y-2'>
              <label className='text-sm font-medium'>
                Mở thêm hôm nay (+ đơn)
              </label>
              <Input
                type='number'
                min={0}
                disabled={!sellerSelected}
                className='h-11 rounded-[6px] px-4 text-[14px]'
                value={extraInput}
                onChange={(e) => setExtraInput(e.target.value)}
                placeholder='0'
              />
              {sellerSelected ? (
                <p className='text-[12px] text-muted-foreground'>
                  Chỉ áp dụng cho {selectedSeller?.username ?? 'người bán này'}{' '}
                  hôm nay ({info?.date ?? '...'}). Đặt 0 để tắt. Tổng hôm nay ={' '}
                  {totalToday} đơn.
                </p>
              ) : (
                <p className='text-[12px] text-muted-foreground'>
                  Chọn người bán để mở thêm đơn cho riêng họ hôm nay.
                </p>
              )}
            </div>
          </div>

          <DialogFooter className='border-t px-6 py-4'>
            <Button
              type='button'
              variant='outline'
              className='rounded-[6px]'
              onClick={() => setOpen(false)}
            >
              Hủy
            </Button>
            <Button
              type='button'
              className='rounded-[6px]'
              disabled={saving || loading || !sellerSelected}
              onClick={() => void handleSave()}
            >
              {saving ? (
                <>
                  <Loader2 className='size-4 animate-spin' />
                  Đang lưu...
                </>
              ) : (
                'Lưu'
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
