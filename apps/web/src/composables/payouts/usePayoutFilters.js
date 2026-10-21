import { ref, computed, watch } from "vue";
import { normalizeStatus } from "@/composables/reimbursements/useReimbursementFilters";

export function resolvePayoutMethod(item) {
  const payout = item?.payout || {};
  const method = payout.payout_method || item?.payout_method || "Cash";
  if (
    String(method).toLowerCase() === "other" &&
    (payout.payout_method_other || item?.payout_method_other)
  ) {
    return payout.payout_method_other || item.payout_method_other;
  }
  return method;
}

export function isPayoutAcknowledged(item) {
  const payout = item?.payout || {};
  return Boolean(
    payout.acknowledged_at ||
      payout.employee_signature ||
      item?.acknowledged_at ||
      item?.employee_signature,
  );
}

export function getAcknowledgementStatusKey(item) {
  return isPayoutAcknowledged(item)
    ? "acknowledged"
    : "awaiting-acknowledgement";
}

export function getAcknowledgementStatusLabel(item) {
  return isPayoutAcknowledged(item)
    ? "Acknowledged"
    : "Awaiting Acknowledgement";
}

function getSortValue(row, key) {
  const value = row[key];
  if (key === "amount") {
    return Number(value || 0);
  }
  if (key === "grantedDate") {
    const timestamp = new Date(value || 0).getTime();
    return Number.isNaN(timestamp) ? 0 : timestamp;
  }
  if (key === "referenceNumber" || key === "id") {
    return Number(row.id || 0);
  }
  return String(value || "").toLowerCase();
}

