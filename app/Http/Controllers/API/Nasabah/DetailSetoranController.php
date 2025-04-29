<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\DetailSetoran;
use App\Models\SetoranSampah;
use App\Models\KatalogSampah;
use App\Models\SubKategoriSampah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DetailSetoranController extends Controller
{
    // Status constants - mengikuti konstanta di SetoranSampahController
    const STATUS_PENGAJUAN = 'pengajuan';
    const STATUS_DIPROSES = 'diproses';
    const STATUS_SELESAI = 'selesai';
    const STATUS_BATAL = 'batal';

    /**
     * Menambahkan item ke setoran sampah.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'setoran_sampah_id' => 'required|exists:setoran_sampah,id',
            'katalog_sampah_id' => 'required|exists:katalog_sampah,id',
            'berat' => 'sometimes|numeric|min:0.01', // berat bisa kosong pada awal pengajuan
            'foto' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $setoranId = $request->setoran_sampah_id;
        $katalogSampahId = $request->katalog_sampah_id;

        // Cek apakah setoran ini milik user yang login
        $setoran = SetoranSampah::where('id', $setoranId)
            ->where('user_id', $userId)
            ->first();

        if (!$setoran) {
            return response()->json([
                'success' => false,
                'message' => 'Setoran tidak ditemukan atau Anda tidak memiliki akses'
            ], 404);
        }

        // Cek apakah setoran masih dalam status pengajuan
        if ($setoran->status !== self::STATUS_PENGAJUAN) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya setoran dengan status pengajuan yang dapat dimodifikasi'
            ], 422);
        }

        // Cek apakah katalog sampah dari bank sampah yang sama
        $katalogSampah = KatalogSampah::where('id', $katalogSampahId)
            ->where('bank_sampah_id', $setoran->bank_sampah_id)
            ->first();

        if (!$katalogSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Item sampah tidak valid atau tidak terdaftar di bank sampah ini'
            ], 422);
        }

        // Cek apakah item ini sudah ada di setoran
        $existingDetail = DetailSetoran::where('setoran_sampah_id', $setoranId)
            ->where('katalog_sampah_id', $katalogSampahId)
            ->first();

        if ($existingDetail) {
            return response()->json([
                'success' => false,
                'message' => 'Item ini sudah ditambahkan ke setoran ini'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Buat detail setoran baru
            $detailSetoran = new DetailSetoran();
            $detailSetoran->setoran_sampah_id = $setoranId;
            $detailSetoran->katalog_sampah_id = $katalogSampahId;
            $detailSetoran->berat = $request->berat ?? 0; // Bisa 0 jika hanya pengajuan
            $detailSetoran->harga_per_kg = $katalogSampah->harga_per_kg;
            $detailSetoran->nilai = ($request->berat ?? 0) * $katalogSampah->harga_per_kg;

            // Proses foto jika ada
            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('setoran_sampah', 'public');
                $detailSetoran->foto = $path;
            }

            $detailSetoran->save();

            // Update total berat dan nilai setoran
            $totalBerat = DetailSetoran::where('setoran_sampah_id', $setoranId)->sum('berat');
            $totalNilai = DetailSetoran::where('setoran_sampah_id', $setoranId)->sum('nilai');

            $setoran->total_berat = $totalBerat;
            $setoran->total_nilai = $totalNilai;
            $setoran->save();

            DB::commit();

            // Load relasi katalog sampah untuk detail respons
            $detailSetoran->load('katalogSampah.subKategori.kategoriSampah');

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil ditambahkan ke setoran',
                'data' => [
                    'detail_setoran' => $detailSetoran,
                    'total_berat' => $totalBerat,
                    'total_berat_format' => number_format($totalBerat, 2, ',', '.') . ' kg',
                    'total_nilai' => $totalNilai,
                    'total_nilai_format' => 'Rp ' . number_format($totalNilai, 0, ',', '.')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan item ke setoran: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengupdate detail setoran.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'berat' => 'required|numeric|min:0.01',
            'foto' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $detailSetoran = DetailSetoran::find($id);

        if (!$detailSetoran) {
            return response()->json([
                'success' => false,
                'message' => 'Detail setoran tidak ditemukan'
            ], 404);
        }

        // Cek apakah setoran ini milik user yang login
        $setoran = SetoranSampah::where('id', $detailSetoran->setoran_sampah_id)
            ->where('user_id', $userId)
            ->first();

        if (!$setoran) {
            return response()->json([
                'success' => false,
                'message' => 'Setoran tidak ditemukan atau Anda tidak memiliki akses'
            ], 404);
        }

        // Cek apakah setoran masih dalam status pengajuan
        if ($setoran->status !== self::STATUS_PENGAJUAN) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya setoran dengan status pengajuan yang dapat dimodifikasi'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Update detail setoran
            $oldBerat = $detailSetoran->berat;
            $detailSetoran->berat = $request->berat;
            $detailSetoran->nilai = $request->berat * $detailSetoran->harga_per_kg;

            // Proses foto jika ada
            if ($request->hasFile('foto')) {
                // Hapus foto lama jika ada
                if ($detailSetoran->foto) {
                    Storage::disk('public')->delete($detailSetoran->foto);
                }

                $path = $request->file('foto')->store('setoran_sampah', 'public');
                $detailSetoran->foto = $path;
            }

            $detailSetoran->save();

            // Update total berat dan nilai setoran
            $totalBerat = DetailSetoran::where('setoran_sampah_id', $setoran->id)->sum('berat');
            $totalNilai = DetailSetoran::where('setoran_sampah_id', $setoran->id)->sum('nilai');

            $setoran->total_berat = $totalBerat;
            $setoran->total_nilai = $totalNilai;
            $setoran->save();

            DB::commit();

            // Load relasi katalog sampah untuk detail respons
            $detailSetoran->load('katalogSampah.subKategori');

            return response()->json([
                'success' => true,
                'message' => 'Detail setoran berhasil diupdate',
                'data' => [
                    'detail_setoran' => $detailSetoran,
                    'total_berat' => $totalBerat,
                    'total_berat_format' => number_format($totalBerat, 2, ',', '.') . ' kg',
                    'total_nilai' => $totalNilai,
                    'total_nilai_format' => 'Rp ' . number_format($totalNilai, 0, ',', '.')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate detail setoran: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menghapus item dari setoran.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $userId = Auth::id();
        $detailSetoran = DetailSetoran::find($id);

        if (!$detailSetoran) {
            return response()->json([
                'success' => false,
                'message' => 'Detail setoran tidak ditemukan'
            ], 404);
        }

        // Cek apakah setoran ini milik user yang login
        $setoran = SetoranSampah::where('id', $detailSetoran->setoran_sampah_id)
            ->where('user_id', $userId)
            ->first();

        if (!$setoran) {
            return response()->json([
                'success' => false,
                'message' => 'Setoran tidak ditemukan atau Anda tidak memiliki akses'
            ], 404);
        }

        // Cek apakah setoran masih dalam status pengajuan
        if ($setoran->status !== self::STATUS_PENGAJUAN) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya setoran dengan status pengajuan yang dapat dimodifikasi'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Hapus foto jika ada
            if ($detailSetoran->foto) {
                Storage::disk('public')->delete($detailSetoran->foto);
            }

            // Hapus detail setoran
            $detailSetoran->delete();

            // Update total berat dan nilai setoran
            $totalBerat = DetailSetoran::where('setoran_sampah_id', $setoran->id)->sum('berat');
            $totalNilai = DetailSetoran::where('setoran_sampah_id', $setoran->id)->sum('nilai');

            $setoran->total_berat = $totalBerat;
            $setoran->total_nilai = $totalNilai;
            $setoran->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil dihapus dari setoran',
                'data' => [
                    'total_berat' => $totalBerat,
                    'total_berat_format' => number_format($totalBerat, 2, ',', '.') . ' kg',
                    'total_nilai' => $totalNilai,
                    'total_nilai_format' => 'Rp ' . number_format($totalNilai, 0, ',', '.')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus item dari setoran: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan list detail setoran.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getBySetoran(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'setoran_sampah_id' => 'required|exists:setoran_sampah,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $setoranId = $request->setoran_sampah_id;

        // Cek apakah setoran ini milik user yang login
        $setoran = SetoranSampah::where('id', $setoranId)
            ->where('user_id', $userId)
            ->first();

        if (!$setoran) {
            return response()->json([
                'success' => false,
                'message' => 'Setoran tidak ditemukan atau Anda tidak memiliki akses'
            ], 404);
        }

        // Ambil detail setoran dengan relasi katalog sampah dan sub kategori
        $detailSetoran = DetailSetoran::where('setoran_sampah_id', $setoranId)
            ->with(['katalogSampah.subKategori.kategoriSampah'])
            ->get();

        // Format harga dan nilai untuk tampilan
        foreach ($detailSetoran as $detail) {
            $detail->harga_format = 'Rp ' . number_format($detail->harga_per_kg, 0, ',', '.');
            $detail->nilai_format = 'Rp ' . number_format($detail->nilai, 0, ',', '.');
            $detail->berat_format = number_format($detail->berat, 2, ',', '.') . ' kg';
            $detail->kategori_utama = $detail->katalogSampah->kategoriSampahText ?? '';
            $detail->sub_kategori = $detail->katalogSampah->subKategoriText ?? '';

            // Add foto URL if exists
            if ($detail->foto) {
                $detail->foto_url = url('storage/' . $detail->foto);
            }
        }

        // Kelompokkan item berdasarkan sub-kategori untuk tampilan yang lebih terorganisir
        $detailBySubKategori = [];
        foreach ($detailSetoran as $detail) {
            $subKategoriId = $detail->katalogSampah->sub_kategori_sampah_id ?? 'none';
            $subKategoriNama = $detail->katalogSampah->subKategori ?
                $detail->katalogSampah->subKategori->nama_sub_kategori : 'Lainnya';

            if (!isset($detailBySubKategori[$subKategoriId])) {
                $detailBySubKategori[$subKategoriId] = [
                    'id' => $subKategoriId,
                    'nama' => $subKategoriNama,
                    'warna' => $detail->katalogSampah->subKategori ?
                        $detail->katalogSampah->subKategori->warna : '#cccccc',
                    'items' => []
                ];
            }

            $detailBySubKategori[$subKategoriId]['items'][] = $detail;
        }

        // Konversi ke array untuk response JSON
        $groupedDetails = array_values($detailBySubKategori);

        return response()->json([
            'success' => true,
            'data' => [
                'detail_setoran' => $detailSetoran, // Format original
                'detail_by_sub_kategori' => $groupedDetails, // Grouped by sub-kategori
                'setoran' => [
                    'id' => $setoran->id,
                    'kode_setoran' => $setoran->kode_setoran,
                    'status' => $setoran->status,
                    'tanggal_setoran' => $setoran->tanggal_setoran,
                    'waktu_setoran' => $setoran->waktu_setoran,
                    'total_berat' => $setoran->total_berat,
                    'total_berat_format' => number_format($setoran->total_berat, 2, ',', '.') . ' kg',
                    'total_nilai' => $setoran->total_nilai,
                    'total_nilai_format' => 'Rp ' . number_format($setoran->total_nilai, 0, ',', '.'),
                    'editable' => $setoran->status === self::STATUS_PENGAJUAN
                ]
            ]
        ]);
    }

    /**
     * Mendapatkan detail item sampah dengan data lengkap.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function getItemDetail($id)
    {
        $detailSetoran = DetailSetoran::with(['katalogSampah.subKategori.kategoriSampah'])
            ->find($id);

        if (!$detailSetoran) {
            return response()->json([
                'success' => false,
                'message' => 'Detail setoran tidak ditemukan'
            ], 404);
        }

        $userId = Auth::id();
        $setoran = SetoranSampah::where('id', $detailSetoran->setoran_sampah_id)
            ->where('user_id', $userId)
            ->first();

        if (!$setoran) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke data ini'
            ], 403);
        }

        // Format detail untuk tampilan
        $detailSetoran->harga_format = 'Rp ' . number_format($detailSetoran->harga_per_kg, 0, ',', '.');
        $detailSetoran->nilai_format = 'Rp ' . number_format($detailSetoran->nilai, 0, ',', '.');
        $detailSetoran->berat_format = number_format($detailSetoran->berat, 2, ',', '.') . ' kg';

        if ($detailSetoran->foto) {
            $detailSetoran->foto_url = url('storage/' . $detailSetoran->foto);
        }

        $katalogSampah = $detailSetoran->katalogSampah;
        $subKategori = $katalogSampah->subKategori;

        return response()->json([
            'success' => true,
            'data' => [
                'detail_setoran' => $detailSetoran,
                'item_sampah' => [
                    'id' => $katalogSampah->id,
                    'nama' => $katalogSampah->nama_item_sampah,
                    'kategori_utama' => $katalogSampah->kategoriSampahText,
                    'sub_kategori' => $katalogSampah->subKategoriText,
                    'deskripsi' => $katalogSampah->deskripsi_item_sampah,
                    'cara_pemilahan' => $katalogSampah->cara_pemilahan,
                    'cara_pengemasan' => $katalogSampah->cara_pengemasan,
                    'gambar_url' => $katalogSampah->gambar_item_sampah ?
                        url('storage/' . $katalogSampah->gambar_item_sampah) : null
                ]
            ]
        ]);
    }

    /**
     * Update beberapa item setoran sekaligus.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'setoran_sampah_id' => 'required|exists:setoran_sampah,id',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:detail_setoran,id',
            'items.*.berat' => 'required|numeric|min:0.01'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $setoranId = $request->setoran_sampah_id;

        // Cek apakah setoran ini milik user yang login
        $setoran = SetoranSampah::where('id', $setoranId)
            ->where('user_id', $userId)
            ->first();

        if (!$setoran) {
            return response()->json([
                'success' => false,
                'message' => 'Setoran tidak ditemukan atau Anda tidak memiliki akses'
            ], 404);
        }

        // Cek apakah setoran masih dalam status pengajuan
        if ($setoran->status !== self::STATUS_PENGAJUAN) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya setoran dengan status pengajuan yang dapat dimodifikasi'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $updatedItems = [];

            foreach ($request->items as $item) {
                $detailSetoran = DetailSetoran::find($item['id']);

                // Verifikasi bahwa detail setoran ini milik setoran yang sedang diupdate
                if ($detailSetoran->setoran_sampah_id != $setoranId) {
                    throw new \Exception("Detail setoran tidak valid");
                }

                $detailSetoran->berat = $item['berat'];
                $detailSetoran->nilai = $item['berat'] * $detailSetoran->harga_per_kg;
                $detailSetoran->save();

                $updatedItems[] = $detailSetoran;
            }

            // Update total berat dan nilai setoran
            $totalBerat = DetailSetoran::where('setoran_sampah_id', $setoranId)->sum('berat');
            $totalNilai = DetailSetoran::where('setoran_sampah_id', $setoranId)->sum('nilai');

            $setoran->total_berat = $totalBerat;
            $setoran->total_nilai = $totalNilai;
            $setoran->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item setoran berhasil diupdate',
                'data' => [
                    'updated_items' => $updatedItems,
                    'total_berat' => $totalBerat,
                    'total_berat_format' => number_format($totalBerat, 2, ',', '.') . ' kg',
                    'total_nilai' => $totalNilai,
                    'total_nilai_format' => 'Rp ' . number_format($totalNilai, 0, ',', '.')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate item setoran: ' . $e->getMessage()
            ], 500);
        }
    }
}