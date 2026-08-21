import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Peanut Admin',
  description: 'A reusable multi-tenant administration foundation for ThinkPHP and Vue.',
  lang: 'en-US',
  base: '/peanut-admin/',
  cleanUrls: true,
  lastUpdated: true,
  ignoreDeadLinks: false,
  head: [
    ['meta', { name: 'theme-color', content: '#176b4d' }],
  ],
  sitemap: {
    hostname: 'https://peanut-opensource.github.io/peanut-admin/',
  },
  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/' },
      { text: 'Concepts', link: '/core-concepts/' },
      { text: 'Architecture', link: '/architecture/' },
      { text: 'Standards', link: '/standards/' },
      { text: 'API', link: '/api/' },
      { text: 'P0 Status', link: '/status/' },
    ],
    sidebar: [
      {
        text: 'Foundation',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Core Concepts', link: '/core-concepts/' },
          { text: 'Architecture', link: '/architecture/' },
        ],
      },
      {
        text: 'Developer Guide',
        items: [
          { text: 'Guide Overview', link: '/guide/' },
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Internal Starter', link: '/guide/internal-starter' },
          { text: 'Authentication', link: '/guide/authentication' },
          { text: 'Authorization', link: '/guide/authorization' },
          { text: 'Module Development', link: '/guide/module-development' },
          { text: 'Admin Web', link: '/guide/admin-web' },
          { text: 'Testing', link: '/guide/testing' },
          { text: 'Upgrade', link: '/guide/upgrade' },
          { text: 'Troubleshooting', link: '/guide/troubleshooting' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'Kernel Schema', link: '/reference/kernel-schema' },
          { text: 'Typed Targets', link: '/reference/typed-targets' },
          { text: 'Data Permission Package', link: '/reference/packages/data-permission' },
          { text: 'Shared Master Scope', link: '/reference/shared-master' },
          { text: 'Security Baseline', link: '/security/p0-baseline' },
          { text: 'ASVS P0 Map', link: '/security/asvs-p0-map' },
          { text: 'Performance Baseline', link: '/performance/p0-baseline' },
          { text: 'Third-Party Licenses', link: '/reference/third-party-licenses.generated' },
          { text: 'Backup Boundary', link: '/operations/backup-and-recovery' },
        ],
      },
      {
        text: 'Engineering',
        items: [
          { text: 'Engineering Standards', link: '/standards/' },
          { text: 'Dependency Policy', link: '/standards/dependency-policy' },
          { text: 'Dependency Decisions', link: '/decisions/dependencies/' },
          { text: 'API Contract', link: '/api/' },
        ],
      },
      {
        text: 'Delivery',
        items: [
          { text: 'P0 Status', link: '/status/' },
          { text: 'P1 Execution Baseline', link: '/status/p1-execution-baseline' },
          { text: 'P1 Module Readiness', link: '/status/p1-downstream-module-readiness-plan' },
          { text: 'P1-W01 Origin Contract', link: '/status/p1-w01-protected-transport-origin-contract' },
        ],
      },
    ],
    search: {
      provider: 'local',
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/peanut-opensource/peanut-admin' },
    ],
    editLink: {
      pattern: 'https://github.com/peanut-opensource/peanut-admin/edit/dev/docs/:path',
      text: 'Edit this page on GitHub',
    },
    footer: {
      message: 'Released under the Apache License 2.0.',
      copyright: 'Peanut Admin contributors',
    },
  },
})
