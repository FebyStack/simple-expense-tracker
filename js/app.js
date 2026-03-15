/**
 * Expense Tracker — Client-Side Application
 *
 * Handles:
 *  - Auth flows (login, register, logout, session check)
 *  - CSRF token injection
 *  - Expense CRUD with XSS-safe rendering
 *  - Dashboard stats (summary cards, category breakdown, monthly totals)
 *  - Search & filter
 *  - Client-side form validation
 */

// ============================================================
// Helpers
// ============================================================

/** Escape HTML to prevent XSS when inserting dynamic content. */
function esc(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str ?? '')));
    return div.innerHTML;
}

/** Format a number as Philippine Peso. */
function formatCurrency(amount) {
    return Number(amount).toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP',
    });
}

/** Generic fetch wrapper that returns JSON and throws on non-OK. */
async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    const data = await response.json();
    if (!response.ok) {
        throw new Error(data.error || 'Request failed');
    }
    return data;
}

/** Show an inline error message in an alert element. */
function showAlert(id, message) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = message;
    el.style.display = 'block';
}

/** Hide an inline alert. */
function hideAlert(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

/** Show a field-level validation error. */
function showFieldError(fieldId, msg) {
    const el = document.getElementById(fieldId);
    if (el) el.textContent = msg;
}

/** Clear all field-level errors inside a form. */
function clearFieldErrors(form) {
    form.querySelectorAll('.form-error').forEach((el) => (el.textContent = ''));
    form.querySelectorAll('.form-input.invalid').forEach((el) => el.classList.remove('invalid'));
}

// ============================================================
// Session & CSRF
// ============================================================

let csrfToken = '';

/** Fetch session info + CSRF token from the backend. */
async function fetchSession() {
    const data = await requestJson('api/session.php');
    csrfToken = data.csrf_token || '';

    // Inject token into every hidden csrf field on the page
    document.querySelectorAll('#csrfToken').forEach((el) => {
        el.value = csrfToken;
    });

    return data;
}

/** Require authentication — redirect to login if not logged in. */
async function requireAuth() {
    try {
        const session = await fetchSession();
        if (!session.logged_in) {
            window.location.href = 'login.html';
            return null;
        }

        // Display username in navbar
        const navUser = document.getElementById('navUsername');
        if (navUser) navUser.textContent = 'Hello, ' + session.username;

        return session;
    } catch {
        window.location.href = 'login.html';
        return null;
    }
}

/** Redirect to dashboard if already logged in (for auth pages). */
async function redirectIfLoggedIn() {
    try {
        const session = await fetchSession();
        if (session.logged_in) {
            window.location.href = 'dashboard.html';
        }
    } catch {
        // not logged in — stay on current page
    }
}

// ============================================================
// Logout
// ============================================================

function bindLogout() {
    const btn = document.getElementById('logoutBtn');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        try {
            await requestJson('api/logout.php', { method: 'POST' });
        } catch {
            // ignore
        }
        window.location.href = 'login.html';
    });
}

// ============================================================
// Login Page
// ============================================================

function initLoginPage() {
    const form = document.getElementById('loginForm');
    if (!form) return;

    redirectIfLoggedIn();

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearFieldErrors(form);
        hideAlert('authError');

        const email = form.email.value.trim();
        const password = form.password.value;

        // Client validation
        let valid = true;
        if (!email || !/\S+@\S+\.\S+/.test(email)) {
            showFieldError('emailError', 'Enter a valid email address.');
            valid = false;
        }
        if (!password) {
            showFieldError('passwordError', 'Password is required.');
            valid = false;
        }
        if (!valid) return;

        const formData = new FormData(form);

        try {
            const data = await requestJson('api/login.php', {
                method: 'POST',
                body: formData,
            });
            window.location.href = 'dashboard.html';
        } catch (err) {
            showAlert('authError', err.message);
        }
    });
}

// ============================================================
// Register Page
// ============================================================

