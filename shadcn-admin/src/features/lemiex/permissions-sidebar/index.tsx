'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import { useRouter } from 'next/navigation'
import { ChevronDown, Loader2, RotateCcw, Save, ShieldCheck } from 'lucide-react'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { useI18n } from '@/context/i18n-provider'
import {
  PAGE_ACCESS_GROUP_NAME,
  PAGE_ACCESS_PERMISSION_BY_PATH,
  type PageAccessTreeNode,
  getLemiexPageAccessTree,
  getLemiexRole,
  getRolePagePermissions as getDefaultRolePagePermissions,
} from '@/features/lemiex/layout/sidebar-data'
import { cn } from '@/lib/utils'
import { fetchCurrentUser } from '@/services/auth/api'
import {
  type PermissionRecord,
  type PermissionRole,
  createPermission,
  fetchPermissionMatrix,
  updateRolePermissions,
} from '@/services/permissions/api'
import { useAuthStore } from '@/stores/auth-store'

// Roles that bypass permission middleware on backend → no need to manage UI access for them
const AUTO_FULL_ACCESS_ROLES = new Set(['Admin', 'HR'])

// Per-role page-permission state, keyed by role ID
type RolePermissionState = Record<number, string[]>

const FALLBACK_MESSAGES = {
  vi: {
    title: 'Phân quyền trang',
    subtitle: 'Tích hoặc bỏ tích từng page để áp quyền truy cập theo vai trò.',
    adminNotice: 'Chỉ Admin mới thấy và chỉnh được màn này. Vai trò khác sẽ nhận cấu hình đã lưu.',
    reset: 'Khôi phục mặc định',
    save: 'Lưu cấu hình',
    saving: 'Đang lưu...',
    loading: 'Đang tải cấu hình phân quyền trang...',
    saved: 'Đã lưu cấu hình quyền truy cập trang',
    resetDone: 'Đã khôi phục cấu hình mặc định',
    page: 'Menu / Page',
    fullAccess: 'Toàn quyền',
    empty: 'Không có page nào để cấu hình',
    initError: 'Không thể khởi tạo quyền truy cập trang',
  },
  en: {
    title: 'Page access',
    subtitle: 'Tick or untick each page to manage access by role.',
    adminNotice: 'Only Admin can access and edit this screen. Other roles will receive the saved setup.',
    reset: 'Reset defaults',
    save: 'Save access',
    saving: 'Saving...',
    loading: 'Loading page access configuration...',
    saved: 'Page access configuration saved',
    resetDone: 'Default access configuration restored',
    page: 'Menu / Page',
    fullAccess: 'Full access',
    empty: 'No pages available for configuration',
    initError: 'Failed to initialize page access permissions',
  },
} as const

function flattenTree(nodes: PageAccessTreeNode[]) {
  const map = new Map<string, PageAccessTreeNode>()

  function walk(node: PageAccessTreeNode) {
    map.set(node.id, node)
    node.children?.forEach(walk)
  }

  nodes.forEach(walk)
  return map
}

function getPageAccessPermissionName(url?: string) {
  if (!url) return null
  return PAGE_ACCESS_PERMISSION_BY_PATH[url] || null
}

function collectNodePermissionNames(node: PageAccessTreeNode): string[] {
  const selfPermission = getPageAccessPermissionName(node.url)
  const childPermissions = (node.children || []).flatMap((child) =>
    collectNodePermissionNames(child)
  )

  return Array.from(new Set([...(selfPermission ? [selfPermission] : []), ...childPermissions]))
}

function hasAllPermissions(current: string[], permissionNames: string[]) {
  return permissionNames.every((permissionName) => current.includes(permissionName))
}

function togglePermissionNames(current: string[], permissionNames: string[], checked: boolean) {
  if (checked) {
    return Array.from(new Set([...current, ...permissionNames]))
  }

  return current.filter((permissionName) => !permissionNames.includes(permissionName))
}

function buildPagePermissionDefinition(path: string, title: string) {
  const permissionName = PAGE_ACCESS_PERMISSION_BY_PATH[path]

  return {
    name: permissionName,
    display_name: title,
    description: `Page access for ${title}`,
    group: PAGE_ACCESS_GROUP_NAME,
    route: path,
    method: 'GET',
  }
}

type ManageableRole = {
  id: number
  name: string
  displayName: string
}

