import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: { public: true }
  },
  {
    path: '/',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/dashboard'
      },
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/DashboardView.vue'),
        meta: { title: 'Dashboard' }
      },
      {
        path: 'reimbursements',
        name: 'Reimbursements',
        component: () => import('@/views/ReimbursementsView.vue'),
        meta: { title: 'Reimbursements' }
      },
      {
        path: 'reimbursements/new',
        name: 'ReimbursementForm',
        component: () => import('@/views/ReimbursementFormView.vue'),
        meta: { title: 'New Reimbursement' }
      },
      {
        path: 'cash-advances',
        name: 'CashAdvances',
        component: () => import('@/views/CashAdvancesView.vue'),
        meta: { title: 'Cash Advances' }
      },
      {
        path: 'liquidations',
        name: 'Liquidations',
        component: () => import('@/views/LiquidationsView.vue'),
        meta: { title: 'Liquidations' }
      },
      {
        path: 'receipts',
        name: 'Receipts',
        component: () => import('@/views/ReceiptRepositoryView.vue'),
        meta: { title: 'Receipt Repository' }
      },
      {
        path: 'admin/policy',
        name: 'Policy',
        component: () => import('@/views/admin/PolicyView.vue'),
        meta: { title: 'Policy Management', roles: ['admin'] }
      },
      {
        path: 'admin/audit',
        name: 'Audit',
        component: () => import('@/views/admin/AuditView.vue'),
        meta: { title: 'Audit Log', roles: ['admin'] }
      },
      {
        path: 'admin/reports',
        name: 'Reports',
        component: () => import('@/views/admin/ReportsView.vue'),
        meta: { title: 'Reports & Exports', roles: ['admin'] }
      }
    ]
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 })
})

// Navigation guard
router.beforeEach((to) => {
  const auth = useAuthStore()
  if (!to.meta.public && !auth.isAuthenticated) {
    return { name: 'Login', query: { redirect: to.fullPath } }
  }
  if (to.meta.roles && !to.meta.roles.includes(auth.user?.role)) {
    return { name: 'Dashboard' }
  }
  if (to.name === 'Login' && auth.isAuthenticated) {
    return { name: 'Dashboard' }
  }
})

export default router
