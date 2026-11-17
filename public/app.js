const state = {
    section: "overview",
    customers: [],
    products: [],
    errors: {},
    search: { customers: "", products: "" },
    status: { customers: "all", products: "all" },
    dialog: null,
};

const customerFields = [
    { name: "customer_code", label: "Customer code", required: true, maxLength: 30 },
    { name: "name", label: "Name", required: true, maxLength: 150 },
    { name: "phone", label: "Phone", maxLength: 30 },
    { name: "email", label: "Email", type: "email", maxLength: 150 },
    { name: "credit_limit", label: "Credit limit", type: "number", min: "0", step: "0.01", defaultValue: "0.00" },
    { name: "opening_balance", label: "Opening balance", type: "number", min: "0", step: "0.01", defaultValue: "0.00" },
    { name: "address", label: "Address", type: "textarea", fullWidth: true },
    { name: "status", label: "Status", type: "select", options: ["active", "inactive"], defaultValue: "active" },
];

const productFields = [
    { name: "sku", label: "SKU", required: true, maxLength: 50 },
    { name: "name", label: "Product name", required: true, maxLength: 200 },
    { name: "unit", label: "Unit", required: true, maxLength: 20, defaultValue: "pcs" },
    { name: "status", label: "Status", type: "select", options: ["active", "inactive"], defaultValue: "active" },
    { name: "cost_price", label: "Cost price", type: "number", min: "0", step: "0.01", defaultValue: "0.00" },
    { name: "selling_price", label: "Selling price", type: "number", min: "0", step: "0.01", defaultValue: "0.00" },
    { name: "stock_quantity", label: "Stock quantity", type: "number", min: "0", step: "0.001", defaultValue: "0.000" },
    { name: "reorder_level", label: "Reorder level", type: "number", min: "0", step: "0.001", defaultValue: "0.000" },
    { name: "description", label: "Description", type: "textarea", fullWidth: true },
];

const dialog = document.querySelector("#record-dialog");
const form = document.querySelector("#record-form");
let toastTimer;

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (character) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#39;",
    })[character]);
}

