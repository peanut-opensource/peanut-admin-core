import { defineAdminModule } from '@peanut-admin/admin/core'

export const exampleReferenceModule = defineAdminModule({
  key: 'example.reference',
  routes: [{
    path: '/app/examples/references',
    name: 'example-reference-list',
    component: () => import('./pages/ReferenceListPage.vue'),
    access: {
      moduleKey: 'example.reference',
      permissionKeys: ['example.reference.read', 'example.reference.use'],
    },
  }],
  disposeOnTenantChange: true,
})
