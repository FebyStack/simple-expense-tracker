<?php
include "db.php";

$title = $_POST['title'];
$category = $_POST['category'];
$amount = $_POST['amount'];
$date = $_POST['date'];
$description = $_POST['description'];

$sql = "INSERT INTO exptrack.expenses (title, category, amount, date, description)
VALUES ('$title','$category','$amount','$date','$description')";

$conn->query($sql);

echo "Expense Added";

?>