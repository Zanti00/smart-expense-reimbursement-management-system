import { ref, computed } from "vue";
import { getInitials } from "@/utils/formatters";

export function useCashAdvanceList(store, auth) {
  const activeStatus = ref("All");

  const statusTabs = computed(() => {
    const baseTabs = ["All", "Pending", "Approved", "Rejected", "Disbursed"];
    return auth.isAdmin ? [...baseTabs, "Me"] : baseTabs;
  });

  const formattedItems = computed(() => {
    return store.items.map((item) => ({
      ...item,
      fileDescription: item.document?.file_name || "Document Attached",
      documentUrl: item.documentUrl,
      requested: item.date ? new Date(item.date).toLocaleDateString() : "--",
      dueDate: item.dueDate ? new Date(item.dueDate).toLocaleDateString() : "--",
      user: item.requestedBy || "Unknown User",
      initials: getInitials(item.requestedBy || "Unknown User"),
      outstanding: item.balance || 0,
    }));
  });

  const filteredRows = computed(() => {
    let items = formattedItems.value;
    if (activeStatus.value !== "All") {
      if (activeStatus.value === "Me") {
        items = items.filter((row) => row.userId === auth.user?.id);
      } else {
        items = items.filter(
          (row) => row.status === activeStatus.value.toLowerCase(),
        );
      }
    }
    return items;
  });

  const adminMetrics = computed(() => {
    const items = formattedItems.value;
    const outstandingRows = items.filter((row) => Number(row.outstanding) > 0);
    const uniqueEmployees = new Set(outstandingRows.map((row) => row.user));

    return {
      pending: items.filter((row) => row.status === "pending").length,
      approved: items.filter((row) => row.status === "approved").length,
      rejected: items.filter((row) => row.status === "rejected").length,
      outstanding: outstandingRows.reduce(
        (sum, row) => sum + (Number(row.outstanding) || 0),
        0,
      ),
      outstandingEmployees: uniqueEmployees.size,
    };
  });

  const userMetrics = computed(() => {
    const items = formattedItems.value;
    const outstandingRows = items.filter((row) => Number(row.outstanding) > 0);

    return {
      totalAmount: items.reduce((sum, row) => sum + (Number(row.amount) || 0), 0),
      pending: items.filter((row) => row.status === "pending").length,
      approved: items.filter((row) => row.status === "approved").length,
      rejected: items.filter((row) => row.status === "rejected").length,
      outstanding: outstandingRows.reduce(
        (sum, row) => sum + (Number(row.outstanding) || 0),
        0,
      ),
    };
  });

  const activeMetrics = computed(() => {
    return auth.isAdmin ? adminMetrics.value : userMetrics.value;
  });

  return {
    activeStatus,
    statusTabs,
    formattedItems,
    filteredRows,
    activeMetrics,
  };
}
