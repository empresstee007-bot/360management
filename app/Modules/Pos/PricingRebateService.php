<?php

declare(strict_types=1);

namespace App\Modules\Pos;

use App\Core\Database;

class PricingRebateService
{
    /**
     * Get all pricing tiers (or filtered by SKU)
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function getAllTiers(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (Database::getConnection()) {
            $_SESSION['pricing_tiers'] = self::loadPricingTiersFromDatabase();
        } elseif (!isset($_SESSION['pricing_tiers']) || !is_array($_SESSION['pricing_tiers'])) {
            $_SESSION['pricing_tiers'] = [];
        }

        return $_SESSION['pricing_tiers'];
    }

    /**
     * Get pricing tiers for a specific product SKU, sorted by min_qty ASC
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getProductTiers(string $sku, string $salesChannel = 'all', string $customerClass = '', string $location = ''): array
    {
        $all = self::getAllTiers();
        $skuKey = strtoupper(trim($sku));
        $channel = self::normalizeSalesChannel($salesChannel);
        $class = strtoupper(trim($customerClass));
        $place = strtoupper(trim($location));
        $tiers = $all[$skuKey] ?? [];
        $tiers = array_values(array_filter($tiers, static function (array $tier) use ($channel, $class, $place): bool {
            $tierChannel = self::normalizeSalesChannel((string)($tier['sales_channel'] ?? 'all'));
            if (!($tierChannel === 'all' || $channel === 'all' || $tierChannel === $channel)) {
                return false;
            }
            $tierClass = strtoupper(trim((string)($tier['customer_class'] ?? '')));
            if ($tierClass !== '' && $class !== '' && $tierClass !== $class) {
                return false;
            }
            if ($tierClass !== '' && $class === '') {
                return false;
            }
            $tierLocation = strtoupper(trim((string)($tier['location'] ?? '')));
            if ($tierLocation !== '' && $place !== '' && $tierLocation !== $place) {
                return false;
            }
            if ($tierLocation !== '' && $place === '') {
                return false;
            }
            return true;
        }));

        usort($tiers, static fn ($a, $b) => (int)($a['min_qty'] ?? 0) <=> (int)($b['min_qty'] ?? 0));

        return $tiers;
    }

    public static function canManagePricing(?array $user = null): bool
    {
        $role = strtolower(trim((string)($user['role'] ?? '')));
        if (function_exists('canonical_role')) {
            $role = strtolower(trim((string)\canonical_role($role)));
        }

        $rawRole = strtolower(trim((string)($user['role'] ?? '')));
        return in_array($role, ['admin', 'super_admin', 'pricing_manager'], true)
            || in_array($rawRole, ['super_admin', 'system_admin', 'admin', 'pricing_manager'], true);
    }

    public static function requestPriceChangeApproval(
        string $entityType,
        string $entityId,
        string $action,
        array $oldValue,
        array $newValue,
        array $payload,
        ?string $user = null
    ): array {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $requestId = 'PCA-' . date('Ymd-His') . '-' . random_int(100, 999);
        $request = [
            'id' => $requestId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'payload' => $payload,
            'status' => 'pending',
            'requested_by' => $user ?? (\current_user()['name'] ?? 'Admin'),
            'requested_at' => date('Y-m-d H:i:s'),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ];

        $_SESSION['pricing_change_approvals'][$requestId] = $request;
        self::persistPriceChangeApproval($request);
        self::logAudit($entityType, $entityId, $action . ' Requested', $oldValue, $newValue, $request['requested_by']);

        return [
            'success' => true,
            'message' => 'Price change request saved and is waiting for approval before it affects POS.',
            'request' => $request,
        ];
    }

    public static function getPriceChangeApprovals(string $status = 'pending', int $limit = 50): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $dbRequests = self::loadPriceChangeApprovalsFromDatabase($status, $limit);
        if ($dbRequests !== []) {
            foreach ($dbRequests as $request) {
                $_SESSION['pricing_change_approvals'][(string)$request['id']] = $request;
            }
            return $dbRequests;
        }

        $requests = array_values($_SESSION['pricing_change_approvals'] ?? []);
        if ($status !== 'all') {
            $requests = array_values(array_filter($requests, static fn(array $r): bool => (string)($r['status'] ?? '') === $status));
        }
        usort($requests, static fn(array $a, array $b): int => strcmp((string)($b['requested_at'] ?? ''), (string)($a['requested_at'] ?? '')));

        return array_slice($requests, 0, max(1, $limit));
    }

    public static function approvePriceChange(string $requestId, ?string $approver = null): array
    {
        $request = self::findPriceChangeApproval($requestId);
        if (!$request) {
            return ['success' => false, 'message' => 'Price approval request was not found.'];
        }
        if (($request['status'] ?? '') !== 'pending') {
            return ['success' => false, 'message' => 'This price request has already been reviewed.'];
        }

        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
        $entityType = (string)($request['entity_type'] ?? '');
        $approvedBy = $approver ?? (\current_user()['name'] ?? 'Admin');
        $applyResult = ['success' => false, 'message' => 'Unsupported approval request.'];

        if ($entityType === 'pricing_tier') {
            $mode = (string)($payload['_approval_mode'] ?? 'save');
            if ($mode === 'delete') {
                $ok = self::deletePricingTier((string)($payload['tier_id'] ?? ''), (string)($payload['sku'] ?? ''), $approvedBy);
                $applyResult = ['success' => $ok, 'message' => $ok ? 'Pricing tier deleted after approval.' : 'Pricing tier could not be deleted.'];
            } else {
                unset($payload['_approval_mode']);
                $applyResult = self::savePricingTier($payload, $approvedBy);
            }
        } elseif ($entityType === 'product_price' && class_exists('App\\Modules\\BeverageWarehouse\\BeverageWarehouseService')) {
            $product = \App\Modules\BeverageWarehouse\BeverageWarehouseService::applyApprovedProductPriceChange(
                (string)($payload['sku'] ?? ''),
                is_array($payload['price_values'] ?? null) ? $payload['price_values'] : [],
                $approvedBy
            );
            $applyResult = ['success' => $product !== null, 'message' => $product ? 'Product price updated after approval.' : 'Product could not be updated.'];
        }

        if (empty($applyResult['success'])) {
            return ['success' => false, 'message' => $applyResult['message'] ?? 'Approval could not be applied.'];
        }

        $request['status'] = 'approved';
        $request['approved_by'] = $approvedBy;
        $request['approved_at'] = date('Y-m-d H:i:s');
        self::saveReviewedPriceChangeApproval($request);
        self::logAudit($entityType, (string)($request['entity_id'] ?? $requestId), (string)($request['action'] ?? 'Price Change') . ' Approved', $request['old_value'] ?? [], $request['new_value'] ?? [], $approvedBy);

        return ['success' => true, 'message' => $applyResult['message'] ?? 'Price change approved.', 'request' => $request];
    }

    public static function rejectPriceChange(string $requestId, ?string $reviewer = null): array
    {
        $request = self::findPriceChangeApproval($requestId);
        if (!$request) {
            return ['success' => false, 'message' => 'Price approval request was not found.'];
        }
        if (($request['status'] ?? '') !== 'pending') {
            return ['success' => false, 'message' => 'This price request has already been reviewed.'];
        }

        $reviewedBy = $reviewer ?? (\current_user()['name'] ?? 'Admin');
        $request['status'] = 'rejected';
        $request['rejected_by'] = $reviewedBy;
        $request['rejected_at'] = date('Y-m-d H:i:s');
        self::saveReviewedPriceChangeApproval($request);
        self::logAudit((string)($request['entity_type'] ?? 'price_change'), (string)($request['entity_id'] ?? $requestId), (string)($request['action'] ?? 'Price Change') . ' Rejected', $request['old_value'] ?? [], $request['new_value'] ?? [], $reviewedBy);

        return ['success' => true, 'message' => 'Price change rejected. Current system price was not changed.', 'request' => $request];
    }

    /**
     * Validate that a given quantity range does not overlap with existing active tiers for the product
     */
    public static function validateNoOverlap(string $sku, int $minQty, ?int $maxQty, ?string $excludeTierId = null, string $salesChannel = 'all'): array
    {
        if ($minQty < 1) {
            return ['valid' => false, 'error' => 'Minimum quantity must be at least 1.'];
        }

        if ($maxQty !== null && $maxQty > 0 && $maxQty < $minQty) {
            return ['valid' => false, 'error' => "Maximum quantity ({$maxQty}) cannot be less than Minimum quantity ({$minQty})."];
        }

        $existingTiers = self::getProductTiers($sku, $salesChannel);
        $channel = self::normalizeSalesChannel($salesChannel);
        $normMax = ($maxQty === null || $maxQty <= 0) ? PHP_INT_MAX : $maxQty;

        foreach ($existingTiers as $t) {
            $tierId = (string)($t['id'] ?? '');
            if ($excludeTierId !== null && $tierId === $excludeTierId) {
                continue;
            }

            // Only check active tiers
            if (isset($t['is_active']) && !$t['is_active']) {
                continue;
            }
            $tierChannel = self::normalizeSalesChannel((string)($t['sales_channel'] ?? 'all'));
            if ($tierChannel !== 'all' && $channel !== 'all' && $tierChannel !== $channel) {
                continue;
            }

            $tMin = (int)($t['min_qty'] ?? 1);
            $tMax = (!isset($t['max_qty']) || $t['max_qty'] === null || (int)$t['max_qty'] <= 0) ? PHP_INT_MAX : (int)$t['max_qty'];

            // Overlap condition: max(min1, min2) <= min(max1, max2)
            if (max($minQty, $tMin) <= min($normMax, $tMax)) {
                $rangeStr = $tMax === PHP_INT_MAX ? "{$tMin}+" : "{$tMin}–{$tMax}";
                return [
                    'valid' => false,
                    'error' => "Quantity range [{$minQty}" . ($maxQty ? "–{$maxQty}" : '+') . "] overlaps with existing active Tier '{$t['tier_name']}' ({$rangeStr}).",
                ];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Save / Add / Update a Pricing Tier
     */
    public static function savePricingTier(array $data, ?string $user = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $sku = strtoupper(trim((string)($data['sku'] ?? '')));
        if ($sku === '') {
            return ['success' => false, 'message' => 'Product SKU is required.'];
        }

        $tierId = trim((string)($data['tier_id'] ?? $data['id'] ?? ''));
        $isNew = ($tierId === '' || $tierId === '0');
        if ($isNew) {
            $tierId = 'TIER-' . strtoupper(substr(md5(uniqid()), 0, 8));
        }

        $tierName = trim((string)($data['tier_name'] ?? 'Tier Level'));
        $salesChannel = self::normalizeSalesChannel((string)($data['sales_channel'] ?? 'all'));
        $customerClass = trim((string)($data['customer_class'] ?? ''));
        $location = trim((string)($data['location'] ?? ''));
        $minSellingPrice = max(0.0, (float)($data['min_selling_price'] ?? 0.0));
        $minQty = max(1, (int)($data['min_qty'] ?? 1));
        $rawMax = trim((string)($data['max_qty'] ?? ''));
        $maxQty = ($rawMax === '' || $rawMax === '0' || $rawMax === '+') ? null : (int)$rawMax;
        $sellingPrice = max(0.0, (float)($data['selling_price'] ?? 0.0));
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $startDate = trim((string)($data['start_date'] ?? date('Y-m-d')));
        $endDate = trim((string)($data['end_date'] ?? ''));

        // Validate overlap if active
        if ($isActive) {
            $overlapCheck = self::validateNoOverlap($sku, $minQty, $maxQty, $isNew ? null : $tierId, $salesChannel);
            if (!$overlapCheck['valid']) {
                return ['success' => false, 'message' => $overlapCheck['error']];
            }
        }

        $all = self::getAllTiers();
        $productTiers = $all[$sku] ?? self::getProductTiers($sku);

        $oldTier = null;
        $tierIndex = null;
        foreach ($productTiers as $idx => $t) {
            if ((string)($t['id'] ?? '') === $tierId) {
                $oldTier = $t;
                $tierIndex = $idx;
                break;
            }
        }

        $newTier = [
            'id' => $tierId,
            'sku' => $sku,
            'tier_name' => $tierName,
            'sales_channel' => $salesChannel,
            'customer_class' => $customerClass !== '' ? $customerClass : null,
            'location' => $location !== '' ? $location : null,
            'min_selling_price' => $minSellingPrice,
            'min_qty' => $minQty,
            'max_qty' => $maxQty,
            'selling_price' => $sellingPrice,
            'is_active' => $isActive,
            'start_date' => $startDate,
            'end_date' => $endDate !== '' ? $endDate : null,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $user ?? (\current_user()['name'] ?? 'Admin'),
        ];

        if ($tierIndex !== null) {
            $productTiers[$tierIndex] = $newTier;
            $action = 'Update Pricing Tier';
        } else {
            $productTiers[] = $newTier;
            $action = 'Create Pricing Tier';
        }

        usort($productTiers, static fn ($a, $b) => (int)($a['min_qty'] ?? 0) <=> (int)($b['min_qty'] ?? 0));
        $all[$sku] = $productTiers;
        $_SESSION['pricing_tiers'] = $all;
        self::persistPricingTier($newTier);

        // Log Audit Trail
        self::logAudit(
            'pricing_tier',
            $sku . ':' . $tierId,
            $action,
            $oldTier ?? [],
            $newTier,
            $user ?? (\current_user()['name'] ?? 'Admin')
        );

        return [
            'success' => true,
            'message' => "Pricing Tier '{$tierName}' saved successfully for {$sku}.",
            'tier' => $newTier,
            'tiers' => $productTiers,
        ];
    }

    /**
     * Delete a pricing tier
     */
    public static function deletePricingTier(string $tierId, string $sku, ?string $user = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $sku = strtoupper(trim($sku));
        $all = self::getAllTiers();
        $productTiers = $all[$sku] ?? self::getProductTiers($sku);

        $deleted = null;
        $filtered = [];
        foreach ($productTiers as $t) {
            if ((string)($t['id'] ?? '') === $tierId) {
                $deleted = $t;
            } else {
                $filtered[] = $t;
            }
        }

        if ($deleted) {
            $all[$sku] = $filtered;
            $_SESSION['pricing_tiers'] = $all;
            self::deletePricingTierFromDatabase($tierId, $sku);

            self::logAudit(
                'pricing_tier',
                $sku . ':' . $tierId,
                'Delete Pricing Tier',
                $deleted,
                [],
                $user ?? (\current_user()['name'] ?? 'Admin')
            );
            return true;
        }

        return false;
    }

    /**
     * Resolve the active tier and price for a product based on purchase quantity and effective date
     */
    public static function resolveTierForQty(array $product, int $qty, string $salesChannel = 'all', string $customerClass = '', string $location = ''): array
    {
        $sku = strtoupper(trim((string)($product['sku'] ?? '')));
        $qty = max(1, $qty);
        $channel = self::normalizeSalesChannel($salesChannel);
        $tiers = self::getProductTiers($sku, $channel, $customerClass, $location);
        $today = date('Y-m-d');

        $matchedTier = null;
        foreach ($tiers as $tier) {
            if (isset($tier['is_active']) && !$tier['is_active']) {
                continue;
            }

            // Check date validity
            if (!empty($tier['start_date']) && $tier['start_date'] > $today) {
                continue;
            }
            if (!empty($tier['end_date']) && $tier['end_date'] < $today) {
                continue;
            }

            $min = (int)($tier['min_qty'] ?? 1);
            $max = (!isset($tier['max_qty']) || $tier['max_qty'] === null || (int)$tier['max_qty'] <= 0) ? PHP_INT_MAX : (int)$tier['max_qty'];

            if ($qty >= $min && $qty <= $max) {
                $matchedTier = $tier;
                break;
            }
        }

        $basePrice = self::baseSellingPrice($product);
        if ($matchedTier && isset($matchedTier['selling_price']) && (float)$matchedTier['selling_price'] > 0) {
            $sellingPrice = (float)$matchedTier['selling_price'];
            $tierName = (string)$matchedTier['tier_name'];
            $rangeLabel = (empty($matchedTier['max_qty']) || (int)$matchedTier['max_qty'] <= 0)
                ? "{$matchedTier['min_qty']}+"
                : "{$matchedTier['min_qty']}–{$matchedTier['max_qty']}";
        } else {
            $sellingPrice = $basePrice;
            $tierName = 'Standard Price';
            $rangeLabel = 'Standard';
        }

        return [
            'tier_id' => $matchedTier['id'] ?? 'STANDARD',
            'tier_name' => $tierName,
            'range_label' => $rangeLabel,
            'min_qty' => $matchedTier['min_qty'] ?? 1,
            'max_qty' => $matchedTier['max_qty'] ?? null,
            'min_selling_price' => (float)($matchedTier['min_selling_price'] ?? 0),
            'selling_price' => $sellingPrice,
            'base_price' => $basePrice,
            'sales_channel' => $channel,
            'is_custom_tier' => $matchedTier !== null,
        ];
    }

    public static function normalizeSalesChannel(string $salesChannel): string
    {
        $channel = strtolower(trim(str_replace([' ', '-'], '_', $salesChannel)));
        return in_array($channel, ['depot_sale', 'diversion_sale', 'all'], true) ? $channel : 'all';
    }

    public static function getPromotionRules(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (Database::getConnection()) {
            $_SESSION['promotion_rules'] = self::loadPromotionRulesFromDatabase();
        } elseif (!isset($_SESSION['promotion_rules']) || !is_array($_SESSION['promotion_rules'])) {
            $_SESSION['promotion_rules'] = [];
        }

        return $_SESSION['promotion_rules'];
    }

    public static function savePromotionRule(array $data, ?string $user = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $ruleId = trim((string)($data['promotion_id'] ?? $data['id'] ?? ''));
        $isNew = $ruleId === '' || $ruleId === '0';
        if ($isNew) {
            $ruleId = 'PROMO-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
        }

        $name = trim((string)($data['promotion_name'] ?? 'Supplier Promotion'));
        $eligibleSku = strtoupper(trim((string)($data['eligible_sku'] ?? 'ALL')));
        $salesChannel = self::normalizeSalesChannel((string)($data['sales_channel'] ?? 'all'));
        $benefitType = in_array(($data['benefit_type'] ?? 'amount'), ['amount', 'percent', 'free_qty'], true)
            ? (string)$data['benefit_type']
            : 'amount';
        $allocationMethod = in_array(($data['allocation_method'] ?? 'per_unit'), ['per_unit', 'invoice_total', 'truck_load', 'pallet'], true)
            ? (string)$data['allocation_method']
            : 'per_unit';

        $newRule = [
            'id' => $ruleId,
            'promotion_name' => $name,
            'promotion_code' => strtoupper(trim((string)($data['promotion_code'] ?? $ruleId))),
            'supplier' => strtoupper(trim((string)($data['supplier'] ?? ''))),
            'eligible_sku' => $eligibleSku !== '' ? $eligibleSku : 'ALL',
            'qualifying_qty' => max(1, (int)($data['qualifying_qty'] ?? 1)),
            'reward_qty' => max(0, (int)($data['reward_qty'] ?? 0)),
            'reward_sku' => strtoupper(trim((string)($data['reward_sku'] ?? ''))),
            'benefit_type' => $benefitType,
            'benefit_value' => max(0.0, (float)($data['benefit_value'] ?? 0)),
            'adjustment_factor' => max(0.0, min(100.0, (float)($data['adjustment_factor'] ?? 100))),
            'allocation_method' => $allocationMethod,
            'sales_channel' => $salesChannel,
            'customer_class' => trim((string)($data['customer_class'] ?? '')),
            'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : true,
            'start_date' => trim((string)($data['start_date'] ?? date('Y-m-d'))),
            'end_date' => trim((string)($data['end_date'] ?? '')) ?: null,
            'approval_status' => in_array(($data['approval_status'] ?? 'approved'), ['draft', 'approved', 'inactive'], true)
                ? (string)$data['approval_status']
                : 'approved',
            'updated_by' => $user ?? (\current_user()['name'] ?? 'Admin'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $rules = self::getPromotionRules();
        $oldRule = null;
        $index = null;
        foreach ($rules as $idx => $rule) {
            if ((string)($rule['id'] ?? '') === $ruleId) {
                $oldRule = $rule;
                $index = $idx;
                break;
            }
        }

        if ($index === null) {
            $rules[] = $newRule;
            $action = 'Create Promotion Rule';
        } else {
            $rules[$index] = $newRule;
            $action = 'Update Promotion Rule';
        }

        $_SESSION['promotion_rules'] = $rules;
        self::persistPromotionRule($newRule);
        self::logAudit('promotion_rule', $newRule['promotion_code'], $action, $oldRule ?? [], $newRule, $newRule['updated_by']);

        return ['success' => true, 'message' => "Promotion '{$name}' saved successfully.", 'rule' => $newRule];
    }

    public static function deletePromotionRule(string $ruleId, ?string $user = null): bool
    {
        $rules = self::getPromotionRules();
        $deleted = null;
        $filtered = [];

        foreach ($rules as $rule) {
            if ((string)($rule['id'] ?? '') === $ruleId) {
                $deleted = $rule;
            } else {
                $filtered[] = $rule;
            }
        }

        if (!$deleted) {
            return false;
        }

        $_SESSION['promotion_rules'] = $filtered;
        self::deletePromotionRuleFromDatabase($ruleId);
        self::logAudit('promotion_rule', (string)($deleted['promotion_code'] ?? $ruleId), 'Delete Promotion Rule', $deleted, [], $user ?? (\current_user()['name'] ?? 'Admin'));

        return true;
    }

    public static function resolvePromotion(array $product, int $qty, string $salesChannel = 'depot_sale', ?string $promotionCode = null, string $customerClass = ''): array
    {
        $sku = strtoupper(trim((string)($product['sku'] ?? '')));
        $brand = strtoupper(trim((string)($product['brand'] ?? '')));
        $supplier = strtoupper(trim((string)($product['supplier'] ?? '')));
        $channel = self::normalizeSalesChannel($salesChannel);
        $today = date('Y-m-d');
        $qty = max(1, $qty);
        $class = strtoupper(trim($customerClass));

        foreach (self::getPromotionRules() as $rule) {
            if (empty($rule['is_active']) || ($rule['approval_status'] ?? 'approved') !== 'approved') {
                continue;
            }
            if (!empty($rule['start_date']) && $rule['start_date'] > $today) {
                continue;
            }
            if (!empty($rule['end_date']) && $rule['end_date'] < $today) {
                continue;
            }
            $ruleChannel = self::normalizeSalesChannel((string)($rule['sales_channel'] ?? 'all'));
            if ($ruleChannel !== 'all' && $ruleChannel !== $channel) {
                continue;
            }
            $ruleClass = strtoupper(trim((string)($rule['customer_class'] ?? '')));
            if ($ruleClass !== '' && ($class === '' || $ruleClass !== $class)) {
                continue;
            }
            if ($promotionCode !== null && $promotionCode !== '' && strtoupper($promotionCode) !== strtoupper((string)($rule['promotion_code'] ?? ''))) {
                continue;
            }
            if ($qty < (int)($rule['qualifying_qty'] ?? 1)) {
                continue;
            }
            $eligible = strtoupper((string)($rule['eligible_sku'] ?? 'ALL'));
            if (!in_array($eligible, ['ALL', $sku, $brand, $supplier], true)) {
                continue;
            }

            return self::calculatePromotionBenefit($rule, $product, $qty);
        }

        return [
            'promotion_id' => 'NO-PROMO',
            'promotion_name' => 'No active promotion',
            'promotion_code' => '',
            'benefit_per_unit' => 0.0,
            'total_benefit' => 0.0,
            'reward_qty' => 0,
            'reward_sku' => '',
        ];
    }

    private static function calculatePromotionBenefit(array $rule, array $product, int $qty): array
    {
        $cost = (float)($product['cost_price'] ?? $product['invoice_price'] ?? 0);
        $factor = ((float)($rule['adjustment_factor'] ?? 100)) / 100;
        $benefitValue = (float)($rule['benefit_value'] ?? 0);
        $benefitPerUnit = 0.0;
        $rewardQty = 0;

        if (($rule['benefit_type'] ?? '') === 'percent') {
            $benefitPerUnit = $cost * ($benefitValue / 100) * $factor;
        } elseif (($rule['benefit_type'] ?? '') === 'free_qty') {
            $qualifyingQty = max(1, (int)($rule['qualifying_qty'] ?? 1));
            $rewardQty = intdiv($qty, $qualifyingQty) * max(0, (int)($rule['reward_qty'] ?? 0));
            $benefitPerUnit = $qty > 0 ? (($rewardQty * $cost) / $qty) * $factor : 0.0;
        } else {
            $benefitPerUnit = $benefitValue * $factor;
            if (($rule['allocation_method'] ?? 'per_unit') !== 'per_unit') {
                $benefitPerUnit = $qty > 0 ? $benefitPerUnit / $qty : 0.0;
            }
        }

        return [
            'promotion_id' => (string)($rule['id'] ?? ''),
            'promotion_name' => (string)($rule['promotion_name'] ?? 'Promotion'),
            'promotion_code' => (string)($rule['promotion_code'] ?? ''),
            'benefit_type' => (string)($rule['benefit_type'] ?? 'amount'),
            'allocation_method' => (string)($rule['allocation_method'] ?? 'per_unit'),
            'benefit_per_unit' => $benefitPerUnit,
            'total_benefit' => $benefitPerUnit * $qty,
            'reward_qty' => $rewardQty,
            'reward_sku' => (string)($rule['reward_sku'] ?? ''),
        ];
    }

    /* =========================================================================
       HIERARCHICAL REBATE MANAGEMENT ENGINE
       Hierarchy: Promotion Rebate > Product Rebate > Brand Rebate > Supplier Rebate > Global Rebate
       ========================================================================= */

    /**
     * Get all rebate rules
     */
    public static function getRebateSettings(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (Database::getConnection()) {
            $_SESSION['rebate_rules'] = self::loadRebateRulesFromDatabase();
        } elseif (!isset($_SESSION['rebate_rules']) || !is_array($_SESSION['rebate_rules'])) {
            $_SESSION['rebate_rules'] = [];
        }

        return $_SESSION['rebate_rules'];
    }

    /**
     * Save or update a rebate rule
     */
    public static function saveRebateRule(array $data, ?string $user = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $ruleId = trim((string)($data['id'] ?? ''));
        $isNew = ($ruleId === '' || $ruleId === '0');
        if ($isNew) {
            $ruleId = 'REBATE-' . strtoupper(substr(md5(uniqid()), 0, 8));
        }

        $level = strtolower(trim((string)($data['level'] ?? 'global')));
        $validLevels = ['global', 'supplier', 'brand', 'product', 'promotion'];
        if (!in_array($level, $validLevels, true)) {
            $level = 'global';
        }

        $targetKey = strtoupper(trim((string)($data['target_key'] ?? 'ALL')));
        if (in_array($level, ['supplier', 'product', 'brand', 'promotion'], true) && $targetKey === '') {
            return [
                'success' => false,
                'message' => 'Select or enter a target before saving this rebate rule.',
            ];
        }
        $rebatePct = max(0.0, min(100.0, (float)($data['rebate_pct'] ?? 0.0)));
        $rebateBasePrice = max(0.0, (float)($data['rebate_base_price'] ?? 0.0));
        $adjustmentFactor = max(0.0, min(100.0, (float)($data['adjustment_factor'] ?? 100.0)));
        $formulaType = trim((string)($data['formula_type'] ?? 'standard_pct'));
        $description = trim((string)($data['description'] ?? ''));
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $startDate = trim((string)($data['start_date'] ?? date('Y-m-d')));
        $endDate = trim((string)($data['end_date'] ?? ''));

        $rules = self::getRebateSettings();
        $oldRule = null;
        $ruleIndex = null;

        foreach ($rules as $idx => $r) {
            if ((string)($r['id'] ?? '') === $ruleId || ($level === 'global' && strtolower((string)($r['level'] ?? '')) === 'global')) {
                $oldRule = $r;
                $ruleIndex = $idx;
                $ruleId = $r['id'] ?? $ruleId;
                break;
            }
        }

        $newRule = [
            'id' => $ruleId,
            'level' => $level,
            'target_key' => $targetKey,
            'rebate_pct' => $rebatePct,
            'rebate_base_price' => $rebateBasePrice,
            'adjustment_factor' => $adjustmentFactor,
            'formula_type' => $formulaType,
            'description' => $description ?: self::defaultRebateDescription($level, $targetKey, $rebatePct),
            'is_active' => $isActive,
            'start_date' => $startDate,
            'end_date' => $endDate !== '' ? $endDate : null,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $user ?? (\current_user()['name'] ?? 'Admin'),
        ];

        if ($ruleIndex !== null) {
            $rules[$ruleIndex] = $newRule;
            $action = 'Update Rebate Rule';
        } else {
            $rules[] = $newRule;
            $action = 'Create Rebate Rule';
        }

        $_SESSION['rebate_rules'] = $rules;
        self::persistRebateRule($newRule);

        self::logAudit(
            'rebate_rule',
            $level . ':' . $targetKey,
            $action,
            $oldRule ?? [],
            $newRule,
            $user ?? (\current_user()['name'] ?? 'Admin')
        );

        return [
            'success' => true,
            'message' => "Rebate rule [{$level}: {$targetKey} -> {$rebatePct}%] saved successfully.",
            'rule' => $newRule,
            'rules' => $rules,
        ];
    }

    /**
     * Delete a rebate rule
     */
    public static function deleteRebateRule(string $ruleId, ?string $user = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $rules = self::getRebateSettings();
        $deleted = null;
        $filtered = [];

        foreach ($rules as $r) {
            if ((string)($r['id'] ?? '') === $ruleId) {
                $deleted = $r;
            } else {
                $filtered[] = $r;
            }
        }

        if ($deleted) {
            $_SESSION['rebate_rules'] = $filtered;
            self::deleteRebateRuleFromDatabase($ruleId);
            self::logAudit(
                'rebate_rule',
                $deleted['level'] . ':' . $deleted['target_key'],
                'Delete Rebate Rule',
                $deleted,
                [],
                $user ?? (\current_user()['name'] ?? 'Admin')
            );
            return true;
        }

        return false;
    }

    /**
     * Resolve applicable rebate % following hierarchy:
     * 1. Promotion Rebate (Highest Specificity)
     * 2. Product Rebate (Specific SKU)
     * 3. Brand Rebate (Specific Brand)
     * 4. Supplier Rebate (Specific Supplier)
     * 5. Global Rebate (Depot-wide baseline)
     */
    public static function resolveRebate(array $product, ?string $promoCode = null): array
    {
        $rules = self::getRebateSettings();
        $sku = strtoupper(trim((string)($product['sku'] ?? '')));
        $brand = strtoupper(trim((string)($product['brand'] ?? '')));
        $supplier = strtoupper(trim((string)($product['supplier'] ?? '')));
        $promo = $promoCode ? strtoupper(trim($promoCode)) : '';
        $today = date('Y-m-d');

        $activeRules = array_filter($rules, static function ($r) use ($today): bool {
            if (isset($r['is_active']) && !$r['is_active']) {
                return false;
            }
            if (!empty($r['start_date']) && $r['start_date'] > $today) {
                return false;
            }
            if (!empty($r['end_date']) && $r['end_date'] < $today) {
                return false;
            }
            return true;
        });

        // 1. Check Promotion Rebate
        if ($promo !== '') {
            foreach ($activeRules as $r) {
                if (($r['level'] ?? '') === 'promotion' && strtoupper(trim((string)($r['target_key'] ?? ''))) === $promo) {
                    return self::formatResolvedRebate($r, 'Promotion Rebate');
                }
            }
        }

        // 2. Check Product Rebate (by SKU)
        if ($sku !== '') {
            foreach ($activeRules as $r) {
                if (($r['level'] ?? '') === 'product' && strtoupper(trim((string)($r['target_key'] ?? ''))) === $sku) {
                    return self::formatResolvedRebate($r, "Product Rebate ({$sku})");
                }
            }
        }

        // 3. Check Brand Rebate
        if ($brand !== '') {
            foreach ($activeRules as $r) {
                if (($r['level'] ?? '') === 'brand' && strtoupper(trim((string)($r['target_key'] ?? ''))) === $brand) {
                    return self::formatResolvedRebate($r, "Brand Rebate ({$brand})");
                }
            }
        }

        // 4. Check Supplier Rebate
        if ($supplier !== '') {
            foreach ($activeRules as $r) {
                if (($r['level'] ?? '') === 'supplier' && strtoupper(trim((string)($r['target_key'] ?? ''))) === $supplier) {
                    return self::formatResolvedRebate($r, "Supplier Rebate ({$supplier})");
                }
            }
        }

        // 5. Fallback to Global Rebate
        foreach ($activeRules as $r) {
            if (($r['level'] ?? '') === 'global') {
                return self::formatResolvedRebate($r, 'Global Baseline Rebate');
            }
        }

        return [
            'rebate_pct' => 0.0,
            'level' => 'global',
            'rule_id' => 'NO-REBATE',
            'rule_name' => 'No active rebate',
            'description' => 'No active rebate rule has been configured.',
        ];
    }

    private static function formatResolvedRebate(array $rule, string $defaultTitle): array
    {
        return [
            'rebate_pct' => (float)($rule['rebate_pct'] ?? 0.0),
            'rebate_base_price' => (float)($rule['rebate_base_price'] ?? 0.0),
            'adjustment_factor' => (float)($rule['adjustment_factor'] ?? 100.0),
            'formula_type' => (string)($rule['formula_type'] ?? 'standard_pct'),
            'level' => (string)($rule['level'] ?? 'global'),
            'rule_id' => (string)($rule['id'] ?? 'REBATE-RULE'),
            'rule_name' => $rule['description'] ?? $defaultTitle,
            'description' => (string)($rule['description'] ?? ''),
        ];
    }

    /**
     * Compute comprehensive financial calculations for a product sale:
     * - Rebate Amount = Cost Price * Rebate %
     * - Effective Cost = Cost Price - Rebate Amount
     * - Profit Before Rebate = Selling Price - Cost Price
     * - True Profit After Rebate = Selling Price - Effective Cost
     * - Margin % = True Profit / Selling Price
     */
    public static function calculateEconomics(float $costPrice, float $sellingPrice, float $rebatePct, int $qty = 1, float $rebateBasePrice = 0.0, float $adjustmentFactor = 100.0): array
    {
        $qty = max(1, $qty);
        $costPrice = max(0.0, $costPrice);
        $sellingPrice = max(0.0, $sellingPrice);
        $rebatePct = max(0.0, min(100.0, $rebatePct));

        $rebateBase = $rebateBasePrice > 0 ? $rebateBasePrice : $costPrice;
        $unitRebateAmount = $rebateBase * ($rebatePct / 100.0) * ($adjustmentFactor / 100.0);
        $unitEffectiveCost = max(0.0, $costPrice - $unitRebateAmount);
        $unitProfitBeforeRebate = $sellingPrice - $costPrice;
        $unitTrueProfitAfterRebate = $sellingPrice - $unitEffectiveCost;
        $marginPct = $sellingPrice > 0 ? ($unitTrueProfitAfterRebate / $sellingPrice) * 100.0 : 0.0;

        return [
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'rebate_pct' => $rebatePct,
            'rebate_base_price' => $rebateBase,
            'adjustment_factor' => $adjustmentFactor,
            'unit_rebate_amount' => $unitRebateAmount,
            'unit_effective_cost' => $unitEffectiveCost,
            'unit_profit_before_rebate' => $unitProfitBeforeRebate,
            'unit_true_profit_after_rebate' => $unitTrueProfitAfterRebate,
            'margin_pct' => $marginPct,
            'total_cost' => $costPrice * $qty,
            'total_revenue' => $sellingPrice * $qty,
            'total_rebate' => $unitRebateAmount * $qty,
            'total_effective_cost' => $unitEffectiveCost * $qty,
            'total_profit_before_rebate' => $unitProfitBeforeRebate * $qty,
            'total_true_profit' => $unitTrueProfitAfterRebate * $qty,
            'is_below_cost_but_profitable' => ($sellingPrice < $costPrice && $unitTrueProfitAfterRebate > 0),
        ];
    }

    /**
     * Create an immutable, permanent transaction line-item snapshot for checkout.
     * Historical records will never change even if prices or rebates change in the future.
     */
    public static function createLineItemSnapshot(
        array $product,
        int $qty,
        string $cashier,
        string $branch = 'Jacroxx Warehouse (Main)',
        ?float $overridePrice = null,
        array $context = []
    ): array {
        $sku = strtoupper(trim((string)($product['sku'] ?? '')));
        $name = (string)($product['name'] ?? 'Product');
        $qty = max(1, $qty);
        $packSize = (string)($product['packaging'] ?? 'Crate of 24');
        $costPrice = (float)($product['cost_price'] ?? 0.0);
        $masterProduct = $sku !== '' ? self::findProductBySku($sku) : null;
        if ($masterProduct) {
            $product = array_merge($product, $masterProduct);
            $name = (string)($product['name'] ?? $name);
            $packSize = (string)($product['packaging'] ?? $packSize);
            $costPrice = (float)($product['cost_price'] ?? $costPrice);
        }

        // Resolve dynamic tier
        $salesChannel = self::normalizeSalesChannel((string)($context['sales_channel'] ?? 'depot_sale'));
        $customerClass = (string)($context['customer_class'] ?? '');
        $location = (string)($context['location'] ?? $branch);
        $tier = self::resolveTierForQty($product, $qty, $salesChannel, $customerClass, $location);
        $normalSellingPrice = self::baseSellingPrice($product);
        $actualSellingPrice = $overridePrice !== null ? max(0.0, $overridePrice) : (float)$tier['selling_price'];
        $minimumSellingPrice = (float)($tier['min_selling_price'] ?? $product['minimum_selling_price'] ?? 0.0);

        // Resolve hierarchical rebate and qualifying promotion benefit.
        $promotionCode = trim((string)($context['promotion_code'] ?? ''));
        $promotion = self::resolvePromotion($product, $qty, $salesChannel, $promotionCode !== '' ? $promotionCode : null, $customerClass);
        $rebate = self::resolveRebate($product, $promotion['promotion_code'] !== '' ? $promotion['promotion_code'] : null);
        $rebatePct = (float)$rebate['rebate_pct'];
        $rebateBasePrice = (float)($rebate['rebate_base_price'] ?? ($product['rebate_base_price'] ?? 0.0));
        $adjustmentFactor = (float)($rebate['adjustment_factor'] ?? 100.0);

        // Compute economics
        $econ = self::calculateEconomics($costPrice, $actualSellingPrice, $rebatePct, $qty, $rebateBasePrice, $adjustmentFactor);
        $promotionBenefit = (float)($promotion['benefit_per_unit'] ?? 0.0);
        if ($promotionBenefit > 0) {
            $econ['unit_effective_cost'] = max(0.0, (float)$econ['unit_effective_cost'] - $promotionBenefit);
            $econ['total_effective_cost'] = $econ['unit_effective_cost'] * $qty;
            $econ['unit_true_profit_after_rebate'] = $actualSellingPrice - $econ['unit_effective_cost'];
            $econ['total_true_profit'] = $econ['unit_true_profit_after_rebate'] * $qty;
            $econ['margin_pct'] = $actualSellingPrice > 0 ? ($econ['unit_true_profit_after_rebate'] / $actualSellingPrice) * 100.0 : 0.0;
        }
        $directCostPerUnit = self::resolveDirectCostPerUnit($actualSellingPrice, $context);
        $totalDirectCost = $directCostPerUnit * $qty;
        $operationalNetMargin = (float)$econ['unit_true_profit_after_rebate'] - $directCostPerUnit;
        $totalOperationalNetMargin = $operationalNetMargin * $qty;

        return [
            'id' => $product['id'] ?? time(),
            'sku' => $sku,
            'name' => $name,
            'quantity' => $qty,
            'qty' => $qty,
            'packaging' => $packSize,
            'pack_size' => $packSize,
            'sales_channel' => $salesChannel,
            'cost_price' => $costPrice,
            'invoice_price' => $costPrice,
            'applied_tier_id' => $tier['tier_id'],
            'applied_tier_name' => $tier['tier_name'],
            'tier_range' => $tier['range_label'],
            'normal_selling_price' => $normalSellingPrice,
            'actual_selling_price' => $actualSellingPrice,
            'minimum_selling_price' => $minimumSellingPrice,
            'below_minimum_threshold' => $minimumSellingPrice > 0 && $actualSellingPrice < $minimumSellingPrice,
            'override_reason' => trim((string)($context['override_reason'] ?? '')),
            'price_per_unit' => $actualSellingPrice,
            'crate_deposit' => (float)($product['crate_deposit'] ?? 0.0),
            'total' => $actualSellingPrice * $qty,
            'rebate_pct' => $rebatePct,
            'rebate_base_price' => $econ['rebate_base_price'],
            'rebate_adjustment_factor' => $econ['adjustment_factor'],
            'rebate_level' => $rebate['level'],
            'rebate_rule_name' => $rebate['rule_name'],
            'rebate_amount' => $econ['unit_rebate_amount'],
            'total_rebate_amount' => $econ['total_rebate'],
            'promotion_id' => $promotion['promotion_id'],
            'promotion_name' => $promotion['promotion_name'],
            'promotion_code' => $promotion['promotion_code'],
            'promotion_benefit' => $promotionBenefit,
            'total_promotion_benefit' => (float)($promotion['total_benefit'] ?? 0.0),
            'promotion_reward_qty' => (int)($promotion['reward_qty'] ?? 0),
            'promotion_reward_sku' => (string)($promotion['reward_sku'] ?? ''),
            'effective_cost' => $econ['unit_effective_cost'],
            'effective_landing_cost' => $econ['unit_effective_cost'],
            'total_effective_cost' => $econ['total_effective_cost'],
            'profit_before_rebate' => $econ['unit_profit_before_rebate'],
            'total_profit_before_rebate' => $econ['total_profit_before_rebate'],
            'true_profit_after_rebate' => $econ['unit_true_profit_after_rebate'],
            'total_true_profit' => $econ['total_true_profit'],
            'margin_pct' => $econ['margin_pct'],
            'direct_cost_per_unit' => $directCostPerUnit,
            'total_direct_cost' => $totalDirectCost,
            'operational_net_margin' => $operationalNetMargin,
            'total_operational_net_margin' => $totalOperationalNetMargin,
            'operational_margin_pct' => $actualSellingPrice > 0 ? ($operationalNetMargin / $actualSellingPrice) * 100.0 : 0.0,
            'is_below_cost_profitable' => $econ['is_below_cost_but_profitable'],
            'cashier' => $cashier,
            'branch' => $branch,
            'snapshot_timestamp' => date('Y-m-d H:i:s'),
            'image' => $product['image'] ?? '',
        ];
    }

    private static function resolveDirectCostPerUnit(float $sellingPrice, array $context = []): float
    {
        if (isset($context['direct_cost_per_unit'])) {
            return max(0.0, (float)$context['direct_cost_per_unit']);
        }

        $fixed = 0.0;
        $percent = 0.0;
        if (class_exists('App\Core\AppSettingsService')) {
            $fixed = (float)(\App\Core\AppSettingsService::get('BEVERAGE_DIRECT_COST_PER_UNIT', '0') ?? 0);
            $percent = (float)(\App\Core\AppSettingsService::get('BEVERAGE_DIRECT_COST_PERCENT', '0') ?? 0);
        } elseif (isset($_SESSION['beverage_direct_cost_per_unit']) || isset($_SESSION['beverage_direct_cost_percent'])) {
            $fixed = (float)($_SESSION['beverage_direct_cost_per_unit'] ?? 0);
            $percent = (float)($_SESSION['beverage_direct_cost_percent'] ?? 0);
        }

        return max(0.0, $fixed + ($sellingPrice * max(0.0, $percent) / 100.0));
    }

    /* =========================================================================
       AUDIT TRAIL LOGGING
       ========================================================================= */

    /**
     * Log an audit event for cost price, selling price, rebate, tiers, quantity ranges, or effective dates
     */
    public static function logAudit(
        string $entityType,
        string $entityId,
        string $action,
        array $oldVal,
        array $newVal,
        string $user
    ): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!isset($_SESSION['pricing_audit_logs'])) {
            $_SESSION['pricing_audit_logs'] = [];
        }

        $logEntry = [
            'id' => 'AUD-' . date('Ymd-His') . '-' . rand(100, 999),
            'entity_type' => $entityType, // 'pricing_tier', 'rebate_rule', 'product_price'
            'entity_id' => $entityId,
            'action' => $action,
            'user' => $user ?: 'Admin',
            'old_value' => $oldVal,
            'new_value' => $newVal,
            'timestamp' => date('d M Y, h:i A'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        array_unshift($_SESSION['pricing_audit_logs'], $logEntry);

        // Also record to Database if available
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureAuditTable($pdo);
                $stmt = $pdo->prepare(
                    'INSERT INTO pricing_audit_logs (log_id, entity_type, entity_id, action, user_name, old_value_json, new_value_json, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $logEntry['id'],
                    $entityType,
                    $entityId,
                    $action,
                    $logEntry['user'],
                    json_encode($oldVal),
                    json_encode($newVal),
                    $logEntry['created_at'],
                ]);
            } catch (\Throwable $t) {
                // Keep moving smoothly
            }
        }
    }

    /**
     * Retrieve audit logs
     */
    public static function getAuditLogs(?string $sku = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (Database::getConnection()) {
            $logs = self::loadAuditLogsFromDatabase($sku);
            $_SESSION['pricing_audit_logs'] = $logs;
            return $logs;
        }

        $logs = $_SESSION['pricing_audit_logs'] ?? [];
        if ($sku !== null && $sku !== '') {
            $skuKey = strtoupper(trim($sku));
            return array_values(array_filter($logs, static fn ($l) => str_contains(strtoupper((string)($l['entity_id'] ?? '')), $skuKey)));
        }

        return $logs;
    }

    public static function recordRebateSettlement(array $data, ?string $user = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $sku = strtoupper(trim((string)($data['sku'] ?? 'ALL')));
        $settlement = [
            'id' => trim((string)($data['settlement_id'] ?? '')) ?: 'REB-SET-' . date('Ymd-His') . '-' . rand(100, 999),
            'supplier' => trim((string)($data['supplier'] ?? '')),
            'sku' => $sku !== '' ? $sku : 'ALL',
            'period_start' => trim((string)($data['period_start'] ?? date('Y-m-01'))),
            'period_end' => trim((string)($data['period_end'] ?? date('Y-m-t'))),
            'expected_amount' => max(0.0, (float)($data['expected_amount'] ?? 0)),
            'confirmed_amount' => max(0.0, (float)($data['confirmed_amount'] ?? 0)),
            'received_amount' => max(0.0, (float)($data['received_amount'] ?? 0)),
            'status' => trim((string)($data['status'] ?? 'Confirmed')),
            'reference' => trim((string)($data['reference'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? '')),
            'updated_by' => $user ?? (\current_user()['name'] ?? 'Admin'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $_SESSION['rebate_settlements'] = $_SESSION['rebate_settlements'] ?? [];
        $_SESSION['rebate_settlements'] = array_values(array_filter(
            $_SESSION['rebate_settlements'],
            static fn(array $row): bool => (string)($row['id'] ?? '') !== $settlement['id']
        ));
        array_unshift($_SESSION['rebate_settlements'], $settlement);

        self::persistRebateSettlement($settlement);
        self::logAudit('rebate_settlement', $settlement['id'], 'Record Rebate Settlement', [], $settlement, $settlement['updated_by']);

        return ['success' => true, 'message' => 'Rebate settlement recorded.', 'settlement' => $settlement];
    }

    public static function getRebateSettlements(int $limit = 200): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $rows = self::loadRebateSettlementsFromDatabase($limit);
        if ($rows) {
            $_SESSION['rebate_settlements'] = $rows;
            return $rows;
        }

        return array_slice($_SESSION['rebate_settlements'] ?? [], 0, $limit);
    }

    public static function summarizeExpectedVsConfirmedRebates(array $transactions = []): array
    {
        $expectedBySku = [];
        foreach ($transactions as $tx) {
            $items = $tx['items'] ?? [];
            if (!$items && !empty($tx['items_json'])) {
                $items = json_decode((string)$tx['items_json'], true) ?: [];
            }
            foreach ($items as $item) {
                $sku = strtoupper(trim((string)($item['sku'] ?? 'ALL'))) ?: 'ALL';
                $expectedBySku[$sku] = ($expectedBySku[$sku] ?? 0.0) + (float)($item['total_rebate_amount'] ?? (((float)($item['rebate_amount'] ?? 0)) * max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1))));
            }
        }

        $settlements = self::getRebateSettlements();
        $confirmedBySku = [];
        $receivedBySku = [];
        foreach ($settlements as $settlement) {
            $sku = strtoupper(trim((string)($settlement['sku'] ?? 'ALL'))) ?: 'ALL';
            $confirmedBySku[$sku] = ($confirmedBySku[$sku] ?? 0.0) + (float)($settlement['confirmed_amount'] ?? 0);
            $receivedBySku[$sku] = ($receivedBySku[$sku] ?? 0.0) + (float)($settlement['received_amount'] ?? 0);
        }

        $keys = array_values(array_unique(array_merge(array_keys($expectedBySku), array_keys($confirmedBySku), array_keys($receivedBySku))));
        sort($keys);
        return array_map(static fn(string $sku): array => [
            'sku' => $sku,
            'expected_amount' => $expectedBySku[$sku] ?? 0.0,
            'confirmed_amount' => $confirmedBySku[$sku] ?? 0.0,
            'received_amount' => $receivedBySku[$sku] ?? 0.0,
            'variance' => ($confirmedBySku[$sku] ?? 0.0) - ($expectedBySku[$sku] ?? 0.0),
        ], $keys);
    }

    private static function ensureAuditTable(\PDO $pdo): void
    {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS pricing_audit_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    log_id VARCHAR(50) NOT NULL UNIQUE,
                    entity_type VARCHAR(50) NOT NULL,
                    entity_id VARCHAR(100) NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    user_name VARCHAR(100) NOT NULL,
                    old_value_json TEXT,
                    new_value_json TEXT,
                    created_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } catch (\Throwable $t) {}
    }

    private static function ensureRebateSettlementsTable(\PDO $pdo): void
    {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS rebate_settlements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    settlement_id VARCHAR(80) NOT NULL UNIQUE,
                    company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
                    supplier VARCHAR(150) DEFAULT NULL,
                    sku VARCHAR(100) NOT NULL DEFAULT "ALL",
                    period_start DATE NOT NULL,
                    period_end DATE NOT NULL,
                    expected_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    confirmed_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    received_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    status VARCHAR(50) NOT NULL DEFAULT "Confirmed",
                    reference VARCHAR(150) DEFAULT NULL,
                    notes TEXT DEFAULT NULL,
                    updated_by VARCHAR(120) DEFAULT NULL,
                    updated_at DATETIME NOT NULL,
                    INDEX idx_rebate_settlements_lookup (company_id, sku, period_start, period_end)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } catch (\Throwable $t) {
        }
    }

