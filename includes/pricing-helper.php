<?php
/**
 * ATHLETICA — Central Pricing Authority Helper
 * 
 * Enforces server-side pricing rules across cart hydration, API, and checkout.
 * Resolves whether a product has an active, enabled Flash Sale price.
 */

declare(strict_types=1);

/**
 * Resolves the true effective selling price for a product.
 *
 * Checks if the product is enrolled in an active, currently valid flash sale.
 * Returns both regular price, sale price (if applicable), discount percentage,
 * and the final authoritative effective unit price.
 *
 * @param PDO $pdo
 * @param int $productId
 * @param float $basePrice
 * @param float|null $oldPrice
 * @return array
 */
function resolve_product_price(PDO $pdo, int $productId, float $basePrice, ?float $oldPrice = null): array
{
    // Default regular resolution
    $result = [
        'effective_price'  => $basePrice,
        'regular_price'    => $basePrice,
        'sale_price'       => null,
        'old_price'        => $oldPrice,
        'is_flash_sale'    => false,
        'flash_sale_id'    => null,
        'flash_sale_title' => null,
        'discount_percent' => 0,
    ];

    if ($oldPrice !== null && $oldPrice > $basePrice) {
        $result['discount_percent'] = (int)round(((1 - ($basePrice / $oldPrice)) * 100));
    }

    // Query for an active Flash Sale matching this product
    $sql = "
        SELECT fs.id AS flash_sale_id, fs.title AS flash_sale_title, fsp.sale_price
        FROM flash_sale_products fsp
        JOIN flash_sales fs ON fs.id = fsp.flash_sale_id
        WHERE fsp.product_id = :product_id
          AND fs.is_active = 1
          AND NOW() BETWEEN fs.starts_at AND fs.ends_at
        ORDER BY fs.ends_at ASC
        LIMIT 1
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':product_id' => $productId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sale && isset($sale['sale_price'])) {
            $salePrice = (float)$sale['sale_price'];
            if ($salePrice > 0 && $salePrice < $basePrice) {
                $result['effective_price']  = $salePrice;
                $result['sale_price']       = $salePrice;
                $result['is_flash_sale']    = true;
                $result['flash_sale_id']    = (int)$sale['flash_sale_id'];
                $result['flash_sale_title'] = $sale['flash_sale_title'];
                // Flash sale discount relative to regular selling price
                $result['discount_percent'] = (int)round(((1 - ($salePrice / $basePrice)) * 100));
            }
        }
    } catch (PDOException $e) {
        // In case table does not exist or DB issue, failsafe gracefully to base price
    }

    return $result;
}
