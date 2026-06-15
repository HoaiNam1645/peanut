import { LemiexUserDetailPage } from '@/features/lemiex/users/user-detail-page'

export default async function LemiexSystemsUserDetailRoute({
  params,
}: {
  params: Promise<{ id: string }>
}) {
  const { id } = await params
  return <LemiexUserDetailPage id={id} />
}