function initRegisterPage() {
    const form = document.getElementById('registerForm');
    if (!form) return;

    redirectIfLoggedIn();

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearFieldErrors(form);
        hideAlert('authError');

        const username = form.username.value.trim();
        const email = form.email.value.trim();
        const password = form.password.value;
        const confirm = form.confirm_password.value;

        let valid = true;
        if (username.length < 2) {
            showFieldError('usernameError', 'Username must be at least 2 characters.');
            valid = false;
        }
        if (!email || !/\S+@\S+\.\S+/.test(email)) {
            showFieldError('emailError', 'Enter a valid email address.');
            valid = false;
        }
        if (password.length < 6) {
            showFieldError('passwordError', 'Password must be at least 6 characters.');
            valid = false;
        }
        if (password !== confirm) {
            showFieldError('confirmError', 'Passwords do not match.');
            valid = false;
        }
        if (!valid) return;

        const formData = new FormData(form);

        try {
            await requestJson('api/register.php', {
                method: 'POST',
                body: formData,
            });
            window.location.href = 'dashboard.html';
        } catch (err) {
            showAlert('authError', err.message);
        }
    });
}

// ============================================================
// Dashboard — Summary & Stats
// ============================================================

async function loadSummary() {
    try {
        const data = await requestJson('api/get_summary.php');

        const totalEl   = document.getElementById('totalAmount');
        const countEl   = document.getElementById('expenseCount');
        const highEl    = document.getElementById('highestExpense');

        if (totalEl) totalEl.textContent   = formatCurrency(data.total_amount);
        if (countEl) countEl.textContent   = data.expense_count;
        if (highEl)  highEl.textContent    = formatCurrency(data.highest_expense);

        // Category breakdown
        const catContainer = document.getElementById('categoryStats');
        if (catContainer && data.categories && data.categories.length) {
            const maxCat = Math.max(...data.categories.map((c) => Number(c.total)));
            catContainer.innerHTML = data.categories
                .map((c) => {
                    const pct = maxCat > 0 ? (Number(c.total) / maxCat) * 100 : 0;
                    return `
                        <div>
                            <div class="stat-row">
                                <span class="stat-label">${esc(c.category)}</span>
                                <span class="stat-value">${formatCurrency(c.total)}</span>
                            </div>
                            <div class="stat-bar-wrap">
                                <div class="stat-bar" style="width:${pct}%"></div>
                            </div>
                        </div>`;
                })
                .join('');
        }

        // Monthly totals
        const monthContainer = document.getElementById('monthlyStats');
        if (monthContainer && data.monthly && data.monthly.length) {
            const maxMonth = Math.max(...data.monthly.map((m) => Number(m.total)));
            monthContainer.innerHTML = data.monthly
                .map((m) => {
                    const pct = maxMonth > 0 ? (Number(m.total) / maxMonth) * 100 : 0;
                    return `
                        <div>
                            <div class="stat-row">
                                <span class="stat-label">${esc(m.month)}</span>
                                <span class="stat-value">${formatCurrency(m.total)}</span>
                            </div>
                            <div class="stat-bar-wrap">
                                <div class="stat-bar" style="width:${pct}%"></div>
                            </div>
                        </div>`;
                })
                .join('');
        }

        // Populate category filter dropdown
        const filterCat = document.getElementById('filterCategory');
        if (filterCat && data.categories) {
            // Keep the "All" option, add the rest
            const existing = filterCat.querySelector('option[value=""]');
            filterCat.innerHTML = '';
            if (existing) filterCat.appendChild(existing);
            data.categories.forEach((c) => {
                const opt = document.createElement('option');
                opt.value = c.category;
                opt.textContent = c.category;
                filterCat.appendChild(opt);
            });
        }
    } catch {
        // silently fail — cards will show defaults
    }
}

// ============================================================
// Dashboard — Expense Table
// ============================================================

