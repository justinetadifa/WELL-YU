<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/WellnessEngine.php';
require_once __DIR__ . '/PropertyAssessment.php';
require_once __DIR__ . '/AutomaticPropertyAssessment.php';
require_once __DIR__ . '/BrokerDocuments.php';

/**
 * Idempotent Demo Account & Rich Role Data Provisioning Service for wELYU (SIKLAB 2026).
 *
 * SAFETY GUARANTEES:
 * - Never auto-executes on normal page loads or config bootstrap.
 * - Invoked ONLY by `scripts/seed-demo-accounts.php` (CLI) or `api/demo-switch.php` (explicit role switch).
 * - Preserves `auto_migrate = false` and `auto_seed = false` in `app/config.php`.
 */
final class DemoAccountService
{
    public const INVESTOR_EMAIL = 'investor@welyu.local';
    public const INVESTOR_PASSWORD = 'Investor2026!';

    public const BROKER_EMAIL = 'broker@welyu.local';
    public const BROKER_PASSWORD = 'Broker2026!';

    public const ADMIN_EMAIL = 'admin@welyu.local';
    public const ADMIN_PASSWORD = 'Admin2026!';

    /**
     * Canonical definition of the 3 pre-verified demo personas.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function accountDefinitions(): array
    {
        return [
            'investor' => [
                'key' => 'investor',
                'role' => 'investor',
                'email' => self::INVESTOR_EMAIL,
                'password' => self::INVESTOR_PASSWORD,
                'name' => 'Juan Dela Cruz (Wellness Investor)',
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz (Wellness Investor)',
                'department' => null,
                'broker_review_authorized' => 0,
                'phone' => '+63 917 812 4401',
                'address' => 'Suite 14B, North Luzon Impact Capital Center, Quezon Ave.',
                'city' => 'San Fernando City, La Union',
                'profession' => 'Principal Wellness & Preventive Care Investor',
                'workspace' => 'investor-dashboard.php',
                'badge' => 'Investor',
                'summary' => 'Pre-populated with 3 saved parcels, 3 side-by-side wellness proposals (Open, Improve, Partner) with all 6 welyu-wellness-1.0 criteria & 5 readiness gates unlocked, break-even scenarios, and an active site-visit request.',
            ],
            'broker' => [
                'key' => 'broker',
                'role' => 'seller',
                'email' => self::BROKER_EMAIL,
                'password' => self::BROKER_PASSWORD,
                'name' => 'Maria Santos, REB (PRC-Verified Broker)',
                'first_name' => 'Maria',
                'last_name' => 'Santos, REB (PRC-Verified Broker)',
                'department' => null,
                'broker_review_authorized' => 0,
                'phone' => '+63 917 550 8820',
                'address' => 'Unit 204, Quezon Avenue Commercial Center, Brgy. Catbangen',
                'city' => 'San Fernando City, La Union',
                'profession' => 'PRC-Licensed Real Estate Broker (REB #0028491)',
                'prc_registration_no' => '0028491',
                'prc_valid_until' => '2029-12-31',
                'company_name' => 'Elyu Coastal & Wellness Realty Corp.',
                'workspace' => 'seller-dashboard.php',
                'badge' => 'PRC Broker',
                'summary' => 'Email & CAO/LEBDO PRC verification pre-approved (REB #0028491, valid through 2029-12-31). Manages 3 active, fully assessed San Fernando City listings (Biday, Catbangen, Poro Point) with investor inquiries, document requests, and scheduled site visits.',
            ],
            'admin' => [
                'key' => 'admin',
                'role' => 'admin',
                'email' => self::ADMIN_EMAIL,
                'password' => self::ADMIN_PASSWORD,
                'name' => 'Justine Tadifa (CICTO Admin & LEBDO/CAO Reviewer)',
                'first_name' => 'Justine',
                'last_name' => 'Tadifa (CICTO Admin & LEBDO/CAO Reviewer)',
                'department' => 'CICTO',
                'broker_review_authorized' => 1,
                'phone' => '+63 917 301 2026',
                'address' => 'City Hall Complex, Marcos Building, Brgy. I',
                'city' => 'San Fernando City, La Union',
                'profession' => 'CICTO Administrator & Joint LEBDO / CAO Reviewer',
                'workspace' => 'admin-dashboard.php',
                'badge' => 'Admin / LEBDO',
                'summary' => 'All-access municipal reviewer with CICTO system governance, CAO/LEBDO PRC broker accreditation, MCE weight configuration, and LEBDO wellness need/service/readiness clearance approval permissions.',
            ],
        ];
    }

    /**
     * Normalize a requested role alias ('investor', 'broker', 'seller', 'admin', 'guest').
     */
    public static function normalizeRoleKey(string $role): string
    {
        $clean = strtolower(trim($role));
        return match ($clean) {
            'investor' => 'investor',
            'broker', 'seller', 'reb' => 'broker',
            'admin', 'reviewer', 'lebdo', 'cicto', 'cao', 'staff' => 'admin',
            default => 'guest',
        };
    }

    /**
     * Ensure all 3 demo accounts and their rich linked data exist in `well_yu_db`,
     * and return the hydrated user record for the requested role key ('investor'|'broker'|'admin').
     */
    public static function ensureAccountForRole(PDO $pdo, string $roleKey): ?array
    {
        $normalized = self::normalizeRoleKey($roleKey);
        if ($normalized === 'guest') {
            return null;
        }
        $definitions = self::accountDefinitions();
        $targetEmail = $definitions[$normalized]['email'] ?? null;
        if ($targetEmail === null) {
            return null;
        }

        // Check if all 3 accounts and the 3 pilot properties + proposals already exist
        $existingUser = sfc_user_repository()->findByEmail($targetEmail);
        $needsSeed = $existingUser === null;
        if (!$needsSeed) {
            $propCount = (int) $pdo->query('SELECT COUNT(*) FROM properties WHERE id IN (101, 102, 103) AND deleted_at IS NULL')->fetchColumn();
            $propProposalCount = (int) $pdo->query('SELECT COUNT(*) FROM welyu_proposals')->fetchColumn();
            if ($propCount < 3 || $propProposalCount < 3) {
                $needsSeed = true;
            } elseif ($normalized === 'broker') {
                $profile = sfc_seller_profile_repository()->findByUserId((int) $existingUser['id']);
                if (($profile['applicationStatus'] ?? '') !== 'verified' || empty($profile['frontDocument']) || empty($profile['backDocument'])) {
                    $needsSeed = true;
                }
            } elseif ($normalized === 'admin' && empty($existingUser['brokerReviewAuthorized'])) {
                $needsSeed = true;
            }
        }

        if ($needsSeed) {
            self::seedAll($pdo);
        }

        return sfc_user_repository()->findByEmail($targetEmail);
    }

    /**
     * Idempotently upsert the 3 pre-verified demo accounts and all linked showcase records.
     *
     * @return array<string, mixed>
     */
    public static function seedAll(PDO $pdo): array
    {
        $definitions = self::accountDefinitions();
        $consentText = sfc_privacy_consent_text();
        $consentVersion = sfc_privacy_consent_version();
        $verifiedTimestamp = '2026-03-10 09:00:00';

        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $userIds = [];
            foreach ($definitions as $key => $def) {
                $userIds[$key] = self::upsertDemoUser($pdo, $def, $consentText, $consentVersion, $verifiedTimestamp);
            }

            $investorId = $userIds['investor'];
            $brokerId = $userIds['broker'];
            $adminId = $userIds['admin'];

            // 1. Investor preferences
            self::upsertInvestorPreferences($pdo, $investorId);

            // 2. PRC Broker verified profile + stored credential images + review log
            self::upsertVerifiedBrokerProfile($pdo, $brokerId, $adminId, $definitions['broker']);

            // 3. Seed 3 fully assessed, city-approved properties in San Fernando City (IDs 101, 102, 103)
            $propertyIds = self::upsertPilotProperties($pdo, $brokerId);

            // 4. Seed published showcase items for Opportunity Atlas / Offer Board
            self::upsertShowcaseItems($pdo);

            // 5. Seed investor shortlists, message threads, document requests, and site-visit logs
            self::upsertInvestorBrokerInteractions($pdo, $investorId, $brokerId, $adminId, $propertyIds);

            // Clear stale empty radar cache files so seeded properties load enriched local wellness baseline
            $radarCacheDir = dirname(__DIR__, 2) . '/data/cache/radar';
            if (is_dir($radarCacheDir)) {
                foreach (glob($radarCacheDir . '/radar_v2_*.json') ?: [] as $cacheFile) {
                    @unlink($cacheFile);
                }
            }

            // 6. Seed welyu_* wellness tables (configuration, needs, services, 3 proposals, 3 unlocked assessments, 3 scenarios, 3-way comparison, events)
            $wellnessSummary = self::upsertWellnessWorkspaceData($pdo, $investorId, $brokerId, $adminId, $propertyIds);

            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }

            $accountsOutput = [];
            foreach ($definitions as $key => $def) {
                $u = sfc_user_repository()->findByEmail($def['email']);
                $accountsOutput[$key] = [
                    'id' => (int) ($u['id'] ?? $userIds[$key]),
                    'name' => $def['name'],
                    'email' => $def['email'],
                    'password' => $def['password'],
                    'role' => $def['role'],
                    'department' => $def['department'],
                    'workspace' => $def['workspace'],
                    'summary' => $def['summary'],
                ];
            }

