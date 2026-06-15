export default function AuthLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <div className='flex min-h-svh flex-col'>
      <div className='flex-1'>{children}</div>
    </div>
  )
}
