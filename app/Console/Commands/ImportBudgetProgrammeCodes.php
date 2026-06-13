<?php
// app/Console/Commands/ImportBudgetProgrammeCodes.php

namespace App\Console\Commands;

use App\Models\FinancialYear;
use App\Models\ProgrammeCode;
use App\Models\EconomyCode;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportBudgetProgrammeCodes extends Command
{
    protected $signature = 'budget:import 
                            {file : Path to the Excel file}
                            {--year=2026 : Financial year for the budget}
                            {--sheet= : Specific sheet to import (optional)}
                            {--dry-run : Test import without saving to database}';
    
    protected $description = 'Import programme codes and budget from Excel file';

    // Track statistics
    private $stats = [
        'mdas' => 0,
        'projects' => 0,
        'errors' => 0,
        'warnings' => 0,
    ];

    public function handle()
    {
        $filePath = $this->argument('file');
        $year = $this->option('year');
        $dryRun = $this->option('dry-run');
        
        // Check if file exists
        if (!file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");
            return 1;
        }

        $this->info("📂 Loading Excel file: " . basename($filePath));
        
        // Get or create financial year
        $financialYear = $this->getOrCreateFinancialYear($year);
        if (!$financialYear) {
            $this->error("❌ Could not create financial year for {$year}");
            return 1;
        }
        
        $this->info("📅 Financial Year: {$financialYear->name} (ID: {$financialYear->id})");
        
        if ($dryRun) {
            $this->warn("⚠️  DRY RUN MODE - No data will be saved to database");
        }
        
        // Load the spreadsheet
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Exception $e) {
            $this->error("❌ Could not load Excel file: " . $e->getMessage());
            return 1;
        }
        
        // Define sectors and their sheet names
        $sectors = [
            'ADMIN SECTOR' => 'ADMIN SECTOR',
            'ECONOMIC SECTOR' => 'ECONOMIC SECTOR',
            'JUSTICE SECTOR' => 'LAW AND JUSTICE SECTOR',
            'SOCIAL SECTOR' => 'SOCIAL SECTOR',
        ];
        
        // If specific sheet is provided, only process that one
        if ($this->option('sheet')) {
            $sheetName = $this->option('sheet');
            $sectors = array_filter($sectors, function($value) use ($sheetName) {
                return $value === $sheetName;
            });
        }
        
        DB::beginTransaction();
        
        try {
            foreach ($sectors as $sectorKey => $sheetName) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
                if (!$sheet) {
                    $this->warn("⚠️  Sheet not found: {$sheetName}");
                    continue;
                }
                
                $this->newLine();
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("📊 Processing {$sectorKey} ({$sheetName})");
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                
                $this->processSheet($sheet, $financialYear->id, $sectorKey, $dryRun);
            }
            
            if (!$dryRun) {
                DB::commit();
                $this->newLine();
                $this->info("✅ " . str_repeat("=", 50));
                $this->info("✅ IMPORT COMPLETED SUCCESSFULLY!");
                $this->info("✅ " . str_repeat("=", 50));
                $this->newLine();
                $this->table(
                    ['Category', 'Count'],
                    [
                        ['MDAs/Parents Created', $this->stats['mdas']],
                        ['Projects Created', $this->stats['projects']],
                        ['Errors', $this->stats['errors']],
                        ['Warnings', $this->stats['warnings']],
                    ]
                );
            } else {
                DB::rollBack();
                $this->newLine();
                $this->info("✅ DRY RUN COMPLETED - No data was saved");
                $this->table(
                    ['Category', 'Count'],
                    [
                        ['MDAs/Parents Would Be Created', $this->stats['mdas']],
                        ['Projects Would Be Created', $this->stats['projects']],
                        ['Errors Found', $this->stats['errors']],
                        ['Warnings', $this->stats['warnings']],
                    ]
                );
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Import failed: " . $e->getMessage());
            Log::error('Budget import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
        
        return 0;
    }

    private function processSheet($sheet, $financialYearId, $sector, $dryRun = false)
    {
        $rows = $sheet->toArray();
        $totalRows = count($rows);
        
        $this->info("📄 Total rows in sheet: {$totalRows}");
        
        $currentMda = null;
        $currentMdaBudget = 0;
        $parentProgrammeCode = null;
        
        // Skip header rows (usually first 3-4 rows)
        $startRow = $this->findStartRow($rows);
        $dataRows = array_slice($rows, $startRow);
        
        $this->info("📍 Data starts at row: " . ($startRow + 1));
        $this->info("🔄 Processing " . count($dataRows) . " data rows...");
        $this->newLine();
        
        $progressBar = $this->output->createProgressBar(count($dataRows));
        $progressBar->start();
        
        foreach ($dataRows as $rowIndex => $row) {
            $progressBar->advance();
            
            $programmeCode = trim($row[0] ?? '');
            $projectDescription = trim($row[1] ?? '');
            $budgetAmount = $this->parseBudgetAmount($row[2] ?? '');
            
            // Skip completely empty rows
            if (empty($programmeCode) && empty($projectDescription) && $budgetAmount == 0) {
                continue;
            }
            
            // Check if this is an MDA/Header row
            $isMdaRow = $this->isMdaRow($programmeCode, $projectDescription, $budgetAmount, $row);
            
            if ($isMdaRow) {
                // Extract MDA name from the description
                $mdaName = $this->extractMdaName($projectDescription, $programmeCode);
                
                if ($mdaName) {
                    $this->newLine();
                    $this->info("\n🏢 Found MDA: {$mdaName}");
                    $this->line("   📌 Code: " . ($programmeCode ?: 'Auto-generated'));
                    $this->line("   💰 Budget: " . number_format($budgetAmount, 2));
                    
                    if (!$dryRun) {
                        // Create or find existing MDA parent programme code
                        $parentProgrammeCode = ProgrammeCode::updateOrCreate(
                            [
                                'code' => $programmeCode ?: $this->generateMdaCode($mdaName, $sector),
                                'financial_year_id' => $financialYearId,
                            ],
                            [
                                'name' => $mdaName,
                                'description' => $mdaName,
                                'approved_budget' => $budgetAmount,
                                'utilized_budget' => 0,
                                'remaining_budget' => $budgetAmount,
                                'is_active' => true,
                                'financial_year_id' => $financialYearId,
                                'sector' => $sector,
                                'mda_name' => $mdaName,
                                'is_mda_parent' => true,
                            ]
                        );
                        
                        $this->stats['mdas']++;
                    } else {
                        // Create a mock object for dry run
                        $parentProgrammeCode = (object)['id' => 'mock_' . uniqid()];
                        $this->stats['mdas']++;
                    }
                    
                    $currentMda = $mdaName;
                    $currentMdaBudget = $budgetAmount;
                } else {
                    $this->warn("\n⚠️  Could not extract MDA name from: " . substr($projectDescription, 0, 50));
                    $this->stats['warnings']++;
                }
            } 
            // Check if this is a project row
            elseif ($this->isProjectRow($programmeCode, $projectDescription, $budgetAmount, $parentProgrammeCode)) {
                
                // Extract economic code ID from programme code
                $economicCodeId = $this->getEconomicCodeId($programmeCode);
                
                if (!$economicCodeId) {
                    $this->warn("\n⚠️  No economic code found for: {$programmeCode} - {$projectDescription}");
                    $this->stats['warnings']++;
                }
                
                if (!$dryRun) {
                    // Create project programme code
                    ProgrammeCode::updateOrCreate(
                        [
                            'code' => $programmeCode,
                            'financial_year_id' => $financialYearId,
                        ],
                        [
                            'name' => $this->truncateText($projectDescription, 250),
                            'description' => $projectDescription,
                            'project_description' => $projectDescription,
                            'approved_budget' => $budgetAmount,
                            'utilized_budget' => 0,
                            'remaining_budget' => $budgetAmount,
                            'is_active' => true,
                            'financial_year_id' => $financialYearId,
                            'sector' => $sector,
                            'mda_name' => $currentMda,
                            'parent_programme_code_id' => $parentProgrammeCode ? $parentProgrammeCode->id : null,
                            'is_mda_parent' => false,
                            'economic_code_id' => $economicCodeId,
                        ]
                    );
                    
                    $this->stats['projects']++;
                } else {
                    $this->stats['projects']++;
                }
            }
            // Check for MDA row without programme code (just text)
            elseif (empty($programmeCode) && !empty($projectDescription) && $budgetAmount > 0 && !$parentProgrammeCode) {
                // Try to detect if this is an MDA from the text
                if ($this->isTextMdaRow($projectDescription)) {
                    $mdaName = $this->cleanMdaName($projectDescription);
                    
                    $this->newLine();
                    $this->warn("\n🏢 Found MDA without code: {$mdaName}");
                    $this->line("   💰 Budget: " . number_format($budgetAmount, 2));
                    
                    if (!$dryRun) {
                        $generatedCode = $this->generateMdaCode($mdaName, $sector);
                        
                        $parentProgrammeCode = ProgrammeCode::create([
                            'code' => $generatedCode,
                            'name' => $mdaName,
                            'description' => $mdaName,
                            'approved_budget' => $budgetAmount,
                            'utilized_budget' => 0,
                            'remaining_budget' => $budgetAmount,
                            'is_active' => true,
                            'financial_year_id' => $financialYearId,
                            'sector' => $sector,
                            'mda_name' => $mdaName,
                            'is_mda_parent' => true,
                        ]);
                        
                        $this->stats['mdas']++;
                    } else {
                        $parentProgrammeCode = (object)['id' => 'mock_' . uniqid()];
                        $this->stats['mdas']++;
                    }
                    
                    $currentMda = $mdaName;
                    $currentMdaBudget = $budgetAmount;
                }
            }
        }
        
        $progressBar->finish();
        $this->newLine(2);
    }
    
    private function findStartRow($rows)
    {
        // Look for the first row that contains a programme code pattern
        foreach ($rows as $index => $row) {
            $firstCol = trim($row[0] ?? '');
            $secondCol = trim($row[1] ?? '');
            
            // Look for programme code pattern (numbers, 10+ digits)
            if (preg_match('/^\d{10,}$/', $firstCol) || 
                preg_match('/MINISTRY|OFFICE|COMMISSION|AGENCY|BUREAU/i', $secondCol)) {
                return $index;
            }
        }
        
        // Default: skip first 3 rows
        return 3;
    }
    
    private function isMdaRow($code, $description, $budget, $row)
    {
        // Check if this is an MDA row based on patterns
        $description = strtoupper($description);
        
        // Has a programme code and description that looks like an MDA
        if (!empty($code) && !empty($description)) {
            $mdaKeywords = ['OFFICE', 'MINISTRY', 'COMMISSION', 'AGENCY', 'BUREAU', 'DEPARTMENT', 'SERVICE', 'AUTHORITY'];
            foreach ($mdaKeywords as $keyword) {
                if (str_contains($description, $keyword)) {
                    return true;
                }
            }
        }
        
        // Has "Total" in any column
        foreach ($row as $cell) {
            if (str_contains(strtoupper($cell), 'TOTAL')) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isProjectRow($code, $description, $budget, $parentProgrammeCode)
    {
        // Must have parent MDA
        if (!$parentProgrammeCode) {
            return false;
        }
        
        // Must have a programme code and description
        if (empty($code) || empty($description)) {
            return false;
        }
        
        // Must have a budget amount
        if ($budget <= 0) {
            return false;
        }
        
        // Should not be an MDA row
        if ($this->isMdaRow($code, $description, $budget, [])) {
            return false;
        }
        
        return true;
    }
    
    private function isTextMdaRow($description)
    {
        $description = strtoupper($description);
        $mdaKeywords = ['MINISTRY', 'OFFICE OF', 'COMMISSION', 'AGENCY', 'BUREAU', 'DEPARTMENT', 'AUTHORITY'];
        
        foreach ($mdaKeywords as $keyword) {
            if (str_contains($description, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function extractMdaName($description, $code)
    {
        // Clean up the description
        $description = trim($description);
        
        // If description already looks like an MDA name
        if (preg_match('/^[A-Z\s\&\-\/]+$/', $description) && strlen($description) > 5) {
            return ucwords(strtolower($description));
        }
        
        // Look for common MDA patterns
        $patterns = [
            '/^(MINISTRY OF .+?)(?:\s+\(|$)/i',
            '/^(OFFICE OF .+?)(?:\s+\(|$)/i',
            '/^(.+? COMMISSION)(?:\s+\(|$)/i',
            '/^(.+? AGENCY)(?:\s+\(|$)/i',
            '/^(.+? BUREAU)(?:\s+\(|$)/i',
            '/^(.+? DEPARTMENT)(?:\s+\(|$)/i',
            '/^(.+? AUTHORITY)(?:\s+\(|$)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                return trim($matches[1]);
            }
        }
        
        // If we have a code but no good description, use code as name
        if (!empty($code) && empty($description)) {
            return "MDA_{$code}";
        }
        
        // Return cleaned description (first 100 chars)
        return substr($description, 0, 100);
    }
    
    private function cleanMdaName($description)
    {
        // Remove extra spaces and clean
        $cleaned = preg_replace('/\s+/', ' ', $description);
        $cleaned = trim($cleaned);
        
        // Remove trailing "Total" or other markers
        $cleaned = preg_replace('/\s+Total$/i', '', $cleaned);
        
        return $cleaned;
    }
    
    private function parseBudgetAmount($value)
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        // Handle Excel date serial numbers
        if (is_float($value) && $value > 40000) {
            return 0;
        }
        
        // Remove commas, spaces, and currency symbols
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);
        
        if (empty($cleaned)) {
            return 0;
        }
        
        return (float) $cleaned;
    }
    
    private function getEconomicCodeId($programmeCode)
    {
        if (empty($programmeCode)) {
            return null;
        }
        
        // Try different prefix lengths
        $prefixes = [
            substr($programmeCode, 0, 6),  // First 6 digits
            substr($programmeCode, 0, 4),  // First 4 digits
            substr($programmeCode, 0, 3),  // First 3 digits
            substr($programmeCode, 0, 2),  // First 2 digits
        ];
        
        foreach ($prefixes as $prefix) {
            $economyCode = EconomyCode::where('code', 'LIKE', "{$prefix}%")->first();
            if ($economyCode) {
                return $economyCode->id;
            }
        }
        
        // For series 32 specifically
        if (str_starts_with($programmeCode, '32')) {
            $economyCode = EconomyCode::where('code', 'LIKE', '32%')->first();
            if ($economyCode) {
                return $economyCode->id;
            }
        }
        
        return null;
    }
    
    private function generateMdaCode($mdaName, $sector)
    {
        // Generate a unique code for MDA without a programme code
        $sectorCode = substr(md5($sector), 0, 4);
        $nameCode = substr(md5($mdaName), 0, 8);
        $timestamp = time();
        
        return strtoupper("MDA_{$sectorCode}_{$nameCode}_{$timestamp}");
    }
    
    private function getOrCreateFinancialYear($year)
    {
        // Try to find existing financial year
        $financialYear = FinancialYear::where('name', 'LIKE', "%{$year}%")
            ->orWhere('start_date', 'LIKE', "%{$year}%")
            ->first();
        
        if ($financialYear) {
            return $financialYear;
        }
        
        // Create new financial year
        return FinancialYear::create([
            'name' => "{$year} Financial Year",
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'is_active' => true,
        ]);
    }
    
    private function truncateText($text, $length = 250)
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length - 3) . '...';
    }
}