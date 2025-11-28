<?php

//index.php - Backend para receber mensagens do frontend

header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Permite requests do frontend no mesmo pod

// Health check endpoint for Docker
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo "OK";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método não permitido";
    exit;
}

$nome       = trim($_POST['nome'] ?? '');
$email      = trim($_POST['email'] ?? '');
$comentario = trim($_POST['comentario'] ?? '');

if (empty($nome) || empty($email) || empty($comentario)) {
    http_response_code(400);
    echo "Todos os campos são obrigatórios";
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "E-mail inválido";
    exit;
}

try {
    $host = getenv('DB_HOST');      // mysql-service
    $dbname = getenv('DB_NAME');    // meubanco
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");

    $sql = "INSERT INTO mensagens (nome, email, comentario) VALUES (:nome, :email, :comentario)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome'       => $nome,
        ':email'      => $email,
        ':comentario' => $comentario
    ]);

    echo "OK";
} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro no backend: " . $e->getMessage());
    echo "Erro interno no servidor";
}