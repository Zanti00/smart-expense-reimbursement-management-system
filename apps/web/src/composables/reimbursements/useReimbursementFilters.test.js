import { describe, it, expect } from "vitest";
import { useReimbursementFilters } from "./useReimbursementFilters";

describe("useReimbursementFilters sorting", () => {
  it("sorts latest requests first by default (dateSubmitted desc, then id desc)", () => {
    const mockStore = {
      items: [
        {
          id: 1,
          description: "Older Request",
          amount: 500,
          date: "2026-08-01",
          created_at: "2026-08-01T10:00:00Z",
          status: "pending",
        },
        {
          id: 2,
          description: "Newer Request",
          amount: 1500,
          date: "2026-08-20",
          created_at: "2026-08-20T10:00:00Z",
          status: "pending",
        },
        {
          id: 3,
          description: "Latest Request Same Date Higher ID",
          amount: 800,
          date: "2026-08-20",
          created_at: "2026-08-20T15:00:00Z",
          status: "pending",
        },
      ],
    };

    const filters = useReimbursementFilters(mockStore);
    expect(filters.sortKey.value).toBe("dateSubmitted");
    expect(filters.sortDirection.value).toBe("desc");

    const sorted = filters.sortedTableRows.value;
    expect(sorted.map((r) => r.id)).toEqual([3, 2, 1]);
  });

  it("handles sorting toggles correctly and defaults date/id to desc", () => {
    const mockStore = {
      items: [
        { id: 1, amount: 100, date: "2026-08-01", status: "pending" },
        { id: 2, amount: 300, date: "2026-08-02", status: "pending" },
      ],
    };

    const filters = useReimbursementFilters(mockStore);
    // Toggle dateSubmitted flips from desc to asc
    filters.toggleSort({ key: "dateSubmitted" });
    expect(filters.sortKey.value).toBe("dateSubmitted");
    expect(filters.sortDirection.value).toBe("asc");

    // Switching to amount defaults to asc
    filters.toggleSort({ key: "amount" });
    expect(filters.sortKey.value).toBe("amount");
    expect(filters.sortDirection.value).toBe("asc");

    // Switching to id defaults to desc
    filters.toggleSort({ key: "action", sortKey: "id" });
    expect(filters.sortKey.value).toBe("id");
    expect(filters.sortDirection.value).toBe("desc");
  });
});
