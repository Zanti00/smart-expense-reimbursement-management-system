// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { resolveAuthModuleUrl, useAuthStore } from "./auth";

describe("resolveAuthModuleUrl", () => {
  const originalEnv = import.meta.env.VITE_AUTH_MODULE_URL;

  afterEach(() => {
    import.meta.env.VITE_AUTH_MODULE_URL = originalEnv;
  });

  it("handles empty or root '/' without generating protocol-relative URL", () => {
    import.meta.env.VITE_AUTH_MODULE_URL = "/";
    const logoutUrl = resolveAuthModuleUrl("/logout");
    expect(logoutUrl).not.toBe("//logout");
    expect(logoutUrl).toBe(`${window.location.origin}/logout`);
  });

  it("handles empty base string", () => {
    import.meta.env.VITE_AUTH_MODULE_URL = "";
    const logoutUrl = resolveAuthModuleUrl("/logout");
    expect(logoutUrl).toBe(`${window.location.origin}/logout`);
  });

  it("handles absolute base URL with trailing slash cleanly", () => {
    import.meta.env.VITE_AUTH_MODULE_URL = "http://localhost:5173/";
    const logoutUrl = resolveAuthModuleUrl("/logout");
    expect(logoutUrl).toBe("http://localhost:5173/logout");
  });

  it("handles absolute base URL without trailing slash", () => {
    import.meta.env.VITE_AUTH_MODULE_URL = "http://localhost:5173";
    const logoutUrl = resolveAuthModuleUrl("/logout");
    expect(logoutUrl).toBe("http://localhost:5173/logout");
  });

  it("handles paths missing leading slash", () => {
    import.meta.env.VITE_AUTH_MODULE_URL = "http://localhost:5173";
    const logoutUrl = resolveAuthModuleUrl("logout");
    expect(logoutUrl).toBe("http://localhost:5173/logout");
  });

  it("dynamically adapts localhost base to current window origin when accessed via tunnel/remote hostname", () => {
    import.meta.env.VITE_AUTH_MODULE_URL = "http://localhost:5173";
    const originalLocation = window.location;
    delete window.location;
    window.location = {
      origin: "https://my-tunnel.trycloudflare.com",
      hostname: "my-tunnel.trycloudflare.com",
    };

    try {
      const loginUrl = resolveAuthModuleUrl("/login");
      expect(loginUrl).toBe("https://my-tunnel.trycloudflare.com/login");
    } finally {
      window.location = originalLocation;
    }
  });
});

describe("useAuthStore logout", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it("redirects to valid origin-qualified logout URL instead of //logout", () => {
    import.meta.env.VITE_AUTH_MODULE_URL = "/";
    const auth = useAuthStore();
    auth.logout();

    expect(window.location.href).toBe(`${window.location.origin}/logout`);
    expect(window.location.href).not.toBe("//logout");
  });
});