function formatAmount(value) {
    const amount = Number(value);
    if (!Number.isFinite(amount)) return "—";
    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

function statusBadge(status) {
    const normalized = status === "active" ? "active" : "inactive";
    return `<span class="status-badge status-${normalized}">${normalized}</span>`;
}

async function request(path, options = {}) {
    let response;
    try {
        response = await fetch(path, {
            ...options,
            headers: {
                Accept: "application/json",
                ...(options.body ? { "Content-Type": "application/json" } : {}),
                ...options.headers,
            },
        });
    } catch {
        throw new Error("Could not reach the API. Check that the PHP server is running.");
    }

    if (response.status === 204) return null;

    const text = await response.text();
    let result = null;
    if (text !== "") {
        try {
            result = JSON.parse(text);
        } catch {
            throw new Error(`The API returned an invalid response (HTTP ${response.status}).`);
        }
    }

    if (!response.ok) {
        const detail = result?.error || `Request failed (HTTP ${response.status}).`;
        const fields = result?.fields && typeof result.fields === "object"
            ? ` ${Object.entries(result.fields).map(([field, message]) => `${field}: ${message}`).join("; ")}`
            : "";
        throw new Error(`${detail}${fields}`);
    }

    return result;
}

function setConnection(connected, message) {
    const dot = document.querySelector("#connection-dot");
    dot.classList.toggle("connected", connected);
    dot.classList.toggle("disconnected", !connected);
    document.querySelector("#connection-label").textContent = message;
}

async function loadRecords(type) {
    state.errors[type] = "";
    try {
        const result = await request(`/api/${type}`);
        if (!Array.isArray(result?.data)) {
            throw new Error(`The ${type} API response did not contain a data list.`);
        }
        state[type] = result.data;
        delete state.errors[type];
    } catch (error) {
        state.errors[type] = error.message;
    }

    if (state.errors.customers || state.errors.products) {
        const available = (type === "customers" ? state.products : state.customers).length > 0;
        setConnection(available, available ? "API partly available" : "API unavailable");
    } else {
        setConnection(true, "API connected");
    }

    render();
}

function renderOverview() {
    const activeCustomers = state.customers.filter((customer) => customer.status === "active").length;
    const activeProducts = state.products.filter((product) => product.status === "active").length;
    document.querySelector("#total-customers").textContent = state.errors.customers ? "!" : state.customers.length;
    document.querySelector("#total-products").textContent = state.errors.products ? "!" : state.products.length;
    document.querySelector("#active-records").textContent =
        state.errors.customers || state.errors.products ? "!" : activeCustomers + activeProducts;
    document.querySelector("#customer-count").textContent = state.errors.customers ? "!" : state.customers.length;
    document.querySelector("#product-count").textContent = state.errors.products ? "!" : state.products.length;
}

function filteredRecords(type) {
    const query = state.search[type].trim().toLowerCase();
    return state[type].filter((record) => {
        const matchesStatus = state.status[type] === "all" || record.status === state.status[type];
        const searchable = type === "customers"
            ? [record.customer_code, record.name, record.phone, record.email]
            : [record.sku, record.name, record.description, record.unit];
        const matchesQuery = query === "" || searchable.some((value) => String(value ?? "").toLowerCase().includes(query));
        return matchesStatus && matchesQuery;
    });
}

function renderCustomers() {
    const rows = document.querySelector("#customer-rows");
    const empty = document.querySelector("#customer-state");
    if (state.errors.customers) {
        rows.innerHTML = "";
        empty.textContent = `Unable to load customers: ${state.errors.customers}`;
        empty.hidden = false;
        return;
    }

    const customers = filteredRecords("customers");
    rows.innerHTML = customers.map((customer) => {
        const initials = String(customer.name ?? "?").trim().slice(0, 1).toUpperCase();
        const contact = customer.email || customer.phone || "No contact details";
        return `<tr>
            <td>${escapeHtml(customer.customer_code)}</td>
            <td><span class="primary-cell"><span class="avatar">${escapeHtml(initials)}</span><span>${escapeHtml(customer.name)}</span></span></td>
            <td>${escapeHtml(contact)}</td>
            <td>${escapeHtml(formatAmount(customer.credit_limit))}</td>
            <td>${statusBadge(customer.status)}</td>
            <td><div class="row-actions">
                <button class="text-button" type="button" data-edit="customers" data-id="${escapeHtml(customer.id)}">Edit</button>
                <button class="text-button danger" type="button" data-delete-customer="${escapeHtml(customer.id)}">Delete</button>
            </div></td>
        </tr>`;
    }).join("");

    empty.textContent = customers.length === 0 ? "No customers match your search." : "";
    empty.hidden = customers.length !== 0;
}

function renderProducts() {
    const rows = document.querySelector("#product-rows");
    const empty = document.querySelector("#product-state");
    if (state.errors.products) {
        rows.innerHTML = "";
        empty.textContent = `Unable to load products: ${state.errors.products}`;
        empty.hidden = false;
        return;
    }

    const products = filteredRecords("products");
    rows.innerHTML = products.map((product) => {
        const initials = String(product.name ?? "?").trim().slice(0, 1).toUpperCase();
        return `<tr>
            <td>${escapeHtml(product.sku)}</td>
            <td><span class="primary-cell"><span class="avatar">${escapeHtml(initials)}</span><span>${escapeHtml(product.name)}<small class="sub-cell">${escapeHtml(product.unit || "pcs")}</small></span></span></td>
            <td>${escapeHtml(product.stock_quantity)} ${escapeHtml(product.unit || "")}</td>
            <td>${escapeHtml(formatAmount(product.selling_price))}</td>
            <td>${statusBadge(product.status)}</td>
            <td><div class="row-actions">
                <button class="text-button" type="button" data-edit="products" data-id="${escapeHtml(product.id)}">Edit</button>
            </div></td>
        </tr>`;
    }).join("");

    empty.textContent = products.length === 0 ? "No products match your search." : "";
    empty.hidden = products.length !== 0;
}

function render() {
    renderOverview();
    renderCustomers();
    renderProducts();
}

function changeSection(section) {
    state.section = section;
    document.querySelectorAll(".view").forEach((view) => {
        const active = view.id === `${section}-view`;
        view.classList.toggle("active", active);
        view.hidden = !active;
    });
    document.querySelectorAll(".nav-link").forEach((link) => {
        link.classList.toggle("active", link.dataset.section === section);
    });
    document.querySelector("#page-title").textContent =
        section === "overview" ? "Overview" : section[0].toUpperCase() + section.slice(1);
    const type = section === "products" ? "products" : "customers";
    document.querySelector("#top-add-label").textContent = `Add ${section === "products" ? "product" : "customer"}`;
    document.querySelector("#top-add-button").hidden = section === "overview";
    document.querySelector("#top-add-button").dataset.add = type;
}

function fieldMarkup(field, value) {
    const required = field.required ? " required" : "";
    const maxLength = field.maxLength ? ` maxlength="${field.maxLength}"` : "";
    const fullWidth = field.fullWidth ? " full-width" : "";
    const currentValue = value ?? field.defaultValue ?? "";
    let control;

    if (field.type === "select") {
        const options = field.options.map((option) =>
            `<option value="${option}"${String(currentValue) === option ? " selected" : ""}>${option[0].toUpperCase() + option.slice(1)}</option>`
        ).join("");
        control = `<select id="field-${field.name}" name="${field.name}"${required}>${options}</select>`;
    } else if (field.type === "textarea") {
        control = `<textarea id="field-${field.name}" name="${field.name}"${required}>${escapeHtml(currentValue)}</textarea>`;
    } else {
        const type = field.type || "text";
        const numeric = type === "number"
            ? ` min="${field.min}" step="${field.step}"`
            : "";
        control = `<input id="field-${field.name}" name="${field.name}" type="${type}" value="${escapeHtml(currentValue)}"${numeric}${maxLength}${required}>`;
    }

    return `<label class="form-field${fullWidth}" for="field-${field.name}">${field.label}${control}</label>`;
}

function openEditor(type, record = null) {
    const fields = type === "customers" ? customerFields : productFields;
    state.dialog = { type, id: record?.id ?? null };
    document.querySelector("#dialog-eyebrow").textContent = record ? "UPDATE RECORD" : "NEW RECORD";
    document.querySelector("#dialog-title").textContent = `${record ? "Edit" : "Add"} ${type === "customers" ? "customer" : "product"}`;
    document.querySelector("#dialog-submit").textContent = `Save ${type === "customers" ? "customer" : "product"}`;
    document.querySelector("#form-fields").innerHTML =
        fields.map((field) => fieldMarkup(field, record?.[field.name])).join("");
    form.reset();
    dialog.showModal();
}

function closeEditor() {
    if (dialog.open) dialog.close();
    state.dialog = null;
}

function showToast(message, isError = false) {
    const toast = document.querySelector("#toast");
    toast.textContent = message;
    toast.classList.toggle("error", isError);
    toast.classList.add("visible");
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove("visible"), 3500);
}

