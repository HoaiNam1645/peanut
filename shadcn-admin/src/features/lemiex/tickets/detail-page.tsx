'use client'
/* eslint-disable @next/next/no-img-element */

import { useEffect, useMemo, useRef, useState } from 'react'
import { useRouter } from 'next/navigation'
import { ArrowLeft, Paperclip, Send } from 'lucide-react'
import { toast } from 'sonner'
import { useI18n } from '@/context/i18n-provider'
import { useAuthStore } from '@/stores/auth-store'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { fetchTicketById, sendTicketMessage, updateTicketStatus, type TicketDetail, type TicketMessage } from '@/services/tickets/api'

const fallbackMessages = {
  back: 'Back',
  backToTickets: 'Back to Tickets',
  loading: 'Loading ticket...',
  notFound: 'Ticket not found',
  loadDetailFailed: 'Failed to load ticket details',
  fileSizeError: 'File size must be less than 10MB',
  fileTypeError: 'Only JPG, PNG, GIF, and PDF files are allowed',
  viewPdf: 'View PDF',
  noMessages: 'No messages yet. Start the conversation!',
  placeholder: 'Type your message... (Shift+Enter for new line)',
  placeholderImage: 'Image selected - ready to send',
  enterMessage: 'Please enter a message or attach a file',
  sendFailed: 'Failed to send message',
  statusUpdated: 'Status updated successfully!',
  statusUpdateFailed: 'Failed to update status',
  markSolved: 'Mark as Solved',
  reopen: 'Reopen',
  status: { new: 'New', solved: 'Solved' },
  unknown: 'Unknown',
}

const ALLOWED_TYPES = [
  'image/jpeg',
  'image/jpg',
  'image/png',
  'image/gif',
  'application/pdf',
]

function formatDate(dateString?: string | null) {
  if (!dateString) return ''
  try {
    return new Date(dateString).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return ''
  }
}