export function usePayoutFilters(store, auth) {
  const searchQuery = ref("");
  const activeStatus = ref("All");
  const activeEmployee = ref("All");
  const activeMethod = ref("All");
  const dateFrom = ref("");
  const dateTo = ref("");
  const sortKey = ref("grantedDate");
  const sortDirection = ref("desc");
  const pageSize = 10;
  const currentPage = ref(1);

  const grantedItems = computed(() => {
    const currentUserId = auth?.user?.id;
    const isAdmin = Boolean(auth?.isAdmin);

    return (store.items || []).filter((item) => {
      if (normalizeStatus(item.status) !== "granted") return false;
      if (isAdmin) return true;
      if (currentUserId === undefined || currentUserId === null) return true;
      const ownerId =
        item.user?.id ?? item.user_id ?? item.userId ?? item.submitted_by;
      return String(ownerId) === String(currentUserId);
    });
  });

  const tableRows = computed(() =>
    grantedItems.value.map((item) => {
      const payout = item.payout || {};
      const requestDate =
        payout.request_date ||
        item.date ||
        item.submitted_at ||
        item.created_at;
      const approvedDate =
        payout.approved_at ||
        item.approved_at ||
        item.updated_at ||
        requestDate;
      const grantedDate =
        payout.granted_at || item.granted_at || item.updated_at || requestDate;
      const amount = payout.amount ?? item.amount ?? 0;
      const payoutMethod = resolvePayoutMethod(item);
      const grantedBy =
        payout.granted_by || item.granted_by || "Finance / Admin";
      const approvedBy =
        payout.approved_by || item.approved_by || "Approver";
      const granterSignature =
        payout.granter_signature || item.granter_signature || null;
      const employeeSignature =
        payout.employee_signature || item.employee_signature || null;
      const acknowledgedAt =
        payout.acknowledged_at || item.acknowledged_at || null;
      const employeeName =
        item.user?.name || item.submitted_by_name || "Employee";
      const acknowledgementStatus = getAcknowledgementStatusKey(item);
      const acknowledgementLabel = getAcknowledgementStatusLabel(item);

      return {
        ...item,
        referenceNumber: `Ref #${item.id}`,
        employeeName,
        amount,
        payoutMethod,
        requestDate,
        approvedDate,
        grantedDate,
        grantedBy,
        approvedBy,
        granterSignature,
        employeeSignature,
        acknowledgedAt,
        acknowledgementStatus,
        acknowledgementLabel,
        acknowledged: isPayoutAcknowledged(item),
      };
    }),
  );

  const employeeOptions = computed(() => {
    const names = tableRows.value.map((r) => r.employeeName).filter(Boolean);
    return Array.from(new Set(names));
  });

  const methodOptions = ["All", "Cash", "E-wallet", "Card", "Visa", "Other"];

  const filteredTableRows = computed(() => {
    let rows = tableRows.value;

    if (activeStatus.value !== "All") {
      const targetKey =
        activeStatus.value === "Acknowledged"
          ? "acknowledged"
          : "awaiting-acknowledgement";
      rows = rows.filter((row) => row.acknowledgementStatus === targetKey);
    }

    if (activeEmployee.value !== "All") {
      rows = rows.filter((row) => row.employeeName === activeEmployee.value);
    }

    if (activeMethod.value !== "All") {
      rows = rows.filter((row) => {
        const method = row.payoutMethod || "";
        if (activeMethod.value === "Other") {
          return !["cash", "e-wallet", "card", "visa"].includes(
            String(method).toLowerCase(),
          );
        }
        return String(method).toLowerCase() === activeMethod.value.toLowerCase();
      });
    }

    if (dateFrom.value) {
      rows = rows.filter((row) => {
        const d = String(row.grantedDate || "").slice(0, 10);
        return d >= dateFrom.value;
      });
    }

    if (dateTo.value) {
      rows = rows.filter((row) => {
        const d = String(row.grantedDate || "").slice(0, 10);
        return d <= dateTo.value;
      });
    }

    if (searchQuery.value.trim()) {
      const q = searchQuery.value.trim().toLowerCase();
      rows = rows.filter((row) =>
        [
          row.id,
          row.referenceNumber,
          row.employeeName,
          row.description,
          row.amount,
          row.payoutMethod,
          row.grantedDate,
          row.grantedBy,
          row.acknowledgementLabel,
        ].some((val) =>
          String(val || "")
            .toLowerCase()
            .includes(q),
        ),
      );
    }

    return rows;
  });

  const sortedTableRows = computed(() => {
    const rows = [...filteredTableRows.value];
    return rows.sort((a, b) => {
      const aValue = getSortValue(a, sortKey.value);
      const bValue = getSortValue(b, sortKey.value);
      if (aValue === bValue) {
        return (Number(b.id) || 0) - (Number(a.id) || 0);
      }
      const result = aValue > bValue ? 1 : -1;
      return sortDirection.value === "asc" ? result : -result;
    });
  });

  const totalPages = computed(() =>
    Math.max(1, Math.ceil(sortedTableRows.value.length / pageSize)),
  );

  const paginatedTableRows = computed(() => {
    const start = (currentPage.value - 1) * pageSize;
    return sortedTableRows.value.slice(start, start + pageSize);
  });

  watch(
    [
      searchQuery,
      activeStatus,
      activeEmployee,
      activeMethod,
      dateFrom,
      dateTo,
    ],
    () => {
      currentPage.value = 1;
    },
  );

  watch(totalPages, (pages) => {
    if (currentPage.value > pages) currentPage.value = pages;
  });

  function toggleSort(column) {
    const key = column.sortKey || column.key;
    if (sortKey.value === key) {
      sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
      currentPage.value = 1;
      return;
    }
    sortKey.value = key;
    sortDirection.value = ["grantedDate", "id", "referenceNumber"].includes(key)
      ? "desc"
      : "asc";
    currentPage.value = 1;
  }

  return {
    searchQuery,
    activeStatus,
    activeEmployee,
    activeMethod,
    dateFrom,
    dateTo,
    employeeOptions,
    methodOptions,
    sortKey,
    sortDirection,
    pageSize,
    currentPage,
    tableRows,
    filteredTableRows,
    sortedTableRows,
    paginatedTableRows,
    totalPages,
    toggleSort,
  };
}
