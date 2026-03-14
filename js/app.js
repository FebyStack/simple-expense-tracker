fetch('api/get_expenses.php')
.then(res => res.json())
.then(data => {

const table = document.querySelector("#expenseTable tbody");

data.forEach(expense => {

table.innerHTML += `
<tr>
<td>${expense.title}</td>
<td>${expense.category}</td>
<td>${expense.amount}</td>
<td>${expense.date}</td>
<td>
<button onclick="editExpense(${expense.id})">Edit</button>
<button onclick="deleteExpense(${expense.id})">Delete</button>
</td>
</tr>
`;

});

});