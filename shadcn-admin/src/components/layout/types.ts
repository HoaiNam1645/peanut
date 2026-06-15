import { type LinkProps } from '@/lib/router'

type User = {
  name: string
  email: string
  avatar: string
}

type TeamId = 'workspace' | 'lemiex'

type Team = {
  id: TeamId
  name: string
  logo: React.ElementType
  plan: string
  defaultUrl: string
}

type BaseNavItem = {
  title: string
  badge?: string
  icon?: React.ElementType
}

type NavLink = BaseNavItem & {
  url: LinkProps['to'] | (string & {})
  items?: never
}

type NavCollapsible = BaseNavItem & {
  items: (BaseNavItem & { url: LinkProps['to'] | (string & {}) })[]
  url?: never
}

type NavItem = NavCollapsible | NavLink

type NavGroup = {
  title: string
  items: NavItem[]
}

type SidebarData = {
  user: User
  teams: Team[]
  navGroups: NavGroup[]
}

export type {
  SidebarData,
  Team,
  TeamId,
  NavGroup,
  NavItem,
  NavCollapsible,
  NavLink,
}