async function loadExpenses(queryString = '') {
    const tableBody = document.querySelector('#expenseTable tbody');
    if (!tableBody) return;

    tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>';

    try {
        const url = 'api/get_expenses.php' + (queryString ? '?' + queryString : '');
        const data = await requestJson(url);
        const expenses = data.expenses || [];

        if (expenses.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No expenses found.</td></tr>';
            return;
        }

        tableBody.innerHTML = '';

        expenses.forEach((expense) => {
            tableBody.innerHTML += `
                <tr class="expense-row">
                    <td>${esc(expense.title)}</td>
                    <td>${esc(expense.category || '—')}</td>
                    <td>${formatCurrency(expense.amount)}</td>
                    <td>${esc(expense.date)}</td>
                    <td>${esc(expense.description || '—')}</td>
                    <td class="actions">
                        <a class="btn btn-primary btn-sm" href="edit-expense.html?id=${esc(expense.id)}">Edit</a>
                        <button class="btn btn-danger btn-sm" data-id="${esc(expense.id)}" data-action="delete">Delete</button>
                    </td>
                </tr>`;
        });
    } catch (err) {
        tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">${esc(err.message)}</td></tr>`;
    }
}

// ============================================================
// Dashboard — Delete handler (event delegation)
// ============================================================

function bindDeleteHandler() {
    const table = document.getElementById('expenseTable');
    if (!table) return;

    table.addEventListener('click', async (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement) || target.dataset.action !== 'delete') return;

        const id = target.dataset.id;
        if (!id || !window.confirm('Are you sure you want to delete this expense?')) return;

        const formData = new FormData();
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);

        try {
            await requestJson('api/delete_expense.php', {
                method: 'POST',
                body: formData,
            });
            await loadExpenses(buildFilterQuery());
            await loadSummary();
        } catch (err) {
            alert(err.message);
        }
    });
}

// ============================================================
// Dashboard — Search / Filter
// ============================================================

function buildFilterQuery() {
    const params = new URLSearchParams();

    const search    = document.getElementById('filterSearch');
    const category  = document.getElementById('filterCategory');
    const dateFrom  = document.getElementById('filterDateFrom');
    const dateTo    = document.getElementById('filterDateTo');
    const amountMin = document.getElementById('filterAmountMin');
    const amountMax = document.getElementById('filterAmountMax');

    if (search && search.value.trim())    params.set('search', search.value.trim());
    if (category && category.value)       params.set('category', category.value);
    if (dateFrom && dateFrom.value)       params.set('date_from', dateFrom.value);
    if (dateTo && dateTo.value)           params.set('date_to', dateTo.value);
    if (amountMin && amountMin.value)     params.set('amount_min', amountMin.value);
    if (amountMax && amountMax.value)     params.set('amount_max', amountMax.value);

    return params.toString();
}

function bindFilters() {
    const applyBtn = document.getElementById('applyFilters');
    const clearBtn = document.getElementById('clearFilters');

    if (applyBtn) {
        applyBtn.addEventListener('click', () => {
            loadExpenses(buildFilterQuery());
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            ['filterSearch', 'filterDateFrom', 'filterDateTo', 'filterAmountMin', 'filterAmountMax'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            const cat = document.getElementById('filterCategory');
            if (cat) cat.value = '';
            loadExpenses();
        });
    }

    // Allow pressing Enter in search field
    const searchInput = document.getElementById('filterSearch');
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadExpenses(buildFilterQuery());
            }
        });
    }
}

// ============================================================
// Dashboard — Init
// ============================================================

async function initDashboard() {
    const session = await requireAuth();
    if (!session) return;

    bindLogout();
    await loadSummary();
    await loadExpenses();
    bindDeleteHandler();
    bindFilters();
}

// ============================================================
// Add Expense Page
// ============================================================

