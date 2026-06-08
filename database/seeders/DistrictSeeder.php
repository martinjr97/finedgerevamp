<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Get provinces (assuming they exist)
        $provinces = Province::all()->keyBy('name');
        
        // Zambian districts organized by province
        $districts = [
            // Central Province
            ['province' => 'Central', 'name' => 'Chibombo', 'code' => 'CENT-CHIB'],
            ['province' => 'Central', 'name' => 'Kabwe', 'code' => 'CENT-KABW'],
            ['province' => 'Central', 'name' => 'Kapiri Mposhi', 'code' => 'CENT-KAPM'],
            ['province' => 'Central', 'name' => 'Mkushi', 'code' => 'CENT-MKUS'],
            ['province' => 'Central', 'name' => 'Mumbwa', 'code' => 'CENT-MUMB'],
            ['province' => 'Central', 'name' => 'Serenje', 'code' => 'CENT-SERE'],
            
            // Copperbelt Province
            ['province' => 'Copperbelt', 'name' => 'Chililabombwe', 'code' => 'COPP-CHIL'],
            ['province' => 'Copperbelt', 'name' => 'Chingola', 'code' => 'COPP-CHIN'],
            ['province' => 'Copperbelt', 'name' => 'Kalulushi', 'code' => 'COPP-KALU'],
            ['province' => 'Copperbelt', 'name' => 'Kitwe', 'code' => 'COPP-KITW'],
            ['province' => 'Copperbelt', 'name' => 'Luanshya', 'code' => 'COPP-LUAN'],
            ['province' => 'Copperbelt', 'name' => 'Lufwanyama', 'code' => 'COPP-LUFW'],
            ['province' => 'Copperbelt', 'name' => 'Masaiti', 'code' => 'COPP-MASA'],
            ['province' => 'Copperbelt', 'name' => 'Mpongwe', 'code' => 'COPP-MPON'],
            ['province' => 'Copperbelt', 'name' => 'Mufulira', 'code' => 'COPP-MUFU'],
            ['province' => 'Copperbelt', 'name' => 'Ndola', 'code' => 'COPP-NDOL'],
            
            // Eastern Province
            ['province' => 'Eastern', 'name' => 'Chadiza', 'code' => 'EAST-CHAD'],
            ['province' => 'Eastern', 'name' => 'Chipata', 'code' => 'EAST-CHIP'],
            ['province' => 'Eastern', 'name' => 'Katete', 'code' => 'EAST-KATE'],
            ['province' => 'Eastern', 'name' => 'Lundazi', 'code' => 'EAST-LUND'],
            ['province' => 'Eastern', 'name' => 'Mambwe', 'code' => 'EAST-MAMB'],
            ['province' => 'Eastern', 'name' => 'Nyimba', 'code' => 'EAST-NYIM'],
            ['province' => 'Eastern', 'name' => 'Petauke', 'code' => 'EAST-PETA'],
            ['province' => 'Eastern', 'name' => 'Sinda', 'code' => 'EAST-SIND'],
            ['province' => 'Eastern', 'name' => 'Vubwi', 'code' => 'EAST-VUBW'],
            
            // Luapula Province
            ['province' => 'Luapula', 'name' => 'Chembe', 'code' => 'LUAP-CHEM'],
            ['province' => 'Luapula', 'name' => 'Chiengi', 'code' => 'LUAP-CHIE'],
            ['province' => 'Luapula', 'name' => 'Chifunabuli', 'code' => 'LUAP-CHIF'],
            ['province' => 'Luapula', 'name' => 'Kawambwa', 'code' => 'LUAP-KAWA'],
            ['province' => 'Luapula', 'name' => 'Luwingu', 'code' => 'LUAP-LUWI'],
            ['province' => 'Luapula', 'name' => 'Mansa', 'code' => 'LUAP-MANS'],
            ['province' => 'Luapula', 'name' => 'Milenge', 'code' => 'LUAP-MILE'],
            ['province' => 'Luapula', 'name' => 'Mwansabombwe', 'code' => 'LUAP-MWAN'],
            ['province' => 'Luapula', 'name' => 'Mwense', 'code' => 'LUAP-MWEN'],
            ['province' => 'Luapula', 'name' => 'Nchelenge', 'code' => 'LUAP-NCHE'],
            ['province' => 'Luapula', 'name' => 'Samfya', 'code' => 'LUAP-SAMF'],
            
            // Lusaka Province
            ['province' => 'Lusaka', 'name' => 'Chongwe', 'code' => 'LUSA-CHON'],
            ['province' => 'Lusaka', 'name' => 'Kafue', 'code' => 'LUSA-KAFU'],
            ['province' => 'Lusaka', 'name' => 'Luangwa', 'code' => 'LUSA-LUAN'],
            ['province' => 'Lusaka', 'name' => 'Lusaka', 'code' => 'LUSA-LUSA'],
            ['province' => 'Lusaka', 'name' => 'Rufunsa', 'code' => 'LUSA-RUFU'],
            ['province' => 'Lusaka', 'name' => 'Shibuyunji', 'code' => 'LUSA-SHIB'],
            
            // Muchinga Province
            ['province' => 'Muchinga', 'name' => 'Chama', 'code' => 'MUCH-CHAM'],
            ['province' => 'Muchinga', 'name' => 'Chinsali', 'code' => 'MUCH-CHIN'],
            ['province' => 'Muchinga', 'name' => 'Isoka', 'code' => 'MUCH-ISOK'],
            ['province' => 'Muchinga', 'name' => 'Kanchibiya', 'code' => 'MUCH-KANC'],
            ['province' => 'Muchinga', 'name' => 'Lavushimanda', 'code' => 'MUCH-LAVU'],
            ['province' => 'Muchinga', 'name' => 'Mafinga', 'code' => 'MUCH-MAFI'],
            ['province' => 'Muchinga', 'name' => 'Mpika', 'code' => 'MUCH-MPIK'],
            ['province' => 'Muchinga', 'name' => 'Nakonde', 'code' => 'MUCH-NAKO'],
            ['province' => 'Muchinga', 'name' => 'Shiwang\'andu', 'code' => 'MUCH-SHIW'],
            
            // Northern Province
            ['province' => 'Northern', 'name' => 'Chilubi', 'code' => 'NORT-CHIL'],
            ['province' => 'Northern', 'name' => 'Kaputa', 'code' => 'NORT-KAPU'],
            ['province' => 'Northern', 'name' => 'Kasama', 'code' => 'NORT-KASA'],
            ['province' => 'Northern', 'name' => 'Lunte', 'code' => 'NORT-LUNT'],
            ['province' => 'Northern', 'name' => 'Lupososhi', 'code' => 'NORT-LUPO'],
            ['province' => 'Northern', 'name' => 'Luwingu', 'code' => 'NORT-LUWI'],
            ['province' => 'Northern', 'name' => 'Mbala', 'code' => 'NORT-MBAL'],
            ['province' => 'Northern', 'name' => 'Mporokoso', 'code' => 'NORT-MPOR'],
            ['province' => 'Northern', 'name' => 'Mpulungu', 'code' => 'NORT-MPUL'],
            ['province' => 'Northern', 'name' => 'Mungwi', 'code' => 'NORT-MUNG'],
            ['province' => 'Northern', 'name' => 'Nsama', 'code' => 'NORT-NSAM'],
            ['province' => 'Northern', 'name' => 'Senga Hill', 'code' => 'NORT-SENG'],
            
            // North-Western Province
            ['province' => 'North-Western', 'name' => 'Chavuma', 'code' => 'NW-CHAV'],
            ['province' => 'North-Western', 'name' => 'Ikelenge', 'code' => 'NW-IKEL'],
            ['province' => 'North-Western', 'name' => 'Kabompo', 'code' => 'NW-KABO'],
            ['province' => 'North-Western', 'name' => 'Kalumbila', 'code' => 'NW-KALU'],
            ['province' => 'North-Western', 'name' => 'Kasempa', 'code' => 'NW-KASE'],
            ['province' => 'North-Western', 'name' => 'Manyinga', 'code' => 'NW-MANY'],
            ['province' => 'North-Western', 'name' => 'Mufumbwe', 'code' => 'NW-MUFU'],
            ['province' => 'North-Western', 'name' => 'Mushindamo', 'code' => 'NW-MUSH'],
            ['province' => 'North-Western', 'name' => 'Mwinilunga', 'code' => 'NW-MWIN'],
            ['province' => 'North-Western', 'name' => 'Solwezi', 'code' => 'NW-SOLW'],
            ['province' => 'North-Western', 'name' => 'Zambezi', 'code' => 'NW-ZAMB'],
            
            // Southern Province
            ['province' => 'Southern', 'name' => 'Choma', 'code' => 'SOUT-CHOM'],
            ['province' => 'Southern', 'name' => 'Gwembe', 'code' => 'SOUT-GWEM'],
            ['province' => 'Southern', 'name' => 'Itezhi-Tezhi', 'code' => 'SOUT-ITEZ'],
            ['province' => 'Southern', 'name' => 'Kalomo', 'code' => 'SOUT-KALO'],
            ['province' => 'Southern', 'name' => 'Kazungula', 'code' => 'SOUT-KAZU'],
            ['province' => 'Southern', 'name' => 'Livingstone', 'code' => 'SOUT-LIVI'],
            ['province' => 'Southern', 'name' => 'Mazabuka', 'code' => 'SOUT-MAZA'],
            ['province' => 'Southern', 'name' => 'Monze', 'code' => 'SOUT-MONZ'],
            ['province' => 'Southern', 'name' => 'Namwala', 'code' => 'SOUT-NAMW'],
            ['province' => 'Southern', 'name' => 'Pemba', 'code' => 'SOUT-PEMB'],
            ['province' => 'Southern', 'name' => 'Siavonga', 'code' => 'SOUT-SIAV'],
            ['province' => 'Southern', 'name' => 'Sinazongwe', 'code' => 'SOUT-SINA'],
            ['province' => 'Southern', 'name' => 'Zimba', 'code' => 'SOUT-ZIMB'],
            
            // Western Province
            ['province' => 'Western', 'name' => 'Kalabo', 'code' => 'WEST-KALA'],
            ['province' => 'Western', 'name' => 'Kaoma', 'code' => 'WEST-KAOM'],
            ['province' => 'Western', 'name' => 'Limulunga', 'code' => 'WEST-LIMU'],
            ['province' => 'Western', 'name' => 'Luampa', 'code' => 'WEST-LUAM'],
            ['province' => 'Western', 'name' => 'Lukulu', 'code' => 'WEST-LUKU'],
            ['province' => 'Western', 'name' => 'Mitete', 'code' => 'WEST-MITE'],
            ['province' => 'Western', 'name' => 'Mongu', 'code' => 'WEST-MONG'],
            ['province' => 'Western', 'name' => 'Mulobezi', 'code' => 'WEST-MULO'],
            ['province' => 'Western', 'name' => 'Mwandi', 'code' => 'WEST-MWAN'],
            ['province' => 'Western', 'name' => 'Nalolo', 'code' => 'WEST-NALO'],
            ['province' => 'Western', 'name' => 'Nkeyema', 'code' => 'WEST-NKEY'],
            ['province' => 'Western', 'name' => 'Senanga', 'code' => 'WEST-SENA'],
            ['province' => 'Western', 'name' => 'Sesheke', 'code' => 'WEST-SESH'],
            ['province' => 'Western', 'name' => 'Shang\'ombo', 'code' => 'WEST-SHAN'],
            ['province' => 'Western', 'name' => 'Sikongo', 'code' => 'WEST-SIKO'],
        ];

        foreach ($districts as $district) {
            $province = $provinces->get($district['province']);
            if ($province) {
                District::firstOrCreate(
                    ['code' => $district['code']],
                    [
                        'province_id' => $province->id,
                        'name' => $district['name'],
                        'code' => $district['code'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
