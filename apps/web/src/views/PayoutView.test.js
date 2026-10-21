// @vitest-environment happy-dom
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";
import PayoutView from "@/views/PayoutView.vue";
import BaseKpiGrid from "@/components/base/BaseKpiGrid.vue";
import BaseUtilityToolbar from "@/components/base/BaseUtilityToolbar.vue";
import PayoutsTable from "@/components/payouts/PayoutsTable.vue";
import PayoutDetailsModal from "@/components/payouts/PayoutDetailsModal.vue";
import { buildNavLinks } from "@/composables/useNavLinks";

const { mockItems, fetchAllMock, acknowledgePayoutMock, authState } = vi.hoisted(() => ({
  fetchAllMock: vi.fn(),
  acknowledgePayoutMock: vi.fn().mockResolvedValue({ id: 102, status: "granted" }),
  authState: {
    isAdmin: false,
    user: { id: "user-1", name: "Employee One" },
  },
  mockItems: [
    {
      id: 101,
      user_id: "user-1",
      submitted_by_name: "Employee One",
      description: "Client Dinner",
      amount: "1500.00",
      currency: "PHP",
      status: "granted",
      date: "2026-10-01",
      created_at: "2026-10-01T08:00:00Z",
      updated_at: "2026-10-10T10:00:00Z",
      payout: {
        id: 1,
        reimbursement_id: 101,
        request_date: "2026-10-01",
        approved_at: "2026-10-05T14:00:00Z",
        granted_at: "2026-10-10T10:00:00Z",
        approved_by: "Finance Manager",
        granted_by: "Accounting Staff",
        payout_method: "E-wallet",
        amount: "1500.00",
        granter_signature: "data:image/png;base64,granterSig101",
        employee_signature: "data:image/png;base64,empSig101",
        acknowledged_at: "2026-10-11T09:00:00Z",
      },
    },
    {
      id: 102,
      user_id: "user-1",
      submitted_by_name: "Employee One",
      description: "Office Supplies",
      amount: "2500.50",
      currency: "PHP",
      status: "granted",
      date: "2026-10-02",
      created_at: "2026-10-02T08:00:00Z",
      updated_at: "2026-10-12T10:00:00Z",
      payout: {
        id: 2,
        reimbursement_id: 102,
        request_date: "2026-10-02",
        approved_at: "2026-10-06T14:00:00Z",
        granted_at: "2026-10-12T10:00:00Z",
        approved_by: "Finance Manager",
        granted_by: "Cashier Jane",
        payout_method: "Cash",
        amount: "2500.50",
        granter_signature: "data:image/png;base64,granterSig102",
        employee_signature: null,
        acknowledged_at: null,
      },
    },
    {
      id: 103,
      user_id: "user-1",
      submitted_by_name: "Employee One",
      description: "Pending Expense",
      amount: "800.00",
      status: "pending",
      date: "2026-10-03",
    },
    {
      id: 104,
      user_id: "user-1",
      submitted_by_name: "Employee One",
      description: "Approved Expense",
      amount: "900.00",
      status: "approved",
      date: "2026-10-04",
    },
    {
      id: 105,
      user_id: "user-2",
      submitted_by_name: "Employee Two",
      description: "Other Employee Granted",
      amount: "5000.00",
      currency: "PHP",
      status: "granted",
      date: "2026-10-05",
      created_at: "2026-10-05T08:00:00Z",
      updated_at: "2026-10-13T10:00:00Z",
      payout: {
        id: 3,
        reimbursement_id: 105,
        request_date: "2026-10-05",
        approved_at: "2026-10-07T14:00:00Z",
        granted_at: "2026-10-13T10:00:00Z",
        approved_by: "Admin Director",
        granted_by: "Cashier Bob",
        payout_method: "Card",
        amount: "5000.00",
        granter_signature: "data:image/png;base64,granterSig105",
        employee_signature: null,
        acknowledged_at: null,
      },
    },
  ],
}));

vi.mock("@/stores/reimbursement", () => ({
  useReimbursementStore: () => ({
    items: mockItems,
    isLoading: false,
    fetchAll: fetchAllMock,
    acknowledgePayout: acknowledgePayoutMock,
  }),
}));

vi.mock("@/stores/auth", () => ({
  useAuthStore: () => authState,
}));