function PageAccessRow({
  node,
  depth,
  permissions,
  manageableRoles,
  onToggleRole,
  expanded,
  onToggleExpand,
  adminAccessLabel,
  autoFullAccessRoleIds,
  gridTemplate,
}: {
  node: PageAccessTreeNode
  depth: number
  permissions: RolePermissionState
  manageableRoles: ManageableRole[]
  onToggleRole: (roleId: number, permissionNames: string[], checked: boolean) => void
  expanded: Record<string, boolean>
  onToggleExpand: (nodeId: string) => void
  adminAccessLabel: string
  autoFullAccessRoleIds: Set<number>
  gridTemplate: string
}) {
  const hasChildren = Boolean(node.children?.length)
  const isExpanded = expanded[node.id] ?? true
  const permissionNames = collectNodePermissionNames(node)

  return (
    <>
      <div
        className='grid min-h-12 items-center border-b border-border/60 bg-background'
        style={{ gridTemplateColumns: gridTemplate }}
      >
        <div
          className='sticky left-0 z-10 flex items-center gap-2 border-r border-border/60 bg-background px-4 py-3'
          style={{ paddingLeft: `${16 + depth * 18}px` }}
        >
          {hasChildren ? (
            <button
              type='button'
              onClick={() => onToggleExpand(node.id)}
              className='inline-flex size-6 items-center justify-center rounded-[6px] border border-border/70 bg-background text-muted-foreground transition hover:text-foreground'
            >
              <ChevronDown
                className={cn('size-4 transition-transform', !isExpanded && '-rotate-90')}
              />
            </button>
          ) : (
            <span className='inline-flex size-6 items-center justify-center text-muted-foreground'>
              <ShieldCheck className='size-4' />
            </span>
          )}

          <div className='min-w-0'>
            <div className={cn('truncate', hasChildren ? 'font-semibold' : 'text-sm font-medium')}>
              {node.title}
            </div>
            {node.url ? (
              <div className='truncate text-xs text-muted-foreground'>{node.url}</div>
            ) : null}
          </div>
        </div>

        {manageableRoles.map((role) => {
          const isAutoFull = autoFullAccessRoleIds.has(role.id)
          return (
            <div
              key={`${node.id}-${role.id}`}
              className='flex items-center justify-center px-3 py-3'
            >
              {isAutoFull ? (
                <span className='text-xs font-semibold text-emerald-600'>
                  {adminAccessLabel}
                </span>
              ) : permissionNames.length > 0 ? (
                <Checkbox
                  checked={hasAllPermissions(permissions[role.id] || [], permissionNames)}
                  onCheckedChange={(checked) =>
                    onToggleRole(role.id, permissionNames, Boolean(checked))
                  }
                />
              ) : (
                <span className='text-muted-foreground'>-</span>
              )}
            </div>
          )
        })}
      </div>

      {hasChildren && isExpanded
        ? node.children?.map((child) => (
            <PageAccessRow
              key={child.id}
              node={child}
              depth={depth + 1}
              permissions={permissions}
              manageableRoles={manageableRoles}
              onToggleRole={onToggleRole}
              expanded={expanded}
              onToggleExpand={onToggleExpand}
              adminAccessLabel={adminAccessLabel}
              autoFullAccessRoleIds={autoFullAccessRoleIds}
              gridTemplate={gridTemplate}
            />
          ))
        : null}
    </>
  )
}

