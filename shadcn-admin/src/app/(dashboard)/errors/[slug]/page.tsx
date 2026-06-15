import { notFound } from 'next/navigation'

import { ForbiddenError } from '@/features/errors/forbidden'
import { GeneralError } from '@/features/errors/general-error'
import { MaintenanceError } from '@/features/errors/maintenance-error'
import { NotFoundError } from '@/features/errors/not-found-error'
import { UnauthorisedError } from '@/features/errors/unauthorized-error'

const errorPages = {
  forbidden: <ForbiddenError />,
  'internal-server-error': <GeneralError />,
  'maintenance-error': <MaintenanceError />,
  'not-found': <NotFoundError />,
  unauthorized: <UnauthorisedError />,
}

export default async function ErrorSlugPage({
  params,
}: {
  params: Promise<{ slug: string }>
}) {
  const { slug } = await params

  if (!(slug in errorPages)) {
    notFound()
  }

  return errorPages[slug as keyof typeof errorPages]
}
