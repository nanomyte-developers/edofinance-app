<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class EconomyCodesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Clear existing data
        DB::table('economy_code_items')->delete();
        DB::table('economy_codes')->delete();

        $data = [
            // Revenue Section - 10000000
            ['code' => '10000000', 'name' => 'Revenue', 'is_header' => true],
            ['code' => '10010199', 'name' => 'Revenue Account', 'is_header' => false],
            
            // FAAC Revenue - 11010100
            ['code' => '11010100', 'name' => 'Government Share Of FAAC (Statutory Revenue)', 'is_header' => true],
            ['code' => '11010101', 'name' => 'Statutory Allocation', 'is_header' => false],
            ['code' => '11010102', 'name' => 'JAAC (Statutory Allocation)', 'is_header' => false],
            ['code' => '11010103', 'name' => 'Training Refunds (International travelling)', 'is_header' => false],
            ['code' => '11010104', 'name' => 'Faac Special Allocations', 'is_header' => false],
            ['code' => '11010105', 'name' => 'Budget Augmentation', 'is_header' => false],
            ['code' => '11010106', 'name' => '13% Mineral Derivation', 'is_header' => false],
            ['code' => '11010107', 'name' => 'Recovery Of Bailout Funds From LGCS', 'is_header' => false],
            ['code' => '11010108', 'name' => 'Exchange Rate Gain', 'is_header' => false],
            ['code' => '11010109', 'name' => 'Subsidy Re-Investment Programmes (Sure-P)', 'is_header' => false],
            ['code' => '11010110', 'name' => 'Refund By Rivers State', 'is_header' => false],
            ['code' => '11010111', 'name' => 'Refund Of N10b, Loan Deduction', 'is_header' => false],
            ['code' => '11010112', 'name' => 'Refund of Excess Bank Charges', 'is_header' => false],
            ['code' => '11010113', 'name' => 'Other FGN FAAC Fund', 'is_header' => false],
            ['code' => '11010114', 'name' => 'Refunds Of Paris Club Overdeduction', 'is_header' => false],
            ['code' => '11010115', 'name' => 'FSP Budget Support Facility', 'is_header' => false],
            ['code' => '11010116', 'name' => 'FG Loan (Bailout) To Pay Salaries', 'is_header' => false],
            ['code' => '11010117', 'name' => 'Non Oil Revenue', 'is_header' => false],
            ['code' => '11010118', 'name' => 'NDA & Okwori Refund', 'is_header' => false],
            ['code' => '11010119', 'name' => 'Capital Refund', 'is_header' => false],
            ['code' => '11010120', 'name' => 'Additional Funds From NNPC', 'is_header' => false],
            ['code' => '11010121', 'name' => 'Stabilization Fund', 'is_header' => false],
            ['code' => '11010122', 'name' => 'Federal Government Refund on Roads', 'is_header' => false],
            ['code' => '11010123', 'name' => 'Coctribution to Roads', 'is_header' => false],
            ['code' => '11010124', 'name' => 'Tax Refund', 'is_header' => false],
            ['code' => '11010125', 'name' => 'Recovery of Loan from LGC IRO Rollout Cashless Technology', 'is_header' => false],
            ['code' => '11010126', 'name' => '13% Mineral Derivation', 'is_header' => false],
            ['code' => '11010127', 'name' => 'Share of Solid Mineral', 'is_header' => false],
            ['code' => '11010128', 'name' => 'FGN Goods & Valuable Consideration', 'is_header' => false],
            ['code' => '11010129', 'name' => 'FGN Project Gazette', 'is_header' => false],
            ['code' => '11010130', 'name' => 'Revenue Equalization', 'is_header' => false],
            
            // VAT - 11010200
            ['code' => '11010200', 'name' => 'Government Share Of VAT', 'is_header' => true],
            ['code' => '11010201', 'name' => 'Share Of VAT', 'is_header' => false],
            ['code' => '11010202', 'name' => 'Pension Re-Imbursement', 'is_header' => false],
            ['code' => '11010203', 'name' => 'Refund to Edo State Government', 'is_header' => false],
            ['code' => '11010204', 'name' => 'Bailout (ISPO)', 'is_header' => false],
            ['code' => '11010205', 'name' => 'Health Intervention Loan', 'is_header' => false],
            
            // Excess Crude - 11010300
            ['code' => '11010300', 'name' => 'Government Share Of Excess Crude Account', 'is_header' => true],
            ['code' => '11010301', 'name' => 'Excess Crude', 'is_header' => false],
            ['code' => '11010302', 'name' => 'Refund Of CAC\'s Loan', 'is_header' => false],
            ['code' => '11010303', 'name' => 'Ecological Funds', 'is_header' => false],
            
            // Independent Revenue - 12000000
            ['code' => '12000000', 'name' => 'Independent Revenue', 'is_header' => true],
            
            // Tax Revenue - 12010000
            ['code' => '12010000', 'name' => 'Tax Revenue', 'is_header' => true],
            
            // Personal Taxes - 12010100
            ['code' => '12010100', 'name' => 'Personal Taxes', 'is_header' => true],
            ['code' => '12010101', 'name' => 'Personal Taxes', 'is_header' => false],
            ['code' => '12010104', 'name' => 'Stamp Duty', 'is_header' => false],
            ['code' => '12010105', 'name' => 'Pool Betting Tax', 'is_header' => false],
            ['code' => '12010106', 'name' => 'Development Tax/Levy', 'is_header' => false],
            ['code' => '12010107', 'name' => 'Capital Gain Tax', 'is_header' => false],
            ['code' => '12010108', 'name' => 'Direct Assessment', 'is_header' => false],
            ['code' => '12010109', 'name' => 'Withholding Tax', 'is_header' => false],
            ['code' => '12010110', 'name' => 'Consumption Tax', 'is_header' => false],
            ['code' => '12010112', 'name' => 'Tax Audit', 'is_header' => false],
            ['code' => '12010113', 'name' => 'Electronic Money Transfer Fee', 'is_header' => false],
            ['code' => '12010199', 'name' => 'Tax Account', 'is_header' => false],
            
            // Non-Tax Revenue - 12020000
            ['code' => '12020000', 'name' => 'Non-Tax Revenue', 'is_header' => true],
            
            // Licences - 12020100
            ['code' => '12020100', 'name' => 'Licences - General', 'is_header' => true],
            ['code' => '12020105', 'name' => 'Radio/Television Station Licences', 'is_header' => false],
            ['code' => '12020106', 'name' => 'Re-imbursement', 'is_header' => false],
            ['code' => '12020107', 'name' => 'Boats & Canoe (Small Craft ) Licence', 'is_header' => false],
            ['code' => '12020109', 'name' => 'Registation Of Voluntary Organizations', 'is_header' => false],
            ['code' => '12020110', 'name' => 'Inland Water-Way Licence', 'is_header' => false],
            ['code' => '12020111', 'name' => 'Bake House Licence', 'is_header' => false],
            ['code' => '12020113', 'name' => 'Brickmaking, Etc Licence', 'is_header' => false],
            ['code' => '12020114', 'name' => 'Cart Licences', 'is_header' => false],
            ['code' => '12020115', 'name' => 'Dane Gun Licences', 'is_header' => false],
            ['code' => '12020116', 'name' => 'Cattle Dealer Licences', 'is_header' => false],
            ['code' => '12020117', 'name' => 'Dried Fish & Meat Licences', 'is_header' => false],
            ['code' => '12020118', 'name' => 'Pet (Dog) Licences', 'is_header' => false],
            ['code' => '12020119', 'name' => 'Fishing Permits', 'is_header' => false],
            ['code' => '12020120', 'name' => 'Hawker\'s Permits', 'is_header' => false],
            ['code' => '12020121', 'name' => 'Hunting Permits', 'is_header' => false],
            ['code' => '12020122', 'name' => 'Produce Buying Licences', 'is_header' => false],
            ['code' => '12020123', 'name' => 'Registration Fees for NGOs', 'is_header' => false],
            ['code' => '12020126', 'name' => 'Tractor Hiring Services', 'is_header' => false],
            ['code' => '12020128', 'name' => 'Borehole Drilling Licences', 'is_header' => false],
            ['code' => '12020129', 'name' => 'Pool Betting & Casino Licences/Gaming', 'is_header' => false],
            ['code' => '12020130', 'name' => 'Cinematograph Licences', 'is_header' => false],
            ['code' => '12020131', 'name' => '', 'is_header' => false],
            ['code' => '12020132', 'name' => 'Motor Vehicle License', 'is_header' => false],
            ['code' => '12020133', 'name' => 'Drivers\' Licences', 'is_header' => false],
            ['code' => '12020134', 'name' => 'Patent Medicine & Drug Stores Licences', 'is_header' => false],
            ['code' => '12020135', 'name' => 'Private Schools Licences', 'is_header' => false],
            ['code' => '12020136', 'name' => 'Health Facilities Licences', 'is_header' => false],
            ['code' => '12020137', 'name' => 'Trade Permit Licences', 'is_header' => false],
            ['code' => '12020138', 'name' => 'Forestry/Timber Licence', 'is_header' => false],
            ['code' => '12020140', 'name' => 'Lottery Permit', 'is_header' => false],
            ['code' => '12020144', 'name' => 'Games and Sawmillers', 'is_header' => false],
            ['code' => '12020145', 'name' => 'Miscellaneous Receipt', 'is_header' => false],
            ['code' => '12020146', 'name' => 'Electronic Money Transfer Fee', 'is_header' => false],
            
            // Mining Rents - 12020200
            ['code' => '12020200', 'name' => 'Mining Rents', 'is_header' => true],
            ['code' => '12020201', 'name' => 'Mining Rents', 'is_header' => false],
            
            // Royalties - 12020300
            ['code' => '12020300', 'name' => 'Royalties', 'is_header' => true],
            ['code' => '12020301', 'name' => 'Royalties', 'is_header' => false],
            
            // Fees - General - 12020400
            ['code' => '12020400', 'name' => 'Fees - General', 'is_header' => true],
            ['code' => '12020401', 'name' => 'Court Fees', 'is_header' => false],
            ['code' => '12020402', 'name' => 'Registration of Bus Premises', 'is_header' => false],
            ['code' => '12020403', 'name' => 'News/Communication', 'is_header' => false],
            ['code' => '12020404', 'name' => 'Trade Union Fees', 'is_header' => false],
            ['code' => '12020409', 'name' => 'Weights & Measure Fees', 'is_header' => false],
            ['code' => '12020410', 'name' => 'Electrical Inspectorate Fees', 'is_header' => false],
            ['code' => '12020412', 'name' => 'Research Testing Fees', 'is_header' => false],
            ['code' => '12020413', 'name' => 'Films Censorship/ Production Fees', 'is_header' => false],
            ['code' => '12020415', 'name' => 'Trade Testing Fees', 'is_header' => false],
            ['code' => '12020417', 'name' => 'Contractor Registration Fees', 'is_header' => false],
            ['code' => '12020418', 'name' => 'Marriage/ Divorce Fees', 'is_header' => false],
            ['code' => '12020419', 'name' => 'Attestation Of Bachelorhood & Spinsterhood Fees', 'is_header' => false],
            ['code' => '12020420', 'name' => 'Pilgrims Welfare Fees', 'is_header' => false],
            ['code' => '12020424', 'name' => 'Accreditation Fees', 'is_header' => false],
            ['code' => '12020425', 'name' => 'Disinfection Of Produce Fees', 'is_header' => false],
            ['code' => '12020426', 'name' => 'Court Summons/Oath Fees', 'is_header' => false],
            ['code' => '12020427', 'name' => 'Tender Fees', 'is_header' => false],
            ['code' => '12020428', 'name' => 'Fire Safety Certificate Fees', 'is_header' => false],
            ['code' => '12020430', 'name' => 'Professional Registration Fees', 'is_header' => false],
            ['code' => '12020431', 'name' => 'Environmental Impact Assessment Fees', 'is_header' => false],
            ['code' => '12020436', 'name' => 'Bill Board Advertisement Fees', 'is_header' => false],
            ['code' => '12020437', 'name' => 'Deeds Registration Fees', 'is_header' => false],
            ['code' => '12020438', 'name' => 'Survey/ Planning/ Building Fees', 'is_header' => false],
            ['code' => '12020439', 'name' => 'Agency Fees', 'is_header' => false],
            ['code' => '12020440', 'name' => 'Medical Consultancy Fees', 'is_header' => false],
            ['code' => '12020441', 'name' => 'Laboratory Fees', 'is_header' => false],
            ['code' => '12020442', 'name' => 'Association Fees', 'is_header' => false],
            ['code' => '12020443', 'name' => 'Birth & Death Registration Fees', 'is_header' => false],
            ['code' => '12020444', 'name' => 'Burial Fees', 'is_header' => false],
            ['code' => '12020445', 'name' => 'Change Of Ownership Fees', 'is_header' => false],
            ['code' => '12020446', 'name' => 'Agricultural/Vetinary Services Fees', 'is_header' => false],
            ['code' => '12020447', 'name' => 'Land Use Fees', 'is_header' => false],
            ['code' => '12020448', 'name' => 'Development Levies', 'is_header' => false],
            ['code' => '12020449', 'name' => 'Business/Trade Operating Fees', 'is_header' => false],
            ['code' => '12020450', 'name' => 'Inspection Fees', 'is_header' => false],
            ['code' => '12020451', 'name' => 'Timber & Forest Fees', 'is_header' => false],
            ['code' => '12020452', 'name' => 'School Tuition/Registration/Examination Fees-Undergraduate', 'is_header' => false],
            ['code' => '12020453', 'name' => 'Applications Fees', 'is_header' => false],
            ['code' => '12020454', 'name' => 'Parking Fees', 'is_header' => false],
            ['code' => '12020455', 'name' => 'School Tuition/Registration/Examination Fees-Postgraduate', 'is_header' => false],
            ['code' => '12020456', 'name' => 'School Tuition/Registration/Examination Fees - Others', 'is_header' => false],
            ['code' => '12020457', 'name' => 'Affiliation Charges', 'is_header' => false],
            ['code' => '12020458', 'name' => 'Unity/Staff/Other School Fees/Levies', 'is_header' => false],
            ['code' => '12020459', 'name' => 'Right Of Occupancy Fees', 'is_header' => false],
            ['code' => '12020460', 'name' => 'Building Plan Approval Fees', 'is_header' => false],
            ['code' => '12020461', 'name' => 'Title Transfer Fees', 'is_header' => false],
            ['code' => '12020462', 'name' => 'Publication Fees', 'is_header' => false],
            ['code' => '12020463', 'name' => 'Hospital Service Registration Fees', 'is_header' => false],
            ['code' => '12020464', 'name' => 'Hospital Service Charges', 'is_header' => false],
            ['code' => '12020465', 'name' => 'Sports/Recreational Facilities Fees', 'is_header' => false],
            ['code' => '12020466', 'name' => 'Indigenship Registration Fees', 'is_header' => false],
            ['code' => '12020467', 'name' => 'Traffic Offence', 'is_header' => false],
            ['code' => '12020472', 'name' => 'Environmental Audit Report', 'is_header' => false],
            ['code' => '12020477', 'name' => 'Fees from Waste Collection', 'is_header' => false],
            ['code' => '12020478', 'name' => 'Workshop Fees', 'is_header' => false],
            ['code' => '12020479', 'name' => 'Charges for Miscellanous', 'is_header' => false],
            ['code' => '12020481', 'name' => 'Contract Agreement', 'is_header' => false],
            ['code' => '12020482', 'name' => 'Public Address Equipment Fees', 'is_header' => false],
            ['code' => '12020483', 'name' => 'Registration fees for NGOs and Daycare center', 'is_header' => false],
            ['code' => '12020484', 'name' => 'Administration fees for adoption and women development market', 'is_header' => false],
            ['code' => '12020485', 'name' => 'Attestation Fees', 'is_header' => false],
            ['code' => '12020486', 'name' => 'New Connection', 'is_header' => false],
            ['code' => '12020487', 'name' => 'Tenement Rate', 'is_header' => false],
            ['code' => '12020489', 'name' => 'Special Development Levy', 'is_header' => false],
            ['code' => '12020493', 'name' => 'Haulage Fees', 'is_header' => false],
            ['code' => '12020494', 'name' => 'Benin Central Park', 'is_header' => false],
            
            // Fines - General - 12020500
            ['code' => '12020500', 'name' => 'Fines - General', 'is_header' => true],
            ['code' => '12020501', 'name' => 'Fines/Penalties', 'is_header' => false],
            ['code' => '12020502', 'name' => 'Court Fines', 'is_header' => false],
            ['code' => '12020503', 'name' => 'Dislodging Of Effluent/Pollution Fine', 'is_header' => false],
            ['code' => '12020504', 'name' => 'Environmental Mobile', 'is_header' => false],
            ['code' => '12020505', 'name' => 'Forestry Line', 'is_header' => false],
            
            // Sales - General - 12020600
            ['code' => '12020600', 'name' => 'Sales - General', 'is_header' => true],
            ['code' => '12020601', 'name' => 'Sales Of Journal & Publications', 'is_header' => false],
            ['code' => '12020603', 'name' => 'Sales Of ID Cards', 'is_header' => false],
            ['code' => '12020604', 'name' => 'Sales Of Stores/Scraps/Unservicable Items', 'is_header' => false],
            ['code' => '12020605', 'name' => 'Sales Of Vaccines', 'is_header' => false],
            ['code' => '12020606', 'name' => 'Sales Of Bills Of Entries/Application Forms', 'is_header' => false],
            ['code' => '12020607', 'name' => 'Sales Of Consultancy Registration Forms', 'is_header' => false],
            ['code' => '12020608', 'name' => 'Sales Of Improved Seeds/Chemical', 'is_header' => false],
            ['code' => '12020609', 'name' => 'Proceeds From Sales Of Farm Produce', 'is_header' => false],
            ['code' => '12020610', 'name' => 'Proceeds From Sales Of Goods By Public Auctions', 'is_header' => false],
            ['code' => '12020611', 'name' => 'Proceeds From Sales Of Govt. Vehicles', 'is_header' => false],
            ['code' => '12020612', 'name' => 'Proceeds From Sales Of Drugs And Medications', 'is_header' => false],
            ['code' => '12020613', 'name' => 'Proceeds From Sales Of Ships Scraps', 'is_header' => false],
            ['code' => '12020614', 'name' => 'Proceeds From Sales Of Govt. Building', 'is_header' => false],
            ['code' => '12020615', 'name' => 'Sales Of Uniforms', 'is_header' => false],
            ['code' => '12020616', 'name' => 'Sales Of Forms', 'is_header' => false],
            ['code' => '12020617', 'name' => 'Sales Of Plan Phostat Print/Map', 'is_header' => false],
            ['code' => '12020618', 'name' => 'Sales Of Reagents & Chemicals', 'is_header' => false],
            ['code' => '12020619', 'name' => 'Sales Of Flags/Potraits', 'is_header' => false],
            ['code' => '12020620', 'name' => 'Sales Of Other Government Properties', 'is_header' => false],
            ['code' => '12020621', 'name' => 'Sales Of Government Panapharelia (Flags, Portraits, Art Works Etc)', 'is_header' => false],
            ['code' => '12020622', 'name' => 'Sale of Waste bags/Bins', 'is_header' => false],
            ['code' => '12020698', 'name' => 'Earned Discount', 'is_header' => false],
            ['code' => '12020699', 'name' => 'Sales Account', 'is_header' => false],
            
            // Earnings - General - 12020700
            ['code' => '12020700', 'name' => 'Earnings -General', 'is_header' => true],
            ['code' => '12020701', 'name' => 'Earnings From Consultancy Services', 'is_header' => false],
            ['code' => '12020702', 'name' => 'Earnings From Laboratory Services', 'is_header' => false],
            ['code' => '12020703', 'name' => 'Earnings From Hire Of Plants & Equipment', 'is_header' => false],
            ['code' => '12020704', 'name' => 'Earnings From The Use Of Govt. Vehicles', 'is_header' => false],
            ['code' => '12020705', 'name' => 'Earnings From The Use Of Govt. Halls/Others', 'is_header' => false],
            ['code' => '12020706', 'name' => 'Earnings From Tolls Of Expressway', 'is_header' => false],
            ['code' => '12020707', 'name' => 'Earnings From Medical Services', 'is_header' => false],
            ['code' => '12020708', 'name' => 'Earnings From Agricultural Produce', 'is_header' => false],
            ['code' => '12020709', 'name' => 'Earnings From Tourism/Culture/Arts Centres', 'is_header' => false],
            ['code' => '12020710', 'name' => 'Earnings From Hire Of Aircraft', 'is_header' => false],
            ['code' => '12020711', 'name' => 'Earnings From Commercial Activities', 'is_header' => false],
            ['code' => '12020712', 'name' => 'Hire Of Academic Gown/Book Of Preceedings/Others', 'is_header' => false],
            ['code' => '12020713', 'name' => 'Earnings From Library Services', 'is_header' => false],
            ['code' => '12020714', 'name' => 'Earnings From Ict Services', 'is_header' => false],
            ['code' => '12020715', 'name' => 'Maintenance/Repairs Fees', 'is_header' => false],
            ['code' => '12020720', 'name' => 'Earnings From Guest Houses', 'is_header' => false],
            ['code' => '12020724', 'name' => 'Edo Broadcasting Service Fees Charges', 'is_header' => false],
            ['code' => '12020725', 'name' => 'Earnings from Wood Workshop/Laboratory', 'is_header' => false],
            
            // Rent On Government Buildings - 12020800
            ['code' => '12020800', 'name' => 'Rent On Government Buildings - General', 'is_header' => true],
            ['code' => '12020801', 'name' => 'Rent On Govt.Quarters', 'is_header' => false],
            ['code' => '12020802', 'name' => 'Rent On Govt.Offices', 'is_header' => false],
            ['code' => '12020803', 'name' => 'Rent On Govt Buildings', 'is_header' => false],
            ['code' => '12020804', 'name' => 'Rent On Conference Centres', 'is_header' => false],
            ['code' => '12020805', 'name' => 'Rent On Building At Aerodromes', 'is_header' => false],
            
            // Rent On Land & Others - 12020900
            ['code' => '12020900', 'name' => 'Rent On Land & Others - General', 'is_header' => true],
            ['code' => '12020901', 'name' => 'Rent On Govt. Land', 'is_header' => false],
            ['code' => '12020902', 'name' => 'Rent On Oil Plot & Aerodromes', 'is_header' => false],
            ['code' => '12020903', 'name' => 'Rents & Premium On The Allocation Of Land', 'is_header' => false],
            ['code' => '12020904', 'name' => 'Rents Of Plots & Sites Services Programme', 'is_header' => false],
            ['code' => '12020905', 'name' => 'Lease Rental', 'is_header' => false],
            ['code' => '12020906', 'name' => 'Rents On Govt. Properties', 'is_header' => false],
            ['code' => '12020907', 'name' => 'Rent On Industrial Estate', 'is_header' => false],
            
            // Investment Income - 12021100
            ['code' => '12021100', 'name' => 'Investment Income', 'is_header' => true],
            ['code' => '12021101', 'name' => 'Operating Surplus', 'is_header' => false],
            ['code' => '12021102', 'name' => 'Dividend Received', 'is_header' => false],
            ['code' => '12021103', 'name' => 'Other Investment Income', 'is_header' => false],
            
            // Interest Earned - 12021200
            ['code' => '12021200', 'name' => 'Interest Earned', 'is_header' => true],
            ['code' => '12021201', 'name' => 'Motor Vehicle Advances', 'is_header' => false],
            ['code' => '12021202', 'name' => 'Bicycle Advances (Interest)', 'is_header' => false],
            ['code' => '12021203', 'name' => 'Refurbishing Loan', 'is_header' => false],
            ['code' => '12021204', 'name' => 'Furniture Loan', 'is_header' => false],
            ['code' => '12021205', 'name' => 'Interest On Housing Loan', 'is_header' => false],
            ['code' => '12021206', 'name' => 'Interest On Loans To States', 'is_header' => false],
            ['code' => '12021207', 'name' => 'Interest On Loans To LGAs', 'is_header' => false],
            ['code' => '12021208', 'name' => 'Interest On Loans To Government Owned Companies', 'is_header' => false],
            ['code' => '12021209', 'name' => 'Interest On Debenture Loans', 'is_header' => false],
            ['code' => '12021210', 'name' => 'Bank Interest', 'is_header' => false],
            ['code' => '12021211', 'name' => 'Gains On Foreign Exchange', 'is_header' => false],
            ['code' => '12021212', 'name' => 'Interest On Treasury Bills & Fixed Deposits', 'is_header' => false],
            ['code' => '12021213', 'name' => 'Infrastructural Development Loan', 'is_header' => false],
            ['code' => '12021299', 'name' => 'Discount Taken', 'is_header' => false],
            
            // Re-Imbursement General - 12021300
            ['code' => '12021300', 'name' => 'Re-Imbursement General', 'is_header' => true],
            ['code' => '12021301', 'name' => 'Re-Imbursement', 'is_header' => false],
            ['code' => '12021302', 'name' => 'Audit Fees', 'is_header' => false],
            
            // Miscellaneous - 12021400
            ['code' => '12021400', 'name' => 'Miscellaneous', 'is_header' => true],
            ['code' => '12021401', 'name' => 'Donation to Security', 'is_header' => false],
            ['code' => '12021402', 'name' => 'Insurance Claims', 'is_header' => false],
            ['code' => '12021403', 'name' => 'Contribution to Road Projects', 'is_header' => false],
            ['code' => '12021404', 'name' => 'Contribution to Social Intervention Funds', 'is_header' => false],
            ['code' => '12021405', 'name' => 'Contribution to Health Care', 'is_header' => false],
            ['code' => '12021406', 'name' => 'Contribution by LGC to Fibre Optic Cables Across the 18 LGA\'s', 'is_header' => false],
            
            // Aid And Grants - 13000000
            ['code' => '13000000', 'name' => 'Aid And Grants', 'is_header' => true],
            
            // Aid - 13010000
            ['code' => '13010000', 'name' => 'Aid', 'is_header' => true],
            
            // Domestic Aids - 13010100
            ['code' => '13010100', 'name' => 'Domestic Aids', 'is_header' => true],
            ['code' => '13010101', 'name' => 'Current Domestic Aids', 'is_header' => false],
            
            // Foreign Aids - 13010200
            ['code' => '13010200', 'name' => 'Foreign Aids', 'is_header' => true],
            ['code' => '13010201', 'name' => 'Current Foreign Aids', 'is_header' => false],
            
            // Domestic Grants - 13020300
            ['code' => '13020300', 'name' => 'Domestic Grants', 'is_header' => true],
            ['code' => '13020301', 'name' => 'Current Domestic Grants', 'is_header' => false],
            ['code' => '13020302', 'name' => 'Capital Domestic Grants', 'is_header' => false],
            ['code' => '13020303', 'name' => 'Endowment Income', 'is_header' => false],
            
            // Foreign Grants - 13020400
            ['code' => '13020400', 'name' => 'Foreign Grants', 'is_header' => true],
            ['code' => '13020401', 'name' => 'Current Foreign Grants', 'is_header' => false],
            ['code' => '13020402', 'name' => 'Capital Foreign Grants', 'is_header' => false],
            
            // Capital Development Fund (CDF) Receipts - 14000000
            ['code' => '14000000', 'name' => 'Capital Development Fund (CDF) Receipts', 'is_header' => true],
            
            // Transfer From Consolidated Revenue Fund To CDF - 14010000
            ['code' => '14010000', 'name' => 'Transfer From Consolidated Revenue Fund To CDF', 'is_header' => true],
            ['code' => '14010101', 'name' => 'Transfer From CRF To CDF', 'is_header' => false],
            
            // Other Capital Receipts - 14020000
            ['code' => '14020000', 'name' => 'Other Capital Receipts', 'is_header' => true],
            ['code' => '14020100', 'name' => 'Other Capital Receipts', 'is_header' => false],
            ['code' => '14020200', 'name' => 'Other Capital Receipts', 'is_header' => false],
            ['code' => '14020201', 'name' => 'Other Capital Receipts To CDF', 'is_header' => false],
            ['code' => '14020202', 'name' => 'Sales Of Fixed Asset', 'is_header' => false],
            
            // Loans/Borrowings Receipt - 14030000
            ['code' => '14030000', 'name' => 'Loans/Borrowings Receipt', 'is_header' => true],
            
            // Internal Loans - 14030100
            ['code' => '14030100', 'name' => 'Internal Loans', 'is_header' => true],
            ['code' => '14030101', 'name' => 'Internal Loans/Borrowing Receipt From Financial Institutions', 'is_header' => false],
            ['code' => '14030102', 'name' => 'Internal Loans/Borrowing Receipt From Other Government Entities', 'is_header' => false],
            ['code' => '14030103', 'name' => 'Internal Loans/Borrowing Receipt From Other Entities/ Organisations', 'is_header' => false],
            
            // External Loans - 14030200
            ['code' => '14030200', 'name' => 'External Loans', 'is_header' => true],
            ['code' => '14030201', 'name' => 'External Loans/Borrowings From Financial Institutions', 'is_header' => false],
            ['code' => '14030202', 'name' => 'External Loans/Borrowings From Other Government Entities', 'is_header' => false],
            ['code' => '14030203', 'name' => 'External Loans/Borrowings From Other Entities/Organisations', 'is_header' => false],
            
            // Debt Forgiveness - 14040000
            ['code' => '14040000', 'name' => 'Debt Forgiveness', 'is_header' => true],
            
            // Foreign Debt Forgiveness - 14040100
            ['code' => '14040100', 'name' => 'Foreign Debt Forgiveness', 'is_header' => true],
            ['code' => '14040101', 'name' => 'Foreign Debt Forgiveness', 'is_header' => false],
            
            // Domestic Debt Forgiveness - 14040200
            ['code' => '14040200', 'name' => 'Domestic Debt Forgiveness', 'is_header' => true],
            ['code' => '14040201', 'name' => 'Domestic Debt Forgiveness', 'is_header' => false],
            
            // Gain On Disposal Of Asset - 14050000
            ['code' => '14050000', 'name' => 'Gain On Disposal Of Asset', 'is_header' => true],
            
            // Gain On Disposal Of Asset - PPE - 14050100
            ['code' => '14050100', 'name' => 'Gain On Disposal Of Asset - PPE', 'is_header' => true],
            ['code' => '14050101', 'name' => 'Gain On Disposal Of Asset - PPE', 'is_header' => false],
            
            // Gain On Disposal Of Asset - Investment Property - 14050200
            ['code' => '14050200', 'name' => 'Gain On Disposal Of Asset - Investment Property', 'is_header' => true],
            ['code' => '14050201', 'name' => 'Gain On Disposal Of Asset - Investment Property', 'is_header' => false],
            
            // Gain On Disposal Of Asset - Intangible - 14050300
            ['code' => '14050300', 'name' => 'Gain On Disposal Of Asset - Intangible', 'is_header' => true],
            ['code' => '14050301', 'name' => 'Gain On Disposal Of Asset -Intangible', 'is_header' => false],
            
            // Minority Interest Share Of Surplus - 14060000
            ['code' => '14060000', 'name' => 'Minority Interest Share Of Surplus', 'is_header' => true],
            
            // Minority Interest Share Of Surplus - 14060100
            ['code' => '14060100', 'name' => 'Minority Interest Share Of Surplus', 'is_header' => true],
            ['code' => '14060101', 'name' => 'Minority Interest Share Of Surplus', 'is_header' => false],
            
            // Extraordinary Items - 14070000
            ['code' => '14070000', 'name' => 'Extraordinary Items', 'is_header' => true],
            
            // Extraordinary Items - 14070100
            ['code' => '14070100', 'name' => 'Extraordinary Items', 'is_header' => true],
            ['code' => '14070101', 'name' => 'Extraordinary Items', 'is_header' => false],
            ['code' => '14070102', 'name' => 'Unspecified Revenue', 'is_header' => false],
            ['code' => '14070103', 'name' => 'Recoveries (Stolen & Other Funds)', 'is_header' => false],
            
            // Gain On Swapped Assets - 14080000
            ['code' => '14080000', 'name' => 'Gain On Swapped Assets', 'is_header' => true],
            
            // Gain On Swapped Assets - PPE - 14080100
            ['code' => '14080100', 'name' => 'Gain On Swapped Assets - PPE', 'is_header' => true],
            ['code' => '14080101', 'name' => 'Gain On Swapped Assets - PPE', 'is_header' => false],
            
            // Gain On Swapped Assets - Investment Property - 14080200
            ['code' => '14080200', 'name' => 'Gain On Swapped Assets - Investment Property', 'is_header' => true],
            ['code' => '14080201', 'name' => 'Gain On Swapped Assets - Investment Property', 'is_header' => false],
            
            // Gain On Swapped Assets - Intangible - 14080300
            ['code' => '14080300', 'name' => 'Gain On Swapped Assets - Intangible', 'is_header' => true],
            ['code' => '14080301', 'name' => 'Gain On Swapped Assets - Intangible', 'is_header' => false],
            
            // Gain On Swapped Assets - Inventory - 14080400
            ['code' => '14080400', 'name' => 'Gain On Swapped Assets - Inventory', 'is_header' => true],
            ['code' => '14080401', 'name' => 'Gain On Swapped Assets - Inventory', 'is_header' => false],
            
            // Gain On Swapped Services - 14090000
            ['code' => '14090000', 'name' => 'Gain On Swapped Services', 'is_header' => true],
            
            // Gain On Swapped Services - 14090100
            ['code' => '14090100', 'name' => 'Gain On Swapped Services', 'is_header' => true],
            ['code' => '14090101', 'name' => 'Gain On Swapped Services Rendered', 'is_header' => false],
            
            // Gain On Foreign Exchange - 14100000
            ['code' => '14100000', 'name' => 'Gain On Foreign Exchange', 'is_header' => true],
            
            // Gain On Foreign Exchange - 14100100
            ['code' => '14100100', 'name' => 'Gain On Foreign Exchange', 'is_header' => true],
            ['code' => '14100101', 'name' => 'Gain On Foreign Exchange', 'is_header' => false],
            ['code' => '14100196', 'name' => 'Cross Currency Rounding Account', 'is_header' => false],
            ['code' => '14100197', 'name' => 'Rounding', 'is_header' => false],
            ['code' => '14100198', 'name' => 'Realised Gain', 'is_header' => false],
            ['code' => '14100199', 'name' => 'PO Rate Variance Gain', 'is_header' => false],
            
            // Transfers - 15000000
            ['code' => '15000000', 'name' => 'Transfers', 'is_header' => true],
            
            // Transfer To Fund Recurrent Expenditure - 15010000
            ['code' => '15010000', 'name' => 'Transfer To Fund Recurrent Expenditure', 'is_header' => true],
            ['code' => '15010100', 'name' => 'Transfer To Fund Recurrent Expenditure', 'is_header' => false],
            ['code' => '15010101', 'name' => 'Receipt From CRF To Fund Mda Recurrent Expenditure', 'is_header' => false],
            ['code' => '15010102', 'name' => 'Receipt From Other Agency To Fund Recurrent Expenditure', 'is_header' => false],
            ['code' => '15010103', 'name' => 'Receipt Of CRF Revenue From PSEs', 'is_header' => false],
            
            // Expenditures - 20000000
            ['code' => '20000000', 'name' => 'Expenditures', 'is_header' => true],
            
            // Personnel Cost - 21000000
            ['code' => '21000000', 'name' => 'Personnel Cost', 'is_header' => true],
            
            // Salary - 21010000
            ['code' => '21010000', 'name' => 'Salary', 'is_header' => true],
            
            // Salaries And Wages - 21010100
            ['code' => '21010100', 'name' => 'Salaries And Wages', 'is_header' => true],
            ['code' => '21010101', 'name' => 'Salary', 'is_header' => false],
            ['code' => '21010102', 'name' => 'Over Time Payments', 'is_header' => false],
            ['code' => '21010103', 'name' => 'Consolidated Revenue Fund Charge- Salaries', 'is_header' => false],
            ['code' => '21010104', 'name' => 'Unclaimed Salary', 'is_header' => false],
            
            // Allowances And Social Contribution - 21020000
            ['code' => '21020000', 'name' => 'Allowances And Social Contribution', 'is_header' => true],
            
            // Allowances - 21020100
            ['code' => '21020100', 'name' => 'Allowances', 'is_header' => true],
            ['code' => '21020101', 'name' => 'Non Regular Allowances', 'is_header' => false],
            
            // Social Contributions - 21020200
            ['code' => '21020200', 'name' => 'Social Contributions', 'is_header' => true],
            ['code' => '21020201', 'name' => 'Nhis Contribution', 'is_header' => false],
            ['code' => '21020202', 'name' => 'Contributory Pension (Employers)', 'is_header' => false],
            ['code' => '21020203', 'name' => 'Group Life Insurance', 'is_header' => false],
            ['code' => '21020204', 'name' => 'Employees Compensation Fund', 'is_header' => false],
            ['code' => '21020205', 'name' => 'Housing Fund Contribution', 'is_header' => false],
            ['code' => '21020206', 'name' => 'Contribution to other Employees Schemes', 'is_header' => false],
            
            // Gratuity, Pension, Death Benefits - 21030100
            ['code' => '21030101', 'name' => 'Gratuity (CRFC)', 'is_header' => false],
            ['code' => '21030102', 'name' => 'Pension (CRFC)', 'is_header' => false],
            ['code' => '21030103', 'name' => 'Death Benefits (CRFC)', 'is_header' => false],
            ['code' => '21050189', 'name' => 'Discount allowed', 'is_header' => false],
            
            // Other Recurrent Costs - 22000000
            ['code' => '22000000', 'name' => 'Other Recurrent Costs', 'is_header' => true],
            
            // Social Benefits - 22010000
            ['code' => '22010000', 'name' => 'Social Benefits', 'is_header' => true],
            ['code' => '22010100', 'name' => 'Social Benefits', 'is_header' => false],
            ['code' => '22010101', 'name' => 'Gratuity', 'is_header' => false],
            ['code' => '22010102', 'name' => 'Pension', 'is_header' => false],
            ['code' => '22010103', 'name' => 'Death Benefits', 'is_header' => false],
            ['code' => '22010104', 'name' => '3months In Lieu Of Notice', 'is_header' => false],
            ['code' => '22010105', 'name' => 'Pension Overpayment', 'is_header' => false],
            ['code' => '22010106', 'name' => 'Gratuity Overpayment', 'is_header' => false],
            ['code' => '22010107', 'name' => 'Death Benefit Overpayment', 'is_header' => false],
            
            // Overhead Cost - 22020000
            ['code' => '22020000', 'name' => 'Overhead Cost', 'is_header' => true],
            
            // Travel & Transport - 22020100
            ['code' => '22020100', 'name' => 'Travel& Transport - General', 'is_header' => true],
            ['code' => '22020101', 'name' => 'Local Travel & Transport: Training', 'is_header' => false],
            ['code' => '22020102', 'name' => 'Local Travel & Transport: Others', 'is_header' => false],
            ['code' => '22020103', 'name' => 'International Travel & Transport: Training', 'is_header' => false],
            ['code' => '22020104', 'name' => 'International Travel & Transport: Others', 'is_header' => false],
            
            // Utilities - 22020200
            ['code' => '22020200', 'name' => 'Utilities - General', 'is_header' => true],
            ['code' => '22020201', 'name' => 'Electricity Charges', 'is_header' => false],
            ['code' => '22020202', 'name' => 'Telephone Charges', 'is_header' => false],
            ['code' => '22020203', 'name' => 'Internet Access Charges', 'is_header' => false],
            ['code' => '22020204', 'name' => 'Satellite Broadcasting Access Charges', 'is_header' => false],
            ['code' => '22020205', 'name' => 'Water Rates', 'is_header' => false],
            ['code' => '22020206', 'name' => 'Sewage Charges', 'is_header' => false],
            ['code' => '22020207', 'name' => 'Leased Communication Lines', 'is_header' => false],
            ['code' => '22020208', 'name' => 'Multi Year Tariff Order', 'is_header' => false],
            ['code' => '22020209', 'name' => 'Interactive Learning Network', 'is_header' => false],
            ['code' => '22020210', 'name' => 'Software Charges/ Licence Renewal', 'is_header' => false],
            
            // Materials & Supplies - 22020300
            ['code' => '22020300', 'name' => 'Materials & Supplies - General', 'is_header' => true],
            ['code' => '22020301', 'name' => 'Office Stationeries/Computer Consumables', 'is_header' => false],
            ['code' => '22020302', 'name' => 'Books', 'is_header' => false],
            ['code' => '22020303', 'name' => 'Newspapers', 'is_header' => false],
            ['code' => '22020304', 'name' => 'Magazines & Periodicals', 'is_header' => false],
            ['code' => '22020305', 'name' => 'Printing Of Non Security Documents', 'is_header' => false],
            ['code' => '22020306', 'name' => 'Printing Of Security Documents', 'is_header' => false],
            ['code' => '22020307', 'name' => 'Drugs/Laboratory/Medical Supplies', 'is_header' => false],
            ['code' => '22020308', 'name' => 'Field & Camping Materials Supplies', 'is_header' => false],
            ['code' => '22020309', 'name' => 'Uniforms & Other Clothing', 'is_header' => false],
            ['code' => '22020310', 'name' => 'Teaching Aids / Instruction Materials', 'is_header' => false],
            ['code' => '22020311', 'name' => 'Food Stuff / Catering Materials Supplies', 'is_header' => false],
            ['code' => '22020312', 'name' => 'Production, Publication And Circulation Of Annual Financial Statements', 'is_header' => false],
            ['code' => '22020313', 'name' => 'Production Of Reports To Public Accounts Committee (Pac)', 'is_header' => false],
            
            // Maintenance Services - 22020400
            ['code' => '22020400', 'name' => 'Maintenance Services - General', 'is_header' => true],
            ['code' => '22020401', 'name' => 'Maintenance Of Motor Vehicle/Transport Equipment', 'is_header' => false],
            ['code' => '22020402', 'name' => 'Maintenance Of Office Furniture', 'is_header' => false],
            ['code' => '22020403', 'name' => 'Maintenance Of Office Building / Residential Qtrs', 'is_header' => false],
            ['code' => '22020404', 'name' => 'Maintenance Of Office / It Equipments', 'is_header' => false],
            ['code' => '22020405', 'name' => 'Maintenance Of Plants/Generators', 'is_header' => false],
            ['code' => '22020406', 'name' => 'Other Maintenance Services', 'is_header' => false],
            ['code' => '22020407', 'name' => 'Maintenance Of Aircrafts', 'is_header' => false],
            ['code' => '22020408', 'name' => 'Maintenance Of Sea Boats', 'is_header' => false],
            ['code' => '22020409', 'name' => 'Maintenance Of Railway Equipment', 'is_header' => false],
            ['code' => '22020410', 'name' => 'Maintenance Of Street Lightings', 'is_header' => false],
            ['code' => '22020411', 'name' => 'Maintenance Of Communication Equipment', 'is_header' => false],
            ['code' => '22020412', 'name' => 'Maintenance Of Markets/Public Places', 'is_header' => false],
            ['code' => '22020413', 'name' => 'Minor Road Maintenance', 'is_header' => false],
            
            // Training - 22020500
            ['code' => '22020500', 'name' => 'Training - General', 'is_header' => true],
            ['code' => '22020501', 'name' => 'Local Training', 'is_header' => false],
            ['code' => '22020502', 'name' => 'International Training', 'is_header' => false],
            
            // Other Services - 22020600
            ['code' => '22020600', 'name' => 'Other Services - General', 'is_header' => true],
            ['code' => '22020601', 'name' => 'Security Services', 'is_header' => false],
            ['code' => '22020602', 'name' => 'Office Rent', 'is_header' => false],
            ['code' => '22020603', 'name' => 'Residential Rent', 'is_header' => false],
            ['code' => '22020604', 'name' => 'Security Vote (Including Operations)', 'is_header' => false],
            ['code' => '22020605', 'name' => 'Cleaning & Fumigation Services', 'is_header' => false],
            ['code' => '22020606', 'name' => 'Land Use Charges', 'is_header' => false],
            ['code' => '22020607', 'name' => 'Rescue Services', 'is_header' => false],
            ['code' => '22020608', 'name' => 'Tenement Rate', 'is_header' => false],
            
            // Consulting & Professional Services - 22020700
            ['code' => '22020700', 'name' => 'Consulting & Professional Services - General', 'is_header' => true],
            ['code' => '22020701', 'name' => 'Financial Consulting', 'is_header' => false],
            ['code' => '22020702', 'name' => 'Information Technology Consulting', 'is_header' => false],
            ['code' => '22020703', 'name' => 'Legal Services', 'is_header' => false],
            ['code' => '22020704', 'name' => 'Engineering Services', 'is_header' => false],
            ['code' => '22020705', 'name' => 'Architectural Services', 'is_header' => false],
            ['code' => '22020706', 'name' => 'Surveying Services', 'is_header' => false],
            ['code' => '22020707', 'name' => 'Agricultural Consulting', 'is_header' => false],
            ['code' => '22020708', 'name' => 'Medical Consulting', 'is_header' => false],
            ['code' => '22020709', 'name' => 'Auditing Of Accounts', 'is_header' => false],
            
            // Fuel & Lubricants - 22020800
            ['code' => '22020800', 'name' => 'Fuel & Lubricants - General', 'is_header' => true],
            ['code' => '22020801', 'name' => 'Motor Vehicle Fuel Cost', 'is_header' => false],
            ['code' => '22020802', 'name' => 'Other Transport Equipment Fuel Cost', 'is_header' => false],
            ['code' => '22020803', 'name' => 'Plant / Generator Fuel Cost', 'is_header' => false],
            ['code' => '22020804', 'name' => 'Aircraft Fuel Cost', 'is_header' => false],
            ['code' => '22020805', 'name' => 'Sea Boat Fuel Cost', 'is_header' => false],
            ['code' => '22020806', 'name' => 'Cooking Gas/Fuel Cost', 'is_header' => false],
            
            // Financial Charges - 22020900
            ['code' => '22020900', 'name' => 'Financial Charges - General', 'is_header' => true],
            ['code' => '22020901', 'name' => 'Bank Charges (Other Than Interest)', 'is_header' => false],
            ['code' => '22020902', 'name' => 'Insurance Premium', 'is_header' => false],
            ['code' => '22020904', 'name' => 'Other CRF Bank Charges (Garnishee Order)', 'is_header' => false],
            ['code' => '22020905', 'name' => 'Interest/Discount On Foreign Loan', 'is_header' => false],
            ['code' => '22020906', 'name' => 'Foreign Interest/Discount - Short Term Borrowings', 'is_header' => false],
            ['code' => '22020907', 'name' => 'Domestic Interest/Discount - Treasury Bill', 'is_header' => false],
            ['code' => '22020908', 'name' => 'Domestic Interest/Discount - Short Term Borrowings', 'is_header' => false],
            ['code' => '22020909', 'name' => 'Guaranteed Loans', 'is_header' => false],
            
            // Miscellaneous Expenses General - 22021000
            ['code' => '22021000', 'name' => 'Miscellaneous Expenses General', 'is_header' => true],
            ['code' => '22021001', 'name' => 'Refreshment & Meals', 'is_header' => false],
            ['code' => '22021002', 'name' => 'Honorarium & Sitting Allowance', 'is_header' => false],
            ['code' => '22021003', 'name' => 'Publicity & Advertisements', 'is_header' => false],
            ['code' => '22021004', 'name' => 'Medical Expenses-Local', 'is_header' => false],
            ['code' => '22021006', 'name' => 'Postages & Courier Services', 'is_header' => false],
            ['code' => '22021007', 'name' => 'Welfare Packages', 'is_header' => false],
            ['code' => '22021008', 'name' => 'Subscription To Professional Bodies', 'is_header' => false],
            ['code' => '22021009', 'name' => 'Sporting Activities', 'is_header' => false],
            ['code' => '22021010', 'name' => 'Direct Teaching & Laboratory Cost', 'is_header' => false],
            ['code' => '22021011', 'name' => 'Recruitment and Appointment (State Wide)', 'is_header' => false],
            ['code' => '22021012', 'name' => 'Discipline and Appointment (State Wide)', 'is_header' => false],
            ['code' => '22021013', 'name' => 'Promotion (State Wide)', 'is_header' => false],
            ['code' => '22021014', 'name' => 'Annual Budget Expenses & Administration', 'is_header' => false],
            ['code' => '22021015', 'name' => 'Convocation Expenses', 'is_header' => false],
            ['code' => '22021016', 'name' => 'Matriculation Expenses', 'is_header' => false],
            ['code' => '22021017', 'name' => 'Accreditation Expenses', 'is_header' => false],
            ['code' => '22021019', 'name' => 'Medical Expenses - International', 'is_header' => false],
            ['code' => '22021020', 'name' => 'Election-Logistics Support', 'is_header' => false],
            ['code' => '22021021', 'name' => 'Special Days/ Celebrations', 'is_header' => false],
            ['code' => '22021024', 'name' => 'Donation And Gift', 'is_header' => false],
            ['code' => '22021025', 'name' => 'General Expenses', 'is_header' => false],
            ['code' => '22021037', 'name' => 'Margin For Increase In Costs', 'is_header' => false],
            ['code' => '22021041', 'name' => 'Contingency', 'is_header' => false],
            ['code' => '22021042', 'name' => 'Recurrent Adjustment', 'is_header' => false],
            ['code' => '22021043', 'name' => 'Committee And Commission', 'is_header' => false],
            ['code' => '22021044', 'name' => 'Refund Of Over Head', 'is_header' => false],
            
            // Continue with the remaining sections (Grants, Subsidies, Debt, etc.)
            // For brevity, I'll include the main structure. You can expand similarly for all sections.
            // Continue adding this to the $data array in your EconomyCodesSeeder

            // Grants And Contributions General - 22040000
            ['code' => '22040000', 'name' => 'Grants And Contributions General', 'is_header' => true],

            // Local Grants And Contributions - 22040100
            ['code' => '22040100', 'name' => 'Local Grants And Contributions', 'is_header' => true],
            ['code' => '22040101', 'name' => 'Grant To Other State Governments - Current', 'is_header' => false],
            ['code' => '22040102', 'name' => 'Grant To Other State Governments - Capital', 'is_header' => false],
            ['code' => '22040103', 'name' => 'Grant To Local Governments -Current', 'is_header' => false],
            ['code' => '22040104', 'name' => 'Grant To Local Governments -Capital', 'is_header' => false],
            ['code' => '22040105', 'name' => 'Grants To Government Owned Companies - Current', 'is_header' => false],
            ['code' => '22040106', 'name' => 'Grant To Government Owned Companies - Capital', 'is_header' => false],
            ['code' => '22040107', 'name' => 'Grant To Private Companies - Current', 'is_header' => false],
            ['code' => '22040108', 'name' => 'Grant To Private Companies - Capital', 'is_header' => false],
            ['code' => '22040109', 'name' => 'Grants To Communities/NGOs', 'is_header' => false],
            ['code' => '22040110', 'name' => 'Grants To Academic Institutions', 'is_header' => false],
            ['code' => '22040111', 'name' => 'Contribution To Traditional Councils', 'is_header' => false],

            // Foreign Grants And Contributions - 22040200
            ['code' => '22040200', 'name' => 'Foreign Grants And Contributions', 'is_header' => true],
            ['code' => '22040203', 'name' => 'Contribution To International Organization', 'is_header' => false],
            ['code' => '22040204', 'name' => 'External Financial Obligations', 'is_header' => false],

            // Subsidies General - 22050000
            ['code' => '22050000', 'name' => 'Subsidies General', 'is_header' => true],

            // Subsidy To Public/Public Institutions - 22050100
            ['code' => '22050100', 'name' => 'Subsidy To Public/Public Institutions', 'is_header' => false],
            ['code' => '22050101', 'name' => 'Subsidy To Government Owned Companies', 'is_header' => false],
            ['code' => '22050102', 'name' => 'Meal Subsidy To Government Schools', 'is_header' => false],
            ['code' => '22050104', 'name' => 'Petroleum/Energy Subsidy', 'is_header' => false],
            ['code' => '22050105', 'name' => 'Education Subsidy', 'is_header' => false],
            ['code' => '22050106', 'name' => 'Agricultural Inputs Subsidy', 'is_header' => false],
            ['code' => '22050107', 'name' => 'Health Subsidy', 'is_header' => false],
            ['code' => '22050108', 'name' => 'Religious Pilgrimage Subsidy', 'is_header' => false],

            // Subsidy To Private Companies - 22050200
            ['code' => '22050200', 'name' => 'Subsidy To Private Companies', 'is_header' => true],
            ['code' => '22050201', 'name' => 'Subsidy To Private Companies', 'is_header' => false],

            // Public Debt Charges - 22060000
            ['code' => '22060000', 'name' => 'Public Debt Charges', 'is_header' => true],

            // External Loans Repayment - 22060100
            ['code' => '22060100', 'name' => 'External Loans Repayment', 'is_header' => true],
            ['code' => '22060101', 'name' => 'Foreign Interest/Discount - Short Term Borrowings', 'is_header' => false],
            ['code' => '22060102', 'name' => 'Servicing Of External Loans', 'is_header' => false],

            // Internal Loans Repayment - 22060200
            ['code' => '22060200', 'name' => 'Internal Loans Repayment', 'is_header' => true],
            ['code' => '22060201', 'name' => 'Servicing Of Bonds', 'is_header' => false],
            ['code' => '22060202', 'name' => 'Domestic Interest /Discount - Short Term Borrowings', 'is_header' => false],
            ['code' => '22060203', 'name' => 'Guranteed Loans', 'is_header' => false],
            ['code' => '22060204', 'name' => 'Others: Contractual Obligations', 'is_header' => false],
            ['code' => '22060205', 'name' => 'Bank Charges (Local)', 'is_header' => false],
            ['code' => '22060206', 'name' => 'Below The Line Accounts', 'is_header' => false],

            // Insurance Premium - 22060300
            ['code' => '22060300', 'name' => 'Insurance Premium', 'is_header' => true],
            ['code' => '22060301', 'name' => 'Interest - Internal Public Debt', 'is_header' => false],
            ['code' => '22060402', 'name' => 'Domestic Principal - Long Term Borrowings', 'is_header' => false],

            // Transfer To Other Fund - 22070000
            ['code' => '22070000', 'name' => 'Transfer To Other Fund', 'is_header' => true],

            // Transfer To Fund Recurrent Expenditure-Payment - 22070100
            ['code' => '22070100', 'name' => 'Transfer To Fund Recurrent Expenditure-Payment', 'is_header' => false],
            ['code' => '22070101', 'name' => 'Payment From CRF To Fund Mda Recurrent Expenditure', 'is_header' => false],
            ['code' => '22070102', 'name' => 'Payment To Other Agency To Fund Recurrent Expenditure', 'is_header' => false],
            ['code' => '22070103', 'name' => 'Payment Of Share Of State IGR To Local Governments', 'is_header' => false],
            ['code' => '22070104', 'name' => 'CRF Revenue Remittance By PSEs', 'is_header' => false],

            // Transfers-Payment To Individuals - 22080000
            ['code' => '22080000', 'name' => 'Transfers-Payment To Individuals', 'is_header' => true],

            // Transfers-Payment To Individuals - 22080100
            ['code' => '22080100', 'name' => 'Transfers-Payment To Individuals', 'is_header' => false],
            ['code' => '22080101', 'name' => 'Transfers-Payment To Unemployed', 'is_header' => false],
            ['code' => '22080102', 'name' => 'Transfers-Payment To Aged/Vulnerable Group', 'is_header' => false],

            // Loss On Foreign Exchange - 22090000
            ['code' => '22090000', 'name' => 'Loss On Foreign Exchange', 'is_header' => true],
            ['code' => '22090100', 'name' => 'Loss On Foreign Exchange', 'is_header' => false],
            ['code' => '22090101', 'name' => 'Loss On Foreign Exchange', 'is_header' => false],

            // Research And Development - Recurrent - 23000000
            ['code' => '23000000', 'name' => 'Research And Development - Recurrent', 'is_header' => true],
            ['code' => '23010105', 'name' => 'Consultancy & Professional Service General', 'is_header' => false],
            ['code' => '23010112', 'name' => 'Purchase Of Office Furniture', 'is_header' => false],
            ['code' => '23020118', 'name' => 'Special Intervention Fund', 'is_header' => false],

            // Research And Development - Recurrent (R&D) - 23050000
            ['code' => '23050000', 'name' => 'Research And Development - Recurrent (R&D)', 'is_header' => true],

            // Research And Development - Recurrent (R&D) - 23050100
            ['code' => '23050100', 'name' => 'Research And Development - Recurrent (R&D)', 'is_header' => true],
            ['code' => '23050101', 'name' => 'Research And Development - Recurrent (R&D)', 'is_header' => false],

            // Depreciation Charges - 24000000
            ['code' => '24000000', 'name' => 'Depreciation Charges', 'is_header' => true],

            // Depreciation Charges - PPE - 24010000
            ['code' => '24010000', 'name' => 'Depreciation Charges - PPE', 'is_header' => true],

            // Depreciation Charges - Land & Buildings - General - 24010100
            ['code' => '24010100', 'name' => 'Depreciaition Charges - Land & Buildings -General', 'is_header' => true],
            ['code' => '24010101', 'name' => 'Depreciation Charges - Land & Buildings - Office', 'is_header' => false],
            ['code' => '24010102', 'name' => 'Depreciation Charges - Land & Buildings - Residential', 'is_header' => false],
            ['code' => '24010103', 'name' => 'Depreciation Charges - Silos', 'is_header' => false],
            ['code' => '24010104', 'name' => 'Depreciation Charges - Other Storage Facilities', 'is_header' => false],

            // Depreciation Charges - Infrastructure - General - 24010200
            ['code' => '24010200', 'name' => 'Depreciation Charges - Infrastructure - General', 'is_header' => true],
            ['code' => '24010201', 'name' => 'Depreciation Charges - Rails', 'is_header' => false],
            ['code' => '24010202', 'name' => 'Depreciation Charges - Roads & Bridges', 'is_header' => false],
            ['code' => '24010203', 'name' => 'Depreciation Charges - Airports', 'is_header' => false],
            ['code' => '24010204', 'name' => 'Depreciation Charges - Harbours/ Sea Ports', 'is_header' => false],
            ['code' => '24010205', 'name' => 'Depreciation Charges - Zoos, Parks & Reserves', 'is_header' => false],
            ['code' => '24010206', 'name' => 'Depreciation Charges - Security Installations/ Equipment', 'is_header' => false],
            ['code' => '24010207', 'name' => 'Depreciation Charges - Electricity Transmission Network', 'is_header' => false],
            ['code' => '24010208', 'name' => 'Depreciation Charges - Water Distribution Network', 'is_header' => false],
            ['code' => '24010209', 'name' => 'Depreciation Charges - Sewage/ Drainage Network', 'is_header' => false],
            ['code' => '24010210', 'name' => 'Depreciation Charges - Dams', 'is_header' => false],
            ['code' => '24010211', 'name' => 'Depreciation Charges - Specialised Research Equipment (E.G. Satellite)', 'is_header' => false],
            ['code' => '24010212', 'name' => 'Depreciation Charges -Boreholes & Other Water Facilities', 'is_header' => false],
            ['code' => '24010213', 'name' => 'Depreciation Charges -Waste Disposal Equipments', 'is_header' => false],

            // Depreciation Charges - Plant & Machinery - General - 24010300
            ['code' => '24010300', 'name' => 'Depreciation Charges - Plant & Machinery - General', 'is_header' => true],
            ['code' => '24010301', 'name' => 'Depreciation Charges - Earth Moving Equipment - Bull Dozers Etc.', 'is_header' => false],
            ['code' => '24010302', 'name' => 'Depreciation Charges - Industrial Equipment', 'is_header' => false],
            ['code' => '24010303', 'name' => 'Depreciation Charges - Navigational Equipment', 'is_header' => false],
            ['code' => '24010304', 'name' => 'Depreciation Charges - Power Plants', 'is_header' => false],
            ['code' => '24010305', 'name' => 'Depreciation Charges - Power Generating Sets', 'is_header' => false],
            ['code' => '24010306', 'name' => 'Depreciation Charges - Broadcast & Communication Equipments', 'is_header' => false],

            // Depreciation Charges - Transportation Equipment - General - 24010400
            ['code' => '24010400', 'name' => 'Depreciation Charges - Transportation Equipment - General', 'is_header' => true],
            ['code' => '24010401', 'name' => 'Depreciation Charges - Ships', 'is_header' => false],
            ['code' => '24010402', 'name' => 'Depreciation Charges - Air Crafts', 'is_header' => false],
            ['code' => '24010403', 'name' => 'Depreciation Charges - Trains', 'is_header' => false],
            ['code' => '24010404', 'name' => 'Depreciation Charges - Sea Boats', 'is_header' => false],
            ['code' => '24010405', 'name' => 'Depreciation Charges - Motor Vehicles', 'is_header' => false],
            ['code' => '24010406', 'name' => 'Depreciation Charges - Tricycle', 'is_header' => false],
            ['code' => '24010407', 'name' => 'Depreciation Charges - Motor Cycles', 'is_header' => false],
            ['code' => '24010408', 'name' => 'Depreciation Charges - Bicycle', 'is_header' => false],

            // Depreciation Charges - Office Equipment - General - 24010500
            ['code' => '24010500', 'name' => 'Depreciation Charges - Office Equipment - General', 'is_header' => true],
            ['code' => '24010501', 'name' => 'Depreciation Charges - Computers', 'is_header' => false],
            ['code' => '24010502', 'name' => 'Depreciation Charges - Printers', 'is_header' => false],
            ['code' => '24010503', 'name' => 'Depreciation Charges - Scanners', 'is_header' => false],
            ['code' => '24010504', 'name' => 'Depreciation Charges - Fax Machine', 'is_header' => false],
            ['code' => '24010505', 'name' => 'Depreciation Charges - Photocopiers', 'is_header' => false],
            ['code' => '24010506', 'name' => 'Depreciation Charges - Type-Writers', 'is_header' => false],
            ['code' => '24010507', 'name' => 'Depreciation Charges - Shredding Machines', 'is_header' => false],
            ['code' => '24010508', 'name' => 'Depreciation Charges - Projectors', 'is_header' => false],
            ['code' => '24010509', 'name' => 'Depreciation Charges - Binding Equipment', 'is_header' => false],

            // Depreciation Charges - Furniture & Fittings - General - 24010600
            ['code' => '24010600', 'name' => 'Depreciation Charges - Furniture & Fittings - General', 'is_header' => true],
            ['code' => '24010601', 'name' => 'Depreciation Charges - Chairs', 'is_header' => false],
            ['code' => '24010602', 'name' => 'Depreciation Charges - Tables', 'is_header' => false],
            ['code' => '24010603', 'name' => 'Depreciation Charges - File Cabinets/ Cupboards', 'is_header' => false],
            ['code' => '24010604', 'name' => 'Depreciation Charges - Television Sets', 'is_header' => false],
            ['code' => '24010605', 'name' => 'Depreciation Charges - Radio Sets', 'is_header' => false],
            ['code' => '24010606', 'name' => 'Depreciation Charges - Air -Conditioner', 'is_header' => false],
            ['code' => '24010607', 'name' => 'Depreciation Charges - Stools', 'is_header' => false],
            ['code' => '24010608', 'name' => 'Depreciation Charges - Shelves', 'is_header' => false],
            ['code' => '24010609', 'name' => 'Depreciation Charges - Ceiling Fans', 'is_header' => false],
            ['code' => '24010610', 'name' => 'Depreciation Charges - Refrigerators', 'is_header' => false],

            // Depreciation Charges - Service Concession Assets - 24010700
            ['code' => '24010700', 'name' => 'Depreciation Charges - Service Concession Assets', 'is_header' => true],
            ['code' => '24010701', 'name' => 'Depreciation Charges - Service Concession Assets (PPE)', 'is_header' => false],

            // Depreciation Charges - Leased Assets-Finance Lease - 24010800
            ['code' => '24010800', 'name' => 'Depreciation Charges - Leased Assets-Finance Lease', 'is_header' => true],
            ['code' => '24010801', 'name' => 'Leased Assets', 'is_header' => false],

            // Depreciation Charges - Specialised Assets - 24010900
            ['code' => '24010900', 'name' => 'Depreciation Charges - Specialised Assets', 'is_header' => true],
            ['code' => '24010901', 'name' => 'Depreciation Charges - Military Equipments', 'is_header' => false],
            ['code' => '24010902', 'name' => 'Depreciation Charges - Police/Para-Military Equipments', 'is_header' => false],
            ['code' => '24010904', 'name' => 'Depreciation Charges - Laboratory/Medical Equipments', 'is_header' => false],

            // Depreciation Charges - Investment Property - 24011000
            ['code' => '24011000', 'name' => 'Depreciation Charges - Investment Property', 'is_header' => true],
            ['code' => '24011001', 'name' => 'Depreciation Charges - Service Concession Assets (PPE)', 'is_header' => false],

            // Depreciation Charges - Investment Property - 24020000
            ['code' => '24020000', 'name' => 'Depreciation Charges - Investment Property', 'is_header' => true],

            // Depreciation Charges - Investment - Land & Building - General - 24020100
            ['code' => '24020100', 'name' => 'Depreciation Charges - Investment - Land & Building - General', 'is_header' => true],
            ['code' => '24020101', 'name' => 'Depreciation Charges - Investment - Land & Buildings - Office', 'is_header' => false],
            ['code' => '24020102', 'name' => 'Depreciation Charges - Investment - Land & Buildings - Residential', 'is_header' => false],
            ['code' => '24020103', 'name' => 'Depreciation Charges - Investment - Silos', 'is_header' => false],
            ['code' => '24020104', 'name' => 'Depreciation Charges - Investment - Storage Facilities', 'is_header' => false],

            // Depreciation Charges - Investment - Infrastructure - General - 24020200
            ['code' => '24020200', 'name' => 'Depreciation Charges - Investment - Infrastructure - General', 'is_header' => true],
            ['code' => '24020202', 'name' => 'Depreciation Charges - Investment - Roads & Bridges', 'is_header' => false],
            ['code' => '24020205', 'name' => 'Depreciation Charges - Investment - Zoos, Parks & Reserves', 'is_header' => false],
            ['code' => '24020206', 'name' => 'Depreciation Charges - Investment - Security Installations/ Equipment', 'is_header' => false],
            ['code' => '24020207', 'name' => 'Depreciation Charges -Investment - Electricity Transmission Network', 'is_header' => false],
            ['code' => '24020208', 'name' => 'Depreciation Charges - Investment - Water Distribution Network', 'is_header' => false],
            ['code' => '24020209', 'name' => 'Depreciation Charges - Investment - Sewage/ Drainage Network', 'is_header' => false],
            ['code' => '24020210', 'name' => 'Depreciation Charges - Investment - Dams', 'is_header' => false],
            ['code' => '24020211', 'name' => 'Depreciation Charges - Investment - Specialised Research Equipment (E.G. Satellite)', 'is_header' => false],

            // Depreciation Charges - Investment -Plant & Machinery - General - 24020300
            ['code' => '24020300', 'name' => 'Depreciation Charges - Investment -Plant & Machinery - General', 'is_header' => true],
            ['code' => '24020301', 'name' => 'Depreciation Charges - Investment - Earth Moving Equipment', 'is_header' => false],
            ['code' => '24020302', 'name' => 'Depreciation Charges - Investment - Industrial Equipment', 'is_header' => false],
            ['code' => '24020304', 'name' => 'Depreciation Charges - Investment - Power Plants', 'is_header' => false],
            ['code' => '24020305', 'name' => 'Depreciation Charges - Investment - Power Generating Sets', 'is_header' => false],

            // Depreciation Charges - Investment - Transport Equipment - General - 24020400
            ['code' => '24020400', 'name' => 'Depreciation Charges - Investment - Transport Equipment - General', 'is_header' => true],
            ['code' => '24020405', 'name' => 'Depreciation Charges - Investment - Motor Vehicles', 'is_header' => false],

            // Depreciation Charges - Investment - Office Equipment - General - 24020500
            ['code' => '24020500', 'name' => 'Depreciation Charges - Investment - Office Equipment - General', 'is_header' => true],
            ['code' => '24020501', 'name' => 'Depreciation Charges - Investment - Computers', 'is_header' => false],
            ['code' => '24020502', 'name' => 'Depreciation Charges - Investment - Printers', 'is_header' => false],
            ['code' => '24020503', 'name' => 'Depreciation Charges - Investment - Scanners', 'is_header' => false],
            ['code' => '24020504', 'name' => 'Depreciation Charges - Investment - Fax Machine', 'is_header' => false],
            ['code' => '24020505', 'name' => 'Depreciation Charges - Investment - Photocopiers', 'is_header' => false],
            ['code' => '24020506', 'name' => 'Depreciation Charges - Investment - Type-Writers', 'is_header' => false],
            ['code' => '24020507', 'name' => 'Depreciation Charges - Investment - Shredding Machines', 'is_header' => false],
            ['code' => '24020508', 'name' => 'Depreciation Charges - Investment - Television Sets', 'is_header' => false],
            ['code' => '24020509', 'name' => 'Depreciation Charges - Investment - Radio Sets', 'is_header' => false],
            ['code' => '24020510', 'name' => 'Depreciation Charges - Investment - Air-Conditoner', 'is_header' => false],
            ['code' => '24020511', 'name' => 'Depreciation Charges - Investment - Projectors', 'is_header' => false],
            ['code' => '24020512', 'name' => 'Depreciation Charges - Investment - Binging Equipment', 'is_header' => false],

            // Depreciation Charges - Investment - Furnitures & Fitting - General - 24020600
            ['code' => '24020600', 'name' => 'Depreciation Charges - Investment - Furnitures & Fitting - General', 'is_header' => true],
            ['code' => '24020601', 'name' => 'Depreciation Charges - Investment - Chairs', 'is_header' => false],
            ['code' => '24020602', 'name' => 'Depreciation Charges - Investment - Tables', 'is_header' => false],
            ['code' => '24020603', 'name' => 'Depreciation Charges - Investment - File Cabinets/Cupboards', 'is_header' => false],
            ['code' => '24020604', 'name' => 'Depreciation Charges - Investment - Stools', 'is_header' => false],
            ['code' => '24020605', 'name' => 'Depreciation Charges - Investment - Shelves', 'is_header' => false],
            ['code' => '24020606', 'name' => 'Depreciation Charges - Investment - Ceiling Fans', 'is_header' => false],

            // Impairment Charges - 25000000
            ['code' => '25000000', 'name' => 'Impairment Charges', 'is_header' => true],

            // Impairment Charges- PPE - 25010000
            ['code' => '25010000', 'name' => 'Impairment Charges- PPE', 'is_header' => true],

            // Impairment Charges - Land & Buildings -General - 25010100
            ['code' => '25010100', 'name' => 'Impairment Charges - Land & Buildings -General', 'is_header' => true],
            ['code' => '25010101', 'name' => 'Impairment Charges - Land & Buildings - Office', 'is_header' => false],
            ['code' => '25010102', 'name' => 'Impairment Charges - Land & Buildings - Residential', 'is_header' => false],
            ['code' => '25010103', 'name' => 'Impairment Chrages - Silos', 'is_header' => false],
            ['code' => '25010104', 'name' => 'Impairment Charges - Other Storage Facilities', 'is_header' => false],

            // Impairment Charges - Infrastructure - General - 25010200
            ['code' => '25010200', 'name' => 'Impairment Charges - Infrastructure - General', 'is_header' => true],
            ['code' => '25010201', 'name' => 'Impairment Charges - Rails', 'is_header' => false],
            ['code' => '25010202', 'name' => 'Impairment Charges - Roads & Bridges', 'is_header' => false],
            ['code' => '25010203', 'name' => 'Impairment Charges - Airports', 'is_header' => false],
            ['code' => '25010204', 'name' => 'Impairment Charges - Harbours/ Sea Ports', 'is_header' => false],
            ['code' => '25010205', 'name' => 'Impairment Charges - Zoos, Parks & Reserves', 'is_header' => false],
            ['code' => '25010206', 'name' => 'Impairment Charges - Security Installations/ Equipment', 'is_header' => false],
            ['code' => '25010207', 'name' => 'Impairment Charges - Electricity Transmission Network', 'is_header' => false],
            ['code' => '25010208', 'name' => 'Impairment Charges - Water Distribution Network', 'is_header' => false],
            ['code' => '25010209', 'name' => 'Impairment Charges - Sewage/ Drainage Network', 'is_header' => false],
            ['code' => '25010210', 'name' => 'Impairment Charges - Dams', 'is_header' => false],
            ['code' => '25010211', 'name' => 'Impairment Charges - Specialised Research Equipment (E.G. Satellite)', 'is_header' => false],
            ['code' => '25010212', 'name' => 'Impairment Charges -Boreholes & Other Water Facilities', 'is_header' => false],
            ['code' => '25010213', 'name' => 'Impairment Charges -Waste Disposal Equipments', 'is_header' => false],

            // Impairment Charges - Plant & Machinery - General - 25010300
            ['code' => '25010300', 'name' => 'Impairment Charges - Plant & Machinery - General', 'is_header' => true],
            ['code' => '25010301', 'name' => 'Impairment Charges - Earth Moving Equipment - Bull Dozers Etc.', 'is_header' => false],
            ['code' => '25010302', 'name' => 'Impairment Charges - Industrial Equipment', 'is_header' => false],
            ['code' => '25010303', 'name' => 'Impairment Charges - Navigational Equipment', 'is_header' => false],
            ['code' => '25010304', 'name' => 'Impairment Charges - Power Plants', 'is_header' => false],
            ['code' => '25010305', 'name' => 'Impairment Charges - Power Generating Sets', 'is_header' => false],

            // Impairment Charges - Transportation Equipment - General - 25010400
            ['code' => '25010400', 'name' => 'Impairment Charges - Transportation Equipment - General', 'is_header' => true],
            ['code' => '25010401', 'name' => 'Impairment Charges - Ships', 'is_header' => false],
            ['code' => '25010402', 'name' => 'Impairment Charges - Air Crafts', 'is_header' => false],
            ['code' => '25010403', 'name' => 'Impairment Charges - Trains', 'is_header' => false],
            ['code' => '25010404', 'name' => 'Impairment Charges - Sea Boats', 'is_header' => false],
            ['code' => '25010405', 'name' => 'Impairment Charges - Motor Vehicles', 'is_header' => false],
            ['code' => '25010406', 'name' => 'Impairment Charges - Tricycle', 'is_header' => false],
            ['code' => '25010407', 'name' => 'Impairment Charges - Motor Cycles', 'is_header' => false],
            ['code' => '25010408', 'name' => 'Impairment Charges - Bicycle', 'is_header' => false],

            // Impairment Charges - Office Equipment - General - 25010500
            ['code' => '25010500', 'name' => 'Impairment Charges - Office Equipment - General', 'is_header' => true],
            ['code' => '25010501', 'name' => 'Impairment Charges - Computers', 'is_header' => false],
            ['code' => '25010502', 'name' => 'Impairment Charges - Printers', 'is_header' => false],
            ['code' => '25010503', 'name' => 'Impairment Charges - Scanners', 'is_header' => false],
            ['code' => '25010504', 'name' => 'Impairment Charges - Fax Machine', 'is_header' => false],
            ['code' => '25010505', 'name' => 'Impairment Charges - Photocopiers', 'is_header' => false],
            ['code' => '25010506', 'name' => 'Impairment Charges - Type-Writers', 'is_header' => false],
            ['code' => '25010507', 'name' => 'Impairment Charges - Shredding Machines', 'is_header' => false],
            ['code' => '25010508', 'name' => 'Impairment Charges - Television Sets', 'is_header' => false],
            ['code' => '25010509', 'name' => 'Impairment Charges - Radio Sets', 'is_header' => false],
            ['code' => '25010510', 'name' => 'Impairment Charges - Air -Conditioner', 'is_header' => false],
            ['code' => '25010511', 'name' => 'Impairment Charges - Projectors', 'is_header' => false],
            ['code' => '25010512', 'name' => 'Impairment Charges - Binding Equipment', 'is_header' => false],

            // Impairment Charges - Furniture & Fittings - General - 25010600
            ['code' => '25010600', 'name' => 'Impairment Charges - Furniture & Fittings - General', 'is_header' => true],
            ['code' => '25010601', 'name' => 'Impairment Charges - Chairs', 'is_header' => false],
            ['code' => '25010602', 'name' => 'Impairment Charges - Tables', 'is_header' => false],
            ['code' => '25010603', 'name' => 'Impairment Charges - File Cabinets/ Cupboards', 'is_header' => false],
            ['code' => '25010604', 'name' => 'Impairment Charges - Stools', 'is_header' => false],
            ['code' => '25010605', 'name' => 'Impairment Charges - Shelves', 'is_header' => false],
            ['code' => '25010606', 'name' => 'Impairment Charges - Ceiling Fans', 'is_header' => false],
            ['code' => '25010610', 'name' => 'Impairment Charges - Refrigerators', 'is_header' => false],

            // Impairment Charges - Investment Property - 25020000
            ['code' => '25020000', 'name' => 'Impairment Charges - Investment Property', 'is_header' => false],

            // Impairment Charges - Investment - Land & Building - General - 25020100
            ['code' => '25020100', 'name' => 'Impairment Charges - Investment - Land & Building - General', 'is_header' => true],
            ['code' => '25020101', 'name' => 'Impairment Charges - Investment - Land & Buildings - Office', 'is_header' => false],
            ['code' => '25020102', 'name' => 'Impairment Charges - Investment - Land & Buildings - Residential', 'is_header' => false],
            ['code' => '25020103', 'name' => 'Impairment Charges - Investment - Silos', 'is_header' => false],
            ['code' => '25020104', 'name' => 'Impairment Charges - Investment Property - Other Storage Facilities', 'is_header' => false],

            // Impairment Charges - Intangible Assets - 25030000
            ['code' => '25030000', 'name' => 'Impairment Charges - Intangible Assets', 'is_header' => true],

            // Impairment Charges - Intangible Assets - 25030100
            ['code' => '25030100', 'name' => 'Impairment Charges - Intangible Assets', 'is_header' => false],
            ['code' => '25030101', 'name' => 'Impairment Charges - Goodwill (Acquired)', 'is_header' => false],
            ['code' => '25030102', 'name' => 'Impairment Charges - Patent Right', 'is_header' => false],
            ['code' => '25030103', 'name' => 'Impairment Charges - Copyright', 'is_header' => false],
            ['code' => '25030104', 'name' => 'Impairment Charges - Trade Mark', 'is_header' => false],
            ['code' => '25030105', 'name' => 'Impairment Charges - Franchise', 'is_header' => false],
            ['code' => '25030106', 'name' => 'Impairment Charges - R&D', 'is_header' => false],
            ['code' => '25030107', 'name' => 'Impairmentcharges - Broadcast Rights', 'is_header' => false],

            // Ammortization Charges - Intangible Assets - 26000000
            ['code' => '26000000', 'name' => 'Ammortization Charges - Intangible Assets', 'is_header' => true],

            // Ammortization Charges - Intangible Assets - 26010000
            ['code' => '26010000', 'name' => 'Ammortization Charges - Intangible Assets', 'is_header' => true],

            // Ammortization Charges - Intangible Assets - 26010100
            ['code' => '26010100', 'name' => 'Ammortization Charges - Intangible Assets', 'is_header' => true],
            ['code' => '26010101', 'name' => 'Ammortization Charges - Goodwill', 'is_header' => false],
            ['code' => '26010102', 'name' => 'Ammortization Charges - Patent Right', 'is_header' => false],
            ['code' => '26010103', 'name' => 'Ammortization Charges - Copyright', 'is_header' => false],
            ['code' => '26010104', 'name' => 'Ammortization Charges - Trade Mark', 'is_header' => false],
            ['code' => '26010105', 'name' => 'Ammortization Charges - Franchise', 'is_header' => false],
            ['code' => '26010106', 'name' => 'Amortization Charges - R&D', 'is_header' => false],
            ['code' => '26010107', 'name' => 'Amortization Charges - Broadcast Rights', 'is_header' => false],

            // Bad Debts Charges - 27000000
            ['code' => '27000000', 'name' => 'Bad Debts Charges', 'is_header' => true],

            // Bad Debts Charges - 27010000
            ['code' => '27010000', 'name' => 'Bad Debts Charges', 'is_header' => true],

            // Foreign Bad Debts Charges - 27010100
            ['code' => '27010100', 'name' => 'Foreign Bad Debts Charges', 'is_header' => true],
            ['code' => '27010101', 'name' => 'Bilateral Bad Debts Charges', 'is_header' => false],

            // Domestic Bad Debts Charges - 27010200
            ['code' => '27010200', 'name' => 'Domestic Bad Debts Charges', 'is_header' => true],
            ['code' => '27010201', 'name' => 'Bad Debts Charges - States', 'is_header' => false],
            ['code' => '27010202', 'name' => 'Bad Debts Charges - Local Governments', 'is_header' => false],
            ['code' => '27010203', 'name' => 'Bad Debts Charges - Ministries, Departments & Agencies', 'is_header' => false],
            ['code' => '27010204', 'name' => 'Bad Debts Charges - Commercial Debts', 'is_header' => false],
            ['code' => '27010205', 'name' => 'Bad Debts Charges - Other Debts', 'is_header' => false],

            // Loss On Disposal - 28000000
            ['code' => '28000000', 'name' => 'Loss On Disposal', 'is_header' => true],

            // Loss On Disposal Of Asset - 28010000
            ['code' => '28010000', 'name' => 'Loss On Disposal Of Asset', 'is_header' => true],

            // Loss On Disposal Of Asset - PPE - 28010100
            ['code' => '28010100', 'name' => 'Loss On Disposal Of Asset - PPE', 'is_header' => true],
            ['code' => '28010101', 'name' => 'Loss On Disposal Of Asset - PPE', 'is_header' => false],

            // Loss On Disposal Of Asset - Investment Property - 28010200
            ['code' => '28010200', 'name' => 'Loss On Disposal Of Asset - Investment Property', 'is_header' => true],
            ['code' => '28010201', 'name' => 'Loss On Disposal Of Asset - Investment Property', 'is_header' => false],

            // Loss On Disposal Of Asset - Intangible - 28010300
            ['code' => '28010300', 'name' => 'Loss On Disposal Of Asset - Intangible', 'is_header' => true],
            ['code' => '28010301', 'name' => 'Loss On Disposal Of Asset - Intangible', 'is_header' => false],

            // Loss On Swapped Asset - 28010400
            ['code' => '28010400', 'name' => 'Loss On Swapped Asset', 'is_header' => true],
            ['code' => '28010401', 'name' => 'Loss On Swapped Asset', 'is_header' => false],

            // Loss On Swapped Services - 28010500
            ['code' => '28010500', 'name' => 'Loss On Swapped Services', 'is_header' => true],
            ['code' => '28010501', 'name' => 'Loss On Swapped Services', 'is_header' => false],

            // Expense Account and related items
            ['code' => '29010001', 'name' => 'Expense Account', 'is_header' => false],
            ['code' => '29010002', 'name' => 'Cost of Good sold', 'is_header' => false],
            ['code' => '29010003', 'name' => 'Purchase Price Variance', 'is_header' => false],
            ['code' => '29010004', 'name' => 'Invoice Price Variance', 'is_header' => false],
            ['code' => '29010005', 'name' => 'Encumbrance', 'is_header' => false],
            ['code' => '29010006', 'name' => 'Cost VarianceAccount', 'is_header' => false],

            // Cash/Bank Balances Held By AG - 31010000
        ['code' => '31010000', 'name' => 'Cash/Bank Balances Held By AG', 'is_header' => true],

        // Consolidated Revenue Fund - 31010100
        ['code' => '31010100', 'name' => 'Consolidated Revenue Fund', 'is_header' => true],
        ['code' => '31010101', 'name' => 'Cash Balance: Consolidated Revenue Fund', 'is_header' => false],
        ['code' => '31010105', 'name' => 'Remmitances', 'is_header' => false],
        ['code' => '31010108', 'name' => 'Cash At Close', 'is_header' => false],
        ['code' => '31010109', 'name' => 'Advance To Lgcs(Roll Out Technology)', 'is_header' => false],
        ['code' => '31010110', 'name' => 'Advances on FGN Bailout to LGCs', 'is_header' => false],
        ['code' => '31010111', 'name' => 'Loan Repayment bank Clearing Account', 'is_header' => false],

        // Capital Development Fund - 31010200
        ['code' => '31010200', 'name' => 'Capital Development Fund', 'is_header' => true],
        ['code' => '31010201', 'name' => 'Cash Balance: Capital Development Fund', 'is_header' => false],

        // Contigency Fund - 31010300
        ['code' => '31010300', 'name' => 'Contigency Fund', 'is_header' => true],
        ['code' => '31010301', 'name' => 'Cash Balance: Contigency Fund', 'is_header' => false],

        // Tsa-Account Cash Balance - 31010500
        ['code' => '31010500', 'name' => 'Tsa-Account Cash Balance', 'is_header' => true],

        // ACCESS BANK - 31010600
        ['code' => '31010600', 'name' => 'ACCESS BANK', 'is_header' => true],
        ['code' => '31010601', 'name' => 'Access Bank Plc_0003470284_IGR', 'is_header' => false],
        ['code' => '31010602', 'name' => 'Access Bank Plc_0005984271_OVERHEAD A/C', 'is_header' => false],
        ['code' => '31010603', 'name' => 'Access Bank Plc_0006658616_IGR/Pay Tech.', 'is_header' => false],
        ['code' => '31010604', 'name' => 'Access Bank Plc_0006663034_IGR', 'is_header' => false],
        ['code' => '31010605', 'name' => 'Access Bank Plc_0022059783_IGR', 'is_header' => false],
        ['code' => '31010606', 'name' => 'Access Bank Plc_0022079017_Spec Paymt', 'is_header' => false],
        ['code' => '31010607', 'name' => 'Access Bank Plc_0022080705_EDSG Oil & Gas (Escrow A/C)', 'is_header' => false],
        ['code' => '31010608', 'name' => 'Access Bank Plc_0022058896_Driver Lic', 'is_header' => false],
        ['code' => '31010609', 'name' => 'Access Bank Plc_0689398892_GPA', 'is_header' => false],
        ['code' => '31010610', 'name' => 'Access Bank Plc_0692810790_Consolidation', 'is_header' => false],
        ['code' => '31010611', 'name' => 'Access Bank Plc_0694584761_Settlemt A/C', 'is_header' => false],
        ['code' => '31010612', 'name' => 'Access Bank Plc_0696383827_TCOI (Treas. Oper)', 'is_header' => false],
        ['code' => '31010613', 'name' => 'Access Bank Plc_0696164965_SRA', 'is_header' => false],
        ['code' => '31010614', 'name' => 'Access Bank Plc_0696164989_VAT A/C', 'is_header' => false],
        ['code' => '31010615', 'name' => 'Access Bank Plc_0694588484_Loan Contr Fin', 'is_header' => false],
        ['code' => '31010616', 'name' => 'Access Bank Plc_0694572337_Loan Contr Fin', 'is_header' => false],
        ['code' => '31010617', 'name' => 'Access Bank Plc_0694588491_Loan Contr Fin', 'is_header' => false],
        ['code' => '31010618', 'name' => 'Access Bank Plc_0694591585_Loan Contr Fin', 'is_header' => false],
        ['code' => '31010619', 'name' => 'Access Bank Plc_0694595868_Loan Contr Fin', 'is_header' => false],
        ['code' => '31010620', 'name' => 'Access Bank Plc_0694565939_Loan Contr Fin', 'is_header' => false],
        ['code' => '31010621', 'name' => 'Access Bank Plc_0697484340_ TCOI(Sal Asst)', 'is_header' => false],
        ['code' => '31010622', 'name' => 'Access Bank Plc_0694584747_Tax A/C', 'is_header' => false],
        ['code' => '31010623', 'name' => 'Access Bank Plc_0699650502_Capital/Proj Loan', 'is_header' => false],
        ['code' => '31010624', 'name' => 'Access Bank_0703039587_EDSG DPOII', 'is_header' => false],
        ['code' => '31010625', 'name' => 'Access Bank_0703039893_Naira/ Current A/C DPOII', 'is_header' => false],
        ['code' => '31010626', 'name' => 'Access Bank_0710027623_FSP Budget Support', 'is_header' => false],
        ['code' => '31010627', 'name' => 'Access Bank_0718573863_Paris & London Club', 'is_header' => false],
        ['code' => '31010628', 'name' => 'Access Bank_0718573865_Term Deposit', 'is_header' => false],
        ['code' => '31010629', 'name' => 'Access Bank_0058150449_Edo State JAAC', 'is_header' => false],
        ['code' => '31010630', 'name' => 'Access Bank Plc__Term Deposit', 'is_header' => false],
        ['code' => '31010631', 'name' => 'Access Bank Plc_0725365848_IGR/Seed', 'is_header' => false],
        ['code' => '31010632', 'name' => 'Access Bank Plc_0725385110_EDSG Contributory Pension', 'is_header' => false],
        ['code' => '31010633', 'name' => 'Access Bank Plc_0739607129_CALL A/C', 'is_header' => false],
        ['code' => '31010634', 'name' => 'Access Bank Plc_0764294888_TCO Pension Arrears&Gratuity', 'is_header' => false],
        ['code' => '31010635', 'name' => 'Access Bank Plc_0775751235_Payment to HOS', 'is_header' => false],
        ['code' => '31010636', 'name' => 'Access Bank Plc_0775750300_Payment to GHP', 'is_header' => false],
        ['code' => '31010637', 'name' => 'Access Bank Plc_0796511720_NDDC/EDSOPADEC', 'is_header' => false],
        ['code' => '31010638', 'name' => 'Access Bank Plc_0796470928_Fed Govt Refund on Road', 'is_header' => false],
        ['code' => '31010639', 'name' => 'Access Bank Plc_0766035348_Health Intervention Account', 'is_header' => false],
        ['code' => '31010640', 'name' => 'Access Bank Plc_0802123295_Education Intervention Fund', 'is_header' => false],
        ['code' => '31010641', 'name' => 'Access Bank Plc_0768071634_Accelerated Infrastructure', 'is_header' => false],
        ['code' => '31010642', 'name' => 'Access Bank Plc_0775736009_Monthly Security', 'is_header' => false],
        ['code' => '31010643', 'name' => 'Access Bank Plc_0775751194_Payment to SSG', 'is_header' => false],
        ['code' => '31010644', 'name' => 'Access Bank Plc_0775750386_Payment to Dep Governor', 'is_header' => false],
        ['code' => '31010645', 'name' => 'Access Bank Plc_0775750142_Payment to MDAs', 'is_header' => false],
        ['code' => '31010646', 'name' => 'Access Bank Plc_0777515446_Edo GIS', 'is_header' => false],
        ['code' => '31010647', 'name' => 'Access Bank Plc_0697494395_EDSG/CBN LG Salary Assistance Acct', 'is_header' => false],
        ['code' => '31010648', 'name' => 'Access Bank Plc_0697494340_EDSG/CBN LG Salary Assistance Acct', 'is_header' => false],
        ['code' => '31010649', 'name' => 'Access Bank Plc_0022066923_SUBEB', 'is_header' => false],
        ['code' => '31010650', 'name' => 'Access Bank Plc_0802122295_Education_intervention_fund', 'is_header' => false],
        ['code' => '31010651', 'name' => 'Access Bank Plc_0800177270_Teachers_Professional_Dev_Training', 'is_header' => false],
        ['code' => '31010652', 'name' => 'Access Bank Plc_0022052698_EDSG_MISCELLANEOUS', 'is_header' => false],
        ['code' => '31010653', 'name' => 'Access Bank Plc_1384587550_EDSG_COVID-19 RELIEF FUND', 'is_header' => false],
        ['code' => '31010654', 'name' => 'Access Bank Plc_1386839057_1% INTERVENTION_FUND_FOR_SECURITY_OPERATIONS', 'is_header' => false],
        ['code' => '31010655', 'name' => 'Access Bank Plc_1379993920_SPECIAL RECEIPT AND PAYMENT', 'is_header' => false],
        ['code' => '31010656', 'name' => 'Access Bank Plc_**********_MISCELLANEOUS TERM DEPOSIT', 'is_header' => false],
        ['code' => '31010657', 'name' => 'Access Bank Plc_**********_CONTRACT_FINANCING', 'is_header' => false],
        ['code' => '31010658', 'name' => 'Access Bank Plc_1437188619_VEHICLE_NUMBER_PLATE_TRADING', 'is_header' => false],
        ['code' => '31010659', 'name' => 'Access Bank Plc_**********_OVERHEAD_ACCOUNT TERM DEPOSIT', 'is_header' => false],
        ['code' => '31010660', 'name' => 'Access Bank Plc_**********_GPA/CAPITAL_EXPENDITURE_TERM', 'is_header' => false],
        ['code' => '31010661', 'name' => 'Access Bank Plc_1898336215_EDSG_PAYMENTS_TO_EDHA', 'is_header' => false],
        ['code' => '31010662', 'name' => 'Access Bank Plc_1898342551_EDSG_PAYMENTS_TO_EDO_STATE_JUDICIARY', 'is_header' => false],

        // AFRIBANK PLC - 31010700
        ['code' => '31010700', 'name' => 'AFRIBANK PLC', 'is_header' => true],
        ['code' => '31010701', 'name' => 'Afribank Plc._36701004_EDSG (Rent)', 'is_header' => false],

        // ALL STATES T. BK PLC - 31010800
        ['code' => '31010800', 'name' => 'ALL STATES T. BK PLC', 'is_header' => true],
        ['code' => '31010801', 'name' => 'Allstates T. BK. Plc._0211201000462_Special Reserve', 'is_header' => false],
        ['code' => '31010802', 'name' => 'Allstates T. BK. Plc._2505000228_Transp. Proj.', 'is_header' => false],
        ['code' => '31010803', 'name' => 'Allstates T. BK. Plc._2505000237_VAT', 'is_header' => false],
        ['code' => '31010804', 'name' => 'Allstates T. BK. Plc._2505000262_Tower of Peace', 'is_header' => false],
        ['code' => '31010805', 'name' => 'Allstates T. BK. Plc._2505000273_EDSG D. L.', 'is_header' => false],
        ['code' => '31010806', 'name' => 'Allstates T. BK. Plc._2505000291_Loan Facility', 'is_header' => false],
        ['code' => '31010807', 'name' => 'Allstates T. BK. Plc._2505000620_IGR', 'is_header' => false],
        ['code' => '31010808', 'name' => 'Allstates T. BK. Plc._2505100249_Spec. Pymt.', 'is_header' => false],
        ['code' => '31010809', 'name' => 'Allstates T. BK. Plc._2505100276_M.M. Lottery', 'is_header' => false],
        ['code' => '31010810', 'name' => 'Allstates T. BK. Plc._2505100294_SSG (GH)', 'is_header' => false],

        // DIAMOND BANK - 31010900
        ['code' => '31010900', 'name' => 'DIAMOND BANK', 'is_header' => true],
        ['code' => '31010901', 'name' => 'Diamond Bank Plc_0018962689_Misc. A/C', 'is_header' => false],
        ['code' => '31010902', 'name' => 'Diamond/Access Bank Plc_0025859505_IGR', 'is_header' => false],
        ['code' => '31010903', 'name' => 'Diamond Bank Plc_0046476332_Land Use Ch', 'is_header' => false],
        ['code' => '31010904', 'name' => 'Diamond Bank Plc._0005137366_IGR', 'is_header' => false],

        // ECOBANK - 31011000
        ['code' => '31011000', 'name' => 'ECOBANK', 'is_header' => true],
        ['code' => '31011001', 'name' => 'Ecobank Plc_0282006285_IGR', 'is_header' => false],
        ['code' => '31011002', 'name' => 'Ecobank Plc_0005137366/ 2442036374_IGR', 'is_header' => false],
        ['code' => '31011003', 'name' => 'Ecobank Plc_2902001533_GPA/TCO', 'is_header' => false],
        ['code' => '31011004', 'name' => 'Ecobank Plc_2902027759_Iyekogba H', 'is_header' => false],
        ['code' => '31011005', 'name' => 'Ecobank Plc_2442058860_Escrow A/C', 'is_header' => false],
        ['code' => '31011006', 'name' => 'Ecobank Plc_4812047043_Overhead', 'is_header' => false],
        ['code' => '31011007', 'name' => 'Ecobank Plc_0283005247_TCO', 'is_header' => false],
        ['code' => '31011008', 'name' => 'Ecobank Plc_0282019379_IGR Interswit', 'is_header' => false],
        ['code' => '31011009', 'name' => 'Ecobank Plc_5540012451_Vehicle_Number_Plate_Trading', 'is_header' => false],
        ['code' => '31011010', 'name' => 'Ecobank Plc_2900034524_Edsg_Deposit_Special_Revenue', 'is_header' => false],

        // EQUITORIAL T. BANK - 31011100
        ['code' => '31011100', 'name' => 'EQUITORIAL T. BANK', 'is_header' => true],
        ['code' => '31011101', 'name' => 'Equitorial T. Bank Plc._0360004884911_Sinking Fund III', 'is_header' => false],
        ['code' => '31011102', 'name' => 'Equitorial T. Bank Plc._0360004884929_Sinking Fund II', 'is_header' => false],
        ['code' => '31011103', 'name' => 'Equitorial T. Bank Plc._0360020046418_Special A/C', 'is_header' => false],
        ['code' => '31011104', 'name' => 'Equitorial T. Bank Plc._0530004884929_Sinking Fund', 'is_header' => false],

        // FCM BANK PLC - 31011200
        ['code' => '31011200', 'name' => 'FCM BANK PLC', 'is_header' => true],
        ['code' => '31011201', 'name' => 'FCM Bank Plc._0432954019_IGR', 'is_header' => false],
        ['code' => '31011202', 'name' => 'FCM Bank Plc._0544047010_GPA', 'is_header' => false],
        ['code' => '31011203', 'name' => 'FCM Bank Plc._0596578012_Infrastructual Dev.', 'is_header' => false],
        ['code' => '31011204', 'name' => 'FCM Bank Plc._0132746035_Edo Rev Levy Bond', 'is_header' => false],
        ['code' => '31011205', 'name' => 'FCM Bank Plc._124386301_TCOI', 'is_header' => false],
        ['code' => '31011206', 'name' => 'FCM Bank Plc._2381157015_Pol. Reform Prog.', 'is_header' => false],
        ['code' => '31011207', 'name' => 'FCMB Plc__Term Deposit', 'is_header' => false],
        ['code' => '31011208', 'name' => 'FCMB Plc__Term Deposit Investment', 'is_header' => false],
        ['code' => '31011209', 'name' => 'FCMB_PLC_EDSG_BENIN_CENTRAL_PARK_SINKING', 'is_header' => false],

        // FIDELITY BANK PLC - 31011300
        ['code' => '31011300', 'name' => 'FIDELITY BANK PLC', 'is_header' => true],
        ['code' => '31011301', 'name' => 'Fidelity Bank Plc._0530017247_IGR', 'is_header' => false],
        ['code' => '31011302', 'name' => 'Fidelity Bank Plc._5030017292_EDSG proceeds', 'is_header' => false],
        ['code' => '31011303', 'name' => 'Fidelity Bank Plc._5030017302_Edo H/R, Abuja', 'is_header' => false],
        ['code' => '31011304', 'name' => 'Fidelity Bank Plc._5030017340_(GPA)', 'is_header' => false],
        ['code' => '31011305', 'name' => 'Fidelity Bank Plc._5030017357_(Tax Deduction)', 'is_header' => false],
        ['code' => '31011306', 'name' => 'Fidelity Bank Plc._5030036914_Flood Disaster', 'is_header' => false],
        ['code' => '31011307', 'name' => 'Fidelity Bank Plc._5030117099_Basic_Education_Cert_Exam', 'is_header' => false],
        ['code' => '31011308', 'name' => 'Fidelity Bank Plc._5030118694_Edsg_Primary_School_Cert_Exam', 'is_header' => false],
        ['code' => '31011309', 'name' => 'Fidelity Bank Plc._5030121582_Edsg_Retirement_Benefits_Bond_Redemption', 'is_header' => false],


        // FIRST BANK - 31011400
        ['code' => '31011400', 'name' => 'FIRST BANK', 'is_header' => true],
        ['code' => '31011401', 'name' => 'First Bank Nig. Plc._2009596784_Domiciliary Account for Income from NPDC', 'is_header' => false],
        ['code' => '31011402', 'name' => 'First Bank Nig. Plc._2006459257_IGR', 'is_header' => false],
        ['code' => '31011403', 'name' => 'First Bank Nig. Plc._2013649795_IGR/Pay Tech.', 'is_header' => false],
        ['code' => '31011404', 'name' => 'First Bank Nig. Plc._2014572234_Proj. Escrow', 'is_header' => false],
        ['code' => '31011405', 'name' => 'First Bank Nig. Plc._2014572241_Loan Repymt.', 'is_header' => false],
        ['code' => '31011406', 'name' => 'First Bank Nig. Plc._2020695822_Subsidy Reinvest', 'is_header' => false],
        ['code' => '31011407', 'name' => 'First Bank Nig. Plc._2022141000_TCO1', 'is_header' => false],
        ['code' => '31011408', 'name' => 'First Bank Nig. Plc._2021180712_SRA', 'is_header' => false],
        ['code' => '31011409', 'name' => 'First Bank Nig. Plc._2022141103_Spec. Paymt', 'is_header' => false],
        ['code' => '31011410', 'name' => 'First Bank Nig. Plc._2021181025_VAT A/C', 'is_header' => false],
        ['code' => '31011411', 'name' => 'First Bank Nig. Plc._2021180853_Excess Crude', 'is_header' => false],
        ['code' => '31011412', 'name' => 'First Bank Nig. Plc._2010268438_IGR Intersw', 'is_header' => false],
        ['code' => '31011413', 'name' => 'First Bank Nig. Plc._2020058126_DPO', 'is_header' => false],
        ['code' => '31011414', 'name' => 'First Bank Nig. Plc._2020058377_DPO', 'is_header' => false],
        ['code' => '31011415', 'name' => 'First Bank Nig. Plc._2024868833_Land Use Ch', 'is_header' => false],
        ['code' => '31011416', 'name' => 'First Bank Nig. Plc._2019480882_General Payment/TCO', 'is_header' => false],
        ['code' => '31011417', 'name' => 'First Bank Nig. Plc._1102900729321_IRO NPDC', 'is_header' => false],
        ['code' => '31011418', 'name' => 'First Bank Nig. Plc._2038392911_EDSG_ECOLOGICAL_FUNDS', 'is_header' => false],
        ['code' => '31011419', 'name' => 'First Bank Nig. Plc._2040649472_CAPITAL_EXPENDITURE', 'is_header' => false],
        ['code' => '31011420', 'name' => 'First Bank Nig. Plc._2040649551_IGR_CONSOLIDATION', 'is_header' => false],
        ['code' => '31011421', 'name' => 'First Bank Nig. Plc._2041055542_EDSG_SETTLEMENT_A/C', 'is_header' => false],
        ['code' => '31011422', 'name' => 'First Bank Nig. Plc._**********_CONTRACTORS_FINANCING_FACILITY', 'is_header' => false],
        ['code' => '31011423', 'name' => 'First Bank Nig. Plc._2041055384_EDSG/FBN BRIDGING FINANCE FACILITY', 'is_header' => false],
        ['code' => '31011424', 'name' => 'First Bank Nig. Plc._2041521452_EDSG BENIN CENTRAL PARK TRANSPORT', 'is_header' => false],
        ['code' => '31011425', 'name' => 'First Bank Nig. Plc._2041521610_EDSG BENIN CENTRAL PARK MEMORANDUM', 'is_header' => false],
        ['code' => '31011426', 'name' => 'First Bank Nig. Plc._2041521562_EDSG BENIN CENTRAL PARK COMMERCIAL ACCOUNT', 'is_header' => false],
        ['code' => '31011427', 'name' => 'First Bank Nig. Plc._2042175216_EDSG ULTRA MODERN MALL RENTAL PROCEEDS ACCOUNT', 'is_header' => false],
        ['code' => '31011428', 'name' => 'First Bank Nig. Plc._2042175285_EDSG ULTRA MODERN MALL SERVICE CHARGE ACCOUNT', 'is_header' => false],
        ['code' => '31011429', 'name' => 'First Bank Nig. Plc._2038608263_SRA', 'is_header' => false],
        ['code' => '31011430', 'name' => 'First Bank Nig. Plc._2038608421_VAT', 'is_header' => false],
        ['code' => '31011431', 'name' => 'First Bank Nig. Plc._2043858967_EDSG_BG_SINKING_FUND', 'is_header' => false],

        // GUARANTY TRUST BANK - 31011500
        ['code' => '31011500', 'name' => 'GUARANTY TRUST BANK', 'is_header' => true],
        ['code' => '31011501', 'name' => 'Guaranty Trust BK _0031636471_C of O', 'is_header' => false],
        ['code' => '31011502', 'name' => 'Guaranty Trust BK _0031880645_IGR', 'is_header' => false],
        ['code' => '31011503', 'name' => 'Guaranty Trust BK _0031636464_Gen. Pur/TCO', 'is_header' => false],
        ['code' => '31011504', 'name' => 'Guaranty Trust BK _0051755293_EGIS', 'is_header' => false],
        ['code' => '31011505', 'name' => 'Guaranty T. Bank Plc._4124004644112_GP/TCO1   A/C 2', 'is_header' => false],

        // HERITAGE/ENTERP BANK - 31011600
        ['code' => '31011600', 'name' => 'HERITAGE/ENTERP BANK', 'is_header' => true],
        ['code' => '31011601', 'name' => 'Heritage/Enterp Bk_1400014044_IGR', 'is_header' => false],
        ['code' => '31011602', 'name' => 'Heritage/Enterp Bk_6001488882_Overhead', 'is_header' => false],
        ['code' => '31011603', 'name' => 'Heritage/Enterp Bk_5003631053/  1400031205_Overhead', 'is_header' => false],
        ['code' => '31011604', 'name' => 'Heritage/Enterp Bk_6001488806_Overhead', 'is_header' => false],

        // INTERCONTINENTAL T. BANK - 31011700
        ['code' => '31011700', 'name' => 'INTERCONTINENTAL T. BANK', 'is_header' => true],
        ['code' => '31011701', 'name' => 'Intercontinental T. B. _0034-192638-002_Statutory', 'is_header' => false],
        ['code' => '31011702', 'name' => 'Intercontinental T. B. _034001000008279_TCO I', 'is_header' => false],

        // IVIE COMMUNITY BANK - 31011800
        ['code' => '31011800', 'name' => 'IVIE COMMUNITY BANK', 'is_header' => true],
        ['code' => '31011801', 'name' => 'Ivie Community Bk._200101102628_Grassroot Dev.', 'is_header' => false],

        // KEYSTONE BANK - 31011900
        ['code' => '31011900', 'name' => 'KEYSTONE BANK', 'is_header' => true],
        ['code' => '31011901', 'name' => 'Keystone Bk_1002818635_IGR', 'is_header' => false],
        ['code' => '31011902', 'name' => 'Keystone Bk_1001173256_Overhead', 'is_header' => false],
        ['code' => '31011903', 'name' => 'Keystone Bk_10021882807_GPA', 'is_header' => false],
        ['code' => '31011904', 'name' => 'Keystone Bank_1000699920_EDSG Pay-direct', 'is_header' => false],
        ['code' => '31011905', 'name' => 'Keystone Bank_1005912149_Settlement', 'is_header' => false],

        // MAINSTREET BANK - 31012000
        ['code' => '31012000', 'name' => 'MAINSTREET BANK', 'is_header' => true],
        ['code' => '31012001', 'name' => 'Mainstreet Bank_1751081477_School Fees', 'is_header' => false],
        ['code' => '31012002', 'name' => 'Mainstreet Bank_1465413259/  7000010265_IGR', 'is_header' => false],
        ['code' => '31012003', 'name' => 'Mainstreet Bank_2276942149614_TCO1', 'is_header' => false],

        // SAVANNAH BANK - 31012100
        ['code' => '31012100', 'name' => 'SAVANNAH BANK', 'is_header' => true],
        ['code' => '31012101', 'name' => 'Savannah Bk. (Distr.)_1911023821_Des/Sp. Rev.', 'is_header' => false],
        ['code' => '31012102', 'name' => 'Savannah Bk. (Distr.)_1911023838_Uncl. Salaries', 'is_header' => false],

        // SKYE BANK PLC - 31012200
        ['code' => '31012200', 'name' => 'SKYE BANK PLC', 'is_header' => true],
        ['code' => '31012201', 'name' => 'Skye Bank Plc_11770005403_TCO1', 'is_header' => false],
        ['code' => '31012202', 'name' => 'Skye Bank Plc_1770411453_GPA', 'is_header' => false],
        ['code' => '31012203', 'name' => 'Skye Bank Plc_1790115353_IGR', 'is_header' => false],
        ['code' => '31012204', 'name' => 'Skye Bank Plc_1770412223_SRA', 'is_header' => false],
        ['code' => '31012205', 'name' => 'Skye Bank Plc_1770412230_VAT', 'is_header' => false],
        ['code' => '31012206', 'name' => 'Skye Bank Plc_1770412247_Excess Cr. Oil', 'is_header' => false],
        ['code' => '31012207', 'name' => 'Skye Bank Plc_1770413244_Proj Esc', 'is_header' => false],
        ['code' => '31012208', 'name' => 'Skye Bank Plc_1790115360_IGR/ Pay Tech', 'is_header' => false],
        ['code' => '31012209', 'name' => 'Skye Bank Plc_1790094342_IGR/Interswitch', 'is_header' => false],
        ['code' => '31012210', 'name' => 'Skye Bank Plc_1750002756_Spec. Payment.', 'is_header' => false],
        ['code' => '31012211', 'name' => 'Skye Bank Plc_4030007984_Land Use Ch', 'is_header' => false],
        ['code' => '31012212', 'name' => 'Skye Bank Plc_1790134996_IGR/ Remmitance A/C', 'is_header' => false],
        ['code' => '31012213', 'name' => 'Skye Bank Plc_4030015664_Sp. Rev. A/C', 'is_header' => false],
        ['code' => '31012214', 'name' => 'Skye Bank Plc_4030020312_Receipt and Payment A/C', 'is_header' => false],
        ['code' => '31012215', 'name' => 'Skye Bank Plc_4060017872_Igr', 'is_header' => false],

        // STANBIC IBTC - 31012300
        ['code' => '31012300', 'name' => 'STANBIC IBTC', 'is_header' => true],
        ['code' => '31012301', 'name' => 'Stanbic IBTC_9201804270_Escrow', 'is_header' => false],
        ['code' => '31012302', 'name' => 'Stanbic IBTC_9202433231_GPA', 'is_header' => false],
        ['code' => '31012303', 'name' => 'Stanbic IBTC_0000567027_IGR', 'is_header' => false],
        ['code' => '31012304', 'name' => 'Stanbic IBTC_0005949392_I.G.R', 'is_header' => false],
        ['code' => '31012305', 'name' => 'Stanbic IBTC_9201800485_IGR/Pay-Direct', 'is_header' => false],

        // STERLING BANK - 31012400
        ['code' => '31012400', 'name' => 'STERLING BANK', 'is_header' => true],
        ['code' => '31012401', 'name' => 'Sterling Bank_0014031660_IGR', 'is_header' => false],
        ['code' => '31012402', 'name' => 'Sterling Bank_0014086275_Motor Cycle Ioan', 'is_header' => false],
        ['code' => '31012403', 'name' => 'Sterling Bank_0014015437_GPA', 'is_header' => false],
        ['code' => '31012404', 'name' => 'Sterling Bank_0013934740_Escrow', 'is_header' => false],
        ['code' => '31012405', 'name' => 'Sterling Bank_0014045108_EDSG A/C', 'is_header' => false],
        ['code' => '31012406', 'name' => 'Sterling Bank_0006954243_IGR', 'is_header' => false],
        ['code' => '31012407', 'name' => 'Sterling Bk Plc_0023982793_IGR/Pay-Direct', 'is_header' => false],
        ['code' => '31012408', 'name' => 'Sterling Bk Plc_0029504355_IGR EDSG', 'is_header' => false],
        ['code' => '31012409', 'name' => 'Sterling Bk Plc_0062962350_IGR/Consolid', 'is_header' => false],
        ['code' => '31012410', 'name' => 'Sterling Bk Plc_0063757256_Capital Expenditure', 'is_header' => false],
        ['code' => '31012411', 'name' => 'Sterling Bk Plc_0063869243_EDSG (Agric Proj Adv. A/C', 'is_header' => false],
        ['code' => '31012412', 'name' => 'Sterling Bk Plc_0072563053_2nd Federal Govt Refund on Project', 'is_header' => false],
        ['code' => '31012413', 'name' => 'Sterling Bk Plc_0067029713_CDS/Sinking Fund Account', 'is_header' => false],
        ['code' => '31012414', 'name' => 'Sterling Bk Plc_0073415537_Investment Income Account', 'is_header' => false],
        ['code' => '31012415', 'name' => 'Sterling Bk Plc_0076824554_Covid 19 Relief', 'is_header' => false],
        ['code' => '31012416', 'name' => 'Sterling Bk Plc_0065407304_Commercial Agric Credit Scheme', 'is_header' => false],
        ['code' => '31012417', 'name' => 'Sterling Bk Plc_0076504960_SPECIAL_RECEIPT_AND_PAYMENT', 'is_header' => false],
        ['code' => '31012418', 'name' => 'Sterling Bk Plc_**********_EDSG_BOND_PROCEEDS', 'is_header' => false],
        ['code' => '31012419', 'name' => 'Sterling Bk Plc_0076859794_COVID_19_RELIEF_FUND_DOLLAR_ACC', 'is_header' => false],
        ['code' => '31012420', 'name' => 'Sterling Bk Plc_0086596940_EDSG_EDSOPADEC_ENAGEED_RESOURCE_LTD_ESCROW_ACC', 'is_header' => false],
        ['code' => '31012421', 'name' => 'Sterling Bk Plc_0085416638_EDOGIS', 'is_header' => false],
        ['code' => '31012422', 'name' => 'Sterling Bk Plc_0087608594_EDSG_REVENUE CONSOLIDATION DOLLAR A/C', 'is_header' => false],
        ['code' => '31012423', 'name' => 'Sterling Bk Plc_0087608934_REVENUE_CONSOLIDATION_DRAWDOWN_NAIRA A/C', 'is_header' => false],
        ['code' => '31012424', 'name' => 'Sterling Bk Plc_0087315010_EDSG_NG_CARES_SPECIAL_RECEIPT & PAYMENT_ACCOUNT', 'is_header' => false],
        ['code' => '31012425', 'name' => 'Sterling Bk Plc_0092013349_EDSG_SOCIAL_INTERVENTION_FUND', 'is_header' => false],
        ['code' => '31012426', 'name' => 'Sterling Bk Plc_0086902329_EDSG/ASR_AFRICA_PROJECT', 'is_header' => false],
        ['code' => '31012427', 'name' => 'Sterling Bk Plc_0092552833_EDSG_Local_Government_Council_Payment_Account', 'is_header' => false],
        ['code' => '31012428', 'name' => 'Sterling Bk Plc_0093268641_EDSG_Deposit_Special_Revenue_Account', 'is_header' => false],
        ['code' => '31012429', 'name' => 'Sterling Bk Plc_0093918647_Ministry_of _Finance_Incoporated', 'is_header' => false],
        ['code' => '31012430', 'name' => 'Sterling Bk Plc_0098062978_Deposit/Special Revenue_Account', 'is_header' => false],
        ['code' => '31012431', 'name' => 'Sterling Bk Plc_0095145410_New_Town_Development_Authority', 'is_header' => false],

        // UBA PLC - 31012500
        ['code' => '31012500', 'name' => 'UBA PLC', 'is_header' => true],
        ['code' => '31012501', 'name' => 'UBA Plc_1003640482_M.V Loan Ref', 'is_header' => false],
        ['code' => '31012502', 'name' => 'UBA Plc_1004132993_IGR', 'is_header' => false],
        ['code' => '31012503', 'name' => 'UBA Plc_1004120150_IGR', 'is_header' => false],
        ['code' => '31012504', 'name' => 'UBA Plc_1004053410_Excess Crude', 'is_header' => false],
        ['code' => '31012505', 'name' => 'UBA Plc_1003730017_IGR/EXP', 'is_header' => false],
        ['code' => '31012506', 'name' => 'UBA Plc_1012055709_Dep/Spec Rev.', 'is_header' => false],
        ['code' => '31012507', 'name' => 'UBA Plc_1013246977_Edo H/R-Lagos', 'is_header' => false],
        ['code' => '31012508', 'name' => 'UBA Plc_1013288601_Unclaimed Sal', 'is_header' => false],
        ['code' => '31012509', 'name' => 'UBA Plc, B/C_1013759530_Iguosa H.Est.', 'is_header' => false],
        ['code' => '31012510', 'name' => 'UBA Plc, B/C_1014370732_Proj Escrow', 'is_header' => false],
        ['code' => '31012511', 'name' => 'UBA Plc, B/C_1014370725_Loan Repaymt', 'is_header' => false],
        ['code' => '31012512', 'name' => 'UBA Plc, B/C_1014811873_GPA', 'is_header' => false],
        ['code' => '31012513', 'name' => 'UBA Plc, B/C_1011845455_Consolidation', 'is_header' => false],
        ['code' => '31012514', 'name' => 'UBA Plc, B/C_1013945153_IGR/PayTech', 'is_header' => false],
        ['code' => '31012515', 'name' => 'UBA Plc, B/C_1017348044_Flood Relief', 'is_header' => false],
        ['code' => '31012516', 'name' => 'UBA Plc, B/C_1017207637_TCOI', 'is_header' => false],
        ['code' => '31012517', 'name' => 'UBA Plc, B/C_1018133861_Land Use Ch', 'is_header' => false],
        ['code' => '31012518', 'name' => 'UBA Plc, B/C_1001021218_Misc A/C', 'is_header' => false],
        ['code' => '31012519', 'name' => 'UBA Plc, B/C_1010250531_IGR', 'is_header' => false],
        ['code' => '31012520', 'name' => 'UBA Plc.     _0040000414_TCO I', 'is_header' => false],
        ['code' => '31012521', 'name' => 'UBA Plc.     _2033378216_Pen. & Gratuity', 'is_header' => false],
        ['code' => '31012522', 'name' => 'UBA Plc, B/C_1022751950_Covid 19 Support', 'is_header' => false],
        ['code' => '31012523', 'name' => 'UBA Plc, B/C_1027788845_Capital Expenditure', 'is_header' => false],
        ['code' => '31012524', 'name' => 'UBA Plc, B/C_1027687584_Vat', 'is_header' => false],

        // UNION BANK PLC - 31012600
        ['code' => '31012600', 'name' => 'UNION BANK PLC', 'is_header' => true],
        ['code' => '31012601', 'name' => 'Union Bank Plc_0015027022_AIGR', 'is_header' => false],
        ['code' => '31012602', 'name' => 'Union Bank Plc_0014888372_IGR 11', 'is_header' => false],
        ['code' => '31012603', 'name' => 'Union Bank Plc_0014765769_Forestry', 'is_header' => false],
        ['code' => '31012604', 'name' => 'Union Bank Plc_0010847166_VAT $  TAX', 'is_header' => false],
        ['code' => '31012605', 'name' => 'Union Bank Plc_0035087860_IGR', 'is_header' => false],
        ['code' => '31012606', 'name' => 'Union Bank Plc_0035015410_G.P.A', 'is_header' => false],
        ['code' => '31012607', 'name' => 'Union Bank Plc_0042138359_IGR', 'is_header' => false],
        ['code' => '31012608', 'name' => 'Union Bank Plc_0065630140_IGR', 'is_header' => false],

        // UNITY BANK PLC - 31012700
        ['code' => '31012700', 'name' => 'UNITY BANK PLC', 'is_header' => true],
        ['code' => '31012701', 'name' => 'Unity Bank Plc_0012188606_M/V No Plate', 'is_header' => false],
        ['code' => '31012702', 'name' => 'Unity Bank Plc_0021673836_IGR', 'is_header' => false],
        ['code' => '31012703', 'name' => 'Unity Bank Plc_0024705354_G.P.A', 'is_header' => false],
        ['code' => '31012704', 'name' => 'Unity Bank Plc_0012352221_IGR', 'is_header' => false],
        ['code' => '31012705', 'name' => 'Unity Bank Plc_0003576597_Movable Term Deposit', 'is_header' => false],
        ['code' => '31012706', 'name' => 'Unity Bank Plc_0003892293_Movable Term Deposit', 'is_header' => false],
        ['code' => '31012707', 'name' => 'Unity Bank Plc_0026061007_IGR', 'is_header' => false],
        ['code' => '31012708', 'name' => 'Unity Bank Plc_0017995261_IGR/Interswit', 'is_header' => false],

        // WEMA BANK PLC - 31012800
        ['code' => '31012800', 'name' => 'WEMA BANK PLC', 'is_header' => true],
        ['code' => '31012801', 'name' => 'WEMA Bank Plc_0122146651_IGR', 'is_header' => false],
        ['code' => '31012802', 'name' => 'WEMA Bank Plc_0122307656_GPA', 'is_header' => false],

        // ZENITH BANK PLC - 31012900
        ['code' => '31012900', 'name' => 'ZENITH BANK PLC', 'is_header' => true],
        ['code' => '31012901', 'name' => 'Zenith Bank Plc_1010501196_Fertilizer A/C', 'is_header' => false],
        ['code' => '31012902', 'name' => 'Zenith Bank Plc_1011304404_Gen Purp A/C', 'is_header' => false],
        ['code' => '31012903', 'name' => 'Zenith Bank Plc_1130004993_IGR', 'is_header' => false],
        ['code' => '31012904', 'name' => 'Zenith Bank Plc_1010994738_GPA', 'is_header' => false],
        ['code' => '31012905', 'name' => 'Zenith Bank Plc_1011866072_C of O', 'is_header' => false],
        ['code' => '31012906', 'name' => 'Zenith Bank Plc_1012019318_EDPA A/C', 'is_header' => false],
        ['code' => '31012907', 'name' => 'Zenith Bank Plc_1012017211_EDSG INVESTMENT INCOME', 'is_header' => false],
        ['code' => '31012908', 'name' => 'Zenith Bank Plc_1012045809_Inf Dev Levies', 'is_header' => false],
        ['code' => '31012909', 'name' => 'Zenith Bank Plc_1012656997_Misc', 'is_header' => false],
        ['code' => '31012910', 'name' => 'Zenith Bank Plc_1012840633_Vehicle N.P', 'is_header' => false],
        ['code' => '31012911', 'name' => 'Zenith Bank Plc_1012840640_National Dr', 'is_header' => false],
        ['code' => '31012912', 'name' => 'Zenith Bank Plc_1013692640_ECTS', 'is_header' => false],
        ['code' => '31012913', 'name' => 'Zenith Bank Plc_1013851823_Land Use Ch', 'is_header' => false],
        ['code' => '31012914', 'name' => 'Zenith Bank Plc_1013885860_Drugs & Rel Rev', 'is_header' => false],
        ['code' => '31012915', 'name' => 'Zenith Bank Plc_1012590842_Payment A/C', 'is_header' => false],
        ['code' => '31012916', 'name' => 'Zenith Bank Plc_1014282002_Manual IGR', 'is_header' => false],
        ['code' => '31012917', 'name' => 'Zenith Bank Plc__', 'is_header' => false],
        ['code' => '31012918', 'name' => 'Zenith Bank Plc_1012840633_Veh. No Plate (Garnishee)', 'is_header' => false],
        ['code' => '31012919', 'name' => 'Zenith Bank Plc_1012897833_Edo St Security Fund', 'is_header' => false],
        ['code' => '31012920', 'name' => 'Zenith Bank Plc_1130004993_IGR/Interswitch (Garnishee)', 'is_header' => false],
        ['code' => '31012921', 'name' => 'Zenith Bank Plc_1016440141_10% Contribution Pension Scheme', 'is_header' => false],
        ['code' => '31012922', 'name' => 'Zenith Bank Plc_Interswitch_Garnishee', 'is_header' => false],
        ['code' => '31012923', 'name' => 'Zenith Bank Plc_Manual_Garnishee', 'is_header' => false],
        ['code' => '31012924', 'name' => 'Zenith Bank Plc_1222307601_EDSG_Investment_Proceeds', 'is_header' => false],
        ['code' => '31012925', 'name' => 'Zenith Bank Plc_1228566185_IGR', 'is_header' => false],
        ['code' => '31012926', 'name' => 'Zenith Bank Plc_1012017211_Investment_income', 'is_header' => false],
        ['code' => '31012927', 'name' => 'Zenith Bank Plc_1228696309_EDPHCDA_Revenue', 'is_header' => false],

        // Globus Bank Ltd - 31013000
        ['code' => '31013000', 'name' => 'Globus Bank Ltd', 'is_header' => true],
        ['code' => '31013001', 'name' => 'Globus Bank Ltd', 'is_header' => false],
        ['code' => '31013002', 'name' => 'Globus Bank Ltd_1000065507_IGR', 'is_header' => false],
        ['code' => '31013003', 'name' => 'Globus Bank PLC_1000193875_EDPHCDA_Revenue', 'is_header' => false],

        // Standard Chartered Bank - 31013050
        ['code' => '31013050', 'name' => 'Standard Chartered Bank', 'is_header' => true],
        ['code' => '31013051', 'name' => 'Standard Chartered Bank_0005454406_Paydirect Account', 'is_header' => false],

        // Premium Trust Bank - 31013100
        ['code' => '31013100', 'name' => 'Premium Trust Bank', 'is_header' => true],
        ['code' => '31013101', 'name' => 'Premium Trust Bank_0080050216_IGR_Consolidation_ Account', 'is_header' => false],

        // Parallex_Bank_Ltd - 31013150
        ['code' => '31013150', 'name' => 'Parallex_Bank_Ltd', 'is_header' => true],
        ['code' => '31013151', 'name' => 'Parallex_Bank_Ltd_1000167587_IGR', 'is_header' => false],

        // Cash And Bank Balances Held By Main Treasury - 31020000
        ['code' => '31020000', 'name' => 'Cash And Bank Balances Held By Main Treasury', 'is_header' => true],

        // Cash And Bank Balances Held By Mdas/Sub-Treasuries - 31020100
        ['code' => '31020100', 'name' => 'Cash And Bank Balances Held By Mdas/Sub-Treasuries', 'is_header' => true],
        ['code' => '31020101', 'name' => 'Cash Balance: Capital', 'is_header' => false],
        ['code' => '31020102', 'name' => 'Cash Balance: Personnel', 'is_header' => false],
        ['code' => '31020103', 'name' => 'Cash Balance: Overhead', 'is_header' => false],
        ['code' => '31020104', 'name' => 'Cash Balance: Revenue', 'is_header' => false],
        ['code' => '31020106', 'name' => 'Cash Balance: Aids & Grants', 'is_header' => false],
        ['code' => '31020107', 'name' => 'Cash Balance: Loans', 'is_header' => false],
        ['code' => '31020108', 'name' => 'Cash Balance: Other Funds', 'is_header' => false],
        ['code' => '31020109', 'name' => 'Cash and Bank Balances held by MDA', 'is_header' => false],

        // Internal Cash Transfers - 31040000
        ['code' => '31040000', 'name' => 'Internal Cash Transfers', 'is_header' => true],

        // Internal Cash Transfer - General - 31040100
        ['code' => '31040100', 'name' => 'Internal Cash Transfer - General', 'is_header' => true],
        ['code' => '31040101', 'name' => 'Cash Transfer To Outstations', 'is_header' => false],
        ['code' => '31040102', 'name' => 'Inter Account Transfers', 'is_header' => false],
        ['code' => '31040103', 'name' => 'Inter-Mda  Cash Transfer', 'is_header' => false],
        ['code' => '31040104', 'name' => 'Revenue Remittance', 'is_header' => false],

        // Inventories - 31050000
        ['code' => '31050000', 'name' => 'Inventories', 'is_header' => true],

        // Inventories - 31050100
        ['code' => '31050100', 'name' => 'Inventories', 'is_header' => true],
        ['code' => '31050101', 'name' => 'Engineering Stores', 'is_header' => false],
        ['code' => '31050102', 'name' => 'Medical Stores', 'is_header' => false],
        ['code' => '31050103', 'name' => 'Industrial & Chemical Stores', 'is_header' => false],
        ['code' => '31050104', 'name' => 'Amory Stores (Ammunition, Etc)', 'is_header' => false],
        ['code' => '31050105', 'name' => 'Fuel & Lubricants', 'is_header' => false],
        ['code' => '31050106', 'name' => 'Agricultural Inputs', 'is_header' => false],
        ['code' => '31050107', 'name' => 'Farm Stock', 'is_header' => false],
        ['code' => '31050108', 'name' => 'Scholastic Materials', 'is_header' => false],
        ['code' => '31050109', 'name' => 'Stationeries Stores', 'is_header' => false],
        ['code' => '31050110', 'name' => 'Printed Materials', 'is_header' => false],
        ['code' => '31050111', 'name' => 'Building Material', 'is_header' => false],
        ['code' => '31050112', 'name' => 'Strategic Stock Piles', 'is_header' => false],
        ['code' => '31050113', 'name' => 'Unissued Currency', 'is_header' => false],
        ['code' => '31050114', 'name' => 'Stamps', 'is_header' => false],
        ['code' => '31050115', 'name' => 'Property Held For Sale', 'is_header' => false],
        ['code' => '31050116', 'name' => 'Aircraft Spare Store', 'is_header' => false],
        ['code' => '31050117', 'name' => 'Computer/Information Technology Store', 'is_header' => false],
        ['code' => '31050118', 'name' => 'Provisional Store', 'is_header' => false],
        ['code' => '31050119', 'name' => 'Equipment Store', 'is_header' => false],
        ['code' => '31050120', 'name' => 'Projects Store (IPPIS, GIFMIS, IPSAS , Etc)', 'is_header' => false],
        ['code' => '31050121', 'name' => 'Electrical/Electronic Store', 'is_header' => false],
        ['code' => '31050122', 'name' => 'Grains Store', 'is_header' => false],
        ['code' => '31050123', 'name' => 'Perishable Store', 'is_header' => false],
        ['code' => '31050124', 'name' => 'Motor Spare Store', 'is_header' => false],
        ['code' => '31050125', 'name' => 'Rail Spare Store', 'is_header' => false],
        ['code' => '31050126', 'name' => 'Ship Spare Store', 'is_header' => false],
        ['code' => '31050127', 'name' => 'Furniture Store', 'is_header' => false],
        ['code' => '31050128', 'name' => 'Plant/ Equipment Store', 'is_header' => false],
        ['code' => '31050129', 'name' => 'Plant/ Equipment Spare Store', 'is_header' => false],
        ['code' => '31050130', 'name' => 'Animal Feed Store', 'is_header' => false],
        ['code' => '31050131', 'name' => 'Veterinary Store', 'is_header' => false],
        ['code' => '31050132', 'name' => 'Class Ware/Apparatus Store', 'is_header' => false],
        ['code' => '31050133', 'name' => 'Laboratory Equipment Store', 'is_header' => false],
        ['code' => '31050134', 'name' => 'Uniform Store', 'is_header' => false],
        ['code' => '31050135', 'name' => 'Other Stock', 'is_header' => false],
        ['code' => '31050192', 'name' => 'Intransit Inventory Account', 'is_header' => false],
        ['code' => '31050193', 'name' => 'Retainage', 'is_header' => false],
        ['code' => '31050194', 'name' => 'Receiving Inventory Account', 'is_header' => false],
        ['code' => '31050195', 'name' => 'Deferred COGS', 'is_header' => false],
        ['code' => '31050196', 'name' => 'Resource', 'is_header' => false],
        ['code' => '31050197', 'name' => 'Material Overhead', 'is_header' => false],
        ['code' => '31050198', 'name' => 'Outside Processing', 'is_header' => false],
        ['code' => '31050199', 'name' => 'Clearing Account', 'is_header' => false],

        // Work-In-Progress - 31050200
        ['code' => '31050200', 'name' => 'Work-In-Progress', 'is_header' => true],
        ['code' => '31050201', 'name' => 'Work-In-Progress', 'is_header' => false],

        // Advances - 31060000
        ['code' => '31060000', 'name' => 'Advances', 'is_header' => true],

        // Personal Advances - 31060100
        ['code' => '31060100', 'name' => 'Personal Advances', 'is_header' => true],
        ['code' => '31060101', 'name' => 'Personal Advances', 'is_header' => false],
        ['code' => '31060102', 'name' => 'Non Personal Others', 'is_header' => false],

        // Administrative Advances - 31060200
        ['code' => '31060200', 'name' => 'Administrative Advances', 'is_header' => true],
        ['code' => '31060201', 'name' => 'Administrative Advances (EIRS)', 'is_header' => false],
        ['code' => '31060203', 'name' => 'Advance To Local Government', 'is_header' => false],
        ['code' => '31060204', 'name' => 'LGC Deposit Paid Off', 'is_header' => false],
        ['code' => '31060205', 'name' => 'Advance to MWCCE', 'is_header' => false],

        // Imprests - 31060300
        ['code' => '31060300', 'name' => 'Imprests', 'is_header' => true],
        ['code' => '31060301', 'name' => 'Imprests', 'is_header' => false],

        // Prepayment/ Arrears Of Revenue - 31080000
        ['code' => '31080000', 'name' => 'Prepayment/ Arrears Of Revenue', 'is_header' => true],

        // Prepayment- General - 31080100
        ['code' => '31080100', 'name' => 'Prepayment- General', 'is_header' => true],
        ['code' => '31080101', 'name' => 'Prepayment', 'is_header' => false],
        ['code' => '31080102', 'name' => 'Bill Payable', 'is_header' => false],
        ['code' => '31080195', 'name' => 'AR Clearing Account', 'is_header' => false],
        ['code' => '31080196', 'name' => 'On account', 'is_header' => false],
        ['code' => '31080197', 'name' => 'Unidentified Receipt', 'is_header' => false],
        ['code' => '31080198', 'name' => 'Unapplied Receipt', 'is_header' => false],
        ['code' => '31080199', 'name' => 'Receivable Account', 'is_header' => false],

        // Investments - 31090000
        ['code' => '31090000', 'name' => 'Investments', 'is_header' => true],

        // Local Investments - 31090100
        ['code' => '31090100', 'name' => 'Local Investments', 'is_header' => true],
        ['code' => '31090101', 'name' => 'Local Investments: Quoted Companies', 'is_header' => false],
        ['code' => '31090102', 'name' => 'Local Investments: Non Quoted Companies', 'is_header' => false],
        ['code' => '31090103', 'name' => 'Investment In Nigerian Treasury Bills (NTBS)', 'is_header' => false],
        ['code' => '31090104', 'name' => 'Investment In Treasury Bills Of Other Governments', 'is_header' => false],
        ['code' => '31090105', 'name' => 'Investment In Treasury Bonds', 'is_header' => false],
        ['code' => '31090106', 'name' => 'Investment In Deriviatives', 'is_header' => false],
        ['code' => '31090107', 'name' => 'Investment In Public Corporations', 'is_header' => false],
        ['code' => '31090121', 'name' => 'Crown Agents Account Fund', 'is_header' => false],
        ['code' => '31090122', 'name' => 'Treasury Clearance and others', 'is_header' => false],

        // Foreign  Investments - 31090200
        ['code' => '31090200', 'name' => 'Foreign  Investments', 'is_header' => true],
        ['code' => '31090201', 'name' => 'Foreign Investments: Quoted Companies', 'is_header' => false],
        ['code' => '31090202', 'name' => 'Foreign Investments: Non Quoted Companies', 'is_header' => false],

        // Loans Granted - 31100000
        ['code' => '31100000', 'name' => 'Loans Granted', 'is_header' => true],

        // Local Loans - 31100100
        ['code' => '31100100', 'name' => 'Local Loans', 'is_header' => true],
        ['code' => '31100101', 'name' => 'Loan To Other State Governments', 'is_header' => false],
        ['code' => '31100102', 'name' => 'Loan To Local Governments', 'is_header' => false],
        ['code' => '31100103', 'name' => 'Loan To Government Owned Companies', 'is_header' => false],
        ['code' => '31100104', 'name' => 'Loan To Private Companies', 'is_header' => false],

        // Foreign Loans - 31100200
        ['code' => '31100200', 'name' => 'Foreign Loans', 'is_header' => true],
        ['code' => '31100201', 'name' => 'Loan To Foreign Governments', 'is_header' => false],
        ['code' => '31100202', 'name' => 'Loan To Foreign/International Organizations', 'is_header' => false],
        ['code' => '31100203', 'name' => 'Loan To Foreign Companies', 'is_header' => false],

        // Non-Current Assets - 32000000
        ['code' => '32000000', 'name' => 'Non-Current Assets', 'is_header' => true],

        // Property, Plant & Equipment - 32010000
        ['code' => '32010000', 'name' => 'Property, Plant & Equipment', 'is_header' => true],

        // Land & Building - General - 32010100
        ['code' => '32010100', 'name' => 'Land & Building - General', 'is_header' => true],
        ['code' => '32010101', 'name' => 'Land & Buildings - Administrative', 'is_header' => false],
        ['code' => '32010102', 'name' => 'Land & Buildings - Residential', 'is_header' => false],
        ['code' => '32010103', 'name' => 'Silos', 'is_header' => false],
        ['code' => '32010104', 'name' => 'Other Storage Facilities', 'is_header' => false],
        ['code' => '32010105', 'name' => 'Land', 'is_header' => false],
        ['code' => '32010106', 'name' => 'Forest Reserve', 'is_header' => false],
        ['code' => '32010107', 'name' => 'Land & Builings -Medical Facilities', 'is_header' => false],
        ['code' => '32010108', 'name' => 'Land & Buildings -Educationl Facilities', 'is_header' => false],
        ['code' => '32010109', 'name' => 'Land & Buildings - Commercial Facilities', 'is_header' => false],
        ['code' => '32010110', 'name' => 'Jetties', 'is_header' => false],
        ['code' => '32010111', 'name' => 'court building', 'is_header' => false],
        ['code' => '32010112', 'name' => 'Land & Buildings -Sport Facilities', 'is_header' => false],

        // Infrastructure - General - 32010200
        ['code' => '32010200', 'name' => 'Infrastructure - General', 'is_header' => true],
        ['code' => '32010201', 'name' => 'Rails', 'is_header' => false],
        ['code' => '32010202', 'name' => 'Roads & Bridges', 'is_header' => false],
        ['code' => '32010203', 'name' => 'Airports', 'is_header' => false],
        ['code' => '32010204', 'name' => 'Harbours/ Sea Ports/ Jetties', 'is_header' => false],
        ['code' => '32010205', 'name' => 'Zoos, Parks & Reserves', 'is_header' => false],
        ['code' => '32010206', 'name' => 'Security Installations/ Equipment', 'is_header' => false],
        ['code' => '32010207', 'name' => 'Electricity Transmission Network', 'is_header' => false],
        ['code' => '32010208', 'name' => 'Water Distribution Network', 'is_header' => false],
        ['code' => '32010209', 'name' => 'Sewage/ Drainage Network', 'is_header' => false],
        ['code' => '32010210', 'name' => 'Dams', 'is_header' => false],
        ['code' => '32010211', 'name' => 'Specialised Research Equipment (E.G. Satellite)', 'is_header' => false],
        ['code' => '32010212', 'name' => 'Monuments', 'is_header' => false],
        ['code' => '32010213', 'name' => 'Heritage Assets', 'is_header' => false],
        ['code' => '32010214', 'name' => 'Boreholes & Other Water Facilities', 'is_header' => false],
        ['code' => '32010215', 'name' => 'Waste Disposal Equipments', 'is_header' => false],
        ['code' => '32010216', 'name' => 'Street Lights', 'is_header' => false],
        ['code' => '32010217', 'name' => 'Cities And Towns', 'is_header' => false],
        ['code' => '32010218', 'name' => 'Billboards', 'is_header' => false],

        // Plant & Machinery - General - 32010300
        ['code' => '32010300', 'name' => 'Plant & Machinery - General', 'is_header' => true],
        ['code' => '32010301', 'name' => 'Earth Moving Equipment - Bull Dozers Etc.', 'is_header' => false],
        ['code' => '32010302', 'name' => 'Industrial Equipment', 'is_header' => false],
        ['code' => '32010303', 'name' => 'Navigational Equipment', 'is_header' => false],
        ['code' => '32010304', 'name' => 'Power Plants', 'is_header' => false],
        ['code' => '32010305', 'name' => 'Power Generating Sets', 'is_header' => false],
        ['code' => '32010306', 'name' => 'Broadcast And Communication Equipment', 'is_header' => false],
        ['code' => '32010307', 'name' => 'Plants and Equipment', 'is_header' => false],

        // Transport Equipment - 32010400
        ['code' => '32010400', 'name' => 'Transport Equipment', 'is_header' => true],
        ['code' => '32010401', 'name' => 'Ships', 'is_header' => false],
        ['code' => '32010402', 'name' => 'Air Crafts', 'is_header' => false],
        ['code' => '32010403', 'name' => 'Trains', 'is_header' => false],
        ['code' => '32010404', 'name' => 'Boats', 'is_header' => false],
        ['code' => '32010405', 'name' => 'Motor Vehicles', 'is_header' => false],
        ['code' => '32010406', 'name' => 'Tricycle', 'is_header' => false],
        ['code' => '32010407', 'name' => 'Motor Cycles', 'is_header' => false],
        ['code' => '32010408', 'name' => 'Bicycle', 'is_header' => false],
        ['code' => '32010409', 'name' => 'Transport Equipment- General', 'is_header' => false],
        ['code' => '32010410', 'name' => 'Sport Equipment', 'is_header' => false],

        // Office Equipment - General - 32010500
        ['code' => '32010500', 'name' => 'Office Equipment - General', 'is_header' => true],
        ['code' => '32010501', 'name' => 'Computers', 'is_header' => false],
        ['code' => '32010502', 'name' => 'Printers', 'is_header' => false],
        ['code' => '32010503', 'name' => 'Scanners', 'is_header' => false],
        ['code' => '32010504', 'name' => 'Fax Machine', 'is_header' => false],
        ['code' => '32010505', 'name' => 'Photocopiers', 'is_header' => false],
        ['code' => '32010506', 'name' => 'Type-Writers', 'is_header' => false],
        ['code' => '32010507', 'name' => 'Shredding Machines', 'is_header' => false],
        ['code' => '32010509', 'name' => 'Binding Equipment', 'is_header' => false],
        ['code' => '32010510', 'name' => 'Computer Software', 'is_header' => false],
        ['code' => '32010513', 'name' => 'Office Equipment', 'is_header' => false],
        ['code' => '32010514', 'name' => 'IT Equipment', 'is_header' => false],

        // Furniture & Fittings - General - 32010600
        ['code' => '32010600', 'name' => 'Furniture & Fittings - General', 'is_header' => true],
        ['code' => '32010601', 'name' => 'Chairs', 'is_header' => false],
        ['code' => '32010602', 'name' => 'Tables', 'is_header' => false],
        ['code' => '32010603', 'name' => 'Safes/ File Cabinets/ Cupboards', 'is_header' => false],
        ['code' => '32010604', 'name' => 'Television Sets', 'is_header' => false],
        ['code' => '32010605', 'name' => 'Radio Sets', 'is_header' => false],
        ['code' => '32010606', 'name' => 'Air Conditioner', 'is_header' => false],
        ['code' => '32010607', 'name' => 'Stools', 'is_header' => false],
        ['code' => '32010608', 'name' => 'Shelves', 'is_header' => false],
        ['code' => '32010609', 'name' => 'Ceiling Fans', 'is_header' => false],
        ['code' => '32010610', 'name' => 'Refridgerators', 'is_header' => false],
        ['code' => '32010611', 'name' => 'Internet Facility', 'is_header' => false],
        ['code' => '32010612', 'name' => 'Furniture and Fittings', 'is_header' => false],

        // Service Concession Assets (Ppp)-General - 32010700
        ['code' => '32010700', 'name' => 'Service Concession Assets (Ppp)-General', 'is_header' => true],
        ['code' => '32010701', 'name' => 'Service Concession Assets (Ppp)', 'is_header' => false],

        // Leased Assets-Finance Lease - 32010800
        ['code' => '32010800', 'name' => 'Leased Assets-Finance Lease', 'is_header' => true],
        ['code' => '32010801', 'name' => 'Leased Assets', 'is_header' => false],

        // Specialised Assets-General - 32010900
        ['code' => '32010900', 'name' => 'Specialised Assets-General', 'is_header' => true],
        ['code' => '32010901', 'name' => 'Military Equipments', 'is_header' => false],
        ['code' => '32010902', 'name' => 'Police/Para-Military Equipments', 'is_header' => false],
        ['code' => '32010903', 'name' => 'Biological Assets', 'is_header' => false],
        ['code' => '32010904', 'name' => 'Laboratory/Medical Equipments', 'is_header' => false],
        ['code' => '32010905', 'name' => 'Infrastructure - General', 'is_header' => false],
        ['code' => '32010906', 'name' => 'Fire Fighting Equipment', 'is_header' => false],

        // Assets-Under-Construction - 32011000
        ['code' => '32011000', 'name' => 'Assets-Under-Construction', 'is_header' => true],
        ['code' => '32011001', 'name' => 'Assets-Under-Construction', 'is_header' => false],

        // Investment Property - 32020000
        ['code' => '32020000', 'name' => 'Investment Property', 'is_header' => true],

        // Land & Building - General - 32020100
        ['code' => '32020100', 'name' => 'Land & Building - General', 'is_header' => true],
        ['code' => '32020101', 'name' => 'Land & Buildings - Office', 'is_header' => false],
        ['code' => '32020102', 'name' => 'Land & Buildings - Residential', 'is_header' => false],
        ['code' => '32020103', 'name' => 'Silos', 'is_header' => false],
        ['code' => '32020104', 'name' => 'Other Storage Facilities(Investment)', 'is_header' => false],

        // Research and Development - 32030109
        ['code' => '32030109', 'name' => 'Research and Development', 'is_header' => false],

        // Intangible Assets - 33000000
        ['code' => '33000000', 'name' => 'Intangible Assets', 'is_header' => true],

        // Intangible Assets - 33010000
        ['code' => '33010000', 'name' => 'Intangible Assets', 'is_header' => true],

        // Intangible Assets - 33010100
        ['code' => '33010100', 'name' => 'Intangible Assets', 'is_header' => true],
        ['code' => '33010101', 'name' => 'Goodwill (Acquired)', 'is_header' => false],
        ['code' => '33010102', 'name' => 'Patent Right', 'is_header' => false],
        ['code' => '33010103', 'name' => 'Copyright', 'is_header' => false],
        ['code' => '33010104', 'name' => 'Trade Mark', 'is_header' => false],
        ['code' => '33010105', 'name' => 'Franchise', 'is_header' => false],
        ['code' => '33010106', 'name' => 'Intangible Asset', 'is_header' => false],
        ['code' => '33010108', 'name' => 'Asset Over Liabilities', 'is_header' => false],
        ['code' => '33010109', 'name' => 'Research & Development', 'is_header' => false],
        ['code' => '33010110', 'name' => 'Broadcast Rights', 'is_header' => false],
        // Liabilities/ Equity - 40000000
            ['code' => '40000000', 'name' => 'Liabilities/ Equity', 'is_header' => true],
            
            // Liabilities/ Equity - 41000000
            ['code' => '41000000', 'name' => 'Liabilities/ Equity', 'is_header' => true],

            // Deposits - General - 41010000
            ['code' => '41010000', 'name' => 'Deposits - General', 'is_header' => true],

            // Contract Retention Fees - 41010100
            ['code' => '41010100', 'name' => 'Contract Retention Fees', 'is_header' => true],
            ['code' => '41010101', 'name' => 'Contract Retention Fees', 'is_header' => false],
            ['code' => '41010103', 'name' => 'Caution Fees', 'is_header' => false],
            ['code' => '41010104', 'name' => 'Undisbursed Scholarships', 'is_header' => false],
            ['code' => '41010105', 'name' => 'Undisbursed Siwes', 'is_header' => false],
            ['code' => '41010106', 'name' => 'Bonds & Sureties', 'is_header' => false],
            ['code' => '41010107', 'name' => 'Other Deposits', 'is_header' => false],
            ['code' => '41010109', 'name' => 'Remittances', 'is_header' => false],
            ['code' => '41010110', 'name' => 'Deposits (LGCs portion of the Bailout)', 'is_header' => false],

            // Loans And Debts - 41020000
            ['code' => '41020000', 'name' => 'Loans And Debts', 'is_header' => true],

            // Internal Loan Stock - 41020100
            ['code' => '41020100', 'name' => 'Internal Loan Stock', 'is_header' => true],
            ['code' => '41020101', 'name' => 'Short Term Borrowings', 'is_header' => false],
            ['code' => '41020102', 'name' => 'Nigerian Treasury Bills (Ntbs)', 'is_header' => false],
            ['code' => '41020103', 'name' => 'Treasury Bonds', 'is_header' => false],
            ['code' => '41020104', 'name' => 'Treasury Certificates', 'is_header' => false],
            ['code' => '41020105', 'name' => 'Bank debt of N11.939 billion Restructured', 'is_header' => false],
            ['code' => '41020106', 'name' => 'FGN N15.942B Bailout Salary', 'is_header' => false],
            ['code' => '41020107', 'name' => 'N10 Billion Excess Crude Project Loan', 'is_header' => false],
            ['code' => '41020108', 'name' => 'N1.111Billion FSP Budget Support', 'is_header' => false],
            ['code' => '41020109', 'name' => 'N2.315 Billion ECTS Leyland Buses Loan', 'is_header' => false],
            ['code' => '41020110', 'name' => 'N1.110 Billion Vehicle Loan', 'is_header' => false],
            ['code' => '41020111', 'name' => 'EDSG Bonds', 'is_header' => false],
            ['code' => '41020112', 'name' => 'AMCON Purchase of BBL Debts', 'is_header' => false],
            ['code' => '41020113', 'name' => 'UBA N1.00 Billion Agric Loan', 'is_header' => false],
            ['code' => '41020114', 'name' => 'CBN Loan on behalf MWCCE', 'is_header' => false],
            ['code' => '41020115', 'name' => 'N1.500B GIS Geographic Information', 'is_header' => false],
            ['code' => '41020116', 'name' => 'Sterling CAC N5.00B Loans', 'is_header' => false],
            ['code' => '41020117', 'name' => 'First Bank CFF N20.00B Loans', 'is_header' => false],
            ['code' => '41020118', 'name' => '1.5m Sterling Bank Facility', 'is_header' => false],
            ['code' => '41020119', 'name' => 'EDSG Bonds2', 'is_header' => false],

            // EXTERNAL LOAN - 41020200
            ['code' => '41020200', 'name' => 'EXTERNAL LOAN', 'is_header' => true],
            ['code' => '41020201', 'name' => 'External Loans from Donor Agencies', 'is_header' => false],
            ['code' => '41020202', 'name' => 'World Bank N14.765', 'is_header' => false],
            ['code' => '41020203', 'name' => 'World Bank N11.831 Billion Budget Support', 'is_header' => false],
            ['code' => '41020204', 'name' => 'N55.918B. Other External Loan', 'is_header' => false],
            ['code' => '41020205', 'name' => 'N0.503B ECTS AMCON Debt', 'is_header' => false],
            ['code' => '41020206', 'name' => 'FGN Bridging Facility', 'is_header' => false],
            ['code' => '41020207', 'name' => 'Abura Oil Field Overpayment Refund', 'is_header' => false],

            // Unremitted Deductions - 41030000
            ['code' => '41030000', 'name' => 'Unremitted Deductions', 'is_header' => true],

            // Unremitted Taxes - 41030100
            ['code' => '41030100', 'name' => 'Unremitted Taxes', 'is_header' => true],
            ['code' => '41030101', 'name' => 'Paye', 'is_header' => false],
            ['code' => '41030102', 'name' => 'Withholding Tax', 'is_header' => false],
            ['code' => '41030103', 'name' => 'Value Added Tax', 'is_header' => false],
            ['code' => '41030104', 'name' => 'Development Levy (3% EDL)', 'is_header' => false],
            ['code' => '41030105', 'name' => 'Legal Fees', 'is_header' => false],
            ['code' => '41030106', 'name' => 'Stamp Duties', 'is_header' => false],
            ['code' => '41030107', 'name' => 'Consultancy Fees', 'is_header' => false],
            ['code' => '41030108', 'name' => 'Audit Fees', 'is_header' => false],
            ['code' => '41030199', 'name' => 'Tax Liability Account', 'is_header' => false],

            // Cooperative Society - 41030200
            ['code' => '41030200', 'name' => 'Cooperative Society', 'is_header' => true],
            ['code' => '41030201', 'name' => 'Edsiec', 'is_header' => false],
            ['code' => '41030202', 'name' => 'Contributory Pension Scheme', 'is_header' => false],
            ['code' => '41030203', 'name' => 'Union Dues', 'is_header' => false],
            ['code' => '41030204', 'name' => 'Hmb Staff', 'is_header' => false],
            ['code' => '41030205', 'name' => 'Cooperative Society', 'is_header' => false],
            ['code' => '41030206', 'name' => 'ENHF (National Housing Fund)', 'is_header' => false],
            ['code' => '41030207', 'name' => 'The Sec. Sspop (PPEp)', 'is_header' => false],
            ['code' => '41030208', 'name' => 'Welfare Deduction', 'is_header' => false],
            ['code' => '41030209', 'name' => 'Manr Staff', 'is_header' => false],
            ['code' => '41030210', 'name' => 'Sec. Ministry Of Health', 'is_header' => false],
            ['code' => '41030211', 'name' => 'Other Deductions', 'is_header' => false],
            ['code' => '41030214', 'name' => 'Loan Deductions', 'is_header' => false],
            ['code' => '41030215', 'name' => 'Edo Min Of Justice', 'is_header' => false],
            ['code' => '41030216', 'name' => 'Overpayment', 'is_header' => false],
            ['code' => '41030217', 'name' => 'Edo State Sport Council', 'is_header' => false],
            ['code' => '41030220', 'name' => 'M.V. Loan - Civil Service', 'is_header' => false],
            ['code' => '41030221', 'name' => 'Refund Of Advance To Local Governments', 'is_header' => false],
            ['code' => '41030222', 'name' => 'LGA Portion Of Paris Club', 'is_header' => false],
            ['code' => '41030223', 'name' => 'President, Staff MPCS, Auditor-General', 'is_header' => false],
            ['code' => '41030224', 'name' => 'Idia', 'is_header' => false],
            ['code' => '41030225', 'name' => 'Sec, Esan-West Workers', 'is_header' => false],
            ['code' => '41030226', 'name' => 'Rural Elect Board', 'is_header' => false],
            ['code' => '41030227', 'name' => 'Btve Staff Welfare', 'is_header' => false],
            ['code' => '41030228', 'name' => 'Ebs Staff Saving Scheme', 'is_header' => false],
            ['code' => '41030229', 'name' => 'Xmas Saving Edha', 'is_header' => false],
            ['code' => '41030230', 'name' => 'Min Of Justice Admin Staff Assoc', 'is_header' => false],
            ['code' => '41030231', 'name' => 'Bnc Ltd', 'is_header' => false],
            ['code' => '41030232', 'name' => 'Dept. Audit Cooperative', 'is_header' => false],
            ['code' => '41030233', 'name' => 'SUBEB', 'is_header' => false],
            ['code' => '41030234', 'name' => 'High Court CTSS', 'is_header' => false],
            ['code' => '41030235', 'name' => 'Customary Court CTSS', 'is_header' => false],
            ['code' => '41030236', 'name' => 'The Sec, Commerce & Industry CTSS', 'is_header' => false],
            ['code' => '41030237', 'name' => 'Law Officer Association Of Nig CTSS', 'is_header' => false],
            ['code' => '41030238', 'name' => 'The Sec. Ebs MPCS (CTSS)', 'is_header' => false],
            ['code' => '41030239', 'name' => 'Edha Staff Welfare Scheme', 'is_header' => false],
            ['code' => '41030240', 'name' => 'The Sec. Speb MPCS', 'is_header' => false],
            ['code' => '41030241', 'name' => 'The Sec. Techscope MPCS', 'is_header' => false],
            ['code' => '41030242', 'name' => 'Edpa CTSS', 'is_header' => false],
            ['code' => '41030243', 'name' => 'The President, Edstma Multi Purpose', 'is_header' => false],

            // Welfare - 41030300
            ['code' => '41030300', 'name' => 'Welfare', 'is_header' => true],
            ['code' => '41030301', 'name' => 'Min Of Justice Law Offr Assoc Of Nig', 'is_header' => false],
            ['code' => '41030302', 'name' => 'Treas. Staff Welfare Assoc', 'is_header' => false],
            ['code' => '41030303', 'name' => 'Staff Welfare Bnc Ltd', 'is_header' => false],
            ['code' => '41030304', 'name' => 'Min Of Edu Welfare Scheme', 'is_header' => false],
            ['code' => '41030305', 'name' => 'Dir Of Admin Gov\'s Office Staff Welfare', 'is_header' => false],
            ['code' => '41030306', 'name' => 'Sec. Comm & Industry Welfare Scheme', 'is_header' => false],
            ['code' => '41030307', 'name' => 'Edha Welfare Scheme', 'is_header' => false],
            ['code' => '41030308', 'name' => 'Chairman, Staff Welfare (PPEB)', 'is_header' => false],
            ['code' => '41030309', 'name' => 'Edha Staff Welfare', 'is_header' => false],
            ['code' => '41030310', 'name' => 'Sec. Staff Welfare Scheme (OAG)', 'is_header' => false],
            ['code' => '41030311', 'name' => 'Edo State Urban Water Board Welfare', 'is_header' => false],
            ['code' => '41030312', 'name' => 'Magistrate Association', 'is_header' => false],
            ['code' => '41030313', 'name' => 'State High Court Dept Of Accounts Welfare', 'is_header' => false],
            ['code' => '41030314', 'name' => 'Edo State Sport Council Staff Welfare', 'is_header' => false],
            ['code' => '41030315', 'name' => 'Area Customary Court Pres. Asso. (ACCPA)', 'is_header' => false],
            ['code' => '41030316', 'name' => 'Reb Staff Welfare', 'is_header' => false],
            ['code' => '41030317', 'name' => 'Coaches Welfare', 'is_header' => false],
            ['code' => '41030318', 'name' => 'PPEB Staff Welfare', 'is_header' => false],
            ['code' => '41030319', 'name' => 'Edo Health Insurance Scheme', 'is_header' => false],

            // Union Dues - 41030400
            ['code' => '41030400', 'name' => 'Union Dues', 'is_header' => true],
            ['code' => '41030401', 'name' => 'Nig Union Of Pensioners', 'is_header' => false],
            ['code' => '41030402', 'name' => 'Nig Assoc Of Hospital & Admin Pharm', 'is_header' => false],
            ['code' => '41030403', 'name' => 'Pharmacy Tehnician Assoc', 'is_header' => false],
            ['code' => '41030404', 'name' => 'Medical & Health Worker Union', 'is_header' => false],
            ['code' => '41030405', 'name' => 'Edha Parliamentary Staff Assoc', 'is_header' => false],
            ['code' => '41030406', 'name' => 'Nigeria Dental Association', 'is_header' => false],
            ['code' => '41030407', 'name' => 'Nigeria Medical Association', 'is_header' => false],
            ['code' => '41030408', 'name' => 'Medical Women Assoc, Edo State', 'is_header' => false],
            ['code' => '41030409', 'name' => 'Conference Of Sec Sch Tutors (Assus)', 'is_header' => false],
            ['code' => '41030410', 'name' => 'National Assoc Of Med Lab', 'is_header' => false],
            ['code' => '41030411', 'name' => 'Nuppppron', 'is_header' => false],
            ['code' => '41030412', 'name' => 'National Union Of Teachers', 'is_header' => false],
            ['code' => '41030413', 'name' => 'NUJ', 'is_header' => false],
            ['code' => '41030414', 'name' => 'NLC', 'is_header' => false],
            ['code' => '41030415', 'name' => 'N.A.S.U', 'is_header' => false],
            ['code' => '41030416', 'name' => 'Assoc Of Senior Civil Servant', 'is_header' => false],
            ['code' => '41030417', 'name' => 'Aupctre', 'is_header' => false],
            ['code' => '41030418', 'name' => 'Nucscaw (Typist/Stenography)', 'is_header' => false],
            ['code' => '41030419', 'name' => 'Sec/Treas, Ncsu', 'is_header' => false],
            ['code' => '41030420', 'name' => 'RATTAWU', 'is_header' => false],
            ['code' => '41030421', 'name' => 'Catholic Nurses Guild Of Nig', 'is_header' => false],
            ['code' => '41030422', 'name' => 'National Assoc Of Resident Doctors', 'is_header' => false],
            ['code' => '41030423', 'name' => 'Medical & Dental Assoc', 'is_header' => false],
            ['code' => '41030424', 'name' => 'National Assoc Of Nurses & Midwives', 'is_header' => false],
            ['code' => '41030425', 'name' => 'Assoc Of Hmb Optometrist', 'is_header' => false],
            ['code' => '41030426', 'name' => 'Parliamentary Staff Assoc', 'is_header' => false],
            ['code' => '41030427', 'name' => 'Sec. Allied Workers Union Of Nig', 'is_header' => false],
            ['code' => '41030428', 'name' => 'Sec. Pharmaceutical Soc Of Nig', 'is_header' => false],
            ['code' => '41030429', 'name' => 'Sec. Law Office Assoc Of Nig', 'is_header' => false],
            ['code' => '41030430', 'name' => 'Sec. Assoc Of Medical Lab', 'is_header' => false],
            ['code' => '41030431', 'name' => 'Charman, Nupppprow', 'is_header' => false],
            ['code' => '41030432', 'name' => 'Sec. Nuppppron', 'is_header' => false],
            ['code' => '41030433', 'name' => 'Sec/Treas. Assoc Of Junior Staff', 'is_header' => false],
            ['code' => '41030434', 'name' => 'Sec/Treas. Almagamated Union', 'is_header' => false],
            ['code' => '41030435', 'name' => 'JUSUN', 'is_header' => false],
            ['code' => '41030436', 'name' => 'Agric Allied Workers Union Of Nig', 'is_header' => false],
            ['code' => '41030437', 'name' => 'Technical Worker Union', 'is_header' => false],

            // Loan Deductions - 41030500
            ['code' => '41030500', 'name' => 'Loan Deductions', 'is_header' => true],
            ['code' => '41030501', 'name' => 'Samono Coy Ltd', 'is_header' => false],
            ['code' => '41030502', 'name' => 'Mgr Edpa Staff Rent Repayment', 'is_header' => false],
            ['code' => '41030503', 'name' => 'Edsg, Motor Veh Loan Refund', 'is_header' => false],
            ['code' => '41030504', 'name' => 'Fed Mort Bank Plc', 'is_header' => false],
            ['code' => '41030505', 'name' => 'Rent From Quarters Edha', 'is_header' => false],
            ['code' => '41030506', 'name' => 'UIDC', 'is_header' => false],
            ['code' => '41030507', 'name' => 'First Bank Loan', 'is_header' => false],
            ['code' => '41030508', 'name' => 'GTB Bank Loan', 'is_header' => false],
            ['code' => '41030509', 'name' => 'Urban Water Board Staff Loan Refund', 'is_header' => false],
            ['code' => '41030510', 'name' => 'EDSG Staff Loan Refund', 'is_header' => false],

            // Contributory Pension Scheme - 41030600
            ['code' => '41030600', 'name' => 'Contributory Pension Scheme', 'is_header' => true],
            ['code' => '41030601', 'name' => 'Employees\' Contributory Pension', 'is_header' => false],
            ['code' => '41030610', 'name' => 'Overpayment Recoverable (Receipt)', 'is_header' => false],
            ['code' => '41030710', 'name' => 'Federal Withholding Tax Payable', 'is_header' => false],
            ['code' => '41030711', 'name' => 'State Withholding Tax Payable', 'is_header' => false],
            ['code' => '41030801', 'name' => 'Other Deductions', 'is_header' => false],
            ['code' => '41030802', 'name' => 'National Housing Insurance Scheme', 'is_header' => false],

            // Accrued Expenses - 41040000
            ['code' => '41040000', 'name' => 'Accrued Expenses', 'is_header' => true],
            ['code' => '41040100', 'name' => 'Accrued Expenses', 'is_header' => true],
            ['code' => '41040101', 'name' => 'Personnel Emoluments', 'is_header' => false],
            ['code' => '41040102', 'name' => 'Pension & Gratuity', 'is_header' => false],
            ['code' => '41040103', 'name' => 'Professional Fees', 'is_header' => false],
            ['code' => '41040104', 'name' => 'Overheads', 'is_header' => false],
            ['code' => '41040105', 'name' => 'Utilities', 'is_header' => false],
            ['code' => '41040106', 'name' => 'Pension and Public Funds', 'is_header' => false],
            ['code' => '41040107', 'name' => 'Other non personnel deductions', 'is_header' => false],
            ['code' => '41040195', 'name' => 'Unearned Discount', 'is_header' => false],
            ['code' => '41040196', 'name' => 'Unallocated Revenue', 'is_header' => false],
            ['code' => '41040197', 'name' => 'Expense Clearing Account', 'is_header' => false],
            ['code' => '41040198', 'name' => 'Expense AP Accrual', 'is_header' => false],
            ['code' => '41040199', 'name' => 'Liability', 'is_header' => false],

            // Motor Vehicle Loan ref. - 41040200
            ['code' => '41040200', 'name' => 'Motor Vehicle Loan ref.', 'is_header' => true],
            ['code' => '41040201', 'name' => 'Motor Vehicle Loan refund', 'is_header' => false],
            ['code' => '41040299', 'name' => 'Bank Clearing Account', 'is_header' => false],

            // Current Portion Of Long-Term Borrowings - 41050000
            ['code' => '41050000', 'name' => 'Current Portion Of Long-Term Borrowings', 'is_header' => true],
            ['code' => '41050100', 'name' => 'Current Portion Of Long-Term Borrowings', 'is_header' => true],
            ['code' => '41050101', 'name' => 'Treasury Bonds', 'is_header' => false],
            ['code' => '41050102', 'name' => 'Bi-Lateral Loans', 'is_header' => false],
            ['code' => '41050103', 'name' => 'Multi-Lateral Loans', 'is_header' => false],
            ['code' => '41050104', 'name' => 'World Bank (IDA) Loan', 'is_header' => false],
            ['code' => '41050105', 'name' => 'International Fund For Agric Dev (IFAD)', 'is_header' => false],
            ['code' => '41050106', 'name' => 'Ecowas Fund For Artisan Fisheries', 'is_header' => false],
            ['code' => '41050107', 'name' => 'Rural Finance Institution Program', 'is_header' => false],

            // Deferred Income - 41060000
            ['code' => '41060000', 'name' => 'Deferred Income', 'is_header' => true],
            ['code' => '41060100', 'name' => 'Deferred Income', 'is_header' => true],
            ['code' => '41060101', 'name' => 'Deferred Income', 'is_header' => false],

            // Payables - 41070000
            ['code' => '41070000', 'name' => 'Payables', 'is_header' => true],
            ['code' => '41070100', 'name' => 'Payables- Fixed Assets', 'is_header' => true],
            ['code' => '41070101', 'name' => 'Payables- Motor vehicles', 'is_header' => false],
            ['code' => '41070200', 'name' => 'Payables- Others', 'is_header' => true],
            ['code' => '41070201', 'name' => 'Payables - All below the line credits', 'is_header' => false],
            ['code' => '41079999', 'name' => 'Inventory AP Accrual', 'is_header' => false],

            // Provision For Depreciation - 42000000
            ['code' => '42000000', 'name' => 'Provision For Depreciation', 'is_header' => true],

            // 42010100
            ['code' => '42010100', 'name' => '', 'is_header' => true],
            ['code' => '42010101', 'name' => 'Prov For Dep - Land And Buildings - General', 'is_header' => false],
            ['code' => '42010106', 'name' => 'Prov For Dep - Forest Reserve', 'is_header' => false],

            // Provision For Dep- Infrastructure - General - 42010200
            ['code' => '42010200', 'name' => 'Provision For Dep- Infrastructure - General', 'is_header' => true],
            ['code' => '42010201', 'name' => 'Prov. For Dep-Rails', 'is_header' => false],
            ['code' => '42010202', 'name' => 'Prov. For Dep-Roads & Bridges', 'is_header' => false],
            ['code' => '42010203', 'name' => 'Prov. For Dep-Airports', 'is_header' => false],
            ['code' => '42010204', 'name' => 'Prov. For Dep-Harbours/ Sea Ports', 'is_header' => false],
            ['code' => '42010205', 'name' => 'Prov. For Dep-Zoos, Parks & Reserves', 'is_header' => false],
            ['code' => '42010206', 'name' => 'Prov. For Dep-Security Installations/ Equipment', 'is_header' => false],
            ['code' => '42010207', 'name' => 'Prov. For Dep-Electricity Transmission Network', 'is_header' => false],
            ['code' => '42010208', 'name' => 'Prov. For Dep-Water Distribution Network', 'is_header' => false],
            ['code' => '42010209', 'name' => 'Prov. For Dep-Sewage/ Drainage Network', 'is_header' => false],
            ['code' => '42010210', 'name' => 'Prov. For Dep-Dams', 'is_header' => false],
            ['code' => '42010211', 'name' => 'Prov. For Dep-Specialised Research Equipment (E.G. Satellite)', 'is_header' => false],
            ['code' => '42010212', 'name' => 'Prov. For Dep-Boreholes & Other Water Facilities', 'is_header' => false],
            ['code' => '42010213', 'name' => 'Prov. For Dep-Waste Disposal Equipments', 'is_header' => false],

            // Accumulated Prov. For Dep - Plant & Machinery - General - 42010300
            ['code' => '42010300', 'name' => 'Accumulated Prov. For Dep - Plant & Machinery - General', 'is_header' => true],
            ['code' => '42010301', 'name' => 'Prov. For Dep-Earth Moving Equipment - Bull Dozers Etc.', 'is_header' => false],
            ['code' => '42010302', 'name' => 'Prov. For Dep-Industrial Equipment', 'is_header' => false],
            ['code' => '42010303', 'name' => 'Prov. For Dep-Navigational Equipment', 'is_header' => false],
            ['code' => '42010304', 'name' => 'Prov. For Dep-Power Plants', 'is_header' => false],
            ['code' => '42010305', 'name' => 'Prov. For Dep-Power Generating Sets', 'is_header' => false],
            ['code' => '42010307', 'name' => 'Prov. For Dep-Plant and Equipment', 'is_header' => false],

            // Accumulated Prov. For  Dep - Transportation Equipment - General - 42010400
            ['code' => '42010400', 'name' => 'Accumulated Prov. For  Dep - Transportation Equipment - General', 'is_header' => true],
            ['code' => '42010401', 'name' => 'Prov. For Dep-Ships', 'is_header' => false],
            ['code' => '42010402', 'name' => 'Prov. For Dep-Air Crafts', 'is_header' => false],
            ['code' => '42010403', 'name' => 'Prov. For Dep-Trains', 'is_header' => false],
            ['code' => '42010404', 'name' => 'Prov. For Dep-Sea Boats', 'is_header' => false],
            ['code' => '42010405', 'name' => 'Prov. For Dep-Motor Vehicles', 'is_header' => false],
            ['code' => '42010406', 'name' => 'Prov. For Dep-Tricycle', 'is_header' => false],
            ['code' => '42010407', 'name' => 'Prov. For Dep-Motor Cycles', 'is_header' => false],
            ['code' => '42010408', 'name' => 'Prov. For Dep-Bicycle', 'is_header' => false],

            // Accumulated Prov. For Dep - Office Equipment - General - 42010500
            ['code' => '42010500', 'name' => 'Accumulated Prov. For Dep - Office Equipment - General', 'is_header' => true],
            ['code' => '42010501', 'name' => 'Prov. For Dep-Computers', 'is_header' => false],
            ['code' => '42010502', 'name' => 'Prov. For Dep-Printers', 'is_header' => false],
            ['code' => '42010503', 'name' => 'Prov. For Dep-Scanners', 'is_header' => false],
            ['code' => '42010504', 'name' => 'Prov. For Dep-Fax Machine', 'is_header' => false],
            ['code' => '42010505', 'name' => 'Prov. For Dep-Photocopiers', 'is_header' => false],
            ['code' => '42010506', 'name' => 'Prov. For Dep-Type-Writers', 'is_header' => false],
            ['code' => '42010507', 'name' => 'Prov. For Dep-Shredding Machines', 'is_header' => false],
            ['code' => '42010511', 'name' => 'Prov. For Dep-Projectors', 'is_header' => false],
            ['code' => '42010512', 'name' => 'Prov. For Dep-Binding Equipment', 'is_header' => false],
            ['code' => '42010513', 'name' => 'Prov. For Dep-Office Equipment', 'is_header' => false],
            ['code' => '42010514', 'name' => 'Prov. For Dep- IT Equipment', 'is_header' => false],

            // Accumulated Prov. For Dep - Furniture & Fittings - General - 42010600
            ['code' => '42010600', 'name' => 'Accumulated Prov. For Dep - Furniture & Fittings - General', 'is_header' => true],
            ['code' => '42010601', 'name' => 'Prov. For Dep-Chairs', 'is_header' => false],
            ['code' => '42010602', 'name' => 'Prov. For Dep-Tables', 'is_header' => false],
            ['code' => '42010603', 'name' => 'Prov. For Dep-Safes/ File Cabinets/ Cupboards', 'is_header' => false],
            ['code' => '42010604', 'name' => 'Prov. For Dep-Stools', 'is_header' => false],
            ['code' => '42010605', 'name' => 'Prov. For Dep-Shelves', 'is_header' => false],
            ['code' => '42010606', 'name' => 'Prov. For Dep-Ceiling Fans', 'is_header' => false],
            ['code' => '42010607', 'name' => 'Prov. For Refridgerators', 'is_header' => false],
            ['code' => '42010608', 'name' => 'Prov. For Dep-Television Sets', 'is_header' => false],
            ['code' => '42010609', 'name' => 'Prov. For Dep-Radio Sets', 'is_header' => false],
            ['code' => '42010610', 'name' => 'Prov. For Dep-Air -Conditioner', 'is_header' => false],
            ['code' => '42010612', 'name' => 'Prov. For Dep-Air -Furniture and Fittings', 'is_header' => false],

            // Accumulated Prov. For - Service Concession Assets - 42010700
            ['code' => '42010700', 'name' => 'Accumulated Prov. For - Service Concession Assets', 'is_header' => true],
            ['code' => '42010701', 'name' => 'Prov. For Dep - Service Concession Assets (PPE)', 'is_header' => false],

            // Accumulated Prov. For Leased Assets-Finance Lease - 42010800
            ['code' => '42010800', 'name' => 'Accumulated Prov. For Leased Assets-Finance Lease', 'is_header' => true],
            ['code' => '42010801', 'name' => 'Prov. For Leased Assets', 'is_header' => false],

            // Accumulated Prov. For Specialised Assets-General - 42010900
            ['code' => '42010900', 'name' => 'Accumulated Prov. For Specialised Assets-General', 'is_header' => true],
            ['code' => '42010901', 'name' => 'Prov. For Military Equipments', 'is_header' => false],
            ['code' => '42010902', 'name' => 'Prov. For Police/Para-Military Equipments', 'is_header' => false],
            ['code' => '42010903', 'name' => 'Prov. For Biological Assets', 'is_header' => false],
            ['code' => '42010904', 'name' => 'Prov. For Laboratory/Medical Equipments', 'is_header' => false],

            // Accum. Prov. For  Dep - Assets Under Construction - 42011000
            ['code' => '42011000', 'name' => 'Accum. Prov. For  Dep - Assets Under Construction', 'is_header' => true],
            ['code' => '42011001', 'name' => 'Accum. Prov. For  Dep -Assets Under Construction', 'is_header' => false],

            // Provision For Depreciation - Investment Property - 42020000
            ['code' => '42020000', 'name' => 'Provision For Depreciation - Investment Property', 'is_header' => true],

            // Prov For Dep - Investment - Land And Buildings - General - 42020100
            ['code' => '42020100', 'name' => 'Prov For Dep - Investment - Land And Buildings - General', 'is_header' => true],
            ['code' => '42020101', 'name' => 'Prov For Dep - Investment - Land And Buildings - Office', 'is_header' => false],
            ['code' => '42020102', 'name' => 'Prov For Dep - Investment - Land And Buildings - Residential', 'is_header' => false],
            ['code' => '42020103', 'name' => 'Prov. For Dep - Investment Property- Silos', 'is_header' => false],
            ['code' => '42030102', 'name' => 'World Bank Loan', 'is_header' => false],
            ['code' => '42020104', 'name' => 'Prov. For Dep - Investment Property- Other Storage Facilities', 'is_header' => false],

            // Accumulated Provision For Impairment - PPE - 43010000
            ['code' => '43010000', 'name' => 'Accumulated Provision For Impairment - PPE', 'is_header' => true],

            // Accumulated Prov. For Impairment - Land & Buildings -General - 43010100
            ['code' => '43010100', 'name' => 'Accumulated Prov. For Impairment - Land & Buildings -General', 'is_header' => true],
            ['code' => '43010101', 'name' => 'Prov. For Impairment - Land & Buildings - Administrative', 'is_header' => false],
            ['code' => '43010102', 'name' => 'Prov. For Impairment - Land & Buildings - Residential', 'is_header' => false],
            ['code' => '43010103', 'name' => 'Prov. For Impairment - Silos', 'is_header' => false],
            ['code' => '43010104', 'name' => 'Prov. For Impairment - Storage Facilities', 'is_header' => false],

            // Accumulated Prov. For Impairment - Infrastructure - General - 43010200
            ['code' => '43010200', 'name' => 'Accumulated Prov. For Impairment - Infrastructure - General', 'is_header' => true],
            ['code' => '43010201', 'name' => 'Prov. For Impairment - Rails', 'is_header' => false],
            ['code' => '43010202', 'name' => 'Prov. For Impairment - Roads & Bridges', 'is_header' => false],
            ['code' => '43010203', 'name' => 'Prov. For Impairment - Airports', 'is_header' => false],
            ['code' => '43010204', 'name' => 'Prov. For Impairment - Harbours/ Sea Ports', 'is_header' => false],
            ['code' => '43010205', 'name' => 'Prov. For Impairment - Zoos, Parks & Reserves', 'is_header' => false],
            ['code' => '43010206', 'name' => 'Prov. For Impairment - Security Installations/ Equipment', 'is_header' => false],
            ['code' => '43010207', 'name' => 'Prov. For Impairment - Electricity Transmission Network', 'is_header' => false],
            ['code' => '43010208', 'name' => 'Prov. For Impairment - Water Distribution Network', 'is_header' => false],
            ['code' => '43010209', 'name' => 'Prov. For Impairment - Sewage/ Drainage Network', 'is_header' => false],
            ['code' => '43010210', 'name' => 'Prov. For Impairment - Dams', 'is_header' => false],
            ['code' => '43010211', 'name' => 'Prov. For Impairment - Specialised Research Equipment (E.G. Satellite)', 'is_header' => false],
            ['code' => '43010212', 'name' => 'Prov. For Impairment-Boreholes & Other Water Facilities', 'is_header' => false],
            ['code' => '43010213', 'name' => 'Prov. For Impairment-Waste Disposal Equipments', 'is_header' => false],

            // Accumulated Prov. For  Impairment -  Plant & Machinery - General - 43010300
            ['code' => '43010300', 'name' => 'Accumulated Prov. For  Impairment -  Plant & Machinery - General', 'is_header' => true],
            ['code' => '43010301', 'name' => 'Prov. For Impairment - Earth Moving Equipment - Bull Dozers Etc.', 'is_header' => false],
            ['code' => '43010302', 'name' => 'Prov. For Impairment - Industrial Equipment', 'is_header' => false],
            ['code' => '43010303', 'name' => 'Prov. For Impairment - Navigational Equipment', 'is_header' => false],
            ['code' => '43010304', 'name' => 'Prov. For Impairment - Power Plants', 'is_header' => false],
            ['code' => '43010305', 'name' => 'Prov. For Impairment - Power Generating Sets', 'is_header' => false],
            ['code' => '43010306', 'name' => 'Prov. For Impairment- Broadcast & Communication Equipments', 'is_header' => false],

            // Accum. Prov. For  Impairment -  Transportation Equipment - General - 43010400
            ['code' => '43010400', 'name' => 'Accum. Prov. For  Impairment -  Transportation Equipment - General', 'is_header' => true],
            ['code' => '43010401', 'name' => 'Prov. For Impairment - Ships', 'is_header' => false],
            ['code' => '43010402', 'name' => 'Prov. For Impairment - Air Crafts', 'is_header' => false],
            ['code' => '43010403', 'name' => 'Prov. For Impairment - Trains', 'is_header' => false],
            ['code' => '43010404', 'name' => 'Prov. For Impairment - Sea Boats', 'is_header' => false],
            ['code' => '43010405', 'name' => 'Prov. For Impairment - Motor Vehicles', 'is_header' => false],
            ['code' => '43010406', 'name' => 'Prov. For Impairment - Tricycle', 'is_header' => false],
            ['code' => '43010407', 'name' => 'Prov. For Impairment - Motor Cycles', 'is_header' => false],
            ['code' => '43010408', 'name' => 'Prov. For Impairment - Bicycle', 'is_header' => false],

            // Accumulated Prov. For Impairment -  Office Equipment - General - 43010500
            ['code' => '43010500', 'name' => 'Accumulated Prov. For Impairment -  Office Equipment - General', 'is_header' => true],
            ['code' => '43010501', 'name' => 'Prov. For Impairment - Computers', 'is_header' => false],
            ['code' => '43010502', 'name' => 'Prov. For Impairment - Printers', 'is_header' => false],
            ['code' => '43010503', 'name' => 'Prov. For Impairment - Scanners', 'is_header' => false],
            ['code' => '43010504', 'name' => 'Prov. For Impairment - Fax Machine', 'is_header' => false],
            ['code' => '43010505', 'name' => 'Prov. For Impairment - Photocopiers', 'is_header' => false],
            ['code' => '43010506', 'name' => 'Prov. For Impairment - Type-Writers', 'is_header' => false],
            ['code' => '43010507', 'name' => 'Prov. For Impairment - Shredding Machines', 'is_header' => false],
            ['code' => '43010511', 'name' => 'Prov. For Impairment - Projectors', 'is_header' => false],
            ['code' => '43010512', 'name' => 'Prov. For Impairment - Binding Equipment', 'is_header' => false],

            // Accum. Prov. For Impairment -  Furniture & Fittings - General - 43010600
            ['code' => '43010600', 'name' => 'Accum. Prov. For Impairment -  Furniture & Fittings - General', 'is_header' => true],
            ['code' => '43010601', 'name' => 'Prov. For Impairment - Chairs', 'is_header' => false],
            ['code' => '43010602', 'name' => 'Prov. For Impairment - Tables', 'is_header' => false],
            ['code' => '43010603', 'name' => 'Prov. For Impairment - Safes/File Cabinets/ Cupboards', 'is_header' => false],
            ['code' => '43010604', 'name' => 'Prov. For Impairment - Stools', 'is_header' => false],
            ['code' => '43010605', 'name' => 'Prov. For Impairment - Shelves', 'is_header' => false],
            ['code' => '43010606', 'name' => 'Prov. For Impairment - Ceiling Fans', 'is_header' => false],
            ['code' => '43010608', 'name' => 'Prov. For Impairment - Television Sets', 'is_header' => false],
            ['code' => '43010609', 'name' => 'Prov. For Impairment - Radio Sets', 'is_header' => false],
            ['code' => '43010610', 'name' => 'Prov. For Impairment - Air -Conditioner', 'is_header' => false],

            // Provision For Accum. Impairment - Investment Property - 43020000
            ['code' => '43020000', 'name' => 'Provision For Accum. Impairment - Investment Property', 'is_header' => true],

            // Accum. Prov. For Impairment -  Investment Property - Land & Building - General - 43020100
            ['code' => '43020100', 'name' => 'Accum. Prov. For Impairment -  Investment Property - Land & Building - General', 'is_header' => true],
            ['code' => '43020101', 'name' => 'Prov. For Impairment -  Investment Property - Land & Buildings - Administrative', 'is_header' => false],
            ['code' => '43020102', 'name' => 'Prior Year Adjustment', 'is_header' => false],
            ['code' => '43020103', 'name' => 'Transitional Reserves', 'is_header' => false],
            ['code' => '43020104', 'name' => 'Prov. For Impairment -  Investment Property-Other Storage Facilities', 'is_header' => false],

            // Accumulated Provision For Impairment - Intangible - 43030000
            ['code' => '43030000', 'name' => 'Accumulated Provision For Impairment - Intangible', 'is_header' => true],
            ['code' => '43030100', 'name' => 'Accumulated Provision For Impairment - Intangible', 'is_header' => true],
            ['code' => '43030101', 'name' => 'Provision For Impairment - Goodwill', 'is_header' => false],
            ['code' => '43030102', 'name' => 'Provision For Impairment - Patent Right', 'is_header' => false],
            ['code' => '43030103', 'name' => 'Provision For Impairment - Copyright', 'is_header' => false],
            ['code' => '43030104', 'name' => 'Provision For Impairment - Trade Mark', 'is_header' => false],
            ['code' => '43030105', 'name' => 'Provision For Impairment - Franchise', 'is_header' => false],
            ['code' => '43030106', 'name' => 'Provision For Impairment - Monument', 'is_header' => false],
            ['code' => '43030107', 'name' => 'Provision For Impairment - Heritage', 'is_header' => false],
            ['code' => '43030108', 'name' => 'Provision For Impairment- Research & Development', 'is_header' => false],
            ['code' => '43030109', 'name' => 'Provision For Impairment- Broadcast Rights', 'is_header' => false],
            ['code' => '43030110', 'name' => 'Provision For Impairment- Intangible Asset', 'is_header' => false],

            // Provision For Debts - 44000000
            ['code' => '44000000', 'name' => 'Provision For Debts', 'is_header' => true],
            ['code' => '44010000', 'name' => 'Provision For Debts', 'is_header' => true],
            ['code' => '44010100', 'name' => 'Provision For Foreign Debts', 'is_header' => true],
            ['code' => '44010101', 'name' => 'Bilateral', 'is_header' => false],
            ['code' => '44010102', 'name' => 'Multi-Lateral Loans', 'is_header' => false],
            ['code' => '44010200', 'name' => 'Accumulated Provision For Bad Debts- Domestic Loans', 'is_header' => true],
            ['code' => '44010201', 'name' => 'Loans To Other States', 'is_header' => false],
            ['code' => '44010202', 'name' => 'Loans To Local Governments', 'is_header' => false],
            ['code' => '44010203', 'name' => 'Loans To Ministries, Departments & Agencies', 'is_header' => false],
            ['code' => '44010204', 'name' => 'Loans Granted To Government Owned Companies', 'is_header' => false],
            ['code' => '44010205', 'name' => 'Loans Granted To Private Owned Companies', 'is_header' => false],
            ['code' => '44010306', 'name' => 'Prov. For Dep-Broadcast & Communications Equipment', 'is_header' => false],

            // Provision For Ammortization - 45000000
            ['code' => '45000000', 'name' => 'Provision For Ammortization', 'is_header' => true],
            ['code' => '45010000', 'name' => 'Accumulated Provision For Ammortization', 'is_header' => true],
            ['code' => '45010100', 'name' => 'Accumulated Provision For Ammortization', 'is_header' => true],
            ['code' => '45010101', 'name' => 'Accumulated Provision For Ammortization - Goodwill', 'is_header' => false],
            ['code' => '45010102', 'name' => 'Accumulated Provision For Ammortization - Patent Right', 'is_header' => false],
            ['code' => '45010103', 'name' => 'Accumulated Provision For Ammortization - Copyright', 'is_header' => false],
            ['code' => '45010104', 'name' => 'Accumulated Provision For Ammortization - Trade Mark', 'is_header' => false],
            ['code' => '45010105', 'name' => 'Accumulated Provision For Ammortization - Franchise', 'is_header' => false],
            ['code' => '45010106', 'name' => 'Accumulated Provision For Ammortization - Research & Development', 'is_header' => false],
            ['code' => '45010107', 'name' => 'Accumulated Provision For Ammortization - Broadcast Right', 'is_header' => false],

            // Capital & Reserves - 47000000
            ['code' => '47000000', 'name' => 'Capital & Reserves', 'is_header' => true],
            ['code' => '47010000', 'name' => 'Reserves', 'is_header' => true],
            ['code' => '47010100', 'name' => 'Reserves', 'is_header' => true],
            ['code' => '47010101', 'name' => 'Reserve for Encumbrance', 'is_header' => false],
            ['code' => '47010102', 'name' => 'Available For Sale Reserve', 'is_header' => false],
            ['code' => '47010103', 'name' => 'Accumulated Reserve', 'is_header' => false],
            ['code' => '47010200', 'name' => 'Trust & Other Public Funds', 'is_header' => true],
            ['code' => '47020101', 'name' => 'Accumulated Surplus/Deficit', 'is_header' => false],
        
        // ['code' => '47020101', 'name' => 'Accumulated Surplus/Deficit', 'is_header' => false],
        ];

        $currentEconomyCodeId = null;

        foreach ($data as $item) {
            if ($item['is_header']) {
                // This is an Economic Code (header)
                $currentEconomyCodeId = DB::table('economy_codes')->insertGetId([
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // This is an Economic Code Item
                if ($currentEconomyCodeId) {
                    DB::table('economy_code_items')->insert([
                        'economy_code_id' => $currentEconomyCodeId,
                        'code' => $item['code'],
                        'name' => $item['name'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Economic Codes and Items seeded successfully! Total codes: ' . DB::table('economy_codes')->count() . ', Total items: ' . DB::table('economy_code_items')->count());
    }
}
