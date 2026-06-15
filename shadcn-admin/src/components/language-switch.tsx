'use client'

import { Check, Languages } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useI18n } from '@/context/i18n-provider'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

export function LanguageSwitch() {
  const { locale, setLocale, messages } = useI18n()

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button variant='ghost' size='icon' className='scale-95 rounded-full'>
          <Languages className='size-[1.1rem]' />
          <span className='sr-only'>{messages.language.label}</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end'>
        <DropdownMenuItem onClick={() => setLocale('vi')}>
          {messages.language.vietnamese}
          <Check size={14} className={cn('ms-auto', locale !== 'vi' && 'hidden')} />
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => setLocale('en')}>
          {messages.language.english}
          <Check size={14} className={cn('ms-auto', locale !== 'en' && 'hidden')} />
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
