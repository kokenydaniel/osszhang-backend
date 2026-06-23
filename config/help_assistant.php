<?php

/**
 * Modul metaadatok a súgó asszisztenshez.
 * A teljes app-tudás: config/help_knowledge.json
 * Generálás: cd frontend && npm run sync:help-knowledge
 * (forrás: src/config/help.ts + src/config/help-guide.ts)
 */
return [
    'app_name' => 'Összhang',

    'module_paths' => [
        'budget' => '/budget',
        'savings' => '/savings',
        'debts' => '/debts',
        'utilities' => '/utilities',
        'meters' => '/meters',
        'business' => '/business',
        'pocket_money' => '/pocket-money',
        'insurance' => '/insurance',
        'rental' => '/rental',
        'receivables' => '/receivables',
        'travel_planner' => '/tools/travel',
    ],

    'module_labels' => [
        'budget' => 'Költségvetés',
        'savings' => 'Megtakarítások',
        'debts' => 'Tartozások',
        'utilities' => 'Rezsi',
        'meters' => 'Közműórák',
        'business' => 'Vállalkozás',
        'pocket_money' => 'Zsebpénz',
        'insurance' => 'Biztosítások',
        'rental' => 'Bérbeadás',
        'receivables' => 'Kintlévőség',
        'travel_planner' => 'Utazástervező',
    ],

    'module_tiers' => [
        'budget' => null,
        'savings' => 'pro',
        'debts' => 'pro',
        'utilities' => 'pro',
        'meters' => 'pro',
        'pocket_money' => 'pro',
        'insurance' => 'pro',
        'rental' => 'pro',
        'receivables' => 'pro',
        'business' => 'premium',
        'travel_planner' => 'premium',
    ],

    'feature_labels' => [
        'private_wallet' => 'Privát kassza',
        'utility_split' => 'Rezsi megosztás',
        'shopify_import' => 'Shopify import',
        'woocommerce_import' => 'WooCommerce import',
        'unas_import' => 'UNAS import',
        'ai' => 'AI pénzügyi funkciók',
        'attachments' => 'Számla és nyugta csatolás',
        'sumup_import' => 'SumUp könyvelési import',
    ],

    'feature_tiers' => [
        'private_wallet' => 'pro',
        'utility_split' => 'pro',
        'shopify_import' => 'premium',
        'woocommerce_import' => 'premium',
        'unas_import' => 'premium',
        'ai' => 'premium',
        'attachments' => 'premium',
        'sumup_import' => 'premium',
    ],

    'knowledge_topics' => [
        [
            'id' => 'dashboard',
            'title' => 'Irányítópult',
            'path' => '/',
            'summary' => 'A háztartás pénzügyi pillanatképe: egyenleg, fizetendő, figyelmeztetések, modul összefoglalók.',
            'how_to' => [
                'Válaszd ki a kasszát és hónapot a fejlécben.',
                'Widget sorrend: Beállítások → Irányítópult.',
                'Kártyákra kattintva ugorhatsz a modulokhoz.',
            ],
        ],
        [
            'id' => 'budget',
            'module' => 'budget',
            'summary' => 'Havi bevételek és kiadások, kategóriák, egyenleg, fizetendő, saját keretek. Ingyenes csomag alapmodul.',
            'how_to' => [
                'Költségvetés menü → + Új tétel: bevétel vagy kiadás rögzítése (meglévő kategóriából választasz).',
                'Kifizetettként jelölés frissíti az egyenleget.',
                'Új kategória: Beállítások → Modulok → Költségvetés kártya → Kategóriák szekció (admin).',
                'Hónap másolása: ismétlődő tételek átvitele az előző hónapból.',
            ],
            'features' => ['Havi/éves nézet', 'Kategóriák', 'Saját keret', 'Premium: AI túlköltés, fizetési sorrend, spórolás'],
        ],
        [
            'id' => 'budget_categories',
            'title' => 'Költségvetés kategóriák',
            'summary' => 'Új költségkategória csak a Beállítások → Modulok → Költségvetés → Kategóriák szekcióban adható meg. A + Új tétel csak meglévő kategóriát választ, ott nem hozol létre újat.',
            'how_to' => [
                'Beállítások → Modulok fül (/settings?tab=modules).',
                'Költségvetés kártya → Kategóriák szekció kinyitása.',
                '„Új kategória” mezőbe írd a nevet, majd hozzáadás — automatikusan mentődik.',
                'Kategória színe opcionálisan állítható ugyanitt.',
                'Ezután Költségvetés → + Új tétel → Kategória legördülőből választható.',
                'Csak a háztartás adminisztrátora szerkesztheti a kategóriákat.',
            ],
            'features' => [
                'Kategória lista háztartásonként',
                'Színkódok a költségvetésben',
                'Törlés: meglévő tételek nem törlődnek',
            ],
        ],
        [
            'id' => 'savings',
            'module' => 'savings',
            'summary' => 'Megtakarítási célok, bankszámlák, állampapír portfólió. Pro csomag, modul bekapcsolás szükséges.',
            'how_to' => [
                'Beállítások → Modulok: Megtakarítás be.',
                'Megtakarítások menü → számla vagy cél létrehozása.',
                'Befizetés vagy egyenleg rögzítése.',
            ],
        ],
        [
            'id' => 'debts',
            'module' => 'debts',
            'summary' => 'Hitelek, kölcsönök, törlesztési terv, hátralék. Pro csomag.',
            'how_to' => [
                'Modul bekapcsolása, majd Tartozások → Új hitel.',
                'Kamat és törlesztési terv megadása.',
                'Opcionális szinkron a költségvetésbe.',
            ],
            'features' => ['Törlesztési terv', 'Dokumentum csatolás', 'Premium: AI törlesztési stratégia'],
        ],
        [
            'id' => 'utilities',
            'module' => 'utilities',
            'summary' => 'Rezsi számlák, határidők, kifizetés követés, partner megosztás. Pro csomag.',
            'how_to' => [
                'Rezsi menü → új számla vagy sablon.',
                'Összeg, határidő, kifizető megadása.',
                'Megosztás: Beállítások → Rezsi partner.',
            ],
        ],
        [
            'id' => 'meters',
            'module' => 'meters',
            'summary' => 'Közműóra állások, fogyasztás trend, év összehasonlítás. Pro csomag.',
            'how_to' => [
                'Közműórák → Új mérőóra.',
                'Havi leolvasás rögzítése.',
                'Premium: AI anomália jelzés.',
            ],
        ],
        [
            'id' => 'business',
            'module' => 'business',
            'summary' => 'Vállalkozás rendelések, bevételek, AAM/ÁFA kimutatás, webshop import. Premium csomag.',
            'how_to' => [
                'Beállítások → Modulok: Vállalkozás + név.',
                'Rendelés rögzítés vagy import.',
                'Integrációk: Beállítások → Integrációk (Shopify, WooCommerce, UNAS, SumUp).',
            ],
            'features' => ['Rendelésnapló', 'Éves elemzés', 'Premium: könyvelési ZIP'],
        ],
        [
            'id' => 'pocket_money',
            'module' => 'pocket_money',
            'summary' => 'Zsebpénz kiosztás, költés, kamat gyerekeknek/tagoknak. Pro csomag.',
            'how_to' => ['Modul bekapcsolás', 'Tag hozzáadás', 'Kiosztás/költés rögzítés'],
        ],
        [
            'id' => 'insurance',
            'module' => 'insurance',
            'summary' => 'Biztosítási szerződések, díjak, megújítási emlékeztető. Pro csomag.',
            'how_to' => ['Biztosítások → Új szerződés', 'Díj és dátumok', 'Opcionális költségvetés szinkron'],
        ],
        [
            'id' => 'rental',
            'module' => 'rental',
            'summary' => 'Bérbe adott ingatlanok, bérleti díj, befizetés követés. Pro csomag.',
            'how_to' => ['Bérbeadás → Új ingatlan', 'Havi díj beállítás', 'Befizetés rögzítése'],
        ],
        [
            'id' => 'receivables',
            'module' => 'receivables',
            'summary' => 'Kintlévőség — ki mennyivel tartozik (magán kölcsön, előleg). Pro csomag.',
            'how_to' => ['Kintlévőség → Új tétel', 'Visszafizetések rögzítése'],
        ],
        [
            'id' => 'travel_planner',
            'module' => 'travel_planner',
            'summary' => 'AI utazásköltség-tervező, belefér elemzés, PDF export, költségmegosztás. Premium.',
            'how_to' => [
                'Okos eszközök → Utazástervező.',
                'Űrlap kitöltése (cél, napok, keret).',
                'Költségek szerkesztése, PDF letöltés.',
            ],
        ],
        [
            'id' => 'settings',
            'path' => '/settings',
            'summary' => 'Háztartás, tagok, modulok, kategóriák, előfizetés, integrációk.',
            'how_to' => [
                'Háztartás fül: név, tagok, új fiók.',
                'Modulok fül: modul kapcsolók.',
                'Előfizetés fül: csomag, Stripe portál.',
            ],
        ],
        [
            'id' => 'subscription',
            'path' => '/pricing',
            'summary' => 'Ingyenes: költségvetés + 1 közös kassza. Pro: megtakarítás, tartozás, rezsi, privát kassza. Premium: vállalkozás, AI, utazás, importok.',
            'how_to' => ['/pricing vagy Beállítások → Előfizetés', 'Havi/éves Stripe fizetés'],
        ],
        [
            'id' => 'wallets',
            'summary' => 'Közös kassza (ingyenes: 1 db). Pro: korlátlan privát kassza saját költségvetéssel.',
            'how_to' => ['Fejléc kassza választó', 'Privát kassza: Pro csomag'],
        ],
        [
            'id' => 'feedback',
            'path' => '/settings?tab=feedback',
            'summary' => 'Visszajelzés és hibajelentés a fejlesztőknek.',
        ],
        [
            'id' => 'data_import',
            'title' => 'Adatimport (Excel, CSV)',
            'summary' => 'A költségvetés tömeges Excel/CSV importja JELENLEG NEM elérhető az appban. Nem lehet meglévő Excel fájlból automatikusan feltölteni a havi költségvetést és „berendezni” mindent.',
            'how_to' => [
                'Költségvetés tételei manuálisan vihetők fel: Költségvetés → + Új tétel.',
                'Ismétlődő hónapokhoz: Költségvetés → előző hónap másolása funkció (hónap klónozás).',
                'Vállalkozás rendelések: Shopify / WooCommerce / UNAS import — csak Premium, vállalkozás modul.',
                'Bérbeadás: CSV export van (letöltés), import nincs.',
                'Könyvelési dokumentumok (PDF, XLSX) feltölthetők a vállalkozás modulban — ez nem költségvetés import.',
            ],
            'features' => [
                'Nincs Excel import a költségvetéshez',
                'Hónap másolása: gyorsítás ismétlődő tételeknél',
                'Webshop import: csak vállalkozás modul, Premium csomag',
            ],
        ],
    ],
];
