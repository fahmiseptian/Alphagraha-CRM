<?php

namespace App\Support;

use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel master vendor: baris dikelompokkan per nama perusahaan (1 vendor, banyak PIC).
 */
class VendorExcel
{
    public const HEADERS = [
        'No',
        'Nama Perusahaan',
        'Status Perusahaan',
        'TOP',
        'Nama PIC',
        'Job Role',
        'No Telp',
        'Email',
    ];

    public static function template(): StreamedResponse
    {
        $sheet = self::sheetFromGroups([
            [
                'name' => 'PT. Synnex Metrodata Indonesia',
                'company_status' => 'Distributor',
                'top' => CustomerTop::DAYS_30,
                'pics' => [
                    ['name' => 'Steffi Anastasia', 'job_role' => 'Channel Sales', 'phone' => '0853-5254-4923', 'email' => 'steffi.bisara@metrodata.co.id'],
                ],
            ],
            [
                'name' => 'PT. Tech Data Advanced Solutions Indonesia',
                'company_status' => 'Distributor',
                'top' => CustomerTop::DAYS_30,
                'pics' => [
                    ['name' => 'Gerald Pramudya', 'job_role' => 'Account Representative (HPI, Lenovo, Microsoft)', 'phone' => '0812-9770-0872', 'email' => 'gerald.pramudya@techdata.com'],
                ],
            ],
            [
                'name' => 'PT. Adakom International Technology',
                'company_status' => 'Distributor',
                'top' => CustomerTop::DAYS_30,
                'pics' => [
                    ['name' => 'FAISHAL TAUFIK', 'job_role' => 'Account Manager (Asus, Ricoh)', 'phone' => '0896-3291-8908', 'email' => 'faisal@ada-kom.com'],
                    ['name' => 'Contoh PIC Kedua', 'job_role' => 'Sales Support', 'phone' => '0812-0000-0000', 'email' => 'support@ada-kom.com'],
                ],
            ],
        ]);

        return CatalogExcel::downloadCustom(
            'template-vendors.xlsx',
            self::HEADERS,
            $sheet['rows'],
            'Vendors',
            $sheet['merges']
        );
    }

    /**
     * @param  iterable<Vendor>  $vendors
     */
    public static function export(iterable $vendors): StreamedResponse
    {
        $groups = [];
        foreach ($vendors as $vendor) {
            $groups[] = [
                'name' => $vendor->name,
                'company_status' => (string) ($vendor->company_status ?? ''),
                'top' => $vendor->topLabel(),
                'pics' => $vendor->pics->map(fn ($pic) => [
                    'name' => $pic->name,
                    'job_role' => (string) ($pic->job_role ?? ''),
                    'phone' => (string) ($pic->phone ?? ''),
                    'email' => (string) ($pic->email ?? ''),
                ])->all(),
            ];
        }

        $sheet = self::sheetFromGroups($groups);

        return CatalogExcel::downloadCustom('vendors.xlsx', self::HEADERS, $sheet['rows'], 'Vendors', $sheet['merges']);
    }