async function saveRecord(event) {
    event.preventDefault();
    if (!state.dialog) return;

    const { type, id } = state.dialog;
    const fields = type === "customers" ? customerFields : productFields;
    const values = new FormData(form);
    const data = Object.fromEntries(fields.map((field) => {
        const value = values.get(field.name);
        return [field.name, value === "" && ["phone", "email", "address", "description"].includes(field.name) ? null : value];
    }));
    const button = document.querySelector("#dialog-submit");
    button.disabled = true;
    button.textContent = "Saving…";

    try {
        await request(`/api/${type}${id ? `/${encodeURIComponent(id)}` : ""}`, {
            method: id ? "PUT" : "POST",
            body: JSON.stringify(data),
        });
        closeEditor();
        await loadRecords(type);
        showToast(`${type === "customers" ? "Customer" : "Product"} ${id ? "updated" : "created"} successfully.`);
    } catch (error) {
        showToast(error.message, true);
    } finally {
        button.disabled = false;
        button.textContent = `Save ${type === "customers" ? "customer" : "product"}`;
    }
}

async function deleteCustomer(id) {
    const customer = state.customers.find((item) => String(item.id) === String(id));
    const name = customer?.name || "this customer";
    if (!window.confirm(`Delete ${name}? This action cannot be undone.`)) return;

    try {
        await request(`/api/customers/${encodeURIComponent(id)}`, { method: "DELETE" });
        await loadRecords("customers");
        showToast("Customer deleted successfully.");
    } catch (error) {
        showToast(error.message, true);
    }
}

document.querySelectorAll("[data-section]").forEach((button) => {
    button.addEventListener("click", () => changeSection(button.dataset.section));
});

document.querySelectorAll("[data-add]").forEach((button) => {
    button.addEventListener("click", () => openEditor(button.dataset.add));
});

document.querySelectorAll("[data-refresh]").forEach((button) => {
    button.addEventListener("click", () => loadRecords(button.dataset.refresh));
});

document.querySelector("#top-add-button").addEventListener("click", (event) => {
    openEditor(event.currentTarget.dataset.add);
});

document.querySelector("#customer-search").addEventListener("input", (event) => {
    state.search.customers = event.target.value;
    renderCustomers();
});
document.querySelector("#product-search").addEventListener("input", (event) => {
    state.search.products = event.target.value;
    renderProducts();
});
document.querySelector("#customer-status-filter").addEventListener("change", (event) => {
    state.status.customers = event.target.value;
    renderCustomers();
});
document.querySelector("#product-status-filter").addEventListener("change", (event) => {
    state.status.products = event.target.value;
    renderProducts();
});

document.querySelector("#customer-rows").addEventListener("click", (event) => {
    const edit = event.target.closest("[data-edit]");
    const remove = event.target.closest("[data-delete-customer]");
    if (edit) {
        const customer = state.customers.find((item) => String(item.id) === edit.dataset.id);
        if (customer) openEditor("customers", customer);
    }
    if (remove) deleteCustomer(remove.dataset.deleteCustomer);
});

document.querySelector("#product-rows").addEventListener("click", (event) => {
    const edit = event.target.closest("[data-edit]");
    if (!edit) return;
    const product = state.products.find((item) => String(item.id) === edit.dataset.id);
    if (product) openEditor("products", product);
});

form.addEventListener("submit", saveRecord);
document.querySelector("#dialog-close").addEventListener("click", closeEditor);
document.querySelector("#dialog-cancel").addEventListener("click", closeEditor);
dialog.addEventListener("click", (event) => {
    if (event.target === dialog) closeEditor();
});

document.querySelector("#today-label").textContent = new Intl.DateTimeFormat(undefined, {
    weekday: "short",
    month: "short",
    day: "numeric",
}).format(new Date());
changeSection("overview");
render();
Promise.all([loadRecords("customers"), loadRecords("products")]);