    private static function persistRebateSettlement(array $settlement): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        try {
            self::ensureRebateSettlementsTable($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO rebate_settlements
                    (settlement_id, company_id, supplier, sku, period_start, period_end, expected_amount, confirmed_amount, received_amount, status, reference, notes, updated_by, updated_at)
                 VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE supplier=VALUES(supplier), sku=VALUES(sku), period_start=VALUES(period_start), period_end=VALUES(period_end), expected_amount=VALUES(expected_amount), confirmed_amount=VALUES(confirmed_amount), received_amount=VALUES(received_amount), status=VALUES(status), reference=VALUES(reference), notes=VALUES(notes), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)'
            );
            $stmt->execute([
                $settlement['id'],
                $settlement['supplier'] ?: null,
                $settlement['sku'],
                $settlement['period_start'],
                $settlement['period_end'],
                (float)$settlement['expected_amount'],
                (float)$settlement['confirmed_amount'],
                (float)$settlement['received_amount'],
                $settlement['status'],
                $settlement['reference'] ?: null,
                $settlement['notes'] ?: null,
                $settlement['updated_by'] ?? null,
                $settlement['updated_at'],
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function loadRebateSettlementsFromDatabase(int $limit = 200): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            self::ensureRebateSettlementsTable($pdo);
            $limit = max(1, min(500, $limit));
            $stmt = $pdo->query(
                'SELECT settlement_id, supplier, sku, period_start, period_end, expected_amount, confirmed_amount, received_amount, status, reference, notes, updated_by, updated_at
                 FROM rebate_settlements
                 WHERE company_id = "beverage"
                 ORDER BY updated_at DESC, id DESC
                 LIMIT ' . $limit
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
            return array_map(static fn(array $row): array => [
                'id' => (string)$row['settlement_id'],
                'supplier' => (string)($row['supplier'] ?? ''),
                'sku' => (string)($row['sku'] ?? 'ALL'),
                'period_start' => (string)$row['period_start'],
                'period_end' => (string)$row['period_end'],
                'expected_amount' => (float)$row['expected_amount'],
                'confirmed_amount' => (float)$row['confirmed_amount'],
                'received_amount' => (float)$row['received_amount'],
                'status' => (string)$row['status'],
                'reference' => (string)($row['reference'] ?? ''),
                'notes' => (string)($row['notes'] ?? ''),
                'updated_by' => (string)($row['updated_by'] ?? ''),
                'updated_at' => (string)$row['updated_at'],
            ], $rows);
        } catch (\Throwable $t) {
            return [];
        }
    }

    private static function loadAuditLogsFromDatabase(?string $sku = null): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            self::ensureAuditTable($pdo);
            if ($sku !== null && trim($sku) !== '') {
                $stmt = $pdo->prepare(
                    'SELECT log_id, entity_type, entity_id, action, user_name, old_value_json, new_value_json, created_at
                     FROM pricing_audit_logs
                     WHERE UPPER(entity_id) LIKE ?
                     ORDER BY created_at DESC, id DESC
                     LIMIT 300'
                );
                $stmt->execute(['%' . strtoupper(trim($sku)) . '%']);
            } else {
                $stmt = $pdo->query(
                    'SELECT log_id, entity_type, entity_id, action, user_name, old_value_json, new_value_json, created_at
                     FROM pricing_audit_logs
                     ORDER BY created_at DESC, id DESC
                     LIMIT 300'
                );
            }

            $rows = $stmt ? $stmt->fetchAll() : [];
            return array_map(static function (array $row): array {
                $createdAt = (string)($row['created_at'] ?? '');
                $timestamp = $createdAt !== '' ? date('d M Y, h:i A', strtotime($createdAt)) : '';

                return [
                    'id' => (string)($row['log_id'] ?? ''),
                    'entity_type' => (string)($row['entity_type'] ?? ''),
                    'entity_id' => (string)($row['entity_id'] ?? ''),
                    'action' => (string)($row['action'] ?? ''),
                    'user' => (string)($row['user_name'] ?? ''),
                    'old_value' => json_decode((string)($row['old_value_json'] ?? '[]'), true) ?: [],
                    'new_value' => json_decode((string)($row['new_value_json'] ?? '[]'), true) ?: [],
                    'timestamp' => $timestamp,
                    'created_at' => $createdAt,
                ];
            }, $rows);
        } catch (\Throwable $t) {
            return $_SESSION['pricing_audit_logs'] ?? [];
        }
    }

    private static function ensurePricingTables(\PDO $pdo): void
    {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS pricing_tiers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tier_id VARCHAR(50) NOT NULL UNIQUE,
                    company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
                    product_sku VARCHAR(100) NOT NULL,
                    tier_name VARCHAR(150) NOT NULL,
                    sales_channel VARCHAR(30) NOT NULL DEFAULT "all",
                    customer_class VARCHAR(100) DEFAULT NULL,
                    location VARCHAR(120) DEFAULT NULL,
                    min_selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    min_qty INT NOT NULL,
                    max_qty INT DEFAULT NULL,
                    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    start_date DATE NOT NULL,
                    end_date DATE DEFAULT NULL,
                    updated_by VARCHAR(100) DEFAULT NULL,
                    updated_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            foreach ([
                'sales_channel VARCHAR(30) NOT NULL DEFAULT "all"',
                'customer_class VARCHAR(100) DEFAULT NULL',
                'location VARCHAR(120) DEFAULT NULL',
                'min_selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            ] as $columnSql) {
                try {
                    $columnName = strtok($columnSql, ' ');
                    $pdo->exec("ALTER TABLE pricing_tiers ADD COLUMN {$columnSql}");
                    if ($columnName === 'sales_channel') {
                        $pdo->exec('CREATE INDEX idx_pricing_channel ON pricing_tiers (company_id, product_sku, sales_channel, start_date, end_date)');
                    }
                } catch (\Throwable $t) {
                }
            }
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS rebate_rules (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    rule_id VARCHAR(50) NOT NULL UNIQUE,
                    company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
                    level VARCHAR(30) NOT NULL,
                    target_key VARCHAR(150) NOT NULL,
                    rebate_pct DECIMAL(7,3) NOT NULL DEFAULT 0.000,
                    rebate_base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    adjustment_factor DECIMAL(7,3) NOT NULL DEFAULT 100.000,
                    formula_type VARCHAR(50) NOT NULL DEFAULT "standard_pct",
                    description VARCHAR(255) DEFAULT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    start_date DATE NOT NULL,
                    end_date DATE DEFAULT NULL,
                    updated_by VARCHAR(100) DEFAULT NULL,
                    updated_at DATETIME NOT NULL,
                    INDEX idx_rebate_lookup (company_id, level, target_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            foreach ([
                'rebate_base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00',
                'adjustment_factor DECIMAL(7,3) NOT NULL DEFAULT 100.000',
                'formula_type VARCHAR(50) NOT NULL DEFAULT "standard_pct"',
            ] as $columnSql) {
                try {
                    $pdo->exec("ALTER TABLE rebate_rules ADD COLUMN {$columnSql}");
                } catch (\Throwable $t) {
                }
            }
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS promotion_rules (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    promotion_id VARCHAR(50) NOT NULL UNIQUE,
                    company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
                    promotion_name VARCHAR(160) NOT NULL,
                    promotion_code VARCHAR(80) NOT NULL,
                    supplier VARCHAR(150) DEFAULT NULL,
                    eligible_sku VARCHAR(120) NOT NULL DEFAULT "ALL",
                    qualifying_qty INT NOT NULL DEFAULT 1,
                    reward_qty INT NOT NULL DEFAULT 0,
                    reward_sku VARCHAR(120) DEFAULT NULL,
                    benefit_type VARCHAR(30) NOT NULL DEFAULT "amount",
                    benefit_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    adjustment_factor DECIMAL(7,3) NOT NULL DEFAULT 100.000,
                    allocation_method VARCHAR(40) NOT NULL DEFAULT "per_unit",
                    sales_channel VARCHAR(30) NOT NULL DEFAULT "all",
                    customer_class VARCHAR(100) DEFAULT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    start_date DATE NOT NULL,
                    end_date DATE DEFAULT NULL,
                    approval_status VARCHAR(30) NOT NULL DEFAULT "approved",
                    updated_by VARCHAR(100) DEFAULT NULL,
                    updated_at DATETIME NOT NULL,
                    INDEX idx_promotion_lookup (company_id, eligible_sku, sales_channel, start_date, end_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            self::ensurePriceApprovalTable($pdo);
        } catch (\Throwable $t) {
        }
    }

    private static function ensurePriceApprovalTable(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS pricing_change_approvals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_id VARCHAR(80) NOT NULL UNIQUE,
                company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
                entity_type VARCHAR(60) NOT NULL,
                entity_id VARCHAR(160) NOT NULL,
                action_name VARCHAR(120) NOT NULL,
                old_value_json LONGTEXT DEFAULT NULL,
                new_value_json LONGTEXT DEFAULT NULL,
                payload_json LONGTEXT NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT "pending",
                requested_by VARCHAR(160) DEFAULT NULL,
                requested_at DATETIME NOT NULL,
                approved_by VARCHAR(160) DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                rejected_by VARCHAR(160) DEFAULT NULL,
                rejected_at DATETIME DEFAULT NULL,
                INDEX idx_price_approvals_status (company_id, status, requested_at),
                INDEX idx_price_approvals_entity (company_id, entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private static function persistPriceChangeApproval(array $request): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        try {
            self::ensurePriceApprovalTable($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO pricing_change_approvals
                    (request_id, company_id, entity_type, entity_id, action_name, old_value_json, new_value_json, payload_json, status, requested_by, requested_at, approved_by, approved_at, rejected_by, rejected_at)
                 VALUES
                    (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status=VALUES(status), approved_by=VALUES(approved_by), approved_at=VALUES(approved_at), rejected_by=VALUES(rejected_by), rejected_at=VALUES(rejected_at)'
            );
            $stmt->execute([
                (string)$request['id'],
                (string)$request['entity_type'],
                (string)$request['entity_id'],
                (string)$request['action'],
                json_encode($request['old_value'] ?? [], JSON_UNESCAPED_SLASHES),
                json_encode($request['new_value'] ?? [], JSON_UNESCAPED_SLASHES),
                json_encode($request['payload'] ?? [], JSON_UNESCAPED_SLASHES),
                (string)($request['status'] ?? 'pending'),
                (string)($request['requested_by'] ?? ''),
                (string)($request['requested_at'] ?? date('Y-m-d H:i:s')),
                $request['approved_by'] ?? null,
                $request['approved_at'] ?? null,
                $request['rejected_by'] ?? null,
                $request['rejected_at'] ?? null,
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function saveReviewedPriceChangeApproval(array $request): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $_SESSION['pricing_change_approvals'][(string)$request['id']] = $request;
        self::persistPriceChangeApproval($request);
    }

    private static function findPriceChangeApproval(string $requestId): ?array
    {
        $requestId = trim($requestId);
        if ($requestId === '') {
            return null;
        }

        $requests = self::getPriceChangeApprovals('all', 200);
        foreach ($requests as $request) {
            if ((string)($request['id'] ?? '') === $requestId) {
                return $request;
            }
        }

        return null;
    }

    private static function loadPriceChangeApprovalsFromDatabase(string $status = 'pending', int $limit = 50): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            self::ensurePriceApprovalTable($pdo);
            $limit = max(1, min(200, $limit));
            $where = 'company_id = "beverage"';
            if ($status !== 'all') {
                $where .= ' AND status = ' . $pdo->quote($status);
            }
            $stmt = $pdo->query(
                "SELECT request_id, entity_type, entity_id, action_name, old_value_json, new_value_json, payload_json, status, requested_by, requested_at, approved_by, approved_at, rejected_by, rejected_at
                 FROM pricing_change_approvals
                 WHERE {$where}
                 ORDER BY requested_at DESC, id DESC
                 LIMIT {$limit}"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];

            return array_map(static function (array $row): array {
                return [
                    'id' => (string)$row['request_id'],
                    'entity_type' => (string)$row['entity_type'],
                    'entity_id' => (string)$row['entity_id'],
                    'action' => (string)$row['action_name'],
                    'old_value' => json_decode((string)($row['old_value_json'] ?? ''), true) ?: [],
                    'new_value' => json_decode((string)($row['new_value_json'] ?? ''), true) ?: [],
                    'payload' => json_decode((string)($row['payload_json'] ?? ''), true) ?: [],
                    'status' => (string)$row['status'],
                    'requested_by' => (string)($row['requested_by'] ?? ''),
                    'requested_at' => (string)$row['requested_at'],
                    'approved_by' => $row['approved_by'] ?? null,
                    'approved_at' => $row['approved_at'] ?? null,
                    'rejected_by' => $row['rejected_by'] ?? null,
                    'rejected_at' => $row['rejected_at'] ?? null,
                ];
            }, $rows);
        } catch (\Throwable $t) {
            return [];
        }
    }

    private static function loadPricingTiersFromDatabase(): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            self::ensurePricingTables($pdo);
            $stmt = $pdo->query('SELECT tier_id, product_sku, tier_name, sales_channel, customer_class, location, min_selling_price, min_qty, max_qty, selling_price, is_active, start_date, end_date, updated_by, updated_at FROM pricing_tiers WHERE company_id = "beverage" ORDER BY product_sku ASC, sales_channel ASC, min_qty ASC');
            $rows = $stmt ? $stmt->fetchAll() : [];
            $tiers = [];
            foreach ($rows as $row) {
                $sku = strtoupper((string)$row['product_sku']);
                $tiers[$sku][] = [
                    'id' => (string)$row['tier_id'],
                    'sku' => $sku,
                    'tier_name' => (string)$row['tier_name'],
                    'sales_channel' => self::normalizeSalesChannel((string)($row['sales_channel'] ?? 'all')),
                    'customer_class' => $row['customer_class'] ?: null,
                    'location' => $row['location'] ?: null,
                    'min_selling_price' => (float)($row['min_selling_price'] ?? 0),
                    'min_qty' => (int)$row['min_qty'],
                    'max_qty' => $row['max_qty'] !== null ? (int)$row['max_qty'] : null,
                    'selling_price' => (float)$row['selling_price'],
                    'is_active' => (bool)$row['is_active'],
                    'start_date' => (string)$row['start_date'],
                    'end_date' => $row['end_date'] ?: null,
                    'updated_at' => (string)$row['updated_at'],
                    'updated_by' => (string)($row['updated_by'] ?? ''),
                ];
            }
            return $tiers;
        } catch (\Throwable $t) {
            return [];
        }
    }

    private static function persistPricingTier(array $tier): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        try {
            self::ensurePricingTables($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO pricing_tiers (tier_id, company_id, product_sku, tier_name, sales_channel, customer_class, location, min_selling_price, min_qty, max_qty, selling_price, is_active, start_date, end_date, updated_by, updated_at)
                 VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE tier_name=VALUES(tier_name), sales_channel=VALUES(sales_channel), customer_class=VALUES(customer_class), location=VALUES(location), min_selling_price=VALUES(min_selling_price), min_qty=VALUES(min_qty), max_qty=VALUES(max_qty), selling_price=VALUES(selling_price), is_active=VALUES(is_active), start_date=VALUES(start_date), end_date=VALUES(end_date), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)'
            );
            $stmt->execute([
                $tier['id'],
                $tier['sku'],
                $tier['tier_name'],
                $tier['sales_channel'] ?? 'all',
                $tier['customer_class'] ?? null,
                $tier['location'] ?? null,
                (float)($tier['min_selling_price'] ?? 0),
                (int)$tier['min_qty'],
                $tier['max_qty'] ?? null,
                (float)$tier['selling_price'],
                !empty($tier['is_active']) ? 1 : 0,
                $tier['start_date'],
                $tier['end_date'] ?? null,
                $tier['updated_by'] ?? null,
                $tier['updated_at'],
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function deletePricingTierFromDatabase(string $tierId, string $sku): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            self::ensurePricingTables($pdo);
            $stmt = $pdo->prepare('DELETE FROM pricing_tiers WHERE tier_id = ? AND product_sku = ? AND company_id = "beverage"');
            $stmt->execute([$tierId, $sku]);
        } catch (\Throwable $t) {
        }
    }

    private static function loadRebateRulesFromDatabase(): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            self::ensurePricingTables($pdo);
            $stmt = $pdo->query('SELECT rule_id, level, target_key, rebate_pct, rebate_base_price, adjustment_factor, formula_type, description, is_active, start_date, end_date, updated_by, updated_at FROM rebate_rules WHERE company_id = "beverage" ORDER BY FIELD(level, "promotion", "product", "brand", "supplier", "global"), target_key ASC');
            $rows = $stmt ? $stmt->fetchAll() : [];
            return array_map(static fn (array $row): array => [
                'id' => (string)$row['rule_id'],
                'level' => (string)$row['level'],
                'target_key' => (string)$row['target_key'],
                'rebate_pct' => (float)$row['rebate_pct'],
                'rebate_base_price' => (float)($row['rebate_base_price'] ?? 0),
                'adjustment_factor' => (float)($row['adjustment_factor'] ?? 100),
                'formula_type' => (string)($row['formula_type'] ?? 'standard_pct'),
                'description' => (string)($row['description'] ?? ''),
                'is_active' => (bool)$row['is_active'],
                'start_date' => (string)$row['start_date'],
                'end_date' => $row['end_date'] ?: null,
                'updated_at' => (string)$row['updated_at'],
                'updated_by' => (string)($row['updated_by'] ?? ''),
            ], $rows);
        } catch (\Throwable $t) {
            return [];
        }
    }

    private static function persistRebateRule(array $rule): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        try {
            self::ensurePricingTables($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO rebate_rules (rule_id, company_id, level, target_key, rebate_pct, rebate_base_price, adjustment_factor, formula_type, description, is_active, start_date, end_date, updated_by, updated_at)
                 VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE level=VALUES(level), target_key=VALUES(target_key), rebate_pct=VALUES(rebate_pct), rebate_base_price=VALUES(rebate_base_price), adjustment_factor=VALUES(adjustment_factor), formula_type=VALUES(formula_type), description=VALUES(description), is_active=VALUES(is_active), start_date=VALUES(start_date), end_date=VALUES(end_date), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)'
            );
            $stmt->execute([
                $rule['id'],
                $rule['level'],
                $rule['target_key'],
                (float)$rule['rebate_pct'],
                (float)($rule['rebate_base_price'] ?? 0),
                (float)($rule['adjustment_factor'] ?? 100),
                $rule['formula_type'] ?? 'standard_pct',
                $rule['description'] ?? null,
                !empty($rule['is_active']) ? 1 : 0,
                $rule['start_date'],
                $rule['end_date'] ?? null,
                $rule['updated_by'] ?? null,
                $rule['updated_at'],
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function deleteRebateRuleFromDatabase(string $ruleId): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            self::ensurePricingTables($pdo);
            $stmt = $pdo->prepare('DELETE FROM rebate_rules WHERE rule_id = ? AND company_id = "beverage"');
            $stmt->execute([$ruleId]);
        } catch (\Throwable $t) {
        }
    }

    private static function loadPromotionRulesFromDatabase(): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        try {
            self::ensurePricingTables($pdo);
            $stmt = $pdo->query('SELECT promotion_id, promotion_name, promotion_code, supplier, eligible_sku, qualifying_qty, reward_qty, reward_sku, benefit_type, benefit_value, adjustment_factor, allocation_method, sales_channel, customer_class, is_active, start_date, end_date, approval_status, updated_by, updated_at FROM promotion_rules WHERE company_id = "beverage" ORDER BY start_date DESC, promotion_name ASC');
            $rows = $stmt ? $stmt->fetchAll() : [];
            return array_map(static fn(array $row): array => [
                'id' => (string)$row['promotion_id'],
                'promotion_name' => (string)$row['promotion_name'],
                'promotion_code' => (string)$row['promotion_code'],
                'supplier' => (string)($row['supplier'] ?? ''),
                'eligible_sku' => (string)$row['eligible_sku'],
                'qualifying_qty' => (int)$row['qualifying_qty'],
                'reward_qty' => (int)$row['reward_qty'],
                'reward_sku' => (string)($row['reward_sku'] ?? ''),
                'benefit_type' => (string)$row['benefit_type'],
                'benefit_value' => (float)$row['benefit_value'],
                'adjustment_factor' => (float)$row['adjustment_factor'],
                'allocation_method' => (string)$row['allocation_method'],
                'sales_channel' => self::normalizeSalesChannel((string)$row['sales_channel']),
                'customer_class' => (string)($row['customer_class'] ?? ''),
                'is_active' => (bool)$row['is_active'],
                'start_date' => (string)$row['start_date'],
                'end_date' => $row['end_date'] ?: null,
                'approval_status' => (string)$row['approval_status'],
                'updated_by' => (string)($row['updated_by'] ?? ''),
                'updated_at' => (string)$row['updated_at'],
            ], $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    private static function persistPromotionRule(array $rule): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        try {
            self::ensurePricingTables($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO promotion_rules (promotion_id, company_id, promotion_name, promotion_code, supplier, eligible_sku, qualifying_qty, reward_qty, reward_sku, benefit_type, benefit_value, adjustment_factor, allocation_method, sales_channel, customer_class, is_active, start_date, end_date, approval_status, updated_by, updated_at)
                 VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE promotion_name=VALUES(promotion_name), promotion_code=VALUES(promotion_code), supplier=VALUES(supplier), eligible_sku=VALUES(eligible_sku), qualifying_qty=VALUES(qualifying_qty), reward_qty=VALUES(reward_qty), reward_sku=VALUES(reward_sku), benefit_type=VALUES(benefit_type), benefit_value=VALUES(benefit_value), adjustment_factor=VALUES(adjustment_factor), allocation_method=VALUES(allocation_method), sales_channel=VALUES(sales_channel), customer_class=VALUES(customer_class), is_active=VALUES(is_active), start_date=VALUES(start_date), end_date=VALUES(end_date), approval_status=VALUES(approval_status), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)'
            );
            $stmt->execute([
                $rule['id'],
                $rule['promotion_name'],
                $rule['promotion_code'],
                $rule['supplier'] ?: null,
                $rule['eligible_sku'],
                (int)$rule['qualifying_qty'],
                (int)$rule['reward_qty'],
                $rule['reward_sku'] ?: null,
                $rule['benefit_type'],
                (float)$rule['benefit_value'],
                (float)$rule['adjustment_factor'],
                $rule['allocation_method'],
                $rule['sales_channel'],
                $rule['customer_class'] ?: null,
                !empty($rule['is_active']) ? 1 : 0,
                $rule['start_date'],
                $rule['end_date'] ?? null,
                $rule['approval_status'],
                $rule['updated_by'] ?? null,
                $rule['updated_at'],
            ]);
        } catch (\Throwable) {
        }
    }

    private static function deletePromotionRuleFromDatabase(string $ruleId): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }

        try {
            self::ensurePricingTables($pdo);
            $stmt = $pdo->prepare('DELETE FROM promotion_rules WHERE promotion_id = ? AND company_id = "beverage"');
            $stmt->execute([$ruleId]);
        } catch (\Throwable) {
        }
    }

    /* =========================================================================
       INITIAL DATA GENERATORS & HELPERS
       ========================================================================= */

    private static function findProductBySku(string $sku): ?array
    {
        if (class_exists('App\Modules\BeverageWarehouse\BeverageWarehouseService')) {
            $products = \App\Modules\BeverageWarehouse\BeverageWarehouseService::getProducts();
            foreach ($products as $p) {
                if (strcasecmp((string)($p['sku'] ?? ''), $sku) === 0) {
                    return $p;
                }
            }
        }
        return null;
    }

    private static function baseSellingPrice(array $product): float
    {
        foreach (['wholesale_price', 'selling_price', 'price_per_unit'] as $field) {
            $value = (float)($product[$field] ?? 0.0);
            if ($value > 0) {
                return $value;
            }
        }

        return 0.0;
    }

    private static function defaultRebateDescription(string $level, string $targetKey, float $pct): string
    {
        return match ($level) {
            'global' => "Global Baseline Depot Rebate ({$pct}%)",
            'supplier' => "Supplier Rebate for {$targetKey} ({$pct}%)",
            'brand' => "Brand Rebate for {$targetKey} ({$pct}%)",
            'product' => "Product SKU Rebate for {$targetKey} ({$pct}%)",
            'promotion' => "Promo Code Rebate for {$targetKey} ({$pct}%)",
            default => "Rebate Rule ({$pct}%)",
        };
    }
}
