import { OrderDetailPage } from '@/features/lemiex/orders/detail/order-detail-page'

type OrderDetailRouteProps = {
  params: Promise<{
    id: string
  }>
}

export default async function LemiexOrderDetailRoute({
  params,
}: OrderDetailRouteProps) {
  const { id } = await params

  return <OrderDetailPage orderId={id} />
}
