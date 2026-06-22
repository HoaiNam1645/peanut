'use client'

import { useMemo } from 'react'
import { useLayout } from '@/context/layout-provider'
import { useI18n } from '@/context/i18n-provider'
import { useAuthStore } from '@/stores/auth-store'
import { extractPermissionNames, getLemiexRole } from '@/features/lemiex/layout/sidebar-data'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from '@/components/ui/sidebar'
import { getSidebarNavGroups, getSidebarUser } from './data/sidebar-data'
import { NavGroup } from './nav-group'
import { LemiexSidebarQuickAccess } from './lemiex-sidebar-quick-access'
import { NavUser } from './nav-user'

export function AppSidebar() {
  const { collapsible, variant } = useLayout()
  const { locale } = useI18n()
  const user = useAuthStore((state) => state.auth.user)
  const accessToken = useAuthStore((state) => state.auth.accessToken)
  const serverChecked = useAuthStore((state) => state.auth.serverChecked)
  const lemiexRole = getLemiexRole(user?.role)
  const permissionNames = useMemo(() => {
    if (accessToken && !serverChecked) return []
    return extractPermissionNames(user?.role)
  }, [accessToken, serverChecked, user?.role])

  const navGroups = getSidebarNavGroups('lemiex', locale, lemiexRole, permissionNames)
  const sidebarUser = getSidebarUser('lemiex')

  return (
    <Sidebar collapsible={collapsible} variant={variant}>
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size='lg' className='cursor-default select-none hover:bg-transparent active:bg-transparent'>
              <div className='aspect-square size-8 overflow-hidden rounded-lg'>
                <img
                  src='https://res.cloudinary.com/dk9oqs34g/image/upload/v1782121289/2026-06-19_11.41.04_cgactp.jpg'
                  alt='THEUNIV'
                  className='size-full object-cover'
                />
              </div>
              <div className='flex flex-col gap-0.5 leading-none'>
                <span className='font-semibold'>Không gian THEUNIV</span>
                <span className='text-[11px] text-muted-foreground'>Sidebar theo vai trò</span>
              </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent>
        <LemiexSidebarQuickAccess />
        {navGroups.map((props) => (
          <NavGroup key={props.title} {...props} />
        ))}
      </SidebarContent>
      <SidebarFooter>
        <NavUser user={sidebarUser} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
