'use client'

import { Bar, BarChart, ResponsiveContainer, XAxis, YAxis } from 'recharts'

const data = [
  {
    name: 'Jan',
    total: 4520,
  },
  {
    name: 'Feb',
    total: 3380,
  },
  {
    name: 'Mar',
    total: 4180,
  },
  {
    name: 'Apr',
    total: 5010,
  },
  {
    name: 'May',
    total: 2140,
  },
  {
    name: 'Jun',
    total: 1760,
  },
  {
    name: 'Jul',
    total: 5480,
  },
  {
    name: 'Aug',
    total: 4920,
  },
  {
    name: 'Sep',
    total: 2130,
  },
  {
    name: 'Oct',
    total: 4975,
  },
  {
    name: 'Nov',
    total: 1290,
  },
  {
    name: 'Dec',
    total: 1785,
  },
]

export function Overview() {
  return (
    <ResponsiveContainer width='100%' height={350}>
      <BarChart data={data}>
        <XAxis
          dataKey='name'
          stroke='#888888'
          fontSize={12}
          tickLine={false}
          axisLine={false}
        />
        <YAxis
          direction='ltr'
          stroke='#888888'
          fontSize={12}
          tickLine={false}
          axisLine={false}
          tickFormatter={(value) => `$${value}`}
        />
        <Bar
          dataKey='total'
          fill='currentColor'
          radius={[4, 4, 0, 0]}
          className='fill-primary'
        />
      </BarChart>
    </ResponsiveContainer>
  )
}
