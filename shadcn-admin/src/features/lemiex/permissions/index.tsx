'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import { ChevronDown, Loader2, Plus, RefreshCw, Save, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { LanguageSwitch } from '@/components/language-switch'
import { ThemeSwitch } from '@/components/theme-switch'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { useI18n } from '@/context/i18n-provider'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { PAGE_ACCESS_GROUP_NAME } from '@/features/lemiex/layout/sidebar-data'
import { cn } from '@/lib/utils'
import {
  createRole,
  deleteRole,
  fetchPermissionMatrix,
  seedPermissionsFromRoutes,
  updateRolePermissions,
  type PermissionRecord,
  type PermissionRole,
} from '@/services/permissions/api'

type MatrixState = Record<string, number[]>

const fallbackMessages = {
  title: 'Permissions',
  subtitle: 'Manage role-based access control',
  syncPermissions: 'Sync Permissions',
  syncing: 'Syncing...',
  permission: 'Permission',
  save: 'Save',
  saving: 'Saving...',
  adminHasAllPermissions: 'Admin has all permissions',
  savePermissions: 'Save permissions',
  selectAllInGroup: 'Select all in group',
  noPermissions: 'No permissions found',
  loadFailed: 'Failed to load permissions data',
  saveSuccess: 'Permissions saved successfully',
  saveFailed: 'Failed to save permissions',
  syncSuccess: 'Permissions synced successfully',
  syncFailed: 'Failed to sync permissions',
  otherGroup: 'Other',
  createRole: 'Create Role',
  newRoleTitle: 'Create New Role',
  newRoleDescription: 'Add a new role. You can assign permissions after creation.',
  roleName: 'Role Name',
  roleNamePlaceholder: 'e.g. Manager (letters, numbers, underscore)',
  roleDisplayName: 'Display Name',
  roleDisplayNamePlaceholder: 'e.g. Quản lý cấp cao',
  roleDescription: 'Description',
  roleDescriptionPlaceholder: 'Optional description',
  cancel: 'Cancel',
  create: 'Create',
  creating: 'Creating...',
  createSuccess: 'Role created successfully',
  createFailed: 'Failed to create role',
  deleteRole: 'Delete Role',
  confirmDelete: 'Are you sure you want to delete this role?',
  builtInRole: 'Built-in role (cannot delete)',
  deleteSuccess: 'Role deleted successfully',
  deleteFailed: 'Failed to delete role',
}

function buildMatrixFromServer(
  serverMatrix: Record<string, { permissions?: number[] } | undefined> | undefined
): MatrixState {
  const nextMatrix: MatrixState = {}

  Object.entries(serverMatrix || {}).forEach(([roleId, payload]) => {
    nextMatrix[roleId] = payload?.permissions || []
  })

  return nextMatrix
}

