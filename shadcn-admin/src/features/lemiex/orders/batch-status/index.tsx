'use client'

import { useMemo, useState } from 'react'
import { Loader2 } from 'lucide-react'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { Header } from '@/components/layout/header'
import { LanguageSwitch } from '@/components/language-switch'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { FALLBACK_FULFILL_STATUS_OPTIONS } from '@/features/lemiex/orders/constants'
import {
  batchChangeOrderFulfillStatus,
  type BatchChangeStatusResponse,
} from '@/services/orders/api'

const MAX_IDS = 200

function parseOrderIds(raw: string): number[] {
  return Array.from(
    new Set(
      raw
        .split(/[\s,;\n]+/)
        .map((s) => s.trim())
        .filter(Boolean)
        .map((s) => Number(s))
        .filter((n) => Number.isInteger(n) && n > 0)
    )
  )
}

export function BatchChangeOrderStatus() {
  const [rawIds, setRawIds] = useState('')
  const [status, setStatus] = useState<string>('producing')
  const [submitting, setSubmitting] = useState(false)
  const [result, setResult] = useState<BatchChangeStatusResponse | null>(null)

  const parsedIds = useMemo(() => parseOrderIds(rawIds), [rawIds])
  const tooMany = parsedIds.length > MAX_IDS
  const canSubmit = !submitting && parsedIds.length > 0 && !tooMany && !!status

  async function handleSubmit() {
    if (!canSubmit) return
    setSubmitting(true)
    setResult(null)
    try {
      const data = await batchChangeOrderFulfillStatus(parsedIds, status)
      setResult(data)
      if (data.fail_count === 0) {
        toast.success(`Đã đổi ${data.success_count}/${data.total} đơn sang "${data.target_status}"`)
      } else {
        toast.warning(
          `Hoàn tất: ${data.success_count} thành công, ${data.fail_count} thất bại`
        )
      }
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Có lỗi xảy ra'
      toast.error(msg)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <>
      <Header>
        <Search />
        <div className='ml-auto flex items-center space-x-4'>
          <LanguageSwitch />
          <ThemeSwitch />
          <ProfileDropdown />
        </div>
      </Header>

      <Main>
        <div className='mb-4 flex items-center justify-between'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>Batch Change Order Status</h2>
            <p className='text-muted-foreground text-sm'>
              Đổi trạng thái nhiều đơn cùng lúc (tối đa {MAX_IDS} đơn/lần).
            </p>
          </div>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Input</CardTitle>
            <CardDescription>
              Dán hoặc gõ Order ID, phân tách bằng dấu phẩy, dấu chấm phẩy, khoảng trắng hoặc xuống dòng.
            </CardDescription>
          </CardHeader>
          <CardContent className='space-y-4'>
            <div className='space-y-2'>
              <Label htmlFor='order-ids'>Order IDs</Label>
              <Textarea
                id='order-ids'
                value={rawIds}
                onChange={(e) => setRawIds(e.target.value)}
                placeholder={'101, 102, 103\n104\n105;106'}
                className='min-h-32 font-mono text-sm'
              />
              <div className='text-muted-foreground flex items-center gap-3 text-xs'>
                <span>
                  Đã nhận:{' '}
                  <strong className={tooMany ? 'text-destructive' : ''}>
                    {parsedIds.length}
                  </strong>{' '}
                  ID hợp lệ
                </span>
                {tooMany && (
                  <span className='text-destructive'>
                    Vượt quá giới hạn {MAX_IDS} ID
                  </span>
                )}
              </div>
            </div>

            <div className='space-y-2'>
              <Label>Trạng thái đích</Label>
              <Select value={status} onValueChange={setStatus}>
                <SelectTrigger className='w-full max-w-xs'>
                  <SelectValue placeholder='Chọn trạng thái' />
                </SelectTrigger>
                <SelectContent>
                  {FALLBACK_FULFILL_STATUS_OPTIONS.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      {opt.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <Button onClick={handleSubmit} disabled={!canSubmit}>
              {submitting && <Loader2 className='mr-2 h-4 w-4 animate-spin' />}
              Đổi {parsedIds.length || ''} đơn sang &quot;{status}&quot;
            </Button>
          </CardContent>
        </Card>

        {result && (
          <Card className='mt-6'>
            <CardHeader>
              <CardTitle>Kết quả</CardTitle>
              <CardDescription>
                Target: <strong>{result.target_status}</strong> · Tổng:{' '}
                <strong>{result.total}</strong> ·{' '}
                <span className='text-emerald-600'>
                  Thành công: {result.success_count}
                </span>{' '}
                ·{' '}
                <span className='text-destructive'>
                  Thất bại: {result.fail_count}
                </span>
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className='w-24'>Order ID</TableHead>
                    <TableHead className='w-32'>Trạng thái</TableHead>
                    <TableHead>Thông tin</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {result.results.map((row) => (
                    <TableRow key={row.order_id}>
                      <TableCell className='font-mono'>{row.order_id}</TableCell>
                      <TableCell>
                        {row.success ? (
                          <Badge className='bg-emerald-600 hover:bg-emerald-700'>
                            Thành công
                          </Badge>
                        ) : (
                          <Badge variant='destructive'>
                            Lỗi {row.code ?? ''}
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell className='text-muted-foreground text-sm'>
                        {row.success ? '—' : row.message ?? 'Unknown error'}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}
      </Main>
    </>
  )
}
