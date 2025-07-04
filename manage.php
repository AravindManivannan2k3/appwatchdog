<?php
require 'config.php';

// Insert
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add'])) {
    $username = $_POST['username'];
    $department = $_POST['department'];
    $stmt = $pdo->prepare("INSERT INTO user_to_dept (username, department) VALUES (?, ?)");
    $stmt->execute([$username, $department]);
}

// Delete
if (isset($_GET['delete'])) {
    $usernameToDelete = $_GET['delete'];
    
    // Perform the delete operation...
    $stmt = $pdo->prepare("DELETE FROM user_to_dept WHERE username = ?");
    $stmt->execute([$usernameToDelete]);

    // Redirect to clear the query string
    header("Location: manage.php");
    exit;
}


// Fetch data
$stmt = $pdo->query("SELECT * FROM user_to_dept");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User to Department</title>
    <style>
        /* Place the full CSS you provided here */
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: #2e2e2e;
            padding: 20px;
            margin: 0;
            padding-bottom: 60px;
        }
        h1, h2 {
            margin-bottom: 20px;
            color: #3a7bd5;
        }
        form {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            width: 100%;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
            margin-bottom: 15px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            flex: 1 1 200px;
            min-width: 180px;
        }
        .filter-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #444;
        }
        .filter-group input,
.filter-group select {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    background-color: #fff;
    width: 100%;
    height: 38px; /* optional, to make dropdown match input height */
}

        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        button,
        .clear-button {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: bold;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        button {
            background-color: #28a745;
            color: white;
        }
        button:hover {
            background-color: #218838;
        }
        .clear-button {
            background-color: #e63946;
            color: white;
        }
        .clear-button:hover {
            background-color: #c82333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            margin-top: 20px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.05);
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            text-align: left;
            font-size: 14px;
        }
        th {
            background-color: #3a7bd5;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 13px;
        }
        tr:nth-child(even) {
            background-color: #f6f8fb;
        }
        tr:hover {
            background-color: #e3efff;
        }
        th:first-child,
        td:first-child {
            text-align: center;
            width: 80px;
            font-weight: bold;
            background-color: #f1f3f5;
        }
        th:first-child {
            color: #2e2e2e;
        }
        td a {
            font-size: 12px;
            color: #3a7bd5;
            text-decoration: none;
        }
        td a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
            }
            table, thead, tbody, th, td, tr {
                display: block;
            }
            th {
                display: none;
            }
            td {
                position: relative;
                padding-left: 50%;
                border: 1px solid #dee2e6;
                margin-bottom: 10px;
            }
            td::before {
                position: absolute;
                left: 15px;
                top: 12px;
                white-space: nowrap;
                font-weight: bold;
                color: #555;
                content: attr(data-label);
            }
        }
    </style>
</head>
<body>

<h2>User to Department Mapping</h2>

<form method="post">
    <div class="filter-row">
        <div class="filter-group">
            <label for="username">Username</label>
            <input type="text" name="username" required>
        </div>
        <div class="filter-group">
            <label for="department">Department</label>
            <select name="department" required>
        <option value="" disabled selected>N/A</option>
        <option value="HR">HR</option>
        <option value="IT">IT</option>
        <option value="Finance">Finance</option>
        <option value="Marketing">Marketing</option>
        <option value="Operations">Operations</option>
    </select>
        </div>
        <div class="button-row">
            <button type="submit" name="add">Add Entry</button>
        </div>
    </div>
</form>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Username</th>
            <th>Department</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; foreach ($users as $user): ?>
        <tr>
            <td data-label="#"> <?= $i++ ?> </td>
            <td data-label="Username"><?= htmlspecialchars($user['username']) ?></td>
            <td data-label="Department"><?= htmlspecialchars($user['department']) ?></td>
            <td data-label="Actions">
                <!-- <a href="edit.php?username=<?= urlencode($user['username']) ?>">Edit</a> | -->
                <a href="?delete=<?= urlencode($user['username']) ?>" onclick="return confirm('Delete this entry?')">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
