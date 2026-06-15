import { LemiexTicketDetailPage } from '@/features/lemiex/tickets/detail-page'

export default async function LemiexTicketDetailRoute({
  params,
}: {
  params: Promise<{ id: string }>
}) {
  const { id } = await params
  return <LemiexTicketDetailPage id={id} />
}
