<?php

namespace Database\Seeders;

use App\Models\Household;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\BusinessOrderService;
use App\Services\DebtService;
use App\Services\InvestmentService;
use App\Services\MeterService;
use App\Services\SavingService;
use App\Services\UtilityService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoHouseholdSeeder extends Seeder
{
    public const INVITE_CODE = 'DEMO26';

    public const HOUSEHOLD_NAME = 'Összhang Demo';

    public const PASSWORD = 'demo1234';

    public const ADMIN_USERNAME = 'dani';

    public const MEMBER_USERNAME = 'viki';

    public function __construct(
        private readonly BudgetService $budget,
        private readonly UtilityService $utilities,
        private readonly DebtService $debts,
        private readonly SavingService $savings,
        private readonly InvestmentService $investments,
        private readonly MeterService $meters,
        private readonly BusinessOrderService $businessOrders,
    ) {}

    public function run(bool $fresh = false): Household
    {
        if ($fresh) {
            $this->deleteExisting();
        } elseif ($existing = $this->findDemoHousehold()) {
            return $existing;
        }

        return DB::transaction(function () {
            $household = $this->createHousehold();
            $admin = $this->createUser($household, [
                'first_name' => 'Dani',
                'last_name' => 'Demo',
                'username' => 'demo',
                'role' => 'admin',
            ]);
            $partner = $this->createUser($household, [
                'first_name' => 'Viki',
                'last_name' => 'Demo',
                'username' => 'viki',
                'role' => 'member',
            ]);

            $household->update(['utility_split_partner_id' => $partner->id]);
            $household->refresh();

            $this->seedBudget($household, $admin);
            $this->seedUtilities($household);
            $this->seedDebts($household);
            $this->seedSavings($household);
            $this->seedInvestments($household);
            $this->seedMeters($household);
            $this->seedBusiness($household);

            $this->syncPostgresSequences();

            return $household->fresh(['users']);
        });
    }

    public function findDemoHousehold(): ?Household
    {
        return Household::where('invite_code', self::INVITE_CODE)->first();
    }

    private function deleteExisting(): void
    {
        Household::where('invite_code', self::INVITE_CODE)->each(fn (Household $h) => $h->delete());

        User::whereIn('username', ['demo', 'viki'])
            ->whereDoesntHave('household')
            ->delete();
    }

    private function createHousehold(): Household
    {
        return Household::create([
            'name' => self::HOUSEHOLD_NAME,
            'invite_code' => self::INVITE_CODE,
            'manual_balance' => 450_000,
            'onboarding_completed' => true,
            'budget_enabled' => true,
            'savings_enabled' => true,
            'debts_enabled' => true,
            'utilities_enabled' => true,
            'meters_enabled' => true,
            'business_enabled' => true,
            'business_name' => 'Demo Webshop Kft.',
            'shopify_import_enabled' => false,
            'utility_split_enabled' => true,
            'categories' => ['Fizetés', 'Élelmiszer', 'Rezsi', 'Szórakozás', 'Egészség', 'Utazás'],
            'business_settings' => [
                'channels' => ['Webshop', 'Instagram', 'Vásárlói rendelés'],
                'payment_methods' => ['Kártya', 'Utánvét', 'Átutalás'],
                'providers' => ['Foxpost', 'GLS', 'MPL'],
                'destinations' => ['Szolgáltatónál parkol', 'Kiszállítva', 'Visszáru'],
            ],
            'savings_settings' => [
                'owners' => ['Közös', 'Viki'],
                'default_owner' => 'Közös',
                'separate_owner' => 'Viki',
                'currencies' => ['HUF', 'EUR', 'USD'],
                'default_count_in_savings' => true,
            ],
            'utility_templates' => [
                ['type' => 'Áram', 'total' => 18_000, 'due_day' => 15, 'split_rule' => 'shared'],
                ['type' => 'Gáz', 'total' => 22_000, 'due_day' => 10, 'split_rule' => 'shared'],
                ['type' => 'Víz', 'total' => 9_000, 'due_day' => 20, 'split_rule' => 'shared'],
                ['type' => 'Internet', 'total' => 8_990, 'due_day' => 5, 'split_rule' => 'dani-private'],
            ],
        ]);
    }

    private function createUser(Household $household, array $data): User
    {
        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $data['username'],
            'password' => Hash::make(self::PASSWORD),
            'must_change_password' => false,
            'household_id' => $household->id,
            'role' => $data['role'],
            'permissions' => ['budget', 'utilities', 'business', 'meters', 'debts', 'savings'],
        ]);
    }

    private function seedBudget(Household $household, User $admin): void
    {
        $now = Carbon::now();
        $thisMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth()->startOfMonth();

        $rows = [
            ['type' => 'income', 'description' => 'Fizetés — Demo Kft.', 'category' => 'Fizetés', 'amount' => 850_000, 'due' => $thisMonth->copy()->day(5), 'paid' => $thisMonth->copy()->day(5)],
            ['type' => 'expense', 'description' => 'Albérlet', 'category' => 'Rezsi', 'amount' => 180_000, 'due' => $thisMonth->copy()->day(8), 'paid' => null],
            ['type' => 'expense', 'description' => 'Tesco heti bevásárlás', 'category' => 'Élelmiszer', 'amount' => 47_850, 'due' => $thisMonth->copy()->day(12), 'paid' => $thisMonth->copy()->day(12)],
            ['type' => 'expense', 'description' => 'Shell — tankolás', 'category' => 'Utazás', 'amount' => 28_400, 'due' => $thisMonth->copy()->day(14), 'paid' => $thisMonth->copy()->day(14)],
            ['type' => 'expense', 'description' => 'Netflix előfizetés', 'category' => 'Szórakozás', 'amount' => 4_990, 'due' => $thisMonth->copy()->day(1), 'paid' => $thisMonth->copy()->day(1), 'reserve' => true],
            ['type' => 'expense', 'description' => 'Gyógyszertár', 'category' => 'Egészség', 'amount' => 12_300, 'due' => $thisMonth->copy()->day(18), 'paid' => null],
            ['type' => 'income', 'description' => 'Freelance számla', 'category' => 'Fizetés', 'amount' => 120_000, 'due' => $lastMonth->copy()->day(20), 'paid' => $lastMonth->copy()->day(22)],
            ['type' => 'expense', 'description' => 'Lidl — havi nagybevásárlás', 'category' => 'Élelmiszer', 'amount' => 62_100, 'due' => $lastMonth->copy()->day(16), 'paid' => $lastMonth->copy()->day(16)],
        ];

        foreach ($rows as $row) {
            $this->budget->create($household, $admin, [
                'type' => $row['type'],
                'description' => $row['description'],
                'category' => $row['category'],
                'amount' => $row['amount'],
                'dueDate' => $row['due']->toDateString(),
                'paidDate' => $row['paid']?->toDateString(),
                'isBudget' => true,
                'isReserve' => $row['reserve'] ?? false,
            ]);
        }
    }

    private function seedUtilities(Household $household): void
    {
        $now = Carbon::now();
        $thisMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth()->startOfMonth();

        $bills = [
            ['type' => 'Áram', 'total' => 18_420, 'dueDate' => $thisMonth->copy()->day(15), 'paidDate' => $thisMonth->copy()->day(14), 'paidBy' => 'Mi', 'splitRule' => 'shared'],
            ['type' => 'Gáz', 'total' => 22_150, 'dueDate' => $thisMonth->copy()->day(10), 'paidDate' => null, 'paidBy' => null, 'splitRule' => 'shared'],
            ['type' => 'Víz', 'total' => 8_760, 'dueDate' => $thisMonth->copy()->day(20), 'paidDate' => null, 'paidBy' => null, 'splitRule' => 'shared'],
            ['type' => 'Internet', 'total' => 8_990, 'dueDate' => $thisMonth->copy()->day(5), 'paidDate' => $thisMonth->copy()->day(5), 'paidBy' => 'Mi', 'splitRule' => 'dani-private'],
            ['type' => 'Közös kazán', 'total' => 31_200, 'dueDate' => $lastMonth->copy()->day(12), 'paidDate' => $lastMonth->copy()->day(11), 'paidBy' => 'Ildi', 'splitRule' => 'shared'],
        ];

        foreach ($bills as $bill) {
            $this->utilities->create($household, $bill);
        }
    }

    private function seedDebts(Household $household): void
    {
        $this->debts->create($household, [
            'name' => 'Autóhitel',
            'targetAmount' => 2_800_000,
            'paidAmount' => 1_200_000,
            'annualInterestRate' => 8.5,
            'minimumPayment' => 68_000,
            'dueDay' => 15,
            'status' => 'Még fizetendő',
        ]);

        $this->debts->create($household, [
            'name' => 'Diákhitel',
            'targetAmount' => 450_000,
            'paidAmount' => 90_000,
            'annualInterestRate' => 4.5,
            'minimumPayment' => 12_500,
            'dueDay' => 28,
            'status' => 'Még fizetendő',
        ]);
    }

    private function seedSavings(Household $household): void
    {
        $now = Carbon::now();

        $otp = $this->savings->create($household, [
            'institution' => 'OTP Bank',
            'currency' => 'HUF',
            'owner' => 'Közös',
            'count_in_savings' => true,
        ]);

        $this->savings->addEntry($household, $otp['id'], [
            'date' => $now->copy()->subMonths(2)->day(10)->toDateString(),
            'amount' => 200_000,
            'reason' => 'Havi megtakarítás',
        ]);
        $this->savings->addEntry($household, $otp['id'], [
            'date' => $now->copy()->subMonth()->day(10)->toDateString(),
            'amount' => 200_000,
            'reason' => 'Havi megtakarítás',
        ]);
        $this->savings->addEntry($household, $otp['id'], [
            'date' => $now->copy()->day(10)->toDateString(),
            'amount' => 200_000,
            'reason' => 'Havi megtakarítás',
        ]);

        $revolut = $this->savings->create($household, [
            'institution' => 'Revolut',
            'currency' => 'EUR',
            'owner' => 'Viki',
            'count_in_savings' => true,
        ]);

        $this->savings->addEntry($household, $revolut['id'], [
            'date' => $now->copy()->subMonth()->day(3)->toDateString(),
            'amount' => 500,
            'reason' => 'Utazásra félretéve',
        ]);
    }

    private function seedInvestments(Household $household): void
    {
        $now = Carbon::now();

        $this->investments->create($household, [
            'name' => 'MÁP Plusz',
            'type' => 'bond',
            'principalAmount' => 1_000_000,
            'annualInterestRate' => 7.25,
            'owner' => 'Közös',
            'purchaseDate' => $now->copy()->subMonths(8)->toDateString(),
            'currentValue' => 1_048_000,
            'countInSavings' => true,
        ]);

        $this->investments->create($household, [
            'name' => 'DKJ 2026/03',
            'type' => 'bond',
            'principalAmount' => 500_000,
            'annualInterestRate' => 6.1,
            'owner' => 'Közös',
            'purchaseDate' => $now->copy()->subMonths(3)->toDateString(),
            'maturityDate' => $now->copy()->addMonths(9)->toDateString(),
            'maturityAmount' => 525_000,
            'nextPayoutAmount' => 15_250,
            'nextPayoutDate' => $now->copy()->addMonths(2)->toDateString(),
            'countInSavings' => true,
        ]);
    }

    private function seedMeters(Household $household): void
    {
        $now = Carbon::now();
        $emptyRequest = Request::create('/', 'POST');

        $electric = $this->meters->create($household, [
            'name' => 'Villanyóra',
            'unit' => 'kWh',
            'location' => 'Előszoba',
        ]);

        $gas = $this->meters->create($household, [
            'name' => 'Gázmérő',
            'unit' => 'm³',
            'location' => 'Konyha',
        ]);

        $electricReadings = [
            [$now->copy()->subMonths(2)->day(28), 12_450],
            [$now->copy()->subMonth()->day(28), 12_780],
            [$now->copy()->day(28), 13_040],
        ];

        foreach ($electricReadings as [$date, $value]) {
            $this->meters->addReading($household, $electric['id'], [
                'date' => $date->toDateString(),
                'value' => $value,
            ], $emptyRequest);
        }

        $gasReadings = [
            [$now->copy()->subMonths(2)->day(25), 3_210],
            [$now->copy()->subMonth()->day(25), 3_288],
            [$now->copy()->day(25), 3_356],
        ];

        foreach ($gasReadings as [$date, $value]) {
            $this->meters->addReading($household, $gas['id'], [
                'date' => $date->toDateString(),
                'value' => $value,
            ], $emptyRequest);
        }
    }

    private function seedBusiness(Household $household): void
    {
        $now = Carbon::now();

        $orders = [
            ['customerName' => 'Kiss Anna', 'amount' => 24_990, 'date' => $now->copy()->subDays(2), 'paidDate' => $now->copy()->subDay(), 'channel' => 'Webshop', 'paymentMethod' => 'Kártya'],
            ['customerName' => 'Nagy Péter', 'amount' => 18_500, 'date' => $now->copy()->subDays(5), 'paidDate' => null, 'channel' => 'Instagram', 'paymentMethod' => 'Utánvét'],
            ['customerName' => 'Szabó Eszter', 'amount' => 42_300, 'date' => $now->copy()->subDays(8), 'paidDate' => $now->copy()->subDays(6), 'channel' => 'Vásárlói rendelés', 'paymentMethod' => 'Átutalás'],
            ['customerName' => 'Tóth Gábor', 'amount' => 9_990, 'date' => $now->copy()->subDays(1), 'paidDate' => null, 'channel' => 'Webshop', 'paymentMethod' => 'Kártya'],
        ];

        foreach ($orders as $order) {
            $this->businessOrders->create($household, [
                'customerName' => $order['customerName'],
                'amount' => $order['amount'],
                'date' => $order['date']->toDateString(),
                'paidDate' => $order['paidDate']?->toDateString(),
                'channel' => $order['channel'],
                'paymentMethod' => $order['paymentMethod'],
                'provider' => 'Foxpost',
                'destination' => 'Kiszállítva',
            ]);
        }
    }

    private function syncPostgresSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'households',
            'users',
            'transactions',
            'utilities',
            'utility_settlements',
            'meters',
            'meter_readings',
            'debts',
            'savings',
            'ledger_entries',
            'investments',
            'business_orders',
        ];

        foreach ($tables as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1), true)",
            );
        }
    }
}
