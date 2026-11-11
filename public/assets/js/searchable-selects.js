(() => {
  "use strict";

  const controls = new WeakMap();
  let nextId = 0;
  const normalize = (value) => String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();

  function uniqueId() {
    let id;
    do {
      id = `searchable-select-${++nextId}`;
    } while (document.getElementById(id));
    return id;
  }

  function initialize(select) {
    if (controls.has(select)) return;

    const label = select.closest("label") || select.labels?.[0];
    const labelText = select.getAttribute("aria-label") ||
      label?.querySelector("span")?.textContent.trim() ||
      label?.textContent.trim().split("\n")[0].trim() ||
      "opciones";
    let wrapper = select.parentElement;
    if (!wrapper.classList.contains("searchable-select")) {
      wrapper = document.createElement("div");
      wrapper.className = "searchable-select";
      select.before(wrapper);
      wrapper.appendChild(select);
    }

    // Cloned rows retain markup but not event listeners.
    wrapper.querySelectorAll(":scope > .searchable-select-input, :scope > .searchable-select-list, :scope > .searchable-select-error")
      .forEach((element) => element.remove());
    select.classList.add("searchable-select-native");
    select.tabIndex = -1;
    select.setAttribute("aria-hidden", "true");

    const input = document.createElement("input");
    input.type = "text";
    input.autocomplete = "off";
    input.className = "searchable-select-input";
    input.id = uniqueId();
    input.setAttribute("role", "combobox");
    input.setAttribute("aria-autocomplete", "list");
    input.setAttribute("aria-expanded", "false");
    input.setAttribute("aria-label", labelText.replace(/\s*\*$/, ""));
    if (label) label.htmlFor = input.id;

    const list = document.createElement("div");
    list.id = uniqueId();
    list.className = "searchable-select-list";
    list.setAttribute("role", "listbox");
    if (select.multiple) list.setAttribute("aria-multiselectable", "true");
    input.setAttribute("aria-controls", list.id);
    list.hidden = true;
    const error = document.createElement("small");
    error.id = uniqueId();
    error.className = "searchable-select-error";
    error.setAttribute("role", "alert");
    error.hidden = true;
    wrapper.append(input, list, error);

    let active = -1;
    let options = [];
    let query = "";
    let blurTimer;
    let choosing = false;
    const selectedText = () => Array.from(select.selectedOptions)
      .filter((option) => option.value !== "")
      .map((option) => option.textContent.trim()).join(", ");
    const placeholder = () => select.id === "sale-owner" ? "Buscar propietario…" :
      select.id === "sale-patient" ? "Buscar paciente…" :
      (select.querySelector('option[value=""]')?.textContent.trim() || "Seleccionar");

    function sync() {
      input.disabled = select.matches(":disabled");
      input.hidden = select.hidden;
      input.setAttribute("aria-required", String(select.required));
      input.placeholder = placeholder();
      if (select.validity.valid || select.disabled) {
        input.removeAttribute("aria-invalid");
        input.removeAttribute("aria-describedby");
        error.hidden = true;
      }
      if (list.hidden) input.value = selectedText();
      if (input.disabled || input.hidden) close();
      else if (!list.hidden) render(query);
    }

    function close() {
      list.hidden = true;
      input.setAttribute("aria-expanded", "false");
      input.removeAttribute("aria-activedescendant");
      wrapper.classList.remove("is-open");
      input.value = selectedText();
      active = -1;
      query = "";
    }

    function choose(option) {
      if (select.multiple) option.selected = !option.selected;
      else select.value = option.value;
      choosing = true;
      select.dispatchEvent(new Event("change", { bubbles: true }));
      choosing = false;
      input.focus();
      if (select.multiple) {
        input.value = "";
        query = "";
        render("");
      } else {
        close();
      }
    }

    function highlight(index) {
      const items = list.querySelectorAll('[role="option"]');
      if (!items.length) return;
      active = Math.max(0, Math.min(index, items.length - 1));
      items.forEach((item, i) => item.classList.toggle("is-active", i === active));
      input.setAttribute("aria-activedescendant", items[active].id);
      items[active].scrollIntoView({ block: "nearest" });
    }

    function render(filter) {
      const term = normalize(filter.trim());
      options = Array.from(select.options).filter((option) => {
        const group = option.parentElement;
        return !option.hidden && !option.disabled &&
          !(group.tagName === "OPTGROUP" && (group.disabled || group.hidden)) &&
          normalize(`${option.textContent} ${option.dataset.search || ""}`).includes(term);
      });
      list.replaceChildren();
      active = -1;
      input.removeAttribute("aria-activedescendant");
      options.forEach((option, index) => {
        const item = document.createElement("div");
        item.id = `${list.id}-option-${index}`;
        item.className = "searchable-select-option";
        item.classList.toggle("is-selected", option.selected);
        item.setAttribute("role", "option");
        item.setAttribute("aria-selected", String(option.selected));
        item.textContent = option.textContent.trim();
        item.addEventListener("mousedown", (event) => event.preventDefault());
        item.addEventListener("click", (event) => {
          event.preventDefault();
          choose(option);
        });
        list.appendChild(item);
      });
      if (!options.length) {
        const empty = document.createElement("div");
        empty.className = "searchable-select-empty";
        empty.textContent = "Sin resultados";
        list.appendChild(empty);
      }
    }

    function open() {
      if (input.disabled || input.hidden) return;
      list.hidden = false;
      input.setAttribute("aria-expanded", "true");
      wrapper.classList.add("is-open");
      query = "";
      render("");
      input.select();
    }

    input.addEventListener("focus", () => {
      window.clearTimeout(blurTimer);
      open();
    });
    input.addEventListener("click", () => { if (list.hidden) open(); });
    input.addEventListener("input", () => {
      if (list.hidden) {
        list.hidden = false;
        input.setAttribute("aria-expanded", "true");
        wrapper.classList.add("is-open");
      }
      query = input.value;
      render(query);
    });
    input.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !list.hidden) {
        event.preventDefault();
        event.stopPropagation();
        close();
      } else if (event.key === "ArrowDown" || event.key === "ArrowUp") {
        event.preventDefault();
        if (list.hidden) open();
        highlight(active < 0 && event.key === "ArrowUp" ? options.length - 1 :
          active + (event.key === "ArrowDown" ? 1 : -1));
      } else if (event.key === "Enter" && !list.hidden) {
        event.preventDefault();
        if (options.length) choose(options[active < 0 ? 0 : active]);
      }
    });
    input.addEventListener("blur", () => { blurTimer = window.setTimeout(close, 120); });
    select.addEventListener("change", () => {
      input.removeAttribute("aria-invalid");
      input.removeAttribute("aria-describedby");
      error.hidden = true;
      if (!choosing || !select.multiple) close();
      sync();
    });
    select.addEventListener("invalid", (event) => {
      event.preventDefault();
      error.textContent = select.validationMessage;
      error.hidden = false;
      input.setAttribute("aria-invalid", "true");
      input.setAttribute("aria-describedby", error.id);
      input.focus();
    });
    select.addEventListener("focus", () => input.focus());
    controls.set(select, sync);
    sync();
  }

  function syncTree(root) {
    if (!root?.querySelectorAll) return;
    if (root instanceof HTMLSelectElement) controls.get(root)?.();
    root.querySelectorAll("select").forEach((select) => controls.get(select)?.());
  }

  window.SearchableSelects = { sync: syncTree };

  function initializeTree(node) {
    if (!(node instanceof Element) || !node.isConnected) return;
    if (node.matches("select")) initialize(node);
    node.querySelectorAll("select").forEach(initialize);
  }

  function start() {
    document.querySelectorAll("select").forEach(initialize);
    document.addEventListener("reset", (event) => {
      window.setTimeout(() => { if (!event.defaultPrevented) syncTree(event.target); }, 0);
    });
    // View handlers often populate edit forms programmatically during these events.
    ["click", "change"].forEach((name) => document.addEventListener(name, () => {
      queueMicrotask(() => syncTree(document));
    }, true));

    const observer = new MutationObserver((records) => {
      const changed = new Set();
      for (const record of records) {
        if (record.type === "childList") record.addedNodes.forEach(initializeTree);
        const target = record.target instanceof Element ? record.target : record.target.parentElement;
        const select = target?.closest("select");
        if (select && controls.has(select)) changed.add(select);
        if (target?.matches("fieldset")) syncTree(target);
      }
      changed.forEach((select) => controls.get(select)());
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ["disabled", "hidden", "required", "label", "data-search", "selected"],
      characterData: true,
    });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
