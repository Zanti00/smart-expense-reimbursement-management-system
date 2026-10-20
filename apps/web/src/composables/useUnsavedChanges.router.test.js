// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { defineComponent, h, ref } from "vue";
import { mount, flushPromises } from "@vue/test-utils";
import {
  createMemoryHistory,
  createRouter,
  RouterView,
  useRouter,
} from "vue-router";
import { useUnsavedChanges } from "@/composables/useUnsavedChanges";

function createHarness() {
  const Source = defineComponent({
    name: "UnsavedChangesSource",
    setup() {
      const router = useRouter();
      const isDirty = ref(true);
      const localRuns = ref(0);
      const { showConfirmModal, handleConfirmLeave, dismissWithConfirm } =
        useUnsavedChanges(isDirty);

      const go = () =>
        dismissWithConfirm(() => router.push("/target"), { navigates: true });
      const goLocal = () =>
        dismissWithConfirm(() => {
          localRuns.value += 1;
        });

      return {
        showConfirmModal,
        handleConfirmLeave,
        go,
        goLocal,
        localRuns,
      };
    },
    render() {
      return h("div", { class: "source" });
    },
  });

  const Target = defineComponent({
    name: "UnsavedChangesTarget",
    render() {
      return h("div", { class: "target" });
    },
  });

  const Host = defineComponent({
    name: "RouterHost",
    render() {
      return h(RouterView);
    },
  });

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/source", component: Source },
      { path: "/target", component: Target },
    ],
  });

  return { router, Host, Source };
}

async function mountAtSource() {
  const { router, Host, Source } = createHarness();
  await router.push("/source");
  await router.isReady();

  const wrapper = mount(Host, {
    global: { plugins: [router] },
  });
  await flushPromises();

  return {
    router,
    wrapper,
    source: wrapper.findComponent(Source),
  };
}

describe("useUnsavedChanges with a real router", () => {
  it("reaches the target after a confirmed in-page navigation without prompting twice", async () => {
    const { router, source } = await mountAtSource();

    source.vm.go();
    await flushPromises();
    expect(source.vm.showConfirmModal).toBe(true);

    source.vm.handleConfirmLeave();
    await flushPromises();
    await flushPromises();

    expect(router.currentRoute.value.path).toBe("/target");
  });

  it("still confirms and completes a direct route leave", async () => {
    const { router, source } = await mountAtSource();

    router.push("/target");
    await flushPromises();
    expect(source.vm.showConfirmModal).toBe(true);
    expect(router.currentRoute.value.path).toBe("/source");

    source.vm.handleConfirmLeave();
    await flushPromises();
    await flushPromises();

    expect(router.currentRoute.value.path).toBe("/target");
  });

  it("still prompts for a later non-navigating dismissal after one is confirmed", async () => {
    const { source } = await mountAtSource();

    source.vm.goLocal();
    expect(source.vm.showConfirmModal).toBe(true);
    expect(source.vm.localRuns).toBe(0);

    source.vm.handleConfirmLeave();
    expect(source.vm.showConfirmModal).toBe(false);
    expect(source.vm.localRuns).toBe(1);

    source.vm.goLocal();
    expect(source.vm.showConfirmModal).toBe(true);
    expect(source.vm.localRuns).toBe(1);

    source.vm.handleConfirmLeave();
    expect(source.vm.localRuns).toBe(2);
  });
});
