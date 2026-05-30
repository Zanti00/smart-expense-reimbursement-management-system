import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const routes = [
  {
    path: "/auth/callback",
    name: "AuthCallback",
    component: () => import("@/views/AuthCallbackView.vue"),
    meta: { public: true },
  },
  {
    path: "/",
    component: () => import("@/layouts/AppLayout.vue"),
    meta: { requiresAuth: true },
    children: [
      {
        path: "",
        redirect: "/dashboard",
      },
      {
        path: "dashboard",
        name: "Dashboard",
        component: () => import("@/views/DashboardView.vue"),
        meta: { title: "Dashboard" },
      },
      {
        path: "reimbursements",
        name: "Reimbursements",
        component: () => import("@/views/ReimbursementsView.vue"),
        meta: { title: "Reimbursements" },
      },
      {
        path: "reimbursements/new",
        name: "ReimbursementForm",
        component: () => import("@/views/ReimbursementFormView.vue"),
        meta: { title: "New Reimbursement" },
      },
      {
        path: "cash-advances",
        name: "CashAdvances",
        component: () => import("@/views/CashAdvancesView.vue"),
        meta: { title: "Cash Advances" },
      },
      {
        path: "cash-advances/new",
        name: "CashAdvanceForm",
        component: () => import("@/views/CashAdvanceFormView.vue"),
        meta: { title: "New Cash Advance" },
      },
      {
        path: "liquidations",
        name: "Liquidations",
        component: () => import("@/views/LiquidationsView.vue"),
        meta: { title: "Liquidations" },
      },
      {
        path: "receipts",
        name: "Receipts",
        component: () => import("@/views/ReceiptRepositoryView.vue"),
        meta: { title: "Receipt Repository" },
      },
      {
        path: "expense-management",
        name: "ExpenseManagement",
        component: () => import("@/views/ExpenseManagementView.vue"),
        meta: { title: "Expense Management" },
      },
      {
        path: "expense-management/new",
        name: "ExpenseForm",
        component: () => import("@/views/ExpenseFormView.vue"),
        meta: { title: "New Expense" },
      },
      {
        path: "admin/policy",
        name: "Policy",
        component: () => import("@/views/admin/PolicyView.vue"),
        meta: { title: "Policy Management", roles: ["admin"] },
      },
      {
        path: "admin/audit",
        name: "Audit",
        component: () => import("@/views/admin/AuditView.vue"),
        meta: { title: "Audit Log", roles: ["admin"] },
      },
      {
        path: "admin/reports",
        name: "Reports",
        component: () => import("@/views/admin/ReportsView.vue"),
        meta: { title: "Reports & Exports", roles: ["admin"] },
      },
    ],
  },
  {
    path: "/:pathMatch(.*)*",
    redirect: "/dashboard",
  },
];

const router = createRouter({
  history: createWebHistory("/serms/"),
  routes,
  scrollBehavior: () => ({ top: 0 }),
});

// Navigation guard — redirect unauthenticated users to the capstone-auth-module
router.beforeEach(async (to) => {
  const auth = useAuthStore();

  const incomingToken = to.query.token;
  if (incomingToken && !to.meta.public) {
    try {
      auth.setToken(
        Array.isArray(incomingToken) ? incomingToken[0] : incomingToken,
      );
      await auth.fetchProfile();

      const query = { ...to.query };
      delete query.token;

      return {
        path: to.path,
        query,
        hash: to.hash,
        replace: true,
      };
    } catch {
      auth.clearSession();
    }
  }

  // Allow public routes
  if (to.meta.public) {
    return;
  }

  if (!auth.isAuthenticated) {
    try {
      await auth.fetchProfile();
    } catch {
      auth.clearSession();
    }
  }

  // Redirect to external auth module if not authenticated
  if (!auth.isAuthenticated) {
    auth.redirectToLogin(to.fullPath);
    // Return false to cancel the current navigation
    return false;
  }

  // Role-based access control
  if (to.meta.roles && !to.meta.roles.includes(auth.user?.role)) {
    return { name: "Dashboard" };
  }
});

export default router;
