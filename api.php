<?php
ini_set('display_errors', 0); // Prevent PHP warnings from breaking JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database Configuration
$servername = "sql108.infinityfree.com";
$username = "if0_41205237";
$password = "3Cph7LuJU7gOVR";
$dbname = "if0_41205237_login";
$port = 3306;

// Disable default exception handling for mysqli to prevent HTML stack traces
mysqli_report(MYSQLI_REPORT_OFF);

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Robust Request Handling: Check JSON, then POST, then GET
$jsonInput = json_decode(file_get_contents('php://input'), true);
if (!is_array($jsonInput)) $jsonInput = [];

$action = '';
if (!empty($jsonInput['action'])) $action = $jsonInput['action'];
elseif (!empty($_POST['action'])) $action = $_POST['action'];
elseif (!empty($_GET['action'])) $action = $_GET['action'];

$action = trim($action);

// Fallback: Infer action if missing (fixes issues where action param is stripped)
if (empty($action)) {
    $u = $jsonInput['username'] ?? $_POST['username'] ?? '';
    $p = $jsonInput['password'] ?? $_POST['password'] ?? '';
    $d = $jsonInput['details'] ?? $_POST['details'] ?? '';
    
    if (!empty($u) && !empty($p)) {
        $action = !empty($d) ? 'add' : 'login';
    }
}

function sendJson($success, $message, $data = []) {
    global $conn;
    if ($conn instanceof mysqli) {
        $conn->close();
    }
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// Ensure table exists
$tableSql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    details TEXT
)";
$conn->query($tableSql);

switch ($action) {
    case 'read':
        $sql = "SELECT id, username, details FROM users";
        $result = $conn->query($sql);
        $users = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }
        sendJson(true, 'Users retrieved', $users);
        break;

    case 'register': // Alias for add to prevent mismatch errors
    case 'add':
        $user = $conn->real_escape_string($jsonInput['username'] ?? $_POST['username'] ?? '');
        $pass = $jsonInput['password'] ?? $_POST['password'] ?? '';
        $details = $conn->real_escape_string($jsonInput['details'] ?? $_POST['details'] ?? '');

        if (empty($user) || empty($pass)) {
            sendJson(false, 'Username and password required');
        }

        // Check if user exists
        $checkSql = "SELECT id FROM users WHERE username = '$user'";
        $checkResult = $conn->query($checkSql);
        if ($checkResult && $checkResult->num_rows > 0) {
            sendJson(false, 'Username already exists');
        }

        $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);
        $insertSql = "INSERT INTO users (username, password, details) VALUES ('$user', '$hashedPassword', '$details')";

        if ($conn->query($insertSql) === TRUE) {
            sendJson(true, 'User created', ['id' => $conn->insert_id]);
        } else {
            sendJson(false, 'Error creating user: ' . $conn->error);
        }
        break;

    case 'login':
        $user = $conn->real_escape_string($jsonInput['username'] ?? $_POST['username'] ?? '');
        $pass = $jsonInput['password'] ?? $_POST['password'] ?? '';

        $sql = "SELECT id, username, password, details FROM users WHERE username = '$user'";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($pass, $row['password'])) {
                unset($row['password']);
                sendJson(true, 'Login successful', $row);
            } else {
                sendJson(false, 'Invalid credentials');
            }
        } else {
            sendJson(false, 'User not found');
        }
        break;

    case 'edit':
        $id = $conn->real_escape_string($jsonInput['id'] ?? $_POST['id'] ?? '');
        $details = $conn->real_escape_string($jsonInput['details'] ?? $_POST['details'] ?? '');
        
        if (empty($id)) sendJson(false, 'User ID required');

        $sql = "UPDATE users SET details = '$details' WHERE id = '$id'";
        if ($conn->query($sql) === TRUE) {
            sendJson(true, 'User updated');
        } else {
            sendJson(false, 'Error updating user: ' . $conn->error);
        }
        break;

    case 'delete':
        $id = $conn->real_escape_string($jsonInput['id'] ?? $_POST['id'] ?? '');
        if (empty($id)) sendJson(false, 'User ID required');

        $sql = "DELETE FROM users WHERE id = '$id'";
        if ($conn->query($sql) === TRUE) {
            if ($conn->affected_rows > 0) {
                sendJson(true, 'User deleted');
            } else {
                sendJson(false, 'User not found');
            }
        } else {
            sendJson(false, 'Error deleting user: ' . $conn->error);
        }
        break;

    default:
        sendJson(false, "Invalid action: '$action'", ['json' => $jsonInput, 'get' => $_GET, 'post' => $_POST]);
}
?>