    /**
     * @return array{created: int, updated: int, pics: int, skipped: int}
     */
    public static function import(UploadedFile $file): array
    {
        $maps = CatalogExcel::parseToMaps($file);

        return DB::transaction(function () use ($maps) {
            $created = 0;
            $updated = 0;
            $pics = 0;
            $skipped = 0;
            $grouped = self::groupRowsByCompany($maps, $skipped);

            foreach ($grouped as $group) {
                $vendor = Vendor::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($group['name'])])
                    ->first();

                $payload = [
                    'name' => $group['name'],
                    'company_status' => $group['company_status'] !== '' ? $group['company_status'] : null,
                    'top' => $group['top'],
                ];

                if ($vendor) {
                    $vendor->update($payload);
                    $updated++;
                } else {
                    $vendor = Vendor::query()->create($payload + [
                        'is_active' => true,
                        'sort_order' => 0,
                    ]);
                    $created++;
                }

                foreach ($group['pics'] as $i => $picRow) {
                    $existing = $vendor->pics()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($picRow['name'])])
                        ->first();

                    $data = [
                        'name' => $picRow['name'],
                        'job_role' => $picRow['job_role'] !== '' ? $picRow['job_role'] : null,
                        'phone' => $picRow['phone'] !== '' ? $picRow['phone'] : null,
                        'email' => $picRow['email'] !== '' ? $picRow['email'] : null,
                        'sort_order' => $i,
                    ];

                    if ($existing) {
                        $existing->update($data);
                    } else {
                        $vendor->pics()->create($data);
                    }
                    $pics++;
                }
            }

            return compact('created', 'updated', 'pics', 'skipped');
        });
    }

    public static function resultMessage(array $result): string
    {
        $created = (int) ($result['created'] ?? 0);
        $updated = (int) ($result['updated'] ?? 0);
        $pics = (int) ($result['pics'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);

        $msg = sprintf(
            'Import vendor selesai: %d perusahaan baru, %d diperbarui, %d PIC.',
            $created,
            $updated,
            $pics
        );

        if ($skipped > 0) {
            $msg .= sprintf(' %d baris dilewati.', $skipped);
        }

        return $msg;
    }

    /**
     * Satu grup = satu perusahaan. PIC tambahan hanya mengisi kolom PIC.
     *
     * @param  list<array{name: string, company_status?: string, top?: string, pics?: list<array<string, string>>}>  $groups
     * @return array{rows: list<list<string|int>>, merges: list<string>}
     */
    protected static function sheetFromGroups(array $groups): array
    {
        $rows = [];
        $merges = [];
        $excelRow = 2;
        $no = 1;

        foreach ($groups as $group) {
            $pics = array_values($group['pics'] ?? []);
            if ($pics === []) {
                $pics = [['name' => '', 'job_role' => '', 'phone' => '', 'email' => '']];
            }

            $count = count($pics);
            $start = $excelRow;
            $end = $excelRow + $count - 1;

            foreach ($pics as $i => $pic) {
                $isFirst = $i === 0;
                $rows[] = [
                    $isFirst ? $no : '',
                    $isFirst ? (string) ($group['name'] ?? '') : '',
                    $isFirst ? (string) ($group['company_status'] ?? '') : '',
                    $isFirst ? (string) ($group['top'] ?? CustomerTop::LABELS[CustomerTop::DAYS_30]) : '',
                    (string) ($pic['name'] ?? ''),
                    (string) ($pic['job_role'] ?? ''),
                    (string) ($pic['phone'] ?? ''),
                    (string) ($pic['email'] ?? ''),
                ];
            }

            if ($count > 1) {
                $merges[] = "A{$start}:A{$end}";
                $merges[] = "B{$start}:B{$end}";
                $merges[] = "C{$start}:C{$end}";
                $merges[] = "D{$start}:D{$end}";
            }

            $excelRow += $count;
            $no++;
        }

        return compact('rows', 'merges');
    }

    /**
     * Group by nama perusahaan. Nama/status kosong mengikuti baris grup di atasnya.
     *
     * @param  list<array<string, string>>  $maps
     * @return list<array{name: string, company_status: string, top: string, pics: list<array{name: string, job_role: string, phone: string, email: string}>}>
     */
    protected static function groupRowsByCompany(array $maps, int &$skipped): array
    {
        $grouped = [];
        $lastCompany = '';
        $lastStatus = '';
        $lastTop = CustomerTop::DAYS_30;

        foreach ($maps as $map) {
            $rawCompany = trim((string) ($map['nama_perusahaan'] ?? $map['nama'] ?? $map['name'] ?? ''));
            $rawStatus = trim((string) ($map['status_perusahaan'] ?? $map['status'] ?? ''));
            $rawTop = trim((string) ($map['top'] ?? ''));
            $picName = trim((string) ($map['nama_pic'] ?? ''));
            if ($picName === '' && isset($map['nama_perusahaan']) && trim((string) ($map['nama'] ?? '')) !== '') {
                $picName = trim((string) $map['nama']);
            }
            $jobRole = trim((string) ($map['job_role'] ?? ''));
            $phone = trim((string) ($map['no_telp'] ?? ''));
            $email = trim((string) ($map['email'] ?? ''));

            if ($rawCompany !== '') {
                $lastCompany = $rawCompany;
                $lastStatus = $rawStatus;
                $lastTop = self::parseTop($rawTop);
            }
            if ($rawStatus !== '') {
                $lastStatus = $rawStatus;
            }
            if ($rawTop !== '') {
                $lastTop = self::parseTop($rawTop);
            }

            $company = $lastCompany;
            $status = $lastStatus;
            $top = $lastTop;

            if ($company === '') {
                $skipped++;
                continue;
            }
            if (mb_strlen($company) > 255) {
                $skipped++;
                continue;
            }

            $key = mb_strtolower($company);
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $company,
                    'company_status' => $status,
                    'top' => $top,
                    'pics' => [],
                ];
            } elseif ($grouped[$key]['company_status'] === '' && $status !== '') {
                $grouped[$key]['company_status'] = $status;
            }
            if ($rawTop !== '') {
                $grouped[$key]['top'] = $top;
            }

            if ($picName === '' || str_starts_with(Str::lower($picName), 'contoh ')) {
                continue;
            }

            $grouped[$key]['pics'][] = [
                'name' => $picName,
                'job_role' => $jobRole,
                'phone' => $phone,
                'email' => $email,
            ];
        }

        return array_values($grouped);
    }

    protected static function parseTop(mixed $value): string
    {
        $raw = trim((string) $value);
        if (CustomerTop::isValid($raw)) {
            return $raw;
        }

        $lower = Str::lower($raw);
        if ($lower === '' || $lower === '-') {
            return CustomerTop::DAYS_30;
        }

        foreach (CustomerTop::LABELS as $key => $label) {
            if (Str::lower($label) === $lower) {
                return $key;
            }
        }

        if (str_contains($lower, 'cash')) {
            return CustomerTop::CASH;
        }

        if (preg_match('/(\d+)/', $raw, $matches) && CustomerTop::isValid($matches[1])) {
            return $matches[1];
        }

        return CustomerTop::DAYS_30;
    }
}
