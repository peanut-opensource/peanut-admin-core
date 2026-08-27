import { defineConfig } from 'vitepress'

const start = [
  { text: 'Documentation Home', link: '/' },
  { text: 'Developer Guide', link: '/guide/' },
  { text: 'Core Concepts', link: '/core-concepts/' },
]

const develop = [
  { text: 'Installation', link: '/guide/installation' },
  { text: 'Authentication', link: '/guide/authentication' },
  { text: 'Authorization', link: '/guide/authorization' },
  { text: 'Module Development', link: '/guide/module-development' },
  { text: 'Admin Web', link: '/guide/admin-web' },
  { text: 'Testing', link: '/guide/testing' },
  { text: 'Upgrade', link: '/guide/upgrade' },
  { text: 'Troubleshooting', link: '/guide/troubleshooting' },
]

const reference = [
  { text: 'API Contract', link: '/api/' },
  { text: 'Kernel Schema', link: '/reference/kernel-schema' },
  { text: 'Typed Targets', link: '/reference/typed-targets' },
  { text: 'Package Reference', link: '/reference/packages/data-permission' },
  { text: 'Document Catalog', link: '/reference/document-catalog.generated' },
]

export default defineConfig({
  title: 'Peanut Admin Core',
  description: 'Product-neutral multi-tenant administration contracts, packages, and developer guidance.',
  lang: 'en-US',
  base: '/peanut-admin/',
  cleanUrls: true,
  lastUpdated: true,
  ignoreDeadLinks: false,
  head: [['meta', { name: 'theme-color', content: '#176b4d' }]],
  sitemap: { hostname: 'https://peanut-opensource.github.io/peanut-admin/' },
  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/' },
      { text: 'Concepts', link: '/core-concepts/' },
      { text: 'Architecture', link: '/architecture/' },
      { text: 'Reference', items: reference },
      { text: 'Governance', link: '/governance/authoritative-source-map' },
    ],
    sidebar: [
      { text: 'Start', items: start },
      { text: 'Develop', items: develop },
      { text: 'Reference', items: reference },
      {
        text: 'Architecture and Governance',
        items: [
          { text: 'Architecture', link: '/architecture/' },
          { text: 'Engineering Standards', link: '/standards/' },
          { text: 'Authoritative Sources', link: '/governance/authoritative-source-map' },
          { text: 'Document Lifecycle', link: '/governance/document-lifecycle' },
          { text: 'Documentation Impact', link: '/governance/docs-impact' },
        ],
      },
    ],
    search: { provider: 'local' },
    socialLinks: [{ icon: 'github', link: 'https://github.com/peanut-opensource/peanut-admin' }],
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