export function LemiexTicketDetailPage({ id }: { id: string }) {
  const { messages } = useI18n()
  const ui = messages.ticketDetailPage ?? fallbackMessages
  const router = useRouter()
  const currentUser = useAuthStore((state) => state.auth.user)
  const fileInputRef = useRef<HTMLInputElement | null>(null)
  const messagesEndRef = useRef<HTMLDivElement | null>(null)

  const [ticket, setTicket] = useState<TicketDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [sending, setSending] = useState(false)
  const [message, setMessage] = useState('')
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)

  const roleName =
    typeof currentUser?.role === 'string'
      ? currentUser.role
      : currentUser?.role && typeof currentUser.role === 'object'
        ? String(currentUser.role.name || '')
        : String(currentUser?.role_name || '')

  useEffect(() => {
    void (async () => {
      try {
        setLoading(true)
        const response = await fetchTicketById(id)
        setTicket(response)
      } catch (error) {
        toast.error(error instanceof Error ? error.message : ui.loadDetailFailed)
        router.push('/lemiex/tickets')
      } finally {
        setLoading(false)
      }
    })()
  }, [id, router, ui.loadDetailFailed])

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [ticket?.messages])

  function handleFileChange(file?: File | null) {
    if (!file) return
    if (file.size > 10 * 1024 * 1024) {
      toast.error(ui.fileSizeError)
      return
    }
    if (!ALLOWED_TYPES.includes(file.type)) {
      toast.error(ui.fileTypeError)
      return
    }
    setSelectedFile(file)
    if (file.type.startsWith('image/')) {
      const reader = new FileReader()
      reader.onloadend = () => {
        setPreviewUrl(typeof reader.result === 'string' ? reader.result : null)
      }
      reader.readAsDataURL(file)
    } else {
      setPreviewUrl(null)
    }
  }

  function removeFile() {
    setSelectedFile(null)
    setPreviewUrl(null)
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  async function handleSendMessage(event: React.FormEvent) {
    event.preventDefault()
    if (!selectedFile && !message.trim()) {
      toast.error(ui.enterMessage)
      return
    }

    try {
      setSending(true)
      const response = await sendTicketMessage(id, selectedFile ? '' : message.trim(), selectedFile)

      if (response.success || response.status) {
        setTicket((prev) => {
          if (!prev) return prev
          const exists = prev.messages?.some((item) => item.id === response.data?.id)
          if (exists || !response.data) return prev
          return {
            ...prev,
            messages: [...(prev.messages || []), response.data as TicketMessage],
          }
        })
        setMessage('')
        removeFile()
      }
    } catch (error) {
      toast.error(error instanceof Error ? error.message : ui.sendFailed)
    } finally {
      setSending(false)
    }
  }

  async function handleStatusChange(newStatus: number) {
    try {
      await updateTicketStatus(id, newStatus)
      toast.success(ui.statusUpdated)
      const response = await fetchTicketById(id)
      setTicket(response)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : ui.statusUpdateFailed)
    }
  }

  const renderedMessages = useMemo(() => ticket?.messages || [], [ticket?.messages])

  if (loading) {
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
        <Main fluid className='flex flex-1 items-center justify-center px-4 py-6'>
          <div className='text-sm text-muted-foreground'>{ui.loading}</div>
        </Main>
      </>
    )
  }

  if (!ticket) {
    return (
      <>
        <Header fixed>
          <Search />
        </Header>
        <Main fluid className='flex flex-1 items-center justify-center px-4 py-6'>
          <div className='space-y-3 text-center'>
            <h2 className='text-xl font-semibold'>{ui.notFound}</h2>
            <Button className='rounded-[6px]' onClick={() => router.push('/lemiex/tickets')}>
              {ui.backToTickets}
            </Button>
          </div>
        </Main>
      </>
    )
  }

  return (
    <>
      <Header fixed>
        <Search />
      </Header>

      <Main fluid className='flex flex-1 flex-col gap-4 px-4 py-6 sm:px-5 lg:px-6 xl:px-7'>
        <div className='space-y-6'>
          <div className='flex flex-col gap-4'>
            <div className='flex items-center justify-between'>
              <Button
                variant='outline'
                className='h-10 rounded-[6px]'
                onClick={() => router.push('/lemiex/tickets')}
              >
                <ArrowLeft className='size-4' />
                {ui.back}
              </Button>

              <div className='flex items-center gap-2'>
                <Badge
                  variant='outline'
                  className={
                    Number(ticket.status) === 0
                      ? 'border-amber-200 bg-amber-500/10 text-amber-700'
                      : 'border-emerald-200 bg-emerald-500/10 text-emerald-700'
                  }
                >
                  {Number(ticket.status) === 0 ? ui.status.new : ui.status.solved}
                </Badge>

                {roleName === 'Admin' && Number(ticket.status) === 0 ? (
                  <Button className='h-10 rounded-[6px]' onClick={() => void handleStatusChange(1)}>
                    {ui.markSolved}
                  </Button>
                ) : null}
                {roleName === 'Admin' && Number(ticket.status) === 1 ? (
                  <Button
                    variant='outline'
                    className='h-10 rounded-[6px]'
                    onClick={() => void handleStatusChange(0)}
                  >
                    {ui.reopen}
                  </Button>
                ) : null}
              </div>
            </div>

            <div className='flex flex-wrap items-center gap-3'>
              <h1 className='text-3xl font-semibold tracking-tight'>Ticket #{ticket.id}</h1>
              <span className='text-muted-foreground'>•</span>
              <p className='text-lg text-muted-foreground'>{ticket.subject}</p>
              {ticket.order?.order_stt ? (
                <Badge variant='outline' className='rounded-full'>
                  {ticket.order.order_stt}
                </Badge>
              ) : null}
            </div>
          </div>

          <Card className='rounded-[6px] shadow-sm'>
            <CardContent className='flex min-h-[60vh] flex-col gap-4 p-5'>
              <div className='flex-1 space-y-4 overflow-y-auto pr-1'>
                {renderedMessages.length > 0 ? (
                  renderedMessages.map((msg) => {
                    const isMine = msg.user?.id === currentUser?.id
                    const content = msg.message || ''
                    const isImageUrl =
                      content.startsWith('http') &&
                      /\.(jpg|jpeg|png|gif|webp)/i.test(content)
                    const isPdfUrl = content.startsWith('http') && /\.pdf/i.test(content)

                    return (
                      <div
                        key={String(msg.id)}
                        className={`flex gap-3 ${isMine ? 'justify-end' : 'justify-start'}`}
                      >
                        {!isMine ? (
                          <div className='flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary'>
                            {(msg.user?.username || '?').charAt(0).toUpperCase()}
                          </div>
                        ) : null}

                        <div
                          className={`max-w-[75%] rounded-[10px] border px-4 py-3 ${
                            isMine ? 'bg-primary text-primary-foreground' : 'bg-muted/20'
                          }`}
                        >
                          <div className='mb-1 flex items-center gap-2 text-xs opacity-80'>
                            <span>{msg.user?.username || ui.unknown}</span>
                            <span>{formatDate(msg.created_at)}</span>
                          </div>
                          <div className='text-sm leading-6'>
                            {isImageUrl ? (
                              <a href={content} target='_blank' rel='noreferrer'>
                                <img
                                  src={content}
                                  alt='Image'
                                  className='max-h-[240px] rounded-[6px] object-contain'
                                />
                              </a>
                            ) : isPdfUrl ? (
                              <a href={content} target='_blank' rel='noreferrer' className='underline'>
                                {ui.viewPdf}
                              </a>
                            ) : (
                              content
                            )}
                          </div>
                        </div>

                        {isMine ? (
                          <div className='flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary'>
                            {(msg.user?.username || '?').charAt(0).toUpperCase()}
                          </div>
                        ) : null}
                      </div>
                    )
                  })
                ) : (
                  <div className='flex h-full items-center justify-center text-sm text-muted-foreground'>
                    {ui.noMessages}
                  </div>
                )}
                <div ref={messagesEndRef} />
              </div>

              {selectedFile ? (
                <div className='flex items-start gap-3 rounded-[6px] border p-3'>
                  {previewUrl ? (
                    <img
                      src={previewUrl}
                      alt='Preview'
                      className='size-16 rounded-[6px] object-cover'
                    />
                  ) : (
                    <div className='flex size-16 items-center justify-center rounded-[6px] bg-muted text-xl'>
                      PDF
                    </div>
                  )}
                  <div className='min-w-0 flex-1'>
                    <p className='truncate text-sm font-medium'>{selectedFile.name}</p>
                    <p className='text-xs text-muted-foreground'>
                      {(selectedFile.size / 1024).toFixed(2)} KB
                    </p>
                  </div>
                  <Button type='button' variant='outline' className='rounded-[6px]' onClick={removeFile}>
                    {ui.remove}
                  </Button>
                </div>
              ) : null}

              <form className='flex items-end gap-3' onSubmit={handleSendMessage}>
                <Button
                  type='button'
                  variant='outline'
                  size='icon'
                  className='size-11 rounded-[6px]'
                  disabled={sending}
                  onClick={() => fileInputRef.current?.click()}
                >
                  <Paperclip className='size-4' />
                </Button>
                <input
                  ref={fileInputRef}
                  type='file'
                  className='hidden'
                  accept='image/jpeg,image/jpg,image/png,image/gif,application/pdf'
                  onChange={(event) => handleFileChange(event.target.files?.[0] || null)}
                />
                <textarea
                  className='min-h-[44px] flex-1 rounded-[6px] border bg-background px-3 py-3 text-sm'
                  placeholder={selectedFile ? ui.placeholderImage : ui.placeholder}
                  value={message}
                  onChange={(event) => setMessage(event.target.value)}
                  disabled={sending || Boolean(selectedFile)}
                  rows={1}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                      event.preventDefault()
                      void handleSendMessage(event)
                    }
                  }}
                />
                <Button
                  type='submit'
                  className='size-11 rounded-[6px] p-0'
                  disabled={sending || (!message.trim() && !selectedFile)}
                >
                  <Send className='size-4' />
                </Button>
              </form>
            </CardContent>
          </Card>
        </div>
      </Main>
    </>
  )
}
