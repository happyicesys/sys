<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Bahasa Indonesia)
    |--------------------------------------------------------------------------
    |
    | Same keys as lang/zh_CN/validation.php (lang/en plus the rules Laravel 12's
    | own English file carries), so an Indonesian user never gets an English
    | fallback for them. Keep every :placeholder; :attribute is the field name
    | exactly as Laravel gives it.
    |
    */

    'accepted' => ':attribute harus diterima.',
    'accepted_if' => ':attribute harus diterima jika :other adalah :value.',
    'active_url' => ':attribute bukan URL yang valid.',
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal :date atau setelahnya.',
    'alpha' => ':attribute hanya boleh berisi huruf.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => ':attribute hanya boleh berisi huruf dan angka.',
    'array' => ':attribute harus berupa array.',
    'ascii' => ':attribute hanya boleh berisi huruf, angka, dan simbol satu byte.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus tanggal :date atau sebelumnya.',
    'between' => [
        'array' => ':attribute harus berisi :min sampai :max item.',
        'file' => ':attribute harus berukuran :min sampai :max KB.',
        'numeric' => ':attribute harus antara :min dan :max.',
        'string' => ':attribute harus :min sampai :max karakter.',
    ],
    'boolean' => ':attribute harus true atau false.',
    'can' => ':attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'contains' => ':attribute tidak memuat nilai yang wajib ada.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_equals' => ':attribute harus tanggal yang sama dengan :date.',
    'date_format' => ':attribute tidak sesuai format :format.',
    'decimal' => ':attribute harus memiliki :decimal angka desimal.',
    'declined' => ':attribute harus ditolak.',
    'declined_if' => ':attribute harus ditolak jika :other adalah :value.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus :digits digit.',
    'digits_between' => ':attribute harus :min sampai :max digit.',
    'dimensions' => 'Ukuran gambar :attribute tidak valid.',
    'distinct' => ':attribute memiliki nilai yang duplikat.',
    'doesnt_end_with' => ':attribute tidak boleh diakhiri salah satu dari: :values.',
    'doesnt_start_with' => ':attribute tidak boleh diawali salah satu dari: :values.',
    'email' => ':attribute harus alamat email yang valid.',
    'ends_with' => ':attribute harus diakhiri salah satu dari: :values.',
    'enum' => ':attribute yang dipilih tidak valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'extensions' => ':attribute harus berekstensi salah satu dari: :values.',
    'file' => ':attribute harus berupa file.',
    'filled' => ':attribute harus diisi.',
    'gt' => [
        'array' => ':attribute harus berisi lebih dari :value item.',
        'file' => ':attribute harus lebih besar dari :value KB.',
        'numeric' => ':attribute harus lebih besar dari :value.',
        'string' => ':attribute harus lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => ':attribute harus berisi :value item atau lebih.',
        'file' => ':attribute harus lebih besar dari atau sama dengan :value KB.',
        'numeric' => ':attribute harus lebih besar dari atau sama dengan :value.',
        'string' => ':attribute harus :value karakter atau lebih.',
    ],
    'hex_color' => ':attribute harus warna heksadesimal yang valid.',
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'in_array' => ':attribute tidak ada di :other.',
    'integer' => ':attribute harus bilangan bulat.',
    'ip' => ':attribute harus alamat IP yang valid.',
    'ipv4' => ':attribute harus alamat IPv4 yang valid.',
    'ipv6' => ':attribute harus alamat IPv6 yang valid.',
    'json' => ':attribute harus string JSON yang valid.',
    'list' => ':attribute harus berupa daftar.',
    'lowercase' => ':attribute harus huruf kecil.',
    'lt' => [
        'array' => ':attribute harus berisi kurang dari :value item.',
        'file' => ':attribute harus lebih kecil dari :value KB.',
        'numeric' => ':attribute harus lebih kecil dari :value.',
        'string' => ':attribute harus kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => ':attribute tidak boleh berisi lebih dari :value item.',
        'file' => ':attribute harus lebih kecil dari atau sama dengan :value KB.',
        'numeric' => ':attribute harus lebih kecil dari atau sama dengan :value.',
        'string' => ':attribute harus :value karakter atau kurang.',
    ],
    'mac_address' => ':attribute harus alamat MAC yang valid.',
    'max' => [
        'array' => ':attribute tidak boleh berisi lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih besar dari :max KB.',
        'numeric' => ':attribute tidak boleh lebih besar dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'max_digits' => ':attribute tidak boleh lebih dari :max digit.',
    'mimes' => ':attribute harus file bertipe: :values.',
    'mimetypes' => ':attribute harus file bertipe: :values.',
    'min' => [
        'array' => ':attribute harus berisi minimal :min item.',
        'file' => ':attribute minimal :min KB.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'min_digits' => ':attribute minimal :min digit.',
    'missing' => ':attribute harus tidak ada.',
    'missing_if' => ':attribute harus tidak ada jika :other adalah :value.',
    'missing_unless' => ':attribute harus tidak ada kecuali :other adalah :value.',
    'missing_with' => ':attribute harus tidak ada jika :values ada.',
    'missing_with_all' => ':attribute harus tidak ada jika :values semuanya ada.',
    'multiple_of' => ':attribute harus kelipatan :value.',
    'not_in' => ':attribute yang dipilih tidak valid.',
    'not_regex' => 'Format :attribute tidak valid.',
    'numeric' => ':attribute harus berupa angka.',
    'password' => [
        'letters' => ':attribute harus berisi minimal satu huruf.',
        'mixed' => ':attribute harus berisi minimal satu huruf besar dan satu huruf kecil.',
        'numbers' => ':attribute harus berisi minimal satu angka.',
        'symbols' => ':attribute harus berisi minimal satu simbol.',
        'uncompromised' => ':attribute ini pernah muncul dalam kebocoran data. Silakan pilih :attribute lain.',
    ],
    'phone' => ':attribute berisi nomor yang tidak valid.',
    'present' => ':attribute harus ada.',
    'present_if' => ':attribute harus ada jika :other adalah :value.',
    'present_unless' => ':attribute harus ada kecuali :other adalah :value.',
    'present_with' => ':attribute harus ada jika :values ada.',
    'present_with_all' => ':attribute harus ada jika :values semuanya ada.',
    'prohibited' => ':attribute tidak boleh diisi.',
    'prohibited_if' => ':attribute tidak boleh diisi jika :other adalah :value.',
    'prohibited_if_accepted' => ':attribute tidak boleh diisi jika :other diterima.',
    'prohibited_if_declined' => ':attribute tidak boleh diisi jika :other ditolak.',
    'prohibited_unless' => ':attribute tidak boleh diisi kecuali :other ada di :values.',
    'prohibits' => ':attribute tidak boleh ada bersama :other.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_array_keys' => ':attribute harus berisi entri untuk: :values.',
    'required_if' => ':attribute wajib diisi jika :other adalah :value.',
    'required_if_accepted' => ':attribute wajib diisi jika :other diterima.',
    'required_if_declined' => ':attribute wajib diisi jika :other ditolak.',
    'required_unless' => ':attribute wajib diisi kecuali :other ada di :values.',
    'required_with' => ':attribute wajib diisi jika :values ada.',
    'required_with_all' => ':attribute wajib diisi jika :values semuanya ada.',
    'required_without' => ':attribute wajib diisi jika :values tidak ada.',
    'required_without_all' => ':attribute wajib diisi jika :values semuanya tidak ada.',
    'same' => ':attribute dan :other harus sama.',
    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'file' => ':attribute harus berukuran :size KB.',
        'numeric' => ':attribute harus :size.',
        'string' => ':attribute harus :size karakter.',
    ],
    'starts_with' => ':attribute harus diawali salah satu dari: :values.',
    'string' => ':attribute harus berupa teks.',
    'timezone' => ':attribute harus zona waktu yang valid.',
    'ulid' => ':attribute harus ULID yang valid.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => ':attribute gagal diunggah.',
    'uppercase' => ':attribute harus huruf besar.',
    'url' => ':attribute harus URL yang valid.',
    'uuid' => ':attribute harus UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Empty on purpose, like lang/en: attribute names stay as Laravel gives them.
    |
    */

    'attributes' => [],

];
