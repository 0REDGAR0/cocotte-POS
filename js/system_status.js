(() => {
  if (window.__cocotteSystemStatusInit) {
    return;
  }
  window.__cocotteSystemStatusInit = true;

  const SELECTOR = "[data-system-status]";
  const TIME_FORMAT = new Intl.DateTimeFormat("fr-FR", {
    hour: "2-digit",
    minute: "2-digit"
  });

  const state = {
    isOnline: typeof navigator.onLine === "boolean" ? navigator.onLine : true,
    batterySupported: false,
    charging: null,
    level: null
  };

  let batteryManager = null;
  let timeTimer = null;

  function getContainers() {
    return Array.from(document.querySelectorAll(SELECTOR));
  }

  function getBatteryLevelClass(level) {
    if (typeof level !== "number") {
      return "level-unknown";
    }

    if (level <= 0.15) {
      return "level-critical";
    }

    if (level <= 0.35) {
      return "level-low";
    }

    if (level <= 0.7) {
      return "level-medium";
    }

    return "level-full";
  }

  function getBatteryLabel() {
    if (!state.batterySupported) {
      return "Batterie inconnue";
    }

    return state.charging ? "Secteur" : "Batterie";
  }

  function getOnlineLabel() {
    return state.isOnline ? "Internet OK" : "Hors ligne";
  }

  function buildMarkup(currentTime) {
    const batteryLevelClass = getBatteryLevelClass(state.level);
    const batteryChargingClass = state.charging ? "is-charging" : "is-discharging";
    const networkClass = state.isOnline ? "is-online" : "is-offline";

    return `
      <div class="system-status-pill system-status-time">
        <span class="system-status-label">Heure</span>
        <span class="system-status-value">${currentTime}</span>
      </div>
      <div class="system-status-pill system-status-network ${networkClass}">
        <span class="system-status-network-dot" aria-hidden="true"></span>
        <span class="system-status-label">Internet</span>
        <span class="system-status-value">${getOnlineLabel()}</span>
      </div>
      <div class="system-status-pill system-status-battery ${batteryLevelClass} ${batteryChargingClass}">
        <span class="system-battery-gauge" aria-hidden="true">
          <span class="system-battery-fill"></span>
        </span>
        <span class="system-status-label">Alim.</span>
        <span class="system-status-value">${getBatteryLabel()}</span>
      </div>
    `;
  }

  function render() {
    const containers = getContainers();
    if (containers.length === 0) {
      return;
    }

    const currentTime = TIME_FORMAT.format(new Date());

    containers.forEach((container) => {
      container.innerHTML = buildMarkup(currentTime);
      container.setAttribute("aria-label", "Etat local de l'appareil");
    });
  }

  function updateOnlineStatus(isOnline) {
    state.isOnline = Boolean(isOnline);
    render();
  }

  function updateBatteryState() {
    if (!batteryManager) {
      state.batterySupported = false;
      state.charging = null;
      state.level = null;
      render();
      return;
    }

    state.batterySupported = true;
    state.charging = Boolean(batteryManager.charging);
    state.level = typeof batteryManager.level === "number" ? batteryManager.level : null;
    render();
  }

  function bindBatteryManager(manager) {
    batteryManager = manager;
    updateBatteryState();
    batteryManager.addEventListener("chargingchange", updateBatteryState);
    batteryManager.addEventListener("levelchange", updateBatteryState);
  }

  function initBattery() {
    if (typeof navigator.getBattery !== "function") {
      state.batterySupported = false;
      render();
      return;
    }

    navigator.getBattery()
      .then(bindBatteryManager)
      .catch(() => {
        state.batterySupported = false;
        render();
      });
  }

  function init() {
    const containers = getContainers();
    if (containers.length === 0) {
      return;
    }

    render();
    timeTimer = window.setInterval(render, 1000);
    window.addEventListener("online", () => updateOnlineStatus(true));
    window.addEventListener("offline", () => updateOnlineStatus(false));
    initBattery();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
