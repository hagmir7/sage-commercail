<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MachineSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $machines = [
            // ── PLACAGE ──────────────────────────────────────────────────────────
            ['family' => 'PLACAGE', 'ref_machine' => 'LN',   'group_code' => 'LINEA', 'machine_id' => 'MAH1', 'machine_name' => 'MAHROS RUNNER/D-SSL',          'serial_number' => 'MA4131',           'alias' => 'ROBOT DE CHARGE',             'manufacturer' => 'SCM'],
            ['family' => 'PLACAGE', 'ref_machine' => 'LN',   'group_code' => 'LINEA', 'machine_id' => 'LN1',  'machine_name' => 'STEFANI PERFORMANCE SB 1^L-72', 'serial_number' => 'AH004210',         'alias' => 'LINEA 1er partie',            'manufacturer' => 'SCM'],
            ['family' => 'PLACAGE', 'ref_machine' => 'LN',   'group_code' => 'LINEA', 'machine_id' => 'MAH2', 'machine_name' => 'MAHROS GP/DC/A-L',              'serial_number' => 'MA4132',           'alias' => 'ROBOT ROTATION PANNEAU',      'manufacturer' => 'SCM'],
            ['family' => 'PLACAGE', 'ref_machine' => 'LN',   'group_code' => 'LINEA', 'machine_id' => 'LN2',  'machine_name' => 'STEFANI PERFORMANCE SB 2^L-117','serial_number' => 'AH004211',         'alias' => 'LINEA 2eme partie',           'manufacturer' => 'SCM'],
            ['family' => 'PLACAGE', 'ref_machine' => 'LN',   'group_code' => 'LINEA', 'machine_id' => 'MAH3', 'machine_name' => 'MAHROS RUNNER/D-CSL',           'serial_number' => 'MA4133',           'alias' => 'ROBOT DE DÉCHARGE',           'manufacturer' => 'SCM'],
            ['family' => 'PLACAGE', 'ref_machine' => 'IDM',  'group_code' => 'IDM',   'machine_id' => 'IDM',  'machine_name' => 'IDM ACTIVA SB',                 'serial_number' => 'AH004235',         'alias' => 'IDM ou bien Media Linea',     'manufacturer' => 'SCM'],
            ['family' => 'PLACAGE', 'ref_machine' => 'ST1',  'group_code' => 'STEFANI','machine_id' => 'ST1', 'machine_name' => 'STEFANI MD RM',                 'serial_number' => 'AH00001192',       'alias' => 'STEFANI',                     'manufacturer' => 'SCM'],
            ['family' => 'PLACAGE', 'ref_machine' => 'BS1',  'group_code' => 'BIESSE', 'machine_id' => 'BS1', 'machine_name' => 'Roxyl 4.5',                     'serial_number' => '53574',            'alias' => 'BIESSE',                      'manufacturer' => 'BIESSE'],
            ['family' => 'PLACAGE', 'ref_machine' => 'R12',  'group_code' => 'R12',   'machine_id' => 'R12',  'machine_name' => null,                            'serial_number' => null,               'alias' => 'R12 ou Plaqueuse LISTONE',    'manufacturer' => null],
            ['family' => 'PLACAGE', 'ref_machine' => 'VT1',  'group_code' => 'VITAP', 'machine_id' => 'VT1',  'machine_name' => 'Eclipse 2.0',                   'serial_number' => null,               'alias' => 'VITAP',                       'manufacturer' => 'VITAP'],

            // ── DECOUPAGE ─────────────────────────────────────────────────────────
            ['family' => 'DECOUPAGE', 'ref_machine' => 'GB1', 'group_code' => 'GRAND GIBEN',  'machine_id' => 'GB1', 'machine_name' => 'SIGMA 201',       'serial_number' => '047.00.380', 'alias' => 'GRAND GIBEN',  'manufacturer' => 'GIBEN'],
            ['family' => 'DECOUPAGE', 'ref_machine' => 'GB2', 'group_code' => 'PETIT GIBEN',  'machine_id' => 'GB2', 'machine_name' => 'PRISMA 2 SPT',    'serial_number' => '478.97.380', 'alias' => 'PETIT GIBEN',  'manufacturer' => 'GIBEN'],
            ['family' => 'DECOUPAGE', 'ref_machine' => 'F45', 'group_code' => 'F45',          'machine_id' => 'F45', 'machine_name' => 'ALTENDORF F45',   'serial_number' => null,         'alias' => 'F45',          'manufacturer' => 'ALTENDORF'],
            ['family' => 'DECOUPAGE', 'ref_machine' => 'F90', 'group_code' => 'F90',          'machine_id' => 'F90', 'machine_name' => 'ALTENDORF F90',   'serial_number' => null,         'alias' => 'F90',          'manufacturer' => 'ALTENDORF'],

            // ── CNC ───────────────────────────────────────────────────────────────
            ['family' => 'CNC', 'ref_machine' => 'NS1', 'group_code' => 'NESTING', 'machine_id' => 'NS1', 'machine_name' => 'Morbidelli N 100 22 D', 'serial_number' => 'AA10000901', 'alias' => 'NESTING', 'manufacturer' => 'SCM'],

            // ── PERCAGE ───────────────────────────────────────────────────────────
            ['family' => 'PERCAGE', 'ref_machine' => 'ZN1', 'group_code' => 'ZENITH',            'machine_id' => 'ZN1', 'machine_name' => 'Morbidelli ZENITH F2',         'serial_number' => 'AL5287',     'alias' => 'ZENITH',                        'manufacturer' => 'SCM'],
            ['family' => 'PERCAGE', 'ref_machine' => 'FM3', 'group_code' => 'FM300',             'machine_id' => 'FM3', 'machine_name' => 'Morbidelli FM300DA',            'serial_number' => 'XT76',       'alias' => 'PETITE MACHINE PERCEUSE',       'manufacturer' => 'Morbidelli (SCM)'],
            ['family' => 'PERCAGE', 'ref_machine' => 'FM4', 'group_code' => 'FM400',             'machine_id' => 'FM4', 'machine_name' => 'Morbidelli FM400DA+FM400JA',   'serial_number' => 'AL4290+',    'alias' => 'GRANDE MACHINE PERCEUSE',       'manufacturer' => 'Morbidelli (SCM)'],
            ['family' => 'PERCAGE', 'ref_machine' => 'PT1', 'group_code' => 'PERCEUSE TRACERA 1','machine_id' => 'PT1', 'machine_name' => null,                           'serial_number' => null,         'alias' => 'ANCIENNE PERCEUSE TRACERA',     'manufacturer' => null],
            ['family' => 'PERCAGE', 'ref_machine' => 'PT2', 'group_code' => 'PERCEUSE TRACERA 2','machine_id' => 'PT2', 'machine_name' => null,                           'serial_number' => null,         'alias' => 'PERCEUSE TRACERA MODIFIÉE',     'manufacturer' => null],
            ['family' => 'PERCAGE', 'ref_machine' => 'PL1', 'group_code' => 'PERCEUSE LISTONE H','machine_id' => 'PL1', 'machine_name' => null,                           'serial_number' => null,         'alias' => 'PERCEUSE LISTONE HORIZONTAL',   'manufacturer' => null],
            ['family' => 'PERCAGE', 'ref_machine' => 'PL2', 'group_code' => 'PERCEUSE LISTONE N','machine_id' => 'PL2', 'machine_name' => null,                           'serial_number' => null,         'alias' => 'PERCEUSE LISTONE NUMERIQUE',    'manufacturer' => null],

            // ── ASSEMBLAGE ────────────────────────────────────────────────────────
            ['family' => 'ASSEMBLAGE', 'ref_machine' => 'FR1', 'group_code' => 'FOUR',               'machine_id' => 'FR1', 'machine_name' => 'EB-250/70 + RB-130/70', 'serial_number' => '1930-07-02 + 1929-07-02', 'alias' => 'FOUR à CHAUD EXPEDITION',    'manufacturer' => 'CMB'],
            ['family' => 'ASSEMBLAGE', 'ref_machine' => 'FR2', 'group_code' => null,                 'machine_id' => 'FR2', 'machine_name' => 'ERL-150-TI',            'serial_number' => '2682-05-08',              'alias' => 'FOUR à froid LOGISTIQUE',    'manufacturer' => 'CMB'],
            ['family' => 'ASSEMBLAGE', 'ref_machine' => 'RL1', 'group_code' => null,                 'machine_id' => 'RL1', 'machine_name' => '-',                      'serial_number' => '-',                       'alias' => 'Banderoleuse de palettes',   'manufacturer' => 'CEEM'],
            ['family' => 'ASSEMBLAGE', 'ref_machine' => 'PR5', 'group_code' => null,                 'machine_id' => 'PR5', 'machine_name' => 'HG3',                    'serial_number' => '15020',                   'alias' => 'Perceuse visagra',           'manufacturer' => 'OMAL'],
            ['family' => 'ASSEMBLAGE', 'ref_machine' => 'ACC', 'group_code' => 'ASSEMBLAGE ACCESOIRE','machine_id' => 'ACC', 'machine_name' => null,                    'serial_number' => null,                      'alias' => null,                         'manufacturer' => null],

            // ── AUTRE ─────────────────────────────────────────────────────────────
            ['family' => 'AUTRE', 'ref_machine' => 'ELV',  'group_code' => null,            'machine_id' => 'ELV1',  'machine_name' => null,              'serial_number' => null,         'alias' => 'ELEVATEUR',            'manufacturer' => null],
            ['family' => 'AUTRE', 'ref_machine' => 'PIS',  'group_code' => 'PISTOLET',      'machine_id' => 'PIS',   'machine_name' => null,              'serial_number' => null,         'alias' => 'CLOUEUR',              'manufacturer' => null],
            ['family' => 'AUTRE', 'ref_machine' => 'LSR',  'group_code' => 'GRAVEUSE LASER','machine_id' => 'LSR',   'machine_name' => null,              'serial_number' => null,         'alias' => null,                   'manufacturer' => null],
            ['family' => 'AUTRE', 'ref_machine' => 'CLIS', 'group_code' => 'CANAL LISTONE', 'machine_id' => 'CLIS',  'machine_name' => null,              'serial_number' => null,         'alias' => 'CANAL LISTONE',        'manufacturer' => null],
            ['family' => 'AUTRE', 'ref_machine' => 'GA75', 'group_code' => null,            'machine_id' => 'GA75',  'machine_name' => 'GA75VSD + FF',    'serial_number' => 'API865219',  'alias' => 'GRAND COMPRESSEUR',    'manufacturer' => 'ATLAS COPCO'],
            ['family' => 'AUTRE', 'ref_machine' => 'GA55', 'group_code' => null,            'machine_id' => 'GA55',  'machine_name' => 'GA55VSD + FF',    'serial_number' => 'API872560',  'alias' => 'PETIT COMPRESSEUR',    'manufacturer' => 'ATLAS COPCO'],
            ['family' => 'AUTRE', 'ref_machine' => 'ASP1', 'group_code' => null,            'machine_id' => 'ASP1',  'machine_name' => null,              'serial_number' => null,         'alias' => 'ASPIRATEUR1',          'manufacturer' => null],
            ['family' => 'AUTRE', 'ref_machine' => 'ASP2', 'group_code' => null,            'machine_id' => 'ASP2',  'machine_name' => null,              'serial_number' => null,         'alias' => 'ASPIRATEUR2',          'manufacturer' => null],
            ['family' => 'AUTRE', 'ref_machine' => 'BRY1', 'group_code' => null,            'machine_id' => 'BRY1',  'machine_name' => null,              'serial_number' => null,         'alias' => 'BROYEUR',              'manufacturer' => null],
            ['family' => 'AUTRE', 'ref_machine' => 'SILO1','group_code' => null,            'machine_id' => 'SILO1', 'machine_name' => null,              'serial_number' => null,         'alias' => 'SILO',                 'manufacturer' => null],
            ['family' => 'AUTRE', 'ref_machine' => 'SILO2','group_code' => null,            'machine_id' => 'SILO2', 'machine_name' => null,              'serial_number' => null,         'alias' => 'SILO',                 'manufacturer' => null],
        ];

        foreach ($machines as &$machine) {
            $machine['is_active']   = true;
            $machine['notes']       = null;
            $machine['created_at']  = $now;
            $machine['updated_at']  = $now;
        }

        DB::table('machines')->insert($machines);
    }
}