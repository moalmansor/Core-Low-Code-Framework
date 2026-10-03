export interface DepartmentApi {
  uuid: string
  code: string
  name: string
  names: Record<string, string>
  is_active: boolean
  depth: number
  sort_order: number
  members_count: number
  manager: { uuid: string; name: string } | null
  children: DepartmentApi[]
}

export interface DepartmentNode {
  key: string
  label: string
  data: DepartmentApi
  children: DepartmentNode[]
}

export function departmentNodes(list: DepartmentApi[]): DepartmentNode[] {
  return list.map((d) => ({ key: d.uuid, label: `${d.name} (${d.code})`, data: d, children: departmentNodes(d.children) }))
}
