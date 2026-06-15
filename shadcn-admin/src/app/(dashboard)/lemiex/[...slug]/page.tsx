import { LemiexPageShell } from '@/features/lemiex/components/lemiex-page-shell'

function toTitleCase(value: string) {
  return value
    .split('-')
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

export default async function LemiexCatchAllPage({
  params,
}: {
  params: Promise<{ slug: string[] }>
}) {
  const { slug } = await params
  const routePath = `/lemiex/${slug.join('/')}`
  const title = slug.map(toTitleCase).join(' / ')

  return (
    <LemiexPageShell
      title={title}
      description='Màn hình placeholder để chuẩn bị move giao diện Lemiex cũ sang shell mới.'
      routePath={routePath}
    />
  )
}
