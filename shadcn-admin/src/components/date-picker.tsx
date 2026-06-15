'use client'

import { format } from 'date-fns'
import { enUS, vi } from 'date-fns/locale'
import { Calendar as CalendarIcon } from 'lucide-react'
import { useI18n } from '@/context/i18n-provider'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'

type DatePickerProps = {
  selected: Date | undefined
  onSelect: (date: Date | undefined) => void
  placeholder?: string
  className?: string
}

export function DatePicker({
  selected,
  onSelect,
  placeholder = 'Pick a date',
  className,
}: DatePickerProps) {
  const { locale } = useI18n()
  const calendarLocale = locale === 'vi' ? vi : enUS
  const browserLocale =
    typeof navigator !== 'undefined' && navigator.language
      ? navigator.language
      : locale === 'vi'
        ? 'vi-VN'
        : 'en-US'

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant='outline'
          data-empty={!selected}
          className={cn(
            'justify-start text-start font-normal data-[empty=true]:text-muted-foreground',
            className
          )}
        >
          {selected ? (
            format(selected, 'P', { locale: calendarLocale })
          ) : (
            <span>{placeholder}</span>
          )}
          <CalendarIcon className='ms-auto h-4 w-4 opacity-50' />
        </Button>
      </PopoverTrigger>
      <PopoverContent className='w-auto p-0'>
        <Calendar
          mode='single'
          captionLayout='dropdown'
          locale={calendarLocale}
          selected={selected}
          onSelect={onSelect}
          formatters={{
            formatMonthDropdown: (date) =>
              date.toLocaleString(browserLocale, { month: 'short' }),
          }}
          disabled={(date: Date) =>
            date > new Date() || date < new Date('1900-01-01')
          }
        />
      </PopoverContent>
    </Popover>
  )
}
