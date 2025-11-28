CREATE DATABASE IF NOT EXISTS meubanco;

CREATE USER IF NOT EXISTS 'mario'@'%' IDENTIFIED BY 'Senha123';
GRANT ALL PRIVILEGES ON mydb.* TO 'mario'@'%';
FLUSH PRIVILEGES;

USE meubanco;

ALTER DATABASE meubanco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    email VARCHAR(50) NOT NULL,
    comentario TEXT NOT NULL,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO mensagens (nome, email, comentario) VALUES ('Mario Ferreira', 'mario@example.com', 'Primeiro comentário!');
