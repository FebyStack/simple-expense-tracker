async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.error || 'Request failed');
    }

    return data;
}

function formatCurrency(amount) {
    return Number(amount).toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP',
    });
}

async function loadExpenses() {
    const tableBody = document.querySelector('#expenseTable tbody');
    const totalExpenses = document.querySelector('#totalExpenses');

    if (!tableBody || !totalExpenses) {
        return;
    }

    tableBody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';

    try {
        const data = await requestJson('api/get_expenses.php');
        const expenses = data.expenses || [];

        if (expenses.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5">No expenses yet.</td></tr>';
            totalExpenses.textContent = formatCurrency(0);
            return;
        }

        tableBody.innerHTML = '';

        let total = 0;
        expenses.forEach((expense) => {
            total += Number(expense.amount);
            tableBody.innerHTML += `
                <tr class="expense-row">
                    <td>${expense.title}</td>
                    <td>${expense.category || '-'}</td>
                    <td>${formatCurrency(expense.amount)}</td>
                    <td>${expense.date}</td>
                    <td>
                        <a class="btn" href="edit-expense.html?id=${expense.id}">Edit</a>
                        <button class="btn" data-id="${expense.id}" data-action="delete">Delete</button>
                    </td>
                </tr>
            `;
        });

        totalExpenses.textContent = formatCurrency(total);
    } catch (error) {
        tableBody.innerHTML = `<tr><td colspan="5">${error.message}</td></tr>`;
    }
}

async function handleAddForm() {
    const form = document.querySelector('#expenseForm');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);

        try {
            await requestJson('api/add_expense.php', {
                method: 'POST',
                body: formData,
            });

            window.location.href = 'index.html';
        } catch (error) {
            alert(error.message);
        }
    });
}

async function handleEditForm() {
    const form = document.querySelector('#editExpenseForm');

    if (!form) {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');

    if (!id) {
        alert('Expense ID is missing.');
        window.location.href = 'index.html';
        return;
    }

    form.querySelector('input[name="id"]').value = id;

    try {
        const data = await requestJson('api/get_expenses.php');
        const expense = (data.expenses || []).find((item) => String(item.id) === id);

        if (!expense) {
            alert('Expense not found.');
            window.location.href = 'index.html';
            return;
        }

        form.querySelector('input[name="title"]').value = expense.title;
        form.querySelector('input[name="category"]').value = expense.category || '';
        form.querySelector('input[name="amount"]').value = expense.amount;
        form.querySelector('input[name="date"]').value = expense.date;
        form.querySelector('textarea[name="description"]').value = expense.description || '';
    } catch (error) {
        alert(error.message);
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);

        try {
            await requestJson('api/update_expense.php', {
                method: 'POST',
                body: formData,
            });

            window.location.href = 'index.html';
        } catch (error) {
            alert(error.message);
        }
    });
}

function handleDelete() {
    const table = document.querySelector('#expenseTable');

    if (!table) {
        return;
    }

    table.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || target.dataset.action !== 'delete') {
            return;
        }

        const id = target.dataset.id;
        if (!id) {
            return;
        }

        if (!window.confirm('Delete this expense?')) {
            return;
        }

        const formData = new FormData();
        formData.append('id', id);

        try {
            await requestJson('api/delete_expense.php', {
                method: 'POST',
                body: formData,
            });

            await loadExpenses();
        } catch (error) {
            alert(error.message);
        }
    });
}

async function init() {
    await loadExpenses();
    await handleAddForm();
    await handleEditForm();
    handleDelete();
}

init();
