'use client'

import { Area, AreaChart, ResponsiveContainer, XAxis, YAxis } from 'recharts'

const data = [
  {
    name: 'Mon',
    clicks: 540,
    uniques: 390,
  },
  {
    name: 'Tue',
    clicks: 620,
    uniques: 455,
  },
  {
    name: 'Wed',
    clicks: 710,
    uniques: 520,
  },
  {
    name: 'Thu',
    clicks: 685,
    uniques: 498,
  },
  {
    name: 'Fri',
    clicks: 770,
    uniques: 560,
  },
  {
    name: 'Sat',
    clicks: 430,
    uniques: 300,
  },
  {
    name: 'Sun',
    clicks: 510,
    uniques: 365,
  },
]

export function AnalyticsChart() {
  return (
    <ResponsiveContainer width='100%' height={300}>
      <AreaChart data={data}>
        <XAxis
          dataKey='name'
          stroke='#888888'
          fontSize={12}
          tickLine={false}
          axisLine={false}
        />
        <YAxis
          stroke='#888888'
          fontSize={12}
          tickLine={false}
          axisLine={false}
        />
        <Area
          type='monotone'
          dataKey='clicks'
          stroke='currentColor'
          className='text-primary'
          fill='currentColor'
          fillOpacity={0.15}
        />
        <Area
          type='monotone'
          dataKey='uniques'
          stroke='currentColor'
          className='text-muted-foreground'
          fill='currentColor'
          fillOpacity={0.1}
        />
      </AreaChart>
    </ResponsiveContainer>
  )
}
