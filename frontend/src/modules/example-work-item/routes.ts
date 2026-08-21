import { defineAdminModule } from '@peanut-admin/admin/core'

export const exampleWorkItemModule = defineAdminModule({
  key: 'example.work-item',
  routes: [
    {
      path: '/app/examples/work-items',
      name: 'example-work-item-list',
      component: () => import('./pages/WorkItemListPage.vue'),
      access: {
        moduleKey: 'example.work-item',
        permissionKeys: ['example.work-item.read'],
      },
    },
    {
      path: '/app/examples/work-item-policies',
      name: 'example-work-item-policy',
      title: '目标策略发布',
      component: () => import('./pages/WorkItemPolicyPage.vue'),
      access: {
        moduleKey: 'example.work-item',
        permissionKeys: ['example.work-item.policy-publish'],
      },
    },
  ],
  disposeOnTenantChange: true,
})