            return [
                'ok' => true,
                'accounts' => $accountsOutput,
                'property_ids' => $propertyIds,
                'wellness' => $wellnessSummary,
            ];
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function upsertDemoUser(
        PDO $pdo,
        array $def,
        string $consentText,
        string $consentVersion,
        string $verifiedTimestamp
    ): int {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $stmt->execute(['email' => $def['email']]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);

        $passwordHash = password_hash((string) $def['password'], PASSWORD_DEFAULT);

        if ($existingId > 0) {
            $upd = $pdo->prepare(
                'UPDATE users SET
                    role = :role,
                    name = :name,
                    first_name = :first_name,
                    last_name = :last_name,
                    department = :department,
                    broker_review_authorized = :broker_review_authorized,
                    password_hash = :password_hash,
                    phone = :phone,
                    address_line = :address,
                    city = :city,
                    profession = :profession,
                    adult_confirmed_at = :adult_confirmed_at,
                    account_status = \'active\',
                    email_verified_at = :email_verified_at,
                    privacy_consent_at = :privacy_consent_at,
                    privacy_consent_version = :privacy_consent_version,
                    privacy_consent_text = :privacy_consent_text,
                    identity_verification_status = \'verified\',
                    identity_verified_at = :identity_verified_at,
                    last_login_at = NOW(),
                    last_active_at = NOW()
                 WHERE id = :id'
            );
            $upd->execute([
                'role' => $def['role'],
                'name' => $def['name'],
                'first_name' => $def['first_name'],
                'last_name' => $def['last_name'],
                'department' => $def['department'],
                'broker_review_authorized' => (int) ($def['broker_review_authorized'] ?? 0),
                'password_hash' => $passwordHash,
                'phone' => $def['phone'],
                'address' => $def['address'],
                'city' => $def['city'],
                'profession' => $def['profession'],
                'adult_confirmed_at' => $verifiedTimestamp,
                'email_verified_at' => $verifiedTimestamp,
                'privacy_consent_at' => $verifiedTimestamp,
                'privacy_consent_version' => $consentVersion,
                'privacy_consent_text' => $consentText,
                'identity_verified_at' => $verifiedTimestamp,
                'id' => $existingId,
            ]);
            return $existingId;
        }

        $ins = $pdo->prepare(
            'INSERT INTO users (
                role, name, first_name, last_name, department, broker_review_authorized,
                email, email_verified_at, password_hash, phone, address_line, city, profession,
                adult_confirmed_at, account_status, privacy_consent_at, privacy_consent_version,
                privacy_consent_text, identity_verification_status, identity_verified_at,
                last_login_at, last_active_at
            ) VALUES (
                :role, :name, :first_name, :last_name, :department, :broker_review_authorized,
                :email, :email_verified_at, :password_hash, :phone, :address, :city, :profession,
                :adult_confirmed_at, \'active\', :privacy_consent_at, :privacy_consent_version,
                :privacy_consent_text, \'verified\', :identity_verified_at,
                NOW(), NOW()
            )'
        );
        $ins->execute([
            'role' => $def['role'],
            'name' => $def['name'],
            'first_name' => $def['first_name'],
            'last_name' => $def['last_name'],
            'department' => $def['department'],
            'broker_review_authorized' => (int) ($def['broker_review_authorized'] ?? 0),
            'email' => $def['email'],
            'email_verified_at' => $verifiedTimestamp,
            'password_hash' => $passwordHash,
            'phone' => $def['phone'],
            'address' => $def['address'],
            'city' => $def['city'],
            'profession' => $def['profession'],
            'adult_confirmed_at' => $verifiedTimestamp,
            'privacy_consent_at' => $verifiedTimestamp,
            'privacy_consent_version' => $consentVersion,
            'privacy_consent_text' => $consentText,
            'identity_verified_at' => $verifiedTimestamp,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private static function upsertInvestorPreferences(PDO $pdo, int $investorId): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO user_preferences (user_id, notification_cadence)
             VALUES (:user_id, \'instant\')
             ON DUPLICATE KEY UPDATE notification_cadence = \'instant\''
        );
        $stmt->execute(['user_id' => $investorId]);
    }

    private static function upsertVerifiedBrokerProfile(PDO $pdo, int $brokerId, int $adminId, array $brokerDef): void
    {
        $root = dirname(__DIR__, 2);
        $docDir = $root . '/data/broker-documents';
        if (!is_dir($docDir) && !mkdir($docDir, 0775, true) && !is_dir($docDir)) {
            throw new RuntimeException('Unable to create broker documents directory: ' . $docDir);
        }

        $sampleImage = $root . '/assets/images/welyu-shield.png';
        if (!is_file($sampleImage)) {
            $sampleImage = $root . '/assets/images/city-seal.png';
        }
        $bytes = is_file($sampleImage) ? (string) file_get_contents($sampleImage) : '';
        if ($bytes === '') {
            // Minimal valid 1x1 PNG fallback
            $bytes = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        }

        $frontId = hash('sha256', 'welyu-demo-broker-prc-front-0028491');
        $backId = hash('sha256', 'welyu-demo-broker-prc-back-0028491');

        $frontPath = $docDir . '/' . $frontId . '.bin';
        $backPath = $docDir . '/' . $backId . '.bin';
        if (!is_file($frontPath)) {
            file_put_contents($frontPath, $bytes);
        }
        if (!is_file($backPath)) {
            file_put_contents($backPath, $bytes);
        }

        $size = strlen($bytes);
        $sha256 = hash('sha256', $bytes);

        $frontMeta = [
            'id' => $frontId,
            'mime' => 'image/png',
            'size' => $size,
            'sha256' => $sha256,
            'label' => 'PRC Real Estate Broker ID (Front) — Maria Santos, REB #0028491',
            'uploadedAt' => '2026-03-10T09:30:00Z',
        ];
        $backMeta = [
            'id' => $backId,
            'mime' => 'image/png',
            'size' => $size,
            'sha256' => $sha256,
            'label' => 'PRC Real Estate Broker ID (Back) — Valid Until 2029-12-31',
            'uploadedAt' => '2026-03-10T09:30:00Z',
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO seller_profiles (
                user_id, seller_type, legal_name, display_name, phone, company_name,
                business_registration_no, government_id_no, prc_registration_no, prc_valid_until,
                prc_front_json, prc_back_json, application_revision, address_line, barangay, city,
                authorization_basis, application_status, review_notes, submitted_at, reviewed_at, reviewed_by_user_id
            ) VALUES (
                :user_id, \'broker\', :legal_name, :display_name, :phone, :company_name,
                :business_reg, :gov_id, :prc_no, :prc_valid,
                :prc_front, :prc_back, 1, :address_line, \'Catbangen\', \'San Fernando, La Union\',
                :auth_basis, \'verified\', :review_notes, \'2026-03-10 09:30:00\', \'2026-03-10 14:00:00\', :reviewer_id
            ) ON DUPLICATE KEY UPDATE
                seller_type = \'broker\',
                legal_name = VALUES(legal_name),
                display_name = VALUES(display_name),
                phone = VALUES(phone),
                company_name = VALUES(company_name),
                business_registration_no = VALUES(business_registration_no),
                government_id_no = VALUES(government_id_no),
                prc_registration_no = VALUES(prc_registration_no),
                prc_valid_until = VALUES(prc_valid_until),
                prc_front_json = VALUES(prc_front_json),
                prc_back_json = VALUES(prc_back_json),
                application_revision = GREATEST(application_revision, 1),
                address_line = VALUES(address_line),
                barangay = VALUES(barangay),
                city = VALUES(city),
                authorization_basis = VALUES(authorization_basis),
                application_status = \'verified\',
                review_notes = VALUES(review_notes),
                submitted_at = COALESCE(submitted_at, VALUES(submitted_at)),
                reviewed_at = VALUES(reviewed_at),
                reviewed_by_user_id = VALUES(reviewed_by_user_id)'
        );
        $stmt->execute([
            'user_id' => $brokerId,
            'legal_name' => 'Maria Santos, REB',
            'display_name' => 'Maria Santos, REB · Elyu Coastal & Wellness Realty',
            'phone' => $brokerDef['phone'],
            'company_name' => $brokerDef['company_name'],
            'business_reg' => 'DTI-LU-2026-00841',
            'gov_id' => 'PRC-REB-0028491',
            'prc_no' => $brokerDef['prc_registration_no'],
            'prc_valid' => $brokerDef['prc_valid_until'],
            'prc_front' => json_encode($frontMeta, JSON_THROW_ON_ERROR),
            'prc_back' => json_encode($backMeta, JSON_THROW_ON_ERROR),
            'address_line' => $brokerDef['address'],
            'auth_basis' => 'Exclusive PRC-Licensed Real Estate Broker Authority to Sell (Verified by CAO & LEBDO)',
            'review_notes' => 'PRC REB #0028491 credential front/back images and active validity through 2029-12-31 verified by CAO & LEBDO Joint Review Panel.',
            'reviewer_id' => $adminId,
        ]);

        // Ensure a verified review history row exists
        $revCheck = $pdo->prepare('SELECT COUNT(*) FROM broker_application_reviews WHERE user_id = :uid AND decision = \'verified\'');
        $revCheck->execute(['uid' => $brokerId]);
        if ((int) $revCheck->fetchColumn() === 0) {
            $snapshot = [
                'legalName' => 'Maria Santos, REB',
                'prcRegistrationNo' => $brokerDef['prc_registration_no'],
                'prcValidUntil' => $brokerDef['prc_valid_until'],
                'frontDocument' => ['id' => $frontId, 'mime' => 'image/png', 'size' => $size, 'sha256' => $sha256],
                'backDocument' => ['id' => $backId, 'mime' => 'image/png', 'size' => $size, 'sha256' => $sha256],
            ];
            $revIns = $pdo->prepare(
                'INSERT INTO broker_application_reviews (
                    user_id, reviewer_user_id, application_revision, decision, reason, findings, snapshot_json, created_at
                ) VALUES (
                    :uid, :reviewer, 1, \'verified\', :reason, :findings, :snapshot, \'2026-03-10 14:00:00\'
                )'
            );
            $revIns->execute([
                'uid' => $brokerId,
                'reviewer' => $adminId,
                'reason' => 'PRC Registration #0028491 verified in good standing through 2029-12-31.',
                'findings' => 'Approved for immediate commercial and wellness parcel representation in San Fernando City.',
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /**
     * Upsert 3 active, city-approved, 100% assessed San Fernando City properties (IDs 101, 102, 103).
     *
     * @return list<int>
     */
    private static function upsertPilotProperties(PDO $pdo, int $brokerId): array
    {
        $catalog = [
            [
                'id' => 101,
                'name' => 'Biday Preventive Rehab & Senior Mobility Parcel',
                'subname' => 'MacArthur Highway Service Spine · Brgy. Biday, San Fernando City',
                'lat' => 16.625800,
                'lng' => 120.323400,
                'area' => 0.1200, // 1,200 sqm
                'area_sqm' => 1200.0,
                'listing_purpose' => 'sale',
                'price' => 14800000,
                'lease_price' => null,
                'price_per_sqm' => 12333,
                'assessed_value_sqm' => 11500,
                'comparable_market_price' => 12500.0,
                'score' => 91,
                'type' => 'Commercial',
                'category' => 'Commercial',
                'subcategory' => 'Commercial Building',
                'corridor' => 'MacArthur Highway Spine',
                'barangay' => 'Biday',
                'zoning_classification' => 'C-2',
                'proposed_activity' => 'office_professional',
                'existing_land_use' => 'Commercial Lot with Ground-Floor Readiness',
                'road_access' => 96,
                'dist_to_road_km' => 0.06,
                'carriageway_width' => 8.0,
                'distance_to_major_road' => 60.0,
                'utility_status' => 'full_ready',
                'zoning_score' => 95,
                'image_url' => 'assets/images/landing-Property1.jpg',
                'description' => 'Level 1,200 sqm C-2 commercial parcel in Barangay Biday with direct 8-meter paved public road frontage, step-free ground-floor access potential, three-phase LUECO power, PrimeWater line, and dual fiber backbones. Matched to Barangay Biday’s documented unmet need for a Preventive Rehab & Senior Mobility Hub.',
                'tags' => ['Preventive Rehab & Senior Care', 'PWD Step-Free Ready', 'C-2 Commercial Zone', 'Biday Growth Node'],
                'facilities' => ['Three-Phase Power (LUECO)', 'PrimeWater Mainline', 'Dual Fiber Optic', 'Paved 8m Road Frontage', 'Step-Free Curb Cut'],
                'assessment_tags' => ['HIGH_TRAFFIC', 'COMMERCIAL_CORE', 'CORNER_LOT'],
                'nearby_scores' => ['commercial' => 88.0, 'complementary' => 92.0, 'competitive' => 86.0, 'anchors' => 88.0],
                'polygon' => [
                    [120.323220, 16.625650],
                    [120.323580, 16.625650],
                    [120.323580, 16.625950],
                    [120.323220, 16.625950],
                    [120.323220, 16.625650],
                ],
            ],
            [
                'id' => 102,
                'name' => 'Catbangen Civic & After-Hours Therapy Annex',
                'subname' => 'Quezon Avenue Extension · Brgy. Catbangen, San Fernando City',
                'lat' => 16.614200,
                'lng' => 120.311800,
                'area' => 0.0850, // 850 sqm
                'area_sqm' => 850.0,
                'listing_purpose' => 'sale',
                'price' => 11900000,
                'lease_price' => null,
                'price_per_sqm' => 14000,
                'assessed_value_sqm' => 13200,
                'comparable_market_price' => 14500.0,
                'score' => 88,
                'type' => 'Commercial',
                'category' => 'Commercial',
                'subcategory' => 'Retail / Shop',
                'corridor' => 'Catbangen Civic & Coastal Link',
                'barangay' => 'Catbangen',
                'zoning_classification' => 'C-2',
                'proposed_activity' => 'office_professional',
                'existing_land_use' => 'Two-Storey Commercial Clinic Annex',
                'road_access' => 93,
                'dist_to_road_km' => 0.08,
                'carriageway_width' => 7.0,
                'distance_to_major_road' => 80.0,
                'utility_status' => 'full_ready',
                'zoning_score' => 92,
                'image_url' => 'assets/images/landing-FabroBldg.jpg',
                'description' => '850 sqm C-2 commercial clinic and wellness annex along the Quezon Avenue transit corridor in Barangay Catbangen. Ideal for upgrading into a PWD-accessible Preventive Care & Evening Counseling Hub serving working households until 8:00 PM.',
                'tags' => ['Preventive Care & Counseling', 'Transit-Adjacent (95m)', 'Evening Hours Ready', 'Catbangen Urban Core'],
                'facilities' => ['Reliable LUECO Grid', 'Municipal Water Supply', 'Fiber Broadband', 'Illuminated Sidewalk', 'Dedicated Ambulance/Drop-off Bay'],
                'assessment_tags' => ['COMMERCIAL_CORE', 'NEAR_TRANSIT', 'GROWTH_CORRIDOR'],
                'nearby_scores' => ['commercial' => 90.0, 'complementary' => 88.0, 'competitive' => 82.0, 'anchors' => 90.0],
                'polygon' => [
                    [120.311650, 16.614080],
                    [120.311950, 16.614080],
                    [120.311950, 16.614320],
                    [120.311650, 16.614320],
                    [120.311650, 16.614080],
                ],
            ],
            [
                'id' => 103,
                'name' => 'Poro Point Coastal Recovery & Active Mobility Studio Lot',
                'subname' => 'Poro Point Baywalk Approach · Brgy. Poro, San Fernando City',
                'lat' => 16.609500,
                'lng' => 120.296500,
                'area' => 0.1800, // 1,800 sqm
                'area_sqm' => 1800.0,
                'listing_purpose' => 'sale',
                'price' => 24300000,
                'lease_price' => null,
                'price_per_sqm' => 13500,
                'assessed_value_sqm' => 12800,
                'comparable_market_price' => 13800.0,
                'score' => 87,
                'type' => 'Commercial',
                'category' => 'Commercial',
                'subcategory' => 'Mixed-Use Property',
                'corridor' => 'Poro Point Special Economic Zone',
                'barangay' => 'Poro',
                'zoning_classification' => 'C-2',
                'proposed_activity' => 'tour_recreation',
                'existing_land_use' => 'Coastal Wellness & Active Fitness Parcel',
                'road_access' => 90,
                'dist_to_road_km' => 0.12,
                'carriageway_width' => 6.5,
                'distance_to_major_road' => 120.0,
                'utility_status' => 'full_ready',
                'zoning_score' => 90,
                'image_url' => 'assets/images/landing-LaFinns.jpg',
                'description' => '1,800 sqm coastal commercial parcel near the Poro Point Baywalk and greenway loop. Designed for Active Fitness, Hydro-Recovery, and Co-Located Community Wellness programming with high pedestrian and cyclist connectivity.',
                'tags' => ['Active Fitness & Recovery', 'Baywalk Greenway Access', 'Co-Location Partner Ready', 'Poro Point Corridor'],
                'facilities' => ['Grid + Solar Hybrid Ready', 'Potable Water Line', 'High-Speed Fiber', 'Greenway Bike Path Frontage', 'Storm Drainage Outfall'],
                'assessment_tags' => ['TOURISM_ZONE', 'GROWTH_CORRIDOR', 'CORNER_LOT'],
                'nearby_scores' => ['commercial' => 84.0, 'complementary' => 90.0, 'competitive' => 85.0, 'anchors' => 86.0],
                'polygon' => [
                    [120.296300, 16.609340],
                    [120.296700, 16.609340],
                    [120.296700, 16.609660],
                    [120.296300, 16.609660],
                    [120.296300, 16.609340],
                ],
            ],
        ];

        $ownerContact = [
            'name' => 'Maria Santos, REB (PRC #0028491)',
            'email' => self::BROKER_EMAIL,
            'phone' => '+63 917 550 8820',
            'responseSla' => 'Responds within 2 business hours',
            'additionalPhones' => [],
            'socialLinks' => [],
        ];

        $documents = [
            'authority_to_sell' => [
                'status' => 'validated',
                'isVerified' => true,
                'verifiedAt' => '2026-03-12 10:00:00',
                'referenceNo' => 'ATS-SFC-2026-0028491',
            ],
            'site_photos' => 'reviewed',
            'zoning_clearance' => 'reviewed',
            'hazard_report' => 'reviewed',
            'title_copy' => 'reviewed',
            'tax_declaration' => 'reviewed',
            'survey_plan' => 'reviewed',
        ];

        $ids = [];
        foreach ($catalog as $item) {
            $propId = (int) $item['id'];
            $ids[] = $propId;

            $rawInputs = [
                'spatial_accessibility' => [
                    'road_access' => 'frontage',
                    'road_surface_passability' => 'paved',
                    'carriageway_width' => $item['carriageway_width'],
                    'distance_to_major_road' => $item['distance_to_major_road'],
                ],
                'infrastructure_readiness' => [
                    'electricity' => 'grid_reliable',
                    'water' => 'piped_reliable',
                    'internet' => 'fiber_reliable',
                    'drainage' => 'adequate_functional',
                ],
                'property_cost_market_competitiveness' => [
                    'land_area' => $item['area_sqm'],
                    'offered_space_scope' => 'entire_land',
                    'offered_area_sqm' => $item['area_sqm'],
                    'asking_price' => $item['price'],
                    'bir_zonal_value' => $item['assessed_value_sqm'],
                    'comparable_market_price' => $item['comparable_market_price'],
                    'comparable_unit' => 'PHP/m2',
                    'comparable_source' => 'San Fernando City CAO / Market Comparables 2026',
                    'comparables_suitable' => true,
                    'listing_purpose' => 'sale',
                    'is_verified' => true,
                ],
                'nearby_businesses' => [
                    'commercial_activity' => $item['nearby_scores']['commercial'],
                    'complementary_businesses' => $item['nearby_scores']['complementary'],
                    'competitive_pressure' => $item['nearby_scores']['competitive'],
                    'activity_anchors' => $item['nearby_scores']['anchors'],
                    'proposed_activity' => $item['proposed_activity'],
                    'is_verified' => true,
                    'has_certified_benchmarks' => true,
                    'sufficient_source_data' => true,
                    'business_radius_m' => 500,
                    'coverage_complete' => true,
                    'benchmarks' => $item['nearby_scores'],
                ],
                'risk_constraints' => [
                    'flood_susceptibility' => 'low',
                    'active_fault' => 'outside_buffer',
                    'landslide_susceptibility' => 'low',
                    'storm_surge' => 'no_exposure',
                    'liquefaction_susceptibility' => 'low',
                    'tsunami_exposure' => 'no_exposure',
                ],
            ];

            $evaluated = PropertyAssessment::scores($rawInputs, [
                'property_id' => $propId,
                'proposed_activity' => $item['proposed_activity'],
                'zoning_district' => $item['zoning_classification'],
                'assessed_at' => '2026-03-15T10:00:00Z',
                'preview_only' => true,
            ]);

            $radarSnapshot = SurroundingsRadar::search(
                (float) $item['lat'],
                (float) $item['lng'],
                500,
                'rehab_mobility',
                ['local_only' => true]
            );

            $cs = $evaluated['criterion_scores'] ?? [];
            $baselineRadarScore = (float) ($radarSnapshot['wellnessBaseline']['baseline_score'] ?? 0);
            $wWeights = [
                'mobility_entrance' => 20.0,
                'hours_readiness' => 15.0,
                'affordability_cost' => 20.0,
                'service_baseline' => 20.0,
                'environment_safety' => 15.0,
                'zoning_gates' => 10.0,
            ];
            $wLabels = [
                'mobility_entrance' => 'Mobility & entrance',
                'hours_readiness' => 'Hours & readiness',
                'affordability_cost' => 'Affordability & cost',
                'service_baseline' => 'Service baseline',
                'environment_safety' => 'Environment & safety',
                'zoning_gates' => 'Zoning & gates',
            ];
            $wScores = [
                'mobility_entrance' => (float) ($cs['spatial_accessibility'] ?? $item['road_access']),
                'hours_readiness' => (float) ($cs['infrastructure_readiness'] ?? 95.0),
                'affordability_cost' => (float) ($cs['property_cost_market_competitiveness'] ?? 88.0),
                'service_baseline' => $baselineRadarScore > 0 ? round($baselineRadarScore, 1) : (float) ($cs['nearby_businesses'] ?? 87.0),
                'environment_safety' => (float) ($cs['risk_constraints'] ?? 91.0),
                'zoning_gates' => (float) ($cs['zoning_compatibility'] ?? $item['zoning_score'] ?? 95.0),
            ];
            $wContribs = [];
            foreach ($wWeights as $wKey => $wPct) {
                $wContribs[$wKey] = $wScores[$wKey] * $wPct / 100.0;
            }
            $wUnrounded = array_sum($wContribs);
            $evaluated['wellnessMce'] = [
                'version' => WellnessEngine::VERSION,
                'weights' => $wWeights,
                'labels' => $wLabels,
                'scores' => $wScores,
                'contributions' => $wContribs,
                'unrounded_total' => $wUnrounded,
                'score' => round($wUnrounded, 1, PHP_ROUND_HALF_UP),
                'complete' => true,
            ];

            $assessmentJson = json_encode($evaluated['criterion_scores'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $automaticJson = json_encode($evaluated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

            $parcel = [
                'boundaryGeoJson' => [
                    'type' => 'Polygon',
                    'coordinates' => [$item['polygon']],
                ],
                'surveyAreaSqm' => $item['area_sqm'],
                'estimatedAreaSqm' => $item['area_sqm'],
                'frontageMeters' => 28.0,
                'shapeType' => 'regular',
                'topography' => 'flat',
                'observations' => array_merge(
                    $rawInputs['spatial_accessibility'],
                    $rawInputs['infrastructure_readiness'],
                    $rawInputs['risk_constraints'],
                    [
                        'comparable_market_price' => $item['comparable_market_price'],
                        'bir_zonal_value' => $item['assessed_value_sqm'],
                        'offered_space_scope' => 'entire_land',
                        'nearby_businesses' => $rawInputs['nearby_businesses'],
                        'nearby_screening' => $radarSnapshot['screening'] ?? null,
                        'wellness_baseline' => $radarSnapshot['wellnessBaseline'] ?? null,
                    ]
                ),
                'utilities' => [
                    'electricity' => ['connection_status' => 'grid_reliable', 'provider' => 'LUECO'],
                    'water' => ['connection_status' => 'piped_reliable', 'provider' => 'PrimeWater / Metro San Fernando Water District'],
                    'internet' => ['connection_status' => 'fiber_reliable', 'provider' => 'PLDT / Converge Dual Fiber'],
                    'additional_services' => ['drainage' => 'adequate'],
                ],
            ];

            $stmt = $pdo->prepare(
                'INSERT INTO properties (
                    id, name, subname, city, lat, lng, area, listing_purpose, price, lease_price,
                    lease_period, lease_price_unit, price_per_sqm, status, approval_state,
                    deleted_at, archived_at, score, type, corridor, tags_json, facilities_json,
                    road_access, image_url, description, barangay, owner_contact_json, documents_json,
                    seller_user_id, documents_reviewed_at, site_verified_at, last_confirmed_available_at,
                    dist_to_road_km, utility_status, zoning_score, existing_land_use, zoning_classification,
                    proposed_activity, clup_allowed_uses_json, clup_conditional_uses_json, clup_restricted_uses_json,
                    clup_source_reference, clup_verified_at, assessed_value_sqm, readiness_notes,
                    category, subcategory, assessment_json, automatic_assessment_json,
                    assessment_tags_json, nearby_properties_json, parcel_json, contact_mode,
                    contact_broker_user_id, review_note
                ) VALUES (
                    :id, :name, :subname, \'San Fernando, La Union\', :lat, :lng, :area, :listing_purpose, :price, :lease_price,
                    \'month\', \'total\', :price_per_sqm, \'Available\', \'approved\',
                    NULL, NULL, :score, :type, :corridor, :tags_json, :facilities_json,
                    :road_access, :image_url, :description, :barangay, :owner_contact_json, :documents_json,
                    :seller_user_id, \'2026-03-14 11:00:00\', \'2026-03-14 15:00:00\', \'2026-04-10 09:00:00\',
                    :dist_to_road_km, :utility_status, :zoning_score, :existing_land_use, :zoning_classification,
                    :proposed_activity, :allowed_uses, :conditional_uses, :restricted_uses,
                    \'San Fernando City Zoning Ordinance 2018-06 & CLUP 2026\', \'2026-03-14 11:00:00\', :assessed_value_sqm, :readiness_notes,
                    :category, :subcategory, :assessment_json, :automatic_assessment_json,
                    :assessment_tags_json, \'[]\', :parcel_json, \'broker\',
                    :contact_broker_user_id, :review_note
                ) ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    subname = VALUES(subname),
                    lat = VALUES(lat),
                    lng = VALUES(lng),
                    area = VALUES(area),
                    listing_purpose = VALUES(listing_purpose),
                    price = VALUES(price),
                    price_per_sqm = VALUES(price_per_sqm),
                    status = \'Available\',
                    approval_state = \'approved\',
                    deleted_at = NULL,
                    archived_at = NULL,
                    score = VALUES(score),
                    type = VALUES(type),
                    corridor = VALUES(corridor),
                    tags_json = VALUES(tags_json),
                    facilities_json = VALUES(facilities_json),
                    road_access = VALUES(road_access),
                    image_url = VALUES(image_url),
                    description = VALUES(description),
                    barangay = VALUES(barangay),
                    owner_contact_json = VALUES(owner_contact_json),
                    documents_json = VALUES(documents_json),
                    seller_user_id = VALUES(seller_user_id),
                    documents_reviewed_at = VALUES(documents_reviewed_at),
                    site_verified_at = VALUES(site_verified_at),
                    last_confirmed_available_at = VALUES(last_confirmed_available_at),
                    dist_to_road_km = VALUES(dist_to_road_km),
                    utility_status = VALUES(utility_status),
                    zoning_score = VALUES(zoning_score),
                    existing_land_use = VALUES(existing_land_use),
                    zoning_classification = VALUES(zoning_classification),
                    proposed_activity = VALUES(proposed_activity),
                    clup_allowed_uses_json = VALUES(clup_allowed_uses_json),
                    clup_verified_at = VALUES(clup_verified_at),
                    assessed_value_sqm = VALUES(assessed_value_sqm),
                    readiness_notes = VALUES(readiness_notes),
                    category = VALUES(category),
                    subcategory = VALUES(subcategory),
                    assessment_json = VALUES(assessment_json),
                    automatic_assessment_json = VALUES(automatic_assessment_json),
                    assessment_tags_json = VALUES(assessment_tags_json),
                    parcel_json = VALUES(parcel_json),
                    contact_mode = \'broker\',
                    contact_broker_user_id = VALUES(contact_broker_user_id),
                    review_note = VALUES(review_note)'
            );

            $stmt->execute([
                'id' => $propId,
                'name' => $item['name'],
                'subname' => $item['subname'],
                'lat' => $item['lat'],
                'lng' => $item['lng'],
                'area' => $item['area'],
                'listing_purpose' => $item['listing_purpose'],
                'price' => $item['price'],
                'lease_price' => $item['lease_price'],
                'price_per_sqm' => $item['price_per_sqm'],
                'score' => (int) round((float) ($evaluated['iaiScore'] ?? $item['score'])),
                'type' => $item['type'],
                'corridor' => $item['corridor'],
                'tags_json' => json_encode($item['tags'], JSON_THROW_ON_ERROR),
                'facilities_json' => json_encode($item['facilities'], JSON_THROW_ON_ERROR),
                'road_access' => $item['road_access'],
                'image_url' => $item['image_url'],
                'description' => $item['description'],
                'barangay' => $item['barangay'],
                'owner_contact_json' => json_encode($ownerContact, JSON_THROW_ON_ERROR),
                'documents_json' => json_encode($documents, JSON_THROW_ON_ERROR),
                'seller_user_id' => $brokerId,
                'dist_to_road_km' => $item['dist_to_road_km'],
                'utility_status' => $item['utility_status'],
                'zoning_score' => $item['zoning_score'],
                'existing_land_use' => $item['existing_land_use'],
                'zoning_classification' => $item['zoning_classification'],
                'proposed_activity' => $item['proposed_activity'],
                'allowed_uses' => json_encode(['Preventive Rehab & Mobility Clinic', 'Outpatient Wellness Center', 'Active Fitness & Recovery Studio', 'Professional Services Office'], JSON_THROW_ON_ERROR),
                'conditional_uses' => json_encode(['24-Hour Urgent Care Annex'], JSON_THROW_ON_ERROR),
                'restricted_uses' => json_encode(['Heavy Pollutive Manufacturing', 'Hazardous Chemical Storage'], JSON_THROW_ON_ERROR),
                'assessed_value_sqm' => $item['assessed_value_sqm'],
                'readiness_notes' => 'Verified by CICTO, CAO, and LEBDO for San Fernando City priority commercial & wellness sites.',
                'category' => $item['category'],
                'subcategory' => $item['subcategory'],
                'assessment_json' => $assessmentJson,
                'automatic_assessment_json' => $automaticJson,
                'assessment_tags_json' => json_encode($item['assessment_tags'], JSON_THROW_ON_ERROR),
                'parcel_json' => json_encode($parcel, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'contact_broker_user_id' => $brokerId,
                'review_note' => 'Approved by CICTO & CAO/LEBDO Joint Review Panel — Authority to Sell & CLUP C-2 compliance verified.',
            ]);

            // Property media (update stale paths and insert if absent)
            $mediaCheck = $pdo->prepare('SELECT id, source FROM property_media WHERE property_id = :pid ORDER BY sort_order ASC, id ASC LIMIT 1');
            $mediaCheck->execute(['pid' => $propId]);
            $existingMedia = $mediaCheck->fetch();
            $mediaAlt = $item['name'] . ' — Street frontage and utility access in Brgy. ' . $item['barangay'] . ', San Fernando City.';
            if (!$existingMedia) {
                $mediaIns = $pdo->prepare(
                    'INSERT INTO property_media (property_id, kind, source, alt_text, sort_order)
                     VALUES (:pid, \'image\', :source, :alt, 1)'
                );
                $mediaIns->execute([
                    'pid' => $propId,
                    'source' => $item['image_url'],
                    'alt' => $mediaAlt,
                ]);
            } elseif (str_starts_with((string) ($existingMedia['source'] ?? ''), 'assets/images/properties/')) {
                $mediaUpd = $pdo->prepare('UPDATE property_media SET source = :source, alt_text = :alt WHERE id = :id');
                $mediaUpd->execute([
                    'id' => (int) $existingMedia['id'],
                    'source' => $item['image_url'],
                    'alt' => $mediaAlt,
                ]);
            }

            // Property due diligence
            $ddState = [
                'title_verification' => true,
                'zoning_compliance' => true,
                'utility_inspection' => true,
                'hazard_clearance' => true,
                'authority_to_sell' => true,
            ];
            $ddStmt = $pdo->prepare(
                'INSERT INTO property_due_diligence (property_id, state_json, updated_at)
                 VALUES (:pid, :state, NOW())
                 ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = NOW()'
            );
            $ddStmt->execute([
                'pid' => $propId,
                'state' => json_encode($ddState, JSON_THROW_ON_ERROR),
            ]);
        }

        return $ids;
    }

    private static function upsertShowcaseItems(PDO $pdo): void
    {
        $items = [
            [
                'id' => 1,
                'feature_type' => 'ppp',
                'title' => 'Barangay Biday Preventive Rehab & Senior Mobility Hub',
                'slug' => 'biday-preventive-rehab-senior-mobility-hub',
                'partner_label' => 'LEBDO & City Health Office',
                'summary' => 'Turnkey 1,200 sqm C-2 commercial parcel matched to Barangay Biday’s senior mobility and post-stroke rehabilitation gap.',
                'description' => 'All 5 municipal readiness clearances verified. Step-free 8-meter paved public road frontage with three-phase LUECO grid power and PrimeWater connection.',
                'category' => 'Preventive Care & Rehab',
                'location_label' => 'MacArthur Highway Spine · Brgy. Biday',
                'barangay' => 'Biday',
                'status' => 'Investment Ready (Mode A: Open)',
                'pipeline_mode' => 'open',
                'supply_signal' => 'high_demand',
                'cover_image_url' => 'assets/images/landing-Property1.jpg',
                'primary_metric_label' => 'Indicative CapEx',
                'primary_metric_value' => 'PHP 1.85M Fit-Out',
                'secondary_metric_label' => 'Wellness IAI',
                'secondary_metric_value' => '88.88 / 100',
                'investor_thesis' => 'High senior & post-stroke physical therapy demand with zero step-free evening rehab studios within 2 km.',
                'ideal_operator' => 'Physical Therapy & Senior Mobility Clinic Operator',
                'avoidance_note' => 'Excludes acute inpatient hospital beds or diagnostic laboratories.',
                'related_property_id' => 101,
                'is_published' => 1,
                'is_featured' => 1,
                'sort_order' => 1,
            ],
            [
                'id' => 2,
                'feature_type' => 'incentive',
                'title' => 'Catbangen After-Hours Preventive Care & PWD Ramp Upgrade',
                'slug' => 'catbangen-after-hours-preventive-care-pwd-ramp-upgrade',
                'partner_label' => 'LEBDO & City Engineering Office',
                'summary' => 'Facility upgrade pathway adding a step-free PWD entrance ramp and extending weekday preventive care & counseling hours until 8:00 PM.',
                'description' => 'Targeted CapEx retrofit of an existing Catbangen clinic along Quezon Avenue to serve working households and senior residents after 5:00 PM.',
                'category' => 'Mental & Preventive Wellness',
                'location_label' => 'Catbangen Civic & Coastal Link · Brgy. Catbangen',
                'barangay' => 'Catbangen',
                'status' => 'Active Co-Investment (Mode B: Improve)',
                'pipeline_mode' => 'improve',
                'supply_signal' => 'after_hours_gap',
                'cover_image_url' => 'assets/images/landing-FabroBldg.jpg',
                'primary_metric_label' => 'Upgrade CapEx',
                'primary_metric_value' => 'PHP 420K Retrofit',
                'secondary_metric_label' => 'Wellness IAI',
                'secondary_metric_value' => '86.25 / 100',
                'investor_thesis' => 'Fastest payback (13.3 months) by unlocking evening utilization on an already permitted clinical footprint.',
                'ideal_operator' => 'Outpatient Therapy & Preventive Care Group',
                'avoidance_note' => 'Maintain step-free ground-floor entrance compliance.',
                'related_property_id' => 102,
                'is_published' => 1,
                'is_featured' => 1,
                'sort_order' => 2,
            ],
            [
                'id' => 3,
                'feature_type' => 'corridor',
                'title' => 'Poro Point Baywalk Active Fitness & Co-Located Recovery Network',
                'slug' => 'poro-point-baywalk-active-fitness-recovery-network',
                'partner_label' => 'LEBDO & Poro Point Development Council',
                'summary' => 'Low-CapEx shared-space partnership co-locating physical therapy, active recovery, and community nutrition workshops.',
                'description' => 'Co-locates certified recovery specialists inside an existing Poro Point coastal fitness studio with direct Baywalk greenway connectivity.',
                'category' => 'Active Fitness & Recovery',
                'location_label' => 'Poro Point Special Economic Zone · Brgy. Poro',
                'barangay' => 'Poro',
                'status' => 'Partner Matching Open (Mode C: Partner)',
                'pipeline_mode' => 'partner',
                'supply_signal' => 'co_location_ready',
                'cover_image_url' => 'assets/images/landing-LaFinns.jpg',
                'primary_metric_label' => 'Partner Setup',
                'primary_metric_value' => 'PHP 260K Shared Setup',
                'secondary_metric_label' => 'Wellness IAI',
                'secondary_metric_value' => '85.40 / 100',
                'investor_thesis' => 'Lowest break-even threshold (44 visits/month) and 8.7-month payback via revenue-share co-location.',
                'ideal_operator' => 'Sports Recovery, Mobility & Nutrition Partner',
                'avoidance_note' => 'Coordinate evening schedule blocks with host fitness studio.',
                'related_property_id' => 103,
                'is_published' => 1,
                'is_featured' => 1,
                'sort_order' => 3,
            ],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO showcase_items (
                id, feature_type, title, slug, partner_label, summary, description, category,
                location_label, barangay, status, pipeline_mode, supply_signal, cover_image_url,
                primary_metric_label, primary_metric_value, secondary_metric_label, secondary_metric_value,
                investor_thesis, ideal_operator, avoidance_note, related_property_id,
                is_published, is_featured, sort_order
            ) VALUES (
                :id, :feature_type, :title, :slug, :partner_label, :summary, :description, :category,
                :location_label, :barangay, :status, :pipeline_mode, :supply_signal, :cover_image_url,
                :primary_metric_label, :primary_metric_value, :secondary_metric_label, :secondary_metric_value,
                :investor_thesis, :ideal_operator, :avoidance_note, :related_property_id,
                :is_published, :is_featured, :sort_order
            ) ON DUPLICATE KEY UPDATE
                feature_type = VALUES(feature_type),
                title = VALUES(title),
                slug = VALUES(slug),
                partner_label = VALUES(partner_label),
                summary = VALUES(summary),
                description = VALUES(description),
                category = VALUES(category),
                location_label = VALUES(location_label),
                barangay = VALUES(barangay),
                status = VALUES(status),
                pipeline_mode = VALUES(pipeline_mode),
                supply_signal = VALUES(supply_signal),
                cover_image_url = VALUES(cover_image_url),
                primary_metric_label = VALUES(primary_metric_label),
                primary_metric_value = VALUES(primary_metric_value),
                secondary_metric_label = VALUES(secondary_metric_label),
                secondary_metric_value = VALUES(secondary_metric_value),
                investor_thesis = VALUES(investor_thesis),
                ideal_operator = VALUES(ideal_operator),
                avoidance_note = VALUES(avoidance_note),
                related_property_id = VALUES(related_property_id),
                is_published = VALUES(is_published),
                is_featured = VALUES(is_featured),
                sort_order = VALUES(sort_order)'
        );
        foreach ($items as $row) {
            $stmt->execute($row);
        }
    }

    private static function upsertInvestorBrokerInteractions(
        PDO $pdo,
        int $investorId,
        int $brokerId,
        int $adminId,
        array $propertyIds
    ): void {
        // 1. Shortlists for Investor
        $slStmt = $pdo->prepare(
            'INSERT IGNORE INTO property_shortlists (investor_user_id, property_id)
             VALUES (:uid, :pid)'
        );
        foreach ($propertyIds as $pid) {
            $slStmt->execute([
                'uid' => $investorId,
                'pid' => $pid,
            ]);
        }

        // 2. Message Threads & Messages between Investor and PRC Broker on Property #101 & #103
        $threadStmt = $pdo->prepare(
            'INSERT INTO message_threads (property_id, investor_user_id, seller_user_id, subject, last_message_at)
             VALUES (:pid, :investor_id, :seller_id, :subject, NOW())
             ON DUPLICATE KEY UPDATE
                seller_user_id = VALUES(seller_user_id),
                subject = VALUES(subject),
                last_message_at = NOW()'
        );
        $threadStmt->execute([
            'pid' => 101,
            'investor_id' => $investorId,
            'seller_id' => $brokerId,
            'subject' => 'Site Inquiry: Biday Preventive Rehab & Senior Mobility Hub (1,200 sqm)',
        ]);
        $threadStmt->execute([
            'pid' => 103,
            'investor_id' => $investorId,
            'seller_id' => $brokerId,
            'subject' => 'Poro Point Baywalk Active Recovery Co-Location & Ground-Truth Audit',
        ]);

        $threadIdStmt = $pdo->prepare('SELECT id FROM message_threads WHERE property_id = :pid AND investor_user_id = :investor_id LIMIT 1');
        $threadIdStmt->execute(['pid' => 101, 'investor_id' => $investorId]);
        $threadId101 = (int) ($threadIdStmt->fetchColumn() ?: 0);

        $threadIdStmt->execute(['pid' => 103, 'investor_id' => $investorId]);
        $threadId103 = (int) ($threadIdStmt->fetchColumn() ?: 0);

        if ($threadId101 > 0) {
            $msgCountStmt = $pdo->prepare('SELECT COUNT(*) FROM property_messages WHERE thread_id = :tid');
            $msgCountStmt->execute(['tid' => $threadId101]);
            if ((int) $msgCountStmt->fetchColumn() === 0) {
                $msgIns = $pdo->prepare(
                    'INSERT INTO property_messages (thread_id, property_id, sender_user_id, recipient_user_id, sender_name, role, text, created_at)
                     VALUES (:tid, 101, :sender_id, :recipient_id, :sender_name, :role, :text, :created_at)'
                );
                $msgIns->execute([
                    'tid' => $threadId101,
                    'sender_id' => $investorId,
                    'recipient_id' => $brokerId,
                    'sender_name' => 'Juan Dela Cruz (Wellness Investor)',
                    'role' => 'investor',
                    'text' => 'Good day Maria! We evaluated the three wellness pathways (Open, Improve, Partner) and shortlisted the Biday 1,200 sqm C-2 parcel for our Preventive Rehab & Senior Mobility Hub. Can we confirm the step-free curb-cut alignment and conduct our joint site walk this week?',
                    'created_at' => '2026-04-12 10:15:00',
                ]);
                $msgIns->execute([
                    'tid' => $threadId101,
                    'sender_id' => $brokerId,
                    'recipient_id' => $investorId,
                    'sender_name' => 'Maria Santos, REB (PRC-Verified Broker)',
                    'role' => 'seller',
                    'text' => 'Hello Juan! Yes, the 28-meter frontage along the Biday service spine has an approved step-free grade and existing LUECO three-phase & PrimeWater connections. I have confirmed your scheduled site visit and uploaded the validated Authority to Sell and C-2 Zoning Clearance.',
                    'created_at' => '2026-04-12 11:05:00',
                ]);
            }
        }

        // 3. Property Document Requests (1 fulfilled on #101, 1 requested on #102 for live demo action)
        $pdo->exec("UPDATE property_document_requests SET status = 'fulfilled' WHERE status = 'approved'");
        $docReqCheck101 = $pdo->prepare('SELECT COUNT(*) FROM property_document_requests WHERE property_id = 101 AND requester_user_id = :uid');
        $docReqCheck101->execute(['uid' => $investorId]);
        if ((int) $docReqCheck101->fetchColumn() === 0) {
            $docIns = $pdo->prepare(
                'INSERT INTO property_document_requests (
                    property_id, requester_user_id, seller_user_id, requester_name, requester_role,
                    document_name, note, status, response_note, resolved_at
                ) VALUES (
                    :pid, :req_uid, :seller_uid, :req_name, \'investor\',
                    :doc_name, :note, :status, :resp_note, :resolved_at
                )'
            );
            $docIns->execute([
                'pid' => 101,
                'req_uid' => $investorId,
                'seller_uid' => $brokerId,
                'req_name' => 'Juan Dela Cruz (Wellness Investor)',
                'doc_name' => 'zoning_clearance',
                'note' => 'Final engineering & readiness gate verification for the Biday Preventive Rehab & Senior Mobility Hub.',
                'status' => 'fulfilled',
                'resp_note' => 'Full C-2 Locational Clearance, DENR Hazard Clearance, and Lot Survey Plan shared with investor.',
                'resolved_at' => '2026-04-12 11:10:00',
            ]);
            $docIns->execute([
                'pid' => 102,
                'req_uid' => $investorId,
                'seller_uid' => $brokerId,
                'req_name' => 'Juan Dela Cruz (Wellness Investor)',
                'doc_name' => 'site_photos',
                'note' => 'Reviewing ground-floor PWD ramp retrofitting clearance for Mode B (Improve) in Brgy. Catbangen.',
                'status' => 'requested',
                'resp_note' => null,
                'resolved_at' => null,
            ]);
        }

        // 4. Scheduled Site Visit (#101) & Completed Field Audit (#103)
        if ($threadId101 > 0) {
            $visitIns = $pdo->prepare(
                'INSERT INTO visit_logs (
                    property_id, thread_id, investor_user_id, seller_user_id, investment_purpose,
                    primary_start_at, primary_end_at, secondary_start_at, secondary_end_at,
                    confirmed_start_at, confirmed_end_at, visited_at, status,
                    field_audit_json, ground_truth_multiplier, activity_json
                ) VALUES (
                    :pid, :tid, :investor_id, :seller_id, :purpose,
                    :p_start, :p_end, :s_start, :s_end,
                    :c_start, :c_end, :visited_at, :status,
                    :audit_json, :multiplier, :activity_json
                ) ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    confirmed_start_at = VALUES(confirmed_start_at),
                    confirmed_end_at = VALUES(confirmed_end_at),
                    field_audit_json = VALUES(field_audit_json),
                    ground_truth_multiplier = VALUES(ground_truth_multiplier)'
            );
            $visitIns->execute([
                'pid' => 101,
                'tid' => $threadId101,
                'investor_id' => $investorId,
                'seller_id' => $brokerId,
                'purpose' => 'Joint Investor, PRC Broker & LEBDO Technical Inspection — Biday Preventive Rehab Hub',
                'p_start' => '2026-04-22 10:00:00',
                'p_end' => '2026-04-22 11:30:00',
                's_start' => '2026-04-23 14:00:00',
                's_end' => '2026-04-23 15:30:00',
                'c_start' => '2026-04-22 10:00:00',
                'c_end' => '2026-04-22 11:30:00',
                'visited_at' => null,
                'status' => 'confirmed',
                'audit_json' => null,
                'multiplier' => null,
                'activity_json' => json_encode([
                    ['action' => 'proposed', 'actor' => 'Juan Dela Cruz (Wellness Investor)', 'at' => '2026-04-12T10:15:00Z'],
                    ['action' => 'confirmed', 'actor' => 'Maria Santos, REB (PRC-Verified Broker)', 'at' => '2026-04-12T11:05:00Z'],
                ], JSON_THROW_ON_ERROR),
            ]);

            if ($threadId103 > 0) {
                $visitIns->execute([
                    'pid' => 103,
                    'tid' => $threadId103,
                    'investor_id' => $investorId,
                    'seller_id' => $brokerId,
                    'purpose' => 'Municipal Ground-Truth & Poro Point Baywalk Greenway Verification',
                    'p_start' => '2026-03-14 14:00:00',
                    'p_end' => '2026-03-14 15:30:00',
                    's_start' => '2026-03-15 10:00:00',
                    's_end' => '2026-03-15 11:30:00',
                    'c_start' => '2026-03-14 14:00:00',
                    'c_end' => '2026-03-14 15:30:00',
                    'visited_at' => '2026-03-14 15:15:00',
                    'status' => 'completed',
                    'audit_json' => json_encode(['utilitiesVerified' => true, 'roadFrontageVerified' => true, 'hazardClearanceVerified' => true], JSON_THROW_ON_ERROR),
                    'multiplier' => 1.03,
                    'activity_json' => json_encode([
                        ['action' => 'completed', 'actor' => 'Justine Tadifa (CICTO Admin & LEBDO/CAO Reviewer)', 'at' => '2026-03-14T15:15:00Z'],
                    ], JSON_THROW_ON_ERROR),
                ]);
            }
        }
    }

    /**
     * Upsert `welyu_*` records so `wellness.php` has:
     * - Active `welyu-wellness-1.0` configuration
     * - 3 reviewed community needs (`welyu_needs`) owned by Investor (so Admin can also re-review)
     * - 3 reviewed baseline services (`welyu_services`) owned by Investor
     * - 3 proposals (`Open`, `Improve`, `Partner` in `welyu_proposals`) owned by Investor
     * - 3 unlocked assessments (`welyu_assessments`) with all 6 criteria + 5 readiness clearances verified
     * - 3 financial scenarios (`welyu_scenarios`)
     * - 1 saved 3-way comparison (`welyu_comparisons` + `welyu_comparison_items`)
     * - Implementation milestone events (`welyu_events`)
     *
     * @return array<string, mixed>
     */
    private static function upsertWellnessWorkspaceData(
        PDO $pdo,
        int $investorId,
        int $brokerId,
        int $adminId,
        array $propertyIds
    ): array {
        $presets = WellnessEngine::siklabPilotPresets();
        $configPayload = $presets['configuration'] ?? WellnessEngine::defaultConfiguration();

        // 1. Active configuration in welyu_configurations
        $cfgId = (int) ($pdo->query("SELECT id FROM welyu_configurations WHERE methodology_version = 'welyu-wellness-1.0' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);
        if ($cfgId < 1) {
            $cfgIns = $pdo->prepare(
                'INSERT INTO welyu_configurations (created_by_user_id, name, methodology_version, payload_json, validation_status, validation_reference)
                 VALUES (:uid, :name, :version, :payload, :status, :ref)'
            );
            $cfgIns->execute([
                'uid' => $adminId,
                'name' => $configPayload['name'] ?? 'San Fernando City Wellness Rubric',
                'version' => 'welyu-wellness-1.0',
                'payload' => json_encode($configPayload, JSON_THROW_ON_ERROR),
                'status' => $configPayload['validation_status'] ?? 'lgu_validated',
                'ref' => $configPayload['validation_reference'] ?? 'LEBDO, City Health Office & CPDO Joint Methodological Specification 2026',
            ]);
            $cfgId = (int) $pdo->lastInsertId();
        }

        // 2. Upsert 3 community needs (owned by Investor, reviewed by Admin so Admin can also review them without self-review block)
        $needIdsByMode = [];
        $needUpsert = $pdo->prepare(
            'INSERT INTO welyu_needs (owner_user_id, title, category, barangay, lat, lng, payload_json, fingerprint, review_status, review_json, reviewer_user_id)
             VALUES (:owner, :title, :category, :barangay, :lat, :lng, :payload, :fp, \'reviewed\', :review, :reviewer)
             ON DUPLICATE KEY UPDATE
                owner_user_id = VALUES(owner_user_id),
                title = VALUES(title),
                category = VALUES(category),
                barangay = VALUES(barangay),
                lat = VALUES(lat),
                lng = VALUES(lng),
                payload_json = VALUES(payload_json),
                review_status = \'reviewed\',
                review_json = VALUES(review_json),
                reviewer_user_id = VALUES(reviewer_user_id)'
        );
        foreach ($presets['pathways'] as $mode => $pathway) {
            $brgy = $pathway['barangay'];
            $fp = hash('sha256', 'siklab-2026-need:' . $mode . ':' . $brgy);
            $payload = [
                'title' => $pathway['title'],
                'category' => $pathway['category'],
                'barangay' => $brgy,
                'lat' => $pathway['lat'],
                'lng' => $pathway['lng'],
                'intended_users' => $pathway['need_summary'],
                'summary' => $pathway['need_summary'],
                'notes' => $pathway['subtitle'] ?? $pathway['need_summary'],
                'barriers' => $pathway['barriers'],
                'verified_on' => '2026-04-10',
                'evidence' => [
                    'source' => 'CHO / OSCA / PDAO Barangay Needs Registry',
                    'observed_on' => '2026-04-10',
                ],
            ];
            $needUpsert->execute([
                'owner' => $investorId,
                'title' => $pathway['title'],
                'category' => $pathway['category'],
                'barangay' => $brgy,
                'lat' => $pathway['lat'],
                'lng' => $pathway['lng'],
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'fp' => $fp,
                'review' => json_encode(['scope' => 'LEBDO & City Health Office Barangay Pilot Registry 2026', 'reviewed_on' => '2026-04-10'], JSON_THROW_ON_ERROR),
                'reviewer' => $adminId,
            ]);
            $idStmt = $pdo->prepare('SELECT id FROM welyu_needs WHERE fingerprint = :fp LIMIT 1');
            $idStmt->execute(['fp' => $fp]);
            $needIdsByMode[$mode] = (int) ($idStmt->fetchColumn() ?: 0);
        }

        // 3. Upsert baseline & real San Fernando City healthcare/wellness services (keeps open/improve/partner keys intact)
        $baselineServices = [
            'open' => ['Biday Daytime Therapy & Rehab Clinic', 'rehabilitation', 'Biday', 16.6258, 120.3234, 'Mon-Fri 8:00 AM - 5:00 PM', 350, 160, 'Ground-floor entrance; lacks evening rehab shifts', false, 'private'],
            'improve' => ['Catbangen Community Physical Therapy Center', 'preventive_care', 'Catbangen', 16.6142, 120.3118, 'Mon-Sat 7:00 AM - 8:00 PM (Pilot Extended)', 280, 210, 'BP 344 1:12 non-slip ramp & tactile drop-off', false, 'private'],
            'partner' => ['Poro Point Active Fitness & Coastal Studio', 'movement', 'Poro', 16.6095, 120.2965, 'Daily 6:00 AM - 8:30 PM', 240, 150, 'Step-free baywalk frontage & bike/shuttle access', false, 'private'],
            'anchor_itrmc' => ['Ilocos Training & Regional Medical Center (ITRMC)', 'rehabilitation', 'Parian', 16.5978, 120.3182, '24/7 Emergency & Tertiary Outpatient', 0, 1400, 'Public tertiary campus along MacArthur Hwy; OPD queues fill early', true, 'public'],
            'anchor_lorma' => ['Lorma Medical Center', 'rehabilitation', 'Carlatan', 16.6322, 120.3170, '24/7 Private Tertiary Hospital & Rehab', 650, 920, 'Step-free hospital drop-off; limited community evening fitness', false, 'private'],
            'anchor_hemotek' => ['Hemotek Renal Center, Inc.', 'preventive_care', 'Barangay II', 16.6175, 120.3186, 'Mon-Sat 6:00 AM - 9:00 PM', 0, 320, 'Ground-floor dialysis bays; 4-hr caregiver wait periods', false, 'private'],
            'anchor_luckd' => ['La Union Center for Kidney Diseases', 'preventive_care', 'Carlatan', 16.6311, 120.3175, 'Mon-Sat 8:00 AM - 8:00 PM', 0, 260, 'MacArthur Hwy frontage; needs adjacent caregiver respite/nutrition', false, 'private'],
            'anchor_pmp' => ['PMP Pain Center La Union', 'rehabilitation', 'Sevilla', 16.6079, 120.3177, 'Mon-Sun 9:00 AM - 6:00 PM', 450, 140, 'Ground-floor Uno Fuel Bldg; closes 6:00 PM leaving after-work gap', false, 'private'],
            'anchor_dohtrc' => ['DOH-TRC & ITRMC Behavioral Medicine', 'mental_wellness', 'Parian', 16.5984, 120.3194, 'Mon-Fri 8:00 AM - 5:00 PM', 0, 190, 'Clinical psychiatric hub; high stigma barrier for mild stress care', true, 'public'],
            'anchor_anytime' => ['Anytime Fitness (SM City La Union)', 'movement', 'Biday', 16.6256, 120.3210, 'Daily 24/7 Access', 1950, 420, 'Elevator mall access; skewed toward young strength lifters', false, 'private'],
            'anchor_baywalk' => ['Poro Point Baywalk Active Wellness Trail', 'movement', 'Poro', 16.6098, 120.2958, 'Daily 4:30 AM - 9:30 PM', 0, 950, 'Flat coastal promenade for sunrise/sunset walkers & active ageing', true, 'public'],
            'anchor_cho' => ['San Fernando City Health Office (CHO)', 'preventive_care', 'Barangay I', 16.6164, 120.3180, 'Mon-Fri 8:00 AM - 5:00 PM', 0, 480, 'Free public maternal/preventive screening; daytime schedule', true, 'public'],
            'anchor_biday_bhs' => ['Biday Barangay Health Station', 'preventive_care', 'Biday', 16.6247, 120.3238, 'Mon-Fri 8:00 AM - 5:00 PM', 0, 180, 'Free barangay checkups & senior BP monitoring; open to referrals', true, 'public'],
            'anchor_catbangen_bhs' => ['Catbangen Barangay Health Station', 'preventive_care', 'Catbangen', 16.6149, 120.3127, 'Mon-Fri 8:00 AM - 5:00 PM', 0, 195, 'High senior density referral node near coastal settlement', true, 'public'],
            'anchor_sevilla_bhs' => ['Sevilla Barangay Health Station', 'preventive_care', 'Sevilla', 16.6075, 120.3185, 'Mon-Fri 8:00 AM - 5:00 PM', 0, 165, 'Primary barangay referral node near Sevilla-Biday growth corridor', true, 'public'],
        ];
        $serviceIdsByMode = [];
        $srvUpsert = $pdo->prepare(
            'INSERT INTO welyu_services (owner_user_id, title, category, barangay, lat, lng, payload_json, fingerprint, review_status, review_json, reviewer_user_id)
             VALUES (:owner, :title, :category, :barangay, :lat, :lng, :payload, :fp, \'reviewed\', :review, :reviewer)
             ON DUPLICATE KEY UPDATE
                owner_user_id = VALUES(owner_user_id),
                title = VALUES(title),
                category = VALUES(category),
                barangay = VALUES(barangay),
                lat = VALUES(lat),
                lng = VALUES(lng),
                payload_json = VALUES(payload_json),
                review_status = \'reviewed\',
                review_json = VALUES(review_json),
                reviewer_user_id = VALUES(reviewer_user_id)'
        );
        foreach ($baselineServices as $mode => [$name, $cat, $brgy, $lat, $lng, $hours, $pricePhp, $capacityWk, $accessibility, $isFree, $operatorKind]) {
            $fp = hash('sha256', 'siklab-2026-service:' . $name . ':' . $brgy);
            $payload = [
                'title' => $name,
                'category' => $cat,
                'barangay' => $brgy,
                'lat' => $lat,
                'lng' => $lng,
                'hours' => $hours,
                'price_php' => $pricePhp,
                'capacity_per_week' => $capacityWk,
                'accessibility' => $accessibility,
                'is_free' => (bool) $isFree,
                'open_to_partnership' => true,
                'operator_kind' => $operatorKind,
                'verified_on' => '2026-04-10',
                'evidence' => [
                    'source' => 'City Health & BPLO Wellness Facility Registry 2026',
                    'observed_on' => '2026-04-10',
                ],
            ];
            $srvUpsert->execute([
                'owner' => $investorId,
                'title' => $name,
                'category' => $cat,
                'barangay' => $brgy,
                'lat' => $lat,
                'lng' => $lng,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'fp' => $fp,
                'review' => json_encode(['scope' => 'City Health & BPLO Wellness Facility Registry 2026', 'reviewed_on' => '2026-04-10'], JSON_THROW_ON_ERROR),
                'reviewer' => $adminId,
            ]);
            $idStmt = $pdo->prepare('SELECT id FROM welyu_services WHERE fingerprint = :fp LIMIT 1');
            $idStmt->execute(['fp' => $fp]);
            $serviceIdsByMode[$mode] = (int) ($idStmt->fetchColumn() ?: 0);
        }

        // 4. Upsert 3 side-by-side proposals (`Open`, `Improve`, `Partner`) owned by Investor (`investor@welyu.local`)
        $proposalIdsByMode = [];
        $assessmentIdsByMode = [];
        $scenarioIdsByMode = [];
        $hydratedAssessments = [];
        $hydratedScenarios = [];

        $propByMode = [
            'open' => 101,
            'improve' => 102,
            'partner' => 103,
        ];

        foreach ($presets['pathways'] as $mode => $pathway) {
            $title = $pathway['title'];
            $brgy = $pathway['barangay'];
            $propId = $propByMode[$mode] ?? 101;
            $serviceId = $mode === 'open' ? null : ($serviceIdsByMode[$mode] ?? null);

            $propCheck = $pdo->prepare('SELECT id FROM welyu_proposals WHERE owner_user_id = :owner AND mode = :mode AND title = :title LIMIT 1');
            $propCheck->execute([
                'owner' => $investorId,
                'mode' => $mode,
                'title' => $title,
            ]);
            $proposalId = (int) ($propCheck->fetchColumn() ?: 0);

            $proposalPayload = [
                'source_need_id' => $needIdsByMode[$mode] ?? null,
                'concept' => $pathway['need_summary'] ?? $pathway['title'],
                'intended_users' => 'Senior citizens, working families, and post-rehab residents in Brgy. ' . $brgy . ' and surrounding San Fernando City barangays.',
                'category' => $pathway['category'],
                'barangay' => $brgy,
                'location' => 'Brgy. ' . $brgy . ', San Fernando City, La Union',
                'lat' => $pathway['lat'],
                'lng' => $pathway['lng'],
                'barriers_and_improvements' => implode('; ', $pathway['barriers'] ?? []),
                'evidence' => [
                    'source' => 'LEBDO & City Health Office Barangay Registry',
                    'observed_on' => '2026-04-10',
                ],
                'complementary_categories' => ['preventive_care', 'rehab_mobility'],
                'siklab_pilot' => true,
            ];
            $reviewJson = [
                'scope' => 'LEBDO, CAO & CICTO Joint Verification',
                'notes' => 'Verified against Barangay Community Needs Registry and CLUP C-2 locational clearance standards.',
                'reviewed_on' => '2026-04-12',
                'prior_revision' => 1,
            ];

            if ($proposalId > 0) {
                $pUpd = $pdo->prepare(
                    'UPDATE welyu_proposals SET
                        property_id = :property_id,
                        service_id = :service_id,
                        payload_json = :payload,
                        review_status = \'reviewed\',
                        review_json = :review,
                        reviewer_user_id = :reviewer
                     WHERE id = :id'
                );
                $pUpd->execute([
                    'property_id' => $propId,
                    'service_id' => $serviceId,
                    'payload' => json_encode($proposalPayload, JSON_THROW_ON_ERROR),
                    'review' => json_encode($reviewJson, JSON_THROW_ON_ERROR),
                    'reviewer' => $adminId,
                    'id' => $proposalId,
                ]);
            } else {
                $pIns = $pdo->prepare(
                    'INSERT INTO welyu_proposals (
                        owner_user_id, title, property_id, service_id, mode,
                        payload_json, review_status, review_json, reviewer_user_id
                    ) VALUES (
                        :owner, :title, :property_id, :service_id, :mode,
                        :payload, \'reviewed\', :review, :reviewer
                    )'
                );
                $pIns->execute([
                    'owner' => $investorId,
                    'title' => $title,
                    'property_id' => $propId,
                    'service_id' => $serviceId,
                    'mode' => $mode,
                    'payload' => json_encode($proposalPayload, JSON_THROW_ON_ERROR),
                    'review' => json_encode($reviewJson, JSON_THROW_ON_ERROR),
                    'reviewer' => $adminId,
                ]);
                $proposalId = (int) $pdo->lastInsertId();
            }
            $proposalIdsByMode[$mode] = $proposalId;

            // 5. Upsert unlocked assessment (`welyu_assessments`) for each proposal
            $assessmentResult = WellnessEngine::assess($pathway['evidence'], $configPayload);
            $snapshot = [
                'proposal' => [
                    'id' => $proposalId,
                    'title' => $title,
                    'mode' => $mode,
                    'property_id' => $propId,
                    'service_id' => $serviceId,
                    'payload' => $proposalPayload,
                ],
                'configuration' => [
                    'id' => $cfgId,
                    'name' => $configPayload['name'] ?? 'San Fernando City Wellness Rubric',
                    'methodology_version' => 'welyu-wellness-1.0',
                    'payload' => $configPayload,
                ],
                'observations' => $pathway['evidence'],
                'property_id' => $propId,
            ];
            $inputSha = WellnessEngine::hash($snapshot);

            $aCheck = $pdo->prepare('SELECT id FROM welyu_assessments WHERE proposal_id = :pid ORDER BY id DESC LIMIT 1');
            $aCheck->execute(['pid' => $proposalId]);
            $assessmentId = (int) ($aCheck->fetchColumn() ?: 0);

            if ($assessmentId > 0) {
                $aUpd = $pdo->prepare(
                    'UPDATE welyu_assessments SET
                        created_by_user_id = :uid,
                        configuration_id = :cfg_id,
                        methodology_version = \'welyu-wellness-1.0\',
                        snapshot_json = :snapshot,
                        result_json = :result,
                        input_sha256 = :sha
                     WHERE id = :id'
                );
                $aUpd->execute([
                    'uid' => $investorId,
                    'cfg_id' => $cfgId,
                    'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                    'result' => json_encode($assessmentResult, JSON_THROW_ON_ERROR),
                    'sha' => $inputSha,
                    'id' => $assessmentId,
                ]);
            } else {
                $aIns = $pdo->prepare(
                    'INSERT INTO welyu_assessments (
                        proposal_id, created_by_user_id, configuration_id, methodology_version,
                        snapshot_json, result_json, input_sha256
                    ) VALUES (
                        :pid, :uid, :cfg_id, \'welyu-wellness-1.0\',
                        :snapshot, :result, :sha
                    )'
                );
                $aIns->execute([
                    'pid' => $proposalId,
                    'uid' => $investorId,
                    'cfg_id' => $cfgId,
                    'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                    'result' => json_encode($assessmentResult, JSON_THROW_ON_ERROR),
                    'sha' => $inputSha,
                ]);
                $assessmentId = (int) $pdo->lastInsertId();
            }
            $assessmentIdsByMode[$mode] = $assessmentId;
            $hydratedAssessments[$mode] = [
                'id' => $assessmentId,
                'proposal_id' => $proposalId,
                'created_by_user_id' => $investorId,
                'configuration_id' => $cfgId,
                'methodology_version' => 'welyu-wellness-1.0',
                'snapshot' => $snapshot,
                'result' => $assessmentResult,
                'input_sha256' => $inputSha,
            ];

            // 6. Upsert financial scenario (`welyu_scenarios`) for each proposal
            $scenarioInput = $pathway['scenario_input'];
            $scenarioName = (string) ($scenarioInput['name'] ?? ($title . ' — Baseline Viability Model'));
            $scenarioInput['name'] = $scenarioName;
            $scenarioResult = WellnessEngine::scenario($scenarioInput);

            $sCheck = $pdo->prepare('SELECT id FROM welyu_scenarios WHERE proposal_id = :pid ORDER BY id DESC LIMIT 1');
            $sCheck->execute(['pid' => $proposalId]);
            $scenarioId = (int) ($sCheck->fetchColumn() ?: 0);

            if ($scenarioId > 0) {
                $sUpd = $pdo->prepare(
                    'UPDATE welyu_scenarios SET
                        owner_user_id = :uid,
                        name = :name,
                        is_baseline = 1,
                        input_json = :input,
                        result_json = :result
                     WHERE id = :id'
                );
                $sUpd->execute([
                    'uid' => $investorId,
                    'name' => $scenarioName,
                    'input' => json_encode($scenarioInput, JSON_THROW_ON_ERROR),
                    'result' => json_encode($scenarioResult, JSON_THROW_ON_ERROR),
                    'id' => $scenarioId,
                ]);
            } else {
                $sIns = $pdo->prepare(
                    'INSERT INTO welyu_scenarios (
                        proposal_id, owner_user_id, baseline_id, name, is_baseline, input_json, result_json
                    ) VALUES (
                        :pid, :uid, NULL, :name, 1, :input, :result
                    )'
                );
                $sIns->execute([
                    'pid' => $proposalId,
                    'uid' => $investorId,
                    'name' => $scenarioName,
                    'input' => json_encode($scenarioInput, JSON_THROW_ON_ERROR),
                    'result' => json_encode($scenarioResult, JSON_THROW_ON_ERROR),
                ]);
                $scenarioId = (int) $pdo->lastInsertId();
            }
            $scenarioIdsByMode[$mode] = $scenarioId;
            $hydratedScenarios[(string) $proposalId] = [[
                'id' => $scenarioId,
                'proposal_id' => $proposalId,
                'owner_user_id' => $investorId,
                'baseline_id' => null,
                'name' => $scenarioName,
                'is_baseline' => 1,
                'input' => $scenarioInput,
                'result' => $scenarioResult,
            ]];
        }

        // 7. Upsert 3-way comparison (`welyu_comparisons` & `welyu_comparison_items`) owned by Investor
        $cmpName = 'San Fernando City Pilot — Open vs. Improve vs. Partner (3-Way Pathway Comparison)';
        $cmpCheck = $pdo->prepare('SELECT id FROM welyu_comparisons WHERE owner_user_id = :uid AND name = :name LIMIT 1');
        $cmpCheck->execute(['uid' => $investorId, 'name' => $cmpName]);
        $comparisonId = (int) ($cmpCheck->fetchColumn() ?: 0);

        $cmpSnapshot = [
            'methodology_version' => 'welyu-wellness-1.0',
            'assessments' => array_values($hydratedAssessments),
            'scenarios' => $hydratedScenarios,
        ];
        $cmpSha = WellnessEngine::hash($cmpSnapshot);

        if ($comparisonId > 0) {
            $cUpd = $pdo->prepare(
                'UPDATE welyu_comparisons SET
                    snapshot_json = :snapshot,
                    snapshot_sha256 = :sha
                 WHERE id = :id'
            );
            $cUpd->execute([
                'snapshot' => json_encode($cmpSnapshot, JSON_THROW_ON_ERROR),
                'sha' => $cmpSha,
                'id' => $comparisonId,
            ]);
            $pdo->prepare('DELETE FROM welyu_comparison_items WHERE comparison_id = :cid')->execute(['cid' => $comparisonId]);
        } else {
            $cIns = $pdo->prepare(
                'INSERT INTO welyu_comparisons (owner_user_id, name, snapshot_json, snapshot_sha256)
                 VALUES (:uid, :name, :snapshot, :sha)'
            );
            $cIns->execute([
                'uid' => $investorId,
                'name' => $cmpName,
                'snapshot' => json_encode($cmpSnapshot, JSON_THROW_ON_ERROR),
                'sha' => $cmpSha,
            ]);
            $comparisonId = (int) $pdo->lastInsertId();
        }

        $ciIns = $pdo->prepare(
            'INSERT INTO welyu_comparison_items (comparison_id, assessment_id)
             VALUES (:cid, :aid)'
        );
        foreach (['open', 'improve', 'partner'] as $m) {
            $ciIns->execute([
                'cid' => $comparisonId,
                'aid' => $assessmentIdsByMode[$m],
            ]);
        }

        // 8. Upsert implementation milestone events (`welyu_events`) on the Open proposal
        $openPropId = $proposalIdsByMode['open'];
        $evCheck = $pdo->prepare('SELECT COUNT(*) FROM welyu_events WHERE proposal_id = :pid');
        $evCheck->execute(['pid' => $openPropId]);
        if ((int) $evCheck->fetchColumn() === 0) {
            $evIns = $pdo->prepare(
                'INSERT INTO welyu_events (actor_user_id, proposal_id, kind, payload_json)
                 VALUES (:uid, :pid, :kind, :payload)'
            );
            $evIns->execute([
                'uid' => $adminId,
                'pid' => $openPropId,
                'kind' => 'decision',
                'payload' => json_encode([
                    'notes' => 'CPDO C-2 Locational Clearance, CHO Sanitation Pre-Clearance, and all 5 readiness gates verified for Barangay Biday site.',
                    'responsible' => 'Justine Tadifa (CICTO Admin & LEBDO/CAO Reviewer)',
                    'next_action' => 'Proceed to joint technical site walk and fit-out mobilization.',
                    'evidence' => ['source' => 'LEBDO & CPDO Readiness Registry 2026', 'observed_on' => '2026-04-12'],
                    'observed' => false,
                    'metrics' => [],
                ], JSON_THROW_ON_ERROR),
            ]);
            $evIns->execute([
                'uid' => $investorId,
                'pid' => $openPropId,
                'kind' => 'site_visit',
                'payload' => json_encode([
                    'notes' => 'Scheduled joint site inspection with PRC Broker Maria Santos and LEBDO technical team for step-free ramp & three-phase power tap.',
                    'responsible' => 'Juan Dela Cruz (Wellness Investor)',
                    'next_action' => 'Finalize equipment delivery schedule for Q3 2026 opening (160 monthly target sessions).',
                    'evidence' => ['source' => 'Investor-Broker Site Visit Log #101', 'observed_on' => '2026-04-12'],
                    'observed' => false,
                    'metrics' => [],
                ], JSON_THROW_ON_ERROR),
            ]);
        }

        return [
            'need_ids' => $needIdsByMode,
            'service_ids' => $serviceIdsByMode,
            'proposal_ids' => $proposalIdsByMode,
            'assessment_ids' => $assessmentIdsByMode,
            'scenario_ids' => $scenarioIdsByMode,
            'comparison_id' => $comparisonId,
        ];
    }
}
