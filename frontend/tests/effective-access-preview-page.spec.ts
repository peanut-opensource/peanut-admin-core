// @vitest-environment happy-dom

import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import {
  ElButton,
  ElDescriptions,
  ElDescriptionsItem,
  ElPagination,
  ElSkeleton,
  ElTable,
  ElTableColumn,
  ElTag,
} from 'element-plus'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import EffectiveAccessPreviewPage from '../src/pages/common/EffectiveAccessPreviewPage.vue'

const mocks = vi.hoisted(() => {
  class ApiError extends Error {
    public constructor(public readonly problem: {
      status: number
      detail: string
      request_id: string
    }) {
      super('ADMIN_API_ERROR')
    }
  }

  return {
    ApiError,
    get: vi.fn(),
    push: vi.fn(),
    route: { params: { member_id: '9007199254740993123' } },
    unwrap: vi.fn((result: { payload?: unknown }) => result.payload),
  }
})

vi.mock('../src/app/runtime', () => ({
  AdminApiError: mocks.ApiError,
  useAdminRuntime: () => ({
    tenantClient: { GET: mocks.get },
    unwrap: mocks.unwrap,
  }),
}))

vi.mock('vue-router', async () => {
  const { reactive } = await import('vue')
  mocks.route = reactive(mocks.route)

  return {
    useRoute: () => mocks.route,
    useRouter: () => ({ push: mocks.push }),
  }
})

const preview = (overrides: Record<string, unknown> = {}) => ({
  data: {
    preview_kind: 'authorization_inputs',
    evaluated_at: '2026-07-19T09:30:00.000Z',
    snapshot_revision: 'a'.repeat(64),
    member: {
      id: '9007199254740993123',
      display_name: 'Preview Member',
      status: 'active',
      primary_department_id: '11',
      effective: true,
    },
    roles: [{ id: '21', key: 'core.tenant-owner', name: 'Tenant Owner', is_builtin: true }],
    permission_keys: [
      'core.member.effective-access.read',
      'example.work-item.authorization-preview-with-a-very-long-permission-key.read',
    ],
    resource_operations: [{
      resource_key: 'example.authorization-resource-with-a-very-long-resource-key',
      module_key: 'example.work-item',
      operation: 'list',
      ownership: 'business_target_owned',
      access_mode: 'explicit_targets',
      target_cardinality: 'many_readable',
      permission_match: 'all',
      required_permission_keys: ['example.work-item.read'],
      functional_allowed: true,
      data_access: {
        mode: 'conditional',
        runtime_decision_required: true,
        group_match: 'any',
        groups: [{
          source_role_key: 'core.tenant-owner',
          condition_match: 'all',
          conditions: [{
            condition_key: 'core.specified_objects',
            target_resource_key: 'example.project',
            target_count: 2,
          }],
        }],
      },
    }],
    ...overrides,
  },
  meta: { request_id: 'req_preview', page: 1, page_size: 20, total: 21, total_pages: 2 },
})

const mountPage = () => mount(EffectiveAccessPreviewPage, {
  global: {
    components: {
      ElButton,
      ElDescriptions,
      ElDescriptionsItem,
      ElPagination,
      ElSkeleton,
      ElTable,
      ElTableColumn,
      ElTag,
    },
  },
})

enableAutoUnmount(afterEach)

describe('EffectiveAccessPreviewPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mocks.route.params.member_id = '9007199254740993123'
    Object.defineProperty(window, 'innerWidth', { configurable: true, value: 1024 })
    mocks.get.mockResolvedValue({ payload: preview() })
  })

  it('renders the single authoritative snapshot without secondary authorization requests', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(mocks.get).toHaveBeenCalledTimes(1)
    expect(mocks.get).toHaveBeenCalledWith('/api/v1/members/{member_id}/effective-access', {
      params: {
        path: { member_id: '9007199254740993123' },
        query: { page: 1, page_size: 20 },
      },
    })
    expect(wrapper.text()).toContain('Preview Member')
    expect(wrapper.text()).toContain('core.tenant-owner')
    expect(wrapper.text()).toContain('core.member.effective-access.read')
    expect(wrapper.text()).toContain('example.authorization-resource-with-a-very-long-resource-key')
    expect(wrapper.text()).toContain('core.specified_objects')
    expect(wrapper.text()).toContain('仍需运行时判定')
  })

  it('shows loading and allows an explicit refresh', async () => {
    let resolveRequest!: (value: { payload: unknown }) => void
    mocks.get.mockReturnValueOnce(new Promise(resolve => {
      resolveRequest = resolve
    }))

    const wrapper = mountPage()
    await flushPromises()
    expect(wrapper.findComponent(ElSkeleton).exists()).toBe(true)

    resolveRequest({ payload: preview() })
    await flushPromises()
    expect(wrapper.findComponent(ElSkeleton).exists()).toBe(false)

    const refresh = wrapper.findAllComponents(ElButton).find(button => button.text() === '刷新')
    expect(refresh).toBeDefined()
    await refresh?.trigger('click')
    await flushPromises()
    expect(mocks.get).toHaveBeenCalledTimes(2)
  })

  it('requests the selected operation page from the same preview endpoint', async () => {
    const wrapper = mountPage()
    await flushPromises()

    await wrapper.getComponent(ElPagination).vm.$emit('current-change', 2)
    await flushPromises()

    expect(mocks.get).toHaveBeenLastCalledWith('/api/v1/members/{member_id}/effective-access', {
      params: {
        path: { member_id: '9007199254740993123' },
        query: { page: 2, page_size: 20 },
      },
    })
  })

  it('discards an older member response after the route is reused for another member', async () => {
    let resolveFirst!: (value: { payload: unknown }) => void
    mocks.get.mockImplementation((_path, options) => {
      const memberId = options.params.path.member_id
      if (memberId === '9007199254740993123') {
        return new Promise<{ payload: unknown }>(resolve => {
          resolveFirst = resolve
        })
      }

      return Promise.resolve({ payload: preview({
        member: {
          id: memberId,
          display_name: 'Routed Member',
          status: 'active',
          primary_department_id: null,
          effective: true,
        },
      }) })
    })

    const wrapper = mountPage()
    await flushPromises()
    mocks.route.params.member_id = '9007199254740993124'
    await flushPromises()

    expect(mocks.get).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Routed Member')

    resolveFirst({ payload: preview() })
    await flushPromises()
    expect(wrapper.text()).toContain('Routed Member')
    expect(wrapper.text()).not.toContain('Preview Member')
  })

  it('keeps inactive and empty authorization inputs explicit', async () => {
    mocks.get.mockResolvedValue({ payload: {
      ...preview({
        member: {
          id: '9007199254740993123',
          display_name: 'Suspended Member',
          status: 'suspended',
          primary_department_id: null,
          effective: false,
        },
        roles: [],
        permission_keys: [],
        resource_operations: [],
      }),
      meta: { request_id: 'req_empty', page: 1, page_size: 20, total: 0, total_pages: 0 },
    } })

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.text()).toContain('Suspended Member')
    expect(wrapper.text()).toContain('当前成员状态不产生有效访问')
    expect(wrapper.text()).toContain('暂无有效角色')
    expect(wrapper.text()).toContain('暂无有效功能权限')
    expect(wrapper.text()).toContain('暂无可预览的资源操作')
  })

  it('uses a single descriptions column on a compact viewport', async () => {
    Object.defineProperty(window, 'innerWidth', { configurable: true, value: 390 })
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.getComponent(ElDescriptions).props('column')).toBe(1)
  })

  it.each([
    [403, '无权查看该成员的有效访问', 'req_forbidden'],
    [404, '未找到可显示的成员信息', 'req_missing'],
    [500, 'Authorization preview failed.', 'req_failed'],
  ])('renders status %i as an explicit correlated problem', async (status, detail, requestId) => {
    mocks.get.mockRejectedValue(new mocks.ApiError({ status, detail, request_id: requestId }))

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.text()).toContain(detail)
    expect(wrapper.text()).toContain(requestId)
  })
})
