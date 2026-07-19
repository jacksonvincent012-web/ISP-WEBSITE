<?php
// Brand settings loader — call this on every page that needs branding
function loadBrand(): array {
    global $pdo;
    static $brand = null;
    if ($brand === null) {
        try {
            $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
            $brand = $stmt->fetch() ?: [];
        } catch (Exception $e) {
            $brand = [];
        }
    }
    return $brand;
}

function brandStyle(array $brand): string {
    $color = $brand['primary_color'] ?? '#3b82f6';
    return "<style>:root{--primary:$color;--primary-light:" . $color . "20;}</style>";
}

function brandName(array $brand): string {
    return $brand['brand_name'] ?? 'NetConnect ISP';
}

function brandCurrency(array $brand): string {
    return $brand['currency'] ?? 'USD';
}

function brandSupportEmail(array $brand): string {
    return $brand['support_email'] ?? 'support@isp.com';
}
