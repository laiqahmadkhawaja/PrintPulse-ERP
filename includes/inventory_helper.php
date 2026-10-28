<?php
/**
 * Inventory & Stock Management Helper
 */

require_once __DIR__ . '/../config/init.php';

class InventoryHelper {

    /**
     * Record stock movement
     */
    public static function recordMovement($business_id, $product_id, $movement_type, $quantity, $unit_cost = 0.00, $reference_id = null, $notes = '') {
        global $current_settings;
        $pdo = getDB();

        // Check if inventory feature is enabled
        $invEnabled = true;
        if ($current_settings && isset($current_settings['inventory_enabled'])) {
            $invEnabled = (bool)$current_settings['inventory_enabled'];
        }

        // Even if operational interface is disabled, keep historical movements or skip stock decrement if disabled
        if (!$invEnabled && !in_array($movement_type, ['purchase', 'initial'])) {
            return false;
        }

        $prodStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND business_id = ?");
        $prodStmt->execute([$product_id, $business_id]);
        $prod = $prodStmt->fetch();

        if (!$prod) {
            return false;
        }

        // Services do not track physical stock
        if ($prod['product_type'] === 'service') {
            return true;
        }

        // Calculate new stock
        $current_stock = (float)$prod['current_stock'];
        $qty = (float)$quantity;

        // Inward vs Outward movements
        $isInward = in_array($movement_type, ['purchase', 'adjustment_in', 'initial']);
        $new_stock = $isInward ? ($current_stock + $qty) : ($current_stock - $qty);

        // Record stock movement record
        $stmt = $pdo->prepare("INSERT INTO stock_movements 
            (business_id, product_id, movement_type, reference_id, quantity, unit_cost, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $business_id,
            $product_id,
            $movement_type,
            $reference_id,
            $quantity,
            $unit_cost,
            $notes
        ]);

        // Update product table stock and average cost on purchase
        if ($isInward && $movement_type === 'purchase' && $new_stock > 0 && $unit_cost > 0) {
            $oldTotalCost = $current_stock * (float)$prod['purchase_price'];
            $newIncomingCost = $qty * (float)$unit_cost;
            $newAvgCost = round(($oldTotalCost + $newIncomingCost) / $new_stock, 2);

            $upd = $pdo->prepare("UPDATE products SET current_stock = ?, purchase_price = ? WHERE id = ? AND business_id = ?");
            $upd->execute([$new_stock, $newAvgCost, $product_id, $business_id]);
        } else {
            $upd = $pdo->prepare("UPDATE products SET current_stock = ? WHERE id = ? AND business_id = ?");
            $upd->execute([$new_stock, $product_id, $business_id]);
        }

        return true;
    }

    /**
     * Get low stock products
     */
    public static function getLowStockProducts($business_id) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM products 
                               WHERE business_id = ? AND product_type != 'service' 
                               AND current_stock <= reorder_level AND status = 'active' 
                               ORDER BY current_stock ASC");
        $stmt->execute([$business_id]);
        return $stmt->fetchAll();
    }
}