export function LemiexPermissionsPage() {
  const { messages } = useI18n()
  const m = messages.permissionsPage ?? fallbackMessages

  const [loading, setLoading] = useState(true)
  const [syncing, setSyncing] = useState(false)
  const [savingRoleId, setSavingRoleId] = useState<number | null>(null)
  const [deletingRoleId, setDeletingRoleId] = useState<number | null>(null)
  const [roles, setRoles] = useState<PermissionRole[]>([])
  const [permissions, setPermissions] = useState<PermissionRecord[]>([])
  const [grouped, setGrouped] = useState<Record<string, PermissionRecord[]>>({})
  const [matrix, setMatrix] = useState<MatrixState>({})
  const [expandedGroups, setExpandedGroups] = useState<Record<string, boolean>>({})

  const [createDialogOpen, setCreateDialogOpen] = useState(false)
  const [creating, setCreating] = useState(false)
  const [newRoleForm, setNewRoleForm] = useState({
    name: '',
    display_name: '',
    description: '',
  })

  const gridTemplateColumns = useMemo(
    () => `280px repeat(${Math.max(roles.length, 1)}, 180px)`,
    [roles.length]
  )

  const loadData = useCallback(async () => {
    try {
      setLoading(true)
      const response = await fetchPermissionMatrix()
      const nextGrouped = Object.fromEntries(
        Object.entries(response.grouped || {}).filter(
          ([groupName]) => groupName !== PAGE_ACCESS_GROUP_NAME
        )
      )
      const visiblePermissionIds = new Set(
        Object.values(nextGrouped)
          .flat()
          .map((permission) => permission.id)
      )

      setRoles(response.roles || [])
      setPermissions((response.permissions || []).filter((permission) => visiblePermissionIds.has(permission.id)))
      setGrouped(nextGrouped)
      setMatrix(buildMatrixFromServer(response.matrix))

      const nextExpanded: Record<string, boolean> = {}
      Object.keys(nextGrouped).forEach((groupName) => {
        nextExpanded[groupName] = true
      })
      setExpandedGroups(nextExpanded)
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.loadFailed)
    } finally {
      setLoading(false)
    }
  }, [m.loadFailed])

  useEffect(() => {
    void loadData()
  }, [loadData])

  function toggleGroup(groupName: string) {
    setExpandedGroups((prev) => ({
      ...prev,
      [groupName]: !prev[groupName],
    }))
  }

  function roleHasPermission(roleId: number, permissionId: number) {
    return matrix[String(roleId)]?.includes(permissionId) || false
  }

  function togglePermission(roleId: number, permissionId: number) {
    setMatrix((prev) => {
      const current = prev[String(roleId)] || []
      const nextPermissions = current.includes(permissionId)
        ? current.filter((id) => id !== permissionId)
        : [...current, permissionId]

      return {
        ...prev,
        [String(roleId)]: nextPermissions,
      }
    })
  }

  function isAllInGroupChecked(roleId: number, groupPermissions: PermissionRecord[]) {
    const current = matrix[String(roleId)] || []
    return groupPermissions.every((permission) => current.includes(permission.id))
  }

  function isSomeInGroupChecked(roleId: number, groupPermissions: PermissionRecord[]) {
    const current = matrix[String(roleId)] || []
    return (
      groupPermissions.some((permission) => current.includes(permission.id)) &&
      !groupPermissions.every((permission) => current.includes(permission.id))
    )
  }

  function toggleAllInGroup(roleId: number, groupPermissions: PermissionRecord[], checked: boolean) {
    setMatrix((prev) => {
      const current = prev[String(roleId)] || []
      const groupIds = groupPermissions.map((permission) => permission.id)

      const nextPermissions = checked
        ? Array.from(new Set([...current, ...groupIds]))
        : current.filter((permissionId) => !groupIds.includes(permissionId))

      return {
        ...prev,
        [String(roleId)]: nextPermissions,
      }
    })
  }

  async function handleSaveRole(role: PermissionRole) {
    if (!role?.id || role.name === 'Admin') return

    try {
      setSavingRoleId(role.id)
      await updateRolePermissions(role.id, matrix[String(role.id)] || [])
      toast.success(m.saveSuccess)
      await loadData()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.saveFailed)
    } finally {
      setSavingRoleId(null)
    }
  }

  async function handleCreateRole() {
    if (!newRoleForm.name.trim() || !newRoleForm.display_name.trim()) return

    try {
      setCreating(true)
      await createRole({
        name: newRoleForm.name.trim(),
        display_name: newRoleForm.display_name.trim(),
        description: newRoleForm.description.trim() || null,
      })
      toast.success(m.createSuccess)
      setCreateDialogOpen(false)
      setNewRoleForm({ name: '', display_name: '', description: '' })
      await loadData()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.createFailed)
    } finally {
      setCreating(false)
    }
  }

  async function handleDeleteRole(role: PermissionRole) {
    if (!role.id || !window.confirm(m.confirmDelete)) return

    try {
      setDeletingRoleId(role.id)
      await deleteRole(role.id)
      toast.success(m.deleteSuccess)
      await loadData()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.deleteFailed)
    } finally {
      setDeletingRoleId(null)
    }
  }

  async function handleSyncPermissions() {
    try {
      setSyncing(true)
      const response = await seedPermissionsFromRoutes()
      toast.success(response.message || m.syncSuccess)
      await loadData()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : m.syncFailed)
    } finally {
      setSyncing(false)
    }
  }

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

      <Main fluid className='px-4 py-6 @7xl/content:px-6'>
        <div className='mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between'>
          <div>
            <h1 className='text-3xl font-semibold tracking-tight'>{m.title}</h1>
          </div>

          <div className='flex items-center gap-2'>
            <Button
              variant='outline'
              className='h-10 rounded-[6px]'
              onClick={() => setCreateDialogOpen(true)}
            >
              <Plus className='mr-2 size-4' />
              {m.createRole}
            </Button>
            <Button
              className='h-10 rounded-[6px]'
              onClick={() => void handleSyncPermissions()}
              disabled={syncing}
            >
              <RefreshCw className={cn('mr-2 size-4', syncing && 'animate-spin')} />
              {syncing ? m.syncing : m.syncPermissions}
            </Button>
          </div>
        </div>

        {loading ? (
          <div className='py-16 text-center text-sm text-muted-foreground'>{m.syncing}</div>
        ) : permissions.length === 0 || roles.length === 0 ? (
          <div className='py-16 text-center text-sm text-muted-foreground'>{m.noPermissions}</div>
        ) : (
          <div className='w-full overflow-x-auto whitespace-nowrap rounded-[8px] border border-border/80 bg-background'>
            <div className='min-w-[980px]'>
              <div
                className='grid border-b border-border/80 bg-muted/40'
                style={{ gridTemplateColumns }}
              >
                <div className='sticky left-0 z-20 border-r border-border/80 bg-muted px-4 py-3 text-sm font-semibold'>
                  {m.permission}
                </div>
                {roles.map((role) => {
                  const isDeletable = role.removable !== false && role.name !== 'Admin'
                  return (
                    <div
                      key={role.id}
                      className='flex flex-col items-center justify-center gap-2 border-l border-border/80 px-3 py-3 text-center'
                    >
                      <span className='text-sm font-semibold'>
                        {role.display_name || role.name || `Role #${role.id}`}
                      </span>
                      <div className='flex items-center gap-1'>
                        <Button
                          variant='outline'
                          size='sm'
                          className='h-8 rounded-[6px] px-3 text-xs'
                          onClick={() => void handleSaveRole(role)}
                          disabled={savingRoleId === role.id || role.name === 'Admin'}
                          title={role.name === 'Admin' ? m.adminHasAllPermissions : m.savePermissions}
                        >
                          <Save className='mr-1 size-3.5' />
                          {savingRoleId === role.id ? m.saving : m.save}
                        </Button>
                        {isDeletable ? (
                          <Button
                            variant='ghost'
                            size='icon'
                            className='size-8 rounded-[6px] text-muted-foreground hover:text-destructive'
                            onClick={() => void handleDeleteRole(role)}
                            disabled={deletingRoleId === role.id}
                            title={m.deleteRole}
                          >
                            {deletingRoleId === role.id ? (
                              <Loader2 className='size-3.5 animate-spin' />
                            ) : (
                              <Trash2 className='size-3.5' />
                            )}
                          </Button>
                        ) : null}
                      </div>
                    </div>
                  )
                })}
              </div>

              {Object.entries(grouped).map(([groupName, groupPermissions]) => (
                <div key={groupName} className='border-b border-border/70 last:border-b-0'>
                  <div className='grid bg-background' style={{ gridTemplateColumns }}>
                    <button
                      type='button'
                      onClick={() => toggleGroup(groupName)}
                      className='sticky left-0 z-10 flex items-center gap-2 border-r border-border/70 bg-background px-4 py-3 text-left'
                    >
                      <ChevronDown
                        className={cn(
                          'size-4 transition-transform',
                          expandedGroups[groupName] ? 'rotate-0' : '-rotate-90'
                        )}
                      />
                      <span className='font-medium'>{groupName || m.otherGroup}</span>
                      <Badge variant='secondary' className='rounded-[6px]'>
                        {groupPermissions.length}
                      </Badge>
                    </button>

                    {roles.map((role) => (
                      <div
                        key={`${groupName}-${role.id}`}
                        className='flex items-center justify-center border-l border-border/70 px-3 py-3'
                      >
                        <Checkbox
                          checked={
                            isAllInGroupChecked(role.id, groupPermissions)
                              ? true
                              : isSomeInGroupChecked(role.id, groupPermissions)
                                ? 'indeterminate'
                                : false
                          }
                          disabled={role.name === 'Admin'}
                          aria-label={`${m.selectAllInGroup} ${role.display_name || role.name}`}
                          onCheckedChange={(checked) =>
                            toggleAllInGroup(role.id, groupPermissions, checked === true)
                          }
                        />
                      </div>
                    ))}
                  </div>

                  {expandedGroups[groupName] && (
                    <div className='bg-background'>
                      {groupPermissions.map((permission) => (
                        <div
                          key={permission.id}
                          className='grid border-t border-border/70'
                          style={{ gridTemplateColumns }}
                        >
                          <div className='sticky left-0 z-10 border-r border-border/70 bg-background px-4 py-3'>
                            <div className='text-sm font-medium'>
                              {permission.display_name || permission.name}
                            </div>
                            <div className='mt-1 text-xs text-muted-foreground'>
                              {permission.name}
                            </div>
                          </div>

                          {roles.map((role) => (
                            <div
                              key={`${permission.id}-${role.id}`}
                              className='flex items-center justify-center border-l border-border/70 px-3 py-3'
                            >
                              <Checkbox
                                checked={
                                  role.name === 'Admin' ||
                                  roleHasPermission(role.id, permission.id)
                                }
                                disabled={role.name === 'Admin'}
                                aria-label={`${permission.display_name || permission.name} - ${role.display_name || role.name}`}
                                onCheckedChange={() => togglePermission(role.id, permission.id)}
                              />
                            </div>
                          ))}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}
      </Main>

      <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
        <DialogContent className='sm:max-w-[480px]'>
          <DialogHeader>
            <DialogTitle>{m.newRoleTitle}</DialogTitle>
            <DialogDescription>{m.newRoleDescription}</DialogDescription>
          </DialogHeader>
          <div className='grid gap-4 py-2'>
            <div className='grid gap-2'>
              <Label htmlFor='role-name'>{m.roleName}</Label>
              <Input
                id='role-name'
                placeholder={m.roleNamePlaceholder}
                value={newRoleForm.name}
                onChange={(e) =>
                  setNewRoleForm((prev) => ({ ...prev, name: e.target.value }))
                }
              />
            </div>
            <div className='grid gap-2'>
              <Label htmlFor='role-display'>{m.roleDisplayName}</Label>
              <Input
                id='role-display'
                placeholder={m.roleDisplayNamePlaceholder}
                value={newRoleForm.display_name}
                onChange={(e) =>
                  setNewRoleForm((prev) => ({ ...prev, display_name: e.target.value }))
                }
              />
            </div>
            <div className='grid gap-2'>
              <Label htmlFor='role-desc'>{m.roleDescription}</Label>
              <Textarea
                id='role-desc'
                placeholder={m.roleDescriptionPlaceholder}
                rows={3}
                value={newRoleForm.description}
                onChange={(e) =>
                  setNewRoleForm((prev) => ({ ...prev, description: e.target.value }))
                }
              />
            </div>
          </div>
          <DialogFooter>
            <Button
              variant='outline'
              onClick={() => setCreateDialogOpen(false)}
              disabled={creating}
            >
              {m.cancel}
            </Button>
            <Button
              onClick={() => void handleCreateRole()}
              disabled={
                creating ||
                !newRoleForm.name.trim() ||
                !newRoleForm.display_name.trim()
              }
            >
              {creating ? (
                <>
                  <Loader2 className='mr-2 size-4 animate-spin' />
                  {m.creating}
                </>
              ) : (
                <>
                  <Plus className='mr-2 size-4' />
                  {m.create}
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

export { LemiexPermissionsPage as LemiexPermissions }
