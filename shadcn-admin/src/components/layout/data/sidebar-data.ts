import * as React from 'react'
import type { AppLocale } from '@/lib/i18n/types'
import { type NavGroup, type SidebarData, type TeamId, type Team } from '../types'
import {
  getLemiexNavGroups,
  getLemiexRole,
  getLemiexTeam,
} from '@/features/lemiex/layout/sidebar-data'

function getTeams(_locale: AppLocale): Team[] {
  return [getLemiexTeam(_locale)]
}

export const sidebarData: SidebarData = {
  user: {
    name: 'Wecat Admin',
    email: 'wecat@workspace.local',
    avatar: '/avatars/shadcn.jpg',
  },
  teams: getTeams('vi'),
  navGroups: getLemiexNavGroups('vi', 'Admin'),
}

export function getSidebarTeams(locale: AppLocale = 'vi') {
  return getTeams(locale)
}

export function getSidebarNavGroups(
  teamId: TeamId,
  locale: AppLocale = 'vi',
  role: string = 'Admin',
  permissionNames?: string[]
) {
  return getLemiexNavGroups(locale, getLemiexRole(role), permissionNames)
}

export function getSidebarUser(_teamId: TeamId) {
  return {
    name: 'Wecat Admin',
    email: 'wecat@workspace.local',
    avatar: '/avatars/shadcn.jpg',
  }
}
