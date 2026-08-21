import { defineAdminModule } from '@peanut-admin/admin/core'

export const exampleTargetModule = defineAdminModule({
  key: 'example.target',
  routes: [{
    path: '/app/examples/targets',
    name: 'example-target-list',
    component: () => import('./pages/TargetListPage.vue'),
    access: {
      moduleKey: 'example.target',
      permissionKeys: ['example.target.read'],
    },
  }],
  disposeOnTenantChange: true,
})
