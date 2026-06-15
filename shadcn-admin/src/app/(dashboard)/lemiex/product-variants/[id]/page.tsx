import { LemiexProductVariantDetailPage } from '@/features/lemiex/product-variants/detail/product-variant-detail-page'

type ProductVariantDetailPageProps = {
  params: Promise<{ id: string }>
}

export default async function ProductVariantDetailPage({
  params,
}: ProductVariantDetailPageProps) {
  const { id } = await params

  return <LemiexProductVariantDetailPage id={id} />
}
