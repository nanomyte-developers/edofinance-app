<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdministrativeSectorCodesInitialsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Words to ignore when generating initials (Articles, Prepositions, etc.)
        $ignoreWords = ['OF', 'AND', 'THE', 'A', 'AN', 'IN', 'FOR', 'ON', 'WITH', 'AT', 'TO'];

        $codes = DB::table('administrative_sector_codes')->get();

        foreach ($codes as $code) {
            $name = $code->name;

            // 1. Remove content inside parentheses (e.g., '(OFFICE OF THE GOVERNOR)')
            $cleanName = preg_replace('/\s*\(.*?\)\s*/', ' ', $name);
            
            // 2. Remove hyphens and replace with space (e.g., 'Pre-Degree' -> 'Pre Degree')
            $cleanName = str_replace('-', ' ', $cleanName);

            // 3. Split the remaining words and filter
            $words = explode(' ', strtoupper($cleanName));
            $initials = '';

            foreach ($words as $word) {
                $word = trim($word);

                // Only take the initial if the word is not in the ignore list and is not empty
                if (!empty($word) && !in_array($word, $ignoreWords)) {
                    $initials .= $word[0];
                }
            }

            // Fallback: if initials are too long or too short, use a simpler method
            if (strlen($initials) > 10 || strlen($initials) < 2) {
                // If the logic fails, use the first letter of the first two words
                $simpleWords = array_filter(explode(' ', strtoupper($cleanName)));
                $initials = implode('', array_map(fn($w) => $w[0], array_slice($simpleWords, 0, 2)));
            }
            
            // Final sanity check and trim
            $initials = Str::limit($initials, 10, '');


            // 4. Update the record
            DB::table('administrative_sector_codes')
                ->where('id', $code->id)
                ->update([
                    'initials' => $initials,
                    // You can optionally set a default type or status here:
                    // 'type' => 'MDA',
                    // 'status' => true,
                ]);
        }
    }
}
