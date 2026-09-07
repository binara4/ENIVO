<?php
session_start();

// Database Configuration
$servername = "sql108.infinityfree.com";
$username = "if0_41205237";
$password = "3Cph7LuJU7gOVR";
$dbname = "if0_41205237_login";
$port = 3306;

// Disable default exception handling for mysqli
mysqli_report(MYSQLI_REPORT_OFF);

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = trim($_POST['password']);
    $action = $_POST['action'];

    if ($action === 'login') {
        $sql = "SELECT * FROM users WHERE username = '$email'";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                echo "<script>
                    localStorage.setItem('enivo_logged_in', 'true');
                    localStorage.setItem('enivo_user', '" . htmlspecialchars($email) . "');
                    window.location.href = 'index.html';
                </script>";
                exit;
            } else {
                $message = "Incorrect password.";
                header("Location: login.html?error=" . urlencode($message) . "&email=" . urlencode($email));
                exit;
            }
        } else {
            $message = "Account not found.";
            header("Location: login.html?error=" . urlencode($message) . "&email=" . urlencode($email) . "&showRegister=true");
            exit;
        }
    } elseif ($action === 'register') {
        $checkSql = "SELECT id FROM users WHERE username = '$email'";
        $checkResult = $conn->query($checkSql);
        if ($checkResult && $checkResult->num_rows > 0) {
            $message = "User already exists. Please log in.";
            header("Location: login.html?error=" . urlencode($message) . "&email=" . urlencode($email));
            exit;
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, details) VALUES ('$email', '$hashed', 'Registered User')";
            if ($conn->query($sql) === TRUE) {
                $message = "Registration successful! Please log in.";
                header("Location: login.html?success=" . urlencode($message) . "&email=" . urlencode($email));
                exit;
            } else {
                $message = "Error: " . $conn->error;
                header("Location: login.html?error=" . urlencode($message));
                exit;
            }
        }
    }
}
$conn->close();
?>