<?php

namespace Services\Auth;

use Core\Database;

class AuthService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function login(string $email, string $password): ?array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1");

        // Handle case where table might not exist yet gracefully-ish or just fail
        try {
            $stmt->execute([$email]);
        } catch (\PDOException $e) {
            return null;
        }

        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return null; // Invalid credentials
        }

        unset($user['password']);
        return $user;
    }

    public function register(array $data): array
    {
        $conn = $this->db->getConnection();

        if (empty($data['email']) || empty($data['password']) || empty($data['name'])) {
            return ['error' => 'Missing required fields'];
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        try {
            $stmt->execute([$data['email']]);
        } catch (\PDOException $e) {
            // If table doesn't exist, we might need to create it. 
            // For now, let's assume it exists or we fail.
            // Ideally we should have a migration script.
            return ['error' => 'Database error: ' . $e->getMessage()];
        }

        if ($stmt->fetch()) {
            return ['error' => 'Email already registered'];
        }

        // Insert new user
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())");

        if ($stmt->execute([$data['name'], $data['email'], $hash])) {
            $userId = $conn->lastInsertId();
            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'Registration successful'
            ];
        }

        return ['error' => 'Registration failed'];
    }

    public function getUser(int $id): ?array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT id, name, email, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }
}
