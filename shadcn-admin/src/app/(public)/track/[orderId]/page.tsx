import { TrackOrder } from '@/features/lemiex/track/track-order'

export default async function TrackOrderPage({
  params,
}: {
  params: Promise<{ orderId: string }>
}) {
  const { orderId } = await params
  return <TrackOrder orderId={orderId} />
}
