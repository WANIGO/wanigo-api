<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\KatalogSampah;
use App\Models\BankSampah;
use App\Models\SubKategoriSampah;
use App\Models\KategoriSampah;
use App\Models\MemberBankSampah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class KatalogSampahController extends Controller
{
    /**
     * Mendapatkan daftar katalog sampah berdasarkan bank sampah dan kategori.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getByBankSampah(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'kode_kategori' => 'sometimes|in:kering,basah', // Gunakan kode_kategori dari kategori_sampah baru
            'sub_kategori_id' => 'sometimes|exists:sub_kategori_sampah,id', // Tambahan filter sub-kategori
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $bankSampahId = $request->bank_sampah_id;

        // Cek apakah nasabah terdaftar di bank sampah ini
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $bankSampahId)
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini'
            ], 422);
        }

        // Dapatkan informasi bank sampah
        $bankSampah = BankSampah::find($bankSampahId);
        if (!$bankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Bank sampah tidak ditemukan'
            ], 404);
        }

        // Query katalog sampah - Gunakan cara baru dengan sub_kategori
        $query = KatalogSampah::with('subKategori.kategoriSampah')
            ->where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true);

        // Filter berdasarkan kategori jika ada
        if ($request->has('kode_kategori')) {
            $query->byKategoriUtama($request->kode_kategori);
        }

        // Filter berdasarkan sub-kategori jika ada
        if ($request->has('sub_kategori_id')) {
            $query->where('sub_kategori_sampah_id', $request->sub_kategori_id);
        }

        $katalogSampah = $query->get();

        // Hitung jumlah item per kategori utama
        $jumlahKering = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true)
            ->kering()
            ->count();

        $jumlahBasah = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true)
            ->basah()
            ->count();

        // Format response dengan kategori dan sub kategori
        foreach ($katalogSampah as $item) {
            $item->kategori_utama = $item->kategoriSampahText;
            $item->sub_kategori = $item->subKategoriText;
            $item->harga_format = $item->hargaFormat;
        }

        // Dapatkan daftar sub-kategori untuk digunakan di button group filter UI
        $subKategoriList = [];
        if ($request->has('kode_kategori')) {
            $kategori = KategoriSampah::where('kode_kategori', $request->kode_kategori)->first();
            if ($kategori) {
                $subKategoriList = SubKategoriSampah::where('bank_sampah_id', $bankSampahId)
                    ->where('kategori_sampah_id', $kategori->id)
                    ->where('status_aktif', true)
                    ->ordered()
                    ->get()
                    ->map(function($item) use ($katalogSampah) {
                        // Hitung jumlah item di setiap sub kategori
                        $jumlahItem = $katalogSampah->where('sub_kategori_sampah_id', $item->id)->count();
                        return [
                            'id' => $item->id,
                            'nama_sub_kategori' => $item->nama_sub_kategori,
                            'kode_sub_kategori' => $item->kode_sub_kategori,
                            'warna' => $item->warna,
                            'jumlah_item' => $jumlahItem
                        ];
                    });
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bank_sampah' => [
                    'id' => $bankSampah->id,
                    'nama' => $bankSampah->nama_bank_sampah,
                    'alamat' => $bankSampah->alamat_bank_sampah,
                    'tanggal_setoran' => $bankSampah->tanggal_setoran,
                    'status_operasional' => $bankSampah->status_operasional ? 'Aktif' : 'Tutup',
                    'nomor_telepon' => $bankSampah->nomor_telepon_publik
                ],
                'katalog_sampah' => $katalogSampah,
                'sub_kategori_list' => $subKategoriList, // Tambahan data sub-kategori untuk UI button group
                'info_kategori' => [
                    'jumlah_kering' => $jumlahKering,
                    'jumlah_basah' => $jumlahBasah
                ]
            ]
        ]);
    }

    /**
     * Mendapatkan detail item sampah.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $userId = Auth::id();
        $katalogSampah = KatalogSampah::with('subKategori.kategoriSampah')->find($id);

        if (!$katalogSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Item sampah tidak ditemukan'
            ], 404);
        }

        // Cek apakah nasabah terdaftar di bank sampah ini
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $katalogSampah->bank_sampah_id)
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini'
            ], 422);
        }

        // Tambahkan data kategori dan sub kategori
        $katalogSampah->kategori_utama = $katalogSampah->kategoriSampahText;
        $katalogSampah->sub_kategori = $katalogSampah->subKategoriText;
        $katalogSampah->harga_format = $katalogSampah->hargaFormat;

        return response()->json([
            'success' => true,
            'data' => $katalogSampah
        ]);
    }

    /**
     * Mencari item sampah berdasarkan nama.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'keyword' => 'required|string|min:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $bankSampahId = $request->bank_sampah_id;
        $keyword = $request->keyword;

        // Cek apakah nasabah terdaftar di bank sampah ini
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $bankSampahId)
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini'
            ], 422);
        }

        // Cari item sampah berdasarkan keyword
        $katalogSampah = KatalogSampah::with('subKategori.kategoriSampah')
            ->where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true)
            ->where(function($query) use ($keyword) {
                $query->where('nama_item_sampah', 'like', "%{$keyword}%")
                      ->orWhere('deskripsi_item_sampah', 'like', "%{$keyword}%");
            })
            ->get();

        // Format response dengan kategori dan sub kategori
        foreach ($katalogSampah as $item) {
            $item->kategori_utama = $item->kategoriSampahText;
            $item->sub_kategori = $item->subKategoriText;
            $item->harga_format = $item->hargaFormat;
        }

        return response()->json([
            'success' => true,
            'data' => $katalogSampah
        ]);
    }

    /**
     * Mendapatkan item sampah untuk memilih item setoran.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getForSetoran(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'kode_kategori' => 'sometimes|in:kering,basah', // Gunakan kode_kategori dari kategori_sampah
            'sub_kategori_id' => 'sometimes|exists:sub_kategori_sampah,id', // Filter berdasarkan button group UI
            'selected_items' => 'sometimes|array', // ID item yang sudah dipilih
            'setoran_id' => 'sometimes|exists:setoran_sampah,id' // Untuk edit setoran
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $bankSampahId = $request->bank_sampah_id;

        // Cek apakah nasabah terdaftar di bank sampah ini
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $bankSampahId)
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini'
            ], 422);
        }

        // Query katalog sampah dengan relasi sub_kategori
        $query = KatalogSampah::with('subKategori.kategoriSampah')
            ->where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true);

        // Filter berdasarkan kategori utama jika ada
        if ($request->has('kode_kategori')) {
            $query->byKategoriUtama($request->kode_kategori);
        }

        // Filter berdasarkan sub-kategori jika ada
        if ($request->has('sub_kategori_id')) {
            $query->where('sub_kategori_sampah_id', $request->sub_kategori_id);
        }

        $katalogSampah = $query->get();

        // Tandai item yang sudah dipilih
        if ($request->has('selected_items') && is_array($request->selected_items)) {
            foreach ($katalogSampah as $item) {
                $item->is_selected = in_array($item->id, $request->selected_items);
            }
        } elseif ($request->has('setoran_id')) {
            // Jika edit setoran, dapatkan item yang sudah dipilih
            $setoranId = $request->setoran_id;

            // Pastikan setoran ini milik user yang login
            $cekSetoran = \App\Models\SetoranSampah::where('id', $setoranId)
                ->where('user_id', $userId)
                ->first();

            if (!$cekSetoran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setoran tidak ditemukan atau Anda tidak memiliki akses'
                ], 404);
            }

            // Dapatkan ID item yang sudah ada di setoran
            $selectedItemIds = \App\Models\DetailSetoran::where('setoran_sampah_id', $setoranId)
                ->pluck('katalog_sampah_id')
                ->toArray();

            foreach ($katalogSampah as $item) {
                $item->is_selected = in_array($item->id, $selectedItemIds);
            }
        } else {
            foreach ($katalogSampah as $item) {
                $item->is_selected = false;
            }
        }

        // Format data untuk tampilan
        foreach ($katalogSampah as $item) {
            $item->kategori_utama = $item->kategoriSampahText;
            $item->sub_kategori = $item->subKategoriText;
            $item->harga_format = $item->hargaFormat;
            $item->sub_kategori_nama = $item->subKategori ? $item->subKategori->nama_sub_kategori : '';
            $item->gambar_url = $item->gambar_item_sampah ? url('storage/' . $item->gambar_item_sampah) : null;
        }

        // Ambil informasi bank sampah untuk tampilan
        $bankSampah = BankSampah::find($bankSampahId);

        // Kelompokkan katalog berdasarkan sub kategori
        $katalogBySubKategori = $katalogSampah->groupBy('sub_kategori_sampah_id');

        // Ambil daftar sub kategori untuk button group filter UI
        $subKategoriList = [];
        if ($request->has('kode_kategori')) {
            $kategori = KategoriSampah::where('kode_kategori', $request->kode_kategori)->first();
            if ($kategori) {
                $subKategoriList = SubKategoriSampah::where('bank_sampah_id', $bankSampahId)
                    ->where('kategori_sampah_id', $kategori->id)
                    ->where('status_aktif', true)
                    ->ordered()
                    ->get();

                // Tambahkan jumlah item per sub-kategori
                foreach ($subKategoriList as $subKategori) {
                    $subKategori->jumlah_item = isset($katalogBySubKategori[$subKategori->id]) ?
                        $katalogBySubKategori[$subKategori->id]->count() : 0;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bank_sampah' => [
                    'id' => $bankSampah->id,
                    'nama' => $bankSampah->nama_bank_sampah,
                    'alamat' => $bankSampah->alamat_bank_sampah,
                    'tanggal_setoran' => $bankSampah->tanggal_setoran,
                    'status_operasional' => $bankSampah->status_operasional ? 'Aktif' : 'Tutup',
                    'nomor_telepon' => $bankSampah->nomor_telepon_publik
                ],
                'katalog_sampah' => $katalogSampah,
                'sub_kategori' => $subKategoriList
            ]
        ]);
    }

    /**
     * Mendapatkan daftar item sampah dalam sub-kategori tertentu (untuk button group filter).
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getBySubKategori(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'sub_kategori_id' => 'required|exists:sub_kategori_sampah,id',
            'selected_items' => 'sometimes|array', // ID item yang sudah dipilih
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $bankSampahId = $request->bank_sampah_id;
        $subKategoriId = $request->sub_kategori_id;

        // Cek apakah nasabah terdaftar di bank sampah ini
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $bankSampahId)
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini'
            ], 422);
        }

        // Dapatkan sub-kategori
        $subKategori = SubKategoriSampah::find($subKategoriId);
        if (!$subKategori || $subKategori->bank_sampah_id != $bankSampahId) {
            return response()->json([
                'success' => false,
                'message' => 'Sub-kategori tidak ditemukan atau tidak terdaftar di bank sampah ini'
            ], 404);
        }

        // Ambil katalog sampah berdasarkan sub-kategori
        $katalogSampah = KatalogSampah::with('subKategori.kategoriSampah')
            ->where('bank_sampah_id', $bankSampahId)
            ->where('sub_kategori_sampah_id', $subKategoriId)
            ->where('status_aktif', true)
            ->get();

        // Tandai item yang sudah dipilih
        if ($request->has('selected_items') && is_array($request->selected_items)) {
            foreach ($katalogSampah as $item) {
                $item->is_selected = in_array($item->id, $request->selected_items);
            }
        } else {
            foreach ($katalogSampah as $item) {
                $item->is_selected = false;
            }
        }

        // Format data untuk tampilan
        foreach ($katalogSampah as $item) {
            $item->kategori_utama = $item->kategoriSampahText;
            $item->sub_kategori = $item->subKategoriText;
            $item->harga_format = $item->hargaFormat;
            $item->gambar_url = $item->gambar_item_sampah ? url('storage/' . $item->gambar_item_sampah) : null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sub_kategori' => [
                    'id' => $subKategori->id,
                    'nama' => $subKategori->nama_sub_kategori,
                    'kode' => $subKategori->kode_sub_kategori,
                    'warna' => $subKategori->warna,
                    'kategori_id' => $subKategori->kategori_sampah_id
                ],
                'katalog_sampah' => $katalogSampah,
                'total_items' => $katalogSampah->count()
            ]
        ]);
    }

    /**
     * Mendapatkan semua sub-kategori dan jumlah itemnya untuk suatu kategori.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getSubKategoriByKategori(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'kode_kategori' => 'required|in:kering,basah'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $bankSampahId = $request->bank_sampah_id;
        $kodeKategori = $request->kode_kategori;

        // Cek apakah nasabah terdaftar di bank sampah ini
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $bankSampahId)
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini'
            ], 422);
        }

        // Dapatkan ID kategori dari kode kategori
        $kategori = KategoriSampah::where('kode_kategori', $kodeKategori)->first();
        if (!$kategori) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori sampah tidak valid'
            ], 422);
        }

        // Ambil semua sub-kategori yang aktif
        $subKategoriList = SubKategoriSampah::where('bank_sampah_id', $bankSampahId)
            ->where('kategori_sampah_id', $kategori->id)
            ->where('status_aktif', true)
            ->ordered()
            ->get();

        // Hitung jumlah item di setiap sub-kategori
        foreach ($subKategoriList as $subKategori) {
            $subKategori->jumlah_item = KatalogSampah::where('bank_sampah_id', $bankSampahId)
                ->where('sub_kategori_sampah_id', $subKategori->id)
                ->where('status_aktif', true)
                ->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kategori' => [
                    'id' => $kategori->id,
                    'nama' => $kategori->nama_kategori,
                    'kode' => $kategori->kode_kategori
                ],
                'sub_kategori' => $subKategoriList
            ]
        ]);
    }
}