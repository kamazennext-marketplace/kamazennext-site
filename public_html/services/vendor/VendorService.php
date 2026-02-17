<?php

namespace Services\Vendor;

use Core\Database;

class VendorService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function login(string $email, string $password): ?array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT id, name, company, password FROM vendors WHERE email = ? LIMIT 1");
        if (!$stmt) {
            return null; // Database error
        }

        $stmt->execute([$email]);
        $vendor = $stmt->fetch();

        if (!$vendor || !password_verify($password, $vendor['password'])) {
            return null; // Invalid credentials
        }

        unset($vendor['password']); // Don't return the password hash
        return $vendor;
    }

    public function register(array $data): array
    {
        $conn = $this->db->getConnection();

        // Simple validation
        if (empty($data['email']) || empty($data['password']) || empty($data['name'])) {
            return ['error' => 'Missing required fields'];
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM vendors WHERE email = ? LIMIT 1");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['error' => 'Email already registered'];
        }

        // Insert new vendor
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO vendors (name, email, password, company, created_at) VALUES (?, ?, ?, ?, NOW())");

        $company = $data['company'] ?? '';

        if ($stmt->execute([$data['name'], $data['email'], $hash, $company])) {
            $vendorId = $conn->lastInsertId();
            return [
                'success' => true,
                'vendor_id' => $vendorId,
                'message' => 'Registration successful'
            ];
        }

        return ['error' => 'Registration failed'];
    }

    public function getDashboardData(int $vendorId): array
    {
        // Placeholder for dashboard stats
        // In a real scenario, this would query products, clicks, etc. related to the vendor
        return [
            'vendor_id' => $vendorId,
            'stats' => [
                'views' => 0, // Implement real logic later
                'clicks' => 0,
                'leads' => 0
            ]
        ];
    }

    public function submitSoftware(int $vendorId, array $data, ?array $file): array
    {
        $conn = $this->db->getConnection();

        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        $category = $data['category'] ?? '';
        $website = $data['website'] ?? '';
        $price = $data['price'] ?? '';

        if (!$title || !$description || !$category) {
            return ['error' => 'Missing required fields'];
        }

        // Handle image upload
        $imagePath = "";
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $targetDir = __DIR__ . '/../../software/images/'; // Adjust path as needed
            if (!is_dir($targetDir))
                mkdir($targetDir, 0755, true);

            $fileName = time() . "_" . basename($file['name']);
            $targetFile = $targetDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                $imagePath = "software/images/" . $fileName;
            }
        }

        // Insert into DB
        $stmt = $conn->prepare("INSERT INTO software (vendor_id, title, description, category, website, price, image, status, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");

        if ($stmt->execute([$vendorId, $title, $description, $category, $website, $price, $imagePath])) {
            return ['success' => true, 'message' => 'Software submitted successfully. Pending approval.'];
        }

        return ['error' => 'Database error during submission'];
    }
}
