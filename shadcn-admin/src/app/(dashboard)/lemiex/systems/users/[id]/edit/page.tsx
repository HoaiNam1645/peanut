import { LemiexUserFormPage } from '@/features/lemiex/users/user-form-page'

export default async function LemiexSystemsUserEditRoute({
  params,
}: {
  params: Promise<{ id: string }>
}) {
  const { id } = await params
  return <LemiexUserFormPage mode='edit' id={id} />
}