async function initAddExpense() {
    const session = await requireAuth();
    if (!session) return;
    bindLogout();

    const form = document.getElementById('expenseForm');
    if (!form) return;

    // Default date to today
    const dateInput = form.querySelector('input[name="date"]');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearFieldErrors(form);
        hideAlert('formError');

        const title  = form.title.value.trim();
        const amount = form.amount.value;
        const date   = form.date.value;

        let valid = true;
        if (!title) {
            showFieldError('titleError', 'Title is required.');
            valid = false;
        }
        if (!amount || isNaN(amount) || Number(amount) <= 0) {
            showFieldError('amountError', 'Enter a valid positive amount.');
            valid = false;
        }
        if (!date) {
            showFieldError('dateError', 'Date is required.');
            valid = false;
        }
        if (!valid) return;

        const formData = new FormData(form);

        try {
            await requestJson('api/add_expense.php', {
                method: 'POST',
                body: formData,
            });
            window.location.href = 'dashboard.html';
        } catch (err) {
            showAlert('formError', err.message);
        }
    });
}

// ============================================================
// Edit Expense Page
// ============================================================

async function initEditExpense() {
    const session = await requireAuth();
    if (!session) return;
    bindLogout();

    const form = document.getElementById('editExpenseForm');
    if (!form) return;

    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (!id) {
        alert('Expense ID is missing.');
        window.location.href = 'dashboard.html';
        return;
    }

    form.querySelector('input[name="id"]').value = id;

    // Load existing data
    try {
        const data = await requestJson('api/get_expenses.php');
        const expense = (data.expenses || []).find((item) => String(item.id) === id);

        if (!expense) {
            alert('Expense not found.');
            window.location.href = 'dashboard.html';
            return;
        }

        form.querySelector('input[name="title"]').value = expense.title;
        form.querySelector('input[name="amount"]').value = expense.amount;
        form.querySelector('input[name="date"]').value = expense.date;
        form.querySelector('textarea[name="description"]').value = expense.description || '';

        // Set category dropdown
        const catSelect = form.querySelector('select[name="category"]');
        if (catSelect) {
            const catValue = expense.category || '';
            // If the category doesn't exist in options, add it
            let found = false;
            for (const opt of catSelect.options) {
                if (opt.value === catValue) { found = true; break; }
            }
            if (!found && catValue) {
                const opt = document.createElement('option');
                opt.value = catValue;
                opt.textContent = catValue;
                catSelect.appendChild(opt);
            }
            catSelect.value = catValue;
        }
    } catch (err) {
        showAlert('formError', err.message);
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearFieldErrors(form);
        hideAlert('formError');

        const title  = form.title.value.trim();
        const amount = form.amount.value;
        const date   = form.date.value;

        let valid = true;
        if (!title) {
            showFieldError('titleError', 'Title is required.');
            valid = false;
        }
        if (!amount || isNaN(amount) || Number(amount) <= 0) {
            showFieldError('amountError', 'Enter a valid positive amount.');
            valid = false;
        }
        if (!date) {
            showFieldError('dateError', 'Date is required.');
            valid = false;
        }
        if (!valid) return;

        const formData = new FormData(form);

        try {
            await requestJson('api/update_expense.php', {
                method: 'POST',
                body: formData,
            });
            window.location.href = 'dashboard.html';
        } catch (err) {
            showAlert('formError', err.message);
        }
    });
}

// ============================================================
// Index page — redirect
// ============================================================

async function initIndex() {
    try {
        const session = await fetchSession();
        window.location.href = session.logged_in ? 'dashboard.html' : 'login.html';
    } catch {
        window.location.href = 'login.html';
    }
}

// ============================================================
// Router — detect page and run appropriate init
// ============================================================

(function () {
    const page = window.location.pathname.split('/').pop() || 'index.html';

    switch (page) {
        case 'login.html':
            initLoginPage();
            break;
        case 'register.html':
            initRegisterPage();
            break;
        case 'dashboard.html':
            initDashboard();
            break;
        case 'add-expense.html':
            initAddExpense();
            break;
        case 'edit-expense.html':
            initEditExpense();
            break;
        case 'index.html':
        case '':
            initIndex();
            break;
        default:
            initIndex();
    }
})();
