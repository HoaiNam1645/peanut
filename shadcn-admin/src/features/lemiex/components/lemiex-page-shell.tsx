'use client'

import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

type LemiexPageShellProps = {
  title: string
  description: string
  routePath: string
  notes?: string[]
}

export function LemiexPageShell({
  title,
  description,
  routePath,
  notes = [],
}: LemiexPageShellProps) {
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

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-3'>
          <div className='space-y-2'>
            <Badge variant='secondary' className='rounded-full px-3 py-1'>
              Lemiex Migration
            </Badge>
            <div>
              <h2 className='text-2xl font-bold tracking-tight'>{title}</h2>
              <p className='text-muted-foreground'>{description}</p>
            </div>
          </div>
        </div>

        <div className='grid gap-4 lg:grid-cols-[1.25fr_0.75fr]'>
          <Card>
            <CardHeader>
              <CardTitle>Namespace</CardTitle>
            </CardHeader>
            <CardContent className='space-y-3 text-sm text-muted-foreground'>
              <p>
                Tất cả màn mới của Lemiex sẽ được nhóm dưới route prefix
                {' '}
                <span className='font-medium text-foreground'>{routePath}</span>.
              </p>
              <p>
                Đây là điểm neo để move giao diện dần từ
                {' '}
                <span className='font-medium text-foreground'>
                  manage-lemiex-react
                </span>
                {' '}
                sang shell mới mà không đụng vào các màn demo gốc.
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Next Steps</CardTitle>
            </CardHeader>
            <CardContent className='space-y-2 text-sm text-muted-foreground'>
              {(notes.length > 0 ? notes : [
                'Map route cũ sang route Lemiex mới.',
                'Dời layout, filter, table và modal theo từng module.',
                'Giữ shell và design tokens dùng chung cho toàn repo.',
              ]).map((note) => (
                <div key={note} className='rounded-lg border bg-muted/30 px-3 py-2'>
                  {note}
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      </Main>
    </>
  )
}
