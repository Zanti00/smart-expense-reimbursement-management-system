import { defineStore } from "pinia";
import { ref, computed } from "vue";

const AUTH_MODULE_URL =
  import.meta.env.VITE_AUTH_MODULE_URL || "http://localhost:3001";

export const useAuthStore = defineStore("auth", () => {
  const user = ref(null);
  const token = ref(localStorage.getItem("serms_token") || null);

  const isAuthenticated = computed(() => !!user.value);
  const isAdmin = computed(() => user.value?.role === "admin");

  /**
   * Restore session from localStorage on app boot.
   * If a token exists but no user profile is cached, attempt to fetch it.
   */
  function restoreSession() {
    const stored = localStorage.getItem("serms_user");
    if (stored) {
      user.value = JSON.parse(stored);
    }
  }

  /**
   * Redirect the browser to the capstone-auth-module's login page.
   * The auth module will redirect back to /auth/callback?token=JWT after login.
   *
   * @param {string} [redirectPath] — the SERMS path to return to after login
   * @param {string} [errorMessage] — an optional error message to show on the login page
   */
  function redirectToLogin(redirectPath = "/dashboard", errorMessage = null) {
    const callbackUrl = `${window.location.origin}/serms/auth/callback`;
    let loginUrl = `${AUTH_MODULE_URL}/login?redirect_uri=${encodeURIComponent(callbackUrl)}&state=${encodeURIComponent(redirectPath)}`;

    if (errorMessage) {
      loginUrl += `&error=${encodeURIComponent(errorMessage)}`;
    }

    window.location.href = loginUrl;
  }

  /**
   * Handle the callback from the capstone-auth-module.
   * Fetches the user profile from the SERMS API proxy.
   *
   * @returns {Promise<object>} — the user profile
   */
  async function handleCallback() {
    const profile = await fetchProfile();
    return profile;
  }

  /**
   * Fetch the authenticated user's profile from the SERMS API,
   * which proxies the request to the capstone-auth-module's /api/me endpoint.
   *
   * @returns {Promise<object>} — the user profile
   */
  async function fetchProfile() {
    const headers = {
      Accept: "application/json",
    };
    if (token.value) {
      headers["Authorization"] = `Bearer ${token.value}`;
    }

    const response = await fetch("/api/serms/auth/me", {
      headers,
      credentials: "include",
    });

    if (!response.ok) {
      // Token is invalid or expired — clear session
      clearSession();
      throw new Error(
        "Failed to fetch user profile. Session may have expired.",
      );
    }

    const data = await response.json();
    user.value = data;
    localStorage.setItem("serms_user", JSON.stringify(user.value));
    return user.value;
  }

  /**
   * Log the user out: clear local state, then redirect to the
   * capstone-auth-module's logout endpoint to invalidate the token server-side.
   */
  function logout() {
    clearSession();

    // Redirect to auth module to clear server-side session.
    // We intentionally omit redirect_uri so it stays on the login screen and displays a success toast.
    const logoutUrl = `${AUTH_MODULE_URL}/logout`;
    window.location.href = logoutUrl;
  }

  /**
   * Clear all local auth state without redirecting.
   */
  function clearSession() {
    user.value = null;
    token.value = null;
    localStorage.removeItem("serms_user");
    localStorage.removeItem("serms_token");
  }

  return {
    user,
    isAuthenticated,
    isAdmin,
    token,
    restoreSession,
    redirectToLogin,
    handleCallback,
    fetchProfile,
    logout,
    clearSession,
  };
});