describe("PayoutView (G2-048 to G2-052)", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    authState.isAdmin = false;
    authState.user = { id: "user-1", name: "Employee One" };
  });

  it("includes Payout in the employee sidebar navigation (G2-048 Task 1)", () => {
    const links = buildNavLinks();
    const payoutLink = links.find((l) => l.to === "/payout");
    expect(payoutLink).toBeDefined();
    expect(payoutLink.name).toBe("Payout");
  });

  it("lists only the logged-in employee's Granted requests and excludes Pending/Approved/other users (G2-048 Task 2)", () => {
    const wrapper = mount(PayoutView);
    expect(fetchAllMock).toHaveBeenCalledOnce();

    const table = wrapper.findComponent(PayoutsTable);
    expect(table.exists()).toBe(true);

    const rows = table.props("rows");
    expect(rows).toHaveLength(2);
    expect(rows.map((r) => r.id)).toEqual([102, 101]);

    const text = wrapper.text();
    expect(text).toContain("#101");
    expect(text).toContain("#102");
    expect(text).not.toContain("#103");
    expect(text).not.toContain("#104");
    expect(text).not.toContain("#105");
  });

  it("displays the 4 KPI summary cards accurately for the employee's granted payouts", () => {
    const wrapper = mount(PayoutView);
    const kpiGrid = wrapper.findComponent(BaseKpiGrid);
    expect(kpiGrid.exists()).toBe(true);

    const kpis = kpiGrid.props("kpis");
    expect(kpis).toHaveLength(4);
    expect(kpis[0]).toMatchObject({ label: "Total Payouts", value: 2 });
    expect(kpis[1].label).toBe("Total Amount Received");
    expect(kpis[1].value).toContain("4,000.50");
    expect(kpis[2]).toMatchObject({ label: "Acknowledged", value: 1 });
    expect(kpis[3]).toMatchObject({ label: "Awaiting Acknowledgement", value: 1 });
  });

  it("filters payouts by search query and acknowledgement status (G2-048 Task 3)", async () => {
    const wrapper = mount(PayoutView);
    const toolbar = wrapper.findComponent(BaseUtilityToolbar);
    expect(toolbar.exists()).toBe(true);
    expect(toolbar.props("statuses")).toEqual([
      "All",
      "Acknowledged",
      "Awaiting Acknowledgement",
    ]);

    // Filter by Acknowledged
    await toolbar.vm.$emit("update:statusValue", "Acknowledged");
    await nextTick();
    expect(wrapper.findComponent(PayoutsTable).props("rows")).toHaveLength(1);
    expect(wrapper.findComponent(PayoutsTable).props("rows")[0].id).toBe(101);

    // Filter by Awaiting Acknowledgement
    await toolbar.vm.$emit("update:statusValue", "Awaiting Acknowledgement");
    await nextTick();
    expect(wrapper.findComponent(PayoutsTable).props("rows")).toHaveLength(1);
    expect(wrapper.findComponent(PayoutsTable).props("rows")[0].id).toBe(102);

    // Reset status filter and test search
    await toolbar.vm.$emit("update:statusValue", "All");
    await toolbar.vm.$emit("update:search", "E-wallet");
    await nextTick();
    expect(wrapper.findComponent(PayoutsTable).props("rows")).toHaveLength(1);
    expect(wrapper.findComponent(PayoutsTable).props("rows")[0].id).toBe(101);
  });

  it("opens the PayoutDetailsModal with full payout details and granter signature on View action (G2-049)", async () => {
    const wrapper = mount(PayoutView);
    const table = wrapper.findComponent(PayoutsTable);

    const row101 = table.props("rows").find((r) => r.id === 101);
    await table.vm.$emit("view-details", row101);
    await nextTick();

    const modal = wrapper.findComponent(PayoutDetailsModal);
    expect(modal.exists()).toBe(true);
    expect(modal.props("record")).toBeTruthy();
    expect(modal.props("record").id).toBe(101);

    const modalText = modal.text();
    expect(modalText).toContain("1,500.00");
    expect(modalText).toContain("E-wallet");
    expect(modalText).toContain("Accounting Staff");
    expect(modalText).toContain("Oct 1, 2026");
    expect(modalText).toContain("Oct 5, 2026");
    expect(modalText).toContain("Oct 10, 2026");

    const sigImg = modal.find('img[alt="Granter Signature"]');
    expect(sigImg.exists()).toBe(true);
    expect(sigImg.attributes("src")).toBe("data:image/png;base64,granterSig101");
  });

  it("allows employee to acknowledge an unacknowledged payout with signature pad (G2-050)", async () => {
    authState.isAdmin = false;
    authState.user = { id: "user-1", name: "Employee One" };
    const wrapper = mount(PayoutView);
    const table = wrapper.findComponent(PayoutsTable);

    // Open row 102 (unacknowledged)
    const row102 = table.props("rows").find((r) => r.id === 102);
    await table.vm.$emit("view-details", row102);
    await nextTick();

    const modal = wrapper.findComponent(PayoutDetailsModal);
    expect(modal.exists()).toBe(true);

    // Signature pad is visible for employee
    const sigPad = modal.findComponent({ name: "BaseSignaturePad" });
    expect(sigPad.exists()).toBe(true);

    const ackBtn = modal.find('[data-testid="acknowledge-payout-btn"]');
    expect(ackBtn.exists()).toBe(true);
    expect(ackBtn.attributes("disabled")).toBeDefined();

    // Sign
    await sigPad.vm.$emit("update:modelValue", "data:image/png;base64,myEmpSig");
    await nextTick();
    expect(ackBtn.attributes("disabled")).toBeUndefined();

    // Click Acknowledge
    await ackBtn.trigger("click");
    await nextTick();

    expect(acknowledgePayoutMock).toHaveBeenCalledWith(102, "data:image/png;base64,myEmpSig");
  });

  it("locks signature pad and displays acknowledged signature for already acknowledged payout (G2-050 Task 3)", async () => {
    authState.isAdmin = false;
    authState.user = { id: "user-1", name: "Employee One" };
    const wrapper = mount(PayoutView);
    const table = wrapper.findComponent(PayoutsTable);

    // Open row 101 (already acknowledged)
    const row101 = table.props("rows").find((r) => r.id === 101);
    await table.vm.$emit("view-details", row101);
    await nextTick();

    const modal = wrapper.findComponent(PayoutDetailsModal);
    expect(modal.findComponent({ name: "BaseSignaturePad" }).exists()).toBe(false);
    expect(modal.find('[data-testid="acknowledge-payout-btn"]').exists()).toBe(false);

    const empSigImg = modal.find('img[alt="Employee Signature"]');
    expect(empSigImg.exists()).toBe(true);
    expect(empSigImg.attributes("src")).toBe("data:image/png;base64,empSig101");
  });

  it("renders Admin Payout view with 3 KPI cards, Employee column, and all granted payouts (G2-051)", async () => {
    authState.isAdmin = true;
    authState.user = { id: "admin-1", name: "Admin Manager" };
    const wrapper = mount(PayoutView);

    // 3 KPI cards for admin: Total Payouts, Total Amount Released, Awaiting Acknowledgement
    const kpiGrid = wrapper.findComponent(BaseKpiGrid);
    const kpis = kpiGrid.props("kpis");
    expect(kpis).toHaveLength(3);
    expect(kpis[0]).toMatchObject({ label: "Total Payouts", value: 3 });
    expect(kpis[1].label).toBe("Total Amount Released");
    expect(kpis[1].value).toContain("9,000.50"); // 1500 + 2500.50 + 5000
    expect(kpis[2]).toMatchObject({ label: "Awaiting Acknowledgement", value: 2 });

    // Table has Employee column and lists all 3 granted payouts
    const table = wrapper.findComponent(PayoutsTable);
    const columns = table.props("columns");
    expect(columns.some((c) => c.key === "employeeName" || c.key === "employee")).toBe(true);
    expect(table.props("rows")).toHaveLength(3);
    expect(wrapper.text()).toContain("Employee One");
    expect(wrapper.text()).toContain("Employee Two");
  });

  it("filters admin payouts by employee, payout method, and date range (G2-051 Task 3)", async () => {
    authState.isAdmin = true;
    authState.user = { id: "admin-1", name: "Admin Manager" };
    const wrapper = mount(PayoutView);

    // Open Filters popover
    const filterBtn = wrapper.findAll("button").find((btn) => btn.text().includes("Filters"));
    expect(filterBtn).toBeDefined();
    await filterBtn.trigger("click");
    await nextTick();

    // Filter by Employee Two
    const empSelect = wrapper.find("select#admin-employee-filter");
    expect(empSelect.exists()).toBe(true);
    await empSelect.setValue("Employee Two");
    await nextTick();
    expect(wrapper.findComponent(PayoutsTable).props("rows")).toHaveLength(1);
    expect(wrapper.findComponent(PayoutsTable).props("rows")[0].id).toBe(105);

    // Reset employee, filter by Method = Cash
    await empSelect.setValue("All");
    const methodSelect = wrapper.find("select#admin-method-filter");
    expect(methodSelect.exists()).toBe(true);
    await methodSelect.setValue("Cash");
    await nextTick();
    expect(wrapper.findComponent(PayoutsTable).props("rows")).toHaveLength(1);
    expect(wrapper.findComponent(PayoutsTable).props("rows")[0].id).toBe(102);

    // Date range filter
    await methodSelect.setValue("All");
    const dateFrom = wrapper.find("input#admin-date-from");
    const dateTo = wrapper.find("input#admin-date-to");
    expect(dateFrom.exists()).toBe(true);
    expect(dateTo.exists()).toBe(true);
    await dateFrom.setValue("2026-10-11");
    await dateTo.setValue("2026-10-12");
    await nextTick();
    expect(wrapper.findComponent(PayoutsTable).props("rows")).toHaveLength(1);
    expect(wrapper.findComponent(PayoutsTable).props("rows")[0].id).toBe(102);
  });

  it("displays read-only audit details in PayoutDetailsModal for admin without signature pad (G2-052)", async () => {
    authState.isAdmin = true;
    authState.user = { id: "admin-1", name: "Admin Manager" };
    const wrapper = mount(PayoutView);
    const table = wrapper.findComponent(PayoutsTable);

    // Open row 102 (awaiting acknowledgement)
    const row102 = table.props("rows").find((r) => r.id === 102);
    await table.vm.$emit("view-details", row102);
    await nextTick();

    const modal = wrapper.findComponent(PayoutDetailsModal);
    expect(modal.findComponent({ name: "BaseSignaturePad" }).exists()).toBe(false);
    expect(modal.find('[data-testid="acknowledge-payout-btn"]').exists()).toBe(false);
    expect(modal.text()).toContain("Awaiting Acknowledgement");
    expect(modal.text()).toContain("Finance Manager"); // Approved By
    expect(modal.text()).toContain("Cashier Jane"); // Granted By
  });
});
