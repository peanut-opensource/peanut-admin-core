import { defineAdminModule } from '@peanut-admin/admin/core'

export const exampleGreetingModule = defineAdminModule({
  key: 'example.greeting',
  disposeOnTenantChange: true,
  routes: [
    {
      name: 'example.greeting.index',
      path: '/app/example-greeting',
      component: () => import('./page.vue'),
      access: {
        moduleKey: 'example.greeting',
        permissionKeys: ['example.greeting.read'],
      },
    },
  ],
})