export function LemiexPermissionsSidebarPage() {
  const router = useRouter()
  const { locale } = useI18n()
  const m = FALLBACK_MESSAGES[locale]
  const user = useAuthStore((state) => state.auth.user)
  const accessToken = useAuthStore((state) => state.auth.accessToken)
  const setUser = useAuthStore((state) => state.auth.setUser)
  const resetAuth = useAuthStore((state) => state.auth.reset)
  const role = getLemiexRole(user?.role)
  const isAdmin = role === 'Admin'
  const pageTree = useMemo(() => getLemiexPageAccessTree(locale), [locale])
  const treeMap = useMemo(() => flattenTree(pageTree), [pageTree])
  const defaultRolePermissions = useMemo(() => getDefaultRolePagePermissions(), [])
  const [manageableRoles, setManageableRoles] = useState<ManageableRole[]>([])
  const [autoFullAccessRoleIds, setAutoFullAccessRoleIds] = useState<Set<number>>(new Set())
  const [permissions, setPermissions] = useState<RolePermissionState>({})
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [expanded, setExpanded] = useState<Record<string, boolean>>(() => {
    const next: Record<string, boolean> = {}
    pageTree.forEach((group) => {
      next[group.id] = true
      group.children?.forEach((child) => {
        next[child.id] = true
      })
    })
    return next
  })

  const gridTemplate = useMemo(
    () => `minmax(320px, 1fr) repeat(${Math.max(manageableRoles.length, 1)}, 132px)`,
    [manageableRoles.length]
  )

  const buildRolesAndState = useCallback(
    (
      permissionsList: PermissionRecord[],
      roles: PermissionRole[],
      matrix: Record<string, { permissions?: number[] }>
    ) => {
      const pagePermissions = permissionsList.filter(
        (permission) =>
          permission.group === PAGE_ACCESS_GROUP_NAME ||
          (permission.name && Object.values(PAGE_ACCESS_PERMISSION_BY_PATH).includes(permission.name))
      )
      const pagePermissionIdSet = new Set(pagePermissions.map((permission) => permission.id))
      const permissionNameById = new Map(
        pagePermissions
          .filter((permission): permission is PermissionRecord & { name: string } => Boolean(permission.name))
          .map((permission) => [permission.id, permission.name as string])
      )

      // Sort: built-in non-bypass roles first, then custom roles, then auto-bypass at end
      const sorted = [...roles].sort((a, b) => {
        const aAuto = AUTO_FULL_ACCESS_ROLES.has(a.name || '')
        const bAuto = AUTO_FULL_ACCESS_ROLES.has(b.name || '')
        if (aAuto !== bAuto) return aAuto ? 1 : -1
        return (a.id || 0) - (b.id || 0)
      })

      const nextRoles: ManageableRole[] = sorted
        .filter((r) => Boolean(r.id))
        .map((r) => ({
          id: r.id,
          name: r.name || `role-${r.id}`,
          displayName: r.display_name || r.name || `Role #${r.id}`,
        }))

      const nextAutoFull = new Set(
        nextRoles.filter((r) => AUTO_FULL_ACCESS_ROLES.has(r.name)).map((r) => r.id)
      )

      const nextPermissions: RolePermissionState = {}
      nextRoles.forEach((r) => {
        const assignedIds = matrix[String(r.id)]?.permissions || []
        nextPermissions[r.id] = assignedIds
          .filter((permissionId) => pagePermissionIdSet.has(permissionId))
          .map((permissionId) => permissionNameById.get(permissionId) || '')
          .filter(Boolean)
      })

      setManageableRoles(nextRoles)
      setAutoFullAccessRoleIds(nextAutoFull)
      setPermissions(nextPermissions)
    },
    []
  )

  const ensurePagePermissionsAndDefaults = useCallback(async () => {
    let matrixData = await fetchPermissionMatrix()
    let permissionsList = matrixData.permissions || []

    // Auto-create missing page-access permissions in DB
    const missingDefinitions = Object.entries(PAGE_ACCESS_PERMISSION_BY_PATH)
      .filter(([, permissionName]) => !permissionsList.some((permission) => permission.name === permissionName))
      .map(([path]) => {
        const node = treeMap.get(path)
        return buildPagePermissionDefinition(path, node?.title || path)
      })

    if (missingDefinitions.length > 0) {
      for (const definition of missingDefinitions) {
        await createPermission(definition)
      }
      matrixData = await fetchPermissionMatrix()
      permissionsList = matrixData.permissions || []
    }

    // Apply built-in default page permissions for roles that have NO page perms yet
    const pagePermissions = permissionsList.filter(
      (permission) =>
        permission.group === PAGE_ACCESS_GROUP_NAME ||
        (permission.name && Object.values(PAGE_ACCESS_PERMISSION_BY_PATH).includes(permission.name))
    )
    const pagePermissionIds = new Set(pagePermissions.map((permission) => permission.id))
    const permissionIdByName = new Map(
      pagePermissions
        .filter((permission): permission is PermissionRecord & { name: string } => Boolean(permission.name))
        .map((permission) => [permission.name as string, permission.id])
    )

    const initializationUpdates: Array<{ roleId: number; permissionIds: number[] }> = []
    for (const roleRecord of matrixData.roles || []) {
      if (!roleRecord.id) continue
      if (AUTO_FULL_ACCESS_ROLES.has(roleRecord.name || '')) continue

      const currentIds = matrixData.matrix?.[String(roleRecord.id)]?.permissions || []
      const currentPageIds = currentIds.filter((permissionId) => pagePermissionIds.has(permissionId))
      if (currentPageIds.length > 0) continue // already initialized

      // Only apply defaults when role.name exactly matches a built-in LemiexRole key,
      // otherwise (custom roles created via UI) start empty.
      const roleName = roleRecord.name || ''
      const defaultNames = (roleName in defaultRolePermissions)
        ? defaultRolePermissions[roleName as keyof typeof defaultRolePermissions]
        : []
      if (defaultNames.length === 0) continue

      const defaultIds = defaultNames
        .map((permissionName) => permissionIdByName.get(permissionName))
        .filter((permissionId): permissionId is number => typeof permissionId === 'number')

      if (defaultIds.length === 0) continue

      const mergedIds = Array.from(new Set([...currentIds, ...defaultIds]))
      if (mergedIds.length === currentIds.length) continue

      initializationUpdates.push({ roleId: roleRecord.id, permissionIds: mergedIds })
    }

    if (initializationUpdates.length > 0) {
      for (const update of initializationUpdates) {
        await updateRolePermissions(update.roleId, update.permissionIds)
      }
      matrixData = await fetchPermissionMatrix()
      permissionsList = matrixData.permissions || []
    }

    buildRolesAndState(
      permissionsList,
      matrixData.roles || [],
      matrixData.matrix || {}
    )
  }, [defaultRolePermissions, buildRolesAndState, treeMap])

  useEffect(() => {
    if (!accessToken) {
      router.replace('/login?redirect=/lemiex/systems/permissions-sidebar')
      return
    }

    let active = true

    async function load() {
      try {
        setLoading(true)

        let resolvedUser = user
        if (!resolvedUser) {
          const currentUserResponse = await fetchCurrentUser()

          if (!currentUserResponse.success || !currentUserResponse.user) {
            resetAuth()
            router.replace('/login?redirect=/lemiex/systems/permissions-sidebar')
            return
          }

          resolvedUser = currentUserResponse.user
          if (active) {
            setUser(currentUserResponse.user)
          }
        }

        if (getLemiexRole(resolvedUser?.role) !== 'Admin') {
          router.replace('/lemiex/dashboard')
          return
        }

        await ensurePagePermissionsAndDefaults()
      } catch (error) {
        const message = error instanceof Error ? error.message : m.initError
        toast.error(message)
      } finally {
        if (active) setLoading(false)
      }
    }

    void load()

    return () => {
      active = false
    }
  }, [accessToken, ensurePagePermissionsAndDefaults, m.initError, resetAuth, router, setUser, user])

  function handleToggleRole(roleId: number, permissionNames: string[], checked: boolean) {
    setPermissions((prev) => ({
      ...prev,
      [roleId]: togglePermissionNames(prev[roleId] || [], permissionNames, checked),
    }))
  }

  function handleToggleExpand(nodeId: string) {
    setExpanded((prev) => ({
      ...prev,
      [nodeId]: !prev[nodeId],
    }))
  }

  async function handleSave() {
    if (!isAdmin) {
      router.replace('/lemiex/dashboard')
      return
    }

    try {
      setSaving(true)

      const matrixData = await fetchPermissionMatrix()
      const permissionsList = matrixData.permissions || []
      const pagePermissions = permissionsList.filter(
        (permission) =>
          permission.group === PAGE_ACCESS_GROUP_NAME ||
          (permission.name && Object.values(PAGE_ACCESS_PERMISSION_BY_PATH).includes(permission.name))
      )
      const pagePermissionIdsSet = new Set(pagePermissions.map((permission) => permission.id))
      const permissionIdByName = new Map(
        pagePermissions
          .filter((permission): permission is PermissionRecord & { name: string } => Boolean(permission.name))
          .map((permission) => [permission.name as string, permission.id])
      )

      for (const role of manageableRoles) {
        if (autoFullAccessRoleIds.has(role.id)) continue

        const existingIds = matrixData.matrix?.[String(role.id)]?.permissions || []
        const selectedIds = (permissions[role.id] || [])
          .map((permissionName) => permissionIdByName.get(permissionName))
          .filter((permissionId): permissionId is number => typeof permissionId === 'number')
        const mergedIds = Array.from(
          new Set([
            ...existingIds.filter((permissionId) => !pagePermissionIdsSet.has(permissionId)),
            ...selectedIds,
          ])
        )

        await updateRolePermissions(role.id, mergedIds)
      }

      await ensurePagePermissionsAndDefaults()
      toast.success(m.saved)
    } catch (error) {
      const message = error instanceof Error ? error.message : m.initError
      toast.error(message)
    } finally {
      setSaving(false)
    }
  }

  async function handleReset() {
    if (!isAdmin) {
      router.replace('/lemiex/dashboard')
      return
    }

    setPermissions((prev) => {
      const next: RolePermissionState = { ...prev }
      manageableRoles.forEach((role) => {
        if (autoFullAccessRoleIds.has(role.id)) return
        // For custom roles not in built-in defaults map, reset to empty
        next[role.id] = (role.name in defaultRolePermissions)
          ? [...defaultRolePermissions[role.name as keyof typeof defaultRolePermissions]]
          : []
      })
      return next
    })

    toast.success(m.resetDone)
  }

  if (!accessToken || !isAdmin) return null

  return (
    <>
      <Header fixed>
        <Search />
        <div className='ml-auto flex items-center space-x-4'>
          <LanguageSwitch />
          <ThemeSwitch />
          <ProfileDropdown />
        </div>
      </Header>

      <Main fluid className='space-y-6 px-4 py-6 @7xl/content:px-6'>
        <div className='flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between'>
          <div className='space-y-1'>
            <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
            <p className='text-sm text-muted-foreground'>{m.subtitle}</p>
            <p className='text-xs text-muted-foreground'>{m.adminNotice}</p>
          </div>

          <div className='flex items-center gap-3'>
            <Button
              variant='outline'
              className='h-10 rounded-[6px]'
              onClick={() => void handleReset()}
              disabled={loading || saving}
            >
              <RotateCcw className='mr-2 size-4' />
              {m.reset}
            </Button>
            <Button
              className='h-10 rounded-[6px]'
              onClick={() => void handleSave()}
              disabled={loading || saving}
            >
              {saving ? <Loader2 className='mr-2 size-4 animate-spin' /> : <Save className='mr-2 size-4' />}
              {saving ? m.saving : m.save}
            </Button>
          </div>
        </div>

        {loading ? (
          <div className='flex min-h-[240px] items-center justify-center rounded-[10px] border border-border/80 bg-background'>
            <div className='flex items-center gap-3 text-sm text-muted-foreground'>
              <Loader2 className='size-4 animate-spin' />
              {m.loading}
            </div>
          </div>
        ) : pageTree.length === 0 || manageableRoles.length === 0 ? (
          <div className='py-16 text-center text-sm text-muted-foreground'>{m.empty}</div>
        ) : (
          <div className='w-full overflow-x-auto whitespace-nowrap rounded-[10px] border border-border/80 bg-background'>
            <div style={{ minWidth: `${320 + manageableRoles.length * 132}px` }}>
              <div
                className='grid border-b border-border/80 bg-muted/40'
                style={{ gridTemplateColumns: gridTemplate }}
              >
                <div className='sticky left-0 z-20 border-r border-border/80 bg-muted px-4 py-3 text-sm font-semibold'>
                  {m.page}
                </div>
                {manageableRoles.map((role) => (
                  <div
                    key={`head-${role.id}`}
                    className='px-3 py-3 text-center text-sm font-semibold'
                    title={role.name}
                  >
                    {role.displayName}
                  </div>
                ))}
              </div>

              {pageTree.map((group) => (
                <PageAccessRow
                  key={group.id}
                  node={group}
                  depth={0}
                  permissions={permissions}
                  manageableRoles={manageableRoles}
                  onToggleRole={handleToggleRole}
                  expanded={expanded}
                  onToggleExpand={handleToggleExpand}
                  adminAccessLabel={m.fullAccess}
                  autoFullAccessRoleIds={autoFullAccessRoleIds}
                  gridTemplate={gridTemplate}
                />
              ))}
            </div>
          </div>
        )}
      </Main>
    </>
  )
}
