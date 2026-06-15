import Link from 'next/link'

const policyLinks = [
  { href: '/policies/privacy', label: 'Privacy Policy' },
  { href: '/policies/terms', label: 'Terms of Service' },
  { href: '/policies/refund', label: 'Refund Policy' },
  { href: '/policies/shipping', label: 'Shipping Policy' },
  { href: '/policies/contact', label: 'Contact & Support' },
]

export function SiteFooter() {
  const year = new Date().getFullYear()

  return (
    <footer className='border-t bg-background'>
      <div className='mx-auto grid w-full max-w-7xl gap-6 px-4 py-6 text-xs sm:px-6 lg:grid-cols-2 lg:px-8'>
        <div className='space-y-2 text-muted-foreground'>
          <p className='text-sm font-semibold text-foreground'>DragonBug LLC</p>
          <p>
            Registered Address: 30 N Gould St Ste N Sheridan, WY 82801,
            Wyoming, United States.
          </p>
          <p>
            Registered Agent: Nguyen Dang Ngoc Thanh (Thanh Dang Ngoc Nguyen)
          </p>
          <p>Filing Date: 04/14/2026 | EIN: 98-1932106</p>
          <p>
            Contact:{' '}
            <a href='mailto:contact@dragonbugllc.com' className='underline-offset-4 hover:underline'>
              contact@dragonbugllc.com
            </a>{' '}
            |{' '}
            <a href='tel:+13072485984' className='underline-offset-4 hover:underline'>
              +1 (307) 248-5984
            </a>
          </p>
        </div>

        <div className='flex flex-col gap-2 text-muted-foreground lg:items-end'>
          <p className='text-sm font-semibold text-foreground'>Policies</p>
          <nav className='flex flex-wrap gap-x-4 gap-y-2'>
            {policyLinks.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className='underline-offset-4 transition-colors hover:text-foreground hover:underline'
              >
                {item.label}
              </Link>
            ))}
          </nav>
          <p className='pt-1 text-[11px]'>
            Copyright {year} DragonBug LLC. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  )
}
