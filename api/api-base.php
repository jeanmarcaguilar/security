<?php
/**
 * ============================================================
 * CyberShield API - Base Configuration
 * ============================================================
 */

// Enable error reporting for debugging (but don't display errors)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include dependencies
require_once '../includes/config.php';
require_once '../includes/audit_helper.php';

// Start session
session_start();

// API Response Helper
class ApiResponse
{
    public static function success($data = null, $message = 'Success')
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    public static function error($message = 'Error', $code = 400)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    public static function unauthorized($message = 'Unauthorized')
    {
        self::error($message, 401);
    }

    public static function badRequest($message = 'Bad request')
    {
        self::error($message, 400);
    }

    public static function notFound($message = 'Not found')
    {
        self::error($message, 404);
    }

    public static function serverError($message = 'Internal server error')
    {
        self::error($message, 500);
    }
}

// Authentication Helper
class ApiAuth
{
    public static function requireAuth()
    {
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized('User not authenticated');
        }
        return $_SESSION['user_id'];
    }

    public static function requireAdmin()
    {
        $userId = self::requireAuth();

        try {
            $database = new Database();
            $db = $database->getConnection();

            $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || $user['role'] !== 'Admin') {
                ApiResponse::unauthorized('Admin access required');
            }

            return $userId;
        } catch (Exception $e) {
            ApiResponse::serverError('Authentication check failed');
        }
    }

    public static function getCurrentUser()
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        try {
            $database = new Database();
            $db = $database->getConnection();

            $stmt = $db->prepare("SELECT id, username, email, full_name, role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
}

// Input Validation Helper
class ApiValidator
{
    public static function required($fields)
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($_REQUEST[$field]) || empty($_REQUEST[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            ApiResponse::badRequest('Missing required fields: ' . implode(', ', $missing));
        }
    }

    public static function email($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ApiResponse::badRequest('Invalid email format');
        }
        return $email;
    }

    public static function sanitize($input)
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    public static function numeric($value, $min = null, $max = null)
    {
        if (!is_numeric($value)) {
            ApiResponse::badRequest('Value must be numeric');
        }

        $num = (float) $value;

        if ($min !== null && $num < $min) {
            ApiResponse::badRequest("Value must be at least $min");
        }

        if ($max !== null && $num > $max) {
            ApiResponse::badRequest("Value must be at most $max");
        }

        return $num;
    }
}

// Database Helper
class ApiDatabase
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            try {
                $database = new Database();
                self::$instance = $database->getConnection();
            } catch (Exception $e) {
                ApiResponse::serverError('Database connection failed');
            }
        }
        return self::$instance;
    }

    public static function query($sql, $params = [])
    {
        try {
            $db = self::getInstance();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
            ApiResponse::serverError('Database query failed');
        }
    }

    public static function fetch($sql, $params = [])
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function fetchAll($sql, $params = [])
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function insert($table, $data)
    {
        $db = self::getInstance();

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($data));
            return $db->lastInsertId();
        } catch (Exception $e) {
            error_log("Insert error: " . $e->getMessage());
            ApiResponse::serverError('Data insertion failed');
        }
    }

    public static function update($table, $data, $where, $whereParams = [])
    {
        $db = self::getInstance();

        $setParts = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setParts[] = "$column = ?";
            $params[] = $value;
        }

        $setClause = implode(', ', $setParts);
        $sql = "UPDATE $table SET $setClause WHERE $where";
        $params = array_merge($params, $whereParams);

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Update error: " . $e->getMessage());
            ApiResponse::serverError('Data update failed');
        }
    }

    public static function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM $table WHERE $where";

        try {
            $stmt = self::query($sql, $params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Delete error: " . $e->getMessage());
            ApiResponse::serverError('Data deletion failed');
        }
    }
}

// Rate Limiting Helper
class ApiRateLimit
{
    private static $limits = [
        'send_otp' => ['requests' => 5, 'window' => 300], // 5 requests per 5 minutes
        'login' => ['requests' => 10, 'window' => 900], // 10 requests per 15 minutes
        'default' => ['requests' => 100, 'window' => 3600] // 100 requests per hour
    ];

    public static function check($endpoint = 'default')
    {
        $limit = self::$limits[$endpoint] ?? self::$limits['default'];
        $key = $endpoint . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (!isset($_SESSION['rate_limits'][$key])) {
            $_SESSION['rate_limits'][$key] = [
                'requests' => 0,
                'reset_time' => time() + $limit['window']
            ];
        }

        $rateLimit = &$_SESSION['rate_limits'][$key];

        // Reset if window expired
        if (time() > $rateLimit['reset_time']) {
            $rateLimit['requests'] = 0;
            $rateLimit['reset_time'] = time() + $limit['window'];
        }

        // Check limit
        if ($rateLimit['requests'] >= $limit['requests']) {
            $waitTime = $rateLimit['reset_time'] - time();
            ApiResponse::error("Rate limit exceeded. Please wait $waitTime seconds.", 429);
        }

        // Increment counter
        $rateLimit['requests']++;
    }
}

// Logging Helper
class ApiLogger
{
    public static function log($level, $message, $context = [])
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ];

        error_log("[API] " . json_encode($logEntry));
    }

    public static function info($message, $context = [])
    {
        self::log('INFO', $message, $context);
    }

    public static function warning($message, $context = [])
    {
        self::log('WARNING', $message, $context);
    }

    public static function error($message, $context = [])
    {
        self::log('ERROR', $message, $context);
    }
}

// CORS Handler
class ApiCors
{
    public static function handle()
    {
        // Allow specific origins
        $allowedOrigins = [
            'http://localhost',
            'http://localhost:3000',
            'http://127.0.0.1',
            // Add your production domains here
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowedOrigins) || strpos($origin, 'localhost') !== false) {
            header("Access-Control-Allow-Origin: $origin");
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}

// Initialize API
ApiCors::handle();

// Common request validation
function validateJsonInput()
{
    $input = file_get_contents('php://input');
    if (empty($input)) {
        ApiResponse::badRequest('Empty request body');
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        ApiResponse::badRequest('Invalid JSON format');
    }

    return $data;
}

// Get request method
function getRequestMethod()
{
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

// Get pagination parameters
function getPaginationParams($defaultLimit = 20, $maxLimit = 100)
{
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min($maxLimit, max(1, intval($_GET['limit'] ?? $defaultLimit)));
    $offset = ($page - 1) * $limit;

    return compact('page', 'limit', 'offset');
}

// Format pagination response
function formatPaginationResponse($items, $total, $page, $limit)
{
    $totalPages = ceil($total / $limit);

    return [
        'items' => $items,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'items_per_page' => $limit,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
}
?>