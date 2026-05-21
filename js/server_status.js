(() => {
  if (window.__cocotteServerStatusInit) {
    return;
  }
  window.__cocotteServerStatusInit = true;

  const ENDPOINT_URL = "php/get_server_status.php";
  const POLL_INTERVAL_MS = 15000;
  const REQUEST_TIMEOUT_MS = 2500;
  const REFRESH_DEDUP_MS = 900;
  const REFRESH_FEEDBACK_DELAY_MS = 40;
  const REFRESH_LABEL = "Recharger la page";
  const REFRESH_BUSY_LABEL = "Rechargement de la page";
  const THEME_STORAGE_KEY = "cocotte-theme";

  function getLocalSecondsSinceMidnight() {
    const now = new Date();
    return (now.getHours() * 3600) + (now.getMinutes() * 60) + now.getSeconds();
  }

  const state = {
    internetStatus: "unknown",
    batteryStatus: "unknown",
    batteryLevelBand: "unknown",
    batteryAvailable: false,
    secondsSinceMidnight: getLocalSecondsSinceMidnight(),
    lastSyncSucceeded: false,
    timeSource: "local-fallback"
  };

  function getContainer() {
    return document.getElementById("server-status");
  }

  function getValueNode(role) {
    return document.querySelector(`[data-server-status="${role}"] .server-status-value`);
  }

  function getItemNode(role) {
    return document.querySelector(`[data-server-status="${role}"]`);
  }

  function navigateWithCacheBuster() {
    const nextUrl = new URL(window.location.href);
    nextUrl.searchParams.set("r", Date.now().toString());

    try {
      window.location.assign(nextUrl.toString());
    } catch (error) {
      window.location.href = nextUrl.toString();
    }
  }

  function restoreRefreshButton(button) {
    button.dataset.refreshing = "false";
    button.disabled = false;
    button.classList.remove("is-refreshing");
    button.title = REFRESH_LABEL;
    button.setAttribute("aria-label", REFRESH_LABEL);
  }

  function handleRefreshTrigger(button, event) {
    event.preventDefault();
    event.stopPropagation();

    if (typeof event.stopImmediatePropagation === "function") {
      event.stopImmediatePropagation();
    }

    const now = Date.now();
    const lastTriggeredAt = Number(button.dataset.refreshTriggeredAt || "0");

    if (button.dataset.refreshing === "true" || (now - lastTriggeredAt) < REFRESH_DEDUP_MS) {
      return;
    }

    button.dataset.refreshTriggeredAt = now.toString();
    button.dataset.refreshing = "true";
    button.disabled = true;
    button.classList.add("is-refreshing");
    button.title = REFRESH_BUSY_LABEL;
    button.setAttribute("aria-label", REFRESH_BUSY_LABEL);

    window.setTimeout(() => {
      navigateWithCacheBuster();
    }, REFRESH_FEEDBACK_DELAY_MS);

    window.setTimeout(() => {
      if (document.contains(button)) {
        restoreRefreshButton(button);
      }
    }, REFRESH_DEDUP_MS + 400);
  }

  function installRefreshButtons() {
    document.querySelectorAll(".header-refresh-btn").forEach(button => {
      if (button.dataset.refreshBound === "true") {
        return;
      }

      button.dataset.refreshBound = "true";
      restoreRefreshButton(button);

      const boundHandler = event => {
        handleRefreshTrigger(button, event);
      };

      button.addEventListener("click", boundHandler);
      button.addEventListener("touchend", boundHandler, { passive: false });

      if (typeof window.PointerEvent === "function") {
        button.addEventListener("pointerup", boundHandler);
      }
    });
  }

  function clearStoredTheme() {
    try {
      window.localStorage.removeItem(THEME_STORAGE_KEY);
    } catch (error) {
      warn("suppression localStorage impossible pour le theme", error instanceof Error ? error.message : error);
    }
  }

  function disableThemeState() {
    if (document.body) {
      document.body.classList.remove("theme-dark");
    }

    if (document.documentElement) {
      document.documentElement.style.colorScheme = "light";
    }

    clearStoredTheme();
  }

  function parseServerTime(serverTime) {
    if (typeof serverTime !== "string" || !/^\d{2}:\d{2}:\d{2}$/.test(serverTime)) {
      return null;
    }

    const [hours, minutes, seconds] = serverTime.split(":").map(Number);

    if ([hours, minutes, seconds].some((value) => Number.isNaN(value))) {
      return null;
    }

    return (hours * 3600) + (minutes * 60) + seconds;
  }

  function formatClock(totalSeconds) {
    const normalized = typeof totalSeconds === "number"
      ? (((totalSeconds % 86400) + 86400) % 86400)
      : getLocalSecondsSinceMidnight();

    const hours = String(Math.floor(normalized / 3600)).padStart(2, "0");
    const minutes = String(Math.floor((normalized % 3600) / 60)).padStart(2, "0");
    const seconds = String(normalized % 60).padStart(2, "0");

    return `${hours}:${minutes}:${seconds}`;
  }

  function getInternetLabel(status) {
    if (status === "online") {
      return "Internet en ligne";
    }

    if (status === "offline") {
      return "Internet hors ligne";
    }

    return "Internet inconnu";
  }

  function getBatteryLabel() {
    if (!state.batteryAvailable) {
      return "Batterie indisponible";
    }

    if (state.batteryStatus === "charging") {
      return "Batterie en charge";
    }

    if (state.batteryStatus === "full") {
      return "Batterie pleine";
    }

    if (state.batteryLevelBand === "critical") {
      return "Batterie critique";
    }

    if (state.batteryStatus === "discharging") {
      return "Batterie en decharge";
    }

    return "Batterie inconnue";
  }

  function warn(message, details) {
    if (typeof console !== "undefined" && typeof console.warn === "function") {
      if (typeof details === "undefined") {
        console.warn(`[server_status] ${message}`);
      } else {
        console.warn(`[server_status] ${message}`, details);
      }
    }
  }

  function applyState() {
    const container = getContainer();
    if (!container) {
      return;
    }

    const internetNode = getValueNode("internet");
    const batteryNode = getValueNode("battery");
    const clockNode = document.getElementById("clock");
    const internetItem = getItemNode("internet");
    const batteryItem = getItemNode("battery");
    const timeItem = getItemNode("time");
    const internetLabel = getInternetLabel(state.internetStatus);
    const batteryLabel = getBatteryLabel();
    const renderedTime = formatClock(state.secondsSinceMidnight);

    if (internetNode) {
      internetNode.textContent = internetLabel;
    }

    if (batteryNode) {
      batteryNode.textContent = batteryLabel;
    }

    if (clockNode) {
      clockNode.textContent = renderedTime;
    }

    if (internetItem) {
      internetItem.classList.toggle("is-online", state.internetStatus === "online");
      internetItem.classList.toggle("is-offline", state.internetStatus === "offline");
      internetItem.classList.toggle("is-unknown", state.internetStatus === "unknown");
      internetItem.title = internetLabel;
      internetItem.setAttribute("aria-label", internetLabel);
    }

    if (batteryItem) {
      const isUnavailable = !state.batteryAvailable;
      const isCritical = state.batteryAvailable && state.batteryLevelBand === "critical";
      const isCharging = state.batteryAvailable && state.batteryStatus === "charging";
      const isDischarging = state.batteryAvailable && state.batteryStatus === "discharging";
      const isFull = state.batteryAvailable && state.batteryStatus === "full";
      const isUnknown = state.batteryAvailable && !isCharging && !isDischarging && !isFull && !isCritical;

      batteryItem.classList.toggle("is-unavailable", isUnavailable);
      batteryItem.classList.toggle("is-critical", isCritical);
      batteryItem.classList.toggle("is-charging", isCharging);
      batteryItem.classList.toggle("is-discharging", isDischarging);
      batteryItem.classList.toggle("is-full", isFull);
      batteryItem.classList.toggle("is-unknown", isUnknown);
      batteryItem.title = batteryLabel;
      batteryItem.setAttribute("aria-label", batteryLabel);
    }

    if (timeItem) {
      const timeLabel = state.timeSource === "server"
        ? `Heure serveur ${renderedTime}`
        : `Heure locale de secours ${renderedTime}`;
      timeItem.title = timeLabel;
      timeItem.setAttribute("aria-label", timeLabel);
    }

    container.classList.toggle("is-stale", !state.lastSyncSucceeded);
    container.dataset.batteryBand = state.batteryLevelBand;
    container.dataset.batteryStatus = state.batteryStatus;
    container.dataset.timeSource = state.timeSource;
    container.title = state.lastSyncSucceeded
      ? "Etat serveur mis a jour"
      : "Etat serveur indisponible, heure locale de secours active";
  }

  function tickClock() {
    state.secondsSinceMidnight = typeof state.secondsSinceMidnight === "number"
      ? state.secondsSinceMidnight + 1
      : getLocalSecondsSinceMidnight();
    applyState();
  }

  function updateFromPayload(payload) {
    if (!payload || typeof payload !== "object") {
      throw new Error("payload invalide");
    }

    const parsedTime = parseServerTime(payload.server_time);
    if (parsedTime === null) {
      warn("server_time absent ou invalide, bascule sur heure locale", payload);
      state.secondsSinceMidnight = getLocalSecondsSinceMidnight();
      state.timeSource = "local-fallback";
    } else {
      state.secondsSinceMidnight = parsedTime;
      state.timeSource = "server";
    }

    state.internetStatus = payload.internet_status || "unknown";
    state.batteryStatus = payload.battery_status || "unknown";
    state.batteryLevelBand = payload.battery_level_band || "unknown";
    state.batteryAvailable = Boolean(payload.battery_available);
    state.lastSyncSucceeded = true;
    applyState();
  }

  function markAsStale(error) {
    state.lastSyncSucceeded = false;
    state.internetStatus = "unknown";
    state.timeSource = "local-fallback";

    if (typeof state.secondsSinceMidnight !== "number") {
      state.secondsSinceMidnight = getLocalSecondsSinceMidnight();
    }

    warn("fetch endpoint en echec, heure locale de secours active", error instanceof Error ? error.message : error);
    applyState();
  }

  async function fetchStatus() {
    const controller = typeof AbortController === "function" ? new AbortController() : null;
    let timeoutId = null;

    try {
      const responsePromise = fetch(ENDPOINT_URL, {
        cache: "no-store",
        signal: controller ? controller.signal : undefined
      });

      const timeoutPromise = new Promise((_, reject) => {
        timeoutId = window.setTimeout(() => {
          if (controller) {
            controller.abort();
          }

          reject(new Error("timeout"));
        }, REQUEST_TIMEOUT_MS);
      });

      const response = await Promise.race([responsePromise, timeoutPromise]);

      if (!response || !response.ok) {
        throw new Error(response ? `HTTP ${response.status}` : "no response");
      }

      const payload = await response.json();
      updateFromPayload(payload);
    } catch (error) {
      markAsStale(error);
    } finally {
      if (timeoutId !== null) {
        window.clearTimeout(timeoutId);
      }
    }
  }

  function init() {
    const container = getContainer();
    disableThemeState();
    installRefreshButtons();

    if (!container) {
      return;
    }

    if (!getValueNode("internet") || !getValueNode("battery") || !document.getElementById("clock")) {
      warn("structure HTML incomplete pour server-status");
    }

    applyState();
    fetchStatus();
    window.setInterval(fetchStatus, POLL_INTERVAL_MS);
    window.setInterval(tickClock, 1000);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
