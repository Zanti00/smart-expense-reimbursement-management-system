import {
  LayoutDashboard,
  Receipt,
  Wallet,
  FilePieChart,
  FileCheck,
} from "lucide-vue-next";

const base = [
  { header: "OPERATOR" },
  { name: "Dashboard", to: "/dashboard", icon: LayoutDashboard },
  { name: "Reimbursements", to: "/reimbursements", icon: Receipt },
  { name: "Cash Advances", to: "/cash-advances", icon: Wallet },
  { name: "Liquidations", to: "/liquidations", icon: FilePieChart },
];

const employee = [{ name: "My Expense", to: "/my-expense", icon: FileCheck }];

export function buildNavLinks() {
  return [...base, ...employee];
}
